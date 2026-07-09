<?php

namespace App\Core;

use App\Models\Defect;
use App\Models\Project;

/**
 * Centralized EPA Framework workflow engine: 3 phases, 11 sequential steps
 * (Initial Phase -> Dev & Testing Phase -> Release Phase). A project cannot move to
 * the next step until the current step's required artifacts exist in the real tables
 * (backlogs, sprints, prototypes, evaluations, feedback, tests, defects,
 * release_checklists, retrospectives) - it never trusts a free-text phase label alone.
 */
class EpaWorkflow
{
    private static ?array $phasesCache = null;
    private static ?array $stepsCache = null;

    /** Full phase -> steps tree, e.g. for rendering the workflow progress UI. */
    public static function getEPAWorkflow(): array
    {
        $steps = self::steps();
        return array_map(function ($phase) use ($steps) {
            $phase['steps'] = array_values(array_filter($steps, fn($s) => (int) $s['phase_id'] === (int) $phase['id']));
            return $phase;
        }, self::phases());
    }

    private static function phases(): array
    {
        if (self::$phasesCache === null) {
            self::$phasesCache = Database::connection()->query('SELECT * FROM epa_workflow_phases ORDER BY phase_order')->fetchAll();
        }
        return self::$phasesCache;
    }

    private static function steps(): array
    {
        if (self::$stepsCache === null) {
            self::$stepsCache = Database::connection()->query(
                'SELECT s.*, p.phase_code, p.phase_name FROM epa_workflow_steps s
                 JOIN epa_workflow_phases p ON p.id = s.phase_id ORDER BY s.step_order'
            )->fetchAll();
        }
        return self::$stepsCache;
    }

    public static function stepByCode(string $stepCode): ?array
    {
        foreach (self::steps() as $s) {
            if ($s['step_code'] === $stepCode) return $s;
        }
        return null;
    }

    public static function nextStep(string $stepCode): ?array
    {
        $step = self::stepByCode($stepCode);
        if (!$step || !$step['next_step_code']) return null;
        return self::stepByCode($step['next_step_code']);
    }

