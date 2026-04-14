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

    /**
     * Envelope JSON padronizado para endpoints AJAX.
     */
    protected function jsonOutcome(
        bool $ok,
        string $message,
        string $action,
        mixed $state = null,
        int $createdId = 0,
        int $targetId = 0,
        ?string $errorCode = null,
        array $extra = [],
        ?int $status = null
    ): never {
        $payload = [
            'ok' => $ok,
            'message' => $message,
            'action' => $action,
            'state' => $state,
            'created_id' => $createdId,
            'target_id' => $targetId,
            'error_code' => $errorCode,
        ] + $extra;

        if ($status === null) {
            $status = $ok ? 200 : 422;
        }

        Response::json($payload, $status);
    }
}
