<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\User;

class UserController
{
    public function index(Request $request): void
    {
        Response::json(User::all());
    }

    public function store(Request $request): void
    {
        $body = $request->body;
        if (empty($body['name']) || empty($body['email']) || empty($body['password']) || empty($body['role_id'])) {
            Response::error('name, email, password and role_id are required', 422);
        }
        $id = User::create($body);
        AuditLog::record($request->user['sub'], 'create', 'user', $id);
        Response::json(User::find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = User::find($id);
        if (!$existing) {
            Response::error('User not found', 404);
        }
        User::update($id, array_merge($existing, $request->body));
        AuditLog::record($request->user['sub'], 'update', 'user', $id);
        Response::json(User::find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        User::delete($id);
        AuditLog::record($request->user['sub'], 'delete', 'user', $id);
        Response::json(['deleted' => true]);
    }
}
