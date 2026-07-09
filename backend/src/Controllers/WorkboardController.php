<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\EpaWorkflow;
use App\Core\Guard;
use App\Core\Request;
use App\Core\Response;
use App\Models\Backlog;
use App\Models\Defect;
use App\Models\Prototype;
use App\Models\SprintItem;
use App\Models\WorkItem;
use App\Models\WorkItemActivityLog;
use App\Models\WorkItemComment;

/**
 * EPA Workboard: a ClickUp/Jira-style board layered on top of the existing EPA
 * artifacts (backlogs, sprint_items, prototypes, feedback_implementation, tests,
 * defects, release_checklists). work_items is a sync/mapping layer, not a second
 * source of truth - sync() upserts from the real tables, and updateStatus() writes
 * status changes back to the underlying table (for the 3 artifact types with a
 * naturally Kanban-shaped status: backlog, sprint_item, defect) before mirroring the
 * change onto work_items itself. Every write-through reuses the same models/rules the
 * rest of the platform already uses (Backlog::update, SprintItem::update,
 * Defect::updateAssignment/markVerified) rather than re-implementing them.
 */
class WorkboardController
{
    private const BOARD_STATUSES = ['To Do', 'In Progress', 'Review', 'Ready for Testing', 'Testing', 'Done', 'Deferred', 'Blocked'];
    private const EVALUATOR_EXCLUDED = ['Admin', 'Product Owner', 'Developer', 'Tester'];
    private const EVALUATOR_OWN_TYPES = ['evaluation_task', 'feedback_implementation', 'app_requirement'];

    /** Which artifact_type values each role may use when creating a new work item. */
    private const CREATE_PERMISSIONS = [
        'Admin' => ['requirement', 'backlog', 'sprint_item', 'prototype_task', 'feedback_implementation', 'test_case', 'defect', 'release_task', 'retrospective_action', 'evaluation_task', 'app_requirement'],
        'Product Owner' => ['requirement', 'backlog', 'sprint_item', 'feedback_implementation', 'release_task'],
        'Developer' => ['prototype_task', 'feedback_implementation', 'defect'],
        'Tester' => ['test_case', 'defect'],
        'Evaluator' => ['evaluation_task', 'feedback_implementation', 'app_requirement'],
    ];

    public function index(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        Guard::requireProjectAccess($request, $projectId);
        $filters = $request->query;
        // Evaluator never sees the full workboard - only their own assigned items.
        if (($request->user['role'] ?? null) === 'Evaluator') {
            $filters['assignee_id'] = (int) $request->user['sub'];
        }
        Response::json(WorkItem::allForProject($projectId, $filters));
    }

    /** Create a new (manual, no backing artifact row) work item - the "Add Work Item" / To Do button. */
    public function store(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        Guard::requireProjectAccess($request, $projectId);

        $role = $request->user['role'] ?? null;
        $userId = (int) $request->user['sub'];
        $body = $request->body;
        $artifactType = $body['artifact_type'] ?? null;

        $allowedTypes = self::CREATE_PERMISSIONS[$role] ?? [];
        if (!in_array($artifactType, $allowedTypes, true)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized to create this work item type', 'data' => null]);
            exit;
        }

        if (empty($body['title'])) {
            Response::error('title is required', 422);
        }
        $status = $body['status'] ?? 'To Do';
        if (!in_array($status, self::BOARD_STATUSES, true)) {
            Response::error('Invalid status', 422);
        }

        // Developer/Tester/Evaluator default to assigning the task to themselves
        // when no assignee is given (matches how they'd actually use "their own" board).
        $assigneeId = $body['assignee_id'] ?? null;
        if (empty($assigneeId) && in_array($role, ['Developer', 'Tester', 'Evaluator'], true)) {
            $assigneeId = $userId;
        }

        $id = WorkItem::create([
            'project_id' => $projectId,
            'epa_phase' => $body['epa_phase'] ?? null,
            'epa_step' => $body['epa_step'] ?? null,
            'artifact_type' => $artifactType,
            'title' => $body['title'],
            'description' => $body['description'] ?? null,
            'status' => $status,
            'priority' => $body['priority'] ?? null,
            'assignee_id' => $assigneeId,
            'source_type' => $body['source_type'] ?? 'manual',
            'source_id' => $body['source_id'] ?? null,
            'sprint_id' => $body['sprint_id'] ?? null,
            'prototype_id' => $body['prototype_id'] ?? null,
            'due_date' => $body['due_date'] ?? null,
            'created_by' => $userId,
        ]);

        WorkItemActivityLog::record($id, $userId, 'created', null, $status, 'Work item created');
        EpaWorkflow::syncProjectCompletionStatus($projectId);
        Response::json(WorkItem::find($id), 201);
    }

