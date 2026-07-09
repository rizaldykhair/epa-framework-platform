<?php

namespace App\Models;

use App\Core\Database;

class WorkItemComment
{
    public static function allForWorkItem(int $workItemId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*, u.name AS user_name FROM work_item_comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.work_item_id = ? ORDER BY c.created_at ASC'
        );
        $stmt->execute([$workItemId]);
        return $stmt->fetchAll();
    }

    public static function create(int $workItemId, int $userId, string $text): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO work_item_comments (work_item_id, user_id, comment_text) VALUES (?, ?, ?)'
        );
        $stmt->execute([$workItemId, $userId, $text]);
        return (int) Database::connection()->lastInsertId();
    }
}
