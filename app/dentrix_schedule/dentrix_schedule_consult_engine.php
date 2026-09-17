<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Dentrix Schedule Bridge — consult slot engine.
 *
 * Generates and queries OP-3 consult availability from the imported Dentrix
 * schedule, manual calendar blocks, and CRM-booked appointments.
 *
 * DURATION ASSUMPTION: this Dentrix export layout does not print an explicit
 * end time per appointment (see dentrix_schedule_parser.php). Conflict
 * checking therefore assumes a default appointment length for occupancy
 * purposes, and a longer assumed length specifically for guard-keyword
 * (surgery/sedation/etc.) appointments in OP-1/OP-2, since those routinely
 * run long and this rule exists specifically to avoid scheduling a consult
 * during/adjacent to one. Both are named constants below — tune them once
 * real office patterns are confirmed; erring toward more blocking (fewer
 * false "open" slots) was the deliberate choice given incomplete input data.
 */

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/dentrix_schedule_service.php';
require_once __DIR__ . '/dentrix_schedule_parser.php';

if (!defined('DENTRIX_SCHEDULE_SLOT_MINUTES')) {
    define('DENTRIX_SCHEDULE_SLOT_MINUTES', 30);
}
if (!defined('DENTRIX_SCHEDULE_DEFAULT_APPT_MINUTES')) {
    define('DENTRIX_SCHEDULE_DEFAULT_APPT_MINUTES', 30);
}
if (!defined('DENTRIX_SCHEDULE_GUARD_BLOCK_MINUTES')) {
    define('DENTRIX_SCHEDULE_GUARD_BLOCK_MINUTES', 120);
}
if (!defined('DENTRIX_SCHEDULE_PREFERRED_OPERATORY')) {
    define('DENTRIX_SCHEDULE_PREFERRED_OPERATORY', 'OP-3');
}

if (!function_exists('dentrix_schedule_time_to_minutes')) {
    function dentrix_schedule_time_to_minutes(string $hms): int
    {
        [$h, $m] = array_map('intval', explode(':', $hms));
        return $h * 60 + $m;
    }
}

if (!function_exists('dentrix_schedule_minutes_to_time')) {
    function dentrix_schedule_minutes_to_time(int $minutes): string
    {
        $minutes = max(0, $minutes) % (24 * 60);
        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }
}

if (!function_exists('dentrix_schedule_consult_window_starts')) {
    /**
     * Fixed 30-minute consult slot starts: 10:00am-12:00pm and 1:00pm-4:00pm.
     */
    function dentrix_schedule_consult_window_starts(): array
    {
        $starts = [];
        foreach ([[10 * 60, 12 * 60], [13 * 60, 16 * 60]] as [$from, $to]) {
            for ($m = $from; $m < $to; $m += DENTRIX_SCHEDULE_SLOT_MINUTES) {
                $starts[] = $m;
            }
        }
        return $starts;
    }
}

if (!function_exists('dentrix_schedule_lunch_window')) {
    function dentrix_schedule_lunch_window(): array
    {
        return ['start' => '12:00:00', 'end' => '13:00:00'];
    }
}

if (!function_exists('dentrix_schedule_ranges_overlap')) {
    function dentrix_schedule_ranges_overlap(int $aStart, int $aEnd, int $bStart, int $bEnd): bool
    {
        return $aStart < $bEnd && $bStart < $aEnd;
    }
}

if (!function_exists('dentrix_schedule_ensure_lunch_block')) {
    function dentrix_schedule_ensure_lunch_block(string $scheduleDate): void
    {
        $lunch = dentrix_schedule_lunch_window();
        $existing = db_one(
            "SELECT id FROM dentrix_calendar_blocks WHERE schedule_date = :d AND block_type = 'lunch' AND operatory IS NULL LIMIT 1",
            ['d' => $scheduleDate]
        );
        if ($existing) {
            return;
        }
        db_insert(
            "INSERT INTO dentrix_calendar_blocks (schedule_date, operatory, start_time, end_time, block_type, reason, created_at, updated_at)
             VALUES (:d, NULL, :start, :end, 'lunch', 'Lunch (practice-wide)', NOW(), NOW())",
            ['d' => $scheduleDate, 'start' => $lunch['start'], 'end' => $lunch['end']]
        );
    }
}

if (!function_exists('dentrix_schedule_guard_conflict_windows')) {
    /**
     * Returns [ [start_minutes, end_minutes], ... ] busy windows for OP-1/OP-2
     * guard-keyword (surgery/sedation/etc.) appointments on the given date,
     * using DENTRIX_SCHEDULE_GUARD_BLOCK_MINUTES as the assumed duration.
     */
    function dentrix_schedule_guard_conflict_windows(string $scheduleDate): array
    {
        $rows = db_all(
            "SELECT start_time, appointment_reason, raw_block_text FROM dentrix_appointments
             WHERE schedule_date = :d AND operatory IN ('OP-1', 'OP-2') AND status != 'cancelled' AND start_time IS NOT NULL",
            ['d' => $scheduleDate]
        );
        $guardKeywords = dentrix_schedule_parser_guard_keywords();
        $windows = [];
        foreach ($rows as $row) {
            $haystack = strtolower((string)($row['appointment_reason'] ?? '') . ' ' . (string)($row['raw_block_text'] ?? ''));
            foreach ($guardKeywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    $start = dentrix_schedule_time_to_minutes((string)$row['start_time']);
                    $windows[] = [$start, $start + DENTRIX_SCHEDULE_GUARD_BLOCK_MINUTES];
                    break;
                }
            }
        }
        return $windows;
    }
}

