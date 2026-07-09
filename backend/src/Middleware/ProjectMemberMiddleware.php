<?php

namespace App\Middleware;

use App\Core\Guard;
use App\Core\Request;

class ProjectMemberMiddleware
{
    public function __invoke(Request $request, array $params = []): void
    {
        Guard::requireProjectAccess($request, (int) ($params['id'] ?? 0));
    }
}
