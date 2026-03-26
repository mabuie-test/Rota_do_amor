<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function strongPassword(string $value): bool
    {
        return strlen($value) >= 8
            && preg_match('/[A-Z]/', $value)
            && preg_match('/[a-z]/', $value)
            && preg_match('/\d/', $value)
            && preg_match('/[^a-zA-Z\d]/', $value);
    }
}
