<?php
require __DIR__ . '/bootstrap.php';

use App\Services\PaymentService;

$db = App\Core\Database::connection();
$service = new PaymentService();
$stmt = $db->query("SELECT id,user_id,debito_reference FROM payments WHERE payment_type='subscription' AND status='pending'");
foreach ($stmt->fetchAll() as $payment) {
    if (empty($payment['debito_reference'])) {
        continue;
    }

    $status = $service->checkPaymentStatus((string) $payment['debito_reference']);
    $gatewayStatus = mb_strtolower((string) ($status['status'] ?? 'pending'));

    if (in_array($gatewayStatus, ['completed', 'success', 'paid'], true)) {
        $service->markPaymentCompleted((int) $payment['id'], $status);
        $service->syncSubscriptionFromPayment((int) $payment['user_id']);
        continue;
    }

    if (in_array($gatewayStatus, ['failed', 'cancelled', 'rejected'], true)) {
        $service->markPaymentFailed((int) $payment['id'], $status);
    }
}
echo "Subscription payments checked\n";
