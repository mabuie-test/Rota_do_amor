<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;
use RuntimeException;

final class ActivationController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService = new PaymentService())
    {
    }

    public function show(): void
    {
        $this->view('activation/index', ['title' => 'Ativação']);
    }

    public function pay(): void
    {
        try {
            $result = $this->paymentService->initiateActivationPayment(Auth::id() ?? 0, (string) Request::input('phone', ''));
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
