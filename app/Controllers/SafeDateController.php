<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\DailyRouteService;
use App\Services\SafeDateService;

final class SafeDateController extends Controller
{
    public function __construct(
        private readonly SafeDateService $service = new SafeDateService(),
        private readonly DailyRouteService $dailyRoutes = new DailyRouteService()
    )
    {
    }

    public function index(): void
    {
        $userId = Auth::id() ?? 0;
        $scope = (string) Request::input('scope', 'upcoming');
        $items = $this->service->listForUser($userId, $scope);

        $this->view('safe-dates/index', [
            'title' => 'Encontro Seguro',
            'items' => $items,
            'scope' => $scope,
            'prefill_invitee_id' => (int) Request::input('invitee_user_id', 0),
            'prefill_conversation_id' => (int) Request::input('conversation_id', 0),
        ]);
    }

    public function show(array $params): void
    {
        $userId = Auth::id() ?? 0;
        $safeDateId = (int) ($params['id'] ?? 0);
        $safeDate = $this->service->detailForUser($safeDateId, $userId);

        if ($safeDate === []) {
            Response::abort(404, 'Encontro seguro não encontrado.');
        }

        $this->view('safe-dates/show', [
            'title' => 'Detalhe do Encontro Seguro',
            'safe_date' => $safeDate,
        ]);
    }

    public function propose(): void
    {
        $userId = Auth::id() ?? 0;
        $result = $this->service->propose($userId, Request::all());
        if (!empty($result['ok'])) {
            $this->dailyRoutes->trackAction($userId, 'safe_date_proposed', 1);
        }

        if (Request::expectsJson()) {
            Response::json($result, !empty($result['ok']) ? 200 : 422);
        }

        if (!empty($result['ok'])) {
            Flash::set('success', 'Encontro Seguro proposto com sucesso.');
            Response::redirect('/dates/' . (int) ($result['safe_date_id'] ?? 0));
        }

        Flash::set('error', (string) ($result['message'] ?? 'Não foi possível criar o encontro seguro.'));
        Response::redirect('/dates');
    }

    public function accept(array $params): void
    {
        $this->handleTransition('accept', (int) ($params['id'] ?? 0));
    }

    public function decline(array $params): void
    {
        $this->handleTransition('decline', (int) ($params['id'] ?? 0), (string) Request::input('reason', ''));
    }

    public function cancel(array $params): void
    {
        $this->handleTransition('cancel', (int) ($params['id'] ?? 0), (string) Request::input('reason', ''));
    }

    public function reschedule(array $params): void
    {
        $safeDateId = (int) ($params['id'] ?? 0);
        $result = $this->service->requestReschedule(
            $safeDateId,
            Auth::id() ?? 0,
            (string) Request::input('proposed_datetime', ''),
            (string) Request::input('reason', '')
        );

        $this->respondTransition($safeDateId, $result, 'Remarcação solicitada com sucesso.');
    }

    public function respondReschedule(array $params): void
    {
        $safeDateId = (int) ($params['id'] ?? 0);
        $accept = (bool) Request::input('accept', false);
        $result = $this->service->respondReschedule($safeDateId, Auth::id() ?? 0, $accept, (string) Request::input('reason', ''));

        if (!empty($result['ok']) && $accept) {
            $this->dailyRoutes->trackAction(Auth::id() ?? 0, 'safe_date_accepted', 1);
        }

        $this->respondTransition($safeDateId, $result, $accept ? 'Remarcação confirmada.' : 'Remarcação recusada.');
    }

    public function complete(array $params): void
    {
        $this->handleTransition('complete', (int) ($params['id'] ?? 0));
    }

    public function markArrived(array $params): void
    {
        $safeDateId = (int) ($params['id'] ?? 0);
        $result = $this->service->markArrived($safeDateId, Auth::id() ?? 0);
        $this->respondTransition($safeDateId, $result, 'Check-in de chegada registado.');
    }

    public function markFinishedWell(array $params): void
    {
        $safeDateId = (int) ($params['id'] ?? 0);
        $result = $this->service->markFinishedWell($safeDateId, Auth::id() ?? 0);
        $this->respondTransition($safeDateId, $result, 'Confirmação de término em segurança registada.');
    }

    public function feedback(array $params): void
    {
        $safeDateId = (int) ($params['id'] ?? 0);
        $result = $this->service->savePrivateFeedback($safeDateId, Auth::id() ?? 0, Request::all());
        $this->respondTransition($safeDateId, $result, 'Feedback privado guardado com sucesso.');
    }

    private function handleTransition(string $action, int $safeDateId, ?string $reason = null): void
    {
        $result = match ($action) {
            'accept' => $this->service->accept($safeDateId, Auth::id() ?? 0),
            'decline' => $this->service->decline($safeDateId, Auth::id() ?? 0, $reason),
            'cancel' => $this->service->cancel($safeDateId, Auth::id() ?? 0, $reason),
            'complete' => $this->service->complete($safeDateId, Auth::id() ?? 0),
            default => ['ok' => false, 'message' => 'Ação inválida'],
        };

        $success = [
            'accept' => 'Encontro aceite com sucesso.',
            'decline' => 'Encontro recusado.',
            'cancel' => 'Encontro cancelado.',
            'complete' => 'Encontro marcado como concluído.',
        ];

        if (!empty($result['ok']) && $action === 'accept') {
            $this->dailyRoutes->trackAction(Auth::id() ?? 0, 'safe_date_accepted', 1);
        }
        if (!empty($result['ok']) && $action === 'complete') {
            $this->dailyRoutes->trackAction(Auth::id() ?? 0, 'safe_date_completed', 1);
        }

        $this->respondTransition($safeDateId, $result, $success[$action] ?? 'Operação concluída.');
    }

    private function respondTransition(int $safeDateId, array $result, string $successMessage): void
    {
        if (Request::expectsJson()) {
            Response::json($result, !empty($result['ok']) ? 200 : 422);
        }

        Flash::set(!empty($result['ok']) ? 'success' : 'error', !empty($result['ok']) ? $successMessage : (string) ($result['message'] ?? 'Operação inválida.'));
        Response::redirect('/dates/' . $safeDateId);
    }
}
