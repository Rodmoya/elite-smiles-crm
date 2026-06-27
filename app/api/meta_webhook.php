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

    if ($appSecret === '' || $signature === '') {
        return false;
    }

    $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
    return hash_equals($expected, $signature);
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

$queued = meta_queue_enqueue($payload);
if (empty($queued['ok'])) {
    meta_webhook_json(['ok' => false, 'message' => 'Could not queue Meta webhook payload.'], 500);
}

meta_webhook_json([
    'ok' => true,
    'queued' => true,
    'event_id' => (string) ($queued['event_id'] ?? ''),
    'candidate_count' => (int) ($queued['record']['candidate_count'] ?? 0),
]);
