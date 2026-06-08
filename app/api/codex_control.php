<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * Token-protected API for Codex/operator automation.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/leads/lead_meta.php';
require_once dirname(__DIR__) . '/leads/lead_service.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_email.php';
require_once dirname(__DIR__) . '/leads/lead_ai.php';
require_once dirname(__DIR__) . '/core/twilio.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!function_exists('codex_api_response')) {
    function codex_api_response(array $payload, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('codex_api_body')) {
    function codex_api_body(): array
    {
        static $body = null;
        if (is_array($body)) {
            return $body;
        }

        $raw = (string) file_get_contents('php://input');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
                return $body;
            }
        }

        $body = $_POST ?: $_GET;
        return is_array($body) ? $body : [];
    }
}

if (!function_exists('codex_api_value')) {
    function codex_api_value(string $key, mixed $default = null): mixed
    {
        $body = codex_api_body();
        if (array_key_exists($key, $body)) {
            return is_string($body[$key]) ? trim($body[$key]) : $body[$key];
        }
        if (array_key_exists($key, $_GET)) {
            return is_string($_GET[$key]) ? trim((string) $_GET[$key]) : $_GET[$key];
        }
        return $default;
    }
}

if (!function_exists('codex_api_token_from_request')) {
    function codex_api_token_from_request(): string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authorization = (string)($headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
            return trim($matches[1]);
        }

        $headerToken = (string)($headers['X-Elite-Codex-Token'] ?? $headers['x-elite-codex-token'] ?? $_SERVER['HTTP_X_ELITE_CODEX_TOKEN'] ?? '');
        if (trim($headerToken) !== '') {
            return trim($headerToken);
        }

        return trim((string) codex_api_value('token', ''));
    }
}

if (!function_exists('codex_api_auth')) {
    function codex_api_auth(): void
    {
        $expected = trim((string)(defined('ELITE_CODEX_API_TOKEN') ? ELITE_CODEX_API_TOKEN : ''));
        if ($expected === '') {
            codex_api_response(['ok' => false, 'message' => 'Codex API token is not configured.'], 503);
        }

        $provided = codex_api_token_from_request();
        if ($provided === '' || !hash_equals($expected, $provided)) {
            codex_api_response(['ok' => false, 'message' => 'Unauthorized.'], 401);
        }
    }
}

if (!function_exists('codex_api_public_lead_fields')) {
    function codex_api_public_lead_fields(): array
    {
        return [
            'id', 'full_name', 'first_name', 'last_name', 'email', 'phone',
            'preferred_contact', 'procedure_interest', 'source', 'source_medium',
            'source_type', 'landing_page', 'campaign', 'source_campaign',
            'source_ad_set', 'source_ad_name', 'source_post_id', 'source_post_label',
            'external_lead_id', 'instagram_username', 'trigger_keyword', 'status',
            'assigned_to', 'financing_needed', 'financing_option',
            'consultation_status', 'consultation_date', 'lead_value', 'lost_reason',
            'notes', 'sms_opt_status', 'email_opt_status', 'last_contacted_at',
            'last_inbound_at', 'last_outbound_at', 'unread_message_count',
            'next_follow_up_at', 'date_of_birth', 'scheduling_preferred_day',
            'scheduling_preferred_time', 'follow_up_status', 'last_follow_up_check_at',
            'created_at', 'updated_at',
        ];
    }
}

if (!function_exists('codex_api_select_fields')) {
    function codex_api_select_fields(): string
    {
        $fields = ['id'];
        foreach (codex_api_public_lead_fields() as $field) {
            if ($field !== 'id' && function_exists('leads_has_column') && leads_has_column($field)) {
                $fields[] = $field;
            }
        }
        return implode(', ', array_unique($fields));
    }
}

if (!function_exists('codex_api_load_lead')) {
    function codex_api_load_lead(int $leadId): array
    {
        if ($leadId <= 0) {
            codex_api_response(['ok' => false, 'message' => 'Invalid lead selected.'], 422);
        }

        $lead = db_one('SELECT ' . codex_api_select_fields() . ' FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$lead) {
            codex_api_response(['ok' => false, 'message' => 'Lead not found.'], 404);
        }

        return $lead;
    }
}

if (!function_exists('codex_api_timeline')) {
    function codex_api_timeline(int $leadId): array
    {
        $snapshot = lead_comm_snapshot($leadId);
        $emails = lead_email_recent($leadId, 30);
        $items = [];

        foreach (($snapshot['messages'] ?? []) as $message) {
            $items[] = [
                'type' => 'message',
                'channel' => (string)($message['channel'] ?? 'sms'),
                'direction' => (string)($message['direction'] ?? ''),
                'body' => (string)($message['body'] ?? ''),
                'status' => (string)($message['twilio_status'] ?? ''),
                'created_at' => (string)($message['created_at'] ?? ''),
                'raw' => $message,
            ];
        }

        foreach (($snapshot['activities'] ?? []) as $activity) {
            $items[] = [
                'type' => 'activity',
                'activity_type' => (string)($activity['type'] ?? ''),
                'body' => (string)($activity['body'] ?? ''),
                'created_by' => (string)($activity['created_by'] ?? ''),
                'created_at' => (string)($activity['created_at'] ?? ''),
                'raw' => $activity,
            ];
        }

        foreach ($emails as $email) {
            $items[] = [
                'type' => 'email',
                'direction' => (string)($email['direction'] ?? ''),
                'subject' => (string)($email['subject'] ?? ''),
                'body' => (string)($email['body'] ?? ''),
                'status' => (string)($email['status'] ?? ''),
                'created_by' => (string)($email['created_by'] ?? ''),
                'created_at' => (string)($email['created_at'] ?? ''),
                'opened_at' => (string)($email['opened_at'] ?? ''),
                'raw' => $email,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $timeA = strtotime((string)($a['created_at'] ?? '')) ?: 0;
            $timeB = strtotime((string)($b['created_at'] ?? '')) ?: 0;
            return $timeA <=> $timeB;
        });

        return [
            'items' => $items,
            'messages' => $snapshot['messages'] ?? [],
            'activities' => $snapshot['activities'] ?? [],
            'emails' => $emails,
        ];
    }
}