    /** Checks a single EPA step's required artifacts against the real project data.
     *  @return array{valid: bool, missing: string[]} */
    public static function validateEPAArtifacts(int $projectId, string $stepCode): array
    {
        $db = Database::connection();
        $count = function (string $sql, array $params = null) use ($db, $projectId): int {
            $stmt = $db->prepare($sql);
            $stmt->execute($params ?? [$projectId]);
            return (int) $stmt->fetch()['c'];
        };
        $project = Project::find($projectId);
        $missing = [];

        switch ($stepCode) {
            case 'REQ_BACKLOG':
                if (empty($project['description'])) {
                    $missing[] = 'business problem / project description';
                }
                if ($count('SELECT COUNT(*) c FROM backlogs WHERE project_id = ?') < 1) {
                    $missing[] = 'requirement / project adaptive backlog items';
                }
                break;

            case 'PROTOTYPE_DESIGN_SPRINT':
                if ($count('SELECT COUNT(*) c FROM sprints WHERE project_id = ?') < 1) {
                    $missing[] = 'sprint 1';
                }
                if ($count('SELECT COUNT(*) c FROM sprint_items si JOIN sprints s ON s.id = si.sprint_id WHERE s.project_id = ?') < 1) {
                    $missing[] = 'sprint backlog items';
                }
                foreach (['Product Owner', 'Developer', 'Tester', 'Evaluator'] as $role) {
                    if ($count("SELECT COUNT(*) c FROM project_members WHERE project_id = ? AND role_in_project = ? AND status = 'Active'", [$projectId, $role]) < 1) {
                        $missing[] = "assigned {$role}";
                    }
                }
                break;

            case 'DEMO_PROTOTYPE':
                if ($count("SELECT COUNT(*) c FROM prototypes WHERE project_id = ? AND demo_url IS NOT NULL AND demo_url <> ''
                             AND (build_output_url IS NOT NULL OR output_path IS NOT NULL)") < 1) {
                    $missing[] = 'prototype increment with demo_url and build output';
                }
                break;

            case 'USER_EVALUATION_REVIEW':
                if ($count('SELECT COUNT(*) c FROM evaluations WHERE project_id = ?') < 1) {
                    $missing[] = 'evaluation result';
                }
                if ($count('SELECT COUNT(*) c FROM feedback WHERE project_id = ?') < 1) {
                    $missing[] = 'user feedback';
                }
                break;

            case 'UPDATE_BACKLOG':
                $decided = $count("SELECT COUNT(*) c FROM feedback WHERE project_id = ? AND decision IN ('Converted','Rejected')")
                    + $count("SELECT COUNT(*) c FROM feedback WHERE project_id = ? AND status = 'Closed'");
                if ($decided < 1) {
                    $missing[] = 'feedback decision (Converted/Rejected/Closed)';
                }
                break;

            case 'INCREMENT_DELIVERY':
                $implemented = $count('SELECT COUNT(*) c FROM feedback WHERE project_id = ? AND implemented_at IS NOT NULL');
                $versions = $count('SELECT COUNT(*) c FROM prototypes WHERE project_id = ?');
                if ($implemented < 1 && $versions < 2) {
                    $missing[] = 'implemented feedback or an updated (2nd+) prototype increment';
                }
                break;

            case 'ITERATIVE_REFINEMENT':
                if ($count('SELECT COUNT(*) c FROM prototypes WHERE project_id = ?') < 2) {
                    $missing[] = 'prototype version history (2+ versions)';
                }
                if ($count("SELECT COUNT(*) c FROM prototypes WHERE project_id = ? AND implemented_feedback_summary IS NOT NULL AND implemented_feedback_summary <> ''") < 1) {
                    $missing[] = 'before/after change description';
                }
                break;

            case 'USER_TESTING':
                array_push($missing, ...self::testingGateMissing($projectId));
                break;

            case 'PRODUCT_RELEASE':
                // Re-checks the testing gate independently (not just "did step 8 pass at
                // the time"), so release can never be reached with untested/unresolved work
                // even if current_epa_step was somehow desynced from the real artifacts.
                array_push($missing, ...self::testingGateMissing($projectId));
                if ($count('SELECT COUNT(*) c FROM release_checklists WHERE project_id = ?') < 1) {
                    $missing[] = 'release checklist';
                } elseif ($count("SELECT COUNT(*) c FROM release_checklists WHERE project_id = ? AND status <> 'Yes'") > 0) {
                    $missing[] = 'incomplete release checklist items (Product Owner sign-off / Admin approval items included)';
                }
                if ($count("SELECT COUNT(*) c FROM backlogs WHERE project_id = ? AND priority = 'Must' AND status <> 'Done'") > 0) {
                    $missing[] = 'Must backlog items not yet Done';
                }
                if ($count("SELECT COUNT(*) c FROM defects WHERE project_id = ? AND severity = 'Critical' AND status NOT IN ('Verified','Closed')") > 0) {
                    $missing[] = 'open or in-progress critical defects';
                }
                if (empty($project['approved_by'])) {
                    $missing[] = 'Admin approval';
                }
                break;

            case 'FINAL_DEPLOYMENT':
                if (($project['status'] ?? null) !== 'Released') {
                    $missing[] = 'project marked as Released (final deployment)';
                }
                break;

            case 'PRODUCT_RETROSPECTIVE':
                if ($count("SELECT COUNT(*) c FROM retrospectives WHERE project_id = ? AND action_item IS NOT NULL AND action_item <> ''") < 1) {
                    $missing[] = 'retrospective with action items';
                }
                break;
        }

        return ['valid' => empty($missing), 'missing' => $missing];
    }

    /** Shared by USER_TESTING and PRODUCT_RELEASE: only tests flagged
     *  is_required_for_release count toward the gate; every Fail needs a defect. */
    private static function testingGateMissing(int $projectId): array
    {
        $db = Database::connection();
        $missing = [];

        $stmt = $db->prepare('SELECT COUNT(*) c FROM tests WHERE project_id = ? AND is_required_for_release = 1');
        $stmt->execute([$projectId]);
        $required = (int) $stmt->fetch()['c'];
        if ($required < 1) {
            $missing[] = 'test cases';
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) c FROM tests WHERE project_id = ? AND is_required_for_release = 1 AND result = 'Not Run'");
            $stmt->execute([$projectId]);
            if ((int) $stmt->fetch()['c'] > 0) {
                $missing[] = 'all required test cases must have a result';
            }
        }

        $stmt = $db->prepare("SELECT id FROM tests WHERE project_id = ? AND is_required_for_release = 1 AND result = 'Fail'");
        $stmt->execute([$projectId]);
        foreach ($stmt->fetchAll() as $t) {
            if (!Defect::existsForTest((int) $t['id'])) {
                $missing[] = 'defect report for failed test #' . $t['id'];
            }
        }

        return $missing;
    }

    public static function getProjectEPAStatus(int $projectId): array
    {
        $project = Project::find($projectId);
        if (!$project) {
            throw new \RuntimeException('Project not found');
        }
        $steps = self::steps();
        $currentStepCode = $project['current_epa_step'] ?: 'REQ_BACKLOG';
        $completed = [];
        $currentResult = ['valid' => true, 'missing' => []];

        foreach ($steps as $step) {
            $result = self::validateEPAArtifacts($projectId, $step['step_code']);
            if ($result['valid']) {
                $completed[] = $step['step_code'];
            }
            if ($step['step_code'] === $currentStepCode) {
                $currentResult = $result;
            }
        }

        $currentStep = self::stepByCode($currentStepCode);
        $next = self::nextStep($currentStepCode);
        $percentage = (int) round(count($completed) / count($steps) * 100);

        return [
            'project_id' => $projectId,
            'current_phase' => $currentStep['phase_name'] ?? null,
            'current_phase_code' => $currentStep['phase_code'] ?? null,
            'current_step' => $currentStepCode,
            'current_step_name' => $currentStep['step_name'] ?? null,
            'responsible_role' => $currentStep['responsible_roles'] ?? null,
            'completed_steps' => $completed,
            'missing_artifacts' => $currentResult['missing'],
            'completion_percentage' => $percentage,
            'can_move_next' => $currentResult['valid'] && $next !== null,
            'is_epa_aligned' => empty($currentResult['missing']),
            'next_required_action' => $currentResult['valid']
                ? ($next ? 'Ready to move to next step: ' . $next['step_name'] : 'All EPA steps complete.')
                : 'Complete before continuing: ' . implode(', ', $currentResult['missing']),
        ];
    }

