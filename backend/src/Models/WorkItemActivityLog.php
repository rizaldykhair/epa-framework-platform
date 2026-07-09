<?php

namespace App\Models;

use App\Core\Database;

class WorkItemActivityLog
{
    public static function record(int $workItemId, ?int $userId, string $actionType, ?string $oldValue, ?string $newValue, ?string $description = null): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO work_item_activity_logs (work_item_id, user_id, action_type, old_value, new_value, description)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$workItemId, $userId, $actionType, $oldValue, $newValue, $description]);
    }

    public static function allForWorkItem(int $workItemId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT l.*, u.name AS user_name FROM work_item_activity_logs l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.work_item_id = ? ORDER BY l.created_at DESC'
        );
        $stmt->execute([$workItemId]);
        return $stmt->fetchAll();
    }
}
