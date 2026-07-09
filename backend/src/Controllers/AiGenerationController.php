<?php

namespace App\Controllers;

use App\Core\AiGenerator;
use App\Core\Database;
use App\Core\EpaWorkflow;
use App\Core\Guard;
use App\Core\Request;
use App\Core\Response;
use App\Models\AiGenerationRequest;
use App\Models\AuditLog;
use App\Models\Backlog;
use App\Models\Feedback;
use App\Models\GeneratedArtifact;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Prototype;
use App\Models\Sprint;
use App\Models\SprintItem;
use App\Models\TestItem;
use App\Models\User;

class AiGenerationController
{
    private const DEMO_BASE_URL = 'http://localhost/epa-framework/';

    public function index(Request $request): void
    {
        if (($request->user['role'] ?? null) === 'Admin') {
            Response::json(AiGenerationRequest::all());
        } else {
            Response::json(AiGenerationRequest::allForUser((int) $request->user['sub']));
        }
    }

    public function show(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $req = AiGenerationRequest::find($id);
        if (!$req) {
            Response::error('Generation request not found', 404);
        }
        Response::json([
            'request' => $req,
            'artifacts' => GeneratedArtifact::allForRequest($id),
            'project' => $req['generated_project_id'] ? Project::find((int) $req['generated_project_id']) : null,
            'prototype' => $req['generated_prototype_id'] ? Prototype::find((int) $req['generated_prototype_id']) : null,
        ]);
    }

    public function store(Request $request): void
    {
        $body = $request->body;
        if (empty($body['application_name'])) {
            Response::error('application_name is required', 422);
        }
        $id = AiGenerationRequest::create(array_merge($body, [
            'requested_by' => $request->user['sub'],
            'user_role' => $request->user['role'],
        ]));
        AuditLog::record($request->user['sub'], 'create', 'ai_generation_request', $id);
        Response::json(AiGenerationRequest::find($id), 201);
    }

    public function previewPlan(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $req = AiGenerationRequest::find($id);
        if (!$req) {
            Response::error('Generation request not found', 404);
        }
        $plan = AiGenerator::generateEPAPlan($req);
        AiGenerationRequest::updateFields($id, [
            'generation_status' => 'Previewed',
            'ai_raw_response' => json_encode($plan),
        ]);
        Response::json($plan);
    }