    public static function canMoveToNextEPAStep(int $projectId): bool
    {
        return self::getProjectEPAStatus($projectId)['can_move_next'];
    }

    /** Thin, explicitly-named accessors requested alongside the above (kept as aliases
     *  over the same validation/status logic rather than duplicating it). */
    public static function getNextRequiredAction(int $projectId): string
    {
        return self::getProjectEPAStatus($projectId)['next_required_action'];
    }

    /** @return string[] */
    public static function detectMissingArtifacts(int $projectId, string $stepCode): array
    {
        return self::validateEPAArtifacts($projectId, $stepCode)['missing'];
    }

    public static function isProjectEPAAligned(int $projectId): bool
    {
        return self::getProjectEPAStatus($projectId)['is_epa_aligned'];
    }

    /** @return array{moved: bool, missing?: string[], next_step?: string} */
    public static function moveProjectToNextEPAStep(int $projectId, int $userId, string $userRole): array
    {
        $project = Project::find($projectId);
        if (!$project) {
            throw new \RuntimeException('Project not found');
        }
        $currentStepCode = $project['current_epa_step'] ?: 'REQ_BACKLOG';
        $step = self::stepByCode($currentStepCode);
        $allowedRoles = array_map('trim', explode(',', $step['responsible_roles'] ?? ''));
        if ($userRole !== 'Admin' && !in_array($userRole, $allowedRoles, true)) {
            throw new \RuntimeException('Role not authorized to move this EPA step.');
        }

        $result = self::validateEPAArtifacts($projectId, $currentStepCode);
        self::logStep($projectId, $currentStepCode, $step['phase_code'], $result['valid'] ? 'Completed' : 'Blocked', $userId, $result);
        if (!$result['valid']) {
            return ['moved' => false, 'missing' => $result['missing']];
        }

        $next = self::nextStep($currentStepCode);
        if ($next) {
            Project::updateEpaStep($projectId, $next['phase_name'], $next['step_code']);
        }
        self::syncProjectCompletionStatus($projectId);
        return ['moved' => true, 'next_step' => $next['step_code'] ?? null];
    }

    private static function logStep(int $projectId, string $stepCode, string $phaseCode, string $status, int $userId, array $result): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO project_epa_step_logs (project_id, step_code, phase_code, status, completed_by, completed_at, validation_result, missing_artifacts)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $projectId, $stepCode, $phaseCode, $status, $userId,
            $status === 'Completed' ? date('Y-m-d H:i:s') : null,
            $result['valid'] ? 1 : 0,
            implode(', ', $result['missing']),
        ]);
    }

    /** Recomputes and persists the live EPA status fields on the projects row. */
    public static function syncProjectCompletionStatus(int $projectId): array
    {
        $status = self::getProjectEPAStatus($projectId);
        Project::updateEpaStatus($projectId, [
            'epa_completion_percentage' => $status['completion_percentage'],
            'epa_status' => $status['completion_percentage'] >= 100 ? 'Completed' : 'In Progress',
            'epa_next_action' => $status['next_required_action'],
            'epa_missing_artifacts' => implode(', ', $status['missing_artifacts']),
            'is_epa_aligned' => $status['is_epa_aligned'],
        ]);
        return $status;
    }

    /** For a project created before this engine existed (or via AI generation): walks the
     *  11 steps from the start and classifies the project at the first step that still
     *  fails validation - i.e. exactly where it needs to focus next. */
    public static function classifyExistingProjectEPAStatus(int $projectId): array
    {
        $steps = self::steps();
        $currentStepCode = $steps[0]['step_code'];
        foreach ($steps as $step) {
            $result = self::validateEPAArtifacts($projectId, $step['step_code']);
            if ($result['valid']) {
                $next = self::nextStep($step['step_code']);
                $currentStepCode = $next['step_code'] ?? $step['step_code'];
            } else {
                $currentStepCode = $step['step_code'];
                break;
            }
        }
        $step = self::stepByCode($currentStepCode);
        Project::updateEpaStep($projectId, $step['phase_name'], $currentStepCode);
        return self::syncProjectCompletionStatus($projectId);
    }
}
