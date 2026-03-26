<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Model;

final class BoostService extends Model
{
    public function activateBoost(int $userId, int $paymentId): int
    {
        $hours = (int) Config::env('BOOST_DURATION_HOURS', 24);
        $this->execute('INSERT INTO user_boosts (user_id,payment_id,starts_at,ends_at,status,created_at) VALUES (:u,:p,NOW(),DATE_ADD(NOW(), INTERVAL :h HOUR),:s,NOW())', [
            ':u' => $userId,
            ':p' => $paymentId,
            ':h' => $hours,
            ':s' => 'active',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function expireBoosts(): int
    {
        $stmt = $this->db->prepare("UPDATE user_boosts SET status='expired' WHERE status='active' AND ends_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function isUserBoosted(int $userId): bool
    {
        return (bool) $this->fetchOne("SELECT id FROM user_boosts WHERE user_id=:id AND status='active' AND ends_at > NOW()", [':id' => $userId]);
    }

    public function getBoostPriorityMultiplier(int $userId): float
    {
        return $this->isUserBoosted($userId) ? 1.5 : 1.0;
    }
}
