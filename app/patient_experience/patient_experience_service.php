<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Patient Experience foundation service.
 *
 * Phase 1 only: schema, kiosk/check-in session placeholders, and audit helpers.
 */

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/patient_experience_forms.php';

if (!function_exists('patient_experience_ensure_schema')) {
    function patient_experience_ensure_schema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_kiosk_devices (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            device_label VARCHAR(190) NOT NULL DEFAULT 'Waiting Room iPad',
            device_token_hash CHAR(64) NULL,
            location_label VARCHAR(190) NOT NULL DEFAULT 'Front Desk',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            registered_at DATETIME NULL,
            last_seen_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_patient_exp_device_token (device_token_hash),
            KEY idx_patient_exp_device_active (is_active)
        ) {$charset}");

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_kiosk_setup_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kiosk_device_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            revoked_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_patient_exp_setup_token_hash (token_hash),
            KEY idx_patient_exp_setup_device (kiosk_device_id),
            KEY idx_patient_exp_setup_expires (expires_at)
        ) {$charset}");

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_checkin_sessions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kiosk_device_id INT UNSIGNED NULL,
            lead_id INT UNSIGNED NULL,
            patient_name VARCHAR(190) NOT NULL DEFAULT '',
            session_token_hash CHAR(64) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'waiting',
            started_by_user_id INT UNSIGNED NULL,
            expires_at DATETIME NOT NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            cancelled_at DATETIME NULL,
            expired_at DATETIME NULL,
            device_user_agent VARCHAR(255) NOT NULL DEFAULT '',
            device_ip VARCHAR(80) NOT NULL DEFAULT '',
            staff_notes TEXT NULL,
            current_step_key VARCHAR(120) NOT NULL DEFAULT 'welcome',
            progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_patient_exp_session_token (session_token_hash),
            KEY idx_patient_exp_session_status (status),
            KEY idx_patient_exp_session_lead (lead_id),
            KEY idx_patient_exp_session_device (kiosk_device_id),
            KEY idx_patient_exp_session_created (created_at)
        ) {$charset}");

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_form_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            template_key VARCHAR(120) NOT NULL,
            title VARCHAR(190) NOT NULL,
            description TEXT NULL,
            category VARCHAR(80) NOT NULL DEFAULT 'intake',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_patient_exp_template_key (template_key),
            KEY idx_patient_exp_template_active (is_active)
        ) {$charset}");

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_form_template_versions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            template_id INT UNSIGNED NOT NULL,
            version_number INT UNSIGNED NOT NULL DEFAULT 1,
            schema_json LONGTEXT NULL,
            consent_text LONGTEXT NULL,
            effective_at DATETIME NULL,
            retired_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_patient_exp_template_version (template_id, version_number),
            KEY idx_patient_exp_template_versions_template (template_id)
        ) {$charset}");

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_packets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            packet_key VARCHAR(120) NOT NULL,
            title VARCHAR(190) NOT NULL,
            description TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_patient_exp_packet_key (packet_key),
            KEY idx_patient_exp_packet_active (is_active)
        ) {$charset}");

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_packet_sections (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            packet_id INT UNSIGNED NOT NULL,
            template_version_id INT UNSIGNED NOT NULL,
            section_key VARCHAR(120) NOT NULL,
            title VARCHAR(190) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_required TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_patient_exp_packet_sections_packet (packet_id),
            KEY idx_patient_exp_packet_sections_version (template_version_id)
        ) {$charset}");

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_packet_answers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            checkin_session_id INT UNSIGNED NOT NULL,
            packet_section_id INT UNSIGNED NOT NULL,
            template_version_id INT UNSIGNED NOT NULL,
            field_key VARCHAR(190) NOT NULL,
            answer_json LONGTEXT NULL,
            answer_label TEXT NULL,
            is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
            answered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_patient_exp_answers_session (checkin_session_id),
            KEY idx_patient_exp_answers_section (packet_section_id),
            KEY idx_patient_exp_answers_field (field_key)
        ) {$charset}");

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_signatures (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            checkin_session_id INT UNSIGNED NOT NULL,
            packet_section_id INT UNSIGNED NULL,
            template_version_id INT UNSIGNED NULL,
            signer_name VARCHAR(190) NOT NULL,
            signer_relationship VARCHAR(120) NOT NULL DEFAULT 'self',
            signature_storage_key VARCHAR(255) NULL,
            signature_hash CHAR(64) NULL,
            signed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(80) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            device_label VARCHAR(190) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_patient_exp_signatures_session (checkin_session_id),
            KEY idx_patient_exp_signatures_section (packet_section_id),
            KEY idx_patient_exp_signatures_signed (signed_at)
        ) {$charset}");

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_signed_packets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            checkin_session_id INT UNSIGNED NOT NULL,
            packet_key VARCHAR(120) NOT NULL,
            packet_version INT UNSIGNED NOT NULL DEFAULT 1,
            packet_title VARCHAR(190) NOT NULL,
            patient_name VARCHAR(190) NOT NULL DEFAULT '',
            snapshot_hash CHAR(64) NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            signature_count INT UNSIGNED NOT NULL DEFAULT 0,
            signed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_patient_exp_signed_packet_session (checkin_session_id),
            KEY idx_patient_exp_signed_packet_signed (signed_at),
            KEY idx_patient_exp_signed_packet_key (packet_key)
        ) {$charset}");

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_generated_files (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            checkin_session_id INT UNSIGNED NOT NULL,
            file_type VARCHAR(80) NOT NULL DEFAULT 'signed_packet_pdf',
            storage_key VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NULL,
            mime_type VARCHAR(120) NOT NULL DEFAULT 'application/pdf',
            file_size INT UNSIGNED NOT NULL DEFAULT 0,
            sha256_hash CHAR(64) NULL,
            protected_path VARCHAR(500) NOT NULL DEFAULT '',
            generated_by_user_id INT UNSIGNED NULL,
            generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_patient_exp_files_session (checkin_session_id),
            KEY idx_patient_exp_files_type (file_type)
        ) {$charset}");

        db_query("CREATE TABLE IF NOT EXISTS patient_experience_audit_events (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            checkin_session_id INT UNSIGNED NULL,
            kiosk_device_id INT UNSIGNED NULL,
            lead_id INT UNSIGNED NULL,
            user_id INT UNSIGNED NULL,
            event_key VARCHAR(120) NOT NULL,
            event_label VARCHAR(190) NOT NULL DEFAULT '',
            payload_json LONGTEXT NULL,
            ip_address VARCHAR(80) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_patient_exp_audit_session (checkin_session_id),
            KEY idx_patient_exp_audit_event (event_key),
            KEY idx_patient_exp_audit_created (created_at),
            KEY idx_patient_exp_audit_lead (lead_id)
        ) {$charset}");

        patient_experience_upgrade_schema();
        patient_experience_seed_templates();
        patient_experience_seed_packet_sections();
        $done = true;
    }
}

if (!function_exists('patient_experience_column_exists')) {
    function patient_experience_column_exists(string $table, string $column): bool
    {
        return (bool)db_value(
            "SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :schema
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name",
            ['schema' => DB_NAME, 'table_name' => $table, 'column_name' => $column]
        );
    }
}

if (!function_exists('patient_experience_upgrade_schema')) {
    function patient_experience_upgrade_schema(): void
    {
        foreach ([
            'current_step_key' => "ALTER TABLE patient_experience_checkin_sessions ADD COLUMN current_step_key VARCHAR(120) NOT NULL DEFAULT 'welcome' AFTER staff_notes",
            'progress_percent' => "ALTER TABLE patient_experience_checkin_sessions ADD COLUMN progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER current_step_key",
            'review_status' => "ALTER TABLE patient_experience_checkin_sessions ADD COLUMN review_status VARCHAR(40) NOT NULL DEFAULT 'pending' AFTER progress_percent",
            'reviewed_at' => "ALTER TABLE patient_experience_checkin_sessions ADD COLUMN reviewed_at DATETIME NULL AFTER review_status",
            'reviewed_by_user_id' => "ALTER TABLE patient_experience_checkin_sessions ADD COLUMN reviewed_by_user_id INT UNSIGNED NULL AFTER reviewed_at",
        ] as $column => $sql) {
            if (!patient_experience_column_exists('patient_experience_checkin_sessions', $column)) {
                try {
                    db_query($sql);
                } catch (Throwable $e) {
                    esm_log('patient_experience', 'Could not add check-in session column.', ['column' => $column, 'error' => $e->getMessage()]);
                }
            }
        }

        if (!patient_experience_column_exists('patient_experience_kiosk_devices', 'registered_at')) {
            try {
                db_query("ALTER TABLE patient_experience_kiosk_devices ADD COLUMN registered_at DATETIME NULL AFTER is_active");
            } catch (Throwable $e) {
                esm_log('patient_experience', 'Could not add kiosk device registered_at column.', ['error' => $e->getMessage()]);
            }
        }

        try {
            db_query("CREATE TABLE IF NOT EXISTS patient_experience_kiosk_setup_tokens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                kiosk_device_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                revoked_at DATETIME NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_patient_exp_setup_token_hash (token_hash),
                KEY idx_patient_exp_setup_device (kiosk_device_id),
                KEY idx_patient_exp_setup_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            esm_log('patient_experience', 'Could not ensure kiosk setup token table.', ['error' => $e->getMessage()]);
        }
    }
}

if (!function_exists('patient_experience_token')) {
    function patient_experience_token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes(max(16, $bytes))), '+/', '-_'), '=');
    }
}

if (!function_exists('patient_experience_token_hash')) {
    function patient_experience_token_hash(string $token): string
    {
        return hash('sha256', trim($token));
    }
}

if (!function_exists('patient_experience_kiosk_setup_ttl_seconds')) {
    function patient_experience_kiosk_setup_ttl_seconds(): int
    {
        return 86400;
    }
}

if (!function_exists('patient_experience_kiosk_setup_url')) {
    function patient_experience_kiosk_setup_url(string $token): string
    {
        return base_url('patient-experience/setup/' . rawurlencode($token));
    }
}

