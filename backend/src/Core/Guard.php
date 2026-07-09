<?php

namespace App\Core;

class Guard
{
    public static function isMember(int $userId, int $projectId): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT 1 FROM project_members WHERE user_id = ? AND project_id = ? AND status = 'Active'"
        );
        $stmt->execute([$userId, $projectId]);
        return (bool) $stmt->fetch();
    }

    public static function requireProjectAccess(Request $request, int $projectId): void
    {
        if (($request->user['role'] ?? null) === 'Admin') {
            return;
        }
        if (!self::isMember((int) $request->user['sub'], $projectId)) {
            Response::roleDenied();
        }
    }
}
