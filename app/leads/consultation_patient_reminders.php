<?php
declare(strict_types=1);

/** Deterministic, language-aware patient consultation reminder copy. */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lead_language.php';

function consultation_reminder_eligible(array $expected, array $current, ?DateTimeImmutable $now = null): bool
{
    $value = (string)($current['consultation_date'] ?? '');
    if (($current['status'] ?? '') !== 'consultation_booked'
        || !in_array((string)($current['consultation_status'] ?? ''), ['', 'scheduled'], true)
        || $value === '' || $value !== (string)($expected['consultation_date'] ?? '')) {
        return false;
    }
    $zone = new DateTimeZone(APP_TIMEZONE);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $zone);
    return $date !== false && $date->format('Y-m-d H:i:s') === $value
        && $date > ($now ?? new DateTimeImmutable('now', $zone));
}

function consultation_reminder_first_name(array $lead): string
{
    $name = trim((string)($lead['full_name'] ?? ''));
    if ($name === '') {
        return '';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    return trim((string)($parts[0] ?? ''));
}

function consultation_reminder_appointment_time(string $consultationDate): DateTimeImmutable
{
    $timezone = new DateTimeZone(APP_TIMEZONE);
    $appointment = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $consultationDate, $timezone);
    return $appointment ?: new DateTimeImmutable($consultationDate, $timezone);
}

function consultation_reminder_format_appointment(string $consultationDate, string $language = 'en'): string
{
    $dt = consultation_reminder_appointment_time($consultationDate);

    if (lead_language_normalize($language) === 'es') {
        $days = [
            'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles',
            'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado', 'Sunday' => 'domingo',
        ];
        $months = [
            'January' => 'enero', 'February' => 'febrero', 'March' => 'marzo', 'April' => 'abril',
            'May' => 'mayo', 'June' => 'junio', 'July' => 'julio', 'August' => 'agosto',
            'September' => 'septiembre', 'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre',
        ];
        return ($days[$dt->format('l')] ?? $dt->format('l')) . ', ' . $dt->format('j') . ' de '
            . ($months[$dt->format('F')] ?? $dt->format('F')) . ' a las ' . $dt->format('g:i A');
    }
    return $dt->format('l, F j') . ' at ' . $dt->format('g:i A');
}

function consultation_reminder_location_copy(string $language): array
{
    $address = '11762 South State, Suite 300, Draper, UT 84020';
    $mapUrl = 'https://maps.app.goo.gl/ZXg2nV5ARpC7NHLUA';
    if (lead_language_normalize($language) === 'es') {
        return [
            'email' => "Dirección: {$address}\nCómo llegar: {$mapUrl}",
            'sms' => "Dirección: {$address}. Cómo llegar: {$mapUrl}",
        ];
    }
    return [
        'email' => "Address: {$address}\nDirections: {$mapUrl}",
        'sms' => "Address: {$address}. Directions: {$mapUrl}",
    ];
}

