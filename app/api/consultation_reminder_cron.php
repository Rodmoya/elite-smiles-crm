<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Cron-safe consultation reminder runner.
 *
 * Sends deterministic appointment reminders:
 * - day_before: after 9:00 AM the day before the scheduled consultation
 * - morning_of: after 7:00 AM on the consultation day, before the appointment
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/twilio.php';
require_once dirname(__DIR__) . '/leads/lead_service.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_email.php';

header('Content-Type: application/json; charset=utf-8');

function consultation_reminder_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function consultation_reminder_secret(): string
{
    $header = trim((string)($_SERVER['HTTP_X_ELITE_CRON_SECRET'] ?? ''));
    if ($header !== '') {
        return $header;
    }

    return trim((string)($_GET['secret'] ?? $_POST['secret'] ?? ''));
}

function consultation_reminder_ensure_schema(): void
{
    db_query("
        CREATE TABLE IF NOT EXISTS lead_consultation_reminders (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id INT UNSIGNED NOT NULL,
            reminder_key VARCHAR(40) NOT NULL DEFAULT '',
            channel VARCHAR(20) NOT NULL DEFAULT '',
            consultation_date DATETIME NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'sent',
            provider_ref VARCHAR(190) NOT NULL DEFAULT '',
            provider_response TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_lead_reminder (lead_id, reminder_key, channel),
            KEY idx_status_created (status, created_at),
            KEY idx_consultation_date (consultation_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function consultation_reminder_already_sent(int $leadId, string $reminderKey, string $channel, string $consultationDate): bool
{
    consultation_reminder_ensure_schema();

    return (int) db_value(
        "SELECT COUNT(*)
         FROM lead_consultation_reminders
         WHERE lead_id = :lead_id
           AND reminder_key = :reminder_key
           AND channel = :channel
           AND consultation_date = :consultation_date
           AND status = 'sent'",
        [
            'lead_id' => $leadId,
            'reminder_key' => $reminderKey,
            'channel' => $channel,
            'consultation_date' => $consultationDate,
        ]
    ) > 0;
}

function consultation_reminder_record(int $leadId, string $reminderKey, string $channel, string $consultationDate, string $status, string $providerRef = '', string $providerResponse = ''): int
{
    consultation_reminder_ensure_schema();

    return db_insert(
        'INSERT INTO lead_consultation_reminders (
            lead_id, reminder_key, channel, consultation_date, status,
            provider_ref, provider_response, created_at
         ) VALUES (
            :lead_id, :reminder_key, :channel, :consultation_date, :status,
            :provider_ref, :provider_response, :created_at
         )',
        [
            'lead_id' => $leadId,
            'reminder_key' => $reminderKey,
            'channel' => $channel,
            'consultation_date' => $consultationDate,
            'status' => $status,
            'provider_ref' => $providerRef,
            'provider_response' => $providerResponse !== '' ? $providerResponse : null,
            'created_at' => now(),
        ]
    );
}

function consultation_reminder_first_name(array $lead): string
{
    $name = trim((string)($lead['full_name'] ?? ''));
    if ($name === '') {
        return '';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    return trim((string)($parts[0] ?? ''));
}

function consultation_reminder_format_appointment(string $consultationDate): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $consultationDate, new DateTimeZone(APP_TIMEZONE));
    if (!$dt) {
        $dt = new DateTimeImmutable($consultationDate, new DateTimeZone(APP_TIMEZONE));
    }

    return $dt->format('l, F j') . ' at ' . $dt->format('g:i A');
}

function consultation_reminder_copy(array $lead, string $reminderKey): array
{
    $firstName = consultation_reminder_first_name($lead);
    $greeting = $firstName !== '' ? 'Hi ' . $firstName . ',' : 'Hi,';
    $appointment = consultation_reminder_format_appointment((string)$lead['consultation_date']);

    if ($reminderKey === 'morning_of') {
        return [
            'subject' => 'Reminder: your Elite Smiles consultation is today',
            'email' => implode("\n\n", [
                $greeting,
                'This is a quick reminder that your consultation with Elite Smiles is today, ' . $appointment . '.',
                'We look forward to seeing you. If anything changes or you need help finding us, please call us at (801) 572-6262.',
                "Warmly,\nThe Elite Smiles Team\n11762 South State, Suite 300\nDraper, UT 84020",
            ]),
            'sms' => trim(($firstName !== '' ? 'Hi ' . $firstName . ', ' : 'Hi, ') . 'reminder from Elite Smiles: your consultation is today at ' . (new DateTimeImmutable((string)$lead['consultation_date']))->format('g:i A') . '. Questions? Call (801) 572-6262.'),
        ];
    }

    return [
        'subject' => 'Reminder: your Elite Smiles consultation is tomorrow',
        'email' => implode("\n\n", [
            $greeting,
            'This is a friendly reminder that your consultation with Elite Smiles is tomorrow, ' . $appointment . '.',
            'Your consultation is free, and Dr. Meden’s team will review your options, pricing, and financing clearly based on your specific case.',
            'If you need to make any changes, please call us at (801) 572-6262.',
            "Warmly,\nThe Elite Smiles Team\n11762 South State, Suite 300\nDraper, UT 84020",
        ]),
        'sms' => trim(($firstName !== '' ? 'Hi ' . $firstName . ', ' : 'Hi, ') . 'reminder from Elite Smiles: your consultation is tomorrow at ' . (new DateTimeImmutable((string)$lead['consultation_date']))->format('g:i A') . '. Questions? Call (801) 572-6262.'),
    ];
}

function consultation_reminder_send_email(array $lead, string $reminderKey, array $copy): array
{
    $leadId = (int)($lead['id'] ?? 0);
    $consultationDate = (string)($lead['consultation_date'] ?? '');

    if ($leadId <= 0 || $consultationDate === '') {
        return ['ok' => false, 'status' => 'skipped', 'reason' => 'Invalid lead or appointment.'];
    }

    if (consultation_reminder_already_sent($leadId, $reminderKey, 'email', $consultationDate)) {
        return ['ok' => true, 'status' => 'already_sent'];
    }

    if (trim((string)($lead['email'] ?? '')) === '') {
        return ['ok' => false, 'status' => 'skipped', 'reason' => 'No email address.'];
    }

    if ((string)($lead['email_opt_status'] ?? 'subscribed') === 'unsubscribed') {
        return ['ok' => false, 'status' => 'skipped', 'reason' => 'Email opted out.'];
    }

    $send = lead_email_send($leadId, (string)$copy['subject'], (string)$copy['email'], 'Appointment Reminder');
    consultation_reminder_record(
        $leadId,
        $reminderKey,
        'email',
        $consultationDate,
        !empty($send['ok']) ? 'sent' : 'failed',
        (string)($send['email_id'] ?? ''),
        (string)($send['message'] ?? '')
    );

    if (!empty($send['ok'])) {
        lead_email_send_action_alert($lead, 'reminder_sent', $reminderKey . ' email');
    }

    return [
        'ok' => !empty($send['ok']),
        'status' => !empty($send['ok']) ? 'sent' : 'failed',
        'message' => (string)($send['message'] ?? ''),
        'email_id' => (int)($send['email_id'] ?? 0),
    ];
}

function consultation_reminder_send_sms(array $lead, string $reminderKey, array $copy): array
{
    $leadId = (int)($lead['id'] ?? 0);
    $consultationDate = (string)($lead['consultation_date'] ?? '');

    if ($leadId <= 0 || $consultationDate === '') {
        return ['ok' => false, 'status' => 'skipped', 'reason' => 'Invalid lead or appointment.'];
    }

    if (!ELITE_CONSULTATION_REMINDER_SMS_ENABLED) {
        return ['ok' => false, 'status' => 'disabled', 'reason' => 'SMS reminders disabled until texting is available.'];
    }

    if (!elite_twilio_is_configured()) {
        return ['ok' => false, 'status' => 'disabled', 'reason' => 'Twilio is not configured.'];
    }

    if ((string)($lead['sms_opt_status'] ?? 'unknown') === 'opted_out') {
        return ['ok' => false, 'status' => 'skipped', 'reason' => 'SMS opted out.'];
    }

    if (consultation_reminder_already_sent($leadId, $reminderKey, 'sms', $consultationDate)) {
        return ['ok' => true, 'status' => 'already_sent'];
    }

    $send = elite_twilio_send_sms((string)($lead['phone'] ?? ''), (string)$copy['sms']);
    $status = !empty($send['ok']) ? 'sent' : 'failed';
    consultation_reminder_record(
        $leadId,
        $reminderKey,
        'sms',
        $consultationDate,
        $status,
        (string)($send['twilio_sid'] ?? ''),
        (string)($send['message'] ?? '')
    );

    if (!empty($send['ok'])) {
        $messageId = lead_comm_insert_message([
            'lead_id' => $leadId,
            'direction' => 'outbound',
            'channel' => 'sms',
            'from_number' => (string)($send['from'] ?? ''),
            'to_number' => (string)($send['to'] ?? $lead['phone'] ?? ''),
            'body' => (string)$copy['sms'],
            'twilio_message_sid' => (string)($send['twilio_sid'] ?? ''),
            'twilio_status' => (string)($send['twilio_status'] ?? ''),
            'is_read' => 1,
        ]);
        lead_comm_insert_activity($leadId, 'appointment_sms_reminder', 'Sent appointment reminder SMS: ' . mb_substr((string)$copy['sms'], 0, 220), [
            'message_id' => $messageId,
            'reminder_key' => $reminderKey,
            'twilio_sid' => $send['twilio_sid'] ?? '',
        ], 'Appointment Reminder');
        lead_comm_update_rollup($leadId);
        lead_email_send_action_alert($lead, 'reminder_sent', $reminderKey . ' sms');
    } else {
        lead_comm_insert_activity($leadId, 'appointment_sms_reminder_failed', 'Appointment reminder SMS failed: ' . (string)($send['message'] ?? 'Unknown error'), [
            'reminder_key' => $reminderKey,
            'twilio_code' => $send['twilio_code'] ?? null,
        ], 'Appointment Reminder');
    }

    return [
        'ok' => !empty($send['ok']),
        'status' => $status,
        'message' => (string)($send['message'] ?? ''),
        'twilio_sid' => (string)($send['twilio_sid'] ?? ''),
    ];
}

$configuredSecret = trim((string)ELITE_CONSULTATION_REMINDER_CRON_SECRET);
if ($configuredSecret === '' || !hash_equals($configuredSecret, consultation_reminder_secret())) {
    consultation_reminder_json(['ok' => false, 'message' => 'Unauthorized.'], 401);
}

if (!elite_smtp_is_configured()) {
    consultation_reminder_json(['ok' => false, 'message' => 'SMTP is not configured.'], 503);
}

try {
    consultation_reminder_ensure_schema();
} catch (Throwable $e) {
    esm_log('appointment_reminders', 'Could not ensure reminder schema.', ['error' => $e->getMessage()]);
    consultation_reminder_json(['ok' => false, 'message' => 'Could not initialize reminder schema.'], 500);
}

$now = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
$currentTime = $now->format('H:i:s');
$dueKeys = [];

if ($currentTime >= '09:00:00') {
    $dueKeys[] = 'day_before';
}
if ($currentTime >= '07:00:00') {
    $dueKeys[] = 'morning_of';
}

$processed = 0;
$results = [];
$limit = max(1, min(50, (int)($_GET['limit'] ?? $_POST['limit'] ?? 25)));

foreach ($dueKeys as $reminderKey) {
    $targetDate = $reminderKey === 'day_before'
        ? $now->modify('+1 day')->format('Y-m-d')
        : $now->format('Y-m-d');

    $rows = db_all(
        "SELECT *
         FROM leads
         WHERE consultation_date IS NOT NULL
           AND consultation_date <> ''
           AND DATE(consultation_date) = :target_date
           AND consultation_date > NOW()
           AND status = 'consultation_booked'
           AND (consultation_status IS NULL OR consultation_status = '' OR consultation_status = 'scheduled')
         ORDER BY consultation_date ASC, id ASC
         LIMIT {$limit}",
        ['target_date' => $targetDate]
    );

    foreach ($rows as $lead) {
        $processed++;
        $leadId = (int)($lead['id'] ?? 0);
        $copy = consultation_reminder_copy($lead, $reminderKey);
        $emailResult = consultation_reminder_send_email($lead, $reminderKey, $copy);
        $smsResult = consultation_reminder_send_sms($lead, $reminderKey, $copy);

        $results[] = [
            'lead_id' => $leadId,
            'name' => (string)($lead['full_name'] ?? ''),
            'reminder_key' => $reminderKey,
            'consultation_date' => (string)($lead['consultation_date'] ?? ''),
            'email' => $emailResult,
            'sms' => $smsResult,
        ];
    }
}

consultation_reminder_json([
    'ok' => true,
    'message' => 'Consultation reminder run complete.',
    'processed' => $processed,
    'results' => $results,
    'sms_enabled' => ELITE_CONSULTATION_REMINDER_SMS_ENABLED,
]);
