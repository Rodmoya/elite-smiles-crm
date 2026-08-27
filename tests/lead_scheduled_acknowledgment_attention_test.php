<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/leads/lead_service.php';

function scheduled_ack_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$scheduledLead = [
    'status' => 'consultation_booked',
    'consultation_status' => 'scheduled',
    'consultation_date' => '2026-09-02 11:00:00',
];

scheduled_ack_expect(lead_operator_consultation_is_scheduled($scheduledLead), 'A booked consultation must be recognized as scheduled.');
scheduled_ack_expect(lead_operator_consultation_is_scheduled(['status' => 'contacted', 'consultation_status' => 'confirmed']), 'A confirmed consultation status must be recognized even if the legacy lead stage is stale.');
scheduled_ack_expect(!lead_operator_consultation_is_scheduled(['status' => 'contacted', 'consultation_status' => 'requested', 'consultation_date' => '']), 'A requested consultation without an appointment must remain unscheduled.');

scheduled_ack_expect(lead_operator_message_is_acknowledgment('Perfect! I will be there!'), 'A simple attendance confirmation must close the attention halo.');
scheduled_ack_expect(lead_operator_message_is_acknowledgment('Thank you. See you then.'), 'A courtesy acknowledgment must close the attention halo.');
scheduled_ack_expect(lead_operator_message_is_acknowledgment('Perfecto, allí estaré.'), 'A Spanish attendance confirmation must close the attention halo.');
scheduled_ack_expect(lead_operator_message_is_acknowledgment('👍'), 'A simple positive reaction must close the attention halo.');

scheduled_ack_expect(!lead_operator_message_is_acknowledgment('Perfect, can I change the time?'), 'A change request must remain actionable.');
scheduled_ack_expect(!lead_operator_message_is_acknowledgment('Thank you, what is the address?'), 'A question must remain actionable.');
scheduled_ack_expect(!lead_operator_message_is_acknowledgment('Okay, please call me.'), 'A call request must remain actionable.');
scheduled_ack_expect(!lead_operator_message_is_acknowledgment('Perfect. My DOB is 03/03/1968.'), 'A supplied DOB must remain actionable.');
scheduled_ack_expect(!lead_operator_message_is_acknowledgment("Thanks, but I can't make it."), 'A cancellation or attendance problem must remain actionable.');

scheduled_ack_expect(lead_operator_messages_are_acknowledgments(['Perfect!', 'Thank you.']), 'Multiple consecutive acknowledgments may close the halo.');
scheduled_ack_expect(!lead_operator_messages_are_acknowledgments(['Perfect!', 'Can I reschedule?']), 'Any unresolved actionable message must keep the halo.');

$serviceSource = (string)file_get_contents(dirname(__DIR__) . '/app/leads/lead_service.php');
$controlSource = (string)file_get_contents(dirname(__DIR__) . '/app/api/codex_control.php');
scheduled_ack_expect(str_contains($serviceSource, '!$scheduledAcknowledgment'), 'The lead-board queue must apply the scheduled-acknowledgment exception.');
scheduled_ack_expect(str_contains($controlSource, '!$scheduledAcknowledgment'), 'Elite AI command-center attention must apply the same exception.');

echo "Scheduled acknowledgment attention tests passed.\n";
