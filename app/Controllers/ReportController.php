<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ReportService;

final class ReportController extends Controller
{
    public function __construct(private readonly ReportService $service = new ReportService())
    {
    }

    public function store(): void
    {
        $id = $this->service->createReport(
            Auth::id() ?? 0,
            (string) Request::input('report_type', 'profile'),
            (string) Request::input('reason', ''),
            [
                'target_user_id' => Request::input('target_user_id'),
                'target_post_id' => Request::input('target_post_id'),
                'target_message_id' => Request::input('target_message_id'),
            ],
            (string) Request::input('details', '')
        );

        Response::json(['ok' => true, 'report_id' => $id]);
    }
}
