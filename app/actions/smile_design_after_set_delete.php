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

$rawIds = $_POST['after_version_ids'] ?? [];
if (!is_array($rawIds)) {
    $rawIds = [$rawIds];
}
$versionIds = array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn(int $id): bool => $id > 0)));

$result = smile_design_delete_after_set($caseId, $versionIds, auth_user_id());
if (!empty($result['ok'])) {
    $count = (int)($result['deleted_count'] ?? 0);
    flash_set('success', 'Deleted set with ' . (string)$count . ' after angle' . ($count === 1 ? '' : 's') . '.');
} else {
    flash_set('error', (string)($result['message'] ?? 'Could not delete this set.'));
}

redirect($returnUrl);