function consultation_reminder_copy(array $lead, string $reminderKey): array
{
    $firstName = consultation_reminder_first_name($lead);
    $language = lead_language_preference($lead);
    $appointmentDate = (string)($lead['consultation_date'] ?? '');
    $appointment = consultation_reminder_format_appointment($appointmentDate, $language);
    $appointmentTime = consultation_reminder_appointment_time($appointmentDate)->format('g:i A');
    $location = consultation_reminder_location_copy($language);

    if ($language === 'es') {
        $greeting = $firstName !== '' ? 'Hola ' . $firstName . ',' : 'Hola,';
        if ($reminderKey === 'booking_confirmation') {
            return [
                'subject' => 'Su consulta con Elite Smiles está confirmada',
                'email' => implode("\n\n", [
                    $greeting,
                    'Su consulta gratuita con el Dr. Meden está confirmada para el ' . $appointment . '.',
                    $location['email'],
                    'Planee alrededor de 60 minutos. Si algo cambia o necesita reprogramar, responda aquí y le ayudaremos.',
                    "Atentamente,\nEl equipo de Elite Smiles",
                ]),
                'sms' => trim(($firstName !== '' ? 'Hola ' . $firstName . ', ' : 'Hola, ')
                    . 'su consulta con Elite Smiles está confirmada: ' . $appointment . '. '
                    . $location['sms'] . ' Si algo cambia, responda aquí.'),
            ];
        }
        if ($reminderKey === 'morning_of') {
            return [
                'subject' => 'Recordatorio: su consulta con Elite Smiles es hoy',
                'email' => implode("\n\n", [
                    $greeting,
                    'Este es un recordatorio de que su consulta con Elite Smiles es hoy, ' . $appointment . '.',
                    'Esperamos verle. Si algo cambia o necesita ayuda para encontrarnos, responda y avísenos.',
                    $location['email'],
                    "Atentamente,\nEl equipo de Elite Smiles",
                ]),
                'sms' => trim(($firstName !== '' ? 'Hola ' . $firstName . ', ' : 'Hola, ')
                    . 'recordatorio de Elite Smiles: su consulta es hoy a las ' . $appointmentTime
                    . '. ' . $location['sms'] . ' Si necesita algo antes, responda aquí.'),
            ];
        }

        return [
            'subject' => 'Recordatorio: su consulta con Elite Smiles es mañana',
            'email' => implode("\n\n", [
                $greeting,
                'Este es un recordatorio de que su consulta con Elite Smiles es mañana, ' . $appointment . '.',
                'Su consulta es gratis y el equipo del Dr. Meden revisará claramente las opciones para su caso.',
                'Si necesita hacer algún cambio, responda aquí y le ayudaremos.',
                $location['email'],
                "Atentamente,\nEl equipo de Elite Smiles",
            ]),
            'sms' => trim(($firstName !== '' ? 'Hola ' . $firstName . ', ' : 'Hola, ')
                . 'recordatorio de Elite Smiles: su consulta es mañana a las ' . $appointmentTime
                . '. ' . $location['sms'] . ' Si necesita algo, responda aquí.'),
        ];
    }

    $greeting = $firstName !== '' ? 'Hi ' . $firstName . ',' : 'Hi,';
    if ($reminderKey === 'booking_confirmation') {
        return [
            'subject' => 'Your Elite Smiles consultation is confirmed',
            'email' => implode("\n\n", [
                $greeting,
                'Your free consultation with Dr. Meden is confirmed for ' . $appointment . '.',
                $location['email'],
                'Plan on about 60 minutes. If anything changes or you need to reschedule, just reply here and we will help.',
                "Warmly,\nThe Elite Smiles Team",
            ]),
            'sms' => trim(($firstName !== '' ? 'Hi ' . $firstName . ', ' : 'Hi, ')
                . 'you are confirmed with Elite Smiles: ' . $appointment . '. '
                . $location['sms'] . ' If anything changes, just reply here.'),
        ];
    }
    if ($reminderKey === 'morning_of') {
        return [
            'subject' => 'Reminder: your Elite Smiles consultation is today',
            'email' => implode("\n\n", [
                $greeting,
                'This is a quick reminder that your consultation with Elite Smiles is today, ' . $appointment . '.',
                'We look forward to seeing you. If anything changes or you need help finding us, just reply and let us know.',
                $location['email'],
                "Warmly,\nThe Elite Smiles Team",
            ]),
            'sms' => trim(($firstName !== '' ? 'Hi ' . $firstName . ', ' : 'Hi, ')
                . 'reminder from Elite Smiles: your consultation is today at ' . $appointmentTime
                . '. ' . $location['sms'] . ' If you need anything before then, just reply here.'),
        ];
    }

    return [
        'subject' => 'Reminder: your Elite Smiles consultation is tomorrow',
        'email' => implode("\n\n", [
            $greeting,
            'This is a friendly reminder that your consultation with Elite Smiles is tomorrow, ' . $appointment . '.',
            'Your consultation is free, and Dr. Meden’s team will review your options, pricing, and financing clearly based on your specific case.',
            'If you need to make any changes, just reply here and we will help.',
            $location['email'],
            "Warmly,\nThe Elite Smiles Team",
        ]),
        'sms' => trim(($firstName !== '' ? 'Hi ' . $firstName . ', ' : 'Hi, ')
            . 'reminder from Elite Smiles: your consultation is tomorrow at ' . $appointmentTime
            . '. ' . $location['sms'] . ' If you need anything before then, just reply here.'),
    ];
}

/**
 * Sends the patient their booking confirmation (date, time, address,
 * directions) by SMS and email the moment a consultation is saved.
 *
 * Callers decide *when* - they should only call this when the consultation
 * date is new or changed, so an unrelated edit to the lead never re-sends
 * it. Each channel is skipped independently when it is not configured, the
 * lead has no address for it, or the lead opted out. Never throws.
 */
