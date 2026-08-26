<?php
declare(strict_types=1);

if (!function_exists('elite_ai_knowledge_base')) {
    function elite_ai_knowledge_base(): array
    {
        return [
            'locked_rules' => [
                [
                    'key' => 'draft_before_send',
                    'label' => 'Draft Before Send',
                    'text' => 'Client-facing messages must show a draft before send.',
                ],
                [
                    'key' => 'internal_note_rule',
                    'label' => 'Internal Note Rule',
                    'text' => 'AI-assisted actions should create internal notes or audit entries.',
                ],
                [
                    'key' => 'consultation_booked_protection',
                    'label' => 'Consultation Booked Protection',
                    'text' => 'Consultation Booked leads are protected from No Answer logic.',
                ],
                [
                    'key' => 'no_answer_review_only',
                    'label' => 'No Answer Review Only',
                    'text' => 'No Answer is review-only until a human approves it.',
                ],
                [
                    'key' => 'notification_prompt_rule',
                    'label' => 'Notification Prompt Rule',
                    'text' => 'New inbound notifications should lead to a clear next-step question, not an automatic send.',
                ],
                [
                    'key' => 'conversation_continuity',
                    'label' => 'Conversation Continuity',
                    'text' => 'Elite AI should keep the current lead, draft, notification, and operator correction in context so short follow-ups feel conversational.',
                ],
                [
                    'key' => 'consult_first_messaging',
                    'label' => 'Consult First Messaging',
                    'text' => 'For cosmetic leads, explain that every smile is custom and guide toward the complimentary consultation with Dr. Meden instead of quoting cookie-cutter pricing.',
                ],
                [
                    'key' => 'scheduling_intent_flow',
                    'label' => 'Scheduling Intent Flow',
                    'text' => 'When a lead says they want to schedule, collect the missing scheduling details naturally: preferred day, morning/afternoon or time, and DOB. Office hours are Monday-Thursday 9 AM-6 PM; special Friday/Saturday morning consultations may be available.',
                ],
                [
                    'key' => 'one_question_rule',
                    'label' => 'One Question Rule',
                    'text' => 'Patient-facing SMS should usually ask one clear question at a time so scheduling feels easy and human.',
                ],
                [
                    'key' => 'opt_out_cadence',
                    'label' => 'Opt-Out Cadence',
                    'text' => 'Include SMS opt-out language on first touch and periodic bulk/follow-up texts, but do not make every active back-and-forth feel automated.',
                ],
            ],
            'stage_rules' => [
                'new_lead' => 'New leads should be reviewed for first contact needs before anything else.',
                'contacted' => 'Contacted leads should be checked for follow-up timing and inbound replies.',
                'in_contact' => 'In Communication leads should be reviewed for the latest reply and next response timing.',
                'consultation_booked' => 'Consultation Booked leads should be protected from No Answer review.',
                'sale_closed' => 'Closed sales are not active follow-up candidates.',
                'no_answer' => 'No Answer is the durable low-frequency Nurture stage after the guarded active follow-up sprint is exhausted.',
            ],
            'conversion_stage_rules' => [
                'compatibility' => 'Derived conversion stages are read-only compatibility labels layered over legacy lead.status values until the final migration pass.',
                'new_lead' => 'New Lead lasts for the first 24 hours even after first touch; First Touch Sent is a badge, not a stage.',
                'lead_answered' => 'Lead Answered means the lead replied and the conversation is still open.',
                'active_follow_up' => 'Active Follow-Up means the first 24 hours ended without a reply or an answered conversation became quiet.',
                'scheduling' => 'Scheduling requires explicit appointment intent, availability, or a saved scheduling preference.',
                'consult_completed' => 'Consult Completed is derived from past consultation dates and should be treated as a manual-review signal, not an automatic stage move.',
                'nurture' => 'Nurture is low-frequency follow-up after the active sprint; it is separate from Lost and Opted Out.',
                'lost' => 'Lost is reserved for explicit decline or permanent closure.',
                'opted_out' => 'Opted Out must stop automated outreach immediately.',
            ],
        ];
    }
}

