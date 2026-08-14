<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/db.php';

require_marketing_access();
require_csrf();

$draftId = (int)post('draft_id', 0);
$caption = trim((string)post('caption', ''));
$hashtags = trim((string)post('hashtags', ''));
if ($draftId <= 0 || $caption === '') {
    flash_set('error', 'A caption is required before saving the draft.');
    redirect(base_url('social-studio.php'));
}

$ok = db_execute('UPDATE social_studio_drafts SET caption=:caption, hashtags=:hashtags WHERE id=:id AND status IN ("draft", "review", "approved") LIMIT 1', [
    'id' => $draftId,
    'caption' => $caption,
    'hashtags' => $hashtags,
]);
flash_set($ok ? 'success' : 'error', $ok ? 'Draft copy saved.' : 'That draft could not be updated.');
redirect(base_url('social-studio.php'));
