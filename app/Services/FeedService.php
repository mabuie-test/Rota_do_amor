<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class FeedService extends Model
{
    public function createPost(int $userId, string $content): int
    {
        $normalized = trim($content);
        if ($userId <= 0 || $normalized === '' || mb_strlen($normalized) < 3 || mb_strlen($normalized) > 2000) {
            return 0;
        }

        if ($this->looksLikeSpam($normalized)) {
            return 0;
        }

        $this->db->prepare('INSERT INTO posts (user_id,content,status,created_at,updated_at) VALUES (:user_id,:content,:status,NOW(),NOW())')->execute([':user_id' => $userId, ':content' => $normalized, ':status' => 'active']);
        return (int) $this->db->lastInsertId();
    }

    public function deletePost(int $postId, int $userId): void
    {
        $this->db->prepare("UPDATE posts SET status='deleted', updated_at=NOW() WHERE id=:id AND user_id=:user_id")->execute([':id' => $postId, ':user_id' => $userId]);
    }

    public function likePost(int $postId, int $userId): void
    {
        $this->db->prepare('INSERT IGNORE INTO post_likes (post_id,user_id,created_at) VALUES (:post_id,:user_id,NOW())')->execute([':post_id' => $postId, ':user_id' => $userId]);
    }

    public function commentPost(int $postId, int $userId, string $comment): void
    {
        $normalized = trim($comment);
        if ($normalized === '' || mb_strlen($normalized) < 2 || mb_strlen($normalized) > 600) {
            return;
        }

        $this->db->prepare('INSERT INTO post_comments (post_id,user_id,comment_text,created_at) VALUES (:post_id,:user_id,:comment,NOW())')->execute([':post_id' => $postId, ':user_id' => $userId, ':comment' => $normalized]);
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
                       COALESCE(pl.likes_count, 0) AS likes_count,
                       COALESCE(pc.comments_count, 0) AS comments_count,
                       COALESCE(pi.images_count, 0) AS images_count,
                       pi.first_image_path,
                       CASE WHEN ul.id IS NULL THEN 0 ELSE 1 END AS liked_by_viewer
                FROM posts p
                JOIN users u ON u.id = p.user_id
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
                    SELECT post_id, COUNT(*) AS images_count, MIN(image_path) AS first_image_path
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

        return [
            'items' => $stmt->fetchAll(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    public function getUserPosts(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM posts WHERE user_id=:user_id AND status='active' ORDER BY created_at DESC");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    private function looksLikeSpam(string $content): bool
    {
        if (preg_match('/(.)\\1{9,}/u', $content)) {
            return true;
        }

        if (preg_match('/https?:\\/\\//i', $content) && mb_strlen($content) < 20) {
            return true;
        }

        return false;
    }
}
