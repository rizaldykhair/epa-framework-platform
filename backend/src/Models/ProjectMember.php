<?php

namespace App\Models;

use App\Core\Database;

class ProjectMember
{
    public static function allForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT pm.id, pm.project_id, pm.user_id, pm.role_in_project, pm.responsibility_notes, u.name, u.email
             FROM project_members pm JOIN users u ON u.id = pm.user_id
             WHERE pm.project_id = ? ORDER BY pm.id ASC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function projectIdsForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT project_id FROM project_members WHERE user_id = ?');
        $stmt->execute([$userId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'project_id'));
    }

    public static function add(int $projectId, int $userId, string $roleInProject, ?string $responsibilityNotes = null, ?int $assignedBy = null): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO project_members (project_id, user_id, role_in_project, responsibility_notes, assigned_by, status)
             VALUES (?, ?, ?, ?, ?, "Active")
             ON DUPLICATE KEY UPDATE role_in_project = VALUES(role_in_project), responsibility_notes = VALUES(responsibility_notes), status = "Active"'
        );
        $stmt->execute([$projectId, $userId, $roleInProject, $responsibilityNotes, $assignedBy]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function remove(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM project_members WHERE id = ?');
        $stmt->execute([$id]);
    }
}
