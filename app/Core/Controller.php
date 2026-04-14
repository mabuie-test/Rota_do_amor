<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\LayoutViewService;

abstract class Controller
{
    private ?LayoutViewService $layoutViewService = null;

    protected function view(string $view, array $data = []): void
    {
        $data += $this->sharedLayoutData();
        View::render($view, $data);
    }

    protected function sharedLayoutData(): array
    {
        if ($this->layoutViewService === null) {
            $this->layoutViewService = new LayoutViewService();
        }

        return $this->layoutViewService->forAuthenticatedViewer(Auth::id() ?? 0);
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
