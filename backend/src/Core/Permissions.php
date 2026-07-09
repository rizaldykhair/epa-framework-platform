<?php

namespace App\Core;

/**
 * Centralized role/CRUD permission matrix for the EPA Framework role model
 * (Admin, Product Owner, Developer, Tester, Evaluator). RoleMiddleware still
 * does the coarse per-route gate in routes.php; this class adds the
 * fine-grained checks route-level gating can't express: project-membership
 * scoping and per-field write restrictions (e.g. Developer may change a
 * backlog item's status but not its priority).
 */
class Permissions
{
    private const MATRIX = [
        'projects' => [
            'create' => ['Admin', 'Product Owner'],
            'read' => ['Admin', 'Product Owner', 'Developer', 'Tester', 'Evaluator'],
            'update' => ['Admin', 'Product Owner'],
            'delete' => ['Admin'],
            'approve' => ['Admin'],
        ],
        'project_members' => [
            'read' => ['Admin', 'Product Owner', 'Developer', 'Tester', 'Evaluator'],
            'assign' => ['Admin'],
            'delete' => ['Admin'],
        ],
        'backlogs' => [
            'create' => ['Admin', 'Product Owner'],
            'read' => ['Admin', 'Product Owner', 'Developer', 'Tester'],
            'update' => ['Admin', 'Product Owner', 'Developer'],
            'delete' => ['Admin', 'Product Owner'],
        ],
        'prototypes' => [
            'create' => ['Admin', 'Developer'],
            'read' => ['Admin', 'Product Owner', 'Developer', 'Tester', 'Evaluator'],
            'update' => ['Admin', 'Developer'],
            'delete' => ['Admin'],
        ],
        'tests' => [
            'create' => ['Admin', 'Tester'],
            'read' => ['Admin', 'Product Owner', 'Developer', 'Tester'],
            'update' => ['Admin', 'Tester'],
            'delete' => ['Admin', 'Tester'],
        ],
        'defects' => [
            'create' => ['Admin', 'Tester'],
            'read' => ['Admin', 'Product Owner', 'Developer', 'Tester'],
            'update' => ['Admin', 'Developer', 'Tester'],
            'verify' => ['Admin', 'Tester'],
        ],
        'feedback' => [
            'create' => ['Admin', 'Evaluator'],
            'read' => ['Admin', 'Product Owner', 'Developer', 'Evaluator'],
            'update' => ['Admin', 'Product Owner'],
            'convert' => ['Admin', 'Product Owner'],
            'implement' => ['Admin', 'Developer'],
            'delete' => ['Admin'],
        ],
        'ai_generator' => [
            'create' => ['Admin', 'Product Owner', 'Evaluator'],
            'read' => ['Admin', 'Product Owner', 'Developer', 'Tester', 'Evaluator'],
            'generate' => ['Admin', 'Product Owner'],
            'regenerate' => ['Admin', 'Product Owner', 'Developer'],
        ],
    ];

    /** Per-role write whitelists. Absent role/module => no restriction (all fields allowed). */
    private const FIELD_RESTRICTIONS = [
        'projects' => [
            'Product Owner' => ['name', 'description', 'current_epa_phase'],
        ],
        'backlogs' => [
            'Developer' => ['status'],
        ],
        'feedback' => [
            'Product Owner' => ['decision', 'status', 'reason', 'assigned_to'],
        ],
    ];

    public static function canAccess(array $user, string $module, string $action, ?int $projectId = null): bool
    {
        $role = $user['role'] ?? null;
        $allowed = self::MATRIX[$module][$action] ?? null;
        if ($allowed === null || !in_array($role, $allowed, true)) {
            return false;
        }
        if ($projectId !== null && $role !== 'Admin') {
            return Guard::isMember((int) ($user['sub'] ?? 0), $projectId);
        }
        return true;
    }

    /** @return string[]|null Whitelist of writable fields for this role, or null if unrestricted. */
    public static function fieldsAllowed(?string $role, string $module): ?array
    {
        return self::FIELD_RESTRICTIONS[$module][$role] ?? null;
    }

    /** Filters $body down to only the fields a role is allowed to write for a module. */
    public static function restrictFields(?string $role, string $module, array $body): array
    {
        $fields = self::fieldsAllowed($role, $module);
        return $fields ? array_intersect_key($body, array_flip($fields)) : $body;
    }
}
