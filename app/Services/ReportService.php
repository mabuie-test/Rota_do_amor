<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class ReportService extends Model
{
    public function createReport(int $reporterId, string $type, string $reason, array $targets = [], ?string $details = null): int
    {
        $this->execute('INSERT INTO reports (reporter_user_id,target_user_id,target_post_id,target_message_id,report_type,reason,details,status,created_at) VALUES (:reporter,:target_user,:target_post,:target_message,:type,:reason,:details,:status,NOW())', [
            ':reporter' => $reporterId,
            ':target_user' => $targets['target_user_id'] ?? null,
            ':target_post' => $targets['target_post_id'] ?? null,
            ':target_message' => $targets['target_message_id'] ?? null,
            ':type' => $type,
            ':reason' => $reason,
            ':details' => $details,
            ':status' => 'pending',
        ]);

        return (int) $this->db->lastInsertId();
    }
}
