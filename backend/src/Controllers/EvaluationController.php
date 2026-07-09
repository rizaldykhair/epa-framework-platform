<?php

namespace App\Controllers;

use App\Core\Guard;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Evaluation;
use App\Models\Prototype;

class EvaluationController
{
    public function index(Request $request, array $params): void
    {
        $prototypeId = (int) $params['id'];
        $prototype = Prototype::find($prototypeId);
        if (!$prototype) {
            Response::error('Prototype not found', 404);
        }
        Guard::requireProjectAccess($request, (int) $prototype['project_id']);
        Response::json(Evaluation::allForPrototype($prototypeId));
    }

    public function byProject(Request $request, array $params): void
    {
        Response::json(Evaluation::allForProject((int) $params['id']));
    }

    public function store(Request $request, array $params): void
    {
        $prototypeId = (int) $params['id'];
        $prototype = Prototype::find($prototypeId);
        if (!$prototype) {
            Response::error('Prototype not found', 404);
        }
        Guard::requireProjectAccess($request, (int) $prototype['project_id']);

        $body = $request->body;
        foreach (['ease_of_use_rating', 'feature_completeness_rating', 'interface_design_rating', 'performance_rating', 'overall_satisfaction_rating'] as $field) {
            if (empty($body[$field])) {
                Response::error("$field is required", 422);
            }
        }
        $id = Evaluation::create([
            'prototype_id' => $prototypeId,
            'project_id' => $prototype['project_id'],
            'evaluator_id' => $request->user['sub'],
            'ease_of_use_rating' => (int) $body['ease_of_use_rating'],
            'feature_completeness_rating' => (int) $body['feature_completeness_rating'],
            'interface_design_rating' => (int) $body['interface_design_rating'],
            'performance_rating' => (int) $body['performance_rating'],
            'overall_satisfaction_rating' => (int) $body['overall_satisfaction_rating'],
            'what_works_well' => $body['what_works_well'] ?? null,
            'problems_found' => $body['problems_found'] ?? null,
            'improvement_suggestions' => $body['improvement_suggestions'] ?? null,
            'feedback_priority' => $body['feedback_priority'] ?? 'Should',
        ]);
        AuditLog::record($request->user['sub'], 'create', 'evaluation', $id);
        Response::json(['id' => $id], 201);
    }

    public function mine(Request $request): void
    {
        Response::json(Evaluation::allForEvaluator((int) $request->user['sub']));
    }
}
