<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/leads/lead_agent.php';

function expect_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

expect_true(lead_agent_classify_inbound('Can I come in Tuesday afternoon?') === 'ready_to_schedule', 'Scheduling preference should hand off.');
expect_true(lead_agent_classify_inbound('How much does it cost?') === 'cost_redirect', 'Cost question should use approved redirect.');
expect_true(lead_agent_classify_inbound('STOP') === 'opt_out', 'STOP should halt automation.');
expect_true(lead_agent_classify_inbound('I have swelling and pain') === 'needs_attention', 'Clinical concern should require human review.');
expect_true(lead_agent_policy_flags('Your treatment price is $500') === ['treatment_cost_language'], 'Treatment price language should be blocked.');
expect_true(lead_agent_policy_flags('Would mornings or afternoons work better?') === [], 'Approved scheduling language should pass.');

$eligibleBackfill = [
    'full_name' => 'Real Lead',
    'status' => 'contacted',
    'phone' => '+18015550199',
    'email' => 'lead@example.com',
    'sms_opt_status' => 'unknown',
    'email_opt_status' => 'subscribed',
    'last_outbound_at' => '2026-08-05 10:00:00',
];
expect_true(lead_agent_backfill_ineligible_reason($eligibleBackfill) === '', 'A completed first touch should be eligible for safe backfill.');
expect_true(lead_agent_backfill_ineligible_reason(array_merge($eligibleBackfill, ['full_name' => 'Rodrigo Moya'])) === 'internal_or_test_record', 'The owner record must never be enrolled.');
expect_true(lead_agent_backfill_ineligible_reason(array_merge($eligibleBackfill, ['last_inbound_at' => '2026-08-05 10:01:00'])) === 'newer_inbound_requires_review', 'A newer inbound reply must block backfill.');
expect_true(lead_agent_backfill_ineligible_reason(array_merge($eligibleBackfill, ['consultation_date' => '2026-08-10 09:00:00'])) === 'consultation_date_present', 'A consultation must block backfill.');
$emailOnlyBackfill = $eligibleBackfill;
$emailOnlyBackfill['sms_opt_status'] = 'dnd';
expect_true(lead_agent_backfill_ineligible_reason($emailOnlyBackfill) === '', 'An SMS DND lead with subscribed email remains email eligible.');
expect_true(lead_agent_sms_blocked($emailOnlyBackfill), 'DND must block every automated SMS path.');
$noChannelBackfill = $emailOnlyBackfill;
$noChannelBackfill['email_opt_status'] = 'unsubscribed';
expect_true(lead_agent_backfill_ineligible_reason($noChannelBackfill) === 'no_consented_delivery_channel', 'A lead without a consented channel must not be enrolled.');

$plan = lead_agent_cadence_plan();
expect_true(count($plan) === 13, 'Cadence should contain the active sprint, daily taper, and nurture steps.');
expect_true($plan[1]['hours'] === 3.5 && $plan[1]['channel'] === 'sms', 'First follow-up should be an SMS after 3.5 hours.');
expect_true($plan[7]['hours'] === 72 && $plan[7]['phase'] === 'active_sprint', 'Active sprint should cover the first 72 hours.');
expect_true($plan[11]['hours'] === 168 && $plan[11]['phase'] === 'daily_taper', 'Daily taper should run through day seven.');
expect_true($plan[12]['phase'] === 'twice_weekly', 'Long-term nurture should be twice weekly.');

$sampleLead = ['full_name' => 'Taylor Example'];
foreach ($plan as $step => $item) {
    $draft = lead_agent_approved_followup($sampleLead, (string) $item['channel'], (int) $step);
    expect_true(
        lead_agent_policy_flags((string) ($draft['subject'] ?? '') . ' ' . (string) ($draft['body'] ?? '')) === [],
        'Approved cadence content must pass policy gates at step ' . $step . '.'
    );
}
$redirect = lead_agent_cost_redirect($sampleLead, 'sms');
expect_true(lead_agent_policy_flags((string) $redirect['body']) === [], 'Approved question redirect must not discuss treatment cost.');

$alignedMorning = lead_agent_align_contact_time(new DateTimeImmutable('2026-08-06 06:15:00', new DateTimeZone(APP_TIMEZONE)));
$alignedNight = lead_agent_align_contact_time(new DateTimeImmutable('2026-08-06 21:15:00', new DateTimeZone(APP_TIMEZONE)));
expect_true($alignedMorning->format('Y-m-d H:i') === '2026-08-06 08:00', 'Morning sends should move to 8 AM.');
expect_true($alignedNight->format('Y-m-d H:i') === '2026-08-07 08:00', 'Night sends should move to next-day 8 AM.');

echo "Lead Agent policy tests passed.\n";
