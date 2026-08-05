<?php
declare(strict_types=1);

/**
 * Native Meta Lead Ads webhook receiver.
 *
 * Supports Meta's GET verification handshake and queues POST leadgen events
 * without touching the database on the public callback request.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/meta/meta_config.php';
require_once dirname(__DIR__) . '/meta/meta_queue.php';
require_once dirname(__DIR__) . '/meta/meta_webhook_security.php';

function meta_webhook_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function meta_webhook_input(string $key): string
{
    $underscore = str_replace('.', '_', $key);
    return trim((string) ($_GET[$key] ?? $_GET[$underscore] ?? $_POST[$key] ?? $_POST[$underscore] ?? ''));
}

function meta_webhook_signature_valid(string $rawBody): bool
{
    $appSecret = meta_cfg_app_secret();
    $signature = trim((string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? $_SERVER['X_HUB_SIGNATURE_256'] ?? ''));

    return meta_webhook_signature_matches($rawBody, $signature, $appSecret);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $mode = meta_webhook_input('hub.mode');
    $token = meta_webhook_input('hub.verify_token');
    $challenge = meta_webhook_input('hub.challenge');
    $expected = meta_cfg_verify_token();

    if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo $challenge;
        exit;
    }

    meta_webhook_json(['ok' => false, 'message' => 'Meta webhook verification failed.'], 403);
}

if ($method !== 'POST') {
    meta_webhook_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$rawBody = (string) file_get_contents('php://input');
if (trim($rawBody) === '') {
    meta_webhook_json(['ok' => false, 'message' => 'Empty Meta webhook payload.'], 400);
}

if (meta_cfg_app_secret() !== '' && !meta_webhook_signature_valid($rawBody)) {
    meta_webhook_json(['ok' => false, 'message' => 'Invalid Meta webhook signature.'], 403);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    meta_webhook_json(['ok' => false, 'message' => 'Invalid Meta webhook JSON.'], 400);
}

if (!meta_webhook_payload_valid($payload)) {
    meta_webhook_json(['ok' => false, 'message' => 'Invalid Meta lead payload.'], 422);
}

$queued = meta_queue_enqueue($payload);
if (empty($queued['ok'])) {
    meta_webhook_json(['ok' => false, 'message' => 'Could not queue Meta webhook payload.'], 500);
}

$response = [
    'ok' => true,
    'queued' => true,
    'duplicate' => !empty($queued['duplicate']),
    'event_id' => (string) ($queued['event_id'] ?? ''),
    'candidate_count' => (int) ($queued['record']['candidate_count'] ?? 0),
];

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('Connection: close');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} elseif (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();
} else {
    @ob_flush();
    @flush();
}

if (empty($queued['duplicate']) || (string) ($queued['record']['status'] ?? '') === 'pending') {
    try {
        if (!defined('META_PROCESSOR_LIBRARY_ONLY')) {
            define('META_PROCESSOR_LIBRARY_ONLY', true);
        }
        require_once __DIR__ . '/meta_webhook_process.php';
        $immediate = meta_processor_run(1, (string) ($queued['event_id'] ?? ''), false);
        esm_log('meta_webhook', 'Meta webhook immediate processing completed.', [
            'event_id' => (string) ($queued['event_id'] ?? ''),
            'claimed' => (int) ($immediate['claimed'] ?? 0),
            'status' => (string) ($immediate['results'][0]['status'] ?? 'not_claimed'),
        ]);
    } catch (Throwable $e) {
        esm_log('meta_webhook', 'Meta webhook immediate processing failed; event remains recoverable.', [
            'event_id' => (string) ($queued['event_id'] ?? ''),
            'message' => $e->getMessage(),
        ]);
    }
}
