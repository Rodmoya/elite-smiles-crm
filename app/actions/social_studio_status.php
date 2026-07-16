<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_auth();
require_csrf();

$draftId = (int)post('draft_id', 0);
$status = (string)post('status', '');
$labels = social_studio_status_labels();

if (!isset($labels[$status]) || $draftId <= 0) {
    flash_set('error', 'Could not update that social draft.');
    redirect(base_url('social-studio.php'));
}

$ok = social_studio_update_status($draftId, $status, (int)(auth_user_id() ?: 0));
flash_set($ok ? 'success' : 'error', $ok ? ('Draft marked ' . strtolower($labels[$status]) . '.') : 'No draft was updated.');
redirect(base_url('social-studio.php'));
