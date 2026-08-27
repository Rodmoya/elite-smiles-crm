<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/leads/lead_agent.php';
require_once dirname(__DIR__) . '/app/leads/lead_ai.php';

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
expect_true(lead_agent_classify_inbound('Quiero agendar una cita el martes por la tarde.') === 'ready_to_schedule', 'Spanish scheduling intent must stay in the deterministic scheduling flow.');
expect_true(lead_agent_classify_inbound('No me interesa, gracias.') === 'pause', 'A Spanish decline must stop automated follow-up.');
expect_true(lead_agent_classify_inbound('Cuánto cuesta la consulta?') === 'cost_redirect', 'A Spanish cost question must use the safe cost redirect.');
expect_true(lead_agent_decline_kind('No thank you') === 'declined', 'An explicit decline must close the Scheduling pipeline record.');
expect_true(lead_agent_decline_kind('Maybe later') === 'deferred', 'A timing deferral must not be treated as a permanent rejection.');
expect_true(lead_agent_classify_inbound('That is too far for me to travel') === 'pause', 'A distance-based decline must stop automated follow-up.');
expect_true(lead_agent_classify_inbound('I have swelling and pain') === 'needs_attention', 'Clinical concern should require human review.');
expect_true(lead_agent_classify_inbound('I need an appointment because I have pain and swelling') === 'needs_attention', 'Clinical urgency must override scheduling language.');
expect_true(lead_call_consent_requested('Yes, a call would be easier for me.'), 'An affirmative acceptance of an offered call must count as call consent.');
expect_true(lead_call_consent_requested('Can you please call me tomorrow?'), 'A direct call request must count as call consent.');
expect_true(!lead_call_consent_requested("Please don't call; text me instead."), 'A text-only preference must never be mistaken for call consent.');
expect_true(!lead_call_consent_requested('What is your phone number?'), 'Mentioning a phone without requesting a call is not call consent.');
expect_true(lead_agent_classify_inbound('A call would be easier for me.') === 'needs_attention', 'Accepting a call offer must route to human attention.');
expect_true(lead_agent_policy_flags('Your treatment price is $500') === ['treatment_cost_language'], 'Treatment price language should be blocked.');
expect_true(lead_agent_policy_flags('Would mornings or afternoons work better?') === [], 'Approved scheduling language should pass.');
expect_true(lead_agent_policy_flags('If a call is easier, tell me a good time.') === [], 'The agent may offer a call without promising one.');
expect_true(lead_agent_policy_flags('I will call you this afternoon.') === ['unapproved_call_commitment'], 'The agent must not commit to an unrequested call.');
expect_true(lead_agent_policy_flags('Would Tuesday work? Or would Wednesday be better?') === ['multiple_questions'], 'Automated copy must never ask multiple questions at once.');
expect_true(lead_agent_policy_flags('I have you scheduled for Tuesday at 2 PM.') === ['unverified_booking_claim'], 'Lead Agent must never claim an appointment is booked before the scheduling workflow confirms it.');
expect_true(!lead_first_touch_requires_attention('new_lead', false, false, strtotime('+1 day')), 'A new lead with a future automated follow-up must not receive a premature red attention halo.');
expect_true(lead_first_touch_requires_attention('new_lead', true, false, strtotime('-1 minute')), 'A genuinely due first-day follow-up should remain actionable.');
expect_true(lead_first_touch_requires_attention('new_lead', false, false, null), 'A first touch without any saved follow-up schedule should remain actionable after the grace period.');

