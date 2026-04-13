<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\DailyRouteService;
use App\Services\MessageService;
use App\Services\RateLimiterService;
use App\Services\UploadService;
use RuntimeException;

final class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $service = new MessageService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService(),
        private readonly UploadService $uploads = new UploadService(),
        private readonly DailyRouteService $dailyRoutes = new DailyRouteService()
    )
    {
    }

    public function index(): void
    {
        $userId = Auth::id() ?? 0;
        $this->service->touchPresence($userId);

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
            $this->service->markAsDelivered($activeConversationId, $userId);
            $this->service->markAsRead($activeConversationId, $userId);
        }

        $this->view('messages/index', [
            'title' => 'Mensagens',
            'viewer_id' => $userId,
            'conversations' => $conversations,
            'search' => $search,
            'active_conversation_id' => $activeConversationId,
            'messages' => $activeConversation['items'] ?? [],
            'pagination' => $activeConversation['pagination'] ?? [],
            'context' => $activeContext,
        ]);
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
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => 'Muitas mensagens em pouco tempo.'], 429);
            }
            Flash::set('error', 'Muitas mensagens em pouco tempo.');
            Response::redirect('/messages');
        }
        $this->rateLimiter->hit('chat_send', $rateLimitKey, $senderId);

        $attachments = [];
        $messageType = (string) Request::input('message_type', 'text');
        $receiverId = (int) Request::input('receiver_id', 0);

        try {
            if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $attachments[] = $this->uploads->storeImage($_FILES['image'], 'messages/chat');
                $messageType = 'image';
            }

            $messageId = $this->service->sendMessage(
                $senderId,
                $receiverId,
                (string) Request::input('message_text', ''),
                $messageType,
                $attachments
            );

            if ($messageId > 0) {
                $this->rateLimiter->hitSuccess('chat_send', $rateLimitKey, $senderId, ['message_type' => $messageType]);
                $this->dailyRoutes->trackAction($senderId, 'message_sent', 1);
                if (Request::expectsJson()) {
                    Response::json(['ok' => true, 'message_id' => $messageId, 'message' => $this->service->getMessageById($messageId, $senderId)], 200);
                }

                $redirectConversation = $this->service->getOrCreateConversation($senderId, $receiverId);
                Response::redirect('/messages?conversation=' . $redirectConversation);
            }

            foreach ($attachments as $file) {
                $this->uploads->deleteImageBundle($file);
            }
            $this->rateLimiter->hitFailure('chat_send', $rateLimitKey, $senderId, ['reason' => 'send_rejected']);
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message_id' => 0], 422);
            }
            Flash::set('error', 'Não foi possível enviar a mensagem.');
            Response::redirect('/messages');
        } catch (RuntimeException $exception) {
            foreach ($attachments as $file) {
                $this->uploads->deleteImageBundle($file);
            }
            $this->rateLimiter->hitFailure('chat_send', $rateLimitKey, $senderId, ['reason' => 'upload_rejected']);
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }
            Flash::set('error', $exception->getMessage());
            Response::redirect('/messages');
        }
    }

    public function stream(): never
    {
        $userId = Auth::id() ?? 0;
        $conversationId = (int) Request::input('conversation_id', 0);
        $afterId = max(0, (int) Request::input('after_id', 0));

        if (!$this->service->isConversationParticipant($conversationId, $userId)) {
            Response::abort(403, 'Acesso negado');
        }

        $this->service->touchPresence($userId);
        $this->service->markAsDelivered($conversationId, $userId);
        $this->service->markAsRead($conversationId, $userId);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        $startedAt = time();
        while (time() - $startedAt < 25) {
            $this->service->markAsDelivered($conversationId, $userId);
            $updates = $this->service->getConversationUpdates($conversationId, $userId, $afterId);
            $messages = $updates['messages'] ?? [];
            if ($messages !== []) {
                $afterId = (int) end($messages)['id'];
            }

            $payload = [
                'messages' => $messages,
                'typing' => $updates['typing'] ?? [],
                'read_receipts' => $updates['read_receipts'] ?? [],
            ];
            echo "event: chat\n";
            echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            flush();
            sleep(2);
        }

        exit;
    }

    public function typing(): never
    {
        $userId = Auth::id() ?? 0;
        $conversationId = (int) Request::input('conversation_id', 0);
        $typing = (bool) Request::input('typing', false);

        if (!$this->service->isConversationParticipant($conversationId, $userId)) {
            Response::json(['ok' => false], 403);
        }

        $this->service->setTypingState($conversationId, $userId, $typing);
        $this->service->touchPresence($userId);
        Response::json(['ok' => true]);
    }
}
