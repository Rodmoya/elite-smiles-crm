<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../smile_design/smile_design_service.php';

require_auth();
require_csrf();
smile_design_ensure_schema();

$user = auth_user() ?: [];
$first = trim((string)post('first_name'));
$last = trim((string)post('last_name'));
$patientName = trim($first . ' ' . $last);

$leadId = smile_design_match_or_create_lead([
    'patient_name' => $patientName,
    'email' => post('email'),
    'phone' => post('phone'),
    'procedure_interest' => post('procedure_interest'),
    'notes' => "Smile Design staff intake submitted.\n" . (string)post('notes'),
], $user);

$caseId = smile_design_create_case([
    'lead_id' => $leadId,
    'first_name' => $first,
    'last_name' => $last,
    'patient_name' => $patientName,
    'email' => post('email'),
    'phone' => post('phone'),
    'procedure_interest' => post('procedure_interest'),
    'selected_style' => post('selected_style', 'natural'),
    'shade_goal' => 'Doctor consult',
    'notes' => post('notes'),
    'status' => 'staff_intake_submitted',
    'visibility' => 'internal_only',
    'consent_status' => post('consent_status', 'not_recorded'),
], auth_user_id());

$frontUpload = smile_design_store_upload($caseId, $_FILES['before_photo_front'] ?? [], 'before', 'front');
if (empty($frontUpload['ok'])) {
    flash_set('error', (string)($frontUpload['message'] ?? 'Could not upload front photo.'));
    redirect(base_url('smile-design/staff-intake'));
}

foreach ([
    'before_photo_left_45' => 'left_45',
    'before_photo_right_45' => 'right_45',
] as $field => $photoType) {
    $file = $_FILES[$field] ?? null;
    if (!$file || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    $optionalUpload = smile_design_store_upload($caseId, $file, 'before', $photoType);
    if (empty($optionalUpload['ok'])) {
        flash_set('error', (string)($optionalUpload['message'] ?? 'Could not upload optional photo.'));
        redirect(base_url('smile-design/staff-intake'));
    }
}

smile_design_audit($caseId, 'staff_intake_submitted', ['lead_id' => $leadId], auth_user_id());
smile_design_audit($caseId, 'staff_photo_uploaded', ['photo_id' => (int)($frontUpload['photo_id'] ?? 0)], auth_user_id());

$analysisResult = ['ok' => false, 'message' => 'AI case analysis was not started.'];
try {
    $analysisResult = smile_design_run_case_analysis($caseId, (int)($frontUpload['photo_id'] ?? 0), auth_user_id(), true);
} catch (Throwable $e) {
    esm_log('smile_design_analysis', 'Initial staff-intake case analysis failed.', [
        'case_id' => $caseId,
        'before_photo_id' => (int)($frontUpload['photo_id'] ?? 0),
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    $analysisResult = ['ok' => false, 'message' => 'AI case analysis could not be completed right now.'];
}

if (!empty($analysisResult['ok'])) {
    flash_set('success', 'Smile design case created and analyzed successfully.');
} else {
    flash_set('success', 'Smile design case created successfully.');
    flash_set('error', 'Initial AI case analysis did not complete. Use Re-run Analysis inside the case when ready.');
}
redirect(base_url('smile-design/cases/' . $caseId));
