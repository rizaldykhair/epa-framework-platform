<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\SprintItem;

class SprintItemController
{
    public function index(Request $request, array $params): void
    {
        Response::json(SprintItem::allForSprint((int) $params['id']));
    }

    public function mine(Request $request): void
    {
        Response::json(SprintItem::allForUser((int) $request->user['sub']));
    }

    public function store(Request $request, array $params): void
    {
        $body = $request->body;
        if (empty($body['backlog_id'])) {
            Response::error('backlog_id is required', 422);
        }
        $id = SprintItem::create([
            'sprint_id' => (int) $params['id'],
            'backlog_id' => (int) $body['backlog_id'],
            'assigned_to' => $body['assigned_to'] ?? null,
            'status' => $body['status'] ?? 'Planned',
        ]);
        AuditLog::record($request->user['sub'], 'create', 'sprint_item', $id);
        Response::json(SprintItem::find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = SprintItem::find($id);
        if (!$existing) {
            Response::error('Sprint item not found', 404);
        }
        SprintItem::update($id, array_merge($existing, $request->body));
        AuditLog::record($request->user['sub'], 'update', 'sprint_item', $id);
        Response::json(SprintItem::find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        SprintItem::delete($id);
        AuditLog::record($request->user['sub'], 'delete', 'sprint_item', $id);
        Response::json(['deleted' => true]);
    }
}
