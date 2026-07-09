<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

class ReportController
{
    public function summary(Request $request): void
    {
        $pdo = Database::connection();

        $totalProjects = $this->scalar($pdo, 'SELECT COUNT(*) FROM projects');
        $totalUsers = $this->scalar($pdo, 'SELECT COUNT(*) FROM users');
        $activeSprints = $this->scalar($pdo, "SELECT COUNT(*) FROM sprints WHERE status = 'Active'");
        $totalBacklog = $this->scalar($pdo, 'SELECT COUNT(*) FROM backlogs');
        $totalFeedback = $this->scalar($pdo, 'SELECT COUNT(*) FROM feedback');
        $convertedFeedback = $this->scalar($pdo, "SELECT COUNT(*) FROM feedback WHERE decision = 'Converted'");
        $totalTests = $this->scalar($pdo, 'SELECT COUNT(*) FROM tests');
        $openDefects = $this->scalar($pdo, "SELECT COUNT(*) FROM defects WHERE status = 'Open'");
        $releaseYes = $this->scalar($pdo, "SELECT COUNT(*) FROM release_checklists WHERE status = 'Yes'");
        $releaseTotal = $this->scalar($pdo, 'SELECT COUNT(*) FROM release_checklists');

        $projectProgress = $pdo->query(
            "SELECT p.id, p.name,
                    COUNT(b.id) AS total_backlog,
                    SUM(b.status = 'Done') AS done_backlog
             FROM projects p
             LEFT JOIN backlogs b ON b.project_id = p.id
             GROUP BY p.id, p.name
             ORDER BY p.id ASC"
        )->fetchAll();

        $recentActivity = $pdo->query(
            "SELECT a.id, a.action, a.entity, a.entity_id, a.created_at, u.name AS user_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC LIMIT 20"
        )->fetchAll();

        Response::json([
            'totalProjects' => $totalProjects,
            'totalUsers' => $totalUsers,
            'activeSprints' => $activeSprints,
            'totalBacklog' => $totalBacklog,
            'totalFeedback' => $totalFeedback,
            'feedbackConversionRate' => $totalFeedback > 0 ? (int) round($convertedFeedback / $totalFeedback * 100) : 0,
            'totalTests' => $totalTests,
            'openDefects' => $openDefects,
            'releaseReadiness' => $releaseTotal > 0 ? (int) round($releaseYes / $releaseTotal * 100) : 0,
            'projectProgress' => $projectProgress,
            'recentActivity' => $recentActivity,
        ]);
    }

    private function scalar(PDO $pdo, string $sql): int
    {
        return (int) $pdo->query($sql)->fetchColumn();
    }
}
