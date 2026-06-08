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
if ($versionIds === []) {
    flash_set('error', 'No after versions were included in this set.');
    redirect($returnUrl);
}

$selectedLabels = [];
foreach ($versionIds as $versionId) {
    $version = db_one(
        'SELECT * FROM smile_after_versions WHERE id = :id AND case_id = :case_id AND archived = 0 LIMIT 1',
        ['id' => $versionId, 'case_id' => $caseId]
    );
    if (!$version) {
        continue;
    }

    smile_design_set_after_flag($versionId, 'selected_by_doctor', true, auth_user_id());
    $photoType = (string)($version['photo_type'] ?? 'front');
    $selectedLabels[] = (smile_design_photo_type_options()[$photoType] ?? $photoType) . ' #' . (string)$version['version_number'];
}

if ($selectedLabels === []) {
    flash_set('error', 'This set could not be selected. It may have been archived or removed.');
    redirect($returnUrl);
}

flash_set('success', 'Simulation set selected: ' . implode(', ', $selectedLabels) . '.');
redirect($returnUrl);
