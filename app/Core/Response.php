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
        $reference = null;
        if ($status >= 500) {
            $reference = bin2hex(random_bytes(6));
            if (!self::shouldExposeTechnicalDetails()) {
                $safeMessage = sprintf('Erro interno do servidor. Tente novamente mais tarde. Ref: %s', $reference);
            } else {
                $safeMessage = sprintf('Erro interno [%s]: %s', $reference, $message);
            }
        }

        error_log(sprintf(
            '[response.abort]%s status=%d uri=%s ip=%s message=%s',
            $reference !== null ? '[' . $reference . ']' : '',
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

    private static function shouldExposeTechnicalDetails(): bool
    {
        $debug = filter_var((string) Config::env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
        $env = mb_strtolower(trim((string) Config::env('APP_ENV', 'production')));
        return $debug || in_array($env, ['local', 'development', 'dev', 'testing', 'test'], true);
    }
}
