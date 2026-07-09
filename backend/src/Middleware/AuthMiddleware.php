<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

class AuthMiddleware
{
    public function __invoke(Request $request): void
    {
        $token = $request->bearerToken();
        if (!$token) {
            Response::error('Unauthorized: missing token', 401);
        }
        $claims = Auth::verifyToken($token);
        if (!$claims) {
            Response::error('Unauthorized: invalid or expired token', 401);
        }
        $request->user = $claims;
    }
}
