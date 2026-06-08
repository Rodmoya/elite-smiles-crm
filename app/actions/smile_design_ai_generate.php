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

try {
    $customRequest = trim((string)post('custom_request', ''));
    $baseTitle = trim((string)post('version_title', 'After Preview'));
    $procedureLabel = (string)post('procedure_label', $case['procedure_interest'] ?? '');
    $lviStyleKey = (string)post('lvi_style_key', $case['lvi_style_key'] ?? '');
    if (smile_design_procedure_mode($procedureLabel) === 'lip_repositioning') {
        $lviStyleKey = '';
    }
    $notes = (string)post('notes', '');
    $refreshAnalysis = post('refresh_analysis', '') === '1';
    $frontPhoto = smile_design_find_before_photo_by_type($caseId, 'front', true);
    if (!$frontPhoto) {
        flash_set('error', 'A front before photo is required before generating an after preview.');
        redirect(base_url('smile-design/cases/' . $caseId));
    }

    $analysisResult = smile_design_run_case_analysis($caseId, (int)$frontPhoto['id'], auth_user_id(), $refreshAnalysis);
    if (empty($analysisResult['ok'])) {
        flash_set('error', (string)($analysisResult['message'] ?? 'AI case analysis failed.'));
        redirect(base_url('smile-design/cases/' . $caseId));
    }

    $angleDefinitions = [
        'front' => 'Front',
        'left_45' => 'Left 45',
        'right_45' => 'Right 45',
    ];
    $targets = [];
    foreach ($angleDefinitions as $photoType => $label) {
        $photo = $photoType === 'front'
            ? $frontPhoto
            : smile_design_find_before_photo_by_type($caseId, $photoType, true);
        if ($photo) {
            $targets[] = ['photo_type' => $photoType, 'label' => $label, 'photo' => $photo];
        }
    }

    $successes = [];
    $failures = [];
    foreach ($targets as $target) {
        $targetLabel = (string)$target['label'];
        $targetPhotoType = (string)$target['photo_type'];
        $targetPhotoId = (int)$target['photo']['id'];
        $targetTitle = $baseTitle . ' - ' . $targetLabel;
        $targetNotes = trim(implode("\n", array_values(array_filter([
            trim($notes),
            'Generated angle: ' . $targetLabel,
        ]))));

        $result = smile_design_create_ai_after_version($caseId, $targetPhotoId, [
            'provider' => 'google_gemini',
            'version_title' => $targetTitle,
            'custom_request' => $customRequest,
            'procedure_label' => $procedureLabel,
            'lvi_style_key' => $lviStyleKey,
            'photo_type' => $targetPhotoType,
            'target_photo_type' => $targetPhotoType,
            'target_photo_label' => $targetLabel,
            'notes' => $targetNotes,
            'auto_analyze' => false,
            'case_analysis' => $analysisResult['analysis'] ?? null,
            'analysis_summary' => $analysisResult['summary'] ?? '',
            'auto_prepare_missing_angles' => false,
        ], auth_user_id());

        if (!empty($result['ok'])) {
            $successes[] = $targetLabel;
        } else {
            $failures[] = $targetLabel . ': ' . (string)($result['message'] ?? 'Gemini smile preview failed.');
        }
    }

    if ($successes !== []) {
        flash_set('success', 'Generated after preview for ' . implode(', ', $successes) . '.');
    }
    if ($failures !== []) {
        flash_set('error', 'Some angles could not be generated. ' . implode(' ', $failures));
    }
} catch (Throwable $e) {
    esm_log('smile_design_generate', 'AI generate action failed.', [
        'case_id' => $caseId,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    flash_set('error', 'Smile preview could not be generated right now. The issue was logged so we can tighten it quickly.');
}

redirect(base_url('smile-design/cases/' . $caseId));