if (!function_exists('patient_experience_kiosk_setup_qr_url')) {
    function patient_experience_kiosk_setup_qr_url(string $token): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=10&data=' . rawurlencode(patient_experience_kiosk_setup_url($token));
    }
}

if (!function_exists('patient_experience_audit')) {
    function patient_experience_audit(string $eventKey, array $payload = [], ?int $sessionId = null, ?int $leadId = null, ?int $userId = null, ?int $deviceId = null): void
    {
        patient_experience_ensure_schema();
        db_insert(
            'INSERT INTO patient_experience_audit_events (checkin_session_id, kiosk_device_id, lead_id, user_id, event_key, event_label, payload_json, ip_address, user_agent, created_at)
             VALUES (:session_id, :device_id, :lead_id, :user_id, :event_key, :event_label, :payload_json, :ip, :ua, NOW())',
            [
                'session_id' => $sessionId,
                'device_id' => $deviceId,
                'lead_id' => $leadId,
                'user_id' => $userId,
                'event_key' => $eventKey,
                'event_label' => ucwords(str_replace('_', ' ', $eventKey)),
                'payload_json' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'ip' => function_exists('client_ip') ? client_ip() : ($_SERVER['REMOTE_ADDR'] ?? ''),
                'ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]
        );
    }
}

if (!function_exists('patient_experience_seed_templates')) {
    function patient_experience_seed_templates(): void
    {
        $packet = patient_experience_packet_definition();
        $templates = [];
        foreach ((array)($packet['sections'] ?? []) as $section) {
            $templateKey = trim((string)($section['template_key'] ?? $section['section_key'] ?? ''));
            if ($templateKey === '') {
                continue;
            }
            $templates[$templateKey] = [
                'template_key' => $templateKey,
                'title' => (string)($section['title'] ?? ucwords(str_replace('_', ' ', $templateKey))),
                'category' => (string)($section['category'] ?? 'intake'),
                'schema_json' => json_encode($section, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }

        foreach ($templates as $template) {
            $existingId = db_value('SELECT id FROM patient_experience_form_templates WHERE template_key = :template_key LIMIT 1', ['template_key' => $template['template_key']]);
            if (!$existingId) {
                $existingId = db_insert(
                    'INSERT INTO patient_experience_form_templates (template_key, title, category, is_active, created_at)
                     VALUES (:template_key, :title, :category, 1, NOW())',
                    ['template_key' => $template['template_key'], 'title' => $template['title'], 'category' => $template['category']]
                );
            } else {
                db_execute(
                    'UPDATE patient_experience_form_templates
                     SET title = :title,
                         category = :category,
                         updated_at = NOW()
                     WHERE id = :id',
                    [
                        'id' => (int)$existingId,
                        'title' => $template['title'],
                        'category' => $template['category'],
                    ]
                );
            }

            $hasVersion = db_value(
                'SELECT id FROM patient_experience_form_template_versions WHERE template_id = :template_id AND version_number = 1 LIMIT 1',
                ['template_id' => (int)$existingId]
            );
            if (!$hasVersion) {
                db_insert(
                    'INSERT INTO patient_experience_form_template_versions (template_id, version_number, schema_json, consent_text, effective_at, created_at)
                     VALUES (:template_id, 1, :schema_json, :consent_text, NOW(), NOW())',
                    [
                        'template_id' => (int)$existingId,
                        'schema_json' => $template['schema_json'],
                        'consent_text' => 'Digital forms engine template version 1.',
                    ]
                );
            } else {
                db_execute(
                    'UPDATE patient_experience_form_template_versions
                     SET schema_json = :schema_json,
                         consent_text = :consent_text
                     WHERE id = :id',
                    [
                        'id' => (int)$hasVersion,
                        'schema_json' => $template['schema_json'],
                        'consent_text' => 'Digital forms engine template version 1.',
                    ]
                );
            }
        }

        $packetId = db_value('SELECT id FROM patient_experience_packets WHERE packet_key = :packet_key LIMIT 1', ['packet_key' => (string)$packet['packet_key']]);
        if (!$packetId) {
            db_insert(
                'INSERT INTO patient_experience_packets (packet_key, title, description, is_active, created_at)
                 VALUES (:packet_key, :title, :description, 1, NOW())',
                [
                    'packet_key' => (string)$packet['packet_key'],
                    'title' => (string)$packet['title'],
                    'description' => (string)$packet['description'],
                ]
            );
        } else {
            db_execute(
                'UPDATE patient_experience_packets
                 SET title = :title,
                     description = :description,
                     updated_at = NOW()
                 WHERE id = :id',
                [
                    'id' => (int)$packetId,
                    'title' => (string)$packet['title'],
                    'description' => (string)$packet['description'],
                ]
            );
        }
    }
}

if (!function_exists('patient_experience_form_steps')) {
    function patient_experience_form_steps(): array
    {
        $steps = [];
        foreach ((array)(patient_experience_packet_definition()['sections'] ?? []) as $section) {
            $steps[(string)$section['section_key']] = $section;
        }
        return $steps;
    }
}

if (!function_exists('patient_experience_step_keys')) {
    function patient_experience_step_keys(array $answers = []): array
    {
        $keys = [];
        foreach (patient_experience_form_steps() as $stepKey => $step) {
            if (patient_experience_section_is_visible($step, $answers)) {
                $keys[] = $stepKey;
            }
        }
        return $keys;
    }
}

if (!function_exists('patient_experience_condition_matches')) {
    function patient_experience_condition_matches(array $condition, array $answers): bool
    {
        if (isset($condition['all']) && is_array($condition['all'])) {
            foreach ($condition['all'] as $child) {
                if (!is_array($child) || !patient_experience_condition_matches($child, $answers)) {
                    return false;
                }
            }
            return true;
        }
        if (isset($condition['any']) && is_array($condition['any'])) {
            foreach ($condition['any'] as $child) {
                if (is_array($child) && patient_experience_condition_matches($child, $answers)) {
                    return true;
                }
            }
            return false;
        }

        $fieldKey = trim((string)($condition['field'] ?? ''));
        if ($fieldKey === '') {
            return true;
        }
        $operator = trim((string)($condition['operator'] ?? 'equals'));
        $expected = $condition['value'] ?? null;
        $actual = patient_experience_answer_value($answers, $fieldKey);

        if ($operator === 'not_equals') {
            return $actual !== $expected;
        }
        if ($operator === 'in') {
            return is_array($expected) && in_array($actual, $expected, true);
        }
        if ($operator === 'contains') {
            return is_array($actual) ? in_array($expected, $actual, true) : str_contains(strtolower((string)$actual), strtolower((string)$expected));
        }
        if ($operator === 'not_empty') {
            return !patient_experience_empty_answer($actual);
        }
        if ($operator === 'empty') {
            return patient_experience_empty_answer($actual);
        }
        return $actual === $expected;
    }
}

if (!function_exists('patient_experience_answer_value')) {
    function patient_experience_answer_value(array $answers, string $fieldKey): mixed
    {
        if (!array_key_exists($fieldKey, $answers)) {
            return null;
        }
        $value = $answers[$fieldKey];
        if (is_array($value) && array_key_exists('value', $value)) {
            return $value['value'];
        }
        return $value;
    }
}

if (!function_exists('patient_experience_field_is_visible')) {
    function patient_experience_field_is_visible(array $field, array $answers): bool
    {
        $visibleIf = $field['visible_if'] ?? null;
        if (!is_array($visibleIf) || $visibleIf === []) {
            return true;
        }
        return patient_experience_condition_matches($visibleIf, $answers);
    }
}

if (!function_exists('patient_experience_section_is_visible')) {
    function patient_experience_section_is_visible(array $section, array $answers): bool
    {
        $visibleIf = $section['visible_if'] ?? null;
        if (!is_array($visibleIf) || $visibleIf === []) {
            return true;
        }
        return patient_experience_condition_matches($visibleIf, $answers);
    }
}

if (!function_exists('patient_experience_field_children')) {
    function patient_experience_field_children(array $field): array
    {
        $type = (string)($field['type'] ?? 'text');
        $key = (string)($field['key'] ?? '');
        $label = (string)($field['label'] ?? $key);
        if ($type === 'emergency_contact') {
            return [
                ['key' => $key . '_name', 'type' => 'text', 'label' => $label . ' name', 'required' => !empty($field['required'])],
                ['key' => $key . '_relationship', 'type' => 'text', 'label' => $label . ' relationship', 'required' => !empty($field['required'])],
                ['key' => $key . '_phone', 'type' => 'phone', 'label' => $label . ' phone', 'required' => !empty($field['required'])],
            ];
        }
        if ($type === 'insurance') {
            return [
                ['key' => $key . '_provider', 'type' => 'text', 'label' => 'Insurance provider', 'required' => !empty($field['required'])],
                ['key' => $key . '_subscriber_name', 'type' => 'text', 'label' => 'Subscriber name', 'required' => !empty($field['required'])],
                ['key' => $key . '_member_id', 'type' => 'text', 'label' => 'Member ID', 'required' => !empty($field['required'])],
                ['key' => $key . '_group_number', 'type' => 'text', 'label' => 'Group number'],
                ['key' => $key . '_subscriber_dob', 'type' => 'dob', 'label' => 'Subscriber date of birth'],
            ];
        }
        return [];
    }
}

if (!function_exists('patient_experience_field_signature_required')) {
    function patient_experience_field_signature_required(array $field): bool
    {
        return in_array((string)($field['type'] ?? ''), ['signature_capture', 'digital_signature'], true);
    }
}

if (!function_exists('patient_experience_field_is_static')) {
    function patient_experience_field_is_static(array $field): bool
    {
        return in_array((string)($field['type'] ?? ''), ['heading', 'paragraph', 'divider', 'text_block'], true);
    }
}

if (!function_exists('patient_experience_seed_packet_sections')) {
    function patient_experience_seed_packet_sections(): void
    {
        $packet = patient_experience_packet_definition();
        $packetId = (int)db_value('SELECT id FROM patient_experience_packets WHERE packet_key = :packet_key LIMIT 1', ['packet_key' => (string)$packet['packet_key']]);
        if ($packetId <= 0) {
            return;
        }

        foreach (patient_experience_form_steps() as $stepKey => $step) {
            $version = db_one(
                "SELECT v.id
                 FROM patient_experience_form_template_versions v
                 INNER JOIN patient_experience_form_templates t ON t.id = v.template_id
                 WHERE t.template_key = :template_key
                 ORDER BY v.version_number DESC
                 LIMIT 1",
                ['template_key' => (string)$step['template_key']]
            );
            $versionId = (int)($version['id'] ?? 0);
            if ($versionId <= 0) {
                continue;
            }
            $exists = db_value(
                'SELECT id FROM patient_experience_packet_sections WHERE packet_id = :packet_id AND section_key = :section_key LIMIT 1',
                ['packet_id' => $packetId, 'section_key' => $stepKey]
            );
            if ($exists) {
                db_execute(
                    'UPDATE patient_experience_packet_sections
                     SET template_version_id = :template_version_id,
                         title = :title,
                         sort_order = :sort_order,
                         is_required = :is_required
                     WHERE id = :id',
                    [
                        'id' => (int)$exists,
                        'template_version_id' => $versionId,
                        'title' => (string)$step['title'],
                        'sort_order' => (int)$step['sort_order'],
                        'is_required' => !empty($step['is_required']) ? 1 : 0,
                    ]
                );
                continue;
            }
            db_insert(
                'INSERT INTO patient_experience_packet_sections (packet_id, template_version_id, section_key, title, sort_order, is_required, created_at)
                 VALUES (:packet_id, :template_version_id, :section_key, :title, :sort_order, :is_required, NOW())',
                [
                    'packet_id' => $packetId,
                    'template_version_id' => $versionId,
                    'section_key' => $stepKey,
                    'title' => (string)$step['title'],
                    'sort_order' => (int)$step['sort_order'],
                    'is_required' => !empty($step['is_required']) ? 1 : 0,
                ]
            );
        }
    }
}

if (!function_exists('patient_experience_stats')) {
    function patient_experience_stats(): array
    {
        patient_experience_ensure_schema();
        $rows = db_all(
            "SELECT status, COUNT(*) AS total
             FROM patient_experience_checkin_sessions
             WHERE created_at >= CURDATE()
             GROUP BY status"
        );
        $stats = ['waiting' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled_expired' => 0];
        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            $total = (int)($row['total'] ?? 0);
            if ($status === 'waiting') {
                $stats['waiting'] += $total;
            } elseif ($status === 'in_progress') {
                $stats['in_progress'] += $total;
            } elseif ($status === 'completed') {
                $stats['completed'] += $total;
            } elseif (in_array($status, ['cancelled', 'expired'], true)) {
                $stats['cancelled_expired'] += $total;
            }
        }
        return $stats;
    }
}

if (!function_exists('patient_experience_kiosk_device_by_id')) {
    function patient_experience_kiosk_device_by_id(int $deviceId): ?array
    {
        if ($deviceId <= 0) {
            return null;
        }
        patient_experience_ensure_schema();
        $row = db_one(
            "SELECT d.*,
                    active_session.id AS active_session_id,
                    active_session.status AS active_session_status,
                    active_session.patient_name AS active_session_patient_name,
                    active_session.current_step_key AS active_session_step_key
             FROM patient_experience_kiosk_devices d
             LEFT JOIN patient_experience_checkin_sessions active_session
               ON active_session.kiosk_device_id = d.id
              AND active_session.status IN ('waiting', 'in_progress')
              AND active_session.expires_at > NOW()
             WHERE d.id = :id
             LIMIT 1",
            ['id' => $deviceId]
        );
        return $row ?: null;
    }
}

if (!function_exists('patient_experience_kiosk_device_by_label')) {
    function patient_experience_kiosk_device_by_label(string $deviceLabel): ?array
    {
        $label = trim($deviceLabel);
        if ($label === '') {
            return null;
        }
        patient_experience_ensure_schema();
        $row = db_one(
            "SELECT *
             FROM patient_experience_kiosk_devices
             WHERE device_label = :device_label
             ORDER BY id DESC
             LIMIT 1",
            ['device_label' => $label]
        );
        return $row ?: null;
    }
}

if (!function_exists('patient_experience_kiosk_device_registered')) {
    function patient_experience_kiosk_device_registered(array $device): bool
    {
        return trim((string)($device['device_token_hash'] ?? '')) !== '';
    }
}

if (!function_exists('patient_experience_registered_device_options')) {
    function patient_experience_registered_device_options(): array
    {
        patient_experience_ensure_schema();
        return db_all(
            "SELECT id, device_label, location_label
             FROM patient_experience_kiosk_devices
             WHERE is_active = 1
               AND device_token_hash IS NOT NULL
             ORDER BY device_label ASC, id ASC"
        );
    }
}

if (!function_exists('patient_experience_kiosk_devices')) {
    function patient_experience_kiosk_devices(): array
    {
        patient_experience_ensure_schema();
        return db_all(
            "SELECT d.*,
                    active_session.id AS active_session_id,
                    active_session.status AS active_session_status,
                    active_session.patient_name AS active_session_patient_name,
                    active_session.current_step_key AS active_session_step_key,
                    setup.id AS active_setup_token_id,
                    setup.expires_at AS active_setup_expires_at
             FROM patient_experience_kiosk_devices d
             LEFT JOIN patient_experience_checkin_sessions active_session
               ON active_session.kiosk_device_id = d.id
              AND active_session.status IN ('waiting', 'in_progress')
              AND active_session.expires_at > NOW()
             LEFT JOIN patient_experience_kiosk_setup_tokens setup
               ON setup.id = (
                    SELECT st.id
                    FROM patient_experience_kiosk_setup_tokens st
                    WHERE st.kiosk_device_id = d.id
                      AND st.used_at IS NULL
                      AND st.revoked_at IS NULL
                      AND st.expires_at > NOW()
                    ORDER BY st.created_at DESC, st.id DESC
                    LIMIT 1
               )
             ORDER BY d.created_at DESC, d.id DESC"
        );
    }
}

if (!function_exists('patient_experience_create_kiosk_device')) {
    function patient_experience_create_kiosk_device(string $deviceLabel, string $locationLabel, ?int $createdBy = null): int
    {
        patient_experience_ensure_schema();
        $label = trim($deviceLabel) !== '' ? trim($deviceLabel) : 'Waiting Room iPad';
        $location = trim($locationLabel) !== '' ? trim($locationLabel) : 'Front Desk';
        return db_insert(
            "INSERT INTO patient_experience_kiosk_devices
                (device_label, location_label, is_active, created_by, created_at)
             VALUES
                (:device_label, :location_label, 1, :created_by, NOW())",
            [
                'device_label' => substr($label, 0, 190),
                'location_label' => substr($location, 0, 190),
                'created_by' => $createdBy,
            ]
        );
    }
}

if (!function_exists('patient_experience_issue_setup_token')) {
    function patient_experience_issue_setup_token(int $deviceId, ?int $createdBy = null): string
    {
        $device = patient_experience_kiosk_device_by_id($deviceId);
        if (!$device) {
            return '';
        }
        db_query(
            "UPDATE patient_experience_kiosk_setup_tokens
             SET revoked_at = NOW(), updated_at = NOW()
             WHERE kiosk_device_id = :device_id
               AND used_at IS NULL
               AND revoked_at IS NULL",
            ['device_id' => $deviceId]
        );

        $token = patient_experience_token(32);
        $expiresAt = date('Y-m-d H:i:s', time() + patient_experience_kiosk_setup_ttl_seconds());
        db_insert(
            "INSERT INTO patient_experience_kiosk_setup_tokens
                (kiosk_device_id, token_hash, expires_at, created_by, created_at)
             VALUES
                (:device_id, :token_hash, :expires_at, :created_by, NOW())",
            [
                'device_id' => $deviceId,
                'token_hash' => patient_experience_token_hash($token),
                'expires_at' => $expiresAt,
                'created_by' => $createdBy,
            ]
        );

        patient_experience_audit('kiosk_setup_token_created', [
            'device_label' => (string)$device['device_label'],
            'expires_at' => $expiresAt,
        ], null, null, $createdBy, $deviceId);

        return $token;
    }
}

if (!function_exists('patient_experience_active_setup_token_for_device')) {
    function patient_experience_active_setup_token_for_device(int $deviceId): string
    {
        if ($deviceId <= 0) {
            return '';
        }
        patient_experience_ensure_schema();
        $row = db_one(
            "SELECT token_hash, expires_at
             FROM patient_experience_kiosk_setup_tokens
             WHERE kiosk_device_id = :device_id
               AND used_at IS NULL
               AND revoked_at IS NULL
               AND expires_at > NOW()
             ORDER BY created_at DESC, id DESC
             LIMIT 1",
            ['device_id' => $deviceId]
        );
        return $row ? '__hashed__' : '';
    }
}

if (!function_exists('patient_experience_ensure_test_kiosk_setup')) {
    function patient_experience_ensure_test_kiosk_setup(?int $userId = null): array
    {
        $label = 'Test Kiosk';
        $location = 'iPad Test';
        $device = patient_experience_kiosk_device_by_label($label);
        $deviceId = $device ? (int)$device['id'] : patient_experience_create_kiosk_device($label, $location, $userId);

        $activeSetup = db_one(
            "SELECT id, expires_at
             FROM patient_experience_kiosk_setup_tokens
             WHERE kiosk_device_id = :device_id
               AND used_at IS NULL
               AND revoked_at IS NULL
               AND expires_at > NOW()
             ORDER BY created_at DESC, id DESC
             LIMIT 1",
            ['device_id' => $deviceId]
        );

        $token = '';
        if ($activeSetup) {
            $token = patient_experience_issue_setup_token($deviceId, $userId);
        } else {
            $token = patient_experience_issue_setup_token($deviceId, $userId);
        }

        return [
            'device_id' => $deviceId,
            'device_label' => $label,
            'location_label' => $location,
            'setup_token' => $token,
            'setup_url' => patient_experience_kiosk_setup_url($token),
            'setup_qr_url' => patient_experience_kiosk_setup_qr_url($token),
        ];
    }
}

if (!function_exists('patient_experience_revoke_setup_token')) {
    function patient_experience_revoke_setup_token(int $tokenId, ?int $userId = null): bool
    {
        if ($tokenId <= 0) {
            return false;
        }
        $row = db_one(
            "SELECT id, kiosk_device_id
             FROM patient_experience_kiosk_setup_tokens
             WHERE id = :id
             LIMIT 1",
            ['id' => $tokenId]
        );
        if (!$row) {
            return false;
        }
        db_execute(
            "UPDATE patient_experience_kiosk_setup_tokens
             SET revoked_at = NOW(), updated_at = NOW()
             WHERE id = :id
             LIMIT 1",
            ['id' => $tokenId]
        );
        patient_experience_audit('kiosk_setup_token_revoked', [
            'setup_token_id' => $tokenId,
        ], null, null, $userId, (int)$row['kiosk_device_id']);
        return true;
    }
}

if (!function_exists('patient_experience_find_valid_setup_token')) {
    function patient_experience_find_valid_setup_token(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        patient_experience_ensure_schema();
        $row = db_one(
            "SELECT st.*, d.device_label, d.location_label, d.is_active
             FROM patient_experience_kiosk_setup_tokens st
             INNER JOIN patient_experience_kiosk_devices d ON d.id = st.kiosk_device_id
             WHERE st.token_hash = :token_hash
             LIMIT 1",
            ['token_hash' => patient_experience_token_hash($token)]
        );
        if (!$row) {
            return null;
        }
        if (trim((string)($row['used_at'] ?? '')) !== '' || trim((string)($row['revoked_at'] ?? '')) !== '') {
            return null;
        }
        $expiresAt = trim((string)($row['expires_at'] ?? ''));
        if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
            return null;
        }
        if ((int)($row['is_active'] ?? 0) !== 1) {
            return null;
        }
        return $row;
    }
}

if (!function_exists('patient_experience_register_device_from_setup_token')) {
    function patient_experience_register_device_from_setup_token(string $token): ?array
    {
        $setup = patient_experience_find_valid_setup_token($token);
        if (!$setup) {
            return null;
        }

        $deviceId = (int)($setup['kiosk_device_id'] ?? 0);
        if ($deviceId <= 0) {
            return null;
        }

        $deviceToken = patient_experience_token(48);
        db_execute(
            "UPDATE patient_experience_kiosk_devices
             SET device_token_hash = :device_token_hash,
                 registered_at = NOW(),
                 last_seen_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
             LIMIT 1",
            [
                'id' => $deviceId,
                'device_token_hash' => patient_experience_token_hash($deviceToken),
            ]
        );

        db_execute(
            "UPDATE patient_experience_kiosk_setup_tokens
             SET used_at = NOW(), updated_at = NOW()
             WHERE id = :id
             LIMIT 1",
            ['id' => (int)$setup['id']]
        );

        patient_experience_audit('kiosk_device_registered', [
            'device_label' => (string)$setup['device_label'],
            'setup_token_id' => (int)$setup['id'],
        ], null, null, null, $deviceId);

        return [
            'device_id' => $deviceId,
            'device_label' => (string)$setup['device_label'],
            'location_label' => (string)$setup['location_label'],
            'device_token' => $deviceToken,
        ];
    }
}

if (!function_exists('patient_experience_find_device_by_token')) {
    function patient_experience_find_device_by_token(string $deviceToken): ?array
    {
        $deviceToken = trim($deviceToken);
        if ($deviceToken === '') {
            return null;
        }
        patient_experience_ensure_schema();
        $row = db_one(
            "SELECT *
             FROM patient_experience_kiosk_devices
             WHERE device_token_hash = :token_hash
               AND is_active = 1
             LIMIT 1",
            ['token_hash' => patient_experience_token_hash($deviceToken)]
        );
        return $row ?: null;
    }
}

if (!function_exists('patient_experience_touch_device')) {
    function patient_experience_touch_device(int $deviceId): void
    {
        if ($deviceId <= 0) {
            return;
        }
        $device = patient_experience_kiosk_device_by_id($deviceId);
        if (!$device) {
            return;
        }
        db_execute(
            "UPDATE patient_experience_kiosk_devices
             SET last_seen_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
             LIMIT 1",
            ['id' => $deviceId]
        );

        $lastSeenAt = trim((string)($device['last_seen_at'] ?? ''));
        $shouldAudit = $lastSeenAt === '' || strtotime($lastSeenAt) === false || (time() - strtotime($lastSeenAt)) >= 600;
        if ($shouldAudit) {
            patient_experience_audit('kiosk_device_last_seen', [
                'device_label' => (string)$device['device_label'],
            ], null, null, null, $deviceId);
        }
    }
}

if (!function_exists('patient_experience_visible_section_fields')) {
    function patient_experience_visible_section_fields(array $section, array $answers): array
    {
        $visible = [];
        foreach ((array)($section['fields'] ?? []) as $field) {
            if (!patient_experience_field_is_visible($field, $answers)) {
                continue;
            }
            $visible[] = $field;
        }
        return $visible;
    }
}

if (!function_exists('patient_experience_section_completion')) {
    function patient_experience_section_completion(array $session, array $section, array $answers, array $signatureSummary = []): array
    {
        if (!patient_experience_section_is_visible($section, $answers)) {
            return ['status' => 'hidden', 'complete' => true, 'missing_fields' => [], 'missing_signatures' => []];
        }

        $missingFields = [];
        $missingSignatures = [];
        foreach (patient_experience_visible_section_fields($section, $answers) as $field) {
            $children = patient_experience_field_children($field);
            if ($children !== []) {
                foreach ($children as $child) {
                    if (!empty($child['required']) && patient_experience_empty_answer(patient_experience_answer_value($answers, (string)$child['key']))) {
                        $missingFields[] = (string)$child['label'];
                    }
                }
                continue;
            }

            if (patient_experience_field_signature_required($field)) {
                $signatureKey = (string)($field['key'] ?? '');
                if (patient_experience_empty_answer(patient_experience_answer_value($answers, $signatureKey))) {
                    $missingSignatures[] = (string)($field['label'] ?? 'Signature');
                }
                continue;
            }

            if (!patient_experience_field_is_static($field) && !empty($field['required']) && patient_experience_empty_answer(patient_experience_answer_value($answers, (string)$field['key']))) {
                $missingFields[] = (string)($field['label'] ?? $field['key']);
            }
        }

        $sessionStatus = (string)($session['status'] ?? 'waiting');
        $currentStep = (string)($session['current_step_key'] ?? 'welcome');
        if ($missingFields === [] && $missingSignatures === []) {
            return ['status' => 'completed', 'complete' => true, 'missing_fields' => [], 'missing_signatures' => []];
        }
        if ($sessionStatus === 'completed') {
            return ['status' => 'incomplete', 'complete' => false, 'missing_fields' => $missingFields, 'missing_signatures' => $missingSignatures];
        }
        if ($currentStep === (string)$section['section_key']) {
            return ['status' => 'in_progress', 'complete' => false, 'missing_fields' => $missingFields, 'missing_signatures' => $missingSignatures];
        }
        return ['status' => 'waiting', 'complete' => false, 'missing_fields' => $missingFields, 'missing_signatures' => $missingSignatures];
    }
}

if (!function_exists('patient_experience_packet_progress_summary')) {
    function patient_experience_packet_progress_summary(array $session): array
    {
        $answers = patient_experience_answers_for_session((int)$session['id']);
        $sections = [];
        $visibleCount = 0;
        $completedCount = 0;
        foreach (patient_experience_form_steps() as $stepKey => $section) {
            $completion = patient_experience_section_completion($session, $section, $answers);
            if ($completion['status'] === 'hidden') {
                continue;
            }
            $visibleCount++;
            if ($completion['complete']) {
                $completedCount++;
            }
            $sections[] = [
                'key' => $stepKey,
                'title' => (string)($section['title'] ?? $stepKey),
                'status' => $completion['status'],
                'missing_fields' => $completion['missing_fields'],
                'missing_signatures' => $completion['missing_signatures'],
            ];
        }

        $status = (string)($session['status'] ?? 'idle');
        $percent = $visibleCount > 0 ? (int)round(($completedCount / $visibleCount) * 100) : 0;
        if ($status === 'completed') {
            $percent = 100;
        } elseif ($status === 'in_progress') {
            $percent = max($percent, (int)($session['progress_percent'] ?? 0));
        }

        return [
            'status' => $status,
            'current_step' => (string)($session['current_step_key'] ?? 'welcome'),
            'percent_complete' => max(0, min(100, $percent)),
            'visible_sections' => $visibleCount,
            'completed_sections' => $completedCount,
            'sections' => $sections,
        ];
    }
}

if (!function_exists('patient_experience_session_progress')) {
    function patient_experience_session_progress(array $session): array
    {
        $summary = patient_experience_packet_progress_summary($session);
        return [
            'status' => (string)$summary['status'],
            'current_step' => (string)$summary['current_step'],
            'percent_complete' => (int)$summary['percent_complete'],
            'sections' => $summary['sections'],
            'completed_sections' => (int)$summary['completed_sections'],
            'visible_sections' => (int)$summary['visible_sections'],
        ];
    }
}

if (!function_exists('patient_experience_active_session')) {
    function patient_experience_active_session(): ?array
    {
        patient_experience_ensure_schema();
        patient_experience_expire_stale_sessions(null);
        $session = db_one(
            "SELECT s.*, u.first_name, u.last_name, d.device_label, d.location_label
             FROM patient_experience_checkin_sessions s
             LEFT JOIN users u ON u.id = s.started_by_user_id
             LEFT JOIN patient_experience_kiosk_devices d ON d.id = s.kiosk_device_id
             WHERE s.status IN ('waiting', 'in_progress')
               AND s.expires_at > NOW()
             ORDER BY s.created_at DESC, s.id DESC
             LIMIT 1"
        );
        return $session ?: null;
    }
}

if (!function_exists('patient_experience_recent_sessions')) {
    function patient_experience_recent_sessions(int $limit = 20): array
    {
        patient_experience_ensure_schema();
        $limit = max(1, min(100, $limit));
        return db_all(
            "SELECT s.*, u.first_name, u.last_name, d.device_label, d.location_label
             FROM patient_experience_checkin_sessions s
             LEFT JOIN users u ON u.id = s.started_by_user_id
             LEFT JOIN patient_experience_kiosk_devices d ON d.id = s.kiosk_device_id
             ORDER BY s.created_at DESC, s.id DESC
             LIMIT {$limit}"
        );
    }
}

if (!function_exists('patient_experience_start_placeholder_session')) {
    function patient_experience_start_placeholder_session(?int $leadId, string $patientName, ?int $userId, ?int $kioskDeviceId = null): array
    {
        patient_experience_ensure_schema();
        $deviceId = $kioskDeviceId && $kioskDeviceId > 0 ? $kioskDeviceId : null;
        if ($deviceId !== null) {
            $device = patient_experience_kiosk_device_by_id($deviceId);
            if (!$device || !patient_experience_kiosk_device_registered($device) || (int)($device['is_active'] ?? 0) !== 1) {
                return ['id' => 0, 'token' => '', 'expires_at' => '', 'error' => 'Selected kiosk is not ready.'];
            }
        }
        $token = patient_experience_token(32);
        $tokenHash = patient_experience_token_hash($token);
        $expiresAt = date('Y-m-d H:i:s', time() + 7200);
        $sessionId = db_insert(
            'INSERT INTO patient_experience_checkin_sessions (kiosk_device_id, lead_id, patient_name, session_token_hash, status, started_by_user_id, expires_at, started_at, staff_notes, current_step_key, progress_percent, created_at)
             VALUES (:kiosk_device_id, :lead_id, :patient_name, :token_hash, :status, :user_id, :expires_at, NOW(), :staff_notes, :current_step_key, 0, NOW())',
            [
                'kiosk_device_id' => $deviceId,
                'lead_id' => $leadId && $leadId > 0 ? $leadId : null,
                'patient_name' => trim($patientName),
                'token_hash' => $tokenHash,
                'status' => 'waiting',
                'user_id' => $userId,
                'expires_at' => $expiresAt,
                'staff_notes' => '',
                'current_step_key' => 'welcome',
            ]
        );
        patient_experience_audit('session_created', ['expires_at' => $expiresAt], $sessionId, $leadId, $userId);
        if ($deviceId !== null) {
            patient_experience_audit('session_assigned_to_device', [
                'device_label' => (string)($device['device_label'] ?? ''),
                'location_label' => (string)($device['location_label'] ?? ''),
            ], $sessionId, $leadId, $userId, $deviceId);
        }
        return ['id' => $sessionId, 'token' => $token, 'expires_at' => $expiresAt];
    }
}

if (!function_exists('patient_experience_expire_stale_sessions')) {
    function patient_experience_expire_stale_sessions(?int $userId = null): int
    {
        patient_experience_ensure_schema();
        $sessions = db_all(
            "SELECT id, lead_id
             FROM patient_experience_checkin_sessions
             WHERE status IN ('waiting', 'in_progress')
               AND expires_at <= NOW()"
        );
        foreach ($sessions as $session) {
            db_execute(
                "UPDATE patient_experience_checkin_sessions
                 SET status = 'expired',
                     expired_at = NOW(),
                     current_step_key = 'expired',
                     updated_at = NOW()
                 WHERE id = :id",
                ['id' => (int)$session['id']]
            );
            patient_experience_audit('session_expired', [], (int)$session['id'], (int)($session['lead_id'] ?? 0) ?: null, $userId);
        }
        return count($sessions);
    }
}

if (!function_exists('patient_experience_cancel_session')) {
    function patient_experience_cancel_session(int $sessionId, ?int $userId = null): bool
    {
        patient_experience_ensure_schema();
        $session = db_one('SELECT id, lead_id FROM patient_experience_checkin_sessions WHERE id = :id LIMIT 1', ['id' => $sessionId]);
        if (!$session) {
            return false;
        }
        db_execute(
            "UPDATE patient_experience_checkin_sessions
             SET status = 'cancelled',
                 cancelled_at = NOW(),
                 current_step_key = 'cancelled',
                 updated_at = NOW()
             WHERE id = :id
             LIMIT 1",
            ['id' => $sessionId]
        );
        patient_experience_audit('session_cancelled', [], $sessionId, (int)($session['lead_id'] ?? 0) ?: null, $userId);
        return true;
    }
}

if (!function_exists('patient_experience_session_by_kiosk_token')) {
    function patient_experience_session_by_kiosk_token(string $kioskToken): ?array
    {
        $token = trim($kioskToken);
        if ($token === '') {
            return null;
        }
        patient_experience_ensure_schema();
        patient_experience_expire_stale_sessions(null);
        $session = db_one(
            "SELECT *
             FROM patient_experience_checkin_sessions
             WHERE session_token_hash = :token
               AND status IN ('waiting', 'in_progress')
               AND expires_at > NOW()
             LIMIT 1",
            ['token' => strtolower($token)]
        );
        return $session ?: null;
    }
}

if (!function_exists('patient_experience_begin_session')) {
    function patient_experience_begin_session(string $kioskToken, string $deviceToken = ''): array
    {
        $session = trim($deviceToken) !== ''
            ? patient_experience_kiosk_session_for_device($kioskToken, $deviceToken)
            : patient_experience_session_by_kiosk_token($kioskToken);
        if (!$session && trim($deviceToken) !== '') {
            return ['ok' => false, 'message' => 'This check-in session is not linked to this iPad.'];
        }
        if (!$session) {
            return ['ok' => false, 'message' => 'This check-in session is no longer active.'];
        }
        if ((string)$session['status'] === 'waiting') {
            db_execute(
                "UPDATE patient_experience_checkin_sessions
                 SET status = 'in_progress',
                     current_step_key = 'welcome',
                     progress_percent = 1,
                     device_user_agent = :ua,
                     device_ip = :ip,
                     updated_at = NOW()
                 WHERE id = :id",
                [
                    'id' => (int)$session['id'],
                    'ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    'ip' => function_exists('client_ip') ? client_ip() : ($_SERVER['REMOTE_ADDR'] ?? ''),
                ]
            );
            patient_experience_audit('patient_began', [], (int)$session['id'], (int)($session['lead_id'] ?? 0) ?: null);
            $session = $deviceToken !== ''
                ? patient_experience_kiosk_session_for_device($kioskToken, $deviceToken)
                : patient_experience_session_by_kiosk_token($kioskToken);
            if (!$session) {
                return ['ok' => false, 'message' => 'This check-in session is no longer active.'];
            }
        }
        return ['ok' => true, 'session' => patient_experience_public_session($session), 'form' => patient_experience_form_payload($session)];
    }
}

if (!function_exists('patient_experience_public_session')) {
    function patient_experience_public_session(array $session): array
    {
        $displayName = trim((string)($session['patient_name'] ?? ''));
        if ($displayName === '') {
            $displayName = 'Patient';
        }
        return [
            'id' => (int)$session['id'],
            'display_name' => $displayName,
            'status' => (string)$session['status'],
            'current_step' => (string)($session['current_step_key'] ?? 'welcome'),
            'percent_complete' => (int)($session['progress_percent'] ?? 0),
            'expires_at' => (string)$session['expires_at'],
        ];
    }
}

if (!function_exists('patient_experience_form_payload')) {
    function patient_experience_form_payload(array $session): array
    {
        $steps = patient_experience_form_steps();
        $answers = patient_experience_answers_for_session((int)$session['id']);
        $stepKeys = patient_experience_step_keys($answers);
        $currentStep = (string)($session['current_step_key'] ?? 'welcome');
        if (!isset($steps[$currentStep]) || !in_array($currentStep, $stepKeys, true)) {
            $currentStep = $stepKeys[0] ?? array_key_first($steps) ?? 'welcome';
        }
        $step = $steps[$currentStep] ?? ['section_key' => $currentStep, 'title' => 'Check-in', 'fields' => []];
        $progress = patient_experience_packet_progress_summary($session);
        return [
            'current_step' => $currentStep,
            'steps' => $stepKeys,
            'step' => $step,
            'packet_title' => (string)(patient_experience_packet_definition()['title'] ?? 'Patient Packet'),
            'answers' => $answers,
            'signatures' => patient_experience_signatures_for_session((int)$session['id']),
            'progress' => $progress,
            'review' => $currentStep === 'final_review' ? patient_experience_patient_review_payload($session, $answers) : null,
        ];
    }
}

if (!function_exists('patient_experience_answers_for_session')) {
    function patient_experience_answers_for_session(int $sessionId): array
    {
        $rows = db_all(
            'SELECT field_key, answer_json, answer_label, is_sensitive
             FROM patient_experience_packet_answers
             WHERE checkin_session_id = :session_id',
            ['session_id' => $sessionId]
        );
        $answers = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string)($row['answer_json'] ?? ''), true);
            $answers[(string)$row['field_key']] = [
                'value' => $decoded,
                'label' => (string)($row['answer_label'] ?? ''),
                'is_sensitive' => (int)($row['is_sensitive'] ?? 0) === 1,
            ];
        }
        return $answers;
    }
}

