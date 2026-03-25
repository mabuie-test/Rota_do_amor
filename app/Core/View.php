<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $file = dirname(__DIR__) . '/Views/' . $view . '.php';

        if (!is_file($file)) {
            http_response_code(404);
            echo 'View not found.';
            return;
        }

        require dirname(__DIR__) . '/Views/layouts/main.php';
    }
}
