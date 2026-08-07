<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/ai/elite_ai_service.php';

function elite_ai_stage_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$aliases = [
    'first touch attempted' => 'attempted_contact',
    'first touch sent' => 'contacted',
    'scheduling' => 'in_contact',
    'consultation booked' => 'consultation_booked',
    'no show' => 'no_show_reschedule',
    'consult completed' => 'consult_completed',
    'sale closed' => 'treatment_accepted',
    'treatment completed' => 'treatment_completed',
    'nurture' => 'no_answer',
    'unsubscribe' => 'opted_out',
    'archive' => 'lost_lead',
];
foreach ($aliases as $phrase => $expectedStage) {
    elite_ai_stage_expect(elite_ai_stage_from_text($phrase) === $expectedStage, "Stage alias failed: {$phrase}");
}

foreach (elite_ai_stage_aliases() as $stage => $stageAliases) {
    $command = 'move Jordan to ' . (string) $stageAliases[0];
    elite_ai_stage_expect(elite_ai_requested_stage_key($command) === $stage, "Canonical stage command failed: {$stage}");
    elite_ai_stage_expect(
        elite_ai_request_has_explicit_stage_approval(['instruction' => $command]),
        "Explicit approval policy drifted for stage: {$stage}"
    );
}

$directives = [
    'move Jordan to consultation booked' => ['consultation_booked', 'Jordan'],
    'mark Jordan as opted out' => ['opted_out', 'Jordan'],
    'set them to lost' => ['lost_lead', ''],
    'Jordan should be moved to consultation booked' => ['consultation_booked', 'Jordan'],
    'Jordan needs to be moved into scheduling' => ['in_contact', 'Jordan'],
];
foreach ($directives as $phrase => [$expectedStage, $expectedLeadQuery]) {
    elite_ai_stage_expect(elite_ai_prompt_requests_stage_move($phrase), "Stage command was not detected: {$phrase}");
    elite_ai_stage_expect(elite_ai_requested_stage_key($phrase) === $expectedStage, "Wrong stage parsed: {$phrase}");
    elite_ai_stage_expect(elite_ai_extract_stage_move_lead_query($phrase) === $expectedLeadQuery, "Wrong lead query parsed: {$phrase}");
    elite_ai_stage_expect(
        elite_ai_request_has_explicit_stage_approval(['instruction' => $phrase]),
        "Explicit stage command should have consistent approval: {$phrase}"
    );
}

$historical = 'I already changed Jordan to consultation booked';
elite_ai_stage_expect(!elite_ai_prompt_requests_stage_move($historical), 'Historical status statement must not execute as a new stage command.');
elite_ai_stage_expect(
    elite_ai_requested_stage_key('move Jordan from consultation booked to lost') === 'lost_lead',
    'Destination stage must win when the prompt also names the current stage.'
);

$passivePlan = elite_ai_plan_request('Jordan should be moved to consultation booked', '', []);
elite_ai_stage_expect((string) ($passivePlan['intent'] ?? '') === 'move_stage', 'Passive directive should produce move_stage intent.');
elite_ai_stage_expect((string) ($passivePlan['target_stage'] ?? '') === 'consultation_booked', 'Deterministic plan must carry target_stage.');
elite_ai_stage_expect((string) ($passivePlan['lead_query'] ?? '') === 'Jordan', 'Deterministic plan must carry the lead query.');

elite_ai_stage_expect(
    elite_ai_plan_target_stage(['target_stage' => 'opted_out'], 'move this lead to consultation booked') === 'opted_out',
    'Structured target_stage must take precedence over reparsing the raw prompt.'
);
elite_ai_stage_expect(
    elite_ai_plan_target_stage(['target_stage' => 'not_a_stage'], 'move this lead to consultation booked') === 'consultation_booked',
    'Invalid planner stages must be rejected and safely parsed from the explicit prompt.'
);

$schema = elite_ai_planner_schema();
elite_ai_stage_expect(isset($schema['properties']['target_stage']), 'Planner schema must define target_stage.');
elite_ai_stage_expect(in_array('target_stage', (array) ($schema['required'] ?? []), true), 'Planner schema must require target_stage.');
elite_ai_stage_expect(
    elite_ai_prompt_requests_stage_count('How many sale closed leads?') === 'treatment_accepted',
    'Stage counts must use the same canonical stage vocabulary.'
);

db_begin();
try {
    $leadId = db_insert(
        "INSERT INTO leads (full_name, phone, email, status, sms_opt_status, email_opt_status, created_at, updated_at)
         VALUES ('Elite AI Stage Routing Test', '+18015550198', 'elite-ai-stage-test@example.invalid', 'contacted', 'opted_in', 'subscribed', NOW(), NOW())"
    );
    elite_ai_stage_expect($leadId > 0, 'Synthetic stage-routing lead was not created.');

    $ambiguous = elite_ai_handle_move_stage_action(
        ['id' => 0, 'first_name' => 'Test', 'role' => 'admin'],
        [
            'lead_id' => $leadId,
            'target_status' => 'opted_out',
            'instruction' => 'Jordan belongs in opted out',
        ],
        'desktop'
    );
    elite_ai_stage_expect(!empty($ambiguous['requires_approval']), 'Planner-only interpretation must not grant its own approval.');
    elite_ai_stage_expect((string) db_value('SELECT status FROM leads WHERE id = :id', ['id' => $leadId]) === 'contacted', 'Unapproved stage interpretation changed CRM state.');

    $explicit = elite_ai_handle_move_stage_action(
        ['id' => 0, 'first_name' => 'Test', 'role' => 'admin'],
        [
            'lead_id' => $leadId,
            'target_status' => 'opted_out',
            'instruction' => 'mark Elite AI Stage Routing Test as opted out',
        ],
        'desktop'
    );
    elite_ai_stage_expect(!empty($explicit['ok']) && empty($explicit['requires_approval']), 'Explicit stage command did not execute consistently.');
    elite_ai_stage_expect((string) db_value('SELECT status FROM leads WHERE id = :id', ['id' => $leadId]) === 'opted_out', 'Explicit stage command did not update the lead.');
} finally {
    db_rollback();
}

echo "Elite AI stage routing tests passed.\n";
