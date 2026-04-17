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
        if ($pollId <= 0) {
            return [];
        }

        $byId = $this->loadPollStatesByPollIds([$pollId], $viewerId);
        return $byId[$pollId] ?? [];
    }

    /** @param list<int> $postIds */
    public function loadPollsForPosts(array $postIds, int $viewerId): array
    {
        if ($postIds === []) {
            return [];
        }

        $postIds = array_values(array_unique(array_filter($postIds, static fn(int $id): bool => $id > 0)));
        if ($postIds === []) {
            return [];
        }

        $postPlaceholders = implode(',', array_fill(0, count($postIds), '?'));
        $pollStmt = $this->db->prepare(
            "SELECT id, post_id, question, status, ends_at
             FROM post_polls
             WHERE post_id IN ($postPlaceholders)"
        );
        $pollStmt->execute($postIds);
        $pollRows = $pollStmt->fetchAll();
        if ($pollRows === []) {
            return [];
        }

        $pollIds = array_values(array_unique(array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $pollRows)));
        $pollPlaceholders = implode(',', array_fill(0, count($pollIds), '?'));

        $optionsStmt = $this->db->prepare(
            "SELECT o.id, o.poll_id, o.option_text, o.sort_order, COUNT(v.id) AS votes
             FROM post_poll_options o
             LEFT JOIN post_poll_votes v ON v.option_id = o.id
             WHERE o.poll_id IN ($pollPlaceholders)
             GROUP BY o.id, o.poll_id, o.option_text, o.sort_order
             ORDER BY o.poll_id ASC, o.sort_order ASC, o.id ASC"
        );
        $optionsStmt->execute($pollIds);

        $viewerStmt = $this->db->prepare("SELECT poll_id, option_id FROM post_poll_votes WHERE user_id = ? AND poll_id IN ($pollPlaceholders)");
        $viewerStmt->execute(array_merge([$viewerId], $pollIds));

        $optionsByPoll = [];
        $totalVotesByPoll = [];
        foreach ($optionsStmt->fetchAll() as $row) {
            $pollId = (int) ($row['poll_id'] ?? 0);
            $votes = (int) ($row['votes'] ?? 0);
            $totalVotesByPoll[$pollId] = (int) ($totalVotesByPoll[$pollId] ?? 0) + $votes;
            $optionsByPoll[$pollId][] = [
                'id' => (int) ($row['id'] ?? 0),
                'option_text' => (string) ($row['option_text'] ?? ''),
                'votes' => $votes,
            ];
        }

        $viewerOptionByPoll = [];
        foreach ($viewerStmt->fetchAll() as $row) {
            $viewerOptionByPoll[(int) ($row['poll_id'] ?? 0)] = (int) ($row['option_id'] ?? 0);
        }

        $pollsByPost = [];
        foreach ($pollRows as $pollRow) {
            $pollId = (int) ($pollRow['id'] ?? 0);
            $postId = (int) ($pollRow['post_id'] ?? 0);
            if ($pollId <= 0 || $postId <= 0) {
                continue;
            }

            $totalVotes = (int) ($totalVotesByPoll[$pollId] ?? 0);
            $pollsByPost[$postId] = [
                'id' => $pollId,
                'post_id' => $postId,
                'question' => (string) ($pollRow['question'] ?? ''),
                'status' => (string) ($pollRow['status'] ?? 'active'),
                'ends_at' => $pollRow['ends_at'] ?? null,
                'total_votes' => $totalVotes,
                'viewer_option_id' => (int) ($viewerOptionByPoll[$pollId] ?? 0),
                'options' => array_map(static function (array $option) use ($totalVotes): array {
                    $votes = (int) ($option['votes'] ?? 0);
                    return [
                        'id' => (int) ($option['id'] ?? 0),
                        'option_text' => (string) ($option['option_text'] ?? ''),
                        'votes' => $votes,
                        'percentage' => $totalVotes > 0 ? (int) round(($votes / $totalVotes) * 100) : 0,
                    ];
                }, $optionsByPoll[$pollId] ?? []),
            ];
        }

        return $pollsByPost;
    }

    /** @param list<int> $pollIds */
    private function loadPollStatesByPollIds(array $pollIds, int $viewerId): array
    {
        if ($pollIds === []) {
            return [];
        }

        $pollIds = array_values(array_unique(array_filter($pollIds, static fn(int $id): bool => $id > 0)));
        if ($pollIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pollIds), '?'));

        $pollStmt = $this->db->prepare("SELECT id, post_id, question, status, ends_at FROM post_polls WHERE id IN ($placeholders)");
        $pollStmt->execute($pollIds);
        $pollRows = $pollStmt->fetchAll();
        if ($pollRows === []) {
            return [];
        }

        $optionStmt = $this->db->prepare(
            "SELECT o.id, o.poll_id, o.option_text, o.sort_order, COUNT(v.id) AS votes
             FROM post_poll_options o
             LEFT JOIN post_poll_votes v ON v.option_id = o.id
             WHERE o.poll_id IN ($placeholders)
             GROUP BY o.id, o.poll_id, o.option_text, o.sort_order
             ORDER BY o.poll_id ASC, o.sort_order ASC, o.id ASC"
        );
        $optionStmt->execute($pollIds);

        $viewerParams = $pollIds;
        $viewerParams[] = $viewerId;
        $viewerStmt = $this->db->prepare("SELECT poll_id, option_id FROM post_poll_votes WHERE poll_id IN ($placeholders) AND user_id = ?");
        $viewerStmt->execute($viewerParams);

        $optionsByPoll = [];
        $totalVotesByPoll = [];
        foreach ($optionStmt->fetchAll() as $row) {
            $pollId = (int) ($row['poll_id'] ?? 0);
            $votes = (int) ($row['votes'] ?? 0);
            $totalVotesByPoll[$pollId] = (int) ($totalVotesByPoll[$pollId] ?? 0) + $votes;
            $optionsByPoll[$pollId][] = [
                'id' => (int) ($row['id'] ?? 0),
                'option_text' => (string) ($row['option_text'] ?? ''),
                'votes' => $votes,
            ];
        }

        $viewerOptionByPoll = [];
        foreach ($viewerStmt->fetchAll() as $row) {
            $viewerOptionByPoll[(int) ($row['poll_id'] ?? 0)] = (int) ($row['option_id'] ?? 0);
        }

        $states = [];
        foreach ($pollRows as $poll) {
            $pollId = (int) ($poll['id'] ?? 0);
            $totalVotes = (int) ($totalVotesByPoll[$pollId] ?? 0);
            $options = array_map(static function (array $row) use ($totalVotes): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'option_text' => (string) ($row['option_text'] ?? ''),
                    'votes' => (int) ($row['votes'] ?? 0),
                    'percentage' => $totalVotes > 0 ? (int) round((((int) ($row['votes'] ?? 0)) / $totalVotes) * 100) : 0,
                ];
            }, $optionsByPoll[$pollId] ?? []);

            $states[$pollId] = [
                'id' => $pollId,
                'post_id' => (int) ($poll['post_id'] ?? 0),
                'question' => (string) ($poll['question'] ?? ''),
                'status' => (string) ($poll['status'] ?? 'active'),
                'ends_at' => $poll['ends_at'] ?? null,
                'total_votes' => $totalVotes,
                'viewer_option_id' => (int) ($viewerOptionByPoll[$pollId] ?? 0),
                'options' => $options,
            ];
        }

        return $states;
    }
}
