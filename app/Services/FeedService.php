<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use Throwable;

final class FeedService extends Model
{
    private const POST_MOODS = ['disponivel_para_conversar', 'energia_leve', 'romantico_hoje', 'mente_aberta', 'quero_algo_serio', 'so_a_observar'];
    private const RELATIONAL_PHASES = ['recomeco', 'amizade', 'namoro', 'casamento', 'cura_emocional', 'explorar_possibilidades'];
    private const POST_ORIGINS = ['normal', 'prompt_guided', 'poll', 'diary_shared', 'match_collab', 'story_shared'];

    public function __construct(
        private readonly UploadService $uploads = new UploadService(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly FeedReactionService $reactions = new FeedReactionService(),
        private readonly FeedPromptService $prompts = new FeedPromptService(),
        private readonly FeedPollService $polls = new FeedPollService(),
        private readonly FeedRankingService $ranking = new FeedRankingService(),
        private readonly PostPrivateInterestService $privateInterests = new PostPrivateInterestService(),
        private readonly UserSocialAvailabilityService $availability = new UserSocialAvailabilityService()
    ) {
        parent::__construct();
    }

    public function createPost(int $userId, string $content, array $images = [], array $meta = []): int
    {
        $normalized = trim($content);
        $promptAnswer = trim((string) ($meta['prompt_answer_text'] ?? ''));
        $isPoll = is_array($meta['poll'] ?? null);

        if ($userId <= 0 || ($normalized === '' && $images === [] && $promptAnswer === '' && !$isPoll) || mb_strlen($normalized) > 2000) {
            return 0;
        }

        if ($normalized !== '' && mb_strlen($normalized) < 3 && $images === [] && $promptAnswer === '') {
            return 0;
        }

        if ($normalized !== '' && $this->looksLikeSpam($normalized)) {
            return 0;
        }

        $postMood = $this->normalizeAllowed((string) ($meta['post_mood'] ?? ''), self::POST_MOODS);
        $phase = $this->normalizeAllowed((string) ($meta['relational_phase'] ?? ''), self::RELATIONAL_PHASES);
        $origin = $this->normalizeAllowed((string) ($meta['origin_type'] ?? ''), self::POST_ORIGINS) ?? 'normal';

        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT INTO posts (user_id,content,status,post_mood,relational_phase,origin_type,created_at,updated_at) VALUES (:user_id,:content,:status,:post_mood,:relational_phase,:origin_type,NOW(),NOW())')->execute([
                ':user_id' => $userId,
                ':content' => $normalized,
                ':status' => 'active',
                ':post_mood' => $postMood,
                ':relational_phase' => $phase,
                ':origin_type' => $origin,
            ]);
            $postId = (int) $this->db->lastInsertId();

            foreach ($images as $position => $image) {
                $path = trim((string) ($image['path'] ?? ''));
                if ($path === '') {
                    throw new \RuntimeException('Imagem inválida no payload do post.');
                }

                $this->db->prepare('INSERT INTO post_images (post_id,image_path,thumbnail_path,mime_type,file_size,sort_order,created_by_user_id,created_at) VALUES (:post_id,:path,:thumbnail,:mime,:size,:sort_order,:user_id,NOW())')->execute([
                    ':post_id' => $postId,
                    ':path' => $path,
                    ':thumbnail' => $image['thumbnail_path'] ?? null,
                    ':mime' => $image['mime'] ?? null,
                    ':size' => (int) ($image['size'] ?? 0),
                    ':sort_order' => $position + 1,
                    ':user_id' => $userId,
                ]);
            }

            $promptId = (int) ($meta['prompt_id'] ?? 0);
            if ($promptId > 0 && $promptAnswer !== '') {
                if (!$this->prompts->attachPromptAnswer($postId, $promptId, $promptAnswer)) {
                    throw new \RuntimeException('Prompt inválido para publicação guiada.');
                }
                $this->execute("UPDATE posts SET origin_type='prompt_guided' WHERE id=:id", [':id' => $postId]);
            }

