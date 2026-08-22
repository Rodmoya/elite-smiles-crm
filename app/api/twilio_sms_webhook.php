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
require_once dirname(__DIR__) . '/core/mobile_ai_push.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_ai.php';
require_once dirname(__DIR__) . '/leads/lead_agent.php';

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

// Rod's private operator number is a deterministic command channel. Route it
// before patient lookup so an internal reply can never create or mutate a lead.
if (lead_agent_is_operator_sender($from)) {
    try {
        $operatorResult = lead_agent_handle_operator_sms($from, $body, $messageSid);
        $reply = trim((string) ($operatorResult['reply'] ?? ''));
        esm_log('lead_agent_operator_sms', 'Processed authorized operator SMS.', [
            'sid' => $messageSid,
            'handled' => !empty($operatorResult['handled']),
            'duplicate' => !empty($operatorResult['duplicate']),
        ]);
        echo $reply !== ''
            ? '<Response><Message>' . htmlspecialchars($reply, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Message></Response>'
            : '<Response></Response>';
    } catch (Throwable $e) {
        esm_log('lead_agent_operator_sms', 'Operator SMS command failed safely.', ['sid' => $messageSid, 'error' => $e->getMessage()]);
        echo '<Response><Message>Elite AI: I could not process that instruction, so nothing was sent. Please open the CRM.</Message></Response>';
    }
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
} else {
    $currentStage = trim((string)($lead['status'] ?? ''));
    if (in_array($currentStage, ['new_lead', 'attempted_contact', 'contacted', ''], true) && function_exists('leads_has_column') && leads_has_column('status')) {
        $setParts = ["status = 'in_contact'"];
        $params = ['id' => $leadId];
        if (leads_has_column('pipeline_position') && function_exists('lead_pipeline_next_position')) {
            $setParts[] = 'pipeline_position = :pipeline_position';
            $params['pipeline_position'] = lead_pipeline_next_position('in_contact');
        }
        if (leads_has_column('updated_at')) {
            $setParts[] = 'updated_at = :updated_at';
            $params['updated_at'] = now();
        }
        db_execute(
            'UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1',
            $params
        );
    }
}

lead_comm_update_rollup($leadId);

try {
    $freshLeadForPush = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
    $pushResult = mobile_ai_send_lead_event_push($freshLeadForPush ?: $lead, [
        'lead_id' => $leadId,
        'type' => $command === 'opt_out' ? 'stop' : 'reply',
        'message' => $body,
        'notification_id' => 'msg-' . $messageId,
    ]);
    lead_comm_insert_activity($leadId, !empty($pushResult['sent']) ? 'mobile_ai_push_sent' : 'mobile_ai_push_skipped', !empty($pushResult['sent']) ? 'Elite AI push notification sent for inbound SMS.' : 'Elite AI push notification was not delivered to a connected device.', [
        'source' => 'twilio_sms_webhook',
        'message_id' => $messageId,
        'push_result' => $pushResult,
    ], 'System');
} catch (Throwable $e) {
    esm_log('mobile_ai_push', 'Inbound SMS Elite AI push failed.', [
        'lead_id' => $leadId,
        'message_id' => $messageId,
        'error' => $e->getMessage(),
    ]);
}

if (function_exists('elite_send_operator_follow_up_pushover')) {
    try {
        $freshLead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        $pushoverSent = elite_send_operator_follow_up_pushover($freshLead ?: $lead, [
            'event' => 'communication',
            'channel' => 'sms',
            'summary' => 'New SMS reply received from patient.',
            'note' => mb_substr($body, 0, 180),
            'quick_action_mode' => 'communication',
        ]);
        lead_comm_insert_activity($leadId, $pushoverSent ? 'operator_pushover_sent' : 'operator_pushover_failed', $pushoverSent ? 'Pushover notification sent for inbound SMS.' : 'Tried to send Pushover notification for inbound SMS, but no delivery was reported.', [
            'source' => 'twilio_sms_webhook',
            'message_id' => $messageId,
            'twilio_sid' => $messageSid,
        ], 'System');
    } catch (Throwable $e) {
        esm_log('twilio_inbound', 'Inbound SMS Pushover notification failed.', [
            'lead_id' => $leadId,
            'message_id' => $messageId,
            'error' => $e->getMessage(),
        ]);
    }
}

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

if (lead_agent_enabled()) {
    lead_agent_handle_inbound($leadId, $body, 'sms', 'sms-' . ($messageSid !== '' ? $messageSid : (string) $messageId));
} else {
    lead_ai_maybe_autoreply_inbound($leadId, $body, $command);
}

echo '<Response></Response>';
