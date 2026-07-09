<?php

namespace App\Controllers;

use App\Core\EpaWorkflow;
use App\Core\Guard;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Defect;

class DefectController
{
    public function index(Request $request, array $params): void
    {
        Response::json(Defect::allForProject((int) $params['id']));
    }

    public function mine(Request $request): void
    {
        Response::json(Defect::allAssignedToUser((int) $request->user['sub']));
    }

    public function store(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        $body = $request->body;
        if (empty($body['test_id']) || empty($body['description'])) {
            Response::error('test_id and description are required', 422);
        }
        $id = Defect::create([
            'test_id' => (int) $body['test_id'],
            'project_id' => $projectId,
            'title' => $body['title'] ?? null,
            'description' => $body['description'],
            'steps_to_reproduce' => $body['steps_to_reproduce'] ?? null,
            'expected_result' => $body['expected_result'] ?? null,
            'actual_result' => $body['actual_result'] ?? null,
            'severity' => $body['severity'] ?? 'Medium',
            'reported_by' => $request->user['sub'],
            'assigned_to' => $body['assigned_to'] ?? null,
        ]);
        AuditLog::record($request->user['sub'], 'create', 'defect', $id);
        Response::json(Defect::find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = Defect::find($id);
        if (!$existing) {
            Response::error('Defect not found', 404);
        }
        Guard::requireProjectAccess($request, (int) $existing['project_id']);
        $role = $request->user['role'] ?? null;
        $status = $request->body['status'] ?? $existing['status'];
        if (!in_array($status, ['Open', 'Fixed', 'Verified', 'Closed'], true)) {
            Response::error('status must be Open, Fixed, Verified or Closed', 422);
        }
        if ($role === 'Developer') {
            // Developer may only progress a defect assigned to them to "Fixed".
            // Verified/Closed are Tester-only, via the dedicated /verify endpoint.
            if ((int) $existing['assigned_to'] !== (int) $request->user['sub']) {
                Response::roleDenied();
            }
            if (!in_array($status, ['Open', 'Fixed'], true)) {
                Response::roleDenied();
            }
            Defect::updateAssignment($id, $existing['assigned_to'], $status);
        } else {
            Defect::updateAssignment($id, $request->body['assigned_to'] ?? $existing['assigned_to'], $status);
        }
        EpaWorkflow::syncProjectCompletionStatus((int) $existing['project_id']);
        AuditLog::record($request->user['sub'], 'update', 'defect', $id);
        Response::json(Defect::find($id));
    }

    public function verify(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = Defect::find($id);
        if (!$existing) {
            Response::error('Defect not found', 404);
        }
        Guard::requireProjectAccess($request, (int) $existing['project_id']);
        Defect::markVerified($id, (int) $request->user['sub']);
        EpaWorkflow::syncProjectCompletionStatus((int) $existing['project_id']);
        AuditLog::record($request->user['sub'], 'verify', 'defect', $id);
        Response::json(Defect::find($id));
    }
}
