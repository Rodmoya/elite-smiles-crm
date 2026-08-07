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

db_begin();
try {
    $leadId = db_insert(
        "INSERT INTO leads (full_name, phone, email, status, sms_opt_status, email_opt_status, created_at, updated_at)
         VALUES ('Lead Agent Integration Test', '+18015550199', 'lead-agent-test@example.invalid', 'contacted', 'opted_in', 'subscribed', NOW(), NOW())"
    );
    integration_expect($leadId > 0, 'Synthetic lead was not created.');

    $enrollment = lead_agent_enroll($leadId, ['source' => 'integration_test']);
    integration_expect(!empty($enrollment['enrolled']), 'Synthetic lead was not enrolled.');

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
        ['message_id' => $messageId, 'provider_id' => 'SM_TEST_' . $leadId, 'delivery_status' => 'queued']
    );
    integration_expect($touchpointId > 0, 'Automated touchpoint attribution was not created.');
    lead_agent_update_touchpoint_delivery('sms', $messageId, 'delivered', 'SM_TEST_' . $leadId);
    lead_agent_attribute_outcome($leadId, 'reply');
    lead_agent_attribute_outcome($leadId, 'scheduling_intent');
    lead_agent_attribute_outcome($leadId, 'consultation_booked');
    $touchpoint = db_one('SELECT * FROM lead_agent_touchpoints WHERE id = :id LIMIT 1', ['id' => $touchpointId]);
    integration_expect((string) ($touchpoint['delivery_status'] ?? '') === 'delivered' && !empty($touchpoint['delivered_at']), 'Delivery callback must mark the touchpoint delivered.');
    integration_expect(!empty($touchpoint['replied_at']) && !empty($touchpoint['scheduling_intent_at']) && !empty($touchpoint['consultation_booked_at']), 'Reply, scheduling, and booking outcomes must attribute to the latest touch.');
    $performance = lead_agent_performance_metrics(30);
    integration_expect((int) ($performance['touches'] ?? 0) >= 1 && (float) ($performance['reply_rate'] ?? 0) > 0, 'Conversion metrics must include attributed touchpoints.');

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

    db_execute("UPDATE lead_agent_states SET status = 'needs_attention', pause_reason = 'Integration test exception', next_action_at = NULL WHERE lead_id = :lead_id", ['lead_id' => $leadId]);
    $exceptions = lead_agent_exception_rows(100);
    $exceptionIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $exceptions);
    integration_expect(in_array($leadId, $exceptionIds, true), 'Needs Attention must include explicit agent exceptions.');

    $reportDate = date('Y-m-d');
    $report = lead_agent_refresh_daily_report($reportDate, false);
    integration_expect(isset($report['metrics']['actions_completed']), 'Daily report metrics were not generated.');
    integration_expect(isset($report['metrics']['overdue_now'], $report['metrics']['deferred_today']), 'Daily report must include queue-health metrics.');
    integration_expect(trim((string) ($report['executive_summary'] ?? '')) !== '', 'Daily executive summary was not generated.');

    db_rollBack();
    echo "Lead Agent integration test passed.\n";
} catch (Throwable $e) {
    db_rollBack();
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
