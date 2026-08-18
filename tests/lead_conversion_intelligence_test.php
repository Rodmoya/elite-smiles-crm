<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/leads/lead_conversion_intelligence.php';

function conversion_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$conversation = [
    ['direction' => 'outbound', 'body' => 'What would you most like to improve about your smile?', 'created_at' => '2026-08-17 09:00:00'],
    ['direction' => 'inbound', 'body' => 'I am interested in veneers and Wednesday mornings are best.', 'created_at' => '2026-08-17 09:08:00'],
    ['direction' => 'outbound', 'body' => 'Let me check current availability.', 'created_at' => '2026-08-17 09:10:00'],
];
$signals = lead_conversion_extract_signals(['procedure_interest' => 'Veneers', 'scheduling_preferred_day' => 'Wednesday', 'scheduling_preferred_time' => 'morning'], $conversation);
conversion_expect(($signals['treatment_goal'] ?? '') === 'Veneers', 'Known procedure interest must persist as the lead goal.');
conversion_expect(!empty($signals['answered_questions']['goal']), 'A known goal must be marked answered.');
conversion_expect(!empty($signals['answered_questions']['day_preference']) && !empty($signals['answered_questions']['time_preference']), 'Known scheduling preferences must be marked answered.');
conversion_expect(($signals['conversation_state'] ?? '') === 'scheduling', 'Scheduling language must produce a scheduling state.');
$decision = lead_conversion_choose_strategy($signals, 4, ['goal_discovery']);
conversion_expect(($decision['strategy_key'] ?? '') === 'scheduling_preference', 'Scheduling intent must override routine nurture rotation.');

$exploring = lead_conversion_extract_signals(['procedure_interest' => ''], [
    ['direction' => 'outbound', 'body' => 'Hi, Rod with Elite Smiles.', 'created_at' => '2026-08-17 09:00:00'],
]);
$goalDecision = lead_conversion_choose_strategy($exploring, 1, []);
conversion_expect(($goalDecision['strategy_key'] ?? '') === 'goal_discovery', 'The agent should discover the goal before pushing scheduling.');

$engaged = $signals;
$engaged['conversation_state'] = 'engaged';
$engaged['unanswered_inbound'] = false;
$engaged['primary_objection'] = '';
$rotated = lead_conversion_choose_strategy($engaged, 5, ['consultation_value', 'education'], ['consultation_value', 'education', 'trust_credibility']);
conversion_expect(($rotated['strategy_key'] ?? '') === 'trust_credibility', 'The agent must avoid repeating either of the two most recent strategies.');

$objection = $engaged;
$objection['primary_objection'] = 'fear_or_anxiety';
$objection['conversation_state'] = 'objection';
$objectionDecision = lead_conversion_choose_strategy($objection, 5, []);
conversion_expect(($objectionDecision['strategy_key'] ?? '') === 'objection_resolution', 'A known concern must be addressed before another scheduling push.');

$closed = lead_conversion_extract_signals([], [
    ['direction' => 'inbound', 'body' => 'No thank you, your office is too far.', 'created_at' => '2026-08-17 09:00:00'],
]);
conversion_expect(($closed['conversation_state'] ?? '') === 'closed' && (int) ($closed['readiness_score'] ?? -1) === 0, 'A clear decline must close the conversion state and remove priority.');
conversion_expect(lead_conversion_detect_language('Gracias, quiero saber de carillas') === 'es', 'Spanish lead language must be retained.');
conversion_expect(lead_conversion_detect_language('I would like to learn about veneers') === 'en', 'English lead language must be retained.');

echo "Lead conversion intelligence tests passed.\n";
