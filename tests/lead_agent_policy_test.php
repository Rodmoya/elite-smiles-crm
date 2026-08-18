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
expect_true(lead_agent_classify_inbound('No thank you') === 'pause', 'A polite decline must stop automated follow-up.');
expect_true(lead_agent_classify_inbound('That is too far for me to travel') === 'pause', 'A distance-based decline must stop automated follow-up.');
expect_true(lead_agent_classify_inbound('I have swelling and pain') === 'needs_attention', 'Clinical concern should require human review.');
expect_true(lead_agent_classify_inbound('I need an appointment because I have pain and swelling') === 'needs_attention', 'Clinical urgency must override scheduling language.');
expect_true(lead_agent_policy_flags('Your treatment price is $500') === ['treatment_cost_language'], 'Treatment price language should be blocked.');
expect_true(lead_agent_policy_flags('Would mornings or afternoons work better?') === [], 'Approved scheduling language should pass.');

$preference = lead_agent_scheduling_preferences('Tuesday afternoon works best for me.');
expect_true($preference['day'] === 'tuesday' && $preference['period'] === 'afternoon' && !empty($preference['has_preference']), 'Scheduling preference should capture day and time of day.');
$specificTime = lead_agent_scheduling_preferences('Can I come Thursday at 4:30 PM?');
expect_true($specificTime['day'] === 'thursday' && $specificTime['specific_time'] === '4:30 PM', 'A specific requested time should be captured.');
$spanishPreference = lead_agent_scheduling_preferences('El martes por la tarde me funciona mejor.');
expect_true($spanishPreference['day'] === 'tuesday' && $spanishPreference['period'] === 'afternoon', 'Spanish day and time-of-day preferences should remain in the scheduling flow.');
$nextWeekPreference = lead_agent_scheduling_preferences('Next week works for me.');
expect_true($nextWeekPreference['day'] === 'next week' && !empty($nextWeekPreference['has_preference']), 'A next-week preference must not trigger the same scheduling question again.');
$acknowledgment = lead_agent_scheduling_acknowledgment(['full_name' => 'Carlos Example'], $preference);
expect_true(str_contains($acknowledgment, 'Let me check whether that is available') && substr_count($acknowledgment, '?') === 0, 'A complete preference should receive a natural acknowledgment without another question.');
$preferenceQuestion = lead_agent_scheduling_acknowledgment(['full_name' => 'Carlos Example'], lead_agent_scheduling_preferences('I want to schedule.'));
expect_true(substr_count($preferenceQuestion, '?') === 1 && str_contains($preferenceQuestion, 'mornings or afternoons'), 'A scheduling request without a preference should ask one simple question.');
$option1 = '2026-08-19 15:30:00';
$option2 = '2026-08-20 17:00:00';
$offer = lead_agent_availability_offer_message(['full_name' => 'Carlos Example'], $option1, $option2);
expect_true(substr_count($offer, '?') === 1 && str_contains($offer, 'Wednesday, August 19 at 3:30 PM') && str_contains($offer, 'Thursday, August 20 at 5:00 PM'), 'Availability offer should contain exactly two clear options and one question.');
expect_true(lead_agent_match_availability_selection('Wednesday at 3:30 works', $option1, $option2) === 1, 'Lead should be able to select the first option naturally.');
expect_true(lead_agent_match_availability_selection('The second option is better', $option1, $option2) === 2, 'Lead should be able to select the second option by position.');
expect_true(lead_agent_parse_dob('My birthday is 03/19/1999') === '1999-03-19', 'DOB should normalize only after a slot is selected.');

$eligibleBackfill = [
    'full_name' => 'Real Lead',
    'status' => 'contacted',
    'phone' => '+18015550199',
    'email' => 'lead@example.com',
    'sms_opt_status' => 'unknown',
    'email_opt_status' => 'subscribed',
    'last_outbound_at' => '2026-08-05 10:00:00',
    'consultation_status' => 'requested',
];
expect_true(lead_agent_backfill_ineligible_reason($eligibleBackfill) === '', 'A completed first touch should be eligible for safe backfill.');
expect_true(lead_agent_backfill_ineligible_reason(array_merge($eligibleBackfill, ['consultation_status' => 'scheduling'])) === 'scheduling_or_consultation', 'An active scheduling record must not be enrolled.');
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
expect_true(lead_agent_followup_context_reason(['id' => 1], ['status' => 'ready_to_schedule']) === 'conversation_owned_or_paused', 'Follow-up must stay silent after a scheduling handoff.');
expect_true(lead_agent_followup_context_reason(['id' => 1], ['status' => 'human_takeover', 'human_takeover' => 1]) === 'conversation_owned_or_paused', 'Follow-up must stay silent while a human owns the conversation.');
expect_true(lead_agent_lead_is_already_scheduled(['status' => 'consultation_booked']), 'A booked pipeline stage must close the scheduling handoff.');
expect_true(lead_agent_lead_is_already_scheduled(['status' => 'contacted', 'consultation_status' => 'scheduled']), 'A scheduled consultation status must close the scheduling handoff.');
expect_true(lead_agent_lead_is_already_scheduled(['status' => 'contacted', 'consultation_date' => '2026-08-20 10:00:00']), 'A saved consultation date must close the scheduling handoff.');
expect_true(!lead_agent_lead_is_already_scheduled(['status' => 'contacted', 'consultation_status' => 'requested']), 'A requested consultation without an appointment must remain ready for scheduling.');
expect_true(lead_agent_guardrail_reason(
    ['id' => 0, 'status' => 'contacted', 'consultation_status' => 'scheduled'],
    ['status' => 'active', 'human_takeover' => 0, 'started_at' => '2026-08-01 09:00:00'],
    ['channel' => 'sms']
) === 'terminal_or_human_stage', 'A scheduled consultation status must block routine cadence even when the legacy lead stage is stale.');
expect_true(lead_agent_followup_context_reason(['id' => 0], ['status' => 'engaged', 'scheduling_phase' => 'awaiting_preference']) === '', 'An unanswered request for a missing scheduling preference must remain eligible for follow-up.');
expect_true(lead_agent_followup_context_reason(['id' => 0], ['status' => 'engaged', 'scheduling_phase' => 'awaiting_availability']) === 'scheduling_in_progress', 'The agent must stay silent while Rod is checking availability.');