if (!function_exists('patient_experience_signatures_for_session')) {
    function patient_experience_signatures_for_session(int $sessionId): array
    {
        $rows = db_all(
            'SELECT packet_section_id, template_version_id, signer_name, signed_at, signature_hash
             FROM patient_experience_signatures
             WHERE checkin_session_id = :session_id
             ORDER BY signed_at DESC, id DESC',
            ['session_id' => $sessionId]
        );
        return $rows;
    }
}

if (!function_exists('patient_experience_signed_packet_for_session')) {
    function patient_experience_signed_packet_for_session(int $sessionId): ?array
    {
        $row = db_one(
            'SELECT *
             FROM patient_experience_signed_packets
             WHERE checkin_session_id = :session_id
             ORDER BY signed_at DESC, id DESC
             LIMIT 1',
            ['session_id' => $sessionId]
        );
        if (!$row) {
            return null;
        }
        $row['snapshot'] = json_decode((string)($row['snapshot_json'] ?? ''), true) ?: [];
        return $row;
    }
}

if (!function_exists('patient_experience_signature_summary')) {
    function patient_experience_signature_summary(int $sessionId): array
    {
        $row = db_one(
            'SELECT COUNT(*) AS total, MAX(signed_at) AS latest_signed_at
             FROM patient_experience_signatures
             WHERE checkin_session_id = :session_id',
            ['session_id' => $sessionId]
        );
        return [
            'total' => (int)($row['total'] ?? 0),
            'latest_signed_at' => (string)($row['latest_signed_at'] ?? ''),
        ];
    }
}