if (!function_exists('dentrix_schedule_op3_occupied_windows')) {
    function dentrix_schedule_op3_occupied_windows(string $scheduleDate): array
    {
        $rows = db_all(
            "SELECT start_time FROM dentrix_appointments
             WHERE schedule_date = :d AND operatory = :op AND status != 'cancelled' AND start_time IS NOT NULL",
            ['d' => $scheduleDate, 'op' => DENTRIX_SCHEDULE_PREFERRED_OPERATORY]
        );
        $windows = [];
        foreach ($rows as $row) {
            $start = dentrix_schedule_time_to_minutes((string)$row['start_time']);
            $windows[] = [$start, $start + DENTRIX_SCHEDULE_DEFAULT_APPT_MINUTES];
        }
        return $windows;
    }
}

if (!function_exists('dentrix_schedule_manual_block_windows')) {
    function dentrix_schedule_manual_block_windows(string $scheduleDate): array
    {
        $rows = db_all(
            "SELECT start_time, end_time, block_type, reason FROM dentrix_calendar_blocks
             WHERE schedule_date = :d AND (operatory IS NULL OR operatory = :op)",
            ['d' => $scheduleDate, 'op' => DENTRIX_SCHEDULE_PREFERRED_OPERATORY]
        );
        $windows = [];
        foreach ($rows as $row) {
            $windows[] = [
                'start' => dentrix_schedule_time_to_minutes((string)$row['start_time']),
                'end' => dentrix_schedule_time_to_minutes((string)$row['end_time']),
                'label' => (string)($row['block_type'] ?? 'manual_block') . (!empty($row['reason']) ? (': ' . $row['reason']) : ''),
            ];
        }
        return $windows;
    }
}

if (!function_exists('dentrix_schedule_generate_consult_slots')) {
    /**
     * (Re)computes dentrix_consult_slots for one schedule_date from the
     * current imported schedule, manual blocks, and guard-keyword conflicts.
     * Never overwrites a slot the CRM has already held/booked — those rows
     * are left untouched so re-syncing the Dentrix PDF cannot silently
     * clobber a consult a patient already scheduled through the CRM.
     */
    function dentrix_schedule_generate_consult_slots(string $scheduleDate, ?int $importId = null): array
    {
        dentrix_schedule_ensure_schema();
        dentrix_schedule_ensure_lunch_block($scheduleDate);

        $op3Occupied = dentrix_schedule_op3_occupied_windows($scheduleDate);
        $manualBlocks = dentrix_schedule_manual_block_windows($scheduleDate);
        $guardWindows = dentrix_schedule_guard_conflict_windows($scheduleDate);

        $results = [];
        foreach (dentrix_schedule_consult_window_starts() as $startMinutes) {
            $endMinutes = $startMinutes + DENTRIX_SCHEDULE_SLOT_MINUTES;
            $startTime = dentrix_schedule_minutes_to_time($startMinutes);
            $endTime = dentrix_schedule_minutes_to_time($endMinutes);

            $status = 'open';
            $reason = null;

            foreach ($op3Occupied as [$busyStart, $busyEnd]) {
                if (dentrix_schedule_ranges_overlap($startMinutes, $endMinutes, $busyStart, $busyEnd)) {
                    $status = 'occupied';
                    $reason = 'OP-3 has an appointment during this time.';
                    break;
                }
            }
            if ($status === 'open') {
                foreach ($manualBlocks as $block) {
                    if (dentrix_schedule_ranges_overlap($startMinutes, $endMinutes, $block['start'], $block['end'])) {
                        $status = 'blocked';
                        $reason = ucfirst(str_replace('_', ' ', $block['label']));
                        break;
                    }
                }
            }
            if ($status === 'open') {
                foreach ($guardWindows as [$busyStart, $busyEnd]) {
                    if (dentrix_schedule_ranges_overlap($startMinutes, $endMinutes, $busyStart, $busyEnd)) {
                        $status = 'blocked';
                        $reason = 'OP-1/OP-2 has a surgery/sedation-type procedure during this window.';
                        break;
                    }
                }
            }

            $existing = db_one(
                "SELECT id, status FROM dentrix_consult_slots WHERE schedule_date = :d AND start_time = :s AND preferred_operatory = :op",
                ['d' => $scheduleDate, 's' => $startTime, 'op' => DENTRIX_SCHEDULE_PREFERRED_OPERATORY]
            );

            if ($existing && in_array($existing['status'], ['held', 'booked'], true)) {
                // A CRM-driven state; leave it exactly as-is.
                $results[] = ['start_time' => $startTime, 'end_time' => $endTime, 'status' => $existing['status'], 'blocking_reason' => null];
                continue;
            }

            if ($existing) {
                db_query(
                    "UPDATE dentrix_consult_slots SET status = :status, blocking_reason = :reason, generated_from_import_id = :import_id, updated_at = NOW() WHERE id = :id",
                    ['status' => $status, 'reason' => $reason, 'import_id' => $importId, 'id' => $existing['id']]
                );
            } else {
                db_insert(
                    "INSERT INTO dentrix_consult_slots (schedule_date, start_time, end_time, preferred_operatory, status, blocking_reason, generated_from_import_id, created_at, updated_at)
                     VALUES (:d, :s, :e, :op, :status, :reason, :import_id, NOW(), NOW())",
                    [
                        'd' => $scheduleDate, 's' => $startTime, 'e' => $endTime, 'op' => DENTRIX_SCHEDULE_PREFERRED_OPERATORY,
                        'status' => $status, 'reason' => $reason, 'import_id' => $importId,
                    ]
                );
            }

            $results[] = ['start_time' => $startTime, 'end_time' => $endTime, 'status' => $status, 'blocking_reason' => $reason];
        }

        return $results;
    }
}

