<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\FeedbackImplementation;

class FeedbackImplementationController
{
    public function index(Request $request, array $params): void
    {
        Response::json(FeedbackImplementation::allForFeedback((int) $params['id']));
    }

    public function mine(Request $request): void
    {
        Response::json(FeedbackImplementation::allForUser((int) $request->user['sub']));
    }

    public function store(Request $request, array $params): void
    {
        $feedbackId = (int) $params['id'];
        $body = $request->body;
        $id = FeedbackImplementation::create([
            'feedback_id' => $feedbackId,
            'backlog_id' => $body['backlog_id'] ?? null,
            'prototype_id' => $body['prototype_id'] ?? null,
            'implementation_action' => $body['implementation_action'] ?? null,
            'before_change_description' => $body['before_change_description'] ?? null,
            'after_change_description' => $body['after_change_description'] ?? null,
            'developer_notes' => $body['developer_notes'] ?? null,
            'status' => $body['status'] ?? 'Not Started',
            'assigned_developer' => $request->user['sub'],
        ]);
        AuditLog::record($request->user['sub'], 'create', 'feedback_implementation', $id);
        Response::json(FeedbackImplementation::find($id), 201);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = FeedbackImplementation::find($id);
        if (!$existing) {
            Response::error('Feedback implementation record not found', 404);
        }
        FeedbackImplementation::update($id, array_merge($existing, $request->body));
        AuditLog::record($request->user['sub'], 'update', 'feedback_implementation', $id);
        Response::json(FeedbackImplementation::find($id));
    }
}
