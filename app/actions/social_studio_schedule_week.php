<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_publisher.php';

require_marketing_access();
require_csrf();

$weekStart = social_studio_week_start(trim((string)post('week_start', '')));
$publishTime = trim((string)post('publish_time', '10:30'));
$count = max(1, min(7, (int)post('count', 7)));
if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $publishTime)) {
    flash_set('error', 'Choose a valid publishing time.');
    redirect(base_url('social-studio.php?view=calendar&week=' . $weekStart->format('Y-m-d')));
}

$eligible = db_all('SELECT id FROM social_studio_drafts WHERE status="approved" AND scheduled_at IS NULL ORDER BY COALESCE(approved_at, created_at) ASC, id ASC LIMIT ' . $count);
$now = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
$slots = [];
foreach (social_studio_week_days($weekStart) as $day) {
    $slot = new DateTimeImmutable($day->format('Y-m-d') . ' ' . $publishTime, new DateTimeZone(APP_TIMEZONE));
    if ($slot > $now->modify('+1 minute')) {
        $slots[] = $slot;
    }
}

$scheduled = 0;
foreach ($eligible as $index => $draft) {
    if (!isset($slots[$index])) {
        break;
    }
    $result = social_studio_schedule_draft((int)$draft['id'], $slots[$index]->format('Y-m-d H:i:s'));
    if (!empty($result['ok'])) {
        $scheduled++;
    }
}

if ($scheduled > 0) {
    flash_set('success', $scheduled . ' approved post' . ($scheduled === 1 ? ' was' : 's were') . ' added to the weekly calendar.');
} elseif ($eligible === []) {
    flash_set('error', 'Approve at least one post before filling the week.');
} else {
    flash_set('error', 'This week has no future slots at that time. Choose next week or a later time.');
}
redirect(base_url('social-studio.php?view=calendar&week=' . $weekStart->format('Y-m-d')));
