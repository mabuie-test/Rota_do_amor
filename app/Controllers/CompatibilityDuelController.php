<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\CompatibilityDuelService;
use App\Services\DailyRouteEventBridge;
use Throwable;

final class CompatibilityDuelController extends Controller
{
    public function __construct(
        private readonly CompatibilityDuelService $service = new CompatibilityDuelService(),
        private readonly DailyRouteEventBridge $dailyRoutes = new DailyRouteEventBridge()
    ) {
    }

    public function index(): void
    {
        $userId = Auth::id() ?? 0;
        $selectedDuelId = max(0, (int) Request::input('duel', 0));
        $duelContextStatus = 'active';
        try {
            $duel = $this->service->getOrCreateDailyDuel($userId);
            $policy = $this->service->premiumPolicy();
            if ($selectedDuelId > 0 && (int) ($duel['id'] ?? 0) !== $selectedDuelId) {
                $requested = $this->service->getDuelByIdForUser($userId, $selectedDuelId);
                if ($requested !== []) {
                    $duel = $requested;
                } else {
                    $duelContextStatus = 'fallback';
                    Flash::set('warning', 'O duelo da notificação não está mais disponível. Mostramos teu duelo atual.');
                }
            }
        } catch (Throwable $exception) {
            error_log('[compatibility_duel.index_fallback] ' . $exception->getMessage());
            $duel = [];
            $policy = ['free_daily_duels' => 1, 'premium_daily_duels' => 3, 'extra_duels_enabled' => false, 'premium_insights_enabled' => false];
            Flash::set('warning', 'Duelo indisponível temporariamente.');
        }
        if (!empty($duel)) {
            $this->dailyRoutes->trackFromModule($userId, 'compatibility_duel_joined', 'compatibility_duel', 1);
        }
        $this->view('compatibility-duel/index', [
            'title' => 'Duelo de Compatibilidade',
            'duel' => $duel,
            'premium_policy' => $policy,
            'selected_duel_id' => $selectedDuelId,
            'duel_context_status' => $duelContextStatus,
        ]);
    }

    public function vote(): void
    {
        $userId = Auth::id() ?? 0;
        $duelId = (int) Request::input('duel_id', 0);
        $ok = $this->service->vote($duelId, $userId, (int) Request::input('selected_option_id', 0));
        if ($ok) {
            $this->dailyRoutes->trackFromModule($userId, 'compatibility_duel_voted', 'compatibility_duel', 1);
            Flash::set('success', 'Voto registado.');
        } else {
            Flash::set('error', 'Não foi possível registar voto.');
        }

        Response::redirect('/compatibility-duel?duel=' . $duelId);
    }

    public function action(): void
    {
        $userId = Auth::id() ?? 0;
        $result = $this->service->registerAction((int) Request::input('duel_id', 0), $userId, (string) Request::input('action_type', 'view_profile'));
        $ok = (bool) ($result['success'] ?? false);
        if ($ok) {
            $this->dailyRoutes->trackFromModule($userId, 'compatibility_duel_action_taken', 'compatibility_duel', 1);
        }

        Response::json([
            'ok' => $ok,
            'message' => (string) ($result['message'] ?? ($ok ? 'Ação registada.' : 'Falha ao registar ação.')),
            'action' => (string) ($result['action'] ?? ($ok ? 'updated' : 'error')),
            'state' => $result['state'] ?? null,
            'created_id' => (int) ($result['created_id'] ?? 0),
            'target_id' => (int) ($result['target_id'] ?? 0),
            'error_code' => $result['error_code'] ?? null,
        ], $ok ? 200 : 422);
    }
}