$preference = lead_agent_scheduling_preferences('Tuesday afternoon works best for me.');
expect_true($preference['day'] === 'tuesday' && $preference['period'] === 'afternoon' && !empty($preference['has_preference']), 'Scheduling preference should capture day and time of day.');
$specificTime = lead_agent_scheduling_preferences('Can I come Thursday at 4:30 PM?');
expect_true($specificTime['day'] === 'thursday' && $specificTime['specific_time'] === '4:30 PM', 'A specific requested time should be captured.');
$spanishPreference = lead_agent_scheduling_preferences('El martes por la tarde me funciona mejor.');
expect_true($spanishPreference['day'] === 'tuesday' && $spanishPreference['period'] === 'afternoon', 'Spanish day and time-of-day preferences should remain in the scheduling flow.');
$nameOnlyLanguage = lead_language_detect_message_signal('Vania Mendez Perez');
expect_true(($nameOnlyLanguage['language'] ?? '') === 'unknown', 'A person\'s name must never be treated as a language signal.');
$spanishMessageLanguage = lead_language_detect_message_signal('Hola, me interesa una consulta para carillas.');
expect_true(($spanishMessageLanguage['language'] ?? '') === 'es' && ($spanishMessageLanguage['source'] ?? '') === 'inbound_detected', 'A clearly Spanish inbound message should establish a source-backed Spanish preference.');
$explicitSpanishLanguage = lead_language_detect_message_signal('Spanish please');
expect_true(($explicitSpanishLanguage['language'] ?? '') === 'es' && ($explicitSpanishLanguage['source'] ?? '') === 'inbound_explicit', 'An explicit Spanish request should be authoritative.');
$explicitEnglishLanguage = lead_language_detect_message_signal('English please');
expect_true(($explicitEnglishLanguage['language'] ?? '') === 'en' && ($explicitEnglishLanguage['source'] ?? '') === 'inbound_explicit', 'An explicit English request should be authoritative.');
expect_true(lead_language_preference(['full_name' => 'Maria Gutierrez']) === 'unknown', 'A Spanish-looking name must leave language unknown.');
expect_true(lead_language_preference(['notes' => 'Preferred language: Spanish']) === 'es', 'Legacy explicit landing-page language evidence must remain usable.');
$nextWeekPreference = lead_agent_scheduling_preferences('Next week works for me.');
expect_true($nextWeekPreference['day'] === 'next week' && !empty($nextWeekPreference['has_preference']), 'A next-week preference must not trigger the same scheduling question again.');
$rejectedNextWeek = lead_agent_scheduling_preferences("I'm interested. The next week is bad for me.");
expect_true($rejectedNextWeek['day'] === '', 'A rejected next-week window must not be saved as a positive scheduling preference.');
$positiveNextWeek = lead_agent_scheduling_preferences("I can't do this week, so next week works.");
expect_true($positiveNextWeek['day'] === 'next week', 'A positive next-week alternative must survive unrelated negative wording.');
$followingWeek = lead_agent_scheduling_preferences('The following week would be better.');
expect_true($followingWeek['day'] === 'following week', 'The following week must be understood as a scheduling preference.');
$acknowledgment = lead_agent_scheduling_acknowledgment(['full_name' => 'Carlos Example'], $preference);
expect_true(str_contains($acknowledgment, 'Let me check whether that is available') && substr_count($acknowledgment, '?') === 0, 'A complete preference should receive a natural acknowledgment without another question.');
$preferenceQuestion = lead_agent_scheduling_acknowledgment(['full_name' => 'Carlos Example'], lead_agent_scheduling_preferences('I want to schedule.'));
expect_true(substr_count($preferenceQuestion, '?') === 1 && str_contains($preferenceQuestion, 'mornings or afternoons'), 'A scheduling request without a preference should ask one simple question.');
$spanishAcknowledgment = lead_agent_scheduling_acknowledgment(['full_name' => 'Carlos Example', 'preferred_language' => 'es'], $spanishPreference);
expect_true(str_contains($spanishAcknowledgment, 'Permítame revisar') && substr_count($spanishAcknowledgment, '?') === 0, 'A Spanish scheduling preference must receive a Spanish acknowledgment.');
$option1 = '2026-08-19 15:30:00';
$option2 = '2026-08-20 17:00:00';
$offer = lead_agent_availability_offer_message(['full_name' => 'Carlos Example'], $option1, $option2);
expect_true(substr_count($offer, '?') === 1 && str_contains($offer, 'Wednesday, August 19 at 3:30 PM') && str_contains($offer, 'Thursday, August 20 at 5:00 PM'), 'Availability offer should contain exactly two clear options and one question.');
$spanishOffer = lead_agent_availability_offer_message(['full_name' => 'Carlos Example', 'preferred_language' => 'es'], $option1, $option2);
expect_true(substr_count($spanishOffer, '?') === 1 && str_contains($spanishOffer, 'miércoles, 19 de agosto') && str_contains($spanishOffer, 'jueves, 20 de agosto'), 'Spanish availability must present both appointment options in Spanish.');
expect_true(lead_agent_match_availability_selection('Wednesday at 3:30 works', $option1, $option2) === 1, 'Lead should be able to select the first option naturally.');
expect_true(lead_agent_match_availability_selection('The second option is better', $option1, $option2) === 2, 'Lead should be able to select the second option by position.');
expect_true(lead_agent_parse_dob('My birthday is 03/19/1999') === '1999-03-19', 'DOB should normalize only after a slot is selected.');
expect_true(lead_agent_parse_dob('Feb 6th') === '', 'A month and day without a year must never be stored as a DOB or mistaken for an appointment date.');
expect_true(lead_agent_classify_inbound('It is for my brother, his number is 385-230-1659') === 'needs_attention', 'A third-party referral must be handed off safely instead of asking the referring person for DOB.');

$mergedPreference = lead_agent_merge_scheduling_preferences(
    lead_agent_scheduling_preferences('Wednesday works.'),
    lead_agent_scheduling_preferences('Afternoons are better.')
);
expect_true(lead_agent_scheduling_preferences_complete($mergedPreference), 'Scheduling memory must combine a previously supplied day with a later time preference.');
expect_true($mergedPreference['day'] === 'wednesday' && $mergedPreference['period'] === 'afternoon', 'Scheduling memory must retain both historical answers.');

$operatorNow = new DateTimeImmutable('2026-08-22 10:00:00', new DateTimeZone(APP_TIMEZONE));
$operatorCommand = lead_agent_parse_operator_command('S161-ABCD 8/26 3PM, 8/27 4:30PM', $operatorNow);
expect_true(($operatorCommand['action'] ?? '') === 'offer', 'Rod must be able to supply two appointment options by replying to the internal SMS.');
expect_true(($operatorCommand['options'][0] ?? '') === '2026-08-26 15:00:00' && ($operatorCommand['options'][1] ?? '') === '2026-08-27 16:30:00', 'Operator appointment options must normalize in the CRM timezone.');
expect_true((lead_agent_parse_operator_command('S161-ABCD tomorrow at 3', $operatorNow)['action'] ?? '') === 'invalid', 'An ambiguous one-option command must fail closed without sending.');
expect_true((lead_agent_parse_operator_command('HELP', $operatorNow)['action'] ?? '') === 'help', 'The operator SMS channel must expose deterministic help.');
expect_true((lead_agent_parse_operator_command('S161-ABCD CALL', $operatorNow)['action'] ?? '') === 'invalid', 'A generic operator CALL command must not create an unrequested call workflow.');

