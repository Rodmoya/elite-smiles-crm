<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/leads/lead_communications.php
 *
 * Structured SMS message and activity helpers.
 */

require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once __DIR__ . '/lead_service.php';

if (!function_exists('lead_comm_table_exists')) {
    function lead_comm_table_exists(string $table): bool
    {
        try {
            return (bool) db_value(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
                ['table' => $table]
            );
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('lead_comm_column_exists')) {
    function lead_comm_column_exists(string $table, string $column): bool
    {
        try {
            return (bool) db_value(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
                ['table' => $table, 'column' => $column]
            );
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('lead_comm_add_leads_column')) {
    function lead_comm_add_leads_column(string $column, string $definition): void
    {
        if (!function_exists('leads_table_exists') || !leads_table_exists()) {
            return;
        }

        if (lead_comm_column_exists('leads', $column)) {
            return;
        }

        try {
            db_query("ALTER TABLE leads ADD COLUMN {$column} {$definition}");
        } catch (Throwable $e) {
            esm_log('lead_communications', 'Could not add leads column', [
                'column' => $column,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

if (!function_exists('lead_comm_ensure_schema')) {
    function lead_comm_ensure_schema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        try {
            db_query("
                CREATE TABLE IF NOT EXISTS lead_activities (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    lead_id INT UNSIGNED NOT NULL,
                    type VARCHAR(50) NOT NULL DEFAULT 'note',
                    body TEXT NOT NULL,
                    meta_json LONGTEXT NULL,
                    created_by VARCHAR(190) NOT NULL DEFAULT '',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_lead_created (lead_id, created_at),
                    KEY idx_type_created (type, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            db_query("
                CREATE TABLE IF NOT EXISTS lead_messages (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    lead_id INT UNSIGNED NOT NULL,
                    direction VARCHAR(20) NOT NULL DEFAULT 'outbound',
                    channel VARCHAR(20) NOT NULL DEFAULT 'sms',
                    from_number VARCHAR(50) NOT NULL DEFAULT '',
                    to_number VARCHAR(50) NOT NULL DEFAULT '',
                    body TEXT NOT NULL,
                    twilio_message_sid VARCHAR(100) NOT NULL DEFAULT '',
                    twilio_status VARCHAR(50) NOT NULL DEFAULT '',
                    twilio_error_code VARCHAR(50) NOT NULL DEFAULT '',
                    twilio_error_message TEXT NULL,
                    is_read TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    delivered_at DATETIME NULL,
                    read_at DATETIME NULL,
                    PRIMARY KEY (id),
                    KEY idx_lead_created (lead_id, created_at),
                    KEY idx_sid (twilio_message_sid),
                    KEY idx_unread (lead_id, direction, is_read)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {
            esm_log('lead_communications', 'Could not ensure communication tables', [
                'error' => $e->getMessage(),
            ]);
            return;
        }

        lead_comm_add_leads_column('sms_opt_status', "VARCHAR(30) NOT NULL DEFAULT 'unknown'");
        lead_comm_add_leads_column('sms_opted_out_at', 'DATETIME NULL');
        lead_comm_add_leads_column('last_contacted_at', 'DATETIME NULL');
        lead_comm_add_leads_column('last_inbound_at', 'DATETIME NULL');
        lead_comm_add_leads_column('last_outbound_at', 'DATETIME NULL');
        lead_comm_add_leads_column('unread_message_count', 'INT UNSIGNED NOT NULL DEFAULT 0');
        lead_comm_add_leads_column('next_follow_up_at', 'DATETIME NULL');
        lead_comm_add_leads_column('date_of_birth', 'DATE NULL');
        lead_comm_add_leads_column('scheduling_preferred_day', 'VARCHAR(120) NULL');
        lead_comm_add_leads_column('scheduling_preferred_time', 'VARCHAR(120) NULL');
        lead_comm_add_leads_column('follow_up_status', "VARCHAR(50) NOT NULL DEFAULT 'not_checked'");
        lead_comm_add_leads_column('last_follow_up_check_at', 'DATETIME NULL');
    }
}

if (!function_exists('lead_comm_normalize_phone')) {
    function lead_comm_normalize_phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+' . $digits;
        }
        if (str_starts_with(trim($phone), '+') && strlen($digits) >= 10) {
            return '+' . $digits;
        }
        return '';
    }
}

if (!function_exists('lead_comm_user_label')) {
    function lead_comm_user_label(): string
    {
        if (!function_exists('auth_user')) {
            return 'System';
        }

        $user = auth_user();
        $name = trim(((string)($user['first_name'] ?? '')) . ' ' . ((string)($user['last_name'] ?? '')));
        if ($name !== '') {
            return $name;
        }

        return trim((string)($user['email'] ?? 'System')) ?: 'System';
    }
}

if (!function_exists('lead_comm_insert_activity')) {
    function lead_comm_insert_activity(int $leadId, string $type, string $body, array $meta = [], string $createdBy = ''): int
    {
        lead_comm_ensure_schema();
        if ($leadId <= 0 || trim($body) === '') {
            return 0;
        }

        try {
            return db_insert(
                'INSERT INTO lead_activities (lead_id, type, body, meta_json, created_by, created_at)
                 VALUES (:lead_id, :type, :body, :meta_json, :created_by, :created_at)',
                [
                    'lead_id' => $leadId,
                    'type' => $type,
                    'body' => trim($body),
                    'meta_json' => $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                    'created_by' => $createdBy !== '' ? $createdBy : lead_comm_user_label(),
                    'created_at' => now(),
                ]
            );
        } catch (Throwable $e) {
            esm_log('lead_communications', 'Could not insert activity', [
                'lead_id' => $leadId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }
}

if (!function_exists('lead_comm_insert_message')) {
    function lead_comm_insert_message(array $message): int
    {
        lead_comm_ensure_schema();

        $leadId = (int)($message['lead_id'] ?? 0);
        $body = trim((string)($message['body'] ?? ''));
        if ($leadId <= 0 || $body === '') {
            return 0;
        }

        try {
            return db_insert(
                'INSERT INTO lead_messages (
                    lead_id, direction, channel, from_number, to_number, body,
                    twilio_message_sid, twilio_status, twilio_error_code,
                    twilio_error_message, is_read, created_at
                ) VALUES (
                    :lead_id, :direction, :channel, :from_number, :to_number, :body,
                    :twilio_message_sid, :twilio_status, :twilio_error_code,
                    :twilio_error_message, :is_read, :created_at
                )',
                [
                    'lead_id' => $leadId,
                    'direction' => (string)($message['direction'] ?? 'outbound'),
                    'channel' => (string)($message['channel'] ?? 'sms'),
                    'from_number' => lead_comm_normalize_phone((string)($message['from_number'] ?? '')) ?: (string)($message['from_number'] ?? ''),
                    'to_number' => lead_comm_normalize_phone((string)($message['to_number'] ?? '')) ?: (string)($message['to_number'] ?? ''),
                    'body' => $body,
                    'twilio_message_sid' => (string)($message['twilio_message_sid'] ?? ''),
                    'twilio_status' => (string)($message['twilio_status'] ?? ''),
                    'twilio_error_code' => (string)($message['twilio_error_code'] ?? ''),
                    'twilio_error_message' => ($message['twilio_error_message'] ?? null) !== null ? (string)$message['twilio_error_message'] : null,
                    'is_read' => (int)($message['is_read'] ?? 0),
                    'created_at' => (string)($message['created_at'] ?? now()),
                ]
            );
        } catch (Throwable $e) {
            esm_log('lead_communications', 'Could not insert message', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }
}

if (!function_exists('lead_comm_update_rollup')) {
    function lead_comm_update_rollup(int $leadId): void
    {
        lead_comm_ensure_schema();
        if ($leadId <= 0 || !function_exists('leads_has_column')) {
            return;
        }

        try {
            $unread = (int) db_value(
                "SELECT COUNT(*) FROM lead_messages WHERE lead_id = :lead_id AND direction = 'inbound' AND is_read = 0",
                ['lead_id' => $leadId]
            );
            $lastInbound = db_value(
                "SELECT MAX(created_at) FROM lead_messages WHERE lead_id = :lead_id AND direction = 'inbound'",
                ['lead_id' => $leadId]
            );
            $lastOutbound = db_value(
                "SELECT MAX(created_at) FROM lead_messages WHERE lead_id = :lead_id AND direction = 'outbound'",
                ['lead_id' => $leadId]
            );
            $lastContacted = db_value(
                "SELECT MAX(created_at) FROM lead_messages WHERE lead_id = :lead_id",
                ['lead_id' => $leadId]
            );

            $setParts = [];
            $params = ['id' => $leadId];
            foreach ([
                'unread_message_count' => $unread,
                'last_inbound_at' => $lastInbound ?: null,
                'last_outbound_at' => $lastOutbound ?: null,
                'last_contacted_at' => $lastContacted ?: null,
            ] as $column => $value) {
                if (leads_has_column($column)) {
                    $setParts[] = "{$column} = :{$column}";
                    $params[$column] = $value;
                }
            }
            if (leads_has_column('updated_at')) {
                $setParts[] = 'updated_at = :updated_at';
                $params['updated_at'] = now();
            }
            if ($setParts) {
                db_query('UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1', $params);
            }
        } catch (Throwable $e) {
            esm_log('lead_communications', 'Could not update message rollup', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

if (!function_exists('lead_comm_recent_messages')) {
    function lead_comm_recent_messages(int $leadId, int $limit = 50): array
    {
        lead_comm_ensure_schema();
        $limit = max(1, min(100, $limit));
        try {
            return db_all(
                "SELECT id, lead_id, direction, channel, from_number, to_number, body,
                        twilio_message_sid, twilio_status, is_read, created_at, delivered_at
                 FROM lead_messages
                 WHERE lead_id = :lead_id
                 ORDER BY created_at DESC, id DESC
                 LIMIT {$limit}",
                ['lead_id' => $leadId]
            );
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('lead_comm_recent_activities')) {
    function lead_comm_recent_activities(int $leadId, int $limit = 50): array
    {
        lead_comm_ensure_schema();
        $limit = max(1, min(100, $limit));
        try {
            return db_all(
                "SELECT id, lead_id, type, body, meta_json, created_by, created_at
                 FROM lead_activities
                 WHERE lead_id = :lead_id
                 ORDER BY created_at DESC, id DESC
                 LIMIT {$limit}",
                ['lead_id' => $leadId]
            );
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('lead_comm_snapshot')) {
    function lead_comm_snapshot(int $leadId): array
    {
        $messages = array_reverse(lead_comm_recent_messages($leadId, 50));
        $activities = lead_comm_recent_activities($leadId, 50);

        return [
            'messages' => $messages,
            'activities' => $activities,
        ];
    }
}

if (!function_exists('lead_comm_mark_read')) {
    function lead_comm_mark_read(int $leadId): void
    {
        lead_comm_ensure_schema();
        try {
            db_query(
                "UPDATE lead_messages
                 SET is_read = 1, read_at = COALESCE(read_at, :read_at)
                 WHERE lead_id = :lead_id AND direction = 'inbound' AND is_read = 0",
                ['lead_id' => $leadId, 'read_at' => now()]
            );
            lead_comm_update_rollup($leadId);
        } catch (Throwable $e) {
            esm_log('lead_communications', 'Could not mark messages read', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

if (!function_exists('lead_comm_opt_command')) {
    function lead_comm_opt_command(string $body): string
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $body) ?? ''));
        $first = strtok($normalized, ' ') ?: $normalized;

        if (in_array($first, ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'], true)) {
            return 'opt_out';
        }
        if (in_array($first, ['START', 'YES', 'UNSTOP'], true)) {
            return 'opt_in';
        }
        if ($first === 'HELP') {
            return 'help';
        }
        return '';
    }
}

if (!function_exists('lead_comm_find_lead_by_phone')) {
    function lead_comm_find_lead_by_phone(string $phone): ?array
    {
        $normalized = lead_comm_normalize_phone($phone);
        $digits = preg_replace('/\D+/', '', $normalized) ?? '';
        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;
        if ($last10 === '') {
            return null;
        }

        $phoneDigitsSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), '(', ''), ')', ''), '.', '')";
        try {
            return db_one(
                "SELECT * FROM leads
                 WHERE {$phoneDigitsSql} = :digits
                    OR {$phoneDigitsSql} = :digits_with_one
                    OR RIGHT({$phoneDigitsSql}, 10) = :last10
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 1",
                [
                    'digits' => $last10,
                    'digits_with_one' => '1' . $last10,
                    'last10' => $last10,
                ]
            );
        } catch (Throwable $e) {
            esm_log('lead_communications', 'Could not match inbound phone', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

if (!function_exists('lead_comm_create_inbound_lead')) {
    function lead_comm_create_inbound_lead(string $phone, string $body): ?array
    {
        if (!function_exists('leads_table_exists') || !leads_table_exists()) {
            return null;
        }

        $columns = [];
        $params = [];
        $values = [];
        $data = [
            'full_name' => 'Inbound SMS Lead',
            'phone' => lead_comm_normalize_phone($phone) ?: $phone,
            'source' => 'twilio_sms',
            'source_medium' => 'sms',
            'source_type' => 'inbound_sms',
            'status' => function_exists('lead_default_stage') ? lead_default_stage() : 'new_lead',
            'assigned_to' => 'Rod Moya',
            'notes' => 'Inbound SMS received before matching an existing lead: ' . mb_substr($body, 0, 240),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($data as $column => $value) {
            if (leads_has_column($column)) {
                $columns[] = $column;
                $values[] = ':' . $column;
                $params[$column] = $value;
            }
        }

        if (!$columns) {
            return null;
        }

        try {
            $id = db_insert(
                'INSERT INTO leads (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')',
                $params
            );
            return db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $id]);
        } catch (Throwable $e) {
            esm_log('lead_communications', 'Could not create inbound SMS lead', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

if (!function_exists('lead_comm_set_sms_opt_status')) {
    function lead_comm_set_sms_opt_status(int $leadId, string $status): void
    {
        lead_comm_ensure_schema();
        if (!function_exists('leads_has_column')) {
            return;
        }

        $setParts = [];
        $params = ['id' => $leadId];
        if (leads_has_column('sms_opt_status')) {
            $setParts[] = 'sms_opt_status = :sms_opt_status';
            $params['sms_opt_status'] = $status;
        }
        if (leads_has_column('sms_opted_out_at')) {
            $setParts[] = 'sms_opted_out_at = :sms_opted_out_at';
            $params['sms_opted_out_at'] = $status === 'opted_out' ? now() : null;
        }
        if (leads_has_column('updated_at')) {
            $setParts[] = 'updated_at = :updated_at';
            $params['updated_at'] = now();
        }
        if (!$setParts) {
            return;
        }

        try {
            db_query('UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1', $params);
        } catch (Throwable $e) {
            esm_log('lead_communications', 'Could not set SMS opt status', [
                'lead_id' => $leadId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
