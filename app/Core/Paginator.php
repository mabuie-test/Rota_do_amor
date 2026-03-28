<?php

declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    public function __construct(public int $page = 1, public int $perPage = 20)
    {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
