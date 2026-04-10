<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Model;
use App\Services\Payments\DebitoMpesaProvider;
use App\Services\Payments\PaymentProviderInterface;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class PaymentService extends Model
{
    public function __construct(
        private readonly AccountStateService $accountStateService = new AccountStateService(),
        private readonly BadgeService $badgeService = new BadgeService(),
        private readonly FinancialLogService $financialLog = new FinancialLogService(),
        private readonly PaymentProviderInterface $provider = new DebitoMpesaProvider()
    ) {
        parent::__construct();
    }

    public function initiateActivationPayment(int $userId, string $phone): array
    {
        return $this->initiatePayment($userId, $phone, 'activation', (float) Config::env('ACTIVATION_PRICE', 100), 'Activacao Conta Rota do Amor');
    }

    public function initiateSubscriptionPayment(int $userId, string $phone): array
    {
        return $this->initiatePayment($userId, $phone, 'subscription', (float) Config::env('MONTHLY_SUBSCRIPTION_PRICE', 40), 'Renovacao Mensal Rota do Amor');
    }

    public function initiateBoostPayment(int $userId, string $phone): array
    {
        return $this->initiatePayment($userId, $phone, 'boost', (float) Config::env('BOOST_PRICE', 25), 'Boost de Perfil Rota do Amor');
    }

    public function initiatePremiumFeaturePayment(int $userId, string $phone, string $featureType, float $amount): array
    {
        return $this->initiatePayment($userId, $phone, 'premium_feature', $amount, sprintf('Premium %s Rota do Amor', $featureType));
    }

    public function checkPaymentStatus(string $debitoReference): array
    {
        $raw = $this->provider->checkStatus($debitoReference);
        $raw['normalized_status'] = $this->provider->normalizeStatus($raw);
        return $raw;
    }

    public function validateMozambiqueMpesaNumber(string $phone): bool
    {
        return (bool) preg_match('/^2588[4-7]\d{7}$/', $this->normalizePhone($phone));
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '258' . substr($digits, 1);
        }
        if (str_starts_with($digits, '8') && strlen($digits) === 9) {
            $digits = '258' . $digits;
        }

        return $digits;
    }

    public function createPendingPayment(int $userId, string $type, string $phone, float $amount, array $gatewayResponse): int
    {
        $stmt = $this->db->prepare('INSERT INTO payments (user_id,payment_type,phone,amount,currency,status,benefit_application_status,debito_reference,gateway_raw_response,created_at,updated_at) VALUES (:user_id,:payment_type,:phone,:amount,:currency,:status,:benefit_application_status,:debito_reference,:raw,NOW(),NOW())');
        $stmt->execute([
            ':user_id' => $userId,
            ':payment_type' => $type,
            ':phone' => $phone,
            ':amount' => $amount,
            ':currency' => 'MZN',
            ':status' => 'pending',
            ':benefit_application_status' => 'pending',
            ':debito_reference' => $gatewayResponse['reference'] ?? $gatewayResponse['transaction_reference'] ?? null,
            ':raw' => json_encode($gatewayResponse, JSON_THROW_ON_ERROR),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function reconcilePaymentWithIdempotency(int $paymentId, int $userId, string $paymentType, array $statusPayload): string
    {
        $normalized = (string) ($statusPayload['normalized_status'] ?? 'pending');
        $finalStatuses = ['completed', 'failed', 'cancelled'];

        $this->db->beginTransaction();
        try {
            $payment = $this->fetchPaymentForUpdate($paymentId);
            if (!$payment || (int) $payment['user_id'] !== $userId || (string) $payment['payment_type'] !== $paymentType) {
                $this->db->rollBack();
                return 'ignored';
            }

            $currentStatus = (string) ($payment['status'] ?? 'pending');
            $benefitAppliedAt = $payment['benefit_applied_at'] ?? null;
            $benefitStatus = (string) ($payment['benefit_application_status'] ?? 'pending');

            if (in_array($currentStatus, $finalStatuses, true) && $currentStatus !== 'pending' && $normalized !== 'completed') {
                $this->saveGatewayRawResponse($paymentId, $statusPayload);
                $this->financialLog->log('reconciliation_skipped', $paymentId, ['reason' => 'already_finalized', 'status' => $currentStatus]);
                $this->db->commit();
                return $currentStatus;
            }

            if ($normalized === 'completed') {
                if (in_array($currentStatus, ['failed', 'cancelled'], true)) {
                    $this->saveGatewayRawResponse($paymentId, $statusPayload);
                    $this->financialLog->log('reconciliation_skipped', $paymentId, ['reason' => 'cannot_transition_from_failed_state', 'status' => $currentStatus]);
                    $this->db->commit();
                    return $currentStatus;
                }

                if ($currentStatus === 'pending') {
                    $this->markPaymentCompleted($paymentId, $statusPayload);
                } else {
                    $this->saveGatewayRawResponse($paymentId, $statusPayload);
                }

                if ($benefitAppliedAt !== null || $benefitStatus === 'applied') {
                    $this->financialLog->log('benefit_idempotent_skip', $paymentId, ['reason' => 'benefit_already_applied', 'payment_type' => $paymentType]);
                    $this->db->commit();
                    return 'completed';
                }

                $this->markBenefitStatus($paymentId, 'applying');
                $this->applyBenefitForPaymentType($paymentType, $userId, $paymentId);
                $this->markBenefitApplied($paymentId);
                $this->financialLog->log('benefit_applied', $paymentId, ['payment_type' => $paymentType]);
                $this->db->commit();
                return 'completed';
            }

            if (in_array($normalized, ['failed', 'cancelled'], true)) {
                if ($currentStatus === 'pending') {
                    $this->markPaymentFailed($paymentId, $statusPayload);
                    $this->markBenefitStatus($paymentId, 'skipped');
                } else {
                    $this->saveGatewayRawResponse($paymentId, $statusPayload);
                    $this->financialLog->log('reconciliation_skipped', $paymentId, ['reason' => 'already_finalized', 'status' => $currentStatus]);
                }

                $this->db->commit();
                return $normalized;
            }

            $this->saveGatewayRawResponse($paymentId, $statusPayload);
            $this->db->commit();
            return 'pending';
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->markBenefitStatus($paymentId, 'failed');
            $this->financialLog->log('benefit_failed', $paymentId, ['error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function markPaymentCompleted(int $paymentId, array $statusPayload): void
    {
        $stmt = $this->db->prepare("UPDATE payments SET status = :status, paid_at = NOW(), gateway_raw_response = :raw, updated_at = NOW() WHERE id = :id AND status = 'pending'");
        $stmt->execute([
            ':status' => 'completed',
            ':raw' => json_encode($statusPayload, JSON_THROW_ON_ERROR),
            ':id' => $paymentId,
        ]);
        $this->financialLog->log('completed', $paymentId, ['status' => $statusPayload['normalized_status'] ?? $statusPayload['status'] ?? 'completed']);
    }

    public function markPaymentFailed(int $paymentId, array $statusPayload): void
    {
        $stmt = $this->db->prepare("UPDATE payments SET status = :status, gateway_raw_response = :raw, updated_at = NOW() WHERE id = :id AND status = 'pending'");
        $stmt->execute([
            ':status' => in_array(($statusPayload['normalized_status'] ?? ''), ['cancelled'], true) ? 'cancelled' : 'failed',
            ':raw' => json_encode($statusPayload, JSON_THROW_ON_ERROR),
            ':id' => $paymentId,
        ]);
        $this->financialLog->log('failed', $paymentId, ['status' => $statusPayload['normalized_status'] ?? $statusPayload['status'] ?? 'failed']);
    }

    public function syncUserStatusFromPayment(int $userId, string $paymentType): void
    {
        if ($paymentType === 'activation') {
            $stmt = $this->db->prepare('UPDATE users SET activation_paid_at = NOW(), updated_at = NOW() WHERE id = :id');
            $stmt->execute([':id' => $userId]);
        }

        $this->accountStateService->syncUserStatus($userId);
    }

    public function syncSubscriptionFromPayment(int $userId): void
    {
        $days = (int) Config::env('SUBSCRIPTION_DURATION_DAYS', 30);
        $sql = "INSERT INTO subscriptions (user_id,status,starts_at,ends_at,created_at,updated_at)
                VALUES (:user_id,:status,NOW(),DATE_ADD(COALESCE((SELECT MAX(ends_at) FROM subscriptions WHERE user_id=:user_id2 AND ends_at > NOW()), NOW()), INTERVAL :days DAY),NOW(),NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':user_id2' => $userId, ':status' => 'active', ':days' => $days]);

        $this->accountStateService->syncUserStatus($userId);
    }

    public function syncBoostFromPayment(int $userId, int $paymentId): void
    {
        $hours = (int) Config::env('BOOST_DURATION_HOURS', 24);
        $stmt = $this->db->prepare('INSERT INTO user_boosts (user_id,payment_id,status,starts_at,ends_at,created_at) VALUES (:user_id,:payment_id,:status,NOW(),DATE_ADD(NOW(), INTERVAL :hours HOUR),NOW())');
        $stmt->execute([':user_id' => $userId, ':payment_id' => $paymentId, ':status' => 'active', ':hours' => $hours]);

        $this->badgeService->syncSystemBadges($userId);
    }

    public function applyBenefitForPaymentType(string $paymentType, int $userId, int $paymentId): void
    {
        match ($paymentType) {
            'activation' => $this->syncUserStatusFromPayment($userId, 'activation'),
            'subscription' => $this->syncSubscriptionFromPayment($userId),
            'boost' => $this->syncBoostFromPayment($userId, $paymentId),
            default => null,
        };
    }

    public function saveGatewayRawResponse(int $paymentId, array $response): void
    {
        $stmt = $this->db->prepare('UPDATE payments SET gateway_raw_response = :raw, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $paymentId, ':raw' => json_encode($response, JSON_THROW_ON_ERROR)]);
    }

    private function fetchPaymentForUpdate(int $paymentId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $paymentId]);
        return $stmt->fetch() ?: [];
    }

    private function markBenefitApplied(int $paymentId): void
    {
        $stmt = $this->db->prepare("UPDATE payments SET benefit_application_status='applied', benefit_applied_at=NOW(), updated_at=NOW() WHERE id=:id");
        $stmt->execute([':id' => $paymentId]);
    }

    private function markBenefitStatus(int $paymentId, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE payments SET benefit_application_status=:status, updated_at=NOW() WHERE id=:id');
        $stmt->execute([':status' => $status, ':id' => $paymentId]);
    }

    private function initiatePayment(int $userId, string $phone, string $type, float $amount, string $description): array
    {
        $normalized = $this->normalizePhone($phone);
        if (!$this->validateMozambiqueMpesaNumber($normalized)) {
            throw new RuntimeException('Numero M-Pesa invalido para Mocambique.');
        }

        $gatewayResponse = $this->provider->requestPayment(
            $normalized,
            $amount,
            sprintf('%s #%d', $description, $userId),
            hash('sha256', $userId . '|' . $type . '|' . $normalized . '|' . (string) round($amount, 2))
        );
        $paymentId = $this->createPendingPayment($userId, $type, $normalized, $amount, $gatewayResponse);
        $this->financialLog->log('requested', $paymentId, ['type' => $type, 'phone' => $normalized, 'amount' => $amount]);

        return [
            'payment_id' => $paymentId,
            'gateway' => $gatewayResponse,
            'requested_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