$windowCommand = lead_agent_parse_operator_command('S161-ABCDEF next Monday and Tuesday from 2 to 5 are open', $operatorNow);
expect_true(($windowCommand['action'] ?? '') === 'availability_window', 'Natural operator availability must be recognized as a calendar window.');
$windows = (array) ($windowCommand['windows'] ?? []);
expect_true(($windows[0]['start'] ?? '') === '2026-08-24 14:00:00' && ($windows[0]['end'] ?? '') === '2026-08-24 17:00:00', 'Next Monday must resolve from the current Mountain Time date.');
expect_true(($windows[1]['start'] ?? '') === '2026-08-25 14:00:00' && ($windows[1]['end'] ?? '') === '2026-08-25 17:00:00', 'A second weekday in the same instruction must resolve to the correct date.');
$windowSlots = lead_agent_available_slots_for_windows($windows, [
    ['start' => '2026-08-24 14:30:00', 'end' => '2026-08-24 15:00:00'],
], $operatorNow);
expect_true(count($windowSlots) === 11, 'Two three-hour windows must become twelve 30-minute starts minus the occupied calendar slot.');
$mondaySlots = array_values(array_filter($windowSlots, static fn(string $slot): bool => str_starts_with($slot, '2026-08-24')));
expect_true(count($mondaySlots) === 5, 'Monday from 2 to 5 with one occupied block must correctly report five open 30-minute slots.');
$chosenWindowSlots = lead_agent_choose_offer_slots($windowSlots);
expect_true(($chosenWindowSlots[0] ?? '') === '2026-08-24 14:00:00' && ($chosenWindowSlots[1] ?? '') === '2026-08-25 14:00:00', 'The agent should offer one simple choice from each available day first.');
$sameDayNow = new DateTimeImmutable('2026-08-24 15:10:00', new DateTimeZone(APP_TIMEZONE));
$sameDayWindows = lead_agent_parse_operator_availability_windows('Monday from 2 to 5 is open', $sameDayNow);
$sameDaySlots = lead_agent_available_slots_for_windows($sameDayWindows, [], $sameDayNow);
expect_true($sameDaySlots === ['2026-08-24 15:30:00', '2026-08-24 16:00:00', '2026-08-24 16:30:00'], 'Same-day availability must discard elapsed slots using the current Mountain Time.');
$lateMonday = new DateTimeImmutable('2026-08-24 17:10:00', new DateTimeZone(APP_TIMEZONE));
$nextMondayWindow = lead_agent_parse_operator_availability_windows('Monday from 2 to 5 is open', $lateMonday);
expect_true(($nextMondayWindow[0]['start'] ?? '') === '2026-08-31 14:00:00', 'Once the stated window has ended, an unqualified weekday must resolve to the following week.');
$tuesdayNow = new DateTimeImmutable('2026-08-25 10:00:00', new DateTimeZone(APP_TIMEZONE));
$scopedNextWindows = lead_agent_parse_operator_availability_windows('next Monday and Tuesday from 2 to 5', $tuesdayNow);
expect_true(($scopedNextWindows[0]['start'] ?? '') === '2026-08-31 14:00:00' && ($scopedNextWindows[1]['start'] ?? '') === '2026-09-01 14:00:00', 'Next must scope a chronological weekday list instead of resolving Tuesday to today.');
$unalignedWindow = lead_agent_parse_operator_availability_windows('next Monday from 2:10 to 3:10', $operatorNow);
$alignedSlots = lead_agent_available_slots_for_windows($unalignedWindow, [], $operatorNow);
expect_true($alignedSlots === ['2026-08-24 14:30:00'], 'Non-aligned windows must round forward to a valid 30-minute appointment start without leaving the stated window.');
$webhookSource = (string) file_get_contents(dirname(__DIR__) . '/app/api/twilio_sms_webhook.php');
expect_true(
    strpos($webhookSource, 'lead_agent_is_operator_sender($from)') < strpos($webhookSource, 'lead_comm_find_lead_by_phone($from)'),
    'Authorized operator SMS must be intercepted before patient lookup so it can never create a false lead.'
);
$agentSource = (string) file_get_contents(dirname(__DIR__) . '/app/leads/lead_agent.php');
expect_true(!str_contains($agentSource, 'I checked the CRM and Dentrix calendar'), 'The agent must never claim it queried Dentrix directly.');
expect_true(!str_contains($agentSource, 'CODE CALL'), 'Operator help must not offer calls when the lead did not request one.');
$pushSource = (string) file_get_contents(dirname(__DIR__) . '/app/core/mobile_ai_push.php');
expect_true(str_contains($agentSource, "'type' => 'handoff'"), 'Scheduling handoffs must not use the patient-reply push type.');
expect_true(str_contains($pushSource, 'scheduling follow-up needed for'), 'Scheduling handoff pushes must be labeled as follow-up work, not as a new patient message.');
$aiSource = (string) file_get_contents(dirname(__DIR__) . '/app/leads/lead_ai.php');
expect_true(!str_contains($aiSource, 'ask for the preferred day and DOB'), 'The AI prompt must not collect DOB before a patient selects a confirmed slot.');
expect_true(str_contains($aiSource, 'Call-channel boundary'), 'AI drafting must carry the explicit call-consent boundary.');
expect_true(str_contains($agentSource, 'sms_unreachable_email_cycle_resumed'), 'A failed SMS with consented email must resume through email.');
expect_true(str_contains($agentSource, 'unreachable_no_delivery_channel'), 'A lead with no deliverable channel must be parked without another send.');
$statusCallbackSource = (string) file_get_contents(dirname(__DIR__) . '/app/api/twilio_sms_status.php');
expect_true(str_contains($statusCallbackSource, 'lead_agent_mark_sms_delivery_attention'), 'Twilio failed and undelivered callbacks must enter the centralized delivery router.');
$leadServiceSource = (string) file_get_contents(dirname(__DIR__) . '/app/leads/lead_service.php');
expect_true(!str_contains($leadServiceSource, 'Automatically moved new lead from New Lead to Contacted'), 'Successful first touch must no longer empty the New Lead stage.');
expect_true(str_contains($webhookSource, "lead_lifecycle_mark_inbound_answer(\$leadId, 'twilio_sms_webhook')"), 'Inbound SMS must reopen New Lead or Nurture through the central lifecycle transition.');
$emailSource = (string) file_get_contents(dirname(__DIR__) . '/app/leads/lead_email.php');
expect_true(str_contains($emailSource, "lead_lifecycle_mark_inbound_answer(\$leadId, 'lead_email_inbound')"), 'Inbound email must reopen New Lead or Nurture through the central lifecycle transition.');
expect_true(str_contains($emailSource, 'List-Unsubscribe-Post: List-Unsubscribe=One-Click') && str_contains($emailSource, 'List-Unsubscribe: <'), 'Every automated lead email must retain one-click unsubscribe headers.');
expect_true(str_contains($emailSource, 'unsubscribe from follow-up emails') && str_contains($emailSource, '11762 South State, Suite 300, Draper, UT 84020'), 'Lead emails must retain a visible unsubscribe link and the practice mailing address.');
expect_true(str_contains($emailSource, "email_opt_status = 'bounced'"), 'A confirmed email bounce must suppress that address from future monthly outreach.');
$plainCompliance = lead_email_plain_text_with_compliance('Requested information.', 'https://example.com/unsubscribe');
expect_true(str_contains($plainCompliance, '11762 South State, Suite 300, Draper, UT 84020') && str_contains($plainCompliance, 'Unsubscribe from follow-up emails: https://example.com/unsubscribe'), 'Plain-text email alternatives must include both the physical address and a direct unsubscribe mechanism.');
expect_true(lead_email_spf_records_authorize([
    ['txt' => 'v=spf1 ip4:66.225.201.146 include:relay.mailchannels.net include:spf.jetsmtp.net ~all'],
], 'spf.jetsmtp.net'), 'A single SPF record containing the required JetEmail include must authorize automated email.');
expect_true(!lead_email_spf_records_authorize([
    ['txt' => 'v=spf1 ip4:66.225.201.146 include:relay.mailchannels.net ~all'],
], 'spf.jetsmtp.net'), 'Automated email must fail closed while the sender SPF omits JetEmail.');
expect_true(!lead_email_spf_records_authorize([
    ['txt' => 'v=spf1 include:spf.jetsmtp.net ~all'],
    ['txt' => 'v=spf1 include:relay.mailchannels.net ~all'],
], 'spf.jetsmtp.net'), 'Multiple SPF records must not be accepted as valid sender authentication.');
expect_true(str_contains($agentSource, 'lead_agent_reconcile_lifecycle(500, $dryRun)'), 'Every Lead Agent run must perform the dry-run capable lifecycle reconciliation.');
expect_true(str_contains($agentSource, 'lead_agent_repair_cycle_coverage(500, $dryRun)'), 'Every Lead Agent run must repair uncovered active and Nurture cycle records.');
expect_true(str_contains($agentSource, 'lead_agent_repair_slow_active_sprint(500)'), 'Every live run must accelerate leads still carrying the former slow cadence.');
expect_true(str_contains($agentSource, 'lead_agent_run_monthly_email_outreach(10, $dryRun)'), 'Every Lead Agent run must evaluate the guarded monthly Nurture/Lost email lane.');
$leadMetaSource = (string) file_get_contents(dirname(__DIR__) . '/app/leads/lead_meta.php');
expect_true(str_contains($leadMetaSource, "'no_answer', 'lost_lead', ''"), 'A reply from a reactivated Lost lead must reopen the active conversation.');
expect_true(str_contains($leadMetaSource, 'SET lost_reason = NULL'), 'Reopening a Lost lead must clear the stale loss reason.');
expect_true(!str_contains($agentSource, "'send_pushover_fallback' => true"), 'Automated Lead Agent SMS failure must not create a Pushover interruption.');
$operationsSource = (string) file_get_contents(dirname(__DIR__) . '/lead-agent-operations.php');
expect_true(str_contains($operationsSource, 'id="cycle-coverage"') && str_contains($operationsSource, 'id="cycle-coverage-data"'), 'Authenticated Lead Agent Operations must expose live cycle coverage and its exact audit payload.');
expect_true(!lead_attention_is_actionable(['_action_queue' => ['action_key' => 'delivery_issue']]), 'A non-actionable SMS delivery failure must not create a red human-attention halo.');
expect_true(lead_attention_is_actionable(['_action_queue' => ['action_key' => 'reply_needed']]), 'A patient reply that needs an answer must remain in the human-attention queue.');

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
$schedulingPipelineBackfill = array_merge($eligibleBackfill, ['status' => 'in_contact', 'last_inbound_at' => '2026-08-05 10:01:00']);
expect_true(lead_agent_backfill_ineligible_reason($schedulingPipelineBackfill) === '', 'An unscheduled lead in the Scheduling pipeline must remain eligible for agent follow-up even after replying.');
expect_true(lead_agent_backfill_ineligible_reason(array_merge($eligibleBackfill, ['consultation_status' => 'scheduling'])) === 'scheduling_or_consultation', 'An active scheduling record must not be enrolled.');
expect_true(lead_agent_backfill_ineligible_reason(array_merge($eligibleBackfill, ['full_name' => 'Rodrigo Moya'])) === 'internal_or_test_record', 'The owner record must never be enrolled.');
expect_true(lead_agent_backfill_ineligible_reason(array_merge($eligibleBackfill, ['last_inbound_at' => '2026-08-05 10:01:00'])) === 'newer_inbound_requires_review', 'A newer inbound reply must block backfill.');
expect_true(lead_agent_backfill_ineligible_reason(array_merge($eligibleBackfill, ['consultation_date' => '2026-08-10 09:00:00'])) === 'consultation_date_present', 'A consultation must block backfill.');
$emailOnlyBackfill = $eligibleBackfill;
$emailOnlyBackfill['sms_opt_status'] = 'dnd';
expect_true(lead_agent_backfill_ineligible_reason($emailOnlyBackfill) === '', 'An SMS DND lead with subscribed email remains email eligible.');
expect_true(lead_agent_sms_blocked($emailOnlyBackfill), 'DND must block every automated SMS path.');
$invalidPhoneBackfill = array_merge($eligibleBackfill, ['phone' => '801555121']);
expect_true(lead_agent_sms_blocked($invalidPhoneBackfill), 'An incomplete phone number must block every automated SMS path.');
expect_true(lead_agent_backfill_ineligible_reason(array_merge($invalidPhoneBackfill, ['email_opt_status' => 'unsubscribed'])) === 'no_consented_delivery_channel', 'An invalid phone without a usable email must not be enrolled for nurture.');
$noChannelBackfill = $emailOnlyBackfill;
$noChannelBackfill['email_opt_status'] = 'unsubscribed';
expect_true(lead_agent_backfill_ineligible_reason($noChannelBackfill) === 'no_consented_delivery_channel', 'A lead without a consented channel must not be enrolled.');

