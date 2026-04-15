<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\ConnectionInviteService;
use App\Services\DailyRouteEventBridge;
use App\Services\SafeDateService;

final class ConnectionInviteController extends Controller
{
    public function __construct(
        private readonly ConnectionInviteService $service = new ConnectionInviteService(),
        private readonly DailyRouteEventBridge $dailyRoutes = new DailyRouteEventBridge(),
        private readonly SafeDateService $safeDates = new SafeDateService()
    )
    {
    }

    public function received(): void
    {
        $userId = Auth::id() ?? 0;
        $filters = [
            'status' => Request::input('status'),
            'invitation_type' => Request::input('invitation_type'),
            'page' => (int) Request::input('page', 1),
            'per_page' => (int) Request::input('per_page', 12),
        ];

        $listing = $this->service->listReceived($userId, $filters);
        $safeDateCapabilitiesMap = $this->safeDates->eligibleProfileCapabilitiesMapForUser($userId);
        $safeDateEligibleMap = [];
        foreach ($safeDateCapabilitiesMap as $counterpartId => $capabilities) {
            if (!empty($capabilities['can_standard'])) {
                $safeDateEligibleMap[(int) $counterpartId] = true;
            }
        }
        $this->view('invites/received', [
            'title' => 'Convites Recebidos com Intenção',
            'invites' => $listing['items'],
            'pagination' => $listing['pagination'],
            'filters' => $filters,
            'safe_date_eligible_map' => $safeDateEligibleMap,
            'safe_date_capabilities_map' => $safeDateCapabilitiesMap,
        ]);
    }

    public function sent(): void
    {
        $userId = Auth::id() ?? 0;
        $filters = [
            'status' => Request::input('status'),
            'page' => (int) Request::input('page', 1),
            'per_page' => (int) Request::input('per_page', 12),
        ];

        $listing = $this->service->listSent($userId, $filters);
        $this->view('invites/sent', [
            'title' => 'Convites Enviados',
            'invites' => $listing['items'],
            'pagination' => $listing['pagination'],
            'filters' => $filters,
        ]);
    }

    public function send(): void
    {
        $senderId = Auth::id() ?? 0;
        $result = $this->service->sendInvite(
            $senderId,
            (int) Request::input('receiver_user_id', 0),
            (string) Request::input('invitation_type', 'standard'),
            Request::input('opening_message') !== null ? (string) Request::input('opening_message') : null
        );

        if (!empty($result['ok'])) {
            $this->dailyRoutes->trackFromModule($senderId, DailyRouteEventBridge::EVENT_INVITE_SENT, 'invites', 1);
        }

        if (Request::expectsJson()) {
            Response::json($result, !empty($result['ok']) ? 200 : 422);
        }

        Flash::set(!empty($result['ok']) ? 'success' : 'error', (string) ($result['message'] ?? (!empty($result['ok']) ? 'Convite enviado com sucesso.' : 'Não foi possível enviar convite.')));
        Response::redirect((string) ($_SERVER['HTTP_REFERER'] ?? '/discover'));
    }

    public function accept(): void
    {
        $result = $this->service->acceptInvite((int) Request::input('invite_id', 0), Auth::id() ?? 0);

        if (Request::expectsJson()) {
            Response::json($result, !empty($result['ok']) ? 200 : 422);
        }

        if (!empty($result['ok'])) {
            Flash::set('success', 'Convite aceite. Conversa pronta para começar.');
            Response::redirect('/messages?conversation=' . (int) ($result['conversation_id'] ?? 0));
        }

        Flash::set('error', (string) ($result['message'] ?? 'Não foi possível aceitar o convite.'));
        Response::redirect('/invites/received');
    }

    public function decline(): void
    {
        $result = $this->service->declineInvite((int) Request::input('invite_id', 0), Auth::id() ?? 0);

        if (Request::expectsJson()) {
            Response::json($result, !empty($result['ok']) ? 200 : 422);
        }

        Flash::set(!empty($result['ok']) ? 'success' : 'error', (string) ($result['message'] ?? 'Não foi possível recusar o convite.'));
        Response::redirect('/invites/received');
    }
}