if (!function_exists('codex_api_list_leads')) {
    function codex_api_list_leads(): void
    {
        $limit = max(1, min(200, (int) codex_api_value('limit', 50)));
        $status = trim((string) codex_api_value('status', ''));
        $query = trim((string) codex_api_value('q', ''));
        $inboxOnly = filter_var(codex_api_value('inbox', false), FILTER_VALIDATE_BOOLEAN);

        $where = [];
        $params = [];

        if ($status !== '') {
            $allowedStages = lead_stage_labels();
            if (!isset($allowedStages[$status])) {
                codex_api_response(['ok' => false, 'message' => 'Stage is not allowed.'], 422);
            }
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($query !== '') {
            $where[] = '(full_name LIKE :query OR email LIKE :query OR phone LIKE :query OR campaign LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        if ($inboxOnly) {
            $parts = [];
            if (leads_has_column('unread_message_count')) {
                $parts[] = 'unread_message_count > 0';
            }
            if (leads_has_column('follow_up_status')) {
                $parts[] = "follow_up_status IN ('needs_follow_up', 'reply_received')";
            }
            if (leads_has_column('last_inbound_at')) {
                $parts[] = '(last_inbound_at IS NOT NULL AND (last_outbound_at IS NULL OR last_inbound_at > last_outbound_at))';
            }
            if ($parts) {
                $where[] = '(' . implode(' OR ', $parts) . ')';
            }
        }

        $sql = 'SELECT ' . codex_api_select_fields() . ' FROM leads';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY updated_at DESC, id DESC LIMIT ' . $limit;

        codex_api_response([
            'ok' => true,
            'leads' => db_all($sql, $params),
            'stages' => lead_stage_labels(),
        ]);
    }
}

if (!function_exists('codex_api_normalize_phone')) {
    function codex_api_normalize_phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }
        return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
    }
}

if (!function_exists('codex_api_duplicate_groups')) {
    function codex_api_duplicate_groups(): array
    {
        $leads = db_all('SELECT ' . codex_api_select_fields() . ' FROM leads ORDER BY updated_at DESC, id DESC');
        $sets = [];
        $seenSignatures = [];

        foreach (['email', 'phone'] as $field) {
            $buckets = [];
            foreach ($leads as $lead) {
                $key = $field === 'email'
                    ? strtolower(trim((string)($lead['email'] ?? '')))
                    : codex_api_normalize_phone((string)($lead['phone'] ?? ''));

                if ($field === 'email' && ($key === '' || !filter_var($key, FILTER_VALIDATE_EMAIL))) {
                    continue;
                }
                if ($field === 'phone' && strlen($key) < 10) {
                    continue;
                }

                $buckets[$key][] = $lead;
            }

            foreach ($buckets as $key => $groupLeads) {
                if (count($groupLeads) < 2) {
                    continue;
                }

                usort($groupLeads, static function (array $a, array $b): int {
                    $timeCompare = strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
                    if ($timeCompare !== 0) {
                        return $timeCompare;
                    }
                    return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
                });

                $ids = array_map(static fn (array $lead): int => (int)($lead['id'] ?? 0), $groupLeads);
                sort($ids);
                $signature = implode('-', $ids);
                if (isset($seenSignatures[$signature])) {
                    continue;
                }
                $seenSignatures[$signature] = true;

                $sets[] = [
                    'match_type' => $field,
                    'match_key' => $key,
                    'primary_id' => (int)($groupLeads[0]['id'] ?? 0),
                    'duplicate_ids' => array_values(array_filter(array_slice($ids, 0), static fn (int $id): bool => $id !== (int)($groupLeads[0]['id'] ?? 0))),
                    'leads' => $groupLeads,
                ];
            }
        }

        return $sets;
    }
}

