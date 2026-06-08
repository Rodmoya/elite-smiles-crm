<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/api/manychat_webhook.php
 *
 * Public ManyChat webhook endpoint:
 * - POST only
 * - shared secret protected
 * - accepts JSON or form payload
 * - logs raw payload into manychat_intake_logs
 * - checks duplicates against leads table
 * - creates new lead when no duplicate exists
 * - keeps current CRM flow untouched
 */

ob_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';

function manychat_collect_output_buffer(): string
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

function manychat_json_response(int $statusCode, array $payload): void
{
    $buffer = manychat_collect_output_buffer();

    if ($buffer !== '') {
        $payload['buffer_output'] = $buffer;
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function manychat_request_headers_as_json(): string
{
    $headers = [];

    foreach ($_SERVER as $key => $value) {
        if (strncmp($key, 'HTTP_', 5) === 0) {
            $headerName = str_replace('_', '-', substr($key, 5));
            $headers[$headerName] = (string) $value;
        }
    }

    if (isset($_SERVER['CONTENT_TYPE'])) {
        $headers['CONTENT-TYPE'] = (string) $_SERVER['CONTENT_TYPE'];
    }

    if (isset($_SERVER['CONTENT_LENGTH'])) {
        $headers['CONTENT-LENGTH'] = (string) $_SERVER['CONTENT_LENGTH'];
    }

    return json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function manychat_raw_input(): string
{
    $raw = file_get_contents('php://input');
    return is_string($raw) ? $raw : '';
}

function manychat_detect_payload_format(string $rawInput): string
{
    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));

    if ($contentType !== '' && str_contains($contentType, 'application/json')) {
        return 'json';
    }

    if ($contentType !== '' && (
        str_contains($contentType, 'application/x-www-form-urlencoded') ||
        str_contains($contentType, 'multipart/form-data')
    )) {
        return 'form';
    }

    if ($rawInput !== '') {
        $decoded = json_decode($rawInput, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return 'json';
        }
    }

    if (!empty($_POST)) {
        return 'form';
    }

    return 'unknown';
}

function manychat_parse_payload(string $payloadFormat, string $rawInput): array
{
    if ($payloadFormat === 'json') {
        $decoded = json_decode($rawInput, true);
        return is_array($decoded) ? $decoded : [];
    }

    if ($payloadFormat === 'form') {
        $payload = [];
        foreach ($_POST as $key => $value) {
            $payload[$key] = is_string($value) ? trim($value) : $value;
        }
        return $payload;
    }

    return [];
}

function manychat_get_request_token(array $payload): string
{
    $candidates = [
        $_SERVER['HTTP_X_MANYCHAT_SECRET'] ?? null,
        $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? null,
        $_SERVER['HTTP_X_API_KEY'] ?? null,
        $payload['secret'] ?? null,
        $payload['token'] ?? null,
        $payload['webhook_secret'] ?? null,
        $payload['api_key'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate)) {
            $candidate = trim($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    return '';
}

function manychat_payload_value(array $payload, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $payload)) {
            continue;
        }

        $value = $payload[$key];

        if (is_scalar($value) || $value === null) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }
    }

    return $default;
}

function manychat_normalize_phone(?string $phone): string
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    $digits = is_string($digits) ? $digits : '';

    if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
        $digits = substr($digits, 1);
    }

    return $digits;
}

function manychat_normalize_email(?string $email): string
{
    return strtolower(trim((string) $email));
}

function manychat_normalize_username(?string $username): string
{
    $username = trim((string) $username);
    $username = ltrim($username, '@');
    return strtolower($username);
}

