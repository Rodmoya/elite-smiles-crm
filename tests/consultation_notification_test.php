<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/leads/lead_service.php';

function consultation_notification_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$message = lead_consultation_booked_internal_message([
    'id' => 161,
    'full_name' => 'Leonard Example',
    'phone' => '+18015550161',
    'consultation_date' => '2026-08-26 15:00:00',
    'procedure_interest' => 'Veneers',
]);

consultation_notification_expect(str_contains($message, 'Leonard Example'), 'The doctor notification must identify the patient.');
consultation_notification_expect(str_contains($message, 'Wed, Aug 26, 2026 3:00 PM'), 'The doctor notification must include the confirmed local appointment time.');
consultation_notification_expect(str_contains($message, 'Interest: Veneers'), 'The doctor notification must include the known treatment interest.');
consultation_notification_expect(str_contains($message, 'leads.php?lead_id=161'), 'The doctor notification must link directly to the CRM lead.');

echo "Consultation notification tests passed.\n";
