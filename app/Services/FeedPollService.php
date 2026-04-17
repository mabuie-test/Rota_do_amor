<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class FeedPollService extends Model
{
    public function createPollForPost(int $postId, string $question, array $options, ?string $endsAt = null): bool
    {
        $question = trim($question);
        $normalizedOptions = array_values(array_filter(array_map(static fn($v): string => trim((string) $v), $options), static fn(string $v): bool => $v !== ''));

        if ($postId <= 0 || mb_strlen($question) < 4 || mb_strlen($question) > 255 || count($normalizedOptions) < 2 || count($normalizedOptions) > 4) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('INSERT INTO post_polls (post_id,question,status,ends_at,created_at,updated_at) VALUES (:post_id,:question,:status,:ends_at,NOW(),NOW())');
            $stmt->execute([':post_id' => $postId, ':question' => $question, ':status' => 'active', ':ends_at' => $endsAt]);
            $pollId = (int) $this->db->lastInsertId();

            $insertOpt = $this->db->prepare('INSERT INTO post_poll_options (poll_id,option_text,sort_order,created_at) VALUES (:poll_id,:option_text,:sort_order,NOW())');
            foreach ($normalizedOptions as $idx => $option) {
                $insertOpt->execute([':poll_id' => $pollId, ':option_text' => $option, ':sort_order' => $idx + 1]);
            }

            $this->db->commit();
            return true;
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function vote(int $pollId, int $optionId, int $userId): array
    {
        if ($pollId <= 0 || $optionId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Voto inválido.'];
        }

        $poll = $this->fetchOne('SELECT id, post_id FROM post_polls WHERE id=:id AND status=:status AND (ends_at IS NULL OR ends_at > NOW()) LIMIT 1', [':id' => $pollId, ':status' => 'active']);
        if (!$poll) {
            return ['success' => false, 'message' => 'Enquete indisponível.'];
        }

        $option = $this->fetchOne('SELECT id FROM post_poll_options WHERE id=:id AND poll_id=:poll_id LIMIT 1', [':id' => $optionId, ':poll_id' => $pollId]);
        if (!$option) {
            return ['success' => false, 'message' => 'Opção inválida.'];
        }

        $this->execute('INSERT INTO post_poll_votes (poll_id,option_id,user_id,created_at) VALUES (:poll_id,:option_id,:user_id,NOW()) ON DUPLICATE KEY UPDATE option_id=VALUES(option_id), created_at=NOW()', [':poll_id' => $pollId, ':option_id' => $optionId, ':user_id' => $userId]);

        return ['success' => true, 'message' => 'Voto registado.', 'poll' => $this->pollState($pollId, $userId), 'post_id' => (int) ($poll['post_id'] ?? 0)];
    }

    public function pollState(int $pollId, int $viewerId): array
    {
        $poll = $this->fetchOne('SELECT id, post_id, question, status, ends_at FROM post_polls WHERE id=:id LIMIT 1', [':id' => $pollId]) ?: [];
        if ($poll === []) {
            return [];
        }

        $options = $this->fetchAllRows('SELECT o.id, o.option_text, o.sort_order, COUNT(v.id) AS votes FROM post_poll_options o LEFT JOIN post_poll_votes v ON v.option_id=o.id WHERE o.poll_id=:poll_id GROUP BY o.id,o.option_text,o.sort_order ORDER BY o.sort_order ASC, o.id ASC', [':poll_id' => $pollId]);
        $totalVotes = 0;
        foreach ($options as $opt) {
            $totalVotes += (int) ($opt['votes'] ?? 0);
        }

        $viewer = $this->fetchOne('SELECT option_id FROM post_poll_votes WHERE poll_id=:poll_id AND user_id=:user_id LIMIT 1', [':poll_id' => $pollId, ':user_id' => $viewerId]);
        $viewerOptionId = (int) ($viewer['option_id'] ?? 0);

        return [
            'id' => (int) $poll['id'],
            'post_id' => (int) ($poll['post_id'] ?? 0),
            'question' => (string) ($poll['question'] ?? ''),
            'status' => (string) ($poll['status'] ?? 'active'),
            'ends_at' => $poll['ends_at'] ?? null,
            'total_votes' => $totalVotes,
            'viewer_option_id' => $viewerOptionId,
            'options' => array_map(static function (array $row) use ($totalVotes): array {
                $votes = (int) ($row['votes'] ?? 0);
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'option_text' => (string) ($row['option_text'] ?? ''),
                    'votes' => $votes,
                    'percentage' => $totalVotes > 0 ? (int) round(($votes / $totalVotes) * 100) : 0,
                ];
            }, $options),
        ];
    }

    /** @param list<int> $postIds */
    public function loadPollsForPosts(array $postIds, int $viewerId): array
    {
        if ($postIds === []) {
            return [];
        }

        $ph = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->db->prepare("SELECT id FROM post_polls WHERE post_id IN ($ph)");
        $stmt->execute($postIds);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $state = $this->pollState((int) ($row['id'] ?? 0), $viewerId);
            if ($state !== []) {
                $map[(int) ($state['post_id'] ?? 0)] = $state;
            }
        }

        return $map;
    }
}
