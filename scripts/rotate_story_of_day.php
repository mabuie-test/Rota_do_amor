<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\AuditService;

try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('This script must run in CLI mode.');
    }

    $db = Database::connection();
    $audit = new AuditService();

    $db->beginTransaction();

    $db->exec("UPDATE anonymous_stories SET is_story_of_day = 0, updated_at = NOW() WHERE is_story_of_day = 1");

    $stmt = $db->query("SELECT s.id FROM anonymous_stories s
        WHERE s.status IN ('published','featured')
        ORDER BY (SELECT COUNT(*) FROM anonymous_story_reactions r WHERE r.story_id = s.id AND r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) DESC,
                 (SELECT COUNT(*) FROM anonymous_story_comments c WHERE c.story_id = s.id AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) DESC,
                 s.created_at DESC
        LIMIT 1");
    $top = $stmt->fetch();

    if (!$top) {
        $db->commit();
        echo "[anonymous_story] no eligible stories found, rotation skipped\n";
        exit(0);
    }

    $storyId = (int) ($top['id'] ?? 0);
    if ($storyId <= 0) {
        throw new RuntimeException('Invalid story id computed for story of the day rotation.');
    }

    $upd = $db->prepare("UPDATE anonymous_stories SET is_story_of_day = 1, is_featured = 1, status='featured', updated_at = NOW() WHERE id = :id");
    $upd->execute([':id' => $storyId]);
    $audit->logSystemEvent('anonymous_story_story_of_day_rotated', 'anonymous_story', $storyId, ['origin' => 'cron']);

    $db->commit();
    echo "[anonymous_story] story_of_day_rotated story_id={$storyId}\n";
    exit(0);
} catch (Throwable $exception) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, '[anonymous_story] failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