            $pollPayload = is_array($meta['poll'] ?? null) ? $meta['poll'] : [];
            if ($pollPayload !== []) {
                $okPoll = $this->polls->createPollForPost($postId, (string) ($pollPayload['question'] ?? ''), (array) ($pollPayload['options'] ?? []), $pollPayload['ends_at'] ?? null);
                if (!$okPoll) {
                    throw new \RuntimeException('Dados da enquete inválidos.');
                }
                $this->execute("UPDATE posts SET origin_type='poll' WHERE id=:id", [':id' => $postId]);
            }

            $diaryEntryId = (int) ($meta['diary_entry_id'] ?? 0);
            if ($diaryEntryId > 0) {
                $this->execute('INSERT INTO post_diary_shares (post_id,diary_entry_id,share_mode,is_anonymous,created_at) VALUES (:post_id,:diary_entry_id,:share_mode,:is_anonymous,NOW())', [
                    ':post_id' => $postId,
                    ':diary_entry_id' => $diaryEntryId,
                    ':share_mode' => $this->normalizeAllowed((string) ($meta['diary_share_mode'] ?? ''), ['publico', 'so_matches', 'so_interessados', 'anonimo']) ?? 'publico',
                    ':is_anonymous' => (int) ((bool) ($meta['diary_is_anonymous'] ?? false)),
                ]);
                $this->execute("UPDATE posts SET origin_type='diary_shared' WHERE id=:id", [':id' => $postId]);
            }

