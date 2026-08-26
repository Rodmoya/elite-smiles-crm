<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/leads/lead_agent.php';

function integration_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
lead_agent_ensure_schema();
lead_comm_ensure_schema();
lead_email_ensure_schema();

foreach (['human_takeover_until', 'scheduling_phase', 'availability_option_1', 'availability_option_2', 'selected_availability', 'scheduling_context', 'availability_pool_json'] as $column) {
    integration_expect((bool) db_one("SHOW COLUMNS FROM lead_agent_states LIKE '" . $column . "'"), 'Scheduling state column is missing: ' . $column);
}
foreach (['strategy_key', 'strategy_reason', 'decision_confidence'] as $column) {
    integration_expect((bool) db_one("SHOW COLUMNS FROM lead_agent_touchpoints LIKE '" . $column . "'"), 'Conversion tracking column is missing: ' . $column);
}

db_begin();
try {
    $leadId = db_insert(
        "INSERT INTO leads (full_name, phone, email, status, sms_opt_status, email_opt_status, created_at, updated_at)
         VALUES ('Lead Agent Integration Test', '+18015550199', 'lead-agent-test@example.invalid', 'contacted', 'opted_in', 'subscribed', NOW(), NOW())"
    );
    integration_expect($leadId > 0, 'Synthetic lead was not created.');

    $enrollment = lead_agent_enroll($leadId, ['source' => 'integration_test']);
    integration_expect(!empty($enrollment['enrolled']), 'Synthetic lead was not enrolled.');
    $enrolledState = db_one('SELECT started_at, next_action_at FROM lead_agent_states WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]);
    $expectedFirstDayAt = (string) (lead_agent_step_schedule((string) ($enrolledState['started_at'] ?? ''), 1)['at'] ?? '');
    integration_expect((string) ($enrollment['next_action_at'] ?? '') === $expectedFirstDayAt, 'Enrollment must use the guarded 30-minute first-day schedule.');

    db_execute("UPDATE lead_agent_states SET status = 'needs_attention', human_takeover = 1,
        next_action_at = NULL, last_decision = 'sms_delivery_failed_needs_attention'
        WHERE lead_id = :lead_id", ['lead_id' => $leadId]);
    $deliveryRoute = lead_agent_mark_sms_delivery_attention($leadId, 'undelivered', '30003', 'Synthetic unreachable handset.', [
        'event_key' => 'integration-delivery-route-' . $leadId,
        'source' => 'integration_test',
    ]);
    $routedState = db_one('SELECT status, human_takeover, next_action_at, last_decision FROM lead_agent_states WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]);
    integration_expect(empty($deliveryRoute['attention']) && (string)($deliveryRoute['route'] ?? '') === 'email', 'A failed SMS with consented email must route without creating human attention.');
    integration_expect((string)($routedState['status'] ?? '') === 'active' && empty($routedState['human_takeover']) && !empty($routedState['next_action_at']), 'The email fallback cycle must remain active and scheduled.');

    $unreachableLeadId = db_insert(
        "INSERT INTO leads (full_name, phone, email, status, sms_opt_status, email_opt_status, created_at, updated_at)
         VALUES ('Coverage Fixture', '80155512', 'coverage@example.invalid', 'contacted', 'unknown', 'unsubscribed', NOW(), NOW())"
    );
    $unreachableRoute = lead_agent_mark_sms_delivery_attention($unreachableLeadId, 'invalid_number', 'LOCAL_INVALID_PHONE', 'Synthetic invalid number.', [
        'event_key' => 'integration-unreachable-route-' . $unreachableLeadId,
        'source' => 'integration_test',
    ]);
    $unreachableLead = db_one('SELECT status, follow_up_status, next_follow_up_at FROM leads WHERE id = :id LIMIT 1', ['id' => $unreachableLeadId]);
    $unreachableState = db_one('SELECT status, human_takeover, next_action_at, last_decision FROM lead_agent_states WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $unreachableLeadId]);
    integration_expect((string)($unreachableRoute['route'] ?? '') === 'nurture_unreachable', 'A lead without any deliverable channel must enter unreachable Nurture.');
    integration_expect((string)($unreachableLead['status'] ?? '') === 'no_answer' && empty($unreachableLead['next_follow_up_at']), 'Unreachable leads must leave the active pipeline without scheduling another send.');
    integration_expect((string)($unreachableState['status'] ?? '') === 'paused' && empty($unreachableState['human_takeover']) && empty($unreachableState['next_action_at']), 'Unreachable state must be parked without a human-attention halo.');

    db_execute("UPDATE lead_agent_states SET next_action_at = DATE_ADD(started_at, INTERVAL 48 HOUR) WHERE lead_id = :lead_id", ['lead_id' => $leadId]);
    integration_expect(lead_agent_repair_first_day_schedule(20) === 1, 'Existing first-day leads on the old 48-hour schedule must be accelerated once.');
    $repairedState = db_one('SELECT started_at, next_action_at FROM lead_agent_states WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]);
    integration_expect(
        (string) ($repairedState['next_action_at'] ?? '') === (string) (lead_agent_step_schedule((string) ($repairedState['started_at'] ?? ''), 1)['at'] ?? ''),
        'The first-day schedule repair must restore the guarded 30-minute schedule.'
    );

    db_execute("UPDATE lead_agent_states SET next_action_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE lead_id = :lead_id", ['lead_id' => $leadId]);
    $run = lead_agent_run_due(5, true);
    $leadResults = array_values(array_filter((array) ($run['results'] ?? []), static fn(array $result): bool => (int) ($result['lead_id'] ?? 0) === $leadId));
    integration_expect(count($leadResults) === 1, 'Dry-run worker did not process the due lead.');
    integration_expect((string) ($leadResults[0]['action'] ?? '') === 'would_send', 'Dry-run worker should produce a would-send decision.');
    integration_expect((string) ($leadResults[0]['channel'] ?? '') === 'sms', 'First due cadence action should use SMS.');
    integration_expect((int)($run['run_id'] ?? 0) > 0, 'Worker run must return its durable run id.');
    $latestRun = lead_agent_latest_run(true);
    integration_expect((string)($latestRun['status'] ?? '') === 'completed', 'Worker heartbeat must finish successfully.');
    integration_expect((int)($latestRun['due_count'] ?? 0) >= 1 && (int)($latestRun['processed_count'] ?? 0) >= 1, 'Worker heartbeat must record due and processed counts.');
    integration_expect((string)(lead_agent_run_health($latestRun)['key'] ?? '') === 'healthy', 'A newly completed run must report healthy.');

    $outboundSms = (int) db_value("SELECT COUNT(*) FROM lead_messages WHERE lead_id = :lead_id AND direction = 'outbound'", ['lead_id' => $leadId]);
    $outboundEmail = (int) db_value("SELECT COUNT(*) FROM lead_emails WHERE lead_id = :lead_id AND direction = 'outbound'", ['lead_id' => $leadId]);
    integration_expect($outboundSms === 0 && $outboundEmail === 0, 'Dry-run must not create outbound messages.');

    lead_agent_event($leadId, 'historical-sent-' . $leadId . '-' . time(), 'cadence_reserved', 'email', 'sent', 'delivered_to_provider');
    $activity = lead_agent_recent_activity(date('Y-m-d'), 100);
    $historicalVisible = array_filter($activity, static fn(array $event): bool => (int)($event['lead_id'] ?? 0) === $leadId && (string)($event['event_type'] ?? '') === 'cadence_reserved');
    integration_expect($historicalVisible !== [], 'Historical successful cadence events must be visible in the audit trail.');

    lead_agent_record_learning('general', 'sms', 'automatic_reply_sent');
    $learning = db_one("SELECT * FROM lead_agent_learning_items WHERE learning_key = 'general|sms' LIMIT 1");
    integration_expect((int) ($learning['evidence_count'] ?? 0) >= 1, 'Generalized learning evidence was not stored.');
    integration_expect(stripos((string) ($learning['guidance'] ?? ''), 'patient') === false, 'Learning guidance must not contain patient-specific content.');

    $messageId = lead_comm_insert_message([
        'lead_id' => $leadId,
        'direction' => 'outbound',
        'channel' => 'sms',
        'from_number' => '+18015550100',
        'to_number' => '+18015550199',
        'body' => 'Synthetic delivery tracking message.',
        'twilio_message_sid' => 'SM_TEST_' . $leadId,
        'twilio_status' => 'queued',
        'is_read' => 1,
    ]);
    $touchpointId = lead_agent_record_touchpoint(
        ['id' => $leadId, 'source' => 'integration_test', 'procedure_interest' => 'veneers'],
        'integration-touch-' . $leadId,
        'sms',
        1,
        'same_day',
        ['message_id' => $messageId, 'provider_id' => 'SM_TEST_' . $leadId, 'delivery_status' => 'queued', 'strategy_key' => 'goal_discovery', 'strategy_reason' => 'Synthetic next-best action.', 'decision_confidence' => 0.82]
    );
    integration_expect($touchpointId > 0, 'Automated touchpoint attribution was not created.');
    $strategyTouchpoint = db_one('SELECT strategy_key, strategy_reason, decision_confidence FROM lead_agent_touchpoints WHERE id = :id LIMIT 1', ['id' => $touchpointId]);
    integration_expect((string) ($strategyTouchpoint['strategy_key'] ?? '') === 'goal_discovery', 'Touchpoint must retain the selected conversion strategy.');
    integration_expect((float) ($strategyTouchpoint['decision_confidence'] ?? 0) >= 0.82, 'Touchpoint must retain decision confidence.');
    lead_agent_update_touchpoint_delivery('sms', $messageId, 'delivered', 'SM_TEST_' . $leadId);
    lead_agent_attribute_outcome($leadId, 'reply');
    lead_agent_attribute_outcome($leadId, 'scheduling_intent');
    lead_agent_attribute_outcome($leadId, 'consultation_booked');
    $touchpoint = db_one('SELECT * FROM lead_agent_touchpoints WHERE id = :id LIMIT 1', ['id' => $touchpointId]);
    integration_expect((string) ($touchpoint['delivery_status'] ?? '') === 'delivered' && !empty($touchpoint['delivered_at']), 'Delivery callback must mark the touchpoint delivered.');
    integration_expect(!empty($touchpoint['replied_at']) && !empty($touchpoint['scheduling_intent_at']) && !empty($touchpoint['consultation_booked_at']), 'Reply, scheduling, and booking outcomes must attribute to the latest touch.');
    $performance = lead_agent_performance_metrics(30);
    integration_expect((int) ($performance['touches'] ?? 0) >= 1 && (float) ($performance['reply_rate'] ?? 0) > 0, 'Conversion metrics must include attributed touchpoints.');
    integration_expect(lead_agent_refresh_cadence_learning(30) >= 1, 'Daily cadence learning did not aggregate touchpoint outcomes.');
    $cadenceGuidance = lead_agent_cadence_learning_guidance('sms', 5);
    integration_expect($cadenceGuidance !== [], 'Aggregated cadence guidance was not available to future drafts.');
    integration_expect(str_contains((string) ($cadenceGuidance[0]['guidance'] ?? ''), 'Observed'), 'Cadence guidance must summarize observed outcomes without patient content.');

    lead_comm_insert_message([
        'lead_id' => $leadId,
        'direction' => 'inbound',
        'channel' => 'sms',
        'from_number' => '+18015550199',
        'to_number' => '+18015550100',
        'body' => 'Monday works for me.',
        'is_read' => 0,
    ]);
    lead_agent_record_human_outbound($leadId, 'sms', 'Monday at 10 AM is available.');
    $humanState = db_one('SELECT * FROM lead_agent_states WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]);
    integration_expect(!empty($humanState['human_takeover']) && (string) ($humanState['status'] ?? '') === 'human_takeover', 'A manual staff message must give the thread to the human and stop cadence.');
    integration_expect(empty($humanState['next_action_at']), 'Human takeover must clear the next automated follow-up.');
    integration_expect(!empty($humanState['human_takeover_until']) && strtotime((string) $humanState['human_takeover_until']) > time(), 'A normal staff takeover must expire the next day instead of pausing automation forever.');
    $humanOwnedReply = lead_agent_handle_inbound($leadId, 'Monday works for me', 'sms', 'integration-human-owned-' . $leadId);
    integration_expect(!empty($humanOwnedReply['handled']) && empty($humanOwnedReply['sent']) && (string) ($humanOwnedReply['status'] ?? '') === 'human_takeover', 'The agent must stay silent when a patient replies after Rod takes over.');

    require_once dirname(__DIR__) . '/app/leads/lead_ai.php';
    $conversation = lead_ai_patient_conversation($leadId);
    integration_expect(count($conversation) >= 2, 'AI context must include the complete cross-channel patient conversation.');
    $conversationTimes = array_column($conversation, 'created_at');
    $sortedTimes = $conversationTimes;
    sort($sortedTimes);
    integration_expect($conversationTimes === $sortedTimes, 'AI patient conversation must be chronological.');
    $memory = lead_conversion_refresh(['id' => $leadId, 'full_name' => 'Lead Agent Integration Test', 'procedure_interest' => 'veneers'], 3);
    integration_expect((string) ($memory['treatment_goal'] ?? '') === 'veneers', 'Conversion memory must preserve known treatment interest.');
    integration_expect((bool) db_one('SELECT lead_id FROM lead_agent_conversion_memories WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]), 'Conversion memory must persist per lead.');

    $trackingToken = bin2hex(random_bytes(12));
    $emailId = lead_email_insert([
        'lead_id' => $leadId,
        'direction' => 'outbound',
        'from_email' => 'hello@example.invalid',
        'to_email' => 'lead-agent-test@example.invalid',
        'subject' => 'Synthetic tracked email',
        'body' => 'Synthetic email body.',
        'status' => 'sent',
        'tracking_token' => $trackingToken,
        'provider_response' => 'accepted',
        'created_by' => 'Lead Agent',
    ]);
    $emailTouchpointId = lead_agent_record_touchpoint(
        ['id' => $leadId, 'source' => 'integration_test', 'procedure_interest' => 'veneers'],
        'integration-email-touch-' . $leadId,
        'email',
        2,
        'active_sprint',
        ['email_id' => $emailId, 'delivery_status' => 'accepted']
    );
    integration_expect(lead_email_mark_opened($trackingToken), 'Tracked email open could not be recorded.');
    $emailTouchpoint = db_one('SELECT * FROM lead_agent_touchpoints WHERE id = :id LIMIT 1', ['id' => $emailTouchpointId]);
    integration_expect((string) ($emailTouchpoint['delivery_status'] ?? '') === 'opened' && !empty($emailTouchpoint['opened_at']), 'Email tracking pixel must update the attributed touchpoint.');
    lead_agent_update_touchpoint_delivery('email', $emailId, 'bounced', 'imap:test');
    $emailTouchpoint = db_one('SELECT * FROM lead_agent_touchpoints WHERE id = :id LIMIT 1', ['id' => $emailTouchpointId]);
    integration_expect((string) ($emailTouchpoint['delivery_status'] ?? '') === 'bounced', 'Email bounce must update the attributed touchpoint.');

    lead_agent_set_global_pause(true, 0);
    integration_expect(lead_agent_is_globally_paused(), 'Emergency global pause must persist.');
    $pausedRun = lead_agent_run_due(5, true);
    integration_expect(!empty($pausedRun['paused']) && (int) ($pausedRun['processed'] ?? -1) === 0, 'Paused agent must not process due leads.');
    lead_agent_set_global_pause(false, 0);
    integration_expect(!lead_agent_is_globally_paused(), 'Agent must resume after the pause is cleared.');

    db_execute("UPDATE lead_agent_states SET status = 'needs_attention', human_takeover = 1,
        pause_reason = 'Context-aware follow-up could not produce a safe message.', next_action_at = NULL
        WHERE lead_id = :lead_id", ['lead_id' => $leadId]);
    integration_expect(lead_agent_recover_drafting_exceptions(500) >= 1, 'Historical model-drafting exceptions must be requeued automatically.');
    $recoveredState = db_one('SELECT * FROM lead_agent_states WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]);
    integration_expect((string) ($recoveredState['status'] ?? '') === 'active' && empty($recoveredState['human_takeover']) && !empty($recoveredState['next_action_at']), 'Recovered drafting exceptions must resume cadence without human intervention.');

    db_execute("UPDATE lead_agent_states SET status = 'needs_attention', pause_reason = 'Integration test exception', next_action_at = NULL WHERE lead_id = :lead_id", ['lead_id' => $leadId]);
    $exceptions = lead_agent_exception_rows(100);
    $exceptionIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $exceptions);
    integration_expect(in_array($leadId, $exceptionIds, true), 'Needs Attention must include explicit agent exceptions.');

    $reportDate = date('Y-m-d');
    $report = lead_agent_refresh_daily_report($reportDate, false);
    integration_expect(isset($report['metrics']['actions_completed']), 'Daily report metrics were not generated.');
    integration_expect(isset($report['metrics']['overdue_now'], $report['metrics']['deferred_today']), 'Daily report must include queue-health metrics.');
    integration_expect(trim((string) ($report['executive_summary'] ?? '')) !== '', 'Daily executive summary was not generated.');

    lead_comm_insert_message([
        'lead_id' => $leadId,
        'direction' => 'inbound',
        'channel' => 'sms',
        'from_number' => '+18015550199',
        'to_number' => '+18015550100',
        'body' => 'Thank you, but that is too far for me to travel.',
        'is_read' => 1,
    ]);
    integration_expect(lead_agent_latest_inbound_closure_reason($leadId) === 'explicit_decline_or_distance', 'Conversation-level declines must override a stale active CRM stage.');

    lead_comm_insert_message([
        'lead_id' => $leadId,
        'direction' => 'inbound',
        'channel' => 'sms',
        'from_number' => '+18015550199',
        'to_number' => '+18015550100',
        'body' => 'You too, thank you.',
        'is_read' => 1,
    ]);
    integration_expect(lead_agent_latest_inbound_closure_reason($leadId) === 'explicit_decline_or_distance', 'A courtesy reply after a decline must not reopen automated follow-up.');

    lead_comm_insert_message([
        'lead_id' => $leadId,
        'direction' => 'inbound',
        'channel' => 'sms',
        'from_number' => '+18015550199',
        'to_number' => '+18015550100',
        'body' => 'I am interested again and want to schedule a consultation.',
        'is_read' => 1,
    ]);
    integration_expect(lead_agent_latest_inbound_closure_reason($leadId) === '', 'A later explicit scheduling request must reopen a previously declined conversation.');

    lead_comm_insert_message([
        'lead_id' => $leadId,
        'direction' => 'inbound',
        'channel' => 'sms',
        'from_number' => '+18015550199',
        'to_number' => '+18015550100',
        'body' => 'The veneers are for my brother Elkin.',
        'is_read' => 1,
    ]);
    lead_comm_insert_message([
        'lead_id' => $leadId,
        'direction' => 'inbound',
        'channel' => 'sms',
        'from_number' => '+18015550199',
        'to_number' => '+18015550100',
        'body' => 'His number is 385-230-1659.',
        'is_read' => 1,
    ]);
    $referralContact = lead_agent_historical_referral_contact($leadId);
    integration_expect(str_contains($referralContact, 'brother Elkin') && str_contains($referralContact, '385-230-1659'), 'Referral relationship and phone number must be linked across consecutive inbound messages.');

    db_rollBack();
    echo "Lead Agent integration test passed.\n";
} catch (Throwable $e) {
    db_rollBack();
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
