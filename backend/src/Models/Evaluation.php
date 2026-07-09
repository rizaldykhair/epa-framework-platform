<?php

namespace App\Models;

use App\Core\Database;

class Evaluation
{
    public static function allForPrototype(int $prototypeId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.*, u.name AS evaluator_name FROM evaluations e
             JOIN users u ON u.id = e.evaluator_id
             WHERE e.prototype_id = ? ORDER BY e.submitted_at DESC'
        );
        $stmt->execute([$prototypeId]);
        return $stmt->fetchAll();
    }

    public static function allForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.*, u.name AS evaluator_name FROM evaluations e
             JOIN users u ON u.id = e.evaluator_id
             WHERE e.project_id = ? ORDER BY e.submitted_at DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function allForEvaluator(int $evaluatorId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.*, p.version_number, p.version_label, p.project_id
             FROM evaluations e JOIN prototypes p ON p.id = e.prototype_id
             WHERE e.evaluator_id = ? ORDER BY e.submitted_at DESC'
        );
        $stmt->execute([$evaluatorId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO evaluations (prototype_id, project_id, evaluator_id, ease_of_use_rating, feature_completeness_rating,
             interface_design_rating, performance_rating, overall_satisfaction_rating, what_works_well, problems_found,
             improvement_suggestions, feedback_priority, submitted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['prototype_id'],
            $data['project_id'] ?? null,
            $data['evaluator_id'],
            $data['ease_of_use_rating'],
            $data['feature_completeness_rating'],
            $data['interface_design_rating'],
            $data['performance_rating'],
            $data['overall_satisfaction_rating'],
            $data['what_works_well'] ?? null,
            $data['problems_found'] ?? null,
            $data['improvement_suggestions'] ?? null,
            $data['feedback_priority'] ?? 'Should',
        ]);
        return (int) Database::connection()->lastInsertId();
    }
}
