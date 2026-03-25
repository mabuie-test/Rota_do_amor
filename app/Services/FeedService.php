<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class FeedService extends Model
{
    public function createPost(int $userId, string $content): int
    {
        $this->db->prepare('INSERT INTO posts (user_id,content,status,created_at,updated_at) VALUES (:user_id,:content,:status,NOW(),NOW())')->execute([':user_id' => $userId, ':content' => $content, ':status' => 'active']);
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
        $this->db->prepare('INSERT INTO post_comments (post_id,user_id,comment_text,created_at) VALUES (:post_id,:user_id,:comment,NOW())')->execute([':post_id' => $postId, ':user_id' => $userId, ':comment' => $comment]);
    }

    public function getFeedForUser(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT p.* FROM posts p WHERE p.status='active' ORDER BY p.created_at DESC LIMIT 100");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getUserPosts(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM posts WHERE user_id=:user_id AND status='active' ORDER BY created_at DESC");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }
}