function manychat_normalize_payload(array $payload): array
{
    $source = manychat_payload_value($payload, ['source'], MANYCHAT_DEFAULT_SOURCE);
    $sourceMedium = manychat_payload_value($payload, ['source_medium'], MANYCHAT_DEFAULT_SOURCE_MEDIUM);
    $sourceType = manychat_payload_value($payload, ['source_type', 'traffic_type', 'lead_type'], 'organic_dm');

    return [
        'full_name' => manychat_payload_value($payload, ['full_name', 'name', 'contact_name']),
        'phone' => manychat_normalize_phone(manychat_payload_value($payload, ['phone', 'phone_number', 'mobile_phone'])),
        'email' => manychat_normalize_email(manychat_payload_value($payload, ['email', 'email_address'])),
        'procedure_interest' => manychat_payload_value($payload, ['service_interest', 'procedure_interest', 'treatment_interest']),
        'message_text' => manychat_payload_value($payload, ['message_text', 'message', 'context', 'reply_text']),
        'instagram_username' => manychat_normalize_username(manychat_payload_value($payload, ['instagram_username', 'ig_username', 'username', 'instagram_handle'])),
        'trigger_keyword' => manychat_payload_value($payload, ['trigger_keyword', 'keyword']),
        'external_lead_id' => manychat_payload_value($payload, ['external_lead_id', 'lead_id', 'manychat_lead_id', 'subscriber_id']),
        'source' => $source !== '' ? $source : MANYCHAT_DEFAULT_SOURCE,
        'source_medium' => $sourceMedium !== '' ? $sourceMedium : MANYCHAT_DEFAULT_SOURCE_MEDIUM,
        'source_type' => $sourceType,
        'source_campaign' => manychat_payload_value($payload, ['source_campaign', 'campaign', 'campaign_name']),
        'source_ad_set' => manychat_payload_value($payload, ['source_ad_set', 'ad_set', 'adset_name']),
        'source_ad_name' => manychat_payload_value($payload, ['source_ad_name', 'ad_name']),
        'source_post_id' => manychat_payload_value($payload, ['source_post_id', 'post_id']),
        'source_post_label' => manychat_payload_value($payload, ['source_post_label', 'post_label', 'post_title']),
        'assigned_to' => MANYCHAT_DEFAULT_ASSIGNED_TO,
        'status' => MANYCHAT_DEFAULT_STATUS,
    ];
}

function manychat_build_import_note(array $normalized): string
{
    $lines = [];
    $lines[] = '--- Imported from ManyChat on ' . now('Y-m-d H:i:s') . ' ---';

    if ($normalized['instagram_username'] !== '') {
        $lines[] = 'Instagram Username: ' . $normalized['instagram_username'];
    }

    if ($normalized['trigger_keyword'] !== '') {
        $lines[] = 'Trigger Keyword: ' . $normalized['trigger_keyword'];
    }

    if ($normalized['source_type'] !== '') {
        $lines[] = 'Lead Type: ' . $normalized['source_type'];
    }

    if ($normalized['source_campaign'] !== '') {
        $lines[] = 'Campaign: ' . $normalized['source_campaign'];
    }

    if ($normalized['source_ad_set'] !== '') {
        $lines[] = 'Ad Set: ' . $normalized['source_ad_set'];
    }

    if ($normalized['source_ad_name'] !== '') {
        $lines[] = 'Ad Name: ' . $normalized['source_ad_name'];
    }

    if ($normalized['source_post_id'] !== '') {
        $lines[] = 'Post ID: ' . $normalized['source_post_id'];
    }

    if ($normalized['source_post_label'] !== '') {
        $lines[] = 'Post Reference: ' . $normalized['source_post_label'];
    }

    if ($normalized['procedure_interest'] !== '') {
        $lines[] = 'Service Interest: ' . $normalized['procedure_interest'];
    }

    if ($normalized['message_text'] !== '') {
        $lines[] = 'Message Text: ' . $normalized['message_text'];
    }

    return implode("\n", $lines);
}

