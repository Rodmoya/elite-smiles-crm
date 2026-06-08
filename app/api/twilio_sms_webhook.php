<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/api/twilio_sms_webhook.php
 *
 * Twilio inbound SMS webhook.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/twilio.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_ai.php';

header('Content-Type: text/xml; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo '<Response></Response>';
    exit;
}

if (!elite_twilio_validate_request($_POST)) {
    http_response_code(403);
    esm_log('twilio_inbound', 'Rejected inbound SMS webhook due to invalid signature.', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    echo '<Response></Response>';
    exit;
}

$from = trim((string)($_POST['From'] ?? ''));
$to = trim((string)($_POST['To'] ?? ''));
$body = trim((string)($_POST['Body'] ?? ''));
$messageSid = trim((string)($_POST['MessageSid'] ?? $_POST['SmsSid'] ?? ''));
$status = trim((string)($_POST['SmsStatus'] ?? $_POST['MessageStatus'] ?? 'received'));

if ($from === '' || $body === '') {
    echo '<Response></Response>';
    exit;
}

$lead = lead_comm_find_lead_by_phone($from);
if (!$lead) {
    $lead = lead_comm_create_inbound_lead($from, $body);
}

if (!$lead) {
    esm_log('twilio_inbound', 'Inbound SMS could not be matched or saved.', [
        'from' => $from,
        'to' => $to,
        'sid' => $messageSid,
    ]);
    echo '<Response></Response>';
    exit;
}

$leadId = (int)($lead['id'] ?? 0);
$messageId = lead_comm_insert_message([
    'lead_id' => $leadId,
    'direction' => 'inbound',
    'channel' => 'sms',
    'from_number' => $from,
    'to_number' => $to,
    'body' => $body,
    'twilio_message_sid' => $messageSid,
    'twilio_status' => $status,
    'is_read' => 0,
]);

lead_comm_insert_activity($leadId, 'sms_inbound', 'Patient replied by SMS: ' . mb_substr($body, 0, 500), [
    'message_id' => $messageId,
    'twilio_sid' => $messageSid,
    'from' => $from,
    'to' => $to,
], 'Twilio');

$command = lead_comm_opt_command($body);
if ($command === 'opt_out') {
    lead_comm_set_sms_opt_status($leadId, 'opted_out');
    if (function_exists('leads_has_column') && leads_has_column('status')) {
        $setParts = ["status = 'opted_out'"];
        $params = ['id' => $leadId];
        if (leads_has_column('updated_at')) {
            $setParts[] = 'updated_at = :updated_at';
            $params['updated_at'] = now();
        }
        db_execute(
            'UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1',
            $params
        );
    }
    lead_comm_insert_activity($leadId, 'sms_opt_out', 'SMS opt-out captured from patient reply. Do not text this lead unless they opt back in.', [
        'source' => 'twilio_sms_webhook',
        'body' => $body,
    ], 'Twilio');
} elseif ($command === 'opt_in') {
    lead_comm_set_sms_opt_status($leadId, 'opted_in');
    lead_comm_insert_activity($leadId, 'sms_opt_in', 'SMS opt-in captured from patient reply.', [
        'source' => 'twilio_sms_webhook',
        'body' => $body,
    ], 'Twilio');
}

lead_comm_update_rollup($leadId);

esm_log('twilio_inbound', 'Inbound SMS saved.', [
    'lead_id' => $leadId,
    'message_id' => $messageId,
    'from' => $from,
    'sid' => $messageSid,
    'command' => $command,
]);

if ($command === 'help') {
    echo '<Response><Message>Elite Smiles: We received your HELP request. Reply with your question and our team will help. Reply STOP to opt out. Message and data rates may apply.</Message></Response>';
    exit;
}

lead_ai_maybe_autoreply_inbound($leadId, $body, $command);

echo '<Response></Response>';
