<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * POST /crm/api/dentrix-schedule/block-slot
 *
 * Creates a manual calendar block (lunch is managed separately/automatically
 * — this is for surgery-guard windows, closures, etc.) and immediately
 * regenerates that date's consult slot availability.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/dentrix_schedule/dentrix_schedule_service.php';
require_once dirname(__DIR__) . '/dentrix_schedule/dentrix_schedule_consult_engine.php';

function dentrix_schedule_block_slot_json(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (request_method() !== 'POST') {
    dentrix_schedule_block_slot_json(405, ['ok' => false, 'message' => 'Method not allowed.']);
}
if (!auth_check() || !auth_has_role(...dentrix_schedule_allowed_staff_roles())) {
    dentrix_schedule_block_slot_json(401, ['ok' => false, 'message' => 'Unauthorized.']);
}

$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = $_POST;
}

$scheduleDate = trim((string)($body['schedule_date'] ?? ''));
$startTime = trim((string)($body['start_time'] ?? ''));
$endTime = trim((string)($body['end_time'] ?? ''));
$operatory = trim((string)($body['operatory'] ?? ''));
$blockType = trim((string)($body['block_type'] ?? 'manual_block'));
$reason = trim((string)($body['reason'] ?? ''));

$allowedBlockTypes = ['surgery_guard', 'sedation_guard', 'manual_block', 'closed', 'other'];

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduleDate) !== 1) {
    dentrix_schedule_block_slot_json(422, ['ok' => false, 'message' => 'schedule_date must be YYYY-MM-DD.']);
}
if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime) !== 1 || preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $endTime) !== 1) {
    dentrix_schedule_block_slot_json(422, ['ok' => false, 'message' => 'start_time/end_time must be HH:MM.']);
}
if (!in_array($blockType, $allowedBlockTypes, true)) {
    dentrix_schedule_block_slot_json(422, ['ok' => false, 'message' => 'Invalid block_type.']);
}

try {
    $result = dentrix_schedule_block_slot(
        $scheduleDate,
        strlen($startTime) === 5 ? $startTime . ':00' : $startTime,
        strlen($endTime) === 5 ? $endTime . ':00' : $endTime,
        $operatory !== '' ? $operatory : null,
        $blockType,
        $reason !== '' ? $reason : null,
        auth_user_id()
    );
    dentrix_schedule_block_slot_json(!empty($result['ok']) ? 200 : 422, $result);
} catch (Throwable $e) {
    esm_log('dentrix_schedule', 'Block-slot request failed.', ['message' => $e->getMessage()]);
    dentrix_schedule_block_slot_json(500, ['ok' => false, 'message' => 'Block-slot request failed.']);
}
