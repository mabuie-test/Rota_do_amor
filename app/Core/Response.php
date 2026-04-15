<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }

    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function abort(int $status, string $message): never
    {
        $safeMessage = $message;
        if ($status >= 500 && !filter_var((string) Config::env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN)) {
            $safeMessage = 'Erro interno do servidor. Tente novamente mais tarde.';
        }

        error_log(sprintf(
            '[response.abort] status=%d uri=%s ip=%s message=%s',
            $status,
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            $message
        ));

        if (Request::expectsJson()) {
            self::json(['ok' => false, 'message' => $safeMessage], $status);
        }

        http_response_code($status);
        echo $safeMessage;
        exit;
    }
}
