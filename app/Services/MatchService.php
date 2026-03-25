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
        $stmt = $this->db->prepare('SELECT * FROM matches WHERE status = :status AND (user_one_id = :id OR user_two_id = :id) ORDER BY matched_at DESC');
        $stmt->execute([':status' => 'active', ':id' => $userId]);
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
