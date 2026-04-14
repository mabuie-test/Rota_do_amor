<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class FavoriteService extends Model
{
    public function toggle(int $userId, int $targetId): array
    {
        if ($userId <= 0) {
            return $this->errorResult('auth_required', 'Sessão inválida.');
        }

        if ($targetId <= 0) {
            return $this->errorResult('invalid_target', 'Perfil de destino inválido.');
        }

        if ($userId === $targetId) {
            return $this->errorResult('self_favorite_forbidden', 'Não podes favoritar o teu próprio perfil.');
        }

        if (!$this->targetExists($targetId)) {
            return $this->errorResult('target_not_found', 'Este perfil já não está disponível.');
        }

        $existing = $this->fetchOne('SELECT id FROM favorites WHERE user_id=:u AND favorite_user_id=:t', [':u' => $userId, ':t' => $targetId]);
        if ($existing) {
            $this->execute('DELETE FROM favorites WHERE id=:id', [':id' => $existing['id']]);

            return [
                'success' => true,
                'message' => 'Favorito removido.',
                'action' => 'removed',
                'active' => false,
                'error_code' => null,
                'target_id' => $targetId,
            ];
        }

        $this->execute('INSERT INTO favorites (user_id,favorite_user_id,created_at) VALUES (:u,:t,NOW())', [':u' => $userId, ':t' => $targetId]);
        $createdId = (int) $this->db->lastInsertId();
        if ($createdId <= 0) {
            return $this->errorResult('favorite_create_failed', 'Não foi possível adicionar aos favoritos.', $targetId);
        }

        return [
            'success' => true,
            'message' => 'Favoritado com sucesso.',
            'action' => 'created',
            'active' => true,
            'error_code' => null,
            'target_id' => $targetId,
            'created_id' => $createdId,
        ];
    }

    private function targetExists(int $targetId): bool
    {
        return $this->fetchOne("SELECT id FROM users WHERE id=:id AND status='active' LIMIT 1", [':id' => $targetId]) !== null;
    }

    private function errorResult(string $code, string $message, int $targetId = 0): array
    {
        return [
            'success' => false,
            'message' => $message,
            'action' => 'error',
            'active' => false,
            'error_code' => $code,
            'target_id' => $targetId,
        ];
    }
}
