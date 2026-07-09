<?php

namespace App\Controllers;

use App\Core\EpaWorkflow;
use App\Core\Guard;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Prototype;

class PrototypeController
{
    public function index(Request $request, array $params): void
    {
        Response::json(Prototype::allForProject((int) $params['id']));
    }

    private function validateDemoUrl(array $body): void
    {
        $demoUrl = $body['demo_url'] ?? null;
        if ($demoUrl && str_contains($demoUrl, 'example.com')) {
            Response::error('Invalid demo URL. Please replace with real prototype output.', 422);
        }
        $status = $body['status'] ?? 'Planned';
        if (in_array($status, ['Ready for Testing', 'Completed'], true) && empty($demoUrl)) {
            Response::error('demo_url is required when status is Ready for Testing or Completed', 422);
        }
    }

    public function store(Request $request, array $params): void
    {
        $body = $request->body;
        if (empty($body['version_number'])) {
            Response::error('version_number is required', 422);
        }
        $this->validateDemoUrl($body);
        $projectId = (int) $params['id'];
        // Auto-chain to the latest existing version so version history is never lost,
        // unless the caller explicitly specifies a different parent (e.g. a branch/fix).
        $latest = Prototype::allForProject($projectId)[0] ?? null;
        $id = Prototype::create([
            'project_id' => $projectId,
            'sprint_id' => $body['sprint_id'] ?? null,
            'backlog_id' => $body['backlog_id'] ?? null,
            'previous_prototype_id' => $body['previous_prototype_id'] ?? ($latest['id'] ?? null),
            'version_number' => $body['version_number'],
            'prototype_name' => $body['prototype_name'] ?? null,
            'development_type' => $body['development_type'] ?? 'New Feature',
            'demo_url' => $body['demo_url'] ?? null,
            'repository_url' => $body['repository_url'] ?? null,
            'build_output_url' => $body['build_output_url'] ?? null,
            'mobile_build_url' => $body['mobile_build_url'] ?? null,
            'notes' => $body['notes'] ?? null,
            'implemented_feedback_summary' => $body['implemented_feedback_summary'] ?? null,
            'implemented_feedback_ids' => $body['implemented_feedback_ids'] ?? null,
            'implemented_backlog_ids' => $body['implemented_backlog_ids'] ?? null,
            'fixed_defect_ids' => $body['fixed_defect_ids'] ?? null,
            'status' => $body['status'] ?? 'Planned',
            'demo_date' => $body['demo_date'] ?? null,
            'created_by' => $request->user['sub'],
        ]);
        EpaWorkflow::syncProjectCompletionStatus($projectId);
        AuditLog::record($request->user['sub'], 'create', 'prototype', $id);
        Response::json(Prototype::find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = Prototype::find($id);
        if (!$existing) {
            Response::error('Prototype not found', 404);
        }
        Guard::requireProjectAccess($request, (int) $existing['project_id']);
        $merged = array_merge($existing, $request->body);
        $this->validateDemoUrl($merged);
        Prototype::update($id, $merged);
        EpaWorkflow::syncProjectCompletionStatus((int) $existing['project_id']);
        AuditLog::record($request->user['sub'], 'update', 'prototype', $id);
        Response::json(Prototype::find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = Prototype::find($id);
        if ($existing) {
            Guard::requireProjectAccess($request, (int) $existing['project_id']);
        }
        Prototype::delete($id);
        AuditLog::record($request->user['sub'], 'delete', 'prototype', $id);
        Response::json(['deleted' => true]);
    }
}
