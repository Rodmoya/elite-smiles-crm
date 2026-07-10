<?php
declare(strict_types=1);

define('ELITE_CODEX_API_V1', true);

require_once dirname(__DIR__, 3) . '/config/config.php';
require_once dirname(__DIR__, 3) . '/core/helpers.php';
require_once dirname(__DIR__, 3) . '/core/db.php';
require_once dirname(__DIR__, 3) . '/core/codex_api_security.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    codex_security_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$rawBody = (string)file_get_contents('php://input');
$decoded = $rawBody !== '' ? json_decode($rawBody, true) : null;
$body = is_array($decoded) ? $decoded : ($method === 'POST' ? $_POST : $_GET);
$body = is_array($body) ? $body : [];
$action = trim((string)($body['action'] ?? ($method === 'GET' ? ($_GET['action'] ?? 'health') : '')));
if ($action === '' || !preg_match('/^[a-z][a-z0-9_]{1,79}$/', $action)) {
    codex_security_json(['ok' => false, 'message' => 'A valid action is required.'], 400);
}
if ($method === 'GET' && !in_array($action, ['health', 'capabilities', 'stages'], true)) {
    codex_security_json(['ok' => false, 'message' => 'Use POST for requests that may contain patient or operational data.'], 405);
}

$GLOBALS['codex_api_raw_body'] = $rawBody;
$GLOBALS['codex_api_security_context'] = codex_security_authenticate($action, $method, $body, $rawBody);
$GLOBALS['codex_api_v1_body'] = $body;

register_shutdown_function(static function (): void {
    $context = $GLOBALS['codex_api_security_context'] ?? null;
    if (!is_array($context) || !empty($context['finalized'])) {
        return;
    }
    $error = error_get_last();
    $payload = ['ok' => false, 'message' => 'Request terminated unexpectedly.'];
    if ($error !== null) {
        $payload['error_type'] = (int)($error['type'] ?? 0);
    }
    codex_security_finalize(http_response_code() >= 400 ? http_response_code() : 500, $payload);
});

require dirname(__DIR__, 2) . '/codex_control.php';
