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
    $scheduledAt = trim((string)post('scheduled_at', ''));
    if ($scheduledAt === '') {
        $scheduleDay = trim((string)post('schedule_day', ''));
        $scheduleTime = trim((string)post('schedule_time', ''));
        $scheduledAt = $scheduleDay !== '' && $scheduleTime !== '' ? $scheduleDay . ' ' . $scheduleTime : '';
    }
    $result = social_studio_schedule_draft($draftId, $scheduledAt);
} else {
    $result = social_studio_publish_draft($draftId);
}

flash_set(!empty($result['ok']) ? 'success' : 'error', (string)($result['message'] ?? 'Meta publishing failed.'));
if ($mode === 'schedule') {
    $week = trim((string)post('week', ''));
    redirect(base_url('social-studio.php?view=calendar' . ($week !== '' ? '&week=' . rawurlencode($week) : '') . '&draft=' . $draftId));
}
redirect(base_url('social-studio.php?view=published&draft=' . $draftId));