if (!function_exists('patient_experience_signed_packet_snapshot')) {
    function patient_experience_signed_packet_snapshot(array $session): array
    {
        $sessionId = (int)($session['id'] ?? 0);
        $definition = patient_experience_packet_definition();
        $answers = patient_experience_answers_for_session($sessionId);
        $signatures = patient_experience_signatures_for_session($sessionId);
        $review = patient_experience_patient_review_payload($session, $answers);
        return [
            'packet' => [
                'key' => (string)($definition['packet_key'] ?? patient_experience_active_packet_key()),
                'version' => (int)($definition['version'] ?? 1),
                'title' => (string)($definition['title'] ?? 'Patient Packet'),
            ],
            'session' => [
                'id' => $sessionId,
                'patient_name' => (string)($session['patient_name'] ?? ''),
                'lead_id' => (int)($session['lead_id'] ?? 0),
                'status' => (string)($session['status'] ?? ''),
                'completed_at' => (string)($session['completed_at'] ?? ''),
                'current_step_key' => (string)($session['current_step_key'] ?? ''),
                'progress_percent' => (int)($session['progress_percent'] ?? 0),
            ],
            'answers' => $answers,
            'signatures' => $signatures,
            'review' => $review,
            'signed_at' => date('c'),
        ];
    }
}

