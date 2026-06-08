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
require_once dirname(__DIR__) . '/core/twilio.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';

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
$status = trim((string)($_POST['MessageStatus'] ?? $_POST['SmsStatus'] ?? ''));
$errorCode = trim((string)($_POST['ErrorCode'] ?? ''));
$errorMessage = trim((string)($_POST['ErrorMessage'] ?? ''));

if ($sid === '') {
    echo 'ok';
    exit;
}

lead_comm_ensure_schema();

try {
    $message = db_one('SELECT * FROM lead_messages WHERE twilio_message_sid = :sid LIMIT 1', ['sid' => $sid]);
    if ($message) {
        $deliveredAt = in_array($status, ['delivered', 'sent'], true) ? now() : ($message['delivered_at'] ?? null);
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

        $leadId = (int)($message['lead_id'] ?? 0);
        if ($leadId > 0 && in_array($status, ['failed', 'undelivered'], true)) {
            lead_comm_insert_activity($leadId, 'sms_delivery_issue', 'Twilio SMS delivery issue: ' . $status . ($errorCode !== '' ? ' (' . $errorCode . ')' : ''), [
                'twilio_sid' => $sid,
                'status' => $status,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ], 'Twilio');
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
