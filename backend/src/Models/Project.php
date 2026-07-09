<?php

namespace App\Models;

use App\Core\Database;

class Project
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM projects ORDER BY created_at DESC')->fetchAll();
    }

    public static function allForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.* FROM projects p
             JOIN project_members pm ON pm.project_id = p.id
             WHERE pm.user_id = ? ORDER BY p.created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM projects WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO projects (name, project_code, description, client_name, project_type, current_epa_phase, owner_id, created_by, status, start_date, target_release_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['project_code'] ?? null,
            $data['description'] ?? null,
            $data['client_name'] ?? null,
            $data['project_type'] ?? 'Web App',
            $data['current_epa_phase'] ?? 'Requirements Elicitation & Project Adaptive Backlog',
            $data['owner_id'],
            $data['created_by'] ?? $data['owner_id'],
            $data['status'] ?? 'Draft',
            $data['start_date'] ?? null,
            $data['target_release_date'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE projects SET name = ?, project_code = ?, description = ?, client_name = ?, project_type = ?, current_epa_phase = ?,
             status = ?, start_date = ?, target_release_date = ?, risk_notes = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['project_code'] ?? null,
            $data['description'] ?? null,
            $data['client_name'] ?? null,
            $data['project_type'] ?? 'Web App',
            $data['current_epa_phase'] ?? null,
            $data['status'] ?? 'Draft',
            $data['start_date'] ?? null,
            $data['target_release_date'] ?? null,
            $data['risk_notes'] ?? null,
            $id,
        ]);
    }

    public static function approve(int $id, int $approvedBy): void
    {
        $stmt = Database::connection()->prepare("UPDATE projects SET status = 'Active', approved_by = ? WHERE id = ?");
        $stmt->execute([$approvedBy, $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM projects WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function updateCompletionStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE projects SET epa_completion_status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    public static function markContinuedByAi(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE projects SET continued_by_ai = 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** $phaseName is the human-readable phase name (e.g. "Dev & Testing Phase"), kept
     *  consistent with how current_epa_phase was already displayed across dashboards;
     *  $stepCode is the machine-readable EPA step identifier used for gating logic. */
    public static function updateEpaStep(int $id, string $phaseName, string $stepCode): void
    {
        $stmt = Database::connection()->prepare('UPDATE projects SET current_epa_phase = ?, current_epa_step = ? WHERE id = ?');
        $stmt->execute([$phaseName, $stepCode, $id]);
    }

    public static function updateEpaStatus(int $id, array $fields): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE projects SET epa_completion_percentage = ?, epa_status = ?, epa_next_action = ?, epa_missing_artifacts = ?, is_epa_aligned = ? WHERE id = ?'
        );
        $stmt->execute([
            $fields['epa_completion_percentage'],
            $fields['epa_status'],
            $fields['epa_next_action'],
            $fields['epa_missing_artifacts'],
            $fields['is_epa_aligned'] ? 1 : 0,
            $id,
        ]);
    }
}
