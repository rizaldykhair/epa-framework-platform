<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class RoleMiddleware
{
    public static function allow(array $roles): callable
    {
        return function (Request $request, array $params = []) use ($roles): void {
            $role = $request->user['role'] ?? null;
            if (!in_array($role, $roles, true)) {
                Response::roleDenied();
            }
        };
    }
}
