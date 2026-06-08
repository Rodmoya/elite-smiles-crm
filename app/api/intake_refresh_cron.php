<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Cron-safe intake refresh runner.
 *
 * Safety net for new lead intake:
 * - checks very recent unchecked leads
 * - sends backup AT&T email-to-text alerts
 * - flags duplicates
 * - normalizes empty source/stage values
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/mailer.php';
require_once dirname(__DIR__) . '/leads/lead_service.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_email.php';

header('Content-Type: application/json; charset=utf-8');

function intake_refresh_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function intake_refresh_secret(): string
{
    $header = trim((string)($_SERVER['HTTP_X_ELITE_CRON_SECRET'] ?? ''));
    if ($header !== '') {
        return $header;
    }

    return trim((string)($_GET['secret'] ?? $_POST['secret'] ?? ''));
}

function intake_refresh_ensure_schema(): void
{
    db_query("
        CREATE TABLE IF NOT EXISTS lead_intake_refreshes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id INT UNSIGNED NOT NULL,
            alert_sent_at DATETIME NULL,
            duplicate_lead_id INT UNSIGNED NULL,
            duplicate_match_type VARCHAR(50) NOT NULL DEFAULT '',
            normalized_fields TEXT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'checked',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_lead (lead_id),
            KEY idx_created (created_at),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function intake_refresh_existing(int $leadId): ?array
{
    intake_refresh_ensure_schema();

    return db_one(
        'SELECT * FROM lead_intake_refreshes WHERE lead_id = :lead_id LIMIT 1',
        ['lead_id' => $leadId]
    );
}

function intake_refresh_save(array $row): void
{
    intake_refresh_ensure_schema();

    db_query(
        "INSERT INTO lead_intake_refreshes (
            lead_id, alert_sent_at, duplicate_lead_id, duplicate_match_type,
            normalized_fields, status, created_at, updated_at
         ) VALUES (
            :lead_id, :alert_sent_at, :duplicate_lead_id, :duplicate_match_type,
            :normalized_fields, :status, :created_at, :updated_at
         )
         ON DUPLICATE KEY UPDATE
            alert_sent_at = COALESCE(alert_sent_at, VALUES(alert_sent_at)),
            duplicate_lead_id = VALUES(duplicate_lead_id),
            duplicate_match_type = VALUES(duplicate_match_type),
            normalized_fields = VALUES(normalized_fields),
            status = VALUES(status),
            updated_at = VALUES(updated_at)",
        [
            'lead_id' => (int)$row['lead_id'],
            'alert_sent_at' => $row['alert_sent_at'] ?? null,
            'duplicate_lead_id' => $row['duplicate_lead_id'] ?? null,
            'duplicate_match_type' => (string)($row['duplicate_match_type'] ?? ''),
            'normalized_fields' => $row['normalized_fields'] ?? null,
            'status' => (string)($row['status'] ?? 'checked'),
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );
}

function intake_refresh_normalize_lead(array $lead): array
{
    $leadId = (int)($lead['id'] ?? 0);
    if ($leadId <= 0) {
        return [];
    }

    $updates = [];
    if (function_exists('leads_has_column') && leads_has_column('status') && trim((string)($lead['status'] ?? '')) === '') {
        $updates['status'] = 'new_lead';
    }
    if (function_exists('leads_has_column') && leads_has_column('source') && trim((string)($lead['source'] ?? '')) === '') {
        $updates['source'] = 'website';
    }
    if (function_exists('leads_has_column') && leads_has_column('source_medium') && trim((string)($lead['source_medium'] ?? '')) === '') {
        $updates['source_medium'] = 'website';
    }
    if (function_exists('leads_has_column') && leads_has_column('source_type') && trim((string)($lead['source_type'] ?? '')) === '') {
        $updates['source_type'] = 'website_form';
    }
    if (function_exists('leads_has_column') && leads_has_column('updated_at')) {
        $updates['updated_at'] = now();
    }

    if (!$updates) {
        return [];
    }

    $setParts = [];
    $params = ['id' => $leadId];
    foreach ($updates as $field => $value) {
        $placeholder = 'p_' . $field;
        $setParts[] = '`' . $field . '` = :' . $placeholder;
        $params[$placeholder] = $value;
    }

    db_query('UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1', $params);

    return array_keys($updates);
}

$configuredSecret = trim((string)(defined('ELITE_EMAIL_INBOUND_CRON_SECRET') ? ELITE_EMAIL_INBOUND_CRON_SECRET : ''));
if ($configuredSecret === '' || !hash_equals($configuredSecret, intake_refresh_secret())) {
    intake_refresh_json(['ok' => false, 'message' => 'Unauthorized.'], 401);
}

try {
    intake_refresh_ensure_schema();
} catch (Throwable $e) {
    esm_log('lead_intake', 'Could not ensure intake refresh schema.', ['error' => $e->getMessage()]);
    intake_refresh_json(['ok' => false, 'message' => 'Could not initialize intake refresh schema.'], 500);
}

$limit = max(1, min(50, (int)($_GET['limit'] ?? $_POST['limit'] ?? 25)));
$lookbackHours = max(1, min(24, (int)($_GET['lookback_hours'] ?? $_POST['lookback_hours'] ?? 6)));

try {
    $leads = db_all(
        "SELECT l.*
         FROM leads l
         LEFT JOIN lead_intake_refreshes r ON r.lead_id = l.id
         WHERE r.id IS NULL
           AND l.created_at >= DATE_SUB(NOW(), INTERVAL {$lookbackHours} HOUR)
         ORDER BY l.created_at ASC, l.id ASC
         LIMIT {$limit}"
    );
} catch (Throwable $e) {
    esm_log('lead_intake', 'Could not load leads for intake refresh.', ['error' => $e->getMessage()]);
    intake_refresh_json(['ok' => false, 'message' => 'Could not load recent leads.'], 500);
}

$processed = 0;
$alertsSent = 0;
$duplicates = 0;
$results = [];

foreach ($leads as $lead) {
    $processed++;
    $leadId = (int)($lead['id'] ?? 0);
    if ($leadId <= 0 || intake_refresh_existing($leadId)) {
        continue;
    }

    $normalized = [];
    try {
        $normalized = intake_refresh_normalize_lead($lead);
    } catch (Throwable $e) {
        esm_log('lead_intake', 'Could not normalize intake lead.', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
    }

    $duplicate = null;
    try {
        $duplicate = lead_find_duplicate($lead, $leadId);
    } catch (Throwable $e) {
        esm_log('lead_intake', 'Duplicate check failed during intake refresh.', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
    }

    $alertSent = false;
    try {
        if (function_exists('elite_send_lead_email_to_text_alert')) {
            $alertLead = $lead;
            $alertLead['source'] = trim((string)($alertLead['source'] ?? '')) !== '' ? $alertLead['source'] : 'website';
            $alertLead['campaign'] = trim((string)($alertLead['campaign'] ?? '')) !== ''
                ? $alertLead['campaign']
                : (trim((string)($alertLead['landing_page'] ?? '')) !== '' ? $alertLead['landing_page'] : 'website');
            $alertSent = elite_send_lead_email_to_text_alert($alertLead, [
                'lead_id' => $leadId,
                'created_at' => (string)($lead['created_at'] ?? now()),
                'campaign' => (string)($alertLead['campaign'] ?? ''),
                'landing_page' => (string)($alertLead['landing_page'] ?? ''),
            ]);
        }
    } catch (Throwable $e) {
        esm_log('lead_intake', 'Backup new lead alert failed.', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
    }

    if ($alertSent) {
        $alertsSent++;
    }
    if ($duplicate) {
        $duplicates++;
    }

    $status = $duplicate ? 'duplicate_review' : 'checked';
    intake_refresh_save([
        'lead_id' => $leadId,
        'alert_sent_at' => $alertSent ? now() : null,
        'duplicate_lead_id' => $duplicate ? (int)($duplicate['id'] ?? 0) : null,
        'duplicate_match_type' => $duplicate ? (string)($duplicate['duplicate_match_type'] ?? '') : '',
        'normalized_fields' => $normalized ? implode(',', $normalized) : null,
        'status' => $status,
    ]);

    lead_comm_insert_activity($leadId, $duplicate ? 'intake_duplicate_review' : 'intake_refresh_checked', $duplicate
        ? 'Intake refresh found a possible duplicate lead #' . (int)($duplicate['id'] ?? 0) . ' by ' . (string)($duplicate['duplicate_match_type'] ?? 'contact information') . '.'
        : 'Intake refresh checked this new lead and sent a backup alert' . ($alertSent ? '.' : ' check.'),
        [
            'alert_sent' => $alertSent,
            'normalized_fields' => $normalized,
            'duplicate_lead_id' => $duplicate ? (int)($duplicate['id'] ?? 0) : null,
            'source' => 'intake_refresh_cron',
        ],
        'Intake Refresh'
    );

    $results[] = [
        'lead_id' => $leadId,
        'name' => (string)($lead['full_name'] ?? ''),
        'alert_sent' => $alertSent,
        'normalized_fields' => $normalized,
        'duplicate' => $duplicate ? [
            'lead_id' => (int)($duplicate['id'] ?? 0),
            'match_type' => (string)($duplicate['duplicate_match_type'] ?? ''),
        ] : null,
    ];
}

intake_refresh_json([
    'ok' => true,
    'message' => 'Intake refresh complete.',
    'processed' => $processed,
    'alerts_sent' => $alertsSent,
    'duplicates_found' => $duplicates,
    'lookback_hours' => $lookbackHours,
    'results' => $results,
]);
