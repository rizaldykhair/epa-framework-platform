<?php

namespace App\Controllers;

use App\Core\EpaWorkflow;
use App\Core\Guard;
use App\Core\Permissions;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Backlog;
use App\Models\Sprint;
use App\Models\SprintItem;
use App\Models\WorkItem;
use App\Models\WorkItemActivityLog;

class BacklogController
{
    private const ASSIGNABLE_STATUSES = ['Approved', 'Ready for Sprint'];
    public function index(Request $request, array $params): void
    {
        Response::json(Backlog::allForProject((int) $params['id']));
    }

    public function mine(Request $request): void
    {
        Response::json(Backlog::allAssignedToUser((int) $request->user['sub']));
    }

    public function store(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        $body = $request->body;
        if (empty($body['title'])) {
            Response::error('title is required', 422);
        }
        $id = Backlog::create([
            'project_id' => $projectId,
            'code' => Backlog::nextCode($projectId),
            'title' => $body['title'],
            'user_story' => $body['user_story'] ?? null,
            'acceptance_criteria' => $body['acceptance_criteria'] ?? null,
            'business_value' => $body['business_value'] ?? null,
            'priority' => $body['priority'] ?? 'Should',
            'status' => $body['status'] ?? 'To Do',
            'source_type' => $body['source_type'] ?? 'manual_product_owner',
            'source_id' => $body['source_id'] ?? null,
            'source_defect_id' => $body['source_defect_id'] ?? null,
            'source_retrospective_id' => $body['source_retrospective_id'] ?? null,
            'created_from_epa_step' => $body['created_from_epa_step'] ?? null,
            'target_prototype_version' => $body['target_prototype_version'] ?? null,
            'created_by' => $request->user['sub'],
            'assigned_to' => $body['assigned_to'] ?? null,
        ]);
        AuditLog::record($request->user['sub'], 'create', 'backlog', $id);
        Response::json(Backlog::find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = Backlog::find($id);
        if (!$existing) {
            Response::error('Backlog item not found', 404);
        }
        Guard::requireProjectAccess($request, (int) $existing['project_id']);
        $body = Permissions::restrictFields($request->user['role'] ?? null, 'backlogs', $request->body);
        Backlog::update($id, array_merge($existing, $body));
        AuditLog::record($request->user['sub'], 'update', 'backlog', $id);
        Response::json(Backlog::find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = Backlog::find($id);
        if ($existing) {
            Guard::requireProjectAccess($request, (int) $existing['project_id']);
        }
        Backlog::delete($id);
        AuditLog::record($request->user['sub'], 'delete', 'backlog', $id);
        Response::json(['deleted' => true]);
    }

    /**
     * Semi-automatic Adaptive Backlog -> Sprint Backlog assignment: Product Owner picks
     * specific Approved/Ready for Sprint items and explicitly assigns them - nothing here
     * ever auto-moves the whole backlog. Reuses sprint_items as the sprint backlog table
     * and work_items as the Developer Workboard sync layer, exactly like every other
     * artifact type already does.
     */
    public function assignToSprint(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        Guard::requireProjectAccess($request, $projectId);
        $body = $request->body;
        $userId = (int) $request->user['sub'];

        $backlogIds = array_map('intval', $body['backlog_ids'] ?? []);
        if (empty($backlogIds)) {
            Response::error('backlog_ids is required', 422);
        }

        // Resolve target sprint: an existing one, or create a new one now.
        if (!empty($body['create_new_sprint'])) {
            if (empty($body['sprint_name'])) {
                Response::error('sprint_name is required when creating a new sprint', 422);
            }
            $sprintId = Sprint::create([
                'project_id' => $projectId,
                'name' => $body['sprint_name'],
                'start_date' => $body['start_date'] ?? null,
                'end_date' => $body['end_date'] ?? null,
                'status' => 'Active',
            ]);
        } else {
            $sprintId = (int) ($body['sprint_id'] ?? 0);
            $sprint = $sprintId ? Sprint::find($sprintId) : null;
            if (!$sprint || (int) $sprint['project_id'] !== $projectId) {
                Response::error('Selected sprint not found for this project', 422);
                return;
            }
        }

        $assignedDeveloper = !empty($body['assigned_developer']) ? (int) $body['assigned_developer'] : null;
        $targetVersion = $body['target_prototype_version'] ?? null;

        $createdSprintItems = [];
        $createdWorkItems = [];
        $skipped = [];

        foreach ($backlogIds as $backlogId) {
            $backlog = Backlog::find($backlogId);
            if (!$backlog || (int) $backlog['project_id'] !== $projectId) {
                $skipped[] = ['backlog_id' => $backlogId, 'reason' => 'not found in this project'];
                continue;
            }
            if (!in_array($backlog['status'], self::ASSIGNABLE_STATUSES, true)) {
                // Also covers "already In Sprint" and Rejected/Deferred/Done - none of those
                // are in ASSIGNABLE_STATUSES, so this single check both enforces the gate
                // and prevents duplicating an already-sprinted backlog into another sprint.
                $skipped[] = ['backlog_id' => $backlogId, 'reason' => "status '{$backlog['status']}' is not Approved/Ready for Sprint"];
                continue;
            }

            $sprintItemId = SprintItem::create([
                'sprint_id' => $sprintId,
                'backlog_id' => $backlogId,
                'assigned_to' => $assignedDeveloper,
                'status' => 'Planned',
            ]);
            $createdSprintItems[] = SprintItem::find($sprintItemId);

            Backlog::update($backlogId, array_merge($backlog, [
                'status' => 'In Sprint',
                'assigned_to' => $assignedDeveloper ?? $backlog['assigned_to'],
                'target_prototype_version' => $targetVersion ?? $backlog['target_prototype_version'],
                'target_sprint_id' => $sprintId,
            ]));

            $workItemId = WorkItem::upsert([
                'project_id' => $projectId,
                'epa_phase' => 'Dev & Testing Phase',
                'epa_step' => 'PROTOTYPE_DESIGN_SPRINT',
                'artifact_type' => 'sprint_item',
                'artifact_id' => $sprintItemId,
                'title' => 'Sprint Task: ' . $backlog['title'],
                'description' => $backlog['user_story'] ?? null,
                'status' => 'To Do',
                'priority' => $backlog['priority'],
                'assignee_id' => $assignedDeveloper,
                'source_type' => 'adaptive_backlog',
                'source_id' => $backlogId,
                'sprint_id' => $sprintId,
                'created_by' => $userId,
            ]);
            WorkItemActivityLog::record($workItemId, $userId, 'created', null, 'To Do', 'Assigned from Adaptive Backlog to Sprint by Product Owner');
            $createdWorkItems[] = WorkItem::find($workItemId);

            AuditLog::record($userId, 'assign_to_sprint', 'backlog', $backlogId, ['sprint_id' => $sprintId]);
        }

        $epaStatus = EpaWorkflow::syncProjectCompletionStatus($projectId);

        Response::json([
            'sprint_id' => $sprintId,
            'created_sprint_backlog' => $createdSprintItems,
            'created_work_items' => $createdWorkItems,
            'skipped' => $skipped,
            'epa_status' => $epaStatus,
        ], 201);
    }
}
