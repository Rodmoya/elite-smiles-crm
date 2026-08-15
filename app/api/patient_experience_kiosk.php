<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../patient_experience/patient_experience_service.php';

patient_experience_ensure_schema();

if (request_method() === 'GET') {
    $deviceToken = get('device_token', '');
    json_response(patient_experience_kiosk_poll(is_string($deviceToken) ? $deviceToken : ''));
}

if (request_method() !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$action = trim((string)($payload['action'] ?? ''));
$kioskToken = trim((string)($payload['kiosk_token'] ?? ''));
$deviceToken = trim((string)($payload['device_token'] ?? ''));
$directMode = trim((string)($payload['direct_mode'] ?? '')) === '1';

if ($action !== '' && $kioskToken === '' && !($directMode && $action === 'direct_begin')) {
    json_response(['ok' => false, 'message' => 'This check-in session is no longer active.'], 400);
}
if (in_array($action, ['begin', 'save_step', 'complete', 'cancel'], true) && $deviceToken === '' && !$directMode) {
    json_response(['ok' => false, 'message' => 'This kiosk is not registered.'], 403);
}

if ($action === 'begin') {
    json_response(patient_experience_begin_session($kioskToken, $deviceToken));
}

if ($action === 'direct_begin') {
    $patientName = trim((string)($payload['patient_name'] ?? ''));
    if ($patientName === '') {
        $patientName = 'Walk-in Patient';
    }
    $session = patient_experience_start_placeholder_session(null, $patientName, null, null);
    if (!empty($session['error'])) {
        json_response(['ok' => false, 'message' => (string)$session['error']], 400);
    }
    $began = patient_experience_begin_session((string)($session['token'] ?? ''), '');
    if (empty($began['ok'])) {
        json_response($began, 400);
    }
    $began['kiosk_token'] = (string)($session['token'] ?? '');
    json_response($began);
}

if ($action === 'save_step') {
    $stepKey = trim((string)($payload['step_key'] ?? ''));
    $answers = $payload['answers'] ?? [];
    json_response(patient_experience_save_step($kioskToken, $stepKey, is_array($answers) ? $answers : [], $deviceToken));
}

if ($action === 'complete') {
    json_response(patient_experience_complete_session($kioskToken, $deviceToken));
}

if ($action === 'cancel') {
    $session = $deviceToken !== ''
        ? patient_experience_kiosk_session_for_device($kioskToken, $deviceToken)
        : patient_experience_session_by_kiosk_token($kioskToken);
    if (!$session && $deviceToken !== '') {
        json_response(['ok' => false, 'message' => 'This check-in session is not linked to this iPad.'], 403);
    }
    if (!$session) {
        json_response(['ok' => false, 'message' => 'This check-in session is no longer active.'], 404);
    }
    patient_experience_cancel_session((int)$session['id'], null);
    json_response(['ok' => true, 'message' => 'Check-in cancelled.']);
}

json_response(['ok' => false, 'message' => 'Unknown kiosk action.'], 400);