if (!function_exists('patient_experience_store_signed_packet')) {
    function patient_experience_store_signed_packet(int $sessionId): bool
    {
        $session = patient_experience_session_by_id($sessionId);
        if (!$session) {
            return false;
        }

        $snapshot = patient_experience_signed_packet_snapshot($session);
        $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($snapshotJson) || $snapshotJson === '') {
            return false;
        }

        $packet = $snapshot['packet'] ?? [];
        $hash = hash('sha256', $snapshotJson);
        $signatureCount = count((array)($snapshot['signatures'] ?? []));
        db_execute(
            'INSERT INTO patient_experience_signed_packets
                (checkin_session_id, packet_key, packet_version, packet_title, patient_name, snapshot_hash, snapshot_json, signature_count, signed_at, created_at)
             VALUES
                (:session_id, :packet_key, :packet_version, :packet_title, :patient_name, :snapshot_hash, :snapshot_json, :signature_count, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                packet_key = VALUES(packet_key),
                packet_version = VALUES(packet_version),
                packet_title = VALUES(packet_title),
                patient_name = VALUES(patient_name),
                snapshot_hash = VALUES(snapshot_hash),
                snapshot_json = VALUES(snapshot_json),
                signature_count = VALUES(signature_count),
                signed_at = VALUES(signed_at),
                updated_at = NOW()',
            [
                'session_id' => $sessionId,
                'packet_key' => (string)($packet['key'] ?? patient_experience_active_packet_key()),
                'packet_version' => (int)($packet['version'] ?? 1),
                'packet_title' => (string)($packet['title'] ?? 'Patient Packet'),
                'patient_name' => (string)($session['patient_name'] ?? ''),
                'snapshot_hash' => $hash,
                'snapshot_json' => $snapshotJson,
                'signature_count' => $signatureCount,
            ]
        );

        patient_experience_audit('signed_packet_saved', [
            'snapshot_hash' => $hash,
            'signature_count' => $signatureCount,
        ], $sessionId, (int)($session['lead_id'] ?? 0) ?: null);

        return true;
    }
}

