<?php

namespace App\Models;

use App\Core\Database;

class Backlog
{
    public static function allForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM backlogs WHERE project_id = ? ORDER BY created_at DESC');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM backlogs WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function nextCode(int $projectId): string
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) AS c FROM backlogs WHERE project_id = ?');
        $stmt->execute([$projectId]);
        $count = (int) $stmt->fetch()['c'];
        return 'BL-' . str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT);
    }

    public static function allAssignedToUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM backlogs WHERE assigned_to = ? ORDER BY updated_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO backlogs (project_id, code, title, user_story, acceptance_criteria, business_value, priority, status, source_feedback_id,
             source_type, source_id, source_defect_id, source_retrospective_id, created_from_epa_step, target_prototype_version, decision_status, created_by, assigned_to)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['project_id'],
            $data['code'],
            $data['title'],
            $data['user_story'] ?? null,
            $data['acceptance_criteria'] ?? null,
            $data['business_value'] ?? null,
            $data['priority'] ?? 'Should',
            $data['status'] ?? 'To Do',
            $data['source_feedback_id'] ?? null,
            $data['source_type'] ?? 'manual_product_owner',
            $data['source_id'] ?? null,
            $data['source_defect_id'] ?? null,
            $data['source_retrospective_id'] ?? null,
            $data['created_from_epa_step'] ?? null,
            $data['target_prototype_version'] ?? null,
            $data['decision_status'] ?? 'Pending',
            $data['created_by'] ?? null,
            $data['assigned_to'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE backlogs SET title = ?, user_story = ?, acceptance_criteria = ?, business_value = ?, priority = ?, status = ?, assigned_to = ?,
             target_prototype_version = ?, decision_status = ?, target_sprint_id = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['title'],
            $data['user_story'] ?? null,
            $data['acceptance_criteria'] ?? null,
            $data['business_value'] ?? null,
            $data['priority'] ?? 'Should',
            $data['status'] ?? 'To Do',
            $data['assigned_to'] ?? null,
            $data['target_prototype_version'] ?? null,
            $data['decision_status'] ?? 'Pending',
            $data['target_sprint_id'] ?? null,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM backlogs WHERE id = ?');
        $stmt->execute([$id]);
    }
}