function consultation_booking_confirmation_send(array $lead, array $context = []): array
{
    $leadId = (int)($lead['id'] ?? 0);
    $consultationDate = trim((string)($lead['consultation_date'] ?? ''));
    $result = ['sms' => ['status' => 'skipped'], 'email' => ['status' => 'skipped']];
    if ($leadId <= 0 || $consultationDate === '') {
        return $result;
    }

    $lead = function_exists('lead_language_sync_from_conversation') ? lead_language_sync_from_conversation($lead) : $lead;
    $copy = consultation_reminder_copy($lead, 'booking_confirmation');
    $source = trim((string)($context['source'] ?? 'crm'));

    // SMS
    try {
        $phone = trim((string)($lead['phone'] ?? ''));
        $smsOpt = strtolower(trim((string)($lead['sms_opt_status'] ?? 'unknown')));
        if ($phone === '' || in_array($smsOpt, ['opted_out', 'dnd'], true)) {
            $result['sms'] = ['status' => 'skipped', 'reason' => $phone === '' ? 'no_phone' : 'sms_opted_out'];
        } elseif (!function_exists('elite_twilio_is_configured') || !elite_twilio_is_configured() || !function_exists('elite_twilio_send_sms')) {
            $result['sms'] = ['status' => 'disabled', 'reason' => 'twilio_not_configured'];
        } else {
            $send = elite_twilio_send_sms($phone, (string)$copy['sms'], [
                'lead_id' => $leadId,
                'lead' => $lead,
                'send_pushover_fallback' => true,
                'fallback_summary' => 'Twilio could not send the consultation confirmation SMS. Open lead actions to confirm manually.',
                'original_body' => (string)$copy['sms'],
            ]);
            if (!empty($send['ok'])) {
                $sentBody = (string)($send['body'] ?? $copy['sms']);
                $messageId = function_exists('lead_comm_insert_message') ? lead_comm_insert_message([
                    'lead_id' => $leadId,
                    'direction' => 'outbound',
                    'channel' => 'sms',
                    'from_number' => (string)($send['from'] ?? ''),
                    'to_number' => (string)($send['to'] ?? $phone),
                    'body' => $sentBody,
                    'twilio_message_sid' => (string)($send['twilio_sid'] ?? ''),
                    'twilio_status' => (string)($send['twilio_status'] ?? ''),
                    'is_read' => 1,
                ]) : 0;
                if (function_exists('lead_comm_insert_activity')) {
                    lead_comm_insert_activity($leadId, 'appointment_confirmation_sms', 'Sent consultation confirmation SMS: ' . mb_substr($sentBody, 0, 220), [
                        'message_id' => $messageId,
                        'consultation_date' => $consultationDate,
                        'source' => $source,
                        'twilio_sid' => $send['twilio_sid'] ?? '',
                    ], 'Appointment Confirmation');
                }
                $result['sms'] = ['status' => 'sent', 'twilio_sid' => (string)($send['twilio_sid'] ?? '')];
            } else {
                if (function_exists('lead_comm_insert_activity')) {
                    lead_comm_insert_activity($leadId, 'appointment_confirmation_sms_failed', 'Consultation confirmation SMS failed: ' . (string)($send['message'] ?? 'Unknown error'), [
                        'consultation_date' => $consultationDate,
                        'source' => $source,
                    ], 'Appointment Confirmation');
                }
                $result['sms'] = ['status' => 'failed', 'message' => (string)($send['message'] ?? '')];
            }
        }
    } catch (Throwable $e) {
        $result['sms'] = ['status' => 'failed', 'message' => $e->getMessage()];
        if (function_exists('esm_log')) {
            esm_log('appointment_confirmation', 'Confirmation SMS threw.', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
        }
    }

    // Email
    try {
        $email = trim((string)($lead['email'] ?? ''));
        $emailOpt = strtolower(trim((string)($lead['email_opt_status'] ?? 'subscribed')));
        if ($email === '' || in_array($emailOpt, ['unsubscribed', 'opted_out'], true)) {
            $result['email'] = ['status' => 'skipped', 'reason' => $email === '' ? 'no_email' : 'email_opted_out'];
        } elseif (!function_exists('elite_smtp_is_configured') || !elite_smtp_is_configured() || !function_exists('lead_email_send')) {
            $result['email'] = ['status' => 'disabled', 'reason' => 'smtp_not_configured'];
        } else {
            $send = lead_email_send($leadId, (string)$copy['subject'], (string)$copy['email'], 'Appointment Confirmation');
            $ok = !empty($send['ok']);
            if (function_exists('lead_comm_insert_activity')) {
                lead_comm_insert_activity(
                    $leadId,
                    $ok ? 'appointment_confirmation_email' : 'appointment_confirmation_email_failed',
                    $ok ? 'Sent consultation confirmation email.' : 'Consultation confirmation email failed: ' . (string)($send['message'] ?? 'Unknown error'),
                    ['consultation_date' => $consultationDate, 'source' => $source, 'email_id' => (int)($send['email_id'] ?? 0)],
                    'Appointment Confirmation'
                );
            }
            $result['email'] = ['status' => $ok ? 'sent' : 'failed', 'email_id' => (int)($send['email_id'] ?? 0), 'message' => (string)($send['message'] ?? '')];
        }
    } catch (Throwable $e) {
        $result['email'] = ['status' => 'failed', 'message' => $e->getMessage()];
        if (function_exists('esm_log')) {
            esm_log('appointment_confirmation', 'Confirmation email threw.', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
        }
    }

    if (function_exists('lead_comm_update_rollup')) {
        try { lead_comm_update_rollup($leadId); } catch (Throwable) {}
    }

    return $result;
}
