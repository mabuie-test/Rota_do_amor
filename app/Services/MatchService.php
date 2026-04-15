<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class MatchService extends Model
{
    public function createMatch(int $userOneId, int $userTwoId, string $source = 'swipe'): int
    {
        [$a, $b] = $userOneId < $userTwoId ? [$userOneId, $userTwoId] : [$userTwoId, $userOneId];
        $stmt = $this->db->prepare('INSERT INTO matches (user_one_id,user_two_id,matched_at,status,created_from,created_at) VALUES (:a,:b,NOW(),:status,:source,NOW()) ON DUPLICATE KEY UPDATE status = VALUES(status), matched_at = NOW()');
        $stmt->execute([':a' => $a, ':b' => $b, ':status' => 'active', ':source' => $source]);
        return (int) $this->db->lastInsertId();
    }

    public function getUserMatches(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.*,
                    CASE WHEN m.user_one_id = :viewer_1 THEN m.user_two_id ELSE m.user_one_id END AS counterpart_id,
                    CASE WHEN m.user_one_id = :viewer_2 THEN CONCAT(u2.first_name, ' ', u2.last_name) ELSE CONCAT(u1.first_name, ' ', u1.last_name) END AS counterpart_name,
                    CASE WHEN m.user_one_id = :viewer_3 THEN u2.profile_photo_path ELSE u1.profile_photo_path END AS counterpart_photo
             FROM matches m
             JOIN users u1 ON u1.id = m.user_one_id
             JOIN users u2 ON u2.id = m.user_two_id
             WHERE m.status = :status
               AND (m.user_one_id = :id_one OR m.user_two_id = :id_two)
             ORDER BY m.matched_at DESC"
        );
        $stmt->execute([
            ':viewer_1' => $userId,
            ':viewer_2' => $userId,
            ':viewer_3' => $userId,
            ':status' => 'active',
            ':id_one' => $userId,
            ':id_two' => $userId,
        ]);
        return $stmt->fetchAll();
    }

    public function hasActiveMatch(int $userId, int $otherUserId): bool
    {
        [$a, $b] = $userId < $otherUserId ? [$userId, $otherUserId] : [$otherUserId, $userId];
        $stmt = $this->db->prepare('SELECT id FROM matches WHERE user_one_id=:a AND user_two_id=:b AND status=:status');
        $stmt->execute([':a' => $a, ':b' => $b, ':status' => 'active']);
        return (bool) $stmt->fetch();
    }

    public function unmatch(int $userId, int $otherUserId): void
    {
        [$a, $b] = $userId < $otherUserId ? [$userId, $otherUserId] : [$otherUserId, $userId];
        $this->db->prepare("UPDATE matches SET status='unmatched' WHERE user_one_id=:a AND user_two_id=:b")->execute([':a' => $a, ':b' => $b]);
    }

    public function rankMatchesByRecentInteraction(array $matches): array
    {
        return $matches;
    }
}
