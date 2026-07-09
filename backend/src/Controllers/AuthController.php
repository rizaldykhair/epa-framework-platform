<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

class AuthController
{
    public function login(Request $request): void
    {
        $email = trim($request->body['email'] ?? '');
        $password = $request->body['password'] ?? '';
        if ($email === '' || $password === '') {
            Response::error('Email and password are required', 422);
        }

        $user = User::findByEmail($email);
        if (!$user || !Auth::verifyPassword($password, $user['password_hash'])) {
            Response::error('Invalid credentials', 401);
        }

        $token = Auth::issueToken([
            'sub' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role_name'],
        ]);

        Response::json([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role_name'],
            ],
        ]);
    }

    public function me(Request $request): void
    {
        Response::json($request->user);
    }
}
