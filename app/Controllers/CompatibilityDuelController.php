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
        try {
            $duel = $this->service->getOrCreateDailyDuel($userId);
            $policy = $this->service->premiumPolicy();
        } catch (Throwable $exception) {
            error_log('[compatibility_duel.index_fallback] ' . $exception->getMessage());
            $duel = [];
            $policy = ['free_daily_duels' => 1, 'premium_daily_duels' => 3, 'extra_duels_enabled' => false, 'premium_insights_enabled' => false];
            Flash::set('warning', 'Duelo indisponível temporariamente.');
        }
        if (!empty($duel)) {
            $this->dailyRoutes->trackFromModule($userId, 'compatibility_duel_joined', 'compatibility_duel', 1);
        }
        $this->view('compatibility-duel/index', ['title' => 'Duelo de Compatibilidade', 'duel' => $duel, 'premium_policy' => $policy]);
    }

    public function vote(): void
    {
        $userId = Auth::id() ?? 0;
        $ok = $this->service->vote((int) Request::input('duel_id', 0), $userId, (int) Request::input('selected_option_id', 0));
        if ($ok) {
            $this->dailyRoutes->trackFromModule($userId, 'compatibility_duel_voted', 'compatibility_duel', 1);
            Flash::set('success', 'Voto registado.');
        } else {
            Flash::set('error', 'Não foi possível registar voto.');
        }

        Response::redirect('/compatibility-duel');
    }

    public function action(): void
    {
        $userId = Auth::id() ?? 0;
        $ok = $this->service->registerAction((int) Request::input('duel_id', 0), $userId, (string) Request::input('action_type', 'view_profile'));
        if ($ok) {
            $this->dailyRoutes->trackFromModule($userId, 'compatibility_duel_action_taken', 'compatibility_duel', 1);
        }

        Response::json(['ok' => $ok], $ok ? 200 : 422);
    }
}
