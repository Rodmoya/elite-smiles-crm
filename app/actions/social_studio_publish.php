<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_publisher.php';

require_auth();
require_csrf();

$draftId = (int)post('draft_id', 0);
$mode = strtolower(trim((string)post('mode', 'now')));
if ($draftId <= 0 || !in_array($mode, ['now', 'schedule'], true)) {
    flash_set('error', 'Could not process that social post.');
    redirect(base_url('social-studio.php'));
}

if ($mode === 'schedule') {
    $result = social_studio_schedule_draft($draftId, trim((string)post('scheduled_at', '')));
} else {
    $result = social_studio_publish_draft($draftId);
}

flash_set(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Meta publishing failed.'));
redirect(base_url('social-studio.php?draft=' . $draftId));

