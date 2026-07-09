<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\EpaPhase;

class PhaseController
{
    public function index(Request $request, array $params): void
    {
        Response::json(EpaPhase::allForProject((int) $params['id']));
    }

    public function store(Request $request, array $params): void
    {
        $body = $request->body;
        if (empty($body['name']) || empty($body['stage_label']) || !isset($body['sequence'])) {
            Response::error('name, stage_label and sequence are required', 422);
        }
        $id = EpaPhase::create([
            'project_id' => (int) $params['id'],
            'name' => $body['name'],
            'stage_label' => $body['stage_label'],
            'sequence' => $body['sequence'],
            'status' => $body['status'] ?? 'Pending',
        ]);
        AuditLog::record($request->user['sub'], 'create', 'epa_phase', $id);
        Response::json(EpaPhase::find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = EpaPhase::find($id);
        if (!$existing) {
            Response::error('Phase not found', 404);
        }
        EpaPhase::update($id, array_merge($existing, $request->body));
        AuditLog::record($request->user['sub'], 'update', 'epa_phase', $id);
        Response::json(EpaPhase::find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        EpaPhase::delete($id);
        AuditLog::record($request->user['sub'], 'delete', 'epa_phase', $id);
        Response::json(['deleted' => true]);
    }
}
