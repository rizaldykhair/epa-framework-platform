<?php

namespace App\Controllers;

use App\Core\EpaWorkflow;
use App\Core\Guard;
use App\Core\Permissions;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Backlog;
use App\Models\Feedback;
use App\Models\Prototype;

class FeedbackController
{
    public function index(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        if (($request->user['role'] ?? null) === 'Evaluator') {
            Response::json(Feedback::allSubmittedByUser($projectId, (int) $request->user['sub']));
        } else {
            Response::json(Feedback::allForProject($projectId));
        }
    }

    public function mine(Request $request): void
    {
        Response::json(Feedback::allAssignedToUser((int) $request->user['sub']));
    }

    public function store(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        $body = $request->body;
        if (empty($body['finding'])) {
            Response::error('finding is required', 422);
        }
        $id = Feedback::create([
            'project_id' => $projectId,
            'code' => Feedback::nextCode($projectId),
            'finding' => $body['finding'],
            'decision' => $body['decision'] ?? 'Clarify',
            'status' => $body['status'] ?? 'Open',
            'submitted_by' => $request->user['sub'],
            'assigned_to' => $body['assigned_to'] ?? null,
        ]);
        AuditLog::record($request->user['sub'], 'create', 'feedback', $id);
        Response::json(Feedback::find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = Feedback::find($id);
        if (!$existing) {
            Response::error('Feedback not found', 404);
        }
        Guard::requireProjectAccess($request, (int) $existing['project_id']);
        $body = Permissions::restrictFields($request->user['role'] ?? null, 'feedback', $request->body);
        // decision_by records who made the decision, only stamped when a decision is actually part of this update.
        $decisionBy = array_key_exists('decision', $body) ? (int) $request->user['sub'] : null;
        Feedback::update($id, array_merge($existing, $body), $decisionBy);
        AuditLog::record($request->user['sub'], 'update', 'feedback', $id);
        Response::json(Feedback::find($id));
    }

    public function convert(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $fb = Feedback::find($id);
        if (!$fb) {
            Response::error('Feedback not found', 404);
        }
        Guard::requireProjectAccess($request, (int) $fb['project_id']);
        $backlogId = Backlog::create([
            'project_id' => $fb['project_id'],
            'code' => Backlog::nextCode($fb['project_id']),
            'title' => $request->body['title'] ?? $fb['finding'],
            'priority' => $request->body['priority'] ?? 'Should',
            'status' => 'To Do',
            'source_feedback_id' => $id,
            'source_type' => 'evaluator_feedback',
            'decision_status' => 'Approved',
            'created_by' => $request->user['sub'],
            'assigned_to' => $request->body['assigned_to'] ?? null,
        ]);
        Feedback::markConverted($id, $backlogId, (int) $request->user['sub']);
        EpaWorkflow::syncProjectCompletionStatus((int) $fb['project_id']);
        AuditLog::record($request->user['sub'], 'convert', 'feedback', $id, ['backlog_id' => $backlogId]);
        Response::json(['feedback' => Feedback::find($id), 'backlog' => Backlog::find($backlogId)], 201);
    }

    public function implement(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $fb = Feedback::find($id);
        if (!$fb) {
            Response::error('Feedback not found', 404);
        }
        Guard::requireProjectAccess($request, (int) $fb['project_id']);
        $prototypeId = isset($request->body['prototype_id']) ? (int) $request->body['prototype_id'] : null;
        $version = $request->body['prototype_version'] ?? null;
        if (!$prototypeId) {
            $latest = Prototype::allForProject((int) $fb['project_id'])[0] ?? null;
            $prototypeId = $latest['id'] ?? null;
            $version = $latest['version_number'] ?? null;
        }
        Feedback::markImplemented($id, $prototypeId, $version);
        EpaWorkflow::syncProjectCompletionStatus((int) $fb['project_id']);
        AuditLog::record($request->user['sub'], 'implement', 'feedback', $id);
        Response::json(Feedback::find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = Feedback::find($id);
        if ($existing) {
            Guard::requireProjectAccess($request, (int) $existing['project_id']);
        }
        Feedback::delete($id);
        AuditLog::record($request->user['sub'], 'delete', 'feedback', $id);
        Response::json(['deleted' => true]);
    }
}
