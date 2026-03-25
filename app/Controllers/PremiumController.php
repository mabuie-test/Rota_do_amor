<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Services\PaymentService;

final class PremiumController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService = new PaymentService()) {}
    public function show(): void { $this->view('premium/index', ['title' => 'Premium']); }
    public function payBoost(): void { $this->json($this->paymentService->initiateBoostPayment(Auth::id() ?? 1, (string) Request::input('phone', ''))); }
    public function boostStatus(): void { $this->json(['message' => 'Consulte pagamentos pendentes para estado atualizado']); }
}