function manychat_find_existing_lead(array $normalized): ?array
{
    if ($normalized['external_lead_id'] !== '') {
        $row = db_one(
            "SELECT id, full_name, phone, email, instagram_username FROM leads WHERE external_lead_id = :external_lead_id LIMIT 1",
            ['external_lead_id' => $normalized['external_lead_id']]
        );
        if ($row) {
            $row['match_type'] = 'external_lead_id';
            return $row;
        }
    }

    if ($normalized['phone'] !== '') {
        $row = db_one(
            "SELECT id, full_name, phone, email, instagram_username
             FROM leads
             WHERE phone IS NOT NULL
               AND REPLACE(REPLACE(REPLACE(REPLACE(phone, '-', ''), '(', ''), ')', ''), ' ', '') = :phone
             LIMIT 1",
            ['phone' => $normalized['phone']]
        );
        if ($row) {
            $row['match_type'] = 'phone';
            return $row;
        }
    }

    if ($normalized['email'] !== '') {
        $row = db_one(
            "SELECT id, full_name, phone, email, instagram_username
             FROM leads
             WHERE email IS NOT NULL AND LOWER(email) = :email
             LIMIT 1",
            ['email' => $normalized['email']]
        );
        if ($row) {
            $row['match_type'] = 'email';
            return $row;
        }
    }

    if ($normalized['instagram_username'] !== '') {
        $row = db_one(
            "SELECT id, full_name, phone, email, instagram_username
             FROM leads
             WHERE instagram_username IS NOT NULL AND LOWER(instagram_username) = :instagram_username
             LIMIT 1",
            ['instagram_username' => $normalized['instagram_username']]
        );
        if ($row) {
            $row['match_type'] = 'instagram_username';
            return $row;
        }
    }

    return null;
}

