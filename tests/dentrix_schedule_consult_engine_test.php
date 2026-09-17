<?php
declare(strict_types=1);

/**
 * DB-level tests for the Dentrix Schedule Bridge consult slot engine.
 * Runs inside a rolled-back transaction, matching this repo's DB test convention.
 */

require_once dirname(__DIR__) . '/app/dentrix_schedule/dentrix_schedule_service.php';
require_once dirname(__DIR__) . '/app/dentrix_schedule/dentrix_schedule_consult_engine.php';

function dce_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function dce_slot(array $slots, string $startTime): ?array
{
    foreach ($slots as $s) {
        if ($s['start_time'] === $startTime) {
            return $s;
        }
    }
    return null;
}

dentrix_schedule_ensure_schema();
db_begin();

try {
    // Use a date far enough in the future that "past times" filtering never
    // interferes with the open-slot assertions below.
    $futureDate = date('Y-m-d', strtotime('+30 days'));

    // --- Baseline: an empty day should offer every window slot as open ---
    $slots = dentrix_schedule_generate_consult_slots($futureDate);
    dce_expect(count($slots) === 10, 'Must generate exactly 10 slots (4 morning + 6 afternoon 30-min slots). Got: ' . count($slots));
    foreach ($slots as $s) {
        dce_expect($s['status'] === 'open', "Slot {$s['start_time']} must default to open on an empty day.");
    }
    dce_expect(dce_slot($slots, '10:00:00') !== null, 'Must include the 10:00am slot.');
    dce_expect(dce_slot($slots, '11:30:00') !== null, 'Must include the last morning slot (11:30am).');
    dce_expect(dce_slot($slots, '13:00:00') !== null, 'Must include the first afternoon slot (1:00pm).');
    dce_expect(dce_slot($slots, '15:30:00') !== null, 'Must include the last afternoon slot (3:30pm).');
    dce_expect(dce_slot($slots, '12:00:00') === null, 'Must never generate a slot inside the lunch hour.');
    dce_expect(dce_slot($slots, '12:30:00') === null, 'Must never generate a slot inside the lunch hour.');

    // --- Lunch block: must exist as a real calendar_blocks row, practice-wide ---
    $lunch = db_one("SELECT * FROM dentrix_calendar_blocks WHERE schedule_date = :d AND block_type = 'lunch'", ['d' => $futureDate]);
    dce_expect($lunch !== null, 'Generating slots must create a lunch calendar block.');
    dce_expect($lunch['operatory'] === null, 'The lunch block must apply practice-wide (operatory NULL).');
    dce_expect($lunch['start_time'] === '12:00:00' && $lunch['end_time'] === '13:00:00', 'Lunch block must run 12:00pm-1:00pm.');

    // --- OP-3 occupied: an appointment in OP-3 must occupy its slot ---
    db_insert(
        "INSERT INTO dentrix_appointments (schedule_date, day_index, operatory, start_time, patient_name, source, status, dentrix_entry_status, is_manual, created_at, updated_at)
         VALUES (:d, 1, 'OP-3', '10:30:00', 'Occupied, Patient', 'dentrix_import', 'imported', 'not_required', 0, NOW(), NOW())",
        ['d' => $futureDate]
    );
    $slots = dentrix_schedule_generate_consult_slots($futureDate);
    dce_expect(dce_slot($slots, '10:30:00')['status'] === 'occupied', 'A real OP-3 appointment must occupy its own consult slot.');
    dce_expect(dce_slot($slots, '10:00:00')['status'] === 'open', 'An OP-3 appointment must not occupy an unrelated slot.');

    // --- Manual block: blocks OP-3 (or practice-wide) for its window ---
    $blockResult = dentrix_schedule_block_slot($futureDate, '14:00:00', '15:00:00', null, 'closed', 'Office closed early', null);
    dce_expect(!empty($blockResult['ok']), 'block_slot() must succeed.');
    $slots = dentrix_schedule_available_consult_slots($futureDate);
    dce_expect(dce_slot($slots, '14:00:00') === null, 'A manual block must remove the slot from availability.');
    dce_expect(dce_slot($slots, '14:30:00') === null, 'A manual block spanning two 30-min slots must remove both.');
    dce_expect(dce_slot($slots, '13:30:00') !== null, 'A manual block must not remove slots outside its window.');

    // Unblock restores it.
    $unblockResult = dentrix_schedule_unblock_slot((int)$blockResult['block_id'], null);
    dce_expect(!empty($unblockResult['ok']), 'unblock_slot() must succeed.');
    $slots = dentrix_schedule_available_consult_slots($futureDate);
    dce_expect(dce_slot($slots, '14:00:00') !== null, 'Removing the manual block must restore the slot to availability.');

    // The lunch block itself cannot be removed via unblock_slot().
    $lunchUnblock = dentrix_schedule_unblock_slot((int)$lunch['id'], null);
    dce_expect(empty($lunchUnblock['ok']), 'The lunch block must be protected from removal via unblock_slot().');

    // --- OP-1/OP-2 surgery/sedation guard: blocks OP-3 slots in its (assumed) window ---
    db_insert(
        "INSERT INTO dentrix_appointments (schedule_date, day_index, operatory, start_time, patient_name, appointment_reason, source, status, dentrix_entry_status, is_manual, created_at, updated_at)
         VALUES (:d, 1, 'OP-1', '10:00:00', 'Guarded, Patient', 'Wisdom Teeth Extraction, sedation', 'dentrix_import', 'imported', 'not_required', 0, NOW(), NOW())",
        ['d' => $futureDate]
    );
    $slots = dentrix_schedule_generate_consult_slots($futureDate);
    dce_expect(dce_slot($slots, '10:00:00')['status'] === 'blocked', 'A guard-keyword OP-1 appointment must block the overlapping consult slot.');
    dce_expect(dce_slot($slots, '11:30:00')['status'] === 'blocked', 'The guard window must extend across the assumed procedure duration.');
    dce_expect(dce_slot($slots, '13:00:00')['status'] === 'open', 'The guard window must not extend into the afternoon block.');

    // A normal (non-guard-keyword) OP-1/OP-2 appointment must NOT block OP-3.
    db_query("DELETE FROM dentrix_appointments WHERE schedule_date = :d AND operatory = 'OP-1'", ['d' => $futureDate]);
    db_insert(
        "INSERT INTO dentrix_appointments (schedule_date, day_index, operatory, start_time, patient_name, appointment_reason, source, status, dentrix_entry_status, is_manual, created_at, updated_at)
         VALUES (:d, 1, 'OP-1', '10:00:00', 'Routine, Patient', 'Cleaning', 'dentrix_import', 'imported', 'not_required', 0, NOW(), NOW())",
        ['d' => $futureDate]
    );
    $slots = dentrix_schedule_generate_consult_slots($futureDate);
    dce_expect(dce_slot($slots, '10:00:00')['status'] === 'open', 'A routine OP-1 appointment must not block the OP-3 consult slot.');

    // --- Held/booked slots (CRM-driven state) must never be silently overwritten by a re-sync ---
    db_query("UPDATE dentrix_consult_slots SET status = 'held' WHERE schedule_date = :d AND start_time = '15:00:00'", ['d' => $futureDate]);
    dentrix_schedule_generate_consult_slots($futureDate);
    $heldRow = db_one("SELECT status FROM dentrix_consult_slots WHERE schedule_date = :d AND start_time = '15:00:00'", ['d' => $futureDate]);
    dce_expect(($heldRow['status'] ?? null) === 'held', 'Regenerating slots must never overwrite a held/booked slot back to open/occupied.');

    // --- Past-date availability must return nothing ---
    $pastDate = date('Y-m-d', strtotime('-1 day'));
    $pastSlots = dentrix_schedule_available_consult_slots($pastDate);
    dce_expect($pastSlots === [], 'A past schedule_date must never offer any available slots.');

    echo "Dentrix consult slot engine tests passed.\n";
} finally {
    db_rollBack();
}
