<?php

namespace App\Models;

use App\Core\Database;

class Feedback
{
    public static function allForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM feedback WHERE project_id = ? ORDER BY created_at DESC');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM feedback WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function allSubmittedByUser(int $projectId, int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM feedback WHERE project_id = ? AND submitted_by = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$projectId, $userId]);
        return $stmt->fetchAll();
    }

    public static function allAssignedToUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM feedback WHERE assigned_to = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function nextCode(int $projectId): string
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) AS c FROM feedback WHERE project_id = ?');
        $stmt->execute([$projectId]);
        $count = (int) $stmt->fetch()['c'];
        return 'FB-' . str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT);
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO feedback (project_id, code, finding, decision, status, submitted_by, assigned_to) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['project_id'],
            $data['code'],
            $data['finding'],
            $data['decision'] ?? 'Clarify',
            $data['status'] ?? 'Open',
            $data['submitted_by'] ?? null,
            $data['assigned_to'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, ?int $decisionBy = null): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE feedback SET finding = ?, decision = ?, reason = ?, status = ?, assigned_to = ?, decision_by = COALESCE(?, decision_by) WHERE id = ?'
        );
        $stmt->execute([
            $data['finding'],
            $data['decision'] ?? 'Clarify',
            $data['reason'] ?? null,
            $data['status'] ?? 'Open',
            $data['assigned_to'] ?? null,
            $decisionBy,
            $id,
        ]);
    }

    public static function markConverted(int $id, int $backlogId, int $decisionBy): void
    {
        $stmt = Database::connection()->prepare("UPDATE feedback SET decision = 'Converted', converted_backlog_id = ?, decision_by = ? WHERE id = ?");
        $stmt->execute([$backlogId, $decisionBy, $id]);
    }

    public static function markImplemented(int $id, ?int $prototypeId = null, ?string $version = null): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE feedback SET implemented_at = NOW(), status = 'Closed', implemented_in_prototype_id = COALESCE(?, implemented_in_prototype_id),
             implemented_in_version = COALESCE(?, implemented_in_version) WHERE id = ?"
        );
        $stmt->execute([$prototypeId, $version, $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM feedback WHERE id = ?');
        $stmt->execute([$id]);
    }
}