if (!function_exists('elite_ai_memory_ensure_schema')) {
    function elite_ai_memory_ensure_schema(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        try {
            db_query(
                "CREATE TABLE IF NOT EXISTS elite_ai_memory_items (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    memory_type VARCHAR(40) NOT NULL DEFAULT 'experience',
                    title VARCHAR(180) NOT NULL,
                    body TEXT NOT NULL,
                    tags_json LONGTEXT DEFAULT NULL,
                    source VARCHAR(60) NOT NULL DEFAULT 'assistant',
                    lead_id INT UNSIGNED DEFAULT NULL,
                    confidence DECIMAL(5,2) NOT NULL DEFAULT 0.70,
                    use_count INT UNSIGNED NOT NULL DEFAULT 0,
                    last_used_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_elite_ai_memory_type (memory_type),
                    KEY idx_elite_ai_memory_lead (lead_id),
                    KEY idx_elite_ai_memory_updated (updated_at),
                    FULLTEXT KEY ft_elite_ai_memory_text (title, body)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            if (function_exists('esm_log')) {
                esm_log('elite_ai', 'Could not ensure Elite AI memory schema.', ['error' => $e->getMessage()]);
            }
        }

        $ensured = true;
    }
}

if (!function_exists('elite_ai_memory_sanitize')) {
    function elite_ai_memory_sanitize(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $text) ?? $text;
        $text = preg_replace('/(?:\+?1[\s.\-]?)?(?:\(?\d{3}\)?[\s.\-]?)\d{3}[\s.\-]?\d{4}\b/', '[phone]', $text) ?? $text;
        $text = preg_replace('/https?:\/\/\S+/i', '[link]', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return mb_substr(trim($text), 0, 1200);
    }
}

if (!function_exists('elite_ai_memory_keywords')) {
    function elite_ai_memory_keywords(string $prompt, array $context = []): array
    {
        $source = strtolower($prompt . ' ' . (string) ($context['page'] ?? '') . ' ' . (string) ($context['page_title'] ?? ''));
        $source = preg_replace('/[^a-z0-9\s_\-]+/', ' ', $source) ?? $source;
        $words = preg_split('/\s+/', trim($source)) ?: [];
        $skip = array_flip(['the', 'and', 'for', 'with', 'this', 'that', 'from', 'what', 'when', 'where', 'how', 'lead', 'leads', 'please', 'need', 'want', 'can', 'you']);
        $keywords = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word) < 4 || isset($skip[$word])) {
                continue;
            }
            $keywords[$word] = true;
        }

        return array_slice(array_keys($keywords), 0, 8);
    }
}

if (!function_exists('elite_ai_memory_relevant')) {
    function elite_ai_memory_relevant(string $prompt, array $context = [], int $limit = 5): array
    {
        try {
            elite_ai_memory_ensure_schema();
            $limit = max(1, min(8, $limit));
            $leadId = (int) ($context['lead_id'] ?? 0);
            $keywords = elite_ai_memory_keywords($prompt, $context);
            $where = [];
            $params = [];

            if ($leadId > 0) {
                $where[] = 'lead_id = :lead_id';
                $params['lead_id'] = $leadId;
            }

            foreach ($keywords as $idx => $keyword) {
                $key = 'kw' . $idx;
                $where[] = "(title LIKE :$key OR body LIKE :$key)";
                $params[$key] = '%' . $keyword . '%';
            }

            if (!$where) {
                $where[] = "memory_type IN ('rule', 'preference')";
            }

            $rows = db_all(
                'SELECT id, memory_type, title, body, confidence, use_count, updated_at
                 FROM elite_ai_memory_items
                 WHERE ' . implode(' OR ', $where) . '
                 ORDER BY confidence DESC, updated_at DESC
                 LIMIT ' . $limit,
                $params
            );

            if ($rows) {
                $ids = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $rows);
                $ids = array_values(array_filter($ids));
                if ($ids) {
                    db_query(
                        'UPDATE elite_ai_memory_items
                         SET use_count = use_count + 1, last_used_at = :now
                         WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')',
                        ['now' => function_exists('now') ? now() : date('Y-m-d H:i:s')]
                    );
                }
            }

            return array_map(static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'type' => (string) ($row['memory_type'] ?? ''),
                    'title' => (string) ($row['title'] ?? ''),
                    'body' => (string) ($row['body'] ?? ''),
                    'confidence' => (float) ($row['confidence'] ?? 0),
                ];
            }, $rows);
        } catch (Throwable $e) {
            if (function_exists('esm_log')) {
                esm_log('elite_ai', 'Could not retrieve Elite AI memory.', ['error' => $e->getMessage()]);
            }
            return [];
        }
    }
}

