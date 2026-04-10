<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\MessageService;
use App\Services\RateLimiterService;
use App\Services\UploadService;
use RuntimeException;

final class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $service = new MessageService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService(),
        private readonly UploadService $uploads = new UploadService()
    )
    {
    }

    public function index(): void
    {
        $userId = Auth::id() ?? 0;
        $search = trim((string) Request::input('q', ''));
        $activeConversationId = (int) Request::input('conversation', 0);
        $page = max(1, (int) Request::input('page', 1));
        $conversations = $this->service->listConversations($userId, $search);
        if ($activeConversationId <= 0 && $conversations !== []) {
            $activeConversationId = (int) ($conversations[0]['id'] ?? 0);
        }

        $activeConversation = ['items' => [], 'pagination' => ['page' => 1, 'per_page' => 40, 'has_more' => false]];
        $activeContext = [];
        if ($activeConversationId > 0 && $this->service->isConversationParticipant($activeConversationId, $userId)) {
            $activeConversation = $this->service->getConversationMessages($activeConversationId, $userId, $page, 40);
            $activeContext = $this->service->getConversationContext($activeConversationId, $userId);
            $this->service->markAsRead($activeConversationId, $userId);
        }

        $this->view('messages/index', [
            'title' => 'Mensagens',
            'conversations' => $conversations,
            'search' => $search,
            'active_conversation_id' => $activeConversationId,
            'messages' => $activeConversation['items'] ?? [],
            'pagination' => $activeConversation['pagination'] ?? [],
            'context' => $activeContext,
        ]);
    }

    public function show(array $params): void
    {
        $conversationId = (int) ($params['conversation'] ?? 0);
        $userId = Auth::id() ?? 0;
        if (!$this->service->isConversationParticipant($conversationId, $userId)) {
            Response::abort(403, 'Acesso negado');
        }

        $page = max(1, (int) Request::input('page', 1));
        $conversation = $this->service->getConversationMessages($conversationId, $userId, $page, 40);
        $context = $this->service->getConversationContext($conversationId, $userId);
        $this->service->markAsRead($conversationId, $userId);
        $this->view('messages/show', ['title' => 'Conversa', 'messages' => $conversation['items'], 'pagination' => $conversation['pagination'], 'context' => $context]);
    }

    public function send(): void
    {
        $senderId = Auth::id() ?? 0;
        $rateLimitKey = 'chat_send:' . $senderId . ':' . Request::ip();
        if (
            $this->rateLimiter->tooManyAttempts('chat_send', $rateLimitKey, 30, 1, 'any')
            || $this->rateLimiter->tooManyAttempts('chat_send', $rateLimitKey, 18, 1, 'success')
            || $this->rateLimiter->tooManyAttempts('chat_send', $rateLimitKey, 10, 1, 'failed')
        ) {
            Response::json(['ok' => false, 'message' => 'Muitas mensagens em pouco tempo.'], 429);
        }
        $this->rateLimiter->hit('chat_send', $rateLimitKey, $senderId);

        $attachments = [];
        $messageType = (string) Request::input('message_type', 'text');

        try {
            if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $attachments[] = $this->uploads->storeImage($_FILES['image'], 'messages/chat');
                $messageType = 'image';
            }

            $messageId = $this->service->sendMessage(
                $senderId,
                (int) Request::input('receiver_id', 0),
                (string) Request::input('message_text', ''),
                $messageType,
                $attachments
            );

            if ($messageId > 0) {
                $this->rateLimiter->hitSuccess('chat_send', $rateLimitKey, $senderId, ['message_type' => $messageType]);
                Response::json(['ok' => true, 'message_id' => $messageId], 200);
            }

            foreach ($attachments as $file) {
                $this->uploads->deleteImageBundle($file);
            }
            $this->rateLimiter->hitFailure('chat_send', $rateLimitKey, $senderId, ['reason' => 'send_rejected']);
            Response::json(['ok' => false, 'message_id' => 0], 422);
        } catch (RuntimeException $exception) {
            foreach ($attachments as $file) {
                $this->uploads->deleteImageBundle($file);
            }
            $this->rateLimiter->hitFailure('chat_send', $rateLimitKey, $senderId, ['reason' => 'upload_rejected']);
            Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
