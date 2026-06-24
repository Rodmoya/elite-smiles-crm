<?php
declare(strict_types=1);

require_once __DIR__ . '/elite_ai_knowledge.php';
require_once __DIR__ . '/../leads/lead_ai.php';

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

if (!function_exists('elite_ai_normalize_context')) {
    function elite_ai_normalize_context(array $request): array
    {
        $context = is_array($request['context'] ?? null) ? $request['context'] : [];
        $page = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($context['page'] ?? ''));
        $pageTitle = trim((string) ($context['page_title'] ?? ''));
        $currentUrl = trim((string) ($context['current_url'] ?? ''));
        $leadId = (int) ($context['lead_id'] ?? 0);
        $tab = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($context['tab'] ?? ''));

        return [
            'page' => $page !== '' ? $page : 'unknown',
            'page_title' => $pageTitle,
            'current_url' => $currentUrl,
            'lead_id' => $leadId > 0 ? $leadId : 0,
            'tab' => $tab,
        ];
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

        return array_map('elite_ai_enrich_conversion_layer', $rows);
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

if (!function_exists('elite_ai_notification_rows')) {
    function elite_ai_notification_rows(int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));
        $notifications = [];

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
                 ORDER BY lm.created_at DESC, lm.id DESC
                 LIMIT {$limit}"
            );

            foreach ($messages as $row) {
                $leadId = (int) ($row['lead_id'] ?? 0);
                $leadName = trim((string) ($row['full_name'] ?? 'Lead'));
                $notifications[] = [
                    'id' => 'msg-' . (int) ($row['id'] ?? 0),
                    'priority' => ((int) ($row['is_read'] ?? 0) === 0) ? 'high' : 'normal',
                    'title' => 'Reply from ' . $leadName . ($leadId > 0 ? ' - Lead #' . $leadId : ''),
                    'message' => trim((string) ($row['body'] ?? '')),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'lead_id' => $leadId,
                    'lead_name' => $leadName,
                    'status' => trim((string) ($row['status'] ?? '')),
                    'suggested_action' => 'Review context and prepare a draft before sending.',
                ];
            }
        } catch (Throwable $e) {
            esm_log('elite_ai', 'Could not load inbound notifications.', ['error' => $e->getMessage()]);
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
                 WHERE la.type IN ('lead_created', 'stage_change', 'consultation_scheduled', 'follow_up_due', 'manual_sms_followup_prepared')
                 ORDER BY la.created_at DESC, la.id DESC
                 LIMIT {$limit}"
            );

            foreach ($activities as $row) {
                $type = trim((string) ($row['type'] ?? 'activity'));
                $leadId = (int) ($row['lead_id'] ?? 0);
                $leadName = trim((string) ($row['full_name'] ?? 'Lead'));
                $label = match ($type) {
                    'lead_created' => 'New lead',
                    'stage_change' => 'Pipeline update',
                    'consultation_scheduled' => 'Consultation alert',
                    'follow_up_due' => 'Follow-up alert',
                    'manual_sms_followup_prepared' => 'Draft ready',
                    default => 'CRM alert',
                };

                $notifications[] = [
                    'id' => 'act-' . (int) ($row['id'] ?? 0),
                    'priority' => in_array($type, ['lead_created', 'follow_up_due', 'consultation_scheduled'], true) ? 'high' : 'normal',
                    'title' => $label . ': ' . $leadName . ($leadId > 0 ? ' - Lead #' . $leadId : ''),
                    'message' => trim((string) ($row['body'] ?? '')),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'lead_id' => $leadId,
                    'lead_name' => $leadName,
                    'status' => trim((string) ($row['status'] ?? '')),
                    'suggested_action' => $type === 'lead_created'
                        ? 'Review the lead for first-touch readiness.'
                        : 'Open the lead and review the next manual step.',
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

        return array_slice($notifications, 0, $limit);
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
        $notifications = elite_ai_notification_rows(8);
        $cards = [[
            'title' => 'Notifications needing attention',
            'items' => array_map(static function (array $row): string {
                return trim((string) ($row['title'] ?? 'CRM alert')) . ' - ' . trim((string) ($row['suggested_action'] ?? 'Review next step.'));
            }, $notifications),
        ]];

        return [
            'answer' => $notifications
                ? 'These are the newest notification items across replies and CRM activity. Start with unread inbound replies first.'
                : 'There are no notification items to review right now.',
            'cards' => $notifications ? $cards : [],
            'tools_used' => ['notifications'],
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

        $overview = [
            'Stage: ' . elite_ai_stage_label(trim((string) ($lead['status'] ?? ''))),
            'Conversion meaning: ' . ($conversionStageLabel !== '' ? $conversionStageLabel : 'Not available'),
            'Source: ' . (trim((string) ($lead['source'] ?? '')) !== '' ? trim((string) ($lead['source'] ?? '')) : 'Unknown'),
            'Preferred contact: ' . (trim((string) ($lead['preferred_contact'] ?? '')) !== '' ? trim((string) ($lead['preferred_contact'] ?? '')) : 'Not set'),
        ];
        if (trim((string) ($lead['consultation_date'] ?? '')) !== '') {
            $overview[] = 'Consultation: ' . format_datetime((string) ($lead['consultation_date'] ?? ''), 'M j, Y g:i A');
        }

        $cards = [[
            'title' => 'Lead overview',
            'items' => $overview,
        ]];

        $activityItems = [];
        if ($latestInbound) {
            $activityItems[] = 'Latest inbound: ' . format_datetime((string) ($latestInbound['created_at'] ?? ''), 'M j g:i A') . ' - ' . trim((string) ($latestInbound['body'] ?? $latestInbound['subject'] ?? ''));
        }
        if ($latestOutbound) {
            $activityItems[] = 'Latest outbound: ' . format_datetime((string) ($latestOutbound['created_at'] ?? ''), 'M j g:i A') . ' - ' . trim((string) ($latestOutbound['body'] ?? $latestOutbound['subject'] ?? ''));
        }
        if ($latestActivity) {
            $activityItems[] = 'Latest activity: ' . format_datetime((string) ($latestActivity['created_at'] ?? ''), 'M j g:i A') . ' - ' . trim((string) ($latestActivity['body'] ?? ''));
        }
        $activityItems[] = 'Contact attempts: ' . (int) ($attempts['outbound_total'] ?? 0) . ' outbound and ' . (int) ($attempts['inbound_total'] ?? 0) . ' inbound.';
        $cards[] = [
            'title' => 'Latest activity',
            'items' => $activityItems,
        ];

        if ($missingItems) {
            $cards[] = [
                'title' => 'Missing items',
                'items' => $missingItems,
            ];
        }

        $conversionItems = [];
        if ($conversionStageLabel !== '') {
            $conversionItems[] = 'Derived conversion stage: ' . $conversionStageLabel . ' (legacy status remains ' . trim((string)($lead['status'] ?? 'unknown')) . ').';
        }
        if (trim((string)($conversionNextAction['label'] ?? '')) !== '') {
            $conversionItems[] = 'Next Action: ' . trim((string)$conversionNextAction['label']);
        }
        foreach ($conversionBadges as $badge) {
            $label = trim((string)($badge['label'] ?? ''));
            if ($label !== '') {
                $conversionItems[] = 'Flag: ' . $label;
            }
        }
        if ($conversionItems) {
            $cards[] = [
                'title' => 'Conversion layer',
                'items' => $conversionItems,
            ];
        }

        $cards[] = [
            'title' => 'Recommended next step',
            'items' => [$recommendation],
        ];

        return [
            'answer' => trim((string) ($lead['full_name'] ?? 'Lead')) . ' is currently in ' . elite_ai_stage_label(trim((string) ($lead['status'] ?? ''))) . ($conversionStageLabel !== '' ? ' / ' . $conversionStageLabel . ' conversion meaning' : '') . '. I reviewed the most recent communication, the latest activity, and the obvious missing items so you can decide the next manual step.',
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

if (!function_exists('elite_ai_prepare_action_draft')) {
    function elite_ai_prepare_action_draft(array $user, array $request, string $surface): array
    {
        $actionType = strtolower(trim((string) ($request['assistant_action'] ?? '')));
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
                'lead_id' => $leadId,
                'action_id' => $actionId,
                'draft' => $draftPayload,
                'payload' => $draftPayload,
                'draft_preview' => elite_ai_draft_preview_text('draft_sms', $draftPayload),
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
            'lead_id' => $leadId,
            'action_id' => $actionId,
            'draft' => $draftPayload,
            'payload' => $draftPayload,
            'draft_preview' => elite_ai_draft_preview_text('draft_email', $draftPayload),
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
        $result = elite_ai_prepare_action_draft($user, $request, $surface);
        if (!empty($result['ok'])) {
            $context = elite_ai_normalize_context($request);
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
            ];
        }

        return [
            'ok' => false,
            'surface' => $surface,
            'message' => (string) ($result['message'] ?? 'Unable to prepare the requested action.'),
            'context' => elite_ai_normalize_context($request),
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
            return 'notifications';
        }
        if (str_contains($normalized, 'pipeline') || str_contains($normalized, 'board overview') || str_contains($normalized, 'board summary')) {
            return 'pipeline';
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
            ? 'You are already on a lead-specific page, so you can also ask me to summarize this lead.'
            : 'You can also ask me to summarize a specific lead by name.';

        return [
            'answer' => 'Elite AI is in assistant mode with draft-first safety: I can summarize leads, notifications, pipeline priorities, replies, follow-ups, No Answer review candidates, and morning sweep actions, and generate SMS/email drafts for approval without sending. ' . $pageHint,
            'cards' => [[
                'title' => 'Try one of these prompts',
                'items' => [
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
        } catch (Throwable $e) {
            esm_log('elite_ai', 'Could not log assistant interaction.', ['error' => $e->getMessage()]);
        }
    }
}

if (!function_exists('elite_ai_handle_request')) {
    function elite_ai_handle_request(array $user, array $request): array
    {
        $surface = elite_ai_surface($request);
        $prompt = trim((string) ($request['prompt'] ?? ''));
        $quickAction = trim((string) ($request['quick_action'] ?? ''));
        $context = elite_ai_normalize_context($request);
        $intent = elite_ai_detect_intent($prompt, $quickAction, $context);
        $payload = [];
        $leadId = null;

        switch ($intent) {
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
                $payload = elite_ai_pipeline_payload();
                break;

            case 'lead_summary':
                $resolved = elite_ai_resolve_lead_from_request($prompt, $context);
                if (!empty($resolved['lead']) && is_array($resolved['lead'])) {
                    $payload = elite_ai_lead_summary_payload((array) $resolved['lead']);
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

        $summary = trim((string) ($payload['answer'] ?? 'Elite AI completed a read-only response.'));
        elite_ai_log_interaction($user, $surface, $prompt !== '' ? $prompt : $quickAction, (array) ($payload['tools_used'] ?? []), $summary, $leadId, $context);

        return [
            'ok' => true,
            'surface' => $surface,
            'answer' => $summary,
            'cards' => array_values((array) ($payload['cards'] ?? [])),
            'actions' => array_values((array) ($payload['actions'] ?? [])),
            'tools_used' => array_values((array) ($payload['tools_used'] ?? [])),
            'lead_id' => $leadId,
            'context' => $context,
            'knowledge_rules' => elite_ai_knowledge_base()['locked_rules'],
        ];
    }
}
