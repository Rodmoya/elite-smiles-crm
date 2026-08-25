<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/leads/lead_agent.php';

function calendar_hours_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$office = lead_agent_office_minutes();
calendar_hours_expect($office === ['open' => 540, 'last_start' => 1080, 'close' => 1110, 'slot' => 30], 'Lead Agent scheduling must offer 9:00 AM through a final 6:00 PM start in 30-minute increments.');

$source = (string) file_get_contents(dirname(__DIR__) . '/app/partials/dashboard_pipeline.php');
calendar_hours_expect(str_contains($source, 'const calendarOpenHour = 9;'), 'CRM calendar must begin at 9:00 AM.');
calendar_hours_expect(str_contains($source, 'const calendarCloseHour = 18.5;'), 'CRM calendar must preserve a complete final 6:00 PM appointment block.');
calendar_hours_expect(str_contains($source, "const calendarSlotMinutes = 30;"), 'CRM calendar must use 30-minute increments.');
calendar_hours_expect(str_contains($source, "const calendarHoursLabel = '9:00 AM-6:00 PM';"), 'CRM calendar must display the approved scheduling hours.');

$slotCount = (int) floor((($office['close'] - $office['open']) / $office['slot']));
calendar_hours_expect($slotCount === 19, 'CRM calendar must expose exactly 19 starts from 9:00 AM through 6:00 PM.');

echo "CRM calendar hours tests passed.\n";
