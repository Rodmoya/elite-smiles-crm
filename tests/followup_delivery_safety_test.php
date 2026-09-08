<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/leads/lead_agent.php';
require_once dirname(__DIR__) . '/app/leads/lead_ai.php';
require_once dirname(__DIR__) . '/app/leads/consultation_patient_reminders.php';
function verify_delivery(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
foreach (['Wrong #', 'wrong number', 'Sorry, wrong number.', 'You have the wrong number', 'Número equivocado'] as $reply) {
    verify_delivery(lead_agent_classify_inbound($reply) === 'wrong_number', 'Wrong-number suppression: ' . $reply);
}
foreach (['Is this the wrong number?', 'I gave you the wrong number before', 'My number is correct'] as $reply) {
    verify_delivery(!lead_comm_is_wrong_number($reply), 'Avoid ambiguous wrong-number suppression: ' . $reply);
}
verify_delivery(lead_agent_classify_inbound('STOP') === 'opt_out', 'STOP still opts out.');
$now = new DateTimeImmutable('2026-09-08 11:08:00', new DateTimeZone(APP_TIMEZONE));
$lead = ['consultation_date' => '2026-09-08 10:00:00'];
verify_delivery(lead_ai_appointment_elapsed($lead, $now), 'Same-day elapsed time.');
verify_delivery(!lead_ai_appointment_elapsed(['consultation_date' => '2026-09-08 12:00:00'], $now), 'Future appointment.');
verify_delivery(!lead_ai_appointment_elapsed(['consultation_date' => 'invalid'], $now), 'Invalid date.');
foreach (['Hola, todo está listo para su consulta hoy a las 10 AM.', 'See you at your appointment today.'] as $text) {
    $out = lead_ai_guard_elapsed_appointment($lead, ['reply' => $text], $now);
    verify_delivery($out['reply'] !== $text && !$out['should_send'] && $out['needs_human_review'], 'Unsafe SMS guarded.');
}
$email = lead_ai_guard_elapsed_appointment($lead, ['subject' => 'See you soon', 'body' => 'Everything is ready for your appointment.'], $now);
verify_delivery($email['subject'] === 'Checking in about your consultation', 'Email subject and body guarded.');
$safe = ['reply' => 'How did your consultation go?'];
verify_delivery(lead_ai_guard_elapsed_appointment($lead, $safe, $now) === $safe, 'Safe retrospective copy unchanged.');
$future = ['status' => 'consultation_booked', 'consultation_status' => 'scheduled', 'consultation_date' => '2026-09-08 12:00:00'];
verify_delivery(consultation_reminder_eligible($future, $future, $now), 'Future booked appointment eligible.');
verify_delivery(!consultation_reminder_eligible($future, array_replace($future, ['consultation_status' => 'cancelled']), $now), 'Cancellation blocks reminders.');
verify_delivery(!consultation_reminder_eligible($future, array_replace($future, ['consultation_date' => '2026-09-09 12:00:00']), $now), 'Rescheduling invalidates old reminder.');
verify_delivery(!consultation_reminder_eligible($lead, array_replace($future, $lead), $now), 'Elapsed reminders blocked.');
verify_delivery(!consultation_reminder_eligible($future, $future, new DateTimeImmutable('2026-09-08 12:00:00', new DateTimeZone(APP_TIMEZONE))), 'Exact appointment boundary blocked.');
echo "Follow-up delivery safety tests passed.\n";
