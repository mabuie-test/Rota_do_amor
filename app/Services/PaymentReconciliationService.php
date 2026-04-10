<?php

declare(strict_types=1);

namespace App\Services;

final class PaymentReconciliationService
{
    public function __construct(private readonly PaymentService $payments = new PaymentService())
    {
    }

    public function reconcileByReference(int $paymentId, int $userId, string $paymentType, string $reference): string
    {
        $status = $this->payments->checkPaymentStatus($reference);
        return $this->payments->reconcilePaymentWithIdempotency($paymentId, $userId, $paymentType, $status);
    }

    public function reconcileWithPayload(int $paymentId, int $userId, string $paymentType, array $gatewayPayload): string
    {
        if (!isset($gatewayPayload['normalized_status'])) {
            $gatewayPayload['normalized_status'] = 'pending';
        }

        return $this->payments->reconcilePaymentWithIdempotency($paymentId, $userId, $paymentType, $gatewayPayload);
    }
}
