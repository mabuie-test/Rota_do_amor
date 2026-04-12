<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AdminManagementService;
use Throwable;

final class AdminManagementController extends Controller
{
    public function __construct(private readonly AdminManagementService $service = new AdminManagementService())
    {
    }

    public function index(): void
    {
        $this->view('admin/admins', [
            'title' => 'Super Admin · Admins',
            'admins' => $this->service->listAdmins(),
            'roles' => $this->service->roles(),
            'permission_matrix' => $this->service->permissionMatrix(),
        ]);
    }

    public function create(): void
    {
        $payload = $this->payload();

        try {
            $this->service->create($payload, (int) Session::get('admin_id', 0));
            Flash::set('success', 'Admin criado com sucesso.');
        } catch (Throwable $e) {
            Flash::set('error', $e->getMessage());
        }

        Response::redirect('/admin/admins');
    }

    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('error', 'Admin inválido.');
            Response::redirect('/admin/admins');
        }

        try {
            $this->service->update($id, $this->payload(), (int) Session::get('admin_id', 0));
            Flash::set('success', 'Admin atualizado com sucesso.');
        } catch (Throwable $e) {
            Flash::set('error', $e->getMessage());
        }

        Response::redirect('/admin/admins');
    }

    private function payload(): array
    {
        $role = trim((string) Request::input('role', 'moderator'));
        if (!in_array($role, $this->service->roles(), true)) {
            $role = 'moderator';
        }

        return [
            'name' => trim((string) Request::input('name', '')),
            'email' => mb_strtolower(trim((string) Request::input('email', ''))),
            'password' => (string) Request::input('password', ''),
            'role' => $role,
            'status' => trim((string) Request::input('status', 'active')) === 'inactive' ? 'inactive' : 'active',
        ];
    }
}