$legacyNurture = array_merge($eligibleBackfill, ['status' => 'no_answer']);
$nurtureGap = lead_agent_cycle_assessment($legacyNurture, [], false, '');
expect_true((string)($nurtureGap['category'] ?? '') === 'gap' && empty($nurtureGap['covered']), 'A contactable legacy Nurture lead without agent state must be detected as a cycle gap.');
$legacyNurtureWithoutHistory = array_merge($legacyNurture, ['last_outbound_at' => '']);
$historyGap = lead_agent_cycle_assessment($legacyNurtureWithoutHistory, [], false, '');
expect_true((string)($historyGap['category'] ?? '') === 'gap' && (string)($historyGap['reason'] ?? '') === 'legacy_nurture_without_local_touch_history', 'A contactable Nurture record must join the staggered cycle even when old outbound history was not imported.');
$nurtureCovered = lead_agent_cycle_assessment($legacyNurture, [
    'status' => 'nurture',
    'human_takeover' => 0,
    'next_action_at' => '2026-09-05 10:00:00',
], false, '');
expect_true((string)($nurtureCovered['category'] ?? '') === 'covered' && !empty($nurtureCovered['covered']), 'A scheduled Nurture state must count as covered.');
$coveredWithoutRollup = lead_agent_cycle_assessment(array_merge($eligibleBackfill, ['last_outbound_at' => '']), [
    'status' => 'active',
    'human_takeover' => 0,
    'next_action_at' => '2026-08-27 09:00:00',
], false, '');
expect_true((string)($coveredWithoutRollup['category'] ?? '') === 'covered', 'A durable active schedule must remain covered when a communication rollup timestamp is missing.');
$deliveryEmailRoute = lead_agent_cycle_assessment($legacyNurture, [
    'status' => 'needs_attention',
    'human_takeover' => 1,
    'last_decision' => 'sms_delivery_failed_needs_attention',
], true, '');
expect_true((string)($deliveryEmailRoute['category'] ?? '') === 'gap' && (string)($deliveryEmailRoute['channel'] ?? '') === 'email', 'A delivery-only stall with consented email must become an email-cycle repair, not human work.');
$replyAssessment = lead_agent_cycle_assessment(array_merge($eligibleBackfill, [
    'last_inbound_at' => '2026-08-05 10:01:00',
]), [], false, '');
expect_true((string)($replyAssessment['category'] ?? '') === 'human_action', 'A newer patient reply must stay out of automated cycle repair.');
$unreachableAssessment = lead_agent_cycle_assessment(array_merge($invalidPhoneBackfill, [
    'email_opt_status' => 'unsubscribed',
]), [], true, '');
expect_true((string)($unreachableAssessment['category'] ?? '') === 'unreachable', 'A lead without a deliverable SMS or email route must be classified as unreachable.');
$scheduledNurtureAt = lead_agent_legacy_nurture_schedule(37, false, new DateTimeImmutable('2026-08-26 12:00:00', new DateTimeZone(APP_TIMEZONE)));
expect_true($scheduledNurtureAt === '2026-09-03 14:19:00', 'Legacy Nurture reactivation must be deterministically staggered instead of sent as a batch.');
expect_true(lead_agent_followup_context_reason(['id' => 1], ['status' => 'ready_to_schedule']) === 'conversation_owned_or_paused', 'Follow-up must stay silent after a scheduling handoff.');
expect_true(lead_agent_recovered_scheduling_handoff_is_active([
    'agent_status' => 'ready_to_schedule',
    'scheduling_phase' => 'awaiting_availability',
    'human_takeover' => 1,
]), 'An active recovered scheduling handoff must not notify Rod again when its operator request reaches the two-day expiry.');
expect_true(!lead_agent_recovered_scheduling_handoff_is_active([
    'agent_status' => 'engaged',
    'scheduling_phase' => 'awaiting_preference',
    'human_takeover' => 0,
]), 'A real transition into scheduling must still create the first handoff notification.');
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
expect_true(count($plan) === 11, 'The active sprint must cover the requested six-day follow-up window before Nurture.');
expect_true($plan[1]['hours'] === 0.5 && $plan[1]['channel'] === 'sms' && $plan[1]['phase'] === 'same_day_delivery_check', 'The first unanswered follow-up must send by SMS after 30 minutes.');
expect_true($plan[2]['hours'] === 2 && $plan[2]['channel'] === 'sms' && $plan[2]['phase'] === 'same_day_goal_followup', 'The two-hour unanswered follow-up must keep SMS primary.');
expect_true($plan[3]['hours'] === 5 && $plan[3]['channel'] === 'email' && $plan[3]['phase'] === 'same_day_requested_information', 'The first email must wait until the five-hour unanswered milestone.');
expect_true($plan[4]['hours'] === 20 && $plan[4]['channel'] === 'sms' && $plan[5]['hours'] === 24 && $plan[5]['channel'] === 'sms', 'The second day must include at least two planned SMS touches.');
expect_true($plan[6]['hours'] === 32 && $plan[6]['channel'] === 'email', 'Day two may add one educational email only while the lead remains unanswered.');
expect_true($plan[7]['hours'] === 48 && $plan[8]['hours'] === 60, 'The active sprint must include the requested 48-hour and 60-hour milestones.');
expect_true($plan[11]['hours'] === 144 && $plan[11]['phase'] === 'active_sprint_close', 'The final active touch must close the six-day sprint before Nurture.');
$sameDayStep = lead_agent_step_schedule('2026-08-26 12:02:00', 1);
expect_true($sameDayStep['at'] === '2026-08-26 12:32:00', 'A midday first touch must schedule the next engagement 30 minutes later.');
$lateStep = lead_agent_step_schedule('2026-08-26 18:00:00', 3);
expect_true($lateStep['at'] === '2026-08-27 09:00:00', 'A target outside the contact window must move to 9 AM the next day.');
$earlyAligned = lead_agent_align_contact_time(new DateTimeImmutable('2026-08-26 08:59:00', new DateTimeZone(APP_TIMEZONE)));
$closingAligned = lead_agent_align_contact_time(new DateTimeImmutable('2026-08-26 20:00:00', new DateTimeZone(APP_TIMEZONE)));
expect_true($earlyAligned->format('Y-m-d H:i:s') === '2026-08-26 09:00:00', 'No automated touch may send before 9 AM.');
expect_true($closingAligned->format('Y-m-d H:i:s') === '2026-08-27 09:00:00', 'No automated touch may send at or after 8 PM.');
$nurtureStepOne = lead_agent_step_schedule('2026-08-01 09:00:00', 12);
$nurtureStepTwo = lead_agent_step_schedule('2026-08-01 09:00:00', 13);
expect_true($nurtureStepOne['hours'] === 216 && $nurtureStepOne['phase'] === 'twice_weekly_nurture' && $nurtureStepOne['channel'] === 'email', 'Long-term Nurture must resume three days after the active sprint.');
expect_true($nurtureStepTwo['hours'] === 312 && $nurtureStepTwo['channel'] === 'sms', 'The next Nurture touch must follow four days later, producing two touches per week.');

