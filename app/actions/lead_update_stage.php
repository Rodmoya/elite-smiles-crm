<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/actions/lead_update_stage.php
 *
 * AJAX endpoint to save lead stage changes.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/leads/lead_meta.php';
require_once dirname(__DIR__) . '/leads/lead_service.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';

header('Content-Type: application/json; charset=utf-8');

if (!auth_check()) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'message' => 'Unauthorized.',
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Method not allowed.',
    ]);
    exit;
}

try {
    require_csrf();
} catch (Throwable $e) {
    http_response_code(419);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid session token.',
    ]);
    exit;
}

if (!leads_table_exists()) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Leads table not found.',
    ]);
    exit;
}

$leadId = (int) post('lead_id');
$newStage = trim((string) post('status'));

if ($leadId <= 0) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid lead selected.',
    ]);
    exit;
}

if ($newStage === '' || $newStage === '_blank') {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid stage selected.',
    ]);
    exit;
}

$allowedStages = lead_stage_labels();
if (!isset($allowedStages[$newStage])) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Stage is not allowed.',
    ]);
    exit;
}

try {
    lead_comm_ensure_schema();
    $existingLead = db_one(
        "SELECT * FROM leads WHERE id = :id LIMIT 1",
        ['id' => $leadId]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Could not verify lead.',
    ]);
    exit;
}

if (!$existingLead) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'message' => 'Lead not found.',
    ]);
    exit;
}

$setParts = [];
$params = [
    'id' => $leadId,
    'status' => $newStage,
];

if (leads_has_column('status')) {
    $setParts[] = "status = :status";
}

if (leads_has_column('updated_at')) {
    $setParts[] = "updated_at = :updated_at";
    $params['updated_at'] = now();
}

if (empty($setParts)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'No compatible stage field available to update.',
    ]);
    exit;
}

try {
    db_execute(
        "UPDATE leads SET " . implode(', ', $setParts) . " WHERE id = :id LIMIT 1",
        $params
    );

    $oldStage = trim((string)($existingLead['status'] ?? ''));
    if ($oldStage !== $newStage) {
        lead_comm_insert_activity(
            $leadId,
            'stage_change',
            'Moved stage from ' . ($allowedStages[$oldStage] ?? ($oldStage !== '' ? $oldStage : 'Unstaged')) . ' to ' . ($allowedStages[$newStage] ?? $newStage) . '.',
            [
                'from' => $oldStage,
                'to' => $newStage,
            ]
        );
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Lead stage updated.',
        'lead_id' => $leadId,
        'status' => $newStage,
        'status_label' => $allowedStages[$newStage] ?? $newStage,
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Failed to update lead stage.',
    ]);
    exit;
}
