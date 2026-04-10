<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Config;
use RuntimeException;

final class DebitoClient
{
    public function request(string $method, string $path, ?array $payload = null, array $headers = []): array
    {
        $url = rtrim((string) Config::env('DEBITO_BASE_URL'), '/') . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array_merge([
                'Authorization: Bearer ' . Config::env('DEBITO_TOKEN', ''),
                'Content-Type: application/json',
            ], $headers),
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
        }

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        // curl_close became a no-op in PHP 8.0 and emits deprecation warnings in 8.5.
        // We intentionally skip it to avoid output that breaks JSON responses/headers.

        if ($raw === false || $error !== '') {
            throw new RuntimeException('Falha ao comunicar com Débito API: ' . $error);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida da Débito API.');
        }
        $decoded['_http_status'] = $code;

        return $decoded;
    }
}
