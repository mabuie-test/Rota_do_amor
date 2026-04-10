<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\MessageService;
use App\Services\RateLimiterService;

final class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $service = new MessageService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService()
    )
    {
    }

    public function index(): void
    {
        $userId = Auth::id() ?? 0;
        $search = trim((string) Request::input('q', ''));
        $conversations = $this->service->listConversations($userId, $search);
        $this->view('messages/index', ['title' => 'Mensagens', 'conversations' => $conversations, 'search' => $search]);
    }

    public function show(array $params): void
    {
        $conversationId = (int) ($params['conversation'] ?? 0);
        $userId = Auth::id() ?? 0;
        if (!$this->service->isConversationParticipant($conversationId, $userId)) {
            Response::abort(403, 'Acesso negado');
        }

        $messages = $this->service->getConversationMessages($conversationId, $userId);
        $this->service->markAsRead($conversationId, $userId);
        $this->view('messages/show', ['title' => 'Conversa', 'messages' => $messages]);
    }

    public function send(): void
    {
        $senderId = Auth::id() ?? 0;
        $rateLimitKey = 'chat_send:' . $senderId . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('chat_send', $rateLimitKey, 20, 1)) {
            Response::json(['ok' => false, 'message' => 'Muitas mensagens em pouco tempo.'], 429);
        }

        $messageId = $this->service->sendMessage(
            $senderId,
            (int) Request::input('receiver_id', 0),
            (string) Request::input('message_text', ''),
            (string) Request::input('message_type', 'text')
        );
        $this->rateLimiter->hit('chat_send', $rateLimitKey, $senderId);

        Response::json(['ok' => $messageId > 0, 'message_id' => $messageId], $messageId > 0 ? 200 : 422);
    }
}
