<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;

final class AdminAuthorizationService
{
    public function resolveActiveAdminFromSession(): array
    {
        $adminId = (int) Session::get('admin_id', 0);
        if ($adminId <= 0) {
            return [];
        }

        $stmt = Database::connection()->prepare('SELECT id, role, status FROM admins WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $adminId]);
        $admin = $stmt->fetch() ?: [];

        if ($admin === [] || (string) ($admin['status'] ?? 'inactive') !== 'active') {
            $this->invalidateAdminSession();
            return [];
        }

        Session::put('admin_role', (string) ($admin['role'] ?? 'moderator'));
        Session::put('admin_authorized_at', date(DATE_ATOM));

        return $admin;
    }

    public function sessionAdminHasRole(array $allowedRoles): bool
    {
        $admin = $this->resolveActiveAdminFromSession();
        if ($admin === []) {
            return false;
        }

        return in_array((string) ($admin['role'] ?? ''), $allowedRoles, true);
    }

    public function invalidateAdminSession(): void
    {
        Session::forget('admin_id');
        Session::forget('admin_role');
        Session::forget('admin_authorized_at');
    }
}
