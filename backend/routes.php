<?php

use App\Controllers\AiGenerationController;
use App\Controllers\AuditLogController;
use App\Controllers\AuthController;
use App\Controllers\BacklogController;
use App\Controllers\DashboardController;
use App\Controllers\DefectController;
use App\Controllers\EpaWorkflowController;
use App\Controllers\EvaluationController;
use App\Controllers\FeedbackController;
use App\Controllers\FeedbackImplementationController;
use App\Controllers\PhaseController;
use App\Controllers\ProjectController;
use App\Controllers\ProjectMemberController;
use App\Controllers\PrototypeController;
use App\Controllers\ReleaseChecklistController;
use App\Controllers\ReportController;
use App\Controllers\RetrospectiveController;
use App\Controllers\RoleController;
use App\Controllers\SprintController;
use App\Controllers\SprintItemController;
use App\Controllers\TestItemController;
use App\Controllers\UserController;
use App\Controllers\WorkboardController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\ProjectMemberMiddleware;
use App\Middleware\RoleMiddleware;

$router = new Router();
$auth = new AuthMiddleware();
$member = new ProjectMemberMiddleware();

$ADMIN = ['Admin'];
$ADMIN_PO = ['Admin', 'Product Owner'];
$ADMIN_PO_DEV = ['Admin', 'Product Owner', 'Developer'];
$ADMIN_PO_DEV_TEST = ['Admin', 'Product Owner', 'Developer', 'Tester'];
$ADMIN_TEST = ['Admin', 'Tester'];
$ADMIN_DEV = ['Admin', 'Developer'];
$ADMIN_DEV_TEST = ['Admin', 'Developer', 'Tester'];
$ADMIN_EVAL = ['Admin', 'Evaluator'];
$ADMIN_PO_EVAL = ['Admin', 'Product Owner', 'Evaluator'];
$ADMIN_PO_DEV_TEST_EVAL = ['Admin', 'Product Owner', 'Developer', 'Tester', 'Evaluator'];
$ALL_ROLES = ['Admin', 'Product Owner', 'Developer', 'Tester', 'Evaluator'];

// Auth
$router->post('/api/v1/auth/login', [AuthController::class, 'login']);
$router->get('/api/v1/auth/me', [AuthController::class, 'me'], [$auth]);

// Users (Admin only)
$router->get('/api/v1/users', [UserController::class, 'index'], [$auth, RoleMiddleware::allow($ADMIN)]);
$router->post('/api/v1/users', [UserController::class, 'store'], [$auth, RoleMiddleware::allow($ADMIN)]);
$router->put('/api/v1/users/{id}', [UserController::class, 'update'], [$auth, RoleMiddleware::allow($ADMIN)]);
$router->delete('/api/v1/users/{id}', [UserController::class, 'destroy'], [$auth, RoleMiddleware::allow($ADMIN)]);

// Roles (Admin only - populates user management selects)
$router->get('/api/v1/roles', [RoleController::class, 'index'], [$auth, RoleMiddleware::allow($ADMIN)]);

// Projects (Admin full CRUD; Product Owner may submit a proposal (status=Proposed) and
// update functional info on projects they're a member of - field-restricted in ProjectController::update;
// Developer/Tester/Evaluator cannot create or update a project at all)
$router->get('/api/v1/projects', [ProjectController::class, 'index'], [$auth]);
$router->get('/api/v1/projects/{id}', [ProjectController::class, 'show'], [$auth, $member]);
$router->post('/api/v1/projects', [ProjectController::class, 'store'], [$auth, RoleMiddleware::allow($ADMIN_PO)]);
$router->put('/api/v1/projects/{id}', [ProjectController::class, 'update'], [$auth, RoleMiddleware::allow($ADMIN_PO)]);
$router->post('/api/v1/projects/{id}/approve', [ProjectController::class, 'approve'], [$auth, RoleMiddleware::allow($ADMIN)]);
$router->delete('/api/v1/projects/{id}', [ProjectController::class, 'destroy'], [$auth, RoleMiddleware::allow($ADMIN)]);

// Project members (Admin manages; PO can view own project roster)
$router->get('/api/v1/projects/{id}/members', [ProjectMemberController::class, 'index'], [$auth, $member]);
$router->post('/api/v1/projects/{id}/members', [ProjectMemberController::class, 'store'], [$auth, RoleMiddleware::allow($ADMIN)]);
$router->delete('/api/v1/project-members/{id}', [ProjectMemberController::class, 'destroy'], [$auth, RoleMiddleware::allow($ADMIN)]);