function manychat_insert_lead(array $normalized, string $importNote): int
{
    $fullName = trim($normalized['full_name']) !== '' ? trim($normalized['full_name']) : 'Instagram Lead';

    $sql = "
        INSERT INTO leads (
            full_name,
            phone,
            email,
            procedure_interest,
            source,
            source_medium,
            source_type,
            campaign,
            source_campaign,
            source_ad_set,
            source_ad_name,
            source_post_id,
            source_post_label,
            external_lead_id,
            instagram_username,
            trigger_keyword,
            source_context,
            status,
            assigned_to,
            financing_needed,
            financing_option,
            notes,
            created_at,
            updated_at
        ) VALUES (
            :full_name,
            :phone,
            :email,
            :procedure_interest,
            :source,
            :source_medium,
            :source_type,
            :campaign,
            :source_campaign,
            :source_ad_set,
            :source_ad_name,
            :source_post_id,
            :source_post_label,
            :external_lead_id,
            :instagram_username,
            :trigger_keyword,
            :source_context,
            :status,
            :assigned_to,
            :financing_needed,
            :financing_option,
            :notes,
            :created_at,
            :updated_at
        )
    ";

    return (int) db_insert($sql, [
        'full_name' => $fullName,
        'phone' => $normalized['phone'] !== '' ? $normalized['phone'] : null,
        'email' => $normalized['email'] !== '' ? $normalized['email'] : null,
        'procedure_interest' => $normalized['procedure_interest'] !== '' ? $normalized['procedure_interest'] : null,
        'source' => $normalized['source'] !== '' ? $normalized['source'] : MANYCHAT_DEFAULT_SOURCE,
        'source_medium' => $normalized['source_medium'] !== '' ? $normalized['source_medium'] : MANYCHAT_DEFAULT_SOURCE_MEDIUM,
        'source_type' => $normalized['source_type'] !== '' ? $normalized['source_type'] : null,
        'campaign' => $normalized['source_campaign'] !== '' ? $normalized['source_campaign'] : null,
        'source_campaign' => $normalized['source_campaign'] !== '' ? $normalized['source_campaign'] : null,
        'source_ad_set' => $normalized['source_ad_set'] !== '' ? $normalized['source_ad_set'] : null,
        'source_ad_name' => $normalized['source_ad_name'] !== '' ? $normalized['source_ad_name'] : null,
        'source_post_id' => $normalized['source_post_id'] !== '' ? $normalized['source_post_id'] : null,
        'source_post_label' => $normalized['source_post_label'] !== '' ? $normalized['source_post_label'] : null,
        'external_lead_id' => $normalized['external_lead_id'] !== '' ? $normalized['external_lead_id'] : null,
        'instagram_username' => $normalized['instagram_username'] !== '' ? $normalized['instagram_username'] : null,
        'trigger_keyword' => $normalized['trigger_keyword'] !== '' ? $normalized['trigger_keyword'] : null,
        'source_context' => $normalized['message_text'] !== '' ? $normalized['message_text'] : null,
        'status' => $normalized['status'] !== '' ? $normalized['status'] : MANYCHAT_DEFAULT_STATUS,
        'assigned_to' => $normalized['assigned_to'] !== '' ? $normalized['assigned_to'] : MANYCHAT_DEFAULT_ASSIGNED_TO,
        'financing_needed' => 'unsure',
        'financing_option' => 'none',
        'notes' => $importNote,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function manychat_log_intake(array $row): int
{
    $sql = "
        INSERT INTO manychat_intake_logs (
            lead_id,
            external_lead_id,
            source,
            source_medium,
            source_type,
            instagram_username,
            trigger_keyword,
            source_campaign,
            source_ad_set,
            source_ad_name,
            source_post_id,
            source_post_label,
            request_token,
            payload_format,
            request_headers,
            raw_payload,
            normalized_payload,
            import_note,
            duplicate_reason,
            processing_status,
            error_message
        ) VALUES (
            :lead_id,
            :external_lead_id,
            :source,
            :source_medium,
            :source_type,
            :instagram_username,
            :trigger_keyword,
            :source_campaign,
            :source_ad_set,
            :source_ad_name,
            :source_post_id,
            :source_post_label,
            :request_token,
            :payload_format,
            :request_headers,
            :raw_payload,
            :normalized_payload,
            :import_note,
            :duplicate_reason,
            :processing_status,
            :error_message
        )
    ";

    return (int) db_insert($sql, [
        'lead_id' => $row['lead_id'],
        'external_lead_id' => $row['external_lead_id'],
        'source' => $row['source'],
        'source_medium' => $row['source_medium'],
        'source_type' => $row['source_type'],
        'instagram_username' => $row['instagram_username'],
        'trigger_keyword' => $row['trigger_keyword'],
        'source_campaign' => $row['source_campaign'],
        'source_ad_set' => $row['source_ad_set'],
        'source_ad_name' => $row['source_ad_name'],
        'source_post_id' => $row['source_post_id'],
        'source_post_label' => $row['source_post_label'],
        'request_token' => $row['request_token'],
        'payload_format' => $row['payload_format'],
        'request_headers' => $row['request_headers'],
        'raw_payload' => $row['raw_payload'],
        'normalized_payload' => $row['normalized_payload'],
        'import_note' => $row['import_note'],
        'duplicate_reason' => $row['duplicate_reason'],
        'processing_status' => $row['processing_status'],
        'error_message' => $row['error_message'],
    ]);
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

    manychat_json_response(500, [
        'ok' => false,
        'message' => 'Fatal webhook error.',
        'debug' => [
            'type' => $error['type'] ?? null,
            'message' => $error['message'] ?? '',
            'file' => $error['file'] ?? '',
            'line' => $error['line'] ?? 0,
        ],
    ]);
});

if (request_method() !== 'POST') {
    manychat_json_response(405, [
        'ok' => false,
        'message' => 'Invalid request method.',
    ]);
}

$rawInput = manychat_raw_input();
$payloadFormat = manychat_detect_payload_format($rawInput);
$payload = manychat_parse_payload($payloadFormat, $rawInput);
$requestToken = manychat_get_request_token($payload);

if ($requestToken === '' || !hash_equals(MANYCHAT_WEBHOOK_SECRET, $requestToken)) {
    esm_log('manychat_webhook', 'Rejected webhook request due to invalid secret.', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'payload_format' => $payloadFormat,
    ]);

    manychat_json_response(401, [
        'ok' => false,
        'message' => 'Unauthorized webhook request.',
    ]);
}

$normalized = manychat_normalize_payload($payload);
$importNote = manychat_build_import_note($normalized);
$existingLead = null;
$duplicateReason = null;
$processingStatus = 'ready';
$leadId = null;

try {
    $existingLead = manychat_find_existing_lead($normalized);

    if ($existingLead) {
        $leadId = (int) ($existingLead['id'] ?? 0);
        $duplicateReason = (string) ($existingLead['match_type'] ?? 'matched_existing_lead');
        $processingStatus = 'duplicate_found';
    } else {
        $leadId = manychat_insert_lead($normalized, $importNote);
        $processingStatus = 'created';
    }

    $logId = manychat_log_intake([
        'lead_id' => $leadId,
        'external_lead_id' => $normalized['external_lead_id'] !== '' ? $normalized['external_lead_id'] : null,
        'source' => $normalized['source'] !== '' ? $normalized['source'] : null,
        'source_medium' => $normalized['source_medium'] !== '' ? $normalized['source_medium'] : null,
        'source_type' => $normalized['source_type'] !== '' ? $normalized['source_type'] : null,
        'instagram_username' => $normalized['instagram_username'] !== '' ? $normalized['instagram_username'] : null,
        'trigger_keyword' => $normalized['trigger_keyword'] !== '' ? $normalized['trigger_keyword'] : null,
        'source_campaign' => $normalized['source_campaign'] !== '' ? $normalized['source_campaign'] : null,
        'source_ad_set' => $normalized['source_ad_set'] !== '' ? $normalized['source_ad_set'] : null,
        'source_ad_name' => $normalized['source_ad_name'] !== '' ? $normalized['source_ad_name'] : null,
        'source_post_id' => $normalized['source_post_id'] !== '' ? $normalized['source_post_id'] : null,
        'source_post_label' => $normalized['source_post_label'] !== '' ? $normalized['source_post_label'] : null,
        'request_token' => $requestToken,
        'payload_format' => $payloadFormat,
        'request_headers' => manychat_request_headers_as_json(),
        'raw_payload' => $rawInput !== '' ? $rawInput : json_encode($_POST, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'normalized_payload' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'import_note' => $importNote,
        'duplicate_reason' => $duplicateReason,
        'processing_status' => $processingStatus,
        'error_message' => null,
    ]);

    esm_log('manychat_webhook', 'Webhook processed.', [
        'log_id' => $logId,
        'lead_id' => $leadId,
        'external_lead_id' => $normalized['external_lead_id'],
        'instagram_username' => $normalized['instagram_username'],
        'source_type' => $normalized['source_type'],
        'processing_status' => $processingStatus,
        'duplicate_reason' => $duplicateReason,
    ]);

    if ($existingLead) {
        manychat_json_response(200, [
            'ok' => true,
            'message' => 'Duplicate lead matched. New lead was not created.',
            'log_id' => $logId,
            'mode' => 'create_or_match',
            'duplicate_found' => true,
            'duplicate_reason' => $duplicateReason,
            'matched_lead_id' => $leadId,
            'created_lead_id' => null,
        ]);
    }

    manychat_json_response(200, [
        'ok' => true,
        'message' => 'Lead created successfully from ManyChat webhook.',
        'log_id' => $logId,
        'mode' => 'create_or_match',
        'duplicate_found' => false,
        'duplicate_reason' => null,
        'matched_lead_id' => null,
        'created_lead_id' => $leadId,
    ]);
} catch (Throwable $e) {
    esm_log('manychat_webhook', 'Webhook processing failed.', [
        'message' => $e->getMessage(),
    ]);

    manychat_json_response(500, [
        'ok' => false,
        'message' => 'Failed to process webhook payload.',
    ]);
}