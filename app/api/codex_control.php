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
require_once dirname(__DIR__) . '/core/mobile_ai_auth.php';
require_once dirname(__DIR__) . '/leads/lead_meta.php';
require_once dirname(__DIR__) . '/leads/lead_service.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_email.php';
require_once dirname(__DIR__) . '/leads/lead_ai.php';
require_once dirname(__DIR__) . '/ai/elite_ai_service.php';
require_once dirname(__DIR__) . '/core/mailer.php';
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

if (!function_exists('codex_api_has_explicit_send_approval')) {
    function codex_api_has_explicit_send_approval(array $request): bool
    {
        if (filter_var($request['send_approved'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        if (filter_var($request['send_approval'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        if (filter_var($request['send_now'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        if (filter_var($request['approve_send'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        $executionMode = strtolower(trim((string) ($request['execution_mode'] ?? '')));
        if (in_array($executionMode, ['send', 'send_now', 'send_approved', 'send_approval', 'send-approved'], true)) {
            return true;
        }

        $instruction = strtolower(trim((string) ($request['instruction'] ?? '')));
        if ($instruction === '') {
            return false;
        }

        $normalizedInstruction = preg_replace('/[^a-z0-9\\s]+/i', ' ', $instruction);
        $normalizedInstruction = trim((string) preg_replace('/\\s+/', ' ', $normalizedInstruction));

        if ((bool) preg_match('/\\bsend\\s+the\\s+approved\\s+(?:sms|text|email)\\s+drafts?\\b/i', $normalizedInstruction)) {
            return true;
        }

        return (bool) preg_match(
            '/\\b(?:send|dispatch|deliver)\\b(?:\\s+(?:all|the|these|approved)\\s*)?(?:sms|text|email)\\b|\\b(?:send|dispatch)\\s+the\\s+(?:approved\\s+)?drafts?\\b|\\bsend\\s+(?:all|the)\\s+(?:approved\\s+)?(?:sms|email)\\b|\\bsend\\s+(?:all|the|these)\\s+drafts?\\s+now\\b/i',
            $normalizedInstruction
        );
    }
}

if (!function_exists('codex_api_has_explicit_stage_approval')) {
    function codex_api_has_explicit_stage_approval(array $request): bool
    {
        if (filter_var($request['stage_approved'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        if (filter_var($request['stage_approval'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        $executionMode = strtolower(trim((string) ($request['execution_mode'] ?? '')));
        if (in_array($executionMode, ['stage', 'stage_approved', 'stage_approval', 'move_stage'], true)) {
            return true;
        }
        $instruction = strtolower(trim((string) ($request['instruction'] ?? '')));
        if ($instruction === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b(?:move|advance|set|change|shift)\s+(?:lead|card|lead\s+to|them|them\s+to|it|it\s+to|this\s+lead\s+to)?\s*(?:stage|status|pipeline)\b|\b(?:move|set|advance|change)\s+(?:this|the|lead|leads|them)?\s*(?:to|into)\s+(?:new[_ ]?lead|contacted|in[_ ]?contact|follow[_ ]?up[_ ]?needed|follow[_ ]?up|scheduling|consultation[_ ]?booked|consultation[_ ]?completed|no[_ ]?show|reschedule|no[_ ]?show[_ ]?reschedule|treatment[_ ]?accepted|no[_ ]?answer|nurture|lost)\b|\b(?:change|set)\s+lead\s+status\b/i',
            $instruction
        );
    }
}

if (!function_exists('codex_api_text_excerpt')) {
    function codex_api_text_excerpt(string $text, int $limit = 180): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if ($text === '' || strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(substr($text, 0, max(0, $limit - 1))) . '...';
    }
}

if (!function_exists('codex_api_notification_assistant_card')) {
    function codex_api_notification_assistant_card(array $row, string $type = 'reply'): array
    {
        $leadId = (int) ($row['lead_id'] ?? 0);
        $leadName = trim((string) ($row['full_name'] ?? $row['lead_name'] ?? 'Lead'));
        $body = trim((string) ($row['body'] ?? $row['message'] ?? ''));
        $bodyLower = strtolower($body);
        $summary = $type === 'reply'
            ? 'New reply from ' . ($leadName !== '' ? $leadName : 'Lead') . ($leadId > 0 ? ' #' . $leadId : '') . ': "' . codex_api_text_excerpt($body, 120) . '"'
            : 'CRM event for ' . ($leadName !== '' ? $leadName : 'Lead') . ($leadId > 0 ? ' #' . $leadId : '') . ': ' . codex_api_text_excerpt($body, 120);

        $recommended = 'Review the lead context and prepare a draft before any patient-facing send.';
        $intent = 'review_context';
        $safeActions = [
            ['key' => 'open_lead', 'label' => 'Open lead', 'requires_approval' => false],
            ['key' => 'draft_reply', 'label' => 'Draft reply', 'requires_approval' => true],
            ['key' => 'mark_reviewed', 'label' => 'Mark reviewed', 'requires_approval' => false],
        ];

        if ((bool) preg_match('/\b(?:dob|date\s+of\s+birth|birth\s*date)\b|\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/', $bodyLower)) {
            $intent = 'possible_dob';
            $recommended = 'This may contain a DOB. Verify it, save it internally, then confirm the appointment only with an approved draft.';
            $safeActions = [
                ['key' => 'open_lead', 'label' => 'Open lead', 'requires_approval' => false],
                ['key' => 'save_dob_review', 'label' => 'Review/save DOB', 'requires_approval' => false],
                ['key' => 'draft_confirmation', 'label' => 'Draft confirmation', 'requires_approval' => true],
                ['key' => 'mark_reviewed', 'label' => 'Mark reviewed', 'requires_approval' => false],
            ];
        } elseif ((bool) preg_match('/\b(?:yes|works|available|morning|afternoon|pm|am|monday|tuesday|wednesday|thursday|friday|saturday|sunday|tomorrow|today|july|jun|june)\b/', $bodyLower)) {
            $intent = 'scheduling_reply';
            $recommended = 'Likely scheduling intent. Check calendar/appointment context, update internally if approved, and draft the confirmation.';
            $safeActions = [
                ['key' => 'open_lead', 'label' => 'Open lead', 'requires_approval' => false],
                ['key' => 'schedule_review', 'label' => 'Review schedule', 'requires_approval' => false],
                ['key' => 'draft_confirmation', 'label' => 'Draft confirmation', 'requires_approval' => true],
                ['key' => 'mark_reviewed', 'label' => 'Mark reviewed', 'requires_approval' => false],
            ];
        } elseif ((bool) preg_match('/\b(?:thanks|thank you|ok|okay|gracias|perfect|sounds good)\b/', $bodyLower)) {
            $intent = 'acknowledgement';
            $recommended = 'Looks like an acknowledgement. Usually mark reviewed unless context shows a follow-up is still needed.';
            $safeActions = [
                ['key' => 'open_lead', 'label' => 'Open lead', 'requires_approval' => false],
                ['key' => 'mark_reviewed', 'label' => 'Mark reviewed', 'requires_approval' => false],
            ];
        } elseif ((bool) preg_match('/\b(?:price|cost|financ|payment|down|credit|monthly|insurance)\b/', $bodyLower)) {
            $intent = 'financing_or_cost';
            $recommended = 'Cost/financing question. Prepare a warm finance-aware draft, but do not send until approved.';
        }

        return [
            'intent' => $intent,
            'summary' => $summary,
            'recommended_action' => $recommended,
            'draft_before_send_required' => true,
            'send_requires_explicit_approval' => true,
            'safe_actions' => $safeActions,
        ];
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
        $request = (array) codex_api_body();
        $sendApproved = codex_api_has_explicit_send_approval($request);
        $stageApproved = codex_api_has_explicit_stage_approval($request);
        $requestedStatus = '';
        $blockedSend = false;
        $blockedStatus = false;
        $channel = strtolower(trim((string) codex_api_value('channel', 'auto')));
        $createdBy = trim((string) codex_api_value('created_by', 'Codex'));
        $instruction = trim((string) codex_api_value('instruction', ''));
        $subject = trim((string) codex_api_value('subject', ''));
        $message = trim((string) codex_api_value('message', codex_api_value('body', '')));
        $note = trim((string) codex_api_value('note', ''));
        $status = trim((string) codex_api_value('status', ''));
        $nextFollowUpAt = trim((string) codex_api_value('next_follow_up_at', ''));
        $followUpStatus = trim((string) codex_api_value('follow_up_status', ''));
        $notifyOperator = filter_var(codex_api_value('notify_operator', false), FILTER_VALIDATE_BOOLEAN);
        $notifyMode = trim((string) codex_api_value('notify_mode', ''));
        $dryRun = filter_var(codex_api_value('dry_run', false), FILTER_VALIDATE_BOOLEAN);

        if (!in_array($channel, ['auto', 'email', 'sms', 'note'], true)) {
            codex_api_response(['ok' => false, 'message' => 'Channel must be auto, email, sms, or note.'], 422);
        }

        if ($channel !== 'note' && !$sendApproved) {
            $blockedSend = true;
            $channel = 'note';
        }

        if ($channel === 'auto') {
            $channel = trim((string)($lead['email'] ?? '')) !== '' ? 'email' : 'sms';
            if (!$sendApproved && $channel !== 'note') {
                $blockedSend = true;
                $channel = 'note';
            }
        }

        $updates = [];
        if ($status !== '') {
            $requestedStatus = $status;
            if (!$stageApproved) {
                $blockedStatus = true;
            } else {
                $allowedStages = lead_stage_labels();
                if (!isset($allowedStages[$status])) {
                    codex_api_response(['ok' => false, 'message' => 'Stage is not allowed.', 'stages' => $allowedStages], 422);
                }
                $updates['status'] = $status;
            }
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
            $dryRunNotes = [];
            if ($blockedSend) {
                $dryRunNotes[] = 'Communication send blocked until explicit send approval is provided.';
            }
            if ($blockedStatus) {
                $dryRunNotes[] = 'Stage change blocked until explicit stage approval is provided.';
            }
            codex_api_response([
                'ok' => true,
                'dry_run' => true,
                'lead' => $lead,
                'channel' => $channel,
                'subject' => $subject,
                'message_body' => $message,
                'note' => $note,
                'execution_mode' => [
                    'send_approved' => $sendApproved,
                    'stage_approved' => $stageApproved,
                    'blocked_send' => $blockedSend,
                    'blocked_status' => $blockedStatus,
                    'requested_status' => $requestedStatus ?: null,
                    'messages' => $dryRunNotes,
                ],
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
                $sendResult = elite_twilio_send_sms(trim((string)($lead['phone'] ?? '')), $message, [
                    'lead_id' => $leadId,
                    'lead' => $lead,
                    'send_pushover_fallback' => true,
                    'fallback_summary' => 'Twilio could not send the operator SMS. Open lead actions to retry manually.',
                    'original_body' => $message,
                ]);
                if (empty($sendResult['ok'])) {
                    codex_api_response([
                        'ok' => false,
                        'message' => (string)($sendResult['message'] ?? 'SMS failed.'),
                        'lead_id' => $leadId,
                        'operator_fallback_sent' => (bool)($sendResult['operator_fallback_sent'] ?? false),
                    ], 502);
                }
                $sentBody = (string)($sendResult['body'] ?? $message);
                $messageRecordId = lead_comm_insert_message([
                    'lead_id' => $leadId,
                    'direction' => 'outbound',
                    'channel' => 'sms',
                    'from_number' => (string)($sendResult['from'] ?? ''),
                    'to_number' => (string)($sendResult['to'] ?? $lead['phone'] ?? ''),
                    'body' => $sentBody,
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

            $updatedLead = codex_api_load_lead($leadId);
            $operatorNotificationSent = false;
            if ($notifyOperator && function_exists('elite_send_operator_follow_up_pushover')) {
                $notificationContext = [
                    'event' => 'follow_up',
                    'channel' => $channel,
                    'note' => $note,
                    'summary' => 'Follow-up completed. Tap to open lead actions and continue the conversation.',
                ];
                if ($notifyMode !== '') {
                    $notificationContext['quick_action_mode'] = $notifyMode;
                }
                $operatorNotificationSent = elite_send_operator_follow_up_pushover($updatedLead ?: $lead, $notificationContext);
            }

            $executionNotes = [];
            if ($blockedSend) {
                $executionNotes[] = 'Communication send was blocked by default safety policy; explicit send approval required.';
            }
            if ($blockedStatus) {
                $executionNotes[] = 'Stage change was blocked by default safety policy; explicit stage approval required.';
            }

            codex_api_response([
                'ok' => true,
                'message' => 'Follow-up completed.',
                'lead_id' => $leadId,
                'channel' => $channel,
                'delivery' => $sent,
                'lead' => $updatedLead,
                'execution_mode' => [
                    'send_approved' => $sendApproved,
                    'stage_approved' => $stageApproved,
                    'blocked_send' => $blockedSend,
                    'blocked_status' => $blockedStatus,
                    'requested_status' => $requestedStatus ?: null,
                    'messages' => $executionNotes,
                ],
                'operator_notification_sent' => $operatorNotificationSent,
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

        if (!function_exists('elite_send_manual_sms_followup_email')) {
            codex_api_response([
                'ok' => false,
                'message' => 'Manual text notification helper is unavailable.',
                'lead_id' => $leadId,
            ], 503);
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
        $request = (array) codex_api_body();
        $stageApproved = codex_api_has_explicit_stage_approval($request);
        if (!$stageApproved) {
            codex_api_response([
                'ok' => false,
                'message' => 'Stage changes require explicit stage approval.',
                'approval_required' => 'stage_approved',
                'lead_id' => $leadId,
            ], 409);
        }

        $lead = codex_api_load_lead($leadId);
        if (function_exists('lead_pipeline_ensure_schema')) {
            lead_pipeline_ensure_schema();
        }
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
    function codex_api_normalize_nullable_date_field(mixed $value, bool $dateOnly = false): ?string
    {
        $raw = trim((string)$value);
        if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
            return null;
        }

        $formats = $dateOnly
            ? ['Y-m-d', 'm/d/Y', 'n/j/Y', 'Y-m-d H:i:s', 'Y-m-d\TH:i']
            : ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d'];

        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $raw);
            if ($dt instanceof DateTime) {
                return $dateOnly ? $dt->format('Y-m-d') : $dt->format('Y-m-d H:i:s');
            }
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return $dateOnly ? date('Y-m-d', $timestamp) : date('Y-m-d H:i:s', $timestamp);
    }

    function codex_api_update_lead(int $leadId, array $fields): void
    {
        if (function_exists('lead_pipeline_ensure_schema')) {
            lead_pipeline_ensure_schema();
        }

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

        foreach (['consultation_date', 'next_follow_up_at'] as $dateTimeField) {
            if (array_key_exists($dateTimeField, $update)) {
                $update[$dateTimeField] = codex_api_normalize_nullable_date_field($update[$dateTimeField], false);
            }
        }
        if (array_key_exists('date_of_birth', $update)) {
            $update['date_of_birth'] = codex_api_normalize_nullable_date_field($update['date_of_birth'], true);
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

if (!function_exists('codex_api_mobile_notifications')) {
    function codex_api_mobile_notifications(): void
    {
        lead_comm_ensure_schema();
        $limit = max(1, min(50, (int) codex_api_value('limit', 20)));
        $notifications = [];
        $dedupeKeys = [];
        $messageLimit = min(75, max(10, $limit * 4));
        $activityLimit = min(50, max(10, $limit * 2));

        $messages = db_all(
            "SELECT
                lm.id,
                lm.lead_id,
                lm.body,
                lm.is_read,
                lm.created_at,
                l.full_name,
                l.status
             FROM lead_messages lm
             INNER JOIN leads l ON l.id = lm.lead_id
             WHERE lm.direction = 'inbound'
               AND lm.is_read = 0
               AND lm.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
             ORDER BY lm.created_at DESC, lm.id DESC
             LIMIT {$messageLimit}"
        );

        foreach ($messages as $row) {
            $leadId = (int) ($row['lead_id'] ?? 0);
            $dedupeKey = 'reply:' . $leadId;
            if ($leadId <= 0 || isset($dedupeKeys[$dedupeKey])) {
                continue;
            }
            $dedupeKeys[$dedupeKey] = true;
            $assistantCard = codex_api_notification_assistant_card($row, 'reply');
            $notifications[] = [
                'id' => 'msg-' . (int) ($row['id'] ?? 0),
                'type' => 'reply',
                'title' => 'Reply from ' . (trim((string) ($row['full_name'] ?? 'Lead')) ?: 'Lead'),
                'message' => trim((string) ($row['body'] ?? '')),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'priority' => ((int) ($row['is_read'] ?? 0) === 0) ? 'high' : 'normal',
                'is_new' => (int) ($row['is_read'] ?? 0) === 0,
                'lead_id' => $leadId,
                'lead_name' => trim((string) ($row['full_name'] ?? '')),
                'status' => trim((string) ($row['status'] ?? '')),
                'suggested_action' => (string) ($assistantCard['recommended_action'] ?? 'Review context and prepare a draft before sending.'),
                'assistant_card' => $assistantCard,
            ];
        }

        $activities = db_all(
            "SELECT
                la.id,
                la.lead_id,
                la.type,
                la.body,
                la.created_at,
                l.full_name,
                l.status
             FROM lead_activities la
             INNER JOIN leads l ON l.id = la.lead_id
             WHERE la.type IN ('lead_created', 'consultation_scheduled', 'follow_up_due', 'manual_sms_followup_prepared')
               AND la.created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
             ORDER BY la.created_at DESC, la.id DESC
             LIMIT {$activityLimit}"
        );

        foreach ($activities as $row) {
            $type = trim((string) ($row['type'] ?? 'activity'));
            $leadId = (int) ($row['lead_id'] ?? 0);
            $dedupeKey = 'activity:' . $type . ':' . $leadId;
            if ($leadId <= 0 || isset($dedupeKeys[$dedupeKey])) {
                continue;
            }
            $dedupeKeys[$dedupeKey] = true;
            $assistantCard = codex_api_notification_assistant_card($row, $type);
            $notifications[] = [
                'id' => 'act-' . (int) ($row['id'] ?? 0),
                'type' => $type,
                'title' => $type === 'lead_created' ? 'New lead' : 'CRM alert',
                'message' => trim((string) ($row['body'] ?? '')),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'priority' => in_array($type, ['lead_created', 'follow_up_due', 'consultation_scheduled'], true) ? 'high' : 'normal',
                'is_new' => false,
                'lead_id' => $leadId,
                'lead_name' => trim((string) ($row['full_name'] ?? '')),
                'status' => trim((string) ($row['status'] ?? '')),
                'suggested_action' => (string) ($assistantCard['recommended_action'] ?? ($type === 'lead_created'
                    ? 'Open the lead and confirm first-touch drafts.'
                    : 'Open the lead and review next steps.')),
                'assistant_card' => $assistantCard,
            ];
        }

        usort($notifications, static function (array $a, array $b): int {
            $aTime = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
            $bTime = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
            return $bTime <=> $aTime;
        });

        codex_api_response([
            'ok' => true,
            'notifications' => array_slice($notifications, 0, $limit),
            'adapter' => 'lead_messages + lead_activities',
            'draft_before_send_rule' => true,
        ]);
    }
}

if (!function_exists('codex_api_mark_notification_reviewed')) {
    function codex_api_mark_notification_reviewed(): void
    {
        $leadId = (int) codex_api_value('lead_id', 0);
        if ($leadId <= 0) {
            codex_api_response(['ok' => false, 'message' => 'lead_id is required.'], 422);
        }

        codex_api_load_lead($leadId);
        lead_comm_mark_read($leadId);
        lead_comm_insert_activity($leadId, 'operator_notification_reviewed', 'Notification reviewed and cleared through Codex API.', [
            'notification_id' => trim((string) codex_api_value('notification_id', '')),
            'source' => 'codex_api',
            'draft_before_send_rule' => true,
        ], (string) codex_api_value('created_by', 'Codex'));
        lead_comm_update_rollup($leadId);

        codex_api_response([
            'ok' => true,
            'message' => 'Notification reviewed and inbound replies marked read.',
            'lead_id' => $leadId,
            'thread' => codex_api_timeline($leadId),
        ]);
    }
}

if (!function_exists('codex_api_mobile_setup_token')) {
    function codex_api_mobile_setup_token(): void
    {
        $userId = (int) codex_api_value('user_id', 0);
        if ($userId <= 0) {
            codex_api_response(['ok' => false, 'message' => 'user_id is required.'], 422);
        }

        $user = auth_find_user_by_id($userId);
        if (!$user) {
            codex_api_response(['ok' => false, 'message' => 'User not found.'], 404);
        }

        $token = mobile_ai_issue_setup_token($userId, null);
        codex_api_response([
            'ok' => true,
            'user_id' => $userId,
            'setup_url' => mobile_ai_qr_setup_url($token),
            'qr_url' => mobile_ai_qr_image_url($token),
            'expires_in_seconds' => MOBILE_AI_SETUP_TTL_SECONDS,
        ]);
    }
}

if (!function_exists('codex_api_elite_ai_audit_recent')) {
    function codex_api_elite_ai_audit_recent(): void
    {
        elite_ai_ensure_schema();
        $limit = max(1, min(50, (int) codex_api_value('limit', 10)));
        $rows = db_all(
            "SELECT
                l.id,
                l.user_id,
                u.first_name,
                u.last_name,
                u.email,
                l.surface,
                l.prompt,
                l.tools_used_json,
                l.response_summary,
                l.lead_id,
                l.page_context_json,
                l.created_at
             FROM elite_ai_audit_logs l
             LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT {$limit}"
        );

        $logs = [];
        foreach ($rows as $row) {
            $tools = json_decode((string) ($row['tools_used_json'] ?? '[]'), true);
            $context = json_decode((string) ($row['page_context_json'] ?? '{}'), true);
            $logs[] = [
                'id' => (int) ($row['id'] ?? 0),
                'user_id' => (int) ($row['user_id'] ?? 0),
                'user_name' => trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))),
                'user_email' => (string) ($row['email'] ?? ''),
                'surface' => (string) ($row['surface'] ?? ''),
                'prompt' => (string) ($row['prompt'] ?? ''),
                'tools_used' => is_array($tools) ? $tools : [],
                'response_summary' => (string) ($row['response_summary'] ?? ''),
                'lead_id' => (int) ($row['lead_id'] ?? 0),
                'page_context' => is_array($context) ? $context : [],
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        codex_api_response([
            'ok' => true,
            'logs' => $logs,
            'sanitized' => true,
        ]);
    }
}

if (!function_exists('codex_api_mobile_push_save')) {
    function codex_api_mobile_push_save(): void
    {
        $userId = (int) codex_api_value('user_id', 0);
        $subscription = (array) codex_api_value('subscription', []);
        $browser = trim((string) codex_api_value('browser', ''));
        $deviceLabel = trim((string) codex_api_value('device_label', ''));

        if ($userId <= 0 || !$subscription) {
            codex_api_response(['ok' => false, 'message' => 'user_id and subscription are required.'], 422);
        }

        $ok = mobile_ai_save_push_subscription($userId, $subscription, $browser, $deviceLabel);
        codex_api_response([
            'ok' => $ok,
            'message' => $ok ? 'Push subscription stored.' : 'Could not store push subscription.',
        ], $ok ? 200 : 500);
    }
}

if (!function_exists('codex_api_mobile_push_remove')) {
    function codex_api_mobile_push_remove(): void
    {
        $userId = (int) codex_api_value('user_id', 0);
        $endpoint = trim((string) codex_api_value('endpoint', ''));
        if ($userId <= 0 || $endpoint === '') {
            codex_api_response(['ok' => false, 'message' => 'user_id and endpoint are required.'], 422);
        }

        $ok = mobile_ai_remove_push_subscription($userId, $endpoint);
        codex_api_response([
            'ok' => $ok,
            'message' => $ok ? 'Push subscription revoked.' : 'Could not revoke push subscription.',
        ], $ok ? 200 : 500);
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

    if ($action === 'mobile_notifications') {
        codex_api_mobile_notifications();
    }

    if ($action === 'mark_notification_reviewed') {
        codex_api_mark_notification_reviewed();
    }

    if ($action === 'elite_ai_audit_recent') {
        codex_api_elite_ai_audit_recent();
    }

    if ($method !== 'POST') {
        codex_api_response(['ok' => false, 'message' => 'Use POST for write actions.'], 405);
    }

    if ($action === 'follow_up_lead' || $action === 'operator_follow_up') {
        codex_api_follow_up_lead();
    }

    if ($action === 'create_lead') {
        $fields = (array) codex_api_value('lead', codex_api_body());
        if ($fields === []) {
            $fields = (array) codex_api_body();
        }

        if (isset($fields['action'])) {
            unset($fields['action']);
        }

        if (isset($fields['lead'])) {
            $leadPayload = (array) $fields['lead'];

            if (!array_key_exists('source', $leadPayload)
                && (array_key_exists('source', $fields) || array_key_exists('source_medium', $fields) || array_key_exists('source_type', $fields) || array_key_exists('platform', $fields))
            ) {
                $leadPayload = array_merge($leadPayload, [
                    'source' => trim((string)($fields['source'] ?? $leadPayload['source'] ?? '')),
                    'source_medium' => trim((string)($fields['source_medium'] ?? $leadPayload['source_medium'] ?? '')),
                    'source_type' => trim((string)($fields['source_type'] ?? $leadPayload['source_type'] ?? '')),
                    'platform' => trim((string)($fields['platform'] ?? $leadPayload['platform'] ?? '')),
                ]);
            }

            $fields = $leadPayload;
        }

        $fields['source'] = trim((string)($fields['source'] ?? ''));
        $fields['source_medium'] = trim((string)($fields['source_medium'] ?? ''));
        $fields['source_type'] = trim((string)($fields['source_type'] ?? ''));
        $fields['platform'] = trim((string)($fields['platform'] ?? ''));

        lead_enforce_meta_defaults($fields);
        $fields['refresh_duplicate'] = array_key_exists('refresh_duplicate', $fields)
            ? filter_var($fields['refresh_duplicate'], FILTER_VALIDATE_BOOLEAN)
            : true;

        if ($fields['source'] === '') {
            $fields['source'] = 'codex_api';
        }

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

    if ($action === 'import_meta_leads') {
        $rows = (array) codex_api_value('rows', []);
        if ($rows === []) {
            codex_api_response(['ok' => false, 'message' => 'No lead rows were provided.'], 422);
        }

        $result = lead_import_meta_rows($rows, ['first_name' => 'Codex', 'last_name' => 'API']);
        codex_api_response([
            'ok' => true,
            'message' => 'Lead import completed.',
            'result' => $result,
        ], 200);
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

    if ($action === 'mobile_setup_token') {
        codex_api_mobile_setup_token();
    }

    if ($action === 'mobile_push_subscription_save') {
        codex_api_mobile_push_save();
    }

    if ($action === 'mobile_push_subscription_remove') {
        codex_api_mobile_push_remove();
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
        lead_comm_mark_read($leadId);
        lead_comm_insert_activity($leadId, 'operator_notification_reviewed', 'Inbound notification cleared after Codex API email response.', [
            'email_id' => (int)($result['email_id'] ?? 0),
            'source' => 'codex_api',
        ], (string) codex_api_value('created_by', 'Codex'));
        lead_comm_update_rollup($leadId);
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
        $sendResult = elite_twilio_send_sms(trim((string)($lead['phone'] ?? '')), $message, [
            'lead_id' => $leadId,
            'lead' => $lead,
            'send_pushover_fallback' => true,
            'fallback_summary' => 'Twilio could not send the Codex API SMS. Open lead actions to retry manually.',
            'original_body' => $message,
        ]);
        if (empty($sendResult['ok'])) {
            codex_api_response([
                'ok' => false,
                'message' => (string)($sendResult['message'] ?? 'SMS failed.'),
                'lead_id' => $leadId,
                'operator_fallback_sent' => (bool)($sendResult['operator_fallback_sent'] ?? false),
            ], 502);
        }
        $sentBody = (string)($sendResult['body'] ?? $message);
        $messageRecordId = lead_comm_insert_message([
            'lead_id' => $leadId,
            'direction' => 'outbound',
            'channel' => 'sms',
            'from_number' => (string)($sendResult['from'] ?? ''),
            'to_number' => (string)($sendResult['to'] ?? $lead['phone'] ?? ''),
            'body' => $sentBody,
            'twilio_message_sid' => (string)($sendResult['twilio_sid'] ?? ''),
            'twilio_status' => (string)($sendResult['twilio_status'] ?? ''),
            'is_read' => 1,
        ]);
        lead_comm_insert_activity($leadId, 'sms_outbound', 'Sent SMS through Codex API.', [
            'message_id' => $messageRecordId,
            'twilio_sid' => $sendResult['twilio_sid'] ?? '',
            'source' => 'codex_api',
        ], 'Codex');
        lead_comm_mark_read($leadId);
        lead_comm_insert_activity($leadId, 'operator_notification_reviewed', 'Inbound notification cleared after Codex API SMS response.', [
            'message_id' => $messageRecordId,
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
