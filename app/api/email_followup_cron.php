<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Cron-safe email follow-up runner.
 *
 * Intended for cPanel cron or manual server-side checks while Twilio is pending.
 * Disabled by default until SMTP and content quality are tested.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/leads/lead_service.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_email.php';
require_once dirname(__DIR__) . '/leads/lead_ai.php';

header('Content-Type: application/json; charset=utf-8');

function email_followup_secret(): string
{
    $header = trim((string)($_SERVER['HTTP_X_ELITE_CRON_SECRET'] ?? ''));
    if ($header !== '') {
        return $header;
    }
    return trim((string)($_GET['secret'] ?? $_POST['secret'] ?? ''));
}

function email_followup_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$configuredSecret = trim((string) ELITE_EMAIL_FOLLOWUP_CRON_SECRET);
if ($configuredSecret === '' || !hash_equals($configuredSecret, email_followup_secret())) {
    email_followup_json(['ok' => false, 'message' => 'Unauthorized.'], 401);
}

if (!ELITE_EMAIL_AUTOFOLLOWUP_ENABLED) {
    email_followup_json(['ok' => true, 'message' => 'Email auto-follow-up is disabled.', 'processed' => 0, 'sent' => 0]);
}

if (!elite_smtp_is_configured()) {
    email_followup_json(['ok' => false, 'message' => 'SMTP is not configured.'], 503);
}

if (!elite_openai_is_configured()) {
    email_followup_json(['ok' => false, 'message' => 'OpenAI is not configured.'], 503);
}

$limit = max(1, min(25, (int)($_GET['limit'] ?? $_POST['limit'] ?? 10)));

try {
    $rows = db_all(
        "SELECT *
         FROM leads
         WHERE email IS NOT NULL
           AND email <> ''
           AND status IN ('new_lead', 'attempted_contact', 'contacted')
           AND (
                next_follow_up_at IS NULL
                OR next_follow_up_at = ''
                OR next_follow_up_at <= NOW()
                OR follow_up_status = 'needs_follow_up'
           )
         ORDER BY COALESCE(next_follow_up_at, created_at) ASC, id ASC
         LIMIT {$limit}"
    );
} catch (Throwable $e) {
    esm_log('lead_email', 'Email follow-up cron query failed.', ['message' => $e->getMessage()]);
    email_followup_json(['ok' => false, 'message' => 'Could not load due leads.'], 500);
}

$processed = 0;
$sent = 0;
$skipped = [];

foreach ($rows as $lead) {
    $processed++;
    $leadId = (int)($lead['id'] ?? 0);
    if ($leadId <= 0) {
        continue;
    }

    $draft = lead_ai_generate_email($lead, 'Write the next concise email follow-up. If this lead needs human review or should not be emailed, set should_send false.', 'auto_followup_email');
    if (empty($draft['ok'])) {
        $skipped[] = ['lead_id' => $leadId, 'reason' => $draft['message'] ?? 'AI draft failed'];
        continue;
    }

    $data = $draft['data'];
    lead_comm_insert_activity($leadId, 'ai_email_suggestion', 'AI generated automatic email follow-up suggestion: ' . mb_substr((string)($data['subject'] ?? ''), 0, 180), [
        'classification' => $data['classification'] ?? '',
        'confidence' => $data['confidence'] ?? 0,
        'should_send' => $data['should_send'] ?? false,
        'needs_human_review' => $data['needs_human_review'] ?? true,
        'note' => $data['note'] ?? '',
    ], 'OpenAI');

    $canSend = (bool)($data['should_send'] ?? false)
        && !(bool)($data['needs_human_review'] ?? true)
        && (float)($data['confidence'] ?? 0) >= (float)ELITE_AI_MIN_CONFIDENCE
        && trim((string)($data['subject'] ?? '')) !== ''
        && trim((string)($data['body'] ?? '')) !== '';

    if (!$canSend) {
        $skipped[] = ['lead_id' => $leadId, 'reason' => 'AI marked for review or low confidence'];
        continue;
    }

    $result = lead_email_send($leadId, (string)$data['subject'], (string)$data['body'], 'OpenAI');
    if (empty($result['ok'])) {
        $skipped[] = ['lead_id' => $leadId, 'reason' => $result['message'] ?? 'Email send failed'];
        continue;
    }

    $sent++;

    $nextFollowUp = trim((string)($data['next_follow_up_at'] ?? ''));
    $nextSql = null;
    if ($nextFollowUp !== '') {
        $timestamp = strtotime($nextFollowUp);
        if ($timestamp !== false) {
            $nextSql = date('Y-m-d H:i:s', $timestamp);
        }
    }
    if ($nextSql === null) {
        $nextSql = date('Y-m-d H:i:s', strtotime('+2 days'));
    }

    try {
        db_execute(
            "UPDATE leads
             SET follow_up_status = 'ok', next_follow_up_at = :next_follow_up_at, updated_at = :updated_at
             WHERE id = :id
             LIMIT 1",
            [
                'next_follow_up_at' => $nextSql,
                'updated_at' => now(),
                'id' => $leadId,
            ]
        );
    } catch (Throwable $e) {
        esm_log('lead_email', 'Could not update next follow-up after email.', [
            'lead_id' => $leadId,
            'message' => $e->getMessage(),
        ]);
    }
}

email_followup_json([
    'ok' => true,
    'message' => 'Email follow-up run complete.',
    'processed' => $processed,
    'sent' => $sent,
    'skipped' => $skipped,
]);
