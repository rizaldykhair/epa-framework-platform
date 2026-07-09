<?php

namespace App\Models;

use App\Core\Database;

class AiGenerationRequest
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM ai_generation_requests ORDER BY created_at DESC')->fetchAll();
    }

    public static function allForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM ai_generation_requests WHERE requested_by = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM ai_generation_requests WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO ai_generation_requests (requested_by, user_role, application_name, client_name, project_type, target_users,
             business_problem, main_objective, main_features, user_roles_needed, data_entities, workflow_description, reporting_needs,
             design_style, primary_color, layout_preference, output_type, image_mode, source_type, source_ids)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['requested_by'],
            $data['user_role'],
            $data['application_name'],
            $data['client_name'] ?? null,
            $data['project_type'] ?? 'Web-Based Application',
            $data['target_users'] ?? null,
            $data['business_problem'] ?? null,
            $data['main_objective'] ?? null,
            $data['main_features'] ?? null,
            $data['user_roles_needed'] ?? null,
            $data['data_entities'] ?? null,
            $data['workflow_description'] ?? null,
            $data['reporting_needs'] ?? null,
            $data['design_style'] ?? 'Modern',
            $data['primary_color'] ?? '#0b4a3a',
            $data['layout_preference'] ?? 'Dashboard',
            $data['output_type'] ?? null,
            $data['image_mode'] ?? 'hybrid',
            $data['source_type'] ?? null,
            $data['source_ids'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function updateFields(int $id, array $fields): void
    {
        if (!$fields) return;
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $stmt = Database::connection()->prepare("UPDATE ai_generation_requests SET $set WHERE id = ?");
        $stmt->execute([...array_values($fields), $id]);
    }
}
