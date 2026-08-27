<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/leads/lead_service.php';
require_once dirname(__DIR__) . '/app/leads/consultation_doctor_reminders.php';
require_once dirname(__DIR__) . '/app/leads/consultation_patient_reminders.php';

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

$timezone = new DateTimeZone(APP_TIMEZONE);
$tenAmSchedule = consultation_doctor_reminder_schedule('2026-09-02 10:00:00');
consultation_notification_expect(count($tenAmSchedule) === 1, 'A 10:00 AM consultation must create only one Dr. Meden reminder.');
consultation_notification_expect(($tenAmSchedule[0]['key'] ?? '') === 'doctor_9am_one_hour', 'The 10:00 AM reminder must combine the 9:00 AM and one-hour events.');
consultation_notification_expect(($tenAmSchedule[0]['due_at'] ?? null)?->format('Y-m-d H:i:s') === '2026-09-02 09:00:00', 'The combined reminder must be due at 9:00 AM Utah time.');

$threePmSchedule = consultation_doctor_reminder_schedule('2026-09-02 15:00:00');
consultation_notification_expect(count($threePmSchedule) === 2, 'An afternoon consultation must create both doctor reminders.');
consultation_notification_expect(($threePmSchedule[0]['key'] ?? '') === 'doctor_9am', 'The first afternoon reminder must be the 9:00 AM reminder.');
consultation_notification_expect(($threePmSchedule[0]['due_at'] ?? null)?->format('Y-m-d H:i:s') === '2026-09-02 09:00:00', 'The fixed doctor reminder must stay at 9:00 AM Utah time.');
consultation_notification_expect(($threePmSchedule[1]['key'] ?? '') === 'doctor_one_hour_before', 'The second afternoon reminder must be the one-hour reminder.');
consultation_notification_expect(($threePmSchedule[1]['due_at'] ?? null)?->format('Y-m-d H:i:s') === '2026-09-02 14:00:00', 'The one-hour reminder must use the consultation time.');

$morningDue = consultation_doctor_reminder_due_event(
    '2026-09-02 15:00:00',
    new DateTimeImmutable('2026-09-02 09:00:00', $timezone)
);
consultation_notification_expect(($morningDue['key'] ?? '') === 'doctor_9am', 'The morning event must become due at exactly 9:00 AM.');
$oneHourDue = consultation_doctor_reminder_due_event(
    '2026-09-02 15:00:00',
    new DateTimeImmutable('2026-09-02 14:00:00', $timezone)
);
consultation_notification_expect(($oneHourDue['key'] ?? '') === 'doctor_one_hour_before', 'The one-hour event must replace the morning window at 2:00 PM.');
$afterAppointment = consultation_doctor_reminder_due_event(
    '2026-09-02 15:00:00',
    new DateTimeImmutable('2026-09-02 15:00:00', $timezone)
);
consultation_notification_expect($afterAppointment === null, 'No doctor reminder may send at or after the consultation starts.');

$rescheduled = consultation_doctor_reminder_schedule('2026-09-02 15:30:00');
consultation_notification_expect(($rescheduled[1]['due_at'] ?? null)?->format('Y-m-d H:i:s') === '2026-09-02 14:30:00', 'A rescheduled consultation must produce a new one-hour reminder time.');

$combinedMessage = consultation_doctor_reminder_message([
    'id' => 247,
    'full_name' => 'Veronica Example',
    'phone' => '+18015550247',
    'consultation_date' => '2026-09-02 10:00:00',
    'procedure_interest' => 'Veneers',
], $tenAmSchedule[0]);
consultation_notification_expect(str_contains($combinedMessage, '9:00 AM / one-hour consultation reminder'), 'The combined doctor reminder must explain why only one text was sent.');
consultation_notification_expect(str_contains($combinedMessage, '10:00 AM'), 'The doctor reminder must include the consultation time.');

