<?php

namespace App\Models;

use App\Core\Database;

class Prototype
{
    public static function allForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM prototypes WHERE project_id = ? ORDER BY id DESC');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM prototypes WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Comma-joins an int-id list (accepts array or already-joined string) for storage
     *  in the TEXT implemented_*_ids columns - avoids a separate join table for a
     *  simple "which feedback/backlog/defects did this version address" list. */
    private static function idList($value): ?string
    {
        if ($value === null || $value === '') return null;
        return is_array($value) ? implode(',', $value) : (string) $value;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO prototypes (project_id, sprint_id, backlog_id, previous_prototype_id, version_label, version_number, prototype_name, development_type,
             demo_url, repository_url, build_output_url, mobile_build_url, notes, implemented_feedback_summary, implemented_feedback_ids, implemented_backlog_ids, fixed_defect_ids,
             generated_by_ai, generation_request_id, output_path, source_type, status, demo_date, created_by, output_type, image_mode, ui_style, mobile_preview_url)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['project_id'],
            $data['sprint_id'] ?? null,
            $data['backlog_id'] ?? null,
            $data['previous_prototype_id'] ?? null,
            $data['version_number'] ?? $data['version_label'] ?? 'v0.1',
            $data['version_number'] ?? null,
            $data['prototype_name'] ?? null,
            $data['development_type'] ?? 'New Feature',
            $data['demo_url'] ?? null,
            $data['repository_url'] ?? null,
            $data['build_output_url'] ?? null,
            $data['mobile_build_url'] ?? null,
            $data['notes'] ?? null,
            $data['implemented_feedback_summary'] ?? null,
            self::idList($data['implemented_feedback_ids'] ?? null),
            self::idList($data['implemented_backlog_ids'] ?? null),
            self::idList($data['fixed_defect_ids'] ?? null),
            !empty($data['generated_by_ai']) ? 1 : 0,
            $data['generation_request_id'] ?? null,
            $data['output_path'] ?? null,
            $data['source_type'] ?? 'manually_registered',
            $data['status'] ?? 'Planned',
            $data['demo_date'] ?? date('Y-m-d'),
            $data['created_by'] ?? null,
            $data['output_type'] ?? null,
            $data['image_mode'] ?? 'hybrid',
            $data['ui_style'] ?? null,
            $data['mobile_preview_url'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE prototypes SET version_label = ?, version_number = ?, prototype_name = ?, development_type = ?, backlog_id = ?,
             demo_url = ?, repository_url = ?, build_output_url = ?, mobile_build_url = ?, notes = ?, implemented_feedback_summary = ?,
             implemented_feedback_ids = ?, implemented_backlog_ids = ?, fixed_defect_ids = ?, status = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['version_number'] ?? $data['version_label'] ?? 'v0.1',
            $data['version_number'] ?? null,
            $data['prototype_name'] ?? null,
            $data['development_type'] ?? 'New Feature',
            $data['backlog_id'] ?? null,
            $data['demo_url'] ?? null,
            $data['repository_url'] ?? null,
            $data['build_output_url'] ?? null,
            $data['mobile_build_url'] ?? null,
            $data['notes'] ?? null,
            $data['implemented_feedback_summary'] ?? null,
            self::idList($data['implemented_feedback_ids'] ?? null),
            self::idList($data['implemented_backlog_ids'] ?? null),
            self::idList($data['fixed_defect_ids'] ?? null),
            $data['status'] ?? 'Planned',
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM prototypes WHERE id = ?');
        $stmt->execute([$id]);
    }
}
