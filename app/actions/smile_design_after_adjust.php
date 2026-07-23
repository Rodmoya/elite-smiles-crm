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
$case = smile_design_case($caseId);
$returnUrl = trim((string)post('return_url', ''));
$defaultReturnUrl = base_url('smile-design/cases/' . $caseId);
$redirectUrl = $defaultReturnUrl;
$appBase = rtrim(base_url(''), '/');
if ($returnUrl !== '' && (str_starts_with($returnUrl, $appBase . '/') || str_starts_with($returnUrl, '/'))) {
    $redirectUrl = $returnUrl;
}
$beforePhotoId = (int)post('before_photo_id', (int)($version['before_photo_id'] ?? 0));
if ($beforePhotoId <= 0) {
    $primary = smile_design_primary_before_photo($caseId);
    $beforePhotoId = (int)($primary['id'] ?? 0);
}

$adjustmentRequest = trim((string)post('adjustment_request', ''));
if ($adjustmentRequest === '') {
    flash_set('error', 'Please enter the smile adjustment you want before resending.');
    redirect($redirectUrl);
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
$shadeGoal = (string)post('shade_goal', (string)($case['shade_goal'] ?? '210'));
$treatmentScope = (string)post('treatment_scope', (string)($case['treatment_scope'] ?? 'upper'));
$smileWidthGoal = (string)post('smile_width_goal', (string)($case['smile_width_goal'] ?? 'keep_current'));
$shapeScaleDelta = (string)post('shape_scale_delta', '0');
$smileLengthDelta = (string)post('smile_length_delta', '0');
$smileWidthDelta = (string)post('smile_width_delta', '0');
$shadeBrightnessDelta = (string)post('shade_brightness_delta', '0');
$anchorPoints = trim((string)post('anchor_points', ''));
$contourPoints = trim((string)post('contour_points', ''));
$selectionMode = trim((string)post('selection_mode', 'contour'));
$brushMaskData = trim((string)post('brush_mask_data', ''));
$brushOverlayData = trim((string)post('brush_overlay_data', ''));
$editorMode = trim((string)post('editor_mode', 'automatic'));
$selectedTeeth = trim((string)post('selected_teeth', ''));
$toothOffsets = trim((string)post('tooth_offsets', '{}'));
$toothAdjustments = trim((string)post('tooth_adjustments', '{}'));
$precisionMode = trim((string)post('precision_mode', 'balanced'));
if (smile_design_procedure_mode($procedureLabel) === 'lip_repositioning') {
    $lviStyleKey = '';
}

try {
    smile_design_update_case_preferences($caseId, [
        'procedure_interest' => $procedureLabel,
        'selected_style' => $lviStyleKey,
        'shade_goal' => $shadeGoal,
        'treatment_scope' => $treatmentScope,
        'smile_width_goal' => $smileWidthGoal,
        'shape_scale_delta' => (int)$shapeScaleDelta,
        'smile_length_delta' => (int)$smileLengthDelta,
        'smile_width_delta' => (int)$smileWidthDelta,
        'shade_brightness_delta' => (int)$shadeBrightnessDelta,
        'anchor_points' => $anchorPoints,
        'contour_points' => $contourPoints,
        'selection_mode' => $selectionMode,
        'editor_mode' => $editorMode,
        'selected_teeth' => $selectedTeeth,
        'precision_mode' => $precisionMode,
    ], auth_user_id());

    $generationOptions = [
        'provider' => 'google_gemini',
        'version_title' => $versionTitle,
        'custom_request' => $adjustmentRequest,
        'procedure_label' => $procedureLabel,
        'lvi_style_key' => $lviStyleKey,
        'shade_goal' => $shadeGoal,
        'treatment_scope' => $treatmentScope,
        'smile_width_goal' => $smileWidthGoal,
        'photo_type' => post('photo_type', (string)($version['photo_type'] ?? 'front')),
        'notes' => implode("\n", $noteParts),
        'refresh_analysis' => post('refresh_analysis', '') === '1',
        'anchor_points' => $anchorPoints,
        'contour_points' => $contourPoints,
        'selection_mode' => $selectionMode,
        'brush_mask_data' => $brushMaskData,
        'brush_overlay_data' => $brushOverlayData,
        'editor_mode' => $editorMode,
        'selected_teeth' => $selectedTeeth,
        'tooth_offsets' => $toothOffsets,
        'tooth_adjustments' => $toothAdjustments,
        'shape_scale_delta' => (int)$shapeScaleDelta,
        'smile_length_delta' => (int)$smileLengthDelta,
        'smile_width_delta' => (int)$smileWidthDelta,
        'shade_brightness_delta' => (int)$shadeBrightnessDelta,
        'precision_mode' => $precisionMode,
    ];
    if (post('use_reference_after', '') === '1') {
        $generationOptions['reference_after_version_id'] = $versionId;
    }

    $result = smile_design_create_ai_after_version($caseId, $beforePhotoId, $generationOptions, auth_user_id());

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

redirect($redirectUrl);
