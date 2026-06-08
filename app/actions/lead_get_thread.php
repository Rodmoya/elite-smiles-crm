<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/actions/lead_get_thread.php
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/leads/lead_service.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_email.php';

header('Content-Type: application/json; charset=utf-8');

if (!auth_check()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$leadId = (int) input('lead_id');
if ($leadId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Invalid lead selected.']);
    exit;
}

$lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
if (!$lead) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Lead not found.']);
    exit;
}

lead_comm_mark_read($leadId);
$snapshot = lead_comm_snapshot($leadId);
$snapshot['emails'] = lead_email_recent($leadId, 20);

echo json_encode([
    'ok' => true,
    'lead_id' => $leadId,
    'sms_opt_status' => (string)($lead['sms_opt_status'] ?? 'unknown'),
    'unread_message_count' => 0,
    'thread' => $snapshot,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
