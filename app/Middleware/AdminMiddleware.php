<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use App\Core\Session;

final class AdminMiddleware
{
    public function handle(callable $next): mixed
    {
        $adminId = (int) Session::get('admin_id', 0);
        if ($adminId <= 0) {
            Response::redirect('/admin/login');
        }

        $stmt = \App\Core\Database::connection()->prepare('SELECT status, role FROM admins WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $adminId]);
        $admin = $stmt->fetch();
        if (!$admin || (string) ($admin['status'] ?? 'inactive') !== 'active') {
            Session::forget('admin_id');
            Session::forget('admin_role');
            Response::redirect('/admin/login');
        }
        Session::put('admin_role', (string) ($admin['role'] ?? 'moderator'));

        return $next();
    }
}