$dayZeroLimit = lead_agent_daily_outbound_limit('2026-08-26 12:02:00', new DateTimeImmutable('2026-08-26 15:02:00', new DateTimeZone(APP_TIMEZONE)));
$dayOneLimit = lead_agent_daily_outbound_limit('2026-08-26 12:02:00', new DateTimeImmutable('2026-08-27 15:02:00', new DateTimeZone(APP_TIMEZONE)));
$dayFiveLimit = lead_agent_daily_outbound_limit('2026-08-01 10:00:00', new DateTimeImmutable('2026-08-05 18:00:00', new DateTimeZone(APP_TIMEZONE)));
$daySixLimit = lead_agent_daily_outbound_limit('2026-08-01 10:00:00', new DateTimeImmutable('2026-08-06 09:00:00', new DateTimeZone(APP_TIMEZONE)));
expect_true($dayZeroLimit === 4, 'Day one must allow no more than four total SMS and email touches, including first touch.');
expect_true($dayOneLimit === 3, 'The second calendar day must allow two SMS touches and no more than one unanswered email.');
expect_true($dayFiveLimit === 1, 'The cadence must never send more than one automated follow-up in a day.');
expect_true($daySixLimit === 1, 'The one-message daily cap must remain in force after day five.');

$incremental = lead_agent_incremental_schedule('2026-08-05 17:00:00', 1);
expect_true($incremental['at'] === '2026-08-05 19:00:00', 'An overdue 30-minute touch must preserve at least two hours of spacing before the next SMS.');

