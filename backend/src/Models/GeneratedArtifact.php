<?php

namespace App\Models;

use App\Core\Database;

class GeneratedArtifact
{
    public static function allForRequest(int $requestId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM generated_artifacts WHERE generation_request_id = ? ORDER BY id ASC');
        $stmt->execute([$requestId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO generated_artifacts (generation_request_id, project_id, artifact_type, artifact_title, artifact_content, artifact_path, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['generation_request_id'],
            $data['project_id'] ?? null,
            $data['artifact_type'],
            $data['artifact_title'],
            $data['artifact_content'] ?? null,
            $data['artifact_path'] ?? null,
            $data['created_by'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function createMany(array $rows): void
    {
        foreach ($rows as $row) {
            self::create($row);
        }
    }
}
