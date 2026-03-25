<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Model;

final class MessageService extends Model
{
    public function __construct(private readonly MatchService $matchService = new MatchService())
    {
        parent::__construct();
    }

    public function getOrCreateConversation(int $userOneId, int $userTwoId): int
    {
        [$a, $b] = $userOneId < $userTwoId ? [$userOneId, $userTwoId] : [$userTwoId, $userOneId];
        $stmt = $this->db->prepare('SELECT id FROM conversations WHERE user_one_id=:a AND user_two_id=:b LIMIT 1');
        $stmt->execute([':a' => $a, ':b' => $b]);
        $conversation = $stmt->fetch();
        if ($conversation) {
            return (int) $conversation['id'];
        }

        $this->db->prepare('INSERT INTO conversations (user_one_id,user_two_id,created_at,updated_at) VALUES (:a,:b,NOW(),NOW())')->execute([':a' => $a, ':b' => $b]);
        return (int) $this->db->lastInsertId();
    }

    public function sendMessage(int $senderId, int $receiverId, string $messageText, string $messageType = 'text'): int
    {
        if (!$this->userCanMessage($senderId, $receiverId)) {
            return 0;
        }

        $conversationId = $this->getOrCreateConversation($senderId, $receiverId);
        $this->db->prepare('INSERT INTO messages (conversation_id,sender_id,receiver_id,message_text,message_type,is_read,created_at) VALUES (:conversation_id,:sender_id,:receiver_id,:message_text,:message_type,0,NOW())')->execute([
            ':conversation_id' => $conversationId,
            ':sender_id' => $senderId,
            ':receiver_id' => $receiverId,
            ':message_text' => $messageText,
            ':message_type' => $messageType,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getConversationMessages(int $conversationId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM messages WHERE conversation_id = :id ORDER BY created_at ASC');
        $stmt->execute([':id' => $conversationId]);
        return $stmt->fetchAll();
    }

    public function markAsRead(int $conversationId, int $readerId): void
    {
        $this->db->prepare('UPDATE messages SET is_read = 1 WHERE conversation_id = :conversation_id AND receiver_id = :reader_id AND is_read = 0')->execute([':conversation_id' => $conversationId, ':reader_id' => $readerId]);
    }

    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS total FROM messages WHERE receiver_id = :id AND is_read = 0');
        $stmt->execute([':id' => $userId]);
        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    public function userCanMessage(int $senderId, int $receiverId): bool
    {
        $chatOnlyAfterMatch = filter_var((string) Config::env('ALLOW_CHAT_ONLY_AFTER_MATCH', 'true'), FILTER_VALIDATE_BOOLEAN);
        if (!$chatOnlyAfterMatch) {
            return true;
        }

        return $this->matchService->hasActiveMatch($senderId, $receiverId);
    }
}