// EPA phases / timeline (readable by every project member, including Evaluator)
$router->get('/api/v1/projects/{id}/phases', [PhaseController::class, 'index'], [$auth, $member]);
$router->post('/api/v1/projects/{id}/phases', [PhaseController::class, 'store'], [$auth, RoleMiddleware::allow($ADMIN)]);
$router->put('/api/v1/phases/{id}', [PhaseController::class, 'update'], [$auth, RoleMiddleware::allow($ADMIN)]);
$router->delete('/api/v1/phases/{id}', [PhaseController::class, 'destroy'], [$auth, RoleMiddleware::allow($ADMIN)]);

// Adaptive backlog (Evaluator has no access at all)
$router->get('/api/v1/projects/{id}/backlog', [BacklogController::class, 'index'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO_DEV_TEST)]);
$router->get('/api/v1/my/backlog', [BacklogController::class, 'mine'], [$auth, RoleMiddleware::allow($ADMIN_DEV)]);
$router->post('/api/v1/projects/{id}/backlog', [BacklogController::class, 'store'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO)]);
$router->put('/api/v1/backlog/{id}', [BacklogController::class, 'update'], [$auth, RoleMiddleware::allow($ADMIN_PO_DEV)]);
$router->delete('/api/v1/backlog/{id}', [BacklogController::class, 'destroy'], [$auth, RoleMiddleware::allow($ADMIN_PO)]);
$router->post('/api/v1/projects/{id}/backlog/assign-to-sprint', [BacklogController::class, 'assignToSprint'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO)]);

// Sprints (Evaluator has no access)
$router->get('/api/v1/projects/{id}/sprints', [SprintController::class, 'index'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO_DEV_TEST)]);
$router->post('/api/v1/projects/{id}/sprints', [SprintController::class, 'store'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO)]);
$router->put('/api/v1/sprints/{id}', [SprintController::class, 'update'], [$auth, RoleMiddleware::allow($ADMIN_PO)]);
$router->delete('/api/v1/sprints/{id}', [SprintController::class, 'destroy'], [$auth, RoleMiddleware::allow($ADMIN_PO)]);

// Sprint items / task board
$router->get('/api/v1/sprints/{id}/items', [SprintItemController::class, 'index'], [$auth, RoleMiddleware::allow($ADMIN_PO_DEV_TEST)]);
$router->get('/api/v1/my/sprint-items', [SprintItemController::class, 'mine'], [$auth, RoleMiddleware::allow($ADMIN_DEV)]);
$router->post('/api/v1/sprints/{id}/items', [SprintItemController::class, 'store'], [$auth, RoleMiddleware::allow($ADMIN_PO)]);
$router->put('/api/v1/sprint-items/{id}', [SprintItemController::class, 'update'], [$auth, RoleMiddleware::allow($ADMIN_PO_DEV)]);
$router->delete('/api/v1/sprint-items/{id}', [SprintItemController::class, 'destroy'], [$auth, RoleMiddleware::allow($ADMIN_PO)]);

// Prototypes (all project members can view, including Evaluator; Admin+Developer build them)
$router->get('/api/v1/projects/{id}/prototypes', [PrototypeController::class, 'index'], [$auth, $member]);
$router->post('/api/v1/projects/{id}/prototypes', [PrototypeController::class, 'store'], [$auth, $member, RoleMiddleware::allow($ADMIN_DEV)]);
$router->put('/api/v1/prototypes/{id}', [PrototypeController::class, 'update'], [$auth, RoleMiddleware::allow($ADMIN_DEV)]);
$router->delete('/api/v1/prototypes/{id}', [PrototypeController::class, 'destroy'], [$auth, RoleMiddleware::allow($ADMIN)]);

// User evaluation / feedback log (Evaluator only ever sees/creates their own rows - filtered in controller)
$router->get('/api/v1/projects/{id}/feedback', [FeedbackController::class, 'index'], [$auth, $member]);
$router->get('/api/v1/my/feedback', [FeedbackController::class, 'mine'], [$auth, RoleMiddleware::allow($ADMIN_DEV)]);
$router->post('/api/v1/projects/{id}/feedback', [FeedbackController::class, 'store'], [$auth, $member, RoleMiddleware::allow($ADMIN_EVAL)]);
$router->put('/api/v1/feedback/{id}', [FeedbackController::class, 'update'], [$auth, RoleMiddleware::allow($ADMIN_PO)]);
$router->post('/api/v1/feedback/{id}/convert', [FeedbackController::class, 'convert'], [$auth, RoleMiddleware::allow($ADMIN_PO)]);
$router->post('/api/v1/feedback/{id}/implement', [FeedbackController::class, 'implement'], [$auth, RoleMiddleware::allow($ADMIN_DEV)]);
$router->delete('/api/v1/feedback/{id}', [FeedbackController::class, 'destroy'], [$auth, RoleMiddleware::allow($ADMIN)]);

