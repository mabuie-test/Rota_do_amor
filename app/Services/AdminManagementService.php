<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use RuntimeException;

final class AdminManagementService extends Model
{
    private const ROLES = ['super_admin', 'moderator', 'finance', 'support', 'ops', 'content_moderator'];

    public function __construct(private readonly AuditService $audit = new AuditService())
    {
        parent::__construct();
    }

    public function roles(): array
    {
        return self::ROLES;
    }

    public function permissionMatrix(): array
    {
        return [
            'super_admin' => ['all_access', 'manage_admins', 'manage_settings', 'view_finance', 'moderate_content', 'view_risk', 'view_audit'],
            'moderator' => ['moderate_content', 'view_reports', 'view_verifications', 'view_users'],
            'finance' => ['view_finance', 'view_payments', 'view_subscriptions', 'view_boosts'],
            'support' => ['view_users', 'view_reports', 'view_verifications'],
            'ops' => ['view_risk', 'view_audit', 'view_users', 'view_reports'],
            'content_moderator' => ['moderate_content', 'view_reports', 'view_feed'],
        ];
    }

    public function listAdmins(): array
    {
        return $this->fetchAll('SELECT a.id,a.name,a.email,a.role,a.status,a.created_at,a.updated_at,
            creator.name AS created_by_name,
            updater.name AS last_updated_by_name,
            (
                SELECT COUNT(*) FROM activity_logs x
                WHERE x.target_type = "admin" AND x.target_id = a.id
                  AND x.action IN ("admin_updated","admin_role_changed","admin_inactivated","admin_reactivated")
            ) AS changes_count
            FROM admins a
            LEFT JOIN activity_logs created_log ON created_log.action = "admin_created" AND created_log.target_type = "admin" AND created_log.target_id = a.id
            LEFT JOIN admins creator ON creator.id = created_log.actor_id
            LEFT JOIN activity_logs update_log ON update_log.id = (
                SELECT al.id FROM activity_logs al
                WHERE al.target_type = "admin" AND al.target_id = a.id
                  AND al.action IN ("admin_updated","admin_role_changed","admin_inactivated","admin_reactivated")
                ORDER BY al.id DESC LIMIT 1
            )
            LEFT JOIN admins updater ON updater.id = update_log.actor_id
            ORDER BY a.id DESC
            LIMIT 500');
    }

    public function history(): array
    {
        return $this->fetchAll('SELECT al.id, al.action, al.target_id, al.metadata_json, al.created_at, a.name AS actor_name, ta.name AS target_name
            FROM activity_logs al
            LEFT JOIN admins a ON a.id = al.actor_id AND al.actor_type="admin"
            LEFT JOIN admins ta ON ta.id = al.target_id AND al.target_type="admin"
            WHERE al.target_type = "admin"
              AND al.action IN ("admin_created","admin_updated","admin_role_changed","admin_inactivated","admin_reactivated")
            ORDER BY al.id DESC
            LIMIT 120');
    }

    public function create(array $payload, int $actorAdminId): int
    {
        $this->assertValidPayload($payload, true);

        if ($this->fetchOne('SELECT id FROM admins WHERE email = :email LIMIT 1', [':email' => $payload['email']])) {
            throw new RuntimeException('Já existe um admin com este email.');
        }

        $this->execute('INSERT INTO admins (name,email,password,role,status,created_at,updated_at) VALUES (:name,:email,:password,:role,:status,NOW(),NOW())', [
            ':name' => $payload['name'],
            ':email' => $payload['email'],
            ':password' => password_hash($payload['password'], PASSWORD_DEFAULT),
            ':role' => $payload['role'],
            ':status' => $payload['status'] ?? 'active',
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->audit->logAdminEvent($actorAdminId, 'admin_created', 'admin', $id, ['role' => $payload['role'], 'status' => $payload['status'] ?? 'active']);
        return $id;
    }

    public function update(int $id, array $payload, int $actorAdminId): void
    {
        $this->assertValidPayload($payload, false);

        $current = $this->fetchOne('SELECT id, email, role, status FROM admins WHERE id = :id LIMIT 1', [':id' => $id]);
        if (!$current) {
            throw new RuntimeException('Admin não encontrado.');
        }

        if (($current['role'] ?? '') === 'super_admin' && ($payload['confirm_super_admin_change'] ?? '') !== 'CONFIRMAR') {
            throw new RuntimeException('Para alterar um super admin é obrigatório confirmar com o texto CONFIRMAR.');
        }
        if (($current['role'] ?? '') !== 'super_admin' && ($payload['role'] ?? '') === 'super_admin' && ($payload['confirm_super_admin_change'] ?? '') !== 'CONFIRMAR') {
            throw new RuntimeException('Para promover para super admin é obrigatório confirmar com o texto CONFIRMAR.');
        }

        if ($actorAdminId === $id && (($payload['status'] ?? 'active') !== 'active' || ($payload['role'] ?? '') !== ($current['role'] ?? ''))) {
            throw new RuntimeException('Não podes desactivar ou alterar o teu próprio papel nesta tela.');
        }

        $existingEmail = $this->fetchOne('SELECT id FROM admins WHERE email = :email AND id <> :id LIMIT 1', [':email' => $payload['email'], ':id' => $id]);
        if ($existingEmail) {
            throw new RuntimeException('O email informado já pertence a outro admin.');
        }

        if (($current['role'] ?? '') === 'super_admin' && ($payload['role'] ?? '') !== 'super_admin') {
            $this->guardLastSuperAdmin($id);
        }

        if (($current['role'] ?? '') === 'super_admin' && ($payload['status'] ?? 'active') !== 'active') {
            $this->guardLastSuperAdmin($id);
        }

        $params = [
            ':id' => $id,
            ':name' => $payload['name'],
            ':email' => $payload['email'],
            ':role' => $payload['role'],
            ':status' => $payload['status'],
        ];

        if (($payload['password'] ?? '') !== '') {
            $this->execute('UPDATE admins SET name=:name,email=:email,role=:role,status=:status,password=:password,updated_at=NOW() WHERE id=:id', $params + [':password' => password_hash($payload['password'], PASSWORD_DEFAULT)]);
        } else {
            $this->execute('UPDATE admins SET name=:name,email=:email,role=:role,status=:status,updated_at=NOW() WHERE id=:id', $params);
        }

        $changes = [
            'from_role' => $current['role'] ?? null,
            'to_role' => $payload['role'],
            'from_status' => $current['status'] ?? null,
            'to_status' => $payload['status'],
        ];

        $action = ($current['status'] ?? 'inactive') !== $payload['status']
            ? ($payload['status'] === 'active' ? 'admin_reactivated' : 'admin_inactivated')
            : (($current['role'] ?? '') !== $payload['role'] ? 'admin_role_changed' : 'admin_updated');

        $this->audit->logAdminEvent($actorAdminId, $action, 'admin', $id, $changes);
    }

    private function assertValidPayload(array $payload, bool $creating): void
    {
        if (trim((string) ($payload['name'] ?? '')) === '' || trim((string) ($payload['email'] ?? '')) === '') {
            throw new RuntimeException('Nome e email são obrigatórios.');
        }
        if (!filter_var((string) ($payload['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email administrativo inválido.');
        }

        if ($creating && trim((string) ($payload['password'] ?? '')) === '') {
            throw new RuntimeException('Password é obrigatória para criar admin.');
        }

        if (($payload['password'] ?? '') !== '' && mb_strlen((string) $payload['password']) < 8) {
            throw new RuntimeException('A password deve ter pelo menos 8 caracteres.');
        }

        if (!in_array((string) ($payload['role'] ?? ''), self::ROLES, true)) {
            throw new RuntimeException('Papel administrativo inválido.');
        }

        if (!in_array((string) ($payload['status'] ?? ''), ['active', 'inactive'], true)) {
            throw new RuntimeException('Estado administrativo inválido.');
        }
    }

    private function guardLastSuperAdmin(int $adminIdUnderChange): void
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS c FROM admins WHERE role = "super_admin" AND status = "active" AND id <> :id', [':id' => $adminIdUnderChange]);
        if ((int) ($row['c'] ?? 0) <= 0) {
            throw new RuntimeException('Operação bloqueada: deve existir ao menos um super admin activo.');
        }
    }
}