if (!function_exists('patient_experience_merge_answer_values')) {
    function patient_experience_merge_answer_values(array $savedAnswers, array $stepAnswers): array
    {
        $merged = [];
        foreach ($savedAnswers as $key => $answer) {
            $merged[(string)$key] = is_array($answer) && array_key_exists('value', $answer) ? $answer['value'] : $answer;
        }
        foreach ($stepAnswers as $key => $value) {
            $merged[(string)$key] = $value;
        }
        return $merged;
    }
}

if (!function_exists('patient_experience_validate_field_value')) {
    function patient_experience_validate_field_value(array $field, mixed $value): ?string
    {
        $type = (string)($field['type'] ?? 'text');
        $label = (string)($field['label'] ?? $field['key'] ?? 'Field');
        if (patient_experience_empty_answer($value)) {
            return !empty($field['required']) ? 'Please complete: ' . $label : null;
        }
        $text = is_array($value) ? '' : trim((string)$value);
        if ($type === 'email' && !filter_var($text, FILTER_VALIDATE_EMAIL)) {
            return 'Please enter a valid email address.';
        }
        if ($type === 'phone' && strlen(preg_replace('/\D+/', '', $text) ?? '') < 10) {
            return 'Please enter a valid phone number.';
        }
        if ($type === 'zip' && !preg_match('/^\d{5}(?:-\d{4})?$/', $text)) {
            return 'Please enter a valid ZIP code.';
        }
        if (in_array($type, ['date', 'dob'], true) && strtotime($text) === false) {
            return 'Please enter a valid date for ' . $label . '.';
        }
        if ($type === 'digital_initials' && strlen($text) > 8) {
            return 'Please keep initials short.';
        }
        return null;
    }
}

if (!function_exists('patient_experience_clear_hidden_answers_for_section')) {
    function patient_experience_clear_hidden_answers_for_section(int $sessionId, array $section, array $visibleFieldKeys): void
    {
        $allKeys = [];
        foreach ((array)($section['fields'] ?? []) as $field) {
            $allKeys[] = (string)($field['key'] ?? '');
            foreach (patient_experience_field_children($field) as $child) {
                $allKeys[] = (string)$child['key'];
            }
        }
        $hiddenKeys = array_values(array_filter($allKeys, static fn(string $key): bool => $key !== '' && !in_array($key, $visibleFieldKeys, true)));
        if ($hiddenKeys === []) {
            return;
        }
        $placeholders = [];
        $params = ['session_id' => $sessionId];
        foreach ($hiddenKeys as $index => $fieldKey) {
            $token = 'field_' . $index;
            $placeholders[] = ':' . $token;
            $params[$token] = $fieldKey;
        }
        db_execute(
            'DELETE FROM patient_experience_packet_answers
             WHERE checkin_session_id = :session_id
               AND field_key IN (' . implode(', ', $placeholders) . ')',
            $params
        );
    }
}

if (!function_exists('patient_experience_patient_review_payload')) {
    function patient_experience_patient_review_payload(array $session, array $answers): array
    {
        $items = [];
        foreach (patient_experience_form_steps() as $section) {
            if (!patient_experience_section_is_visible($section, $answers) || in_array((string)($section['section_key'] ?? ''), ['final_review', 'final_signature'], true)) {
                continue;
            }
            $rows = [];
            foreach (patient_experience_visible_section_fields($section, $answers) as $field) {
                if (patient_experience_field_is_static($field) || patient_experience_field_signature_required($field)) {
                    continue;
                }
                $children = patient_experience_field_children($field);
                if ($children !== []) {
                    foreach ($children as $child) {
                        $value = patient_experience_answer_value($answers, (string)$child['key']);
                        if (!patient_experience_empty_answer($value)) {
                            $rows[] = ['label' => (string)$child['label'], 'value' => patient_experience_answer_label($value)];
                        }
                    }
                    continue;
                }
                $value = patient_experience_answer_value($answers, (string)($field['key'] ?? ''));
                if (!patient_experience_empty_answer($value)) {
                    $rows[] = ['label' => (string)($field['label'] ?? $field['key']), 'value' => patient_experience_answer_label($value)];
                }
            }
            $items[] = ['title' => (string)$section['title'], 'rows' => $rows];
        }
        return ['sections' => $items];
    }
}

