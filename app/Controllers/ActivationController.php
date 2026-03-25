<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Services\PaymentService;

final class ActivationController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService = new PaymentService()) {}
    public function show(): void { $this->view('activation/index', ['title' => 'Ativação']); }
    public function pay(): void { $this->json($this->paymentService->initiateActivationPayment(Auth::id() ?? 1, (string) Request::input('phone', ''))); }
    public function status(): void { $this->json($this->paymentService->checkPaymentStatus((string) Request::input('reference', ''))); }
}
