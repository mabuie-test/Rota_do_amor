<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Services\PaymentService;
use App\Services\SubscriptionService;

final class SubscriptionController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService = new PaymentService(), private readonly SubscriptionService $subscriptionService = new SubscriptionService()) {}
    public function status(): void { $this->json(['days_remaining' => $this->subscriptionService->getDaysRemaining(Auth::id() ?? 1)]); }
    public function renew(): void { $this->json($this->paymentService->initiateSubscriptionPayment(Auth::id() ?? 1, (string) Request::input('phone', ''))); }
}
