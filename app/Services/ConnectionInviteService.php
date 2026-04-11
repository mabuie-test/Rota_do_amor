<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use DateTimeImmutable;

final class ConnectionInviteService extends Model
{
    private const DEFAULT_EXPIRE_DAYS = 7;

    public function __construct(
        private readonly CompatibilityService $compatibility = new CompatibilityService(),
        private readonly ConnectionModeService $connectionModes = new ConnectionModeService(),
        private readonly BlockService $blocks = new BlockService(),
        private readonly PremiumService $premium = new PremiumService(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly MatchService $matches = new MatchService(),
        private readonly MessageService $messages = new MessageService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService()
    ) {
        parent::__construct();
    }

    public function sendInvite(int $senderId, int $receiverId, string $invitationType = 'standard', ?string $openingMessage = null): array
    {
        $normalizedType = in_array($invitationType, ['standard', 'priority'], true) ? $invitationType : 'standard';
        $sanitizedMessage = $this->sanitizeOpeningMessage($openingMessage);

        $validation = $this->validateBeforeSend($senderId, $receiverId, $normalizedType, $sanitizedMessage);
        if (!$validation['ok']) {
            return $validation;
        }

        $snapshot = $this->buildSnapshot($senderId, $receiverId);
        $senderName = $this->getUserDisplayName($senderId);
        $expiresAt = $this->resolveExpirationDate()->format('Y-m-d H:i:s');

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                'INSERT INTO connection_invites (sender_user_id,receiver_user_id,status,invitation_type,opening_message,current_intention_snapshot,relational_pace_snapshot,compatibility_score_snapshot,compatibility_breakdown_snapshot,expires_at,created_at,updated_at)
                 VALUES (:sender,:receiver,:status,:type,:opening,:intention,:pace,:score,:breakdown,:expires_at,NOW(),NOW())'
            );
            $stmt->execute([
                ':sender' => $senderId,
                ':receiver' => $receiverId,
                ':status' => 'pending',
                ':type' => $normalizedType,
                ':opening' => $sanitizedMessage,
                ':intention' => $snapshot['current_intention_snapshot'],
                ':pace' => $snapshot['relational_pace_snapshot'],
                ':score' => $snapshot['compatibility_score_snapshot'],
                ':breakdown' => $snapshot['compatibility_breakdown_snapshot'],
                ':expires_at' => $expiresAt,
            ]);

            $inviteId = (int) $this->db->lastInsertId();
            $this->db->commit();

            $this->rateLimiter->hitSuccess('invite_send', $this->rateLimitKey($senderId), $senderId, ['receiver_id' => $receiverId, 'type' => $normalizedType]);
            $this->notifications->create(
                $receiverId,
                $normalizedType === 'priority' ? 'invite_priority_received' : 'invite_received',
                $normalizedType === 'priority' ? 'Convite prioritário recebido' : 'Novo convite com intenção',
                $normalizedType === 'priority'
                    ? sprintf('%s enviou-te um convite prioritário com intenção séria.', $senderName)
                    : sprintf('%s enviou-te um convite com intenção e ritmo do momento.', $senderName),
                ['invite_id' => $inviteId, 'sender_id' => $senderId, 'invitation_type' => $normalizedType]
            );

            return ['ok' => true, 'invite_id' => $inviteId];
        } catch (\PDOException $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($this->isPendingInviteUniqueViolation($exception)) {
                return ['ok' => false, 'message' => 'Já existe um convite pendente para este perfil.'];
            }

            return ['ok' => false, 'message' => 'Não foi possível enviar o convite agora.'];
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['ok' => false, 'message' => 'Não foi possível enviar o convite agora.'];
        }
    }

    public function listReceived(int $userId, array $filters = []): array
    {
        $this->expirePendingForUser($userId);

        $params = [':uid' => $userId];
        $where = 'ci.receiver_user_id = :uid';

        if (!empty($filters['status'])) {
            $where .= ' AND ci.status = :status';
            $params[':status'] = (string) $filters['status'];
        }

        if (!empty($filters['invitation_type'])) {
            $where .= ' AND ci.invitation_type = :invitation_type';
            $params[':invitation_type'] = (string) $filters['invitation_type'];
        }

        $orderBy = 'ORDER BY (ci.invitation_type = "priority") DESC, ci.compatibility_score_snapshot DESC, ci.created_at DESC';
        $isPremium = $this->premium->userHasPremium($userId);
        $maxPerPage = $isPremium ? 100 : 25;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $requestedPerPage = (int) ($filters['per_page'] ?? 12);
        $perPage = min($maxPerPage, max(5, $requestedPerPage));
        $offset = ($page - 1) * $perPage;

        $countSql = "SELECT COUNT(*) AS total FROM connection_invites ci WHERE $where";
        $total = (int) (($this->fetchOne($countSql, $params)['total'] ?? 0));

        $sql = "SELECT ci.*,
                       CONCAT(u.first_name, ' ', u.last_name) AS sender_name,
                       u.profile_photo_path AS sender_photo
                FROM connection_invites ci
                JOIN users u ON u.id = ci.sender_user_id
                WHERE $where
                $orderBy
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $items = $this->presentInvites($stmt->fetchAll());

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => ($offset + $perPage) < $total,
            ],
        ];
    }

    public function listSent(int $userId, array $filters = []): array
    {
        $params = [':uid' => $userId];
        $where = 'ci.sender_user_id = :uid';

        if (!empty($filters['status'])) {
            $where .= ' AND ci.status = :status';
            $params[':status'] = (string) $filters['status'];
        }

        $isPremium = $this->premium->userHasPremium($userId);
        $maxPerPage = $isPremium ? 100 : 30;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $requestedPerPage = (int) ($filters['per_page'] ?? 12);
        $perPage = min($maxPerPage, max(5, $requestedPerPage));
        $offset = ($page - 1) * $perPage;

        $countSql = "SELECT COUNT(*) AS total FROM connection_invites ci WHERE $where";
        $total = (int) (($this->fetchOne($countSql, $params)['total'] ?? 0));

        $sql = "SELECT ci.*,
                       CONCAT(u.first_name, ' ', u.last_name) AS receiver_name,
                       u.profile_photo_path AS receiver_photo
                FROM connection_invites ci
                JOIN users u ON u.id = ci.receiver_user_id
                WHERE $where
                ORDER BY (ci.invitation_type = 'priority') DESC, ci.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $items = $this->presentInvites($stmt->fetchAll());

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => ($offset + $perPage) < $total,
            ],
        ];
    }

    public function acceptInvite(int $inviteId, int $receiverId): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM connection_invites WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute([':id' => $inviteId]);
            $invite = $stmt->fetch();

            if (!$invite || (int) $invite['receiver_user_id'] !== $receiverId) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'Convite não encontrado.'];
            }

            if ((string) $invite['status'] !== 'pending') {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'Este convite já foi respondido.'];
            }

            if ($this->isExpired($invite)) {
                $this->execute("UPDATE connection_invites SET status='expired',updated_at=NOW() WHERE id=:id", [':id' => $inviteId]);
                $this->db->commit();
                return ['ok' => false, 'message' => 'Este convite expirou.'];
            }

            $senderId = (int) $invite['sender_user_id'];
            $updated = $this->execute("UPDATE connection_invites SET status='accepted',responded_at=NOW(),updated_at=NOW() WHERE id=:id AND status='pending'", [':id' => $inviteId]);
            if (!$updated) {
                $this->db->rollBack();
                return ['ok' => false, 'message' => 'Este convite já foi respondido.'];
            }

            $this->matches->createMatch($senderId, $receiverId, 'connection');
            $conversationId = $this->messages->getOrCreateConversation($senderId, $receiverId);

            $opening = trim((string) ($invite['opening_message'] ?? ''));
            if ($opening !== '') {
                $contextMessage = 'Contexto do convite aceite: "' . mb_substr($opening, 0, 350) . '"';
                $this->insertInviteContextMessage($conversationId, $senderId, $receiverId, $contextMessage);
            }

            $this->db->commit();

            $receiverName = $this->getUserDisplayName($receiverId);
            $acceptedType = (string) ($invite['invitation_type'] ?? 'standard') === 'priority' ? 'prioritário' : 'com intenção';
            $this->notifications->create($senderId, 'invite_accepted', 'Convite aceite', sprintf('O teu convite %s para %s foi aceite. A conversa já está pronta.', $acceptedType, $receiverName), [
                'invite_id' => $inviteId,
                'receiver_id' => $receiverId,
                'conversation_id' => $conversationId,
            ]);

            return ['ok' => true, 'conversation_id' => $conversationId];
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return ['ok' => false, 'message' => 'Não foi possível aceitar o convite.'];
        }
    }

    public function declineInvite(int $inviteId, int $receiverId): array
    {
        $invite = $this->fetchOne('SELECT id,status,receiver_user_id,sender_user_id,invitation_type FROM connection_invites WHERE id=:id LIMIT 1', [':id' => $inviteId]);
        if (!$invite || (int) $invite['receiver_user_id'] !== $receiverId) {
            return ['ok' => false, 'message' => 'Convite não encontrado.'];
        }

        if ((string) $invite['status'] !== 'pending') {
            return ['ok' => false, 'message' => 'Este convite já foi respondido.'];
        }

        $ok = $this->execute("UPDATE connection_invites SET status='declined',responded_at=NOW(),updated_at=NOW() WHERE id=:id AND status='pending'", [':id' => $inviteId]);
        if ($ok) {
            $receiverName = $this->getUserDisplayName($receiverId);
            $inviteType = ((string) ($invite['invitation_type'] ?? 'standard') === 'priority') ? 'prioritário' : 'com intenção';
            $this->notifications->create((int) $invite['sender_user_id'], 'invite_declined', 'Convite recusado', sprintf('%s recusou o teu convite %s.', $receiverName, $inviteType), ['invite_id' => $inviteId]);
        }

        return ['ok' => $ok, 'message' => $ok ? 'Convite recusado.' : 'Não foi possível recusar o convite.'];
    }

    public function expirePendingForUser(int $userId): int
    {
        $stmt = $this->db->prepare("UPDATE connection_invites SET status='expired',updated_at=NOW() WHERE receiver_user_id=:uid AND status='pending' AND expires_at IS NOT NULL AND expires_at <= NOW()");
        $stmt->execute([':uid' => $userId]);
        return $stmt->rowCount();
    }

    private function validateBeforeSend(int $senderId, int $receiverId, string $invitationType, ?string $openingMessage): array
    {
        if ($senderId <= 0 || $receiverId <= 0 || $senderId === $receiverId) {
            return ['ok' => false, 'message' => 'Convite inválido.'];
        }

        $sender = $this->fetchOne('SELECT id,status FROM users WHERE id = :id LIMIT 1', [':id' => $senderId]);
        $receiver = $this->fetchOne('SELECT id,status FROM users WHERE id = :id LIMIT 1', [':id' => $receiverId]);
        if (!$sender || !$receiver) {
            return ['ok' => false, 'message' => 'Utilizador não encontrado.'];
        }

        if ((string) $sender['status'] !== 'active' || (string) $receiver['status'] !== 'active') {
            return ['ok' => false, 'message' => 'Ambos os perfis devem estar activos para enviar convite.'];
        }

        if ($this->blocks->isBlocked($senderId, $receiverId)) {
            return ['ok' => false, 'message' => 'Não é possível convidar este perfil.'];
        }

        if ($this->hasPolicyBlock($senderId, $receiverId)) {
            return ['ok' => false, 'message' => 'Interações temporariamente limitadas por política da plataforma.'];
        }

        if ($this->hasPendingInvite($senderId, $receiverId)) {
            return ['ok' => false, 'message' => 'Já existe um convite pendente para este perfil.'];
        }

        $rateLimitKey = $this->rateLimitKey($senderId);
        if (
            $this->rateLimiter->tooManyAttempts('invite_send', $rateLimitKey, 8, 60, 'success')
            || $this->rateLimiter->tooManyAttempts('invite_send', $rateLimitKey, 40, 1440, 'success')
        ) {
            return ['ok' => false, 'message' => 'Limite de convites excedido. Tente mais tarde.'];
        }
        $this->rateLimiter->hit('invite_send', $rateLimitKey, $senderId);

        if ($invitationType === 'priority' && !$this->premium->userHasPremium($senderId)) {
            return ['ok' => false, 'message' => 'Convite prioritário disponível apenas no Premium.'];
        }

        if ($invitationType === 'priority' && ($openingMessage === null || trim($openingMessage) === '')) {
            return ['ok' => false, 'message' => 'Convite prioritário exige mensagem de abertura.'];
        }

        return ['ok' => true];
    }

    private function sanitizeOpeningMessage(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        $normalized = trim(strip_tags($message));
        if ($normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, 500);
    }

    private function hasPendingInvite(int $senderId, int $receiverId): bool
    {
        return (bool) $this->fetchOne(
            "SELECT id FROM connection_invites WHERE sender_user_id=:sender AND receiver_user_id=:receiver AND status='pending' LIMIT 1",
            [':sender' => $senderId, ':receiver' => $receiverId]
        );
    }

    private function hasPolicyBlock(int $senderId, int $receiverId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1
             FROM users
             WHERE (id = :sender OR id = :receiver)
               AND status IN ('suspended','banned','pending_activation','pending_verification')
             LIMIT 1"
        );
        $stmt->execute([
            ':sender' => $senderId,
            ':receiver' => $receiverId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function insertInviteContextMessage(int $conversationId, int $senderId, int $receiverId, string $messageText): void
    {
        $existing = $this->fetchOne(
            "SELECT id
             FROM messages
             WHERE conversation_id = :conversation_id
               AND sender_id = :sender_id
               AND receiver_id = :receiver_id
               AND message_type = 'system'
               AND message_text = :message_text
             LIMIT 1",
            [
                ':conversation_id' => $conversationId,
                ':sender_id' => $senderId,
                ':receiver_id' => $receiverId,
                ':message_text' => $messageText,
            ]
        );

        if ($existing) {
            return;
        }

        $this->db->prepare(
            "INSERT INTO messages (conversation_id,sender_id,receiver_id,message_text,message_type,is_read,created_at)
             VALUES (:conversation_id,:sender_id,:receiver_id,:message_text,'system',0,NOW())"
        )->execute([
            ':conversation_id' => $conversationId,
            ':sender_id' => $senderId,
            ':receiver_id' => $receiverId,
            ':message_text' => $messageText,
        ]);

        $this->db->prepare('UPDATE conversations SET updated_at = NOW() WHERE id = :id')->execute([':id' => $conversationId]);
    }

    private function buildSnapshot(int $senderId, int $receiverId): array
    {
        $mode = $this->connectionModes->getForUser($senderId);
        $scores = $this->compatibility->getCompatibilityScoresForTargets($senderId, [$receiverId]);
        $scoreMeta = $scores[$receiverId] ?? null;

        if ($scoreMeta === null) {
            $receiver = $this->fetchOne('SELECT * FROM users WHERE id = :id LIMIT 1', [':id' => $receiverId]) ?: [];
            $scoreMeta = $this->compatibility->calculateCompatibility($senderId, $receiverId, $receiver);
        }

        $breakdown = $scoreMeta['breakdown'] ?? [];
        if (!is_array($breakdown)) {
            $decoded = json_decode((string) $breakdown, true);
            $breakdown = is_array($decoded) ? $decoded : [];
        }

        return [
            'current_intention_snapshot' => (string) ($mode['current_intention'] ?? 'know_without_pressure'),
            'relational_pace_snapshot' => (string) ($mode['relational_pace'] ?? 'balanced'),
            'compatibility_score_snapshot' => round((float) ($scoreMeta['score'] ?? 0), 2),
            'compatibility_breakdown_snapshot' => $breakdown !== [] ? json_encode($breakdown, JSON_THROW_ON_ERROR) : null,
        ];
    }

    private function resolveExpirationDate(): DateTimeImmutable
    {
        $setting = $this->fetchOne("SELECT setting_value,value_type FROM site_settings WHERE setting_key='invites_expiration_days' LIMIT 1");
        $days = self::DEFAULT_EXPIRE_DAYS;
        if ($setting) {
            $value = (int) ($setting['setting_value'] ?? self::DEFAULT_EXPIRE_DAYS);
            if ($value >= 1 && $value <= 30) {
                $days = $value;
            }
        }

        return (new DateTimeImmutable('now'))->modify('+' . $days . ' days');
    }

    private function isExpired(array $invite): bool
    {
        if (empty($invite['expires_at'])) {
            return false;
        }

        return strtotime((string) $invite['expires_at']) <= time();
    }

    private function presentInvites(array $invites): array
    {
        foreach ($invites as &$invite) {
            $invite['is_priority'] = (string) ($invite['invitation_type'] ?? 'standard') === 'priority';
            $invite['is_pending'] = (string) ($invite['status'] ?? '') === 'pending';
            $invite['compatibility_score_snapshot'] = round((float) ($invite['compatibility_score_snapshot'] ?? 0), 1);
            $invite['intention_label'] = $this->connectionModes->labelForIntention((string) ($invite['current_intention_snapshot'] ?? ''));
            $invite['pace_label'] = $this->connectionModes->labelForPace((string) ($invite['relational_pace_snapshot'] ?? ''));
            $invite['intention_icon'] = $this->connectionModes->iconForIntention((string) ($invite['current_intention_snapshot'] ?? ''));
            $invite['pace_icon'] = $this->connectionModes->iconForPace((string) ($invite['relational_pace_snapshot'] ?? ''));
        }
        unset($invite);

        return $invites;
    }

    private function rateLimitKey(int $senderId): string
    {
        return 'invite_send:' . $senderId;
    }

    private function getUserDisplayName(int $userId): string
    {
        $row = $this->fetchOne('SELECT first_name,last_name FROM users WHERE id = :id LIMIT 1', [':id' => $userId]);
        if (!$row) {
            return 'Alguém';
        }

        return trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? ''))) ?: 'Alguém';
    }

    private function isPendingInviteUniqueViolation(\PDOException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $driverMessage = (string) ($exception->errorInfo[2] ?? '');

        if ($sqlState !== '23000' || $driverCode !== 1062) {
            return false;
        }

        return str_contains($driverMessage, 'uq_connection_invites_pending_once');
    }
}
