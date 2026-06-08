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
$leadId = smile_design_match_or_create_lead([
    'patient_name' => post('patient_name'),
    'email' => post('email'),
    'phone' => post('phone'),
    'procedure_interest' => post('procedure_interest', 'Smile Design Preview'),
    'notes' => 'Smile Design case created internally.',
], $user);

$caseId = smile_design_create_case([
    'lead_id' => $leadId,
    'patient_name' => post('patient_name'),
    'email' => post('email'),
    'phone' => post('phone'),
    'procedure_interest' => post('procedure_interest', 'Smile Design Preview'),
    'selected_style' => post('selected_style', 'natural'),
    'shade_goal' => post('shade_goal', 'Natural bright'),
    'notes' => post('notes'),
    'status' => 'staff_intake_submitted',
    'visibility' => 'internal_only',
], auth_user_id());

$frontPhotoId = 0;
if (!empty($_FILES['before_photo_front']) && (int)($_FILES['before_photo_front']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $upload = smile_design_store_upload($caseId, $_FILES['before_photo_front'], 'before', 'front');
    if (empty($upload['ok'])) {
        flash_set('error', (string)($upload['message'] ?? 'Could not upload front photo.'));
    } else {
        $frontPhotoId = (int)($upload['photo_id'] ?? 0);
    }
}

foreach ([
    'before_photo_left_45' => 'left_45',
    'before_photo_right_45' => 'right_45',
] as $field => $photoType) {
    if (empty($_FILES[$field]) || (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    $optionalUpload = smile_design_store_upload($caseId, $_FILES[$field], 'before', $photoType);
    if (empty($optionalUpload['ok'])) {
        flash_set('error', (string)($optionalUpload['message'] ?? 'Could not upload optional photo.'));
    }
}

smile_design_audit($caseId, 'staff_intake_submitted', ['lead_id' => $leadId], auth_user_id());
if ($frontPhotoId > 0) {
    try {
        $analysisResult = smile_design_run_case_analysis($caseId, $frontPhotoId, auth_user_id(), true);
        if (!empty($analysisResult['ok'])) {
            flash_set('success', 'Smile design case created and analyzed successfully.');
        } else {
            flash_set('success', 'Smile design case created successfully.');
            flash_set('error', 'Initial AI case analysis did not complete. Use Re-run Analysis inside the case when ready.');
        }
    } catch (Throwable $e) {
        esm_log('smile_design_analysis', 'Initial internal case analysis failed.', [
            'case_id' => $caseId,
            'before_photo_id' => $frontPhotoId,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        flash_set('success', 'Smile design case created successfully.');
        flash_set('error', 'Initial AI case analysis did not complete. Use Re-run Analysis inside the case when ready.');
    }
} else {
    flash_set('success', 'Smile design case created successfully.');
}

redirect(base_url('smile-design/cases/' . $caseId));