if (!function_exists('codex_api_find_leads')) {
    function codex_api_find_leads(string $query, int $limit = 10): array
    {
        $query = trim($query);
        $limit = max(1, min(25, $limit));
        if ($query === '') {
            codex_api_response(['ok' => false, 'message' => 'Search query is required.'], 422);
        }

        $fields = codex_api_select_fields();
        $phone = codex_api_normalize_phone($query);
        $like = '%' . $query . '%';
        $params = [
            'exact_lower_case' => strtolower($query),
            'exact_lower_where' => strtolower($query),
            'exact_email' => $query,
            'exact_phone' => $query,
            'like_full_name_case' => $like,
            'like_full_name_where' => $like,
            'like_email_where' => $like,
            'like_phone_where' => $like,
        ];

        $where = [
            'LOWER(full_name) = :exact_lower_where',
            'full_name LIKE :like_full_name_where',
            'email LIKE :like_email_where',
            'phone LIKE :like_phone_where',
        ];

        if ($phone !== '') {
            $where[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', ''), '.', '') LIKE :phone_like";
            $params['phone_like'] = '%' . $phone . '%';
        }

        $sql = 'SELECT ' . $fields . ',
            CASE
                WHEN LOWER(full_name) = :exact_lower_case THEN 0
                WHEN email = :exact_email THEN 1
                WHEN phone = :exact_phone THEN 2
                WHEN full_name LIKE :like_full_name_case THEN 3
                ELSE 4
            END AS match_rank
            FROM leads
            WHERE ' . implode(' OR ', $where) . '
            ORDER BY match_rank ASC, updated_at DESC, id DESC
            LIMIT ' . $limit;

        $rows = db_all($sql, $params);
        foreach ($rows as &$row) {
            unset($row['match_rank']);
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('codex_api_resolve_lead_for_operator')) {
    function codex_api_resolve_lead_for_operator(): array
    {
        $leadId = (int) codex_api_value('lead_id', codex_api_value('id', 0));
        if ($leadId > 0) {
            return codex_api_load_lead($leadId);
        }

        $query = trim((string) codex_api_value('query', codex_api_value('name', '')));
        $matches = codex_api_find_leads($query, 8);
        if (!$matches) {
            codex_api_response(['ok' => false, 'message' => 'No matching lead found.', 'query' => $query], 404);
        }

        $exact = array_values(array_filter($matches, static function (array $lead) use ($query): bool {
            return strtolower(trim((string)($lead['full_name'] ?? ''))) === strtolower($query);
        }));

        if (count($exact) === 1) {
            return $exact[0];
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        codex_api_response([
            'ok' => false,
            'message' => 'Multiple matching leads found. Send lead_id to continue.',
            'query' => $query,
            'matches' => array_map(static function (array $lead): array {
                return [
                    'id' => (int)($lead['id'] ?? 0),
                    'full_name' => (string)($lead['full_name'] ?? ''),
                    'email' => (string)($lead['email'] ?? ''),
                    'phone' => (string)($lead['phone'] ?? ''),
                    'status' => (string)($lead['status'] ?? ''),
                    'updated_at' => (string)($lead['updated_at'] ?? ''),
                ];
            }, $matches),
        ], 409);
    }
}

if (!function_exists('codex_api_merge_leads')) {
    function codex_api_merge_leads(int $primaryId, array $duplicateIds, string $reason = 'Duplicate cleanup'): array
    {
        $primary = codex_api_load_lead($primaryId);
        $duplicateIds = array_values(array_unique(array_filter(array_map('intval', $duplicateIds), static fn (int $id): bool => $id > 0 && $id !== $primaryId)));
        if (!$duplicateIds) {
            codex_api_response(['ok' => false, 'message' => 'No duplicate lead IDs provided.'], 422);
        }

        $placeholders = [];
        $params = [];
        foreach ($duplicateIds as $index => $id) {
            $key = 'id' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $duplicates = db_all('SELECT ' . codex_api_select_fields() . ' FROM leads WHERE id IN (' . implode(',', $placeholders) . ')', $params);
        if (count($duplicates) !== count($duplicateIds)) {
            codex_api_response(['ok' => false, 'message' => 'One or more duplicate leads were not found.'], 404);
        }

        $mergeSummary = [];
        foreach ($duplicates as $duplicate) {
            $mergeSummary[] = '#' . (int)$duplicate['id'] . ' ' . trim((string)($duplicate['full_name'] ?? '')) . ' (' . trim((string)($duplicate['email'] ?? '')) . ', ' . trim((string)($duplicate['phone'] ?? '')) . ')';
        }

        $fillableFields = [
            'full_name', 'first_name', 'last_name', 'email', 'phone', 'preferred_contact',
            'procedure_interest', 'source', 'source_medium', 'source_type', 'landing_page',
            'campaign', 'source_campaign', 'source_ad_set', 'source_ad_name', 'source_post_id',
            'source_post_label', 'external_lead_id', 'instagram_username', 'trigger_keyword',
            'assigned_to', 'financing_needed', 'financing_option', 'consultation_status',
            'consultation_date', 'lead_value', 'lost_reason', 'next_follow_up_at',
            'date_of_birth', 'scheduling_preferred_day', 'scheduling_preferred_time',
            'follow_up_status',
        ];

        $updates = [];
        foreach ($fillableFields as $field) {
            if (!leads_has_column($field) || trim((string)($primary[$field] ?? '')) !== '') {
                continue;
            }
            foreach ($duplicates as $duplicate) {
                $value = trim((string)($duplicate[$field] ?? ''));
                if ($value !== '') {
                    $updates[$field] = $value;
                    break;
                }
            }
        }

        $noteParts = [];
        $existingPrimaryNotes = trim((string)($primary['notes'] ?? ''));
        if ($existingPrimaryNotes !== '') {
            $noteParts[] = $existingPrimaryNotes;
        }
        $noteParts[] = '[' . date('Y-m-d H:i') . '] Codex merge: ' . $reason . '. Merged duplicate lead(s): ' . implode('; ', $mergeSummary) . '.';
        foreach ($duplicates as $duplicate) {
            $duplicateNotes = trim((string)($duplicate['notes'] ?? ''));
            if ($duplicateNotes !== '') {
                $noteParts[] = "--- Notes from merged lead #" . (int)$duplicate['id'] . " ---\n" . $duplicateNotes;
            }
        }
        if (leads_has_column('notes')) {
            $updates['notes'] = implode("\n\n", $noteParts);
        }

        lead_comm_ensure_schema();
        lead_email_ensure_schema();

        db_begin();
        try {
            if ($updates) {
                $setParts = [];
                $updateParams = ['id' => $primaryId];
                foreach ($updates as $field => $value) {
                    $setParts[] = $field . ' = :' . $field;
                    $updateParams[$field] = $value;
                }
                if (leads_has_column('updated_at')) {
                    $setParts[] = 'updated_at = :updated_at';
                    $updateParams['updated_at'] = now();
                }
                db_query('UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1', $updateParams);
            }

            foreach (['lead_messages', 'lead_activities', 'lead_emails'] as $table) {
                try {
                    $exists = (bool) db_value(
                        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
                        ['table' => $table]
                    );
                    if ($exists) {
                        db_query('UPDATE ' . $table . ' SET lead_id = :primary_id WHERE lead_id IN (' . implode(',', $placeholders) . ')', array_merge(['primary_id' => $primaryId], $params));
                    }
                } catch (Throwable $e) {
                    // Optional communication tables should not block merging the lead card.
                }
            }

            lead_comm_insert_activity($primaryId, 'lead_merge', 'Merged duplicate lead card(s): ' . implode(', ', array_map(static fn (int $id): string => '#' . $id, $duplicateIds)) . '.', [
                'duplicate_ids' => $duplicateIds,
                'reason' => $reason,
                'source' => 'codex_api',
            ], 'Codex');

            db_query('DELETE FROM leads WHERE id IN (' . implode(',', $placeholders) . ')', $params);
            if (db()->inTransaction()) {
                db_commit();
            }
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db_rollBack();
            }
            throw $e;
        }

        return [
            'primary_id' => $primaryId,
            'merged_ids' => $duplicateIds,
            'lead' => codex_api_load_lead($primaryId),
        ];
    }
}

if (!function_exists('codex_api_follow_up_lead')) {
    function codex_api_follow_up_lead(): void
    {
        $lead = codex_api_resolve_lead_for_operator();
        $leadId = (int)($lead['id'] ?? 0);
        $channel = strtolower(trim((string) codex_api_value('channel', 'auto')));
        $createdBy = trim((string) codex_api_value('created_by', 'Codex'));
        $instruction = trim((string) codex_api_value('instruction', ''));
        $subject = trim((string) codex_api_value('subject', ''));
        $message = trim((string) codex_api_value('message', codex_api_value('body', '')));
        $note = trim((string) codex_api_value('note', ''));
        $status = trim((string) codex_api_value('status', ''));
        $nextFollowUpAt = trim((string) codex_api_value('next_follow_up_at', ''));
        $followUpStatus = trim((string) codex_api_value('follow_up_status', ''));
        $dryRun = filter_var(codex_api_value('dry_run', false), FILTER_VALIDATE_BOOLEAN);

        if (!in_array($channel, ['auto', 'email', 'sms', 'note'], true)) {
            codex_api_response(['ok' => false, 'message' => 'Channel must be auto, email, sms, or note.'], 422);
        }

        if ($channel === 'auto') {
            $channel = trim((string)($lead['email'] ?? '')) !== '' ? 'email' : 'sms';
        }

        $updates = [];
        if ($status !== '') {
            $allowedStages = lead_stage_labels();
            if (!isset($allowedStages[$status])) {
                codex_api_response(['ok' => false, 'message' => 'Stage is not allowed.', 'stages' => $allowedStages], 422);
            }
            $updates['status'] = $status;
        }
        if ($nextFollowUpAt !== '' && leads_has_column('next_follow_up_at')) {
            $timestamp = strtotime(str_replace('T', ' ', $nextFollowUpAt));
            $updates['next_follow_up_at'] = $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : $nextFollowUpAt;
        }
        if ($followUpStatus !== '' && leads_has_column('follow_up_status')) {
            $updates['follow_up_status'] = $followUpStatus;
        }

        if ($message === '' && $channel === 'email') {
            $ai = lead_ai_generate_email($lead, $instruction !== '' ? $instruction : 'Write a warm, professional follow-up email inviting the patient to schedule a free consultation with Dr. Meden.', 'operator_follow_up');
            if (empty($ai['ok'])) {
                codex_api_response(['ok' => false, 'message' => (string)($ai['message'] ?? 'AI email draft failed.'), 'lead_id' => $leadId], 502);
            }
            $draft = (array)($ai['data'] ?? []);
            $subject = $subject !== '' ? $subject : trim((string)($draft['subject'] ?? 'Your Elite Smiles consultation request'));
            $message = trim((string)($draft['body'] ?? ''));
            if ($note === '') {
                $note = trim((string)($draft['note'] ?? 'Codex generated and sent a follow-up email.'));
            }
        }

        if ($message === '' && $channel === 'sms') {
            $ai = lead_ai_generate_reply($lead, $instruction !== '' ? $instruction : 'Write a warm, concise SMS follow-up inviting the patient to schedule a free consultation with Dr. Meden.', 'operator_follow_up');
            if (empty($ai['ok'])) {
                codex_api_response(['ok' => false, 'message' => (string)($ai['message'] ?? 'AI SMS draft failed.'), 'lead_id' => $leadId], 502);
            }
            $draft = (array)($ai['data'] ?? []);
            $message = trim((string)($draft['reply'] ?? ''));
            if ($note === '') {
                $note = trim((string)($draft['note'] ?? 'Codex generated and sent a follow-up SMS.'));
            }
        }

        if ($channel !== 'note' && $message === '') {
            codex_api_response(['ok' => false, 'message' => 'Message body is required.'], 422);
        }

        if ($note === '') {
            $note = 'Codex follow-up through ' . strtoupper($channel) . '.';
        }

        if ($dryRun) {
            codex_api_response([
                'ok' => true,
                'dry_run' => true,
                'lead' => $lead,
                'channel' => $channel,
                'subject' => $subject,
                'message_body' => $message,
                'note' => $note,
                'planned_updates' => $updates,
                'thread' => codex_api_timeline($leadId),
            ]);
        }

        try {
            $sent = null;
            if ($channel === 'email') {
                if (!elite_smtp_is_configured()) {
                    codex_api_response(['ok' => false, 'message' => 'SMTP is not configured.', 'lead_id' => $leadId], 503);
                }
                if ($subject === '') {
                    $subject = 'Your Elite Smiles consultation request';
                }
                $sent = lead_email_send($leadId, $subject, $message, $createdBy);
                if (empty($sent['ok'])) {
                    codex_api_response(['ok' => false, 'message' => (string)($sent['message'] ?? 'Email failed.'), 'lead_id' => $leadId], 502);
                }
            } elseif ($channel === 'sms') {
                if (trim((string)($lead['sms_opt_status'] ?? 'unknown')) === 'opted_out') {
                    codex_api_response(['ok' => false, 'message' => 'This lead has opted out of SMS.', 'lead_id' => $leadId], 409);
                }
                $sendResult = elite_twilio_send_sms(trim((string)($lead['phone'] ?? '')), $message);
                if (empty($sendResult['ok'])) {
                    codex_api_response(['ok' => false, 'message' => (string)($sendResult['message'] ?? 'SMS failed.'), 'lead_id' => $leadId], 502);
                }
                $messageRecordId = lead_comm_insert_message([
                    'lead_id' => $leadId,
                    'direction' => 'outbound',
                    'channel' => 'sms',
                    'from_number' => (string)($sendResult['from'] ?? ''),
                    'to_number' => (string)($sendResult['to'] ?? $lead['phone'] ?? ''),
                    'body' => $message,
                    'twilio_message_sid' => (string)($sendResult['twilio_sid'] ?? ''),
                    'twilio_status' => (string)($sendResult['twilio_status'] ?? ''),
                    'is_read' => 1,
                ]);
                $sent = [
                    'ok' => true,
                    'message_id' => $messageRecordId,
                    'twilio_sid' => $sendResult['twilio_sid'] ?? '',
                    'to' => $sendResult['to'] ?? '',
                ];
                lead_comm_insert_activity($leadId, 'sms_outbound', 'Sent SMS through Codex operator API.', [
                    'message_id' => $messageRecordId,
                    'twilio_sid' => $sendResult['twilio_sid'] ?? '',
                    'source' => 'codex_operator_api',
                ], $createdBy);
                lead_comm_update_rollup($leadId);
            }

            lead_comm_insert_activity($leadId, 'operator_follow_up', $note, [
                'channel' => $channel,
                'source' => 'codex_operator_api',
                'instruction' => $instruction,
            ], $createdBy);

            if ($updates) {
                $setParts = [];
                $params = ['id' => $leadId];
                foreach ($updates as $field => $value) {
                    $placeholder = 'p_' . $field;
                    $setParts[] = '`' . $field . '` = :' . $placeholder;
                    $params[$placeholder] = $value;
                }
                if (leads_has_column('updated_at')) {
                    $setParts[] = 'updated_at = :updated_at';
                    $params['updated_at'] = now();
                }
                db_execute('UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1', $params);
            }

            codex_api_response([
                'ok' => true,
                'message' => 'Follow-up completed.',
                'lead_id' => $leadId,
                'channel' => $channel,
                'delivery' => $sent,
                'lead' => codex_api_load_lead($leadId),
                'thread' => codex_api_timeline($leadId),
            ]);
        } catch (Throwable $e) {
            throw $e;
        }
    }
}

if (!function_exists('codex_api_add_note')) {
    function codex_api_add_note(int $leadId, string $note, string $createdBy = 'Codex'): void
    {
        $lead = codex_api_load_lead($leadId);
        $note = trim($note);
        if ($note === '') {
            codex_api_response(['ok' => false, 'message' => 'Note cannot be empty.'], 422);
        }

        $activityId = lead_comm_insert_activity($leadId, 'internal_note', $note, ['source' => 'codex_api'], $createdBy);

        if (leads_has_column('notes')) {
            $existingNotes = trim((string)($lead['notes'] ?? ''));
            $auditLine = '[' . date('Y-m-d H:i') . '] ' . $createdBy . ': ' . $note;
            $updatedNotes = $existingNotes !== '' ? $existingNotes . "\n\n" . $auditLine : $auditLine;
            $setParts = ['notes = :notes'];
            $params = ['id' => $leadId, 'notes' => $updatedNotes];
            if (leads_has_column('updated_at')) {
                $setParts[] = 'updated_at = :updated_at';
                $params['updated_at'] = now();
            }
            db_execute('UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1', $params);
        }

        codex_api_response([
            'ok' => true,
            'message' => 'Note added.',
            'lead_id' => $leadId,
            'activity_id' => $activityId,
            'lead' => codex_api_load_lead($leadId),
            'thread' => codex_api_timeline($leadId),
        ]);
    }
}

if (!function_exists('codex_api_prepare_sms_followup')) {
    function codex_api_prepare_sms_followup(int $leadId, string $message, string $createdBy = 'Codex'): void
    {
        $lead = codex_api_load_lead($leadId);
        $message = trim($message);
        if ($message === '') {
            codex_api_response(['ok' => false, 'message' => 'SMS follow-up message cannot be empty.'], 422);
        }

        if (trim((string)($lead['sms_opt_status'] ?? 'unknown')) === 'opted_out') {
            codex_api_response(['ok' => false, 'message' => 'This lead has opted out of SMS.', 'lead_id' => $leadId], 409);
        }

        $context = ['lead_id' => $leadId];
        $recipient = trim((string) codex_api_value('to', ''));
        if ($recipient !== '') {
            $context['to'] = $recipient;
        }
        $result = elite_send_manual_sms_followup_email($lead, $message, $context);
        if (empty($result['ok'])) {
            codex_api_response([
                'ok' => false,
                'message' => (string)($result['message'] ?? 'Manual SMS action email failed.'),
                'lead_id' => $leadId,
            ], 502);
        }

        $activityId = lead_comm_insert_activity(
            $leadId,
            'manual_sms_followup_prepared',
            'Prepared manual SMS follow-up action email for Rod to review and send.',
            [
                'source' => 'codex_api',
                'recipient' => $result['to'] ?? '',
                'phone' => $result['phone'] ?? '',
                'sms_body' => $message,
            ],
            $createdBy
        );

        codex_api_response([
            'ok' => true,
            'message' => 'Manual SMS follow-up action email sent and logged.',
            'lead_id' => $leadId,
            'activity_id' => $activityId,
            'recipient' => $result['to'] ?? '',
            'phone' => $result['phone'] ?? '',
            'sms_link' => $result['sms_link'] ?? '',
            'thread' => codex_api_timeline($leadId),
        ]);
    }
}

if (!function_exists('codex_api_move_stage')) {
    function codex_api_move_stage(int $leadId, string $newStage): void
    {
        $lead = codex_api_load_lead($leadId);
        $allowedStages = lead_stage_labels();
        if (!isset($allowedStages[$newStage])) {
            codex_api_response(['ok' => false, 'message' => 'Stage is not allowed.', 'stages' => $allowedStages], 422);
        }

        $oldStage = trim((string)($lead['status'] ?? ''));
        $setParts = ['status = :status'];
        $params = ['id' => $leadId, 'status' => $newStage];
        if (leads_has_column('updated_at')) {
            $setParts[] = 'updated_at = :updated_at';
            $params['updated_at'] = now();
        }

        db_execute('UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1', $params);
        if ($oldStage !== $newStage) {
            lead_comm_insert_activity(
                $leadId,
                'stage_change',
                'Moved stage from ' . ($allowedStages[$oldStage] ?? ($oldStage !== '' ? $oldStage : 'Unstaged')) . ' to ' . ($allowedStages[$newStage] ?? $newStage) . '.',
                ['from' => $oldStage, 'to' => $newStage, 'source' => 'codex_api'],
                'Codex'
            );
        }

        codex_api_response([
            'ok' => true,
            'message' => 'Lead stage updated.',
            'lead_id' => $leadId,
            'status' => $newStage,
            'status_label' => $allowedStages[$newStage],
            'lead' => codex_api_load_lead($leadId),
        ]);
    }
}

if (!function_exists('codex_api_update_lead')) {
    function codex_api_update_lead(int $leadId, array $fields): void
    {
        $lead = codex_api_load_lead($leadId);
        $allowedFields = [
            'full_name', 'phone', 'email', 'preferred_contact', 'procedure_interest',
            'source', 'source_medium', 'source_type', 'landing_page', 'campaign',
            'assigned_to', 'financing_needed', 'financing_option', 'consultation_status',
            'consultation_date', 'lead_value', 'lost_reason', 'next_follow_up_at',
            'date_of_birth', 'scheduling_preferred_day', 'scheduling_preferred_time',
            'follow_up_status',
        ];

        $update = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $fields) && leads_has_column($field)) {
                $update[$field] = is_string($fields[$field]) ? trim($fields[$field]) : $fields[$field];
            }
        }

        if (!$update) {
            codex_api_response(['ok' => false, 'message' => 'No supported fields provided.'], 422);
        }

        $duplicateProbe = [
            'phone' => (string)($update['phone'] ?? $lead['phone'] ?? ''),
            'email' => (string)($update['email'] ?? $lead['email'] ?? ''),
            'external_lead_id' => (string)($update['external_lead_id'] ?? $lead['external_lead_id'] ?? ''),
        ];
        $duplicate = lead_find_duplicate($duplicateProbe, $leadId);
        if ($duplicate) {
            codex_api_response([
                'ok' => false,
                'message' => lead_duplicate_message($duplicate),
                'duplicate_found' => true,
                'duplicate_lead_id' => (int)($duplicate['id'] ?? 0),
                'duplicate_match_type' => (string)($duplicate['duplicate_match_type'] ?? ''),
            ], 409);
        }

        if (isset($update['email'])) {
            $update['email'] = strtolower((string)$update['email']);
            if ($update['email'] !== '' && !filter_var($update['email'], FILTER_VALIDATE_EMAIL)) {
                codex_api_response(['ok' => false, 'message' => 'Please provide a valid email address.'], 422);
            }
        }

        $stageLabels = lead_stage_labels();
        if (isset($update['financing_needed']) && !isset(lead_financing_needed_options()[$update['financing_needed']])) {
            $update['financing_needed'] = 'unsure';
        }
        if (isset($update['financing_option']) && !array_key_exists((string)$update['financing_option'], lead_financing_option_labels())) {
            $update['financing_option'] = 'none';
        }
        unset($stageLabels);

        $setParts = [];
        $params = ['id' => $leadId];
        foreach ($update as $field => $value) {
            $placeholder = 'p_' . $field;
            $setParts[] = '`' . $field . '` = :' . $placeholder;
            $params[$placeholder] = $value;
        }
        if (leads_has_column('updated_at')) {
            $setParts[] = 'updated_at = :updated_at';
            $params['updated_at'] = now();
        }

        db_execute('UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1', $params);
        lead_comm_insert_activity($leadId, 'lead_updated', 'Lead details updated through Codex API.', [
            'fields' => array_keys($update),
            'source' => 'codex_api',
        ], 'Codex');

        codex_api_response([
            'ok' => true,
            'message' => 'Lead updated.',
            'lead_id' => $leadId,
            'lead' => codex_api_load_lead($leadId),
        ]);
    }
}

codex_api_auth();

if (!leads_table_exists()) {
    codex_api_response(['ok' => false, 'message' => 'Leads table not found.'], 500);
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = trim((string) codex_api_value('action', $method === 'GET' ? 'health' : ''));

try {
    if ($action === 'health') {
        codex_api_response([
            'ok' => true,
            'service' => 'elite-smiles-codex-api',
            'time' => now(),
            'stages' => lead_stage_labels(),
            'smtp_configured' => function_exists('elite_smtp_is_configured') ? elite_smtp_is_configured() : false,
            'twilio_configured' => defined('TWILIO_ACCOUNT_SID') && TWILIO_ACCOUNT_SID !== '' && defined('TWILIO_AUTH_TOKEN') && TWILIO_AUTH_TOKEN !== '',
        ]);
    }

    if ($action === 'stages') {
        codex_api_response(['ok' => true, 'stages' => lead_stage_labels()]);
    }

    if ($action === 'find_duplicates') {
        codex_api_response(['ok' => true, 'duplicate_groups' => codex_api_duplicate_groups()]);
    }

    if ($action === 'find_lead' || $action === 'search_leads') {
        codex_api_response([
            'ok' => true,
            'query' => trim((string) codex_api_value('query', codex_api_value('name', codex_api_value('q', '')))),
            'leads' => codex_api_find_leads(trim((string) codex_api_value('query', codex_api_value('name', codex_api_value('q', '')))), (int) codex_api_value('limit', 10)),
        ]);
    }

    if ($action === 'list_leads' || $action === 'inbox') {
        if ($action === 'inbox' && !array_key_exists('inbox', codex_api_body()) && !array_key_exists('inbox', $_GET)) {
            $_GET['inbox'] = '1';
        }
        codex_api_list_leads();
    }

    if ($action === 'get_lead') {
        $leadId = (int) codex_api_value('lead_id', codex_api_value('id', 0));
        codex_api_response([
            'ok' => true,
            'lead' => codex_api_load_lead($leadId),
            'thread' => codex_api_timeline($leadId),
        ]);
    }

    if ($action === 'get_thread') {
        $leadId = (int) codex_api_value('lead_id', codex_api_value('id', 0));
        codex_api_load_lead($leadId);
        codex_api_response([
            'ok' => true,
            'lead_id' => $leadId,
            'thread' => codex_api_timeline($leadId),
        ]);
    }

    if ($method !== 'POST') {
        codex_api_response(['ok' => false, 'message' => 'Use POST for write actions.'], 405);
    }

    if ($action === 'follow_up_lead' || $action === 'operator_follow_up') {
        codex_api_follow_up_lead();
    }

    if ($action === 'create_lead') {
        $fields = (array) codex_api_value('lead', codex_api_body());
        $fields['source'] = trim((string)($fields['source'] ?? 'codex_api'));
        $result = lead_create_minimal($fields, ['first_name' => 'Codex', 'last_name' => 'API']);
        if (empty($result['ok'])) {
            codex_api_response(['ok' => false, 'message' => (string)($result['message'] ?? 'Lead creation failed.')], 422);
        }
        if (!empty($result['duplicate_found'])) {
            codex_api_response([
                'ok' => true,
                'duplicate_found' => true,
                'message' => (string)$result['message'],
                'lead_id' => (int)$result['lead_id'],
                'duplicate_match_type' => (string)($result['duplicate_match_type'] ?? ''),
                'lead' => codex_api_load_lead((int)$result['lead_id']),
            ], 200);
        }
        $leadId = (int)($result['lead_id'] ?? 0);
        lead_comm_insert_activity($leadId, 'lead_created', 'Lead created through Codex API.', ['source' => 'codex_api'], 'Codex');
        codex_api_response(['ok' => true, 'message' => 'Lead created.', 'lead_id' => $leadId, 'lead' => codex_api_load_lead($leadId)], 201);
    }

    if ($action === 'add_note') {
        codex_api_add_note((int) codex_api_value('lead_id', 0), (string) codex_api_value('note', ''), (string) codex_api_value('created_by', 'Codex'));
    }

    if ($action === 'prepare_sms_followup' || $action === 'manual_sms_followup') {
        codex_api_prepare_sms_followup(
            (int) codex_api_value('lead_id', 0),
            (string) codex_api_value('message', codex_api_value('body', '')),
            (string) codex_api_value('created_by', 'Codex')
        );
    }

    if ($action === 'move_stage') {
        codex_api_move_stage((int) codex_api_value('lead_id', 0), trim((string) codex_api_value('status', '')));
    }

    if ($action === 'update_lead') {
        codex_api_update_lead((int) codex_api_value('lead_id', 0), (array) codex_api_value('fields', []));
    }

    if ($action === 'merge_leads') {
        $result = codex_api_merge_leads(
            (int) codex_api_value('primary_id', 0),
            (array) codex_api_value('duplicate_ids', []),
            trim((string) codex_api_value('reason', 'Duplicate cleanup'))
        );
        codex_api_response(['ok' => true, 'message' => 'Duplicate leads merged.', 'merge' => $result]);
    }

    if ($action === 'merge_all_duplicates') {
        $groups = codex_api_duplicate_groups();
        $merged = [];
        foreach ($groups as $group) {
            $duplicateIds = array_values(array_filter(array_map('intval', (array)($group['duplicate_ids'] ?? []))));
            if (!$duplicateIds) {
                continue;
            }
            $alreadyMerged = [];
            foreach ($duplicateIds as $duplicateId) {
                if (db_one('SELECT id FROM leads WHERE id = :id LIMIT 1', ['id' => $duplicateId])) {
                    $alreadyMerged[] = $duplicateId;
                }
            }
            if (!$alreadyMerged) {
                continue;
            }
            $primaryId = (int)($group['primary_id'] ?? 0);
            if ($primaryId > 0 && db_one('SELECT id FROM leads WHERE id = :id LIMIT 1', ['id' => $primaryId])) {
                $merged[] = codex_api_merge_leads($primaryId, $alreadyMerged, 'Automatic duplicate cleanup by Codex API');
            }
        }
        codex_api_response(['ok' => true, 'message' => 'Duplicate cleanup complete.', 'merged' => $merged, 'remaining_duplicate_groups' => codex_api_duplicate_groups()]);
    }

    if ($action === 'draft_email') {
        $leadId = (int) codex_api_value('lead_id', 0);
        $lead = codex_api_load_lead($leadId);
        if (trim((string)($lead['email'] ?? '')) === '') {
            codex_api_response(['ok' => false, 'message' => 'Add a lead email address before drafting.'], 422);
        }
        $result = lead_ai_generate_email($lead, trim((string) codex_api_value('instruction', '')), trim((string) codex_api_value('mode', 'email_draft')));
        if (empty($result['ok'])) {
            codex_api_response(['ok' => false, 'message' => (string)($result['message'] ?? 'AI email draft failed.')], 502);
        }
        lead_comm_insert_activity($leadId, 'ai_email_draft', 'Codex API drafted an email for review.', [
            'classification' => $result['data']['classification'] ?? '',
            'confidence' => $result['data']['confidence'] ?? 0,
            'source' => 'codex_api',
        ], 'OpenAI');
        codex_api_response(['ok' => true, 'lead_id' => $leadId, 'draft' => $result['data']]);
    }

    if ($action === 'send_email') {
        $leadId = (int) codex_api_value('lead_id', 0);
        codex_api_load_lead($leadId);
        if (!elite_smtp_is_configured()) {
            codex_api_response(['ok' => false, 'message' => 'SMTP is not configured.'], 503);
        }
        $result = lead_email_send($leadId, (string) codex_api_value('subject', ''), (string) codex_api_value('body', ''), (string) codex_api_value('created_by', 'Codex'));
        if (empty($result['ok'])) {
            codex_api_response(['ok' => false, 'message' => (string)($result['message'] ?? 'Email failed.'), 'lead_id' => $leadId], 502);
        }
        codex_api_response(['ok' => true, 'message' => 'Email sent and logged.', 'lead_id' => $leadId, 'email_id' => (int)($result['email_id'] ?? 0), 'thread' => codex_api_timeline($leadId)]);
    }

    if ($action === 'send_sms') {
        $leadId = (int) codex_api_value('lead_id', 0);
        $lead = codex_api_load_lead($leadId);
        $message = trim((string) codex_api_value('message', ''));
        if ($message === '') {
            codex_api_response(['ok' => false, 'message' => 'Message cannot be empty.'], 422);
        }
        if (trim((string)($lead['sms_opt_status'] ?? 'unknown')) === 'opted_out') {
            codex_api_response(['ok' => false, 'message' => 'This lead has opted out of SMS.', 'lead_id' => $leadId], 409);
        }
        $sendResult = elite_twilio_send_sms(trim((string)($lead['phone'] ?? '')), $message);
        if (empty($sendResult['ok'])) {
            codex_api_response(['ok' => false, 'message' => (string)($sendResult['message'] ?? 'SMS failed.'), 'lead_id' => $leadId], 502);
        }
        $messageRecordId = lead_comm_insert_message([
            'lead_id' => $leadId,
            'direction' => 'outbound',
            'channel' => 'sms',
            'from_number' => (string)($sendResult['from'] ?? ''),
            'to_number' => (string)($sendResult['to'] ?? $lead['phone'] ?? ''),
            'body' => $message,
            'twilio_message_sid' => (string)($sendResult['twilio_sid'] ?? ''),
            'twilio_status' => (string)($sendResult['twilio_status'] ?? ''),
            'is_read' => 1,
        ]);
        lead_comm_insert_activity($leadId, 'sms_outbound', 'Sent SMS through Codex API.', [
            'message_id' => $messageRecordId,
            'twilio_sid' => $sendResult['twilio_sid'] ?? '',
            'source' => 'codex_api',
        ], 'Codex');
        lead_comm_update_rollup($leadId);
        codex_api_response(['ok' => true, 'message' => 'SMS sent and logged.', 'lead_id' => $leadId, 'thread' => codex_api_timeline($leadId)]);
    }

    codex_api_response(['ok' => false, 'message' => 'Unknown action.'], 404);
} catch (Throwable $e) {
    esm_log('codex_api', 'Codex API request failed.', [
        'action' => $action,
        'error' => $e->getMessage(),
    ]);
    codex_api_response(['ok' => false, 'message' => 'Codex API request failed.'], 500);
}
