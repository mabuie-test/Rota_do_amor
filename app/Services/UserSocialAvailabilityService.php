<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class UserSocialAvailabilityService extends Model
{
    public const TYPES = ['aberto_para_conversar', 'disponivel_hoje', 'energia_social_alta'];

    public function activate(int $userId, string $type, int $durationMinutes = 180): bool
    {
        if ($userId <= 0 || !in_array($type, self::TYPES, true)) {
            return false;
        }

        $durationMinutes = max(30, min(1440, $durationMinutes));
        return $this->execute(
            'INSERT INTO user_social_availability (user_id,availability_type,status,starts_at,ends_at,created_at,updated_at) VALUES (:user_id,:availability_type,:status,NOW(),DATE_ADD(NOW(), INTERVAL :duration MINUTE),NOW(),NOW())',
            [':user_id' => $userId, ':availability_type' => $type, ':status' => 'active', ':duration' => $durationMinutes]
        );
    }

    public function expireElapsed(): void
    {
        $this->execute("UPDATE user_social_availability SET status='expired', updated_at=NOW() WHERE status='active' AND ends_at <= NOW()");
    }

    /** @param list<int> $userIds */
    public function loadActiveForUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $this->expireElapsed();
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->db->prepare("SELECT usa.user_id, usa.availability_type, usa.ends_at FROM user_social_availability usa INNER JOIN (SELECT user_id, MAX(id) AS max_id FROM user_social_availability WHERE status='active' AND ends_at > NOW() GROUP BY user_id) latest ON latest.max_id = usa.id WHERE usa.user_id IN ($ph)");
        $stmt->execute($userIds);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) ($row['user_id'] ?? 0)] = [
                'availability_type' => (string) ($row['availability_type'] ?? ''),
                'ends_at' => $row['ends_at'] ?? null,
            ];
        }

        return $map;
    }
}
