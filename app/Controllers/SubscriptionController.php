<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentReconciliationService;
use App\Services\PaymentService;
use App\Services\RateLimiterService;
use App\Services\SubscriptionService;
use RuntimeException;

final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService = new PaymentService(),
        private readonly PaymentReconciliationService $reconciliation = new PaymentReconciliationService(),
        private readonly SubscriptionService $subscriptionService = new SubscriptionService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService()
    ) {
    }

    public function status(): void
    {
        $userId = Auth::id() ?? 0;
        $data = [
            'ok' => true,
            'days_remaining' => $this->subscriptionService->getDaysRemaining($userId),
            'has_active_subscription' => $this->subscriptionService->userHasActiveSubscription($userId),
        ];

        if (Request::input('format') === 'json') {
            Response::json($data);
        }

        $this->view('subscription/status', ['title' => 'Estado da Subscrição', 'subscription' => $data]);
    }

    public function renew(): void
    {
        $userId = Auth::id() ?? 0;
        $key = 'subscription_renew:' . $userId . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('subscription_renew', $key, 6, 10)) {
            Response::json(['ok' => false, 'message' => 'Muitas tentativas de renovação.'], 429);
        }

        try {
            $result = $this->paymentService->initiateSubscriptionPayment($userId, (string) Request::input('phone', ''));
            $this->rateLimiter->hit('subscription_renew', $key, $userId);
            $gateway = $result['gateway'] ?? [];
            $paymentId = (int) ($result['payment_id'] ?? 0);
            $reference = $this->paymentService->extractGatewayReference(is_array($gateway) ? $gateway : []);
            if ($paymentId <= 0 || $reference === '') {
                throw new RuntimeException('Pagamento iniciado, mas não foi possível confirmar a referência da transação.');
            }

            $poll = $this->reconciliation->pollUntilFinal($paymentId, $userId, 'subscription', $reference);
            $status = (string) ($poll['status'] ?? 'pending');

            if (Request::expectsJson()) {
                $ok = $status === 'completed';
                Response::json([
                    'ok' => $ok,
                    'status' => $status,
                    'next' => $ok ? '/dashboard' : null,
                    'message' => $ok ? 'Pagamento confirmado com sucesso.' : 'Pagamento ainda pendente ou não confirmado.',
                    'payment' => $result,
                    'polling' => $poll,
                ], $ok ? 200 : 422);
            }

            if ($status === 'completed') {
                Flash::set('success', 'Subscrição renovada com sucesso.');
                Response::redirect('/dashboard');
            }

            Flash::set('error', 'Pagamento não confirmado. Tente novamente em instantes.');
            Response::redirect('/subscription/status');
        } catch (RuntimeException $exception) {
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }

            Flash::set('error', $exception->getMessage());
            Response::redirect('/subscription/status');
        }
    }
}
