<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AdminModerationController extends Controller
{
    public function index(): void
    {
        $rows = \App\Core\Database::connection()->query('SELECT * FROM moderation_actions ORDER BY id DESC LIMIT 500')->fetchAll();
        if (Request::input('format') === 'json') {
            Response::json(['moderation_actions' => $rows]);
        }

        $this->view('admin/moderation', ['title' => 'Admin · Moderação', 'actions' => $rows]);
    }

    public function suspend(): void
    {
        $this->apply('suspend');
    }

    public function ban(): void
    {
        $this->apply('ban');
    }

    private function apply(string $action): void
    {
        $userId = (int) Request::input('user_id', 0);
        $reason = (string) Request::input('reason', 'Ação administrativa');
        $db = \App\Core\Database::connection();
        $db->prepare('INSERT INTO moderation_actions (admin_id,user_id,action_type,reason,created_at) VALUES (:a,:u,:action,:reason,NOW())')->execute([
            ':a' => Session::get('admin_id', 0),
            ':u' => $userId,
            ':action' => $action,
            ':reason' => $reason,
        ]);
        $status = $action === 'ban' ? 'banned' : 'suspended';
        $db->prepare('UPDATE users SET status=:status,updated_at=NOW() WHERE id=:id')->execute([':status' => $status, ':id' => $userId]);
        Response::json(['ok' => true]);
    }
}