if (!function_exists('dentrix_schedule_available_consult_slots')) {
    /**
     * Live-read of currently open consult slots for a date, additionally
     * filtering out past times. This is the function both the calendar UI
     * and (in a later pass) the booking flow's pre-check should call —
     * "recheck availability immediately before booking" means calling this
     * again right before writing the appointment, not trusting an earlier read.
     */
    function dentrix_schedule_available_consult_slots(string $scheduleDate, ?DateTimeImmutable $now = null): array
    {
        dentrix_schedule_ensure_schema();
        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'UTC'));
        $today = $now->format('Y-m-d');

        if ($scheduleDate < $today) {
            return [];
        }

        $rows = db_all(
            "SELECT start_time, end_time, preferred_operatory FROM dentrix_consult_slots
             WHERE schedule_date = :d AND status = 'open' ORDER BY start_time ASC",
            ['d' => $scheduleDate]
        );

        $nowMinutes = $scheduleDate === $today ? ((int)$now->format('H') * 60 + (int)$now->format('i')) : -1;

        $available = [];
        foreach ($rows as $row) {
            if ($scheduleDate === $today && dentrix_schedule_time_to_minutes((string)$row['start_time']) <= $nowMinutes) {
                continue;
            }
            $available[] = [
                'schedule_date' => $scheduleDate,
                'start_time' => (string)$row['start_time'],
                'end_time' => (string)$row['end_time'],
                'preferred_operatory' => (string)$row['preferred_operatory'],
            ];
        }
        return $available;
    }
}

if (!function_exists('dentrix_schedule_block_slot')) {
    function dentrix_schedule_block_slot(string $scheduleDate, string $startTime, string $endTime, ?string $operatory, string $blockType, ?string $reason, ?int $userId): array
    {
        dentrix_schedule_ensure_schema();
        if ($startTime >= $endTime) {
            return ['ok' => false, 'message' => 'End time must be after start time.'];
        }
        $blockId = db_insert(
            "INSERT INTO dentrix_calendar_blocks (schedule_date, operatory, start_time, end_time, block_type, reason, created_by_user_id, created_at, updated_at)
             VALUES (:d, :op, :s, :e, :type, :reason, :user, NOW(), NOW())",
            ['d' => $scheduleDate, 'op' => $operatory, 's' => $startTime, 'e' => $endTime, 'type' => $blockType, 'reason' => $reason, 'user' => $userId]
        );
        dentrix_schedule_audit_log(null, 'calendar_block_created', null, json_encode(['block_id' => $blockId, 'schedule_date' => $scheduleDate, 'start' => $startTime, 'end' => $endTime, 'operatory' => $operatory, 'type' => $blockType]), 'user', $userId);
        dentrix_schedule_generate_consult_slots($scheduleDate);
        return ['ok' => true, 'block_id' => $blockId];
    }
}

if (!function_exists('dentrix_schedule_unblock_slot')) {
    function dentrix_schedule_unblock_slot(int $blockId, ?int $userId): array
    {
        dentrix_schedule_ensure_schema();
        $block = db_one("SELECT * FROM dentrix_calendar_blocks WHERE id = :id", ['id' => $blockId]);
        if (!$block) {
            return ['ok' => false, 'message' => 'That block no longer exists.'];
        }
        if ($block['block_type'] === 'lunch') {
            return ['ok' => false, 'message' => 'The lunch block cannot be removed here.'];
        }
        db_query("DELETE FROM dentrix_calendar_blocks WHERE id = :id", ['id' => $blockId]);
        dentrix_schedule_audit_log(null, 'calendar_block_removed', json_encode($block), null, 'user', $userId);
        dentrix_schedule_generate_consult_slots((string)$block['schedule_date']);
        return ['ok' => true];
    }
}
