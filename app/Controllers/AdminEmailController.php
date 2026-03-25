<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\MailService;

final class AdminEmailController extends Controller
{
    public function __construct(private readonly MailService $mail = new MailService()) {}

    public function index(): void
    {
        $user = \App\Core\Database::connection()->prepare('SELECT id,email FROM users WHERE id=:id');
        $user->execute([':id' => (int) Request::input('user_id', 0)]);
        $row = $user->fetch();
        if (!$row) {
            Response::json(['ok' => false, 'message' => 'Usuário não encontrado'], 404);
        }

        $ok = $this->mail->sendGenericTemplate((int) $row['id'], (string) $row['email'], (string) Request::input('subject', 'Notificação administrativa'), 'welcome');
        Response::json(['ok' => $ok]);
    }
}
