<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;
use App\Services\RateLimiterService;
use App\Services\SubscriptionService;
use RuntimeException;

final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService = new PaymentService(),
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
            Response::json(['ok' => true, 'payment' => $result]);
        } catch (RuntimeException $exception) {
            Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
