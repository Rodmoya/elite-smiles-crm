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

$versionId = (int)post('after_version_id', 0);
$version = db_one('SELECT * FROM smile_after_versions WHERE id = :id LIMIT 1', ['id' => $versionId]);
if (!$version) {
    flash_set('error', 'After version not found.');
    redirect(base_url('smile-design/cases'));
}

$caseId = (int)$version['case_id'];
$beforePhotoId = (int)post('before_photo_id', (int)($version['before_photo_id'] ?? 0));
if ($beforePhotoId <= 0) {
    $primary = smile_design_primary_before_photo($caseId);
    $beforePhotoId = (int)($primary['id'] ?? 0);
}

$adjustmentRequest = trim((string)post('adjustment_request', ''));
if ($adjustmentRequest === '') {
    flash_set('error', 'Please enter the smile adjustment you want before resending.');
    redirect(base_url('smile-design/cases/' . $caseId));
}

$versionTitle = trim((string)post('version_title', ''));
if ($versionTitle === '') {
    $versionTitle = 'Revision of #' . (string)$version['version_number'] . ' ' . trim((string)$version['version_title']);
}

$existingNotes = trim((string)($version['notes'] ?? ''));
$internalNotes = trim((string)post('notes', ''));
$noteParts = array_values(array_filter([
    $internalNotes,
    $existingNotes !== '' ? 'Prior version notes: ' . $existingNotes : '',
    'Adjustment request: ' . $adjustmentRequest,
]));
$procedureLabel = (string)post('procedure_label', (string)($version['procedure_label'] ?? ''));
$lviStyleKey = (string)post('lvi_style_key', (string)($version['lvi_style_key'] ?? ''));
if (smile_design_procedure_mode($procedureLabel) === 'lip_repositioning') {
    $lviStyleKey = '';
}

try {
    $result = smile_design_create_ai_after_version($caseId, $beforePhotoId, [
        'provider' => 'google_gemini',
        'reference_after_version_id' => $versionId,
        'version_title' => $versionTitle,
        'custom_request' => $adjustmentRequest,
        'procedure_label' => $procedureLabel,
        'lvi_style_key' => $lviStyleKey,
        'photo_type' => post('photo_type', (string)($version['photo_type'] ?? 'front')),
        'notes' => implode("\n", $noteParts),
        'refresh_analysis' => post('refresh_analysis', '') === '1',
    ], auth_user_id());

    if (empty($result['ok'])) {
        flash_set('error', (string)($result['message'] ?? 'AI revision failed.'));
    } else {
        flash_set('success', 'AI revision generated as a new after version.');
    }
} catch (Throwable $e) {
    esm_log('smile_design_generate', 'AI revision action failed.', [
        'case_id' => $caseId,
        'before_photo_id' => $beforePhotoId,
        'after_version_id' => $versionId,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    flash_set('error', 'Smile adjustment could not be generated right now. The issue was logged so we can fix it without losing the case.');
}

redirect(base_url('smile-design/cases/' . $caseId));
