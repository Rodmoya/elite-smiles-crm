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
$history = [
    ['direction' => 'outbound', 'channel' => 'sms', 'body' => 'Initial SMS', 'created_at' => '2026-09-22 15:27:00', 'delivery_status' => 'delivered'],
    ['direction' => 'outbound', 'channel' => 'email', 'body' => 'Initial email', 'created_at' => '2026-09-22 15:27:00', 'delivery_status' => 'sent'],
    ['direction' => 'outbound', 'channel' => 'sms', 'body' => 'Second SMS', 'created_at' => '2026-09-22 19:37:00', 'delivery_status' => 'delivered'],
];
foreach (['sms', 'email'] as $channel) {
    // Mirrors the reported two-SMS/one-email lead. No staff override flags.
    verify_cooldown(lead_outreach_decision($lead, $history, $channel, false, false, $now) === '', "Staff $channel allowed after three unanswered messages");
    verify_cooldown(lead_outreach_decision($lead, $history, $channel, true, false, $now + 3 * 86400) === 'silent_attempt_limit_reached', 'Agent attempt limit remains enforced after cooldown');
    $oldLead = array_replace($lead, ['created_at' => '2026-08-01 12:00:00']);
    verify_cooldown(lead_outreach_decision($oldLead, [], $channel, false, false, $now) === '', 'Staff may review and contact an older lead');
    verify_cooldown(lead_outreach_decision($oldLead, [], $channel, true, false, $now) === 'staff_reactivation_review_required', 'Agent cannot restart older leads');
    $conversation = [
        ['direction' => 'inbound', 'channel' => $channel, 'body' => 'Tell me more', 'created_at' => '2026-09-20 09:00:00'],
        ['direction' => 'outbound', 'channel' => $channel, 'body' => 'Staff response', 'created_at' => '2026-09-20 10:00:00', 'delivery_status' => 'delivered'],
    ];
    verify_cooldown(lead_outreach_decision($lead, $conversation, $channel, false, false, $now) === '', 'Staff can continue a reviewed conversation');
    verify_cooldown(lead_outreach_decision($lead, $conversation, $channel, true, false, $now) === 'conversation_requires_staff_plan', 'Automatic conversation gate preserved');
    foreach (['STOP' => 'opt_out', "I'll contact you when ready" => 'patient_will_initiate'] as $body => $reason) {
        $held = [['direction' => 'inbound', 'channel' => $channel, 'body' => $body, 'created_at' => '2026-09-23 11:59:00']];
        verify_cooldown(lead_outreach_decision($lead, $held, $channel, false, false, $now) === $reason, 'Patient-requested restriction preserved');
    }
    $failed = [['direction' => 'outbound', 'channel' => $channel, 'body' => 'Earlier attempt', 'created_at' => '2026-09-22 11:00:00', 'delivery_status' => 'failed']];
    verify_cooldown(lead_outreach_decision($lead, $failed, $channel, false, false, $now) === 'delivery_review_required', 'Delivery recovery hold preserved');
    verify_cooldown(lead_outreach_decision(array_replace($lead, ['consultation_date' => '2026-09-24 10:00:00']), [], $channel, false, false, $now) === 'outreach_on_hold', 'Appointment restriction preserved');
}
echo "Staff SMS/email, three-attempt sequence, older leads, conversation gates, shared restrictions and automated cooldown tests passed.\n";
