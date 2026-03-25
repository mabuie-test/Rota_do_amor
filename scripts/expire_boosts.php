<?php
require __DIR__ . '/bootstrap.php';

$db = App\Core\Database::connection();
$ids = $db->query("SELECT DISTINCT user_id FROM user_boosts WHERE status='active' AND ends_at < NOW()")->fetchAll();
$stmt = $db->exec("UPDATE user_boosts SET status='expired' WHERE status='active' AND ends_at < NOW()");
$badge = new App\Services\BadgeService();
foreach ($ids as $row) {
    $badge->syncSystemBadges((int) $row['user_id']);
}
echo 'Expired boosts: ' . $stmt . PHP_EOL;
