<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class IdentityVerificationService extends Model
{
    public function __construct(
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly MailService $mail = new MailService()
    ) {
        parent::__construct();
    }

    public function submitVerification(int $userId, string $documentPath, string $selfiePath): int
    {
        $this->execute('INSERT INTO identity_verifications (user_id,document_image_path,selfie_image_path,status,created_at,updated_at) VALUES (:u,:d,:s,:status,NOW(),NOW())', [
            ':u' => $userId,
            ':d' => $documentPath,
            ':s' => $selfiePath,
            ':status' => 'pending',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function approveVerification(int $verificationId, int $adminId): bool
    {
        $verification = $this->fetchOne('SELECT user_id FROM identity_verifications WHERE id=:id', [':id' => $verificationId]);
        if (!$verification) {
            return false;
        }

        $this->execute("UPDATE identity_verifications SET status='approved',reviewed_by_admin_id=:a,updated_at=NOW() WHERE id=:id", [':a' => $adminId, ':id' => $verificationId]);
        $this->execute("UPDATE users SET premium_status='verified',status=CASE WHEN status='pending_verification' THEN 'active' ELSE status END WHERE id=:id", [':id' => $verification['user_id']]);
        $this->execute("INSERT INTO user_badges (user_id,badge_type,source,is_active,starts_at,created_at) VALUES (:u,'verified','identity_verification',1,NOW(),NOW())", [':u' => $verification['user_id']]);

        $user = $this->fetchOne('SELECT email FROM users WHERE id=:id', [':id' => $verification['user_id']]);
        if ($user) {
            $this->mail->sendIdentityVerificationApprovedEmail((int) $verification['user_id'], (string) $user['email']);
        }
        $this->notifications->create((int) $verification['user_id'], 'verification_approved', 'Identidade aprovada', 'Seu perfil foi verificado com sucesso.');
        return true;
    }

    public function rejectVerification(int $verificationId, int $adminId, string $reason): bool
    {
        $verification = $this->fetchOne('SELECT user_id FROM identity_verifications WHERE id=:id', [':id' => $verificationId]);
        if (!$verification) {
            return false;
        }

        $this->execute("UPDATE identity_verifications SET status='rejected',reviewed_by_admin_id=:a,rejection_reason=:r,updated_at=NOW() WHERE id=:id", [':a' => $adminId, ':r' => $reason, ':id' => $verificationId]);
        $user = $this->fetchOne('SELECT email FROM users WHERE id=:id', [':id' => $verification['user_id']]);
        if ($user) {
            $this->mail->sendIdentityVerificationRejectedEmail((int) $verification['user_id'], (string) $user['email'], $reason);
        }
        $this->notifications->create((int) $verification['user_id'], 'verification_rejected', 'Identidade rejeitada', $reason);
        return true;
    }

    public function userIsVerified(int $userId): bool
    {
        return (bool) $this->fetchOne("SELECT id FROM identity_verifications WHERE user_id=:u AND status='approved'", [':u' => $userId]);
    }
}
