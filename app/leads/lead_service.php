<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/leads/lead_service.php
 *
 * Lead service layer:
 * - lead table checks
 * - safe column helpers
 * - build dashboard stage map
 * - always show only the marketing pipeline columns
 * - fetch stats / rows / recent leads
 * - create minimal lead record
 * - default opportunity value support
 * - shared intake support for landing pages, website, Google, Meta, and future sources
 */

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/mailer.php';
require_once __DIR__ . '/lead_meta.php';
require_once __DIR__ . '/lead_email.php';

if (!function_exists('lead_default_opportunity_value')) {
    function lead_default_opportunity_value(): float
    {
        return 15000.00;
    }
}

if (!function_exists('lead_money_value')) {
    function lead_money_value($value): float
    {
        if ($value === null || $value === '') {
            return lead_default_opportunity_value();
        }

        if (!is_numeric($value)) {
            return lead_default_opportunity_value();
        }

        $amount = round((float)$value, 2);

        // Existing leads created before July 2026 used $10k as the default.
        // Treat that legacy default as the current average unless a custom value is set.
        if ($amount === 10000.00) {
            return lead_default_opportunity_value();
        }

        return $amount;
    }
}

if (!function_exists('leads_table_exists')) {
    function leads_table_exists(): bool
    {
        try {
            return (bool) db_value("SHOW TABLES LIKE 'leads'");
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('lead_related_table_exists')) {
    function lead_related_table_exists(string $table): bool
    {
        static $cache = [];

        $table = trim($table);
        if ($table === '') {
            return false;
        }

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $cache[$table] = (bool) db_value("SHOW TABLES LIKE :table_name", ['table_name' => $table]);
        } catch (Throwable $e) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }
}

if (!function_exists('leads_table_columns')) {
    function leads_table_columns(bool $refresh = false): array
    {
        static $columns = null;

        if ($refresh) {
            $columns = null;
        }

        if ($columns !== null) {
            return $columns;
        }

        $columns = [];

        if (!leads_table_exists()) {
            return $columns;
        }

        try {
            $rows = db_all("SHOW COLUMNS FROM leads");
            foreach ($rows as $row) {
                $field = (string)($row['Field'] ?? '');
                if ($field !== '') {
                    $columns[$field] = true;
                }
            }
        } catch (Throwable $e) {
            $columns = [];
        }

        return $columns;
    }
}

if (!function_exists('leads_has_column')) {
    function leads_has_column(string $column): bool
    {
        $columns = leads_table_columns();
        return isset($columns[$column]);
    }
}

if (!function_exists('lead_pipeline_position_column')) {
    function lead_pipeline_position_column(): string
    {
        return 'pipeline_position';
    }
}

if (!function_exists('lead_pipeline_ensure_status_values')) {
    function lead_pipeline_ensure_status_values(): void
    {
        static $done = false;

        if ($done) {
            return;
        }

        $done = true;

        if (!leads_table_exists() || !leads_has_column('status')) {
            return;
        }

        try {
            $column = db_one("SHOW COLUMNS FROM leads LIKE 'status'");
            $type = (string)($column['Type'] ?? $column['type'] ?? '');

            if (!preg_match('/^enum\((.*)\)$/i', $type)) {
                return;
            }

            preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $matches);
            $values = [];
            foreach (($matches[1] ?? []) as $rawValue) {
                $values[] = stripcslashes((string)$rawValue);
            }

            $changed = false;
            foreach (array_keys(lead_stage_labels()) as $stageKey) {
                if (!in_array($stageKey, $values, true)) {
                    $values[] = $stageKey;
                    $changed = true;
                }
            }

            if (!$changed || !$values) {
                return;
            }

            $quotedValues = array_map(
                static fn(string $value): string => "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'",
                $values
            );

            $nullable = strtoupper((string)($column['Null'] ?? $column['null'] ?? '')) === 'YES';
            $nullSql = $nullable ? 'NULL' : 'NOT NULL';
            $default = $column['Default'] ?? $column['default'] ?? null;
            $defaultSql = '';

            if ($default !== null) {
                $defaultSql = " DEFAULT '" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$default) . "'";
            } elseif ($nullable) {
                $defaultSql = ' DEFAULT NULL';
            }

            db_query('ALTER TABLE leads MODIFY COLUMN status ENUM(' . implode(',', $quotedValues) . ') ' . $nullSql . $defaultSql);
            leads_table_columns(true);
        } catch (Throwable $e) {
            if (function_exists('esm_log')) {
                esm_log('lead_pipeline', 'Could not ensure lead status enum values.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

if (!function_exists('lead_pipeline_ensure_schema')) {
    function lead_pipeline_ensure_schema(): void
    {
        static $done = false;

        if ($done) {
            return;
        }

        $done = true;

        if (!leads_table_exists()) {
            return;
        }

        $column = lead_pipeline_position_column();

        if (!leads_has_column($column)) {
            try {
                db_query("ALTER TABLE leads ADD COLUMN {$column} INT NOT NULL DEFAULT 0");
                leads_table_columns(true);
            } catch (Throwable $e) {
                if (function_exists('esm_log')) {
                    esm_log('lead_pipeline', 'Could not add pipeline position column.', [
                        'column' => $column,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        try {
            db_query("ALTER TABLE leads ADD INDEX idx_leads_status_pipeline_position (status, {$column}, updated_at, id)");
        } catch (Throwable $e) {
            // Index may already exist.
        }

        foreach ([
            'consultation_date' => 'DATETIME NULL DEFAULT NULL',
            'next_follow_up_at' => 'DATETIME NULL DEFAULT NULL',
            'date_of_birth' => 'DATE NULL DEFAULT NULL',
        ] as $dateColumn => $definition) {
            if (!leads_has_column($dateColumn)) {
                continue;
            }

            try {
                $existing = db_one("SHOW COLUMNS FROM leads LIKE :column_name", ['column_name' => $dateColumn]);
                $isNullable = strtoupper((string)($existing['Null'] ?? $existing['null'] ?? '')) === 'YES';
                $default = $existing['Default'] ?? $existing['default'] ?? null;
                if ($isNullable && ($default === null || $default === 'NULL')) {
                    continue;
                }
                db_query("ALTER TABLE leads MODIFY COLUMN {$dateColumn} {$definition}");
                leads_table_columns(true);
            } catch (Throwable $e) {
                if (function_exists('esm_log')) {
                    esm_log('lead_pipeline', 'Could not normalize nullable date column.', [
                        'column' => $dateColumn,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        lead_pipeline_ensure_status_values();
    }
}

if (!function_exists('lead_pipeline_next_position')) {
    function lead_pipeline_next_position(string $stageKey): int
    {
        lead_pipeline_ensure_schema();

        $stageKey = trim($stageKey);
        $column = lead_pipeline_position_column();

        if ($stageKey === '' || !leads_has_column('status') || !leads_has_column($column)) {
            return 0;
        }

        try {
            return (int) db_value(
                "SELECT COALESCE(MAX({$column}), 0) + 1 FROM leads WHERE status = :status" . lead_pipeline_visibility_sql('AND'),
                ['status' => $stageKey]
            );
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('lead_pipeline_save_stage_order')) {
    function lead_pipeline_save_stage_order(string $stageKey, array $orderedLeadIds): bool
    {
        lead_pipeline_ensure_schema();

        $stageKey = trim($stageKey);
        $column = lead_pipeline_position_column();

        if ($stageKey === '' || !leads_has_column('status') || !leads_has_column($column)) {
            return false;
        }

        $leadIds = [];
        foreach ($orderedLeadIds as $leadId) {
            $id = (int) $leadId;
            if ($id > 0 && !in_array($id, $leadIds, true)) {
                $leadIds[] = $id;
            }
        }

        if ($leadIds === []) {
            return false;
        }

        $position = count($leadIds);

        try {
            foreach ($leadIds as $leadId) {
                db_execute(
                    "UPDATE leads
                     SET {$column} = :pipeline_position
                     WHERE id = :id AND status = :status
                     LIMIT 1",
                    [
                        'pipeline_position' => $position,
                        'id' => $leadId,
                        'status' => $stageKey,
                    ]
                );
                $position--;
            }

            return true;
        } catch (Throwable $e) {
            if (function_exists('esm_log')) {
                esm_log('lead_pipeline', 'Could not save stage order.', [
                    'stage' => $stageKey,
                    'error' => $e->getMessage(),
                ]);
            }

            return false;
        }
    }
}

if (!function_exists('lead_db_status_value')) {
    function lead_db_status_value(array $lead): string
    {
        $status = trim((string)($lead['status'] ?? ''));
        return $status !== '' ? $status : '';
    }
}

if (!function_exists('lead_db_value')) {
    function lead_db_value(array $lead): float
    {
        return lead_money_value($lead['lead_value'] ?? null);
    }
}

if (!function_exists('lead_pipeline_visibility_sql')) {
    function lead_pipeline_visibility_sql(string $prefix = 'WHERE'): string
    {
        $clauses = [];

        if (leads_has_column('source_type')) {
            $clauses[] = "(source_type IS NULL OR source_type <> 'smile_design')";
        }

        if (leads_has_column('source')) {
            $clauses[] = "(source IS NULL OR source <> 'smile_design_intake')";
        }

        if (!$clauses) {
            return '';
        }

        return ' ' . trim($prefix) . ' ' . implode(' AND ', $clauses);
    }
}

if (!function_exists('lead_stage_map')) {
    function lead_stage_map(): array
    {
        $labels = lead_stage_labels();
        $preferred = lead_stage_order();
        $map = [];

        foreach ($preferred as $key) {
            $map[$key] = $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
        }

        if (leads_table_exists() && leads_has_column('status')) {
            try {
                $rows = db_all("
                    SELECT DISTINCT status
                    FROM leads
                    WHERE status IS NOT NULL AND status <> ''" . lead_pipeline_visibility_sql('AND') . "
                    ORDER BY status ASC
                ");

                foreach ($rows as $row) {
                    $value = trim((string)($row['status'] ?? ''));
                    if ($value === '') {
                        continue;
                    }

                    if (!isset($map[$value]) && isset($labels[$value])) {
                        $map[$value] = $labels[$value];
                    }
                }
            } catch (Throwable $e) {
                // keep preferred map only
            }
        }

        return $map;
    }
}

if (!function_exists('lead_stage_map_ordered')) {
    function lead_stage_map_ordered(): array
    {
        $map = lead_stage_map();
        $ordered = [];

        foreach (lead_stage_order() as $key) {
            if (isset($map[$key])) {
                $ordered[$key] = $map[$key];
            }
        }

        return $ordered;
    }
}

if (!function_exists('lead_pipeline_display_stage_map')) {
    function lead_pipeline_display_stage_map(): array
    {
        $labels = function_exists('lead_conversion_stage_labels') ? lead_conversion_stage_labels() : lead_stage_labels();
        $order = function_exists('lead_conversion_stage_order') ? lead_conversion_stage_order() : lead_stage_order();
        $map = [];

        foreach ($order as $key) {
            $map[$key] = $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
        }

        return $map;
    }
}

if (!function_exists('lead_dashboard_stats')) {
    function lead_dashboard_stats(): array
    {
        $stats = [
            'total_leads' => 0,
            'leads_today' => 0,
            'leads_week' => 0,
            'conversion_rate' => 0,
            'missed_leads' => 0,
            'pipeline_value_total' => 0.00,
            'closed_value_total' => 0.00,
            'lost_value_total' => 0.00,
            'avg_lead_value' => 0.00,
            'default_opportunity_value' => lead_default_opportunity_value(),
        ];

        if (!leads_table_exists()) {
            return $stats;
        }

        try {
            $stats['total_leads'] = (int) db_value("SELECT COUNT(*) FROM leads" . lead_pipeline_visibility_sql('WHERE'));

            if (leads_has_column('created_at')) {
                $stats['leads_today'] = (int) db_value("
                    SELECT COUNT(*)
                    FROM leads
                    WHERE DATE(created_at) = CURDATE()" . lead_pipeline_visibility_sql('AND') . "
                ");

                $stats['leads_week'] = (int) db_value("
                    SELECT COUNT(*)
                    FROM leads
                    WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)" . lead_pipeline_visibility_sql('AND') . "
                ");
            }

            if (leads_has_column('status')) {
                $stats['missed_leads'] = (int) db_value("
                    SELECT COUNT(*)
                    FROM leads
                    WHERE status = 'new_lead'" . lead_pipeline_visibility_sql('AND') . "
                ");

                $wonCount = (int) db_value("
                    SELECT COUNT(*)
                    FROM leads
                    WHERE status = 'treatment_accepted'" . lead_pipeline_visibility_sql('AND') . "
                ");

                if ($stats['total_leads'] > 0) {
                    $stats['conversion_rate'] = round(($wonCount / $stats['total_leads']) * 100, 1);
                }
            }

            $selectFields = ['id'];

            if (leads_has_column('status')) {
                $selectFields[] = 'status';
            }

            if (leads_has_column('lead_value')) {
                $selectFields[] = 'lead_value';
            }

            if (!empty($selectFields)) {
                $rows = db_all("
                    SELECT " . implode(', ', $selectFields) . "
                    FROM leads" . lead_pipeline_visibility_sql('WHERE') . "
                ");

                $totalValue = 0.00;
                $openValue = 0.00;
                $closedValue = 0.00;
                $lostValue = 0.00;

                foreach ($rows as $row) {
                    $value = lead_db_value($row);
                    $status = trim((string)($row['status'] ?? ''));

                    $totalValue += $value;

                    if ($status === 'treatment_accepted') {
                        $closedValue += $value;
                    } elseif ($status === 'lost_lead' || $status === 'opted_out') {
                        $lostValue += $value;
                    } else {
                        $openValue += $value;
                    }
                }

                $stats['pipeline_value_total'] = round($openValue, 2);
                $stats['closed_value_total'] = round($closedValue, 2);
                $stats['lost_value_total'] = round($lostValue, 2);

                if ($stats['total_leads'] > 0) {
                    $stats['avg_lead_value'] = round($totalValue / $stats['total_leads'], 2);
                }
            }
        } catch (Throwable $e) {
            return $stats;
        }

        return $stats;
    }
}

if (!function_exists('lead_pipeline_counts')) {
    function lead_pipeline_counts(): array
    {
        $counts = [];
        $stageMap = lead_pipeline_display_stage_map();

        foreach ($stageMap as $stageKey => $label) {
            $counts[$stageKey] = 0;
        }

        if (!leads_table_exists() || !leads_has_column('status')) {
            return $counts;
        }

        try {
            $selectFields = ['id', 'status'];
            foreach ([
                'phone',
                'sms_opt_status',
                'last_contacted_at',
                'last_inbound_at',
                'last_outbound_at',
                'unread_message_count',
                'next_follow_up_at',
                'consultation_status',
                'consultation_date',
                'date_of_birth',
                'created_at',
                'updated_at',
            ] as $field) {
                if (leads_has_column($field)) {
                    $selectFields[] = $field;
                }
            }

            $rows = db_all("
                SELECT " . implode(', ', $selectFields) . "
                FROM leads
                WHERE status IS NOT NULL AND status <> ''" . lead_pipeline_visibility_sql('AND') . "
            ");

            foreach ($rows as $row) {
                $stageKey = function_exists('lead_conversion_stage_key')
                    ? lead_conversion_stage_key($row)
                    : trim((string)($row['status'] ?? ''));

                if (isset($counts[$stageKey])) {
                    $counts[$stageKey]++;
                }
            }
        } catch (Throwable $e) {
            return $counts;
        }

        return $counts;
    }
}

if (!function_exists('lead_pipeline_stage_values')) {
    function lead_pipeline_stage_values(): array
    {
        $values = [];
        $stageMap = lead_pipeline_display_stage_map();

        foreach ($stageMap as $stageKey => $label) {
            $values[$stageKey] = 0.00;
        }

        if (!leads_table_exists() || !leads_has_column('status')) {
            return $values;
        }

        $valueExpr = leads_has_column('lead_value')
            ? 'COALESCE(lead_value, 0)'
            : (string) lead_default_opportunity_value();

        try {
            $selectFields = ['id', 'status', "{$valueExpr} AS lead_value"];
            foreach ([
                'phone',
                'sms_opt_status',
                'last_contacted_at',
                'last_inbound_at',
                'last_outbound_at',
                'unread_message_count',
                'next_follow_up_at',
                'consultation_status',
                'consultation_date',
                'date_of_birth',
                'created_at',
                'updated_at',
            ] as $field) {
                if (leads_has_column($field)) {
                    $selectFields[] = $field;
                }
            }

            $rows = db_all("
                SELECT " . implode(', ', $selectFields) . "
                FROM leads
                WHERE status IS NOT NULL AND status <> ''" . lead_pipeline_visibility_sql('AND') . "
            ");

            foreach ($rows as $row) {
                $stageKey = function_exists('lead_conversion_stage_key')
                    ? lead_conversion_stage_key($row)
                    : trim((string)($row['status'] ?? ''));
                if (isset($values[$stageKey])) {
                    $values[$stageKey] = round($values[$stageKey] + lead_db_value($row), 2);
                }
            }
        } catch (Throwable $e) {
            return $values;
        }

        return $values;
    }
}

if (!function_exists('lead_pipeline_rows')) {
    function lead_pipeline_rows(int $limit = 250): array
    {
        lead_pipeline_ensure_schema();

        $grouped = [];
        $stageMap = lead_pipeline_display_stage_map();

        foreach ($stageMap as $stageKey => $label) {
            $grouped[$stageKey] = [];
        }

        if (!leads_table_exists()) {
            return $grouped;
        }

        $selectFields = ['id'];

        foreach ([
            'full_name',
            'phone',
            'email',
            'preferred_contact',
            'procedure_interest',
            'source',
            'source_medium',
            'source_type',
            'landing_page',
            'campaign',
            'source_campaign',
            'source_ad_set',
            'source_ad_name',
            'source_post_id',
            'source_post_label',
            'external_lead_id',
            'instagram_username',
            'trigger_keyword',
            'status',
            'assigned_to',
            'financing_needed',
            'financing_option',
            'consultation_status',
            'consultation_date',
            'lead_value',
            'lost_reason',
            'notes',
            'sms_opt_status',
            'last_contacted_at',
            'last_inbound_at',
            'last_outbound_at',
            'unread_message_count',
            'next_follow_up_at',
            'date_of_birth',
            'intent_type',
            'scheduling_preferred_day',
            'scheduling_preferred_time',
            'follow_up_status',
            'last_follow_up_check_at',
            'dentrix_sync_status',
            'dentrix_patient_key',
            'dentrix_appointment_key',
            'last_dentrix_sync_at',
            'appointment_source',
            'occupied_slot_type',
            'external_calendar_block',
            'pipeline_position',
            'created_at',
            'updated_at'
        ] as $field) {
            if (leads_has_column($field)) {
                $selectFields[] = $field;
            }
        }

        $limit = max(1, min(1000, $limit));
        $orderBy = [];
        $actionDateFields = [];

        $latestActionSubqueries = [
            'latest_message_at' => lead_related_table_exists('lead_messages')
                ? "(SELECT MAX(lm.created_at) FROM lead_messages lm WHERE lm.lead_id = leads.id)"
                : '',
            'latest_activity_at' => lead_related_table_exists('lead_activities')
                ? "(SELECT MAX(la.created_at) FROM lead_activities la WHERE la.lead_id = leads.id)"
                : '',
            'latest_email_at' => lead_related_table_exists('lead_emails')
                ? "(SELECT MAX(le.created_at) FROM lead_emails le WHERE le.lead_id = leads.id)"
                : '',
        ];

        foreach ($latestActionSubqueries as $alias => $expression) {
            if ($expression === '') {
                continue;
            }
            $selectFields[] = "{$expression} AS {$alias}";
            $actionDateFields[] = "COALESCE({$expression}, '1970-01-01 00:00:00')";
        }

        foreach (['last_inbound_at', 'last_outbound_at', 'last_contacted_at', 'updated_at', 'created_at'] as $actionDateField) {
            if (leads_has_column($actionDateField)) {
                $actionDateFields[] = "COALESCE({$actionDateField}, '1970-01-01 00:00:00')";
            }
        }
        if (!empty($actionDateFields)) {
            $orderBy[] = 'GREATEST(' . implode(', ', $actionDateFields) . ') DESC';
        }
        if (leads_has_column(lead_pipeline_position_column())) {
            $orderBy[] = lead_pipeline_position_column() . ' DESC';
        }
        $orderBy[] = 'id DESC';

        try {
            $rows = db_all("
                SELECT " . implode(', ', $selectFields) . "
                FROM leads" . lead_pipeline_visibility_sql('WHERE') . "
                ORDER BY " . implode(', ', $orderBy) . "
                LIMIT {$limit}
            ");

            foreach ($rows as $lead) {
                $stageKey = function_exists('lead_conversion_stage_key')
                    ? lead_conversion_stage_key($lead)
                    : lead_db_status_value($lead);

                if ($stageKey === '' || !isset($grouped[$stageKey])) {
                    continue;
                }

                if (array_key_exists('lead_value', $lead)) {
                    $lead['lead_value'] = number_format(lead_db_value($lead), 2, '.', '');
                }

                $grouped[$stageKey][] = $lead;
            }
        } catch (Throwable $e) {
            return $grouped;
        }

        return $grouped;
    }
}

if (!function_exists('lead_recent_rows')) {
    function lead_recent_rows(int $limit = 8): array
    {
        if (!leads_table_exists()) {
            return [];
        }

        $selectFields = ['id'];

        foreach ([
            'full_name',
            'phone',
            'email',
            'preferred_contact',
            'procedure_interest',
            'source',
            'source_medium',
            'source_type',
            'landing_page',
            'campaign',
            'source_campaign',
            'source_ad_set',
            'source_ad_name',
            'source_post_id',
            'source_post_label',
            'external_lead_id',
            'instagram_username',
            'trigger_keyword',
            'status',
            'assigned_to',
            'financing_needed',
            'financing_option',
            'consultation_status',
            'consultation_date',
            'lead_value',
            'lost_reason',
            'notes',
            'sms_opt_status',
            'last_contacted_at',
            'last_inbound_at',
            'last_outbound_at',
            'unread_message_count',
            'next_follow_up_at',
            'date_of_birth',
            'intent_type',
            'scheduling_preferred_day',
            'scheduling_preferred_time',
            'follow_up_status',
            'last_follow_up_check_at',
            'created_at',
            'updated_at'
        ] as $field) {
            if (leads_has_column($field)) {
                $selectFields[] = $field;
            }
        }

        $limit = max(1, min(100, $limit));
        $orderBy = leads_has_column('updated_at') ? 'updated_at DESC, id DESC' : 'id DESC';

        try {
            $rows = db_all("
                SELECT " . implode(', ', $selectFields) . "
                FROM leads" . lead_pipeline_visibility_sql('WHERE') . "
                ORDER BY {$orderBy}
                LIMIT {$limit}
            ");

            foreach ($rows as &$lead) {
                if (array_key_exists('lead_value', $lead)) {
                    $lead['lead_value'] = number_format(lead_db_value($lead), 2, '.', '');
                }
            }
            unset($lead);

            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('lead_action_queue_priority')) {
    function lead_action_queue_priority(array $lead, array $summary): int
    {
        $action = (array)($summary['next_action'] ?? []);
        $urgency = (array)($summary['urgency'] ?? []);
        $actionKey = (string)($action['key'] ?? '');
        $urgencyKey = (string)($urgency['key'] ?? '');
        $status = trim((string)($lead['status'] ?? ''));
        $stageKey = (string)($summary['stage_key'] ?? '');
        $smsOptStatus = trim((string)($lead['sms_opt_status'] ?? 'unknown'));

        if ($smsOptStatus === 'opted_out' || in_array($status, ['opted_out', 'lost_lead', 'consult_completed', 'treatment_accepted'], true)) {
            return 0;
        }

        if (in_array($stageKey, ['consult_completed', 'treatment_accepted', 'nurture_lost'], true) || $status === 'no_answer') {
            return 0;
        }

        $score = match ($actionKey) {
            'reply_needed' => 100,
            'first_touch' => 95,
            'overdue_follow_up' => 90,
            'second_follow_up' => 82,
            'reschedule' => 78,
            'confirm_appointment' => 74,
            'ask_dob' => 68,
            'close_consult_status' => 62,
            'bad_phone' => 58,
            default => 0,
        };

        if ($urgencyKey === 'reply_now') {
            $score += 8;
        } elseif ($urgencyKey === 'overdue_3d') {
            $score += 6;
        } elseif ($urgencyKey === 'due_48h') {
            $score += 4;
        }

        $unread = (int)($lead['unread_message_count'] ?? 0);
        if ($unread > 0) {
            $score += min(10, $unread * 2);
        }

        return $score;
    }
}

if (!function_exists('lead_action_queue_reason')) {
    function lead_action_queue_reason(array $lead, array $summary): string
    {
        $actionKey = (string)($summary['next_action']['key'] ?? '');
        $stageLabel = (string)($summary['stage_label'] ?? 'Lead');
        $lastInbound = trim((string)($lead['last_inbound_at'] ?? ''));
        $lastOutbound = trim((string)($lead['last_outbound_at'] ?? ''));
        $nextFollowUp = trim((string)($lead['next_follow_up_at'] ?? ''));

        return match ($actionKey) {
            'reply_needed' => $lastInbound !== ''
                ? 'Patient replied after the last outbound touch. Review the thread and answer in context.'
                : 'Inbound reply needs review before the next step.',
            'first_touch' => 'New lead is ready for first contact.',
            'overdue_follow_up' => 'Follow-up is overdue. Re-engage with a short, specific message.',
            'second_follow_up' => $nextFollowUp !== ''
                ? 'Follow-up is due from the saved next-follow-up time.'
                : 'Last outbound touch is more than 24 hours old with no newer reply.',
            'reschedule' => 'No-show or missed consult needs a reschedule attempt.',
            'confirm_appointment' => 'Consult is tomorrow. Confirm appointment details.',
            'ask_dob' => 'Scheduling data is missing DOB for the Dentrix-ready package.',
            'close_consult_status' => 'Lead is in consult completed stage, but consultation status still needs to be closed.',
            'bad_phone' => 'Phone looks invalid or placeholder. Clean contact info before texting.',
            default => 'Review next step in ' . $stageLabel . '.',
        };
    }
}

if (!function_exists('lead_action_queue_tab')) {
    function lead_action_queue_tab(array $summary): string
    {
        $actionKey = (string)($summary['next_action']['key'] ?? '');
        return match ($actionKey) {
            'bad_phone', 'ask_dob', 'close_consult_status' => 'details',
            default => 'communications',
        };
    }
}

if (!function_exists('lead_action_queue_rows')) {
    function lead_action_queue_rows(int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        $groupedRows = lead_pipeline_rows(1000);
        $rows = [];

        foreach ($groupedRows as $stageRows) {
            if (!is_array($stageRows)) {
                continue;
            }

            foreach ($stageRows as $lead) {
                if (!is_array($lead) || !function_exists('lead_conversion_summary')) {
                    continue;
                }

                $summary = lead_conversion_summary($lead);
                $priority = lead_action_queue_priority($lead, $summary);
                if ($priority <= 0) {
                    continue;
                }

                $touchDate = lead_conversion_last_touch_datetime($lead);
                $lead['_action_queue'] = [
                    'priority' => $priority,
                    'action_key' => (string)($summary['next_action']['key'] ?? ''),
                    'action_label' => (string)($summary['next_action']['label'] ?? 'Review next step'),
                    'action_tone' => (string)($summary['next_action']['tone'] ?? 'slate'),
                    'stage_key' => (string)($summary['stage_key'] ?? ''),
                    'stage_label' => (string)($summary['stage_label'] ?? ''),
                    'urgency_label' => (string)($summary['urgency']['label'] ?? ''),
                    'urgency_tone' => (string)($summary['urgency']['tone'] ?? 'slate'),
                    'reason' => lead_action_queue_reason($lead, $summary),
                    'tab' => lead_action_queue_tab($summary),
                    'source_label' => function_exists('lead_operator_source_label') ? lead_operator_source_label($lead) : trim((string)($lead['source'] ?? '')),
                    'last_touch_at' => $touchDate ? $touchDate->format('Y-m-d H:i:s') : '',
                    'sort_at' => $touchDate ? $touchDate->getTimestamp() : 0,
                ];

                $rows[] = $lead;
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $aQueue = (array)($a['_action_queue'] ?? []);
            $bQueue = (array)($b['_action_queue'] ?? []);
            $priorityDiff = (int)($bQueue['priority'] ?? 0) <=> (int)($aQueue['priority'] ?? 0);
            if ($priorityDiff !== 0) {
                return $priorityDiff;
            }
            return (int)($bQueue['sort_at'] ?? 0) <=> (int)($aQueue['sort_at'] ?? 0);
        });

        return array_slice($rows, 0, $limit);
    }
}

if (!function_exists('lead_action_queue_summary')) {
    function lead_action_queue_summary(array $rows): array
    {
        $summary = [
            'total' => count($rows),
            'reply_needed' => 0,
            'first_touch' => 0,
            'follow_up' => 0,
            'schedule' => 0,
            'cleanup' => 0,
        ];

        foreach ($rows as $lead) {
            $actionKey = (string)($lead['_action_queue']['action_key'] ?? '');
            if ($actionKey === 'reply_needed') {
                $summary['reply_needed']++;
            } elseif ($actionKey === 'first_touch') {
                $summary['first_touch']++;
            } elseif (in_array($actionKey, ['overdue_follow_up', 'second_follow_up'], true)) {
                $summary['follow_up']++;
            } elseif (in_array($actionKey, ['reschedule', 'confirm_appointment', 'ask_dob'], true)) {
                $summary['schedule']++;
            } elseif (in_array($actionKey, ['bad_phone', 'close_consult_status'], true)) {
                $summary['cleanup']++;
            }
        }

        return $summary;
    }
}

if (!function_exists('lead_duplicate_normalize_phone')) {
    function lead_duplicate_normalize_phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return substr($digits, 1);
        }
        return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
    }
}

if (!function_exists('lead_find_duplicate')) {
    function lead_find_duplicate(array $data, int $excludeLeadId = 0): ?array
    {
        if (!leads_table_exists()) {
            return null;
        }

        $orderBy = leads_has_column('updated_at') ? 'updated_at DESC, id DESC' : 'id DESC';
        $excludeSql = $excludeLeadId > 0 ? ' AND id <> :exclude_lead_id' : '';
        $excludeParam = $excludeLeadId > 0 ? ['exclude_lead_id' => $excludeLeadId] : [];

        $externalLeadId = trim((string)($data['external_lead_id'] ?? ''));
        if ($externalLeadId !== '' && leads_has_column('external_lead_id')) {
            $row = db_one(
                "SELECT id, full_name, phone, email, status, source, campaign, created_at
                 FROM leads
                 WHERE external_lead_id = :external_lead_id
                 {$excludeSql}
                 ORDER BY id DESC
                 LIMIT 1",
                array_merge(['external_lead_id' => $externalLeadId], $excludeParam)
            );
            if ($row) {
                $row['duplicate_match_type'] = 'external_lead_id';
                return $row;
            }
        }

        $email = strtolower(trim((string)($data['email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && leads_has_column('email')) {
            $row = db_one(
                "SELECT id, full_name, phone, email, status, source, campaign, created_at
                 FROM leads
                 WHERE LOWER(email) = :email
                 {$excludeSql}
                 ORDER BY {$orderBy}
                 LIMIT 1",
                array_merge(['email' => $email], $excludeParam)
            );
            if ($row) {
                $row['duplicate_match_type'] = 'email';
                return $row;
            }
        }

        $phone = lead_duplicate_normalize_phone((string)($data['phone'] ?? ''));
        if (strlen($phone) >= 10 && leads_has_column('phone')) {
            $phoneSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', '')";
            $row = db_one(
                "SELECT id, full_name, phone, email, status, source, campaign, created_at
                 FROM leads
                 WHERE ({$phoneSql} = :phone_exact
                    OR RIGHT({$phoneSql}, 10) = :phone_right
                 )
                 {$excludeSql}
                 ORDER BY {$orderBy}
                 LIMIT 1",
                array_merge(['phone_exact' => $phone, 'phone_right' => $phone], $excludeParam)
            );
            if ($row) {
                $row['duplicate_match_type'] = 'phone';
                return $row;
            }
        }

        return null;
    }
}

if (!function_exists('lead_duplicate_message')) {
    function lead_duplicate_message(array $lead): string
    {
        $matchType = (string)($lead['duplicate_match_type'] ?? 'contact_info');
        $label = match ($matchType) {
            'external_lead_id' => 'source lead ID',
            'email' => 'email address',
            'phone' => 'phone number',
            default => 'contact information',
        };
        $name = trim((string)($lead['full_name'] ?? ''));
        $display = $name !== '' ? $name : 'an existing lead';
        return 'Possible duplicate found by ' . $label . ': ' . $display . ' is already in the CRM.';
    }
}

if (!function_exists('lead_refresh_duplicate_from_input')) {
    function lead_refresh_duplicate_from_input(array $duplicate, array $data): void
    {
        $leadId = (int)($duplicate['id'] ?? 0);
        if ($leadId <= 0 || !leads_table_exists()) {
            return;
        }

        $existing = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$existing) {
            return;
        }

        $updates = [];
        $params = ['id' => $leadId];
        $existingStatus = trim((string)($existing['status'] ?? ''));
        $incomingStatus = trim((string)($data['status'] ?? ''));
        $reopenedFromStatus = '';
        $reopenedToStatus = '';

        foreach ([
            'full_name',
            'phone',
            'email',
            'procedure_interest',
            'preferred_contact',
            'source',
            'source_medium',
            'source_type',
            'landing_page',
            'campaign',
            'external_lead_id',
            'date_of_birth',
            'intent_type',
            'financing_needed',
            'financing_option',
            'consultation_status',
            'consultation_date',
            'lead_value',
        ] as $field) {
            if (!leads_has_column($field)) {
                continue;
            }

            $value = trim((string)($data[$field] ?? ''));
            if ($value === '') {
                continue;
            }

            $current = trim((string)($existing[$field] ?? ''));
            $shouldReplace = $current === ''
                || in_array(strtolower($current), ['unknown lead', 'unnamed lead', 'meta import probe'], true);

            if ($field === 'source' || $field === 'source_medium' || $field === 'source_type' || $field === 'landing_page' || $field === 'campaign') {
                $shouldReplace = true;
            }

            if ($shouldReplace || $field === 'lead_value') {
                $updates[] = "`{$field}` = :{$field}";
                $params[$field] = $value;
            }
        }

        if (
            leads_has_column('status')
            && $incomingStatus !== ''
            && $incomingStatus !== $existingStatus
            && in_array($existingStatus, ['lost_lead', 'treatment_accepted'], true)
        ) {
            $updates[] = '`status` = :status';
            $params['status'] = $incomingStatus;
            $reopenedFromStatus = $existingStatus;
            $reopenedToStatus = $incomingStatus;

            if (leads_has_column('lost_reason')) {
                $updates[] = '`lost_reason` = :lost_reason';
                $params['lost_reason'] = null;
            }

            if (leads_has_column('follow_up_status')) {
                $updates[] = '`follow_up_status` = :follow_up_status';
                $params['follow_up_status'] = 'needs_follow_up';
            }

            if (leads_has_column('next_follow_up_at')) {
                $updates[] = '`next_follow_up_at` = :next_follow_up_at';
                $params['next_follow_up_at'] = null;
            }
        }

        if (leads_has_column('notes')) {
            $incomingNotes = trim((string)($data['notes'] ?? ''));
            if ($incomingNotes !== '') {
                $existingNotes = trim((string)($existing['notes'] ?? ''));
                $separator = $existingNotes !== '' ? "\n\n" : '';
                $updates[] = '`notes` = :notes';
                $params['notes'] = $existingNotes
                    . $separator
                    . '--- Duplicate intake refresh on ' . now() . " ---\n"
                    . $incomingNotes;
            }
        }

        if (leads_has_column('updated_at')) {
            $updates[] = '`updated_at` = :updated_at';
            $params['updated_at'] = now();
        }

        if (empty($updates)) {
            return;
        }

        db_execute('UPDATE leads SET ' . implode(', ', $updates) . ' WHERE id = :id LIMIT 1', $params);

        if (function_exists('lead_comm_insert_activity')) {
            $activityBody = 'Duplicate public intake refreshed this existing lead and moved it to the top of the board.';
            $activityMeta = [
                'source' => 'lead_create_minimal',
                'duplicate_match_type' => (string)($duplicate['duplicate_match_type'] ?? ''),
            ];

            if ($reopenedFromStatus !== '' && $reopenedToStatus !== '') {
                $activityBody = 'Duplicate public intake refreshed this lead, reopened it, and moved it to the top of the board.';
                $activityMeta['status_reopened_from'] = $reopenedFromStatus;
                $activityMeta['status_reopened_to'] = $reopenedToStatus;
            }

            lead_comm_insert_activity($leadId, 'duplicate_intake_refresh', $activityBody, $activityMeta, 'Intake');
        }
    }
}

if (!function_exists('lead_dispatch_operator_intake_alerts')) {
    function lead_dispatch_operator_intake_alerts(array $lead, array $context = []): void
    {
        $leadId = (int)($context['lead_id'] ?? $lead['id'] ?? 0);
        $createdAt = (string)($context['created_at'] ?? $lead['created_at'] ?? now());
        $campaign = (string)($context['campaign'] ?? $lead['campaign'] ?? '');
        $landingPage = (string)($context['landing_page'] ?? $lead['landing_page'] ?? '');
        $sendNotificationEmail = empty($context['suppress_notification_email']);

        $emailToTextSent = false;
        if (function_exists('elite_send_lead_email_to_text_alert')) {
            try {
                $emailToTextSent = elite_send_lead_email_to_text_alert($lead, [
                    'lead_id' => $leadId,
                    'created_at' => $createdAt,
                    'campaign' => $campaign,
                    'landing_page' => $landingPage,
                ]);
            } catch (Throwable $e) {
                if (function_exists('esm_log')) {
                    esm_log('lead_alerts', 'Email-to-text lead alert failed.', [
                        'lead_id' => $leadId,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        $notificationTriggered = false;
        if ($sendNotificationEmail && function_exists('elite_send_lead_notification_email')) {
            try {
                $notificationTriggered = elite_send_lead_notification_email($lead, [
                    'lead_id' => $leadId,
                    'created_at' => $createdAt,
                    'campaign' => $campaign,
                    'landing_page' => $landingPage,
                ]);
            } catch (Throwable $e) {
                if (function_exists('esm_log')) {
                    esm_log('lead_alerts', 'Lead notification email/pushover failed.', [
                        'lead_id' => $leadId,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($leadId > 0 && function_exists('lead_comm_insert_activity')) {
            $body = 'Triggered intake operator alerts.';
            if (!$emailToTextSent && !$notificationTriggered) {
                $body = 'Tried to trigger intake operator alerts, but no alert channel reported success.';
            }

            lead_comm_insert_activity($leadId, 'intake_alerts_triggered', $body, [
                'email_to_text_sent' => $emailToTextSent,
                'notification_triggered' => $notificationTriggered,
                'notification_email_suppressed' => !$sendNotificationEmail,
                'source' => (string)($context['source'] ?? 'lead_create_minimal'),
            ], 'Intake');
        }
    }
}

if (!function_exists('lead_force_send_first_touch_sms')) {
    function lead_force_send_first_touch_sms(int $leadId): array
    {
        if ($leadId <= 0) {
            return [
                'attempted' => false,
                'sent' => false,
                'body' => '',
                'status_label' => 'Lead not found.',
            ];
        }

        if (!function_exists('lead_ai_default_new_lead_sms')) {
            $leadAiPath = __DIR__ . '/lead_ai.php';
            if (is_file($leadAiPath)) {
                require_once $leadAiPath;
            }
        }

        $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$lead) {
            return [
                'attempted' => false,
                'sent' => false,
                'body' => '',
                'status_label' => 'Lead not found.',
            ];
        }

        if (trim((string)($lead['phone'] ?? '')) === '') {
            return [
                'attempted' => false,
                'sent' => false,
                'body' => '',
                'status_label' => 'Lead has no phone number.',
            ];
        }

        if (trim((string)($lead['sms_opt_status'] ?? 'unknown')) === 'opted_out' || trim((string)($lead['status'] ?? '')) === 'opted_out') {
            return [
                'attempted' => false,
                'sent' => false,
                'body' => '',
                'status_label' => 'Lead opted out of SMS.',
            ];
        }

        if (!function_exists('elite_twilio_send_sms')) {
            $twilioPath = dirname(__DIR__) . '/core/twilio.php';
            if (is_file($twilioPath)) {
                require_once $twilioPath;
            }
        }

        if (!function_exists('elite_twilio_send_sms')) {
            return [
                'attempted' => false,
                'sent' => false,
                'body' => '',
                'status_label' => 'Twilio SMS sender unavailable.',
            ];
        }

        $body = function_exists('lead_ai_default_new_lead_sms')
            ? lead_ai_default_new_lead_sms($lead)
            : 'Hi, this is Rod from Elite Smiles. Thanks for reaching out about your smile consultation. We offer a complimentary consultation with Dr. Meden to review options and financing. What day/time works best for you? Reply STOP to opt out.';

        $sendResult = elite_twilio_send_sms((string)($lead['phone'] ?? ''), $body, [
            'lead_id' => $leadId,
            'lead' => $lead,
            'send_pushover_fallback' => true,
            'fallback_summary' => 'Twilio could not send the required first-touch SMS. Open lead actions to retry manually.',
            'original_body' => $body,
        ]);

        if (empty($sendResult['ok'])) {
            return [
                'attempted' => true,
                'sent' => false,
                'body' => $body,
                'status_label' => (string)($sendResult['message'] ?? 'Required first-touch SMS failed.'),
            ];
        }

        $sentBody = (string)($sendResult['body'] ?? $body);
        $messageId = function_exists('lead_comm_insert_message') ? lead_comm_insert_message([
            'lead_id' => $leadId,
            'direction' => 'outbound',
            'channel' => 'sms',
            'from_number' => (string)($sendResult['from'] ?? ''),
            'to_number' => (string)($sendResult['to'] ?? $lead['phone'] ?? ''),
            'body' => $sentBody,
            'twilio_message_sid' => (string)($sendResult['twilio_sid'] ?? ''),
            'twilio_status' => (string)($sendResult['twilio_status'] ?? ''),
            'is_read' => 1,
        ]) : 0;

        if (function_exists('lead_comm_insert_activity')) {
            lead_comm_insert_activity($leadId, 'sms_outbound', 'Required first-touch SMS sent through new-lead workflow fallback.', [
                'message_id' => $messageId,
                'twilio_sid' => $sendResult['twilio_sid'] ?? '',
                'source' => 'new_lead_required_first_touch_sms',
            ], 'System');
        }
        if (function_exists('lead_comm_update_rollup')) {
            lead_comm_update_rollup($leadId);
        }
        if (function_exists('lead_ai_create_outbound_note')) {
            lead_ai_create_outbound_note($leadId, 'sms', '', $sentBody, [
                'message_id' => $messageId,
                'sent_at' => date('Y-m-d H:i:s'),
                'created_by' => 'System',
                'source' => 'new_lead_required_first_touch_sms',
            ]);
        }

        return [
            'attempted' => true,
            'sent' => true,
            'body' => $sentBody,
            'status_label' => 'Required first-touch SMS sent.',
        ];
    }
}

if (!function_exists('lead_create_minimal')) {
    function lead_enforce_meta_defaults(array &$data): void
    {
        $source = strtolower(trim((string)($data['source'] ?? '')));
        $sourceMedium = strtolower(trim((string)($data['source_medium'] ?? '')));
        $sourceType = strtolower(trim((string)($data['source_type'] ?? '')));

        $isMetaSource = $source === 'meta'
            || $source === 'meta_lead_form'
            || str_starts_with($source, 'meta_')
            || str_contains($sourceMedium, 'meta')
            || str_contains($sourceType, 'meta')
            || (string)($data['platform'] ?? '') === 'meta'
            || (string)($data['platform'] ?? '') === 'instagram';

        if (!$isMetaSource) {
            return;
        }

        if ($source === '' || $source === 'manual') {
            $data['source'] = 'meta_lead_form';
        }

        if (trim((string)($data['preferred_contact'] ?? '')) === '') {
            $data['preferred_contact'] = 'text';
        }

        if (trim((string)($data['consultation_status'] ?? '')) === '') {
            $data['consultation_status'] = 'requested';
        }

        if (trim((string)($data['procedure_interest'] ?? '')) === '') {
            $data['procedure_interest'] = 'Veneers';
        }
    }

    function lead_create_minimal(array $input, array $user = []): array
    {
        lead_pipeline_ensure_schema();

        if (!leads_table_exists()) {
            return [
                'ok' => false,
                'message' => 'Leads table not found.',
                'lead_id' => 0,
            ];
        }

        $data = array_merge(lead_empty_record($user), $input);

        if (!isset($data['full_name']) || trim((string)$data['full_name']) === '') {
            $firstName = trim((string)($data['first_name'] ?? ''));
            $lastName = trim((string)($data['last_name'] ?? ''));
            $data['full_name'] = trim(($firstName . ' ' . $lastName));
        } else {
            $data['full_name'] = trim((string)($data['full_name'] ?? ''));
        }
        $data['phone'] = trim((string)($data['phone'] ?? ''));
        $data['email'] = strtolower(trim((string)($data['email'] ?? '')));
        $data['source'] = trim((string)($data['source'] ?? 'manual'));
        $data['source_medium'] = trim((string)($data['source_medium'] ?? ''));
        $data['source_type'] = trim((string)($data['source_type'] ?? ''));
        if (trim((string)($data['preferred_contact'] ?? '')) === '') {
            $data['preferred_contact'] = 'text';
        }
        lead_enforce_meta_defaults($data);
        $data['procedure_interest'] = trim((string)($data['procedure_interest'] ?? ''));
        $data['landing_page'] = trim((string)($data['landing_page'] ?? ''));
        $data['campaign'] = trim((string)($data['campaign'] ?? ''));
        $data['external_lead_id'] = trim((string)($data['external_lead_id'] ?? ''));
        $data['status'] = trim((string)($data['status'] ?? lead_default_stage()));
        if ($data['status'] === '') {
            $data['status'] = lead_default_stage();
        }
        $data['assigned_to'] = trim((string)($data['assigned_to'] ?? lead_default_assigned_to($user)));
        $data['financing_needed'] = trim((string)($data['financing_needed'] ?? 'unsure'));
        $data['financing_option'] = trim((string)($data['financing_option'] ?? 'none'));
        $data['preferred_contact'] = trim((string)($data['preferred_contact'] ?? 'text'));
        $data['consultation_status'] = trim((string)($data['consultation_status'] ?? 'requested'));
        $data['consultation_date'] = trim((string)($data['consultation_date'] ?? ''));
        if ($data['consultation_date'] !== '') {
            $consultationTimestamp = strtotime(str_replace('T', ' ', $data['consultation_date']));
            $data['consultation_date'] = $consultationTimestamp !== false ? date('Y-m-d H:i:s', $consultationTimestamp) : '';
        }
        $data['date_of_birth'] = trim((string)($data['date_of_birth'] ?? ''));
        if ($data['date_of_birth'] !== '') {
            $dateOfBirthTimestamp = null;
            foreach (['Y-m-d', 'm/d/Y', 'm-d-Y', 'Y/m/d'] as $format) {
                $dateOfBirth = DateTime::createFromFormat($format, $data['date_of_birth']);
                if ($dateOfBirth instanceof DateTime) {
                    $dateOfBirthTimestamp = $dateOfBirth->getTimestamp();
                    break;
                }
            }
            if ($dateOfBirthTimestamp === null) {
                $dateOfBirthTimestamp = strtotime($data['date_of_birth']);
            }
            $data['date_of_birth'] = $dateOfBirthTimestamp !== false ? date('Y-m-d', (int)$dateOfBirthTimestamp) : '';
        }
        $data['intent_type'] = trim((string)($data['intent_type'] ?? ''));
        if ($data['intent_type'] !== '') {
            $data['intent_type'] = mb_substr($data['intent_type'], 0, 120);
        }
        $data['lead_value'] = trim((string)($data['lead_value'] ?? ''));
        $data['lost_reason'] = trim((string)($data['lost_reason'] ?? ''));
        $data['notes'] = trim((string)($data['notes'] ?? ''));
        $data['sms_opt_status'] = strtolower(trim((string)($data['sms_opt_status'] ?? 'unknown')));
        if (!in_array($data['sms_opt_status'], ['unknown', 'opted_in', 'opted_out'], true)) {
            $data['sms_opt_status'] = 'unknown';
        }

        if (!lead_is_min_capture_complete($data)) {
            return ['ok' => false, 'message' => 'Please provide at least a name, phone, or email.', 'lead_id' => 0];
        }

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Please provide a valid email address.', 'lead_id' => 0];
        }

        if (empty($data['allow_duplicate'])) {
            try {
                $duplicate = lead_find_duplicate($data);
                if ($duplicate) {
                    $duplicateId = (int)($duplicate['id'] ?? 0);
                    if (!empty($data['refresh_duplicate'])) {
                        lead_refresh_duplicate_from_input($duplicate, $data);

                        if ($duplicateId > 0) {
                            $alertLead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $duplicateId]);
                            if (!$alertLead) {
                                $alertLead = array_merge($duplicate, $data, ['id' => $duplicateId]);
                            }

                            lead_dispatch_operator_intake_alerts($alertLead, [
                                'lead_id' => $duplicateId,
                                'created_at' => (string)($alertLead['created_at'] ?? now()),
                                'campaign' => (string)($alertLead['campaign'] ?? $data['campaign']),
                                'landing_page' => (string)($alertLead['landing_page'] ?? $data['landing_page']),
                                'suppress_notification_email' => !empty($data['suppress_notification_email']),
                                'source' => 'lead_create_minimal_duplicate_refresh',
                            ]);
                        }
                    }

                    return [
                        'ok' => true,
                        'message' => lead_duplicate_message($duplicate),
                        'lead_id' => (int)($duplicate['id'] ?? 0),
                        'duplicate_found' => true,
                        'duplicate_match_type' => (string)($duplicate['duplicate_match_type'] ?? ''),
                        'duplicate_lead' => $duplicate,
                    ];
                }
            } catch (Throwable $e) {
                if (function_exists('esm_log')) {
                    esm_log('lead_duplicates', 'Duplicate check failed during lead creation.', [
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        $stageMap = lead_stage_map();
        if (!isset($stageMap[$data['status']])) {
            $data['status'] = lead_default_stage();
        }

        $financingNeededOptions = lead_financing_needed_options();
        if (!isset($financingNeededOptions[$data['financing_needed']])) {
            $data['financing_needed'] = 'unsure';
        }

        $financingOptions = lead_financing_option_labels();
        if (!array_key_exists($data['financing_option'], $financingOptions)) {
            $data['financing_option'] = 'none';
        }

        if ($data['financing_needed'] === 'no') {
            $data['financing_option'] = 'none';
        }

        $consultationStatusOptions = function_exists('lead_consultation_status_options')
            ? lead_consultation_status_options()
            : [
                '' => 'Not set',
                'requested' => 'Requested',
                'scheduled' => 'Scheduled',
                'completed' => 'Completed',
                'no_show' => 'No Show',
                'not_interested' => 'Not Interested',
            ];

        if (!array_key_exists($data['consultation_status'], $consultationStatusOptions)) {
            $data['consultation_status'] = 'requested';
        }

        $preferredContact = strtolower((string)$data['preferred_contact']);
        if (!in_array($preferredContact, ['text', 'sms', 'call', 'phone', 'email'], true)) {
            $data['preferred_contact'] = 'text';
        } else {
            $data['preferred_contact'] = match ($preferredContact) {
                'sms', 'text' => 'text',
                'phone'       => 'call',
                'call'        => 'call',
                'email'       => 'email',
                default       => 'text',
            };
        }

        $lostReasons = lead_lost_reason_options();
        if (!array_key_exists($data['lost_reason'], $lostReasons)) {
            $data['lost_reason'] = '';
        }

        $leadValue = number_format(lead_default_opportunity_value(), 2, '.', '');
        if ($data['lead_value'] !== '' && is_numeric($data['lead_value'])) {
            $leadValue = number_format((float)$data['lead_value'], 2, '.', '');
        }

        $columns = [];
        $placeholders = [];
        $params = [];

        $candidateValues = [
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'procedure_interest' => $data['procedure_interest'],
            'source' => $data['source'] !== '' ? $data['source'] : 'manual',
            'source_medium' => $data['source_medium'] !== '' ? $data['source_medium'] : null,
            'source_type' => $data['source_type'] !== '' ? $data['source_type'] : null,
            'landing_page' => $data['landing_page'],
            'campaign' => $data['campaign'],
            'external_lead_id' => $data['external_lead_id'] !== '' ? $data['external_lead_id'] : null,
            'status' => $data['status'],
            'assigned_to' => $data['assigned_to'],
            'financing_needed' => $data['financing_needed'],
            'financing_option' => $data['financing_option'],
            'preferred_contact' => $data['preferred_contact'] !== '' ? $data['preferred_contact'] : null,
            'consultation_status' => $data['consultation_status'] !== '' ? $data['consultation_status'] : null,
            'consultation_date' => $data['consultation_date'] !== '' ? $data['consultation_date'] : null,
            'date_of_birth' => $data['date_of_birth'] !== '' ? $data['date_of_birth'] : null,
            'intent_type' => $data['intent_type'] !== '' ? $data['intent_type'] : null,
            'lead_value' => $leadValue,
            'lost_reason' => $data['lost_reason'] !== '' ? $data['lost_reason'] : null,
            'sms_opt_status' => $data['sms_opt_status'],
            'notes' => $data['notes'],
            'pipeline_position' => lead_pipeline_next_position($data['status']),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($candidateValues as $column => $value) {
            if (leads_has_column($column)) {
                $columns[] = "`{$column}`";
                $placeholders[] = ':' . $column;
                $params[$column] = $value;
            }
        }

        if (empty($columns)) {
            return ['ok' => false, 'message' => 'No compatible leads columns were found.', 'lead_id' => 0];
        }

        try {
            $sql = "INSERT INTO leads (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $leadId = (int) db_insert($sql, $params);

            if ($leadId > 0) {
                $alertLead = $data;
                $alertLead['id'] = $leadId;
                $alertLead['lead_value'] = $leadValue;
                $alertLead['created_at'] = $candidateValues['created_at'];
                lead_dispatch_operator_intake_alerts($alertLead, [
                    'lead_id' => $leadId,
                    'created_at' => $candidateValues['created_at'],
                    'campaign' => $data['campaign'],
                    'landing_page' => $data['landing_page'],
                    'suppress_notification_email' => !empty($data['suppress_notification_email']),
                    'source' => 'lead_create_minimal_insert',
                ]);
            }

            $suppressFirstTouchEmail = !empty($data['suppress_first_touch_email']);
            $firstTouchEmail = [
                'attempted' => false,
                'sent' => false,
                'subject' => '',
                'body' => '',
                'status_label' => $suppressFirstTouchEmail ? 'Auto first-touch email suppressed.' : '',
            ];
            if (!$suppressFirstTouchEmail && $leadId > 0 && function_exists('lead_email_maybe_send_first_touch')) {
                try {
                    $firstTouchEmail = lead_email_maybe_send_first_touch($leadId);
                } catch (Throwable $e) {
                    if (function_exists('esm_log')) {
                        esm_log('lead_email', 'Automatic first-touch email hook failed.', [
                            'lead_id' => $leadId,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $suppressFirstTouchSms = !empty($data['suppress_first_touch_sms']);
            $firstTouchSms = [
                'attempted' => false,
                'sent' => false,
                'body' => '',
                'status_label' => $suppressFirstTouchSms ? 'Auto new-lead SMS suppressed.' : '',
            ];
            if ($leadId > 0 && !function_exists('lead_ai_maybe_send_new_lead_sms')) {
                $leadAiPath = __DIR__ . '/lead_ai.php';
                if (is_file($leadAiPath)) {
                    require_once $leadAiPath;
                }
            }
            if (!$suppressFirstTouchSms && $leadId > 0 && function_exists('lead_ai_maybe_send_new_lead_sms')) {
                try {
                    $firstTouchSms = lead_ai_maybe_send_new_lead_sms($leadId);
                } catch (Throwable $e) {
                    if (function_exists('esm_log')) {
                        esm_log('openai', 'Automatic new-lead SMS hook failed.', [
                            'lead_id' => $leadId,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }
            if (!$suppressFirstTouchSms && $leadId > 0 && empty($firstTouchSms['attempted'])) {
                try {
                    $firstTouchSms = lead_force_send_first_touch_sms($leadId);
                } catch (Throwable $e) {
                    $firstTouchSms = [
                        'attempted' => false,
                        'sent' => false,
                        'body' => '',
                        'status_label' => 'Required first-touch SMS fallback failed.',
                    ];
                    if (function_exists('esm_log')) {
                        esm_log('lead_workflow', 'Required first-touch SMS fallback failed.', [
                            'lead_id' => $leadId,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }

            if ($leadId > 0) {
                try {
                    $workflowEmailStatus = (string)($firstTouchEmail['status_label'] ?? 'Auto first-touch email not attempted.');
                    $workflowSmsStatus = (string)($firstTouchSms['status_label'] ?? 'Auto new-lead SMS not attempted.');
                    $workflowBody = 'Automatic new-lead first touch completed. Email: ' . $workflowEmailStatus . ' SMS: ' . $workflowSmsStatus;

                    if (function_exists('lead_comm_insert_activity')) {
                        lead_comm_insert_activity($leadId, 'new_lead_first_touch_completed', $workflowBody, [
                            'email_attempted' => !empty($firstTouchEmail['attempted']),
                            'email_sent' => !empty($firstTouchEmail['sent']),
                            'sms_attempted' => !empty($firstTouchSms['attempted']),
                            'sms_sent' => !empty($firstTouchSms['sent']),
                            'source' => 'lead_create_minimal_auto_workflow',
                        ], 'System');
                    }

                    $freshStageLead = db_one('SELECT id, status FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
                    $freshStage = trim((string)($freshStageLead['status'] ?? ''));
                    $firstTouchSent = !empty($firstTouchEmail['sent']) || !empty($firstTouchSms['sent']);
                    if ($firstTouchSent && ($freshStage === '' || $freshStage === lead_default_stage())) {
                        $contactedStage = 'contacted';
                        $pipelinePosition = function_exists('lead_pipeline_next_position')
                            ? lead_pipeline_next_position($contactedStage)
                            : 0;
                        $updateParts = [];
                        $updateParams = ['id' => $leadId];

                        if (leads_has_column('status')) {
                            $updateParts[] = 'status = :status';
                            $updateParams['status'] = $contactedStage;
                        }
                        if (leads_has_column('pipeline_position')) {
                            $updateParts[] = 'pipeline_position = :pipeline_position';
                            $updateParams['pipeline_position'] = $pipelinePosition;
                        }
                        if (leads_has_column('updated_at')) {
                            $updateParts[] = 'updated_at = :updated_at';
                            $updateParams['updated_at'] = now();
                        }

                        if (!empty($updateParts)) {
                            db_execute('UPDATE leads SET ' . implode(', ', $updateParts) . ' WHERE id = :id LIMIT 1', $updateParams);
                            if (function_exists('lead_comm_insert_activity')) {
                                lead_comm_insert_activity($leadId, 'stage_change', 'Automatically moved new lead from New Lead to Contacted after first-touch outreach.', [
                                    'from' => $freshStage !== '' ? $freshStage : lead_default_stage(),
                                    'to' => $contactedStage,
                                    'source' => 'lead_create_minimal_auto_workflow',
                                ], 'System');
                            }
                        }
                    }
                } catch (Throwable $e) {
                    if (function_exists('esm_log')) {
                        esm_log('lead_workflow', 'Automatic new-lead first-touch workflow failed.', [
                            'lead_id' => $leadId,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }
            if ($leadId > 0 && function_exists('elite_send_new_lead_autoresponse_summary')) {
                try {
                    $freshLead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
                    if ($freshLead) {
                        elite_send_new_lead_autoresponse_summary($freshLead, [
                            'lead_id' => $leadId,
                            'created_at' => $candidateValues['created_at'],
                            'campaign' => $data['campaign'],
                            'landing_page' => $data['landing_page'],
                            'auto_response_email_subject' => (string) ($firstTouchEmail['subject'] ?? ''),
                            'auto_response_email_body' => (string) ($firstTouchEmail['body'] ?? ''),
                            'auto_response_email_status' => (string) ($firstTouchEmail['status_label'] ?? ''),
                            'auto_response_sms_body' => (string) ($firstTouchSms['body'] ?? ''),
                            'auto_response_sms_status' => (string) ($firstTouchSms['status_label'] ?? ''),
                        ]);

                        if (function_exists('lead_comm_insert_activity')) {
                            lead_comm_insert_activity($leadId, 'autoresponse_alerts_sent', 'Sent new-lead auto-response summary alerts.', [
                                'email_sent' => !empty($firstTouchEmail['sent']),
                                'sms_sent' => !empty($firstTouchSms['sent']),
                                'summary_email_to' => 'lead@hi.elitesmilesutah.com',
                            ], 'System');
                        }
                    }
                } catch (Throwable $e) {
                    if (function_exists('esm_log')) {
                        esm_log('lead_alerts', 'New-lead auto-response summary alerts failed.', [
                            'lead_id' => $leadId,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return ['ok' => true, 'message' => 'Lead created successfully.', 'lead_id' => $leadId];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Failed to create lead.', 'lead_id' => 0];
        }
    }
}

if (!function_exists('lead_import_meta_value')) {
    function lead_import_meta_normalize_key(string $key): string
    {
        $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
        return strtolower(trim($key));
    }

    function lead_import_meta_value(array $row, array $keys): string
    {
        $normalizedRow = [];
        foreach ($row as $rowKey => $rowValue) {
            $normalizedRow[lead_import_meta_normalize_key((string) $rowKey)] = $rowValue;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $value = trim((string) $row[$key]);
                if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
                    $value = substr($value, 1, -1);
                }
                return trim(str_replace('""', '"', $value));
            }

            $normalizedKey = lead_import_meta_normalize_key($key);
            if (array_key_exists($normalizedKey, $normalizedRow)) {
                $value = trim((string) $normalizedRow[$normalizedKey]);
                if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
                    $value = substr($value, 1, -1);
                }
                return trim(str_replace('""', '"', $value));
            }
        }

        return '';
    }
}

if (!function_exists('lead_import_meta_expand_row')) {
    function lead_import_meta_expand_row(array $row): array
    {
        if (count($row) !== 1) {
            $normalized = [];
            foreach ($row as $key => $value) {
                $normalized[lead_import_meta_normalize_key((string) $key)] = $value;
            }
            return $normalized;
        }

        $keys = array_keys($row);
        $headerLine = (string) ($keys[0] ?? '');
        $valueLine = (string) ($row[$headerLine] ?? '');
        if (!str_contains($headerLine, "\t") || !str_contains($valueLine, "\t")) {
            return $row;
        }

        $headers = str_getcsv($headerLine, "\t", '"', "\\");
        $values = str_getcsv($valueLine, "\t", '"', "\\");
        $expanded = [];
        foreach ($headers as $index => $header) {
            $header = lead_import_meta_normalize_key((string) $header);
            if ($header === '') {
                continue;
            }
            $expanded[$header] = trim((string) ($values[$index] ?? ''));
        }

        return $expanded ?: $row;
    }
}

if (!function_exists('lead_import_meta_row_to_input')) {
    function lead_import_meta_row_to_input(array $row): array
    {
        $row = lead_import_meta_expand_row($row);
        $externalLeadId = lead_import_meta_value($row, ['id', 'external_lead_id']);
        $createdTime = lead_import_meta_value($row, ['created_time', 'created_at']);
        $platform = strtolower(lead_import_meta_value($row, ['platform']));
        $platform = $platform !== '' ? $platform : 'meta';
        $intentType = lead_import_meta_value($row, [
            'how_soon_are_you_looking_to_start_your_smile_transformation?',
            'intent_type',
            'how_soon',
        ]);

        $notesParts = ['Imported from Meta CSV via leads import tool.'];
        if ($externalLeadId !== '') {
            $notesParts[] = 'External lead ID: ' . $externalLeadId . '.';
        }
        if ($createdTime !== '') {
            $notesParts[] = 'Created: ' . $createdTime . '.';
        }

        return [
            'full_name' => lead_import_meta_value($row, ['full_name', 'name']),
            'phone' => preg_replace('/^p:/i', '', lead_import_meta_value($row, ['phone_number', 'phone'])),
            'email' => lead_import_meta_value($row, ['email']),
            'procedure_interest' => lead_import_meta_value($row, ['procedure_interest', 'service_needed']) ?: 'Veneers',
            'preferred_contact' => lead_import_meta_value($row, ['preferred_contact']) ?: 'Text',
            'source' => lead_import_meta_value($row, ['source']) ?: 'meta_lead_form',
            'source_medium' => lead_import_meta_value($row, ['source_medium']) ?: 'social',
            'source_type' => lead_import_meta_value($row, ['source_type']) ?: 'meta_instant_form',
            'platform' => $platform,
            'landing_page' => lead_import_meta_value($row, ['form_name', 'landing_page']),
            'campaign' => lead_import_meta_value($row, ['campaign_name', 'campaign']),
            'intent_type' => $intentType,
            'external_lead_id' => $externalLeadId,
            'status' => lead_import_meta_value($row, ['status']) ?: 'new_lead',
            'notes' => implode(' ', $notesParts),
        ];
    }
}

if (!function_exists('lead_import_meta_rows')) {
    function lead_import_meta_rows(array $rows, array $user = []): array
    {
        $created = [];
        $duplicates = [];
        $failed = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $failed[] = [
                    'index' => $index,
                    'message' => 'Invalid row payload.',
                ];
                continue;
            }

            $input = lead_import_meta_row_to_input($row);
            $result = lead_create_minimal($input, $user);
            $entry = [
                'index' => $index,
                'name' => (string) ($input['full_name'] ?? ''),
                'external_lead_id' => (string) ($input['external_lead_id'] ?? ''),
                'lead_id' => (int) ($result['lead_id'] ?? 0),
                'message' => (string) ($result['message'] ?? ''),
            ];

            if (!empty($result['duplicate_found'])) {
                $duplicates[] = $entry + [
                    'duplicate_match_type' => (string) ($result['duplicate_match_type'] ?? ''),
                ];
                continue;
            }

            if (!empty($result['ok'])) {
                $created[] = $entry;
                continue;
            }

            $failed[] = $entry;
        }

        return [
            'created' => $created,
            'duplicates' => $duplicates,
            'failed' => $failed,
            'created_count' => count($created),
            'duplicate_count' => count($duplicates),
            'failed_count' => count($failed),
            'total_count' => count($rows),
        ];
    }
}