    public function sync(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        Guard::requireProjectAccess($request, $projectId);
        $count = $this->syncArtifacts($projectId);
        Response::json(['synced' => $count]);
    }

    public function show(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $item = WorkItem::find($id);
        if (!$item) {
            Response::error('Work item not found', 404);
        }
        Guard::requireProjectAccess($request, (int) $item['project_id']);
        Response::json([
            'work_item' => $item,
            'comments' => WorkItemComment::allForWorkItem($id),
            'activity_log' => WorkItemActivityLog::allForWorkItem($id),
        ]);
    }

    public function updateStatus(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $item = WorkItem::find($id);
        if (!$item) {
            Response::error('Work item not found', 404);
        }
        Guard::requireProjectAccess($request, (int) $item['project_id']);

        $role = $request->user['role'] ?? null;
        $userId = (int) $request->user['sub'];
        $newStatus = $request->body['new_status'] ?? null;

        if (!in_array($newStatus, self::BOARD_STATUSES, true)) {
            Response::error('Invalid status', 422);
        }
        if ($role === 'Evaluator') {
            if (!$this->canEvaluatorAccess($item, $userId)) {
                Response::roleDenied();
            }
        } elseif (!in_array($role, self::EVALUATOR_EXCLUDED, true)) {
            Response::roleDenied();
        }

        $oldStatus = $item['status'];
        try {
            $this->writeThroughStatus($item, $newStatus, $role, $userId);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 422);
            return;
        }

