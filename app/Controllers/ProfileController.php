<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\BadgeService;
use App\Services\ConnectionModeService;
use App\Services\DailyRouteEventBridge;
use App\Services\ProfileService;
use App\Services\UploadService;
use App\Services\UserDashboardService;
use App\Services\UserService;
use RuntimeException;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService = new ProfileService(),
        private readonly UserService $userService = new UserService(),
        private readonly BadgeService $badgeService = new BadgeService(),
        private readonly UploadService $uploads = new UploadService(),
        private readonly ConnectionModeService $connectionModes = new ConnectionModeService(),
        private readonly UserDashboardService $dashboard = new UserDashboardService(),
        private readonly DailyRouteEventBridge $dailyRoutes = new DailyRouteEventBridge()
    ) {
    }

    public function index(): void
    {
        $userId = Auth::id() ?? 0;
        $profile = $this->profileService->getProfile($userId);
        $photos = $this->profileService->getUserPhotos($userId);
        $badges = $this->badgeService->getUserBadges($userId);
        $mode = $this->connectionModes->getForUser($userId);
        $insights = $this->dashboard->build($userId);
        $signals = $this->profileService->completionSignals($userId);

        $this->view('profile/index', [
            'title' => 'Meu Perfil',
            'profile' => $profile,
            'photos' => $photos,
            'badges' => $badges,
            'connection_mode' => $mode,
            'intention_options' => $this->connectionModes->intentionOptions(),
            'pace_options' => $this->connectionModes->paceOptions(),
            'openness_options' => $this->connectionModes->opennessOptions(),
            'interests' => $this->profileService->getInterests($userId),
            'preferences' => $this->profileService->getPreferences($userId),
            'provinces' => $this->userService->listProvinces(),
            'cities' => $this->userService->listCities(),
            'profile_checklist' => $insights['profile_checklist'] ?? [],
            'profile_completion_percent' => $insights['profile_completion_percent'] ?? 0,
            'profile_missing_items' => $insights['profile_missing_items'] ?? [],
            'profile_attractiveness_percent' => $insights['profile_attractiveness_percent'] ?? 0,
            'trust_indicator' => $insights['trust_indicator'] ?? 'Baixa',
            'completion_signals' => $signals,
        ]);
    }

    public function update(): void
    {
        $userId = Auth::id() ?? 0;
        $ok = $this->userService->updateProfile($userId, Request::all());
        if ($ok) {
            $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_PROFILE_UPDATED, 'profile', 1);
        }

        if (Request::expectsJson()) {
            $this->jsonOutcome($ok, $ok ? 'Perfil atualizado.' : 'Não foi possível atualizar o perfil.', 'profile_updated', null, 0, $userId, $ok ? null : 'profile_update_failed', [], $ok ? 200 : 422);
        }

        Flash::set($ok ? 'success' : 'error', $ok ? 'Perfil atualizado.' : 'Não foi possível atualizar o perfil.');
        Response::redirect('/profile');
    }

    public function updateInterests(): void
    {
        $userId = Auth::id() ?? 0;
        $input = Request::input('interests', '');
        $tokens = is_array($input) ? $input : (preg_split('/[\n,;]/', (string) $input) ?: []);
        $interests = array_values(array_filter(array_unique(array_map(
            static fn(string $item): string => mb_substr(trim($item), 0, 120),
            array_map(static fn(mixed $item): string => (string) $item, $tokens)
        )), static fn(string $item): bool => $item !== ''));

        $ok = $this->profileService->syncInterests($userId, $interests);
        if ($ok) {
            $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_PROFILE_INTERESTS_UPDATED, 'profile', 1);
        }
        Flash::set($ok ? 'success' : 'error', $ok ? 'Interesses atualizados.' : 'Não foi possível atualizar interesses.');
        Response::redirect('/profile');
    }

    public function updatePreferences(): void
    {
        $userId = Auth::id() ?? 0;
        $ok = $this->profileService->upsertPreferences($userId, Request::all());
        if ($ok) {
            $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_PROFILE_PREFERENCES_UPDATED, 'profile', 1);
        }
        Flash::set($ok ? 'success' : 'error', $ok ? 'Preferências atualizadas.' : 'Não foi possível atualizar preferências.');
        Response::redirect('/profile');
    }

    public function updateConnectionMode(): void
    {
        $userId = Auth::id() ?? 0;
        $ok = $this->connectionModes->upsertForUser($userId, Request::all());
        if ($ok) {
            $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_HEART_MODE_UPDATED, 'profile', 1);
        }

        Flash::set($ok ? 'success' : 'error', $ok
            ? 'Modo do Coração e Ritmo Relacional atualizados com sucesso.'
            : 'Não foi possível atualizar o Modo do Coração.');

        Response::redirect('/profile');
    }

    public function photo(): void
    {
        $userId = Auth::id() ?? 0;
        $stored = null;
        try {
            $stored = $this->uploads->storeImage($_FILES['photo'] ?? [], 'profiles');
            $id = $this->profileService->savePhoto($userId, $stored['path'], true);
            $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_PROFILE_PHOTO_UPLOADED, 'profile', 1);
            if (Request::expectsJson()) {
                $this->jsonOutcome(true, 'Foto principal atualizada.', 'profile_photo_uploaded', ['path' => $stored['path']], $id, $userId);
            }
            Flash::set('success', 'Foto principal atualizada.');
            Response::redirect('/profile');
        } catch (RuntimeException $exception) {
            if ($stored !== null) {
                $this->uploads->deleteImageBundle($stored);
            }
            if (Request::expectsJson()) {
                $this->jsonOutcome(false, $exception->getMessage(), 'profile_photo_upload_failed', null, 0, $userId, 'upload_failed');
            }
            Flash::set('error', $exception->getMessage());
            Response::redirect('/profile');
        }
    }

    public function gallery(): void
    {
        $userId = Auth::id() ?? 0;
        $stored = null;
        try {
            $stored = $this->uploads->storeImage($_FILES['photo'] ?? [], 'gallery');
            $id = $this->profileService->savePhoto($userId, $stored['path'], false);
            $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_PROFILE_PHOTO_UPLOADED, 'profile', 1);
            if (Request::expectsJson()) {
                $this->jsonOutcome(true, 'Foto adicionada à galeria.', 'profile_gallery_photo_uploaded', ['path' => $stored['path']], $id, $userId);
            }
            Flash::set('success', 'Foto adicionada à galeria.');
            Response::redirect('/profile');
        } catch (RuntimeException $exception) {
            if ($stored !== null) {
                $this->uploads->deleteImageBundle($stored);
            }
            if (Request::expectsJson()) {
                $this->jsonOutcome(false, $exception->getMessage(), 'profile_gallery_upload_failed', null, 0, $userId, 'upload_failed');
            }
            Flash::set('error', $exception->getMessage());
            Response::redirect('/profile');
        }
    }

    public function setPrimaryPhoto(): void
    {
        $photoId = (int) Request::input('photo_id', 0);
        $ok = $this->profileService->setPrimaryPhoto(Auth::id() ?? 0, $photoId);
        if (Request::expectsJson()) {
            $this->jsonOutcome($ok, $ok ? 'Foto principal definida.' : 'Foto inválida.', 'profile_primary_photo_set', null, 0, $photoId, $ok ? null : 'invalid_photo', [], $ok ? 200 : 422);
        }
        Flash::set($ok ? 'success' : 'error', $ok ? 'Foto principal definida.' : 'Foto inválida.');
        Response::redirect('/profile');
    }

    public function deletePhoto(): void
    {
        $photoId = (int) Request::input('photo_id', 0);
        $ok = $this->profileService->deletePhoto(Auth::id() ?? 0, $photoId);
        if (Request::expectsJson()) {
            $this->jsonOutcome($ok, $ok ? 'Foto removida.' : 'Foto inválida.', 'profile_photo_deleted', null, 0, $photoId, $ok ? null : 'invalid_photo', [], $ok ? 200 : 422);
        }
        Flash::set($ok ? 'success' : 'error', $ok ? 'Foto removida.' : 'Foto inválida.');
        Response::redirect('/profile');
    }

    public function reorderGallery(): void
    {
        $photoIds = Request::input('photo_ids', []);
        if (!is_array($photoIds)) {
            $this->jsonOutcome(false, 'photo_ids inválido', 'profile_gallery_reordered', null, 0, 0, 'invalid_payload', [], 422);
        }

        $ok = $this->profileService->reorderGallery(Auth::id() ?? 0, $photoIds);
        $this->jsonOutcome($ok, $ok ? 'Galeria reordenada.' : 'Lista de fotos inválida para reordenação.', 'profile_gallery_reordered', null, 0, 0, $ok ? null : 'invalid_gallery_order', [], $ok ? 200 : 422);
    }
}
