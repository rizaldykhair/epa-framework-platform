<?php

namespace App\Controllers;

use App\Core\EpaWorkflow;
use App\Core\Guard;
use App\Core\Permissions;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectMember;

class ProjectController
{
    public function index(Request $request): void
    {
        if (($request->user['role'] ?? null) === 'Admin') {
            Response::json(Project::all());
        } else {
            Response::json(Project::allForUser((int) $request->user['sub']));
        }
    }

    public function show(Request $request, array $params): void
    {
        $project = Project::find((int) $params['id']);
        if (!$project) {
            Response::error('Project not found', 404);
        }
        Response::json($project);
    }

    public function store(Request $request): void
    {
        $body = $request->body;
        $role = $request->user['role'] ?? null;
        $isAdmin = $role === 'Admin';

        if (empty($body['name'])) {
            Response::error('name is required', 422);
        }
        if ($isAdmin && empty($body['owner_id'])) {
            Response::error('owner_id (Product Owner) is required', 422);
        }

        // Admin creates ready-to-run projects; Product Owner submits a proposal pending Admin approval.
        $ownerId = $isAdmin ? (int) $body['owner_id'] : (int) $request->user['sub'];
        $status = $isAdmin ? ($body['status'] ?? 'Active') : 'Proposed';

        // current_epa_phase/step are never taken from user input: every new project must
        // start at EPA Step 1 (Requirements Elicitation) and cannot jump straight to a
        // later phase. Project::create()'s DB defaults already put it at REQ_BACKLOG.
        $id = Project::create([
            'name' => $body['name'],
            'project_code' => $body['project_code'] ?? null,
            'description' => $body['description'] ?? null,
            'client_name' => $body['client_name'] ?? null,
            'project_type' => $body['project_type'] ?? 'Web App',
            'owner_id' => $ownerId,
            'created_by' => $request->user['sub'],
            'status' => $status,
            'start_date' => $body['start_date'] ?? null,
            'target_release_date' => $body['target_release_date'] ?? null,
        ]);

        ProjectMember::add($id, $ownerId, 'Product Owner');
        if (!$isAdmin) {
            ProjectMember::add($id, (int) $request->user['sub'], $role);
        } else {
            ProjectMember::add($id, (int) $request->user['sub'], 'Admin');
        }

        EpaWorkflow::classifyExistingProjectEPAStatus($id);
        AuditLog::record($request->user['sub'], 'create', 'project', $id);
        Response::json(array_merge(Project::find($id), [
            'message' => 'Project created. Complete Requirements Elicitation & Project Adaptive Backlog to continue the EPA workflow.',
        ]), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = Project::find($id);
        if (!$existing) {
            Response::error('Project not found', 404);
        }
        $role = $request->user['role'] ?? null;
        if ($role !== 'Admin') {
            Guard::requireProjectAccess($request, $id);
        }
        $body = Permissions::restrictFields($role, 'projects', $request->body);
        Project::update($id, array_merge($existing, $body));
        AuditLog::record($request->user['sub'], 'update', 'project', $id);
        Response::json(Project::find($id));
    }

    public function approve(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        if (!Project::find($id)) {
            Response::error('Project not found', 404);
        }
        Project::approve($id, (int) $request->user['sub']);
        AuditLog::record($request->user['sub'], 'approve', 'project', $id);
        Response::json(Project::find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        Project::delete($id);
        AuditLog::record($request->user['sub'], 'delete', 'project', $id);
        Response::json(['deleted' => true]);
    }
}
