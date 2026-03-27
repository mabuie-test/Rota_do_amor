<?php

declare(strict_types=1);

use App\Core\Config;

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('app_url')) {
    function app_url(): string
    {
        return rtrim((string) Config::env('APP_URL', ''), '/');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = app_url();
        $normalized = '/' . ltrim($path, '/');
        return $base !== '' ? $base . $normalized : $normalized;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}
