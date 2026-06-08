<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Authenticated endpoint for AI patient email drafts.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_email.php';
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
$mode = trim((string) post('mode', 'email_draft'));

if ($leadId <= 0) {
    json_response(['ok' => false, 'message' => 'Invalid lead selected.'], 422);
}

$lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
if (!$lead) {
    json_response(['ok' => false, 'message' => 'Lead not found.'], 404);
}

if (trim((string)($lead['email'] ?? '')) === '') {
    json_response(['ok' => false, 'message' => 'Add a lead email address before drafting.'], 422);
}

$result = lead_ai_generate_email($lead, $instruction, $mode);
if (empty($result['ok'])) {
    json_response(['ok' => false, 'message' => (string)($result['message'] ?? 'AI email draft failed.')], 502);
}

lead_comm_insert_activity($leadId, 'ai_email_draft', 'AI drafted an email for review: ' . mb_substr((string)($result['data']['subject'] ?? ''), 0, 180), [
    'classification' => $result['data']['classification'] ?? '',
    'confidence' => $result['data']['confidence'] ?? 0,
    'note' => $result['data']['note'] ?? '',
], 'OpenAI');

json_response([
    'ok' => true,
    'lead_id' => $leadId,
    'draft' => $result['data'],
], 200);
