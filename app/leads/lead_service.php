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
        return 10000.00;
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

        return round((float)$value, 2);
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

if (!function_exists('leads_table_columns')) {
    function leads_table_columns(): array
    {
        static $columns = null;

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
        $stageMap = lead_stage_map_ordered();

        foreach ($stageMap as $stageKey => $label) {
            $counts[$stageKey] = 0;
        }

        if (!leads_table_exists() || !leads_has_column('status')) {
            return $counts;
        }

        try {
            $rows = db_all("
                SELECT status AS stage_key, COUNT(*) AS total
                FROM leads
                WHERE status IS NOT NULL AND status <> ''" . lead_pipeline_visibility_sql('AND') . "
                GROUP BY status
            ");

            foreach ($rows as $row) {
                $stageKey = trim((string)($row['stage_key'] ?? ''));
                $total = (int)($row['total'] ?? 0);

                if (isset($counts[$stageKey])) {
                    $counts[$stageKey] = $total;
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
        $stageMap = lead_stage_map_ordered();

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
            $rows = db_all("
                SELECT status AS stage_key, SUM({$valueExpr}) AS total_value
                FROM leads
                WHERE status IS NOT NULL AND status <> ''" . lead_pipeline_visibility_sql('AND') . "
                GROUP BY status
            ");

            foreach ($rows as $row) {
                $stageKey = trim((string)($row['stage_key'] ?? ''));
                if (isset($values[$stageKey])) {
                    $values[$stageKey] = round((float)($row['total_value'] ?? 0), 2);
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
        $grouped = [];
        $stageMap = lead_stage_map_ordered();

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

        $limit = max(1, min(1000, $limit));
        $orderBy = leads_has_column('updated_at') ? 'updated_at DESC, id DESC' : 'id DESC';

        try {
            $rows = db_all("
                SELECT " . implode(', ', $selectFields) . "
                FROM leads" . lead_pipeline_visibility_sql('WHERE') . "
                ORDER BY {$orderBy}
                LIMIT {$limit}
            ");

            foreach ($rows as $lead) {
                $stageKey = lead_db_status_value($lead);

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

        foreach ([
            'full_name',
            'phone',
            'email',
            'procedure_interest',
            'source',
            'source_medium',
            'source_type',
            'landing_page',
            'campaign',
            'external_lead_id',
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
            lead_comm_insert_activity($leadId, 'duplicate_intake_refresh', 'Duplicate public intake refreshed this existing lead and moved it to the top of the board.', [
                'source' => 'lead_create_minimal',
                'duplicate_match_type' => (string)($duplicate['duplicate_match_type'] ?? ''),
            ], 'Intake');
        }
    }
}

if (!function_exists('lead_create_minimal')) {
    function lead_create_minimal(array $input, array $user = []): array
    {
        if (!leads_table_exists()) {
            return [
                'ok' => false,
                'message' => 'Leads table not found.',
                'lead_id' => 0,
            ];
        }

        $data = array_merge(lead_empty_record($user), $input);

        $data['full_name'] = trim((string)($data['full_name'] ?? ''));
        $data['phone'] = trim((string)($data['phone'] ?? ''));
        $data['email'] = strtolower(trim((string)($data['email'] ?? '')));
        $data['procedure_interest'] = trim((string)($data['procedure_interest'] ?? ''));
        $data['source'] = trim((string)($data['source'] ?? 'manual'));
        $data['source_medium'] = trim((string)($data['source_medium'] ?? ''));
        $data['source_type'] = trim((string)($data['source_type'] ?? ''));
        $data['landing_page'] = trim((string)($data['landing_page'] ?? ''));
        $data['campaign'] = trim((string)($data['campaign'] ?? ''));
        $data['external_lead_id'] = trim((string)($data['external_lead_id'] ?? ''));
        $data['status'] = trim((string)($data['status'] ?? lead_default_stage()));
        $data['assigned_to'] = trim((string)($data['assigned_to'] ?? lead_default_assigned_to($user)));
        $data['financing_needed'] = trim((string)($data['financing_needed'] ?? 'unsure'));
        $data['financing_option'] = trim((string)($data['financing_option'] ?? 'none'));
        $data['consultation_status'] = trim((string)($data['consultation_status'] ?? ''));
        $data['consultation_date'] = trim((string)($data['consultation_date'] ?? ''));
        if ($data['consultation_date'] !== '') {
            $consultationTimestamp = strtotime(str_replace('T', ' ', $data['consultation_date']));
            $data['consultation_date'] = $consultationTimestamp !== false ? date('Y-m-d H:i:s', $consultationTimestamp) : '';
        }
        $data['lead_value'] = trim((string)($data['lead_value'] ?? ''));
        $data['lost_reason'] = trim((string)($data['lost_reason'] ?? ''));
        $data['notes'] = trim((string)($data['notes'] ?? ''));

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
                    if (!empty($data['refresh_duplicate'])) {
                        lead_refresh_duplicate_from_input($duplicate, $data);
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
        if ($data['status'] !== '' && !isset($stageMap[$data['status']])) {
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
            $data['consultation_status'] = '';
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
            'consultation_status' => $data['consultation_status'] !== '' ? $data['consultation_status'] : null,
            'consultation_date' => $data['consultation_date'] !== '' ? $data['consultation_date'] : null,
            'lead_value' => $leadValue,
            'lost_reason' => $data['lost_reason'] !== '' ? $data['lost_reason'] : null,
            'notes' => $data['notes'],
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

            if ($leadId > 0 && function_exists('elite_send_lead_email_to_text_alert')) {
                try {
                    $alertLead = $data;
                    $alertLead['id'] = $leadId;
                    $alertLead['lead_value'] = $leadValue;
                    $alertLead['created_at'] = $candidateValues['created_at'];
                    elite_send_lead_email_to_text_alert($alertLead, [
                        'lead_id' => $leadId,
                        'created_at' => $candidateValues['created_at'],
                        'campaign' => $data['campaign'],
                        'landing_page' => $data['landing_page'],
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

            if ($leadId > 0 && function_exists('lead_email_maybe_send_first_touch')) {
                try {
                    lead_email_maybe_send_first_touch($leadId);
                } catch (Throwable $e) {
                    if (function_exists('esm_log')) {
                        esm_log('lead_email', 'Automatic first-touch email hook failed.', [
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
