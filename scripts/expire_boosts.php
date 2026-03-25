<?php
require __DIR__ . '/bootstrap.php';

$db = App\Core\Database::connection();
$stmt = $db->exec("UPDATE user_boosts SET status='expired' WHERE status='active' AND ends_at < NOW()");
echo 'Expired boosts: ' . $stmt . PHP_EOL;