    public function generateProject(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $req = AiGenerationRequest::find($id);
        if (!$req) {
            Response::error('Generation request not found', 404);
        }
        $plan = $req['ai_raw_response'] ? json_decode($req['ai_raw_response'], true) : AiGenerator::generateEPAPlan($req);

        $requester = User::find((int) $req['requested_by']);
        $ownerId = (int) ($request->body['owner_id'] ?? (
            $requester && $requester['role_name'] === 'Product Owner' ? $requester['id'] : $request->user['sub']
        ));

        $slug = AiGenerator::generateProjectSlug($req['application_name']);
        $projectId = Project::create([
            'name' => $req['application_name'],
            'project_code' => 'AI-' . strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($slug)), 0, 10)),
            'description' => $req['business_problem'],
            'client_name' => $req['client_name'],
            'project_type' => $req['project_type'],
            'current_epa_phase' => 'Requirements Elicitation & Project Adaptive Backlog',
            'owner_id' => $ownerId,
            'created_by' => $request->user['sub'],
            'status' => 'Draft',
        ]);
        ProjectMember::add($projectId, $ownerId, 'Product Owner');
        ProjectMember::add($projectId, (int) $request->user['sub'], $request->user['role']);

        $backlogIds = [];
        foreach ($plan['backlog'] as $item) {
            $bId = Backlog::create([
                'project_id' => $projectId,
                'code' => $item['code'],
                'title' => $item['title'],
                'priority' => $item['priority'],
                'status' => 'To Do',
                'source_type' => 'ai_generated',
                'created_by' => $request->user['sub'],
            ]);
            $backlogIds[$item['code']] = $bId;
            GeneratedArtifact::create([
                'generation_request_id' => $id, 'project_id' => $projectId, 'artifact_type' => 'backlog',
                'artifact_title' => $item['title'], 'created_by' => $request->user['sub'],
            ]);
        }
        foreach ($plan['requirements'] as $reqItem) {
            GeneratedArtifact::create([
                'generation_request_id' => $id, 'project_id' => $projectId, 'artifact_type' => 'requirement',
                'artifact_title' => $reqItem['title'], 'artifact_content' => $reqItem['user_story'], 'created_by' => $request->user['sub'],
            ]);
        }

        $sprintId = Sprint::create([
            'project_id' => $projectId,
            'name' => $plan['sprint1']['name'],
            'status' => 'Active',
        ]);
        foreach ($plan['sprint1']['items'] as $code) {
            if (isset($backlogIds[$code])) {
                SprintItem::create(['sprint_id' => $sprintId, 'backlog_id' => $backlogIds[$code], 'status' => 'Planned']);
            }
        }
        GeneratedArtifact::create([
            'generation_request_id' => $id, 'project_id' => $projectId, 'artifact_type' => 'sprint',
            'artifact_title' => $plan['sprint1']['name'], 'artifact_content' => $plan['sprint1']['goal'], 'created_by' => $request->user['sub'],
        ]);

        AiGenerationRequest::updateFields($id, [
            'generation_status' => 'Generating',
            'generated_project_id' => $projectId,
            'ai_raw_response' => json_encode($plan),
        ]);
        $epaStatus = EpaWorkflow::classifyExistingProjectEPAStatus($projectId);
        AuditLog::record($request->user['sub'], 'generate', 'project', $projectId, ['ai_generation_request_id' => $id]);
        Response::json(['project_id' => $projectId, 'slug' => $slug, 'plan' => $plan, 'epa_status' => $epaStatus], 201);
    }

    public function generatePrototype(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $req = AiGenerationRequest::find($id);
        if (!$req || !$req['generated_project_id']) {
            Response::error('Project must be generated before the prototype', 422);
        }
        $plan = json_decode($req['ai_raw_response'], true);
        $slug = AiGenerator::generateProjectSlug($req['application_name']);
        $projectId = (int) $req['generated_project_id'];

        $outputPath = AiGenerator::generatePrototypeFiles($req, $plan, $slug, 'v01');
        $demoUrl = self::DEMO_BASE_URL . $outputPath . '/index.html';

        $prototypeId = Prototype::create([
            'project_id' => $projectId,
            'version_number' => 'v0.1',
            'prototype_name' => $req['application_name'] . ' Initial Prototype',
            'demo_url' => $demoUrl,
            'build_output_url' => $demoUrl,
            'notes' => 'Generated by EPA Framework AI Project Generator (template mode).',
            'generated_by_ai' => true,
            'generation_request_id' => $id,
            'output_path' => $outputPath,
            'source_type' => 'ai_generated',
            'status' => 'Ready for Testing',
            'created_by' => $request->user['sub'],
        ]);

        foreach ($plan['testing_checklist'] as $scenario) {
            $code = TestItem::nextCode($projectId, 'UT');
            TestItem::create([
                'project_id' => $projectId, 'prototype_id' => $prototypeId, 'code' => $code,
                'scenario' => $scenario, 'type' => 'Functional', 'result' => 'Not Run', 'created_by' => $request->user['sub'],
            ]);
            GeneratedArtifact::create([
                'generation_request_id' => $id, 'project_id' => $projectId, 'artifact_type' => 'test_case',
                'artifact_title' => $scenario, 'created_by' => $request->user['sub'],
            ]);
        }
        GeneratedArtifact::create([
            'generation_request_id' => $id, 'project_id' => $projectId, 'artifact_type' => 'evaluation_form',
            'artifact_title' => 'Prototype Evaluation Criteria', 'artifact_content' => implode(', ', $plan['evaluation_criteria']),
            'created_by' => $request->user['sub'],
        ]);
        GeneratedArtifact::create([
            'generation_request_id' => $id, 'project_id' => $projectId, 'artifact_type' => 'prototype',
            'artifact_title' => $req['application_name'] . ' Initial Prototype', 'artifact_path' => $outputPath, 'created_by' => $request->user['sub'],
        ]);

        AiGenerationRequest::updateFields($id, [
            'generation_status' => 'Generated',
            'generated_prototype_id' => $prototypeId,
            'generated_output_path' => $outputPath,
            'generated_demo_url' => $demoUrl,
        ]);
        $epaStatus = EpaWorkflow::classifyExistingProjectEPAStatus($projectId);
        AuditLog::record($request->user['sub'], 'generate', 'prototype', $prototypeId, ['ai_generation_request_id' => $id]);
        Response::json(['prototype_id' => $prototypeId, 'demo_url' => $demoUrl, 'output_path' => $outputPath, 'epa_status' => $epaStatus], 201);
    }

    public function regeneratePrototype(Request $request): void
    {
        $body = $request->body;
        $projectId = (int) ($body['project_id'] ?? 0);
        $prototypeId = (int) ($body['prototype_id'] ?? 0);
        $feedbackIds = $body['feedback_ids'] ?? [];
        $existingPrototype = Prototype::find($prototypeId);
        $project = Project::find($projectId);
        if (!$existingPrototype || !$project) {
            Response::error('Project or prototype not found', 404);
        }
        Guard::requireProjectAccess($request, $projectId);

        $feedbackTexts = [];
        foreach ($feedbackIds as $fbId) {
            $fb = Feedback::find((int) $fbId);
            if ($fb) $feedbackTexts[] = $fb['finding'];
        }

        $requestId = $existingPrototype['generation_request_id'] ?? null;
        $baseInput = $requestId ? AiGenerationRequest::find((int) $requestId) : null;
        $input = $baseInput ?: [
            'application_name' => $project['name'],
            'client_name' => $project['client_name'] ?? '',
            'project_type' => $project['project_type'] ?? 'Web-Based Application',
            'main_objective' => $existingPrototype['notes'] ?? $project['name'],
            'primary_color' => '#0b4a3a',
        ];
        $input['main_features'] = trim(($input['main_features'] ?? '') . "\n" . implode("\n", $feedbackTexts));
        $input['workflow_description'] = $input['workflow_description'] ?? '';

        $plan = AiGenerator::generateEPAPlan($input);
        $slug = AiGenerator::generateProjectSlug($input['application_name']);
        $version = AiGenerator::nextAvailableVersion($slug);
        $outputPath = AiGenerator::generatePrototypeFiles($input, $plan, $slug, $version);
        $demoUrl = self::DEMO_BASE_URL . $outputPath . '/index.html';
        $displayVersion = preg_replace('/^v(\d)(\d+)$/', 'v$1.$2', $version);

        $summary = 'Implemented feedback: ' . implode('; ', $feedbackTexts);
        $newPrototypeId = Prototype::create([
            'project_id' => $projectId,
            'previous_prototype_id' => $prototypeId,
            'version_number' => $displayVersion,
            'prototype_name' => $input['application_name'] . ' Improved Prototype',
            'demo_url' => $demoUrl,
            'build_output_url' => $demoUrl,
            'notes' => 'Regenerated from evaluator feedback via AI Project Generator.',
            'implemented_feedback_summary' => $summary,
            'implemented_feedback_ids' => $feedbackIds,
            'generated_by_ai' => true,
            'generation_request_id' => $requestId,
            'output_path' => $outputPath,
            'source_type' => 'improved_from_feedback',
            'status' => 'Ready for Testing',
            'created_by' => $request->user['sub'],
        ]);

        foreach ($feedbackIds as $fbId) {
            Feedback::markImplemented((int) $fbId, $newPrototypeId, $displayVersion);
        }
        $epaStatus = EpaWorkflow::classifyExistingProjectEPAStatus($projectId);

        AuditLog::record($request->user['sub'], 'regenerate', 'prototype', $newPrototypeId, ['from_prototype_id' => $prototypeId]);
        Response::json(['prototype_id' => $newPrototypeId, 'demo_url' => $demoUrl, 'output_path' => $outputPath, 'epa_status' => $epaStatus], 201);
    }

    // ---------- Continue Existing Project with AI Generator ----------

    /** Checks which EPA artifacts an existing (possibly pre-AI-Generator) project is missing. */
    public function inspectExistingProject(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        $project = Project::find($projectId);
        if (!$project) {
            Response::error('Project not found', 404);
        }
        Response::json(self::completionStatus($project));
    }

    private static function completionStatus(array $project): array
    {
        $projectId = (int) $project['id'];
        $db = Database::connection();
        $count = function (string $sql) use ($db, $projectId): int {
            $stmt = $db->prepare($sql);
            $stmt->execute([$projectId]);
            return (int) $stmt->fetch()['c'];
        };

        // Backlog items double as EPA "requirements" in this system (title/user_story/
        // acceptance_criteria live on the same row), so both flags share one query.
        $hasBacklog = $count('SELECT COUNT(*) c FROM backlogs WHERE project_id = ?') > 0;
        $flags = [
            'has_requirements' => $hasBacklog,
            'has_backlog' => $hasBacklog,
            'has_sprint' => $count('SELECT COUNT(*) c FROM sprints WHERE project_id = ?') > 0,
            'has_prototype' => $count('SELECT COUNT(*) c FROM prototypes WHERE project_id = ?') > 0,
            'has_demo_url' => $count("SELECT COUNT(*) c FROM prototypes WHERE project_id = ? AND demo_url IS NOT NULL AND demo_url <> ''") > 0,
            'has_test_cases' => $count('SELECT COUNT(*) c FROM tests WHERE project_id = ?') > 0,
            'has_evaluation_form' => $count("SELECT COUNT(*) c FROM generated_artifacts WHERE project_id = ? AND artifact_type = 'evaluation_form'") > 0,
        ];
        $missing = array_keys(array_filter($flags, fn($v) => !$v));
        $percentage = (int) round((count($flags) - count($missing)) / count($flags) * 100);
        $epaStatus = $percentage >= 100 ? 'Complete' : 'Missing EPA Artifacts';
        if (($project['epa_completion_status'] ?? null) !== $epaStatus) {
            Project::updateCompletionStatus($projectId, $epaStatus);
        }

        return array_merge([
            'project_id' => $projectId,
            'project_name' => $project['name'],
        ], $flags, [
            'completion_percentage' => $percentage,
            'missing_items' => array_map(fn($k) => substr($k, 4), $missing), // has_x -> x
        ]);
    }

    /** Generates whichever of {backlog/requirements, sprint 1, prototype, test cases,
     *  evaluation form} an existing project is still missing, without duplicating what's already there. */
    public function continueExistingProject(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        $project = Project::find($projectId);
        if (!$project) {
            Response::error('Project not found', 404);
        }
        $status = self::completionStatus($project);
        $input = array_merge($request->body, ['application_name' => $project['name']]);

        $requestId = AiGenerationRequest::create(array_merge($input, [
            'requested_by' => $request->user['sub'],
            'user_role' => $request->user['role'],
        ]));
        $plan = AiGenerator::generateEPAPlan($input);
        AiGenerationRequest::updateFields($requestId, [
            'generated_project_id' => $projectId,
            'ai_raw_response' => json_encode($plan),
            'generation_status' => 'Generating',
        ]);

        $generatedRequirements = 0;
        $generatedBacklog = 0;
        $generatedSprint = 0;

        if (!$status['has_backlog']) {
            foreach ($plan['backlog'] as $item) {
                $bId = Backlog::create([
                    'project_id' => $projectId, 'code' => Backlog::nextCode($projectId), 'title' => $item['title'],
                    'priority' => $item['priority'], 'status' => 'To Do', 'source_type' => 'ai_generated', 'created_by' => $request->user['sub'],
                ]);
                GeneratedArtifact::create([
                    'generation_request_id' => $requestId, 'project_id' => $projectId, 'artifact_type' => 'backlog',
                    'artifact_title' => $item['title'], 'created_by' => $request->user['sub'],
                ]);
                $generatedBacklog++;
            }
            foreach ($plan['requirements'] as $reqItem) {
                GeneratedArtifact::create([
                    'generation_request_id' => $requestId, 'project_id' => $projectId, 'artifact_type' => 'requirement',
                    'artifact_title' => $reqItem['title'], 'artifact_content' => $reqItem['user_story'], 'created_by' => $request->user['sub'],
                ]);
                $generatedRequirements++;
            }
        }

        $allBacklog = Backlog::allForProject($projectId);
        if (!$status['has_sprint'] && $allBacklog) {
            $sprintId = Sprint::create(['project_id' => $projectId, 'name' => $plan['sprint1']['name'], 'status' => 'Active']);
            $mustItems = array_values(array_filter($allBacklog, fn($b) => $b['priority'] === 'Must')) ?: array_slice($allBacklog, 0, 3);
            foreach ($mustItems as $b) {
                SprintItem::create(['sprint_id' => $sprintId, 'backlog_id' => $b['id'], 'status' => 'Planned']);
            }
            GeneratedArtifact::create([
                'generation_request_id' => $requestId, 'project_id' => $projectId, 'artifact_type' => 'sprint',
                'artifact_title' => $plan['sprint1']['name'], 'artifact_content' => $plan['sprint1']['goal'], 'created_by' => $request->user['sub'],
            ]);
            $generatedSprint = 1;
        }

        $prototypeResult = ['prototype_id' => null, 'demo_url' => null, 'output_path' => null];
        if (!$status['has_prototype']) {
            $prototypeResult = $this->generatePrototypeForProject(
                $project, $input, $plan, $requestId, !$status['has_test_cases'], !$status['has_evaluation_form'],
                'continued_from_existing_project', $project['name'] . ' Continued Prototype',
                'Generated by continuing an existing project via EPA Framework AI Generator.',
                (int) $request->user['sub']
            );
        }

        Project::markContinuedByAi($projectId);
        AiGenerationRequest::updateFields($requestId, [
            'generation_status' => 'Generated',
            'generated_prototype_id' => $prototypeResult['prototype_id'],
            'generated_output_path' => $prototypeResult['output_path'],
            'generated_demo_url' => $prototypeResult['demo_url'],
        ]);
        $epaStatus = EpaWorkflow::classifyExistingProjectEPAStatus($projectId);
        AuditLog::record($request->user['sub'], 'continue', 'project', $projectId, ['ai_generation_request_id' => $requestId]);

        Response::json([
            'project_id' => $projectId,
            'generation_request_id' => $requestId,
            'generated_requirements_count' => $generatedRequirements,
            'generated_backlog_count' => $generatedBacklog,
            'generated_sprint_count' => $generatedSprint,
            'prototype_id' => $prototypeResult['prototype_id'],
            'demo_url' => $prototypeResult['demo_url'],
            'completion' => self::completionStatus(Project::find($projectId)),
            'epa_status' => $epaStatus,
        ], 201);
    }

    /** For a project that already has requirements/backlog but is still missing a prototype. */
    public function generateMissingPrototype(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        $project = Project::find($projectId);
        if (!$project) {
            Response::error('Project not found', 404);
        }
        $backlog = Backlog::allForProject($projectId);
        if (!$backlog) {
            Response::error('Project has no backlog yet. Use "Continue with AI Generator" first.', 422);
        }
        $status = self::completionStatus($project);

        $input = array_merge($request->body, ['application_name' => $project['name']]);
        $input['main_features'] = $input['main_features'] ?? implode("\n", array_column($backlog, 'title'));

        $requestId = AiGenerationRequest::create(array_merge($input, [
            'requested_by' => $request->user['sub'],
            'user_role' => $request->user['role'],
        ]));
        $plan = AiGenerator::generateEPAPlan($input);
        AiGenerationRequest::updateFields($requestId, ['generated_project_id' => $projectId, 'ai_raw_response' => json_encode($plan)]);

        $result = $this->generatePrototypeForProject(
            $project, $input, $plan, $requestId, !$status['has_test_cases'], !$status['has_evaluation_form'],
            'continued_from_existing_project', $request->body['prototype_name'] ?? ($project['name'] . ' Prototype'),
            'Generated for an existing project (missing prototype) via EPA Framework AI Generator.',
            (int) $request->user['sub']
        );

        Project::markContinuedByAi($projectId);
        AiGenerationRequest::updateFields($requestId, [
            'generation_status' => 'Generated',
            'generated_prototype_id' => $result['prototype_id'],
            'generated_output_path' => $result['output_path'],
            'generated_demo_url' => $result['demo_url'],
        ]);
        $result['epa_status'] = EpaWorkflow::classifyExistingProjectEPAStatus($projectId);
        AuditLog::record($request->user['sub'], 'generate', 'prototype', $result['prototype_id'], ['ai_generation_request_id' => $requestId, 'existing_project' => true]);
        Response::json($result, 201);
    }

    /** Shared by continueExistingProject/generateMissingPrototype: builds real prototype
     *  files, registers the prototype row, and (optionally) test cases + evaluation-form artifact. */
    private function generatePrototypeForProject(
        array $project, array $input, array $plan, int $requestId, bool $generateTests, bool $generateEvalForm,
        string $sourceType, string $prototypeName, string $notes, int $userId
    ): array {
        $slug = AiGenerator::generateProjectSlug($project['name']);
        $version = AiGenerator::nextAvailableVersion($slug);
        $outputPath = AiGenerator::generatePrototypeFiles($input, $plan, $slug, $version);
        $demoUrl = self::DEMO_BASE_URL . $outputPath . '/index.html';
        $projectId = (int) $project['id'];
        $previous = Prototype::allForProject($projectId)[0] ?? null;

        $prototypeId = Prototype::create([
            'project_id' => $projectId,
            'previous_prototype_id' => $previous['id'] ?? null,
            'version_number' => preg_replace('/^v(\d)(\d+)$/', 'v$1.$2', $version),
            'prototype_name' => $prototypeName,
            'demo_url' => $demoUrl,
            'build_output_url' => $demoUrl,
            'notes' => $notes,
            'generated_by_ai' => true,
            'generation_request_id' => $requestId,
            'output_path' => $outputPath,
            'source_type' => $sourceType,
            'status' => 'Ready for Testing',
            'created_by' => $userId,
        ]);

        if ($generateTests) {
            foreach ($plan['testing_checklist'] as $scenario) {
                $code = TestItem::nextCode($projectId, 'UT');
                TestItem::create([
                    'project_id' => $projectId, 'prototype_id' => $prototypeId, 'code' => $code,
                    'scenario' => $scenario, 'type' => 'Functional', 'result' => 'Not Run', 'created_by' => $userId,
                ]);
                GeneratedArtifact::create([
                    'generation_request_id' => $requestId, 'project_id' => $projectId, 'artifact_type' => 'test_case',
                    'artifact_title' => $scenario, 'created_by' => $userId,
                ]);
            }
        }
        if ($generateEvalForm) {
            GeneratedArtifact::create([
                'generation_request_id' => $requestId, 'project_id' => $projectId, 'artifact_type' => 'evaluation_form',
                'artifact_title' => 'Prototype Evaluation Criteria', 'artifact_content' => implode(', ', $plan['evaluation_criteria']),
                'created_by' => $userId,
            ]);
        }

        return ['prototype_id' => $prototypeId, 'demo_url' => $demoUrl, 'output_path' => $outputPath];
    }

    // ==================== AI Prototype Output Generator (v2 enhancement) ====================
    // New endpoints only - none of the methods above are modified. All three reuse
    // AiGenerator::generatePrototypeIncrementFromEPAArtifacts()/previewIncrementPlan() and the
    // same Feedback::markImplemented/Backlog::update/EpaWorkflow::sync pattern regeneratePrototype()
    // already uses above, so Demo/Repository/Build/Mobile URLs are always auto-filled - never typed.

    private function incrementOptionsFromBody(array $body): array
    {
        return [
            'output_type' => $body['output_type'] ?? 'web_app',
            'image_mode' => $body['image_mode'] ?? 'hybrid',
            'include_gallery' => $body['include_gallery'] ?? 'auto', // auto|force|none
            'feedback_ids' => $body['feedback_ids'] ?? [],
            'backlog_ids' => $body['backlog_ids'] ?? [],
            'defect_ids' => $body['defect_ids'] ?? [],
            'primary_color' => $body['primary_color'] ?? null,
        ];
    }

    /** Read-only "Preview Generation Plan" step - no files written, no DB rows created. */
    public function previewIncrementPlan(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        Guard::requireProjectAccess($request, $projectId);
        try {
            $preview = AiGenerator::previewIncrementPlan($projectId, $this->incrementOptionsFromBody($request->body));
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 404);
            return;
        }
        Response::json($preview);
    }

    /** The real "Generate Prototype Increment" action - from Development Workspace's AI
     *  Prototype Increment Generator, driven by explicit feedback/backlog/defect selection. */
    public function generatePrototypeIncrement(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        Guard::requireProjectAccess($request, $projectId);
        $body = $request->body;
        $userId = (int) $request->user['sub'];

        $requestId = AiGenerationRequest::create([
            'requested_by' => $userId,
            'user_role' => $request->user['role'],
            'application_name' => Project::find($projectId)['name'] ?? 'Prototype Increment',
            'output_type' => $body['output_type'] ?? 'web_app',
            'image_mode' => $body['image_mode'] ?? 'hybrid',
            'source_type' => $body['source_type'] ?? 'feedback_backlog',
            'source_ids' => json_encode(['feedback' => $body['feedback_ids'] ?? [], 'backlog' => $body['backlog_ids'] ?? [], 'defect' => $body['defect_ids'] ?? []]),
            'design_style' => $body['ui_style'] ?? 'Modern',
            'generation_status' => 'Generating',
        ]);

        try {
            $result = AiGenerator::generatePrototypeIncrementFromEPAArtifacts($projectId, $this->incrementOptionsFromBody($body));
        } catch (\RuntimeException $e) {
            AiGenerationRequest::updateFields($requestId, ['generation_status' => 'Failed', 'error_message' => $e->getMessage()]);
            Response::error($e->getMessage(), 422);
            return;
        }

        $prototypeId = Prototype::create([
            'project_id' => $projectId,
            'previous_prototype_id' => $result['previous_prototype_id'],
            'version_number' => $result['version'],
            'prototype_name' => (Project::find($projectId)['name'] ?? 'Prototype') . ' ' . $result['version'],
            'demo_url' => $result['demo_url'],
            'repository_url' => $result['repository_url'],
            'build_output_url' => $result['build_output_url'],
            'mobile_build_url' => $result['mobile_apk_ipa_link'],
            'notes' => 'Generated by AI Prototype Output Generator from ' . ($body['source_type'] ?? 'feedback/backlog') . '.',
            'implemented_feedback_ids' => $body['feedback_ids'] ?? [],
            'implemented_backlog_ids' => $result['source_backlog_ids'],
            'fixed_defect_ids' => $body['defect_ids'] ?? [],
            'generated_by_ai' => true,
            'generation_request_id' => $requestId,
            'output_path' => $result['output_path'],
            'source_type' => 'improved_from_feedback',
            'status' => 'Ready for Testing',
            'created_by' => $userId,
            'output_type' => $body['output_type'] ?? 'web_app',
            'image_mode' => $body['image_mode'] ?? 'hybrid',
            'ui_style' => $body['ui_style'] ?? 'Modern',
            'mobile_preview_url' => $result['mobile_preview_url'],
        ]);

        foreach (($body['feedback_ids'] ?? []) as $fbId) {
            Feedback::markImplemented((int) $fbId, $prototypeId, $result['version']);
        }
        foreach ($result['source_backlog_ids'] as $backlogId) {
            $existing = Backlog::find((int) $backlogId);
            if ($existing) {
                Backlog::update((int) $backlogId, array_merge($existing, ['status' => 'Done', 'target_prototype_version' => $result['version']]));
            }
        }

        AiGenerationRequest::updateFields($requestId, [
            'generation_status' => 'Generated',
            'generated_prototype_id' => $prototypeId,
            'generated_output_path' => $result['output_path'],
            'generated_demo_url' => $result['demo_url'],
        ]);
        $epaStatus = EpaWorkflow::syncProjectCompletionStatus($projectId);
        AuditLog::record($userId, 'generate', 'prototype', $prototypeId, ['ai_generation_request_id' => $requestId, 'increment' => true]);

        Response::json(array_merge($result, [
            'prototype_id' => $prototypeId,
            'generation_request_id' => $requestId,
            'epa_status' => $epaStatus,
        ]), 201);
    }

    /** Lightweight auto-fill for the plain "Create Prototype Increment" manual form: no
     *  explicit feedback/backlog/defect selection, just an output_type - still produces a
     *  real generated prototype (not a dead link) so Developer never types a URL by hand. */
    public function autoLinks(Request $request, array $params): void
    {
        $this->generatePrototypeIncrement($request, $params);
    }
}
