<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class AuditLogController
{
    public function index(Request $request): void
    {
        $rows = Database::connection()->query(
            "SELECT a.id, a.action, a.entity, a.entity_id, a.meta_json, a.created_at, u.name AS user_name, u.email AS user_email
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC LIMIT 200"
        )->fetchAll();
        Response::json($rows);
    }
}
