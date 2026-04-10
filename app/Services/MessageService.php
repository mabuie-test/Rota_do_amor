<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Model;

final class MessageService extends Model
{
    public function __construct(
        private readonly MatchService $matchService = new MatchService(),
        private readonly SubscriptionService $subscriptions = new SubscriptionService(),
        private readonly BlockService $blocks = new BlockService(),
        private readonly UploadService $uploads = new UploadService()
    )
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

    public function sendMessage(int $senderId, int $receiverId, string $messageText, string $messageType = 'text', array $attachments = []): int
    {
        $messageText = trim($messageText);
        $sanitizedType = in_array($messageType, ['text', 'image', 'system'], true) ? $messageType : 'text';

        if ($senderId <= 0 || $receiverId <= 0 || $senderId === $receiverId) {
            return 0;
        }

        if ($sanitizedType === 'text' && $messageText === '') {
            return 0;
        }

        if ($messageText !== '' && mb_strlen($messageText) > 2000) {
            return 0;
        }

        if ($sanitizedType === 'image' && $attachments === []) {
            return 0;
        }

        if (!$this->userCanMessage($senderId, $receiverId)) {
            return 0;
        }

        $conversationId = $this->getOrCreateConversation($senderId, $receiverId);
        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT INTO messages (conversation_id,sender_id,receiver_id,message_text,message_type,is_read,created_at) VALUES (:conversation_id,:sender_id,:receiver_id,:message_text,:message_type,0,NOW())')->execute([
                ':conversation_id' => $conversationId,
                ':sender_id' => $senderId,
                ':receiver_id' => $receiverId,
                ':message_text' => $messageText !== '' ? $messageText : '[imagem]',
                ':message_type' => $sanitizedType,
            ]);
            $messageId = (int) $this->db->lastInsertId();

            foreach ($attachments as $attachment) {
                $this->db->prepare('INSERT INTO message_attachments (message_id,file_path,mime_type,file_size,created_at) VALUES (:message_id,:file_path,:mime_type,:file_size,NOW())')->execute([
                    ':message_id' => $messageId,
                    ':file_path' => $attachment['path'] ?? '',
                    ':mime_type' => $attachment['mime'] ?? null,
                    ':file_size' => (int) ($attachment['size'] ?? 0),
                ]);
            }

            $this->db->prepare('UPDATE conversations SET updated_at = NOW() WHERE id = :id')->execute([':id' => $conversationId]);
            $this->db->commit();

            return $messageId;
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 0;
        }
    }

    public function getConversationMessages(int $conversationId, int $viewerId, int $page = 1, int $perPage = 40): array
    {
        if (!$this->isConversationParticipant($conversationId, $viewerId)) {
            return ['items' => [], 'pagination' => ['page' => 1, 'per_page' => $perPage, 'has_more' => false]];
        }

        $page = max(1, $page);
        $perPage = min(100, max(10, $perPage));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare('SELECT id,conversation_id,sender_id,receiver_id,message_text,message_type,is_read,created_at FROM messages WHERE conversation_id = :id ORDER BY id DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':id', $conversationId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $items = array_reverse($stmt->fetchAll());

        $attachmentMap = $this->attachmentsByMessageIds(array_map(static fn(array $row): int => (int) $row['id'], $items));
        foreach ($items as &$item) {
            $item['attachments'] = $attachmentMap[(int) $item['id']] ?? [];
        }
        unset($item);

        $countRow = $this->fetchOne('SELECT COUNT(*) AS total FROM messages WHERE conversation_id = :id', [':id' => $conversationId]) ?: ['total' => 0];
        $total = (int) ($countRow['total'] ?? 0);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => $total > ($page * $perPage),
                'total' => $total,
            ],
        ];
    }

    public function getConversationContext(int $conversationId, int $viewerId): array
    {
        if (!$this->isConversationParticipant($conversationId, $viewerId)) {
            return [];
        }

        $sql = "SELECT c.id,
                       c.created_at,
                       c.updated_at,
                       CASE WHEN c.user_one_id = :uid THEN u2.id ELSE u1.id END AS other_user_id,
                       CASE WHEN c.user_one_id = :uid THEN CONCAT(u2.first_name,' ',u2.last_name) ELSE CONCAT(u1.first_name,' ',u1.last_name) END AS other_user_name,
                       CASE WHEN c.user_one_id = :uid THEN u2.profile_photo_path ELSE u1.profile_photo_path END AS other_profile_photo,
                       CASE WHEN c.user_one_id = :uid THEN u2.online_status ELSE u1.online_status END AS other_online_status,
                       CASE WHEN c.user_one_id = :uid THEN u2.last_activity_at ELSE u1.last_activity_at END AS other_last_activity_at,
                       CASE WHEN c.user_one_id = :uid THEN u2.status ELSE u1.status END AS other_user_status,
                       CASE
                           WHEN c.user_one_id = :uid THEN EXISTS (
                               SELECT 1
                               FROM identity_verifications iv
                               WHERE iv.user_id = u2.id
                                 AND iv.status = 'approved'
                               LIMIT 1
                           )
                           ELSE EXISTS (
                               SELECT 1
                               FROM identity_verifications iv
                               WHERE iv.user_id = u1.id
                                 AND iv.status = 'approved'
                               LIMIT 1
                           )
                       END AS other_is_verified
                FROM conversations c
                JOIN users u1 ON u1.id = c.user_one_id
                JOIN users u2 ON u2.id = c.user_two_id
                WHERE c.id = :conversation_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':uid' => $viewerId, ':conversation_id' => $conversationId]);
        return $stmt->fetch() ?: [];
    }

    public function markAsRead(int $conversationId, int $readerId): void
    {
        if (!$this->isConversationParticipant($conversationId, $readerId)) {
            return;
        }

        $this->db->prepare('UPDATE messages SET is_read = 1 WHERE conversation_id = :conversation_id AND receiver_id = :reader_id AND is_read = 0')->execute([':conversation_id' => $conversationId, ':reader_id' => $readerId]);
    }

    public function isConversationParticipant(int $conversationId, int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM conversations WHERE id = :id AND (user_one_id = :user_id OR user_two_id = :user_id) LIMIT 1');
        $stmt->execute([':id' => $conversationId, ':user_id' => $userId]);
        return (bool) $stmt->fetch();
    }

    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS total FROM messages WHERE receiver_id = :id AND is_read = 0');
        $stmt->execute([':id' => $userId]);
        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    public function listConversations(int $userId, string $search = ''): array
    {
        $sql = "SELECT c.id,
                       c.updated_at,
                       CASE WHEN c.user_one_id = :uid THEN u2.id ELSE u1.id END AS other_user_id,
                       CASE WHEN c.user_one_id = :uid THEN CONCAT(u2.first_name,' ',u2.last_name) ELSE CONCAT(u1.first_name,' ',u1.last_name) END AS other_user_name,
                       CASE WHEN c.user_one_id = :uid THEN u2.online_status ELSE u1.online_status END AS other_online_status,
                       CASE WHEN c.user_one_id = :uid THEN u2.profile_photo_path ELSE u1.profile_photo_path END AS other_profile_photo,
                       lm.message_text AS last_message,
                       lm.message_type AS last_message_type,
                       lm.created_at AS last_message_at,
                       IFNULL(um.unread_count, 0) AS unread_count
                FROM conversations c
                JOIN users u1 ON u1.id = c.user_one_id
                JOIN users u2 ON u2.id = c.user_two_id
                LEFT JOIN (
                    SELECT m.conversation_id, m.message_text, m.message_type, m.created_at
                    FROM messages m
                    INNER JOIN (
                        SELECT conversation_id, MAX(id) AS last_message_id
                        FROM messages
                        GROUP BY conversation_id
                    ) latest ON latest.last_message_id = m.id
                ) lm ON lm.conversation_id = c.id
                LEFT JOIN (
                    SELECT conversation_id, COUNT(*) AS unread_count
                    FROM messages
                    WHERE receiver_id = :uid AND is_read = 0
                    GROUP BY conversation_id
                ) um ON um.conversation_id = c.id
                WHERE (c.user_one_id = :uid OR c.user_two_id = :uid)";

        $params = [':uid' => $userId];
        if ($search !== '') {
            $sql .= " AND (CASE WHEN c.user_one_id = :uid THEN CONCAT(u2.first_name,' ',u2.last_name) ELSE CONCAT(u1.first_name,' ',u1.last_name) END LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY COALESCE(lm.created_at, c.updated_at) DESC';
        return $this->fetchAllRows($sql, $params);
    }

    public function userCanMessage(int $senderId, int $receiverId): bool
    {
        $chatOnlyAfterMatch = filter_var((string) Config::env('ALLOW_CHAT_ONLY_AFTER_MATCH', 'true'), FILTER_VALIDATE_BOOLEAN);
        if ($this->blocks->isBlocked($senderId, $receiverId)) {
            return false;
        }

        if (!$this->subscriptions->canSendMessages($senderId) || !$this->subscriptions->canSendMessages($receiverId)) {
            return false;
        }

        if (!$chatOnlyAfterMatch) {
            return true;
        }

        return $this->matchService->hasActiveMatch($senderId, $receiverId);
    }

    private function attachmentsByMessageIds(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $this->db->prepare("SELECT message_id,file_path,mime_type,file_size FROM message_attachments WHERE message_id IN ($placeholders) ORDER BY id ASC");
        $stmt->execute(array_values($messageIds));

        $rows = [];
        foreach ($stmt->fetchAll() as $attachment) {
            $rows[(int) $attachment['message_id']][] = $attachment;
        }

        return $rows;
    }

    /**
     * Gancho administrativo para remover anexos físicos e vínculos de mensagens apagadas.
     */
    public function purgeMessageAttachments(array $messageIds): int
    {
        if ($messageIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $this->db->prepare("SELECT file_path FROM message_attachments WHERE message_id IN ($placeholders)");
        $stmt->execute(array_values($messageIds));
        $attachments = $stmt->fetchAll();

        $deleteStmt = $this->db->prepare("DELETE FROM message_attachments WHERE message_id IN ($placeholders)");
        $deleteStmt->execute(array_values($messageIds));

        foreach ($attachments as $attachment) {
            $this->uploads->deleteImageBundle(['path' => $attachment['file_path'] ?? null]);
        }

        return count($attachments);
    }
}
