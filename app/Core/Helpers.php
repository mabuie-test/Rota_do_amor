<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;

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

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}
