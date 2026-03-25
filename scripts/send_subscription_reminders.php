<?php
require __DIR__ . '/bootstrap.php';

use App\Services\MailService;

$db = App\Core\Database::connection();
$mail = new MailService();
$stmt = $db->query("SELECT u.id,u.email,DATEDIFF(MAX(s.ends_at), NOW()) AS days_remaining FROM subscriptions s JOIN users u ON u.id=s.user_id WHERE s.status='active' GROUP BY u.id,u.email HAVING days_remaining BETWEEN 1 AND 3");
foreach ($stmt->fetchAll() as $row) {
    $mail->sendSubscriptionExpiringSoonEmail((int) $row['id'], (string) $row['email'], (int) $row['days_remaining']);
}
echo "Subscription reminders sent\n";
