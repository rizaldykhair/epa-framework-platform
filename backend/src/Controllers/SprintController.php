<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Sprint;

class SprintController
{
    public function index(Request $request, array $params): void
    {
        Response::json(Sprint::allForProject((int) $params['id']));
    }

    public function store(Request $request, array $params): void
    {
        $body = $request->body;
        if (empty($body['name'])) {
            Response::error('name is required', 422);
        }
        $id = Sprint::create([
            'project_id' => (int) $params['id'],
            'name' => $body['name'],
            'start_date' => $body['start_date'] ?? null,
            'end_date' => $body['end_date'] ?? null,
            'status' => $body['status'] ?? 'Planned',
        ]);
        AuditLog::record($request->user['sub'], 'create', 'sprint', $id);
        Response::json(Sprint::find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = Sprint::find($id);
        if (!$existing) {
            Response::error('Sprint not found', 404);
        }
        Sprint::update($id, array_merge($existing, $request->body));
        AuditLog::record($request->user['sub'], 'update', 'sprint', $id);
        Response::json(Sprint::find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        Sprint::delete($id);
        AuditLog::record($request->user['sub'], 'delete', 'sprint', $id);
        Response::json(['deleted' => true]);
    }
}
