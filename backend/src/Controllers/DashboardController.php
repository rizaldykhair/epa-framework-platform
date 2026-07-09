<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\ReleaseChecklist;
use PDO;

class DashboardController
{
    public function metrics(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        $pdo = Database::connection();

        $backlogTotal = $this->scalar($pdo, 'SELECT COUNT(*) FROM backlogs WHERE project_id = ?', [$projectId]);

        $feedbackTotal = $this->scalar($pdo, 'SELECT COUNT(*) FROM feedback WHERE project_id = ?', [$projectId]);
        $feedbackConverted = $this->scalar(
            $pdo,
            "SELECT COUNT(*) FROM feedback WHERE project_id = ? AND decision = 'Converted'",
            [$projectId]
        );

        $testsTotal = $this->scalar($pdo, 'SELECT COUNT(*) FROM tests WHERE project_id = ?', [$projectId]);
        $testsPass = $this->scalar(
            $pdo,
            "SELECT COUNT(*) FROM tests WHERE project_id = ? AND result = 'Pass'",
            [$projectId]
        );

        $openDefects = $this->scalar(
            $pdo,
            "SELECT COUNT(*) FROM defects WHERE project_id = ? AND status = 'Open'",
            [$projectId]
        );

        Response::json([
            'totalBacklog' => $backlogTotal,
            'feedbackRate' => $feedbackTotal > 0 ? (int) round($feedbackConverted / $feedbackTotal * 100) : 0,
            'taskSuccess' => $testsTotal > 0 ? (int) round($testsPass / $testsTotal * 100) : 0,
            'openDefects' => $openDefects,
            'releaseScore' => ReleaseChecklist::readinessScore($projectId),
        ]);
    }

    private function scalar(PDO $pdo, string $sql, array $params): int
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
