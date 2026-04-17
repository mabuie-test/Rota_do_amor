<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class FeedReactionService extends Model
{
    public const TYPES = [
        'gostei_da_energia',
        'identifiquei_me',
        'conversaria_contigo',
        'quero_conhecer',
        'boa_vibe',
    ];

    public function __construct(private readonly NotificationService $notifications = new NotificationService())
    {
        parent::__construct();
    }

    public function toggleReaction(int $postId, int $userId, string $reactionType): array
    {
        $reactionType = trim($reactionType);
        if ($postId <= 0 || $userId <= 0 || !in_array($reactionType, self::TYPES, true)) {
            return ['success' => false, 'message' => 'Reação inválida.', 'error_code' => 'invalid_reaction'];
        }

        $post = $this->fetchOne('SELECT id, user_id FROM posts WHERE id=:id AND status=:status LIMIT 1', [':id' => $postId, ':status' => 'active']) ?: [];
        if ($post === []) {
            return ['success' => false, 'message' => 'Publicação indisponível.', 'error_code' => 'invalid_post'];
        }

        $existing = $this->fetchOne('SELECT id, reaction_type FROM post_reactions WHERE post_id=:post_id AND user_id=:user_id LIMIT 1', [':post_id' => $postId, ':user_id' => $userId]) ?: [];

        if ($existing !== [] && (string) ($existing['reaction_type'] ?? '') === $reactionType) {
            $this->execute('DELETE FROM post_reactions WHERE id=:id', [':id' => (int) $existing['id']]);
            return [
                'success' => true,
                'message' => 'Reação removida.',
                'action' => 'reaction_removed',
                'post_id' => $postId,
                'viewer_reaction' => null,
                'reaction_counts' => $this->reactionCountsForPost($postId),
            ];
        }

        if ($existing !== []) {
            $this->execute('UPDATE post_reactions SET reaction_type=:reaction_type, created_at=NOW() WHERE id=:id', [':reaction_type' => $reactionType, ':id' => (int) $existing['id']]);
            $action = 'reaction_changed';
        } else {
            $this->execute('INSERT INTO post_reactions (post_id,user_id,reaction_type,created_at) VALUES (:post_id,:user_id,:reaction_type,NOW())', [':post_id' => $postId, ':user_id' => $userId, ':reaction_type' => $reactionType]);
            $action = 'reaction_added';
        }

        $ownerId = (int) ($post['user_id'] ?? 0);
        if ($ownerId > 0 && $ownerId !== $userId) {
            $actor = $this->fetchOne('SELECT CONCAT(first_name, " ", last_name) AS actor_name FROM users WHERE id=:id LIMIT 1', [':id' => $userId]) ?: [];
            $this->notifications->create(
                $ownerId,
                'feed_reaction_received',
                'Nova reação intencional',
                sprintf('%s reagiu ao teu post com "%s".', (string) ($actor['actor_name'] ?? 'Alguém'), str_replace('_', ' ', $reactionType)),
                ['post_id' => $postId, 'actor_user_id' => $userId, 'reaction_type' => $reactionType]
            );
        }

        return [
            'success' => true,
            'message' => 'Reação atualizada.',
            'action' => $action,
            'post_id' => $postId,
            'viewer_reaction' => $reactionType,
            'reaction_counts' => $this->reactionCountsForPost($postId),
        ];
    }

    /** @return array<string,int> */
    public function reactionCountsForPost(int $postId): array
    {
        $rows = $this->fetchAllRows('SELECT reaction_type, COUNT(*) AS total FROM post_reactions WHERE post_id=:post_id GROUP BY reaction_type', [':post_id' => $postId]);
        $counts = array_fill_keys(self::TYPES, 0);
        foreach ($rows as $row) {
            $type = (string) ($row['reaction_type'] ?? '');
            if (isset($counts[$type])) {
                $counts[$type] = (int) ($row['total'] ?? 0);
            }
        }

        return $counts;
    }

    /** @param list<int> $postIds */
    public function hydrationMap(array $postIds, int $viewerId): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $rows = $this->db->prepare("SELECT post_id, reaction_type, COUNT(*) AS total FROM post_reactions WHERE post_id IN ($placeholders) GROUP BY post_id, reaction_type");
        $rows->execute($postIds);

        $map = [];
        foreach ($rows->fetchAll() as $row) {
            $postId = (int) ($row['post_id'] ?? 0);
            $type = (string) ($row['reaction_type'] ?? '');
            if (!isset($map[$postId])) {
                $map[$postId] = ['counts' => array_fill_keys(self::TYPES, 0), 'viewer_reaction' => null];
            }
            if (isset($map[$postId]['counts'][$type])) {
                $map[$postId]['counts'][$type] = (int) ($row['total'] ?? 0);
            }
        }

        $viewerStmt = $this->db->prepare("SELECT post_id, reaction_type FROM post_reactions WHERE user_id=? AND post_id IN ($placeholders)");
        $viewerStmt->execute(array_merge([$viewerId], $postIds));
        foreach ($viewerStmt->fetchAll() as $row) {
            $postId = (int) ($row['post_id'] ?? 0);
            if (!isset($map[$postId])) {
                $map[$postId] = ['counts' => array_fill_keys(self::TYPES, 0), 'viewer_reaction' => null];
            }
            $map[$postId]['viewer_reaction'] = (string) ($row['reaction_type'] ?? '');
        }

        return $map;
    }
}
