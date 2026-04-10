<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf';

    public static function token(): string
    {
        Session::start();
        $token = Session::get(self::SESSION_KEY);
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            Session::put(self::SESSION_KEY, $token);
        }
        return $token;
    }

    public static function validate(?string $token): bool
    {
        $stored = (string) Session::get(self::SESSION_KEY, '');
        if ($stored === '' || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }
}
