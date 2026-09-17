<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Dentrix Schedule Bridge — core schema, import, and overwrite logic.
 *
 * This module is intentionally separate from app/dentrix/dentrix_bridge.php,
 * which models a live Dentrix worker webhook integration (dentrix_bridge_jobs,
 * dentrix_occupied_slots). That integration never went live; this module
 * instead ingests manually-printed Dentrix Appointment Book Day View PDFs
 * (uploaded by staff, or synced from a Google Drive folder) and parses them
 * into a CRM-local mirror of the Dentrix schedule. All tables/functions here
 * are namespaced dentrix_schedule_* to avoid any collision with the existing
 * bridge module. See docs/dentrix-bridge-touchpoints.md for the older module.
 *
 * Weekday file convention: 1.pdf=Monday .. 5.pdf=Friday. Filename controls
 * which weekday an import targets; the schedule_date itself is parsed from
 * the PDF content. Re-importing the same weekday file overwrites only the
 * dentrix_import-sourced appointments for the parsed schedule_date — CRM
 * agent/staff-created appointments are never touched. Import history is
 * always kept for audit/debugging even when a sync is skipped or fails.
 */

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/auth.php';

// ---------------------------------------------------------------------
// Role gating — shared by both the staff pages (dentrix-schedule/*) and
// the JSON API endpoints (app/api/dentrix_schedule_*.php), so both enforce
// the exact same access rule from one place.
// ---------------------------------------------------------------------

if (!function_exists('dentrix_schedule_allowed_staff_roles')) {
    function dentrix_schedule_allowed_staff_roles(): array
    {
        return ['admin', 'marketing_manager', 'staff'];
    }
}

if (!function_exists('dentrix_schedule_is_staff_request')) {
    function dentrix_schedule_is_staff_request(): bool
    {
        return auth_check() && auth_has_role(...dentrix_schedule_allowed_staff_roles());
    }
}

if (!function_exists('dentrix_schedule_staff_gate')) {
    function dentrix_schedule_staff_gate(): void
    {
        if (!dentrix_schedule_is_staff_request()) {
            http_response_code(403);
            exit('Forbidden');
        }
    }
}

