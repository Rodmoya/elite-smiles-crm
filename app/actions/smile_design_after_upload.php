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

$caseId = (int)post('case_id', 0);
$case = smile_design_case($caseId);
if (!$case) {
    flash_set('error', 'Smile case not found.');
    redirect(base_url('smile-design/cases'));
}

$procedureLabel = (string)post('procedure_label', $case['procedure_interest'] ?? '');
$lviStyleKey = (string)post('lvi_style_key', $case['lvi_style_key'] ?? '');
if (smile_design_procedure_mode($procedureLabel) === 'lip_repositioning') {
    $lviStyleKey = '';
}

$result = smile_design_create_after_version($caseId, $_FILES['after_photo'] ?? [], [
    'before_photo_id' => post('before_photo_id'),
    'version_title' => post('version_title', 'Manual After Version'),
    'source_type' => post('source_type', 'manual_upload'),
    'procedure_label' => $procedureLabel,
    'lvi_style_key' => $lviStyleKey,
    'photo_type' => post('photo_type', 'front'),
    'notes' => post('notes'),
], auth_user_id());

if (empty($result['ok'])) {
    flash_set('error', (string)($result['message'] ?? 'Could not upload after version.'));
} else {
    $afterVersionId = (int)($result['after_version_id'] ?? 0);
    if ($afterVersionId > 0 && post('approve_preview', '') === '1') {
        smile_design_set_after_flag($afterVersionId, 'approved_for_patient_preview', true, auth_user_id());
    }
    if ($afterVersionId > 0 && post('approve_gallery', '') === '1') {
        smile_design_set_after_flag($afterVersionId, 'approved_for_office_gallery', true, auth_user_id());
    }
    flash_set('success', 'AFTER version uploaded.');
}
redirect(base_url('smile-design/cases/' . $caseId));
