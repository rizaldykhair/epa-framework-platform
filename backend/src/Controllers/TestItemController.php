<?php

namespace App\Controllers;

use App\Core\EpaWorkflow;
use App\Core\Guard;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\Defect;
use App\Models\TestItem;

class TestItemController
{
    public function index(Request $request, array $params): void
    {
        Response::json(TestItem::allForProject((int) $params['id']));
    }

    public function byPrototype(Request $request, array $params): void
    {
        Response::json(TestItem::allByPrototype((int) $params['id']));
    }

    public function store(Request $request, array $params): void
    {
        $projectId = (int) $params['id'];
        $body = $request->body;
        if (empty($body['scenario'])) {
            Response::error('scenario is required', 422);
        }
        $prefix = ($body['type'] ?? 'Functional') === 'Security' ? 'SEC' : 'UT';
        $id = TestItem::create([
            'project_id' => $projectId,
            'created_by' => $request->user['sub'],
            'backlog_id' => $body['backlog_id'] ?? null,
            'prototype_id' => $body['prototype_id'] ?? null,
            'code' => TestItem::nextCode($projectId, $prefix),
            'scenario' => $body['scenario'],
            'type' => $body['type'] ?? 'Functional',
            'result' => $body['result'] ?? 'Not Run',
            'severity' => $body['severity'] ?? null,
            'notes' => $body['notes'] ?? null,
            'evidence_url' => $body['evidence_url'] ?? null,
            'executed_by' => $request->user['sub'],
        ]);
        $this->syncDefect($id, $projectId, $request->user['sub']);
        EpaWorkflow::syncProjectCompletionStatus($projectId);
        AuditLog::record($request->user['sub'], 'create', 'test', $id);
        Response::json(TestItem::find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = TestItem::find($id);
        if (!$existing) {
            Response::error('Test not found', 404);
        }
        Guard::requireProjectAccess($request, (int) $existing['project_id']);
        $body = array_merge($existing, $request->body, ['executed_by' => $request->user['sub']]);
        TestItem::update($id, $body);
        $this->syncDefect($id, (int) $existing['project_id'], $request->user['sub']);
        EpaWorkflow::syncProjectCompletionStatus((int) $existing['project_id']);
        AuditLog::record($request->user['sub'], 'update', 'test', $id);
        Response::json(TestItem::find($id));
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = TestItem::find($id);
        if ($existing) {
            Guard::requireProjectAccess($request, (int) $existing['project_id']);
        }
        TestItem::delete($id);
        AuditLog::record($request->user['sub'], 'delete', 'test', $id);
        Response::json(['deleted' => true]);
    }

    private function syncDefect(int $testId, int $projectId, int $userId): void
    {
        $test = TestItem::find($testId);
        if ($test && $test['result'] === 'Fail' && !Defect::existsForTest($testId)) {
            Defect::create([
                'test_id' => $testId,
                'project_id' => $projectId,
                'description' => 'Defect from failed test: ' . $test['scenario'],
                'severity' => 'Medium',
                'status' => 'Open',
                'reported_by' => $userId,
            ]);
        }
    }
}
