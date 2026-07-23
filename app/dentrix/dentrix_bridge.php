<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Dentrix bridge support.
 *
 * CRM remains the source of truth for leads, pipeline, attribution, and follow-up.
 * Dentrix remains the source of truth for actual appointment placement.
 */

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../leads/lead_meta.php';
require_once __DIR__ . '/../leads/lead_service.php';
require_once __DIR__ . '/../leads/lead_communications.php';

if (!function_exists('dentrix_bridge_worker_url')) {
    function dentrix_bridge_worker_url(): string
    {
        return trim((string)(defined('ELITE_DENTRIX_WORKER_URL') ? ELITE_DENTRIX_WORKER_URL : ''));
    }
}

if (!function_exists('dentrix_bridge_secret')) {
    function dentrix_bridge_secret(): string
    {
        return trim((string)(defined('ELITE_DENTRIX_BRIDGE_SECRET') ? ELITE_DENTRIX_BRIDGE_SECRET : ''));
    }
}

if (!function_exists('dentrix_bridge_ensure_schema')) {
    function dentrix_bridge_ensure_schema(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        if (function_exists('lead_pipeline_ensure_schema')) {
            lead_pipeline_ensure_schema();
        }
        if (function_exists('lead_comm_ensure_schema')) {
            lead_comm_ensure_schema();
        }

        foreach ([
            'dentrix_sync_status' => "VARCHAR(40) NULL",
            'dentrix_patient_key' => "VARCHAR(190) NULL",
            'dentrix_appointment_key' => "VARCHAR(190) NULL",
            'last_dentrix_sync_at' => "DATETIME NULL",
            'appointment_source' => "VARCHAR(40) NULL",
            'occupied_slot_type' => "VARCHAR(40) NULL",
            'external_calendar_block' => "TINYINT(1) NOT NULL DEFAULT 0",
        ] as $column => $definition) {
            if (function_exists('leads_has_column') && leads_has_column($column)) {
                continue;
            }
            try {
                db_query("ALTER TABLE leads ADD COLUMN {$column} {$definition}");
                if (function_exists('leads_table_columns')) {
                    leads_table_columns(true);
                }
            } catch (Throwable $e) {
                esm_log('dentrix_bridge', 'Could not add lead bridge column.', [
                    'column' => $column,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        db_query("CREATE TABLE IF NOT EXISTS dentrix_bridge_jobs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            lead_id BIGINT UNSIGNED NULL,
            job_type VARCHAR(80) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'queued',
            idempotency_key VARCHAR(120) NOT NULL,
            external_job_key VARCHAR(190) NULL,
            payload_json MEDIUMTEXT NOT NULL,
            response_json MEDIUMTEXT NULL,
            attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            available_at DATETIME NOT NULL,
            sent_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_dentrix_job_idempotency (idempotency_key),
            KEY idx_dentrix_jobs_lead (lead_id),
            KEY idx_dentrix_jobs_status (status, available_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS dentrix_occupied_slots (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            dentrix_appointment_key VARCHAR(190) NULL,
            crm_lead_id BIGINT UNSIGNED NULL,
            appointment_source VARCHAR(40) NOT NULL DEFAULT 'dentrix',
            occupied_slot_type VARCHAR(40) NOT NULL DEFAULT 'external',
            external_calendar_block TINYINT(1) NOT NULL DEFAULT 1,
            start_at DATETIME NOT NULL,
            end_at DATETIME NOT NULL,
            provider VARCHAR(120) NULL,
            operatory VARCHAR(80) NULL,
            patient_name VARCHAR(190) NULL,
            title VARCHAR(190) NULL,
            raw_json MEDIUMTEXT NULL,
            last_seen_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_dentrix_slots_range (start_at, end_at),
            KEY idx_dentrix_slots_lead (crm_lead_id),
            KEY idx_dentrix_slots_appt (dentrix_appointment_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS dentrix_bridge_audit (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            lead_id BIGINT UNSIGNED NULL,
            job_id BIGINT UNSIGNED NULL,
            event_type VARCHAR(80) NOT NULL,
            message TEXT NOT NULL,
            payload_json MEDIUMTEXT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_dentrix_audit_lead (lead_id),
            KEY idx_dentrix_audit_event (event_type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $ready = true;
    }
}

if (!function_exists('dentrix_bridge_log')) {
    function dentrix_bridge_log(?int $leadId, ?int $jobId, string $eventType, string $message, array $payload = []): void
    {
        try {
            dentrix_bridge_ensure_schema();
            db_insert(
                'INSERT INTO dentrix_bridge_audit (lead_id, job_id, event_type, message, payload_json, created_at) VALUES (:lead_id, :job_id, :event_type, :message, :payload_json, :created_at)',
                [
                    'lead_id' => $leadId ?: null,
                    'job_id' => $jobId ?: null,
                    'event_type' => mb_substr($eventType, 0, 80),
                    'message' => $message,
                    'payload_json' => $payload ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                    'created_at' => now(),
                ]
            );
        } catch (Throwable $e) {
            esm_log('dentrix_bridge', 'Could not write bridge audit.', ['error' => $e->getMessage()]);
        }
    }
}

if (!function_exists('dentrix_bridge_split_name')) {
    function dentrix_bridge_split_name(string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName));
        if ($fullName === '') {
            return ['', ''];
        }
        $parts = explode(' ', $fullName);
        $first = (string)array_shift($parts);
        $last = trim(implode(' ', $parts));
        return [$first, $last];
    }
}

if (!function_exists('dentrix_bridge_schedule_payload')) {
    function dentrix_bridge_schedule_payload(array $lead, array $overrides = []): array
    {
        [$firstName, $lastName] = dentrix_bridge_split_name((string)($lead['full_name'] ?? ''));
        $consultationDate = trim((string)($overrides['consultation_date'] ?? $lead['consultation_date'] ?? ''));
        $start = $consultationDate !== '' ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $consultationDate)) ?: time()) : '';
        $provider = trim((string)($overrides['provider'] ?? $lead['assigned_to'] ?? 'Walter Meden (DDS1)'));
        if ($provider === '' || strtolower($provider) === 'rod' || strtolower($provider) === 'rodrigo') {
            $provider = 'Walter Meden (DDS1)';
        }
        $requestId = 'crm-job-' . (int)($lead['id'] ?? 0) . '-' . preg_replace('/[^0-9]/', '', $start);

        $payload = [
            'event' => 'schedule_or_update_consult',
            'request_id' => $requestId,
            'crm_lead_id' => (int)($lead['id'] ?? 0),
            'crm_appointment_id' => (int)($lead['id'] ?? 0),
            'requested_at' => date('c'),
            'patient' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'dob' => trim((string)($overrides['date_of_birth'] ?? $lead['date_of_birth'] ?? '')),
                'mobile_phone' => trim((string)($lead['phone'] ?? '')),
                'email' => strtolower(trim((string)($lead['email'] ?? ''))),
            ],
            'appointment' => [
                'date' => $start !== '' ? date('Y-m-d', strtotime($start)) : '',
                'start_time' => $start !== '' ? date('H:i:s', strtotime($start)) : '',
                'duration_minutes' => 30,
                'provider' => $provider,
                'operatory' => 'OP-3',
                'reason' => 'Veneers Consult',
                'procedure_code' => 'D0100',
                'scheduled_type' => 'Fixed',
                'appointment_type' => 'None',
                'notes' => 'consult veneers meta campaign by ROD',
            ],
            'dentrix_defaults' => [
                'referral_source' => 'Doctor/Other',
                'referred_by' => 'Meta Campaign',
            ],
            'existing_links' => [
                'dentrix_patient_key' => trim((string)($lead['dentrix_patient_key'] ?? '')) ?: null,
                'dentrix_appointment_key' => trim((string)($lead['dentrix_appointment_key'] ?? '')) ?: null,
            ],
            'campaign' => [
                'source' => trim((string)($lead['source'] ?? $lead['campaign'] ?? $lead['source_campaign'] ?? 'Meta Campaign')) ?: 'Meta Campaign',
                'campaign' => trim((string)($lead['campaign'] ?? $lead['source_campaign'] ?? '')),
                'landing_page' => trim((string)($lead['landing_page'] ?? '')),
                'consult_reason' => trim((string)($lead['procedure_interest'] ?? 'Veneers Consult')),
            ],
        ];

        $missing = [];
        if ($payload['patient']['first_name'] === '') $missing[] = 'patient first name';
        if ($payload['patient']['last_name'] === '') $missing[] = 'patient last name';
        if ($payload['patient']['dob'] === '') $missing[] = 'DOB';
        if ($payload['patient']['mobile_phone'] === '') $missing[] = 'mobile phone';
        if ($payload['appointment']['date'] === '' || $payload['appointment']['start_time'] === '') $missing[] = 'appointment date/time';
        if ($payload['appointment']['provider'] === '') $missing[] = 'provider';

        return [
            'ok' => $missing === [],
            'missing' => $missing,
            'payload' => $payload,
        ];
    }
}

if (!function_exists('dentrix_bridge_create_schedule_job')) {
    function dentrix_bridge_create_schedule_job(int $leadId, array $options = []): array
    {
        dentrix_bridge_ensure_schema();
        $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$lead) {
            return ['ok' => false, 'message' => 'Lead not found.', 'missing' => ['lead']];
        }

        $payloadResult = dentrix_bridge_schedule_payload($lead, $options);
        if (empty($payloadResult['ok'])) {
            db_execute('UPDATE leads SET dentrix_sync_status = :status, last_dentrix_sync_at = :synced_at WHERE id = :id LIMIT 1', [
                'id' => $leadId,
                'status' => 'missing_data',
                'synced_at' => now(),
            ]);
            dentrix_bridge_log($leadId, null, 'schedule_missing_data', 'Dentrix scheduling payload is missing required data.', $payloadResult);
            return $payloadResult + ['message' => 'Missing required Dentrix scheduling data.'];
        }

        $payload = $payloadResult['payload'];
        $idempotencyKey = (string)$payload['request_id'];
        $existing = db_one('SELECT * FROM dentrix_bridge_jobs WHERE idempotency_key = :key LIMIT 1', ['key' => $idempotencyKey]);
        if ($existing) {
            return ['ok' => true, 'message' => 'Dentrix scheduling job already exists.', 'job_id' => (int)$existing['id'], 'payload' => $payload];
        }

        $now = now();
        $jobId = db_insert(
            'INSERT INTO dentrix_bridge_jobs (lead_id, job_type, status, idempotency_key, payload_json, available_at, created_at, updated_at) VALUES (:lead_id, :job_type, :status, :idempotency_key, :payload_json, :available_at, :created_at, :updated_at)',
            [
                'lead_id' => $leadId,
                'job_type' => 'schedule_consult',
                'status' => 'pending_dispatch',
                'idempotency_key' => $idempotencyKey,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'available_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        db_execute('UPDATE leads SET dentrix_sync_status = :status, appointment_source = :source, last_dentrix_sync_at = :synced_at WHERE id = :id LIMIT 1', [
            'id' => $leadId,
            'status' => 'pending_dispatch',
            'source' => 'crm',
            'synced_at' => $now,
        ]);
        lead_comm_insert_activity($leadId, 'dentrix_schedule_queued', 'Dentrix scheduling request queued from CRM appointment fields.', [
            'job_id' => $jobId,
            'source' => 'dentrix_bridge',
        ], (string)($options['created_by'] ?? 'CRM'));
        dentrix_bridge_log($leadId, $jobId, 'schedule_queued', 'Dentrix scheduling request queued.', $payload);

        $dispatch = dentrix_bridge_dispatch_job($jobId);
        return ['ok' => true, 'message' => 'Dentrix scheduling request queued.', 'job_id' => $jobId, 'payload' => $payload, 'dispatch' => $dispatch];
    }
}

if (!function_exists('dentrix_bridge_dispatch_job')) {
    function dentrix_bridge_dispatch_job(int $jobId): array
    {
        $workerUrl = dentrix_bridge_worker_url();
        if ($workerUrl === '') {
            return ['ok' => true, 'status' => 'queued', 'message' => 'Dentrix worker URL is not configured; job remains queued.'];
        }

        $job = db_one('SELECT * FROM dentrix_bridge_jobs WHERE id = :id LIMIT 1', ['id' => $jobId]);
        if (!$job) {
            return ['ok' => false, 'message' => 'Dentrix job not found.'];
        }

        $payload = (string)($job['payload_json'] ?? '');
        $payloadArray = json_decode($payload, true);
        $eventName = is_array($payloadArray) ? trim((string)($payloadArray['event'] ?? '')) : '';
        if ($eventName === '') {
            $eventName = (string)($job['job_type'] ?? 'dentrix_job');
        }
        $path = (string)($job['job_type'] ?? '') === 'scan_calendar' ? '/requests/scan-calendar' : '/requests/schedule';
        $targetUrl = rtrim($workerUrl, '/') . $path;
        $headers = [
            'Content-Type: application/json',
            'X-Dentrix-Bridge-Event: ' . $eventName,
        ];
        $secret = dentrix_bridge_secret();
        if ($secret !== '') {
            $headers[] = 'X-Dentrix-Bridge-Secret: ' . $secret;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $payload,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($targetUrl, false, $context);
        $statusLine = (string)($http_response_header[0] ?? '');
        $isOk = preg_match('/\s2\d\d\s/', $statusLine) === 1;
        db_execute(
            'UPDATE dentrix_bridge_jobs SET status = :status, attempt_count = attempt_count + 1, response_json = :response_json, last_error = :last_error, sent_at = :sent_at, updated_at = :updated_at WHERE id = :id LIMIT 1',
            [
                'id' => $jobId,
                'status' => $isOk ? 'sent_to_worker' : 'dentrix_failed',
                'response_json' => $response !== false ? (string)$response : null,
                'last_error' => $isOk ? null : ($statusLine !== '' ? $statusLine : 'Worker request failed.'),
                'sent_at' => now(),
                'updated_at' => now(),
            ]
        );
        $leadId = (int)($job['lead_id'] ?? 0);
        if ($leadId > 0) {
            db_execute('UPDATE leads SET dentrix_sync_status = :status, last_dentrix_sync_at = :synced_at WHERE id = :id LIMIT 1', [
                'id' => $leadId,
                'status' => $isOk ? 'sent_to_worker' : 'dentrix_failed',
                'synced_at' => now(),
            ]);
        }

        return [
            'ok' => $isOk,
            'status' => $isOk ? 'sent_to_worker' : 'dentrix_failed',
            'target_url' => $targetUrl,
            'http_status' => $statusLine,
            'response' => $response !== false ? (string)$response : '',
        ];
    }
}

if (!function_exists('dentrix_bridge_dispatch_due_jobs')) {
    function dentrix_bridge_dispatch_due_jobs(int $limit = 10): array
    {
        dentrix_bridge_ensure_schema();
        $limit = max(1, min(50, $limit));
        $rows = db_all(
            'SELECT id FROM dentrix_bridge_jobs WHERE status IN ("queued", "pending_dispatch", "failed", "dentrix_failed") AND available_at <= :now AND attempt_count < 5 ORDER BY available_at ASC, id ASC LIMIT ' . $limit,
            ['now' => now()]
        );

        $results = [];
        foreach ($rows as $row) {
            $jobId = (int)($row['id'] ?? 0);
            if ($jobId <= 0) {
                continue;
            }
            $results[] = ['job_id' => $jobId] + dentrix_bridge_dispatch_job($jobId);
        }

        return [
            'ok' => true,
            'count' => count($results),
            'results' => $results,
        ];
    }
}

if (!function_exists('dentrix_bridge_create_scan_calendar_job')) {
    function dentrix_bridge_create_scan_calendar_job(?string $dateFrom = null, ?string $dateTo = null, array $options = []): array
    {
        dentrix_bridge_ensure_schema();
        $from = $dateFrom ? date('Y-m-d', strtotime($dateFrom) ?: time()) : date('Y-m-d');
        $to = $dateTo ? date('Y-m-d', strtotime($dateTo) ?: strtotime('+7 days')) : date('Y-m-d', strtotime($from . ' +6 days'));
        $requestId = 'scan-job-' . $from . '-' . $to . '-' . bin2hex(random_bytes(4));
        $payload = [
            'event' => 'scan_calendar',
            'request_id' => $requestId,
            'date_from' => $from,
            'date_to' => $to,
            'providers' => array_values($options['providers'] ?? ['Walter Meden (DDS1)']),
            'operatories' => array_values($options['operatories'] ?? ['OP-3', 'OP-4', 'OP-5']),
            'requested_at' => date('c'),
        ];

        $now = now();
        $jobId = db_insert(
            'INSERT INTO dentrix_bridge_jobs (lead_id, job_type, status, idempotency_key, payload_json, available_at, created_at, updated_at) VALUES (:lead_id, :job_type, :status, :idempotency_key, :payload_json, :available_at, :created_at, :updated_at)',
            [
                'lead_id' => null,
                'job_type' => 'scan_calendar',
                'status' => 'pending_dispatch',
                'idempotency_key' => $requestId,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'available_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        dentrix_bridge_log(null, $jobId, 'scan_calendar_queued', 'Dentrix occupied-slot scan request queued.', $payload);

        return [
            'ok' => true,
            'message' => 'Dentrix scan-calendar request queued.',
            'job_id' => $jobId,
            'payload' => $payload,
            'dispatch' => dentrix_bridge_dispatch_job($jobId),
        ];
    }
}

if (!function_exists('dentrix_bridge_upsert_slot')) {
    function dentrix_bridge_upsert_slot(array $slot): void
    {
        dentrix_bridge_ensure_schema();
        $appointmentKey = trim((string)($slot['dentrix_appointment_key'] ?? $slot['appointment_key'] ?? $slot['dentrix_appointment']['dentrix_appointment_key'] ?? ''));
        $leadId = (int)($slot['crm_lead_id'] ?? $slot['lead_id'] ?? 0);
        $startAt = trim((string)($slot['start_at'] ?? ''));
        $endAt = trim((string)($slot['end_at'] ?? $slot['end_time'] ?? ''));
        if ($startAt === '' && !empty($slot['date']) && !empty($slot['start_time'])) {
            $startAt = trim((string)$slot['date']) . ' ' . trim((string)$slot['start_time']);
        }
        if ($endAt !== '' && !str_contains($endAt, '-') && !empty($slot['date'])) {
            $endAt = trim((string)$slot['date']) . ' ' . $endAt;
        }
        if ($startAt === '') {
            return;
        }
        $startAt = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $startAt)) ?: time());
        $endAt = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $endAt)) ?: (strtotime($startAt) + 1800));

        $existing = null;
        if ($appointmentKey !== '') {
            $existing = db_one('SELECT id FROM dentrix_occupied_slots WHERE dentrix_appointment_key = :key LIMIT 1', ['key' => $appointmentKey]);
        }
        if (!$existing && $leadId > 0) {
            $existing = db_one('SELECT id FROM dentrix_occupied_slots WHERE crm_lead_id = :lead_id AND start_at = :start_at LIMIT 1', ['lead_id' => $leadId, 'start_at' => $startAt]);
        }

        $params = [
            'dentrix_appointment_key' => $appointmentKey !== '' ? $appointmentKey : null,
            'crm_lead_id' => $leadId > 0 ? $leadId : null,
            'appointment_source' => ($leadId > 0 || !empty($slot['is_crm_linked'])) ? 'crm_dentrix' : 'dentrix_external',
            'occupied_slot_type' => ($leadId > 0 || !empty($slot['is_crm_linked'])) ? 'crm_lead' : 'external',
            'external_calendar_block' => ($leadId > 0 || !empty($slot['is_crm_linked'])) ? 0 : 1,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'provider' => trim((string)($slot['provider'] ?? $slot['dentrix_appointment']['provider'] ?? '')) ?: null,
            'operatory' => trim((string)($slot['operatory'] ?? $slot['dentrix_appointment']['operatory'] ?? '')) ?: null,
            'patient_name' => trim((string)($slot['patient_name'] ?? $slot['name'] ?? '')) ?: null,
            'title' => trim((string)($slot['title'] ?? $slot['appointment_type'] ?? '')) ?: null,
            'raw_json' => json_encode($slot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'last_seen_at' => now(),
            'updated_at' => now(),
        ];

        if ($existing) {
            $params['id'] = (int)$existing['id'];
            db_execute(
                'UPDATE dentrix_occupied_slots SET dentrix_appointment_key = :dentrix_appointment_key, crm_lead_id = :crm_lead_id, appointment_source = :appointment_source, occupied_slot_type = :occupied_slot_type, external_calendar_block = :external_calendar_block, start_at = :start_at, end_at = :end_at, provider = :provider, operatory = :operatory, patient_name = :patient_name, title = :title, raw_json = :raw_json, last_seen_at = :last_seen_at, updated_at = :updated_at WHERE id = :id LIMIT 1',
                $params
            );
            return;
        }

        $params['created_at'] = now();
        db_insert(
            'INSERT INTO dentrix_occupied_slots (dentrix_appointment_key, crm_lead_id, appointment_source, occupied_slot_type, external_calendar_block, start_at, end_at, provider, operatory, patient_name, title, raw_json, last_seen_at, created_at, updated_at) VALUES (:dentrix_appointment_key, :crm_lead_id, :appointment_source, :occupied_slot_type, :external_calendar_block, :start_at, :end_at, :provider, :operatory, :patient_name, :title, :raw_json, :last_seen_at, :created_at, :updated_at)',
            $params
        );
    }
}

if (!function_exists('dentrix_bridge_apply_result')) {
    function dentrix_bridge_apply_result(array $payload): array
    {
        dentrix_bridge_ensure_schema();
        $event = trim((string)($payload['event'] ?? $payload['type'] ?? ''));
        $requestId = trim((string)($payload['request_id'] ?? ''));
        $providedLeadId = (int)($payload['crm_lead_id'] ?? $payload['lead_id'] ?? 0);
        $crmAppointmentId = (int)($payload['crm_appointment_id'] ?? $payload['appointment_id'] ?? 0);
        $leadId = 0;
        $appointment = is_array($payload['appointment'] ?? null)
            ? $payload['appointment']
            : (is_array($payload['dentrix_appointment'] ?? null) ? $payload['dentrix_appointment'] : $payload);
        $patient = is_array($payload['dentrix_patient'] ?? null) ? $payload['dentrix_patient'] : [];
        $patientKey = trim((string)($payload['dentrix_patient_key'] ?? $payload['patient_key'] ?? $patient['dentrix_patient_key'] ?? $appointment['patient_key'] ?? ''));
        $appointmentKey = trim((string)($payload['dentrix_appointment_key'] ?? $payload['appointment_key'] ?? $appointment['dentrix_appointment_key'] ?? $appointment['appointment_key'] ?? ''));
        $startAt = trim((string)($appointment['start_at'] ?? $payload['start_at'] ?? ''));
        if ($startAt === '' && !empty($appointment['date']) && !empty($appointment['start_time'])) {
            $startAt = trim((string)$appointment['date']) . ' ' . trim((string)$appointment['start_time']);
        }
        if ($event === 'appointment_moved' && !empty($payload['new_date']) && !empty($payload['new_start_time'])) {
            $startAt = trim((string)$payload['new_date']) . ' ' . trim((string)$payload['new_start_time']);
        }

        if (in_array($event, ['occupied_slots', 'slots'], true)) {
            $slots = is_array($payload['slots'] ?? null) ? $payload['slots'] : [];
            foreach ($slots as $slot) {
                if (is_array($slot)) {
                    dentrix_bridge_upsert_slot($slot);
                }
            }
            dentrix_bridge_log(null, null, 'occupied_slots_imported', 'Dentrix occupied slots imported.', ['count' => count($slots)]);
            return ['ok' => true, 'message' => 'Occupied slots imported.', 'count' => count($slots)];
        }

        // Match callbacks conservatively: Dentrix appointment, CRM appointment, request, lead/date, then patient/date.
        if ($appointmentKey !== '') {
            $leadId = (int)db_value('SELECT id FROM leads WHERE dentrix_appointment_key = :key LIMIT 1', ['key' => $appointmentKey]);
        }
        if ($leadId <= 0 && $crmAppointmentId > 0) {
            $leadId = (int)db_value('SELECT id FROM leads WHERE id = :id LIMIT 1', ['id' => $crmAppointmentId]);
        }
        $job = null;
        if ($requestId !== '') {
            $job = db_one('SELECT id, lead_id FROM dentrix_bridge_jobs WHERE idempotency_key = :request_id LIMIT 1', ['request_id' => $requestId]);
            if ($leadId <= 0 && $job) {
                $leadId = (int)($job['lead_id'] ?? 0);
            }
        }
        if ($leadId <= 0 && $providedLeadId > 0) {
            $params = ['id' => $providedLeadId];
            $dateClause = '';
            if ($startAt !== '') {
                $params['consultation_date'] = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $startAt)) ?: time());
                $dateClause = ' AND consultation_date = :consultation_date';
            }
            $leadId = (int)db_value('SELECT id FROM leads WHERE id = :id' . $dateClause . ' LIMIT 1', $params);
            if ($leadId <= 0) {
                $leadId = (int)db_value('SELECT id FROM leads WHERE id = :id LIMIT 1', ['id' => $providedLeadId]);
            }
        }
        if ($leadId <= 0 && $startAt !== '') {
            $patientName = trim((string)($payload['patient_name'] ?? $appointment['patient_name'] ?? ''));
            $patientDob = trim((string)($payload['dob'] ?? $payload['date_of_birth'] ?? $patient['dob'] ?? ''));
            if ($patientName !== '' && $patientDob !== '') {
                $leadId = (int)db_value(
                    'SELECT id FROM leads WHERE full_name = :full_name AND date_of_birth = :dob AND consultation_date = :consultation_date ORDER BY updated_at DESC LIMIT 1',
                    [
                        'full_name' => $patientName,
                        'dob' => date('Y-m-d', strtotime($patientDob) ?: time()),
                        'consultation_date' => date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $startAt)) ?: time()),
                    ]
                );
            }
        }

        $updates = [];
        $status = 'synced';
        $activityType = 'dentrix_sync';
        $activityBody = 'Dentrix bridge result received.';

        if ($patientKey !== '') $updates['dentrix_patient_key'] = $patientKey;
        if ($appointmentKey !== '') $updates['dentrix_appointment_key'] = $appointmentKey;
        $updates['last_dentrix_sync_at'] = now();
        $updates['appointment_source'] = 'dentrix';
        $updates['external_calendar_block'] = 0;

        if ($event === 'scheduling_result' && (string)($payload['status'] ?? '') !== 'success') {
            $event = 'sync_failure';
        }

        if (in_array($event, ['patient_found', 'patient_created'], true)) {
            $status = $event;
            $activityType = 'dentrix_patient_linked';
            $activityBody = 'Dentrix patient ' . ($event === 'patient_created' ? 'created' : 'found') . ' and linked to CRM lead.';
        } elseif (in_array($event, ['scheduling_result', 'appointment_created', 'appointment_moved'], true)) {
            $status = $event === 'appointment_moved' ? 'dentrix_moved' : (trim((string)($payload['sync_status'] ?? '')) ?: 'dentrix_confirmed');
            $updates['consultation_status'] = 'scheduled';
            if ($startAt !== '') {
                $updates['consultation_date'] = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $startAt)) ?: time());
            }
            $updates['status'] = 'consultation_booked';
            $activityType = $event === 'appointment_moved' ? 'dentrix_appointment_moved' : 'dentrix_appointment_created';
            $activityBody = $event !== 'appointment_moved'
                ? 'Dentrix appointment created and CRM calendar linked.'
                : 'Dentrix appointment moved; CRM appointment time updated from Dentrix.';
            dentrix_bridge_upsert_slot([
                'dentrix_appointment_key' => $appointmentKey,
                'crm_lead_id' => $leadId,
                'start_at' => $updates['consultation_date'] ?? $startAt,
                'end_at' => trim((string)($appointment['end_at'] ?? $appointment['end_time'] ?? $payload['new_end_time'] ?? $payload['end_at'] ?? '')),
                'provider' => $appointment['provider'] ?? '',
                'operatory' => $appointment['operatory'] ?? '',
                'patient_name' => $appointment['patient_name'] ?? $payload['patient_name'] ?? '',
                'title' => 'CRM lead consult',
            ]);
        } elseif (in_array($event, ['no_show', 'appointment_no_show'], true)) {
            $status = 'dentrix_no_show';
            $updates['consultation_status'] = 'no_show';
            $updates['status'] = 'no_show_reschedule';
            $activityType = 'dentrix_no_show';
            $activityBody = 'Dentrix reported no-show; CRM moved to no-show/reschedule workflow.';
        } elseif (in_array($event, ['completed_paid', 'completed', 'appointment_completed', 'appointment_completed_paid'], true)) {
            $status = in_array($event, ['completed_paid', 'appointment_completed_paid'], true) ? 'dentrix_completed_paid' : 'dentrix_completed';
            $updates['consultation_status'] = 'completed';
            $updates['status'] = in_array($event, ['completed_paid', 'appointment_completed_paid'], true)
                ? 'treatment_completed'
                : 'consult_completed';
            $activityType = in_array($event, ['completed_paid', 'appointment_completed_paid'], true) ? 'dentrix_completed_paid' : 'dentrix_completed';
            $activityBody = in_array($event, ['completed_paid', 'appointment_completed_paid'], true)
                ? 'Dentrix reported treatment completed and paid; CRM moved to completed/paid tracking.'
                : 'Dentrix reported consultation completed.';
        } elseif (in_array($event, ['sync_failed', 'sync_failure'], true)) {
            $status = 'dentrix_failed';
            $activityType = 'dentrix_sync_failed';
            $activityBody = 'Dentrix sync failed: ' . trim((string)($payload['message'] ?? $payload['error'] ?? 'Unknown error'));
        }

        $updates['dentrix_sync_status'] = $status;

        if ($leadId > 0 && $updates) {
            $setParts = [];
            $params = ['id' => $leadId];
            foreach ($updates as $field => $value) {
                if (!function_exists('leads_has_column') || !leads_has_column($field)) {
                    continue;
                }
                $placeholder = 'p_' . $field;
                $setParts[] = '`' . $field . '` = :' . $placeholder;
                $params[$placeholder] = $value;
            }
            if ($setParts) {
                $setParts[] = 'updated_at = :updated_at';
                $params['updated_at'] = now();
                db_execute('UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1', $params);
            }
            lead_comm_insert_activity($leadId, $activityType, $activityBody, [
                'source' => 'dentrix_bridge',
                'event' => $event,
                'payload' => $payload,
            ], 'Dentrix Bridge');
        }

        if ($job) {
            db_execute(
                'UPDATE dentrix_bridge_jobs SET status = :status, response_json = :response_json, completed_at = :completed_at, updated_at = :updated_at WHERE id = :id LIMIT 1',
                [
                    'id' => (int)$job['id'],
                    'status' => $updates['dentrix_sync_status'] ?? 'synced',
                    'response_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        dentrix_bridge_log($leadId > 0 ? $leadId : null, null, $event !== '' ? $event : 'result', $activityBody, $payload);
        return ['ok' => true, 'message' => 'Dentrix bridge result applied.', 'lead_id' => $leadId, 'event' => $event, 'updates' => $updates];
    }
}

if (!function_exists('dentrix_bridge_calendar_slots')) {
    function dentrix_bridge_calendar_slots(?string $from = null, ?string $to = null): array
    {
        dentrix_bridge_ensure_schema();
        $from = $from ?: date('Y-m-d 00:00:00', strtotime('-30 days'));
        $to = $to ?: date('Y-m-d 23:59:59', strtotime('+90 days'));
        $rows = db_all(
            'SELECT * FROM dentrix_occupied_slots WHERE end_at >= :from_date AND start_at <= :to_date ORDER BY start_at ASC LIMIT 1000',
            ['from_date' => $from, 'to_date' => $to]
        );
        return array_map(static function (array $row): array {
            return [
                'id' => (int)($row['id'] ?? 0),
                'leadId' => (string)($row['crm_lead_id'] ?? ''),
                'dentrixAppointmentKey' => (string)($row['dentrix_appointment_key'] ?? ''),
                'startAt' => (string)($row['start_at'] ?? ''),
                'endAt' => (string)($row['end_at'] ?? ''),
                'provider' => (string)($row['provider'] ?? ''),
                'operatory' => (string)($row['operatory'] ?? ''),
                'patientName' => (string)($row['patient_name'] ?? ''),
                'title' => (string)($row['title'] ?? ''),
                'source' => (string)($row['appointment_source'] ?? 'dentrix'),
                'slotType' => (string)($row['occupied_slot_type'] ?? 'external'),
                'externalBlock' => (bool)($row['external_calendar_block'] ?? true),
            ];
        }, $rows);
    }
}
