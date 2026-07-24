<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Authenticated endpoint for AI patient SMS drafts.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_ai.php';

header('Content-Type: application/json; charset=utf-8');

if (!auth_check()) {
    json_response(['ok' => false, 'message' => 'Unauthorized.'], 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    require_csrf();
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Invalid session token.'], 419);
}

$leadId = (int) post('lead_id');
$instruction = trim((string) post('instruction'));
$mode = trim((string) post('mode', 'sms_draft'));
$currentMessage = trim((string) post('current_message'));
$isImproveMode = substr($mode, 0, 16) === 'operator_improve';

if ($leadId <= 0) {
    json_response(['ok' => false, 'message' => 'Invalid lead selected.'], 422);
}

$lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
if (!$lead) {
    json_response(['ok' => false, 'message' => 'Lead not found.'], 404);
}

if (trim((string)($lead['phone'] ?? '')) === '') {
    json_response(['ok' => false, 'message' => 'Add a lead phone number before drafting.'], 422);
}

if (trim((string)($lead['sms_opt_status'] ?? 'unknown')) === 'opted_out') {
    json_response(['ok' => false, 'message' => 'This lead has opted out of SMS.'], 409);
}

$result = $isImproveMode
    ? lead_ai_improve_sms($lead, $currentMessage, $instruction)
    : lead_ai_generate_reply($lead, $instruction, $mode);
if (empty($result['ok'])) {
    json_response(['ok' => false, 'message' => (string)($result['message'] ?? 'AI SMS draft failed.')], 502);
}

lead_comm_insert_activity(
    $leadId,
    'ai_draft',
    ($isImproveMode ? 'AI improved' : 'AI drafted') . ' an SMS for review: ' . mb_substr((string)($result['data']['reply'] ?? ''), 0, 180),
    [
        'classification' => $result['data']['classification'] ?? '',
        'confidence' => $result['data']['confidence'] ?? 0,
        'note' => $result['data']['note'] ?? '',
    ],
    'OpenAI'
);

json_response([
    'ok' => true,
    'lead_id' => $leadId,
    'draft' => $result['data'],
], 200);
