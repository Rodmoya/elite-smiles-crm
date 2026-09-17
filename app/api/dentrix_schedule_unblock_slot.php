<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * POST /crm/api/dentrix-schedule/unblock-slot
 *
 * Removes a manual calendar block and immediately regenerates that date's
 * consult slot availability. The lunch block cannot be removed this way.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/dentrix_schedule/dentrix_schedule_service.php';
require_once dirname(__DIR__) . '/dentrix_schedule/dentrix_schedule_consult_engine.php';

function dentrix_schedule_unblock_slot_json(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (request_method() !== 'POST') {
    dentrix_schedule_unblock_slot_json(405, ['ok' => false, 'message' => 'Method not allowed.']);
}
if (!auth_check() || !auth_has_role(...dentrix_schedule_allowed_staff_roles())) {
    dentrix_schedule_unblock_slot_json(401, ['ok' => false, 'message' => 'Unauthorized.']);
}

$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = $_POST;
}

$blockId = (int)($body['block_id'] ?? 0);
if ($blockId <= 0) {
    dentrix_schedule_unblock_slot_json(422, ['ok' => false, 'message' => 'block_id is required.']);
}

try {
    $result = dentrix_schedule_unblock_slot($blockId, auth_user_id());
    dentrix_schedule_unblock_slot_json(!empty($result['ok']) ? 200 : 422, $result);
} catch (Throwable $e) {
    esm_log('dentrix_schedule', 'Unblock-slot request failed.', ['message' => $e->getMessage()]);
    dentrix_schedule_unblock_slot_json(500, ['ok' => false, 'message' => 'Unblock-slot request failed.']);
}
