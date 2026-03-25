<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Model;
use DateTimeImmutable;
use RuntimeException;

final class PaymentService extends Model
{
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
        $url = rtrim((string) Config::env('DEBITO_BASE_URL'), '/') . '/transactions/' . rawurlencode($debitoReference) . '/status';
        return $this->http('GET', $url, null);
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
        $stmt = $this->db->prepare('INSERT INTO payments (user_id,payment_type,phone,amount,currency,status,debito_reference,gateway_raw_response,created_at,updated_at) VALUES (:user_id,:payment_type,:phone,:amount,:currency,:status,:debito_reference,:raw,NOW(),NOW())');
        $stmt->execute([
            ':user_id' => $userId,
            ':payment_type' => $type,
            ':phone' => $phone,
            ':amount' => $amount,
            ':currency' => 'MZN',
            ':status' => 'pending',
            ':debito_reference' => $gatewayResponse['reference'] ?? null,
            ':raw' => json_encode($gatewayResponse, JSON_THROW_ON_ERROR),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function markPaymentCompleted(int $paymentId, array $statusPayload): void
    {
        $stmt = $this->db->prepare('UPDATE payments SET status = :status, paid_at = NOW(), gateway_raw_response = :raw, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':status' => 'completed',
            ':raw' => json_encode($statusPayload, JSON_THROW_ON_ERROR),
            ':id' => $paymentId,
        ]);
    }

    public function markPaymentFailed(int $paymentId, array $statusPayload): void
    {
        $stmt = $this->db->prepare('UPDATE payments SET status = :status, gateway_raw_response = :raw, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            ':status' => 'failed',
            ':raw' => json_encode($statusPayload, JSON_THROW_ON_ERROR),
            ':id' => $paymentId,
        ]);
    }

    public function syncUserStatusFromPayment(int $userId, string $paymentType): void
    {
        if ($paymentType !== 'activation') {
            return;
        }

        $stmt = $this->db->prepare("UPDATE users SET activation_paid_at = NOW(), status = CASE WHEN email_verified_at IS NOT NULL THEN 'active' ELSE 'pending_activation' END, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $userId]);
    }

    public function syncSubscriptionFromPayment(int $userId): void
    {
        $days = (int) Config::env('SUBSCRIPTION_DURATION_DAYS', 30);
        $stmt = $this->db->prepare('INSERT INTO subscriptions (user_id,status,starts_at,ends_at,created_at,updated_at) VALUES (:user_id,:status,NOW(),DATE_ADD(NOW(), INTERVAL :days DAY),NOW(),NOW())');
        $stmt->execute([':user_id' => $userId, ':status' => 'active', ':days' => $days]);
    }

    public function syncBoostFromPayment(int $userId, int $paymentId): void
    {
        $hours = (int) Config::env('BOOST_DURATION_HOURS', 24);
        $stmt = $this->db->prepare('INSERT INTO user_boosts (user_id,payment_id,status,starts_at,ends_at,created_at) VALUES (:user_id,:payment_id,:status,NOW(),DATE_ADD(NOW(), INTERVAL :hours HOUR),NOW())');
        $stmt->execute([':user_id' => $userId, ':payment_id' => $paymentId, ':status' => 'active', ':hours' => $hours]);
    }

    public function saveGatewayRawResponse(int $paymentId, array $response): void
    {
        $stmt = $this->db->prepare('UPDATE payments SET gateway_raw_response = :raw, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $paymentId, ':raw' => json_encode($response, JSON_THROW_ON_ERROR)]);
    }

    private function initiatePayment(int $userId, string $phone, string $type, float $amount, string $description): array
    {
        $normalized = $this->normalizePhone($phone);
        if (!$this->validateMozambiqueMpesaNumber($normalized)) {
            throw new RuntimeException('Numero M-Pesa invalido para Mocambique.');
        }

        $body = [
            'msisdn' => $normalized,
            'amount' => $amount,
            'reference_description' => sprintf('%s #%d', $description, $userId),
        ];

        $url = rtrim((string) Config::env('DEBITO_BASE_URL'), '/') . '/wallets/' . Config::env('DEBITO_WALLET_ID') . '/c2b/mpesa';
        $gatewayResponse = $this->http('POST', $url, $body);
        $paymentId = $this->createPendingPayment($userId, $type, $normalized, $amount, $gatewayResponse);

        return [
            'payment_id' => $paymentId,
            'gateway' => $gatewayResponse,
            'requested_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    private function http(string $method, string $url, ?array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . Config::env('DEBITO_TOKEN', ''),
                'Content-Type: application/json',
            ],
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
        }

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $error !== '') {
            throw new RuntimeException('Falha ao comunicar com Débito API: ' . $error);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida da Débito API.');
        }

        $decoded['_http_status'] = $code;
        return $decoded;
    }
}
