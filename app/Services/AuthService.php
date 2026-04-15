<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Model;
use App\Core\Request;

final class AuthService extends Model
{
    public function __construct(private readonly UserService $users = new UserService())
    {
        parent::__construct();
    }

    public function attemptLogin(string $email, string $password): array
    {
        if (!$this->checkRateLimit($email)) {
            return ['ok' => false, 'message' => 'Muitas tentativas. Tente novamente em alguns minutos.'];
        }

        $user = $this->users->getByEmail($email);
        if (!$user || !password_verify($password, (string) $user['password'])) {
            $this->recordLoginAttempt($email, false);
            return ['ok' => false, 'message' => 'Credenciais inválidas.'];
        }

        if (in_array($user['status'], ['suspended', 'banned'], true)) {
            return ['ok' => false, 'message' => 'Conta indisponível.'];
        }

        if (filter_var((string) Config::env('EMAIL_VERIFICATION_REQUIRED', 'true'), FILTER_VALIDATE_BOOLEAN) && !$user['email_verified_at']) {
            return ['ok' => false, 'message' => 'Verifique seu email para continuar.'];
        }

        Auth::login((int) $user['id']);
        $this->recordLoginAttempt($email, true);
        $this->execute('UPDATE users SET last_activity_at = NOW(), online_status = 1 WHERE id = :id', [':id' => $user['id']]);

        return ['ok' => true, 'user' => $user];
    }

    public function logout(int $userId): void
    {
        $this->execute('UPDATE users SET online_status = 0 WHERE id = :id', [':id' => $userId]);
        Auth::logout();
    }

    private function checkRateLimit(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS total
            FROM activity_logs
            WHERE action = 'login_failed'
              AND target_type = 'login'
              AND rate_limit_key = :rate_limit_key
              AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        $stmt->execute([':rate_limit_key' => $this->loginRateLimitKey($email)]);
        $total = (int) ($stmt->fetch()['total'] ?? 0);

        return $total < 10;
    }

    private function recordLoginAttempt(string $email, bool $success): void
    {
        $this->execute('INSERT INTO activity_logs (actor_type,actor_id,action,target_type,target_id,rate_limit_key,metadata_json,ip_address,created_at) VALUES (:actor_type,:actor_id,:action,:target_type,:target_id,:rate_limit_key,:metadata,:ip,NOW())', [
            ':actor_type' => 'system',
            ':actor_id' => null,
            ':action' => $success ? 'login_success' : 'login_failed',
            ':target_type' => 'login',
            ':target_id' => null,
            ':metadata' => json_encode(['email' => mb_strtolower(trim($email))], JSON_THROW_ON_ERROR),
            ':ip' => Request::ip(),
            ':rate_limit_key' => $this->loginRateLimitKey($email),
        ]);
    }

    private function loginRateLimitKey(string $email): string
    {
        return 'login:' . hash('sha256', mb_strtolower(trim($email)) . '|' . Request::ip());
    }
}
