<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;

final class SecurityHeadersMiddleware
{
    public function handle(): void
    {
        $csp = $this->buildContentSecurityPolicy();

        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: SAMEORIGIN');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()');
        header('Content-Security-Policy: ' . $csp);

        if ($this->isSecureRequest() && Config::env('APP_ENV', 'local') !== 'local') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    private function buildContentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "upgrade-insecure-requests",

            // Imagens locais e uploads com data/blob para previews e lightbox.
            "img-src 'self' data: blob:",

            // Fontes locais + Google Fonts CDN usado em app/Views/layouts/main.php.
            "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",

            // CSS local + Bootstrap (jsDelivr), Font Awesome (Cloudflare) e Google Fonts stylesheet.
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",

            // JS local + Bootstrap bundle carregado por CDN (jsDelivr).
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",

            // Reservado para chamadas same-origin (fetch/feed interactions).
            "connect-src 'self'",
        ]);
    }

    private function isSecureRequest(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwardedProto === 'https') {
            return true;
        }

        $forwardedSsl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
        if ($forwardedSsl === 'on') {
            return true;
        }

        return false;
    }
}