if (!function_exists('patient_experience_save_step')) {
    function patient_experience_save_step(string $kioskToken, string $stepKey, array $answers, string $deviceToken = ''): array
    {
        $session = trim($deviceToken) !== ''
            ? patient_experience_kiosk_session_for_device($kioskToken, $deviceToken)
            : patient_experience_session_by_kiosk_token($kioskToken);
        if (!$session && trim($deviceToken) !== '') {
            return ['ok' => false, 'message' => 'This check-in session is not linked to this iPad.'];
        }
        if (!$session || (string)$session['status'] !== 'in_progress') {
            return ['ok' => false, 'message' => 'This check-in session is not active.'];
        }

        $steps = patient_experience_form_steps();
        if (!isset($steps[$stepKey])) {
            return ['ok' => false, 'message' => 'Unknown form step.'];
        }

        $section = patient_experience_packet_section($stepKey);
        if (!$section) {
            return ['ok' => false, 'message' => 'Form section is not ready.'];
        }

        $savedAnswers = patient_experience_answers_for_session((int)$session['id']);
        $mergedAnswers = patient_experience_merge_answer_values($savedAnswers, $answers);
        $visibleFields = patient_experience_visible_section_fields($steps[$stepKey], $mergedAnswers);
        $visibleFieldKeys = [];

        foreach ($visibleFields as $field) {
            $fieldKey = (string)($field['key'] ?? '');
            if ($fieldKey !== '') {
                $visibleFieldKeys[] = $fieldKey;
            }
            foreach (patient_experience_field_children($field) as $child) {
                $visibleFieldKeys[] = (string)$child['key'];
            }

            if (patient_experience_field_is_static($field)) {
                continue;
            }

            $children = patient_experience_field_children($field);
            if ($children !== []) {
                foreach ($children as $child) {
                    $childKey = (string)$child['key'];
                    $childValue = $answers[$childKey] ?? patient_experience_answer_value($savedAnswers, $childKey);
                    $error = patient_experience_validate_field_value($child, $childValue);
                    if ($error !== null) {
                        return ['ok' => false, 'message' => $error];
                    }
                    patient_experience_upsert_answer((int)$session['id'], $section, $child, $childValue);
                }
                continue;
            }

            $value = $answers[$fieldKey] ?? patient_experience_answer_value($savedAnswers, $fieldKey);
            if (patient_experience_field_signature_required($field)) {
                $savedSignature = patient_experience_save_signature((int)$session['id'], $section, $field, (string)$value, $session);
                if (!$savedSignature['ok']) {
                    return $savedSignature;
                }
                patient_experience_upsert_answer((int)$session['id'], $section, $field, $savedSignature['storage_key'] ?? '');
                continue;
            }

            $error = patient_experience_validate_field_value($field, $value);
            if ($error !== null) {
                return ['ok' => false, 'message' => $error];
            }
            patient_experience_upsert_answer((int)$session['id'], $section, $field, $value);
        }

        patient_experience_clear_hidden_answers_for_section((int)$session['id'], $steps[$stepKey], $visibleFieldKeys);

        $updatedAnswers = patient_experience_answers_for_session((int)$session['id']);
        $stepKeys = patient_experience_step_keys($updatedAnswers);
        $currentIndex = array_search($stepKey, $stepKeys, true);
        $nextIndex = is_int($currentIndex) ? $currentIndex + 1 : count($stepKeys);
        $isComplete = $nextIndex >= count($stepKeys);
        $nextStep = $isComplete ? 'complete' : $stepKeys[$nextIndex];
        $percent = $isComplete ? 100 : (int)round(($nextIndex / max(1, count($stepKeys))) * 100);

        db_execute(
            "UPDATE patient_experience_checkin_sessions
             SET current_step_key = :step_key,
                 progress_percent = :progress,
                 status = :status,
                 completed_at = CASE WHEN :status_complete = 1 THEN NOW() ELSE completed_at END,
                 updated_at = NOW()
             WHERE id = :id",
            [
                'step_key' => $nextStep,
                'progress' => $percent,
                'status' => $isComplete ? 'completed' : 'in_progress',
                'status_complete' => $isComplete ? 1 : 0,
                'id' => (int)$session['id'],
            ]
        );

        patient_experience_audit('step_saved', ['step_key' => $stepKey], (int)$session['id'], (int)($session['lead_id'] ?? 0) ?: null);
        if ($isComplete) {
            patient_experience_audit('session_completed', [], (int)$session['id'], (int)($session['lead_id'] ?? 0) ?: null);
            return ['ok' => true, 'completed' => true, 'message' => 'Check-in completed.'];
        }

        $updated = $deviceToken !== ''
            ? patient_experience_kiosk_session_for_device($kioskToken, $deviceToken)
            : patient_experience_session_by_kiosk_token($kioskToken);
        return ['ok' => true, 'completed' => false, 'session' => patient_experience_public_session($updated ?: $session), 'form' => patient_experience_form_payload($updated ?: $session)];
    }
}

if (!function_exists('patient_experience_signature_storage_dir')) {
    function patient_experience_signature_storage_dir(int $sessionId): string
    {
        $relative = 'patient-experience/signatures/session-' . max(1, $sessionId);
        $directory = storage_path($relative);
        ensure_directory($directory);
        return $directory;
    }
}

if (!function_exists('patient_experience_save_signature')) {
    function patient_experience_save_signature(int $sessionId, array $section, array $field, string $dataUrl, array $session): array
    {
        $dataUrl = trim($dataUrl);
        if ($dataUrl !== '' && !str_starts_with($dataUrl, 'data:image/')) {
            return ['ok' => true, 'storage_key' => $dataUrl, 'signature_hash' => ''];
        }
        if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=\r\n]+)$/', $dataUrl, $matches)) {
            return ['ok' => false, 'message' => 'Please capture the required signature.'];
        }

        $binary = base64_decode(str_replace(["\r", "\n"], '', $matches[1]), true);
        if (!is_string($binary) || strlen($binary) < 200) {
            return ['ok' => false, 'message' => 'The signature was too small. Please sign again.'];
        }
        if (strlen($binary) > 1500000) {
            return ['ok' => false, 'message' => 'The signature image is too large. Please clear and sign again.'];
        }

        $hash = hash('sha256', $binary);
        $fieldKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)($field['key'] ?? 'signature'));
        $fileName = $fieldKey . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.png';
        $directory = patient_experience_signature_storage_dir($sessionId);
        $filePath = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $fileName;
        if (@file_put_contents($filePath, $binary) === false) {
            return ['ok' => false, 'message' => 'Could not save signature. Please try again.'];
        }

        $storageKey = 'patient-experience/signatures/session-' . $sessionId . '/' . $fileName;
        $signerName = trim((string)($session['patient_name'] ?? ''));
        if ($signerName === '') {
            $signerName = 'Patient';
        }

        db_insert(
            'INSERT INTO patient_experience_signatures (checkin_session_id, packet_section_id, template_version_id, signer_name, signer_relationship, signature_storage_key, signature_hash, signed_at, ip_address, user_agent, device_label, created_at)
             VALUES (:session_id, :section_id, :template_version_id, :signer_name, :relationship, :storage_key, :signature_hash, NOW(), :ip, :ua, :device_label, NOW())',
            [
                'session_id' => $sessionId,
                'section_id' => (int)$section['id'],
                'template_version_id' => (int)$section['template_version_id'],
                'signer_name' => $signerName,
                'relationship' => 'self',
                'storage_key' => $storageKey,
                'signature_hash' => $hash,
                'ip' => function_exists('client_ip') ? client_ip() : ($_SERVER['REMOTE_ADDR'] ?? ''),
                'ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'device_label' => 'Waiting Room iPad',
            ]
        );

        patient_experience_audit('signature_captured', [
            'field_key' => (string)($field['key'] ?? 'signature'),
            'packet_section_id' => (int)$section['id'],
            'signature_hash' => $hash,
        ], $sessionId, (int)($session['lead_id'] ?? 0) ?: null);

        return ['ok' => true, 'storage_key' => $storageKey, 'signature_hash' => $hash];
    }
}

if (!function_exists('patient_experience_complete_session')) {
    function patient_experience_complete_session(string $kioskToken, string $deviceToken = ''): array
    {
        $deviceToken = trim($deviceToken);
        $session = $deviceToken !== ''
            ? patient_experience_kiosk_session_for_device($kioskToken, $deviceToken)
            : patient_experience_session_by_kiosk_token($kioskToken);
        if (!$session && $deviceToken !== '') {
            return ['ok' => false, 'message' => 'This check-in session is not linked to this iPad.'];
        }
        if (!$session) {
            return ['ok' => false, 'message' => 'This check-in session is no longer active.'];
        }
        db_execute(
            "UPDATE patient_experience_checkin_sessions
             SET status = 'completed',
                 current_step_key = 'complete',
                 progress_percent = 100,
                 completed_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id",
            ['id' => (int)$session['id']]
        );
        patient_experience_store_signed_packet((int)$session['id']);
        patient_experience_audit('session_completed', [], (int)$session['id'], (int)($session['lead_id'] ?? 0) ?: null);
        return ['ok' => true, 'message' => 'Check-in completed.'];
    }
}

if (!function_exists('patient_experience_kiosk_session_for_device')) {
    function patient_experience_kiosk_session_for_device(string $kioskToken, string $deviceToken): ?array
    {
        $session = patient_experience_session_by_kiosk_token($kioskToken);
        if (!$session) {
            return null;
        }
        $device = patient_experience_find_device_by_token($deviceToken);
        if (!$device) {
            return null;
        }
        if ((int)($session['kiosk_device_id'] ?? 0) !== (int)($device['id'] ?? 0)) {
            return null;
        }
        return $session;
    }
}

if (!function_exists('patient_experience_packet_section')) {
    function patient_experience_packet_section(string $stepKey): ?array
    {
        patient_experience_seed_packet_sections();
        $row = db_one(
            'SELECT id, template_version_id
             FROM patient_experience_packet_sections
             WHERE section_key = :section_key
             ORDER BY id DESC
             LIMIT 1',
            ['section_key' => $stepKey]
        );
        return $row ?: null;
    }
}

if (!function_exists('patient_experience_session_by_id')) {
    function patient_experience_session_by_id(int $sessionId): ?array
    {
        patient_experience_ensure_schema();
        $row = db_one(
            "SELECT s.*, reviewer.first_name AS reviewer_first_name, reviewer.last_name AS reviewer_last_name
             FROM patient_experience_checkin_sessions s
             LEFT JOIN users reviewer ON reviewer.id = s.reviewed_by_user_id
             WHERE s.id = :id
             LIMIT 1",
            ['id' => $sessionId]
        );
        return $row ?: null;
    }
}

if (!function_exists('patient_experience_session_audit_timeline')) {
    function patient_experience_session_audit_timeline(int $sessionId): array
    {
        return db_all(
            'SELECT event_key, event_label, payload_json, created_at
             FROM patient_experience_audit_events
             WHERE checkin_session_id = :session_id
             ORDER BY created_at DESC, id DESC
             LIMIT 60',
            ['session_id' => $sessionId]
        );
    }
}

