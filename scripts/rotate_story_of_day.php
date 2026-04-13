<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\AuditService;

$db = Database::connection();
$audit = new AuditService();

$db->beginTransaction();
try {
    $db->exec("UPDATE anonymous_stories SET is_story_of_day = 0, updated_at = NOW() WHERE is_story_of_day = 1");

    $stmt = $db->query("SELECT s.id FROM anonymous_stories s
        WHERE s.status IN ('published','featured')
        ORDER BY (SELECT COUNT(*) FROM anonymous_story_reactions r WHERE r.story_id = s.id AND r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) DESC,
                 (SELECT COUNT(*) FROM anonymous_story_comments c WHERE c.story_id = s.id AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) DESC,
                 s.created_at DESC
        LIMIT 1");
    $top = $stmt->fetch();

    if ($top) {
        $storyId = (int) ($top['id'] ?? 0);
        $upd = $db->prepare("UPDATE anonymous_stories SET is_story_of_day = 1, is_featured = 1, status='featured', updated_at = NOW() WHERE id = :id");
        $upd->execute([':id' => $storyId]);
        $audit->logSystemEvent('anonymous_story_story_of_day_rotated', 'anonymous_story', $storyId, ['origin' => 'cron']);
    }

    $db->commit();
    echo "[anonymous_story] story_of_day_rotated\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, '[anonymous_story] failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
