<?php

namespace App\Controllers;

use App\Core\EpaWorkflow;
use App\Core\Guard;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Backlog;
use App\Models\Retrospective;

class RetrospectiveController
{
    public function index(Request $request, array $params): void
    {
        Response::json(Retrospective::allForProject((int) $params['id']));
    }

    public function store(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        $body = $request->body;
        $id = Retrospective::create([
            'project_id' => $projectId,
            'went_well' => $body['went_well'] ?? null,
            'needs_improvement' => $body['needs_improvement'] ?? null,
            'action_item' => $body['action_item'] ?? null,
            'create_improvement_backlog' => $body['create_improvement_backlog'] ?? false,
            'next_iteration_recommendation' => $body['next_iteration_recommendation'] ?? null,
            'created_by' => $request->user['sub'],
        ]);

        // Retrospective improvement loop: an action item isn't left as a passive note -
        // optionally turn it straight into a backlog item, so the next sprint actually
        // picks it up as adaptive backlog work rather than requiring a separate manual step.
        if (!empty($body['create_improvement_backlog']) && !empty($body['action_item'])) {
            $backlogId = Backlog::create([
                'project_id' => $projectId,
                'code' => Backlog::nextCode($projectId),
                'title' => $body['action_item'],
                'priority' => 'Should',
                'status' => 'To Do',
                'source_type' => 'retrospective',
                'source_retrospective_id' => $id,
                'created_by' => $request->user['sub'],
            ]);
            Retrospective::setGeneratedBacklog($id, $backlogId);
            EpaWorkflow::syncProjectCompletionStatus($projectId);
        }

        AuditLog::record($request->user['sub'], 'create', 'retrospective', $id);
        Response::json(Retrospective::find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = Retrospective::find($id);
        if (!$existing) {
            Response::error('Retrospective not found', 404);
        }
        Guard::requireProjectAccess($request, (int) $existing['project_id']);
        Retrospective::update($id, array_merge($existing, $request->body));
        AuditLog::record($request->user['sub'], 'update', 'retrospective', $id);
        Response::json(Retrospective::find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = Retrospective::find($id);
        if ($existing) {
            Guard::requireProjectAccess($request, (int) $existing['project_id']);
        }
        Retrospective::delete($id);
        AuditLog::record($request->user['sub'], 'delete', 'retrospective', $id);
        Response::json(['deleted' => true]);
    }
}
