<?php
declare(strict_types=1);

/** Deterministic, language-aware patient consultation reminder copy. */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lead_language.php';

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
