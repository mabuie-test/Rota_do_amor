<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
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
            if (Request::expectsJson()) {
                Response::json([
                    'ok' => false,
                    'message' => 'Muitas denúncias em pouco tempo.',
                    'action' => 'error',
                    'state' => null,
                    'created_id' => 0,
                    'target_id' => (int) Request::input('target_user_id', 0),
                    'error_code' => 'rate_limited',
                ], 429);
            }

            Flash::set('error', 'Muitas denúncias em pouco tempo.');
            Response::redirect('/feed');
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

        if ($id <= 0) {
            $this->rateLimiter->hitFailure('report_create', $key, $userId, ['reason' => 'report_not_created']);
            if (Request::expectsJson()) {
                Response::json([
                    'ok' => false,
                    'message' => 'Não foi possível registar esta denúncia.',
                    'action' => 'error',
                    'state' => null,
                    'created_id' => 0,
                    'target_id' => (int) Request::input('target_user_id', 0),
                    'error_code' => 'report_create_failed',
                ], 422);
            }

            Flash::set('error', 'Não foi possível enviar denúncia. Verifique os dados e tente novamente.');
            Response::redirect('/feed');
        }

        $this->rateLimiter->hitSuccess('report_create', $key, $userId, ['report_id' => $id]);

        if (Request::expectsJson()) {
            Response::json([
                'ok' => true,
                'message' => 'Denúncia enviada para revisão.',
                'action' => 'created',
                'state' => ['status' => 'pending'],
                'created_id' => $id,
                'target_id' => (int) Request::input('target_user_id', 0),
                'error_code' => null,
            ]);
        }

        Flash::set('success', 'Denúncia enviada para revisão.');
        Response::redirect('/feed');
    }
}
