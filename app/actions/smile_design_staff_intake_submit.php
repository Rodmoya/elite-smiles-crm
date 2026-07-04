<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../leads/lead_service.php';
require_once __DIR__ . '/../smile_design/smile_design_service.php';

require_auth();
require_csrf();
smile_design_ensure_schema();

$first = trim((string)post('first_name'));
$last = trim((string)post('last_name'));
$patientName = trim($first . ' ' . $last);
$phone = trim((string)post('phone'));
$email = strtolower(trim((string)post('email')));
$leadId = (int)post('lead_id', 0);
$mobileUploadToken = trim((string)post('mobile_upload_token', ''));

if ($leadId > 0) {
    $leadExists = db_value('SELECT id FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
    if (!$leadExists) {
        $leadId = 0;
    }
}
$returnParams = [];
if ($leadId > 0) {
    $returnParams['lead_id'] = (string)$leadId;
}
if ($mobileUploadToken !== '' && smile_design_verify_token($mobileUploadToken, 'mobile_upload')) {
    $returnParams['mobile_upload_token'] = $mobileUploadToken;
}
$staffIntakeReturnUrl = base_url('smile-design/staff-intake' . ($returnParams ? '?' . http_build_query($returnParams) : ''));

if ($first === '' || $phone === '') {
    flash_set('error', 'First name and phone are required before creating a Smile Design case.');
    redirect($staffIntakeReturnUrl);
}

$frontFile = $_FILES['before_photo_front'] ?? null;
$hasLocalFront = $frontFile && (int)($frontFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$mobileUploads = [];
$hasMobileFront = false;
if ($mobileUploadToken !== '' && smile_design_verify_token($mobileUploadToken, 'mobile_upload')) {
    $mobileUploads = smile_design_mobile_uploads_for_token($mobileUploadToken, true);
    $hasMobileFront = !empty($mobileUploads['front']);
}

if (!$hasLocalFront && !$hasMobileFront) {
    $uploadedAngles = [];
    foreach ($mobileUploads as $mobilePhotoType => $mobileUpload) {
        if (!empty($mobileUpload)) {
            $uploadedAngles[] = ucwords(str_replace('_', ' ', (string)$mobilePhotoType));
        }
    }
    $missingFrontMessage = 'Please upload a front before photo from this computer, or scan the QR code and upload the Front photo from your phone before creating the case.';
    if ($uploadedAngles !== []) {
        $missingFrontMessage .= ' Phone uploads found: ' . implode(', ', $uploadedAngles) . '. The Front slot is still required.';
    }
    flash_set('error', $missingFrontMessage);
    redirect($staffIntakeReturnUrl);
}

$frontUpload = ['ok' => false, 'photo_id' => 0];
$localPhotoTypes = [];
$caseId = 0;
$frontPhotoId = 0;

try {
    db_begin();

    if ($leadId > 0) {
        $leadSetParts = [];
        $leadParams = ['id' => $leadId];
        foreach ([
            'first_name' => $first,
            'last_name' => $last,
            'full_name' => $patientName,
            'phone' => $phone,
            'email' => $email,
            'procedure_interest' => trim((string)post('procedure_interest')),
        ] as $column => $value) {
            if (function_exists('leads_has_column') && leads_has_column($column)) {
                $leadSetParts[] = $column . ' = :' . $column;
                $leadParams[$column] = $value !== '' ? $value : null;
            }
        }
        if (function_exists('leads_has_column') && leads_has_column('updated_at')) {
            $leadSetParts[] = 'updated_at = :updated_at';
            $leadParams['updated_at'] = now();
        }
        if (!empty($leadSetParts)) {
            db_execute('UPDATE leads SET ' . implode(', ', $leadSetParts) . ' WHERE id = :id LIMIT 1', $leadParams);
        }
    }

    $caseId = smile_design_create_case([
        'lead_id' => $leadId,
        'first_name' => $first,
        'last_name' => $last,
        'patient_name' => $patientName,
        'email' => $email,
        'phone' => $phone,
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

    if ($hasLocalFront) {
        $frontUpload = smile_design_store_upload($caseId, $frontFile, 'before', 'front');
        if (empty($frontUpload['ok'])) {
            throw new RuntimeException((string)($frontUpload['message'] ?? 'Could not upload front photo.'));
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
            throw new RuntimeException((string)($optionalUpload['message'] ?? 'Could not upload optional photo.'));
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
        throw new RuntimeException('Please upload a front before photo from this computer or scan the QR code and upload it from your phone.');
    }

    smile_design_audit($caseId, 'staff_intake_submitted', ['lead_id' => $leadId ?: null], auth_user_id());
    smile_design_audit($caseId, 'staff_photo_uploaded', ['photo_id' => $frontPhotoId], auth_user_id());

    db_commit();
} catch (Throwable $e) {
    db_rollBack();
    esm_log('smile_design_staff_intake', 'Staff intake case creation failed.', [
        'message' => $e->getMessage(),
        'case_id' => $caseId,
    ]);
    flash_set('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not create Smile Design case.');
    redirect($staffIntakeReturnUrl);
}

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