$englishPatientReminder = consultation_reminder_copy([
    'id' => 248,
    'full_name' => 'Taylor Example',
    'consultation_date' => '2026-09-02 15:00:00',
    'preferred_language' => 'en',
], 'morning_of');
consultation_notification_expect(str_starts_with((string)$englishPatientReminder['sms'], 'Hi Taylor'), 'An English-preferring patient must receive the English reminder.');
consultation_notification_expect(str_contains((string)$englishPatientReminder['sms'], '11762 South State, Suite 300, Draper, UT 84020'), 'The patient SMS must include the complete practice address.');
consultation_notification_expect(str_contains((string)$englishPatientReminder['sms'], 'https://maps.app.goo.gl/ZXg2nV5ARpC7NHLUA'), 'The patient SMS must include the approved Google Maps directions link.');
consultation_notification_expect(str_contains((string)$englishPatientReminder['email'], 'Directions:'), 'The patient email must include a clear English directions label.');

$spanishPatientReminder = consultation_reminder_copy([
    'id' => 249,
    'full_name' => 'Taylor Example',
    'consultation_date' => '2026-09-02 15:00:00',
    'preferred_language' => 'es',
], 'day_before');
consultation_notification_expect(str_starts_with((string)$spanishPatientReminder['sms'], 'Hola Taylor'), 'A Spanish-preferring patient must receive the Spanish reminder.');
consultation_notification_expect(str_contains((string)$spanishPatientReminder['sms'], 'Dirección:'), 'The Spanish patient SMS must label the address in Spanish.');
consultation_notification_expect(str_contains((string)$spanishPatientReminder['sms'], 'Cómo llegar:'), 'The Spanish patient SMS must label the map link in Spanish.');
consultation_notification_expect(str_contains((string)$spanishPatientReminder['email'], 'https://maps.app.goo.gl/ZXg2nV5ARpC7NHLUA'), 'The Spanish patient email must include the approved Google Maps directions link.');

$cronSource = (string)file_get_contents(dirname(__DIR__) . '/app/api/consultation_reminder_cron.php');
consultation_notification_expect(str_contains($cronSource, "consultation_reminder_already_sent(\$leadId, \$reminderKey, 'internal_sms', \$consultationDate)"), 'Repeated cron runs must deduplicate each doctor reminder.');
consultation_notification_expect(str_contains($cronSource, 'consultation_doctor_reminder_due_event'), 'The live reminder worker must use the tested doctor timing policy.');
consultation_notification_expect(str_contains($cronSource, "'consultation_doctor_reminder_sms'"), 'Successful doctor reminders must create an auditable lead activity.');
consultation_notification_expect(str_contains($cronSource, "\$doctorOnly = filter_var"), 'The worker must support a doctor-only mode that cannot accidentally activate patient reminders.');
consultation_notification_expect(str_contains($cronSource, 'lead_language_sync_from_conversation'), 'Patient reminders must reconcile language from stored inbound conversation history before composing copy.');

$smsWebhookSource = (string)file_get_contents(dirname(__DIR__) . '/app/api/twilio_sms_webhook.php');
$emailSource = (string)file_get_contents(dirname(__DIR__) . '/app/leads/lead_email.php');
consultation_notification_expect(str_contains($smsWebhookSource, 'lead_language_record_inbound($leadId, $body)'), 'Inbound SMS must mark a clear language preference even when Lead Agent is disabled.');
consultation_notification_expect(str_contains($emailSource, 'lead_language_record_inbound($leadId, $newReplyText)'), 'Inbound email must mark language from only the patient\'s new reply.');

$workflow = (string)file_get_contents(dirname(__DIR__) . '/.github/workflows/consultation-doctor-reminders.yml');
consultation_notification_expect(str_contains($workflow, "cron: '0,30 0,14-23 * * *'"), 'The production fallback must cover every Utah consultation half-hour in both MST and MDT.');
consultation_notification_expect(str_contains($workflow, 'doctor_only=1'), 'The doctor reminder schedule must not activate patient reminders.');
consultation_notification_expect(str_contains($workflow, 'ELITE_CONSULTATION_REMINDER_CRON_SECRET || secrets.ELITE_LEAD_AGENT_CRON_SECRET'), 'The reminder schedule must support the established Lead Agent cron credential as a fallback.');

echo "Consultation notification tests passed.\n";
