<?php

namespace App\Models;

use App\Core\Database;

class SprintItem
{
    public static function allForSprint(int $sprintId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT si.*, b.code, b.title, b.priority
             FROM sprint_items si JOIN backlogs b ON b.id = si.backlog_id
             WHERE si.sprint_id = ? ORDER BY si.id ASC'
        );
        $stmt->execute([$sprintId]);
        return $stmt->fetchAll();
    }

    public static function allForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT si.*, b.code, b.title, b.priority, s.project_id, s.name AS sprint_name
             FROM sprint_items si
             JOIN backlogs b ON b.id = si.backlog_id
             JOIN sprints s ON s.id = si.sprint_id
             WHERE si.assigned_to = ? ORDER BY si.id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM sprint_items WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO sprint_items (sprint_id, backlog_id, assigned_to, status) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['sprint_id'],
            $data['backlog_id'],
            $data['assigned_to'] ?? null,
            $data['status'] ?? 'Planned',
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE sprint_items SET assigned_to = ?, status = ? WHERE id = ?'
        );
        $stmt->execute([$data['assigned_to'] ?? null, $data['status'] ?? 'Planned', $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM sprint_items WHERE id = ?');
        $stmt->execute([$id]);
    }
}
