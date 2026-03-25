<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Model;
use App\Core\Validator;

final class PasswordResetService extends Model
{
    public function __construct(private readonly MailService $mailService = new MailService())
    {
        parent::__construct();
    }

    public function requestReset(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT id,email FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if (!$user) {
            return true;
        }

        $token = $this->createResetToken((int) $user['id']);
        return $this->mailService->sendPasswordResetEmail((int) $user['id'], (string) $user['email'], $token);
    }

    public function createResetToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $minutes = (int) Config::env('PASSWORD_RESET_TOKEN_EXPIRY_MINUTES', 60);
        $stmt = $this->db->prepare('SELECT email FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $email = (string) ($stmt->fetch()['email'] ?? '');

        $this->invalidateUserResetTokens($userId);
        $this->db->prepare('INSERT INTO password_resets (user_id,email,token,expires_at,created_at) VALUES (:user_id,:email,:token,DATE_ADD(NOW(), INTERVAL :minutes MINUTE),NOW())')->execute([
            ':user_id' => $userId,
            ':email' => $email,
            ':token' => $token,
            ':minutes' => $minutes,
        ]);

        return $token;
    }

    public function validateResetToken(string $token): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM password_resets WHERE token = :token AND used_at IS NULL AND expires_at >= NOW()');
        $stmt->execute([':token' => $token]);
        return (bool) $stmt->fetch();
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        if (!Validator::strongPassword($newPassword)) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT id,user_id FROM password_resets WHERE token = :token AND used_at IS NULL AND expires_at >= NOW() LIMIT 1');
        $stmt->execute([':token' => $token]);
        $reset = $stmt->fetch();
        if (!$reset) {
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->prepare('UPDATE users SET password = :password WHERE id = :id')->execute([':password' => $hash, ':id' => $reset['user_id']]);
        $this->db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id')->execute([':id' => $reset['id']]);
        $this->invalidateUserResetTokens((int) $reset['user_id']);

        return true;
    }

    public function invalidateUserResetTokens(int $userId): void
    {
        $this->db->prepare('UPDATE password_resets SET used_at = COALESCE(used_at, NOW()) WHERE user_id = :user_id AND used_at IS NULL')->execute([':user_id' => $userId]);
    }
}
