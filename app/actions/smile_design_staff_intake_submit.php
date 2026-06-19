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

$first = trim((string)post('first_name'));
$last = trim((string)post('last_name'));
$patientName = trim($first . ' ' . $last);
$mobileUploadToken = trim((string)post('mobile_upload_token', ''));

$caseId = smile_design_create_case([
    'first_name' => $first,
    'last_name' => $last,
    'patient_name' => $patientName,
    'email' => post('email'),
    'phone' => post('phone'),
    'procedure_interest' => post('procedure_interest'),
    'selected_style' => post('selected_style', 'natural'),
    'shade_goal' => post('shade_goal', '110'),
    'treatment_scope' => post('treatment_scope', 'upper'),
    'smile_width_goal' => post('smile_width_goal', 'keep_current'),
    'notes' => post('notes'),
    'status' => 'staff_intake_submitted',
    'visibility' => 'internal_only',
    'consent_status' => post('consent_status', 'not_recorded'),
], auth_user_id());

$frontUpload = ['ok' => false, 'photo_id' => 0];
$localPhotoTypes = [];
$frontFile = $_FILES['before_photo_front'] ?? null;
if ($frontFile && (int)($frontFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $frontUpload = smile_design_store_upload($caseId, $frontFile, 'before', 'front');
    if (empty($frontUpload['ok'])) {
        flash_set('error', (string)($frontUpload['message'] ?? 'Could not upload front photo.'));
        redirect(base_url('smile-design/staff-intake'));
    }
    $localPhotoTypes[] = 'front';
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
    $localPhotoTypes[] = $photoType;
}

$mobileImport = ['ok' => true, 'photo_ids' => []];
if ($mobileUploadToken !== '') {
    $mobileImport = smile_design_import_mobile_uploads_to_case($mobileUploadToken, $caseId, auth_user_id(), $localPhotoTypes);
}

$frontPhotoId = (int)($frontUpload['photo_id'] ?? 0);
if ($frontPhotoId <= 0 && !empty($mobileImport['photo_ids']['front'])) {
    $frontPhotoId = (int)$mobileImport['photo_ids']['front'];
}

if ($frontPhotoId <= 0) {
    flash_set('error', 'Please upload a front before photo from this computer or scan the QR code and upload it from your phone.');
    redirect(base_url('smile-design/staff-intake'));
}

smile_design_audit($caseId, 'staff_intake_submitted', ['lead_id' => null], auth_user_id());
smile_design_audit($caseId, 'staff_photo_uploaded', ['photo_id' => $frontPhotoId], auth_user_id());

$analysisResult = ['ok' => false, 'message' => 'AI case analysis was not started.'];
try {
    $analysisResult = smile_design_run_case_analysis($caseId, $frontPhotoId, auth_user_id(), true);
} catch (Throwable $e) {
    esm_log('smile_design_analysis', 'Initial staff-intake case analysis failed.', [
        'case_id' => $caseId,
        'before_photo_id' => $frontPhotoId,
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
