<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Authenticated endpoint for sending patient email follow-ups.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_email.php';

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
$subject = trim((string) post('subject'));
$body = trim((string) post('body'));

if ($leadId <= 0) {
    json_response(['ok' => false, 'message' => 'Invalid lead selected.'], 422);
}

if (!elite_smtp_is_configured()) {
    json_response(['ok' => false, 'message' => 'SMTP is not configured yet. Add the cPanel mailbox SMTP values to .env first.'], 503);
}

$result = lead_email_send($leadId, $subject, $body);
if (empty($result['ok'])) {
    json_response([
        'ok' => false,
        'message' => (string)($result['message'] ?? 'Email failed.'),
        'lead_id' => $leadId,
    ], 502);
}

json_response([
    'ok' => true,
    'message' => 'Email sent and logged.',
    'lead_id' => $leadId,
    'email_id' => (int)($result['email_id'] ?? 0),
    'to' => (string)($result['to'] ?? ''),
], 200);
