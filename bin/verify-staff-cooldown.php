<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require $argv[1];
date_default_timezone_set('America/Denver');
$now = strtotime('2026-09-23 12:00:00');
$lead = ['status' => 'contacted', 'sms_opt_status' => 'opted_in', 'email_opt_status' => 'subscribed', 'created_at' => '2026-09-22 12:00:00'];
function verify_cooldown(bool $passed, string $label): void {
    if (!$passed) throw new RuntimeException($label);
}
foreach (['sms', 'email'] as $previous) {
    foreach (['sms', 'email'] as $channel) {
        $history = [['direction' => 'outbound', 'channel' => $previous, 'body' => 'Earlier contact', 'created_at' => '2026-09-23 11:59:00', 'delivery_status' => 'delivered']];
        verify_cooldown(lead_outreach_decision($lead, $history, $channel, false, false, $now) === '', "Staff $channel after $previous must be allowed");
        verify_cooldown(lead_outreach_decision($lead, $history, $channel, true, false, $now) === 'cross_channel_48_hour_cooldown', "Agent $channel after $previous must wait");
        foreach (['sms_opt_status' => 'opted_out', 'email_opt_status' => 'unsubscribed'] as $key => $status) {
            verify_cooldown(lead_outreach_decision(array_replace($lead, [$key => $status]), $history, $channel, false, false, $now) === 'contact_suppressed', 'Staff cannot bypass opt-outs');
        }
        $history[0]['created_at'] = '2026-09-21 12:00:00';
        verify_cooldown(lead_outreach_decision($lead, $history, $channel, true, false, $now) === '', 'Agent eligible at exact 48-hour boundary');
    }
}
echo "Staff SMS/email, cross-channel automation, opt-outs and exact 48-hour boundary passed.\n";
