<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class PostPrivateInterestService extends Model
{
    public const TYPES = [
        'quero_conhecer_te_melhor',
        'gostei_desta_publicacao',
        'achei_tua_vibe_interessante',
    ];

    public function __construct(private readonly NotificationService $notifications = new NotificationService())
    {
        parent::__construct();
    }

    public function send(int $postId, int $senderId, string $interestType, ?string $message = null): array
    {
        $interestType = trim($interestType);
        if ($postId <= 0 || $senderId <= 0 || !in_array($interestType, self::TYPES, true)) {
            return ['success' => false, 'message' => 'Interesse inválido.', 'error_code' => 'invalid_interest'];
        }

        $post = $this->fetchOne('SELECT id, user_id FROM posts WHERE id=:id AND status=:status LIMIT 1', [':id' => $postId, ':status' => 'active']) ?: [];
        if ($post === []) {
            return ['success' => false, 'message' => 'Post indisponível.', 'error_code' => 'invalid_post'];
        }

        $receiverId = (int) ($post['user_id'] ?? 0);
        if ($receiverId <= 0 || $receiverId === $senderId) {
            return ['success' => false, 'message' => 'Não é possível enviar interesse para este post.', 'error_code' => 'invalid_receiver'];
        }

        $safeMessage = trim((string) $message);
        if ($safeMessage !== '' && mb_strlen($safeMessage) > 240) {
            return ['success' => false, 'message' => 'Mensagem opcional excede o limite.', 'error_code' => 'message_too_long'];
        }

        $this->execute('INSERT INTO post_private_interests (post_id,sender_user_id,receiver_user_id,interest_type,message_optional,status,created_at,updated_at) VALUES (:post_id,:sender,:receiver,:interest_type,:message,:status,NOW(),NOW()) ON DUPLICATE KEY UPDATE interest_type=VALUES(interest_type), message_optional=VALUES(message_optional), status=:status2, updated_at=NOW()', [
            ':post_id' => $postId,
            ':sender' => $senderId,
            ':receiver' => $receiverId,
            ':interest_type' => $interestType,
            ':message' => $safeMessage !== '' ? $safeMessage : null,
            ':status' => 'sent',
            ':status2' => 'sent',
        ]);

        $actor = $this->fetchOne('SELECT CONCAT(first_name, " ", last_name) AS actor_name FROM users WHERE id=:id LIMIT 1', [':id' => $senderId]) ?: [];
        $this->notifications->create(
            $receiverId,
            'feed_private_interest_received',
            'Interesse privado recebido',
            sprintf('%s enviou interesse privado no teu post.', (string) ($actor['actor_name'] ?? 'Alguém')),
            ['post_id' => $postId, 'actor_user_id' => $senderId, 'interest_type' => $interestType]
        );

        return ['success' => true, 'message' => 'Interesse privado enviado.', 'post_id' => $postId];
    }

    /** @param list<int> $postIds */
    public function aggregateByPosts(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $ph = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->db->prepare("SELECT post_id, COUNT(*) AS total FROM post_private_interests WHERE post_id IN ($ph) GROUP BY post_id");
        $stmt->execute($postIds);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) ($row['post_id'] ?? 0)] = (int) ($row['total'] ?? 0);
        }
        return $map;
    }
}
