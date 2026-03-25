<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function id(): ?int
    {
        $id = Session::get('user_id');
        return $id ? (int) $id : null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function login(int $userId): void
    {
        Session::put('user_id', $userId);
        Session::regenerate();
    }

    public static function logout(): void
    {
        Session::forget('user_id');
        Session::regenerate();
    }
}
