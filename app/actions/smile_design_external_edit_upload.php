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

$sourceVersionId = (int)post('source_after_version_id', 0);
$sourceVersion = $sourceVersionId > 0
    ? db_one('SELECT * FROM smile_after_versions WHERE id = :id LIMIT 1', ['id' => $sourceVersionId])
    : null;
if (!$sourceVersion) {
    flash_set('error', 'The source after version could not be found.');
    redirect(base_url('smile-design/cases'));
}

$caseId = (int)($sourceVersion['case_id'] ?? 0);
$result = smile_design_create_external_edit_version($caseId, $sourceVersionId, $_FILES['after_photo'] ?? [], [
    'version_title' => post('version_title', ''),
    'notes' => post('notes', ''),
], auth_user_id());

if (empty($result['ok'])) {
    flash_set('error', (string)($result['message'] ?? 'The corrected image could not be uploaded.'));
    redirect(base_url('smile-design/adjust?case_id=' . $caseId . '&version_id=' . $sourceVersionId));
}

flash_set('success', 'External edit saved as a new after version. The original remains unchanged.');
redirect(base_url('smile-design/cases/' . $caseId . '#generate'));
