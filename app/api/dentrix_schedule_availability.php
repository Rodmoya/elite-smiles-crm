<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * GET /crm/api/dentrix-schedule/availability
 *
 * Live-read of currently open OP-3 consult slots for a date. Staff-session
 * authenticated JSON endpoint (no separate CSRF token — same pattern as this
 * codebase's other internal fetch()-based JSON APIs; the SameSite=Lax
 * session cookie already prevents cross-site submission).
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/dentrix_schedule/dentrix_schedule_service.php';
require_once dirname(__DIR__) . '/dentrix_schedule/dentrix_schedule_consult_engine.php';

function dentrix_schedule_availability_json(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (request_method() !== 'GET') {
    dentrix_schedule_availability_json(405, ['ok' => false, 'message' => 'Method not allowed.']);
}
if (!auth_check() || !auth_has_role(...dentrix_schedule_allowed_staff_roles())) {
    dentrix_schedule_availability_json(401, ['ok' => false, 'message' => 'Unauthorized.']);
}

$scheduleDate = trim((string)($_GET['schedule_date'] ?? date('Y-m-d')));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduleDate) !== 1) {
    dentrix_schedule_availability_json(422, ['ok' => false, 'message' => 'schedule_date must be YYYY-MM-DD.']);
}

try {
    $slots = dentrix_schedule_available_consult_slots($scheduleDate);
    dentrix_schedule_availability_json(200, ['ok' => true, 'schedule_date' => $scheduleDate, 'slots' => $slots]);
} catch (Throwable $e) {
    esm_log('dentrix_schedule', 'Availability lookup failed.', ['message' => $e->getMessage()]);
    dentrix_schedule_availability_json(500, ['ok' => false, 'message' => 'Availability lookup failed.']);
}
