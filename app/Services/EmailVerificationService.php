<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Model;

final class EmailVerificationService extends Model
{
    public function __construct(
        private readonly MailService $mailService = new MailService(),
        private readonly AccountStateService $accountStates = new AccountStateService()
    ) {
        parent::__construct();
    }

    public function createVerificationToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $hours = (int) Config::env('EMAIL_VERIFICATION_TOKEN_EXPIRY_HOURS', 24);

        $this->db->prepare('UPDATE email_verifications SET expires_at = NOW() WHERE user_id = :user_id AND verified_at IS NULL')->execute([':user_id' => $userId]);
        $this->db->prepare('INSERT INTO email_verifications (user_id,token,expires_at,created_at) VALUES (:user_id,:token,DATE_ADD(NOW(), INTERVAL :hours HOUR),NOW())')->execute([':user_id' => $userId, ':token' => $token, ':hours' => $hours]);

        return $token;
    }

    public function sendVerification(int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT email FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        if (!$user) {
            return false;
        }

        return $this->mailService->sendVerificationEmail($userId, (string) $user['email'], $this->createVerificationToken($userId));
    }

    public function verifyToken(string $token): bool
    {
        $stmt = $this->db->prepare('SELECT id,user_id FROM email_verifications WHERE token = :token AND verified_at IS NULL AND expires_at >= NOW() ORDER BY id DESC LIMIT 1');
        $stmt->execute([':token' => $token]);
        $verification = $stmt->fetch();
        if (!$verification) {
            return false;
        }

        $this->db->prepare('UPDATE email_verifications SET verified_at = NOW() WHERE id = :id')->execute([':id' => $verification['id']]);
        $this->db->prepare('UPDATE users SET email_verified_at = NOW(), updated_at = NOW() WHERE id = :id')->execute([':id' => $verification['user_id']]);
        $this->accountStates->syncUserStatus((int) $verification['user_id']);

        return true;
    }

    public function resendVerification(int $userId): bool
    {
        return $this->sendVerification($userId);
    }

    public function isEmailVerified(int $userId): bool
    {
        return $this->accountStates->isEmailVerified($userId);
    }
}
