<?php

declare(strict_types=1);

namespace App\Services\Payments;

interface PaymentProviderInterface
{
    public function requestPayment(string $phone, float $amount, string $description, string $idempotencyKey): array;

    public function checkStatus(string $reference): array;

    public function normalizeStatus(array $gatewayPayload): string;
}

