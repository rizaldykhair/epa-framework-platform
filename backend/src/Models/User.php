<?php

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

class User
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ?'
        );
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            unset($row['password_hash']);
        }
        return $row ?: null;
    }

    public static function all(): array
    {
        $rows = Database::connection()->query(
            'SELECT u.id, u.name, u.email, u.status, u.created_at, r.name AS role_name, r.id AS role_id
             FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.id ASC'
        )->fetchAll();
        return $rows;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (name, email, password_hash, role_id, status) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['email'],
            Auth::hashPassword($data['password']),
            $data['role_id'],
            $data['status'] ?? 'Active',
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        if (!empty($data['password'])) {
            $stmt = Database::connection()->prepare(
                'UPDATE users SET name = ?, email = ?, role_id = ?, status = ?, password_hash = ? WHERE id = ?'
            );
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['role_id'],
                $data['status'] ?? 'Active',
                Auth::hashPassword($data['password']),
                $id,
            ]);
            return;
        }
        $stmt = Database::connection()->prepare(
            'UPDATE users SET name = ?, email = ?, role_id = ?, status = ? WHERE id = ?'
        );
        $stmt->execute([$data['name'], $data['email'], $data['role_id'], $data['status'] ?? 'Active', $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }
}
