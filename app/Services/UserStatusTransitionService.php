<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class UserStatusTransitionService extends Model
{
    private const ALLOWED_TRANSITIONS = [
        'pending_activation' => ['pending_verification', 'active', 'expired', 'suspended', 'banned'],
        'pending_verification' => ['active', 'expired', 'suspended', 'banned'],
        'active' => ['expired', 'suspended', 'banned', 'pending_verification'],
        'expired' => ['active', 'suspended', 'banned', 'pending_verification'],
        'suspended' => ['active', 'expired', 'banned'],
        'banned' => ['suspended', 'active'],
    ];

    public function __construct(
        private readonly AuditService $audit = new AuditService(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly MailService $mail = new MailService()
    ) {
        parent::__construct();
    }

    public function allowedStatuses(): array
    {
        return array_keys(self::ALLOWED_TRANSITIONS);
    }

    public function transition(int $userId, string $toStatus, int $adminId, string $reason, string $source = 'admin_user'): array
    {
        $user = $this->fetchOne('SELECT id,email,status FROM users WHERE id=:id LIMIT 1', [':id' => $userId]);
        if (!$user) {
            return ['ok' => false, 'message' => 'Utilizador não encontrado.'];
        }

        $fromStatus = (string) ($user['status'] ?? 'pending_activation');
        if (!in_array($toStatus, $this->allowedStatuses(), true)) {
            return ['ok' => false, 'message' => 'Estado de destino inválido.'];
        }

        if ($fromStatus === $toStatus) {
            return ['ok' => true, 'message' => 'Estado já estava aplicado.', 'from_status' => $fromStatus, 'to_status' => $toStatus];
        }

        if (!$this->canTransition($fromStatus, $toStatus)) {
            return ['ok' => false, 'message' => sprintf('Transição não permitida: %s -> %s.', $fromStatus, $toStatus)];
        }

        $this->execute('UPDATE users SET status=:status, updated_at=NOW() WHERE id=:id', [
            ':status' => $toStatus,
            ':id' => $userId,
        ]);

        $this->audit->logAdminEvent(
            $adminId,
            'user_status_changed',
            'user',
            $userId,
            [
                'source' => $source,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $reason,
                'result' => 'success',
            ]
        );

        $this->execute('INSERT INTO moderation_actions (admin_id,user_id,action_type,reason,created_at) VALUES (:admin,:user,:action,:reason,NOW())', [
            ':admin' => $adminId,
            ':user' => $userId,
            ':action' => $this->inferModerationAction($toStatus, $fromStatus),
            ':reason' => $reason,
        ]);

        $this->notifications->create(
            $userId,
            'account_status_changed',
            'Estado da conta actualizado',
            'O estado da tua conta foi alterado para ' . $toStatus . '.'
        );
        $this->mail->sendAccountStatusChangedEmail($userId, (string) $user['email'], $toStatus);

        return ['ok' => true, 'message' => 'Estado actualizado com auditoria.', 'from_status' => $fromStatus, 'to_status' => $toStatus];
    }

    private function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    private function inferModerationAction(string $toStatus, string $fromStatus): string
    {
        if ($toStatus === 'banned') {
            return 'ban';
        }

        if ($toStatus === 'suspended') {
            return 'suspend';
        }

        if ($fromStatus === 'banned' && in_array($toStatus, ['active', 'expired', 'pending_verification', 'pending_activation'], true)) {
            return 'unban';
        }

        if ($fromStatus === 'suspended' && $toStatus !== 'banned') {
            return 'unsuspend';
        }

        return in_array($toStatus, ['active', 'pending_verification'], true) ? 'activate' : 'deactivate';
    }
}
