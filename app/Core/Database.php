<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Config::env('DB_HOST', '127.0.0.1'),
            Config::env('DB_PORT', '3306'),
            Config::env('DB_NAME', 'rota_do_amor')
        );

        try {
            self::$pdo = new PDO($dsn, (string) Config::env('DB_USER', 'root'), (string) Config::env('DB_PASS', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::configureSessionTimezone(self::$pdo);
        } catch (PDOException $exception) {
            throw new PDOException('Database connection failed: ' . $exception->getMessage(), (int) $exception->getCode());
        }

        return self::$pdo;
    }

    private static function configureSessionTimezone(PDO $pdo): void
    {
        $timezone = trim((string) Config::env('DB_TIMEZONE', Config::env('APP_DB_TIMEZONE', '+02:00')));
        if ($timezone === '') {
            $timezone = '+02:00';
        }

        try {
            $stmt = $pdo->prepare('SET time_zone = :tz');
            $stmt->execute([':tz' => $timezone]);
        } catch (PDOException $exception) {
            error_log('[database.timezone_config_failed] timezone=' . $timezone . ' error=' . $exception->getMessage());
        }
    }
}
