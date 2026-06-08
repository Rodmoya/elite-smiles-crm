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

$adjustmentRequest = trim((string)post('adjustment_request', ''));
if ($adjustmentRequest === '') {
    flash_set('error', 'Please enter the correction you want before resending all angles.');
    redirect(base_url('smile-design/cases/' . $caseId . '#generate'));
}

$frontPhoto = smile_design_find_before_photo_by_type($caseId, 'front', true);
if (!$frontPhoto) {
    flash_set('error', 'A front before photo is required before resending all angles.');
    redirect(base_url('smile-design/cases/' . $caseId . '#generate'));
}

try {
    $refreshAnalysis = post('refresh_analysis', '') === '1';
    $analysisResult = smile_design_run_case_analysis($caseId, (int)$frontPhoto['id'], auth_user_id(), $refreshAnalysis);
    if (empty($analysisResult['ok'])) {
        flash_set('error', (string)($analysisResult['message'] ?? 'AI case analysis failed.'));
        redirect(base_url('smile-design/cases/' . $caseId . '#generate'));
    }

    $angleDefinitions = [
        'front' => 'Front',
        'left_45' => 'Left 45',
        'right_45' => 'Right 45',
    ];
    $procedureLabel = (string)($case['procedure_interest'] ?? '');
    $lviStyleKey = smile_design_procedure_mode($procedureLabel) === 'lip_repositioning'
        ? ''
        : (string)($case['lvi_style_key'] ?? '');

    $successes = [];
    $failures = [];

    foreach ($angleDefinitions as $photoType => $label) {
        $beforePhoto = $photoType === 'front'
            ? $frontPhoto
            : smile_design_find_before_photo_by_type($caseId, $photoType, true);
        if (!$beforePhoto) {
            continue;
        }

        $referenceVersion = smile_design_selected_after_version($caseId, $photoType);
        if (!$referenceVersion) {
            $failures[] = $label . ': no generated after exists yet.';
            continue;
        }

        $versionTitle = 'Revision of #' . (string)$referenceVersion['version_number'] . ' - ' . $label;
        $notes = trim(implode("\n", [
            'Batch correction for all angles.',
            'Corrected angle: ' . $label,
            'Correction request: ' . $adjustmentRequest,
        ]));

        $result = smile_design_create_ai_after_version($caseId, (int)$beforePhoto['id'], [
            'provider' => 'google_gemini',
            'reference_after_version_id' => (int)$referenceVersion['id'],
            'version_title' => $versionTitle,
            'custom_request' => $adjustmentRequest,
            'procedure_label' => $procedureLabel,
            'lvi_style_key' => $lviStyleKey,
            'photo_type' => $photoType,
            'target_photo_type' => $photoType,
            'target_photo_label' => $label,
            'notes' => $notes,
            'auto_analyze' => false,
            'case_analysis' => $analysisResult['analysis'] ?? null,
            'analysis_summary' => $analysisResult['summary'] ?? '',
            'auto_prepare_missing_angles' => false,
        ], auth_user_id());

        if (!empty($result['ok'])) {
            $successes[] = $label;
        } else {
            $failures[] = $label . ': ' . (string)($result['message'] ?? 'AI correction failed.');
        }
    }

    if ($successes !== []) {
        flash_set('success', 'Generated corrected afters for ' . implode(', ', $successes) . '.');
    }
    if ($failures !== []) {
        flash_set('error', 'Some angle corrections could not be generated. ' . implode(' ', $failures));
    }
} catch (Throwable $e) {
    esm_log('smile_design_generate', 'Batch AI correction action failed.', [
        'case_id' => $caseId,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    flash_set('error', 'All-angle correction could not be generated right now. The issue was logged.');
}

redirect(base_url('smile-design/cases/' . $caseId . '#generate'));
