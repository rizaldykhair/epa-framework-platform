<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\ProjectMember;

class ProjectMemberController
{
    public function index(Request $request, array $params): void
    {
        Response::json(ProjectMember::allForProject((int) $params['id']));
    }

    public function store(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        $body = $request->body;
        if (empty($body['user_id']) || empty($body['role_in_project'])) {
            Response::error('user_id and role_in_project are required', 422);
        }
        ProjectMember::add($projectId, (int) $body['user_id'], $body['role_in_project'], $body['responsibility_notes'] ?? null, (int) $request->user['sub']);
        AuditLog::record($request->user['sub'], 'create', 'project_member', $projectId);
        Response::json(ProjectMember::allForProject($projectId), 201);
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        ProjectMember::remove($id);
        AuditLog::record($request->user['sub'], 'delete', 'project_member', $id);
        Response::json(['deleted' => true]);
    }
}
