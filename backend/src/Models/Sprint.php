<?php

namespace App\Models;

use App\Core\Database;

class Sprint
{
    public static function allForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM sprints WHERE project_id = ? ORDER BY id ASC');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM sprints WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO sprints (project_id, name, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['project_id'],
            $data['name'],
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['status'] ?? 'Planned',
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE sprints SET name = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['status'] ?? 'Planned',
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM sprints WHERE id = ?');
        $stmt->execute([$id]);
    }
}
