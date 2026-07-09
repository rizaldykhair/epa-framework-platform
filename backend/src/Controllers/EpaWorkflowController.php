<?php

namespace App\Controllers;

use App\Core\EpaWorkflow;
use App\Core\Request;
use App\Core\Response;
use App\Models\Project;

class EpaWorkflowController
{
    public function getWorkflow(Request $request): void
    {
        Response::json(EpaWorkflow::getEPAWorkflow());
    }

    public function projectStatus(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        if (!Project::find($projectId)) {
            Response::error('Project not found', 404);
        }
        Response::json(EpaWorkflow::getProjectEPAStatus($projectId));
    }

    public function validateStep(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        if (!Project::find($projectId)) {
            Response::error('Project not found', 404);
        }
        $stepCode = $request->body['step_code'] ?? (EpaWorkflow::getProjectEPAStatus($projectId)['current_step']);
        $result = EpaWorkflow::validateEPAArtifacts($projectId, $stepCode);
        $next = EpaWorkflow::nextStep($stepCode);
        $result['next_required_action'] = $result['valid']
            ? ($next ? 'Ready to move to next step: ' . $next['step_name'] : 'All EPA steps complete.')
            : 'Complete before continuing: ' . implode(', ', $result['missing']);
        Response::json($result);
    }

    public function moveNextStep(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        if (!Project::find($projectId)) {
            Response::error('Project not found', 404);
        }
        try {
            $result = EpaWorkflow::moveProjectToNextEPAStep($projectId, (int) $request->user['sub'], $request->user['role']);
        } catch (\RuntimeException $e) {
            Response::roleDenied();
        }
        if (!$result['moved']) {
            Response::error('Current EPA step is not complete yet.', 422, $result['missing']);
        }
        Response::json(array_merge($result, ['status' => EpaWorkflow::getProjectEPAStatus($projectId)]));
    }

    public function syncProject(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        if (!Project::find($projectId)) {
            Response::error('Project not found', 404);
        }
        Response::json(EpaWorkflow::classifyExistingProjectEPAStatus($projectId));
    }

    /** Admin-only: classify every project into its correct EPA phase/step. */
    public function syncAllProjects(Request $request): void
    {
        $results = [];
        foreach (Project::all() as $project) {
            $results[] = EpaWorkflow::classifyExistingProjectEPAStatus((int) $project['id']) + ['project_name' => $project['name']];
        }
        Response::json($results);
    }

    public function phaseDashboard(Request $request): void
    {
        $role = $request->user['role'] ?? null;
        $projects = $role === 'Admin' ? Project::all() : Project::allForUser((int) $request->user['sub']);
        $summary = [
            'total_projects' => count($projects),
            'by_phase' => [],
            'incomplete_projects' => [],
            'blocked_projects' => [],
            'ready_for_next_step' => [],
        ];
        foreach ($projects as $project) {
            $status = EpaWorkflow::getProjectEPAStatus((int) $project['id']);
            $phase = $status['current_phase'] ?? 'Unknown';
            $summary['by_phase'][$phase] = ($summary['by_phase'][$phase] ?? 0) + 1;
            $row = [
                'project_id' => $project['id'],
                'project_name' => $project['name'],
                'current_phase' => $status['current_phase'],
                'current_step' => $status['current_step_name'],
                'completion_percentage' => $status['completion_percentage'],
                'missing_artifacts' => $status['missing_artifacts'],
            ];
            if ($status['completion_percentage'] < 100 && !empty($status['missing_artifacts'])) {
                $summary['incomplete_projects'][] = $row;
                $summary['blocked_projects'][] = $row;
            }
            if ($status['can_move_next']) {
                $summary['ready_for_next_step'][] = $row;
            }
        }
        Response::json($summary);
    }
}
