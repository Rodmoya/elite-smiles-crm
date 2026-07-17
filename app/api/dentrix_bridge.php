<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Dentrix worker callback endpoint.
 *
 * Expected worker events:
 * - patient_found / patient_created
 * - appointment_created / appointment_moved
 * - no_show
 * - completed / completed_paid
 * - sync_failed
 * - occupied_slots
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/dentrix/dentrix_bridge.php';

function dentrix_bridge_api_json(int $statusCode, array $payload): never
{
    while (ob_get_level() > 0) {
        $buffer = (string)ob_get_contents();
        ob_end_clean();
        if ($buffer !== '') {
            $payload['buffer_output'] = trim($buffer);
        }
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (request_method() !== 'POST') {
    dentrix_bridge_api_json(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

$secret = dentrix_bridge_secret();
if ($secret === '') {
    dentrix_bridge_api_json(503, ['ok' => false, 'message' => 'Dentrix bridge secret is not configured.']);
}

$rawBody = (string)file_get_contents('php://input');
$providedSignature = strtolower(trim((string)($_SERVER['HTTP_X_ELITE_DENTRIX_SIGNATURE'] ?? '')));
$providedSecret = trim((string)($_SERVER['HTTP_X_DENTRIX_BRIDGE_SECRET'] ?? $_SERVER['HTTP_X_ELITE_DENTRIX_SECRET'] ?? ''));
$expectedSignature = hash_hmac('sha256', $rawBody, $secret);

if (
    !hash_equals($expectedSignature, $providedSignature)
    && !hash_equals($secret, $providedSecret)
) {
    dentrix_bridge_api_json(401, ['ok' => false, 'message' => 'Unauthorized.']);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    dentrix_bridge_api_json(400, ['ok' => false, 'message' => 'Invalid JSON payload.']);
}

$eventHeader = trim((string)($_SERVER['HTTP_X_DENTRIX_BRIDGE_EVENT'] ?? ''));
if ($eventHeader !== '' && empty($payload['event'])) {
    $payload['event'] = $eventHeader;
}

try {
    $result = dentrix_bridge_apply_result($payload);
    dentrix_bridge_api_json(!empty($result['ok']) ? 200 : 422, $result);
} catch (Throwable $e) {
    esm_log('dentrix_bridge', 'Dentrix bridge callback failed.', [
        'message' => $e->getMessage(),
        'payload' => $payload,
    ]);
    dentrix_bridge_api_json(500, ['ok' => false, 'message' => 'Dentrix bridge callback failed.']);
}
