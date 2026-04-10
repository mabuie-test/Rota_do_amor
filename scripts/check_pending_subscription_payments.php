<?php
require __DIR__ . '/bootstrap.php';

use App\Services\PaymentReconciliationService;

$db = App\Core\Database::connection();
$service = new PaymentReconciliationService();
$stmt = $db->query("SELECT id,user_id,debito_reference FROM payments WHERE payment_type='subscription' AND status='pending'");
foreach ($stmt->fetchAll() as $payment) {
    if (empty($payment['debito_reference'])) {
        continue;
    }

    $service->reconcileByReference((int) $payment['id'], (int) $payment['user_id'], 'subscription', (string) $payment['debito_reference']);
}
echo "Subscription payments checked\n";
