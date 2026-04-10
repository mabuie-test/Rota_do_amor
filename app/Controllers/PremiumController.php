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
use RuntimeException;

final class PremiumController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService = new PaymentService(),
        private readonly PaymentReconciliationService $reconciliation = new PaymentReconciliationService(),
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
            $gateway = $result['gateway'] ?? [];
            $paymentId = (int) ($result['payment_id'] ?? 0);
            $reference = $this->paymentService->extractGatewayReference(is_array($gateway) ? $gateway : []);
            if ($paymentId <= 0 || $reference === '') {
                throw new RuntimeException('Pagamento iniciado, mas não foi possível confirmar a referência da transação.');
            }

            $poll = $this->reconciliation->pollUntilFinal($paymentId, $userId, 'boost', $reference);
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
                Flash::set('success', 'Boost activado com sucesso.');
                Response::redirect('/dashboard');
            }

            Flash::set('error', 'Pagamento do boost não confirmado. Tente novamente em instantes.');
            Response::redirect('/premium');
        } catch (RuntimeException $exception) {
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }

            Flash::set('error', $exception->getMessage());
            Response::redirect('/premium');
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