$plan = lead_agent_cadence_plan();
expect_true(count($plan) === 11, 'Cadence should define the twice-daily five-day sprint and the first daily follow-up.');
expect_true($plan[1]['hours'] === 8 && $plan[1]['channel'] === 'sms', 'A second SMS must wait at least eight hours.');
expect_true($plan[10]['hours'] === 114 && $plan[10]['phase'] === 'active_sprint', 'The active sprint should provide two follow-up opportunities per day through day five.');
expect_true($plan[11]['hours'] === 138 && $plan[11]['phase'] === 'daily_follow_up', 'Daily follow-up should begin after the five-day sprint.');
$dailyStep = lead_agent_step_schedule('2026-08-01 09:00:00', 14);
expect_true($dailyStep['hours'] === 210 && $dailyStep['phase'] === 'daily_follow_up', 'Long-term follow-up must continue every 24 hours instead of tapering to twice weekly.');

$dayFiveLimit = lead_agent_daily_outbound_limit('2026-08-01 10:00:00', new DateTimeImmutable('2026-08-05 18:00:00', new DateTimeZone(APP_TIMEZONE)));
$daySixLimit = lead_agent_daily_outbound_limit('2026-08-01 10:00:00', new DateTimeImmutable('2026-08-06 09:00:00', new DateTimeZone(APP_TIMEZONE)));
expect_true($dayFiveLimit === 2, 'The agent may make up to two total outreach attempts per day through day five.');
expect_true($daySixLimit === 1, 'The agent must reduce to one outreach attempt per day after day five.');

$incremental = lead_agent_incremental_schedule('2026-08-05 17:00:00', 1);
expect_true($incremental['at'] === '2026-08-06 08:00:00', 'An overdue catch-up must schedule the next step from the send time instead of an expired start date.');

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

$fallback = lead_agent_safe_contextual_fallback([
    'full_name' => 'Taylor Example',
    'procedure_interest' => 'Veneers',
], 'sms', 13);
expect_true((string) ($fallback['draft_source'] ?? '') === 'approved_fallback', 'Long-term nurture must have an approved fallback when AI drafting is unavailable.');
expect_true(str_contains((string) $fallback['body'], 'whenever you are ready'), 'Fallback nurture should keep the door open without pressure.');
expect_true(str_contains((string) $fallback['body'], 'veneers consultation'), 'Fallback nurture should preserve the known treatment interest.');
expect_true(lead_agent_policy_flags((string) $fallback['body']) === [], 'Approved fallback nurture must pass policy gates.');

$preferenceFallback = lead_agent_safe_contextual_fallback([
    'full_name' => 'Taylor Example',
    'procedure_interest' => 'Veneers',
    'scheduling_preferred_day' => 'Wednesday',
    'scheduling_preferred_time' => 'morning',
], 'sms', 13);
expect_true(str_contains((string) $preferenceFallback['body'], 'Wednesday morning'), 'Fallback nurture must remember previously supplied scheduling preferences.');
expect_true(!str_contains((string) $preferenceFallback['body'], 'mornings or afternoons'), 'Fallback nurture must not repeat an answered preference question.');

$reportCopy = lead_agent_report_copy('2026-08-05', [
    'actions_completed' => 2,
    'sms_sent' => 1,
    'emails_sent' => 1,
    'outbound_total' => 2,
    'inbound_handled' => 0,
    'ready_to_schedule_today' => 0,
    'needs_attention_today' => 0,
]);
expect_true(str_contains($reportCopy['executive_summary'], '1 text, 1 email'), 'Executive summary copy should use singular channel labels correctly.');

