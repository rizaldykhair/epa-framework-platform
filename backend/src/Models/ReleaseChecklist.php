<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ReleaseChecklist
{
    public static function allForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM release_checklists WHERE project_id = ? ORDER BY id ASC');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM release_checklists WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO release_checklists (project_id, item, status, verified_by) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$data['project_id'], $data['item'], $data['status'] ?? 'No', $data['verified_by'] ?? null]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE release_checklists SET item = ?, status = ?, verified_by = ? WHERE id = ?'
        );
        $stmt->execute([$data['item'], $data['status'] ?? 'No', $data['verified_by'] ?? null, $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM release_checklists WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function readinessScore(int $projectId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT SUM(status = 'Yes') AS yes_count, COUNT(*) AS total FROM release_checklists WHERE project_id = ?"
        );
        $stmt->execute([$projectId]);
        $row = $stmt->fetch();
        if (!$row || (int) $row['total'] === 0) {
            return 0;
        }
        return (int) round(((int) $row['yes_count'] / (int) $row['total']) * 100);
    }
}
