<?php
declare(strict_types=1);

/**
 * Pure timing and message helpers for Dr. Meden's consultation reminders.
 *
 * Appointment timestamps are stored in the CRM's local timezone. Using
 * America/Denver through APP_TIMEZONE keeps the fixed reminder at 9:00 AM
 * Utah time across both MST and MDT.
 */

if (!function_exists('consultation_doctor_reminder_datetime')) {
    function consultation_doctor_reminder_datetime(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timezone = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'America/Denver');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }

        try {
            return new DateTimeImmutable($value, $timezone);
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('consultation_doctor_reminder_schedule')) {
    function consultation_doctor_reminder_schedule(string $consultationDate): array
    {
        $appointment = consultation_doctor_reminder_datetime($consultationDate);
        if (!$appointment) {
            return [];
        }

        $morning = $appointment->setTime(9, 0, 0);
        $oneHourBefore = $appointment->modify('-1 hour');
        $events = [];

        if ($morning->getTimestamp() === $oneHourBefore->getTimestamp()) {
            $events[] = [
                'key' => 'doctor_9am_one_hour',
                'kind' => 'combined',
                'due_at' => $morning,
            ];
        } else {
            // A fixed 9:00 AM reminder is useful only while the consultation
            // is still ahead. A 9:00 AM consultation therefore gets its
            // one-hour reminder at 8:00 AM, not another text at start time.
            if ($morning < $appointment) {
                $events[] = [
                    'key' => 'doctor_9am',
                    'kind' => 'morning',
                    'due_at' => $morning,
                ];
            }
            if ($oneHourBefore->format('Y-m-d') === $appointment->format('Y-m-d') && $oneHourBefore < $appointment) {
                $events[] = [
                    'key' => 'doctor_one_hour_before',
                    'kind' => 'one_hour',
                    'due_at' => $oneHourBefore,
                ];
            }
        }

        usort($events, static function (array $left, array $right): int {
            return $left['due_at']->getTimestamp() <=> $right['due_at']->getTimestamp();
        });

        foreach ($events as $index => &$event) {
            $next = $events[$index + 1]['due_at'] ?? $appointment;
            $event['window_ends_at'] = $next;
            $event['consultation_at'] = $appointment;
        }
        unset($event);

        return $events;
    }
}

if (!function_exists('consultation_doctor_reminder_due_event')) {
    function consultation_doctor_reminder_due_event(string $consultationDate, DateTimeImmutable $now): ?array
    {
        $timezone = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'America/Denver');
        $localNow = $now->setTimezone($timezone);

        foreach (consultation_doctor_reminder_schedule($consultationDate) as $event) {
            if ($localNow >= $event['due_at'] && $localNow < $event['window_ends_at']) {
                return $event;
            }
        }

        return null;
    }
}

if (!function_exists('consultation_doctor_reminder_phone_label')) {
    function consultation_doctor_reminder_phone_label(string $phone): string
    {
        $raw = trim($phone);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10
            ? sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 4))
            : ($raw !== '' ? $raw : 'Not set in CRM');
    }
}

if (!function_exists('consultation_doctor_reminder_message')) {
    function consultation_doctor_reminder_message(array $lead, array $event): string
    {
        $name = trim((string)($lead['full_name'] ?? ''));
        if ($name === '') {
            $name = 'Lead #' . (int)($lead['id'] ?? 0);
        }

        $appointment = $event['consultation_at'] ?? consultation_doctor_reminder_datetime((string)($lead['consultation_date'] ?? ''));
        $time = $appointment instanceof DateTimeImmutable ? $appointment->format('g:i A') : 'the scheduled time';
        $kind = (string)($event['kind'] ?? 'morning');
        $prefix = match ($kind) {
            'one_hour' => 'One-hour consultation reminder: ',
            'combined' => '9:00 AM / one-hour consultation reminder: ',
            default => 'Today\'s consultation reminder: ',
        };
        $interest = trim((string)($lead['procedure_interest'] ?? ''));

        return $prefix
            . $name
            . ' at ' . $time
            . '. Patient phone: ' . consultation_doctor_reminder_phone_label((string)($lead['phone'] ?? ''))
            . ($interest !== '' ? '. Interest: ' . $interest : '')
            . '. Open: ' . base_url('leads.php?lead_id=' . (int)($lead['id'] ?? 0));
    }
}