if (!function_exists('dentrix_schedule_ensure_schema')) {
    function dentrix_schedule_ensure_schema(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        db_query("CREATE TABLE IF NOT EXISTS dentrix_schedule_imports (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            source_type VARCHAR(20) NOT NULL,
            source_folder_id VARCHAR(190) NULL,
            source_file_name VARCHAR(190) NOT NULL,
            source_file_id VARCHAR(190) NULL,
            source_day_index TINYINT UNSIGNED NULL,
            source_week_start DATE NULL,
            schedule_date DATE NULL,
            pdf_hash CHAR(64) NOT NULL,
            imported_at DATETIME NULL,
            parsed_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            error_message TEXT NULL,
            raw_text LONGTEXT NULL,
            parser_version VARCHAR(40) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_dsi_day_index (source_day_index, created_at),
            KEY idx_dsi_schedule_date (schedule_date),
            KEY idx_dsi_status (parsed_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS dentrix_appointments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            import_id BIGINT UNSIGNED NULL,
            schedule_date DATE NOT NULL,
            day_index TINYINT UNSIGNED NOT NULL,
            operatory VARCHAR(40) NULL,
            start_time TIME NULL,
            end_time TIME NULL,
            patient_name VARCHAR(190) NULL,
            appointment_reason VARCHAR(255) NULL,
            phone VARCHAR(40) NULL,
            provider VARCHAR(120) NULL,
            appointment_type VARCHAR(80) NULL,
            staff VARCHAR(120) NULL,
            raw_block_text TEXT NULL,
            source_confidence DECIMAL(4,3) NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'dentrix_import',
            status VARCHAR(40) NOT NULL DEFAULT 'imported',
            dob DATE NULL,
            lead_id BIGINT UNSIGNED NULL,
            dentrix_entry_status VARCHAR(20) NOT NULL DEFAULT 'not_required',
            notified_at DATETIME NULL,
            notified_to VARCHAR(190) NULL,
            is_manual TINYINT(1) NOT NULL DEFAULT 0,
            created_by_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_da_date_day (schedule_date, day_index),
            KEY idx_da_import (import_id),
            KEY idx_da_source (source),
            KEY idx_da_status (status),
            KEY idx_da_lead (lead_id),
            KEY idx_da_entry_status (dentrix_entry_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS dentrix_calendar_blocks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            schedule_date DATE NOT NULL,
            operatory VARCHAR(40) NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            block_type VARCHAR(30) NOT NULL,
            reason VARCHAR(255) NULL,
            created_by_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_dcb_date (schedule_date),
            KEY idx_dcb_operatory (operatory)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS dentrix_consult_slots (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            schedule_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            preferred_operatory VARCHAR(40) NOT NULL DEFAULT 'OP-3',
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            blocking_reason VARCHAR(255) NULL,
            generated_from_import_id BIGINT UNSIGNED NULL,
            appointment_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_dcs_date_start (schedule_date, start_time, preferred_operatory),
            KEY idx_dcs_status (status),
            KEY idx_dcs_appointment (appointment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS dentrix_appointment_audit_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            appointment_id BIGINT UNSIGNED NULL,
            action VARCHAR(60) NOT NULL,
            old_value TEXT NULL,
            new_value TEXT NULL,
            actor_type VARCHAR(20) NOT NULL DEFAULT 'system',
            actor_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            KEY idx_daal_appointment (appointment_id),
            KEY idx_daal_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $ready = true;
    }
}

// ---------------------------------------------------------------------
// Weekday <-> filename mapping
// ---------------------------------------------------------------------

if (!function_exists('dentrix_schedule_weekday_names')) {
    function dentrix_schedule_weekday_names(): array
    {
        return [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
        ];
    }
}

if (!function_exists('dentrix_schedule_day_name')) {
    function dentrix_schedule_day_name(int $dayIndex): string
    {
        return dentrix_schedule_weekday_names()[$dayIndex] ?? 'Unknown';
    }
}

if (!function_exists('dentrix_schedule_day_index_from_filename')) {
    /**
     * File name controls weekday: 1.pdf=Monday .. 5.pdf=Friday.
     * Matches exactly "1.pdf" through "5.pdf" (case-insensitive); anything
     * else (including 1 (1).pdf style browser-download duplicates) returns
     * null so the caller can require an explicit weekday instead of guessing.
     */
    function dentrix_schedule_day_index_from_filename(string $fileName): ?int
    {
        $fileName = trim($fileName);
        if (preg_match('/^([1-5])\.pdf$/i', $fileName, $m) === 1) {
            return (int)$m[1];
        }
        return null;
    }
}

// ---------------------------------------------------------------------
// Hashing
// ---------------------------------------------------------------------

if (!function_exists('dentrix_schedule_hash_file')) {
    function dentrix_schedule_hash_file(string $filePath): string
    {
        $hash = hash_file('sha256', $filePath);
        return $hash !== false ? $hash : '';
    }
}

// ---------------------------------------------------------------------
// Import history helpers
// ---------------------------------------------------------------------

if (!function_exists('dentrix_schedule_latest_parsed_import_for_day')) {
    function dentrix_schedule_latest_parsed_import_for_day(int $dayIndex): ?array
    {
        dentrix_schedule_ensure_schema();
        return db_one(
            "SELECT * FROM dentrix_schedule_imports
             WHERE source_day_index = :day_index AND parsed_status = 'parsed'
             ORDER BY created_at DESC LIMIT 1",
            ['day_index' => $dayIndex]
        );
    }
}

if (!function_exists('dentrix_schedule_record_import')) {
    /**
     * Always inserts an import row (audit/debugging history), regardless of
     * whether the sync goes on to skip, parse, or fail.
     */
    function dentrix_schedule_record_import(array $data): int
    {
        dentrix_schedule_ensure_schema();
        return db_insert(
            "INSERT INTO dentrix_schedule_imports
                (source_type, source_folder_id, source_file_name, source_file_id,
                 source_day_index, source_week_start, schedule_date, pdf_hash,
                 imported_at, parsed_status, error_message, raw_text, parser_version,
                 created_at, updated_at)
             VALUES
                (:source_type, :source_folder_id, :source_file_name, :source_file_id,
                 :source_day_index, :source_week_start, :schedule_date, :pdf_hash,
                 :imported_at, :parsed_status, :error_message, :raw_text, :parser_version,
                 NOW(), NOW())",
            [
                'source_type' => (string)($data['source_type'] ?? 'manual'),
                'source_folder_id' => $data['source_folder_id'] ?? null,
                'source_file_name' => (string)($data['source_file_name'] ?? ''),
                'source_file_id' => $data['source_file_id'] ?? null,
                'source_day_index' => $data['source_day_index'] ?? null,
                'source_week_start' => $data['source_week_start'] ?? null,
                'schedule_date' => $data['schedule_date'] ?? null,
                'pdf_hash' => (string)($data['pdf_hash'] ?? ''),
                'imported_at' => $data['imported_at'] ?? date('Y-m-d H:i:s'),
                'parsed_status' => (string)($data['parsed_status'] ?? 'pending'),
                'error_message' => $data['error_message'] ?? null,
                'raw_text' => $data['raw_text'] ?? null,
                'parser_version' => $data['parser_version'] ?? null,
            ]
        );
    }
}

if (!function_exists('dentrix_schedule_update_import_status')) {
    function dentrix_schedule_update_import_status(int $importId, string $status, ?string $errorMessage = null): void
    {
        db_query(
            "UPDATE dentrix_schedule_imports SET parsed_status = :status, error_message = :error_message, updated_at = NOW() WHERE id = :id",
            ['status' => $status, 'error_message' => $errorMessage, 'id' => $importId]
        );
    }
}

// ---------------------------------------------------------------------
// Overwrite logic
// ---------------------------------------------------------------------

if (!function_exists('dentrix_schedule_overwrite_appointments')) {
    /**
     * Overwrites only dentrix_import-sourced appointments for the given
     * schedule_date. CRM-agent and manual-staff appointments (source !=
     * dentrix_import) are never deleted, so a re-sync cannot clobber a
     * consult a patient already booked through the CRM.
     */
    function dentrix_schedule_overwrite_appointments(int $importId, string $scheduleDate, int $dayIndex, array $parsedAppointments): array
    {
        dentrix_schedule_ensure_schema();

        db_query(
            "DELETE FROM dentrix_appointments WHERE schedule_date = :schedule_date AND source = 'dentrix_import'",
            ['schedule_date' => $scheduleDate]
        );

        $insertedIds = [];
        foreach ($parsedAppointments as $appt) {
            $insertedIds[] = db_insert(
                "INSERT INTO dentrix_appointments
                    (import_id, schedule_date, day_index, operatory, start_time, end_time,
                     patient_name, appointment_reason, phone, provider, appointment_type, staff,
                     raw_block_text, source_confidence, source, status, dentrix_entry_status,
                     is_manual, created_at, updated_at)
                 VALUES
                    (:import_id, :schedule_date, :day_index, :operatory, :start_time, :end_time,
                     :patient_name, :appointment_reason, :phone, :provider, :appointment_type, :staff,
                     :raw_block_text, :source_confidence, 'dentrix_import', 'imported', 'not_required',
                     0, NOW(), NOW())",
                [
                    'import_id' => $importId,
                    'schedule_date' => $scheduleDate,
                    'day_index' => $dayIndex,
                    'operatory' => $appt['operatory'] ?? null,
                    'start_time' => $appt['start_time'] ?? null,
                    'end_time' => $appt['end_time'] ?? null,
                    'patient_name' => $appt['patient_name'] ?? null,
                    'appointment_reason' => $appt['appointment_reason'] ?? null,
                    'phone' => $appt['phone'] ?? null,
                    'provider' => $appt['provider'] ?? null,
                    'appointment_type' => $appt['appointment_type'] ?? null,
                    'staff' => $appt['staff'] ?? null,
                    'raw_block_text' => $appt['raw_block_text'] ?? null,
                    'source_confidence' => $appt['confidence'] ?? null,
                ]
            );
        }

        return $insertedIds;
    }
}

// ---------------------------------------------------------------------
// Orchestration: import a single PDF (manual upload or Drive sync)
// ---------------------------------------------------------------------

if (!function_exists('dentrix_schedule_import_pdf')) {
    /**
     * Orchestrates a single PDF import:
     *  1. Hash the file; skip re-parsing if unchanged from the last parsed
     *     import for this weekday (unless force is set).
     *  2. Extract + parse the PDF via the parser service.
     *  3. Overwrite only that schedule_date's dentrix_import appointments.
     *
     * $options:
     *   - source_type: 'manual' | 'drive' (default 'manual')
     *   - day_index: int 1-5, required unless derivable from source_file_name
     *   - source_folder_id, source_file_id, source_week_start: passthrough metadata
     *   - force: bool, re-parse even if the hash is unchanged
     *
     * Returns a summary array with ok, status (parsed|skipped|failed),
     * import_id, schedule_date, appointment counts, and warnings/errors.
     */
    function dentrix_schedule_import_pdf(string $filePath, string $sourceFileName, array $options = []): array
    {
        dentrix_schedule_ensure_schema();

        $dayIndex = isset($options['day_index'])
            ? (int)$options['day_index']
            : dentrix_schedule_day_index_from_filename($sourceFileName);

        if ($dayIndex === null || $dayIndex < 1 || $dayIndex > 5) {
            return [
                'ok' => false,
                'status' => 'failed',
                'message' => 'Could not determine the weekday for "' . $sourceFileName . '". Expected 1.pdf-5.pdf or an explicit weekday.',
            ];
        }

        if (!is_file($filePath)) {
            return ['ok' => false, 'status' => 'failed', 'message' => 'Uploaded PDF was not found on disk.'];
        }

        $hash = dentrix_schedule_hash_file($filePath);
        $force = !empty($options['force']);

        if (!$force) {
            $previous = dentrix_schedule_latest_parsed_import_for_day($dayIndex);
            if ($previous && hash_equals((string)$previous['pdf_hash'], $hash)) {
                $importId = dentrix_schedule_record_import([
                    'source_type' => $options['source_type'] ?? 'manual',
                    'source_folder_id' => $options['source_folder_id'] ?? null,
                    'source_file_name' => $sourceFileName,
                    'source_file_id' => $options['source_file_id'] ?? null,
                    'source_day_index' => $dayIndex,
                    'source_week_start' => $options['source_week_start'] ?? null,
                    'schedule_date' => $previous['schedule_date'] ?? null,
                    'pdf_hash' => $hash,
                    'parsed_status' => 'skipped',
                    'error_message' => 'Unchanged from the previous import for this weekday.',
                ]);
                return [
                    'ok' => true,
                    'status' => 'skipped',
                    'import_id' => $importId,
                    'schedule_date' => $previous['schedule_date'] ?? null,
                    'day_index' => $dayIndex,
                    'message' => 'PDF hash unchanged; skipped re-parsing.',
                ];
            }
        }

        $parsed = dentrix_schedule_parser_parse_pdf($filePath, $sourceFileName, $dayIndex);

        if (empty($parsed['ok'])) {
            $importId = dentrix_schedule_record_import([
                'source_type' => $options['source_type'] ?? 'manual',
                'source_folder_id' => $options['source_folder_id'] ?? null,
                'source_file_name' => $sourceFileName,
                'source_file_id' => $options['source_file_id'] ?? null,
                'source_day_index' => $dayIndex,
                'source_week_start' => $options['source_week_start'] ?? null,
                'pdf_hash' => $hash,
                'parsed_status' => 'failed',
                'error_message' => (string)($parsed['message'] ?? 'PDF parsing failed.'),
                'raw_text' => $parsed['raw_text'] ?? null,
                'parser_version' => $parsed['parser_version'] ?? null,
            ]);
            return [
                'ok' => false,
                'status' => 'failed',
                'import_id' => $importId,
                'day_index' => $dayIndex,
                'message' => (string)($parsed['message'] ?? 'PDF parsing failed.'),
            ];
        }

        $scheduleDate = $parsed['schedule_date'] ?? null;
        if (!$scheduleDate) {
            // No confident date found in the PDF text. Preserve raw text for
            // debugging and fail loudly rather than guessing a date and
            // silently overwriting the wrong day's schedule.
            $importId = dentrix_schedule_record_import([
                'source_type' => $options['source_type'] ?? 'manual',
                'source_folder_id' => $options['source_folder_id'] ?? null,
                'source_file_name' => $sourceFileName,
                'source_file_id' => $options['source_file_id'] ?? null,
                'source_day_index' => $dayIndex,
                'source_week_start' => $options['source_week_start'] ?? null,
                'pdf_hash' => $hash,
                'parsed_status' => 'failed',
                'error_message' => 'Could not confidently determine the schedule date from the PDF.',
                'raw_text' => $parsed['raw_text'] ?? null,
                'parser_version' => $parsed['parser_version'] ?? null,
            ]);
            return [
                'ok' => false,
                'status' => 'failed',
                'import_id' => $importId,
                'day_index' => $dayIndex,
                'message' => 'Could not confidently determine the schedule date from the PDF.',
                'warnings' => $parsed['warnings'] ?? [],
            ];
        }

        $importId = dentrix_schedule_record_import([
            'source_type' => $options['source_type'] ?? 'manual',
            'source_folder_id' => $options['source_folder_id'] ?? null,
            'source_file_name' => $sourceFileName,
            'source_file_id' => $options['source_file_id'] ?? null,
            'source_day_index' => $dayIndex,
            'source_week_start' => $options['source_week_start'] ?? null,
            'schedule_date' => $scheduleDate,
            'pdf_hash' => $hash,
            'parsed_status' => 'parsed',
            'raw_text' => $parsed['raw_text'] ?? null,
            'parser_version' => $parsed['parser_version'] ?? null,
        ]);

        $insertedIds = dentrix_schedule_overwrite_appointments($importId, $scheduleDate, $dayIndex, $parsed['appointments'] ?? []);

        // Regenerate OP-3 consult availability for this date now that the
        // schedule changed. Optional dependency: the consult engine file
        // isn't required here to avoid a circular require, so this no-ops
        // (rather than fatal-erroring) if it hasn't been loaded by the caller.
        if (function_exists('dentrix_schedule_generate_consult_slots')) {
            dentrix_schedule_generate_consult_slots($scheduleDate, $importId);
        }

        return [
            'ok' => true,
            'status' => 'parsed',
            'import_id' => $importId,
            'schedule_date' => $scheduleDate,
            'day_index' => $dayIndex,
            'appointment_count' => count($insertedIds),
            'warnings' => $parsed['warnings'] ?? [],
        ];
    }
}

// ---------------------------------------------------------------------
// Read helpers for the staff upload/history page
// ---------------------------------------------------------------------

if (!function_exists('dentrix_schedule_recent_imports')) {
    function dentrix_schedule_recent_imports(int $limit = 50): array
    {
        dentrix_schedule_ensure_schema();
        $limit = max(1, min($limit, 200));
        return db_all("SELECT * FROM dentrix_schedule_imports ORDER BY created_at DESC LIMIT {$limit}");
    }
}

if (!function_exists('dentrix_schedule_weekday_status_snapshot')) {
    /**
     * One row per weekday (1-5) with its most recent import (of any status)
     * and the current dentrix_import appointment count for that import's
     * schedule_date, for a quick "is this weekday's schedule current" view.
     */
    function dentrix_schedule_weekday_status_snapshot(): array
    {
        dentrix_schedule_ensure_schema();
        $rows = [];
        foreach (dentrix_schedule_weekday_names() as $dayIndex => $label) {
            $latest = db_one(
                "SELECT * FROM dentrix_schedule_imports WHERE source_day_index = :day_index ORDER BY created_at DESC LIMIT 1",
                ['day_index' => $dayIndex]
            );
            $appointmentCount = 0;
            if ($latest && !empty($latest['schedule_date'])) {
                $appointmentCount = (int) db_value(
                    "SELECT COUNT(*) FROM dentrix_appointments WHERE schedule_date = :d AND source = 'dentrix_import'",
                    ['d' => $latest['schedule_date']]
                );
            }
            $rows[] = [
                'day_index' => $dayIndex,
                'label' => $label,
                'latest_import' => $latest,
                'appointment_count' => $appointmentCount,
            ];
        }
        return $rows;
    }
}

// ---------------------------------------------------------------------
// Audit log
// ---------------------------------------------------------------------

if (!function_exists('dentrix_schedule_audit_log')) {
    function dentrix_schedule_audit_log(?int $appointmentId, string $action, $oldValue, $newValue, string $actorType = 'system', ?int $actorId = null): void
    {
        dentrix_schedule_ensure_schema();
        db_query(
            "INSERT INTO dentrix_appointment_audit_log (appointment_id, action, old_value, new_value, actor_type, actor_id, created_at)
             VALUES (:appointment_id, :action, :old_value, :new_value, :actor_type, :actor_id, NOW())",
            [
                'appointment_id' => $appointmentId,
                'action' => $action,
                'old_value' => is_scalar($oldValue) || $oldValue === null ? $oldValue : json_encode($oldValue),
                'new_value' => is_scalar($newValue) || $newValue === null ? $newValue : json_encode($newValue),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]
        );
    }
}
