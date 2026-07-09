<?php

namespace App\Models;

use App\Core\Database;

class Defect
{
    public static function allForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM defects WHERE project_id = ? ORDER BY created_at DESC');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM defects WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function existsForTest(int $testId): bool
    {
        $stmt = Database::connection()->prepare('SELECT id FROM defects WHERE test_id = ?');
        $stmt->execute([$testId]);
        return (bool) $stmt->fetch();
    }

    public static function allAssignedToUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM defects WHERE assigned_to = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO defects (test_id, project_id, title, description, steps_to_reproduce, expected_result, actual_result, severity, status, reported_by, assigned_to)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['test_id'],
            $data['project_id'],
            $data['title'] ?? null,
            $data['description'],
            $data['steps_to_reproduce'] ?? null,
            $data['expected_result'] ?? null,
            $data['actual_result'] ?? null,
            $data['severity'] ?? 'Medium',
            $data['status'] ?? 'Open',
            $data['reported_by'] ?? null,
            $data['assigned_to'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE defects SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    public static function updateAssignment(int $id, ?int $assignedTo, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE defects SET assigned_to = ?, status = ? WHERE id = ?');
        $stmt->execute([$assignedTo, $status, $id]);
    }

    public static function markVerified(int $id, int $verifiedBy): void
    {
        $stmt = Database::connection()->prepare("UPDATE defects SET status = 'Verified', verified_at = NOW(), verified_by = ? WHERE id = ?");
        $stmt->execute([$verifiedBy, $id]);
    }
}
