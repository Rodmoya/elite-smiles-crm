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
$action = (string)post('after_action', '');
$version = db_one('SELECT * FROM smile_after_versions WHERE id = :id LIMIT 1', ['id' => $versionId]);
if (!$version) {
    flash_set('error', 'AFTER version not found.');
    redirect(base_url('smile-design/cases'));
}
$caseId = (int)$version['case_id'];
$casePageUrl = base_url('smile-design/cases/' . $caseId);
$returnUrl = trim((string)post('return_url', ''));
if (
    $returnUrl === ''
    || !str_starts_with($returnUrl, $casePageUrl)
    || str_contains($returnUrl, "\r")
    || str_contains($returnUrl, "\n")
) {
    $returnUrl = $casePageUrl . '#generate';
}
$flag = match ($action) {
    'select' => 'selected_by_doctor',
    'approve_preview' => 'approved_for_patient_preview',
    'approve_gallery' => 'approved_for_office_gallery',
    'approve_marketing' => 'approved_for_marketing',
    'unapprove_preview' => 'approved_for_patient_preview',
    'unapprove_gallery' => 'approved_for_office_gallery',
    'archive' => 'archived',
    default => '',
};
if ($action === 'delete') {
    $result = smile_design_delete_after_version($versionId, auth_user_id());
    flash_set($result['ok'] ? 'success' : 'error', (string)($result['ok'] ? 'AFTER version deleted.' : ($result['message'] ?? 'Could not delete AFTER version.')));
} elseif ($flag !== '') {
    $enabled = !in_array($action, ['unapprove_preview', 'unapprove_gallery'], true);
    smile_design_set_after_flag($versionId, $flag, $enabled, auth_user_id());
    flash_set('success', 'AFTER version updated.');
}
redirect($returnUrl);