if (!function_exists('elite_ai_memory_remember')) {
    function elite_ai_memory_remember(string $type, string $title, string $body, array $tags = [], ?int $leadId = null, float $confidence = 0.7): int
    {
        $title = mb_substr(elite_ai_memory_sanitize($title), 0, 180);
        $body = elite_ai_memory_sanitize($body);
        if ($title === '' || $body === '') {
            return 0;
        }

        try {
            elite_ai_memory_ensure_schema();
            return (int) db_insert(
                'INSERT INTO elite_ai_memory_items (memory_type, title, body, tags_json, source, lead_id, confidence, created_at, updated_at)
                 VALUES (:memory_type, :title, :body, :tags_json, :source, :lead_id, :confidence, :created_at, :updated_at)',
                [
                    'memory_type' => mb_substr(preg_replace('/[^a-z0-9_\-]/i', '', $type) ?: 'experience', 0, 40),
                    'title' => $title,
                    'body' => $body,
                    'tags_json' => json_encode(array_values($tags), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'source' => 'elite_ai_learning',
                    'lead_id' => $leadId && $leadId > 0 ? $leadId : null,
                    'confidence' => max(0.1, min(1.0, $confidence)),
                    'created_at' => function_exists('now') ? now() : date('Y-m-d H:i:s'),
                    'updated_at' => function_exists('now') ? now() : date('Y-m-d H:i:s'),
                ]
            );
        } catch (Throwable $e) {
            if (function_exists('esm_log')) {
                esm_log('elite_ai', 'Could not save Elite AI memory.', ['error' => $e->getMessage()]);
            }
            return 0;
        }
    }
}

if (!function_exists('elite_ai_memory_should_persist_interaction')) {
    function elite_ai_memory_should_persist_interaction(string $prompt, array $toolsUsed): bool
    {
        $prompt = strtolower(trim($prompt));
        if ($prompt === '') {
            return false;
        }

        if ((bool) preg_match('/\b(?:remember|learn|from now on|always|never|next time|rule|preference|we should|we need to)\b/i', $prompt)) {
            return true;
        }

        foreach ($toolsUsed as $tool) {
            $tool = (string) $tool;
            if (str_contains($tool, 'lead.') || str_contains($tool, 'draft') || str_contains($tool, 'notification')) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('elite_ai_memory_learn_from_interaction')) {
    function elite_ai_memory_learn_from_interaction(array $user, string $surface, string $prompt, array $toolsUsed, string $responseSummary, ?int $leadId, array $context): void
    {
        if (in_array('memory.remember', array_map('strval', $toolsUsed), true)) {
            return;
        }

        if (!elite_ai_memory_should_persist_interaction($prompt, $toolsUsed)) {
            return;
        }

        $toolLabel = implode(', ', array_slice(array_map('strval', $toolsUsed), 0, 5));
        $title = $toolLabel !== '' ? 'Assistant experience: ' . $toolLabel : 'Assistant experience';
        $body = 'Operator request: ' . $prompt . ' | Assistant outcome: ' . $responseSummary;
        $tags = array_values(array_filter([
            'surface:' . $surface,
            'page:' . (string) ($context['page'] ?? 'unknown'),
            $toolLabel !== '' ? 'tools:' . $toolLabel : '',
        ]));

        elite_ai_memory_remember('experience', $title, $body, $tags, $leadId, 0.55);
    }
}
