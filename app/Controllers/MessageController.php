<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\DailyRouteEventBridge;
use App\Services\MessageService;
use App\Services\RateLimiterService;
use App\Services\SafeDateService;
use App\Services\UploadService;
use RuntimeException;

final class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $service = new MessageService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService(),
        private readonly UploadService $uploads = new UploadService(),
        private readonly DailyRouteEventBridge $dailyRoutes = new DailyRouteEventBridge(),
        private readonly SafeDateService $safeDates = new SafeDateService()
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
            $otherUserId = (int) ($activeContext['other_user_id'] ?? 0);
            $proposalContext = $otherUserId > 0
                ? $this->safeDates->proposalContextForPair($userId, $otherUserId)
                : ['ok' => false, 'safety_capabilities' => []];
            $activeContext['can_propose_safe_date'] = !empty($proposalContext['ok']);
            $activeContext['safe_date_safety_capabilities'] = is_array($proposalContext['safety_capabilities'] ?? null)
                ? $proposalContext['safety_capabilities']
                : [];
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
            $this->respondMessageFailure('Muitas mensagens em pouco tempo.', '/messages', 'rate_limited', 429);
        }
        $this->rateLimiter->hit('chat_send', $rateLimitKey, $senderId);

        $attachments = [];
        $messageType = (string) Request::input('message_type', 'text');
        $receiverId = (int) Request::input('receiver_id', 0);

        try {
            if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $storedAttachment = $this->uploads->storeImage($_FILES['image'], 'messages/chat');
                $path = trim((string) ($storedAttachment['path'] ?? ''));
                if ($path === '') {
                    throw new RuntimeException('Falha no upload do anexo da mensagem.');
                }

                $attachments[] = $storedAttachment;
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
                $this->dailyRoutes->trackFromModule($senderId, DailyRouteEventBridge::EVENT_MESSAGE_SENT, 'messages', 1);
                if (Request::expectsJson()) {
                    $this->jsonOutcome(true, 'Mensagem enviada.', 'message_sent', $this->service->getMessageById($messageId, $senderId), $messageId, $receiverId);
                }

                $redirectConversation = $this->service->getOrCreateConversation($senderId, $receiverId);
                Response::redirect('/messages?conversation=' . $redirectConversation);
            }

            foreach ($attachments as $file) {
                $this->uploads->deleteImageBundle($file);
            }
            $this->rateLimiter->hitFailure('chat_send', $rateLimitKey, $senderId, ['reason' => 'send_rejected']);
            $this->respondMessageFailure('Não foi possível enviar a mensagem.', '/messages', 'message_rejected');
        } catch (RuntimeException $exception) {
            foreach ($attachments as $file) {
                $this->uploads->deleteImageBundle($file);
            }
            $this->rateLimiter->hitFailure('chat_send', $rateLimitKey, $senderId, ['reason' => 'upload_rejected']);
            $this->respondMessageFailure($exception->getMessage(), '/messages', 'upload_failed');
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
        $this->service->markAsDelivered($conversationId, $userId, $afterId);
        $this->service->markAsRead($conversationId, $userId, $afterId);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        $startedAt = time();
        $presenceTouchedAt = 0;
        while (time() - $startedAt < 25) {
            if ((time() - $presenceTouchedAt) >= 12) {
                $this->service->touchPresence($userId);
                $presenceTouchedAt = time();
            }

            $checkpointId = $afterId;
            $updates = $this->service->getConversationUpdates($conversationId, $userId, $afterId);
            $messages = $updates['messages'] ?? [];
            if ($messages !== []) {
                $afterId = (int) end($messages)['id'];
                $this->service->markAsDelivered($conversationId, $userId, $checkpointId);
                $this->service->markAsRead($conversationId, $userId, $checkpointId);
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
            $this->jsonOutcome(false, 'Acesso negado à conversa.', 'typing_state_updated', null, 0, $conversationId, 'forbidden', [], 403);
        }

        $this->service->setTypingState($conversationId, $userId, $typing);
        $this->service->touchPresence($userId);
        $this->jsonOutcome(true, 'Estado de escrita atualizado.', 'typing_state_updated', ['typing' => $typing], 0, $conversationId);
    }

    private function respondMessageFailure(string $message, string $redirectPath, string $errorCode, int $status = 422): never
    {
        if (Request::expectsJson()) {
            $this->jsonOutcome(false, $message, 'message_send_failed', null, 0, (int) Request::input('receiver_id', 0), $errorCode, [], $status);
        }

        Flash::set('error', $message);
        Response::redirect($redirectPath);
    }
}
