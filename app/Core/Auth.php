<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }
}
