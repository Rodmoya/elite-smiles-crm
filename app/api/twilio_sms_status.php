<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/api/twilio_sms_status.php
 *
 * Twilio outbound SMS status callback.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/mailer.php';
require_once dirname(__DIR__) . '/core/twilio.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_agent.php';
require_once dirname(__DIR__) . '/leads/lead_agent_observability.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo 'method not allowed';
    exit;
}

if (!elite_twilio_validate_request($_POST)) {
    http_response_code(403);
    esm_log('twilio_status', 'Rejected SMS status callback due to invalid signature.', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    echo 'forbidden';
    exit;
}

$sid = trim((string)($_POST['MessageSid'] ?? $_POST['SmsSid'] ?? ''));
$status = strtolower(trim((string)($_POST['MessageStatus'] ?? $_POST['SmsStatus'] ?? '')));
$errorCode = trim((string)($_POST['ErrorCode'] ?? ''));
$errorMessage = trim((string)($_POST['ErrorMessage'] ?? ''));

if ($sid === '') {
    echo 'ok';
    exit;
}

lead_comm_ensure_schema();

try {
    // Internal notifications use the same callback endpoint. Update their
    // delivery audit first because they are not stored in lead_messages.
    $internalMessage = null;
    try {
        $internalMessage = db_one('SELECT * FROM internal_sms_logs WHERE twilio_sid = :sid LIMIT 1', ['sid' => $sid]);
    } catch (Throwable $e) {
        // The internal alert table may not exist on older deployments yet.
    }
    if (is_array($internalMessage)) {
        $previousInternalStatus = strtolower(trim((string) ($internalMessage['twilio_status'] ?? '')));
        db_execute(
            'UPDATE internal_sms_logs SET twilio_status = :status, error_message = :error_message WHERE id = :id LIMIT 1',
            [
                'id' => (int) ($internalMessage['id'] ?? 0),
                'status' => $status,
                'error_message' => $errorMessage !== '' ? $errorMessage : null,
            ]
        );
        if (in_array($status, ['failed', 'undelivered'], true) && $previousInternalStatus !== $status
            && function_exists('elite_send_pushover_notification')) {
            $recipientName = trim((string) ($internalMessage['recipient_name'] ?? 'internal recipient'));
            elite_send_pushover_notification(
                'Internal SMS delivery failed',
                'Twilio could not deliver an Elite Smiles internal alert to ' . $recipientName
                    . ($errorCode !== '' ? ' (' . $errorCode . ')' : '') . '. Open the CRM notification log.',
                base_url('crm-settings.php'),
                'Open notification settings'
            );
        }
    }

    $message = db_one('SELECT * FROM lead_messages WHERE twilio_message_sid = :sid LIMIT 1', ['sid' => $sid]);
    if ($message) {
        $previousStatus = strtolower(trim((string) ($message['twilio_status'] ?? '')));
        $deliveredAt = $status === 'delivered' ? now() : ($message['delivered_at'] ?? null);
        db_query(
            'UPDATE lead_messages
             SET twilio_status = :status,
                 twilio_error_code = :error_code,
                 twilio_error_message = :error_message,
                 delivered_at = :delivered_at
             WHERE id = :id
             LIMIT 1',
            [
                'id' => (int)$message['id'],
                'status' => $status,
                'error_code' => $errorCode,
                'error_message' => $errorMessage !== '' ? $errorMessage : null,
                'delivered_at' => $deliveredAt,
            ]
        );
        lead_agent_update_touchpoint_delivery('sms', (int) $message['id'], $status, $sid);

        $leadId = (int)($message['lead_id'] ?? 0);
        if ($leadId > 0 && in_array($status, ['failed', 'undelivered'], true) && $previousStatus !== $status) {
            lead_agent_mark_sms_delivery_attention($leadId, $status, $errorCode, $errorMessage, [
                'event_key' => 'twilio-status-' . $sid . '-' . $status,
                'source' => 'twilio_status_callback',
                'twilio_sid' => $sid,
            ]);
        }
    }
} catch (Throwable $e) {
    esm_log('twilio_status', 'Could not update SMS status callback.', [
        'sid' => $sid,
        'status' => $status,
        'error' => $e->getMessage(),
    ]);
}

echo 'ok';
