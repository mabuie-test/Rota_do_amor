<?php
require __DIR__ . '/bootstrap.php';

use App\Services\PaymentService;

$db = App\Core\Database::connection();
$service = new PaymentService();
$stmt = $db->query("SELECT id,user_id,debito_reference FROM payments WHERE payment_type='subscription' AND status='pending'");
foreach ($stmt->fetchAll() as $payment) {
    $status = $service->checkPaymentStatus((string) $payment['debito_reference']);
    if (($status['status'] ?? '') === 'completed') {
        $service->markPaymentCompleted((int) $payment['id'], $status);
        $service->syncSubscriptionFromPayment((int) $payment['user_id']);
    }
}
echo "Subscription payments checked\n";
