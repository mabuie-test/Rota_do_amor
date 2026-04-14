<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use Throwable;

final class FeedService extends Model
{
    public function __construct(
        private readonly UploadService $uploads = new UploadService(),
        private readonly NotificationService $notifications = new NotificationService()
    )
    {
        parent::__construct();
    }

    public function createPost(int $userId, string $content, array $images = []): int
    {
        $normalized = trim($content);
        if ($userId <= 0 || ($normalized === '' && $images === []) || mb_strlen($normalized) > 2000) {
            return 0;
        }

        if ($normalized !== '' && mb_strlen($normalized) < 3 && $images === []) {
            return 0;
        }

        if ($normalized !== '' && $this->looksLikeSpam($normalized)) {
            return 0;
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT INTO posts (user_id,content,status,created_at,updated_at) VALUES (:user_id,:content,:status,NOW(),NOW())')->execute([
                ':user_id' => $userId,
                ':content' => $normalized,
                ':status' => 'active',
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

    public function likePost(int $postId, int $userId): void
    {
        if ($postId <= 0 || $userId <= 0) {
            return;
        }

        $stmt = $this->db->prepare('INSERT IGNORE INTO post_likes (post_id,user_id,created_at) VALUES (:post_id,:user_id,NOW())');
        $stmt->execute([':post_id' => $postId, ':user_id' => $userId]);
        if ($stmt->rowCount() < 1) {
            return;
        }

        $post = $this->fetchOne('SELECT p.id, p.user_id, CONCAT(u.first_name, " ", u.last_name) AS actor_name FROM posts p JOIN users u ON u.id = :actor_id WHERE p.id=:post_id LIMIT 1', [
            ':post_id' => $postId,
            ':actor_id' => $userId,
        ]) ?: [];

        $ownerId = (int) ($post['user_id'] ?? 0);
        if ($ownerId > 0 && $ownerId !== $userId) {
            $this->notifications->create(
                $ownerId,
                'feed_like_received',
                'Novo like na tua publicação',
                sprintf('%s gostou da tua publicação.', (string) ($post['actor_name'] ?? 'Alguém')),
                ['post_id' => $postId, 'actor_user_id' => $userId]
            );
        }
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

    public function commentPost(int $postId, int $userId, string $comment, int $parentCommentId = 0): void
    {
        $normalized = trim($comment);
        if ($normalized === '' || mb_strlen($normalized) < 2 || mb_strlen($normalized) > 600 || $postId <= 0 || $userId <= 0) {
            return;
        }

        $parentId = null;
        if ($parentCommentId > 0) {
            $parent = $this->fetchOne('SELECT id,parent_comment_id FROM post_comments WHERE id=:id AND post_id=:post_id LIMIT 1', [
                ':id' => $parentCommentId,
                ':post_id' => $postId,
            ]) ?: [];

            if ($parent === [] || (int) ($parent['parent_comment_id'] ?? 0) > 0) {
                return;
            }

            $parentId = (int) $parent['id'];
        }

        $this->db->prepare('INSERT INTO post_comments (post_id,user_id,parent_comment_id,comment_text,created_at) VALUES (:post_id,:user_id,:parent_comment_id,:comment,NOW())')->execute([
            ':post_id' => $postId,
            ':user_id' => $userId,
            ':parent_comment_id' => $parentId,
            ':comment' => $normalized,
        ]);

        $post = $this->fetchOne('SELECT p.user_id, CONCAT(u.first_name, " ", u.last_name) AS actor_name FROM posts p JOIN users u ON u.id=:actor_id WHERE p.id=:post_id LIMIT 1', [
            ':post_id' => $postId,
            ':actor_id' => $userId,
        ]) ?: [];
        $ownerId = (int) ($post['user_id'] ?? 0);
        if ($ownerId > 0 && $ownerId !== $userId) {
            $this->notifications->create(
                $ownerId,
                'feed_comment_received',
                'Novo comentário na tua publicação',
                sprintf('%s comentou a tua publicação.', (string) ($post['actor_name'] ?? 'Alguém')),
                ['post_id' => $postId, 'actor_user_id' => $userId]
            );
        }
    }

    public function getFeedForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(50, max(5, $perPage));
        $offset = ($page - 1) * $perPage;

        $countRow = $this->fetchOne("SELECT COUNT(*) AS total FROM posts WHERE status='active'") ?: ['total' => 0];
        $total = (int) ($countRow['total'] ?? 0);

        $sql = "SELECT p.id,
                       p.user_id,
                       p.content,
                       p.status,
                       p.created_at,
                       p.updated_at,
                       CONCAT(u.first_name, ' ', u.last_name) AS author_name,
                       u.online_status AS author_online,
                       u.profile_photo_path AS author_photo,
                       IFNULL(iv.is_verified, 0) AS author_verified,
                       COALESCE(pl.likes_count, 0) AS likes_count,
                       COALESCE(pc.comments_count, 0) AS comments_count,
                       COALESCE(pi.images_count, 0) AS images_count,
                       pi.first_image_path,
                       pi.first_thumbnail_path,
                       CASE WHEN ul.id IS NULL THEN 0 ELSE 1 END AS liked_by_viewer
                FROM posts p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN (
                    SELECT user_id, MAX(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS is_verified
                    FROM identity_verifications
                    GROUP BY user_id
                ) iv ON iv.user_id = u.id
                LEFT JOIN (
                    SELECT post_id, COUNT(*) AS likes_count
                    FROM post_likes
                    GROUP BY post_id
                ) pl ON pl.post_id = p.id
                LEFT JOIN (
                    SELECT post_id, COUNT(*) AS comments_count
                    FROM post_comments
                    GROUP BY post_id
                ) pc ON pc.post_id = p.id
                LEFT JOIN (
                    SELECT post_id, COUNT(*) AS images_count, MIN(image_path) AS first_image_path, MIN(thumbnail_path) AS first_thumbnail_path
                    FROM post_images
                    GROUP BY post_id
                ) pi ON pi.post_id = p.id
                LEFT JOIN post_likes ul ON ul.post_id = p.id AND ul.user_id = :viewer
                WHERE p.status = 'active'
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':viewer', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll();
        $postIds = array_map(static fn(array $row): int => (int) $row['id'], $items);
        $images = $this->loadImagesForPosts($postIds);
        $comments = $this->loadTopCommentsForPosts($postIds);

        foreach ($items as &$item) {
            $postId = (int) $item['id'];
            $item['images'] = $images[$postId] ?? [];
            $item['comments'] = $comments[$postId] ?? [];
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
        ];
    }

    public function getUserPosts(int $userId, int $limit = 6): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = $this->db->prepare(
            "SELECT p.id, p.user_id, p.content, p.created_at,
                    COALESCE(pl.likes_count, 0) AS likes_count,
                    COALESCE(pc.comments_count, 0) AS comments_count,
                    COALESCE(pi.images_count, 0) AS images_count,
                    pi.first_image_path,
                    pi.first_thumbnail_path
             FROM posts p
             LEFT JOIN (
                SELECT post_id, COUNT(*) AS likes_count FROM post_likes GROUP BY post_id
             ) pl ON pl.post_id = p.id
             LEFT JOIN (
                SELECT post_id, COUNT(*) AS comments_count FROM post_comments GROUP BY post_id
             ) pc ON pc.post_id = p.id
             LEFT JOIN (
                SELECT post_id, COUNT(*) AS images_count, MIN(image_path) AS first_image_path, MIN(thumbnail_path) AS first_thumbnail_path
                FROM post_images GROUP BY post_id
             ) pi ON pi.post_id = p.id
             WHERE p.user_id=:user_id AND p.status='active'
             ORDER BY p.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
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

    private function loadTopCommentsForPosts(array $postIds): array
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
                WHERE pc.post_id IN ($placeholders)
                  AND pc.parent_comment_id IS NULL
                ORDER BY pc.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($postIds));
        $parents = $stmt->fetchAll();

        $parentIds = array_map(static fn(array $row): int => (int) $row['id'], $parents);
        $repliesByParent = $this->loadRepliesByParent($parentIds);

        $grouped = [];
        foreach ($parents as $row) {
            $id = (int) $row['id'];
            $postId = (int) $row['post_id'];
            $grouped[$postId][] = [
                'id' => $id,
                'user_id' => (int) $row['user_id'],
                'comment_text' => $row['comment_text'],
                'created_at' => $row['created_at'],
                'author_name' => $row['author_name'],
                'reply_count' => (int) ($row['reply_count'] ?? 0),
                'replies' => $repliesByParent[$id] ?? [],
            ];
        }

        foreach ($grouped as $postId => $postComments) {
            $grouped[$postId] = array_slice($postComments, 0, 3);
        }

        return $grouped;
    }

    private function loadRepliesByParent(array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
        $stmt = $this->db->prepare("SELECT pc.id, pc.parent_comment_id, pc.user_id, pc.comment_text, pc.created_at,
                                           CONCAT(u.first_name, ' ', u.last_name) AS author_name
                                    FROM post_comments pc
                                    JOIN users u ON u.id = pc.user_id
                                    WHERE pc.parent_comment_id IN ($placeholders)
                                    ORDER BY pc.id ASC");
        $stmt->execute(array_values($parentIds));

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['parent_comment_id']][] = [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'comment_text' => $row['comment_text'],
                'created_at' => $row['created_at'],
                'author_name' => $row['author_name'],
            ];
        }

        return $grouped;
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
