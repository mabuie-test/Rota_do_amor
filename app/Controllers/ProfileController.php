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
        private readonly UserDashboardService $dashboard = new UserDashboardService()
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
        ]);
    }

    public function update(): void
    {
        $ok = $this->userService->updateProfile(Auth::id() ?? 0, Request::all());

        if (Request::expectsJson()) {
            Response::json(['ok' => $ok], $ok ? 200 : 422);
        }

        Flash::set($ok ? 'success' : 'error', $ok ? 'Perfil atualizado.' : 'Não foi possível atualizar o perfil.');
        Response::redirect('/profile');
    }

    public function updateInterests(): void
    {
        $userId = Auth::id() ?? 0;
        $raw = (string) Request::input('interests', '');
        $interests = array_values(array_filter(array_unique(array_map(
            static fn(string $item): string => mb_substr(trim($item), 0, 120),
            preg_split('/[\n,;]/', $raw) ?: []
        )), static fn(string $item): bool => $item !== ''));

        $ok = $this->profileService->syncInterests($userId, $interests);
        Flash::set($ok ? 'success' : 'error', $ok ? 'Interesses atualizados.' : 'Não foi possível atualizar interesses.');
        Response::redirect('/profile');
    }

    public function updatePreferences(): void
    {
        $ok = $this->profileService->upsertPreferences(Auth::id() ?? 0, Request::all());
        Flash::set($ok ? 'success' : 'error', $ok ? 'Preferências atualizadas.' : 'Não foi possível atualizar preferências.');
        Response::redirect('/profile');
    }

    public function updateConnectionMode(): void
    {
        $userId = Auth::id() ?? 0;
        $ok = $this->connectionModes->upsertForUser($userId, Request::all());

        Flash::set($ok ? 'success' : 'error', $ok
            ? 'Modo do Coração e Ritmo Relacional atualizados com sucesso.'
            : 'Não foi possível atualizar o Modo do Coração.');

        Response::redirect('/profile');
    }

    public function photo(): void
    {
        try {
            $stored = $this->uploads->storeImage($_FILES['photo'] ?? [], 'profiles');
            $id = $this->profileService->savePhoto(Auth::id() ?? 0, $stored['path'], true);
            if (Request::expectsJson()) {
                Response::json(['ok' => true, 'photo_id' => $id, 'path' => $stored['path']]);
            }
            Flash::set('success', 'Foto principal atualizada.');
            Response::redirect('/profile');
        } catch (RuntimeException $exception) {
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }
            Flash::set('error', $exception->getMessage());
            Response::redirect('/profile');
        }
    }

    public function gallery(): void
    {
        try {
            $stored = $this->uploads->storeImage($_FILES['photo'] ?? [], 'gallery');
            $id = $this->profileService->savePhoto(Auth::id() ?? 0, $stored['path'], false);
            if (Request::expectsJson()) {
                Response::json(['ok' => true, 'photo_id' => $id, 'path' => $stored['path']]);
            }
            Flash::set('success', 'Foto adicionada à galeria.');
            Response::redirect('/profile');
        } catch (RuntimeException $exception) {
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }
            Flash::set('error', $exception->getMessage());
            Response::redirect('/profile');
        }
    }

    public function setPrimaryPhoto(): void
    {
        $ok = $this->profileService->setPrimaryPhoto(Auth::id() ?? 0, (int) Request::input('photo_id', 0));
        if (Request::expectsJson()) {
            Response::json(['ok' => $ok], $ok ? 200 : 422);
        }
        Flash::set($ok ? 'success' : 'error', $ok ? 'Foto principal definida.' : 'Foto inválida.');
        Response::redirect('/profile');
    }

    public function deletePhoto(): void
    {
        $ok = $this->profileService->deletePhoto(Auth::id() ?? 0, (int) Request::input('photo_id', 0));
        if (Request::expectsJson()) {
            Response::json(['ok' => $ok], $ok ? 200 : 422);
        }
        Flash::set($ok ? 'success' : 'error', $ok ? 'Foto removida.' : 'Foto inválida.');
        Response::redirect('/profile');
    }

    public function reorderGallery(): void
    {
        $photoIds = Request::input('photo_ids', []);
        if (!is_array($photoIds)) {
            Response::json(['ok' => false, 'message' => 'photo_ids inválido'], 422);
        }
        $this->profileService->reorderGallery(Auth::id() ?? 0, $photoIds);
        Response::json(['ok' => true]);
    }
}
