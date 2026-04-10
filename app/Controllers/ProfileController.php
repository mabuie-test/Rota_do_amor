<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\BadgeService;
use App\Services\ProfileService;
use App\Services\UploadService;
use App\Services\UserService;
use RuntimeException;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService = new ProfileService(),
        private readonly UserService $userService = new UserService(),
        private readonly BadgeService $badgeService = new BadgeService(),
        private readonly UploadService $uploads = new UploadService()
    ) {
    }

    public function index(): void
    {
        $userId = Auth::id() ?? 0;
        $profile = $this->profileService->getProfile($userId);
        $badges = $this->badgeService->getUserBadges($userId);
        $this->view('profile/index', ['title' => 'Meu Perfil', 'profile' => $profile, 'badges' => $badges]);
    }

    public function update(): void
    {
        $ok = $this->userService->updateProfile(Auth::id() ?? 0, Request::all());
        Response::json(['ok' => $ok]);
    }

    public function photo(): void
    {
        try {
            $stored = $this->uploads->storeImage($_FILES['photo'] ?? [], 'profiles');
            $id = $this->profileService->savePhoto(Auth::id() ?? 0, $stored['path'], true);
            Response::json(['ok' => true, 'photo_id' => $id, 'path' => $stored['path']]);
        } catch (RuntimeException $exception) {
            Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function gallery(): void
    {
        try {
            $stored = $this->uploads->storeImage($_FILES['photo'] ?? [], 'gallery');
            $id = $this->profileService->savePhoto(Auth::id() ?? 0, $stored['path'], false);
            Response::json(['ok' => true, 'photo_id' => $id, 'path' => $stored['path']]);
        } catch (RuntimeException $exception) {
            Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
