<?php
declare(strict_types=1);

require_once __DIR__ . '/elite_ai_knowledge.php';
require_once __DIR__ . '/../leads/lead_ai.php';
require_once __DIR__ . '/elite_ai_tools.php';

if (!function_exists('elite_ai_ensure_schema')) {
    function elite_ai_ensure_schema(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        try {
            db_query(
                "CREATE TABLE IF NOT EXISTS elite_ai_audit_logs (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id INT UNSIGNED NOT NULL,
                    surface VARCHAR(32) NOT NULL DEFAULT 'desktop',
                    prompt TEXT NOT NULL,
                    tools_used_json LONGTEXT DEFAULT NULL,
                    response_summary TEXT NOT NULL,
                    lead_id INT UNSIGNED DEFAULT NULL,
                    page_context_json LONGTEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_elite_ai_audit_user (user_id),
                    KEY idx_elite_ai_audit_surface (surface),
                    KEY idx_elite_ai_audit_lead (lead_id),
                    KEY idx_elite_ai_audit_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            db_query(
                "CREATE TABLE IF NOT EXISTS elite_ai_action_queue (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id INT UNSIGNED NOT NULL,
                    surface VARCHAR(32) NOT NULL DEFAULT 'desktop',
                    action_type VARCHAR(60) NOT NULL,
                    lead_id INT UNSIGNED NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending_review',
                    request_prompt TEXT DEFAULT NULL,
                    request_context_json LONGTEXT DEFAULT NULL,
                    request_payload_json LONGTEXT DEFAULT NULL,
                    draft_payload_json LONGTEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    completed_at DATETIME DEFAULT NULL,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_elite_ai_action_queue_user (user_id),
                    KEY idx_elite_ai_action_queue_status (status),
                    KEY idx_elite_ai_action_queue_lead (lead_id),
                    KEY idx_elite_ai_action_queue_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $queueColumns = [
                'surface' => "ALTER TABLE elite_ai_action_queue ADD COLUMN surface VARCHAR(32) NOT NULL DEFAULT 'desktop' AFTER user_id",
                'action_type' => "ALTER TABLE elite_ai_action_queue ADD COLUMN action_type VARCHAR(60) NOT NULL AFTER surface",
                'lead_id' => "ALTER TABLE elite_ai_action_queue ADD COLUMN lead_id INT UNSIGNED NOT NULL AFTER action_type",
                'status' => "ALTER TABLE elite_ai_action_queue ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending_review' AFTER lead_id",
                'request_prompt' => "ALTER TABLE elite_ai_action_queue ADD COLUMN request_prompt TEXT DEFAULT NULL AFTER status",
                'request_context_json' => "ALTER TABLE elite_ai_action_queue ADD COLUMN request_context_json LONGTEXT DEFAULT NULL AFTER request_prompt",
                'request_payload_json' => "ALTER TABLE elite_ai_action_queue ADD COLUMN request_payload_json LONGTEXT DEFAULT NULL AFTER request_context_json",
                'draft_payload_json' => "ALTER TABLE elite_ai_action_queue ADD COLUMN draft_payload_json LONGTEXT DEFAULT NULL AFTER request_payload_json",
                'completed_at' => "ALTER TABLE elite_ai_action_queue ADD COLUMN completed_at DATETIME DEFAULT NULL AFTER created_at",
                'updated_at' => "ALTER TABLE elite_ai_action_queue ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER completed_at",
            ];

            foreach ($queueColumns as $column => $sql) {
                try {
                    $exists = (int) db_value(
                        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
                        ['table' => 'elite_ai_action_queue', 'column' => $column]
                    );
                    if ($exists === 0) {
                        db_query($sql);
                    }
                } catch (Throwable $e) {
                    esm_log('elite_ai', 'Could not ensure Elite AI action queue column.', [
                        'column' => $column,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (function_exists('elite_ai_memory_ensure_schema')) {
                elite_ai_memory_ensure_schema();
            }
        } catch (Throwable $e) {
            esm_log('elite_ai', 'Could not ensure Elite AI audit schema.', ['error' => $e->getMessage()]);
        }

        $ensured = true;
    }
}

if (!function_exists('elite_ai_surface')) {
    function elite_ai_surface(array $request): string
    {
        return strtolower(trim((string) ($request['surface'] ?? 'desktop'))) === 'mobile' ? 'mobile' : 'desktop';
    }
}

if (!function_exists('elite_ai_infer_thread_lead_id')) {
    function elite_ai_infer_thread_lead_id(array $assistantThread): int
    {
        foreach (array_reverse($assistantThread) as $item) {
            $text = trim((string) ($item['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            if (preg_match('/\blead\s*#\s*(\d{1,10})\b/i', $text, $matches)) {
                return (int) $matches[1];
            }
            if (preg_match('/\(#\s*(\d{1,10})\)/i', $text, $matches)) {
                return (int) $matches[1];
            }
            if (preg_match('/\bfor\s+lead\s+(\d{1,10})\b/i', $text, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }
}

if (!function_exists('elite_ai_prompt_references_conversation_subject')) {
    function elite_ai_prompt_references_conversation_subject(string $prompt): bool
    {
        $normalized = strtolower(trim($prompt));
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b(?:it|this|that|them|her|him|he|she|they|the lead|the patient|same lead|same person|yes|ok|okay|do it|send it|draft it|answer it|answer them|move them|move her|move him|clear it)\b/i',
            $normalized
        );
    }
}

if (!function_exists('elite_ai_prompt_is_affirmation')) {
    function elite_ai_prompt_is_affirmation(string $prompt): bool
    {
        $normalized = strtolower(trim($prompt));
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match('/^(?:yes|yep|yeah|correct|that one|that is right|that\'s right|do it|go ahead|ok|okay|please do|yes please|confirm)$/i', $normalized);
    }
}

if (!function_exists('elite_ai_infer_pending_stage_move_from_thread')) {
    function elite_ai_infer_pending_stage_move_from_thread(array $assistantThread): array
    {
        foreach (array_reverse($assistantThread) as $item) {
            $role = strtolower(trim((string) ($item['role'] ?? 'assistant')));
            if ($role !== 'assistant') {
                continue;
            }

            $text = trim((string) ($item['text'] ?? ''));
            if ($text === '' || stripos($text, 'move') === false) {
                continue;
            }

            $candidates = [];
            if (preg_match_all('/^\s*([^\r\n#]+?)\s+\(#\s*(\d{1,10})\)/mi', $text, $candidateMatches, PREG_SET_ORDER)) {
                foreach ($candidateMatches as $candidateMatch) {
                    $name = trim((string) ($candidateMatch[1] ?? ''));
                    $id = (int) ($candidateMatch[2] ?? 0);
                    if ($id > 0) {
                        $candidates[] = [
                            'lead_id' => $id,
                            'name' => $name,
                        ];
                    }
                }
            }

            $targetStage = '';
            if (preg_match('/move\s+(?:this|the)\s+lead\s+to\s+([^?.\n]+)[?.]?/i', $text, $stageMatches)) {
                $targetStage = elite_ai_requested_stage_key((string) ($stageMatches[1] ?? ''));
            }
            if ($targetStage === '' && preg_match('/move\s+to\s+([^?.\n]+)[?.]?/i', $text, $stageMatches)) {
                $targetStage = elite_ai_requested_stage_key((string) ($stageMatches[1] ?? ''));
            }

            if ($targetStage === '') {
                continue;
            }

            $leadId = 0;
            if (count($candidates) === 1) {
                $leadId = (int) ($candidates[0]['lead_id'] ?? 0);
            } elseif (preg_match('/\(#\s*(\d{1,10})\)/i', $text, $idMatches) && count($candidates) === 0) {
                $leadId = (int) $idMatches[1];
            }

            return [
                'lead_id' => $leadId,
                'target_status' => $targetStage,
                'candidates' => $candidates,
            ];
        }

        return [];
    }
}

if (!function_exists('elite_ai_resolve_pending_stage_move_selection')) {
    function elite_ai_resolve_pending_stage_move_selection(string $prompt, array $pendingMove): int
    {
        $candidates = array_values((array) ($pendingMove['candidates'] ?? []));
        if (!$candidates) {
            return (int) ($pendingMove['lead_id'] ?? 0);
        }

        $normalized = strtolower(trim($prompt));
        $normalized = trim((string) preg_replace('/[^a-z0-9\s]+/i', ' ', $normalized));
        $normalized = trim((string) preg_replace('/\s+/', ' ', $normalized));
        if ($normalized === '') {
            return 0;
        }

        if (preg_match('/\b(?:first|top|1st|number\s*1)\b/i', $normalized)) {
            return (int) ($candidates[0]['lead_id'] ?? 0);
        }

        $bestId = 0;
        $bestScore = 99;
        foreach ($candidates as $candidate) {
            $name = strtolower(trim((string) ($candidate['name'] ?? '')));
            $id = (int) ($candidate['lead_id'] ?? 0);
            if ($id <= 0 || $name === '') {
                continue;
            }
            $cleanName = trim((string) preg_replace('/[^a-z0-9\s]+/i', ' ', $name));
            $cleanName = trim((string) preg_replace('/\s+/', ' ', $cleanName));
            if ($cleanName === '') {
                continue;
            }
            if ($cleanName === $normalized || str_contains($cleanName, $normalized)) {
                return $id;
            }

            $nameParts = preg_split('/\s+/', $cleanName) ?: [];
            foreach ($nameParts as $part) {
                $distance = levenshtein($normalized, $part);
                if ($distance < $bestScore) {
                    $bestScore = $distance;
                    $bestId = $id;
                }
            }
        }

        return $bestScore <= 2 ? $bestId : 0;
    }
}

if (!function_exists('elite_ai_prompt_requests_pending_draft_review')) {
    function elite_ai_prompt_requests_pending_draft_review(string $prompt): bool
    {
        $normalized = strtolower(trim($prompt));
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match('/\b(?:send it|send this|send that|yes send|approve|approved|use draft|use it|edit draft|show draft|where is the draft|draft again)\b/i', $normalized);
    }
}

if (!function_exists('elite_ai_pending_draft_conversation_payload')) {
    function elite_ai_pending_draft_conversation_payload(array $user, array $context): ?array
    {
        $pending = function_exists('elite_ai_pending_drafts_for_user') ? elite_ai_pending_drafts_for_user($user, 8) : [];
        if (!$pending) {
            return null;
        }

        $leadId = (int) ($context['lead_id'] ?? 0);
        $selected = null;
        foreach ($pending as $draft) {
            if ($leadId > 0 && (int) ($draft['lead_id'] ?? 0) === $leadId) {
                $selected = $draft;
                break;
            }
        }
        if (!$selected) {
            $selected = $pending[0];
        }

        $selectedLeadId = (int) ($selected['lead_id'] ?? 0);
        $leadName = trim((string) ($selected['lead_name'] ?? 'this lead'));
        $preview = trim((string) ($selected['draft_preview'] ?? 'Draft ready for review.'));

        return [
            'answer' => 'I found the pending ' . trim((string) ($selected['channel'] ?? 'draft')) . ' draft for ' . ($leadName !== '' ? $leadName : 'this lead') . '. I did not send it. Review it here, then use the draft action if it looks right.',
            'cards' => [[
                'title' => 'Pending draft',
                'items' => [$preview],
            ]],
            'actions' => array_values((array) ($selected['actions'] ?? [])),
            'tools_used' => ['pending_draft_context'],
            'lead_id' => $selectedLeadId > 0 ? $selectedLeadId : null,
            'pending_drafts' => $pending,
        ];
    }
}

if (!function_exists('elite_ai_current_subject_payload')) {
    function elite_ai_current_subject_payload(?int $leadId): array
    {
        $leadId = (int) ($leadId ?? 0);
        if ($leadId <= 0 || !function_exists('elite_ai_load_lead')) {
            return [];
        }

        $lead = elite_ai_load_lead($leadId);
        if (!$lead) {
            return [];
        }

        return [
            'type' => 'lead',
            'lead_id' => $leadId,
            'label' => trim((string) ($lead['full_name'] ?? 'Lead #' . $leadId)),
            'status' => trim((string) ($lead['status'] ?? '')),
            'conversion_stage' => trim((string) ($lead['conversion_stage_label'] ?? '')),
        ];
    }
}

if (!function_exists('elite_ai_normalize_context')) {
function elite_ai_normalize_context(array $request): array
    {
        $context = is_array($request['context'] ?? null) ? $request['context'] : [];
        $page = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($context['page'] ?? ''));
        $pageTitle = trim((string) ($context['page_title'] ?? ''));
        $currentUrl = trim((string) ($context['current_url'] ?? ''));
        $leadId = (int) ($context['lead_id'] ?? 0);
        $tab = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($context['tab'] ?? ''));
        $notification = [];
        if (is_array($context['notification'] ?? null)) {
            $notificationSource = (array) $context['notification'];
            $notificationLeadId = (int) ($notificationSource['lead_id'] ?? 0);
            $notification = [
                'id' => mb_substr(trim((string) ($notificationSource['id'] ?? '')), 0, 80),
                'type' => mb_substr(trim((string) ($notificationSource['type'] ?? '')), 0, 40),
                'title' => mb_substr(trim((string) ($notificationSource['title'] ?? '')), 0, 180),
                'message' => mb_substr(trim((string) ($notificationSource['message'] ?? '')), 0, 700),
                'created_at' => mb_substr(trim((string) ($notificationSource['created_at'] ?? '')), 0, 40),
                'lead_id' => $notificationLeadId > 0 ? $notificationLeadId : 0,
                'lead_name' => mb_substr(trim((string) ($notificationSource['lead_name'] ?? '')), 0, 160),
                'status' => mb_substr(trim((string) ($notificationSource['status'] ?? '')), 0, 80),
                'suggested_action' => mb_substr(trim((string) ($notificationSource['suggested_action'] ?? '')), 0, 260),
                'is_new' => !empty($notificationSource['is_new']),
            ];
            if ($leadId <= 0 && $notification['lead_id'] > 0) {
                $leadId = (int) $notification['lead_id'];
            }
        }
        $assistantThread = [];
        if (is_array($context['assistant_thread'] ?? null)) {
            foreach (array_slice($context['assistant_thread'], -8) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $role = strtolower(trim((string) ($item['role'] ?? 'assistant')));
                $text = trim((string) ($item['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $assistantThread[] = [
                    'role' => $role === 'user' ? 'user' : 'assistant',
                    'text' => mb_substr($text, 0, 700),
                ];
            }
        }
        if ($leadId <= 0) {
            $threadLeadId = elite_ai_infer_thread_lead_id($assistantThread);
            if ($threadLeadId > 0) {
                $leadId = $threadLeadId;
            }
        }

    return [
        'page' => $page !== '' ? $page : 'unknown',
        'page_title' => $pageTitle,
        'current_url' => $currentUrl,
        'lead_id' => $leadId > 0 ? $leadId : 0,
        'tab' => $tab,
        'notification' => $notification,
        'assistant_thread' => $assistantThread,
    ];
    }
}

if (!function_exists('elite_ai_request_has_explicit_send_permission')) {
    function elite_ai_request_has_explicit_send_permission(array $request): bool
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
        $executionMode = strtolower(trim((string) ($request['execution_mode'] ?? '')));
        if (in_array($executionMode, ['send', 'send_now', 'send_approved', 'send-confirmed', 'approved_send'], true)) {
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
            '/\b(?:send|dispatch|deliver)\b(?:\\s+(?:all|the|these|approved)\\s*)?(?:sms|text|email)\b|\b(?:send|dispatch)\s+the\s+(?:approved\s+)?drafts?\b|\bsend\s+(?:all|the)\s+(?:approved\s+)?(?:sms|email)\b|\bsend\s+(?:all|the|these)\s+drafts?\s+now\b/i',
            $normalizedInstruction
        );
    }
}

if (!function_exists('elite_ai_request_has_explicit_stage_approval')) {
    function elite_ai_request_has_explicit_stage_approval(array $request): bool
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

if (!function_exists('elite_ai_execution_policy_tag')) {
    function elite_ai_execution_policy_tag(array $request): string
    {
        return elite_ai_request_has_explicit_send_permission($request) ? 'send-approved' : 'internal-only';
    }
}

if (!function_exists('elite_ai_stage_label')) {
    function elite_ai_stage_label(string $status): string
    {
        $labels = function_exists('lead_stage_labels') ? lead_stage_labels() : [];
        return trim((string) ($labels[$status] ?? '')) !== '' ? (string) $labels[$status] : ucwords(str_replace('_', ' ', $status));
    }
}

if (!function_exists('elite_ai_lead_select_fields')) {
    function elite_ai_lead_select_fields(): string
    {
        $fields = ['id'];
        $candidates = [
            'full_name', 'first_name', 'last_name', 'email', 'phone', 'preferred_contact',
            'procedure_interest', 'source', 'source_medium', 'source_type', 'campaign',
            'status', 'consultation_status', 'consultation_date', 'notes', 'lead_value',
            'date_of_birth', 'unread_message_count', 'last_contacted_at', 'last_inbound_at',
            'last_outbound_at', 'next_follow_up_at', 'follow_up_status',
            'scheduling_preferred_day', 'scheduling_preferred_time', 'created_at', 'updated_at',
        ];

        foreach ($candidates as $field) {
            if (function_exists('leads_has_column') && leads_has_column($field)) {
                $fields[] = $field;
            }
        }

        return implode(', ', array_unique($fields));
    }
}

if (!function_exists('elite_ai_enrich_conversion_layer')) {
    function elite_ai_enrich_conversion_layer(array $lead): array
    {
        if (!function_exists('lead_conversion_summary')) {
            return $lead;
        }

        $summary = lead_conversion_summary($lead);
        $lead['conversion_stage_key'] = (string)($summary['stage_key'] ?? '');
        $lead['conversion_stage_label'] = (string)($summary['stage_label'] ?? '');
        $lead['conversion_next_action'] = (array)($summary['next_action'] ?? []);
        $lead['conversion_badges'] = (array)($summary['badges'] ?? []);
        return $lead;
    }
}

if (!function_exists('elite_ai_load_lead')) {
    function elite_ai_load_lead(int $leadId): ?array
    {
        if ($leadId <= 0) {
            return null;
        }

        $lead = db_one('SELECT ' . elite_ai_lead_select_fields() . ' FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        return $lead ? elite_ai_enrich_conversion_layer($lead) : null;
    }
}

if (!function_exists('elite_ai_find_leads')) {
    function elite_ai_find_leads(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(10, $limit));
        $digits = preg_replace('/\D+/', '', $query) ?? '';
        $params = [
            'name' => '%' . $query . '%',
            'email' => '%' . $query . '%',
        ];

        $where = [
            'full_name LIKE :name',
            'email LIKE :email',
        ];

        if ($digits !== '') {
            $where[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', ''), '.', '') LIKE :phone";
            $params['phone'] = '%' . $digits . '%';
        }

        $rows = db_all(
            'SELECT ' . elite_ai_lead_select_fields() . '
             FROM leads
             WHERE ' . implode(' OR ', $where) . '
             ORDER BY updated_at DESC, id DESC
             LIMIT ' . $limit,
            $params
        );

        if (!$rows && $digits === '' && mb_strlen($query) >= 3) {
            $needle = strtolower(preg_replace('/[^a-z0-9]+/i', '', $query) ?? '');
            $candidates = db_all(
                'SELECT ' . elite_ai_lead_select_fields() . '
                 FROM leads
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 250'
            );
            $scored = [];
            foreach ($candidates as $candidate) {
                $name = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) ($candidate['full_name'] ?? '')) ?? '');
                if ($name === '') {
                    continue;
                }
                $first = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) (preg_split('/\s+/', trim((string) ($candidate['full_name'] ?? '')))[0] ?? '')) ?? '');
                $distance = min(
                    levenshtein($needle, mb_substr($name, 0, max(mb_strlen($needle), 1))),
                    $first !== '' ? levenshtein($needle, $first) : 99
                );
                if ($distance <= 2) {
                    $candidate['_elite_ai_match_distance'] = $distance;
                    $scored[] = $candidate;
                }
            }
            usort($scored, static function (array $a, array $b): int {
                return ((int) ($a['_elite_ai_match_distance'] ?? 99)) <=> ((int) ($b['_elite_ai_match_distance'] ?? 99));
            });
            $rows = array_slice($scored, 0, $limit);
        }

        return array_map(static function (array $row): array {
            $distance = $row['_elite_ai_match_distance'] ?? null;
            $row = elite_ai_enrich_conversion_layer($row);
            if ($distance !== null) {
                $row['_elite_ai_match_distance'] = (int) $distance;
            }
            return $row;
        }, $rows);
    }
}

if (!function_exists('elite_ai_lead_thread')) {
    function elite_ai_lead_thread(int $leadId): array
    {
        $snapshot = function_exists('lead_comm_snapshot') ? lead_comm_snapshot($leadId) : ['messages' => [], 'activities' => []];
        $emails = function_exists('lead_email_recent') ? lead_email_recent($leadId, 12) : [];
        $items = [];

        foreach (($snapshot['messages'] ?? []) as $message) {
            $items[] = [
                'kind' => 'message',
                'direction' => (string) ($message['direction'] ?? ''),
                'channel' => (string) ($message['channel'] ?? 'sms'),
                'body' => trim((string) ($message['body'] ?? '')),
                'created_at' => (string) ($message['created_at'] ?? ''),
            ];
        }

        foreach ($emails as $email) {
            $items[] = [
                'kind' => 'email',
                'direction' => (string) ($email['direction'] ?? ''),
                'channel' => 'email',
                'body' => trim((string) ($email['body'] ?? '')),
                'subject' => trim((string) ($email['subject'] ?? '')),
                'created_at' => (string) ($email['created_at'] ?? ''),
            ];
        }

        foreach (($snapshot['activities'] ?? []) as $activity) {
            $items[] = [
                'kind' => 'activity',
                'activity_type' => (string) ($activity['type'] ?? ''),
                'body' => trim((string) ($activity['body'] ?? '')),
                'created_at' => (string) ($activity['created_at'] ?? ''),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $timeA = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
            $timeB = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
            return $timeB <=> $timeA;
        });

        return [
            'messages' => $snapshot['messages'] ?? [],
            'activities' => $snapshot['activities'] ?? [],
            'emails' => $emails,
            'items' => $items,
        ];
    }
}

if (!function_exists('elite_ai_attempt_counts')) {
    function elite_ai_attempt_counts(int $leadId): array
    {
        $counts = [
            'outbound_messages' => 0,
            'inbound_messages' => 0,
            'outbound_emails' => 0,
            'inbound_emails' => 0,
        ];

        try {
            $counts['outbound_messages'] = (int) (db_value("SELECT COUNT(*) FROM lead_messages WHERE lead_id = :lead_id AND direction = 'outbound'", ['lead_id' => $leadId]) ?? 0);
            $counts['inbound_messages'] = (int) (db_value("SELECT COUNT(*) FROM lead_messages WHERE lead_id = :lead_id AND direction = 'inbound'", ['lead_id' => $leadId]) ?? 0);
        } catch (Throwable $e) {
            esm_log('elite_ai', 'Could not count lead messages.', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
        }

        try {
            $counts['outbound_emails'] = (int) (db_value("SELECT COUNT(*) FROM lead_emails WHERE lead_id = :lead_id AND direction = 'outbound'", ['lead_id' => $leadId]) ?? 0);
            $counts['inbound_emails'] = (int) (db_value("SELECT COUNT(*) FROM lead_emails WHERE lead_id = :lead_id AND direction = 'inbound'", ['lead_id' => $leadId]) ?? 0);
        } catch (Throwable $e) {
            $counts['outbound_emails'] = 0;
            $counts['inbound_emails'] = 0;
        }

        $counts['outbound_total'] = $counts['outbound_messages'] + $counts['outbound_emails'];
        $counts['inbound_total'] = $counts['inbound_messages'] + $counts['inbound_emails'];

        return $counts;
    }
}

if (!function_exists('elite_ai_latest_direction_item')) {
    function elite_ai_latest_direction_item(array $thread, string $direction): ?array
    {
        foreach (($thread['items'] ?? []) as $item) {
            if (($item['kind'] ?? '') === 'activity') {
                continue;
            }
            if (strtolower((string) ($item['direction'] ?? '')) === strtolower($direction)) {
                return $item;
            }
        }
        return null;
    }
}

if (!function_exists('elite_ai_latest_activity_item')) {
    function elite_ai_latest_activity_item(array $thread): ?array
    {
        foreach (($thread['items'] ?? []) as $item) {
            if (($item['kind'] ?? '') === 'activity') {
                return $item;
            }
        }
        return null;
    }
}

if (!function_exists('elite_ai_missing_items')) {
    function elite_ai_missing_items(array $lead): array
    {
        $missing = [];
        if (trim((string) ($lead['phone'] ?? '')) === '') {
            $missing[] = 'Phone number is missing.';
        } elseif (function_exists('lead_conversion_bad_phone') && lead_conversion_bad_phone($lead)) {
            $missing[] = 'Phone number looks invalid or placeholder.';
        }
        if (trim((string) ($lead['email'] ?? '')) === '') {
            $missing[] = 'Email is missing.';
        }
        if (trim((string) ($lead['preferred_contact'] ?? '')) === '') {
            $missing[] = 'Preferred contact method is not set.';
        }
        if (trim((string) ($lead['status'] ?? '')) === 'consultation_booked' && trim((string) ($lead['date_of_birth'] ?? '')) === '') {
            $missing[] = 'Date of birth is still missing for a booked consultation.';
        }

        return $missing;
    }
}

if (!function_exists('elite_ai_stage_rule_note')) {
    function elite_ai_stage_rule_note(string $status): string
    {
        $rules = elite_ai_knowledge_base();
        return (string) ($rules['stage_rules'][$status] ?? '');
    }
}

if (!function_exists('elite_ai_recommended_next_step')) {
    function elite_ai_recommended_next_step(array $lead, array $thread, array $attempts): string
    {
        $status = trim((string) ($lead['status'] ?? ''));
        $ruleText = elite_ai_stage_rule_note($status);
        $latestInbound = elite_ai_latest_direction_item($thread, 'inbound');
        $latestOutbound = elite_ai_latest_direction_item($thread, 'outbound');
        $unreadCount = (int) ($lead['unread_message_count'] ?? 0);
        $followUpStatus = trim((string) ($lead['follow_up_status'] ?? ''));
        $nextFollowUpAt = trim((string) ($lead['next_follow_up_at'] ?? ''));

        if ($status === 'consultation_booked') {
            return 'Protect this lead and review appointment readiness only. ' . ($ruleText !== '' ? $ruleText : 'Consultation Booked is protected.');
        }

        if ($unreadCount > 0 || ($latestInbound && (!$latestOutbound || strtotime((string) ($latestInbound['created_at'] ?? '')) > strtotime((string) ($latestOutbound['created_at'] ?? ''))))) {
            return 'Review the latest inbound communication and prepare a draft reply before sending. Rule: client-facing messages must show a draft before send.';
        }

        if ($status === 'new_lead') {
            return 'This lead still needs first contact review. Confirm the best first-touch draft and preferred contact path.';
        }

        if ($nextFollowUpAt !== '' && strtotime($nextFollowUpAt) !== false && strtotime($nextFollowUpAt) <= time()) {
            return 'This lead has a follow-up due now. Review context and prepare the next outreach draft.';
        }

        if (in_array($followUpStatus, ['needs_follow_up', 'reply_received'], true)) {
            return 'This lead is flagged for follow-up. Review the latest context and decide the next manual touch.';
        }

        if (($attempts['outbound_total'] ?? 0) >= 5 && ($attempts['inbound_total'] ?? 0) === 0) {
            return 'This is a No Answer review candidate, but it should stay review-only until a human approves the move.';
        }

        return 'Review the last communication and set the next best manual step. ' . ($ruleText !== '' ? $ruleText : 'Stay in read-only review mode.');
    }
}

if (!function_exists('elite_ai_notification_excerpt')) {
    function elite_ai_notification_excerpt(string $text, int $limit = 150): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if ($text === '' || strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(substr($text, 0, max(0, $limit - 1))) . '...';
    }
}

if (!function_exists('elite_ai_notification_action_card')) {
    function elite_ai_notification_action_card(array $row, string $type = 'reply'): array
    {
        $leadId = (int) ($row['lead_id'] ?? 0);
        $leadName = trim((string) ($row['full_name'] ?? $row['lead_name'] ?? 'Lead'));
        $body = trim((string) ($row['body'] ?? $row['message'] ?? ''));
        $bodyLower = strtolower($body);
        $summary = $type === 'reply'
            ? 'New reply from ' . ($leadName !== '' ? $leadName : 'Lead') . ($leadId > 0 ? ' #' . $leadId : '') . ': "' . elite_ai_notification_excerpt($body, 110) . '"'
            : 'CRM event for ' . ($leadName !== '' ? $leadName : 'Lead') . ($leadId > 0 ? ' #' . $leadId : '') . ': ' . elite_ai_notification_excerpt($body, 110);

        $intent = 'review_context';
        $recommended = 'Review context and prepare a draft before any patient-facing send.';
        $operatorPrompt = $leadId > 0 ? 'Check lead #' . $leadId . ' and suggest the next step.' : 'Review this notification and suggest the next step.';

        if ((bool) preg_match('/\b(?:dob|date\s+of\s+birth|birth\s*date)\b|\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/', $bodyLower)) {
            $intent = 'possible_dob';
            $recommended = 'This may include a DOB. Verify it, save it internally, then draft any confirmation for approval.';
            $operatorPrompt = $leadId > 0 ? 'Review lead #' . $leadId . ' for DOB and tell me what internal update is needed.' : $operatorPrompt;
        } elseif ((bool) preg_match('/\b(?:yes|works|available|morning|afternoon|pm|am|monday|tuesday|wednesday|thursday|friday|saturday|sunday|tomorrow|today|july|jun|june)\b/', $bodyLower)) {
            $intent = 'scheduling_reply';
            $recommended = 'Likely scheduling intent. Check calendar/lead context, then prepare a confirmation draft.';
            $operatorPrompt = $leadId > 0 ? 'Review lead #' . $leadId . ' scheduling reply and suggest the next step.' : $operatorPrompt;
        } elseif ((bool) preg_match('/\b(?:thanks|thank you|ok|okay|gracias|perfect|sounds good)\b/', $bodyLower)) {
            $intent = 'acknowledgement';
            $recommended = 'Likely acknowledgement. Mark reviewed unless the lead still needs a specific next step.';
            $operatorPrompt = $leadId > 0 ? 'Check lead #' . $leadId . ' and tell me if this acknowledgement needs a reply.' : $operatorPrompt;
        } elseif ((bool) preg_match('/\b(?:price|cost|financ|payment|down|credit|monthly|insurance)\b/', $bodyLower)) {
            $intent = 'financing_or_cost';
            $recommended = 'Cost/financing question. Prepare a warm finance-aware draft for approval.';
            $operatorPrompt = $leadId > 0 ? 'Prepare a finance-aware reply draft for lead #' . $leadId . ' after reviewing context.' : $operatorPrompt;
        }

        return [
            'intent' => $intent,
            'summary' => $summary,
            'recommended_action' => $recommended,
            'operator_prompt' => $operatorPrompt,
            'draft_before_send_required' => true,
            'send_requires_explicit_approval' => true,
        ];
    }
}

if (!function_exists('elite_ai_notification_rows')) {
    function elite_ai_select_notification_window(array $notifications, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $unread = array_values(array_filter($notifications, static fn (array $row): bool => !empty($row['is_new'])));
        if (count($unread) > $limit) {
            return $unread;
        }

        return array_slice($notifications, 0, $limit);
    }

    function elite_ai_notification_rows(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $notifications = [];
        $dedupeKeys = [];
        $messageLimit = 50;
        $activityLimit = 30;

        try {
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
                   AND lm.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                 ORDER BY lm.created_at DESC, lm.id DESC
                 LIMIT {$messageLimit}"
            );

            foreach ($messages as $row) {
                $leadId = (int) ($row['lead_id'] ?? 0);
                $leadName = trim((string) ($row['full_name'] ?? 'Lead'));
                $dedupeKey = "msg:{$leadId}";
                if ($leadId <= 0 || isset($dedupeKeys[$dedupeKey])) {
                    continue;
                }
                $dedupeKeys[$dedupeKey] = true;
                $assistantCard = elite_ai_notification_action_card($row, 'reply');
                $isUnread = (int) ($row['is_read'] ?? 0) === 0;

                $notifications[] = [
                    'id' => 'msg-' . (int) ($row['id'] ?? 0),
                    'type' => 'reply',
                    'priority' => $isUnread ? 'high' : 'normal',
                    'is_new' => $isUnread,
                    'title' => 'Reply from ' . $leadName . ($leadId > 0 ? ' - Lead #' . $leadId : ''),
                    'message' => trim((string) ($row['body'] ?? '')),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'lead_id' => $leadId,
                    'lead_name' => $leadName,
                    'status' => trim((string) ($row['status'] ?? '')),
                    'suggested_action' => (string) ($assistantCard['recommended_action'] ?? 'Review context and prepare a draft before sending.'),
                    'assistant_card' => $assistantCard,
                ];
            }
        } catch (Throwable $e) {
            esm_log('elite_ai', 'Could not load inbound notifications.', ['error' => $e->getMessage()]);
        }

        try {
            $newLeads = db_all(
                "SELECT
                    l.id AS lead_id,
                    l.full_name,
                    l.status,
                    l.source,
                    l.source_type,
                    l.created_at,
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM lead_activities reviewed
                        WHERE reviewed.lead_id = l.id
                          AND reviewed.type = 'operator_notification_reviewed'
                          AND reviewed.created_at >= l.created_at
                    ) THEN 0 ELSE 1 END AS is_new
                 FROM leads l
                 WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
                 ORDER BY l.created_at DESC, l.id DESC
                 LIMIT {$activityLimit}"
            );

            foreach ($newLeads as $row) {
                $leadId = (int) ($row['lead_id'] ?? 0);
                $leadName = trim((string) ($row['full_name'] ?? 'Lead'));
                $dedupeKey = 'new_lead:' . $leadId;
                if ($leadId <= 0 || isset($dedupeKeys[$dedupeKey])) {
                    continue;
                }
                $dedupeKeys[$dedupeKey] = true;
                $isUnread = !empty($row['is_new']);
                $source = trim((string) ($row['source'] ?? ''));
                $sourceType = trim((string) ($row['source_type'] ?? ''));
                $sourceLabel = $sourceType === 'meta_instant_form'
                    ? 'Meta Lead Form'
                    : ($source !== '' ? ucwords(str_replace('_', ' ', $source)) : 'CRM');
                $assistantCard = elite_ai_notification_action_card($row, 'lead_created');

                $notifications[] = [
                    'id' => 'lead-' . $leadId,
                    'type' => 'new_lead',
                    'priority' => $isUnread ? 'high' : 'normal',
                    'is_new' => $isUnread,
                    'title' => 'New lead: ' . $leadName . ' - Lead #' . $leadId,
                    'message' => 'New lead received from ' . $sourceLabel . '.',
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'lead_id' => $leadId,
                    'lead_name' => $leadName,
                    'status' => trim((string) ($row['status'] ?? '')),
                    'suggested_action' => 'Open the lead and review first-touch status.',
                    'assistant_card' => $assistantCard,
                ];
            }
        } catch (Throwable $e) {
            esm_log('elite_ai', 'Could not load new-lead notifications.', ['error' => $e->getMessage()]);
        }

        try {
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
                 WHERE la.type IN ('consultation_scheduled', 'follow_up_due', 'manual_sms_followup_prepared')
                   AND la.created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
                 ORDER BY la.created_at DESC, la.id DESC
                 LIMIT {$activityLimit}"
            );

            foreach ($activities as $row) {
                $type = trim((string) ($row['type'] ?? 'activity'));
                $leadId = (int) ($row['lead_id'] ?? 0);
                $leadName = trim((string) ($row['full_name'] ?? 'Lead'));
                $dedupeKey = 'activity:' . $type . ':' . $leadId;
                if ($leadId <= 0 || isset($dedupeKeys[$dedupeKey])) {
                    continue;
                }
                $dedupeKeys[$dedupeKey] = true;

                $label = match ($type) {
                    'lead_created' => 'New lead',
                    'consultation_scheduled' => 'Consultation alert',
                    'follow_up_due' => 'Follow-up alert',
                    'manual_sms_followup_prepared' => 'Draft ready',
                    default => 'CRM alert',
                };
                $assistantCard = elite_ai_notification_action_card($row, $type);

                $notifications[] = [
                    'id' => 'act-' . (int) ($row['id'] ?? 0),
                    'type' => $type,
                    'priority' => 'normal',
                    'is_new' => false,
                    'title' => $label . ': ' . $leadName . ($leadId > 0 ? ' - Lead #' . $leadId : ''),
                    'message' => trim((string) ($row['body'] ?? '')),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'lead_id' => $leadId,
                    'lead_name' => $leadName,
                    'status' => trim((string) ($row['status'] ?? '')),
                    'suggested_action' => (string) ($assistantCard['recommended_action'] ?? ($type === 'lead_created'
                        ? 'Review the lead for first-touch readiness.'
                        : 'Open the lead and review the next manual step.')),
                    'assistant_card' => $assistantCard,
                ];
            }
        } catch (Throwable $e) {
            esm_log('elite_ai', 'Could not load activity notifications.', ['error' => $e->getMessage()]);
        }

        usort($notifications, static function (array $a, array $b): int {
            $timeA = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
            $timeB = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
            return $timeB <=> $timeA;
        });

        return elite_ai_select_notification_window($notifications, $limit);
    }
}

if (!function_exists('elite_ai_new_leads')) {
    function elite_ai_new_leads(int $limit = 6): array
    {
        return db_all(
            "SELECT " . elite_ai_lead_select_fields() . "
             FROM leads
             WHERE status = 'new_lead'
             ORDER BY created_at DESC, id DESC
             LIMIT {$limit}"
        );
    }
}

if (!function_exists('elite_ai_replies_today')) {
    function elite_ai_replies_today(int $limit = 6): array
    {
        return db_all(
            "SELECT
                lm.lead_id,
                lm.body,
                lm.created_at,
                l.full_name,
                l.status
             FROM lead_messages lm
             INNER JOIN leads l ON l.id = lm.lead_id
             WHERE lm.direction = 'inbound'
               AND DATE(lm.created_at) = CURDATE()
             ORDER BY lm.created_at DESC, lm.id DESC
             LIMIT {$limit}"
        );
    }
}

if (!function_exists('elite_ai_follow_up_candidates')) {
    function elite_ai_follow_up_candidates(int $limit = 6): array
    {
        $conditions = ["status IN ('contacted', 'in_contact')"];

        if (function_exists('leads_has_column') && leads_has_column('next_follow_up_at')) {
            $conditions[] = "(next_follow_up_at IS NOT NULL AND next_follow_up_at <= NOW())";
        }
        if (function_exists('leads_has_column') && leads_has_column('follow_up_status')) {
            $conditions[] = "follow_up_status IN ('needs_follow_up', 'reply_received')";
        }
        if (function_exists('leads_has_column') && leads_has_column('last_inbound_at') && leads_has_column('last_outbound_at')) {
            $conditions[] = "(last_inbound_at IS NOT NULL AND (last_outbound_at IS NULL OR last_inbound_at > last_outbound_at))";
        }

        return db_all(
            "SELECT " . elite_ai_lead_select_fields() . "
             FROM leads
             WHERE (" . implode(' OR ', $conditions) . ")
               AND status <> 'consultation_booked'
             ORDER BY COALESCE(next_follow_up_at, updated_at) ASC, updated_at DESC
             LIMIT {$limit}"
        );
    }
}

if (!function_exists('elite_ai_stale_leads')) {
    function elite_ai_stale_leads(int $limit = 5): array
    {
        return db_all(
            "SELECT " . elite_ai_lead_select_fields() . "
             FROM leads
             WHERE status NOT IN ('consultation_booked', 'sale_closed', 'no_answer')
               AND updated_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
             ORDER BY updated_at ASC, id ASC
             LIMIT {$limit}"
        );
    }
}

if (!function_exists('elite_ai_no_answer_candidates')) {
    function elite_ai_no_answer_candidates(int $limit = 6): array
    {
        $rows = db_all(
            "SELECT
                l.id,
                l.full_name,
                l.status,
                l.updated_at,
                COALESCE(msg.outbound_count, 0) AS outbound_count,
                COALESCE(msg.inbound_count, 0) AS inbound_count,
                msg.last_inbound_at,
                msg.last_outbound_at
             FROM leads l
             LEFT JOIN (
                SELECT
                    lead_id,
                    SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) AS outbound_count,
                    SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) AS inbound_count,
                    MAX(CASE WHEN direction = 'inbound' THEN created_at ELSE NULL END) AS last_inbound_at,
                    MAX(CASE WHEN direction = 'outbound' THEN created_at ELSE NULL END) AS last_outbound_at
                FROM lead_messages
                GROUP BY lead_id
             ) msg ON msg.lead_id = l.id
             WHERE l.status NOT IN ('consultation_booked', 'sale_closed')
             ORDER BY outbound_count DESC, l.updated_at DESC
             LIMIT 30"
        );

        $candidates = [];
        foreach ($rows as $row) {
            $outboundCount = (int) ($row['outbound_count'] ?? 0);
            $inboundCount = (int) ($row['inbound_count'] ?? 0);
            $lastInboundAt = trim((string) ($row['last_inbound_at'] ?? ''));
            $lastOutboundAt = trim((string) ($row['last_outbound_at'] ?? ''));
            $hasRecentReply = $lastInboundAt !== '' && ($lastOutboundAt === '' || strtotime($lastInboundAt) >= strtotime($lastOutboundAt));

            if ($outboundCount < 5 || $hasRecentReply) {
                continue;
            }

            $candidates[] = [
                'id' => (int) ($row['id'] ?? 0),
                'full_name' => trim((string) ($row['full_name'] ?? 'Lead')),
                'status' => trim((string) ($row['status'] ?? '')),
                'outbound_count' => $outboundCount,
                'inbound_count' => $inboundCount,
                'last_inbound_at' => $lastInboundAt,
                'last_outbound_at' => $lastOutboundAt,
                'review_reason' => 'High outbound attempts with no newer inbound reply.',
            ];

            if (count($candidates) >= $limit) {
                break;
            }
        }

        return $candidates;
    }
}

if (!function_exists('elite_ai_stage_counts')) {
    function elite_ai_stage_counts(): array
    {
        $counts = [];
        foreach (db_all('SELECT status, COUNT(*) AS total FROM leads GROUP BY status') as $row) {
            $status = trim((string) ($row['status'] ?? ''));
            if ($status === '') {
                continue;
            }
            $counts[$status] = (int) ($row['total'] ?? 0);
        }
        return $counts;
    }
}

if (!function_exists('elite_ai_format_lead_line')) {
    function elite_ai_format_lead_line(array $lead, string $extra = ''): string
    {
        $line = trim((string) ($lead['full_name'] ?? 'Lead'));
        $leadId = (int) ($lead['id'] ?? $lead['lead_id'] ?? 0);
        if ($leadId > 0) {
            $line .= ' (#' . $leadId . ')';
        }
        $status = trim((string) ($lead['status'] ?? ''));
        if ($status !== '') {
            $line .= ' - ' . elite_ai_stage_label($status);
        }
        if ($extra !== '') {
            $line .= ' - ' . $extra;
        }
        return $line;
    }
}

if (!function_exists('elite_ai_morning_sweep_payload')) {
    function elite_ai_morning_sweep_payload(): array
    {
        $newLeads = elite_ai_new_leads(5);
        $followUps = elite_ai_follow_up_candidates(5);
        $replies = elite_ai_replies_today(5);
        $noAnswer = elite_ai_no_answer_candidates(5);
        $notifications = elite_ai_notification_rows(5);

        $cards = [
            [
                'title' => 'New Leads needing first contact',
                'items' => array_map(static fn (array $lead): string => elite_ai_format_lead_line($lead, 'Created ' . format_datetime((string) ($lead['created_at'] ?? ''), 'M j g:i A')), $newLeads),
            ],
            [
                'title' => 'Contacted leads needing follow-up',
                'items' => array_map(static fn (array $lead): string => elite_ai_format_lead_line($lead, trim((string) ($lead['next_follow_up_at'] ?? '')) !== '' ? 'Due ' . format_datetime((string) ($lead['next_follow_up_at'] ?? ''), 'M j g:i A') : 'Needs review'), $followUps),
            ],
            [
                'title' => 'Replies needing response',
                'items' => array_map(static fn (array $row): string => elite_ai_format_lead_line($row, 'Reply at ' . format_datetime((string) ($row['created_at'] ?? ''), 'g:i A')), $replies),
            ],
            [
                'title' => 'No Answer review candidates',
                'items' => array_map(static fn (array $row): string => elite_ai_format_lead_line($row, 'Outbound attempts ' . (int) ($row['outbound_count'] ?? 0)), $noAnswer),
            ],
            [
                'title' => 'High-priority notifications',
                'items' => array_map(static fn (array $row): string => trim((string) ($row['title'] ?? 'CRM alert')) . ' - ' . trim((string) ($row['suggested_action'] ?? 'Review next step.')), array_filter($notifications, static fn (array $item): bool => ($item['priority'] ?? 'normal') === 'high')),
            ],
        ];

        return [
            'answer' => 'Morning sweep is ready. I pulled the newest first-contact leads, follow-ups, replies, No Answer review candidates, and the highest-priority notifications so you can decide what to handle first.',
            'cards' => array_values(array_filter($cards, static fn (array $card): bool => !empty($card['items']))),
            'tools_used' => ['pipeline_overview', 'new_leads', 'follow_up_candidates', 'replies_today', 'no_answer_review', 'notifications'],
        ];
    }
}

if (!function_exists('elite_ai_notifications_payload')) {
    function elite_ai_notifications_payload(): array
    {
        $notifications = elite_ai_notification_rows(5);
        $reviewedLeadIds = [];
        foreach ($notifications as &$notification) {
            $leadId = (int)($notification['lead_id'] ?? 0);
            if ($leadId <= 0 || empty($notification['is_new'])) {
                continue;
            }
            if (function_exists('lead_comm_mark_read')) {
                lead_comm_mark_read($leadId);
            }
            if (function_exists('lead_comm_update_rollup')) {
                lead_comm_update_rollup($leadId);
            }
            if (($notification['type'] ?? '') === 'new_lead' && function_exists('lead_comm_insert_activity')) {
                lead_comm_insert_activity($leadId, 'operator_notification_reviewed', 'New-lead notification marked read when the notification list was opened.', [
                    'source' => 'pipeline_notifications',
                    'notification_type' => 'new_lead',
                ], 'System');
            }
            $notification['is_new'] = false;
            $reviewedLeadIds[] = $leadId;
        }
        unset($notification);
        $reviewedLeadIds = array_values(array_unique($reviewedLeadIds));
        $cards = [[
            'title' => 'Latest notifications',
            'items' => array_map(static function (array $row): string {
                $card = is_array($row['assistant_card'] ?? null) ? $row['assistant_card'] : [];
                $summary = trim((string) ($card['summary'] ?? $row['title'] ?? 'CRM alert'));
                $next = trim((string) ($card['recommended_action'] ?? $row['suggested_action'] ?? 'Review next step.'));
                $state = !empty($row['is_new']) ? 'Unread' : 'Read';
                return $state . ': ' . $summary . ' - ' . $next;
            }, $notifications),
        ]];
        return [
            'answer' => $notifications
                ? 'Here are the latest notifications.' . ($reviewedLeadIds ? ' Opening this list marked the unread items as read.' : '')
                : 'There are no notification items to review right now.',
            'cards' => $notifications ? $cards : [],
            'actions' => [],
            'reviewed_lead_ids' => $reviewedLeadIds,
            'tools_used' => ['notifications'],
        ];
    }
}

if (!function_exists('elite_ai_mark_notification_reviewed_payload')) {
    function elite_ai_mark_notification_reviewed_payload(array $user, int $leadId, string $source = 'elite_ai'): array
    {
        if ($leadId <= 0) {
            return [
                'ok' => false,
                'message' => 'Tell me which lead notification to clear.',
            ];
        }

        $lead = elite_ai_load_lead($leadId);
        if (!$lead) {
            return [
                'ok' => false,
                'message' => 'I could not find that lead.',
            ];
        }

        if (!function_exists('lead_comm_mark_read')) {
            return [
                'ok' => false,
                'message' => 'Notification clearing is not available right now.',
            ];
        }

        lead_comm_mark_read($leadId);
        if (function_exists('lead_comm_insert_activity')) {
            $createdBy = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
            lead_comm_insert_activity($leadId, 'operator_notification_reviewed', 'Elite AI marked the active notification reviewed after operator instruction.', [
                'source' => $source,
                'user_id' => (int) ($user['id'] ?? 0),
                'draft_before_send_rule' => true,
            ], $createdBy !== '' ? $createdBy : 'Elite AI');
        }
        if (function_exists('lead_comm_update_rollup')) {
            lead_comm_update_rollup($leadId);
        }

        $leadName = trim((string) ($lead['full_name'] ?? 'Lead #' . $leadId));
        return [
            'ok' => true,
            'action' => 'mark_reviewed',
            'lead_id' => $leadId,
            'message' => 'Cleared active notifications for ' . ($leadName !== '' ? $leadName : 'lead #' . $leadId) . '. No SMS or email was sent.',
            'answer' => 'Cleared active notifications for ' . ($leadName !== '' ? $leadName : 'lead #' . $leadId) . '. No SMS or email was sent.',
            'cards' => [[
                'title' => 'Internal action completed',
                'items' => [
                    'Marked unread inbound replies reviewed.',
                    'Added an internal activity note.',
                    'No patient-facing message was sent.',
                ],
            ]],
            'actions' => [],
            'tools_used' => ['notification_review', 'lead_thread'],
        ];
    }
}

if (!function_exists('elite_ai_pipeline_payload')) {
    function elite_ai_pipeline_payload(): array
    {
        $counts = elite_ai_stage_counts();
        $stale = elite_ai_stale_leads(5);
        $cards = [[
            'title' => 'Pipeline by stage',
            'items' => array_map(static function (string $status) use ($counts): string {
                return elite_ai_stage_label($status) . ': ' . (int) $counts[$status];
            }, array_keys($counts)),
        ]];

        if ($stale) {
            $cards[] = [
                'title' => 'Stale leads needing review',
                'items' => array_map(static fn (array $lead): string => elite_ai_format_lead_line($lead, 'Updated ' . format_datetime((string) ($lead['updated_at'] ?? ''), 'M j g:i A')), $stale),
            ];
        }

        return [
            'answer' => 'Here is the current pipeline snapshot with stage counts and the oldest active leads that look stale.',
            'cards' => $cards,
            'tools_used' => ['pipeline_overview', 'stale_leads'],
        ];
    }
}

if (!function_exists('elite_ai_prompt_requests_stage_count')) {
    function elite_ai_prompt_requests_stage_count(string $prompt): ?string
    {
        $normalized = strtolower(trim($prompt));
        if ($normalized === '' || !preg_match('/\bhow many\b|\bcount\b|\bnumber of\b/i', $normalized)) {
            return null;
        }

        $map = [
            'first touch attempted' => 'attempted_contact',
            'first touch attempt' => 'attempted_contact',
            'attempted contact' => 'attempted_contact',
            'first touch sent' => 'contacted',
            'first touch' => 'contacted',
            'scheduling' => 'in_contact',
            'in communication' => 'in_contact',
            'in contact' => 'in_contact',
            'consultation booked' => 'consultation_booked',
            'no show' => 'no_show_reschedule',
            'no show reschedule' => 'no_show_reschedule',
            'reschedule' => 'no_show_reschedule',
            'new lead' => 'new_lead',
            'contacted' => 'contacted',
            'nurture' => 'no_answer',
            'no answer' => 'no_answer',
            'no answer nurture' => 'no_answer',
            'opted out' => 'opted_out',
            'sale closed' => 'sale_closed',
            'treatment accepted' => 'treatment_accepted',
            'treatment completed' => 'treatment_completed',
            'consult completed' => 'consult_completed',
            'lead lost' => 'lost_lead',
        ];

        foreach ($map as $label => $status) {
            if (str_contains($normalized, $label)) {
                return $status;
            }
        }

        return null;
    }
}

if (!function_exists('elite_ai_pipeline_count_payload')) {
    function elite_ai_pipeline_count_payload(string $status): array
    {
        $counts = elite_ai_stage_counts();
        $count = (int) ($counts[$status] ?? 0);
        $label = elite_ai_stage_label($status);

        return [
            'answer' => 'There are ' . $count . ' leads in ' . $label . '.',
            'cards' => [],
            'tools_used' => ['pipeline_count'],
        ];
    }
}

if (!function_exists('elite_ai_shorten_patient_quote')) {
    function elite_ai_shorten_patient_quote(string $text, int $limit = 180): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if ($text === '' || strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(substr($text, 0, max(0, $limit - 1))) . '...';
    }
}

if (!function_exists('elite_ai_lead_summary_payload')) {
    function elite_ai_lead_summary_payload(array $lead): array
    {
        $leadId = (int) ($lead['id'] ?? 0);
        $thread = elite_ai_lead_thread($leadId);
        $attempts = elite_ai_attempt_counts($leadId);
        $latestInbound = elite_ai_latest_direction_item($thread, 'inbound');
        $latestOutbound = elite_ai_latest_direction_item($thread, 'outbound');
        $latestActivity = elite_ai_latest_activity_item($thread);
        $missingItems = elite_ai_missing_items($lead);
        $recommendation = elite_ai_recommended_next_step($lead, $thread, $attempts);
        $conversionSummary = function_exists('lead_conversion_summary') ? lead_conversion_summary($lead) : null;
        $conversionStageLabel = (string)($conversionSummary['stage_label'] ?? ($lead['conversion_stage_label'] ?? ''));
        $conversionNextAction = (array)($conversionSummary['next_action'] ?? ($lead['conversion_next_action'] ?? []));
        $conversionBadges = (array)($conversionSummary['badges'] ?? ($lead['conversion_badges'] ?? []));
        $leadName = trim((string) ($lead['full_name'] ?? 'This lead'));
        $status = trim((string) ($lead['status'] ?? ''));
        $statusLabel = elite_ai_stage_label($status);
        $preferredContact = trim((string) ($lead['preferred_contact'] ?? ''));
        $sourceLabel = trim((string) ($lead['source'] ?? '')) !== '' ? trim((string) ($lead['source'] ?? '')) : 'Unknown';

        $recommendation = str_replace(
            [
                'Rule: client-facing messages must show a draft before send.',
                'Review the last communication and set the next best manual step.',
                'Review the latest inbound communication and prepare a draft reply before sending.',
                'Protect this lead and review appointment readiness only.',
            ],
            [
                '',
                'I would review the last communication and decide the next step.',
                'I would review the latest inbound message and prepare a reply draft.',
                'I would protect the appointment and review appointment readiness only.',
            ],
            $recommendation
        );
        $recommendation = trim((string) preg_replace('/\s+/', ' ', $recommendation));

        $conversationLine = '';
        if ($latestInbound) {
            $inboundTime = strtotime((string) ($latestInbound['created_at'] ?? '')) ?: 0;
            $outboundTime = $latestOutbound ? (strtotime((string) ($latestOutbound['created_at'] ?? '')) ?: 0) : 0;
            $conversationLine = $leadName . ' last replied';
            if (trim((string) ($latestInbound['created_at'] ?? '')) !== '') {
                $conversationLine .= ' at ' . format_datetime((string) ($latestInbound['created_at'] ?? ''), 'M j g:i A');
            }
            $conversationLine .= ': "' . elite_ai_shorten_patient_quote((string) ($latestInbound['body'] ?? $latestInbound['subject'] ?? '')) . '"';
            if ($latestOutbound && $outboundTime > $inboundTime) {
                $conversationLine .= ' We already answered after that at ' . format_datetime((string) ($latestOutbound['created_at'] ?? ''), 'M j g:i A') . '.';
            }
        } elseif ($latestOutbound) {
            $conversationLine = $leadName . ' has not sent an inbound reply yet. Last outbound touch was ' . format_datetime((string) ($latestOutbound['created_at'] ?? ''), 'M j g:i A') . '.';
        } else {
            $conversationLine = $leadName . ' does not show a recent conversation yet.';
        }

        $showConversionLabel = $conversionStageLabel !== ''
            && strcasecmp($conversionStageLabel, $statusLabel) !== 0
            && !($status === 'no_answer' && strcasecmp($conversionStageLabel, 'Nurture / Lost') === 0);
        $statusLine = 'They are in ' . $statusLabel . ($showConversionLabel ? ' / ' . $conversionStageLabel : '') . '.';

        $appointmentLine = '';
        if (trim((string) ($lead['consultation_date'] ?? '')) !== '') {
            $appointmentLine = 'Consult is set for ' . format_datetime((string) ($lead['consultation_date'] ?? ''), 'M j, Y g:i A') . '.';
        }

        $nextLine = '';
        if ($recommendation !== '') {
            $nextLine = elite_ai_conversational_next_line($recommendation);
        }

        $cards = [];
        $supportItems = [
            'Preferred contact: ' . ($preferredContact !== '' ? strtolower($preferredContact) : 'not set'),
            'Source: ' . $sourceLabel,
            'Contact attempts: ' . (int) ($attempts['outbound_total'] ?? 0) . ' outbound and ' . (int) ($attempts['inbound_total'] ?? 0) . ' inbound.',
        ];
        if ($appointmentLine !== '') {
            $supportItems[] = $appointmentLine;
        }
        if ($missingItems) {
            $supportItems[] = 'Missing: ' . implode(' ', $missingItems);
        }
        if (trim((string)($conversionNextAction['label'] ?? '')) !== '') {
            $supportItems[] = 'Next action signal: ' . trim((string)$conversionNextAction['label']);
        }
        foreach ($conversionBadges as $badge) {
            $label = trim((string)($badge['label'] ?? ''));
            if ($label !== '') {
                $supportItems[] = 'Flag: ' . $label;
            }
        }
        $cards[] = [
            'title' => 'Quick context',
            'items' => $supportItems,
        ];

        return [
            'answer' => trim(implode(' ', array_filter([$conversationLine, $statusLine, $nextLine]))),
            'cards' => $cards,
            'tools_used' => ['lead_summary', 'lead_thread', 'knowledge_rules'],
            'lead_id' => $leadId,
            'actions' => elite_ai_build_assistant_actions($lead),
        ];
    }
}

if (!function_exists('elite_ai_build_assistant_actions')) {
    function elite_ai_build_assistant_actions(array $lead): array
    {
        $actions = [];
        $leadId = (int) ($lead['id'] ?? 0);
        if ($leadId <= 0) {
            return $actions;
        }

        if (trim((string) ($lead['phone'] ?? '')) !== '' && trim((string) ($lead['sms_opt_status'] ?? 'unknown')) !== 'opted_out') {
            $actions[] = [
                'type' => 'draft_sms',
                'label' => 'Prepare SMS draft',
                'lead_id' => $leadId,
                'help' => 'Generate a SMS draft for human review.',
                'channel' => 'sms',
            ];
        }

        if (trim((string) ($lead['email'] ?? '')) !== '') {
            $actions[] = [
                'type' => 'draft_email',
                'label' => 'Prepare Email draft',
                'lead_id' => $leadId,
                'help' => 'Generate an email draft for human review.',
                'channel' => 'email',
            ];
        }

        return $actions;
    }
}

if (!function_exists('elite_ai_draft_channel_label')) {
    function elite_ai_draft_channel_label(string $actionType): string
    {
        return $actionType === 'draft_sms' ? 'SMS' : 'Email';
    }
}

if (!function_exists('elite_ai_parse_draft_payload')) {
    function elite_ai_parse_draft_payload($payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (!is_string($payload)) {
            return [];
        }

        $payload = trim($payload);
        if ($payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('elite_ai_normalize_draft_payload_for_response')) {
    function elite_ai_normalize_draft_payload_for_response(array $draft, string $actionType): array
    {
        $draft = $draft ?: [];
        $result = [];
        $normalized = array_merge([
            'channel' => elite_ai_draft_channel_label($actionType),
            'status' => 'Draft only - not sent',
            'type' => $actionType,
        ], $draft);

        if (!empty($normalized['reply']) && is_string($normalized['reply'])) {
            $result['reply'] = trim($normalized['reply']);
        }
        if (!empty($normalized['message']) && is_string($normalized['message'])) {
            $result['reply'] = trim($normalized['message']);
        }
        if (!empty($normalized['text']) && is_string($normalized['text'])) {
            $result['reply'] = trim($normalized['text']);
        }
        if (!empty($normalized['body']) && is_string($normalized['body'])) {
            $result['body'] = trim($normalized['body']);
        }
        if (!empty($normalized['subject']) && is_string($normalized['subject'])) {
            $result['subject'] = trim($normalized['subject']);
        }

        $result['status'] = $normalized['status'];
        $result['channel'] = $normalized['channel'];
        $result['type'] = $normalized['type'];
        return $result;
    }
}

if (!function_exists('elite_ai_build_draft_preview_actions')) {
    function elite_ai_build_draft_preview_actions(int $leadId, int $actionId): array
    {
        $base = [
            ['type' => 'use_draft', 'label' => 'Use Draft', 'lead_id' => $leadId, 'action_id' => $actionId],
            ['type' => 'edit_draft', 'label' => 'Edit Draft', 'lead_id' => $leadId, 'action_id' => $actionId],
            ['type' => 'cancel_draft', 'label' => 'Cancel', 'lead_id' => $leadId, 'action_id' => $actionId],
        ];

        return array_map(
            static fn(array $action): array => [
                'type' => $action['type'],
                'label' => $action['label'],
                'lead_id' => $action['lead_id'],
                'action_id' => $action['action_id'],
            ],
            $base
        );
    }
}

if (!function_exists('elite_ai_pending_action_row')) {
    function elite_ai_pending_action_row(array $row): array
    {
        $actionType = (string) ($row['action_type'] ?? '');
        $leadId = (int) ($row['lead_id'] ?? 0);
        $draft = elite_ai_parse_draft_payload($row['draft_payload_json'] ?? null);
        $payload = elite_ai_normalize_draft_payload_for_response($draft, $actionType);
        $preview = elite_ai_draft_preview_text($actionType, $payload);

        return [
            'action_id' => (int) ($row['id'] ?? 0),
            'lead_id' => $leadId,
            'lead_name' => trim((string) ($row['full_name'] ?? '')),
            'action_type' => $actionType,
            'channel' => elite_ai_draft_channel_label($actionType),
            'status' => 'Pending review',
            'draft_preview' => $preview,
            'draft' => $payload,
            'created_at' => (string) ($row['updated_at'] ?? ''),
            'actions' => elite_ai_build_draft_preview_actions($leadId, (int) ($row['id'] ?? 0)),
        ];
    }
}

if (!function_exists('elite_ai_pending_drafts_for_user')) {
    function elite_ai_pending_drafts_for_user(array $user, int $limit = 6): array
    {
        $limit = max(1, min(12, $limit));
        if ((int) ($user['id'] ?? 0) <= 0) {
            return [];
        }

        try {
            $rows = db_all(
                "SELECT q.id, q.action_type, q.lead_id, q.created_at, q.updated_at, q.draft_payload_json, l.full_name
                 FROM elite_ai_action_queue q
                 LEFT JOIN leads l ON l.id = q.lead_id
                 WHERE q.user_id = :user_id AND q.status = :status
                 ORDER BY q.updated_at DESC, q.id DESC
                 LIMIT {$limit}",
                [
                    'user_id' => (int) ($user['id'] ?? 0),
                    'status' => 'pending_review',
                ]
            );

            return array_map('elite_ai_pending_action_row', $rows ?: []);
        } catch (Throwable $e) {
            esm_log('elite_ai', 'Could not load pending draft queue.', ['error' => $e->getMessage()]);
            return [];
        }
    }
}

if (!function_exists('elite_ai_load_action_item')) {
    function elite_ai_load_action_item(array $user, int $actionId): ?array
    {
        if ((int) ($user['id'] ?? 0) <= 0 || $actionId <= 0) {
            return null;
        }

        try {
            $item = db_one(
                'SELECT id, user_id, action_type, lead_id, status, draft_payload_json, request_prompt, request_context_json, created_at
                 FROM elite_ai_action_queue
                 WHERE id = :id AND user_id = :user_id
                 LIMIT 1',
                [
                    'id' => $actionId,
                    'user_id' => (int) ($user['id'] ?? 0),
                ]
            );
            return $item ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('elite_ai_mark_action_status')) {
    function elite_ai_mark_action_status(int $actionId, string $status): bool
    {
        try {
            db_query(
                "UPDATE elite_ai_action_queue
                 SET status = :status, updated_at = :updated_at
                 WHERE id = :id",
                [
                    'id' => $actionId,
                    'status' => $status,
                    'updated_at' => now(),
                ]
            );
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('elite_ai_create_action_item')) {
    function elite_ai_create_action_item(array $user, string $surface, array $lead, string $actionType, array $request, array $draft): int
    {
        $actionType = trim($actionType);
        if ($actionType === '') {
            return 0;
        }

        $leadId = (int) ($lead['id'] ?? 0);
        if ($leadId <= 0) {
            return 0;
        }

        $context = elite_ai_normalize_context($request);
        $requestPrompt = trim((string) ($request['prompt'] ?? ''));

        try {
            $recent = db_one(
                "SELECT id, draft_payload_json
                 FROM elite_ai_action_queue
                 WHERE user_id = :user_id
                   AND action_type = :action_type
                   AND lead_id = :lead_id
                   AND status = :status
                   AND request_prompt = :request_prompt
                   AND draft_payload_json IS NOT NULL
                   AND TRIM(draft_payload_json) <> ''
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                 ORDER BY id DESC
                 LIMIT 1",
                [
                    'user_id' => (int) ($user['id'] ?? 0),
                    'action_type' => $actionType,
                    'lead_id' => $leadId,
                    'status' => 'pending_review',
                    'request_prompt' => $requestPrompt,
                ]
            );

            if (!empty($recent['id'])) {
                db_query(
                    "UPDATE elite_ai_action_queue
                     SET surface = :surface,
                         request_prompt = :request_prompt,
                         request_context_json = :request_context_json,
                         request_payload_json = :request_payload_json,
                         draft_payload_json = :draft_payload_json,
                         updated_at = :updated_at
                     WHERE id = :id",
                    [
                        'id' => (int) $recent['id'],
                        'surface' => $surface,
                        'request_prompt' => $requestPrompt,
                        'request_context_json' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'request_payload_json' => json_encode($request, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'draft_payload_json' => json_encode($draft, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]
                );
                return (int) $recent['id'];
            }
        } catch (Throwable $e) {
            // Proceed with creating a new row on lookup failure.
        }

        try {
            return (int) db_insert(
                'INSERT INTO elite_ai_action_queue
                    (user_id, surface, action_type, lead_id, status, request_prompt, request_context_json, request_payload_json, draft_payload_json, updated_at, completed_at)
                 VALUES (:user_id, :surface, :action_type, :lead_id, :status, :request_prompt, :request_context_json, :request_payload_json, :draft_payload_json, :updated_at, :completed_at)',
                [
                    'user_id' => (int) ($user['id'] ?? 0),
                    'surface' => $surface,
                    'action_type' => $actionType,
                    'lead_id' => $leadId,
                    'status' => 'pending_review',
                    'request_prompt' => $requestPrompt,
                    'request_context_json' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'request_payload_json' => json_encode($request, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'draft_payload_json' => json_encode($draft, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                    'completed_at' => null,
                ]
            );
        } catch (Throwable $e) {
            esm_log('elite_ai', 'Could not create action queue item.', [
                'error' => $e->getMessage(),
                'user_id' => (int) ($user['id'] ?? 0),
                'lead_id' => $leadId,
                'action_type' => $actionType,
            ]);
            return 0;
        }
    }
}

if (!function_exists('elite_ai_sms_draft_has_text')) {
    function elite_ai_sms_draft_has_text(array $draft): bool
    {
        $reply = trim((string) ($draft['reply'] ?? $draft['message'] ?? $draft['text'] ?? $draft['body'] ?? ''));
        return $reply !== '';
    }
}

if (!function_exists('elite_ai_email_draft_has_text')) {
    function elite_ai_email_draft_has_text(array $draft): bool
    {
        $subject = trim((string) ($draft['subject'] ?? ''));
        $body = trim((string) ($draft['body'] ?? ''));
        return $subject !== '' && $body !== '';
    }
}

if (!function_exists('elite_ai_draft_preview_text')) {
    function elite_ai_draft_preview_text(string $actionType, array $draft): string
    {
        if ($actionType === 'draft_sms') {
            return trim((string) ($draft['reply'] ?? $draft['message'] ?? $draft['text'] ?? $draft['body'] ?? ''));
        }

        if ($actionType === 'draft_email') {
            $subject = trim((string) ($draft['subject'] ?? ''));
            $body = trim((string) ($draft['body'] ?? ''));
            if ($subject === '' && $body === '') {
                return '';
            }

            return 'Subject: ' . ($subject !== '' ? $subject : '(no subject)') . "\n\n" . ($body !== '' ? $body : '(no body)');
        }

        return '';
    }
}

if (!function_exists('elite_ai_ensure_sms_draft_payload')) {
    function elite_ai_ensure_sms_draft_payload(array $draft, array $lead, string $instruction): array
    {
        if (elite_ai_sms_draft_has_text($draft)) {
            return $draft;
        }

        return elite_ai_fallback_sms_draft($lead, $instruction);
    }
}

if (!function_exists('elite_ai_ensure_email_draft_payload')) {
    function elite_ai_ensure_email_draft_payload(array $draft, array $lead, string $instruction): array
    {
        if (elite_ai_email_draft_has_text($draft)) {
            return $draft;
        }

        return elite_ai_fallback_email_draft($lead, $instruction);
    }
}

if (!function_exists('elite_ai_fallback_sms_draft')) {
    function elite_ai_fallback_sms_draft(array $lead, string $instruction): array
    {
        $firstName = trim((string) ($lead['first_name'] ?? ''));
        if ($firstName === '') {
            $firstName = trim((string) (($parts = preg_split('/\s+/', trim((string) ($lead['full_name'] ?? '')))) ? ($parts[0] ?? 'there') : 'there'));
            if ($firstName === '') {
                $firstName = 'there';
            }
        }

        $baseNote = 'Need manual review before sending.';
        if ($instruction === '') {
            $baseNote = 'Suggested follow-up draft ready for review.';
        }

        return [
            'classification' => 'needs_human_review',
            'reply' => 'Hi ' . $firstName . ', thanks for reaching out about your smile needs. We received your message and will follow up shortly to schedule a free consultation at your convenience.',
            'note' => $baseNote . ' ' . $instruction,
            'recommended_stage' => 'contacted',
            'needs_human_review' => true,
            'should_send' => false,
            'confidence' => 0.0,
        ];
    }
}

if (!function_exists('elite_ai_fallback_email_draft')) {
    function elite_ai_fallback_email_draft(array $lead, string $instruction): array
    {
        $firstName = trim((string) ($lead['first_name'] ?? ''));
        if ($firstName === '') {
            $firstName = trim((string) (($parts = preg_split('/\s+/', trim((string) ($lead['full_name'] ?? '')))) ? ($parts[0] ?? 'there') : 'there'));
            if ($firstName === '') {
                $firstName = 'there';
            }
        }

        $baseNote = 'Need manual review before sending.';
        if ($instruction === '') {
            $baseNote = 'Suggested email draft ready for review.';
        }

        return [
            'classification' => 'needs_human_review',
            'subject' => 'Re: Free Consultation Follow-up',
            'body' => "Hi {$firstName},\n\nThank you for reaching out about your smile.\n\nWe received your message and a team member will follow up to help schedule your free consultation.\n\nThe Elite Smiles Team",
            'note' => $baseNote . ' ' . $instruction,
            'recommended_stage' => 'contacted',
            'next_follow_up_at' => '',
            'needs_human_review' => true,
            'should_send' => false,
            'confidence' => 0.0,
        ];
    }
}

if (!function_exists('elite_ai_requested_stage_key')) {
    function elite_ai_requested_stage_key(string $text): string
    {
        $normalized = strtolower(trim($text));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/[^a-z0-9\s\/_-]+/i', ' ', $normalized);
        $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $normalized));
        $patterns = [
            'new_lead' => '/\bnew\s*lead\b/',
            'contacted' => '/\b(?:contacted|first\s*touch(?:\s*sent)?)\b/',
            'in_contact' => '/\b(?:in\s*contact|scheduling|schedule|schudele|schudle)\b/',
            'consultation_booked' => '/\b(?:consultation\s*booked|consult\s*booked|booked)\b/',
            'no_show_reschedule' => '/\b(?:no\s*show|reschedule)\b/',
            'consult_completed' => '/\b(?:consult\s*completed|consultation\s*completed|completed\s*consult)\b/',
            'treatment_accepted' => '/\b(?:treatment\s*accepted|accepted|sale\s*closed)\b/',
            'treatment_completed' => '/\b(?:treatment\s*completed|completed\s*treatment|completed\s*paid|paid\s*completed|case\s*completed)\b/',
            'no_answer' => '/\b(?:no\s*answer|nurture|follow\s*later)\b/',
            'opted_out' => '/\b(?:opted\s*out|unsubscribe|stop)\b/',
            'lost_lead' => '/\b(?:lost|archive|archived)\b/',
        ];

        foreach ($patterns as $stage => $pattern) {
            if ((bool) preg_match($pattern, $normalized)) {
                return $stage;
            }
        }

        return '';
    }
}

if (!function_exists('elite_ai_prompt_requests_stage_move')) {
    function elite_ai_prompt_requests_stage_move(string $prompt): bool
    {
        $normalized = strtolower(trim($prompt));
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match('/\b(?:move|send|put|set|change|mark)\b.+\b(?:to|as|into)\b/i', $normalized)
            && elite_ai_requested_stage_key($normalized) !== '';
    }
}

if (!function_exists('elite_ai_extract_stage_move_lead_query')) {
    function elite_ai_extract_stage_move_lead_query(string $prompt): string
    {
        $text = trim($prompt);
        if ($text === '') {
            return '';
        }

        $patterns = [
            '/\b(?:move|send|put|set|change|mark)\s+(.+?)\s+(?:to|as|into)\s+.+$/i',
            '/^(.+?)\s+(?:to|as|into)\s+(?:nurture|no\s*answer|lost|archive|archived|scheduling|consultation\s*booked|booked|first\s*touch\s*sent|first\s*touch\s*attempted)$/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $query = trim((string) ($matches[1] ?? ''));
                $query = preg_replace('/\b(?:lead|patient|card)\b/i', '', $query) ?? $query;
                $query = trim((string) preg_replace('/\s+/', ' ', $query), " \t\n\r\0\x0B?.");
                if ((bool) preg_match('/^(?:him|her|them|it|this|that|same|same lead|same patient|the same)$/i', $query)) {
                    return '';
                }
                return $query;
            }
        }

        return '';
    }
}

if (!function_exists('elite_ai_conversational_next_line')) {
    function elite_ai_conversational_next_line(string $recommendation): string
    {
        $recommendation = trim((string) preg_replace('/\s+/', ' ', $recommendation));
        if ($recommendation === '') {
            return '';
        }

        $recommendation = rtrim($recommendation, '.');
        if ((bool) preg_match('/^(?:i would|i\'d|we should|review|prepare|protect|confirm|ask|offer)\b/i', $recommendation)) {
            return ucfirst($recommendation) . '.';
        }

        return 'I would ' . lcfirst($recommendation) . '.';
    }
}

if (!function_exists('elite_ai_prompt_requests_internal_note')) {
    function elite_ai_prompt_requests_internal_note(string $prompt): bool
    {
        return (bool) preg_match('/\b(?:make|add|leave|create|record)\s+(?:a\s+)?notes?\b|\bnotes?\b/i', strtolower(trim($prompt)));
    }
}

if (!function_exists('elite_ai_internal_action_plan')) {
    function elite_ai_internal_action_plan(string $prompt, array $lead): array
    {
        $leadId = (int) ($lead['id'] ?? 0);
        if ($leadId <= 0) {
            return ['cards' => [], 'actions' => []];
        }

        $items = [];
        $actions = [];
        $stage = elite_ai_requested_stage_key($prompt);
        if ($stage !== '') {
            $label = elite_ai_stage_label($stage);
            $items[] = 'Internal action available: move this lead to ' . $label . '.';
            $actions[] = [
                'type' => 'move_stage',
                'label' => 'Move to ' . $label,
                'lead_id' => $leadId,
                'target_status' => $stage,
                'help' => 'Move this lead to ' . $label . ' after operator approval.',
            ];
        }

        if (elite_ai_prompt_requests_internal_note($prompt) || $stage !== '') {
            $items[] = 'Internal action available: add an activity note summarizing this assistant plan.';
            $actions[] = [
                'type' => 'add_note',
                'label' => 'Add internal note',
                'lead_id' => $leadId,
                'help' => 'Add an internal note summarizing: ' . mb_substr(trim($prompt), 0, 220),
            ];
        }

        if (!$items) {
            return ['cards' => [], 'actions' => []];
        }

        $items[] = 'Patient-facing SMS/email still requires explicit draft approval before sending.';

        return [
            'cards' => [[
                'title' => 'Action plan',
                'items' => $items,
            ]],
            'actions' => $actions,
        ];
    }
}

if (!function_exists('elite_ai_handle_move_stage_action')) {
    function elite_ai_handle_move_stage_action(array $user, array $request, string $surface): array
    {
        $leadId = (int) ($request['lead_id'] ?? 0);
        $targetStatus = trim((string) ($request['target_status'] ?? $request['status'] ?? ''));
        if ($targetStatus === '') {
            $targetStatus = elite_ai_requested_stage_key((string) ($request['instruction'] ?? $request['prompt'] ?? ''));
        }
        if ($leadId <= 0 || $targetStatus === '') {
            return ['ok' => false, 'message' => 'I need a lead and a specific stage before I can move it.'];
        }

        $lead = elite_ai_load_lead($leadId);
        if (!$lead) {
            return ['ok' => false, 'message' => 'Lead not found.'];
        }

        $allowedStages = function_exists('lead_stage_labels') ? lead_stage_labels() : [];
        if (!isset($allowedStages[$targetStatus])) {
            return ['ok' => false, 'message' => 'That stage is not allowed.'];
        }

        $oldStatus = trim((string) ($lead['status'] ?? ''));
        $setParts = ['status = :status'];
        $params = ['id' => $leadId, 'status' => $targetStatus];
        if (function_exists('leads_has_column') && leads_has_column('updated_at')) {
            $setParts[] = 'updated_at = :updated_at';
            $params['updated_at'] = now();
        }

        db_query('UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1', $params);
        if ($oldStatus !== $targetStatus && function_exists('lead_comm_insert_activity')) {
            lead_comm_insert_activity(
                $leadId,
                'stage_change',
                'Elite AI moved stage from ' . elite_ai_stage_label($oldStatus) . ' to ' . elite_ai_stage_label($targetStatus) . '.',
                ['from' => $oldStatus, 'to' => $targetStatus, 'source' => 'elite_ai_action'],
                trim((string) ($user['first_name'] ?? 'Elite AI'))
            );
        }
        if (function_exists('lead_comm_update_rollup')) {
            lead_comm_update_rollup($leadId);
        }

        return [
            'ok' => true,
            'surface' => $surface,
            'action' => 'move_stage',
            'lead_id' => $leadId,
            'status' => $targetStatus,
            'answer' => 'Moved ' . trim((string) ($lead['full_name'] ?? 'this lead')) . ' to ' . elite_ai_stage_label($targetStatus) . '.',
            'message' => 'Lead stage updated.',
            'cards' => [],
            'actions' => [],
        ];
    }
}

if (!function_exists('elite_ai_handle_add_note_action')) {
    function elite_ai_handle_add_note_action(array $user, array $request, string $surface): array
    {
        $leadId = (int) ($request['lead_id'] ?? 0);
        if ($leadId <= 0) {
            return ['ok' => false, 'message' => 'I need a lead before I can add a note.'];
        }

        $lead = elite_ai_load_lead($leadId);
        if (!$lead) {
            return ['ok' => false, 'message' => 'Lead not found.'];
        }

        $instruction = trim((string) ($request['instruction'] ?? $request['prompt'] ?? ''));
        $note = trim((string) ($request['note'] ?? ''));
        if ($note === '') {
            $note = 'Elite AI action note: ' . ($instruction !== '' ? $instruction : 'Operator reviewed this lead with Elite AI.');
        }
        $note = mb_substr($note, 0, 900);

        if (!function_exists('lead_comm_insert_activity')) {
            return ['ok' => false, 'message' => 'Activity notes are not available right now.'];
        }

        lead_comm_insert_activity(
            $leadId,
            'operator_note',
            $note,
            ['source' => 'elite_ai_action'],
            trim((string) ($user['first_name'] ?? 'Elite AI'))
        );
        if (function_exists('lead_comm_update_rollup')) {
            lead_comm_update_rollup($leadId);
        }

        return [
            'ok' => true,
            'surface' => $surface,
            'action' => 'add_note',
            'lead_id' => $leadId,
            'answer' => 'Added an internal note for ' . trim((string) ($lead['full_name'] ?? 'this lead')) . '.',
            'message' => 'Internal note added.',
            'cards' => [],
            'actions' => [],
        ];
    }
}

if (!function_exists('elite_ai_prepare_action_draft')) {
    function elite_ai_prepare_action_draft(array $user, array $request, string $surface): array
    {
        $actionType = strtolower(trim((string) ($request['assistant_action'] ?? '')));
        $actionId = (int) ($request['action_id'] ?? 0);
        if (in_array($actionType, ['use_draft', 'edit_draft', 'cancel_draft'], true)) {
            if ($actionId <= 0) {
                return ['ok' => false, 'message' => 'Missing draft action id.'];
            }

            $item = elite_ai_load_action_item($user, $actionId);
            if (!$item) {
                return ['ok' => false, 'message' => 'Draft action not found.'];
            }

            if (trim((string) ($item['status'] ?? '')) !== 'pending_review') {
                return ['ok' => false, 'message' => 'This draft is no longer available for review.'];
            }

            $draftType = (string) ($item['action_type'] ?? '');
            $draftPayload = elite_ai_normalize_draft_payload_for_response(elite_ai_parse_draft_payload($item['draft_payload_json'] ?? null), $draftType);
            $leadId = (int) ($item['lead_id'] ?? 0);

            if ($actionType === 'cancel_draft') {
                elite_ai_mark_action_status($actionId, 'cancelled');
                return [
                    'ok' => true,
                    'surface' => $surface,
                    'action' => 'cancel_draft',
                    'action_id' => $actionId,
                    'lead_id' => $leadId,
                    'status' => 'cancelled',
                    'message' => 'Draft cancelled.',
                    'draft_badge' => 'Draft only - not sent',
                    'draft_actions' => [],
                ];
            }

                return [
                    'ok' => true,
                    'surface' => $surface,
                    'action' => $actionType,
                    'action_id' => $actionId,
                    'lead_id' => $leadId,
                    'action_type' => $draftType,
                    'draft' => $draftPayload,
                    'payload' => $draftPayload,
                    'draft_preview' => elite_ai_draft_preview_text($draftType, $draftPayload),
                    'draft_badge' => 'Draft only - not sent',
                'draft_actions' => elite_ai_build_draft_preview_actions($leadId, $actionId),
                'status' => 'pending_review',
                'message' => $actionType === 'edit_draft'
                    ? 'Draft ready in edit mode.'
                    : 'Draft ready in composer for review.',
                'channel' => elite_ai_draft_channel_label($draftType),
                'warning' => null,
            ];
        }

        if (!in_array($actionType, ['draft_sms', 'draft_email'], true)) {
            return ['ok' => false, 'message' => 'Unsupported assistant action.'];
        }

        $leadId = (int) ($request['lead_id'] ?? 0);
        if ($leadId <= 0) {
            return ['ok' => false, 'message' => 'Missing lead id for assistant action.'];
        }

        $lead = elite_ai_load_lead($leadId);
        if (!$lead) {
            return ['ok' => false, 'message' => 'Lead not found.'];
        }

        $instruction = trim((string) ($request['instruction'] ?? ''));
        if ($instruction === '') {
            $instruction = 'Prepare a warm, human-reviewed follow-up draft based on the lead context and recent communication.';
        }

        if ($actionType === 'draft_sms') {
            if (trim((string) ($lead['phone'] ?? '')) === '') {
                return ['ok' => false, 'message' => 'Add a lead phone number before drafting SMS.'];
            }
            if (trim((string) ($lead['sms_opt_status'] ?? 'unknown')) === 'opted_out') {
                return ['ok' => false, 'message' => 'This lead has opted out of SMS.'];
            }

            $result = lead_ai_generate_reply($lead, $instruction, 'sms_draft');
            $usedFallback = false;
            if (empty($result['ok'])) {
                $result['data'] = elite_ai_fallback_sms_draft($lead, $instruction);
                $usedFallback = true;
            }

            $result['data'] = elite_ai_ensure_sms_draft_payload((array) ($result['data'] ?? []), $lead, $instruction);
            if (!$usedFallback && !elite_ai_sms_draft_has_text((array) ($result['data'] ?? []))) {
                $result['data'] = elite_ai_fallback_sms_draft($lead, $instruction);
                $usedFallback = true;
            }

            $actionId = elite_ai_create_action_item($user, $surface, $lead, 'draft_sms', $request, (array) ($result['data'] ?? []));
            if ($actionId <= 0) {
                return ['ok' => false, 'message' => 'SMS draft was generated, but the approval queue could not save it. Please try again before using this draft.'];
            }

            $draftPayload = (array) ($result['data'] ?? []);
            return [
                'ok' => true,
                'surface' => $surface,
                'action' => 'draft_sms',
                'action_type' => 'draft_sms',
                'lead_id' => $leadId,
                'action_id' => $actionId,
                'channel' => 'SMS',
                'draft' => $draftPayload,
                'payload' => $draftPayload,
                'draft_preview' => elite_ai_draft_preview_text('draft_sms', $draftPayload),
                'draft_badge' => 'Draft only - not sent',
                'draft_actions' => elite_ai_build_draft_preview_actions($leadId, $actionId),
                'status' => 'pending_review',
                'message' => 'SMS draft created and queued for approval.',
                'warning' => $usedFallback ? 'AI draft fallback used.' : null,
            ];
        }

        if (trim((string) ($lead['email'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'Add a lead email address before drafting email.'];
        }

        $result = lead_ai_generate_email($lead, $instruction, 'email_draft');
        $usedFallback = false;
        if (empty($result['ok'])) {
            $result['data'] = elite_ai_fallback_email_draft($lead, $instruction);
            $usedFallback = true;
        }

        $result['data'] = elite_ai_ensure_email_draft_payload((array) ($result['data'] ?? []), $lead, $instruction);
        if (!$usedFallback && !elite_ai_email_draft_has_text((array) ($result['data'] ?? []))) {
            $result['data'] = elite_ai_fallback_email_draft($lead, $instruction);
            $usedFallback = true;
        }

        $actionId = elite_ai_create_action_item($user, $surface, $lead, 'draft_email', $request, (array) ($result['data'] ?? []));
        if ($actionId <= 0) {
            return ['ok' => false, 'message' => 'Email draft was generated, but the approval queue could not save it. Please try again before using this draft.'];
        }

            $draftPayload = (array) ($result['data'] ?? []);
            return [
                'ok' => true,
                'surface' => $surface,
                'action' => 'draft_email',
                'action_type' => 'draft_email',
                'lead_id' => $leadId,
                'action_id' => $actionId,
                'channel' => 'Email',
                'draft' => $draftPayload,
                'payload' => $draftPayload,
                'draft_preview' => elite_ai_draft_preview_text('draft_email', $draftPayload),
                'draft_badge' => 'Draft only - not sent',
                'draft_actions' => elite_ai_build_draft_preview_actions($leadId, $actionId),
                'status' => 'pending_review',
                'message' => 'Email draft created and queued for approval.',
                'warning' => $usedFallback ? 'AI draft fallback used.' : null,
            ];
        }
}

if (!function_exists('elite_ai_handle_action_request')) {
    function elite_ai_handle_action_request(array $user, array $request): array
    {
        $surface = elite_ai_surface($request);
        $assistantAction = strtolower(trim((string) ($request['assistant_action'] ?? '')));
        if ($assistantAction === 'move_stage') {
            $context = elite_ai_normalize_context($request);
            $result = elite_ai_tool_run($user, 'lead.move_stage', $request + ['surface' => $surface], $context + ['surface' => $surface]);
            $result = elite_ai_plain_text_payload($result);
            elite_ai_log_interaction(
                $user,
                $surface,
                (string) ($request['prompt'] ?? $request['instruction'] ?? ''),
                ['lead.move_stage'],
                trim((string) ($result['answer'] ?? $result['message'] ?? 'Stage action completed.')),
                (int) ($result['lead_id'] ?? 0),
                $context
            );

            return $result + [
                'context' => $context,
                'current_subject' => elite_ai_current_subject_payload((int) ($result['lead_id'] ?? 0) ?: null),
                'tool_capabilities' => elite_ai_tool_capabilities($surface),
                'pending_drafts' => elite_ai_pending_drafts_for_user($user, 8),
            ];
        }

        if ($assistantAction === 'add_note') {
            $context = elite_ai_normalize_context($request);
            $result = elite_ai_tool_run($user, 'lead.add_note', $request + ['surface' => $surface], $context + ['surface' => $surface]);
            $result = elite_ai_plain_text_payload($result);
            elite_ai_log_interaction(
                $user,
                $surface,
                (string) ($request['prompt'] ?? $request['instruction'] ?? ''),
                ['lead.add_note'],
                trim((string) ($result['answer'] ?? $result['message'] ?? 'Note action completed.')),
                (int) ($result['lead_id'] ?? 0),
                $context
            );

            return $result + [
                'context' => $context,
                'current_subject' => elite_ai_current_subject_payload((int) ($result['lead_id'] ?? 0) ?: null),
                'tool_capabilities' => elite_ai_tool_capabilities($surface),
                'pending_drafts' => elite_ai_pending_drafts_for_user($user, 8),
            ];
        }

        if ($assistantAction === 'mark_reviewed') {
            $context = elite_ai_normalize_context($request);
            $leadId = (int) ($request['lead_id'] ?? $context['lead_id'] ?? 0);
            $result = elite_ai_tool_run($user, 'notifications.mark_reviewed', $request + [
                'surface' => $surface,
                'lead_id' => $leadId,
                'source' => 'elite_ai_action_button',
            ], $context + ['surface' => $surface]);
            $result = elite_ai_plain_text_payload($result);
            elite_ai_log_interaction(
                $user,
                $surface,
                (string) ($request['prompt'] ?? ''),
                ['notifications.mark_reviewed'],
                trim((string) ($result['message'] ?? 'Notification review action completed.')),
                $leadId,
                $context
            );

            return $result + [
                'surface' => $surface,
                'context' => $context,
                'current_subject' => elite_ai_current_subject_payload($leadId),
                'tool_capabilities' => elite_ai_tool_capabilities($surface),
                'pending_drafts' => elite_ai_pending_drafts_for_user($user, 8),
            ];
        }

        $result = elite_ai_prepare_action_draft($user, $request, $surface);
        if (!empty($result['ok'])) {
            $context = elite_ai_normalize_context($request);
            if (!empty($result['draft_preview'])) {
                $result['answer'] = trim((string) ($result['message'] ?? 'Draft ready.')) . "\n\n" . trim((string) $result['draft_preview']);
            }
            $result = elite_ai_plain_text_payload($result);
            elite_ai_log_interaction(
                $user,
                $surface,
                (string) ($request['prompt'] ?? ''),
                ['draft_' . (string) ($result['action'] ?? 'prepared')],
                trim((string) ($result['message'] ?? 'Draft action prepared for review.')),
                (int) ($result['lead_id'] ?? 0),
                $context
            );

            return $result + [
                'ok' => true,
                'context' => $context,
                'current_subject' => elite_ai_current_subject_payload((int) ($result['lead_id'] ?? 0) ?: null),
                'tool_capabilities' => elite_ai_tool_capabilities($surface),
                'pending_drafts' => elite_ai_pending_drafts_for_user($user, 8),
            ];
        }

        return [
            'ok' => false,
            'surface' => $surface,
            'message' => (string) ($result['message'] ?? 'Unable to prepare the requested action.'),
            'context' => elite_ai_normalize_context($request),
            'current_subject' => elite_ai_current_subject_payload((int) ($request['lead_id'] ?? 0) ?: null),
            'tool_capabilities' => elite_ai_tool_capabilities($surface),
            'pending_drafts' => elite_ai_pending_drafts_for_user($user, 8),
        ];
    }
}

if (!function_exists('elite_ai_no_answer_payload')) {
    function elite_ai_no_answer_payload(): array
    {
        $candidates = elite_ai_no_answer_candidates(8);
        return [
            'answer' => $candidates
                ? 'These look like No Answer review candidates based on high outbound attempts without a newer reply. This stays review-only and Consultation Booked remains protected.'
                : 'I did not find strong No Answer review candidates right now.',
            'cards' => $candidates ? [[
                'title' => 'No Answer review candidates',
                'items' => array_map(static fn (array $row): string => elite_ai_format_lead_line($row, $row['review_reason'] . ' Outbound attempts: ' . (int) ($row['outbound_count'] ?? 0)), $candidates),
            ]] : [],
            'tools_used' => ['no_answer_review', 'knowledge_rules'],
        ];
    }
}

if (!function_exists('elite_ai_follow_up_payload')) {
    function elite_ai_follow_up_payload(): array
    {
        $candidates = elite_ai_follow_up_candidates(8);
        return [
            'answer' => $candidates
                ? 'These contacted leads look like the next follow-up priorities based on due dates, follow-up flags, or replies that overtook the last outbound touch.'
                : 'I do not see active follow-up candidates right now.',
            'cards' => $candidates ? [[
                'title' => 'Follow-up candidates',
                'items' => array_map(static fn (array $lead): string => elite_ai_format_lead_line($lead, trim((string) ($lead['next_follow_up_at'] ?? '')) !== '' ? 'Due ' . format_datetime((string) ($lead['next_follow_up_at'] ?? ''), 'M j g:i A') : 'Needs review'), $candidates),
            ]] : [],
            'tools_used' => ['follow_up_candidates'],
        ];
    }
}

if (!function_exists('elite_ai_new_leads_payload')) {
    function elite_ai_new_leads_payload(): array
    {
        $leads = elite_ai_new_leads(8);
        return [
            'answer' => $leads
                ? 'These are the newest leads that still need first-contact review.'
                : 'There are no new leads waiting for first contact right now.',
            'cards' => $leads ? [[
                'title' => 'New leads needing first contact',
                'items' => array_map(static fn (array $lead): string => elite_ai_format_lead_line($lead, 'Created ' . format_datetime((string) ($lead['created_at'] ?? ''), 'M j g:i A')), $leads),
            ]] : [],
            'tools_used' => ['new_leads'],
        ];
    }
}

if (!function_exists('elite_ai_replies_payload')) {
    function elite_ai_replies_payload(): array
    {
        $replies = elite_ai_replies_today(8);
        return [
            'answer' => $replies
                ? 'These leads replied today and likely need a human-reviewed response next.'
                : 'I do not see inbound replies from today.',
            'cards' => $replies ? [[
                'title' => 'Replies today',
                'items' => array_map(static fn (array $row): string => elite_ai_format_lead_line($row, 'Reply at ' . format_datetime((string) ($row['created_at'] ?? ''), 'g:i A')), $replies),
            ]] : [],
            'tools_used' => ['replies_today'],
        ];
    }
}

if (!function_exists('elite_ai_extract_reference')) {
    function elite_ai_extract_reference(string $prompt): array
    {
        $prompt = trim($prompt);

        if (preg_match('/lead\s*#?\s*(\d+)/i', $prompt, $matches)) {
            return ['lead_id' => (int) $matches[1], 'query' => ''];
        }

        if (preg_match('/\b(?:summarize|summary|check|review)\s+(.+)$/i', $prompt, $matches)) {
            $subject = trim((string) $matches[1], " \t\n\r\0\x0B?.");
            if ($subject !== '' && !in_array(strtolower($subject), ['this', 'this lead', 'lead'], true)) {
                return ['lead_id' => 0, 'query' => $subject];
            }
        }

        if (preg_match('/\b(?:from|for)\s+([a-z][a-z\s\'\.-]{1,80})$/i', $prompt, $matches)) {
            $subject = trim((string) $matches[1], " \t\n\r\0\x0B?.");
            $subject = preg_replace('/\b(?:reply|replay|response|message|text|sms|email)\b/i', '', $subject);
            $subject = trim((string) preg_replace('/\s+/', ' ', (string) $subject));
            if ($subject !== '' && !in_array(strtolower($subject), ['this', 'this lead', 'lead'], true)) {
                return ['lead_id' => 0, 'query' => $subject];
            }
        }

        return ['lead_id' => 0, 'query' => ''];
    }
}

if (!function_exists('elite_ai_prompt_mentions_current_lead')) {
    function elite_ai_prompt_mentions_current_lead(string $prompt): bool
    {
        $prompt = strtolower($prompt);
        return str_contains($prompt, 'this lead') || str_contains($prompt, 'this patient') || str_contains($prompt, 'what should i do next');
    }
}

if (!function_exists('elite_ai_resolve_lead_from_request')) {
    function elite_ai_resolve_lead_from_request(string $prompt, array $context): array
    {
        if (($context['lead_id'] ?? 0) > 0 && elite_ai_prompt_mentions_current_lead($prompt)) {
            $lead = elite_ai_load_lead((int) $context['lead_id']);
            if ($lead) {
                return ['lead' => $lead, 'matches' => [], 'clarify' => ''];
            }
        }

        $reference = elite_ai_extract_reference($prompt);
        if (($reference['lead_id'] ?? 0) > 0) {
            $lead = elite_ai_load_lead((int) $reference['lead_id']);
            return ['lead' => $lead, 'matches' => [], 'clarify' => $lead ? '' : 'I could not find that lead number.'];
        }

        $query = trim((string) ($reference['query'] ?? ''));
        if ($query === '' && ($context['lead_id'] ?? 0) > 0) {
            $lead = elite_ai_load_lead((int) $context['lead_id']);
            if ($lead) {
                return ['lead' => $lead, 'matches' => [], 'clarify' => ''];
            }
        }

        if ($query === '') {
            return ['lead' => null, 'matches' => [], 'clarify' => 'Which lead should I summarize?'];
        }

        $matches = elite_ai_find_leads($query, 5);
        if (count($matches) === 1) {
            return ['lead' => $matches[0], 'matches' => [], 'clarify' => ''];
        }

        if (!$matches) {
            return ['lead' => null, 'matches' => [], 'clarify' => 'I could not find a lead matching "' . $query . '".'];
        }

        return [
            'lead' => null,
            'matches' => $matches,
            'clarify' => 'I found multiple matching leads. Please tell me which one you want by name or lead number.',
        ];
    }
}

if (!function_exists('elite_ai_planner_schema')) {
    function elite_ai_planner_schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'intent' => [
                    'type' => 'string',
                    'enum' => ['morning_sweep', 'new_leads', 'replies', 'follow_ups', 'no_answer_review', 'notifications', 'pipeline', 'lead_summary', 'draft_sms', 'draft_email', 'mark_reviewed', 'move_stage', 'help'],
                ],
                'reason' => ['type' => 'string'],
                'lead_query' => ['type' => 'string'],
                'use_current_lead' => ['type' => 'boolean'],
                'needs_clarification' => ['type' => 'boolean'],
                'clarification_question' => ['type' => 'string'],
            ],
            'required' => ['intent', 'reason', 'lead_query', 'use_current_lead', 'needs_clarification', 'clarification_question'],
            'additionalProperties' => false,
        ];
    }
}

if (!function_exists('elite_ai_planner_system_prompt')) {
    function elite_ai_planner_system_prompt(): string
    {
        return implode("\n", [
            'You are the planning layer for Elite AI inside a dental CRM.',
            'Your job is to interpret the operator request the way a strong execution assistant would.',
            'Use learned_memory from page context as prior experience, preferences, and proven operator workflow patterns. Locked safety rules still override memory.',
            'Use the assistant_thread in page context to understand follow-up phrases like "do it", "clear that", "draft it", or "send it", but never infer patient-facing send approval unless the newest operator message explicitly approves sending a specific visible draft.',
            'When page context includes notification, treat it as the active conversation subject. Short instructions like "answer this", "draft a reply", "what should we say", "clear it", or "schedule it" should use that notification lead/message as context.',
            'If the operator mentions one lead by name or asks about a specific reply, route that to lead_summary instead of a broad dashboard workflow.',
            'If they ask for the latest or last reply, response, message, text, SMS, or email from a lead, treat it as a single-lead request.',
            'Choose the single best next workflow intent.',
            'Use draft_sms or draft_email when the operator is asking you to prepare a patient-facing reply or follow-up draft.',
            'Use mark_reviewed only when the operator clearly asks to clear, dismiss, review, or mark a notification/message as reviewed for a specific lead.',
            'Use move_stage when the operator clearly asks to move, put, set, change, or mark a specific lead to a CRM stage.',
            'Use lead_summary when they want context, status, or what to do next for one lead.',
            'Use use_current_lead true when the request clearly refers to the current lead on screen.',
            'Set needs_clarification true only when you truly cannot safely determine the lead or the requested workflow.',
            'Do not approve sending. Do not approve stage moves. Planning only.',
            'Return only JSON.',
        ]);
    }
}

if (!function_exists('elite_ai_plan_request')) {
    function elite_ai_prompt_requests_reply_draft(string $prompt): bool
    {
        $normalized = strtolower(trim($prompt));
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match('/\b(?:draft|answer|reply|respond|response|say|text|message|apologize|apologise|apologies|sorry)\b/i', $normalized);
    }

    function elite_ai_prompt_explicitly_requests_email(string $prompt): bool
    {
        return (bool) preg_match('/\b(?:email|e-mail|mail)\b/i', strtolower(trim($prompt)));
    }

    function elite_ai_plan_request(string $prompt, string $quickAction, array $context): array
    {
        $quickAction = strtolower(trim($quickAction));
        if ($quickAction !== '') {
            return [
                'intent' => elite_ai_detect_intent('', $quickAction, $context),
                'reason' => 'Quick action selected.',
                'lead_query' => '',
                'use_current_lead' => (int) ($context['lead_id'] ?? 0) > 0,
                'needs_clarification' => false,
                'clarification_question' => '',
                'provider' => 'deterministic',
            ];
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            return [
                'intent' => 'help',
                'reason' => 'Empty prompt.',
                'lead_query' => '',
                'use_current_lead' => false,
                'needs_clarification' => false,
                'clarification_question' => '',
                'provider' => 'deterministic',
            ];
        }

        if (elite_ai_prompt_requests_stage_move($prompt)) {
            return [
                'intent' => 'move_stage',
                'reason' => 'Deterministic CRM stage move request.',
                'lead_query' => elite_ai_extract_stage_move_lead_query($prompt),
                'use_current_lead' => (int) ($context['lead_id'] ?? 0) > 0 && elite_ai_extract_stage_move_lead_query($prompt) === '',
                'needs_clarification' => false,
                'clarification_question' => '',
                'provider' => 'deterministic',
            ];
        }

        if (elite_ai_prompt_requests_reply_draft($prompt)) {
            return [
                'intent' => elite_ai_prompt_explicitly_requests_email($prompt) ? 'draft_email' : 'draft_sms',
                'reason' => 'Deterministic reply draft request. Defaulting to SMS unless email is explicitly requested.',
                'lead_query' => '',
                'use_current_lead' => (int) ($context['lead_id'] ?? 0) > 0 || elite_ai_prompt_references_conversation_subject($prompt),
                'needs_clarification' => (int) ($context['lead_id'] ?? 0) <= 0 && trim((string) ($context['notification']['lead_name'] ?? '')) === '',
                'clarification_question' => 'Which lead should I draft this for?',
                'provider' => 'deterministic',
            ];
        }

        if (!function_exists('lead_ai_json_response')) {
            return [
                'intent' => elite_ai_detect_intent($prompt, '', $context),
                'reason' => 'Planner unavailable, using fallback intent router.',
                'lead_query' => '',
                'use_current_lead' => (int) ($context['lead_id'] ?? 0) > 0 && (elite_ai_prompt_mentions_current_lead($prompt) || elite_ai_prompt_references_conversation_subject($prompt)),
                'needs_clarification' => false,
                'clarification_question' => '',
                'provider' => 'fallback',
            ];
        }

        $memoryLines = [];
        foreach (array_slice((array) ($context['learned_memory'] ?? []), 0, 5) as $memory) {
            if (!is_array($memory)) {
                continue;
            }
            $memoryLines[] = '- ' . trim((string) ($memory['title'] ?? 'Memory')) . ': ' . trim((string) ($memory['body'] ?? ''));
        }

        $userPrompt = 'Operator request: ' . $prompt . "\n\n"
            . ($memoryLines ? 'Relevant learned memory:' . "\n" . implode("\n", $memoryLines) . "\n\n" : '')
            . 'Page context: ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $result = lead_ai_json_response(
            elite_ai_planner_system_prompt(),
            $userPrompt,
            elite_ai_planner_schema(),
            'elite_ai_request_planner',
            'general'
        );

        if (empty($result['ok']) || !is_array($result['data'] ?? null)) {
            return [
                'intent' => elite_ai_detect_intent($prompt, '', $context),
                'reason' => 'Planner fallback: ' . (string) ($result['message'] ?? 'AI planner unavailable.'),
                'lead_query' => '',
                'use_current_lead' => (int) ($context['lead_id'] ?? 0) > 0 && (elite_ai_prompt_mentions_current_lead($prompt) || elite_ai_prompt_references_conversation_subject($prompt)),
                'needs_clarification' => false,
                'clarification_question' => '',
                'provider' => 'fallback',
            ];
        }

        return [
            'intent' => (string) ($result['data']['intent'] ?? 'help'),
            'reason' => trim((string) ($result['data']['reason'] ?? '')),
            'lead_query' => trim((string) ($result['data']['lead_query'] ?? '')),
            'use_current_lead' => (bool) ($result['data']['use_current_lead'] ?? false),
            'needs_clarification' => (bool) ($result['data']['needs_clarification'] ?? false),
            'clarification_question' => trim((string) ($result['data']['clarification_question'] ?? '')),
            'provider' => (string) ($result['provider'] ?? 'openai'),
            'model' => (string) ($result['model'] ?? ''),
        ];
    }
}

if (!function_exists('elite_ai_resolve_lead_from_plan')) {
    function elite_ai_clean_lead_query(string $query): string
    {
        $query = trim($query);
        $query = preg_replace('/\b(?:any|latest|last|most recent|recent)\s+(?:reply|replay|response|message|text|sms|email)\s+(?:from|for)\s+/i', '', $query);
        $query = preg_replace('/\b(?:reply|replay|response|message|text|sms|email)\s+(?:from|for)\s+/i', '', (string) $query);
        $query = preg_replace('/\b(?:check|show|read|review|what is|what\'s|did|has|have|got|get|received)\b/i', '', (string) $query);
        $query = preg_replace('/\b(?:reply|replay|response|message|text|sms|email|from|for)\b/i', '', (string) $query);
        return trim((string) preg_replace('/\s+/', ' ', (string) $query), " \t\n\r\0\x0B?.");
    }

    function elite_ai_resolve_lead_from_plan(array $plan, string $prompt, array $context): array
    {
        if (!empty($plan['use_current_lead']) && (int) ($context['lead_id'] ?? 0) > 0) {
            $lead = elite_ai_load_lead((int) $context['lead_id']);
            if ($lead) {
                return ['lead' => $lead, 'matches' => [], 'clarify' => ''];
            }
        }

        $leadQuery = elite_ai_clean_lead_query((string) ($plan['lead_query'] ?? ''));
        if ($leadQuery !== '') {
            $matches = elite_ai_find_leads($leadQuery, 5);
            if (count($matches) === 1) {
                if ((int) ($matches[0]['_elite_ai_match_distance'] ?? 0) > 0) {
                    $matchName = trim((string) ($matches[0]['full_name'] ?? 'that lead'));
                    $matchId = (int) ($matches[0]['id'] ?? 0);
                    $targetStage = (string) ($plan['intent'] ?? '') === 'move_stage'
                        ? elite_ai_requested_stage_key($prompt)
                        : '';
                    $actionQuestion = $targetStage !== ''
                        ? ' Do you want me to move this lead to ' . elite_ai_stage_label($targetStage) . '?'
                        : ' Is that who you mean?';
                    return [
                        'lead' => null,
                        'matches' => $matches,
                        'clarify' => 'I found a close match for "' . $leadQuery . '": ' . ($matchName !== '' ? $matchName : 'that lead') . ($matchId > 0 ? ' (#' . $matchId . ')' : '') . '.' . $actionQuestion,
                    ];
                }
                return ['lead' => $matches[0], 'matches' => [], 'clarify' => ''];
            }
            if ($matches === []) {
                return ['lead' => null, 'matches' => [], 'clarify' => 'I could not find a lead matching "' . $leadQuery . '".'];
            }
            $targetStage = (string) ($plan['intent'] ?? '') === 'move_stage'
                ? elite_ai_requested_stage_key($prompt)
                : '';
            $clarify = $targetStage !== ''
                ? 'I found multiple matching leads. Which one should I move to ' . elite_ai_stage_label($targetStage) . '?'
                : 'I found multiple matching leads. Please tell me which one you want by name or lead number.';
            return [
                'lead' => null,
                'matches' => $matches,
                'clarify' => $clarify,
            ];
        }

        return elite_ai_resolve_lead_from_request($prompt, $context);
    }
}

if (!function_exists('elite_ai_detect_intent')) {
    function elite_ai_detect_intent(string $prompt, string $quickAction, array $context): string
    {
        $quickAction = strtolower(trim($quickAction));
        if ($quickAction !== '') {
            return match ($quickAction) {
                'morning-sweep' => 'morning_sweep',
                'new-leads' => 'new_leads',
                'replies' => 'replies',
                'follow-ups' => 'follow_ups',
                'no-answer-review' => 'no_answer_review',
                'notifications' => 'notifications',
                'summarize-lead' => 'lead_summary',
                'what-next' => 'next_step',
                default => 'help',
            };
        }

        $normalized = strtolower(trim($prompt));
        if ($normalized === '') {
            return 'help';
        }
        if (str_contains($normalized, 'morning sweep') || str_contains($normalized, 'run sweep')) {
            return 'morning_sweep';
        }
        if (str_contains($normalized, 'new leads')) {
            return 'new_leads';
        }
        if (str_contains($normalized, 'who replied') || str_contains($normalized, 'replies') || str_contains($normalized, 'reply today')) {
            return 'replies';
        }
        if (str_contains($normalized, 'follow-up') || str_contains($normalized, 'follow up')) {
            return 'follow_ups';
        }
        if (str_contains($normalized, 'no answer')) {
            return 'no_answer_review';
        }
        if (str_contains($normalized, 'notification')) {
            if ((str_contains($normalized, 'clear') || str_contains($normalized, 'mark') || str_contains($normalized, 'reviewed') || str_contains($normalized, 'dismiss'))) {
                return 'mark_reviewed';
            }
            return 'notifications';
        }
        if ((str_contains($normalized, 'clear') || str_contains($normalized, 'mark') || str_contains($normalized, 'dismiss')) && (str_contains($normalized, 'message') || str_contains($normalized, 'reply') || str_contains($normalized, 'reviewed'))) {
            return 'mark_reviewed';
        }
        if (elite_ai_prompt_requests_stage_count($normalized) !== null) {
            return 'pipeline';
        }
        if (str_contains($normalized, 'pipeline') || str_contains($normalized, 'board overview') || str_contains($normalized, 'board summary')) {
            return 'pipeline';
        }
        if (elite_ai_prompt_requests_latest_reply($normalized)) {
            return 'lead_summary';
        }
        if (str_contains($normalized, 'summarize this lead') || str_contains($normalized, 'summarize ') || str_contains($normalized, 'check ') || str_contains($normalized, 'review ')) {
            return 'lead_summary';
        }
        if (str_contains($normalized, 'what should i do next')) {
            return ($context['lead_id'] ?? 0) > 0 ? 'lead_summary' : 'morning_sweep';
        }

        return 'help';
    }
}

if (!function_exists('elite_ai_help_payload')) {
    function elite_ai_help_payload(array $context): array
    {
        $pageHint = ($context['page'] ?? '') === 'leads' && ($context['lead_id'] ?? 0) > 0
            ? 'You are already on a lead, so I can check the latest reply, suggest the next move, or prepare a draft for that lead.'
            : 'You can ask me about a specific lead by name, check a reply, or tell me what you want to get done.';

        return [
            'answer' => 'Tell me what you want done. I can check a lead, read the last reply, suggest the next move, clear reviewed notifications, or prepare a draft for approval. ' . $pageHint,
            'cards' => [[
                'title' => 'Try one of these prompts',
                'items' => [
                    'Check last response from Cindy Soper',
                    'Run morning sweep',
                    'Show new leads that need first contact',
                    'Who replied today?',
                    'Which contacted leads need follow-up?',
                    'Review No Answer candidates',
                    'Summarize Daniel Cordero',
                    'What notifications need attention?',
                ],
            ]],
            'tools_used' => ['knowledge_rules'],
        ];
    }
}

if (!function_exists('elite_ai_prompt_requests_latest_reply')) {
    function elite_ai_prompt_requests_latest_reply(string $prompt): bool
    {
        $normalized = strtolower(trim($prompt));
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b(?:check|show|read|review|what(?:\'s| is))\b.*\b(?:last|latest|most recent)\b.*\b(?:reply|replay|response|message|text|sms|email)\b|\b(?:last|latest|most recent)\b.*\b(?:reply|replay|response|message)\b.*\bfrom\b|\b(?:any|got|get|have|has|received)\b.*\b(?:reply|replay|response|message|text|sms|email)\b.*\bfrom\b|\b(?:did|has)\b.*\b(?:reply|replied|replay|replayed)\b/i',
            $normalized
        );
    }
}

if (!function_exists('elite_ai_format_operator_time')) {
    function elite_ai_format_operator_time(string $timestamp): string
    {
        $ts = strtotime($timestamp);
        if (!$ts) {
            return '';
        }

        return date('M j, g:i A', $ts);
    }
}

if (!function_exists('elite_ai_latest_reply_payload')) {
    function elite_ai_latest_reply_payload(array $lead): array
    {
        $thread = elite_ai_lead_thread((int) ($lead['id'] ?? 0));
        $latestInbound = elite_ai_latest_direction_item($thread, 'inbound');
        $fullName = trim((string) ($lead['full_name'] ?? 'This lead'));
        $nextStep = trim((string) elite_ai_recommended_next_step($lead, $thread, elite_ai_attempt_counts((int) ($lead['id'] ?? 0))));

        if (!$latestInbound) {
            $answer = $fullName . ' does not have a recent inbound reply for me to review.';
            if ($nextStep !== '') {
                $answer .= ' Next, ' . lcfirst(elite_ai_conversational_next_line($nextStep));
            }

            return [
                'answer' => $answer,
                'cards' => [],
                'actions' => [],
                'tools_used' => ['lead_lookup', 'lead_thread', 'next_step'],
                'lead_id' => (int) ($lead['id'] ?? 0),
            ];
        }

        $channel = strtoupper((string) ($latestInbound['channel'] ?? 'message'));
        $timeLabel = elite_ai_format_operator_time((string) ($latestInbound['created_at'] ?? ''));
        $body = trim((string) ($latestInbound['body'] ?? ''));
        $body = $body !== '' ? $body : 'No message body was captured.';
        $latestOutbound = elite_ai_latest_direction_item($thread, 'outbound');
        $inboundTime = strtotime((string) ($latestInbound['created_at'] ?? '')) ?: 0;
        $outboundTime = $latestOutbound ? (strtotime((string) ($latestOutbound['created_at'] ?? '')) ?: 0) : 0;

        $answer = $fullName . ' said';
        if ($timeLabel !== '') {
            $answer .= ' at ' . $timeLabel;
        }
        $answer .= ': "' . elite_ai_shorten_patient_quote($body, 240) . '"';
        if ($latestOutbound && $outboundTime > $inboundTime) {
            $answer .= ' We already answered after that at ' . elite_ai_format_operator_time((string) ($latestOutbound['created_at'] ?? '')) . ', so right now we are waiting on the next reply.';
        }

        $cards = [];
        if ($nextStep !== '' && !($latestOutbound && $outboundTime > $inboundTime)) {
            $answer .= ' ' . elite_ai_conversational_next_line($nextStep);
        }

        return [
            'answer' => $answer,
            'cards' => $cards,
            'actions' => [
                [
                    'type' => 'draft_sms',
                    'label' => 'Prepare SMS draft',
                    'help' => 'Prepare a reply draft based on the latest inbound context.',
                    'lead_id' => (int) ($lead['id'] ?? 0),
                ],
                [
                    'type' => 'draft_email',
                    'label' => 'Prepare Email draft',
                    'help' => 'Prepare an email draft based on the latest inbound context.',
                    'lead_id' => (int) ($lead['id'] ?? 0),
                ],
            ],
            'tools_used' => ['lead_lookup', 'lead_thread', 'next_step'],
            'lead_id' => (int) ($lead['id'] ?? 0),
        ];
    }
}

if (!function_exists('elite_ai_log_interaction')) {
    function elite_ai_log_interaction(array $user, string $surface, string $prompt, array $toolsUsed, string $responseSummary, ?int $leadId, array $context): void
    {
        try {
            elite_ai_ensure_schema();
            db_insert(
                'INSERT INTO elite_ai_audit_logs (user_id, surface, prompt, tools_used_json, response_summary, lead_id, page_context_json, created_at)
                 VALUES (:user_id, :surface, :prompt, :tools_used_json, :response_summary, :lead_id, :page_context_json, :created_at)',
                [
                    'user_id' => (int) ($user['id'] ?? 0),
                    'surface' => $surface,
                    'prompt' => $prompt,
                    'tools_used_json' => json_encode(array_values($toolsUsed), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'response_summary' => mb_substr($responseSummary, 0, 1000),
                    'lead_id' => $leadId ?: null,
                    'page_context_json' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                ]
            );

            if (function_exists('elite_ai_memory_learn_from_interaction')) {
                elite_ai_memory_learn_from_interaction($user, $surface, $prompt, $toolsUsed, $responseSummary, $leadId, $context);
            }
        } catch (Throwable $e) {
            esm_log('elite_ai', 'Could not log assistant interaction.', ['error' => $e->getMessage()]);
        }
    }
}

if (!function_exists('elite_ai_plain_text_payload')) {
    function elite_ai_plain_text_payload(array $payload): array
    {
        $answer = trim((string) ($payload['answer'] ?? ''));
        $lines = [];

        foreach (array_slice((array) ($payload['cards'] ?? []), 0, 3) as $card) {
            if (!is_array($card)) {
                continue;
            }

            $title = trim((string) ($card['title'] ?? ''));
            $items = array_values(array_filter(array_map(
                static fn($item): string => trim((string) $item),
                (array) ($card['items'] ?? [])
            )));

            if ($title !== '' && $items) {
                $lines[] = $title . ':';
            } elseif ($title !== '') {
                $lines[] = $title;
            }

            foreach (array_slice($items, 0, 5) as $item) {
                $lines[] = $item;
            }
        }

        if ($lines) {
            $answer = trim($answer . "\n\n" . implode("\n", $lines));
        }

        $payload['answer'] = $answer !== '' ? $answer : 'Ready.';
        $payload['cards'] = [];
        return $payload;
    }
}

if (!function_exists('elite_ai_handle_request')) {
function elite_ai_handle_request(array $user, array $request): array
{
        $surface = elite_ai_surface($request);
        $prompt = trim((string) ($request['prompt'] ?? ''));
        $quickAction = trim((string) ($request['quick_action'] ?? ''));
        $context = elite_ai_normalize_context($request);
        $memoryPrompt = $prompt !== '' ? $prompt : $quickAction;
        $learnedMemory = function_exists('elite_ai_memory_relevant')
            ? elite_ai_memory_relevant($memoryPrompt, $context, 5)
            : [];
        if ($learnedMemory) {
            $context['learned_memory'] = $learnedMemory;
        }

        if ($quickAction === '' && $prompt !== '' && (bool) preg_match('/^\s*(?:remember|learn|from now on|always|never)\b/i', $prompt)) {
            $result = elite_ai_tool_run($user, 'memory.remember', [
                'surface' => $surface,
                'title' => 'Operator instruction',
                'body' => $prompt,
                'memory_type' => 'preference',
                'tags' => ['operator_instruction', 'surface:' . $surface],
                'confidence' => 0.9,
            ], $context + ['surface' => $surface]);
            $summary = !empty($result['ok'])
                ? 'I saved that to Elite AI learned memory and will use it in future CRM tasks.'
                : (string) ($result['message'] ?? 'I could not save that memory.');
            elite_ai_log_interaction($user, $surface, $prompt, ['memory.remember'], $summary, null, $context);

            return [
                'ok' => !empty($result['ok']),
                'surface' => $surface,
                'answer' => $summary,
                'execution_policy' => elite_ai_execution_policy_tag($request),
                'pending_drafts' => elite_ai_pending_drafts_for_user($user, 8),
                'cards' => [],
                'actions' => [],
                'tools_used' => ['memory.remember'],
                'tool_capabilities' => elite_ai_tool_capabilities($surface),
                'lead_id' => null,
                'current_subject' => [],
                'context' => $context,
                'knowledge_rules' => elite_ai_knowledge_base()['locked_rules'],
                'learned_memory' => $learnedMemory,
            ];
        }

        if ($quickAction === '' && elite_ai_prompt_requests_pending_draft_review($prompt)) {
            $draftPayload = elite_ai_pending_draft_conversation_payload($user, $context);
            if ($draftPayload) {
                $draftPayload = elite_ai_plain_text_payload($draftPayload);
                $summary = trim((string) ($draftPayload['answer'] ?? 'Pending draft ready for review.'));
                elite_ai_log_interaction(
                    $user,
                    $surface,
                    $prompt,
                    (array) ($draftPayload['tools_used'] ?? []),
                    $summary,
                    (int) ($draftPayload['lead_id'] ?? 0) ?: null,
                    $context
                );

                return [
                    'ok' => true,
                    'surface' => $surface,
                    'answer' => $summary,
                    'execution_policy' => elite_ai_execution_policy_tag($request),
                    'pending_drafts' => array_values((array) ($draftPayload['pending_drafts'] ?? elite_ai_pending_drafts_for_user($user, 8))),
                    'cards' => array_values((array) ($draftPayload['cards'] ?? [])),
                    'actions' => array_values((array) ($draftPayload['actions'] ?? [])),
                    'tools_used' => array_values((array) ($draftPayload['tools_used'] ?? [])),
                    'tool_capabilities' => elite_ai_tool_capabilities($surface),
                    'lead_id' => (int) ($draftPayload['lead_id'] ?? 0) ?: null,
                    'current_subject' => elite_ai_current_subject_payload((int) ($draftPayload['lead_id'] ?? 0) ?: null),
                    'context' => $context,
                    'knowledge_rules' => elite_ai_knowledge_base()['locked_rules'],
                    'learned_memory' => $learnedMemory,
                ];
            }
        }

        if ($quickAction === '') {
            $pendingMove = elite_ai_infer_pending_stage_move_from_thread((array) ($context['assistant_thread'] ?? []));
            $isAffirmation = elite_ai_prompt_is_affirmation($prompt);
            $selectedPendingLeadId = $isAffirmation
                ? (int) ($pendingMove['lead_id'] ?? 0)
                : elite_ai_resolve_pending_stage_move_selection($prompt, $pendingMove);
            if ($selectedPendingLeadId > 0 && trim((string) ($pendingMove['target_status'] ?? '')) !== '') {
                $leadId = $selectedPendingLeadId;
                $targetStage = trim((string) $pendingMove['target_status']);
                $moveResult = elite_ai_tool_run($user, 'lead.move_stage', [
                    'surface' => $surface,
                    'lead_id' => $leadId,
                    'target_status' => $targetStage,
                    'instruction' => 'Confirmed pending stage move: ' . $prompt,
                ], $context + ['surface' => $surface]);
                $moveResult = elite_ai_plain_text_payload($moveResult);
                $summary = trim((string) ($moveResult['answer'] ?? $moveResult['message'] ?? 'Stage action completed.'));
                elite_ai_log_interaction(
                    $user,
                    $surface,
                    $prompt,
                    ['conversation.confirmation', 'lead.move_stage'],
                    $summary,
                    $leadId,
                    $context
                );

                return [
                    'ok' => !empty($moveResult['ok']),
                    'surface' => $surface,
                    'answer' => $summary,
                    'execution_policy' => elite_ai_execution_policy_tag($request),
                    'pending_drafts' => elite_ai_pending_drafts_for_user($user, 8),
                    'cards' => array_values((array) ($moveResult['cards'] ?? [])),
                    'actions' => array_values((array) ($moveResult['actions'] ?? [])),
                    'tools_used' => ['conversation.confirmation', 'lead.move_stage'],
                    'tool_capabilities' => elite_ai_tool_capabilities($surface),
                    'lead_id' => $leadId,
                    'current_subject' => elite_ai_current_subject_payload($leadId),
                    'context' => $context,
                    'knowledge_rules' => elite_ai_knowledge_base()['locked_rules'],
                    'learned_memory' => $learnedMemory,
                ];
            }
        }

        $plan = elite_ai_plan_request($prompt, $quickAction, $context);
        $intent = (string) ($plan['intent'] ?? elite_ai_detect_intent($prompt, $quickAction, $context));
        $requestsLatestReply = $quickAction === '' && elite_ai_prompt_requests_latest_reply($prompt);
        $stageCountStatus = $quickAction === '' ? elite_ai_prompt_requests_stage_count($prompt) : null;
        $payload = [];
        $leadId = null;

        if ($requestsLatestReply) {
            $intent = 'lead_summary';
        } elseif ($stageCountStatus !== null) {
            $intent = 'pipeline';
        }

        if (!empty($plan['needs_clarification']) && trim((string) ($plan['clarification_question'] ?? '')) !== '') {
            $payload = [
                'answer' => trim((string) $plan['clarification_question']),
                'cards' => [],
                'actions' => [],
                'tools_used' => ['planner_' . (string) ($plan['provider'] ?? 'fallback')],
            ];
        } elseif (in_array($intent, ['draft_sms', 'draft_email'], true)) {
            $resolved = elite_ai_resolve_lead_from_plan($plan, $prompt, $context);
            if (!empty($resolved['lead']) && is_array($resolved['lead'])) {
                $leadId = (int) (($resolved['lead']['id'] ?? 0));
                $draftResult = elite_ai_prepare_action_draft($user, $request + [
                    'assistant_action' => $intent,
                    'lead_id' => $leadId,
                    'instruction' => $prompt,
                ], $surface);

                if (!empty($draftResult['ok'])) {
                    $actionPlan = elite_ai_internal_action_plan($prompt, (array) $resolved['lead']);
                    $cards = [[
                        'title' => $intent === 'draft_sms' ? 'SMS draft preview' : 'Email draft preview',
                        'items' => [trim((string) ($draftResult['draft_preview'] ?? 'Draft prepared for review.'))],
                    ]];
                    $cards = array_merge($cards, (array) ($actionPlan['cards'] ?? []));
                    $actions = array_merge(
                        array_values((array) ($draftResult['draft_actions'] ?? [])),
                        array_values((array) ($actionPlan['actions'] ?? []))
                    );
                    $payload = [
                        'answer' => trim((string) ($draftResult['message'] ?? 'Draft prepared for review.'))
                            . ' Provider: ' . trim((string) (($draftResult['draft']['provider'] ?? $draftResult['provider'] ?? 'AI')))
                            . '. Nothing was sent.'
                            . (!empty($actionPlan['actions']) ? ' I also prepared the safe internal actions I understood from your instruction.' : ''),
                        'cards' => $cards,
                        'actions' => $actions,
                        'tools_used' => array_merge(['planner_' . (string) ($plan['provider'] ?? 'fallback'), $intent], !empty($actionPlan['actions']) ? ['action_plan'] : []),
                    ];
                } else {
                    $payload = [
                        'answer' => (string) ($draftResult['message'] ?? 'I could not prepare the requested draft.'),
                        'cards' => [],
                        'actions' => [],
                        'tools_used' => ['planner_' . (string) ($plan['provider'] ?? 'fallback'), $intent],
                    ];
                }
            } else {
                $items = [];
                foreach ((array) ($resolved['matches'] ?? []) as $match) {
                    $items[] = elite_ai_format_lead_line($match, trim((string) ($match['email'] ?? '')) !== '' ? trim((string) ($match['email'] ?? '')) : trim((string) ($match['phone'] ?? '')));
                }
                $payload = [
                    'answer' => (string) ($resolved['clarify'] ?? 'Which lead should I draft for?'),
                    'cards' => $items ? [[
                        'title' => 'Possible matches',
                        'items' => $items,
                    ]] : [],
                    'actions' => [],
                    'tools_used' => ['planner_' . (string) ($plan['provider'] ?? 'fallback'), 'lead_lookup'],
                ];
            }
        } else {

        switch ($intent) {
            case 'move_stage':
                $targetStage = elite_ai_requested_stage_key($prompt);
                $resolved = elite_ai_resolve_lead_from_plan($plan, $prompt, $context);
                if ($targetStage === '') {
                    $payload = [
                        'answer' => 'Which stage should I move that lead to?',
                        'cards' => [],
                        'actions' => [],
                        'tools_used' => ['stage_move_parse'],
                    ];
                    break;
                }

                if (!empty($resolved['lead']) && is_array($resolved['lead'])) {
                    $leadId = (int) (($resolved['lead']['id'] ?? 0));
                    $moveResult = elite_ai_tool_run($user, 'lead.move_stage', [
                        'surface' => $surface,
                        'lead_id' => $leadId,
                        'target_status' => $targetStage,
                        'instruction' => $prompt,
                    ], $context + ['surface' => $surface]);
                    $payload = $moveResult + [
                        'tools_used' => ['planner_' . (string) ($plan['provider'] ?? 'fallback'), 'lead.lookup', 'lead.move_stage'],
                    ];
                } else {
                    $items = [];
                    foreach ((array) ($resolved['matches'] ?? []) as $match) {
                        $items[] = elite_ai_format_lead_line($match, trim((string) ($match['email'] ?? '')) !== '' ? trim((string) ($match['email'] ?? '')) : trim((string) ($match['phone'] ?? '')));
                    }
                    $payload = [
                        'answer' => (string) ($resolved['clarify'] ?? 'Which lead should I move?'),
                        'cards' => $items ? [[
                            'title' => 'Possible matches',
                            'items' => $items,
                        ]] : [],
                        'actions' => [],
                        'tools_used' => ['planner_' . (string) ($plan['provider'] ?? 'fallback'), 'lead_lookup', 'stage_move_parse'],
                    ];
                }
                break;

            case 'mark_reviewed':
                $resolved = elite_ai_resolve_lead_from_plan($plan, $prompt, $context);
                if (!empty($resolved['lead']) && is_array($resolved['lead'])) {
                    $leadId = (int) (($resolved['lead']['id'] ?? 0));
                    $payload = elite_ai_mark_notification_reviewed_payload($user, $leadId, 'elite_ai_prompt');
                } else {
                    $items = [];
                    foreach ((array) ($resolved['matches'] ?? []) as $match) {
                        $items[] = elite_ai_format_lead_line($match, trim((string) ($match['email'] ?? '')) !== '' ? trim((string) ($match['email'] ?? '')) : trim((string) ($match['phone'] ?? '')));
                    }
                    $payload = [
                        'answer' => (string) ($resolved['clarify'] ?? 'Which lead notification should I clear?'),
                        'cards' => $items ? [[
                            'title' => 'Possible matches',
                            'items' => $items,
                        ]] : [],
                        'actions' => [],
                        'tools_used' => ['lead_lookup'],
                    ];
                }
                break;

            case 'morning_sweep':
                $payload = elite_ai_morning_sweep_payload();
                break;

            case 'new_leads':
                $payload = elite_ai_new_leads_payload();
                break;

            case 'replies':
                $payload = elite_ai_replies_payload();
                break;

            case 'follow_ups':
                $payload = elite_ai_follow_up_payload();
                break;

            case 'no_answer_review':
                $payload = elite_ai_no_answer_payload();
                break;

            case 'notifications':
                $payload = elite_ai_notifications_payload();
                break;

            case 'pipeline':
                $payload = $stageCountStatus !== null
                    ? elite_ai_pipeline_count_payload($stageCountStatus)
                    : elite_ai_pipeline_payload();
                break;

            case 'lead_summary':
                $resolved = elite_ai_resolve_lead_from_plan($plan, $prompt, $context);
                if (!empty($resolved['lead']) && is_array($resolved['lead'])) {
                    $payload = $requestsLatestReply
                        ? elite_ai_latest_reply_payload((array) $resolved['lead'])
                        : elite_ai_lead_summary_payload((array) $resolved['lead']);
                    $leadId = (int) (($payload['lead_id'] ?? 0));
                } else {
                    $items = [];
                    foreach ((array) ($resolved['matches'] ?? []) as $match) {
                        $items[] = elite_ai_format_lead_line($match, trim((string) ($match['email'] ?? '')) !== '' ? trim((string) ($match['email'] ?? '')) : trim((string) ($match['phone'] ?? '')));
                    }
                    $payload = [
                        'answer' => (string) ($resolved['clarify'] ?? 'Which lead should I summarize?'),
                        'cards' => $items ? [[
                            'title' => 'Possible matches',
                            'items' => $items,
                        ]] : [],
                        'tools_used' => ['lead_lookup'],
                    ];
                }
                break;

            default:
                $payload = elite_ai_help_payload($context);
                break;
        }
        }

        $payload = elite_ai_plain_text_payload($payload);
        $summary = trim((string) ($payload['answer'] ?? 'Elite AI completed a read-only response.'));
        elite_ai_log_interaction($user, $surface, $prompt !== '' ? $prompt : $quickAction, (array) ($payload['tools_used'] ?? []), $summary, $leadId, $context);

        $executionPolicy = elite_ai_execution_policy_tag($request);

        return [
            'ok' => true,
            'surface' => $surface,
            'answer' => $summary,
            'execution_policy' => $executionPolicy,
            'pending_drafts' => elite_ai_pending_drafts_for_user($user, 8),
            'cards' => array_values((array) ($payload['cards'] ?? [])),
            'actions' => array_values((array) ($payload['actions'] ?? [])),
            'tools_used' => array_values((array) ($payload['tools_used'] ?? [])),
            'tool_capabilities' => elite_ai_tool_capabilities($surface),
            'lead_id' => $leadId,
            'current_subject' => elite_ai_current_subject_payload($leadId),
            'context' => $context,
            'knowledge_rules' => elite_ai_knowledge_base()['locked_rules'],
            'learned_memory' => $learnedMemory,
        ];
    }
}


