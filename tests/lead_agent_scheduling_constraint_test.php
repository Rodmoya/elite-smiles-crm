<?php
declare(strict_types=1);

/**
 * Regression cover for the scheduling misread that told a patient
 * "Monday 6:00 AM sounds good" after he wrote
 * "my work schedule is from 6:00am, to 4:30 pm Monday thru Saturday".
 *
 * The parser had taken the first weekday and the first clock time out of a
 * sentence describing when he could NOT come, and confirmed a time outside the
 * 9:00 AM - 6:00 PM window the same agent had quoted a message earlier.
 */

require_once dirname(__DIR__) . '/app/leads/lead_agent.php';

function scheduling_constraint_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$lead = ['full_name' => 'Daviid Richard'];

// --- The exact message from the live thread -------------------------------
$workShift = lead_agent_scheduling_preferences('Great, my work schedule is from 6:00am, to 4:30 pm Monday thru Saturday');
scheduling_constraint_expect(
    !empty($workShift['constraint']['has_constraint']),
    'A stated work shift must be recognized as unavailability.'
);
scheduling_constraint_expect(
    $workShift['day'] === '' && $workShift['specific_time'] === '',
    'A work shift must never yield a requested day or time.'
);
scheduling_constraint_expect(
    empty($workShift['ready_for_availability']),
    'A work shift must never be treated as ready to book.'
);
scheduling_constraint_expect(
    ($workShift['constraint']['busy_end'] ?? null) === (16 * 60) + 30,
    'The end of the busy window must be read as 4:30 PM.'
);

$reply = lead_agent_scheduling_acknowledgment($lead, $workShift);
scheduling_constraint_expect(
    !str_contains($reply, 'sounds good'),
    'The agent must not confirm a time the patient just said they cannot make.'
);
scheduling_constraint_expect(
    str_contains($reply, '5:00 PM') && str_contains($reply, '5:30 PM'),
    'The reply must offer the times that survive inside office hours after the shift ends.'
);
scheduling_constraint_expect(
    !str_contains($reply, '6:00 AM'),
    'The reply must never echo the start of the work shift as an appointment time.'
);

// --- A remembered day must not resurrect the booking ----------------------
$merged = lead_agent_merge_scheduling_preferences(
    lead_agent_scheduling_preferences('Monday works'),
    $workShift
);
scheduling_constraint_expect(
    $merged['day'] === '' && empty($merged['ready_for_availability']),
    'A previously stored day must not combine with a work shift into a booking.'
);

// --- Other real replies from the same thread ------------------------------
foreach (["I'm at work 6am", "I'll call you when I'm available.", "Yes, I'm at work now, and I will be contacting you all asap."] as $message) {
    $parsed = lead_agent_scheduling_preferences($message);
    scheduling_constraint_expect(
        empty($parsed['ready_for_availability']) && ($parsed['specific_time'] ?? '') === '',
        'An at-work message must not produce a requested time: ' . $message
    );
}

// --- Office hours are enforced before any confirmation --------------------
$tooEarly = lead_agent_scheduling_preferences('Could I come Monday at 7:00 AM?');
scheduling_constraint_expect(
    !empty($tooEarly['time_outside_office_hours']) && empty($tooEarly['ready_for_availability']),
    'A 7:00 AM request is outside office hours and must not be bookable.'
);
scheduling_constraint_expect(
    str_contains(lead_agent_scheduling_acknowledgment($lead, $tooEarly), 'falls outside'),
    'An out-of-hours request must be corrected rather than confirmed.'
);
$tooLate = lead_agent_scheduling_preferences('Can I come Friday at 8:00 PM?');
scheduling_constraint_expect(
    !empty($tooLate['time_outside_office_hours']) && empty($tooLate['ready_for_availability']),
    'An 8:00 PM request is past the last consultation start and must not be bookable.'
);

// --- Genuine preferences must still flow through untouched ----------------
$afternoon = lead_agent_scheduling_preferences('Tuesday afternoon works best for me.');
scheduling_constraint_expect(
    $afternoon['day'] === 'tuesday' && $afternoon['period'] === 'afternoon' && !empty($afternoon['ready_for_availability']),
    'A plain preference containing the word "works" must not be mistaken for a work shift.'
);
$specific = lead_agent_scheduling_preferences('Can I come Thursday at 4:30 PM?');
scheduling_constraint_expect(
    $specific['day'] === 'thursday' && $specific['specific_time'] === '4:30 PM' && !empty($specific['ready_for_availability']),
    'A specific in-hours request must still be captured and bookable.'
);
scheduling_constraint_expect(
    str_contains(lead_agent_scheduling_acknowledgment($lead, $specific), 'Let me check whether that is available'),
    'A valid preference must still receive the normal acknowledgment.'
);
$afterWork = lead_agent_scheduling_preferences('I am free after work on Wednesday afternoon');
scheduling_constraint_expect(
    empty($afterWork['constraint']['has_constraint']) && $afterWork['day'] === 'wednesday',
    '"After work" describes availability and must stay a preference.'
);

// --- Spanish ---------------------------------------------------------------
$spanishShift = lead_agent_scheduling_preferences('Mi horario de trabajo es de 7:00 am a 3:00 pm');
scheduling_constraint_expect(
    !empty($spanishShift['constraint']['has_constraint'])
        && ($spanishShift['constraint']['busy_end'] ?? null) === 15 * 60,
    'A Spanish work shift must be recognized, including the "a" range separator.'
);
$spanishReply = lead_agent_scheduling_acknowledgment(['full_name' => 'Maria Lopez', 'preferred_language' => 'es'], $spanishShift);
scheduling_constraint_expect(
    str_contains($spanishReply, '3:30 PM') && str_contains($spanishReply, '¿'),
    'A Spanish work shift must be answered in Spanish with times that fit after it.'
);

// --- Helpers ---------------------------------------------------------------
scheduling_constraint_expect(
    lead_agent_minutes_to_clock(12 * 60) === '12:00 PM' && lead_agent_minutes_to_clock(0) === '12:00 AM',
    'Midnight and noon must format correctly.'
);
scheduling_constraint_expect(
    lead_agent_clock_to_minutes(12, 0, 'am') === 0 && lead_agent_clock_to_minutes(12, 30, 'pm') === (12 * 60) + 30,
    'Twelve-hour boundaries must convert correctly.'
);

fwrite(STDOUT, "Lead agent scheduling constraint tests passed.\n");
