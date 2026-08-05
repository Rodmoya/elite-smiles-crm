<?php
declare(strict_types=1);

/**
 * Guarded lead-nurture agent.
 *
 * New leads are explicitly enrolled after the existing first-touch workflow.
 * Eligible first-touch records that predate the agent are backfilled safely.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/twilio.php';
require_once dirname(__DIR__) . '/notifications/internal_sms.php';
require_once __DIR__ . '/lead_communications.php';
require_once __DIR__ . '/lead_email.php';

if (!function_exists('lead_agent_enabled')) {
    function lead_agent_enabled(): bool
    {
        return defined('ELITE_LEAD_AGENT_ENABLED') && ELITE_LEAD_AGENT_ENABLED;
    }
}
if (!function_exists('lead_agent_mode')) {
    function lead_agent_mode(): string
    {
        $mode = defined('ELITE_LEAD_AGENT_MODE') ? strtolower(trim((string) ELITE_LEAD_AGENT_MODE)) : 'active';
        return in_array($mode, ['active', 'shadow', 'off'], true) ? $mode : 'shadow';
    }
}

if (!function_exists('lead_agent_ensure_schema')) {
    function lead_agent_ensure_schema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        db_query("CREATE TABLE IF NOT EXISTS lead_agent_states (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id INT UNSIGNED NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'active',
            cadence_step INT UNSIGNED NOT NULL DEFAULT 0,
            started_at DATETIME NOT NULL,
            next_action_at DATETIME NULL,
            last_action_at DATETIME NULL,
            last_inbound_event_key VARCHAR(160) NOT NULL DEFAULT '',
            last_decision VARCHAR(80) NOT NULL DEFAULT '',
            handoff_notified_at DATETIME NULL,
            human_takeover TINYINT(1) NOT NULL DEFAULT 0,
            pause_reason VARCHAR(190) NOT NULL DEFAULT '',
            lock_token VARCHAR(80) NOT NULL DEFAULT '',
            locked_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_lead_agent_lead (lead_id),
            KEY idx_lead_agent_due (status, next_action_at),
            KEY idx_lead_agent_lock (locked_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS lead_agent_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id INT UNSIGNED NOT NULL,
            event_key VARCHAR(190) NOT NULL,
            event_type VARCHAR(60) NOT NULL,
            channel VARCHAR(20) NOT NULL DEFAULT '',
            status VARCHAR(30) NOT NULL DEFAULT 'recorded',
            reason VARCHAR(190) NOT NULL DEFAULT '',
            payload_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_lead_agent_event (event_key),
            KEY idx_lead_agent_event_lead (lead_id, created_at),
            KEY idx_lead_agent_event_type (event_type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS lead_agent_daily_reports (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            report_date DATE NOT NULL,
            report_status VARCHAR(20) NOT NULL DEFAULT 'live',
            executive_summary TEXT NOT NULL,
            morning_review TEXT NOT NULL,
            metrics_json LONGTEXT NOT NULL,
            finalized_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_lead_agent_report_date (report_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS lead_agent_learning_items (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            learning_key VARCHAR(120) NOT NULL,
            intent VARCHAR(60) NOT NULL,
            channel VARCHAR(20) NOT NULL DEFAULT '',
            guidance VARCHAR(500) NOT NULL,
            evidence_count INT UNSIGNED NOT NULL DEFAULT 0,
            successful_reply_count INT UNSIGNED NOT NULL DEFAULT 0,
            scheduling_handoff_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_outcome VARCHAR(60) NOT NULL DEFAULT '',
            last_seen_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_lead_agent_learning_key (learning_key),
            KEY idx_lead_agent_learning_evidence (evidence_count, last_seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('lead_agent_learning_guidance_for_intent')) {
    function lead_agent_learning_guidance_for_intent(string $intent, string $channel): string
    {
        $channelLabel = $channel === 'email' ? 'email' : 'text message';
        return match ($intent) {
            'ready_to_schedule' => 'Scheduling language is a handoff signal. Pause automation and let Rod provide and confirm appointment times.',
            'cost_redirect' => 'Acknowledge that each smile is different and guide the lead to a complimentary consultation without discussing costs, pricing, payments, or financing.',
            'pause' => 'Respect the lead’s timing immediately and stop automated follow-up until a new inbound message restarts the conversation.',
            'opt_out' => 'Honor opt-out language immediately and do not send another automated message.',
            'needs_attention' => 'Clinical, urgent, complaint, call-request, or ambiguous messages require human judgment.',
            default => 'For a general ' . $channelLabel . ' reply, be warm and concise, answer only what is known, ask one useful next-step question, and avoid clinical or financial claims.',
        };
    }
}

if (!function_exists('lead_agent_record_learning')) {
    function lead_agent_record_learning(string $intent, string $channel, string $outcome = 'observed'): void
    {
        lead_agent_ensure_schema();
        $intent = preg_replace('/[^a-z0-9_\-]/i', '', strtolower($intent)) ?: 'general';
        $channel = in_array(strtolower($channel), ['sms', 'email'], true) ? strtolower($channel) : '';
        $outcome = preg_replace('/[^a-z0-9_\-]/i', '', strtolower($outcome)) ?: 'observed';
        $key = substr($intent . '|' . ($channel !== '' ? $channel : 'any'), 0, 120);
        $successful = in_array($outcome, ['automatic_reply_sent', 'reply_sent'], true) ? 1 : 0;
        $scheduled = $outcome === 'ready_to_schedule' ? 1 : 0;
        db_query(
            "INSERT INTO lead_agent_learning_items
                (learning_key, intent, channel, guidance, evidence_count, successful_reply_count, scheduling_handoff_count, last_outcome, last_seen_at, created_at, updated_at)
             VALUES (:learning_key, :intent, :channel, :guidance, 1, :successful, :scheduled, :outcome, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                guidance = VALUES(guidance),
                evidence_count = evidence_count + 1,
                successful_reply_count = successful_reply_count + VALUES(successful_reply_count),
                scheduling_handoff_count = scheduling_handoff_count + VALUES(scheduling_handoff_count),
                last_outcome = VALUES(last_outcome),
                last_seen_at = NOW(),
                updated_at = NOW()",
            [
                'learning_key' => $key,
                'intent' => substr($intent, 0, 60),
                'channel' => substr($channel, 0, 20),
                'guidance' => lead_agent_learning_guidance_for_intent($intent, $channel),
                'successful' => $successful,
                'scheduled' => $scheduled,
                'outcome' => substr($outcome, 0, 60),
            ]
        );
    }
}

if (!function_exists('lead_agent_learned_guidance')) {
    function lead_agent_learned_guidance(string $intent = '', int $limit = 4): array
    {
        lead_agent_ensure_schema();
        $limit = max(1, min(10, $limit));
        $params = [];
        $where = '';
        if ($intent !== '') {
            $where = 'WHERE intent = :intent';
            $params['intent'] = $intent;
        }
        return db_all("SELECT intent, channel, guidance, evidence_count, successful_reply_count, scheduling_handoff_count, last_outcome, last_seen_at
            FROM lead_agent_learning_items {$where}
            ORDER BY evidence_count DESC, last_seen_at DESC LIMIT {$limit}", $params);
    }
}

if (!function_exists('lead_agent_cadence_plan')) {
    function lead_agent_cadence_plan(): array
    {
        return [
            1 => ['hours' => 3.5, 'channel' => 'sms', 'phase' => 'same_day'],
            2 => ['hours' => 18, 'channel' => 'email', 'phase' => 'active_sprint'],
            3 => ['hours' => 24, 'channel' => 'sms', 'phase' => 'active_sprint'],
            4 => ['hours' => 42, 'channel' => 'email', 'phase' => 'active_sprint'],
            5 => ['hours' => 48, 'channel' => 'sms', 'phase' => 'active_sprint'],
            6 => ['hours' => 66, 'channel' => 'email', 'phase' => 'active_sprint'],
            7 => ['hours' => 72, 'channel' => 'sms', 'phase' => 'active_sprint'],
            8 => ['hours' => 96, 'channel' => 'email', 'phase' => 'daily_taper'],
            9 => ['hours' => 120, 'channel' => 'sms', 'phase' => 'daily_taper'],
            10 => ['hours' => 144, 'channel' => 'email', 'phase' => 'daily_taper'],
            11 => ['hours' => 168, 'channel' => 'sms', 'phase' => 'daily_taper'],
            12 => ['hours' => 252, 'channel' => 'email', 'phase' => 'twice_weekly'],
            13 => ['hours' => 336, 'channel' => 'sms', 'phase' => 'twice_weekly'],
        ];
    }
}

if (!function_exists('lead_agent_align_contact_time')) {
    function lead_agent_align_contact_time(DateTimeImmutable $candidate): DateTimeImmutable
    {
        $hour = (int) $candidate->format('G');
        if ($hour < 8) {
            return $candidate->setTime(8, 0);
        }
        if ($hour >= 21) {
            return $candidate->modify('+1 day')->setTime(8, 0);
        }
        return $candidate;
    }
}

if (!function_exists('lead_agent_step_schedule')) {
    function lead_agent_step_schedule(string $startedAt, int $step): array
    {
        $plan = lead_agent_cadence_plan();
        $start = new DateTimeImmutable($startedAt !== '' ? $startedAt : 'now', new DateTimeZone(APP_TIMEZONE));
        if (isset($plan[$step])) {
            $hours = (float) $plan[$step]['hours'];
            $seconds = (int) round($hours * 3600);
            $at = lead_agent_align_contact_time($start->modify('+' . $seconds . ' seconds'));
            return $plan[$step] + ['step' => $step, 'at' => $at->format('Y-m-d H:i:s')];
        }

        $extra = max(1, $step - count($plan));
        $hours = 336 + ($extra * 84);
        $channel = $extra % 2 === 0 ? 'sms' : 'email';
        $at = lead_agent_align_contact_time($start->modify('+' . ($hours * 3600) . ' seconds'));
        return ['step' => $step, 'hours' => $hours, 'channel' => $channel, 'phase' => 'twice_weekly', 'at' => $at->format('Y-m-d H:i:s')];
    }
}

if (!function_exists('lead_agent_incremental_schedule')) {
    function lead_agent_incremental_schedule(string $baseAt, int $completedStep): array
    {
        $current = lead_agent_step_schedule($baseAt, $completedStep);
        $following = lead_agent_step_schedule($baseAt, $completedStep + 1);
        $delayHours = max(3.5, (float) ($following['hours'] ?? 0) - (float) ($current['hours'] ?? 0));
        $base = new DateTimeImmutable($baseAt !== '' ? $baseAt : 'now', new DateTimeZone(APP_TIMEZONE));
        $at = lead_agent_align_contact_time($base->modify('+' . (int) round($delayHours * 3600) . ' seconds'));
        $following['at'] = $at->format('Y-m-d H:i:s');
        return $following;
    }
}

if (!function_exists('lead_agent_repair_compressed_catchup')) {
    function lead_agent_repair_compressed_catchup(): int
    {
        lead_agent_ensure_schema();
        $rows = db_all("SELECT * FROM lead_agent_states
            WHERE status IN ('active', 'engaged')
              AND cadence_step > 0
              AND last_action_at IS NOT NULL
              AND next_action_at IS NOT NULL
              AND next_action_at <= NOW()");
        $repaired = 0;
        foreach ($rows as $state) {
            $lastActionAt = trim((string) ($state['last_action_at'] ?? ''));
            if ($lastActionAt === '' || strtotime($lastActionAt) === false) {
                continue;
            }
            $following = lead_agent_incremental_schedule($lastActionAt, (int) ($state['cadence_step'] ?? 0));
            if (strtotime((string) $following['at']) <= time()) {
                continue;
            }
            $repaired += db_execute(
                "UPDATE lead_agent_states
                 SET next_action_at = :next_action_at, last_decision = 'repaired_compressed_catchup', updated_at = NOW()
                 WHERE lead_id = :lead_id AND next_action_at <= NOW()",
                ['next_action_at' => $following['at'], 'lead_id' => (int) ($state['lead_id'] ?? 0)]
            );
        }
        return $repaired;
    }
}

if (!function_exists('lead_agent_policy_flags')) {
    function lead_agent_policy_flags(string $body): array
    {
        $flags = [];
        $text = strtolower(trim($body));
        if ($text === '') {
            return ['empty_message'];
        }
        if (preg_match('/\b(cost|price|pricing|payment|payments|financ(?:e|ing)|monthly payment|credit approval|quote)\b|\$\s*\d/i', $text)) {
            $flags[] = 'treatment_cost_language';
        }
        if (preg_match('/\b(guarantee|guaranteed|perfect result|will fix|best treatment for you|you are a candidate)\b/i', $text)) {
            $flags[] = 'outcome_or_clinical_claim';
        }
        if (preg_match('/\b(card number|social security|ssn)\b|\b\d{3}-\d{2}-\d{4}\b/i', $text)) {
            $flags[] = 'sensitive_information';
        }
        return array_values(array_unique($flags));
    }
}

if (!function_exists('lead_agent_classify_inbound')) {
    function lead_agent_classify_inbound(string $body): string
    {
        $text = strtolower(trim(preg_replace('/\s+/', ' ', $body) ?? $body));
        if ($text === '') {
            return 'needs_attention';
        }
        if (preg_match('/^(stop|stopall|unsubscribe|cancel|end|quit|remove me|wrong number|do not text|don\'t text)\b/i', $text)) {
            return 'opt_out';
        }
        if (preg_match('/\b(not interested|no longer interested|not right now|maybe later|please pause)\b/i', $text)) {
            return 'pause';
        }
        if (preg_match('/\b(cost|price|pricing|how much|payment|payments|financ(?:e|ing)|monthly|insurance)\b|\$/i', $text)) {
            return 'cost_redirect';
        }
        if (preg_match('/\b(book|schedule|appointment|consult|come in|available|availability|morning|afternoon|evening|weekday|weekend|monday|tuesday|wednesday|thursday|friday|saturday|tomorrow|next week)\b/i', $text)) {
            return 'ready_to_schedule';
        }
        if (preg_match('/\b(call me|please call|can you call|complaint|upset|angry|refund|lawyer|pain|infection|swelling|emergency|diagnos|candidate|eligible)\b/i', $text)) {
            return 'needs_attention';
        }
        return 'general';
    }
}

if (!function_exists('lead_agent_event')) {
    function lead_agent_event(int $leadId, string $eventKey, string $type, string $channel, string $status, string $reason, array $payload = []): bool
    {
        lead_agent_ensure_schema();
        try {
            db_insert(
                'INSERT INTO lead_agent_events (lead_id, event_key, event_type, channel, status, reason, payload_json, created_at)
                 VALUES (:lead_id, :event_key, :event_type, :channel, :status, :reason, :payload_json, NOW())',
                [
                    'lead_id' => $leadId,
                    'event_key' => substr($eventKey, 0, 190),
                    'event_type' => substr($type, 0, 60),
                    'channel' => substr($channel, 0, 20),
                    'status' => substr($status, 0, 30),
                    'reason' => substr($reason, 0, 190),
                    'payload_json' => $payload ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                ]
            );
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('lead_agent_sms_blocked')) {
    function lead_agent_sms_blocked(array $lead): bool
    {
        return in_array(strtolower(trim((string) ($lead['sms_opt_status'] ?? ''))), ['dnd', 'opted_out'], true)
            || trim((string) ($lead['phone'] ?? '')) === '';
    }
}

if (!function_exists('lead_agent_email_blocked')) {
    function lead_agent_email_blocked(array $lead): bool
    {
        return strtolower(trim((string) ($lead['email_opt_status'] ?? ''))) === 'unsubscribed'
            || !filter_var(trim((string) ($lead['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('lead_agent_internal_or_test_record')) {
    function lead_agent_internal_or_test_record(array $lead): bool
    {
        $name = strtolower(trim((string) ($lead['full_name'] ?? '')));
        if ($name === 'rodrigo moya') {
            return true;
        }
        return $name !== '' && (bool) preg_match('/(?:^|\b)(?:test test|test lead|dummy data|integration test)(?:\b|$)/i', $name);
    }
}

if (!function_exists('lead_agent_backfill_ineligible_reason')) {
    function lead_agent_backfill_ineligible_reason(array $lead): string
    {
        if (lead_agent_internal_or_test_record($lead)) {
            return 'internal_or_test_record';
        }
        if (!in_array(trim((string) ($lead['status'] ?? '')), ['contacted', 'attempted_contact'], true)) {
            return 'stage_not_first_touch';
        }
        if (trim((string) ($lead['consultation_date'] ?? '')) !== '') {
            return 'consultation_date_present';
        }
        // "requested" is the normal intake default and does not mean an
        // appointment is being scheduled. Only durable scheduling states stop
        // automated follow-up.
        if (in_array(trim((string) ($lead['consultation_status'] ?? '')), ['scheduling', 'booked', 'confirmed', 'completed'], true)) {
            return 'scheduling_or_consultation';
        }
        if (in_array(trim((string) ($lead['follow_up_status'] ?? '')), ['ready_to_schedule', 'needs_attention'], true)) {
            return 'human_follow_up_state';
        }
        $lastOutbound = trim((string) ($lead['last_outbound_at'] ?? ''));
        if ($lastOutbound === '' || strtotime($lastOutbound) === false) {
            return 'first_touch_not_recorded';
        }
        $lastInbound = trim((string) ($lead['last_inbound_at'] ?? ''));
        if ($lastInbound !== '' && strtotime($lastInbound) !== false && strtotime($lastInbound) >= strtotime($lastOutbound)) {
            return 'newer_inbound_requires_review';
        }
        if (lead_agent_sms_blocked($lead) && lead_agent_email_blocked($lead)) {
            return 'no_consented_delivery_channel';
        }
        return '';
    }
}

if (!function_exists('lead_agent_enroll')) {
    function lead_agent_enroll(int $leadId, array $context = []): array
    {
        if (!lead_agent_enabled() || lead_agent_mode() === 'off') {
            return ['ok' => true, 'enrolled' => false, 'message' => 'Lead agent is disabled.'];
        }
        lead_agent_ensure_schema();
        $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$lead) {
            return ['ok' => false, 'enrolled' => false, 'message' => 'Lead not found.'];
        }
        if (in_array(trim((string) ($lead['status'] ?? '')), ['opted_out', 'consultation_booked', 'consult_completed', 'treatment_accepted', 'treatment_completed', 'lost_lead'], true)) {
            return ['ok' => true, 'enrolled' => false, 'message' => 'Lead stage is not eligible for nurture.'];
        }

        $requestedStart = trim((string) ($context['started_at'] ?? ''));
        $startedAt = $requestedStart !== '' && strtotime($requestedStart) !== false ? $requestedStart : now();
        $next = lead_agent_step_schedule($startedAt, 1);
        $requestedNext = trim((string) ($context['next_action_at'] ?? ''));
        if ($requestedNext !== '' && strtotime($requestedNext) !== false) {
            $next['at'] = $requestedNext;
        }
        db_query(
            "INSERT INTO lead_agent_states (lead_id, status, cadence_step, started_at, next_action_at, last_action_at, last_decision, created_at, updated_at)
             VALUES (:lead_id, 'active', 0, :started_at, :next_action_at, :last_action_at, 'enrolled_after_first_touch', NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                status = IF(human_takeover = 1, status, 'active'),
                next_action_at = IF(human_takeover = 1, next_action_at, VALUES(next_action_at)),
                last_action_at = IF(human_takeover = 1, last_action_at, VALUES(last_action_at)),
                last_decision = IF(human_takeover = 1, last_decision, 'reenrolled_after_first_touch'),
                updated_at = NOW()",
            ['lead_id' => $leadId, 'started_at' => $startedAt, 'next_action_at' => $next['at'], 'last_action_at' => $startedAt]
        );
        lead_agent_event($leadId, 'enroll-' . $leadId . '-' . date('YmdHis'), 'enrolled', '', 'recorded', 'first_touch_completed', $context + ['next_action_at' => $next['at']]);
        if (function_exists('lead_comm_insert_activity')) {
            lead_comm_insert_activity($leadId, 'lead_agent_enrolled', 'Lead Agent started the active-sprint nurture sequence.', [
                'next_action_at' => $next['at'],
                'mode' => lead_agent_mode(),
                'source' => (string) ($context['source'] ?? 'new_lead_first_touch'),
            ], 'Lead Agent');
        }
        return ['ok' => true, 'enrolled' => true, 'next_action_at' => $next['at']];
    }
}

if (!function_exists('lead_agent_backfill_eligible')) {
    function lead_agent_backfill_eligible(int $limit = 200, bool $dryRun = false): array
    {
        if (!lead_agent_enabled() || lead_agent_mode() === 'off') {
            return ['ok' => true, 'evaluated' => 0, 'enrolled' => 0, 'candidates' => []];
        }
        lead_agent_ensure_schema();
        $limit = max(1, min(500, $limit));
        $rows = db_all("SELECT l.*
            FROM leads l
            LEFT JOIN lead_agent_states s ON s.lead_id = l.id
            WHERE s.id IS NULL
              AND l.status IN ('contacted', 'attempted_contact')
            ORDER BY COALESCE(l.last_outbound_at, l.updated_at, l.created_at) ASC, l.id ASC
            LIMIT {$limit}");

        $candidates = [];
        $enrolled = 0;
        foreach ($rows as $lead) {
            $reason = lead_agent_backfill_ineligible_reason($lead);
            if ($reason !== '') {
                continue;
            }
            $startedAt = (string) $lead['last_outbound_at'];
            $nextActionAt = lead_agent_step_schedule($startedAt, 1)['at'];
            $existingNext = trim((string) ($lead['next_follow_up_at'] ?? ''));
            if ($existingNext !== '' && strtotime($existingNext) !== false && strtotime($existingNext) > time()) {
                $nextActionAt = $existingNext;
            }
            $candidate = [
                'lead_id' => (int) ($lead['id'] ?? 0),
                'full_name' => (string) ($lead['full_name'] ?? ''),
                'started_at' => $startedAt,
                'next_action_at' => $nextActionAt,
                'channel' => lead_agent_sms_blocked($lead) ? 'email' : 'sms',
            ];
            $candidates[] = $candidate;
            if ($dryRun) {
                continue;
            }
            $result = lead_agent_enroll((int) $candidate['lead_id'], [
                'source' => 'eligible_first_touch_backfill',
                'started_at' => $startedAt,
                'next_action_at' => $nextActionAt,
            ]);
            if (!empty($result['enrolled'])) {
                $enrolled++;
            }
        }

        return [
            'ok' => true,
            'evaluated' => count($rows),
            'enrolled' => $enrolled,
            'dry_run' => $dryRun,
            'candidates' => $candidates,
        ];
    }
}

if (!function_exists('lead_agent_pause')) {
    function lead_agent_pause(int $leadId, string $reason, string $status = 'paused'): void
    {
        lead_agent_ensure_schema();
        db_execute(
            'UPDATE lead_agent_states SET status = :status, next_action_at = NULL, pause_reason = :reason, lock_token = \'\', locked_at = NULL, updated_at = NOW() WHERE lead_id = :lead_id',
            ['status' => $status, 'reason' => substr($reason, 0, 190), 'lead_id' => $leadId]
        );
    }
}

if (!function_exists('lead_agent_internal_handoff')) {
    function lead_agent_internal_handoff(array $lead, string $kind, string $reason): array
    {
        $leadId = (int) ($lead['id'] ?? 0);
        $status = $kind === 'ready_to_schedule' ? 'ready_to_schedule' : 'needs_attention';
        lead_agent_pause($leadId, $reason, $status);

        if (function_exists('leads_has_column') && leads_has_column('follow_up_status')) {
            db_execute('UPDATE leads SET follow_up_status = :status, next_follow_up_at = NULL, updated_at = NOW() WHERE id = :id LIMIT 1', [
                'status' => $status === 'ready_to_schedule' ? 'ready_to_schedule' : 'needs_follow_up',
                'id' => $leadId,
            ]);
        }

        $push = ['sent' => false, 'configured' => false];
        try {
            $pushPath = dirname(__DIR__) . '/core/mobile_ai_push.php';
            if (is_file($pushPath)) {
                require_once $pushPath;
            }
            if (function_exists('mobile_ai_send_lead_event_push')) {
                $push = mobile_ai_send_lead_event_push($lead, [
                    'lead_id' => $leadId,
                    'type' => 'reply',
                    'message' => $status === 'ready_to_schedule'
                        ? 'Ready to schedule. Lead Agent paused; Rod must provide appointment times.'
                        : 'Lead Agent needs help deciding the next response and has paused.',
                    'notification_id' => 'lead-agent-' . $status . '-' . $leadId . '-' . time(),
                ]);
            }
        } catch (Throwable $e) {
            esm_log('lead_agent', 'Elite AI handoff push failed.', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
        }

        $internal = ['ok' => false, 'message' => 'Rod recipient is unavailable.'];
        $recipient = internal_sms_find_recipient('rod_moya');
        if ($recipient && !empty($recipient['enabled'])) {
            $internal = internal_sms_send(
                $recipient,
                $status === 'ready_to_schedule'
                    ? 'Elite AI: A lead is ready to schedule. Lead Agent is paused. Open CRM lead #' . $leadId . ' to provide and confirm appointment times.'
                    : 'Elite AI: Lead Agent needs your review for CRM lead #' . $leadId . '. Automation is paused.',
                0
            );
        }

        db_execute('UPDATE lead_agent_states SET handoff_notified_at = NOW(), last_decision = :decision, updated_at = NOW() WHERE lead_id = :lead_id', [
            'decision' => $status,
            'lead_id' => $leadId,
        ]);
        lead_agent_event($leadId, 'handoff-' . $status . '-' . $leadId . '-' . time(), 'handoff', '', 'recorded', $reason, [
            'elite_ai_push_sent' => !empty($push['sent']),
            'internal_sms_sent' => !empty($internal['ok']),
        ]);
        lead_comm_insert_activity($leadId, 'lead_agent_handoff', $status === 'ready_to_schedule'
            ? 'Lead Agent paused and handed this lead to Rod for scheduling.'
            : 'Lead Agent paused and requested human review.', [
                'kind' => $status,
                'reason' => $reason,
                'elite_ai_push_sent' => !empty($push['sent']),
                'internal_sms_sent' => !empty($internal['ok']),
            ], 'Lead Agent');

        return ['ok' => true, 'status' => $status, 'push' => $push, 'internal_sms' => $internal];
    }
}

if (!function_exists('lead_agent_sms_send')) {
    function lead_agent_sms_send(array $lead, string $body, string $eventKey): array
    {
        $leadId = (int) ($lead['id'] ?? 0);
        $flags = lead_agent_policy_flags($body);
        if ($flags !== []) {
            return ['ok' => false, 'message' => 'Policy blocked SMS.', 'policy_flags' => $flags];
        }
        $result = elite_twilio_send_sms((string) ($lead['phone'] ?? ''), $body, [
            'lead_id' => $leadId,
            'lead' => $lead,
            'send_pushover_fallback' => true,
            'fallback_summary' => 'Lead Agent SMS could not be delivered. Open the CRM to review.',
            'original_body' => $body,
        ]);
        if (empty($result['ok'])) {
            return $result;
        }
        $sentBody = (string) ($result['body'] ?? $body);
        $messageId = lead_comm_insert_message([
            'lead_id' => $leadId,
            'direction' => 'outbound',
            'channel' => 'sms',
            'from_number' => (string) ($result['from'] ?? ''),
            'to_number' => (string) ($result['to'] ?? $lead['phone'] ?? ''),
            'body' => $sentBody,
            'twilio_message_sid' => (string) ($result['twilio_sid'] ?? ''),
            'twilio_status' => (string) ($result['twilio_status'] ?? ''),
            'is_read' => 1,
        ]);
        lead_comm_insert_activity($leadId, 'lead_agent_sms_outbound', 'Lead Agent sent an approved SMS.', [
            'message_id' => $messageId,
            'event_key' => $eventKey,
            'twilio_sid' => (string) ($result['twilio_sid'] ?? ''),
        ], 'Lead Agent');
        lead_comm_update_rollup($leadId);
        return ['ok' => true, 'message_id' => $messageId, 'body' => $sentBody];
    }
}

if (!function_exists('lead_agent_email_send')) {
    function lead_agent_email_send(array $lead, string $subject, string $body, string $eventKey): array
    {
        $flags = lead_agent_policy_flags($subject . ' ' . $body);
        if ($flags !== []) {
            return ['ok' => false, 'message' => 'Policy blocked email.', 'policy_flags' => $flags];
        }
        $result = lead_email_send((int) ($lead['id'] ?? 0), $subject, $body, 'Lead Agent');
        if (!empty($result['ok'])) {
            lead_comm_insert_activity((int) $lead['id'], 'lead_agent_email_outbound', 'Lead Agent sent an approved email.', [
                'email_id' => (int) ($result['email_id'] ?? 0),
                'event_key' => $eventKey,
            ], 'Lead Agent');
        }
        return $result;
    }
}

if (!function_exists('lead_agent_first_name')) {
    function lead_agent_first_name(array $lead): string
    {
        $name = trim((string) ($lead['full_name'] ?? ''));
        $first = preg_split('/\s+/', $name)[0] ?? '';
        return preg_replace('/[^\p{L}\p{M}\'\-]/u', '', $first) ?: '';
    }
}

if (!function_exists('lead_agent_approved_followup')) {
    function lead_agent_approved_followup(array $lead, string $channel, int $step): array
    {
        $first = lead_agent_first_name($lead);
        $hello = $first !== '' ? 'Hi ' . $first . ',' : 'Hi,';
        $sms = [
            1 => $hello . ' Rod with Elite Smiles checking back. What would you most like to improve about your smile? I can help you with the next step. Reply STOP to opt out.',
            3 => $hello . ' if a brighter, more even smile is still on your mind, I can help you arrange a complimentary consultation with Dr. Meden. Do mornings or afternoons usually work better?',
            5 => $hello . ' just making sure your questions did not get lost. What would help you feel comfortable taking the next step toward your smile consultation?',
            7 => $hello . ' I am still here to help with your smile goals. Would you like Rod to help arrange your complimentary consultation?',
            9 => $hello . ' checking in from Elite Smiles. If you are still exploring your options, a complimentary consultation is the easiest next step. Would you like help getting started?',
            11 => $hello . ' your smile consultation is here whenever you are ready. Reply with the best day of the week and Rod can help from there.',
            13 => $hello . ' just keeping the door open. If improving your smile is still a goal, reply when you are ready and we will help with the next step.',
        ];
        if ($channel === 'sms') {
            $body = $sms[$step] ?? $hello . ' Elite Smiles checking in. Is improving your smile still something you would like help with? Reply STOP to opt out.';
            return ['subject' => '', 'body' => $body];
        }

        $subject = $step <= 4 ? 'Your smile goals' : 'Still thinking about your smile?';
        $body = $hello . "\n\n"
            . ($step <= 4
                ? 'I wanted to make sure you have an easy way to continue the conversation. Dr. Meden can review your goals during a complimentary consultation and explain which options fit your smile.'
                : 'Whenever you are ready, Elite Smiles can help you understand what is possible for your smile through a complimentary consultation with Dr. Meden.')
            . "\n\nWould mornings or afternoons usually be easier for you?\n\nElite Smiles";
        return ['subject' => $subject, 'body' => $body];
    }
}

if (!function_exists('lead_agent_cost_redirect')) {
    function lead_agent_cost_redirect(array $lead, string $channel): array
    {
        $first = lead_agent_first_name($lead);
        $hello = $first !== '' ? 'Hi ' . $first . ',' : 'Hi,';
        $body = $hello . ' every smile is different, so Dr. Meden reviews your goals and clinical needs during the complimentary consultation. Would you like Rod to help get that scheduled?';
        return $channel === 'email'
            ? ['subject' => 'Your Elite Smiles consultation', 'body' => $body . "\n\nElite Smiles"]
            : ['subject' => '', 'body' => $body];
    }
}

if (!function_exists('lead_agent_handle_inbound')) {
    function lead_agent_handle_inbound(int $leadId, string $body, string $channel = 'sms', string $eventKey = ''): array
    {
        if (!lead_agent_enabled() || lead_agent_mode() === 'off') {
            return ['ok' => true, 'handled' => false, 'message' => 'Lead agent is disabled.'];
        }
        lead_agent_ensure_schema();
        $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$lead) {
            return ['ok' => false, 'handled' => false, 'message' => 'Lead not found.'];
        }

        $eventKey = $eventKey !== '' ? $eventKey : 'inbound-' . $channel . '-' . $leadId . '-' . hash('sha256', $body);
        $state = db_one('SELECT * FROM lead_agent_states WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]);
        if (!$state) {
            lead_agent_enroll($leadId, ['source' => 'inbound_message']);
            $state = db_one('SELECT * FROM lead_agent_states WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]);
        }
        if ((string) ($state['last_inbound_event_key'] ?? '') === $eventKey) {
            return ['ok' => true, 'handled' => false, 'duplicate' => true];
        }

        db_execute('UPDATE lead_agent_states SET last_inbound_event_key = :event_key, next_action_at = NULL, updated_at = NOW() WHERE lead_id = :lead_id', [
            'event_key' => substr($eventKey, 0, 160),
            'lead_id' => $leadId,
        ]);
        $intent = lead_agent_classify_inbound($body);
        lead_agent_event($leadId, $eventKey, 'inbound_classified', $channel, 'recorded', $intent);
        lead_agent_record_learning($intent, $channel, 'observed');

        if ($intent === 'opt_out') {
            lead_agent_pause($leadId, 'inbound_opt_out', 'opted_out');
            return ['ok' => true, 'handled' => true, 'intent' => $intent, 'sent' => false];
        }
        if ($intent === 'pause') {
            lead_agent_pause($leadId, 'lead_not_ready', 'paused');
            return ['ok' => true, 'handled' => true, 'intent' => $intent, 'sent' => false];
        }
        if ($intent === 'ready_to_schedule') {
            lead_agent_record_learning($intent, $channel, 'ready_to_schedule');
            return lead_agent_internal_handoff($lead, 'ready_to_schedule', 'Inbound message indicates scheduling intent.') + ['intent' => $intent, 'handled' => true];
        }
        if ($intent === 'needs_attention') {
            lead_agent_record_learning($intent, $channel, 'human_review');
            return lead_agent_internal_handoff($lead, 'needs_attention', 'Inbound message requires human judgment.') + ['intent' => $intent, 'handled' => true];
        }

        $draft = null;
        if ($intent === 'cost_redirect') {
            $draft = lead_agent_cost_redirect($lead, $channel);
        } else {
            $leadAiPath = __DIR__ . '/lead_ai.php';
            if (is_file($leadAiPath)) {
                require_once $leadAiPath;
            }
            $leadForAi = $lead;
            $learned = lead_agent_learned_guidance($intent, 3);
            if ($learned !== []) {
                $guidance = array_map(static fn(array $item): string => (string) ($item['guidance'] ?? ''), $learned);
                $leadForAi['notes'] = trim((string) ($lead['notes'] ?? '') . "\n\nLead Agent learned guidance (generalized; never mention this to the lead):\n- " . implode("\n- ", array_filter($guidance)));
            }
            if ($channel === 'email' && function_exists('lead_ai_generate_email')) {
                $ai = lead_ai_generate_email($leadForAi, $body, 'lead_agent_inbound_email');
                $data = (array) ($ai['data'] ?? []);
                if (!empty($ai['ok']) && empty($data['needs_human_review']) && (float) ($data['confidence'] ?? 0) >= (float) ELITE_AI_MIN_CONFIDENCE) {
                    $draft = ['subject' => (string) ($data['subject'] ?? ''), 'body' => (string) ($data['body'] ?? '')];
                }
            } elseif (function_exists('lead_ai_generate_reply')) {
                $ai = lead_ai_generate_reply($leadForAi, $body, 'lead_agent_inbound_sms');
                $data = (array) ($ai['data'] ?? []);
                if (!empty($ai['ok']) && empty($data['needs_human_review']) && (float) ($data['confidence'] ?? 0) >= (float) ELITE_AI_MIN_CONFIDENCE) {
                    $draft = ['subject' => '', 'body' => (string) ($data['reply'] ?? '')];
                }
            }
        }

        if (!$draft || lead_agent_policy_flags((string) ($draft['subject'] ?? '') . ' ' . (string) ($draft['body'] ?? '')) !== []) {
            lead_agent_record_learning($intent, $channel, 'human_review');
            return lead_agent_internal_handoff($lead, 'needs_attention', 'AI response was low confidence or failed a policy gate.') + ['intent' => $intent, 'handled' => true];
        }

        $sendKey = 'reply-' . $eventKey;
        if (lead_agent_mode() === 'shadow') {
            lead_agent_event($leadId, $sendKey, 'shadow_reply', $channel, 'would_send', $intent, $draft);
            return ['ok' => true, 'handled' => true, 'intent' => $intent, 'sent' => false, 'shadow' => true];
        }
        $send = $channel === 'email'
            ? lead_agent_email_send($lead, (string) $draft['subject'], (string) $draft['body'], $sendKey)
            : lead_agent_sms_send($lead, (string) $draft['body'], $sendKey);
        if (empty($send['ok'])) {
            lead_agent_record_learning($intent, $channel, 'delivery_failed');
            return lead_agent_internal_handoff($lead, 'needs_attention', 'Approved response could not be delivered.') + ['intent' => $intent, 'handled' => true];
        }

        $next = lead_agent_align_contact_time((new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->modify('+24 hours'));
        db_execute("UPDATE lead_agent_states SET status = 'engaged', last_action_at = NOW(), next_action_at = :next_action_at, last_decision = 'answered_inbound', updated_at = NOW() WHERE lead_id = :lead_id", [
            'next_action_at' => $next->format('Y-m-d H:i:s'),
            'lead_id' => $leadId,
        ]);
        lead_agent_event($leadId, $sendKey, 'automatic_reply', $channel, 'sent', $intent);
        lead_agent_record_learning($intent, $channel, 'automatic_reply_sent');
        return ['ok' => true, 'handled' => true, 'intent' => $intent, 'sent' => true];
    }
}

if (!function_exists('lead_agent_daily_outbound_count')) {
    function lead_agent_daily_outbound_count(int $leadId, string $date): int
    {
        $sms = (int) db_value("SELECT COUNT(*) FROM lead_messages WHERE lead_id = :lead_id AND direction = 'outbound' AND DATE(created_at) = :day", ['lead_id' => $leadId, 'day' => $date]);
        $email = 0;
        try {
            $email = (int) db_value("SELECT COUNT(*) FROM lead_emails WHERE lead_id = :lead_id AND direction = 'outbound' AND DATE(created_at) = :day", ['lead_id' => $leadId, 'day' => $date]);
        } catch (Throwable $e) {
            $email = 0;
        }
        return $sms + $email;
    }
}

if (!function_exists('lead_agent_guardrail_reason')) {
    function lead_agent_guardrail_reason(array $lead, array $state, array $schedule): string
    {
        if (!empty($state['human_takeover'])) {
            return 'human_takeover';
        }
        $stage = trim((string) ($lead['status'] ?? ''));
        if (in_array($stage, ['opted_out', 'consultation_booked', 'consult_completed', 'treatment_accepted', 'treatment_completed', 'lost_lead'], true)) {
            return 'terminal_or_human_stage';
        }
        if (trim((string) ($lead['consultation_date'] ?? '')) !== '') {
            return 'consultation_date_present';
        }
        if (lead_agent_sms_blocked($lead) && lead_agent_email_blocked($lead)) {
            return 'all_channels_opted_out';
        }
        $hour = (int) date('G');
        if ($hour < 8 || $hour >= 21) {
            return 'quiet_hours';
        }
        $startedDay = substr((string) ($state['started_at'] ?? ''), 0, 10);
        $today = date('Y-m-d');
        $max = $today === $startedDay ? 3 : (((int) ($schedule['hours'] ?? 0)) <= 72 ? 2 : 1);
        if (lead_agent_daily_outbound_count((int) $lead['id'], $today) >= $max) {
            return 'daily_cap';
        }
        return '';
    }
}

if (!function_exists('lead_agent_process_state')) {
    function lead_agent_process_state(array $state, bool $dryRun = false): array
    {
        $leadId = (int) ($state['lead_id'] ?? 0);
        $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$lead) {
            lead_agent_pause($leadId, 'lead_missing', 'paused');
            return ['lead_id' => $leadId, 'action' => 'paused', 'reason' => 'lead_missing'];
        }

        $nextStep = (int) ($state['cadence_step'] ?? 0) + 1;
        $schedule = lead_agent_step_schedule((string) ($state['started_at'] ?? now()), $nextStep);
        $reason = lead_agent_guardrail_reason($lead, $state, $schedule);
        if ($reason !== '') {
            if (in_array($reason, ['terminal_or_human_stage', 'consultation_date_present', 'all_channels_opted_out', 'human_takeover'], true)) {
                lead_agent_pause($leadId, $reason, $reason === 'all_channels_opted_out' ? 'opted_out' : 'paused');
            } else {
                $deferred = lead_agent_align_contact_time((new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->modify($reason === 'daily_cap' ? '+1 day' : '+1 hour'));
                db_execute('UPDATE lead_agent_states SET next_action_at = :next_action_at, last_decision = :decision, lock_token = \'\', locked_at = NULL, updated_at = NOW() WHERE lead_id = :lead_id', [
                    'next_action_at' => $deferred->format('Y-m-d H:i:s'), 'decision' => 'deferred_' . $reason, 'lead_id' => $leadId,
                ]);
            }
            return ['lead_id' => $leadId, 'action' => 'skipped', 'reason' => $reason];
        }

        $channel = (string) $schedule['channel'];
        if ($channel === 'sms' && lead_agent_sms_blocked($lead)) {
            $channel = 'email';
        }
        if ($channel === 'email' && lead_agent_email_blocked($lead)) {
            $channel = 'sms';
        }
        if (($channel === 'sms' && lead_agent_sms_blocked($lead)) || ($channel === 'email' && lead_agent_email_blocked($lead))) {
            lead_agent_internal_handoff($lead, 'needs_attention', 'No consented, deliverable contact channel remains.');
            return ['lead_id' => $leadId, 'action' => 'handoff', 'reason' => 'no_delivery_channel'];
        }

        $eventKey = 'cadence-' . $leadId . '-' . $nextStep;
        $draft = lead_agent_approved_followup($lead, $channel, $nextStep);
        $flags = lead_agent_policy_flags((string) ($draft['subject'] ?? '') . ' ' . (string) ($draft['body'] ?? ''));
        if ($flags !== []) {
            lead_agent_internal_handoff($lead, 'needs_attention', 'Cadence content failed a policy gate.');
            return ['lead_id' => $leadId, 'action' => 'handoff', 'reason' => 'policy_gate', 'flags' => $flags];
        }
        if ($dryRun || lead_agent_mode() === 'shadow') {
            lead_agent_event($leadId, $eventKey . '-shadow-' . date('YmdHi'), 'shadow_cadence', $channel, 'would_send', (string) $schedule['phase'], ['step' => $nextStep]);
            db_execute('UPDATE lead_agent_states SET lock_token = \'\', locked_at = NULL, last_decision = :decision, updated_at = NOW() WHERE lead_id = :lead_id', [
                'decision' => 'shadow_would_send_step_' . $nextStep, 'lead_id' => $leadId,
            ]);
            return ['lead_id' => $leadId, 'action' => 'would_send', 'channel' => $channel, 'step' => $nextStep];
        }

        if (!lead_agent_event($leadId, $eventKey, 'cadence_reserved', $channel, 'pending', (string) $schedule['phase'], ['step' => $nextStep])) {
            return ['lead_id' => $leadId, 'action' => 'skipped', 'reason' => 'duplicate_event'];
        }
        $send = $channel === 'email'
            ? lead_agent_email_send($lead, (string) $draft['subject'], (string) $draft['body'], $eventKey)
            : lead_agent_sms_send($lead, (string) $draft['body'], $eventKey);
        if (empty($send['ok'])) {
            db_execute("UPDATE lead_agent_events SET status = 'failed', reason = :reason WHERE event_key = :event_key", [
                'reason' => substr((string) ($send['message'] ?? 'delivery_failed'), 0, 190), 'event_key' => $eventKey,
            ]);
            lead_agent_internal_handoff($lead, 'needs_attention', 'Automated follow-up delivery failed.');
            return ['lead_id' => $leadId, 'action' => 'handoff', 'reason' => 'delivery_failed'];
        }

        $following = lead_agent_step_schedule((string) $state['started_at'], $nextStep + 1);
        if (strtotime((string) $following['at']) <= time()) {
            $following = lead_agent_incremental_schedule(now(), $nextStep);
        }
        db_execute("UPDATE lead_agent_states SET status = 'active', cadence_step = :step, last_action_at = NOW(), next_action_at = :next_action_at, last_decision = :decision, lock_token = '', locked_at = NULL, updated_at = NOW() WHERE lead_id = :lead_id", [
            'step' => $nextStep,
            'next_action_at' => $following['at'],
            'decision' => 'sent_step_' . $nextStep,
            'lead_id' => $leadId,
        ]);
        db_execute("UPDATE lead_agent_events SET status = 'sent', reason = 'delivered_to_provider' WHERE event_key = :event_key", ['event_key' => $eventKey]);
        return ['lead_id' => $leadId, 'action' => 'sent', 'channel' => $channel, 'step' => $nextStep, 'next_action_at' => $following['at']];
    }
}

if (!function_exists('lead_agent_run_due')) {
    function lead_agent_run_due(int $limit = 20, bool $dryRun = false): array
    {
        lead_agent_ensure_schema();
        $limit = max(1, min(50, $limit));
        $backfill = lead_agent_backfill_eligible(200, $dryRun);
        $repairedCatchup = $dryRun ? 0 : lead_agent_repair_compressed_catchup();
        $rows = db_all("SELECT * FROM lead_agent_states
            WHERE status IN ('active', 'engaged')
              AND human_takeover = 0
              AND next_action_at IS NOT NULL
              AND next_action_at <= NOW()
              AND (locked_at IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))
            ORDER BY next_action_at ASC, id ASC
            LIMIT {$limit}");

        $results = [];
        foreach ($rows as $row) {
            $leadId = (int) ($row['lead_id'] ?? 0);
            $token = bin2hex(random_bytes(16));
            $locked = db_execute("UPDATE lead_agent_states SET lock_token = :token, locked_at = NOW(), updated_at = NOW()
                WHERE lead_id = :lead_id AND (locked_at IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))", [
                'token' => $token, 'lead_id' => $leadId,
            ]);
            if ($locked < 1) {
                continue;
            }
            $fresh = db_one('SELECT * FROM lead_agent_states WHERE lead_id = :lead_id AND lock_token = :token LIMIT 1', ['lead_id' => $leadId, 'token' => $token]);
            if (!$fresh) {
                continue;
            }
            try {
                $results[] = lead_agent_process_state($fresh, $dryRun);
            } catch (Throwable $e) {
                db_execute("UPDATE lead_agent_states SET lock_token = '', locked_at = NULL, last_decision = 'worker_error', updated_at = NOW() WHERE lead_id = :lead_id", ['lead_id' => $leadId]);
                esm_log('lead_agent', 'Lead agent worker failed.', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
                $results[] = ['lead_id' => $leadId, 'action' => 'error', 'reason' => 'worker_exception'];
            }
        }
        try {
            $today = (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d');
            lead_agent_refresh_daily_report($today, false);
            if ((int) date('G') >= 6) {
                $yesterday = (new DateTimeImmutable('yesterday', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d');
                lead_agent_refresh_daily_report($yesterday, true);
            }
        } catch (Throwable $e) {
            esm_log('lead_agent', 'Daily operations report refresh failed.', ['error' => $e->getMessage()]);
        }
        return ['ok' => true, 'mode' => lead_agent_mode(), 'dry_run' => $dryRun, 'backfill' => $backfill, 'repaired_catchup' => $repairedCatchup, 'processed' => count($results), 'results' => $results];
    }
}

if (!function_exists('lead_agent_daily_metrics')) {
    function lead_agent_daily_metrics(string $date): array
    {
        lead_agent_ensure_schema();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Invalid report date.');
        }
        $eventCount = static function (string $where, array $params = []) use ($date): int {
            return (int) db_value("SELECT COUNT(*) FROM lead_agent_events WHERE DATE(created_at) = :report_date AND {$where}", ['report_date' => $date] + $params);
        };
        $metrics = [
            'enrolled' => $eventCount("event_type = 'enrolled'"),
            'cadence_sent' => $eventCount("event_type = 'cadence_reserved' AND status = 'sent'"),
            'automatic_replies' => $eventCount("event_type = 'automatic_reply' AND status = 'sent'"),
            'sms_sent' => $eventCount("event_type IN ('cadence_reserved', 'automatic_reply') AND status = 'sent' AND channel = 'sms'"),
            'emails_sent' => $eventCount("event_type IN ('cadence_reserved', 'automatic_reply') AND status = 'sent' AND channel = 'email'"),
            'inbound_handled' => $eventCount("event_type = 'inbound_classified'"),
            'ready_to_schedule_today' => $eventCount("event_type = 'handoff' AND reason LIKE 'Inbound message indicates scheduling intent.%'"),
            'needs_attention_today' => $eventCount("event_type = 'handoff' AND reason NOT LIKE 'Inbound message indicates scheduling intent.%'"),
            'policy_blocks' => $eventCount("event_type = 'handoff' AND reason LIKE '%policy%'"),
            'delivery_failures' => $eventCount("event_type = 'handoff' AND reason LIKE '%deliver%'"),
            'learning_observations' => $eventCount("event_type = 'inbound_classified'"),
            'active_now' => (int) db_value("SELECT COUNT(*) FROM lead_agent_states WHERE status IN ('active', 'engaged') AND human_takeover = 0"),
            'ready_to_schedule_now' => (int) db_value("SELECT COUNT(*) FROM lead_agent_states WHERE status = 'ready_to_schedule'"),
            'needs_attention_now' => (int) db_value("SELECT COUNT(*) FROM lead_agent_states WHERE status = 'needs_attention'"),
        ];
        $metrics['outbound_total'] = $metrics['sms_sent'] + $metrics['emails_sent'];
        $metrics['actions_completed'] = $metrics['outbound_total'] + $metrics['inbound_handled'];
        return $metrics;
    }
}

if (!function_exists('lead_agent_report_copy')) {
    function lead_agent_report_copy(string $date, array $metrics): array
    {
        $label = (new DateTimeImmutable($date, new DateTimeZone(APP_TIMEZONE)))->format('F j');
        $textLabel = (int) $metrics['sms_sent'] === 1 ? 'text' : 'texts';
        $emailLabel = (int) $metrics['emails_sent'] === 1 ? 'email' : 'emails';
        $inboundLabel = (int) $metrics['inbound_handled'] === 1 ? 'conversation' : 'conversations';
        $summary = $metrics['actions_completed'] > 0
            ? "Lead Agent completed {$metrics['actions_completed']} communication actions on {$label}: {$metrics['sms_sent']} {$textLabel}, {$metrics['emails_sent']} {$emailLabel}, and {$metrics['inbound_handled']} inbound {$inboundLabel} reviewed."
            : "Lead Agent recorded no communication actions on {$label}. The system remained available and monitored enrolled leads.";
        if ($metrics['ready_to_schedule_today'] > 0) {
            $summary .= " {$metrics['ready_to_schedule_today']} lead" . ($metrics['ready_to_schedule_today'] === 1 ? ' is' : 's are') . ' ready for Rod to schedule.';
        }
        if ($metrics['needs_attention_today'] > 0) {
            $summary .= " {$metrics['needs_attention_today']} exception" . ($metrics['needs_attention_today'] === 1 ? ' requires' : 's require') . ' human judgment.';
        } else {
            $summary .= ' No new exception required human judgment.';
        }
        $review = "Yesterday the agent handled {$metrics['inbound_handled']} inbound conversation" . ($metrics['inbound_handled'] === 1 ? '' : 's')
            . " and sent {$metrics['outbound_total']} approved follow-up" . ($metrics['outbound_total'] === 1 ? '' : 's') . '.';
        $review .= $metrics['ready_to_schedule_today'] > 0
            ? " Start with the {$metrics['ready_to_schedule_today']} scheduling handoff" . ($metrics['ready_to_schedule_today'] === 1 ? '' : 's') . '.'
            : ' There are no new scheduling handoffs from that day.';
        $review .= $metrics['needs_attention_today'] > 0
            ? " Then review {$metrics['needs_attention_today']} agent exception" . ($metrics['needs_attention_today'] === 1 ? '' : 's') . '.'
            : ' The agent completed its work without a new exception.';
        return ['executive_summary' => $summary, 'morning_review' => $review];
    }
}

if (!function_exists('lead_agent_refresh_daily_report')) {
    function lead_agent_refresh_daily_report(string $date, bool $finalize = false): array
    {
        $metrics = lead_agent_daily_metrics($date);
        $copy = lead_agent_report_copy($date, $metrics);
        db_query(
            "INSERT INTO lead_agent_daily_reports (report_date, report_status, executive_summary, morning_review, metrics_json, finalized_at, created_at, updated_at)
             VALUES (:report_date, :report_status, :executive_summary, :morning_review, :metrics_json, :finalized_at, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                report_status = IF(report_status = 'final', 'final', VALUES(report_status)),
                executive_summary = VALUES(executive_summary),
                morning_review = VALUES(morning_review),
                metrics_json = VALUES(metrics_json),
                finalized_at = IF(VALUES(finalized_at) IS NULL, finalized_at, VALUES(finalized_at)),
                updated_at = NOW()",
            [
                'report_date' => $date,
                'report_status' => $finalize ? 'final' : 'live',
                'executive_summary' => $copy['executive_summary'],
                'morning_review' => $copy['morning_review'],
                'metrics_json' => json_encode($metrics, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'finalized_at' => $finalize ? now() : null,
            ]
        );
        return ['date' => $date, 'status' => $finalize ? 'final' : 'live', 'metrics' => $metrics] + $copy;
    }
}

if (!function_exists('lead_agent_daily_reports')) {
    function lead_agent_daily_reports(int $limit = 31): array
    {
        lead_agent_ensure_schema();
        $limit = max(1, min(180, $limit));
        $rows = db_all("SELECT * FROM lead_agent_daily_reports ORDER BY report_date DESC LIMIT {$limit}");
        foreach ($rows as &$row) {
            $row['metrics'] = json_decode((string) ($row['metrics_json'] ?? '{}'), true) ?: [];
        }
        unset($row);
        return $rows;
    }
}

if (!function_exists('lead_agent_recent_activity')) {
    function lead_agent_recent_activity(string $date, int $limit = 30): array
    {
        lead_agent_ensure_schema();
        $limit = max(1, min(100, $limit));
        return db_all("SELECT e.id, e.lead_id, e.event_type, e.channel, e.status, e.reason, e.created_at, l.full_name
            FROM lead_agent_events e
            LEFT JOIN leads l ON l.id = e.lead_id
            WHERE DATE(e.created_at) = :report_date
              AND e.event_type NOT IN ('cadence_reserved')
            ORDER BY e.created_at DESC, e.id DESC LIMIT {$limit}", ['report_date' => $date]);
    }
}

if (!function_exists('lead_agent_exception_rows')) {
    function lead_agent_exception_rows(int $limit = 50): array
    {
        lead_agent_ensure_schema();
        $limit = max(1, min(100, $limit));
        $rows = db_all("SELECT l.*, s.pause_reason AS agent_attention_reason, s.updated_at AS agent_attention_at
            FROM lead_agent_states s
            INNER JOIN leads l ON l.id = s.lead_id
            WHERE s.status = 'needs_attention'
            ORDER BY COALESCE(s.handoff_notified_at, s.updated_at) DESC LIMIT {$limit}");
        foreach ($rows as &$lead) {
            $reason = trim((string) ($lead['agent_attention_reason'] ?? '')) ?: 'Lead Agent cannot safely determine the next step.';
            $lead['_action_queue'] = [
                'priority' => 100,
                'action_key' => 'agent_exception',
                'action_label' => 'Agent needs help',
                'action_tone' => 'rose',
                'stage_key' => (string) ($lead['status'] ?? ''),
                'stage_label' => 'Human review',
                'urgency_label' => 'Agent paused',
                'urgency_tone' => 'rose',
                'reason' => $reason,
                'tab' => 'communications',
                'source_label' => (string) ($lead['source'] ?? ''),
                'last_touch_at' => (string) ($lead['agent_attention_at'] ?? ''),
                'sort_at' => strtotime((string) ($lead['agent_attention_at'] ?? '')) ?: 0,
            ];
        }
        unset($lead);
        return $rows;
    }
}
