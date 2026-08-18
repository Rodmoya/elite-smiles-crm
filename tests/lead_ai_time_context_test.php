<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/leads/lead_ai.php';

function lead_ai_time_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$utc = new DateTimeImmutable('2026-08-19 01:30:00', new DateTimeZone('UTC'));
$context = lead_ai_current_time_context($utc);
lead_ai_time_expect(($context['date'] ?? '') === '2026-08-18', 'Draft context must use the Denver calendar date.');
lead_ai_time_expect(($context['time'] ?? '') === '7:30 PM', 'Draft context must use the Denver local time.');
lead_ai_time_expect(($context['day_of_week'] ?? '') === 'Tuesday', 'Draft context must include the current weekday.');
lead_ai_time_expect(($context['timezone'] ?? '') === APP_TIMEZONE, 'Draft context must identify the authoritative timezone.');
lead_ai_time_expect(!empty($context['past_dates_forbidden']), 'Draft context must explicitly forbid past dates.');
lead_ai_time_expect(str_contains(lead_ai_system_prompt(), 'Never offer, suggest, or describe a date/time earlier than current_time'), 'SMS drafting prompt must reject past availability.');
lead_ai_time_expect(str_contains(lead_ai_email_system_prompt(), 'Never offer, suggest, or describe a date/time earlier than current_time'), 'Email drafting prompt must reject past availability.');
$smsDraftContext = json_decode(lead_ai_context(['id' => 0], '', 'draft_sms'), true) ?: [];
$emailDraftContext = json_decode(lead_ai_email_context(['id' => 0], '', 'draft_email'), true) ?: [];
lead_ai_time_expect(!empty($smsDraftContext['current_time']['datetime']), 'SMS Draft must receive the authoritative current timestamp.');
lead_ai_time_expect(!empty($emailDraftContext['current_time']['datetime']), 'Email Draft must receive the authoritative current timestamp.');
lead_ai_time_expect(!empty($smsDraftContext['scheduling_context']['past_dates_forbidden']), 'SMS scheduling context must forbid past dates.');
lead_ai_time_expect(!empty($emailDraftContext['scheduling_context']['availability_requires_operator_confirmation']), 'Email scheduling context must require operator-confirmed availability.');

echo "Lead AI time-context tests passed.\n";