$namedReportCopy = lead_agent_report_copy('2026-08-05', [
    'actions_completed' => 1,
    'sms_sent' => 1,
    'emails_sent' => 0,
    'outbound_total' => 1,
    'inbound_handled' => 0,
    'ready_to_schedule_today' => 1,
    'needs_attention_today' => 1,
    'scheduling_leads' => [['id' => 7, 'full_name' => 'Alex Schedule']],
    'exception_leads' => [['id' => 8, 'full_name' => 'Jordan Review']],
]);
expect_true(str_contains($namedReportCopy['executive_summary'], 'Alex Schedule'), 'Executive summary should name scheduling handoffs.');
expect_true(str_contains($namedReportCopy['executive_summary'], 'Jordan Review'), 'Executive summary should name human-review exceptions.');
$linkedReportCopy = lead_agent_linked_report_text($namedReportCopy['executive_summary'], [
    'scheduling_leads' => [['id' => 7, 'full_name' => 'Alex Schedule']],
    'exception_leads' => [['id' => 8, 'full_name' => 'Jordan Review']],
]);
expect_true(str_contains($linkedReportCopy, 'leads.php?lead_id=7'), 'Scheduling names should use the Leads module parameter that opens the record.');
expect_true(str_contains($linkedReportCopy, 'leads.php?lead_id=8'), 'Exception names should use the Leads module parameter that opens the record.');

$completePreference = lead_agent_scheduling_preferences('Wenesdays in the mornign work for me.');
expect_true((string) ($completePreference['day'] ?? '') === 'wednesday', 'Common Wednesday misspellings should be understood.');
expect_true((string) ($completePreference['period'] ?? '') === 'morning', 'Common morning misspellings should be understood.');
expect_true(lead_agent_scheduling_preferences_complete($completePreference), 'A day and time period should be enough to request availability from Rod.');
$completeAcknowledgment = lead_agent_scheduling_acknowledgment(['full_name' => 'Maria Lopez'], $completePreference);
expect_true(str_contains($completeAcknowledgment, 'Let me check whether that is available'), 'Complete preferences must be acknowledged without promising availability.');
expect_true(!str_contains($completeAcknowledgment, 'should work'), 'The agent must never imply an unconfirmed time is available.');

$dayOnlyPreference = lead_agent_scheduling_preferences('Wednesday would work.');
expect_true(!lead_agent_scheduling_preferences_complete($dayOnlyPreference), 'A day without a time preference must not trigger Rod yet.');
$dayOnlyAcknowledgment = lead_agent_scheduling_acknowledgment(['full_name' => 'Maria Lopez'], $dayOnlyPreference);
expect_true(str_contains($dayOnlyAcknowledgment, 'Do you prefer morning or afternoon?'), 'Day-only scheduling should collect the missing time preference.');
expect_true(str_contains($dayOnlyAcknowledgment, '9:00 AM') && str_contains($dayOnlyAcknowledgment, '6:00 PM'), 'Scheduling guidance should explain consultation hours.');

$timeOnlyPreference = lead_agent_scheduling_preferences('Mornings are easier.');
expect_true(!lead_agent_scheduling_preferences_complete($timeOnlyPreference), 'A time period without a day must not trigger Rod yet.');
$timeOnlyAcknowledgment = lead_agent_scheduling_acknowledgment(['full_name' => 'Maria Lopez'], $timeOnlyPreference);
expect_true(str_contains($timeOnlyAcknowledgment, 'particular day this week'), 'Time-only scheduling should collect the missing day preference.');

$openAvailabilityAcknowledgment = lead_agent_scheduling_acknowledgment(['full_name' => 'Maria Lopez'], lead_agent_scheduling_preferences('What do you have available?'));
expect_true(str_contains($openAvailabilityAcknowledgment, 'check what we have available this week'), 'Open availability questions should receive a natural response.');
expect_true(str_contains($openAvailabilityAcknowledgment, 'mornings or afternoons'), 'Open availability questions should narrow the preference before involving Rod.');

$alignedMorning = lead_agent_align_contact_time(new DateTimeImmutable('2026-08-06 06:15:00', new DateTimeZone(APP_TIMEZONE)));
$alignedNight = lead_agent_align_contact_time(new DateTimeImmutable('2026-08-06 21:15:00', new DateTimeZone(APP_TIMEZONE)));
expect_true($alignedMorning->format('Y-m-d H:i') === '2026-08-06 08:00', 'Morning sends should move to 8 AM.');
expect_true($alignedNight->format('Y-m-d H:i') === '2026-08-07 08:00', 'Night sends should move to next-day 8 AM.');

$healthNow = new DateTimeImmutable('2026-08-07 11:00:00', new DateTimeZone(APP_TIMEZONE));
$healthyRun = lead_agent_run_health(['status' => 'completed', 'finished_at' => '2026-08-07 10:45:00', 'started_at' => '2026-08-07 10:44:59'], $healthNow);
$staleRun = lead_agent_run_health(['status' => 'completed', 'finished_at' => '2026-08-07 09:00:00', 'started_at' => '2026-08-07 08:59:59'], $healthNow);
expect_true($healthyRun['key'] === 'healthy', 'A recent completed worker run should be healthy.');
expect_true($staleRun['key'] === 'stale', 'A worker with no recent run should be stale.');

echo "Lead Agent policy tests passed.\n";
