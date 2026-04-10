<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Config;

final class DebitoEmolaProvider implements PaymentProviderInterface
{
    public function __construct(private readonly DebitoClient $client = new DebitoClient())
    {
    }

    public function requestPayment(string $phone, float $amount, string $description, string $idempotencyKey): array
    {
        return $this->client->request('POST', '/wallets/' . Config::env('DEBITO_WALLET_ID') . '/c2b/emola', [
            'msisdn' => $phone,
            'amount' => $amount,
            'reference_description' => $description,
        ], ['Idempotency-Key: ' . $idempotencyKey]);
    }

    public function checkStatus(string $reference): array
    {
        return $this->client->request('GET', '/transactions/' . rawurlencode($reference) . '/status');
    }

    public function normalizeStatus(array $gatewayPayload): string
    {
        $status = mb_strtolower((string) ($gatewayPayload['status'] ?? 'pending'));
        return match (true) {
            in_array($status, ['completed', 'success', 'paid'], true) => 'completed',
            in_array($status, ['failed', 'rejected'], true) => 'failed',
            $status === 'cancelled' => 'cancelled',
            default => 'pending',
        };
    }
}