// Testing matrix (Evaluator has no access)
$router->get('/api/v1/projects/{id}/tests', [TestItemController::class, 'index'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO_DEV_TEST)]);
$router->get('/api/v1/prototypes/{id}/tests', [TestItemController::class, 'byPrototype'], [$auth, RoleMiddleware::allow($ADMIN_PO_DEV_TEST)]);
$router->post('/api/v1/projects/{id}/tests', [TestItemController::class, 'store'], [$auth, $member, RoleMiddleware::allow($ADMIN_TEST)]);
$router->put('/api/v1/tests/{id}', [TestItemController::class, 'update'], [$auth, RoleMiddleware::allow($ADMIN_TEST)]);
$router->delete('/api/v1/tests/{id}', [TestItemController::class, 'destroy'], [$auth, RoleMiddleware::allow($ADMIN_TEST)]);

// Defects (Tester reports manually; auto-generated from failed tests too; Evaluator has no access)
$router->get('/api/v1/projects/{id}/defects', [DefectController::class, 'index'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO_DEV_TEST)]);
$router->get('/api/v1/my/defects', [DefectController::class, 'mine'], [$auth, RoleMiddleware::allow($ADMIN_DEV)]);
$router->post('/api/v1/projects/{id}/defects', [DefectController::class, 'store'], [$auth, $member, RoleMiddleware::allow($ADMIN_TEST)]);
$router->put('/api/v1/defects/{id}', [DefectController::class, 'update'], [$auth, RoleMiddleware::allow($ADMIN_DEV_TEST)]);
$router->post('/api/v1/defects/{id}/verify', [DefectController::class, 'verify'], [$auth, RoleMiddleware::allow($ADMIN_TEST)]);

// Release checklist (Evaluator has no access; PO approves)
$router->get('/api/v1/projects/{id}/release-checklist', [ReleaseChecklistController::class, 'index'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO_DEV_TEST)]);
$router->post('/api/v1/projects/{id}/release-checklist', [ReleaseChecklistController::class, 'store'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO)]);
$router->put('/api/v1/release-checklist/{id}', [ReleaseChecklistController::class, 'update'], [$auth, RoleMiddleware::allow($ADMIN_PO)]);
$router->delete('/api/v1/release-checklist/{id}', [ReleaseChecklistController::class, 'destroy'], [$auth, RoleMiddleware::allow($ADMIN)]);

// Retrospective (Evaluator has no access)
$router->get('/api/v1/projects/{id}/retrospectives', [RetrospectiveController::class, 'index'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO_DEV_TEST)]);
$router->post('/api/v1/projects/{id}/retrospectives', [RetrospectiveController::class, 'store'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO)]);
$router->put('/api/v1/retrospectives/{id}', [RetrospectiveController::class, 'update'], [$auth, RoleMiddleware::allow($ADMIN_PO)]);
$router->delete('/api/v1/retrospectives/{id}', [RetrospectiveController::class, 'destroy'], [$auth, RoleMiddleware::allow($ADMIN)]);

// Evaluations (Evaluator creates; Admin/PO/Developer read for a prototype or a whole project)
$router->get('/api/v1/prototypes/{id}/evaluations', [EvaluationController::class, 'index'], [$auth, RoleMiddleware::allow($ADMIN_PO_DEV)]);
$router->post('/api/v1/prototypes/{id}/evaluations', [EvaluationController::class, 'store'], [$auth, RoleMiddleware::allow($ADMIN_EVAL)]);
$router->get('/api/v1/projects/{id}/evaluations', [EvaluationController::class, 'byProject'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO_DEV)]);
$router->get('/api/v1/my/evaluations', [EvaluationController::class, 'mine'], [$auth, RoleMiddleware::allow($ADMIN_EVAL)]);

// Feedback implementation (Developer logs implementation work against a feedback item)
$router->get('/api/v1/feedback/{id}/implementations', [FeedbackImplementationController::class, 'index'], [$auth, RoleMiddleware::allow($ADMIN_PO_DEV)]);
$router->post('/api/v1/feedback/{id}/implementations', [FeedbackImplementationController::class, 'store'], [$auth, RoleMiddleware::allow($ADMIN_DEV)]);
$router->put('/api/v1/feedback-implementation/{id}', [FeedbackImplementationController::class, 'update'], [$auth, RoleMiddleware::allow($ADMIN_DEV)]);
$router->get('/api/v1/my/feedback-implementations', [FeedbackImplementationController::class, 'mine'], [$auth, RoleMiddleware::allow($ADMIN_DEV)]);

// Dashboard metrics (project-scoped, any project member)
$router->get('/api/v1/projects/{id}/dashboard', [DashboardController::class, 'metrics'], [$auth, $member]);

// Reports & audit logs (Admin only)
$router->get('/api/v1/reports/summary', [ReportController::class, 'summary'], [$auth, RoleMiddleware::allow($ADMIN)]);
$router->get('/api/v1/audit-logs', [AuditLogController::class, 'index'], [$auth, RoleMiddleware::allow($ADMIN)]);

