<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/leads/lead_agent.php';
function language_expect(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}
foreach (['Español', 'ESPANOL', 'Spanish please', 'Español por favor.', 'Prefiero español'] as $text) {
    $draft = lead_agent_language_only_reply($text);
    language_expect($draft !== null && str_contains($draft['body'], 'español'), 'Spanish choice: ' . $text);
    language_expect(lead_agent_policy_flags($draft['body']) === [], 'Approved language reply passes content policy');
    language_expect(lead_agent_reply_failure_reason($draft, [], []) === '', 'Language reply needs no AI provider');
}
foreach (['English', 'In English please', 'Inglés'] as $text) {
    language_expect(str_contains(lead_agent_language_only_reply($text)['body'] ?? '', 'English'), 'English choice');
}
foreach (['Español STOP', 'Español, tengo dolor', 'Spanish I want Tuesday', 'No me contacte', '', 'Hola', 'Spanish how much?', 'Spanish unsubscribe'] as $text) {
    language_expect(lead_agent_language_only_reply($text) === null, 'Must not swallow substantive intent: ' . $text);
}
language_expect(str_contains(lead_agent_reply_failure_reason(null, ['ok' => false], []), 'provider'), 'Provider failure reason');
language_expect(str_contains(lead_agent_reply_failure_reason(null, ['ok' => true, 'data' => ['needs_human_review' => true]], []), 'flagged'), 'Human review reason');
language_expect(str_contains(lead_agent_reply_failure_reason(null, ['ok' => true, 'data' => ['confidence' => 0]], []), 'confidence'), 'Confidence reason');
language_expect(str_contains(lead_agent_reply_failure_reason(['body' => ''], ['ok' => true, 'data' => ['confidence' => 1]], []), 'empty'), 'Empty reply reason');
language_expect(str_contains(lead_agent_reply_failure_reason(['body' => 'test'], [], ['treatment_cost_language']), 'treatment_cost_language'), 'Exact policy flag');
$source = file_get_contents(dirname(__DIR__) . '/app/leads/lead_agent.php');
$handler = substr($source, strpos($source, 'function lead_agent_handle_inbound('));
$shortcut = strpos($handler, '$draft = lead_agent_language_only_reply($body)');
foreach (["\$intent === 'opt_out'", "\$intent === 'pause'", "'human_takeover_active'", "\$schedulingPhase === 'awaiting_slot_selection'"] as $guard) {
    language_expect(strpos($handler, $guard) < $shortcut, 'Existing guard precedes shortcut: ' . $guard);
}
$sms = file_get_contents(dirname(__DIR__) . '/app/actions/lead_send_sms.php');
language_expect(strpos($sms, 'lead_comm_clear_follow_up_attention($leadId)') < strpos($sms, "lead_agent_record_human_outbound(\$leadId, 'sms'"), 'SMS cleanup must not erase resume time');
echo "Language-only replies, substantive intent exclusion, diagnostic reasons and routing guard tests passed.\n";
