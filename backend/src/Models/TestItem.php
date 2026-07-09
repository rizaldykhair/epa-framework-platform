<?php

namespace App\Models;

use App\Core\Database;

class TestItem
{
    public static function allForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM tests WHERE project_id = ? ORDER BY id ASC');
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM tests WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function nextCode(int $projectId, string $prefix): string
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) AS c FROM tests WHERE project_id = ? AND code LIKE ?');
        $stmt->execute([$projectId, $prefix . '-%']);
        $count = (int) $stmt->fetch()['c'];
        return $prefix . '-' . str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT);
    }

    public static function allByPrototype(int $prototypeId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM tests WHERE prototype_id = ? ORDER BY id ASC');
        $stmt->execute([$prototypeId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO tests (project_id, created_by, backlog_id, prototype_id, code, scenario, type, result, is_required_for_release, severity, notes, evidence_url, executed_by, executed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $result = $data['result'] ?? 'Not Run';
        $stmt->execute([
            $data['project_id'],
            $data['created_by'] ?? null,
            $data['backlog_id'] ?? null,
            $data['prototype_id'] ?? null,
            $data['code'],
            $data['scenario'],
            $data['type'] ?? 'Functional',
            $result,
            array_key_exists('is_required_for_release', $data) ? (int) (bool) $data['is_required_for_release'] : 1,
            $data['severity'] ?? null,
            $data['notes'] ?? null,
            $data['evidence_url'] ?? null,
            $data['executed_by'] ?? null,
            $result !== 'Not Run' ? date('Y-m-d H:i:s') : null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE tests SET scenario = ?, type = ?, result = ?, is_required_for_release = ?, severity = ?, notes = ?, evidence_url = ?, executed_by = ?, executed_at = ? WHERE id = ?'
        );
        $result = $data['result'] ?? 'Not Run';
        $stmt->execute([
            $data['scenario'],
            $data['type'] ?? 'Functional',
            $result,
            array_key_exists('is_required_for_release', $data) ? (int) (bool) $data['is_required_for_release'] : 1,
            $data['severity'] ?? null,
            $data['notes'] ?? null,
            $data['evidence_url'] ?? null,
            $data['executed_by'] ?? null,
            $result !== 'Not Run' ? date('Y-m-d H:i:s') : null,
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM tests WHERE id = ?');
        $stmt->execute([$id]);
    }
}