// AI Project Generator (Mode 1: local template-based; Mode 2 external AI is an optional future extension)
$router->get('/api/v1/ai-generator/requests', [AiGenerationController::class, 'index'], [$auth, RoleMiddleware::allow($ADMIN_PO_DEV_TEST_EVAL)]);
$router->get('/api/v1/ai-generator/requests/{id}', [AiGenerationController::class, 'show'], [$auth, RoleMiddleware::allow($ADMIN_PO_DEV_TEST_EVAL)]);
$router->post('/api/v1/ai-generator/requests', [AiGenerationController::class, 'store'], [$auth, RoleMiddleware::allow($ADMIN_PO_EVAL)]);
$router->post('/api/v1/ai-generator/requests/{id}/preview-plan', [AiGenerationController::class, 'previewPlan'], [$auth, RoleMiddleware::allow($ADMIN_PO)]);
$router->post('/api/v1/ai-generator/requests/{id}/generate-project', [AiGenerationController::class, 'generateProject'], [$auth, RoleMiddleware::allow($ADMIN_PO)]);
$router->post('/api/v1/ai-generator/requests/{id}/generate-prototype', [AiGenerationController::class, 'generatePrototype'], [$auth, RoleMiddleware::allow($ADMIN_PO)]);
$router->post('/api/v1/ai-generator/regenerate', [AiGenerationController::class, 'regeneratePrototype'], [$auth, RoleMiddleware::allow($ADMIN_PO_DEV)]);

// Continue Existing Project with AI Generator (projects created before the AI Generator existed)
$router->get('/api/v1/projects/{id}/ai-continuation/inspect', [AiGenerationController::class, 'inspectExistingProject'], [$auth, $member]);
$router->post('/api/v1/projects/{id}/ai-continuation/continue', [AiGenerationController::class, 'continueExistingProject'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO)]);
$router->post('/api/v1/projects/{id}/ai-continuation/generate-prototype', [AiGenerationController::class, 'generateMissingPrototype'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO_DEV)]);

// AI Prototype Output Generator (v2 enhancement: modern UI/gallery/mobile scaffold prototype
// increments from EPA artifacts, with auto-filled Demo/Repository/Build/Mobile URLs)
$router->post('/api/v1/projects/{id}/ai-generator/preview-increment-plan', [AiGenerationController::class, 'previewIncrementPlan'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO_DEV)]);
$router->post('/api/v1/projects/{id}/ai-generator/generate-increment', [AiGenerationController::class, 'generatePrototypeIncrement'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO_DEV)]);
$router->post('/api/v1/projects/{id}/ai-generator/auto-links', [AiGenerationController::class, 'autoLinks'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO_DEV)]);

// EPA Workflow engine (3 phases / 11 steps - enforces the real EPA lifecycle sequence)
$router->get('/api/v1/epa/workflow', [EpaWorkflowController::class, 'getWorkflow'], [$auth]);
$router->get('/api/v1/epa/phase-dashboard', [EpaWorkflowController::class, 'phaseDashboard'], [$auth]);
$router->post('/api/v1/epa/sync-all', [EpaWorkflowController::class, 'syncAllProjects'], [$auth, RoleMiddleware::allow($ADMIN)]);
$router->get('/api/v1/projects/{id}/epa/status', [EpaWorkflowController::class, 'projectStatus'], [$auth, $member]);
$router->post('/api/v1/projects/{id}/epa/validate', [EpaWorkflowController::class, 'validateStep'], [$auth, $member]);
$router->post('/api/v1/projects/{id}/epa/move-next', [EpaWorkflowController::class, 'moveNextStep'], [$auth, $member]);
$router->post('/api/v1/projects/{id}/epa/sync', [EpaWorkflowController::class, 'syncProject'], [$auth, $member]);

// EPA Workboard (enhancement layer over existing artifacts - Evaluator has no access,
// same as backlog/sprint/testing/defects which it syncs from)
$router->get('/api/v1/projects/{id}/workboard', [WorkboardController::class, 'index'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO_DEV_TEST_EVAL)]);
$router->post('/api/v1/projects/{id}/workboard', [WorkboardController::class, 'store'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO_DEV_TEST_EVAL)]);
$router->post('/api/v1/projects/{id}/workboard/sync', [WorkboardController::class, 'sync'], [$auth, $member, RoleMiddleware::allow($ADMIN_PO)]);
$router->get('/api/v1/work-items/{id}', [WorkboardController::class, 'show'], [$auth]);
$router->put('/api/v1/work-items/{id}/status', [WorkboardController::class, 'updateStatus'], [$auth]);
$router->post('/api/v1/work-items/{id}/comments', [WorkboardController::class, 'addComment'], [$auth]);

$router->dispatch($request);
