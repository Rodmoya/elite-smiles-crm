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

$beforePhotoId = (int)post('before_photo_id', 0);
if ($beforePhotoId <= 0) {
    $primary = smile_design_primary_before_photo($caseId);
    $beforePhotoId = (int)($primary['id'] ?? 0);
}

$result = smile_design_run_case_analysis($caseId, $beforePhotoId, auth_user_id(), true);
if (empty($result['ok'])) {
    flash_set('error', (string)($result['message'] ?? 'AI case analysis failed.'));
} else {
    flash_set('success', 'AI case analysis updated.');
}

redirect(base_url('smile-design/cases/' . $caseId));
