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
    integration_expect((int) ($run['processed'] ?? 0) === 1, 'Dry-run worker did not process the due lead.');
    integration_expect((string) ($run['results'][0]['action'] ?? '') === 'would_send', 'Dry-run worker should produce a would-send decision.');
    integration_expect((string) ($run['results'][0]['channel'] ?? '') === 'sms', 'First due cadence action should use SMS.');

    $outboundSms = (int) db_value("SELECT COUNT(*) FROM lead_messages WHERE lead_id = :lead_id AND direction = 'outbound'", ['lead_id' => $leadId]);
    $outboundEmail = (int) db_value("SELECT COUNT(*) FROM lead_emails WHERE lead_id = :lead_id AND direction = 'outbound'", ['lead_id' => $leadId]);
    integration_expect($outboundSms === 0 && $outboundEmail === 0, 'Dry-run must not create outbound messages.');

    lead_agent_record_learning('general', 'sms', 'automatic_reply_sent');
    $learning = db_one("SELECT * FROM lead_agent_learning_items WHERE learning_key = 'general|sms' LIMIT 1");
    integration_expect((int) ($learning['evidence_count'] ?? 0) >= 1, 'Generalized learning evidence was not stored.');
    integration_expect(stripos((string) ($learning['guidance'] ?? ''), 'patient') === false, 'Learning guidance must not contain patient-specific content.');

    db_execute("UPDATE lead_agent_states SET status = 'needs_attention', pause_reason = 'Integration test exception', next_action_at = NULL WHERE lead_id = :lead_id", ['lead_id' => $leadId]);
    $exceptions = lead_agent_exception_rows(100);
    $exceptionIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $exceptions);
    integration_expect(in_array($leadId, $exceptionIds, true), 'Needs Attention must include explicit agent exceptions.');

    $reportDate = date('Y-m-d');
    $report = lead_agent_refresh_daily_report($reportDate, false);
    integration_expect(isset($report['metrics']['actions_completed']), 'Daily report metrics were not generated.');
    integration_expect(trim((string) ($report['executive_summary'] ?? '')) !== '', 'Daily executive summary was not generated.');

    db_rollBack();
    echo "Lead Agent integration test passed.\n";
} catch (Throwable $e) {
    db_rollBack();
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}
