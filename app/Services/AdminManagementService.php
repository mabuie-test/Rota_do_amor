<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

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

    public function listAdmins(): array
    {
        return $this->fetchAll('SELECT id,name,email,role,status,created_at,updated_at FROM admins ORDER BY id DESC LIMIT 500');
    }

    public function create(array $payload, int $actorAdminId): int
    {
        $this->execute('INSERT INTO admins (name,email,password,role,status,created_at,updated_at) VALUES (:name,:email,:password,:role,:status,NOW(),NOW())', [
            ':name' => $payload['name'],
            ':email' => $payload['email'],
            ':password' => password_hash($payload['password'], PASSWORD_DEFAULT),
            ':role' => $payload['role'],
            ':status' => $payload['status'] ?? 'active',
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->audit->logAdminEvent($actorAdminId, 'admin_created', 'admin', $id, ['role' => $payload['role']]);
        return $id;
    }

    public function update(int $id, array $payload, int $actorAdminId): void
    {
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

        $this->audit->logAdminEvent($actorAdminId, 'admin_updated', 'admin', $id, ['role' => $payload['role'], 'status' => $payload['status']]);
    }
}
