<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class IdentityVerificationService extends Model
{
    public function __construct(
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly MailService $mail = new MailService(),
        private readonly BadgeService $badges = new BadgeService(),
        private readonly AccountStateService $accountStates = new AccountStateService(),
        private readonly AuditService $audit = new AuditService()
    ) {
        parent::__construct();
    }

    public function submitVerification(int $userId, string $documentPath, string $selfiePath): int
    {
        $this->execute("UPDATE identity_verifications SET status='rejected', rejection_reason='Substituída por novo envio', updated_at=NOW() WHERE user_id=:u AND status='pending'", [':u' => $userId]);
        $this->execute('INSERT INTO identity_verifications (user_id,document_image_path,selfie_image_path,status,created_at,updated_at) VALUES (:u,:d,:s,:status,NOW(),NOW())', [':u' => $userId, ':d' => $documentPath, ':s' => $selfiePath, ':status' => 'pending']);
        return (int) $this->db->lastInsertId();
    }

    public function listForAdminPanel(int $limit = 500): array
    {
        $safeLimit = max(1, min($limit, 1000));
        return $this->fetchAll("SELECT iv.id,iv.user_id,iv.status,iv.document_image_path,iv.selfie_image_path,iv.rejection_reason,iv.reviewed_by_admin_id,iv.created_at,iv.updated_at,u.first_name,u.last_name,u.email,u.status AS user_status,u.premium_status,a.name AS reviewed_by_admin_name FROM identity_verifications iv INNER JOIN users u ON u.id = iv.user_id LEFT JOIN admins a ON a.id = iv.reviewed_by_admin_id ORDER BY iv.id DESC LIMIT {$safeLimit}");
    }

    public function latestForUser(int $userId): array
    {
        return $this->fetchOne('SELECT id,status,rejection_reason,created_at,updated_at FROM identity_verifications WHERE user_id=:u ORDER BY id DESC LIMIT 1', [':u' => $userId]) ?: [];
    }

    public function approveVerification(int $verificationId, int $adminId): bool
    {
        $verification = $this->fetchOne('SELECT id,user_id,status FROM identity_verifications WHERE id=:id LIMIT 1', [':id' => $verificationId]);
        if (!$verification) {
            return false;
        }

        $latestId = (int) ($this->fetchOne('SELECT id FROM identity_verifications WHERE user_id=:u ORDER BY id DESC LIMIT 1', [':u' => $verification['user_id']])['id'] ?? 0);
        if ($latestId !== (int) $verification['id']) {
            return false;
        }

        $this->execute("UPDATE identity_verifications SET status='approved',reviewed_by_admin_id=:a,rejection_reason=NULL,updated_at=NOW() WHERE id=:id", [':a' => $adminId, ':id' => $verificationId]);
        $this->execute("UPDATE identity_verifications SET status='rejected',rejection_reason='Substituída por aprovação mais recente',updated_at=NOW() WHERE user_id=:u AND id<>:id AND status='approved'", [':u' => $verification['user_id'], ':id' => $verificationId]);
        $this->execute("UPDATE users SET premium_status='verified', status=CASE WHEN status='pending_verification' THEN 'active' ELSE status END, updated_at=NOW() WHERE id=:id", [':id' => $verification['user_id']]);

        $userId = (int) $verification['user_id'];
        $this->badges->assignBadge($userId, 'verified', 'identity_verification', date('Y-m-d H:i:s'));
        $this->badges->syncSystemBadges($userId);
        $this->accountStates->syncUserStatus($userId);

        $user = $this->fetchOne('SELECT email FROM users WHERE id=:id', [':id' => $userId]);
        if ($user) {
            $this->mail->sendIdentityVerificationApprovedEmail($userId, (string) $user['email']);
        }

        $this->notifications->create($userId, 'verification_approved', 'Identidade aprovada', 'Seu perfil foi verificado com sucesso.');
        $this->audit->logAdminEvent($adminId, 'verification_approved', 'identity_verification', $verificationId, ['user_id' => $userId]);
        return true;
    }

    public function rejectVerification(int $verificationId, int $adminId, string $reason): bool
    {
        $verification = $this->fetchOne('SELECT id,user_id,status FROM identity_verifications WHERE id=:id LIMIT 1', [':id' => $verificationId]);
        if (!$verification) {
            return false;
        }

        $this->execute("UPDATE identity_verifications SET status='rejected',reviewed_by_admin_id=:a,rejection_reason=:r,updated_at=NOW() WHERE id=:id", [':a' => $adminId, ':r' => $reason, ':id' => $verificationId]);
        $this->execute("UPDATE users SET premium_status=CASE WHEN premium_status='verified' THEN 'premium' ELSE premium_status END, updated_at=NOW() WHERE id=:id", [':id' => $verification['user_id']]);

        $userId = (int) $verification['user_id'];
        $this->badges->revokeBadge($userId, 'verified');
        $this->badges->syncSystemBadges($userId);

        $user = $this->fetchOne('SELECT email FROM users WHERE id=:id', [':id' => $userId]);
        if ($user) {
            $this->mail->sendIdentityVerificationRejectedEmail($userId, (string) $user['email'], $reason);
        }

        $this->notifications->create($userId, 'verification_rejected', 'Identidade rejeitada', $reason);
        $this->audit->logAdminEvent($adminId, 'verification_rejected', 'identity_verification', $verificationId, ['user_id' => $userId, 'reason' => $reason]);
        return true;
    }

    public function userIsVerified(int $userId): bool
    {
        return (bool) $this->fetchOne("SELECT id FROM identity_verifications WHERE user_id=:u AND status='approved'", [':u' => $userId]);
    }
}
