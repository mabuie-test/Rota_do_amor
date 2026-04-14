<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class ReportService extends Model
{
    public function createReport(int $reporterId, string $type, string $reason, array $targets = [], ?string $details = null): int
    {
        $type = trim($type);
        $reason = trim($reason);
        $targetUserId = (int) ($targets['target_user_id'] ?? 0);
        $targetPostId = (int) ($targets['target_post_id'] ?? 0);
        $targetMessageId = (int) ($targets['target_message_id'] ?? 0);

        if ($reporterId <= 0 || $type === '' || $reason === '') {
            return 0;
        }

        if ($targetUserId <= 0 && $targetPostId <= 0 && $targetMessageId <= 0) {
            return 0;
        }

        if ($targetUserId > 0 && !$this->targetUserExists($targetUserId)) {
            return 0;
        }

        if ($targetPostId > 0 && !$this->targetPostExists($targetPostId)) {
            return 0;
        }

        if ($targetMessageId > 0 && !$this->targetMessageExists($targetMessageId)) {
            return 0;
        }

        $this->execute('INSERT INTO reports (reporter_user_id,target_user_id,target_post_id,target_message_id,report_type,reason,details,status,created_at) VALUES (:reporter,:target_user,:target_post,:target_message,:type,:reason,:details,:status,NOW())', [
            ':reporter' => $reporterId,
            ':target_user' => $targetUserId > 0 ? $targetUserId : null,
            ':target_post' => $targetPostId > 0 ? $targetPostId : null,
            ':target_message' => $targetMessageId > 0 ? $targetMessageId : null,
            ':type' => $type,
            ':reason' => mb_substr($reason, 0, 120),
            ':details' => $details,
            ':status' => 'pending',
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function targetUserExists(int $userId): bool
    {
        return $this->fetchOne("SELECT id FROM users WHERE id=:id AND status='active' LIMIT 1", [':id' => $userId]) !== null;
    }

    private function targetPostExists(int $postId): bool
    {
        return $this->fetchOne("SELECT id FROM posts WHERE id=:id AND status='active' LIMIT 1", [':id' => $postId]) !== null;
    }

    private function targetMessageExists(int $messageId): bool
    {
        return $this->fetchOne('SELECT id FROM messages WHERE id=:id LIMIT 1', [':id' => $messageId]) !== null;
    }
}
