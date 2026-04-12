<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class RiskCenterService extends Model
{
    public function suspiciousUsers(): array
    {
        return $this->fetchAll("SELECT u.id,u.first_name,u.last_name,u.email,u.status,
                        (SELECT COUNT(*) FROM reports r WHERE r.target_user_id=u.id) AS reports_count,
                        (SELECT COUNT(*) FROM blocks b WHERE b.target_user_id=u.id) AS blocked_count,
                        (SELECT COUNT(*) FROM messages m WHERE m.sender_id=u.id AND m.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)) AS messages_24h
                    FROM users u
                    HAVING reports_count >= 2 OR blocked_count >= 3 OR messages_24h >= 100
                    ORDER BY reports_count DESC, blocked_count DESC, messages_24h DESC
                    LIMIT 200");
    }
}
