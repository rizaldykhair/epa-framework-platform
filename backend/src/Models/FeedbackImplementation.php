<?php

namespace App\Models;

use App\Core\Database;

class FeedbackImplementation
{
    public static function allForFeedback(int $feedbackId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM feedback_implementation WHERE feedback_id = ? ORDER BY id DESC');
        $stmt->execute([$feedbackId]);
        return $stmt->fetchAll();
    }

    public static function allForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT fi.*, f.finding, f.project_id FROM feedback_implementation fi
             JOIN feedback f ON f.id = fi.feedback_id
             WHERE fi.assigned_developer = ? ORDER BY fi.updated_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM feedback_implementation WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO feedback_implementation (feedback_id, backlog_id, prototype_id, implementation_action, before_change_description,
             after_change_description, developer_notes, status, assigned_developer)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['feedback_id'],
            $data['backlog_id'] ?? null,
            $data['prototype_id'] ?? null,
            $data['implementation_action'] ?? null,
            $data['before_change_description'] ?? null,
            $data['after_change_description'] ?? null,
            $data['developer_notes'] ?? null,
            $data['status'] ?? 'Not Started',
            $data['assigned_developer'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE feedback_implementation SET implementation_action = ?, before_change_description = ?, after_change_description = ?,
             developer_notes = ?, status = ?, assigned_developer = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['implementation_action'] ?? null,
            $data['before_change_description'] ?? null,
            $data['after_change_description'] ?? null,
            $data['developer_notes'] ?? null,
            $data['status'] ?? 'Not Started',
            $data['assigned_developer'] ?? null,
            $id,
        ]);
    }
}
