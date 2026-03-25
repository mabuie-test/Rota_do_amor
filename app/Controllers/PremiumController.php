<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\PaymentService;

final class PremiumController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService = new PaymentService())
    {
    }

    public function show(): void
    {
        $this->view('premium/index', ['title' => 'Premium']);
    }

    public function payBoost(): void
    {
        $result = $this->paymentService->initiateBoostPayment(Auth::id() ?? 0, (string) Request::input('phone', ''));
        Response::json(['ok' => true, 'payment' => $result]);
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
