<?php

namespace App\Controllers;

use App\Core\Guard;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\ReleaseChecklist;

class ReleaseChecklistController
{
    public function index(Request $request, array $params): void
    {
        Response::json(ReleaseChecklist::allForProject((int) $params['id']));
    }

    public function store(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        $body = $request->body;
        if (empty($body['item'])) {
            Response::error('item is required', 422);
        }
        $id = ReleaseChecklist::create([
            'project_id' => $projectId,
            'item' => $body['item'],
            'status' => $body['status'] ?? 'No',
            'verified_by' => $request->user['sub'],
        ]);
        AuditLog::record($request->user['sub'], 'create', 'release_checklist', $id);
        Response::json(ReleaseChecklist::find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = ReleaseChecklist::find($id);
        if (!$existing) {
            Response::error('Checklist item not found', 404);
        }
        Guard::requireProjectAccess($request, (int) $existing['project_id']);
        $body = array_merge($existing, $request->body, ['verified_by' => $request->user['sub']]);
        ReleaseChecklist::update($id, $body);
        AuditLog::record($request->user['sub'], 'update', 'release_checklist', $id);
        Response::json(ReleaseChecklist::find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = ReleaseChecklist::find($id);
        if ($existing) {
            Guard::requireProjectAccess($request, (int) $existing['project_id']);
        }
        ReleaseChecklist::delete($id);
        AuditLog::record($request->user['sub'], 'delete', 'release_checklist', $id);
        Response::json(['deleted' => true]);
    }
}
