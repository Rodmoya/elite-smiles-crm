<?php
declare(strict_types=1);

/**
 * DB-level tests for the Dentrix Schedule Bridge import/overwrite logic.
 * Exercises dentrix_schedule_record_import()/dentrix_schedule_overwrite_appointments()
 * directly (bypassing the pdftotext-dependent orchestrator) so this test has
 * no dependency on the pdftotext binary being installed.
 *
 * Runs inside a rolled-back transaction, matching this repo's DB test convention.
 */

require_once dirname(__DIR__) . '/app/dentrix_schedule/dentrix_schedule_service.php';

function dsi_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

dentrix_schedule_ensure_schema();
db_begin();

try {
    $today = date('Y-m-d');
    $otherDate = date('Y-m-d', strtotime('+7 days'));

    // --- Duplicate hash skip -------------------------------------------------
    $hashA = hash('sha256', 'fixture-pdf-contents-a');

    $import1 = dentrix_schedule_record_import([
        'source_type' => 'manual',
        'source_file_name' => '1.pdf',
        'source_day_index' => 1,
        'schedule_date' => $today,
        'pdf_hash' => $hashA,
        'parsed_status' => 'parsed',
    ]);
    dsi_expect($import1 > 0, 'First import row must be created.');

    $latest = dentrix_schedule_latest_parsed_import_for_day(1);
    dsi_expect($latest !== null && $latest['pdf_hash'] === $hashA, 'Latest parsed import lookup must return the row just inserted.');
    dsi_expect((string)$latest['pdf_hash'] === $hashA, 'Unchanged hash must be detected by comparing against the latest parsed import.');

    // A second "sync" with the identical hash should record skip history rather than re-parsing.
    $import2 = dentrix_schedule_record_import([
        'source_type' => 'manual',
        'source_file_name' => '1.pdf',
        'source_day_index' => 1,
        'schedule_date' => $today,
        'pdf_hash' => $hashA,
        'parsed_status' => 'skipped',
        'error_message' => 'Unchanged from the previous import for this weekday.',
    ]);
    dsi_expect($import2 > 0 && $import2 !== $import1, 'A skipped re-sync must still be recorded as its own history row (audit/debugging).');
    $skippedRow = db_one('SELECT parsed_status FROM dentrix_schedule_imports WHERE id = :id', ['id' => $import2]);
    dsi_expect(($skippedRow['parsed_status'] ?? null) === 'skipped', 'The duplicate-hash import must be marked skipped, not parsed.');

    // --- Overwrite scoped to one weekday/date only ---------------------------
    dentrix_schedule_overwrite_appointments($import1, $today, 1, [
        [
            'operatory' => 'OP-1',
            'start_time' => '09:00:00',
            'patient_name' => 'Original, Patient',
            'appointment_reason' => 'Cleaning',
            'raw_block_text' => 'fixture block',
            'confidence' => 0.9,
        ],
    ]);

    // Seed an appointment on a different date that must never be touched.
    $otherImport = dentrix_schedule_record_import([
        'source_type' => 'manual',
        'source_file_name' => '2.pdf',
        'source_day_index' => 2,
        'schedule_date' => $otherDate,
        'pdf_hash' => hash('sha256', 'fixture-pdf-contents-b'),
        'parsed_status' => 'parsed',
    ]);
    dentrix_schedule_overwrite_appointments($otherImport, $otherDate, 2, [
        [
            'operatory' => 'OP-2',
            'start_time' => '10:00:00',
            'patient_name' => 'Untouched, Patient',
            'raw_block_text' => 'other day fixture block',
            'confidence' => 0.9,
        ],
    ]);

    $todayCountBefore = (int) db_value('SELECT COUNT(*) FROM dentrix_appointments WHERE schedule_date = :d', ['d' => $today]);
    dsi_expect($todayCountBefore === 1, 'Today must have exactly one imported appointment before re-sync. Got: ' . $todayCountBefore);

    // --- CRM-agent and manual-staff appointments must never be deleted -------
    db_insert(
        "INSERT INTO dentrix_appointments
            (schedule_date, day_index, operatory, start_time, patient_name, source, status, dentrix_entry_status, is_manual, created_at, updated_at)
         VALUES (:d, 1, 'OP-3', '10:30:00', 'CRM Booked, Patient', 'crm_agent', 'scheduled_pending_dentrix_entry', 'needs_entry', 0, NOW(), NOW())",
        ['d' => $today]
    );
    db_insert(
        "INSERT INTO dentrix_appointments
            (schedule_date, day_index, operatory, start_time, patient_name, source, status, dentrix_entry_status, is_manual, created_at, updated_at)
         VALUES (:d, 1, 'OP-4', '11:00:00', 'Staff Booked, Patient', 'manual_staff', 'confirmed_in_dentrix', 'entered', 1, NOW(), NOW())",
        ['d' => $today]
    );

    // Re-sync the same weekday with a changed hash and a different imported appointment.
    $import3 = dentrix_schedule_record_import([
        'source_type' => 'manual',
        'source_file_name' => '1.pdf',
        'source_day_index' => 1,
        'schedule_date' => $today,
        'pdf_hash' => hash('sha256', 'fixture-pdf-contents-changed'),
        'parsed_status' => 'parsed',
    ]);
    dentrix_schedule_overwrite_appointments($import3, $today, 1, [
        [
            'operatory' => 'OP-1',
            'start_time' => '09:30:00',
            'patient_name' => 'Updated, Patient',
            'appointment_reason' => 'Filling',
            'raw_block_text' => 'fixture block v2',
            'confidence' => 0.9,
        ],
    ]);

    $importedRows = db_all("SELECT * FROM dentrix_appointments WHERE schedule_date = :d AND source = 'dentrix_import'", ['d' => $today]);
    dsi_expect(count($importedRows) === 1, 'Re-sync must overwrite (not append) the dentrix_import rows for that date. Got: ' . count($importedRows));
    dsi_expect(($importedRows[0]['patient_name'] ?? null) === 'Updated, Patient', 'Only the new import\'s appointment must remain after overwrite.');
    dsi_expect(($importedRows[0]['import_id'] ?? null) == $import3, 'The surviving appointment must be linked to the newest import.');

    $crmAgentRow = db_one("SELECT * FROM dentrix_appointments WHERE schedule_date = :d AND source = 'crm_agent'", ['d' => $today]);
    dsi_expect($crmAgentRow !== null, 'A re-sync of the dentrix_import rows must never delete a CRM-agent-created appointment.');
    dsi_expect(($crmAgentRow['patient_name'] ?? null) === 'CRM Booked, Patient', 'The CRM-agent appointment must survive untouched.');

    $manualStaffRow = db_one("SELECT * FROM dentrix_appointments WHERE schedule_date = :d AND source = 'manual_staff'", ['d' => $today]);
    dsi_expect($manualStaffRow !== null, 'A re-sync of the dentrix_import rows must never delete a manual-staff-created appointment.');

    // The other date's imported appointment must be completely unaffected by re-syncing "today".
    $otherRow = db_one("SELECT * FROM dentrix_appointments WHERE schedule_date = :d", ['d' => $otherDate]);
    dsi_expect($otherRow !== null && ($otherRow['patient_name'] ?? null) === 'Untouched, Patient', 'Overwrite must be scoped to the synced date only; other dates must be untouched.');

    // --- Audit log ------------------------------------------------------------
    dentrix_schedule_audit_log((int)$importedRows[0]['id'], 'test_action', 'old', 'new', 'system');
    $auditRow = db_one('SELECT * FROM dentrix_appointment_audit_log WHERE appointment_id = :id ORDER BY id DESC LIMIT 1', ['id' => $importedRows[0]['id']]);
    dsi_expect($auditRow !== null && $auditRow['action'] === 'test_action', 'Audit log entries must be recorded against the appointment id.');

    echo "Dentrix schedule import/overwrite tests passed.\n";
} finally {
    db_rollBack();
}
