<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Protected Dentrix occupied-slot scan request creator.
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/dentrix/dentrix_bridge.php';

function dentrix_scan_api_json(int $statusCode, array $payload): never
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

if (!in_array(request_method(), ['GET', 'POST'], true)) {
    dentrix_scan_api_json(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

$secret = dentrix_bridge_secret();
if ($secret === '') {
    dentrix_scan_api_json(503, ['ok' => false, 'message' => 'Dentrix bridge secret is not configured.']);
}

$providedSecret = trim((string)($_SERVER['HTTP_X_DENTRIX_BRIDGE_SECRET'] ?? $_SERVER['HTTP_X_ELITE_DENTRIX_SECRET'] ?? $_GET['secret'] ?? ''));
if (!hash_equals($secret, $providedSecret)) {
    dentrix_scan_api_json(401, ['ok' => false, 'message' => 'Unauthorized.']);
}

$dateFrom = trim((string)($_GET['date_from'] ?? $_POST['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? $_POST['date_to'] ?? ''));

try {
    dentrix_scan_api_json(200, dentrix_bridge_create_scan_calendar_job($dateFrom ?: null, $dateTo ?: null));
} catch (Throwable $e) {
    esm_log('dentrix_bridge', 'Dentrix scan-calendar endpoint failed.', ['message' => $e->getMessage()]);
    dentrix_scan_api_json(500, ['ok' => false, 'message' => 'Dentrix scan-calendar request failed.']);
}
