<?php

declare(strict_types=1);

namespace App\Core;

final class Bootstrap
{
    private function __construct()
    {
    }

    public static function loadEnvironment(string $basePath): void
    {
        $envPath = rtrim($basePath, '/\\') . '/.env';
        if (!is_file($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$k, $v] = explode('=', $line, 2);
            $key = trim($k);
            if ($key === '') {
                continue;
            }

            $value = trim(trim($v), "\"'");
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function configureTimezone(string $default = 'Africa/Maputo'): string
    {
        $configured = trim((string) Config::env('APP_TIMEZONE', $default));
        $timezone = in_array($configured, timezone_identifiers_list(), true) ? $configured : $default;

        date_default_timezone_set($timezone);

        return $timezone;
    }
}
