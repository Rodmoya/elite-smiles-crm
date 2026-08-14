<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_marketing_access();
require_csrf();

$draftId = (int)post('draft_id', 0);
$status = (string)post('status', '');
$labels = social_studio_status_labels();

if (!isset($labels[$status]) || $draftId <= 0) {
    flash_set('error', 'Could not update that social draft.');
    redirect(base_url('social-studio.php'));
}

$draft = db_one('SELECT status, image_storage_key, branded_image_storage_key, generation_status FROM social_studio_drafts WHERE id=:id LIMIT 1', ['id' => $draftId]);
if ($status === 'approved' && (!$draft || (trim((string)($draft['branded_image_storage_key'] ?? '')) === '' && trim((string)($draft['image_storage_key'] ?? '')) === ''))) {
    flash_set('error', 'Generate a finished image before approving this draft.');
    redirect(base_url('social-studio.php'));
}
if ($status === 'scheduled' && (string)($draft['status'] ?? '') !== 'approved') {
    flash_set('error', 'Approve the finished post before scheduling it.');
    redirect(base_url('social-studio.php'));
}

$ok = social_studio_update_status($draftId, $status, (int)(auth_user_id() ?: 0));
if ($ok && $status === 'approved') {
    flash_set('success', 'Post approved. Choose its publishing date and time, or use Fill week.');
    redirect(base_url('social-studio.php?view=calendar&draft=' . $draftId));
}
flash_set($ok ? 'success' : 'error', $ok ? ('Draft marked ' . strtolower($labels[$status]) . '.') : 'No draft was updated.');
redirect(base_url('social-studio.php'));