        WorkItem::updateStatus($id, $newStatus);
        WorkItemActivityLog::record($id, $userId, 'status_changed', $oldStatus, $newStatus, "Status changed from {$oldStatus} to {$newStatus}");
        EpaWorkflow::syncProjectCompletionStatus((int) $item['project_id']);
        Response::json(WorkItem::find($id));
    }

    public function addComment(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $item = WorkItem::find($id);
        if (!$item) {
            Response::error('Work item not found', 404);
        }
        Guard::requireProjectAccess($request, (int) $item['project_id']);

        $role = $request->user['role'] ?? null;
        $userId = (int) $request->user['sub'];
        if ($role === 'Evaluator') {
            if (!$this->canEvaluatorAccess($item, $userId)) {
                Response::roleDenied();
            }
        } elseif (!in_array($role, self::EVALUATOR_EXCLUDED, true)) {
            Response::roleDenied();
        }

        $text = trim($request->body['comment_text'] ?? '');
        if ($text === '') {
            Response::error('comment_text is required', 422);
        }
        $commentId = WorkItemComment::create($id, $userId, $text);
        WorkItemActivityLog::record($id, $userId, 'commented', null, null, 'Comment added');
        Response::json(['id' => $commentId], 201);
    }

    /** Role- and EPA-rule-aware write-through to the underlying artifact table.
     *  Throws RuntimeException with a user-facing message on any violation so the
     *  frontend can revert the dragged card and display the error. */
    private function writeThroughStatus(array $item, string $newStatus, ?string $role, int $userId): void
    {
        switch ($item['artifact_type']) {
            case 'backlog':
                if (!in_array($role, ['Admin', 'Product Owner', 'Developer'], true)) {
                    throw new \RuntimeException('Your role cannot move backlog items on the board.');
                }
                if (in_array($newStatus, ['Ready for Testing', 'Testing'], true)) {
                    $prototypes = Prototype::allForProject((int) $item['project_id']);
                    if (empty($prototypes)) {
                        throw new \RuntimeException('Cannot move to Ready for Testing: no prototype version exists yet for this project.');
                    }
                }
                $mapped = match ($newStatus) {
                    'To Do', 'Deferred' => $newStatus,
                    'Done' => 'Done',
                    default => 'In Progress',
                };
                $backlog = Backlog::find((int) $item['artifact_id']);
                if ($backlog) {
                    Backlog::update((int) $item['artifact_id'], array_merge($backlog, ['status' => $mapped]));
                }
                break;

            case 'sprint_item':
                if (!in_array($role, ['Admin', 'Product Owner', 'Developer'], true)) {
                    throw new \RuntimeException('Your role cannot move sprint items on the board.');
                }
                $mapped = match ($newStatus) {
                    'Done' => 'Done',
                    'To Do', 'Deferred' => 'Planned',
                    default => 'In Progress',
                };
                $sprintItem = SprintItem::find((int) $item['artifact_id']);
                if ($sprintItem) {
                    SprintItem::update((int) $item['artifact_id'], ['assigned_to' => $sprintItem['assigned_to'], 'status' => $mapped]);
                }
                break;

            case 'defect':
                $defect = Defect::find((int) $item['artifact_id']);
                if (!$defect) {
                    break;
                }
                if ($role === 'Developer') {
                    if ((int) $defect['assigned_to'] !== $userId) {
                        throw new \RuntimeException('You can only update defects assigned to you.');
                    }
                    if (!in_array($newStatus, ['To Do', 'In Progress'], true)) {
                        throw new \RuntimeException('Developer cannot verify or close a defect - only Tester/Admin can do that.');
                    }
                    Defect::updateAssignment((int) $item['artifact_id'], $defect['assigned_to'], $newStatus === 'In Progress' ? 'Fixed' : 'Open');
                } elseif ($role === 'Tester') {
                    if ($newStatus === 'Done') {
                        if ($defect['status'] !== 'Fixed') {
                            throw new \RuntimeException('Cannot close/verify a defect that has not been fixed yet.');
                        }
                        Defect::markVerified((int) $item['artifact_id'], $userId);
                    } else {
                        $mapped = $newStatus === 'In Progress' ? 'Fixed' : 'Open';
                        Defect::updateStatus((int) $item['artifact_id'], $mapped);
                    }
                } elseif ($role === 'Admin') {
                    $mapped = match ($newStatus) {
                        'Done' => 'Verified',
                        'To Do' => 'Open',
                        default => 'Fixed',
                    };
                    Defect::updateStatus((int) $item['artifact_id'], $mapped);
                } else {
                    throw new \RuntimeException('Your role cannot move defects on the board.');
                }
                break;

            default:
                // Every other artifact_type (prototype_task, test_case, release_task,
                // requirement, retrospective_action, evaluation_task, app_requirement,
                // and manually-created feedback_implementation items): board-only status
                // change, no write-through. updateStatus() already gated who may reach
                // here (the 4 non-Evaluator roles freely, Evaluator only on their own
                // evaluation/feedback/app_requirement items), so nothing further to check.
                break;
        }
    }

    /** Evaluator may only touch a work item that's assigned to them and is one of the
     *  3 types they're allowed to create (evaluation_task/feedback_implementation/app_requirement). */
    private function canEvaluatorAccess(array $item, int $userId): bool
    {
        return (int) ($item['assignee_id'] ?? 0) === $userId
            && in_array($item['artifact_type'], self::EVALUATOR_OWN_TYPES, true);
    }

    /** Upserts all 7 artifact types for a project into work_items. Idempotent - safe to
     *  re-run any time (e.g. after manual DB changes) since it keys off UNIQUE(artifact_type, artifact_id). */
    private function syncArtifacts(int $projectId): int
    {
        $db = Database::connection();
        $count = 0;

        $stmt = $db->prepare('SELECT * FROM backlogs WHERE project_id = ?');
        $stmt->execute([$projectId]);
        foreach ($stmt->fetchAll() as $b) {
            WorkItem::upsert([
                'project_id' => $projectId,
                'epa_phase' => 'Initial Phase',
                'epa_step' => 'REQ_BACKLOG',
                'artifact_type' => 'backlog',
                'artifact_id' => $b['id'],
                'title' => $b['code'] . ' - ' . $b['title'],
                'description' => $b['user_story'],
                'status' => match ($b['status']) {
                    'Done' => 'Done',
                    'Deferred' => 'Deferred',
                    'In Progress' => 'In Progress',
                    default => 'To Do',
                },
                'priority' => $b['priority'],
                'assignee_id' => $b['assigned_to'],
                'source_type' => $b['source_type'],
                'source_id' => $b['source_id'],
                'created_by' => $b['created_by'],
            ]);
            $count++;
        }

        $stmt = $db->prepare(
            'SELECT si.*, b.title AS backlog_title, b.priority AS backlog_priority, s.project_id
             FROM sprint_items si JOIN backlogs b ON b.id = si.backlog_id JOIN sprints s ON s.id = si.sprint_id
             WHERE s.project_id = ?'
        );
        $stmt->execute([$projectId]);
        foreach ($stmt->fetchAll() as $si) {
            WorkItem::upsert([
                'project_id' => $projectId,
                'epa_phase' => 'Dev & Testing Phase',
                'epa_step' => 'PROTOTYPE_DESIGN_SPRINT',
                'artifact_type' => 'sprint_item',
                'artifact_id' => $si['id'],
                'title' => 'Sprint Task: ' . $si['backlog_title'],
                'status' => match ($si['status']) {
                    'Done' => 'Done',
                    'In Progress' => 'In Progress',
                    default => 'To Do',
                },
                'priority' => $si['backlog_priority'],
                'assignee_id' => $si['assigned_to'],
                'sprint_id' => $si['sprint_id'],
            ]);
            $count++;
        }

        $stmt = $db->prepare('SELECT * FROM prototypes WHERE project_id = ?');
        $stmt->execute([$projectId]);
        foreach ($stmt->fetchAll() as $p) {
            WorkItem::upsert([
                'project_id' => $projectId,
                'epa_phase' => 'Dev & Testing Phase',
                'epa_step' => 'DEMO_PROTOTYPE',
                'artifact_type' => 'prototype_task',
                'artifact_id' => $p['id'],
                'title' => 'Prototype ' . ($p['version_label'] ?? $p['version_number']) . ($p['prototype_name'] ? ' - ' . $p['prototype_name'] : ''),
                'description' => $p['notes'],
                'status' => match ($p['status']) {
                    'Completed' => 'Done',
                    'Ready for Testing' => 'Ready for Testing',
                    'Revised' => 'Review',
                    'In Progress' => 'In Progress',
                    default => 'To Do',
                },
                'sprint_id' => $p['sprint_id'],
                'prototype_id' => $p['id'],
                'created_by' => $p['created_by'],
            ]);
            $count++;
        }

        $stmt = $db->prepare(
            'SELECT fi.*, f.project_id, f.finding FROM feedback_implementation fi
             JOIN feedback f ON f.id = fi.feedback_id WHERE f.project_id = ?'
        );
        $stmt->execute([$projectId]);
        foreach ($stmt->fetchAll() as $fi) {
            WorkItem::upsert([
                'project_id' => $projectId,
                'epa_phase' => 'Dev & Testing Phase',
                'epa_step' => 'INCREMENT_DELIVERY',
                'artifact_type' => 'feedback_implementation',
                'artifact_id' => $fi['id'],
                'title' => 'Implement Feedback: ' . mb_strimwidth($fi['finding'], 0, 60, '...'),
                'description' => $fi['implementation_action'],
                'status' => match ($fi['status']) {
                    'Implemented' => 'Done',
                    'In Progress' => 'In Progress',
                    'Need Clarification' => 'Review',
                    default => 'To Do',
                },
                'assignee_id' => $fi['assigned_developer'],
                'prototype_id' => $fi['prototype_id'],
                'source_type' => 'feedback',
                'source_id' => $fi['feedback_id'],
            ]);
            $count++;
        }

        $stmt = $db->prepare('SELECT * FROM tests WHERE project_id = ?');
        $stmt->execute([$projectId]);
        foreach ($stmt->fetchAll() as $t) {
            WorkItem::upsert([
                'project_id' => $projectId,
                'epa_phase' => 'Dev & Testing Phase',
                'epa_step' => 'USER_TESTING',
                'artifact_type' => 'test_case',
                'artifact_id' => $t['id'],
                'title' => $t['code'] . ' - ' . $t['scenario'],
                'status' => match ($t['result']) {
                    'Pass' => 'Done',
                    'Fail' => 'Review',
                    'Retest' => 'Testing',
                    default => 'To Do',
                },
                'priority' => match ($t['severity']) {
                    'Critical', 'High' => 'Must',
                    'Medium' => 'Should',
                    default => 'Could',
                },
                'assignee_id' => $t['executed_by'],
                'prototype_id' => $t['prototype_id'],
            ]);
            $count++;
        }

        $stmt = $db->prepare('SELECT * FROM defects WHERE project_id = ?');
        $stmt->execute([$projectId]);
        foreach ($stmt->fetchAll() as $d) {
            WorkItem::upsert([
                'project_id' => $projectId,
                'epa_phase' => 'Dev & Testing Phase',
                'epa_step' => 'USER_TESTING',
                'artifact_type' => 'defect',
                'artifact_id' => $d['id'],
                'title' => $d['title'] ?? ('Defect #' . $d['id']),
                'description' => $d['description'],
                'status' => match ($d['status']) {
                    'Verified', 'Closed' => 'Done',
                    'Fixed' => 'In Progress',
                    default => 'To Do',
                },
                'priority' => match ($d['severity']) {
                    'Critical', 'High' => 'Must',
                    'Medium' => 'Should',
                    default => 'Could',
                },
                'assignee_id' => $d['assigned_to'],
            ]);
            $count++;
        }

        $stmt = $db->prepare('SELECT * FROM release_checklists WHERE project_id = ?');
        $stmt->execute([$projectId]);
        foreach ($stmt->fetchAll() as $r) {
            WorkItem::upsert([
                'project_id' => $projectId,
                'epa_phase' => 'Release Phase',
                'epa_step' => 'PRODUCT_RELEASE',
                'artifact_type' => 'release_task',
                'artifact_id' => $r['id'],
                'title' => $r['item'],
                'status' => $r['status'] === 'Yes' ? 'Done' : 'To Do',
                'assignee_id' => $r['verified_by'],
            ]);
            $count++;
        }

        return $count;
    }
}
