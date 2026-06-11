<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Public Meta Lead Ads webhook endpoint.
 *
 * Thin boundary only:
 * - GET verification
 * - POST signature validation
 * - safe logging
 * - delegate lead handling to app/meta service layer
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/meta/meta_config.php';
require_once __DIR__ . '/app/meta/meta_queue.php';

function meta_webhook_collect_buffer(): string
{
    $buffer = '';

    while (ob_get_level() > 0) {
        $chunk = (string) ob_get_contents();
        if ($chunk !== '') {
            $buffer .= $chunk;
        }

        ob_end_clean();
    }

    return trim($buffer);
}

function meta_webhook_json_response(int $statusCode, array $payload): void
{
    $buffer = meta_webhook_collect_buffer();
    if ($buffer !== '') {
        esm_log('meta_webhooks', 'Unexpected buffered output captured at webhook boundary.', [
            'status_code' => $statusCode,
            'buffer_length' => strlen($buffer),
        ]);
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function meta_webhook_plain_response(int $statusCode, string $body): void
{
    $buffer = meta_webhook_collect_buffer();
    if ($buffer !== '') {
        esm_log('meta_webhooks', 'Unexpected buffered output captured at verification boundary.', [
            'status_code' => $statusCode,
            'buffer_length' => strlen($buffer),
        ]);
    }

    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $body;
    exit;
}

function meta_webhook_query_value(array $query, string $key): string
{
    $value = $query[$key] ?? '';
    return is_scalar($value) ? trim((string) $value) : '';
}

function meta_webhook_verify_token(): string
{
    return trim((string) meta_cfg_verify_token());
}

function meta_webhook_has_verify_token(): bool
{
    return meta_webhook_verify_token() !== '';
}

function meta_webhook_decode_payload(string $rawBody): array
{
    if (trim($rawBody) === '') {
        return [];
    }

    $decoded = json_decode($rawBody, true);
    return is_array($decoded) ? $decoded : [];
}

function meta_webhook_raw_body(): string
{
    $raw = file_get_contents('php://input');
    return is_string($raw) ? $raw : '';
}

function meta_webhook_request_signature(): string
{
    $raw = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? $_SERVER['X_HUB_SIGNATURE_256'] ?? '';
    return is_string($raw) ? trim($raw) : '';
}

function meta_webhook_candidate_summary(array $payload): array
{
    return meta_queue_extract_candidates($payload);
}

if (request_method() === 'GET' && (($_GET['hub_mode'] ?? '') === 'subscribe' || (string) ($_GET['hub.mode'] ?? '') === 'subscribe')) {
    $verifyToken = meta_webhook_query_value($_GET, 'hub_verify_token');
    $challenge = meta_webhook_query_value($_GET, 'hub_challenge');

    if (!meta_webhook_has_verify_token()) {
        esm_log('meta_webhooks', 'Meta verification request rejected because META_VERIFY_TOKEN is not configured.', []);
        meta_webhook_plain_response(503, '');
    }

    if ($challenge === '') {
        esm_log('meta_webhooks', 'Meta verification request missing challenge.', [
            'mode' => meta_webhook_query_value($_GET, 'hub_mode'),
        ]);
        meta_webhook_plain_response(400, '');
    }

    if (!hash_equals(meta_webhook_verify_token(), $verifyToken)) {
        esm_log('meta_webhooks', 'Meta verification failed.', [
            'mode' => meta_webhook_query_value($_GET, 'hub_mode'),
        ]);
        meta_webhook_plain_response(403, '');
    }

    esm_log('meta_webhooks', 'Meta verification succeeded.', [
        'mode' => meta_webhook_query_value($_GET, 'hub_mode'),
    ]);
    meta_webhook_plain_response(200, $challenge);
}

if (request_method() !== 'POST') {
    meta_webhook_json_response(405, [
        'ok' => false,
        'message' => 'Method not allowed.',
    ]);
}

$rawBody = meta_webhook_raw_body();
$payload = meta_webhook_decode_payload($rawBody);
$signature = meta_webhook_request_signature();
$appSecret = meta_cfg_app_secret();
$candidateSummary = meta_webhook_candidate_summary($payload);

esm_log('meta_webhooks', 'Meta webhook received.', [
    'content_length' => strlen($rawBody),
    'candidate_count' => count($candidateSummary),
    'candidates' => $candidateSummary,
]);

if ($appSecret !== '') {
    $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
    if (!hash_equals($expected, $signature)) {
        esm_log('meta_webhooks', 'Meta webhook signature validation failed.', [
            'candidate_count' => count($candidateSummary),
            'candidates' => $candidateSummary,
            'signature_present' => $signature !== '',
        ]);

        meta_webhook_json_response(401, [
            'ok' => false,
            'message' => 'Unauthorized.',
        ]);
    }
} elseif (!meta_queue_allows_unsigned_loopback()) {
    esm_log('meta_webhooks', 'Meta webhook rejected because META_APP_SECRET is not configured and request is not local cli-server loopback.', [
        'app_env' => APP_ENV,
        'candidate_count' => count($candidateSummary),
        'candidates' => $candidateSummary,
    ]);

    meta_webhook_json_response(503, [
        'ok' => false,
        'message' => 'Webhook is not configured.',
    ]);
}

$queueResult = meta_queue_enqueue($payload);
if (empty($queueResult['ok'])) {
    esm_log('meta_webhooks', 'Meta webhook queue enqueue failed.', [
        'candidate_count' => count($candidateSummary),
        'candidates' => $candidateSummary,
        'message' => 'Could not persist queued event.',
    ]);

    meta_webhook_json_response(503, [
        'ok' => false,
        'message' => 'Webhook queue is unavailable.',
    ]);
}

esm_log('meta_webhooks', 'Meta webhook queued.', [
    'event_id' => (string) ($queueResult['event_id'] ?? ''),
    'candidate_count' => count($candidateSummary),
    'candidates' => $candidateSummary,
]);

meta_webhook_json_response(200, [
    'ok' => true,
    'message' => 'Meta webhook accepted.',
    'event_id' => (string) ($queueResult['event_id'] ?? ''),
]);
