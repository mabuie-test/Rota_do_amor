<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;
use App\Services\RateLimiterService;
use RuntimeException;

final class PremiumController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService = new PaymentService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService()
    )
    {
    }

    public function show(): void
    {
        $this->view('premium/index', ['title' => 'Premium']);
    }

    public function payBoost(): void
    {
        $userId = Auth::id() ?? 0;
        $key = 'boost_pay:' . $userId . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('boost_pay', $key, 8, 10)) {
            Response::json(['ok' => false, 'message' => 'Muitas tentativas de boost.'], 429);
        }

        try {
            $result = $this->paymentService->initiateBoostPayment($userId, (string) Request::input('phone', ''));
            $this->rateLimiter->hit('boost_pay', $key, $userId);
            Response::json(['ok' => true, 'payment' => $result]);
        } catch (RuntimeException $exception) {
            Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function boostStatus(): void
    {
        $reference = (string) Request::input('reference', '');
        if ($reference === '') {
            Response::json(['ok' => false, 'message' => 'Informe reference'], 422);
        }

        Response::json(['ok' => true, 'gateway' => $this->paymentService->checkPaymentStatus($reference)]);
    }
}
