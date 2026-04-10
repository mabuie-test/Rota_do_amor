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

final class ActivationController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService = new PaymentService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService()
    )
    {
    }

    public function show(): void
    {
        $this->view('activation/index', ['title' => 'Ativação']);
    }

    public function pay(): void
    {
        $userId = Auth::id() ?? 0;
        $key = 'activation_pay:' . $userId . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('activation_pay', $key, 6, 10)) {
            Response::json(['ok' => false, 'message' => 'Muitas tentativas de pagamento.'], 429);
        }

        try {
            $result = $this->paymentService->initiateActivationPayment($userId, (string) Request::input('phone', ''));
            $this->rateLimiter->hit('activation_pay', $key, $userId);
            Response::json(['ok' => true, 'payment' => $result]);
        } catch (RuntimeException $exception) {
            Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function status(): void
    {
        Response::json($this->paymentService->checkPaymentStatus((string) Request::input('reference', '')));
    }
}
