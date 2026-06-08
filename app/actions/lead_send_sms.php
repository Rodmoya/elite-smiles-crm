<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/actions/lead_send_sms.php
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

function lead_sms_collect_buffer(): string
{
    $contents = '';

    while (ob_get_level() > 0) {
        $chunk = (string) ob_get_contents();
        if ($chunk !== '') {
            $contents .= $chunk;
        }
        ob_end_clean();
    }

    return trim($contents);
}

function lead_sms_json_response(int $statusCode, array $payload): void
{
    $buffer = lead_sms_collect_buffer();

    if ($buffer !== '') {
        $payload['buffer_output'] = $buffer;
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
    if (!in_array((int) $error['type'], $fatalTypes, true)) {
        return;
    }

    lead_sms_json_response(500, [
        'ok' => false,
        'message' => 'Fatal error while sending SMS.',
        'debug' => [
            'message' => $error['message'] ?? '',
            'file' => $error['file'] ?? '',
            'line' => $error['line'] ?? 0,
        ],
    ]);
});

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/twilio.php';
require_once dirname(__DIR__) . '/leads/lead_service.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';

if (!function_exists('auth_check')) {
    lead_sms_json_response(500, ['ok' => false, 'message' => 'Auth helper not available.']);
}

if (!auth_check()) {
    lead_sms_json_response(401, ['ok' => false, 'message' => 'Unauthorized.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    lead_sms_json_response(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

try {
    require_csrf();
} catch (Throwable $e) {
    lead_sms_json_response(419, ['ok' => false, 'message' => 'Invalid session token.']);
}

if (!function_exists('leads_table_exists') || !leads_table_exists()) {
    lead_sms_json_response(500, ['ok' => false, 'message' => 'Leads table not found.']);
}

$leadId = (int) post('lead_id');
$message = trim((string) post('message'));

if ($leadId <= 0) {
    lead_sms_json_response(422, ['ok' => false, 'message' => 'Invalid lead selected.']);
}

if ($message === '') {
    lead_sms_json_response(422, ['ok' => false, 'message' => 'Message cannot be empty.']);
}

try {
    $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
} catch (Throwable $e) {
    lead_sms_json_response(500, [
        'ok' => false,
        'message' => 'Could not load lead.',
        'debug' => ['exception' => $e->getMessage()],
    ]);
}

if (!$lead) {
    lead_sms_json_response(404, ['ok' => false, 'message' => 'Lead not found.']);
}

$smsOptStatus = trim((string) ($lead['sms_opt_status'] ?? 'unknown'));
if ($smsOptStatus === 'opted_out') {
    lead_sms_json_response(409, [
        'ok' => false,
        'message' => 'This lead has opted out of SMS. Do not send text messages unless they opt back in.',
        'lead_id' => $leadId,
    ]);
}

$phone = trim((string) ($lead['phone'] ?? ''));
$sendResult = elite_twilio_send_sms($phone, $message);

if (!($sendResult['ok'] ?? false)) {
    lead_sms_json_response(502, [
        'ok' => false,
        'message' => $sendResult['message'] ?? 'SMS failed.',
        'lead_id' => $leadId,
        'status_code' => $sendResult['status_code'] ?? 0,
        'twilio_code' => $sendResult['twilio_code'] ?? null,
    ]);
}

$messageRecordId = lead_comm_insert_message([
    'lead_id' => $leadId,
    'direction' => 'outbound',
    'channel' => 'sms',
    'from_number' => (string)($sendResult['from'] ?? ''),
    'to_number' => (string)($sendResult['to'] ?? $phone),
    'body' => $message,
    'twilio_message_sid' => (string)($sendResult['twilio_sid'] ?? ''),
    'twilio_status' => (string)($sendResult['twilio_status'] ?? ''),
    'is_read' => 1,
]);

lead_comm_insert_activity($leadId, 'sms_outbound', 'Sent SMS to ' . ($sendResult['to'] ?? $phone) . ': ' . mb_substr($message, 0, 240), [
    'message_id' => $messageRecordId,
    'twilio_sid' => $sendResult['twilio_sid'] ?? '',
    'twilio_status' => $sendResult['twilio_status'] ?? '',
]);
lead_comm_update_rollup($leadId);

$notes = (string) ($lead['notes'] ?? '');
$auditLine = '[' . date('Y-m-d H:i') . '] SMS sent via Twilio to ' . ($sendResult['to'] ?? '') . ': ' . mb_substr($message, 0, 240);
$updatedNotes = trim($notes) !== '' ? rtrim($notes) . "\n\n" . $auditLine : $auditLine;

if (function_exists('leads_has_column') && leads_has_column('notes')) {
    try {
        $setParts = ['notes = :notes'];
        $params = ['notes' => $updatedNotes, 'id' => $leadId];

        if (leads_has_column('updated_at')) {
            $setParts[] = 'updated_at = :updated_at';
            $params['updated_at'] = date('Y-m-d H:i:s');
        }

        db_execute('UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1', $params);
    } catch (Throwable $e) {
        esm_log('twilio_sms', 'SMS sent but note update failed', [
            'lead_id' => $leadId,
            'error' => $e->getMessage(),
        ]);
    }
}

lead_sms_json_response(200, [
    'ok' => true,
    'message' => 'SMS sent and logged.',
    'lead_id' => $leadId,
    'to' => $sendResult['to'] ?? '',
    'from' => $sendResult['from'] ?? '',
    'twilio_sid' => $sendResult['twilio_sid'] ?? '',
    'twilio_status' => $sendResult['twilio_status'] ?? '',
    'thread' => lead_comm_snapshot($leadId),
    'notes' => $updatedNotes,
]);
