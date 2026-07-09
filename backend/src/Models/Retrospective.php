<?php

namespace App\Models;

use App\Core\Database;

class Retrospective
{
    public static function allForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM retrospectives WHERE project_id = ? ORDER BY created_at DESC');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM retrospectives WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO retrospectives (project_id, sprint_id, went_well, needs_improvement, action_item, create_improvement_backlog, next_iteration_recommendation, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['project_id'],
            $data['sprint_id'] ?? null,
            $data['went_well'] ?? null,
            $data['needs_improvement'] ?? null,
            $data['action_item'] ?? null,
            !empty($data['create_improvement_backlog']) ? 1 : 0,
            $data['next_iteration_recommendation'] ?? null,
            $data['created_by'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function setGeneratedBacklog(int $id, int $backlogId): void
    {
        $stmt = Database::connection()->prepare('UPDATE retrospectives SET generated_backlog_id = ? WHERE id = ?');
        $stmt->execute([$backlogId, $id]);
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE retrospectives SET went_well = ?, needs_improvement = ?, action_item = ?, next_iteration_recommendation = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['went_well'] ?? null,
            $data['needs_improvement'] ?? null,
            $data['action_item'] ?? null,
            $data['next_iteration_recommendation'] ?? null,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM retrospectives WHERE id = ?');
        $stmt->execute([$id]);
    }
}