$smsPriorityNow = new DateTimeImmutable('2026-08-26 15:00:00', new DateTimeZone(APP_TIMEZONE));
$smsPriorityNextDay = new DateTimeImmutable('2026-08-27 15:00:00', new DateTimeZone(APP_TIMEZONE));
$smsPriorityThirdDay = new DateTimeImmutable('2026-08-28 15:00:00', new DateTimeZone(APP_TIMEZONE));
expect_true(lead_agent_prioritize_first_two_day_sms('email', '2026-08-26 12:00:00', 1, false, $smsPriorityNow) === 'sms', 'Day one must prioritize a second SMS before email.');
expect_true(lead_agent_prioritize_first_two_day_sms('email', '2026-08-26 12:00:00', 1, false, $smsPriorityNextDay) === 'sms', 'Day two must prioritize a second SMS before email.');
expect_true(lead_agent_prioritize_first_two_day_sms('email', '2026-08-26 12:00:00', 2, false, $smsPriorityNextDay) === 'email', 'Email may proceed after two SMS touches have sent that day.');
expect_true(lead_agent_prioritize_first_two_day_sms('email', '2026-08-26 12:00:00', 1, true, $smsPriorityNow) === 'email', 'An unavailable SMS channel must not block the email fallback.');
expect_true(lead_agent_prioritize_first_two_day_sms('email', '2026-08-26 12:00:00', 1, false, $smsPriorityThirdDay) === 'email', 'Later cadence days must follow the planned channel.');
expect_true(lead_agent_prioritize_first_two_day_sms('email', '2026-08-26 12:00:00', 2, false, $smsPriorityThirdDay, true) === 'sms', 'After a lead answers by SMS, future automated engagement must stay on SMS while that channel remains deliverable.');
expect_true(lead_agent_prioritize_first_two_day_sms('email', '2026-08-26 12:00:00', 2, true, $smsPriorityThirdDay, true) === 'email', 'Email may remain the fallback only when SMS later becomes unavailable.');
expect_true(lead_agent_post_reply_resume_step('sms') === 3, 'An SMS reply must skip the unanswered five-hour email step.');
expect_true(lead_agent_post_reply_resume_step('email') === 2, 'An email reply may remain in the email-capable engagement path.');

