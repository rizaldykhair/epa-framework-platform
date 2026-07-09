<?php

namespace App\Models;

use App\Core\Database;

/**
 * work_items is a thin sync/mapping layer over the real artifact tables
 * (backlogs, sprint_items, prototypes, feedback_implementation, tests, defects,
 * release_checklists) - it is never the source of truth. (artifact_type, artifact_id)
 * uniquely identifies the source row, so sync() always upserts instead of duplicating.
 */
class WorkItem
{
    public static function upsert(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO work_items (project_id, epa_phase, epa_step, artifact_type, artifact_id, title, description, status, priority,
             assignee_id, source_type, source_id, sprint_id, prototype_id, due_date, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               project_id = VALUES(project_id), epa_phase = VALUES(epa_phase), epa_step = VALUES(epa_step),
               title = VALUES(title), description = VALUES(description), status = VALUES(status), priority = VALUES(priority),
               assignee_id = VALUES(assignee_id), source_type = VALUES(source_type), source_id = VALUES(source_id),
               sprint_id = VALUES(sprint_id), prototype_id = VALUES(prototype_id), due_date = VALUES(due_date)'
        );
        $stmt->execute([
            $data['project_id'],
            $data['epa_phase'] ?? null,
            $data['epa_step'] ?? null,
            $data['artifact_type'],
            $data['artifact_id'],
            $data['title'],
            $data['description'] ?? null,
            $data['status'] ?? 'To Do',
            $data['priority'] ?? null,
            $data['assignee_id'] ?? null,
            $data['source_type'] ?? null,
            $data['source_id'] ?? null,
            $data['sprint_id'] ?? null,
            $data['prototype_id'] ?? null,
            $data['due_date'] ?? null,
            $data['created_by'] ?? null,
        ]);
        $id = (int) Database::connection()->lastInsertId();
        if ($id === 0) {
            $stmt = Database::connection()->prepare('SELECT id FROM work_items WHERE artifact_type = ? AND artifact_id = ?');
            $stmt->execute([$data['artifact_type'], $data['artifact_id']]);
            $id = (int) $stmt->fetch()['id'];
        }
        return $id;
    }

    /** Manually-created work item (e.g. "Add Work Item" on the board) - no backing
     *  artifact row, so artifact_id stays NULL (distinct from every synced row). */
    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO work_items (project_id, epa_phase, epa_step, artifact_type, artifact_id, title, description, status, priority,
             assignee_id, source_type, source_id, sprint_id, prototype_id, due_date, created_by)
             VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['project_id'],
            $data['epa_phase'] ?? null,
            $data['epa_step'] ?? null,
            $data['artifact_type'],
            $data['title'],
            $data['description'] ?? null,
            $data['status'] ?? 'To Do',
            $data['priority'] ?? null,
            $data['assignee_id'] ?? null,
            $data['source_type'] ?? 'manual',
            $data['source_id'] ?? null,
            $data['sprint_id'] ?? null,
            $data['prototype_id'] ?? null,
            $data['due_date'] ?? null,
            $data['created_by'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.*, u.name AS assignee_name FROM work_items w LEFT JOIN users u ON u.id = w.assignee_id WHERE w.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @param array $filters status, assignee_id, priority, epa_phase, epa_step, artifact_type, sprint_id, prototype_id */
    public static function allForProject(int $projectId, array $filters = []): array
    {
        $sql = 'SELECT w.*, u.name AS assignee_name FROM work_items w LEFT JOIN users u ON u.id = w.assignee_id WHERE w.project_id = ?';
        $params = [$projectId];
        foreach (['status', 'assignee_id', 'priority', 'epa_phase', 'epa_step', 'artifact_type', 'sprint_id', 'prototype_id'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND w.$field = ?";
                $params[] = $filters[$field];
            }
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (w.title LIKE ? OR w.description LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY w.updated_at DESC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE work_items SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }
}
