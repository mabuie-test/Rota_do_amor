<?php

declare(strict_types=1);

namespace App\Core;

final class Flash
{
    public static function set(string $key, string $message): void
    {
        Session::put('flash_' . $key, $message);
    }
}
