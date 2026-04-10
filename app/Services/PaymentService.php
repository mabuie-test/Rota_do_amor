<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Model;
use App\Services\Payments\DebitoEmolaProvider;
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
        private readonly PaymentProviderInterface $mpesaProvider = new DebitoMpesaProvider(),
        private readonly PaymentProviderInterface $emolaProvider = new DebitoEmolaProvider()
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
        $raw = $this->mpesaProvider->checkStatus($debitoReference);
        $raw['normalized_status'] = $this->mpesaProvider->normalizeStatus($raw);
        return $raw;
    }

    public function validateMozambiqueMpesaNumber(string $phone): bool
    {
        return (bool) preg_match('/^258(8[4-7]\d{7})$/', $this->normalizePhone($phone));
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

            $payment = $this->repairPaymentStateIfRecoverable($payment, $paymentType, $userId);

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
                    if ($benefitAppliedAt === null && $benefitStatus === 'applied') {
                        $this->markBenefitApplied($paymentId);
                    }
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
        $stmt = $this->db->prepare("UPDATE payments SET status = :status, paid_at = COALESCE(paid_at, NOW()), gateway_raw_response = :raw, updated_at = NOW() WHERE id = :id AND status = 'pending'");
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
        $stmt = $this->db->prepare("UPDATE payments SET benefit_application_status='applied', benefit_applied_at=COALESCE(benefit_applied_at, NOW()), updated_at=NOW() WHERE id=:id");
        $stmt->execute([':id' => $paymentId]);
    }

    private function markBenefitStatus(int $paymentId, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE payments SET benefit_application_status=:status, updated_at=NOW() WHERE id=:id');
        $stmt->execute([':status' => $status, ':id' => $paymentId]);
    }

    private function repairPaymentStateIfRecoverable(array $payment, string $paymentType, int $userId): array
    {
        $paymentId = (int) ($payment['id'] ?? 0);
        if ($paymentId <= 0) {
            return $payment;
        }

        $status = (string) ($payment['status'] ?? 'pending');
        $benefitStatus = (string) ($payment['benefit_application_status'] ?? 'pending');
        $benefitAppliedAt = $payment['benefit_applied_at'] ?? null;

        if ($status === 'completed' && $benefitStatus === 'applied' && $benefitAppliedAt === null) {
            $this->markBenefitApplied($paymentId);
            $this->financialLog->log('state_repaired', $paymentId, ['reason' => 'applied_without_timestamp']);
            $payment['benefit_applied_at'] = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            $payment['benefit_application_status'] = 'applied';
            return $payment;
        }

        if ($status === 'completed' && in_array($benefitStatus, ['pending', 'failed', 'skipped', 'applying'], true) && $benefitAppliedAt !== null) {
            $this->markBenefitStatus($paymentId, 'applied');
            $this->financialLog->log('state_repaired', $paymentId, ['reason' => 'timestamp_exists_missing_applied_status']);
            $payment['benefit_application_status'] = 'applied';
            return $payment;
        }

        if ($status === 'completed' && in_array($benefitStatus, ['failed', 'skipped', 'pending'], true) && $benefitAppliedAt === null) {
            $benefitCheck = $this->paymentBenefitExists($paymentType, $userId, $paymentId);
            if (($benefitCheck['exists'] ?? false) === true && ($benefitCheck['confidence'] ?? 'none') === 'strong') {
                $this->markBenefitApplied($paymentId);
                $this->financialLog->log('state_repaired', $paymentId, ['reason' => 'legacy_benefit_detected', 'evidence' => $benefitCheck]);
                $payment['benefit_application_status'] = 'applied';
                $payment['benefit_applied_at'] = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            } else {
                $this->financialLog->log('state_repair_rejected', $paymentId, [
                    'reason' => 'recoverable_benefit_missing_or_insufficient_evidence',
                    'from_status' => $benefitStatus,
                    'evidence' => $benefitCheck,
                ]);
                $payment['benefit_application_status'] = 'pending';
            }
        }

        if (in_array($status, ['failed', 'cancelled'], true) && ($benefitAppliedAt !== null || $benefitStatus === 'applied')) {
            $this->financialLog->log('state_repair_rejected', $paymentId, ['reason' => 'failed_or_cancelled_with_benefit_marker', 'status' => $status]);
        }

        return $payment;
    }

    private function detectBenefitEvidence(string $paymentType, int $userId, int $paymentId): array
    {
        return match ($paymentType) {
            'activation' => $this->detectActivationEvidence($userId, $paymentId),
            'subscription' => $this->detectSubscriptionEvidence($userId, $paymentId),
            'boost' => $this->detectBoostEvidence($paymentId),
            default => ['exists' => false, 'strength' => 'none', 'reason' => 'unknown_payment_type'],
        };
    }

    private function paymentBenefitExists(string $paymentType, int $userId, int $paymentId): array
    {
        $evidence = $this->detectBenefitEvidence($paymentType, $userId, $paymentId);
        $strength = (string) ($evidence['strength'] ?? 'none');

        return [
            'exists' => (bool) ($evidence['exists'] ?? false),
            'confidence' => $strength,
            'evidence' => $evidence,
        ];
    }

    private function detectBoostEvidence(int $paymentId): array
    {
        $boost = $this->fetchOne('SELECT id FROM user_boosts WHERE payment_id=:payment_id LIMIT 1', [':payment_id' => $paymentId]);
        return [
            'exists' => $boost !== null,
            'strength' => $boost !== null ? 'strong' : 'none',
            'reason' => $boost !== null ? 'boost_linked_by_payment_id' : 'boost_not_found_by_payment_id',
        ];
    }

    private function detectActivationEvidence(int $userId, int $paymentId): array
    {
        $row = $this->fetchOne('SELECT u.activation_paid_at, p.paid_at, p.created_at FROM users u JOIN payments p ON p.id=:payment_id WHERE u.id=:user_id LIMIT 1', [':user_id' => $userId, ':payment_id' => $paymentId]);
        if ($row === null || empty($row['activation_paid_at'])) {
            return ['exists' => false, 'strength' => 'none', 'reason' => 'activation_marker_missing'];
        }

        if (!empty($row['paid_at']) && abs(strtotime((string) $row['activation_paid_at']) - strtotime((string) $row['paid_at'])) <= 3600) {
            return ['exists' => true, 'strength' => 'strong', 'reason' => 'activation_paid_at_matches_paid_at'];
        }

        return ['exists' => true, 'strength' => 'weak', 'reason' => 'activation_marker_without_time_match'];
    }

    private function detectSubscriptionEvidence(int $userId, int $paymentId): array
    {
        $row = $this->fetchOne('SELECT paid_at, created_at FROM payments WHERE id=:payment_id LIMIT 1', [':payment_id' => $paymentId]) ?: [];
        $anchor = $row['paid_at'] ?? $row['created_at'] ?? null;
        if ($anchor === null) {
            return ['exists' => false, 'strength' => 'none', 'reason' => 'missing_payment_anchor_time'];
        }

        $subscription = $this->fetchOne('SELECT id, created_at FROM subscriptions WHERE user_id=:user_id AND created_at BETWEEN DATE_SUB(:anchor, INTERVAL 10 MINUTE) AND DATE_ADD(:anchor, INTERVAL 10 MINUTE) ORDER BY id DESC LIMIT 1', [':user_id' => $userId, ':anchor' => $anchor]);
        if ($subscription === null) {
            return ['exists' => false, 'strength' => 'none', 'reason' => 'subscription_not_found_in_safe_window'];
        }

        $ambiguousCount = (int) (($this->fetchOne(
            "SELECT COUNT(*) AS total
             FROM payments
             WHERE user_id = :user_id
               AND payment_type = 'subscription'
               AND status = 'completed'
               AND id <> :payment_id
               AND COALESCE(paid_at, created_at) BETWEEN DATE_SUB(:anchor, INTERVAL 20 MINUTE) AND DATE_ADD(:anchor, INTERVAL 20 MINUTE)",
            [':user_id' => $userId, ':payment_id' => $paymentId, ':anchor' => $anchor]
        ) ?: [])['total'] ?? 0);

        if ($ambiguousCount > 0) {
            return ['exists' => true, 'strength' => 'weak', 'reason' => 'ambiguous_subscription_window_multiple_completed_payments'];
        }

        return ['exists' => true, 'strength' => 'strong', 'reason' => 'subscription_window_match_without_ambiguous_completed_payments'];
    }

    private function initiatePayment(int $userId, string $phone, string $type, float $amount, string $description): array
    {
        $normalized = $this->normalizePhone($phone);
        if (!$this->validateMozambiqueMpesaNumber($normalized)) {
            throw new RuntimeException('Número inválido. Use 25884/85XXXXXXX (M-Pesa) ou 25886/87XXXXXXX (e-Mola).');
        }

        $gatewayMsisdn = $this->toGatewayMsisdn($normalized);
        $provider = $this->providerForPhone($gatewayMsisdn);

        $gatewayResponse = $provider->requestPayment(
            $gatewayMsisdn,
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

    private function toGatewayMsisdn(string $normalizedPhone): string
    {
        $local = substr($normalizedPhone, 3);
        if ($local === false || !preg_match('/^8[4-7]\d{7}$/', $local)) {
            throw new RuntimeException('Número inválido para o gateway.');
        }

        return $local;
    }

    private function providerForPhone(string $gatewayMsisdn): PaymentProviderInterface
    {
        if (preg_match('/^8[4-5]\d{7}$/', $gatewayMsisdn) === 1) {
            return $this->mpesaProvider;
        }

        return $this->emolaProvider;
    }
}
