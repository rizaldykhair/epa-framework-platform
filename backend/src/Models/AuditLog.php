<?php

namespace App\Models;

use App\Core\Database;

class AuditLog
{
    public static function record(?int $userId, string $action, string $entity, ?int $entityId, ?array $meta = null): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity, entity_id, meta_json) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $action, $entity, $entityId, $meta ? json_encode($meta) : null]);
    }
}