if (!function_exists('patient_experience_staff_review_context')) {
    function patient_experience_staff_review_context(int $sessionId): ?array
    {
        $session = patient_experience_session_by_id($sessionId);
        if (!$session) {
            return null;
        }
        $answers = patient_experience_answers_for_session($sessionId);
        $progress = patient_experience_packet_progress_summary($session);
        $missingFields = [];
        $missingSignatures = [];
        foreach ((array)$progress['sections'] as $section) {
            foreach ((array)($section['missing_fields'] ?? []) as $label) {
                $missingFields[] = (string)$label;
            }
            foreach ((array)($section['missing_signatures'] ?? []) as $label) {
                $missingSignatures[] = (string)$label;
            }
        }

        $patientSummary = [
            'Patient name' => trim(trim((string)patient_experience_answer_value($answers, 'legal_first_name')) . ' ' . trim((string)patient_experience_answer_value($answers, 'legal_last_name'))),
            'Preferred name' => (string)patient_experience_answer_value($answers, 'preferred_name'),
            'Date of birth' => (string)patient_experience_answer_value($answers, 'date_of_birth'),
            'Phone' => (string)patient_experience_answer_value($answers, 'mobile_phone'),
            'Email' => (string)patient_experience_answer_value($answers, 'email'),
            'Preferred contact' => (string)patient_experience_answer_value($answers, 'preferred_contact_method'),
        ];

        $photoPermissions = [
            'Clinical' => (string)patient_experience_answer_value($answers, 'clinical_photo_consent'),
            'Marketing' => (string)patient_experience_answer_value($answers, 'marketing_photo_consent'),
            'Social media' => (string)patient_experience_answer_value($answers, 'social_media_consent'),
            'Website' => (string)patient_experience_answer_value($answers, 'website_consent'),
            'Educational' => (string)patient_experience_answer_value($answers, 'educational_consent'),
            'Printed marketing' => (string)patient_experience_answer_value($answers, 'printed_marketing_consent'),
        ];

        return [
            'session' => $session,
            'answers' => $answers,
            'progress' => $progress,
            'patient_summary' => $patientSummary,
            'medical_alerts' => array_values(array_filter([
                patient_experience_answer_label(patient_experience_answer_value($answers, 'medical_conditions')),
                patient_experience_answer_label(patient_experience_answer_value($answers, 'allergies')),
                patient_experience_answer_label(patient_experience_answer_value($answers, 'medications')),
                (string)patient_experience_answer_value($answers, 'pregnancy_follow_up'),
            ], static fn(string $item): bool => trim($item) !== '')),
            'medications' => (array)(patient_experience_answer_value($answers, 'medications') ?: []),
            'allergies' => (array)(patient_experience_answer_value($answers, 'allergies') ?: []),
            'insurance' => [
                'Has insurance' => (string)patient_experience_answer_value($answers, 'has_insurance'),
                'Provider' => (string)patient_experience_answer_value($answers, 'insurance_information_provider'),
                'Subscriber' => (string)patient_experience_answer_value($answers, 'insurance_information_subscriber_name'),
                'Member ID' => (string)patient_experience_answer_value($answers, 'insurance_information_member_id'),
                'Group number' => (string)patient_experience_answer_value($answers, 'insurance_information_group_number'),
            ],
            'interested_services' => (array)(patient_experience_answer_value($answers, 'interested_services') ?: []),
            'financing_interest' => (string)patient_experience_answer_value($answers, 'financing_interest'),
            'treatment_goals' => [
                'Smile goals' => (string)patient_experience_answer_value($answers, 'smile_goals'),
                'Timeframe' => (string)patient_experience_answer_value($answers, 'treatment_timeframe'),
                'Financing notes' => (string)patient_experience_answer_value($answers, 'financing_notes'),
            ],
            'missing_fields' => array_values(array_unique(array_filter($missingFields))),
            'missing_signatures' => array_values(array_unique(array_filter($missingSignatures))),
            'consent_status' => [
                'Consent to proceed' => (string)patient_experience_answer_value($answers, 'consent_to_proceed_ack'),
                'HIPAA' => (string)patient_experience_answer_value($answers, 'hipaa_acknowledged'),
                'Financial policy' => (string)patient_experience_answer_value($answers, 'financial_policy_ack'),
                'No recording' => (string)patient_experience_answer_value($answers, 'no_recording_acknowledged'),
                'Final review' => (string)patient_experience_answer_value($answers, 'final_review_ack'),
            ],
            'photo_permissions' => $photoPermissions,
            'audit_timeline' => patient_experience_session_audit_timeline($sessionId),
            'signed_packet' => patient_experience_signed_packet_for_session($sessionId),
        ];
    }
}

if (!function_exists('patient_experience_mark_reviewed')) {
    function patient_experience_mark_reviewed(int $sessionId, int $userId, string $staffNotes): bool
    {
        $session = patient_experience_session_by_id($sessionId);
        if (!$session) {
            return false;
        }
        db_execute(
            "UPDATE patient_experience_checkin_sessions
             SET review_status = 'reviewed',
                 reviewed_at = NOW(),
                 reviewed_by_user_id = :user_id,
                 staff_notes = :staff_notes,
                 updated_at = NOW()
             WHERE id = :id",
            [
                'id' => $sessionId,
                'user_id' => $userId,
                'staff_notes' => trim($staffNotes),
            ]
        );
        patient_experience_audit('staff_reviewed', ['staff_notes' => trim($staffNotes)], $sessionId, (int)($session['lead_id'] ?? 0) ?: null, $userId);
        return true;
    }
}

if (!function_exists('patient_experience_empty_answer')) {
    function patient_experience_empty_answer(mixed $value): bool
    {
        if (is_array($value)) {
            return count(array_filter($value, static fn($item): bool => trim((string)$item) !== '')) === 0;
        }
        return trim((string)$value) === '';
    }
}

if (!function_exists('patient_experience_answer_label')) {
    function patient_experience_answer_label(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }
        return trim((string)$value);
    }
}

if (!function_exists('patient_experience_upsert_answer')) {
    function patient_experience_upsert_answer(int $sessionId, array $section, array $field, mixed $value): void
    {
        $fieldKey = (string)$field['key'];
        $existing = db_value(
            'SELECT id FROM patient_experience_packet_answers WHERE checkin_session_id = :session_id AND field_key = :field_key LIMIT 1',
            ['session_id' => $sessionId, 'field_key' => $fieldKey]
        );
        $payload = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $params = [
            'session_id' => $sessionId,
            'packet_section_id' => (int)$section['id'],
            'template_version_id' => (int)$section['template_version_id'],
            'field_key' => $fieldKey,
            'answer_json' => $payload,
            'answer_label' => patient_experience_answer_label($value),
            'is_sensitive' => !empty($field['sensitive']) ? 1 : 0,
        ];

        if ($existing) {
            $params['id'] = (int)$existing;
            db_execute(
                'UPDATE patient_experience_packet_answers
                 SET packet_section_id = :packet_section_id,
                     template_version_id = :template_version_id,
                     answer_json = :answer_json,
                     answer_label = :answer_label,
                     is_sensitive = :is_sensitive,
                     answered_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id',
                $params
            );
            return;
        }

        db_insert(
            'INSERT INTO patient_experience_packet_answers (checkin_session_id, packet_section_id, template_version_id, field_key, answer_json, answer_label, is_sensitive, answered_at, created_at)
             VALUES (:session_id, :packet_section_id, :template_version_id, :field_key, :answer_json, :answer_label, :is_sensitive, NOW(), NOW())',
            $params
        );
    }
}

if (!function_exists('patient_experience_kiosk_poll')) {
    function patient_experience_kiosk_poll(?string $deviceToken = null): array
    {
        patient_experience_ensure_schema();
        $token = trim((string)$deviceToken);
        if ($token === '') {
            return [
                'ok' => true,
                'state' => 'setup_required',
                'message' => 'This iPad is not registered yet.',
                'clear_device' => false,
            ];
        }

        $device = patient_experience_find_device_by_token($token);
        if (!$device) {
            return [
                'ok' => true,
                'state' => 'setup_required',
                'message' => 'This iPad needs to be set up again.',
                'clear_device' => true,
            ];
        }
        $deviceId = (int)$device['id'];
        patient_experience_touch_device($deviceId);

        $session = db_one(
            "SELECT id, status, expires_at, patient_name, current_step_key, progress_percent, session_token_hash
             FROM patient_experience_checkin_sessions
             WHERE status IN ('waiting', 'in_progress')
               AND expires_at > NOW()
               AND kiosk_device_id = :device_id
             ORDER BY created_at DESC, id DESC
             LIMIT 1",
            ['device_id' => $deviceId]
        );

        if (!$session) {
            return [
                'ok' => true,
                'state' => 'idle',
                'message' => 'No active check-in session.',
                'device' => [
                    'id' => $deviceId,
                    'label' => (string)($device['device_label'] ?? 'Waiting Room iPad'),
                    'location_label' => (string)($device['location_label'] ?? 'Front Desk'),
                    'last_seen_at' => (string)($device['last_seen_at'] ?? ''),
                ],
            ];
        }

        return [
            'ok' => true,
            'state' => 'assigned',
            'device' => [
                'id' => $deviceId,
                'label' => (string)($device['device_label'] ?? 'Waiting Room iPad'),
                'location_label' => (string)($device['location_label'] ?? 'Front Desk'),
                'last_seen_at' => (string)($device['last_seen_at'] ?? ''),
            ],
            'session' => [
                'id' => (int)$session['id'],
                'display_name' => trim((string)($session['patient_name'] ?? '')) ?: 'Patient',
                'status' => (string)$session['status'],
                'kiosk_token' => (string)$session['session_token_hash'],
                'current_step' => (string)($session['current_step_key'] ?? 'welcome'),
                'percent_complete' => (int)($session['progress_percent'] ?? 0),
                'expires_at' => (string)$session['expires_at'],
            ],
        ];
    }
}
