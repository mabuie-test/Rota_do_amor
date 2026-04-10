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
        $normalized = (string) ($status['normalized_status'] ?? 'pending');

        if ($normalized === 'completed') {
            $this->payments->markPaymentCompleted($paymentId, $status);
            $this->payments->applyBenefitForPaymentType($paymentType, $userId, $paymentId);
            return 'completed';
        }

        if (in_array($normalized, ['failed', 'cancelled'], true)) {
            $this->payments->markPaymentFailed($paymentId, $status);
            return $normalized;
        }

        $this->payments->saveGatewayRawResponse($paymentId, $status);
        return 'pending';
    }
}

