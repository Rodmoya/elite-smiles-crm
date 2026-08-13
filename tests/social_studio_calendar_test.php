<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/social_studio/social_studio_service.php';

function social_calendar_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$week = social_studio_week_start('2026-08-12');
social_calendar_assert($week->format('Y-m-d H:i:s') === '2026-08-10 00:00:00', 'A requested week must begin on Monday.');
$days = social_studio_week_days($week);
social_calendar_assert(count($days) === 7, 'The content calendar must always contain seven days.');
social_calendar_assert($days[0]->format('Y-m-d') === '2026-08-10', 'The first timeline day must be Monday.');
social_calendar_assert($days[6]->format('Y-m-d') === '2026-08-16', 'The final timeline day must be Sunday.');

$page = file_get_contents(dirname(__DIR__) . '/social-studio.php') ?: '';
$scheduleAction = file_get_contents(dirname(__DIR__) . '/app/actions/social_studio_schedule_week.php') ?: '';
$publishAction = file_get_contents(dirname(__DIR__) . '/app/actions/social_studio_publish.php') ?: '';
$statusAction = file_get_contents(dirname(__DIR__) . '/app/actions/social_studio_status.php') ?: '';
social_calendar_assert(str_contains($page, 'Create &amp; review'), 'The Create and review workspace tab is missing.');
social_calendar_assert(str_contains($page, 'Content calendar'), 'The Content calendar workspace tab is missing.');
social_calendar_assert(str_contains($page, 'Published posts'), 'The Published archive is missing.');
social_calendar_assert(str_contains($page, 'Approve &amp; schedule'), 'Approval must guide the user directly to scheduling.');
social_calendar_assert(str_contains($page, 'Fill week with approved posts'), 'The weekly auto-fill control is missing.');
social_calendar_assert(str_contains($scheduleAction, 'social_studio_schedule_draft'), 'Weekly scheduling must use the guarded Meta scheduling service.');
social_calendar_assert(str_contains($scheduleAction, 'status="approved" AND scheduled_at IS NULL'), 'Weekly scheduling must only use approved unscheduled posts.');
social_calendar_assert(str_contains($page, 'data-schedule-card'), 'Approved posts must be draggable calendar cards.');
social_calendar_assert(str_contains($page, 'data-calendar-day'), 'Each calendar day must be a drop target.');
social_calendar_assert(str_contains($page, 'data-schedule-time'), 'Scheduling must use a time dropdown.');
social_calendar_assert(str_contains($page, '$minutes = 0; $minutes < 24 * 60'), 'The scheduling dropdown must cover the full 24-hour day.');
social_calendar_assert(str_contains($page, 'Each day can hold multiple posts'), 'The calendar must explain that a day can contain multiple posts.');
social_calendar_assert(str_contains($page, 'Choose day without dragging'), 'Drag scheduling must provide an accessible non-drag fallback.');
social_calendar_assert(str_contains($publishAction, "post('schedule_day'"), 'The publisher must accept a day selected by the calendar.');
social_calendar_assert(str_contains($publishAction, 'view=calendar'), 'Scheduling must return to the content calendar.');
social_calendar_assert(str_contains($statusAction, 'image_storage_key'), 'Approval must accept the finished image visible in the review queue.');

echo "Social Studio calendar tests passed.\n";
