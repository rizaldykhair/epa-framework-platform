<?php

namespace App\Models;

use App\Core\Database;

class EpaPhase
{
    public static function allForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM epa_phases WHERE project_id = ? ORDER BY sequence ASC');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM epa_phases WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO epa_phases (project_id, name, stage_label, sequence, status) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['project_id'],
            $data['name'],
            $data['stage_label'],
            $data['sequence'],
            $data['status'] ?? 'Pending',
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE epa_phases SET name = ?, stage_label = ?, sequence = ?, status = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['stage_label'],
            $data['sequence'],
            $data['status'] ?? 'Pending',
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM epa_phases WHERE id = ?');
        $stmt->execute([$id]);
    }
}
