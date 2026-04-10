<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\RateLimiterService;
use App\Services\ReportService;

final class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $service = new ReportService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService()
    )
    {
    }

    public function store(): void
    {
        $userId = Auth::id() ?? 0;
        $key = 'report_create:' . $userId . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('report_create', $key, 10, 10)) {
            Response::json(['ok' => false, 'message' => 'Muitas denúncias em pouco tempo.'], 429);
        }

        $id = $this->service->createReport(
            $userId,
            (string) Request::input('report_type', 'profile'),
            (string) Request::input('reason', ''),
            [
                'target_user_id' => Request::input('target_user_id'),
                'target_post_id' => Request::input('target_post_id'),
                'target_message_id' => Request::input('target_message_id'),
            ],
            (string) Request::input('details', '')
        );
        $this->rateLimiter->hit('report_create', $key, $userId);

        Response::json(['ok' => true, 'report_id' => $id]);
    }
}
