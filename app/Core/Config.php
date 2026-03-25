<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $cache = [];

    public static function env(string $key, mixed $default = null): mixed
    {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        $value = getenv($key);
        return $value === false ? $default : $value;
    }

    public static function get(string $file, ?string $key = null, mixed $default = null): mixed
    {
        if (!isset(self::$cache[$file])) {
            $path = dirname(__DIR__, 2) . '/config/' . $file . '.php';
            self::$cache[$file] = is_file($path) ? require $path : [];
        }

        if ($key === null) {
            return self::$cache[$file];
        }

        return self::$cache[$file][$key] ?? $default;
    }
}