            $this->db->commit();
            return $postId;
        } catch (Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 0;
        }
    }

    public function deletePost(int $postId, int $userId): void
    {
        $this->db->beginTransaction();
        try {
            $post = $this->fetchOne('SELECT id FROM posts WHERE id=:id AND user_id=:user_id AND status <> :status LIMIT 1 FOR UPDATE', [':id' => $postId, ':user_id' => $userId, ':status' => 'deleted']);
            if ($post === null) {
                $this->db->rollBack();
                return;
            }

            $this->db->prepare("UPDATE posts SET status='deleted', updated_at=NOW() WHERE id=:id AND user_id=:user_id")->execute([':id' => $postId, ':user_id' => $userId]);
            $this->db->commit();
        } catch (Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
        }
    }

    public function toggleLikePost(int $postId, int $userId): array
    {
        if ($postId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Post inválido.', 'action' => 'error', 'error_code' => 'invalid_post'];
        }

        $post = $this->fetchOne('SELECT p.id, p.user_id FROM posts p WHERE p.id=:post_id AND p.status=:status LIMIT 1', [':post_id' => $postId, ':status' => 'active']) ?: [];

        if ($post === []) {
            return ['success' => false, 'message' => 'Post inválido.', 'action' => 'error', 'error_code' => 'invalid_post'];
        }

        $existing = $this->fetchOne('SELECT id FROM post_likes WHERE post_id=:post_id AND user_id=:user_id LIMIT 1', [':post_id' => $postId, ':user_id' => $userId]) ?: [];

        if ($existing !== []) {
            $this->execute('DELETE FROM post_likes WHERE id=:id', [':id' => (int) $existing['id']]);

            return [
                'success' => true,
                'message' => 'Like removido.',
                'action' => 'unliked',
                'post_id' => $postId,
                'liked_by_viewer' => 0,
                'likes_count' => $this->countLikes($postId),
            ];
        }

        $this->execute('INSERT INTO post_likes (post_id,user_id,created_at) VALUES (:post_id,:user_id,NOW())', [':post_id' => $postId, ':user_id' => $userId]);

        $ownerId = (int) ($post['user_id'] ?? 0);
        if ($ownerId > 0 && $ownerId !== $userId) {
            $actor = $this->fetchOne('SELECT CONCAT(first_name, " ", last_name) AS actor_name FROM users WHERE id=:id LIMIT 1', [':id' => $userId]) ?: [];
            $this->notifications->create(
                $ownerId,
                'feed_like_received',
                'Novo like na tua publicação',
                sprintf('%s gostou da tua publicação.', (string) ($actor['actor_name'] ?? 'Alguém')),
                ['post_id' => $postId, 'actor_user_id' => $userId]
            );
        }

        return [
            'success' => true,
            'message' => 'Like registado.',
            'action' => 'liked',
            'post_id' => $postId,
            'liked_by_viewer' => 1,
            'likes_count' => $this->countLikes($postId),
        ];
    }

    public function toggleReaction(int $postId, int $userId, string $reactionType): array
    {
        return $this->reactions->toggleReaction($postId, $userId, $reactionType);
    }

    public function votePoll(int $pollId, int $optionId, int $userId): array
    {
        return $this->polls->vote($pollId, $optionId, $userId);
    }


    public function pollState(int $pollId, int $viewerId): array
    {
        return $this->polls->pollState($pollId, $viewerId);
    }

    public function sendPrivateInterest(int $postId, int $senderId, string $interestType, ?string $message = null): array
    {
        return $this->privateInterests->send($postId, $senderId, $interestType, $message);
    }

    public function activateSocialAvailability(int $userId, string $type, int $durationMinutes = 180): bool
    {
        return $this->availability->activate($userId, $type, $durationMinutes);
    }

    public function getFeedPrompts(): array
    {
        return $this->prompts->listActivePrompts();
    }

    public function commentPost(int $postId, int $userId, string $comment, int $parentCommentId = 0): array
    {
        $normalized = trim($comment);
        if ($postId <= 0 || $userId <= 0) {
            return ['success' => false, 'message' => 'Post inválido.', 'action' => 'error', 'error_code' => 'invalid_post'];
        }

        $post = $this->fetchOne('SELECT p.id, p.user_id FROM posts p WHERE p.id=:id AND p.status=:status LIMIT 1', [':id' => $postId, ':status' => 'active']) ?: [];
        if ($post === []) {
            return ['success' => false, 'message' => 'Post inválido.', 'action' => 'error', 'error_code' => 'invalid_post'];
        }

        if ($normalized === '') {
            return ['success' => false, 'message' => 'Comentário vazio.', 'action' => 'validation_error', 'error_code' => 'empty_comment'];
        }

        if (mb_strlen($normalized) < 2) {
            return ['success' => false, 'message' => 'Comentário demasiado curto.', 'action' => 'validation_error', 'error_code' => 'short_comment'];
        }

        if (mb_strlen($normalized) > 600) {
            return ['success' => false, 'message' => 'Comentário demasiado longo.', 'action' => 'validation_error', 'error_code' => 'long_comment'];
        }

        $parentId = null;
        if ($parentCommentId > 0) {
            $parent = $this->fetchOne('SELECT id,parent_comment_id,user_id FROM post_comments WHERE id=:id AND post_id=:post_id LIMIT 1', [':id' => $parentCommentId, ':post_id' => $postId]) ?: [];

            if ($parent === []) {
                return ['success' => false, 'message' => 'Comentário pai inválido.', 'action' => 'validation_error', 'error_code' => 'invalid_parent'];
            }

            if ((int) ($parent['parent_comment_id'] ?? 0) > 0) {
                return ['success' => false, 'message' => 'Não é permitido responder além de 1 nível.', 'action' => 'validation_error', 'error_code' => 'reply_depth_exceeded'];
            }

            $parentId = (int) $parent['id'];
        }

        $stmt = $this->db->prepare('INSERT INTO post_comments (post_id,user_id,parent_comment_id,comment_text,created_at) VALUES (:post_id,:user_id,:parent_comment_id,:comment,NOW())');
        $ok = $stmt->execute([':post_id' => $postId, ':user_id' => $userId, ':parent_comment_id' => $parentId, ':comment' => $normalized]);

        if (!$ok || $stmt->rowCount() < 1) {
            return ['success' => false, 'message' => 'Não foi possível gravar comentário.', 'action' => 'error'];
        }

        $createdCommentId = (int) $this->db->lastInsertId();

        $actor = $this->fetchOne('SELECT CONCAT(first_name, " ", last_name) AS actor_name FROM users WHERE id=:id LIMIT 1', [':id' => $userId]) ?: [];
        $ownerId = (int) ($post['user_id'] ?? 0);
        if ($ownerId > 0 && $ownerId !== $userId) {
            $this->notifications->create(
                $ownerId,
                'feed_comment_received',
                'Novo comentário na tua publicação',
                sprintf('%s comentou a tua publicação.', (string) ($actor['actor_name'] ?? 'Alguém')),
                ['post_id' => $postId, 'comment_id' => $createdCommentId, 'actor_user_id' => $userId]
            );
        }

        return ['success' => true, 'message' => $parentId === null ? 'Comentário enviado.' : 'Resposta enviada.', 'action' => $parentId === null ? 'commented' : 'replied', 'created_id' => $createdCommentId, 'target_id' => $parentId, 'post_id' => $postId];
    }

    public function getFeedForUser(int $userId, int $page = 1, int $perPage = 20, int $selectedPostId = 0, int $selectedCommentId = 0, bool $expandSelected = false, string $tab = 'for_you'): array
    {
        $page = max(1, $page);
        $perPage = min(50, max(5, $perPage));
        $offset = ($page - 1) * $perPage;
        $tab = $this->ranking->normalizeTab($tab);

        $countRow = $this->fetchOne("SELECT COUNT(*) AS total FROM posts WHERE status='active'") ?: ['total' => 0];
        $total = (int) ($countRow['total'] ?? 0);

        $conditions = ["p.status='active'"];
        if ($tab === FeedRankingService::TAB_NEARBY) {
            $conditions[] = '(u.city_id = vu.city_id OR u.province_id = vu.province_id)';
        } elseif ($tab === FeedRankingService::TAB_SAME_INTENTION) {
            $conditions[] = 'viewer_mode.current_intention IS NOT NULL AND author_mode.current_intention = viewer_mode.current_intention';
        }

        $where = implode(' AND ', $conditions);
        $orderBy = $this->ranking->orderByForTab($tab);

        $sql = "SELECT p.id, p.user_id, p.content, p.status, p.created_at, p.updated_at,
                       p.post_mood, p.relational_phase, p.origin_type,
                       CONCAT(u.first_name, ' ', u.last_name) AS author_name,
                       u.online_status AS author_online,
                       u.profile_photo_path AS author_photo,
                       u.premium_status AS author_premium,
                       u.city_id AS author_city_id,
                       u.province_id AS author_province_id,
                       IFNULL(iv.is_verified, 0) AS author_verified,
                       COALESCE(pl.likes_count, 0) AS likes_count,
                       COALESCE(pc.comments_count, 0) AS comments_count,
                       COALESCE(pi.images_count, 0) AS images_count,
                       pi.first_image_path,
                       pi.first_thumbnail_path,
                       CASE WHEN ul.id IS NULL THEN 0 ELSE 1 END AS liked_by_viewer,
                       COALESCE(cs.score, 0) AS compatibility_score,
                       COALESCE(author_mode.current_intention = viewer_mode.current_intention, 0) AS same_intention,
                       COALESCE(author_mode.relational_pace = viewer_mode.relational_pace, 0) AS same_pace,
                       CASE WHEN u.city_id = vu.city_id THEN 1 ELSE 0 END AS is_same_city,
                       CASE WHEN u.province_id = vu.province_id THEN 1 ELSE 0 END AS is_same_province,
                       (COALESCE(pl.likes_count, 0) + COALESCE(pc.comments_count, 0) + COALESCE(rv.total_votes, 0) + COALESCE(pr.total_reactions, 0) + COALESCE(pi2.private_interest_count, 0)) AS engagement_score,
                       (
                         (COALESCE(cs.score, 0) * 0.45)
                         + (CASE WHEN author_mode.current_intention = viewer_mode.current_intention THEN 18 ELSE 0 END)
                         + (CASE WHEN author_mode.relational_pace = viewer_mode.relational_pace THEN 14 ELSE 0 END)
                         + (CASE WHEN p.post_mood IS NOT NULL AND p.post_mood <> '' THEN 6 ELSE 0 END)
                         + (CASE WHEN iv.is_verified = 1 THEN 8 ELSE 0 END)
                         + (COALESCE(pl.likes_count, 0) * 0.7)
                         + (COALESCE(pc.comments_count, 0) * 1.1)
                       ) AS relational_score,
                       (
                         (CASE WHEN p.post_mood IS NOT NULL AND p.post_mood <> '' THEN 20 ELSE 0 END)
                         + (CASE WHEN author_mode.relational_pace = viewer_mode.relational_pace THEN 16 ELSE 0 END)
                         + (CASE WHEN p.relational_phase IS NOT NULL AND p.relational_phase <> '' THEN 14 ELSE 0 END)
                       ) AS vibe_score
                FROM posts p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN users vu ON vu.id = :viewer
                LEFT JOIN user_connection_modes author_mode ON author_mode.user_id = p.user_id
                LEFT JOIN user_connection_modes viewer_mode ON viewer_mode.user_id = :viewer_mode
                LEFT JOIN compatibility_scores cs ON cs.user_id = :viewer_comp AND cs.target_user_id = p.user_id
                LEFT JOIN (
                    SELECT user_id, MAX(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS is_verified
                    FROM identity_verifications
                    GROUP BY user_id
                ) iv ON iv.user_id = u.id
                LEFT JOIN (SELECT post_id, COUNT(*) AS likes_count FROM post_likes GROUP BY post_id) pl ON pl.post_id = p.id
                LEFT JOIN (SELECT post_id, COUNT(*) AS comments_count FROM post_comments GROUP BY post_id) pc ON pc.post_id = p.id
                LEFT JOIN (SELECT post_id, COUNT(*) AS images_count, MIN(image_path) AS first_image_path, MIN(thumbnail_path) AS first_thumbnail_path FROM post_images GROUP BY post_id) pi ON pi.post_id = p.id
                LEFT JOIN (SELECT poll.post_id, COUNT(v.id) AS total_votes FROM post_polls poll LEFT JOIN post_poll_votes v ON v.poll_id = poll.id GROUP BY poll.post_id) rv ON rv.post_id = p.id
                LEFT JOIN (SELECT post_id, COUNT(*) AS total_reactions FROM post_reactions GROUP BY post_id) pr ON pr.post_id = p.id
                LEFT JOIN (SELECT post_id, COUNT(*) AS private_interest_count FROM post_private_interests GROUP BY post_id) pi2 ON pi2.post_id = p.id
                LEFT JOIN post_likes ul ON ul.post_id = p.id AND ul.user_id = :viewer_like
                WHERE $where
                ORDER BY $orderBy
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':viewer', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':viewer_mode', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':viewer_comp', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':viewer_like', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll();
        $postIds = array_map(static fn(array $row): int => (int) $row['id'], $items);

        if ($selectedPostId > 0 && !in_array($selectedPostId, $postIds, true)) {
            $selectedPost = $this->loadPostById($selectedPostId, $userId);
            if ($selectedPost !== []) {
                array_unshift($items, $selectedPost);
                array_unshift($postIds, $selectedPostId);
            }
        }

        $commentContext = $this->resolveCommentContext($selectedCommentId, $selectedPostId);
        if ($commentContext !== null && !in_array((int) $commentContext['post_id'], $postIds, true)) {
            $commentPost = $this->loadPostById((int) $commentContext['post_id'], $userId);
            if ($commentPost !== []) {
                array_unshift($items, $commentPost);
                array_unshift($postIds, (int) $commentContext['post_id']);
            }
        }

        $images = $this->loadImagesForPosts($postIds);
        $expandedPostIds = [];
        if ($expandSelected && $selectedPostId > 0) {
            $expandedPostIds[] = $selectedPostId;
        }
        if ($commentContext !== null) {
            $expandedPostIds[] = (int) $commentContext['post_id'];
        }
        $expandedPostIds = array_values(array_unique(array_filter($expandedPostIds)));

        $comments = $this->loadTopCommentsForPosts($postIds, $expandedPostIds, $commentContext);
        $reactionMap = $this->reactions->hydrationMap($postIds, $userId);
        $promptMap = $this->prompts->loadAnswersForPosts($postIds);
        $pollMap = $this->polls->loadPollsForPosts($postIds, $userId);
        $privateInterestCounts = $this->privateInterests->aggregateByPosts($postIds);

        $authorIds = array_values(array_unique(array_map(static fn(array $row): int => (int) ($row['user_id'] ?? 0), $items)));
        $availability = $this->availability->loadActiveForUsers($authorIds);

        foreach ($items as &$item) {
            $postId = (int) $item['id'];
            $authorId = (int) ($item['user_id'] ?? 0);
            $item['images'] = $images[$postId] ?? [];
            $item['comments'] = $comments[$postId]['items'] ?? [];
            $item['comments_has_more'] = (bool) ($comments[$postId]['has_more'] ?? false);
            $item['comments_loaded_total'] = (int) ($comments[$postId]['loaded_total'] ?? count($item['comments']));
            $item['comments_expanded'] = in_array($postId, $expandedPostIds, true);
            $item['reactions'] = $reactionMap[$postId]['counts'] ?? array_fill_keys(FeedReactionService::TYPES, 0);
            $item['viewer_reaction'] = $reactionMap[$postId]['viewer_reaction'] ?? null;
            $item['prompt_answer'] = $promptMap[$postId] ?? null;
            $item['poll'] = $pollMap[$postId] ?? null;
            $item['private_interest_count'] = (int) ($privateInterestCounts[$postId] ?? 0);
            $item['author_availability'] = $availability[$authorId] ?? null;
            $item['author_trust_flags'] = [
                'verified' => (int) ($item['author_verified'] ?? 0) === 1,
                'profile_complete' => !empty($item['author_photo']) && (int) ($item['compatibility_score'] ?? 0) >= 0,
                'premium' => in_array((string) ($item['author_premium'] ?? 'basic'), ['premium', 'boosted', 'verified'], true),
            ];

            if ((int) ($item['user_id'] ?? 0) === $userId) {
                $item['owner_interaction_summary'] = $this->buildOwnerInteractionSummary($postId, $userId);
            }
        }
        unset($item);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
            'selected_tab' => $tab,
            'tabs' => [
                ['key' => 'for_you', 'label' => 'Para ti'],
                ['key' => 'trending', 'label' => 'Em alta'],
                ['key' => 'nearby', 'label' => 'Perto de ti'],
                ['key' => 'same_vibe', 'label' => 'Mesma vibe'],
                ['key' => 'same_intention', 'label' => 'Mesma intenção'],
            ],
        ];
    }

    private function buildOwnerInteractionSummary(int $postId, int $ownerId): array
    {
        $row = $this->fetchOne(
            "SELECT
                SUM(CASE WHEN cs.score >= 70 THEN 1 ELSE 0 END) AS compatible_people,
                SUM(CASE WHEN inter.actor_intention IS NOT NULL AND inter.actor_intention = owner_mode.current_intention THEN 1 ELSE 0 END) AS same_intention_people,
                SUM(CASE WHEN inter.actor_city_id = owner.city_id OR inter.actor_province_id = owner.province_id THEN 1 ELSE 0 END) AS nearby_people
             FROM (
                 SELECT pr.user_id AS actor_user_id,
                        ucm.current_intention AS actor_intention,
                        u.city_id AS actor_city_id,
                        u.province_id AS actor_province_id
                 FROM post_reactions pr
                 JOIN users u ON u.id = pr.user_id
                 LEFT JOIN user_connection_modes ucm ON ucm.user_id = pr.user_id
                 WHERE pr.post_id = :post_id
                 UNION
                 SELECT plc.user_id AS actor_user_id,
                        ucm.current_intention AS actor_intention,
                        u.city_id AS actor_city_id,
                        u.province_id AS actor_province_id
                 FROM post_likes plc
                 JOIN users u ON u.id = plc.user_id
                 LEFT JOIN user_connection_modes ucm ON ucm.user_id = plc.user_id
                 WHERE plc.post_id = :post_id_dup
             ) inter
             LEFT JOIN compatibility_scores cs ON cs.user_id = :owner_id AND cs.target_user_id = inter.actor_user_id
             LEFT JOIN user_connection_modes owner_mode ON owner_mode.user_id = :owner_mode
             JOIN users owner ON owner.id = :owner_user",
            [':post_id' => $postId, ':post_id_dup' => $postId, ':owner_id' => $ownerId, ':owner_mode' => $ownerId, ':owner_user' => $ownerId]
        ) ?: [];

        return [
            'compatible_people' => (int) ($row['compatible_people'] ?? 0),
            'same_intention_people' => (int) ($row['same_intention_people'] ?? 0),
            'nearby_people' => (int) ($row['nearby_people'] ?? 0),
        ];
    }

    private function normalizeAllowed(string $value, array $allowed): ?string
    {
        $value = trim($value);
        if ($value === '' || !in_array($value, $allowed, true)) {
            return null;
        }

        return $value;
    }

    public function purgePostMedia(int $postId): int
    {
        $images = $this->fetchAllRows('SELECT image_path,thumbnail_path FROM post_images WHERE post_id=:post_id', [':post_id' => $postId]);
        $this->execute('DELETE FROM post_images WHERE post_id=:post_id', [':post_id' => $postId]);

        foreach ($images as $image) {
            $this->uploads->deleteImageBundle(['path' => $image['image_path'] ?? null, 'thumbnail_path' => $image['thumbnail_path'] ?? null]);
        }

        return count($images);
    }

    private function loadPostById(int $postId, int $viewerId): array
    {
        if ($postId <= 0) {
            return [];
        }

        return $this->fetchOne(
            "SELECT p.id, p.user_id, p.content, p.status, p.created_at, p.updated_at,
                    p.post_mood, p.relational_phase, p.origin_type,
                    CONCAT(u.first_name, ' ', u.last_name) AS author_name,
                    u.online_status AS author_online, u.profile_photo_path AS author_photo,
                    IFNULL(iv.is_verified, 0) AS author_verified,
                    COALESCE(pl.likes_count, 0) AS likes_count,
                    COALESCE(pc.comments_count, 0) AS comments_count,
                    COALESCE(pi.images_count, 0) AS images_count,
                    pi.first_image_path, pi.first_thumbnail_path,
                    CASE WHEN ul.id IS NULL THEN 0 ELSE 1 END AS liked_by_viewer
             FROM posts p
             JOIN users u ON u.id = p.user_id
             LEFT JOIN (SELECT user_id, MAX(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS is_verified FROM identity_verifications GROUP BY user_id) iv ON iv.user_id = u.id
             LEFT JOIN (SELECT post_id, COUNT(*) AS likes_count FROM post_likes GROUP BY post_id) pl ON pl.post_id = p.id
             LEFT JOIN (SELECT post_id, COUNT(*) AS comments_count FROM post_comments GROUP BY post_id) pc ON pc.post_id = p.id
             LEFT JOIN (SELECT post_id, COUNT(*) AS images_count, MIN(image_path) AS first_image_path, MIN(thumbnail_path) AS first_thumbnail_path FROM post_images GROUP BY post_id) pi ON pi.post_id = p.id
             LEFT JOIN post_likes ul ON ul.post_id = p.id AND ul.user_id = :viewer
             WHERE p.id = :id AND p.status='active' LIMIT 1",
            [':id' => $postId, ':viewer' => $viewerId]
        ) ?: [];
    }

    private function resolveCommentContext(int $commentId, int $selectedPostId): ?array
    {
        if ($commentId <= 0) {
            return null;
        }

        $row = $this->fetchOne('SELECT id, post_id, parent_comment_id FROM post_comments WHERE id=:id LIMIT 1', [':id' => $commentId]);
        if (!$row) {
            return null;
        }

        $parentId = (int) ($row['parent_comment_id'] ?? 0);
        return ['comment_id' => (int) ($row['id'] ?? 0), 'post_id' => (int) ($row['post_id'] ?? $selectedPostId), 'parent_comment_id' => $parentId > 0 ? $parentId : null, 'root_comment_id' => $parentId > 0 ? $parentId : (int) ($row['id'] ?? 0), 'is_reply' => $parentId > 0];
    }

    private function loadImagesForPosts(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->db->prepare("SELECT post_id,image_path,thumbnail_path,mime_type,file_size,sort_order FROM post_images WHERE post_id IN ($placeholders) ORDER BY post_id ASC, sort_order ASC, id ASC");
        $stmt->execute(array_values($postIds));

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['post_id']][] = $row;
        }

        return $grouped;
    }

    private function loadTopCommentsForPosts(array $postIds, array $expandedPostIds = [], ?array $commentContext = null): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $sql = "SELECT pc.id, pc.post_id, pc.user_id, pc.parent_comment_id, pc.comment_text, pc.created_at,
                       CONCAT(u.first_name, ' ', u.last_name) AS author_name,
                       COALESCE(rc.reply_count, 0) AS reply_count
                FROM post_comments pc
                JOIN users u ON u.id = pc.user_id
                LEFT JOIN (
                    SELECT parent_comment_id, COUNT(*) AS reply_count
                    FROM post_comments
                    WHERE parent_comment_id IS NOT NULL
                    GROUP BY parent_comment_id
                ) rc ON rc.parent_comment_id = pc.id
                WHERE pc.post_id IN ($placeholders) AND pc.parent_comment_id IS NULL
                ORDER BY pc.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($postIds));
        $parents = $stmt->fetchAll();

        $parentsByPost = [];
        foreach ($parents as $parentRow) {
            $parentsByPost[(int) $parentRow['post_id']][] = $parentRow;
        }

        $parentIds = array_map(static fn(array $row): int => (int) $row['id'], $parents);
        $repliesByParent = $this->loadRepliesByParent($parentIds);

        $targetRootId = (int) ($commentContext['root_comment_id'] ?? 0);
        $results = [];
        foreach ($postIds as $postId) {
            $postRows = $parentsByPost[$postId] ?? [];
            $allItems = [];
            foreach ($postRows as $row) {
                $id = (int) $row['id'];
                $allItems[] = ['id' => $id, 'user_id' => (int) $row['user_id'], 'comment_text' => $row['comment_text'], 'created_at' => $row['created_at'], 'author_name' => $row['author_name'], 'reply_count' => (int) ($row['reply_count'] ?? 0), 'replies' => $repliesByParent[$id] ?? []];
            }

            $isExpanded = in_array((int) $postId, $expandedPostIds, true);
            $shownItems = $isExpanded ? $allItems : array_slice($allItems, 0, 3);
            if (!$isExpanded && $targetRootId > 0) {
                $existsInShown = false;
                foreach ($shownItems as $shown) {
                    if ((int) ($shown['id'] ?? 0) === $targetRootId) {
                        $existsInShown = true;
                        break;
                    }
                }
                if (!$existsInShown) {
                    foreach ($allItems as $candidate) {
                        if ((int) ($candidate['id'] ?? 0) === $targetRootId) {
                            $shownItems[] = $candidate;
                            break;
                        }
                    }
                }
            }

            $results[$postId] = ['items' => $shownItems, 'has_more' => count($allItems) > count($shownItems), 'loaded_total' => count($shownItems)];
        }

        return $results;
    }

    private function loadRepliesByParent(array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
        $stmt = $this->db->prepare("SELECT pc.id, pc.parent_comment_id, pc.user_id, pc.comment_text, pc.created_at, CONCAT(u.first_name, ' ', u.last_name) AS author_name FROM post_comments pc JOIN users u ON u.id = pc.user_id WHERE pc.parent_comment_id IN ($placeholders) ORDER BY pc.id ASC");
        $stmt->execute(array_values($parentIds));

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['parent_comment_id']][] = ['id' => (int) $row['id'], 'user_id' => (int) $row['user_id'], 'comment_text' => $row['comment_text'], 'created_at' => $row['created_at'], 'author_name' => $row['author_name']];
        }

        return $grouped;
    }

    private function countLikes(int $postId): int
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM post_likes WHERE post_id=:post_id', [':post_id' => $postId]);
        return (int) ($row['total'] ?? 0);
    }

    private function looksLikeSpam(string $content): bool
    {
        if (preg_match('/(.)\\1{9,}/u', $content)) {
            return true;
        }

        if (preg_match('/https?:\/\//i', $content) && mb_strlen($content) < 20) {
            return true;
        }

        return false;
    }
}
