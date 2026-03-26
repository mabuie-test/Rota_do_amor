<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\MessageService;

final class MessageController extends Controller
{
    public function __construct(private readonly MessageService $service = new MessageService())
    {
    }

    public function index(): void
    {
        $this->view('messages/index', ['title' => 'Mensagens']);
    }

    public function show(array $params): void
    {
        $messages = $this->service->getConversationMessages((int) ($params['conversation'] ?? 0));
        $this->view('messages/show', ['title' => 'Conversa', 'messages' => $messages]);
    }

    public function send(): void
    {
        $messageId = $this->service->sendMessage(
            Auth::id() ?? 0,
            (int) Request::input('receiver_id', 0),
            (string) Request::input('message_text', ''),
            (string) Request::input('message_type', 'text')
        );

        Response::json(['ok' => $messageId > 0, 'message_id' => $messageId]);
    }
}
