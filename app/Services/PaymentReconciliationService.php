<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

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

    public function pollUntilFinal(
        int $paymentId,
        int $userId,
        string $paymentType,
        string $reference,
        int $maxAttempts = 8,
        int $intervalMs = 1500
    ): array {
        $lastGateway = ['normalized_status' => 'pending'];
        $lastReconciledStatus = 'pending';
        $attempts = max(1, $maxAttempts);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $lastGateway = $this->payments->checkPaymentStatus($reference);
                $lastReconciledStatus = $this->reconcileWithPayload($paymentId, $userId, $paymentType, $lastGateway);
            } catch (RuntimeException $exception) {
                if (!$this->isTransientGatewayFailure($exception)) {
                    throw $exception;
                }

                $lastGateway = [
                    'normalized_status' => 'pending',
                    'status' => 'pending',
                    'message' => 'Gateway indisponível temporariamente durante confirmação.',
                ];
                $lastReconciledStatus = 'pending';
            }

            if (in_array($lastReconciledStatus, ['completed', 'failed', 'cancelled'], true)) {
                return [
                    'status' => $lastReconciledStatus,
                    'gateway' => $lastGateway,
                    'attempts' => $attempt,
                ];
            }

            if ($attempt < $attempts) {
                usleep(max(100, $intervalMs) * 1000);
            }
        }

        return [
            'status' => $lastReconciledStatus,
            'gateway' => $lastGateway,
            'attempts' => $attempts,
        ];
    }

    private function isTransientGatewayFailure(RuntimeException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());
        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'could not resolve host')
            || str_contains($message, 'failed to connect')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'gateway indisponível');
    }
}
