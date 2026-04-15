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

if (!function_exists('safe_date_capability_meta')) {
    /**
     * @param array<string, mixed> $capabilities
     * @return array{labels: array<int, string>, context: string, summary: string}
     */
    function safe_date_capability_meta(array $capabilities, string $context = 'generic'): array
    {
        $labels = ['Standard'];
        if (!empty($capabilities['can_verified_only'])) {
            $labels[] = 'Verificados';
        }
        if (!empty($capabilities['can_premium_guard'])) {
            $labels[] = 'Premium Guard';
        }

        $messageByContext = [
            'generic' => 'Encontro Seguro disponível',
            'messages' => 'Encontro Seguro disponível nesta conversa',
            'matches' => 'Pronto para avançar com Encontro Seguro',
            'invites' => 'Disponível para resposta com Encontro Seguro',
            'profile' => 'Este par suporta Encontro Seguro',
        ];
        $base = $messageByContext[$context] ?? $messageByContext['generic'];

        if (!empty($capabilities['can_premium_guard'])) {
            $base = 'Premium Guard disponível';
        } elseif (!empty($capabilities['can_verified_only'])) {
            $base = 'Disponível com Verificados';
        }

        return [
            'labels' => $labels,
            'context' => $base,
            'summary' => 'Níveis disponíveis: ' . implode(', ', $labels),
        ];
    }
}