$lifecycleNow = new DateTimeImmutable('2026-08-26 15:00:00', new DateTimeZone(APP_TIMEZONE));
$firstDayLead = [
    'status' => 'contacted',
    'created_at' => '2026-08-26 09:00:00',
    'last_outbound_at' => '2026-08-26 09:02:00',
    'last_inbound_at' => '',
    'consultation_status' => 'requested',
];
expect_true(lead_conversion_stage_key($firstDayLead, $lifecycleNow) === 'new_lead', 'First touch must not remove a lead from New Lead during the first 24 hours.');
$olderUnanswered = $firstDayLead;
$olderUnanswered['created_at'] = '2026-08-25 08:00:00';
expect_true(lead_conversion_stage_key($olderUnanswered, $lifecycleNow) === 'active_follow_up', 'An unanswered lead must enter Active Follow-Up after 24 hours.');
$openConversation = $olderUnanswered + ['phone' => '+18015550199'];
$openConversation['status'] = 'in_contact';
$openConversation['last_inbound_at'] = '2026-08-26 14:30:00';
$openConversation['last_outbound_at'] = '2026-08-26 14:00:00';
expect_true(lead_conversion_stage_key($openConversation, $lifecycleNow) === 'lead_answered', 'A newer inbound response must display as Lead Answered, not Scheduling.');
$recentAnswer = $openConversation;
$recentAnswer['last_inbound_at'] = '2026-08-26 12:00:00';
$recentAnswer['last_outbound_at'] = '2026-08-26 14:30:00';
expect_true(lead_conversion_stage_key($recentAnswer, $lifecycleNow) === 'lead_answered', 'A recently answered conversation must stay open before the stall threshold.');
$stalledAnswer = $recentAnswer;
$stalledAnswer['last_outbound_at'] = '2026-08-26 12:30:00';
expect_true(lead_conversion_stage_key($stalledAnswer, $lifecycleNow) === 'active_follow_up', 'A conversation quiet for two hours after our answer must enter Active Follow-Up.');
$schedulingLead = $openConversation;
$schedulingLead['consultation_status'] = 'scheduling';
expect_true(lead_conversion_stage_key($schedulingLead, $lifecycleNow) === 'scheduling', 'Only explicit scheduling context should display in Scheduling.');
expect_true(lead_conversion_stage_key(['status' => 'no_answer'], $lifecycleNow) === 'nurture', 'No Answer must display as Nurture.');
expect_true(lead_conversion_stage_key(['status' => 'lost_lead'], $lifecycleNow) === 'lost', 'Lost must remain separate from Nurture.');
expect_true(lead_conversion_stage_key(['status' => 'opted_out'], $lifecycleNow) === 'opted_out', 'Opted Out must remain separate from Nurture.');

expect_true(lead_agent_lifecycle_decision($stalledAnswer, ['status' => 'engaged', 'cadence_step' => 2], 0, $lifecycleNow) === 'active_follow_up', 'The reconciler must persist a stalled answered conversation as Active Follow-Up.');
$longStalled = $stalledAnswer;
$longStalled['last_inbound_at'] = '2026-08-23 12:00:00';
$longStalled['last_outbound_at'] = '2026-08-26 12:00:00';
expect_true(lead_agent_lifecycle_decision($longStalled, ['status' => 'engaged', 'cadence_step' => 4], 2, $lifecycleNow) === 'nurture', 'Two re-engagement attempts over 72 hours must move a stopped conversation to Nurture.');
$unansweredInbound = $longStalled;
$unansweredInbound['last_outbound_at'] = '2026-08-23 11:00:00';
expect_true(lead_agent_lifecycle_decision($unansweredInbound, ['status' => 'engaged', 'cadence_step' => 4], 2, $lifecycleNow) === '', 'An unanswered inbound message must never be moved to Nurture.');
expect_true(lead_agent_lifecycle_decision($olderUnanswered, ['status' => 'active', 'cadence_step' => 11], 0, $lifecycleNow) === 'nurture', 'A never-answered lead must enter Nurture after the eleven-step six-day active sprint.');
expect_true(lead_conversion_stage_legacy_target('lead_answered') === 'in_contact' && lead_conversion_stage_legacy_target('nurture') === 'no_answer', 'New display stages must retain legacy database compatibility.');

$firstTouchSms = lead_ai_default_new_lead_sms(['full_name' => 'Taylor Example']);
expect_true(!str_contains(strtolower($firstTouchSms), 'morning') && !str_contains(strtolower($firstTouchSms), 'afternoon'), 'First touch must discover the smile goal before asking for scheduling preferences.');
expect_true(str_contains(strtolower($firstTouchSms), 'what are you hoping to improve'), 'First-touch SMS should begin a natural goal-focused conversation.');
expect_true(str_contains($firstTouchSms, 'Reply STOP to opt out.'), 'The first SMS must identify the one-step STOP mechanism.');
expect_true(str_contains($firstTouchSms, 'English or Spanish is welcome') && str_contains($firstTouchSms, 'ESPANOL'), 'Unknown-language first touch must offer Spanish neutrally.');
$englishFirstTouchSms = lead_ai_default_new_lead_sms(['full_name' => 'Taylor Example', 'preferred_language' => 'en']);
expect_true(!str_contains($englishFirstTouchSms, 'ESPANOL'), 'A confirmed English preference must not receive the unknown-language prompt.');
$spanishFirstTouchSms = lead_ai_default_new_lead_sms(['full_name' => 'Taylor Example', 'preferred_language' => 'es']);
expect_true(str_starts_with($spanishFirstTouchSms, 'Hola Taylor') && str_contains($spanishFirstTouchSms, '¿Qué le gustaría mejorar'), 'A confirmed Spanish preference must receive Spanish first touch.');
$firstTouchEmail = lead_email_default_first_touch(['full_name' => 'Taylor Example', 'procedure_interest' => 'Veneers']);
expect_true(substr_count((string) $firstTouchEmail['body'], '?') === 0, 'First-touch email must add trust and education instead of duplicating the SMS question.');
expect_true((string) $firstTouchEmail['subject'] === 'The information you requested from Elite Smiles', 'First-touch email needs an accurate subject tied to the lead request.');
expect_true(str_contains((string) $firstTouchEmail['body'], 'Here is the information you requested.'), 'First-touch email must immediately explain why the lead is receiving it.');
$smsReachableLead = ['phone' => '+18015550199', 'sms_opt_status' => 'unknown', 'status' => 'new_lead'];
expect_true(lead_email_first_touch_should_wait_for_sms($smsReachableLead), 'A lead reachable by SMS must not receive email before the unanswered five-hour milestone.');
expect_true(!lead_email_first_touch_should_wait_for_sms($smsReachableLead, false), 'An email-only intake path must retain immediate first-touch email.');
expect_true(!lead_email_first_touch_should_wait_for_sms(['phone' => '123', 'sms_opt_status' => 'unknown', 'status' => 'new_lead']), 'An invalid phone must allow email to serve as the viable first-touch channel.');
expect_true(!lead_email_first_touch_should_wait_for_sms(['phone' => '+18015550199', 'sms_opt_status' => 'opted_out', 'status' => 'new_lead']), 'SMS opt-out must allow consented email without waiting on SMS.');

