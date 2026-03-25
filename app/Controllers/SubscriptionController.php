<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use RuntimeException;

final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService = new PaymentService(),
        private readonly SubscriptionService $subscriptionService = new SubscriptionService()
    ) {
    }

    public function status(): void
    {
        Response::json([
            'ok' => true,
            'days_remaining' => $this->subscriptionService->getDaysRemaining(Auth::id() ?? 0),
            'has_active_subscription' => $this->subscriptionService->userHasActiveSubscription(Auth::id() ?? 0),
        ]);
    }

    public function renew(): void
    {
        try {
            $result = $this->paymentService->initiateSubscriptionPayment(Auth::id() ?? 0, (string) Request::input('phone', ''));
            Response::json(['ok' => true, 'payment' => $result]);
        } catch (RuntimeException $exception) {
            Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
