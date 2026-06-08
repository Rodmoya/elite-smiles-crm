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
$action = (string)post('preview_action', 'create');
$case = smile_design_case($caseId);
if (!$case) {
    flash_set('error', 'Smile case not found.');
    redirect(base_url('smile-design/cases'));
}

if (in_array($action, ['revoke', 'disable', 'off'], true)) {
    smile_design_revoke_preview_links($caseId, auth_user_id());
    flash_set('success', 'Open preview link is off.');
} elseif ($action === 'delete') {
    $linkId = (int)post('preview_link_id', 0);
    if ($linkId <= 0 || !smile_design_delete_preview_link($caseId, $linkId, auth_user_id())) {
        flash_set('error', 'Preview link not found for deletion.');
    } else {
        flash_set('success', 'Preview link deleted.');
    }
} else {
    $result = smile_design_issue_or_reuse_preview_link($caseId, auth_user_id(), 14);
    $link = is_array($result['link'] ?? null) ? $result['link'] : null;
    $previewUrl = $link ? smile_design_preview_link_url($link) : null;
    smile_design_audit($caseId, !empty($result['created']) ? 'preview_link_created' : 'preview_link_reused', ['expires_days' => 14], auth_user_id());
    flash_set(
        'success',
        'Open preview link is on' . ($previewUrl ? ': ' . $previewUrl : '.')
    );
}

redirect(base_url('smile-design/cases/' . $caseId));