$monthlyNow = new DateTimeImmutable('2026-08-27 12:00:00', new DateTimeZone(APP_TIMEZONE));
$monthlyNurtureLead = [
    'id' => 101,
    'full_name' => 'Taylor Example',
    'status' => 'no_answer',
    'email' => 'taylor@example.com',
    'email_opt_status' => 'subscribed',
    'created_at' => '2026-05-01 10:00:00',
    'updated_at' => '2026-06-01 10:00:00',
    'last_outbound_at' => '2026-07-20 10:00:00',
    'last_inbound_at' => '',
];
expect_true(lead_agent_monthly_email_due($monthlyNurtureLead, '2026-07-20 10:00:00', '', $monthlyNow), 'A Nurture lead with no successful email for 30 days must be eligible for monthly reactivation.');
expect_true(!lead_agent_monthly_email_due($monthlyNurtureLead, '2026-08-10 10:00:00', '', $monthlyNow), 'A recent successful email must block another monthly reactivation email.');
expect_true(!lead_agent_monthly_email_due(array_merge($monthlyNurtureLead, ['email_opt_status' => 'unsubscribed']), '2026-07-20 10:00:00', '', $monthlyNow), 'Email unsubscribe must permanently block monthly reactivation.');
expect_true(!lead_agent_monthly_email_due(array_merge($monthlyNurtureLead, ['email_opt_status' => 'bounced']), '2026-07-20 10:00:00', '', $monthlyNow), 'A bounced email address must never be retried by monthly reactivation.');
$monthlyLostLead = array_merge($monthlyNurtureLead, ['status' => 'lost_lead', 'lost_reason' => 'not_ready']);
expect_true(lead_agent_monthly_email_due($monthlyLostLead, '2026-07-20 10:00:00', '', $monthlyNow), 'An ordinary Lost business outcome may receive one low-frequency monthly email.');
expect_true(!lead_agent_monthly_email_due(array_merge($monthlyLostLead, ['lost_reason' => 'wrong_lead']), '2026-07-20 10:00:00', '', $monthlyNow), 'A wrong-recipient Lost record must never receive monthly email.');
expect_true(!lead_agent_monthly_email_due($monthlyLostLead, '2026-07-20 10:00:00', 'explicit_decline_or_distance', $monthlyNow), 'An explicit decline or do-not-contact signal must block monthly email even if the pipeline says Lost.');
expect_true(!lead_agent_monthly_email_due(array_merge($monthlyLostLead, [
    'last_inbound_at' => '2026-08-20 10:00:00',
    'last_outbound_at' => '2026-08-19 10:00:00',
]), '2026-07-20 10:00:00', '', $monthlyNow), 'A patient reply waiting for an answer must never receive automated monthly email.');
expect_true(!lead_agent_monthly_email_due(array_merge($monthlyLostLead, ['consultation_status' => 'scheduled']), '2026-07-20 10:00:00', '', $monthlyNow), 'A scheduled consultation must block monthly reactivation.');
$monthlySubjects = [];
for ($rotation = 0; $rotation < 4; $rotation++) {
    $monthlyDraft = lead_agent_monthly_email_template($monthlyNurtureLead, $rotation);
    $monthlySubjects[] = (string)$monthlyDraft['subject'];
    expect_true(lead_agent_policy_flags((string)$monthlyDraft['subject'] . ' ' . (string)$monthlyDraft['body']) === [], 'Every monthly email rotation must pass Lead Agent policy gates.');
}
expect_true(count(array_unique($monthlySubjects)) === 4, 'Monthly reactivation must rotate useful subjects instead of repeating one stale email.');
$spanishMonthlyDraft = lead_agent_monthly_email_template(array_merge($monthlyNurtureLead, ['preferred_language' => 'es']), 0);
expect_true(str_starts_with((string)$spanishMonthlyDraft['body'], 'Hola Taylor') && lead_agent_policy_flags((string)$spanishMonthlyDraft['subject'] . ' ' . (string)$spanishMonthlyDraft['body']) === [], 'Spanish-preferring Nurture and Lost leads must receive approved Spanish monthly copy.');

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
expect_true(in_array('misleading_thread_subject', lead_agent_policy_flags('Re: Your request Here is an unrelated first email.'), true), 'Lead Agent must block a fake reply prefix on a new email subject.');
expect_true(in_array('misleading_urgency', lead_agent_policy_flags('Final notice: act now.'), true), 'Lead Agent must block urgency language that could mislead recipients or damage sender reputation.');

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
expect_true($alignedMorning->format('Y-m-d H:i') === '2026-08-06 09:00', 'Morning sends should move to 9 AM.');
expect_true($alignedNight->format('Y-m-d H:i') === '2026-08-07 09:00', 'Night sends should move to next-day 9 AM.');

$healthNow = new DateTimeImmutable('2026-08-07 11:00:00', new DateTimeZone(APP_TIMEZONE));
$healthyRun = lead_agent_run_health(['status' => 'completed', 'finished_at' => '2026-08-07 10:45:00', 'started_at' => '2026-08-07 10:44:59'], $healthNow);
$staleRun = lead_agent_run_health(['status' => 'completed', 'finished_at' => '2026-08-07 09:00:00', 'started_at' => '2026-08-07 08:59:59'], $healthNow);
expect_true($healthyRun['key'] === 'healthy', 'A recent completed worker run should be healthy.');
expect_true($staleRun['key'] === 'stale', 'A worker with no recent run should be stale.');

echo "Lead Agent policy tests passed.\n";
