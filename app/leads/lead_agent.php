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
require_once __DIR__ . '/lead_meta.php';
require_once __DIR__ . '/lead_language.php';
require_once __DIR__ . '/lead_communications.php';
require_once __DIR__ . '/lead_email.php';
require_once __DIR__ . '/lead_agent_observability.php';
require_once __DIR__ . '/lead_conversion_intelligence.php';
require_once __DIR__ . '/lead_agent_instructions.php';
require_once dirname(__DIR__) . '/dentrix/dentrix_bridge.php';

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
            human_takeover_until DATETIME NULL,
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

        db_query("CREATE TABLE IF NOT EXISTS lead_agent_operator_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            request_code VARCHAR(24) NOT NULL,
            lead_id INT UNSIGNED NOT NULL,
            request_type VARCHAR(40) NOT NULL DEFAULT 'availability',
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            context_json LONGTEXT NULL,
            response_body VARCHAR(500) NOT NULL DEFAULT '',
            response_message_sid VARCHAR(80) NULL DEFAULT NULL,
            expires_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_lead_agent_operator_code (request_code),
            UNIQUE KEY uq_lead_agent_operator_sid (response_message_sid),
            KEY idx_lead_agent_operator_pending (lead_id, status, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS lead_agent_runs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            run_key VARCHAR(100) NOT NULL,
            mode VARCHAR(20) NOT NULL DEFAULT 'active',
            dry_run TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(24) NOT NULL DEFAULT 'running',
            started_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            due_count INT UNSIGNED NOT NULL DEFAULT 0,
            processed_count INT UNSIGNED NOT NULL DEFAULT 0,
            sent_count INT UNSIGNED NOT NULL DEFAULT 0,
            skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
            handoff_count INT UNSIGNED NOT NULL DEFAULT 0,
            error_count INT UNSIGNED NOT NULL DEFAULT 0,
            backfill_enrolled_count INT UNSIGNED NOT NULL DEFAULT 0,
            repaired_catchup_count INT UNSIGNED NOT NULL DEFAULT 0,
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            summary_json LONGTEXT NULL,
            error_message VARCHAR(500) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_lead_agent_run_key (run_key),
            KEY idx_lead_agent_run_started (started_at),
            KEY idx_lead_agent_run_status (status, started_at)
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
        $stateColumns = [
            'human_takeover_until' => 'ALTER TABLE lead_agent_states ADD COLUMN human_takeover_until DATETIME NULL AFTER human_takeover',
            'scheduling_phase' => "ALTER TABLE lead_agent_states ADD COLUMN scheduling_phase VARCHAR(40) NOT NULL DEFAULT '' AFTER pause_reason",
            'availability_option_1' => 'ALTER TABLE lead_agent_states ADD COLUMN availability_option_1 DATETIME NULL AFTER scheduling_phase',
            'availability_option_2' => 'ALTER TABLE lead_agent_states ADD COLUMN availability_option_2 DATETIME NULL AFTER availability_option_1',
            'selected_availability' => 'ALTER TABLE lead_agent_states ADD COLUMN selected_availability DATETIME NULL AFTER availability_option_2',
            'scheduling_context' => "ALTER TABLE lead_agent_states ADD COLUMN scheduling_context VARCHAR(500) NOT NULL DEFAULT '' AFTER selected_availability",
            'availability_pool_json' => "ALTER TABLE lead_agent_states ADD COLUMN availability_pool_json LONGTEXT NULL AFTER scheduling_context",
        ];
        foreach ($stateColumns as $column => $sql) {
            if (!db_one("SHOW COLUMNS FROM lead_agent_states LIKE '" . $column . "'")) {
                db_query($sql);
            }
        }
        lead_agent_observability_ensure_schema();
        lead_conversion_ensure_schema();
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
        $successful = $outcome === 'lead_replied' ? 1 : 0;
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

if (!function_exists('lead_agent_record_learning_outcome')) {
    function lead_agent_record_learning_outcome(string $intent, string $channel, string $outcome): void
    {
        lead_agent_ensure_schema();
        $intent = preg_replace('/[^a-z0-9_\-]/i', '', strtolower($intent)) ?: 'general';
        $channel = in_array(strtolower($channel), ['sms', 'email'], true) ? strtolower($channel) : '';
        $outcome = preg_replace('/[^a-z0-9_\-]/i', '', strtolower($outcome)) ?: 'observed';
        $key = substr($intent . '|' . ($channel !== '' ? $channel : 'any'), 0, 120);
        db_execute(
            "UPDATE lead_agent_learning_items SET
                successful_reply_count = successful_reply_count + :successful,
                scheduling_handoff_count = scheduling_handoff_count + :scheduled,
                last_outcome = :outcome,
                last_seen_at = NOW(), updated_at = NOW()
             WHERE learning_key = :learning_key",
            [
                'successful' => $outcome === 'lead_replied' ? 1 : 0,
                'scheduled' => $outcome === 'ready_to_schedule' ? 1 : 0,
                'outcome' => $outcome,
                'learning_key' => $key,
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

if (!function_exists('lead_agent_refresh_cadence_learning')) {
    /**
     * Convert observed delivery/reply outcomes into generalized guidance.
     * No patient text or identifiers are stored in the learning table.
     */
    function lead_agent_refresh_cadence_learning(int $days = 30): int
    {
        lead_agent_ensure_schema();
        $days = max(7, min(90, $days));
        $rows = db_all("SELECT channel, cadence_step,
                COUNT(*) AS touches,
                SUM(replied_at IS NOT NULL) AS replies,
                SUM(scheduling_intent_at IS NOT NULL) AS scheduling_intents,
                SUM(consultation_booked_at IS NOT NULL) AS bookings,
                SUM(delivery_status IN ('failed','undelivered','bounced','dropped')) AS failures
            FROM lead_agent_touchpoints
            WHERE cadence_step > 0 AND sent_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
            GROUP BY channel, cadence_step");
        $updated = 0;
        foreach ($rows as $row) {
            $channel = in_array((string) ($row['channel'] ?? ''), ['sms', 'email'], true) ? (string) $row['channel'] : '';
            $step = max(1, (int) ($row['cadence_step'] ?? 0));
            $touches = max(0, (int) ($row['touches'] ?? 0));
            if ($channel === '' || $touches < 1) {
                continue;
            }
            $replies = max(0, (int) ($row['replies'] ?? 0));
            $scheduling = max(0, (int) ($row['scheduling_intents'] ?? 0));
            $bookings = max(0, (int) ($row['bookings'] ?? 0));
            $failures = max(0, (int) ($row['failures'] ?? 0));
            $replyRate = round(($replies / $touches) * 100, 1);
            $schedulingRate = round(($scheduling / $touches) * 100, 1);
            $failureRate = round(($failures / $touches) * 100, 1);
            $intent = 'cadence_step_' . $step;
            $key = $intent . '|' . $channel;
            $guidance = "Observed {$touches} {$channel} follow-ups at cadence step {$step}: {$replyRate}% replied, {$schedulingRate}% showed scheduling intent, {$failureRate}% failed delivery. Keep the next message concise, warm, low-pressure, and focused on one next step.";
            db_query(
                "INSERT INTO lead_agent_learning_items
                    (learning_key, intent, channel, guidance, evidence_count, successful_reply_count, scheduling_handoff_count, last_outcome, last_seen_at, created_at, updated_at)
                 VALUES (:learning_key, :intent, :channel, :guidance, :evidence, :replies, :scheduling, :outcome, NOW(), NOW(), NOW())
                 ON DUPLICATE KEY UPDATE guidance = VALUES(guidance), evidence_count = VALUES(evidence_count),
                    successful_reply_count = VALUES(successful_reply_count), scheduling_handoff_count = VALUES(scheduling_handoff_count),
                    last_outcome = VALUES(last_outcome), last_seen_at = NOW(), updated_at = NOW()",
                [
                    'learning_key' => substr($key, 0, 120),
                    'intent' => substr($intent, 0, 60),
                    'channel' => $channel,
                    'guidance' => substr($guidance, 0, 500),
                    'evidence' => $touches,
                    'replies' => $replies,
                    'scheduling' => $scheduling,
                    'outcome' => $bookings > 0 ? 'consultation_booked' : ($scheduling > 0 ? 'ready_to_schedule' : 'observed'),
                ]
            );
            $updated++;
        }
        return $updated;
    }
}

if (!function_exists('lead_agent_cadence_learning_guidance')) {
    function lead_agent_cadence_learning_guidance(string $channel, int $limit = 3): array
    {
        lead_agent_ensure_schema();
        $channel = in_array($channel, ['sms', 'email'], true) ? $channel : '';
        if ($channel === '') {
            return [];
        }
        $limit = max(1, min(5, $limit));
        return db_all("SELECT intent, channel, guidance, evidence_count, successful_reply_count, scheduling_handoff_count
            FROM lead_agent_learning_items
            WHERE channel = :channel AND intent LIKE 'cadence_step_%'
            ORDER BY (successful_reply_count + scheduling_handoff_count * 2) / GREATEST(evidence_count, 1) DESC,
                     evidence_count DESC, last_seen_at DESC
            LIMIT {$limit}", ['channel' => $channel]);
    }
}

if (!function_exists('lead_agent_cadence_plan')) {
    function lead_agent_cadence_plan(): array
    {
        return [
            // First touch sends SMS immediately; its email waits until the
            // lead remains unanswered. SMS stays primary during the first two
            // days, with at least two SMS touches per day when deliverable.
            // The daily cap remains authoritative when quiet-hours alignment
            // moves two target windows onto the same calendar day.
            1 => ['hours' => 0.5, 'channel' => 'sms', 'phase' => 'same_day_delivery_check'],
            2 => ['hours' => 2, 'channel' => 'sms', 'phase' => 'same_day_goal_followup'],
            3 => ['hours' => 5, 'channel' => 'email', 'phase' => 'same_day_requested_information'],
            4 => ['hours' => 20, 'channel' => 'sms', 'phase' => 'next_day_early_reengagement'],
            5 => ['hours' => 24, 'channel' => 'sms', 'phase' => 'next_day_reengagement'],
            6 => ['hours' => 32, 'channel' => 'email', 'phase' => 'next_day_education'],
            7 => ['hours' => 48, 'channel' => 'sms', 'phase' => 'day_three_followup'],
            8 => ['hours' => 60, 'channel' => 'email', 'phase' => 'day_four_education'],
            9 => ['hours' => 96, 'channel' => 'sms', 'phase' => 'day_five_followup'],
            10 => ['hours' => 120, 'channel' => 'email', 'phase' => 'day_six_education'],
            11 => ['hours' => 144, 'channel' => 'sms', 'phase' => 'active_sprint_close'],
        ];
    }
}

if (!function_exists('lead_agent_align_contact_time')) {
    function lead_agent_align_contact_time(DateTimeImmutable $candidate): DateTimeImmutable
    {
        $hour = (int) $candidate->format('G');
        if ($hour < 9) {
            return $candidate->setTime(9, 0);
        }
        if ($hour >= 20) {
            return $candidate->modify('+1 day')->setTime(9, 0);
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
        $lastPlanItem = end($plan) ?: ['hours' => 144, 'channel' => 'sms'];
        $lastPlanHours = (int) round((float) ($lastPlanItem['hours'] ?? 144));
        // Two touches per week after the active sprint: three days, then four
        // days, alternating email and SMS without ever creating a burst.
        $hours = $lastPlanHours + (intdiv($extra, 2) * 168) + ($extra % 2 === 1 ? 72 : 0);
        $channel = $extra % 2 === 1 ? 'email' : 'sms';
        $at = lead_agent_align_contact_time($start->modify('+' . ($hours * 3600) . ' seconds'));
        return ['step' => $step, 'hours' => $hours, 'channel' => $channel, 'phase' => 'twice_weekly_nurture', 'at' => $at->format('Y-m-d H:i:s')];
    }
}

if (!function_exists('lead_agent_daily_outbound_limit')) {
    function lead_agent_daily_outbound_limit(string $startedAt, ?DateTimeImmutable $now = null): int
    {
        $timezone = new DateTimeZone(APP_TIMEZONE);
        $now = $now ?? new DateTimeImmutable('now', $timezone);
        if (trim($startedAt) === '' || strtotime($startedAt) === false) {
            return 1;
        }
        try {
            $started = new DateTimeImmutable($startedAt, $timezone);
        } catch (Throwable $e) {
            return 1;
        }

        // Immediate first touch is SMS. Day one allows the 30-minute and
        // two-hour SMS plus one delayed email; day two allows two SMS plus
        // one email. Later days stay at one total automated touch.
        $startedDay = $started->setTimezone($timezone)->setTime(0, 0);
        $currentDay = $now->setTimezone($timezone)->setTime(0, 0);
        $elapsedDays = (int) $startedDay->diff($currentDay)->format('%r%a');
        if ($elapsedDays <= 0) {
            return 4;
        }
        return $elapsedDays === 1 ? 3 : 1;
    }
}

if (!function_exists('lead_agent_prioritize_first_two_day_sms')) {
    /** Keep SMS ahead for two days and keep answered SMS conversations on SMS. */
    function lead_agent_prioritize_first_two_day_sms(
        string $plannedChannel,
        string $startedAt,
        int $smsSentToday,
        bool $smsUnavailable,
        ?DateTimeImmutable $now = null,
        bool $latestReplyIsSms = false
    ): string {
        $plannedChannel = $plannedChannel === 'email' ? 'email' : 'sms';
        if ($plannedChannel !== 'email' || $smsUnavailable) {
            return $plannedChannel;
        }
        if ($latestReplyIsSms) {
            return 'sms';
        }
        if ($smsSentToday >= 2) {
            return $plannedChannel;
        }
        $timezone = new DateTimeZone(APP_TIMEZONE);
        $now = $now ?? new DateTimeImmutable('now', $timezone);
        try {
            $started = new DateTimeImmutable($startedAt !== '' ? $startedAt : 'now', $timezone);
        } catch (Throwable $e) {
            return $plannedChannel;
        }
        $elapsedDays = (int)$started->setTime(0, 0)->diff($now->setTimezone($timezone)->setTime(0, 0))->format('%r%a');
        return $elapsedDays >= 0 && $elapsedDays <= 1 ? 'sms' : $plannedChannel;
    }
}

if (!function_exists('lead_agent_incremental_schedule')) {
    function lead_agent_incremental_schedule(string $baseAt, int $completedStep): array
    {
        $current = lead_agent_step_schedule($baseAt, $completedStep);
        $following = lead_agent_step_schedule($baseAt, $completedStep + 1);
        $delayHours = max(2, (float) ($following['hours'] ?? 0) - (float) ($current['hours'] ?? 0));
        $base = new DateTimeImmutable($baseAt !== '' ? $baseAt : 'now', new DateTimeZone(APP_TIMEZONE));
        $at = lead_agent_align_contact_time($base->modify('+' . (int) round($delayHours * 3600) . ' seconds'));
        $following['at'] = $at->format('Y-m-d H:i:s');
        return $following;
    }
}

if (!function_exists('lead_agent_post_reply_resume_step')) {
    /**
     * A text reply ends the unanswered email path. Resume with the next SMS
     * engagement step so an email is not sent after the lead answers by SMS.
     */
    function lead_agent_post_reply_resume_step(string $channel): int
    {
        return strtolower(trim($channel)) === 'sms' ? 3 : 2;
    }
}

if (!function_exists('lead_agent_repair_compressed_catchup')) {
    function lead_agent_repair_compressed_catchup(): int
    {
        lead_agent_ensure_schema();
        $rows = db_all("SELECT * FROM lead_agent_states
            WHERE status IN ('active', 'engaged', 'nurture')
              AND COALESCE(scheduling_phase, '') = ''
              AND next_action_at IS NOT NULL
            ORDER BY next_action_at ASC");
        $repaired = 0;
        foreach ($rows as $state) {
            $completedStep = (int) ($state['cadence_step'] ?? 0);
            $lastActionAt = trim((string) ($state['last_action_at'] ?? ''));
            $startedAt = trim((string) ($state['started_at'] ?? ''));
            if ($completedStep > 0 && ($lastActionAt === '' || strtotime($lastActionAt) === false)) {
                continue;
            }
            $following = $completedStep > 0
                ? lead_agent_incremental_schedule($lastActionAt, $completedStep)
                : lead_agent_step_schedule($startedAt !== '' ? $startedAt : now(), 1);
            $currentTimestamp = strtotime((string) ($state['next_action_at'] ?? ''));
            $minimumTimestamp = strtotime((string) ($following['at'] ?? ''));
            if ($currentTimestamp === false || $minimumTimestamp === false || $minimumTimestamp <= time() || $currentTimestamp >= $minimumTimestamp) {
                continue;
            }
            $changed = db_execute(
                "UPDATE lead_agent_states
                 SET next_action_at = :next_action_at, last_decision = 'repaired_compressed_catchup', updated_at = NOW()
                 WHERE lead_id = :lead_id AND next_action_at < :minimum_next_action_at",
                ['next_action_at' => $following['at'], 'minimum_next_action_at' => $following['at'], 'lead_id' => (int) ($state['lead_id'] ?? 0)]
            );
            $repaired += $changed;
            if ($changed > 0) {
                lead_agent_event((int) ($state['lead_id'] ?? 0), 'cadence-spacing-repaired-' . (int) ($state['lead_id'] ?? 0) . '-' . date('YmdHi'), 'cadence_rescheduled', '', 'recorded', 'minimum_spacing_enforced', [
                    'previous_next_action_at' => (string) ($state['next_action_at'] ?? ''),
                    'next_action_at' => (string) ($following['at'] ?? ''),
                ]);
            }
        }
        return $repaired;
    }
}

if (!function_exists('lead_agent_repair_first_day_schedule')) {
    /** Accelerate unanswered first-day leads still carrying an older, slower schedule. */
    function lead_agent_repair_first_day_schedule(int $limit = 200): int
    {
        lead_agent_ensure_schema();
        $limit = max(1, min(500, $limit));
        $rows = db_all("SELECT s.lead_id, s.started_at, s.next_action_at, s.cadence_step,
                l.last_outbound_at, l.last_inbound_at
            FROM lead_agent_states s
            INNER JOIN leads l ON l.id = s.lead_id
            WHERE s.status IN ('active', 'engaged')
              AND s.human_takeover = 0
              AND s.cadence_step IN (0,1,2)
              AND s.next_action_at IS NOT NULL
              AND s.started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY s.started_at ASC
            LIMIT {$limit}");
        $repaired = 0;

        foreach ($rows as $row) {
            $startedAt = trim((string) ($row['started_at'] ?? ''));
            $currentAt = trim((string) ($row['next_action_at'] ?? ''));
            $lastOutboundAt = trim((string) ($row['last_outbound_at'] ?? ''));
            $lastInboundAt = trim((string) ($row['last_inbound_at'] ?? ''));
            if ($startedAt === '' || $currentAt === '' || strtotime($startedAt) === false || strtotime($currentAt) === false) {
                continue;
            }
            if ($lastInboundAt !== '' && strtotime($lastInboundAt) !== false
                && ($lastOutboundAt === '' || strtotime($lastInboundAt) >= strtotime($lastOutboundAt))) {
                continue;
            }

            $completedStep = (int)($row['cadence_step'] ?? 0);
            $sameDay = lead_agent_step_schedule($startedAt, $completedStep + 1);
            $sameDayAt = trim((string) ($sameDay['at'] ?? ''));
            if ($sameDayAt === '' || strtotime($sameDayAt) === false || strtotime($sameDayAt) >= strtotime($currentAt)) {
                continue;
            }

            $changed = db_execute(
                "UPDATE lead_agent_states
                 SET next_action_at = :next_action_at, last_decision = 'first_day_schedule_repaired', updated_at = NOW()
                 WHERE lead_id = :lead_id AND cadence_step = :cadence_step AND human_takeover = 0
                   AND next_action_at > :next_action_compare",
                [
                    'next_action_at' => $sameDayAt,
                    'next_action_compare' => $sameDayAt,
                    'lead_id' => (int) ($row['lead_id'] ?? 0),
                    'cadence_step' => $completedStep,
                ]
            );
            $repaired += $changed;
            if ($changed > 0) {
                lead_agent_event((int) ($row['lead_id'] ?? 0), 'first-day-schedule-repaired-' . (int) ($row['lead_id'] ?? 0) . '-' . $completedStep, 'cadence_rescheduled', 'sms', 'recorded', 'first_day_engagement_window', [
                    'previous_next_action_at' => $currentAt,
                    'next_action_at' => $sameDayAt,
                ]);
            }
        }

        if ($repaired > 0) {
            lead_agent_sync_crm_followup_schedule();
        }
        return $repaired;
    }
}

if (!function_exists('lead_agent_repair_slow_active_sprint')) {
    /** One-way migration from older, slower plans to the current six-day sprint. */
    function lead_agent_repair_slow_active_sprint(int $limit = 500): int
    {
        lead_agent_ensure_schema();
        $limit = max(1, min(1000, $limit));
        $activeStepCount = count(lead_agent_cadence_plan());
        $rows = db_all("SELECT s.lead_id, s.started_at, s.next_action_at, s.cadence_step,
                l.last_inbound_at, l.last_outbound_at, l.follow_up_status
            FROM lead_agent_states s
            INNER JOIN leads l ON l.id = s.lead_id
            WHERE s.status IN ('active','engaged')
              AND s.human_takeover = 0
              AND s.cadence_step < {$activeStepCount}
              AND COALESCE(s.scheduling_phase, '') = ''
              AND s.next_action_at IS NOT NULL
              AND l.status NOT IN ('opted_out','lost_lead','consultation_booked','consult_completed','treatment_accepted','treatment_completed')
            ORDER BY s.next_action_at ASC
            LIMIT {$limit}");
        $repaired = 0;
        foreach ($rows as $row) {
            if (in_array(trim((string)($row['follow_up_status'] ?? '')), ['ready_to_schedule', 'needs_attention'], true)) {
                continue;
            }
            $lastInbound = trim((string)($row['last_inbound_at'] ?? ''));
            $lastOutbound = trim((string)($row['last_outbound_at'] ?? ''));
            if ($lastInbound !== '' && strtotime($lastInbound) !== false
                && ($lastOutbound === '' || strtotime($lastInbound) >= strtotime($lastOutbound))) {
                continue;
            }
            $startedAt = trim((string)($row['started_at'] ?? ''));
            $currentAt = trim((string)($row['next_action_at'] ?? ''));
            if ($startedAt === '' || $currentAt === '' || strtotime($startedAt) === false || strtotime($currentAt) === false) {
                continue;
            }
            $completedStep = (int)($row['cadence_step'] ?? 0);
            $expected = lead_agent_step_schedule($startedAt, $completedStep + 1);
            $targetAt = (string)($expected['at'] ?? '');
            if ($targetAt === '' || strtotime($targetAt) === false || strtotime($targetAt) >= strtotime($currentAt)) {
                continue;
            }
            if (strtotime($targetAt) <= time()) {
                $targetAt = lead_agent_align_contact_time(new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d H:i:s');
            }
            $changed = db_execute("UPDATE lead_agent_states
                SET next_action_at = :next_action_at, last_decision = 'slow_active_sprint_repaired', updated_at = NOW()
                WHERE lead_id = :lead_id AND cadence_step = :cadence_step AND human_takeover = 0
                  AND next_action_at > :next_action_compare", [
                'next_action_at' => $targetAt,
                'next_action_compare' => $targetAt,
                'lead_id' => (int)$row['lead_id'],
                'cadence_step' => $completedStep,
            ]);
            $repaired += $changed;
            if ($changed > 0) {
                lead_agent_event((int)$row['lead_id'], 'active-sprint-repaired-' . (int)$row['lead_id'] . '-' . $completedStep, 'cadence_rescheduled', (string)($expected['channel'] ?? ''), 'recorded', 'six_day_active_sprint', [
                    'previous_next_action_at' => $currentAt,
                    'next_action_at' => $targetAt,
                ]);
            }
        }
        if ($repaired > 0) {
            lead_agent_sync_crm_followup_schedule();
        }
        return $repaired;
    }
}

if (!function_exists('lead_agent_lifecycle_decision')) {
    /** Pure lifecycle decision used by the cron reconciler and policy tests. */
    function lead_agent_lifecycle_decision(
        array $lead,
        array $state = [],
        int $reengagementAttempts = 0,
        ?DateTimeImmutable $now = null
    ): string {
        $status = trim((string)($lead['status'] ?? ''));
        if (in_array($status, ['no_answer', 'lost_lead', 'opted_out', 'consultation_booked', 'consult_completed', 'treatment_accepted', 'treatment_completed'], true)) {
            return '';
        }
        if (!empty($state['human_takeover'])
            || in_array(trim((string)($state['status'] ?? '')), ['human_takeover', 'ready_to_schedule', 'needs_attention', 'paused', 'opted_out'], true)
            || in_array(trim((string)($lead['follow_up_status'] ?? '')), ['ready_to_schedule', 'needs_attention'], true)
            || lead_conversion_has_future_consult($lead)
            || lead_conversion_has_scheduling_context($lead)) {
            return '';
        }

        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
        $lastInbound = lead_conversion_datetime($lead['last_inbound_at'] ?? '');
        $lastOutbound = lead_conversion_datetime($lead['last_outbound_at'] ?? '');
        if ($lastInbound !== null) {
            // Never advance while a patient message is waiting for a response.
            if ($lastOutbound === null || $lastOutbound <= $lastInbound) {
                return '';
            }
            if ($lastInbound <= $now->modify('-72 hours') && $reengagementAttempts >= 2) {
                return 'nurture';
            }
            return $lastOutbound <= $now->modify('-2 hours') ? 'active_follow_up' : '';
        }

        if ((int)($state['cadence_step'] ?? 0) >= count(lead_agent_cadence_plan())) {
            return 'nurture';
        }
        if ($lastOutbound !== null && !lead_conversion_is_first_24_hours($lead, $now)) {
            return 'active_follow_up';
        }
        return '';
    }
}

if (!function_exists('lead_agent_enter_nurture')) {
    function lead_agent_enter_nurture(int $leadId, string $reason): array
    {
        $transition = lead_lifecycle_transition_status(
            $leadId,
            'no_answer',
            'Active follow-up completed; lead moved to low-frequency Nurture.',
            'lead_agent_lifecycle',
            ['new_lead', 'attempted_contact', 'contacted', 'in_contact', '']
        );
        if (empty($transition['ok'])) {
            return $transition;
        }
        $nextActionAt = null;
        $state = db_one('SELECT * FROM lead_agent_states WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]);
        if ($state) {
            $following = lead_agent_step_schedule((string)($state['started_at'] ?? now()), max(6, (int)($state['cadence_step'] ?? 0) + 1));
            if (strtotime((string)($following['at'] ?? '')) <= time()) {
                $following['at'] = lead_agent_align_contact_time(
                    (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->modify('+30 days')
                )->format('Y-m-d H:i:s');
            }
            db_execute("UPDATE lead_agent_states
                SET status = 'nurture', next_action_at = :next_action_at, last_decision = :decision,
                    lock_token = '', locked_at = NULL, updated_at = NOW()
                WHERE lead_id = :lead_id AND human_takeover = 0", [
                'next_action_at' => $following['at'],
                'decision' => substr('entered_nurture_' . $reason, 0, 80),
                'lead_id' => $leadId,
            ]);
            $nextActionAt = (string)$following['at'];
            lead_agent_sync_crm_followup_schedule($leadId);
        }
        lead_agent_event($leadId, 'lifecycle-nurture-' . $leadId, 'lifecycle_transition', '', 'recorded', $reason);
        return $transition + ['next_action_at' => $nextActionAt];
    }
}

if (!function_exists('lead_agent_reconcile_lifecycle')) {
    /** Dry-run capable, idempotent bridge from timestamps/cadence into durable stages. */
    function lead_agent_reconcile_lifecycle(int $limit = 500, bool $dryRun = false): array
    {
        lead_agent_ensure_schema();
        $limit = max(1, min(1000, $limit));
        $rows = db_all("SELECT l.*, s.status AS agent_status, s.cadence_step, s.started_at,
                s.human_takeover, s.scheduling_phase AS agent_scheduling_phase,
                (SELECT COUNT(*) FROM lead_agent_events e
                 WHERE e.lead_id = l.id AND e.event_type = 'cadence_sent'
                   AND l.last_inbound_at IS NOT NULL AND e.created_at > l.last_inbound_at) AS reengagement_attempts
            FROM leads l
            LEFT JOIN lead_agent_states s ON s.lead_id = l.id
            WHERE l.status IN ('new_lead','attempted_contact','contacted','in_contact')
            ORDER BY COALESCE(l.last_inbound_at, l.created_at) ASC, l.id ASC
            LIMIT {$limit}");
        $result = [
            'evaluated' => count($rows),
            'dry_run' => $dryRun,
            'activated' => 0,
            'nurtured' => 0,
            'candidates' => [],
        ];
        $now = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
        foreach ($rows as $lead) {
            $state = [
                'status' => (string)($lead['agent_status'] ?? ''),
                'cadence_step' => (int)($lead['cadence_step'] ?? 0),
                'started_at' => (string)($lead['started_at'] ?? ''),
                'human_takeover' => (int)($lead['human_takeover'] ?? 0),
            ];
            $decision = lead_agent_lifecycle_decision($lead, $state, (int)($lead['reengagement_attempts'] ?? 0), $now);
            $current = trim((string)($lead['status'] ?? ''));
            if ($decision === 'active_follow_up' && $current !== 'contacted') {
                $result['activated']++;
                $result['candidates'][] = ['lead_id' => (int)$lead['id'], 'from' => $current, 'to' => 'contacted', 'reason' => 'active_follow_up'];
                if (!$dryRun) {
                    lead_lifecycle_transition_status(
                        (int)$lead['id'],
                        'contacted',
                        $current === 'new_lead'
                            ? 'The 24-hour New Lead window ended without a reply; Active Follow-Up started.'
                            : 'The answered conversation became quiet; Active Follow-Up started.',
                        'lead_agent_lifecycle',
                        ['new_lead', 'attempted_contact', 'in_contact', '']
                    );
                }
            } elseif ($decision === 'nurture') {
                $result['nurtured']++;
                $result['candidates'][] = ['lead_id' => (int)$lead['id'], 'from' => $current, 'to' => 'no_answer', 'reason' => 'active_sprint_exhausted'];
                if (!$dryRun) {
                    lead_agent_enter_nurture((int)$lead['id'], 'active_sprint_exhausted');
                }
            }
        }
        return $result;
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
        if (preg_match('/\b(?:(?:i|we|rod|our team)\s+(?:will|[’\']ll|am going to|are going to|plan to)\s+(?:give\s+you\s+a\s+)?call|expect\s+(?:my|our|a)\s+call|(?:i|we)\s+(?:just\s+)?tried\s+(?:calling|to\s+call))\b/iu', $text)) {
            $flags[] = 'unapproved_call_commitment';
        }
        if (substr_count($body, '?') > 1) {
            $flags[] = 'multiple_questions';
        }
        if (preg_match('/\b(?:i (?:have|got) you|you(?:\'re| are)) (?:scheduled|booked|confirmed)\b|\bappointment (?:is|has been) confirmed\b/i', $text)) {
            $flags[] = 'unverified_booking_claim';
        }
        if (preg_match('/^\s*(?:re|fw|fwd)\s*:/i', $body)) {
            $flags[] = 'misleading_thread_subject';
        }
        if (preg_match('/\b(?:urgent|final notice|act now|last chance|limited time)\b/i', $text)) {
            $flags[] = 'misleading_urgency';
        }
        return array_values(array_unique($flags));
    }
}

if (!function_exists('lead_agent_requested_followup_at')) {
    /**
     * Parse an explicit future-contact request without treating a normal
     * appointment preference as permission to keep messaging now.
     */
    function lead_agent_requested_followup_at(string $body, ?DateTimeImmutable $now = null): string
    {
        $timezone = new DateTimeZone(APP_TIMEZONE);
        $now = $now ?? new DateTimeImmutable('now', $timezone);
        $text = strtolower(trim(preg_replace('/\s+/', ' ', $body) ?? $body));
        if ($text === '') {
            return '';
        }

        $deferSignal = preg_match(
            '/\b(?:wait(?:ing)?(?:\s+until|\s+till|\s+for)?|hold\s+off|pause|reach\s+out|contact\s+me|check\s+back|follow\s+up|start\s+making\s+appointments?|espere?(?:\s+hasta)?|esperar(?:\s+hasta)?|paus[ae]|comun[ií]quese|cont[aá]cteme|vuelva\s+a\s+contactar)\b/iu',
            $text
        );
        $untilSignal = preg_match('/\b(?:until|till|hasta)\b/iu', $text);
        if (!$deferSignal && !$untilSignal) {
            return '';
        }

        $monthNames = [
            'january' => 1, 'enero' => 1,
            'february' => 2, 'febrero' => 2,
            'march' => 3, 'marzo' => 3,
            'april' => 4, 'abril' => 4,
            'may' => 5, 'mayo' => 5,
            'june' => 6, 'junio' => 6,
            'july' => 7, 'julio' => 7,
            'august' => 8, 'agosto' => 8,
            'september' => 9, 'septiembre' => 9, 'setiembre' => 9,
            'october' => 10, 'octubre' => 10,
            'november' => 11, 'noviembre' => 11,
            'december' => 12, 'diciembre' => 12,
        ];
        $monthPattern = implode('|', array_map(static fn(string $month): string => preg_quote($month, '/'), array_keys($monthNames)));
        if (preg_match('/\b(' . $monthPattern . ')\b(?:\s+(\d{1,2})(?:st|nd|rd|th)?)?(?:[\s,]+(20\d{2}))?/iu', $text, $matches)) {
            $month = $monthNames[strtolower((string)$matches[1])] ?? 0;
            $day = max(1, min(31, (int)($matches[2] ?? 1)));
            $year = (int)($matches[3] ?? $now->format('Y'));
            if ($month < 1) {
                return '';
            }
            $candidate = $now->setDate($year, $month, 1)->setTime(9, 0, 0);
            $maxDay = (int)$candidate->format('t');
            $candidate = $candidate->setDate($year, $month, min($day, $maxDay));
            if (!isset($matches[3]) && $candidate <= $now) {
                $candidate = $candidate->modify('+1 year');
            }
            return $candidate->format('Y-m-d H:i:s');
        }

        if (preg_match('/\b(?:next\s+month|el\s+pr[oó]ximo\s+mes)\b/iu', $text)) {
            return $now->modify('first day of next month')->setTime(9, 0, 0)->format('Y-m-d H:i:s');
        }

        return '';
    }
}

if (!function_exists('lead_agent_classify_inbound')) {
    function lead_agent_classify_inbound(string $body): string
    {
        $text = strtolower(trim(preg_replace('/\s+/', ' ', $body) ?? $body));
        if ($text === '') {
            return 'needs_attention';
        }
        if (preg_match('/^(stop|stopall|unsubscribe|cancel|end|quit|remove me|wrong number|do not text|don\'t text|cancelar|no me escriba|no me escriban|deje de escribir)\b/iu', $text)) {
            return 'opt_out';
        }
        if (lead_agent_requested_followup_at($body) !== '') {
            return 'pause';
        }
        if (preg_match('/\b(not interested|no longer interested|not right now|maybe later|please pause|no thank you|too far|farther than|cannot travel|can\'t travel|do not want|don\'t want|no me interesa|ya no me interesa|ahora no|tal vez despues|tal vez después|no gracias|muy lejos|no puedo viajar)\b/iu', $text)) {
            return 'pause';
        }
        if (preg_match('/\b(brother|sister|husband|wife|son|daughter|friend|patient)\b/i', $text)
            && preg_match('/(?:\+?1[\s.\-]?)?\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}/', $text)) {
            return 'needs_attention';
        }
        if (preg_match('/\b(cost|price|pricing|how much|payment|payments|financ(?:e|ing)|monthly|insurance|costo|precio|cuanto cuesta|cuánto cuesta|pago|pagos|financiamiento|seguro)\b|\$/iu', $text)) {
            return 'cost_redirect';
        }
        if (lead_call_consent_requested($text)
            || preg_match('/\b(complaint|upset|angry|refund|lawyer|pain|infection|swelling|emergency|diagnos|candidate|eligible|queja|enojado|reembolso|abogado|dolor|infeccion|infección|hinchazon|hinchazón|emergencia|diagnostico|diagnóstico)\b/iu', $text)) {
            return 'needs_attention';
        }
        if (preg_match('/\b(book|schedule|appointment|consult|come in|available|availability|morning|mornings|mornign|afternoon|afternoons|evening|weekday|weekend|monday|tuesday|wednesday|wednesdays|wensday|wensdays|wenesday|wenesdays|thursday|friday|saturday|tomorrow|next week|agendar|programar|cita|consulta|disponible|disponibilidad|mañana|manana|tarde|lunes|martes|miércoles|miercoles|jueves|viernes|sábado|sabado|domingo|proxima semana|próxima semana)\b/iu', $text)) {
            return 'ready_to_schedule';
        }
        return 'general';
    }
}

if (!function_exists('lead_agent_scheduling_preferences')) {
    function lead_agent_scheduling_preferences(string $body): array
    {
        $text = strtolower(trim(preg_replace('/\s+/', ' ', $body) ?? $body));
        // Preserve positive alternatives while removing only a specifically
        // rejected "next week" window from preference extraction.
        $dayText = preg_replace([
            '/\bnext\s+week\s+(?:is|will\s+be|would\s+be|looks?)?\s*(?:bad|not\s+good|impossible|unavailable|busy|does(?:n\'t|\s+not)\s+work|won(?:\'t|\s+not)\s+work|is(?:n\'t|\s+not)\s+possible)(?:\s+for\s+me)?\b/i',
            '/\b(?:i\s+)?(?:can(?:\'t|not)|won(?:\'t|not)|will\s+not|do(?:n\'t|\s+not))\s+(?:do|make|come|schedule)?\s*(?:it\s+)?next\s+week\b/i',
            '/\bnot\s+next\s+week\b/i',
        ], ' ', $text) ?? $text;
        $period = '';
        if (preg_match('/\b(morning|mornings|mornign|mañana|mañanas)\b/ui', $text)) {
            $period = 'morning';
        } elseif (preg_match('/\b(afternoon|afternoons|tarde|tardes)\b/ui', $text)) {
            $period = 'afternoon';
        } elseif (preg_match('/\b(evening|evenings|noche|noches)\b/ui', $text)) {
            $period = 'evening';
        }

        $day = '';
        $dayAliases = [
            'monday' => 'monday', 'lunes' => 'monday',
            'tuesday' => 'tuesday', 'martes' => 'tuesday',
            'wednesday' => 'wednesday', 'wednesdays' => 'wednesday', 'wensday' => 'wednesday',
            'wensdays' => 'wednesday', 'wenesday' => 'wednesday', 'wenesdays' => 'wednesday',
            'miércoles' => 'wednesday', 'miercoles' => 'wednesday',
            'thursday' => 'thursday', 'jueves' => 'thursday',
            'friday' => 'friday', 'viernes' => 'friday',
            'saturday' => 'saturday', 'sábado' => 'saturday', 'sabado' => 'saturday',
            'sunday' => 'sunday', 'domingo' => 'sunday',
            'today' => 'today', 'hoy' => 'today',
            'tomorrow' => 'tomorrow', 'next week' => 'next week',
            'following week' => 'following week', 'week after next' => 'following week',
        ];
        if (preg_match('/\b(following\s+week|week\s+after\s+next|next\s+week|monday|tuesday|wednesday|wednesdays|wensday|wensdays|wenesday|wenesdays|thursday|friday|saturday|sunday|today|tomorrow|lunes|martes|miércoles|miercoles|jueves|viernes|sábado|sabado|domingo|hoy)\b/ui', $dayText, $matches)) {
            $day = $dayAliases[strtolower((string) $matches[1])] ?? '';
        }

        $specificTime = '';
        if (preg_match('/\b(1[0-2]|0?[1-9])(?::([0-5]\d))?\s*(a\.?m\.?|p\.?m\.?)\b/i', $text, $matches)) {
            $minutes = (string) ($matches[2] ?? '');
            $specificTime = (int) $matches[1] . ':' . ($minutes !== '' ? $minutes : '00') . ' ' . strtoupper(str_replace('.', '', (string) ($matches[3] ?? '')));
        }

        return [
            'day' => $day,
            'period' => $period,
            'specific_time' => $specificTime,
            'has_preference' => $day !== '' || $period !== '' || $specificTime !== '',
            'ready_for_availability' => $day !== '' && ($period !== '' || $specificTime !== ''),
        ];
    }
}

if (!function_exists('lead_agent_scheduling_preferences_complete')) {
    function lead_agent_scheduling_preferences_complete(array $preferences): bool
    {
        return trim((string) ($preferences['day'] ?? '')) !== ''
            && (trim((string) ($preferences['period'] ?? '')) !== ''
                || trim((string) ($preferences['specific_time'] ?? '')) !== '');
    }
}

if (!function_exists('lead_agent_merge_scheduling_preferences')) {
    function lead_agent_merge_scheduling_preferences(array $older, array $newer): array
    {
        foreach (['day', 'period', 'specific_time'] as $key) {
            if (trim((string) ($newer[$key] ?? '')) === '' && trim((string) ($older[$key] ?? '')) !== '') {
                $newer[$key] = (string) $older[$key];
            }
        }
        $newer['has_preference'] = trim((string) ($newer['day'] ?? '')) !== ''
            || trim((string) ($newer['period'] ?? '')) !== ''
            || trim((string) ($newer['specific_time'] ?? '')) !== '';
        $newer['ready_for_availability'] = lead_agent_scheduling_preferences_complete($newer);
        return $newer;
    }
}

if (!function_exists('lead_agent_historical_scheduling_preferences')) {
    /** Recover the latest known day/time from the full inbound SMS history. */
    function lead_agent_historical_scheduling_preferences(int $leadId): array
    {
        $merged = lead_agent_scheduling_preferences('');
        if ($leadId <= 0) {
            return $merged;
        }
        $rows = db_all("SELECT body FROM lead_messages WHERE lead_id = :lead_id AND direction = 'inbound' ORDER BY created_at ASC, id ASC", ['lead_id' => $leadId]);
        foreach ($rows as $row) {
            $candidate = lead_agent_scheduling_preferences((string) ($row['body'] ?? ''));
            if (!empty($candidate['has_preference'])) {
                $merged = lead_agent_merge_scheduling_preferences($merged, $candidate);
            }
        }
        return $merged;
    }
}

if (!function_exists('lead_agent_decline_kind')) {
    function lead_agent_decline_kind(string $body): string
    {
        $text = strtolower(trim(preg_replace('/\s+/', ' ', $body) ?? $body));
        if (preg_match('/\b(not right now|maybe later|another time|not yet|reach out later|check back|ahora no|tal vez despues|tal vez después|otro momento|todavia no|todavía no)\b/iu', $text)) {
            return 'deferred';
        }
        if (preg_match('/\b(not interested|no longer interested|no thank you|too far|cannot travel|can\'t travel|do not want|don\'t want|no me interesa|ya no me interesa|no gracias|muy lejos|no puedo viajar|no quiero)\b/iu', $text)) {
            return 'declined';
        }
        return 'paused';
    }
}

if (!function_exists('lead_agent_operator_phone')) {
    function lead_agent_operator_phone(): string
    {
        $recipient = internal_sms_find_recipient('rod_moya');
        return internal_sms_normalize_phone((string) ($recipient['phone'] ?? ''));
    }
}

if (!function_exists('lead_agent_is_operator_sender')) {
    function lead_agent_is_operator_sender(string $phone): bool
    {
        $operator = lead_agent_operator_phone();
        return $operator !== '' && internal_sms_normalize_phone($phone) === $operator;
    }
}

if (!function_exists('lead_agent_parse_operator_datetime')) {
    function lead_agent_parse_operator_datetime(string $value, ?DateTimeImmutable $now = null): string
    {
        $timezone = new DateTimeZone(APP_TIMEZONE);
        $now = $now ?: new DateTimeImmutable('now', $timezone);
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        try {
            $candidate = new DateTimeImmutable($value, $timezone);
            if (!preg_match('/\b\d{4}\b/', $value)) {
                $candidate = $candidate->setDate((int) $now->format('Y'), (int) $candidate->format('m'), (int) $candidate->format('d'));
                if ($candidate <= $now) {
                    $candidate = $candidate->modify('+1 year');
                }
            }
        } catch (Throwable $e) {
            return '';
        }
        return $candidate > $now ? $candidate->format('Y-m-d H:i:s') : '';
    }
}

if (!function_exists('lead_agent_parse_operator_command')) {
    /** Parse Rod's deterministic SMS command. Untrusted free text never reaches an LLM. */
    function lead_agent_parse_operator_command(string $body, ?DateTimeImmutable $now = null): array
    {
        $body = trim(preg_replace('/\s+/', ' ', $body) ?? $body);
        if (preg_match('/^(help|commands?)$/i', $body)) {
            return ['action' => 'help', 'code' => '', 'options' => []];
        }
        $code = '';
        $instruction = $body;
        if (preg_match('/^(S\d+(?:-[A-Z0-9]{4,8})?)\s+(.+)$/i', $body, $matches)) {
            $code = strtoupper((string) $matches[1]);
            $instruction = trim((string) $matches[2]);
        }
        if (preg_match('/^(wait|hold)$/i', $instruction)) {
            return ['action' => 'wait', 'code' => $code, 'options' => []];
        }
        $windows = lead_agent_parse_operator_availability_windows($instruction, $now);
        if ($windows !== []) {
            return ['action' => 'availability_window', 'code' => $code, 'options' => [], 'windows' => $windows];
        }
        $parts = preg_split('/\s*(?:,|\||\bor\b|\band\b)\s*/i', $instruction) ?: [];
        if (count($parts) !== 2) {
            return ['action' => 'invalid', 'code' => $code, 'options' => []];
        }
        $option1 = lead_agent_parse_operator_datetime((string) $parts[0], $now);
        $option2 = lead_agent_parse_operator_datetime((string) $parts[1], $now);
        if ($option1 === '' || $option2 === '' || $option1 === $option2) {
            return ['action' => 'invalid', 'code' => $code, 'options' => []];
        }
        return ['action' => 'offer', 'code' => $code, 'options' => [$option1, $option2]];
    }
}

if (!function_exists('lead_agent_office_minutes')) {
    function lead_agent_office_minutes(): array
    {
        // Consultations are offered from 9:00 AM through a final 6:00 PM start.
        return ['open' => 9 * 60, 'last_start' => 18 * 60, 'close' => 18 * 60 + 30, 'slot' => 30];
    }
}

if (!function_exists('lead_agent_operator_time_range')) {
    function lead_agent_operator_time_range(string $body): array
    {
        if (!preg_match('/(?:\bfrom\s+|\bbetween\s+)?(\d{1,2})(?::([0-5]\d))?\s*(a\.?m\.?|p\.?m\.?)?\s*(?:-|\bto\b|\buntil\b|\bthrough\b)\s*(\d{1,2})(?::([0-5]\d))?\s*(a\.?m\.?|p\.?m\.?)?/i', $body, $matches)) {
            return [];
        }
        $startHour = (int) $matches[1];
        $startMinute = (int) ($matches[2] ?? 0);
        $startMeridian = strtolower(str_replace('.', '', (string) ($matches[3] ?? '')));
        $endHour = (int) $matches[4];
        $endMinute = (int) ($matches[5] ?? 0);
        $endMeridian = strtolower(str_replace('.', '', (string) ($matches[6] ?? '')));
        if ($startHour < 1 || $startHour > 12 || $endHour < 1 || $endHour > 12) {
            return [];
        }
        $office = lead_agent_office_minutes();
        $hourCandidates = static function (int $hour, string $meridian): array {
            if ($meridian !== '') {
                $normalized = $hour % 12;
                return [$normalized + ($meridian === 'pm' ? 12 : 0)];
            }
            $values = [$hour % 12, ($hour % 12) + 12];
            return array_values(array_unique($values));
        };
        $candidates = [];
        foreach ($hourCandidates($startHour, $startMeridian) as $start24) {
            foreach ($hourCandidates($endHour, $endMeridian) as $end24) {
                $start = $start24 * 60 + $startMinute;
                $end = $end24 * 60 + $endMinute;
                if ($start < $office['open'] || $start > $office['last_start'] || $end <= $start || $end > $office['close']) {
                    continue;
                }
                $duration = $end - $start;
                if ($duration < $office['slot'] || $duration > 9 * 60) {
                    continue;
                }
                $candidates[] = ['start' => $start, 'end' => $end, 'duration' => $duration];
            }
        }
        if ($candidates === []) {
            return [];
        }
        usort($candidates, static fn(array $a, array $b): int => $a['duration'] <=> $b['duration'] ?: $a['start'] <=> $b['start']);
        return ['start_minutes' => $candidates[0]['start'], 'end_minutes' => $candidates[0]['end']];
    }
}

if (!function_exists('lead_agent_parse_operator_availability_windows')) {
    /** Resolve natural availability windows in the practice timezone. */
    function lead_agent_parse_operator_availability_windows(string $body, ?DateTimeImmutable $now = null): array
    {
        $timezone = new DateTimeZone(APP_TIMEZONE);
        $now = ($now ?: new DateTimeImmutable('now', $timezone))->setTimezone($timezone);
        $body = strtolower(trim(preg_replace('/\s+/', ' ', $body) ?? $body));
        $range = lead_agent_operator_time_range($body);
        if ($range === []) {
            return [];
        }
        $weekdayMap = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
        $dates = [];
        $previousWeekdayDate = null;
        if (preg_match_all('/\b(next\s+)?(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $targetDow = $weekdayMap[strtolower((string) $match[2])];
                $daysAhead = ($targetDow - (int) $now->format('w') + 7) % 7;
                $explicitNext = trim((string) ($match[1] ?? '')) !== '';
                $windowEndToday = (int) $range['end_minutes'];
                $nowMinutes = (int) $now->format('G') * 60 + (int) $now->format('i');
                if ($daysAhead === 0 && ($explicitNext || $nowMinutes >= $windowEndToday)) {
                    $daysAhead = 7;
                }
                $date = $now->setTime(0, 0)->modify('+' . $daysAhead . ' days');
                if ($previousWeekdayDate instanceof DateTimeImmutable && $date <= $previousWeekdayDate) {
                    $date = $date->modify('+7 days');
                }
                $dates[$date->format('Y-m-d')] = $date;
                $previousWeekdayDate = $date;
            }
        }
        if (preg_match_all('/\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?\b/', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $year = isset($match[3]) && $match[3] !== '' ? (int) $match[3] : (int) $now->format('Y');
                if ($year < 100) {
                    $year += 2000;
                }
                if (!checkdate((int) $match[1], (int) $match[2], $year)) {
                    continue;
                }
                $date = $now->setDate($year, (int) $match[1], (int) $match[2])->setTime(0, 0);
                if ($date->setTime(23, 59) < $now) {
                    $date = $date->modify('+1 year');
                }
                $dates[$date->format('Y-m-d')] = $date;
            }
        }
        if ($dates === []) {
            return [];
        }
        ksort($dates);
        $windows = [];
        foreach ($dates as $date) {
            $start = $date->setTime(intdiv((int) $range['start_minutes'], 60), (int) $range['start_minutes'] % 60);
            $end = $date->setTime(intdiv((int) $range['end_minutes'], 60), (int) $range['end_minutes'] % 60);
            if ($end <= $now) {
                continue;
            }
            $windows[] = ['start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s')];
        }
        return $windows;
    }
}

if (!function_exists('lead_agent_available_slots_for_windows')) {
    function lead_agent_available_slots_for_windows(array $windows, array $occupiedIntervals = [], ?DateTimeImmutable $now = null): array
    {
        $timezone = new DateTimeZone(APP_TIMEZONE);
        $now = ($now ?: new DateTimeImmutable('now', $timezone))->setTimezone($timezone);
        $office = lead_agent_office_minutes();
        $slots = [];
        foreach ($windows as $window) {
            try {
                $cursor = new DateTimeImmutable((string) ($window['start'] ?? ''), $timezone);
                $end = new DateTimeImmutable((string) ($window['end'] ?? ''), $timezone);
            } catch (Throwable $e) {
                continue;
            }
            $minute = (int) $cursor->format('i');
            if ($minute % $office['slot'] !== 0) {
                $cursor = $cursor->modify('+' . ($office['slot'] - ($minute % $office['slot'])) . ' minutes');
                $cursor = $cursor->setTime((int) $cursor->format('G'), (int) $cursor->format('i'), 0);
            }
            while ($cursor->modify('+' . $office['slot'] . ' minutes') <= $end) {
                $slotEnd = $cursor->modify('+' . $office['slot'] . ' minutes');
                if ($cursor <= $now) {
                    $cursor = $slotEnd;
                    continue;
                }
                $blocked = false;
                foreach ($occupiedIntervals as $occupied) {
                    try {
                        $occupiedStart = new DateTimeImmutable((string) ($occupied['start'] ?? ''), $timezone);
                        $occupiedEnd = new DateTimeImmutable((string) ($occupied['end'] ?? ''), $timezone);
                    } catch (Throwable $e) {
                        continue;
                    }
                    if ($cursor < $occupiedEnd && $slotEnd > $occupiedStart) {
                        $blocked = true;
                        break;
                    }
                }
                if (!$blocked) {
                    $slots[] = $cursor->format('Y-m-d H:i:s');
                }
                $cursor = $slotEnd;
            }
        }
        return array_values(array_unique($slots));
    }
}

if (!function_exists('lead_agent_calendar_occupied_intervals')) {
    function lead_agent_calendar_occupied_intervals(array $windows): array
    {
        if ($windows === []) {
            return [];
        }
        $starts = array_column($windows, 'start');
        $ends = array_column($windows, 'end');
        sort($starts);
        rsort($ends);
        $from = (string) ($starts[0] ?? '');
        $to = (string) ($ends[0] ?? '');
        if ($from === '' || $to === '') {
            return [];
        }
        $intervals = [];
        $crmRows = db_all("SELECT consultation_date AS start_at, DATE_ADD(consultation_date, INTERVAL 30 MINUTE) AS end_at
            FROM leads
            WHERE consultation_date IS NOT NULL AND consultation_date < :to_date
              AND DATE_ADD(consultation_date, INTERVAL 30 MINUTE) > :from_date
              AND status NOT IN ('lost_lead','opted_out','no_answer')", ['from_date' => $from, 'to_date' => $to]);
        foreach ($crmRows as $row) {
            $intervals[] = ['start' => (string) ($row['start_at'] ?? ''), 'end' => (string) ($row['end_at'] ?? '')];
        }
        // Dentrix is not connected to a live availability API. Rod's Twilio
        // reply is the authoritative Dentrix-checked window. This pass only
        // prevents conflicts with appointments already saved in the CRM.
        return $intervals;
    }
}

if (!function_exists('lead_agent_choose_offer_slots')) {
    /** Prefer one option per day so the patient receives a useful, simple choice. */
    function lead_agent_choose_offer_slots(array $slots): array
    {
        sort($slots);
        $chosen = [];
        $seenDates = [];
        foreach ($slots as $slot) {
            $date = substr((string) $slot, 0, 10);
            if ($date === '' || isset($seenDates[$date])) {
                continue;
            }
            $chosen[] = (string) $slot;
            $seenDates[$date] = true;
            if (count($chosen) === 2) {
                return $chosen;
            }
        }
        foreach ($slots as $slot) {
            if (!in_array((string) $slot, $chosen, true)) {
                $chosen[] = (string) $slot;
            }
            if (count($chosen) === 2) {
                break;
            }
        }
        return $chosen;
    }
}

if (!function_exists('lead_agent_slots_for_operator_windows')) {
    function lead_agent_slots_for_operator_windows(array $windows, ?DateTimeImmutable $now = null): array
    {
        $occupied = lead_agent_calendar_occupied_intervals($windows);
        $available = lead_agent_available_slots_for_windows($windows, $occupied, $now);
        return ['available' => $available, 'chosen' => lead_agent_choose_offer_slots($available), 'occupied_count' => count($occupied)];
    }
}

if (!function_exists('lead_agent_scheduling_preference_label')) {
    function lead_agent_scheduling_preference_label(array $preferences): string
    {
        $parts = [];
        $day = trim((string) ($preferences['day'] ?? ''));
        $period = trim((string) ($preferences['period'] ?? ''));
        $specificTime = trim((string) ($preferences['specific_time'] ?? ''));
        if ($day !== '') {
            $parts[] = ucfirst($day);
        }
        if ($period !== '') {
            $parts[] = $period;
        }
        if ($specificTime !== '') {
            $parts[] = $specificTime;
        }
        return trim(implode(' ', $parts));
    }
}

if (!function_exists('lead_agent_scheduling_acknowledgment')) {
    function lead_agent_scheduling_acknowledgment(array $lead, array $preferences): string
    {
        $first = lead_agent_first_name($lead);
        $day = trim((string) ($preferences['day'] ?? ''));
        $period = trim((string) ($preferences['period'] ?? ''));
        $specificTime = trim((string) ($preferences['specific_time'] ?? ''));
        $hasDay = $day !== '';
        $hasTime = $period !== '' || $specificTime !== '';
        $name = $first !== '' ? ', ' . $first : '';
        if (lead_language_is_spanish($lead)) {
            $spanishDays = [
                'monday' => 'lunes', 'tuesday' => 'martes', 'wednesday' => 'miércoles',
                'thursday' => 'jueves', 'friday' => 'viernes', 'saturday' => 'sábado',
                'sunday' => 'domingo', 'today' => 'hoy', 'tomorrow' => 'mañana',
                'next week' => 'la próxima semana', 'following week' => 'la semana siguiente',
            ];
            $dayLabel = $spanishDays[$day] ?? $day;
            $periodLabel = ['morning' => 'la mañana', 'afternoon' => 'la tarde', 'evening' => 'la noche'][$period] ?? $period;
            if (!$hasDay && !$hasTime) {
                return 'Claro' . $name . '. Puedo revisar lo que tenemos disponible esta semana. '
                    . '¿Generalmente le funciona mejor por la mañana o por la tarde? '
                    . 'Programamos consultas desde las 9:00 AM hasta la última consulta a las 6:00 PM.';
            }
            if ($hasDay && !$hasTime) {
                return 'Tomaré ' . $dayLabel . ' como su preferencia' . $name . '. ¿Prefiere por la mañana o por la tarde? '
                    . 'Programamos consultas desde las 9:00 AM hasta la última consulta a las 6:00 PM.';
            }
            if (!$hasDay && $hasTime) {
                $timeLabel = $specificTime !== '' ? 'Alrededor de las ' . $specificTime : ucfirst($periodLabel);
                return $timeLabel . ' funciona como preferencia' . $name . '. ¿Qué día de esta semana le resulta más fácil?';
            }
            $label = trim(($dayLabel !== '' ? $dayLabel . ' ' : '') . ($specificTime !== '' ? $specificTime : $periodLabel));
            return ucfirst($label !== '' ? $label : 'Ese horario') . ' suena bien' . $name . '. '
                . 'Permítame revisar si está disponible y le responderé en breve.';
        }
        if (!$hasDay && !$hasTime) {
            return 'Absolutely' . $name . '—I can check what we have available this week. '
                . 'Do mornings or afternoons usually work better for you? '
                . 'We schedule consultations from 9:00 AM through our last consultation at 6:00 PM.';
        }
        if ($hasDay && !$hasTime) {
            return ucfirst($day) . ' works as a preference' . $name . '. Do you prefer morning or afternoon? '
                . 'We schedule consultations from 9:00 AM through our last consultation at 6:00 PM.';
        }
        if (!$hasDay && $hasTime) {
            $timeLabel = $specificTime !== '' ? 'Around ' . $specificTime : ucfirst($period);
            return $timeLabel . ' works as a preference' . $name . '. Is there a particular day this week that is easiest for you?';
        }
        $label = lead_agent_scheduling_preference_label($preferences);
        return ($label !== '' ? $label : 'That time') . ' sounds good' . $name . '. '
            . 'Let me check whether that is available, and I’ll get back to you shortly.';
    }
}

if (!function_exists('lead_agent_format_availability')) {
    function lead_agent_format_availability(string $value, string $language = 'en'): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }
        if (lead_language_normalize($language) === 'es') {
            $days = [
                'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles',
                'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado', 'Sunday' => 'domingo',
            ];
            $months = [
                'January' => 'enero', 'February' => 'febrero', 'March' => 'marzo', 'April' => 'abril',
                'May' => 'mayo', 'June' => 'junio', 'July' => 'julio', 'August' => 'agosto',
                'September' => 'septiembre', 'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre',
            ];
            return ($days[date('l', $timestamp)] ?? date('l', $timestamp)) . ', ' . date('j', $timestamp)
                . ' de ' . ($months[date('F', $timestamp)] ?? date('F', $timestamp)) . ' a las ' . date('g:i A', $timestamp);
        }
        return date('l, F j \a\t g:i A', $timestamp);
    }
}

if (!function_exists('lead_agent_availability_offer_message')) {
    function lead_agent_availability_offer_message(array $lead, string $option1, string $option2): string
    {
        $first = lead_agent_first_name($lead);
        if (lead_language_is_spanish($lead)) {
            $hello = $first !== '' ? 'Hola ' . $first . ', revisé nuestra disponibilidad. ' : 'Hola, revisé nuestra disponibilidad. ';
            return $hello . 'Podemos ofrecerle ' . lead_agent_format_availability($option1, 'es') . ' o '
                . lead_agent_format_availability($option2, 'es') . '. ¿Cuál le funciona mejor?';
        }
        $hello = $first !== '' ? 'Hi ' . $first . ', I checked our availability. ' : 'Hi, I checked our availability. ';
        return $hello . 'We can offer ' . lead_agent_format_availability($option1) . ' or '
            . lead_agent_format_availability($option2) . '. Which works better for you?';
    }
}

if (!function_exists('lead_agent_match_availability_selection')) {
    function lead_agent_match_availability_selection(string $body, string $option1, string $option2): int
    {
        $text = strtolower(trim(preg_replace('/\s+/', ' ', $body) ?? $body));
        if (preg_match('/\b(first|option\s*1|number\s*1|#1|primera|primer|opci[oó]n\s*1|n[uú]mero\s*1)\b/iu', $text)) {
            return 1;
        }
        if (preg_match('/\b(second|option\s*2|number\s*2|#2|segunda|segundo|opci[oó]n\s*2|n[uú]mero\s*2)\b/iu', $text)) {
            return 2;
        }
        $matches = [];
        foreach ([1 => $option1, 2 => $option2] as $number => $option) {
            $timestamp = strtotime($option);
            if ($timestamp === false) {
                continue;
            }
            $tokens = [
                strtolower(date('l', $timestamp)),
                strtolower(date('F j', $timestamp)),
                strtolower(date('g:i A', $timestamp)),
                strtolower(date('g A', $timestamp)),
            ];
            $spanishDays = [
                'monday' => 'lunes', 'tuesday' => 'martes', 'wednesday' => 'miércoles',
                'thursday' => 'jueves', 'friday' => 'viernes', 'saturday' => 'sábado', 'sunday' => 'domingo',
            ];
            $tokens[] = $spanishDays[strtolower(date('l', $timestamp))] ?? '';
            foreach ($tokens as $token) {
                if ($token !== '' && str_contains($text, $token)) {
                    $matches[$number] = true;
                }
            }
        }
        return count($matches) === 1 ? (int) array_key_first($matches) : 0;
    }
}

if (!function_exists('lead_agent_parse_dob')) {
    function lead_agent_parse_dob(string $body): string
    {
        $text = trim($body);
        $candidates = [];
        if (preg_match('/\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\b/', $text, $matches)) {
            $candidates[] = (string) $matches[1];
        } elseif (preg_match('/\b((?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+\d{1,2}(?:st|nd|rd|th)?[,]?\s+\d{4})\b/i', $text, $matches)) {
            $candidates[] = preg_replace('/(\d)(st|nd|rd|th)\b/i', '$1', (string) $matches[1]) ?? (string) $matches[1];
        }
        foreach ($candidates as $candidate) {
            $timestamp = strtotime($candidate);
            if ($timestamp === false) {
                continue;
            }
            $normalized = date('Y-m-d', $timestamp);
            $year = (int) date('Y', $timestamp);
            if ($year >= 1900 && $normalized <= date('Y-m-d')) {
                return $normalized;
            }
        }
        return '';
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

if (!function_exists('lead_agent_run_start')) {
    function lead_agent_run_start(bool $dryRun): array
    {
        lead_agent_ensure_schema();
        $startedMicrotime = microtime(true);
        $runKey = 'run-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $runId = db_insert(
            "INSERT INTO lead_agent_runs (run_key, mode, dry_run, status, started_at, created_at, updated_at)
             VALUES (:run_key, :mode, :dry_run, 'running', NOW(), NOW(), NOW())",
            ['run_key' => $runKey, 'mode' => lead_agent_mode(), 'dry_run' => $dryRun ? 1 : 0]
        );
        return ['id' => $runId, 'key' => $runKey, 'started_microtime' => $startedMicrotime];
    }
}

if (!function_exists('lead_agent_run_finish')) {
    function lead_agent_run_finish(array $run, string $status, int $dueCount, array $results, array $backfill = [], int $repairedCatchup = 0, string $errorMessage = ''): void
    {
        $counts = ['sent' => 0, 'skipped' => 0, 'handoff' => 0, 'error' => 0, 'would_send' => 0, 'paused' => 0];
        $safeResults = [];
        foreach ($results as $result) {
            $action = (string)($result['action'] ?? 'error');
            if (array_key_exists($action, $counts)) $counts[$action]++;
            $safeResults[] = array_intersect_key((array)$result, array_flip(['lead_id', 'action', 'reason', 'channel', 'step', 'next_action_at']));
        }
        $durationMs = max(0, (int)round((microtime(true) - (float)($run['started_microtime'] ?? microtime(true))) * 1000));
        db_execute(
            "UPDATE lead_agent_runs SET status = :status, finished_at = NOW(), due_count = :due_count,
                processed_count = :processed_count, sent_count = :sent_count, skipped_count = :skipped_count,
                handoff_count = :handoff_count, error_count = :error_count,
                backfill_enrolled_count = :backfill_enrolled_count, repaired_catchup_count = :repaired_catchup_count,
                duration_ms = :duration_ms, summary_json = :summary_json, error_message = :error_message, updated_at = NOW()
             WHERE id = :id",
            [
                'status' => substr($status, 0, 24), 'due_count' => max(0, $dueCount), 'processed_count' => count($results),
                'sent_count' => $counts['sent'], 'skipped_count' => $counts['skipped'] + $counts['paused'],
                'handoff_count' => $counts['handoff'], 'error_count' => $counts['error'],
                'backfill_enrolled_count' => (int)($backfill['enrolled'] ?? 0), 'repaired_catchup_count' => max(0, $repairedCatchup),
                'duration_ms' => $durationMs,
                'summary_json' => json_encode(['would_send' => $counts['would_send'], 'results' => $safeResults], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'error_message' => substr($errorMessage, 0, 500), 'id' => (int)($run['id'] ?? 0),
            ]
        );
    }
}

if (!function_exists('lead_agent_latest_run')) {
    function lead_agent_latest_run(bool $includeDryRun = false): ?array
    {
        lead_agent_ensure_schema();
        $row = db_one('SELECT * FROM lead_agent_runs ' . ($includeDryRun ? '' : 'WHERE dry_run = 0 ') . 'ORDER BY started_at DESC, id DESC LIMIT 1');
        if (!$row) return null;
        $row['summary'] = json_decode((string)($row['summary_json'] ?? '{}'), true) ?: [];
        return $row;
    }
}

if (!function_exists('lead_agent_run_health')) {
    function lead_agent_run_health(?array $run, ?DateTimeImmutable $now = null): array
    {
        if (!$run) return ['key' => 'unknown', 'label' => 'No run recorded', 'tone' => 'slate', 'age_minutes' => null];
        $now = $now ?: new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
        $status = (string)($run['status'] ?? 'unknown');
        $anchor = trim((string)($run['finished_at'] ?? '')) ?: (string)($run['started_at'] ?? '');
        $timestamp = $anchor !== '' ? strtotime($anchor) : false;
        $ageMinutes = $timestamp !== false ? max(0, (int)floor(($now->getTimestamp() - $timestamp) / 60)) : null;
        if ($status === 'failed') return ['key' => 'failed', 'label' => 'Last run failed', 'tone' => 'rose', 'age_minutes' => $ageMinutes];
        if ($status === 'running' && ($ageMinutes ?? 0) >= 10) return ['key' => 'stuck', 'label' => 'Run appears stuck', 'tone' => 'rose', 'age_minutes' => $ageMinutes];
        if (($ageMinutes ?? 9999) <= 30) return ['key' => 'healthy', 'label' => 'Healthy', 'tone' => 'emerald', 'age_minutes' => $ageMinutes];
        if (($ageMinutes ?? 9999) <= 60) return ['key' => 'delayed', 'label' => 'Run delayed', 'tone' => 'amber', 'age_minutes' => $ageMinutes];
        return ['key' => 'stale', 'label' => 'No recent run', 'tone' => 'rose', 'age_minutes' => $ageMinutes];
    }
}

if (!function_exists('lead_agent_sms_blocked')) {
    function lead_agent_sms_blocked(array $lead): bool
    {
        return in_array(strtolower(trim((string) ($lead['sms_opt_status'] ?? ''))), ['dnd', 'opted_out'], true)
            || !elite_phone_is_valid_us((string) ($lead['phone'] ?? ''));
    }
}

if (!function_exists('lead_agent_email_blocked')) {
    function lead_agent_email_blocked(array $lead): bool
    {
        return in_array(strtolower(trim((string) ($lead['email_opt_status'] ?? ''))), [
            'unsubscribed', 'opted_out', 'bounced', 'blocked', 'dropped', 'invalid',
        ], true)
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
        if (!in_array(trim((string) ($lead['status'] ?? '')), ['contacted', 'attempted_contact', 'in_contact'], true)) {
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
        if (trim((string) ($lead['status'] ?? '')) !== 'in_contact'
            && $lastInbound !== '' && strtotime($lastInbound) !== false && strtotime($lastInbound) >= strtotime($lastOutbound)) {
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
        lead_agent_sync_crm_followup_schedule($leadId);
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
              AND l.status IN ('contacted', 'attempted_contact', 'in_contact')
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

if (!function_exists('lead_agent_cycle_state_from_row')) {
    /** Extract the aliased agent-state fields returned by the coverage query. */
    function lead_agent_cycle_state_from_row(array $row): array
    {
        return [
            'id' => (int)($row['agent_state_id'] ?? 0),
            'status' => trim((string)($row['agent_state_status'] ?? '')),
            'cadence_step' => (int)($row['agent_cadence_step'] ?? 0),
            'started_at' => trim((string)($row['agent_started_at'] ?? '')),
            'next_action_at' => trim((string)($row['agent_next_action_at'] ?? '')),
            'last_action_at' => trim((string)($row['agent_last_action_at'] ?? '')),
            'last_decision' => trim((string)($row['agent_last_decision'] ?? '')),
            'human_takeover' => (int)($row['agent_human_takeover'] ?? 0),
            'scheduling_phase' => trim((string)($row['agent_scheduling_phase'] ?? '')),
            'pause_reason' => trim((string)($row['agent_pause_reason'] ?? '')),
        ];
    }
}

if (!function_exists('lead_agent_cycle_rows')) {
    /** Exact, consent-aware source rows for the production cycle audit. */
    function lead_agent_cycle_rows(int $limit = 1000): array
    {
        lead_agent_ensure_schema();
        lead_comm_ensure_schema();
        $limit = max(1, min(2000, $limit));
        return db_all("SELECT l.*,
                s.id AS agent_state_id,
                s.status AS agent_state_status,
                s.cadence_step AS agent_cadence_step,
                s.started_at AS agent_started_at,
                s.next_action_at AS agent_next_action_at,
                s.last_action_at AS agent_last_action_at,
                s.last_decision AS agent_last_decision,
                s.human_takeover AS agent_human_takeover,
                s.scheduling_phase AS agent_scheduling_phase,
                s.pause_reason AS agent_pause_reason,
                (EXISTS(
                    SELECT 1 FROM lead_messages delivery_message
                    WHERE delivery_message.lead_id = l.id
                      AND delivery_message.direction = 'outbound'
                      AND delivery_message.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                      AND LOWER(COALESCE(delivery_message.twilio_status, '')) IN ('failed','undelivered')
                ) OR EXISTS(
                    SELECT 1 FROM lead_activities delivery_activity
                    WHERE delivery_activity.lead_id = l.id
                      AND delivery_activity.type = 'sms_delivery_issue'
                      AND delivery_activity.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                )) AS agent_recent_sms_delivery_issue
            FROM leads l
            LEFT JOIN lead_agent_states s ON s.lead_id = l.id
            ORDER BY l.id ASC
            LIMIT {$limit}");
    }
}

if (!function_exists('lead_agent_cycle_assessment')) {
    /**
     * Pure coverage classification. It deliberately separates automation gaps,
     * human replies, protected stages, and unreachable records.
     */
    function lead_agent_cycle_assessment(
        array $lead,
        array $state = [],
        bool $recentSmsDeliveryIssue = false,
        string $closureReason = ''
    ): array {
        $status = trim((string)($lead['status'] ?? ''));
        $stateStatus = trim((string)($state['status'] ?? ''));
        $lastDecision = trim((string)($state['last_decision'] ?? ''));
        $deliveryOnlyState = $lastDecision === 'sms_delivery_failed_needs_attention';
        $base = [
            'eligible' => false,
            'covered' => false,
            'category' => 'protected',
            'reason' => '',
            'channel' => '',
        ];

        if (lead_agent_internal_or_test_record($lead)) {
            return array_merge($base, ['reason' => 'internal_or_test_record']);
        }
        if (!in_array($status, ['new_lead', 'attempted_contact', 'contacted', 'in_contact', 'no_answer'], true)) {
            return array_merge($base, ['reason' => 'protected_lifecycle_stage']);
        }
        if (trim((string)($lead['consultation_date'] ?? '')) !== ''
            || in_array(strtolower(trim((string)($lead['consultation_status'] ?? ''))), ['scheduling', 'scheduled', 'booked', 'confirmed', 'completed'], true)) {
            return array_merge($base, ['reason' => 'scheduling_or_consultation']);
        }
        if ($closureReason !== '') {
            return array_merge($base, ['reason' => 'conversation_closed']);
        }

        $lastOutbound = trim((string)($lead['last_outbound_at'] ?? ''));
        $lastInbound = trim((string)($lead['last_inbound_at'] ?? ''));
        if ($lastInbound !== '' && strtotime($lastInbound) !== false
            && ($lastOutbound === '' || strtotime($lastOutbound) === false || strtotime($lastInbound) >= strtotime($lastOutbound))) {
            return array_merge($base, ['category' => 'human_action', 'reason' => 'newer_inbound_requires_reply']);
        }

        $smsAvailable = !lead_agent_sms_blocked($lead) && !$recentSmsDeliveryIssue;
        $emailAvailable = !lead_agent_email_blocked($lead);
        if (!$smsAvailable && !$emailAvailable) {
            return array_merge($base, ['category' => 'unreachable', 'reason' => 'no_consented_delivery_channel']);
        }
        $channel = $smsAvailable && $emailAvailable ? 'sms+email' : ($smsAvailable ? 'sms' : 'email');

        if (!$deliveryOnlyState && (!empty($state['human_takeover'])
            || in_array($stateStatus, ['human_takeover', 'ready_to_schedule', 'needs_attention', 'paused', 'opted_out'], true)
            || trim((string)($state['scheduling_phase'] ?? '')) !== ''
            || in_array(trim((string)($lead['follow_up_status'] ?? '')), ['ready_to_schedule', 'needs_attention'], true))) {
            return array_merge($base, ['category' => 'human_action', 'reason' => 'human_owned_or_attention', 'channel' => $channel]);
        }

        $covered = in_array($stateStatus, ['active', 'engaged', 'nurture'], true)
            && empty($state['human_takeover'])
            && trim((string)($state['next_action_at'] ?? '')) !== '';
        if ($covered) {
            return [
                'eligible' => true,
                'covered' => true,
                'category' => 'covered',
                'reason' => 'scheduled_in_cycle',
                'channel' => $channel,
            ];
        }
        if ($lastOutbound === '' || strtotime($lastOutbound) === false) {
            if ($status === 'no_answer') {
                return [
                    'eligible' => true,
                    'covered' => false,
                    'category' => 'gap',
                    'reason' => 'legacy_nurture_without_local_touch_history',
                    'channel' => $channel,
                ];
            }
            return array_merge($base, ['category' => 'first_touch_pending', 'reason' => 'first_touch_not_recorded', 'channel' => $channel]);
        }
        return [
            'eligible' => true,
            'covered' => false,
            'category' => 'gap',
            'reason' => $deliveryOnlyState ? 'delivery_route_stalled' : 'missing_cycle_schedule',
            'channel' => $channel,
        ];
    }
}

if (!function_exists('lead_agent_cycle_coverage')) {
    /** Authenticated operations audit; no message bodies are returned. */
    function lead_agent_cycle_coverage(int $limit = 1000, bool $includeRows = true): array
    {
        $rows = lead_agent_cycle_rows($limit);
        $summary = [
            'total' => count($rows),
            'eligible' => 0,
            'covered' => 0,
            'gaps' => 0,
            'human_action' => 0,
            'first_touch_pending' => 0,
            'unreachable' => 0,
            'protected' => 0,
        ];
        $byReason = [];
        $byStage = [];
        $details = [];

        foreach ($rows as $lead) {
            $leadId = (int)($lead['id'] ?? 0);
            $state = lead_agent_cycle_state_from_row($lead);
            $smsIssue = !empty($lead['agent_recent_sms_delivery_issue']);
            $closureReason = $leadId > 0 && trim((string)($lead['last_inbound_at'] ?? '')) !== ''
                ? lead_agent_latest_inbound_closure_reason($leadId)
                : '';
            $assessment = lead_agent_cycle_assessment($lead, $state, $smsIssue, $closureReason);
            $category = (string)($assessment['category'] ?? 'protected');
            $reason = (string)($assessment['reason'] ?? 'unknown');
            $stage = trim((string)($lead['status'] ?? '')) ?: 'unknown';
            $summaryKey = $category === 'gap' ? 'gaps' : $category;
            $summary[$summaryKey] = (int)($summary[$summaryKey] ?? 0) + 1;
            if (!empty($assessment['eligible'])) {
                $summary['eligible']++;
            }
            $byReason[$reason] = (int)($byReason[$reason] ?? 0) + 1;
            $byStage[$stage] = (int)($byStage[$stage] ?? 0) + 1;
            if ($includeRows && in_array($category, ['gap', 'human_action', 'first_touch_pending', 'unreachable'], true)) {
                $details[] = [
                    'lead_id' => $leadId,
                    'full_name' => trim((string)($lead['full_name'] ?? '')),
                    'stage' => $stage,
                    'category' => $category,
                    'reason' => $reason,
                    'channel' => (string)($assessment['channel'] ?? ''),
                    'agent_status' => (string)($state['status'] ?? ''),
                    'cadence_step' => (int)($state['cadence_step'] ?? 0),
                    'next_action_at' => (string)($state['next_action_at'] ?? ''),
                    'last_decision' => (string)($state['last_decision'] ?? ''),
                    'pause_reason' => (string)($state['pause_reason'] ?? ''),
                    'scheduling_phase' => (string)($state['scheduling_phase'] ?? ''),
                    'follow_up_status' => trim((string)($lead['follow_up_status'] ?? '')),
                    'last_outbound_at' => trim((string)($lead['last_outbound_at'] ?? '')),
                    'last_inbound_at' => trim((string)($lead['last_inbound_at'] ?? '')),
                ];
            }
        }
        ksort($byReason);
        ksort($byStage);
        return [
            'ok' => true,
            'generated_at' => now(),
            'summary' => $summary,
            'repairs_24h' => [
                'cycle_enrolled' => (int)db_value("SELECT COUNT(*) FROM lead_agent_events WHERE event_type = 'cycle_enrolled' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"),
                'unreachable_closed' => (int)db_value("SELECT COUNT(*) FROM lead_agent_events WHERE event_type = 'cycle_closed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"),
            ],
            'by_reason' => $byReason,
            'by_stage' => $byStage,
            'rows' => $details,
        ];
    }
}

if (!function_exists('lead_agent_legacy_nurture_schedule')) {
    /** Deterministically spread old Nurture contacts across the next month. */
    function lead_agent_legacy_nurture_schedule(int $leadId, bool $alreadyDue = false, ?DateTimeImmutable $now = null): string
    {
        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
        $days = $alreadyDue ? 1 + ($leadId % 3) : 1 + ($leadId % 30);
        $hour = 9 + ($leadId % 8);
        $minute = ($leadId * 7) % 60;
        return $now->modify('+' . $days . ' days')->setTime($hour, $minute)->format('Y-m-d H:i:s');
    }
}

if (!function_exists('lead_agent_repair_cycle_coverage')) {
    /**
     * Enroll uncovered active/Nurture leads without sending immediately.
     * Explicit replies, scheduling, consent blocks, and human-owned threads
     * are never changed. Delivery-only failures continue by email when possible.
     */
    function lead_agent_repair_cycle_coverage(int $limit = 500, bool $dryRun = false): array
    {
        $rows = lead_agent_cycle_rows($limit);
        $result = [
            'evaluated' => count($rows),
            'dry_run' => $dryRun,
            'enrolled_active' => 0,
            'enrolled_nurture' => 0,
            'delivery_routed_to_email' => 0,
            'unreachable_moved_to_nurture' => 0,
            'candidates' => [],
        ];
        $now = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));

        foreach ($rows as $lead) {
            $leadId = (int)($lead['id'] ?? 0);
            if ($leadId <= 0) {
                continue;
            }
            $state = lead_agent_cycle_state_from_row($lead);
            $smsIssue = !empty($lead['agent_recent_sms_delivery_issue']);
            $closureReason = trim((string)($lead['last_inbound_at'] ?? '')) !== ''
                ? lead_agent_latest_inbound_closure_reason($leadId)
                : '';
            $assessment = lead_agent_cycle_assessment($lead, $state, $smsIssue, $closureReason);
            $deliveryOnlyState = in_array((string)($state['last_decision'] ?? ''), [
                'sms_delivery_failed_needs_attention',
                'sms_unreachable_email_cycle_resumed',
                'email_bounced_switch_channel',
                'unreachable_no_delivery_channel',
                'unreachable_invalid_contact',
            ], true);

            if ((string)($assessment['category'] ?? '') === 'unreachable'
                && lead_agent_confirmed_unreachable_contact($lead, $state, $smsIssue)) {
                $result['unreachable_moved_to_nurture']++;
                $result['candidates'][] = ['lead_id' => $leadId, 'action' => 'pause_unreachable', 'channel' => ''];
                if ($dryRun) {
                    continue;
                }
                lead_agent_reconcile_unreachable_contact($leadId, 'lead_agent_cycle_repair');
                continue;
            }

            if ((string)($assessment['category'] ?? '') !== 'gap') {
                continue;
            }
            $isNurture = trim((string)($lead['status'] ?? '')) === 'no_answer';
            $startedAt = trim((string)($state['started_at'] ?? ''));
            if ($startedAt === '' || strtotime($startedAt) === false) {
                $startedAt = trim((string)($lead['last_outbound_at'] ?? ''));
            }
            if ($startedAt === '' || strtotime($startedAt) === false) {
                $startedAt = now();
            }
            $alreadyDue = trim((string)($lead['follow_up_status'] ?? '')) === 'needs_follow_up';
            $nextActionAt = $isNurture
                ? lead_agent_legacy_nurture_schedule($leadId, $alreadyDue, $now)
                : lead_agent_align_contact_time($now->modify('+5 minutes'))->format('Y-m-d H:i:s');
            $agentStatus = $isNurture ? 'nurture' : 'active';
            $cadenceStep = $isNurture
                ? max(count(lead_agent_cadence_plan()), (int)($state['cadence_step'] ?? 0))
                : max(0, (int)($state['cadence_step'] ?? 0));
            $decision = $isNurture ? 'legacy_nurture_cycle_enrolled' : 'active_cycle_coverage_repaired';
            $result[$isNurture ? 'enrolled_nurture' : 'enrolled_active']++;
            if ($deliveryOnlyState && (string)($assessment['channel'] ?? '') === 'email') {
                $result['delivery_routed_to_email']++;
                $decision = 'sms_unreachable_email_cycle_resumed';
            }
            $result['candidates'][] = [
                'lead_id' => $leadId,
                'action' => $agentStatus,
                'channel' => (string)($assessment['channel'] ?? ''),
                'next_action_at' => $nextActionAt,
            ];
            if ($dryRun) {
                continue;
            }

            db_query("INSERT INTO lead_agent_states
                    (lead_id, status, cadence_step, started_at, next_action_at, last_action_at,
                     last_decision, human_takeover, human_takeover_until, pause_reason,
                     lock_token, locked_at, created_at, updated_at)
                VALUES
                    (:lead_id, :status, :cadence_step, :started_at, :next_action_at, :last_action_at,
                     :last_decision, 0, NULL, '', '', NULL, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status), cadence_step = VALUES(cadence_step),
                    next_action_at = VALUES(next_action_at),
                    human_takeover = 0, human_takeover_until = NULL,
                    pause_reason = '', last_decision = VALUES(last_decision),
                    lock_token = '', locked_at = NULL, updated_at = NOW()", [
                'lead_id' => $leadId,
                'status' => $agentStatus,
                'cadence_step' => $cadenceStep,
                'started_at' => $startedAt,
                'next_action_at' => $nextActionAt,
                'last_action_at' => trim((string)($state['last_action_at'] ?? '')) ?: $startedAt,
                'last_decision' => $decision,
            ]);
            db_execute("UPDATE leads SET follow_up_status = 'ok', next_follow_up_at = :next_action_at, updated_at = NOW() WHERE id = :id LIMIT 1", [
                'next_action_at' => $nextActionAt,
                'id' => $leadId,
            ]);
            lead_agent_event($leadId, 'cycle-enrolled-' . $leadId, 'cycle_enrolled', (string)($assessment['channel'] ?? ''), 'recorded', $decision, [
                'next_action_at' => $nextActionAt,
                'cadence_step' => $cadenceStep,
            ]);
        }

        return $result;
    }
}

if (!function_exists('lead_agent_state_is_patient_hold')) {
    function lead_agent_state_is_patient_hold(array $state): bool
    {
        return trim((string)($state['status'] ?? '')) === 'paused'
            && trim((string)($state['pause_reason'] ?? '')) === 'patient_requested_future_followup';
    }
}

if (!function_exists('lead_agent_state_has_active_patient_hold')) {
    function lead_agent_state_has_active_patient_hold(array $state, ?DateTimeImmutable $now = null): bool
    {
        if (!lead_agent_state_is_patient_hold($state)) {
            return false;
        }
        $nextActionAt = trim((string)($state['next_action_at'] ?? ''));
        if ($nextActionAt === '') {
            return false;
        }
        try {
            $holdUntil = new DateTimeImmutable($nextActionAt, new DateTimeZone(APP_TIMEZONE));
        } catch (Throwable) {
            return false;
        }
        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
        return $holdUntil > $now;
    }
}

if (!function_exists('lead_agent_hold_until')) {
    /** Silence every automated channel until the saved date, then request human review. */
    function lead_agent_hold_until(int $leadId, string $holdUntil, string $source = 'operator'): array
    {
        if ($leadId <= 0) {
            return ['ok' => false, 'message' => 'Invalid lead selected.'];
        }
        $timezone = new DateTimeZone(APP_TIMEZONE);
        try {
            $wakeAt = new DateTimeImmutable($holdUntil, $timezone);
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'Hold date is invalid.'];
        }
        $now = new DateTimeImmutable('now', $timezone);
        if ($wakeAt <= $now) {
            return ['ok' => false, 'message' => 'Hold date must be in the future.'];
        }

        lead_agent_ensure_schema();
        $lead = db_one('SELECT id, status, full_name FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$lead) {
            return ['ok' => false, 'message' => 'Lead not found.'];
        }
        $wakeAtText = $wakeAt->format('Y-m-d H:i:s');
        $startedAt = now();
        $existing = db_one('SELECT started_at FROM lead_agent_states WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]);
        if (trim((string)($existing['started_at'] ?? '')) !== '') {
            $startedAt = (string)$existing['started_at'];
        }

        db_begin();
        try {
            db_query("INSERT INTO lead_agent_states
                    (lead_id, status, cadence_step, started_at, next_action_at, last_action_at,
                     last_decision, human_takeover, human_takeover_until, pause_reason,
                     scheduling_phase, availability_option_1, availability_option_2,
                     selected_availability, scheduling_context, availability_pool_json,
                     lock_token, locked_at, created_at, updated_at)
                VALUES
                    (:lead_id, 'paused', 0, :started_at, :next_action_at, NULL,
                     'patient_requested_future_hold', 0, NULL, 'patient_requested_future_followup',
                     '', NULL, NULL, NULL, '', NULL, '', NULL, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    status = 'paused', next_action_at = VALUES(next_action_at),
                    last_decision = 'patient_requested_future_hold',
                    human_takeover = 0, human_takeover_until = NULL,
                    pause_reason = 'patient_requested_future_followup',
                    scheduling_phase = '', availability_option_1 = NULL,
                    availability_option_2 = NULL, selected_availability = NULL,
                    scheduling_context = '', availability_pool_json = NULL,
                    lock_token = '', locked_at = NULL, updated_at = NOW()", [
                'lead_id' => $leadId,
                'started_at' => $startedAt,
                'next_action_at' => $wakeAtText,
            ]);
            db_execute("UPDATE leads SET status = 'no_answer', follow_up_status = 'ok',
                    next_follow_up_at = :next_follow_up_at, updated_at = NOW()
                WHERE id = :lead_id LIMIT 1", [
                'next_follow_up_at' => $wakeAtText,
                'lead_id' => $leadId,
            ]);
            db_execute("UPDATE lead_agent_operator_requests
                SET status = 'cancelled', completed_at = NOW(), updated_at = NOW()
                WHERE lead_id = :lead_id AND status = 'pending'", ['lead_id' => $leadId]);
            db_commit();
        } catch (Throwable $e) {
            db_rollBack();
            throw $e;
        }

        $source = trim($source) !== '' ? trim($source) : 'operator';
        lead_comm_insert_activity(
            $leadId,
            'lead_agent_patient_hold',
            'Automated SMS and email follow-up paused until ' . $wakeAt->format('M j, Y \a\t g:i A') . '.',
            ['hold_until' => $wakeAtText, 'source' => $source],
            $source === 'lead_agent_inbound' ? 'Lead Agent' : 'Codex'
        );
        lead_agent_event(
            $leadId,
            'patient-hold-' . $leadId . '-' . $wakeAt->format('YmdHi'),
            'patient_hold',
            '',
            'recorded',
            'patient_requested_future_followup',
            ['hold_until' => $wakeAtText, 'source' => $source]
        );

        return [
            'ok' => true,
            'lead_id' => $leadId,
            'status' => 'paused',
            'stage' => 'no_answer',
            'hold_until' => $wakeAtText,
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
        if ($status === 'opted_out') {
            db_execute("UPDATE leads SET status = 'opted_out', next_follow_up_at = NULL, updated_at = NOW() WHERE id = :lead_id LIMIT 1", ['lead_id' => $leadId]);
        }
    }
}

if (!function_exists('lead_agent_record_human_outbound')) {
    /** Manual staff communication owns the thread only until the next day. */
    function lead_agent_record_human_outbound(int $leadId, string $channel, string $body): void
    {
        if ($leadId <= 0) {
            return;
        }
        lead_agent_ensure_schema();
        $state = db_one('SELECT * FROM lead_agent_states WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]);
        if (!$state) {
            return;
        }
        if (lead_agent_state_is_patient_hold($state)) {
            lead_agent_event(
                $leadId,
                'human-outbound-during-hold-' . $channel . '-' . $leadId . '-' . hash('sha256', $body . '|' . microtime(true)),
                'human_outbound_during_hold',
                $channel,
                'recorded',
                'patient_requested_future_followup',
                ['hold_until' => (string)($state['next_action_at'] ?? '')]
            );
            return;
        }
        $resumeAt = (new DateTimeImmutable('tomorrow 09:00', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d H:i:s');
        db_execute(
            "UPDATE lead_agent_states
             SET status = 'human_takeover', human_takeover = 1, human_takeover_until = :resume_at, next_action_at = NULL,
                 pause_reason = 'manual_staff_message_until_next_day', last_decision = 'temporary_staff_takeover',
                 lock_token = '', locked_at = NULL, updated_at = NOW()
             WHERE lead_id = :lead_id",
            ['resume_at' => $resumeAt, 'lead_id' => $leadId]
        );
        db_execute('UPDATE leads SET next_follow_up_at = :resume_at, updated_at = NOW() WHERE id = :lead_id LIMIT 1', [
            'resume_at' => $resumeAt,
            'lead_id' => $leadId,
        ]);
        lead_agent_event(
            $leadId,
            'human-outbound-' . $channel . '-' . $leadId . '-' . hash('sha256', $body . '|' . microtime(true)),
            'human_takeover',
            $channel,
            'recorded',
            'manual_staff_message_until_next_day',
            ['resume_at' => $resumeAt]
        );
    }
}

if (!function_exists('lead_agent_release_expired_human_takeovers')) {
    function lead_agent_release_expired_human_takeovers(?int $onlyLeadId = null): int
    {
        lead_agent_ensure_schema();
        $params = [];
        $leadFilter = '';
        if ($onlyLeadId !== null && $onlyLeadId > 0) {
            $leadFilter = ' AND lead_id = :lead_id';
            $params['lead_id'] = $onlyLeadId;
        }
        $rows = db_all("SELECT * FROM lead_agent_states
            WHERE status = 'human_takeover' AND human_takeover = 1
              AND pause_reason IN ('manual_staff_message', 'manual_staff_message_until_next_day')
              AND (human_takeover_until <= NOW() OR (human_takeover_until IS NULL AND DATE(updated_at) < CURDATE())){$leadFilter}", $params);
        $released = 0;
        foreach ($rows as $state) {
            $leadId = (int) ($state['lead_id'] ?? 0);
            $resume = lead_agent_align_contact_time(new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d H:i:s');
            $released += db_execute("UPDATE lead_agent_states
                SET status = 'active', human_takeover = 0, human_takeover_until = NULL,
                    pause_reason = '', next_action_at = :next_action_at,
                    last_decision = 'temporary_staff_takeover_expired', updated_at = NOW()
                WHERE lead_id = :lead_id AND status = 'human_takeover' AND human_takeover = 1", [
                'next_action_at' => $resume,
                'lead_id' => $leadId,
            ]);
            db_execute('UPDATE leads SET next_follow_up_at = :next_action_at, updated_at = NOW() WHERE id = :lead_id LIMIT 1', [
                'next_action_at' => $resume,
                'lead_id' => $leadId,
            ]);
            lead_agent_event($leadId, 'temporary-takeover-released-' . $leadId . '-' . date('Ymd'), 'resumed', '', 'recorded', 'temporary_staff_takeover_expired');
        }
        return $released;
    }
}

if (!function_exists('lead_agent_release_due_patient_holds')) {
    /** Wake a dated hold into human review; never send automatically at expiry. */
    function lead_agent_release_due_patient_holds(?int $onlyLeadId = null): int
    {
        lead_agent_ensure_schema();
        $params = [];
        $leadFilter = '';
        if ($onlyLeadId !== null && $onlyLeadId > 0) {
            $leadFilter = ' AND lead_id = :lead_id';
            $params['lead_id'] = $onlyLeadId;
        }
        $rows = db_all("SELECT lead_id, next_action_at FROM lead_agent_states
            WHERE status = 'paused'
              AND pause_reason = 'patient_requested_future_followup'
              AND next_action_at IS NOT NULL
              AND next_action_at <= NOW(){$leadFilter}", $params);
        $released = 0;
        foreach ($rows as $state) {
            $leadId = (int)($state['lead_id'] ?? 0);
            if ($leadId <= 0) {
                continue;
            }
            $changed = db_execute("UPDATE lead_agent_states
                SET status = 'needs_attention', human_takeover = 1,
                    human_takeover_until = NULL, next_action_at = NULL,
                    pause_reason = 'patient_requested_future_followup_due',
                    last_decision = 'patient_requested_future_hold_due',
                    lock_token = '', locked_at = NULL, updated_at = NOW()
                WHERE lead_id = :lead_id
                  AND status = 'paused'
                  AND pause_reason = 'patient_requested_future_followup'
                  AND next_action_at <= NOW()", ['lead_id' => $leadId]);
            if (!$changed) {
                continue;
            }
            $released++;
            db_execute("UPDATE leads SET follow_up_status = 'needs_follow_up',
                    next_follow_up_at = NULL, updated_at = NOW()
                WHERE id = :lead_id LIMIT 1", ['lead_id' => $leadId]);
            lead_agent_event(
                $leadId,
                'patient-hold-due-' . $leadId . '-' . date('Ymd'),
                'patient_hold_due',
                '',
                'recorded',
                'patient_requested_future_followup_due',
                ['held_until' => (string)($state['next_action_at'] ?? '')]
            );
            lead_comm_insert_activity(
                $leadId,
                'lead_agent_patient_hold_due',
                'The patient-requested hold has ended. Review the conversation before contacting the lead.',
                ['held_until' => (string)($state['next_action_at'] ?? '')],
                'Lead Agent'
            );
        }
        return $released;
    }
}

if (!function_exists('lead_agent_sync_crm_followup_schedule')) {
    /** Keep the lead list and worker on one authoritative follow-up schedule. */
    function lead_agent_sync_crm_followup_schedule(?int $onlyLeadId = null): int
    {
        lead_agent_ensure_schema();
        $params = [];
        $where = '';
        if ($onlyLeadId !== null && $onlyLeadId > 0) {
            $where = ' WHERE s.lead_id = :lead_id';
            $params['lead_id'] = $onlyLeadId;
        }
        $rows = db_all("SELECT s.lead_id, s.status, s.human_takeover, s.human_takeover_until, s.next_action_at, s.pause_reason
            FROM lead_agent_states s{$where}", $params);
        $updated = 0;
        foreach ($rows as $state) {
            $status = (string) ($state['status'] ?? '');
            $temporaryTakeover = $status === 'human_takeover'
                && !empty($state['human_takeover'])
                && trim((string) ($state['human_takeover_until'] ?? '')) !== '';
            $nextAt = null;
            if (in_array($status, ['active', 'engaged', 'nurture'], true) && empty($state['human_takeover'])) {
                $nextAt = trim((string) ($state['next_action_at'] ?? '')) ?: null;
            } elseif ($status === 'paused'
                && trim((string)($state['pause_reason'] ?? '')) === 'patient_requested_future_followup') {
                $nextAt = trim((string)($state['next_action_at'] ?? '')) ?: null;
            } elseif ($temporaryTakeover) {
                $nextAt = trim((string) ($state['human_takeover_until'] ?? '')) ?: null;
            }
            $updated += db_execute('UPDATE leads SET next_follow_up_at = :next_action_at, updated_at = NOW()
                WHERE id = :lead_id AND NOT (next_follow_up_at <=> :next_action_compare) LIMIT 1', [
                'next_action_at' => $nextAt,
                'next_action_compare' => $nextAt,
                'lead_id' => (int) ($state['lead_id'] ?? 0),
            ]);
        }
        return $updated;
    }
}

if (!function_exists('lead_agent_operator_request')) {
    function lead_agent_operator_request(int $leadId, string $requestType = 'availability', array $context = []): array
    {
        lead_agent_ensure_schema();
        $existing = db_one("SELECT * FROM lead_agent_operator_requests
            WHERE lead_id = :lead_id AND request_type = :request_type AND status = 'pending'
              AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY id DESC LIMIT 1", ['lead_id' => $leadId, 'request_type' => $requestType]);
        if ($existing) {
            db_execute('UPDATE lead_agent_operator_requests SET context_json = :context_json, expires_at = DATE_ADD(NOW(), INTERVAL 2 DAY), updated_at = NOW() WHERE id = :id', [
                'context_json' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'id' => (int) $existing['id'],
            ]);
            return $existing;
        }
        $code = 'S' . $leadId . '-' . strtoupper(substr(hash('sha256', $leadId . '|' . microtime(true) . '|' . random_int(1000, 9999)), 0, 6));
        $id = db_insert("INSERT INTO lead_agent_operator_requests
            (request_code, lead_id, request_type, status, context_json, expires_at, created_at, updated_at)
            VALUES (:request_code, :lead_id, :request_type, 'pending', :context_json, DATE_ADD(NOW(), INTERVAL 2 DAY), NOW(), NOW())", [
                'request_code' => $code,
                'lead_id' => $leadId,
                'request_type' => $requestType,
                'context_json' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        return ['id' => $id, 'request_code' => $code, 'lead_id' => $leadId, 'request_type' => $requestType, 'status' => 'pending'];
    }
}

if (!function_exists('lead_agent_handle_operator_sms')) {
    function lead_agent_handle_operator_sms(string $from, string $body, string $messageSid = ''): array
    {
        if (!lead_agent_is_operator_sender($from)) {
            return ['handled' => false, 'reply' => ''];
        }
        lead_agent_ensure_schema();
        if ($messageSid !== '') {
            $reserved = lead_agent_event(0, 'operator-sms-' . $messageSid, 'operator_sms_received', 'sms', 'processing', 'authorized_operator_command');
            if (!$reserved) {
                return ['handled' => true, 'duplicate' => true, 'reply' => 'Elite AI: That instruction was already processed.'];
            }
        }
        $command = lead_agent_parse_operator_command($body);
        if (($command['action'] ?? '') === 'help') {
            return ['handled' => true, 'reply' => 'Elite AI commands: reply with a window such as S123-ABCDEF next Monday and Tuesday from 2 to 5, or two exact times. You can also reply CODE WAIT.'];
        }
        $code = (string) ($command['code'] ?? '');
        $request = $code !== '' ? db_one("SELECT * FROM lead_agent_operator_requests WHERE request_code = :code AND status = 'pending' AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1", ['code' => $code]) : null;
        if (!$request && $code === '') {
            $pendingRequests = db_all("SELECT * FROM lead_agent_operator_requests WHERE status = 'pending' AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY id DESC LIMIT 3");
            if (count($pendingRequests) === 1) {
                $request = $pendingRequests[0];
                $code = (string) ($request['request_code'] ?? '');
            } elseif (count($pendingRequests) > 1) {
                return ['handled' => true, 'reply' => 'Elite AI: More than one lead is waiting for availability, so I did not send anything. Please include the request code from my message.'];
            }
        }
        if (!$request) {
            return ['handled' => true, 'reply' => 'Elite AI: I could not match that instruction to an active request. Reply HELP for the format or open the CRM.'];
        }
        $leadId = (int) ($request['lead_id'] ?? 0);
        $action = (string) ($command['action'] ?? 'invalid');
        if ($action === 'availability_window') {
            $windows = (array) ($command['windows'] ?? []);
            $slotResult = lead_agent_slots_for_operator_windows($windows);
            $available = (array) ($slotResult['available'] ?? []);
            $chosen = (array) ($slotResult['chosen'] ?? []);
            if (count($chosen) < 2) {
                $count = count($available);
                return ['handled' => true, 'reply' => 'Elite AI: I used the Dentrix-confirmed window you provided and checked CRM conflicts. I found ' . $count . ' open 30-minute slot' . ($count === 1 ? '' : 's') . '. I did not send anything because I need at least two choices. Please send another window.'];
            }
            $result = lead_agent_offer_availability($leadId, (string) $chosen[0], (string) $chosen[1], 0, $available);
            if (empty($result['ok'])) {
                return ['handled' => true, 'reply' => 'Elite AI: I did not send anything. ' . (string) ($result['message'] ?? 'Please check the CRM.')];
            }
            $context = json_decode((string) ($request['context_json'] ?? ''), true);
            $context = is_array($context) ? $context : [];
            $context['operator_windows'] = $windows;
            $context['available_slots'] = $available;
            $context['offered_slots'] = $chosen;
            db_execute("UPDATE lead_agent_operator_requests SET status = 'completed', context_json = :context_json, response_body = :body, response_message_sid = :sid, completed_at = NOW(), updated_at = NOW() WHERE id = :id AND status = 'pending'", [
                'context_json' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'body' => substr($body, 0, 500), 'sid' => $messageSid !== '' ? $messageSid : null, 'id' => (int) $request['id'],
            ]);
            return ['handled' => true, 'reply' => 'Elite AI: I used your Dentrix-confirmed window, checked CRM conflicts, found ' . count($available) . ' open 30-minute slots, and offered ' . lead_agent_format_availability((string) $chosen[0]) . ' and ' . lead_agent_format_availability((string) $chosen[1]) . '.'];
        }
        if ($action === 'offer') {
            $options = (array) ($command['options'] ?? []);
            $optionWindows = [];
            foreach ($options as $option) {
                $timestamp = strtotime((string) $option);
                if ($timestamp !== false) {
                    $optionWindows[] = ['start' => date('Y-m-d H:i:s', $timestamp), 'end' => date('Y-m-d H:i:s', $timestamp + 1800)];
                }
            }
            $checked = lead_agent_slots_for_operator_windows($optionWindows);
            if (count((array) ($checked['available'] ?? [])) !== 2) {
                return ['handled' => true, 'reply' => 'Elite AI: I treated the times you provided as Dentrix-confirmed, but at least one conflicts with an appointment already saved in CRM. I did not send anything. Please choose two other times or send a wider window.'];
            }
            $result = lead_agent_offer_availability($leadId, (string) ($options[0] ?? ''), (string) ($options[1] ?? ''), 0, $options);
            if (empty($result['ok'])) {
                return ['handled' => true, 'reply' => 'Elite AI: I did not send anything. ' . (string) ($result['message'] ?? 'Please check the CRM.')];
            }
            db_execute("UPDATE lead_agent_operator_requests SET status = 'completed', response_body = :body, response_message_sid = :sid, completed_at = NOW(), updated_at = NOW() WHERE id = :id AND status = 'pending'", [
                'body' => substr($body, 0, 500), 'sid' => $messageSid !== '' ? $messageSid : null, 'id' => (int) $request['id'],
            ]);
            return ['handled' => true, 'reply' => 'Elite AI: Done. I offered both times to the lead and will continue the scheduling conversation.'];
        }
        if ($action === 'wait') {
            db_execute("UPDATE lead_agent_operator_requests SET expires_at = DATE_ADD(NOW(), INTERVAL 2 DAY), response_body = :body, response_message_sid = :sid, updated_at = NOW() WHERE id = :id", [
                'body' => substr($body, 0, 500), 'sid' => $messageSid !== '' ? $messageSid : null, 'id' => (int) $request['id'],
            ]);
            return ['handled' => true, 'reply' => 'Elite AI: Understood. I will keep the patient conversation paused while you check availability.'];
        }
        return ['handled' => true, 'reply' => 'Elite AI: I did not send anything. Reply ' . $code . ' with a window such as next Monday and Tuesday from 2 to 5, two exact times, or WAIT.'];
    }
}

if (!function_exists('lead_agent_internal_handoff')) {
    function lead_agent_internal_handoff(array $lead, string $kind, string $reason, array $context = []): array
    {
        $leadId = (int) ($lead['id'] ?? 0);
        $status = $kind === 'ready_to_schedule' ? 'ready_to_schedule' : 'needs_attention';
        lead_agent_pause($leadId, $reason, $status);
        db_execute('UPDATE lead_agent_states SET human_takeover = 1, human_takeover_until = NULL, next_action_at = NULL, updated_at = NOW() WHERE lead_id = :lead_id', [
            'lead_id' => $leadId,
        ]);

        if (function_exists('leads_has_column') && leads_has_column('follow_up_status')) {
            db_execute('UPDATE leads SET follow_up_status = :status, next_follow_up_at = NULL, updated_at = NOW() WHERE id = :id LIMIT 1', [
                'status' => $status === 'ready_to_schedule' ? 'ready_to_schedule' : 'needs_follow_up',
                'id' => $leadId,
            ]);
        }

        $leadName = trim((string) ($lead['full_name'] ?? '')) ?: 'This lead';
        $preference = trim((string) ($context['preference'] ?? ''));
        $selectedOption = trim((string) ($context['selected_option'] ?? ''));
        $handoffStage = trim((string) ($context['stage'] ?? 'availability'));
        $operatorRequest = null;
        if ($status === 'ready_to_schedule' && $handoffStage !== 'confirmation') {
            $operatorRequest = lead_agent_operator_request($leadId, 'availability', [
                'preference' => $preference,
                'lead_name' => $leadName,
            ]);
        }
        if ($status === 'ready_to_schedule' && $handoffStage === 'confirmation') {
            $operatorMessage = $leadName . ' selected ' . ($selectedOption !== '' ? $selectedOption : 'an appointment option')
                . '. DOB is on file. Please confirm the appointment in the CRM.';
        } elseif ($status === 'ready_to_schedule') {
            $requestCode = trim((string) ($operatorRequest['request_code'] ?? ''));
            $operatorMessage = $leadName . ($preference !== '' ? ' prefers ' . $preference : ' is ready to schedule')
                . '. Check Dentrix, then reply with a confirmed window, for example: ' . $requestCode . ' next Monday and Tuesday from 2 to 5. I will check CRM conflicts and offer the best two open 30-minute slots.';
        } elseif ($handoffStage === 'call_requested') {
            $operatorMessage = $leadName . ' asked for a phone call. Please call this lead today; the agent will resume tomorrow if no appointment is completed.';
        } elseif ($handoffStage === 'third_party_referral') {
            $operatorMessage = $leadName . ' provided contact information for another patient. Please create or link the correct patient record before outreach.';
        } else {
            $operatorMessage = 'Lead Agent needs help deciding the next response for ' . $leadName . ' and has paused.';
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
                    'type' => 'handoff',
                    'message' => $operatorMessage,
                    'notification_id' => 'lead-agent-' . $status . '-' . $leadId . '-' . time(),
                ]);
            }
        } catch (Throwable $e) {
            esm_log('lead_agent', 'Elite AI handoff push failed.', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
        }

        $internal = ['ok' => false, 'message' => 'Rod recipient is unavailable.'];
        $recipient = internal_sms_find_recipient('rod_moya');
        if ($recipient && !empty($recipient['enabled'])) {
            $leadUrl = base_url('leads.php?lead_id=' . $leadId);
            $internal = internal_sms_send(
                $recipient,
                'Elite AI: ' . $operatorMessage . ' Open ' . $leadName . ': ' . $leadUrl,
                0
            );
        }

        // Twilio is the primary operator channel. Pushover is intentionally a
        // quiet fallback and must not duplicate a successful Twilio handoff.
        $pushoverFallbackSent = false;
        if (empty($internal['ok']) && function_exists('elite_send_pushover_notification')) {
            $pushoverFallbackSent = elite_send_pushover_notification(
                'Elite AI handoff — SMS failed',
                $operatorMessage,
                base_url('leads.php?lead_id=' . $leadId),
                'Open ' . $leadName
            );
        }

        db_execute('UPDATE lead_agent_states SET handoff_notified_at = NOW(), last_decision = :decision, updated_at = NOW() WHERE lead_id = :lead_id', [
            'decision' => $status,
            'lead_id' => $leadId,
        ]);
        lead_agent_event($leadId, 'handoff-' . $status . '-' . $leadId . '-' . time(), 'handoff', '', 'recorded', $reason, [
            'elite_ai_push_sent' => !empty($push['sent']),
            'internal_sms_sent' => !empty($internal['ok']),
            'pushover_fallback_sent' => $pushoverFallbackSent,
            'stage' => $handoffStage,
            'preference' => $preference,
            'selected_option' => $selectedOption,
            'operator_request_code' => (string) ($operatorRequest['request_code'] ?? ''),
        ]);
        lead_comm_insert_activity($leadId, 'lead_agent_handoff', $status === 'ready_to_schedule'
            ? 'Lead Agent paused and handed this lead to Rod for scheduling.'
            : 'Lead Agent paused and requested human review.', [
                'kind' => $status,
                'reason' => $reason,
                'elite_ai_push_sent' => !empty($push['sent']),
                'internal_sms_sent' => !empty($internal['ok']),
                'pushover_fallback_sent' => $pushoverFallbackSent,
                'stage' => $handoffStage,
                'preference' => $preference,
                'selected_option' => $selectedOption,
                'operator_request_code' => (string) ($operatorRequest['request_code'] ?? ''),
            ], 'Lead Agent');

        return ['ok' => true, 'status' => $status, 'push' => $push, 'internal_sms' => $internal, 'pushover_fallback_sent' => $pushoverFallbackSent];
    }
}

if (!function_exists('lead_agent_mark_sms_delivery_attention')) {
    /**
     * A failed/undelivered SMS cannot be repaired by another automated text.
     * Keep the failure in the audit trail, route the cycle to email when one is
     * consented, and quietly park unreachable leads in Nurture. Provider
     * failures are not operator work and must not create a red halo.
     */
    function lead_agent_mark_sms_delivery_attention(
        int $leadId,
        string $status,
        string $errorCode = '',
        string $errorMessage = '',
        array $context = []
    ): array {
        if ($leadId <= 0) {
            return ['ok' => false, 'attention' => false, 'message' => 'Lead not found.'];
        }

        lead_agent_ensure_schema();
        lead_comm_ensure_schema();
        $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$lead) {
            return ['ok' => false, 'attention' => false, 'message' => 'Lead not found.'];
        }

        $status = strtolower(trim($status)) ?: 'failed';
        $errorCode = trim($errorCode);
        $errorMessage = trim($errorMessage);
        $source = trim((string)($context['source'] ?? 'sms_delivery')) ?: 'sms_delivery';
        $eventKey = trim((string)($context['event_key'] ?? ''));
        if ($eventKey === '') {
            $eventKey = 'sms-delivery-attention-' . $leadId . '-' . hash('sha256', $status . '|' . $errorCode . '|' . $errorMessage . '|' . $source);
        }
        $eventKey = substr(preg_replace('/[^a-zA-Z0-9_.:\-]/', '-', $eventKey) ?: '', 0, 190);

        $statusLabel = $status === 'invalid_number' ? 'invalid phone number' : $status;
        $reason = 'SMS delivery failed (' . $statusLabel
            . ($errorCode !== '' ? ', ' . $errorCode : '')
            . '). Automatic SMS retry is blocked.';
        $reason = mb_substr($reason, 0, 190);
        $startedAt = trim((string)($lead['created_at'] ?? ''));
        if ($startedAt === '' || strtotime($startedAt) === false) {
            $startedAt = now();
        }
        $emailAvailable = !lead_agent_email_blocked($lead);
        $existing = db_one('SELECT * FROM lead_agent_states WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]) ?: [];
        $existingStatus = trim((string)($existing['status'] ?? ''));
        $leadStatus = trim((string)($lead['status'] ?? ''));
        $lastInboundAt = trim((string)($lead['last_inbound_at'] ?? ''));
        $lastOutboundAt = trim((string)($lead['last_outbound_at'] ?? ''));
        $newerInbound = $lastInboundAt !== '' && strtotime($lastInboundAt) !== false
            && ($lastOutboundAt === '' || strtotime($lastOutboundAt) === false || strtotime($lastInboundAt) >= strtotime($lastOutboundAt));
        $protectedLeadStage = in_array($leadStatus, ['opted_out', 'lost_lead', 'consultation_booked', 'consult_completed', 'treatment_accepted', 'treatment_completed'], true)
            || trim((string)($lead['consultation_date'] ?? '')) !== ''
            || in_array(strtolower(trim((string)($lead['consultation_status'] ?? ''))), ['scheduling', 'scheduled', 'booked', 'confirmed', 'completed'], true);
        $preserveHumanState = (string)($existing['last_decision'] ?? '') !== 'sms_delivery_failed_needs_attention'
            && ($newerInbound
                || $protectedLeadStage
                || !empty($existing['human_takeover'])
                || in_array($existingStatus, ['human_takeover', 'ready_to_schedule', 'needs_attention', 'paused', 'opted_out'], true)
                || trim((string)($existing['scheduling_phase'] ?? '')) !== '');
        $cycleStatus = $existingStatus === 'nurture' || $leadStatus === 'no_answer'
            ? 'nurture'
            : 'active';
        $cadenceStep = $cycleStatus === 'nurture'
            ? max(count(lead_agent_cadence_plan()), (int)($existing['cadence_step'] ?? 0))
            : max(0, (int)($existing['cadence_step'] ?? 0));
        $nextActionAt = trim((string)($existing['next_action_at'] ?? ''));
        if ($emailAvailable && ($nextActionAt === '' || strtotime($nextActionAt) === false)) {
            $nextActionAt = $cycleStatus === 'nurture'
                ? lead_agent_legacy_nurture_schedule($leadId)
                : lead_agent_align_contact_time((new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->modify('+30 minutes'))->format('Y-m-d H:i:s');
        }
        $decision = $emailAvailable ? 'sms_unreachable_email_cycle_resumed' : 'unreachable_no_delivery_channel';
        $pauseReason = $emailAvailable
            ? $reason . ' The Lead Agent will continue through email.'
            : $reason . ' No consented email channel remains.';

        if (!$preserveHumanState) {
            db_query(
            "INSERT INTO lead_agent_states
                (lead_id, status, cadence_step, started_at, next_action_at, last_action_at, last_decision,
                 human_takeover, human_takeover_until, pause_reason, lock_token, locked_at, created_at, updated_at)
             VALUES
                (:lead_id, :status, :cadence_step, :started_at, :next_action_at, NULL, :last_decision,
                 0, NULL, :pause_reason, '', NULL, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                status = VALUES(status), cadence_step = VALUES(cadence_step),
                human_takeover = 0, human_takeover_until = NULL,
                next_action_at = VALUES(next_action_at), last_decision = VALUES(last_decision),
                pause_reason = VALUES(pause_reason), lock_token = '', locked_at = NULL, updated_at = NOW()",
            [
                'lead_id' => $leadId,
                'status' => $emailAvailable ? $cycleStatus : 'paused',
                'cadence_step' => $cadenceStep,
                'started_at' => $startedAt,
                'next_action_at' => $emailAvailable ? $nextActionAt : null,
                'last_decision' => $decision,
                'pause_reason' => mb_substr($pauseReason, 0, 190),
            ]
            );

            if (function_exists('leads_has_column') && leads_has_column('follow_up_status')) {
                $sets = [$emailAvailable ? "follow_up_status = 'ok'" : "follow_up_status = 'unreachable'", 'updated_at = NOW()'];
                if (leads_has_column('next_follow_up_at')) {
                    $sets[] = $emailAvailable ? 'next_follow_up_at = :next_follow_up_at' : 'next_follow_up_at = NULL';
                }
                $leadParams = ['id' => $leadId];
                if ($emailAvailable && leads_has_column('next_follow_up_at')) {
                    $leadParams['next_follow_up_at'] = $nextActionAt;
                }
                db_execute('UPDATE leads SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1', $leadParams);
            }
            if (!$emailAvailable) {
                lead_lifecycle_transition_status(
                    $leadId,
                    'no_answer',
                    'No deliverable contact channel remains; lead moved to Nurture without another send.',
                    'sms_delivery_failure',
                    ['new_lead', 'attempted_contact', 'contacted', 'in_contact', 'no_answer', '']
                );
                lead_agent_reconcile_unreachable_contact($leadId, 'sms_delivery_failure');
            }
        }

        $alreadyRecorded = false;
        try {
            $alreadyRecorded = (int)db_value(
                "SELECT COUNT(*) FROM lead_activities
                 WHERE lead_id = :lead_id AND type = 'sms_delivery_issue' AND meta_json LIKE :event_key",
                [
                    'lead_id' => $leadId,
                    'event_key' => '%\"event_key\":\"' . $eventKey . '\"%',
                ]
            ) > 0;
        } catch (Throwable $e) {
            $alreadyRecorded = false;
        }

        if (!$alreadyRecorded) {
            $activityBody = 'SMS delivery failed: ' . $statusLabel
                . ($errorCode !== '' ? ' (' . $errorCode . ')' : '')
                . ($preserveHumanState
                    ? '. The existing human-owned conversation state was preserved.'
                    : ($emailAvailable
                    ? '. Automatic SMS retry is blocked; the Lead Agent will continue through email.'
                    : '. No deliverable channel remains; the lead was moved to Nurture without another send.'));
            lead_comm_insert_activity($leadId, 'sms_delivery_issue', $activityBody, [
                'event_key' => $eventKey,
                'status' => $status,
                'error_code' => $errorCode,
                'error_message' => mb_substr($errorMessage, 0, 500),
                'source' => $source,
                'twilio_sid' => substr(trim((string)($context['twilio_sid'] ?? '')), 0, 120),
                'automatic_retry' => 'sms_blocked',
                'resolution' => $preserveHumanState ? 'preserve_human_owned_state' : ($emailAvailable ? 'continue_by_email' : 'nurture_unreachable'),
            ], 'Twilio');
        }

        return [
            'ok' => true,
            'attention' => false,
            'reason' => $reason,
            'event_key' => $eventKey,
            'route' => $preserveHumanState ? 'human_owned_audit_only' : ($emailAvailable ? 'email' : 'nurture_unreachable'),
        ];
    }
}

if (!function_exists('lead_agent_sms_send')) {
    function lead_agent_sms_send(array $lead, string $body, string $eventKey): array
    {
        $leadId = (int) ($lead['id'] ?? 0);
        $body = lead_language_maybe_add_sms_offer($lead, $body);
        $flags = lead_agent_policy_flags($body);
        if ($flags !== []) {
            return ['ok' => false, 'message' => 'Policy blocked SMS.', 'policy_flags' => $flags];
        }
        $result = elite_twilio_send_sms((string) ($lead['phone'] ?? ''), $body, [
            'lead_id' => $leadId,
            'lead' => $lead,
            'send_pushover_fallback' => false,
            'fallback_summary' => 'Lead Agent SMS could not be delivered. Open the CRM to review.',
            'original_body' => $body,
        ]);
        if (empty($result['ok'])) {
            $attention = lead_agent_mark_sms_delivery_attention(
                $leadId,
                'failed',
                (string)($result['twilio_code'] ?? ''),
                (string)($result['message'] ?? 'Lead Agent SMS failed.'),
                [
                    'event_key' => $eventKey . '-sms-failed',
                    'source' => 'lead_agent_sms',
                ]
            );
            return $result + ['requires_attention' => !empty($attention['attention'])];
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
        return [
            'ok' => true,
            'message_id' => $messageId,
            'provider_id' => (string) ($result['twilio_sid'] ?? ''),
            'delivery_status' => (string) ($result['twilio_status'] ?? 'accepted'),
            'body' => $sentBody,
        ];
    }
}

if (!function_exists('lead_agent_email_send')) {
    function lead_agent_email_send(array $lead, string $subject, string $body, string $eventKey): array
    {
        if (function_exists('lead_email_automation_authentication_status')) {
            $authentication = lead_email_automation_authentication_status();
            if (empty($authentication['ready'])) {
                return ['ok' => false, 'message' => 'Automated email paused until sender SPF is valid.', 'authentication' => $authentication];
            }
        }
        $body = lead_language_maybe_add_email_offer($lead, $body);
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
        if (!empty($result['ok'])) {
            $result['delivery_status'] = 'accepted';
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

if (!function_exists('lead_agent_draft_conversion_meta')) {
    function lead_agent_draft_conversion_meta(array $draft, array $memory = []): array
    {
        $strategy = trim((string) ($draft['strategy_key'] ?? $memory['strategy_key'] ?? ''));
        if (!array_key_exists($strategy, lead_conversion_strategy_labels())) {
            $strategy = 'consultation_value';
        }
        return [
            'strategy_key' => $strategy,
            'strategy_reason' => mb_substr(trim((string) ($draft['strategy_reason'] ?? $memory['strategy_reason'] ?? 'Safe next-best action selected from the complete conversation.')), 0, 500),
            'decision_confidence' => max(0.0, min(1.0, (float) ($draft['confidence'] ?? $memory['confidence'] ?? 0.5))),
        ];
    }
}

if (!function_exists('lead_agent_approved_followup')) {
    function lead_agent_approved_followup(array $lead, string $channel, int $step): array
    {
        $first = lead_agent_first_name($lead);
        $hello = $first !== '' ? 'Hi ' . $first . ',' : 'Hi,';
        if (lead_language_is_spanish($lead)) {
            $hola = $first !== '' ? 'Hola ' . $first . ',' : 'Hola,';
            $spanishSms = [
                1 => $hola . ' quería asegurarme de que recibió la información que solicitó. ¿Qué le gustaría mejorar más de su sonrisa: color, forma, espacios u otra cosa?',
                2 => $hola . ' una pregunta rápida para poder ayudarle mejor: ¿qué cambio en su sonrisa sería el más importante para usted?',
                4 => $hola . ' quería mantener abierta la conversación. ¿Hay alguna pregunta que pueda responderle sobre sus opciones para la sonrisa?',
                5 => $hola . ' retomando nuestra conversación hoy. ¿Qué pregunta sería más útil responder sobre sus opciones para la sonrisa?',
                7 => $hola . ' sigo disponible para ayudarle sin presión. ¿Le sería útil saber qué esperar durante una consulta gratis? Responda STOP para cancelar.',
                9 => $hola . ' si una llamada le resulta más fácil, puedo pedirle a Rod que se comunique a la hora que usted prefiera. ¿Desea continuar por mensaje o por llamada?',
                11 => $hola . ' cerraré el seguimiento activo por ahora, pero puede responder cuando guste y retomamos. Responda STOP para cancelar.',
            ];
            if ($channel === 'sms') {
                return ['subject' => '', 'body' => $spanishSms[$step] ?? $hola . ' seguimos disponibles para ayudarle. ¿Mejorar su sonrisa todavía es algo en lo que desea apoyo? Responda STOP para cancelar.'];
            }
            $spanishSubjects = [
                3 => 'La información que solicitó a Elite Smiles',
                6 => 'Qué esperar en su consulta de sonrisa',
                8 => 'Un próximo paso claro y sin presión',
                10 => 'Aquí estamos cuando esté listo',
            ];
            $spanishBodies = [
                3 => 'Aquí tiene la información que solicitó: cada sonrisa es diferente, por eso el Dr. Meden revisa sus dientes, mordida, fotos y metas antes de explicar qué opciones podrían funcionar. La consulta es gratis y sin presión.',
                6 => 'Durante una consulta gratis, el objetivo es entender sus metas, revisar su sonrisa y explicarle opciones personalizadas con claridad. No necesita tomar ninguna decisión antes de venir.',
                8 => 'Si todavía está explorando, está bien. Podemos ayudarle a entender qué podría ser posible para su sonrisa y cuál sería un próximo paso personalizado.',
                10 => 'Dejaré la puerta abierta. Cuando sea el momento adecuado, puede responder este correo y continuaremos desde aquí sin comenzar de nuevo.',
            ];
            return [
                'subject' => $spanishSubjects[$step] ?? 'Aquí estamos cuando esté listo',
                'body' => $hola . "\n\n"
                    . ($spanishBodies[$step] ?? 'Seguimos disponibles para ayudarle a entender sus opciones para la sonrisa sin presión.')
                    . "\n\nPuede responder aquí cuando tenga una pregunta.\n\nEl equipo de Elite Smiles",
            ];
        }
        $sms = [
            1 => $hello . ' I wanted to make sure the information you requested reached you. What would you most like to improve about your smile—color, shape, spacing, or something else?',
            2 => $hello . ' one quick question so I can help better: what change in your smile would matter most to you?',
            4 => $hello . ' I wanted to keep the conversation open. Is there a question I can answer about your smile options?',
            5 => $hello . ' picking this back up today. What question would be most helpful for us to answer about your smile options?',
            7 => $hello . ' I’m still here to help without pressure. Would it be useful to know what to expect during a complimentary consultation? Reply STOP to opt out.',
            9 => $hello . ' if a call is easier, I can ask Rod to reach out at a time you choose. Would you rather continue by text or call?',
            11 => $hello . ' I’ll close active follow-up for now, but you can reply anytime and we’ll pick it back up. Reply STOP to opt out.',
        ];
        if ($channel === 'sms') {
            $body = $sms[$step] ?? $hello . ' Elite Smiles checking in. Is improving your smile still something you would like help with? Reply STOP to opt out.';
            return ['subject' => '', 'body' => $body];
        }

        $subjects = [
            3 => 'The information you requested from Elite Smiles',
            6 => 'What to expect at your smile consultation',
            8 => 'A clear, low-pressure next step',
            10 => 'Here when you are ready',
        ];
        $bodies = [
            3 => 'Here is the information you requested: every smile is different, so Dr. Meden reviews your teeth, bite, photos, and goals before explaining which options may fit. The consultation is complimentary and low pressure.',
            6 => 'During a complimentary consultation, the goal is to understand what you want to improve, review your smile, and explain personalized options clearly. You do not need to make any decision before you come in.',
            8 => 'If you are still exploring, that is completely okay. We can help you understand what may be possible for your smile and what a personalized next step would look like.',
            10 => 'I will leave the door open. When the timing feels right, reply to this email and we can continue from here without starting over.',
        ];
        $subject = $subjects[$step] ?? 'Here when you are ready';
        $body = $hello . "\n\n"
            . ($bodies[$step] ?? 'We are still available to help you understand your smile options without pressure.')
            . "\n\nYou can reply here whenever a question comes up.\n\nThe Elite Smiles Team";
        return ['subject' => $subject, 'body' => $body];
    }
}

if (!function_exists('lead_agent_strategy_followup_draft')) {
    function lead_agent_strategy_followup_draft(array $lead, string $channel, int $step, array $conversionMemory = []): array
    {
        $first = lead_agent_first_name($lead);
        $hello = $first !== '' ? 'Hi ' . $first . ',' : 'Hi,';
        $goal = trim((string) ($conversionMemory['treatment_goal'] ?? (string) ($lead['procedure_interest'] ?? 'smile care')));
        $goal = $goal !== '' ? strtolower(preg_replace('/\s+/u', ' ', $goal)) : 'your smile goals';
        $objection = trim((string) ($conversionMemory['primary_objection'] ?? ''));
        $strategy = trim((string) ($conversionMemory['strategy_key'] ?? 'consultation_value'));
        $day = trim((string) ($lead['scheduling_preferred_day'] ?? ''));
        $time = trim((string) ($lead['scheduling_preferred_time'] ?? ''));
        $hasDay = $day !== '';
        $hasTime = $time !== '';

        if (lead_language_is_spanish($lead)) {
            return lead_agent_approved_followup($lead, $channel, $step) + [
                'draft_source' => 'strategy_template',
                'strategy_key' => trim((string)($conversionMemory['strategy_key'] ?? 'consultation_value')),
            ];
        }

        $goalLine = 'your smile goals';
        if ($goal !== '') {
            $goalLine = preg_match('/\b(veneers|implants|smile\s+makeover)\b/i', $goal)
                ? $goal
                : 'your smile goals';
        }

        $sms = '';
        $subject = '';
        $body = '';

        switch ($strategy) {
            case 'goal_discovery':
                $sms = $hello . ' one quick question: what is the biggest result you are hoping for with ' . $goalLine . '?';
                $subject = 'Your smile goals';
                $body = $hello . "\n\nGreat choice to keep your goal clear before anything else. What is the biggest thing you would like to improve?\n\nElite Smiles";
                break;
            case 'education':
                $sms = $hello . ' most people find a short consult helps us focus on exactly what matters for them. Would you like a quick overview of options for ' . $goalLine . '?';
                $subject = 'Helpful next step for your smile';
                $body = $hello . "\n\nA helpful first step is a free consult so we can review photos and options specifically for your smile goals.\n\nWould you like a quick overview first?\n\nElite Smiles";
                break;
            case 'trust_credibility':
                $sms = $hello . ' totally understand moving slowly on this. Dr. Meden usually starts with a complimentary consult to review your goals, expectations, and timing options. Would that still be helpful for you?';
                $subject = 'A calm next step for your smile';
                $body = $hello . "\n\nThat is a very reasonable place to be. Dr. Meden likes to review each smile in a complimentary consult and map what is realistic for your specific goals before anything starts.\n\nWould you like that kind of first step?\n\nElite Smiles";
                break;
            case 'objection_resolution':
                $objectionText = $objection !== '' ? 'I hear your concern about ' . str_replace('_', ' ', $objection) . '. ' : '';
                $sms = $hello . ' ' . $objectionText . 'Totally valid. What part would you like me to clarify first?';
                $subject = 'Let me help with that';
                $body = $hello . "\n\n" . $objectionText . 'Totally valid. If that concern is important right now, what specific detail would help you feel better about next steps?\n\nElite Smiles';
                break;
            case 'scheduling_preference':
                if ($hasDay && !$hasTime) {
                    $sms = $hello . ' thanks for sharing ' . ucfirst($day) . '. Do mornings or afternoons usually work best?';
                } elseif (!$hasDay && $hasTime) {
                    $sms = $hello . ' thanks for sharing your timing. Is there a particular day this week that is best for you?';
                } elseif ($hasDay && $hasTime) {
                    $sms = $hello . ' great, I can ask Rod to check availability on ' . $day . ' in the ' . $time . '.';
                } else {
                    $sms = $hello . ' would mornings or afternoons usually work best for you?';
                }
                $subject = 'Checking availability';
                $body = $hello . "\n\n" . ($hasDay && $hasTime
                    ? 'Great—thank you for that. I can ask Rod to check what we currently have available ' . $day . ' in the ' . $time . '.'
                    : 'Great, thanks for sharing what works for you. I can ask Rod to check if there are complimentary consultation options that fit your timing.') .
                    "\n\nWould you like me to check that for you?\n\nElite Smiles";
                break;
            case 'open_door':
                $sms = $hello . ' just keeping the line open. If now feels like a better time, I can still help with a complimentary consultation. No pressure—does that sound okay?';
                $subject = 'Whenever you are ready';
                $body = $hello . "\n\nNo pressure at all — we are here whenever you are ready. If you want, we can keep this simple and start with a complimentary consult with Dr. Meden.\n\nElite Smiles";
                break;
            default:
                $sms = $hello . ' this is a quick note from Elite Smiles. If improving your smile is still important, what would be most helpful for you to understand next?';
                $subject = 'Here when you are ready';
                $body = $hello . "\n\nIf improving your smile is still a goal, we are here to help you understand your options without pressure. You can reply with any question whenever the timing feels right.\n\nElite Smiles";
                break;
        }

        if ($channel === 'email') {
            return ['subject' => $subject !== '' ? $subject : 'Your smile consultation', 'body' => $body !== '' ? $body : $sms . "\n\nElite Smiles"];
        }

        return ['subject' => '', 'body' => $sms, 'draft_source' => 'strategy_template', 'strategy_key' => $strategy];
    }
}

if (!function_exists('lead_agent_safe_contextual_fallback')) {
    /** Approved copy used only when the model cannot safely draft routine nurture. */
    function lead_agent_safe_contextual_fallback(array $lead, string $channel, int $step): array
    {
        $first = lead_agent_first_name($lead);
        $hello = $first !== '' ? 'Hi ' . $first . ',' : 'Hi,';
        $day = trim((string) ($lead['scheduling_preferred_day'] ?? ''));
        $time = trim((string) ($lead['scheduling_preferred_time'] ?? ''));
        $interest = strtolower(trim((string) ($lead['procedure_interest'] ?? '')));
        $goal = str_contains($interest, 'veneer') ? 'veneers consultation' : 'smile consultation';

        if (lead_language_is_spanish($lead)) {
            $hola = $first !== '' ? 'Hola ' . $first . ',' : 'Hola,';
            $spanishGoal = str_contains($interest, 'veneer') ? 'consulta de carillas' : 'consulta de sonrisa';
            if ($day !== '' && $time === '') {
                $body = $hola . ' estamos disponibles cuando quiera programar su ' . $spanishGoal
                    . ' gratis. Anteriormente mencionó ' . $day . '. ¿Generalmente le funciona mejor por la mañana o por la tarde?';
            } elseif ($day === '' && $time !== '') {
                $body = $hola . ' estamos disponibles cuando quiera programar su ' . $spanishGoal
                    . ' gratis. Anteriormente prefirió ' . $time . '. ¿Qué día de la semana le funciona mejor?';
            } elseif ($day !== '' && $time !== '') {
                $body = $hola . ' estamos disponibles cuando quiera para su ' . $spanishGoal
                    . ' gratis. ¿Quiere que le pida a Rod revisar disponibilidad para ' . $day . ' ' . $time . '?';
            } else {
                return lead_agent_approved_followup($lead, $channel, $step) + ['draft_source' => 'approved_fallback'];
            }
            return $channel === 'email'
                ? ['subject' => 'Cuando esté listo', 'body' => $body . "\n\nElite Smiles", 'draft_source' => 'approved_fallback']
                : ['subject' => '', 'body' => $body, 'draft_source' => 'approved_fallback'];
        }

        if ($day !== '' && $time === '') {
            $body = $hello . ' we are here whenever you are ready to schedule your complimentary ' . $goal
                . '. You previously mentioned ' . $day . '. Would mornings or afternoons usually work best?';
        } elseif ($day === '' && $time !== '') {
            $body = $hello . ' we are here whenever you are ready to schedule your complimentary ' . $goal
                . '. You previously preferred ' . $time . '. What day of the week works best for you?';
        } elseif ($day !== '' && $time !== '') {
            $body = $hello . ' we are here whenever you are ready for your complimentary ' . $goal
                . '. Would you like me to ask Rod to check availability for ' . $day . ' ' . $time . '?';
        } elseif ($step >= 9) {
            $body = $hello . ' we are here whenever you are ready to schedule your complimentary ' . $goal
                . '. Would you like me to check what appointment times are currently available?';
        } else {
            return lead_agent_approved_followup($lead, $channel, $step) + ['draft_source' => 'approved_fallback'];
        }

        if ($channel === 'email') {
            return ['subject' => 'Whenever you are ready', 'body' => $body . "\n\nElite Smiles", 'draft_source' => 'approved_fallback'];
        }
        return ['subject' => '', 'body' => $body, 'draft_source' => 'approved_fallback'];
    }
}

if (!function_exists('lead_agent_cost_redirect')) {
    function lead_agent_cost_redirect(array $lead, string $channel): array
    {
        $first = lead_agent_first_name($lead);
        if (lead_language_is_spanish($lead)) {
            $hola = $first !== '' ? 'Hola ' . $first . ',' : 'Hola,';
            $body = $hola . ' cada sonrisa es diferente, por eso el Dr. Meden revisa sus metas y necesidades durante la consulta gratis. ¿Quiere que Rod le ayude a programarla?';
            return $channel === 'email'
                ? ['subject' => 'Su consulta con Elite Smiles', 'body' => $body . "\n\nElite Smiles"]
                : ['subject' => '', 'body' => $body];
        }
        $hello = $first !== '' ? 'Hi ' . $first . ',' : 'Hi,';
        $body = $hello . ' every smile is different, so Dr. Meden reviews your goals and clinical needs during the complimentary consultation. Would you like Rod to help get that scheduled?';
        return $channel === 'email'
            ? ['subject' => 'Your Elite Smiles consultation', 'body' => $body . "\n\nElite Smiles"]
            : ['subject' => '', 'body' => $body];
    }
}

if (!function_exists('lead_agent_send_natural_reply')) {
    function lead_agent_send_natural_reply(array $lead, string $channel, array $draft, string $eventKey, string $intent): array
    {
        $leadId = (int) ($lead['id'] ?? 0);
        $flags = lead_agent_policy_flags((string) ($draft['subject'] ?? '') . ' ' . (string) ($draft['body'] ?? ''));
        if ($flags !== []) {
            return ['ok' => false, 'sent' => false, 'policy_flags' => $flags];
        }
        if (lead_agent_mode() === 'shadow') {
            lead_agent_event($leadId, $eventKey, 'shadow_reply', $channel, 'would_send', $intent, $draft);
            return ['ok' => true, 'sent' => false, 'shadow' => true];
        }
        $send = $channel === 'email'
            ? lead_agent_email_send($lead, (string) ($draft['subject'] ?? 'Your Elite Smiles consultation'), (string) ($draft['body'] ?? ''), $eventKey)
            : lead_agent_sms_send($lead, (string) ($draft['body'] ?? ''), $eventKey);
        if (empty($send['ok'])) {
            return $send + ['sent' => false];
        }
        lead_agent_event($leadId, $eventKey, 'automatic_reply', $channel, 'sent', $intent);
        lead_agent_record_touchpoint($lead, $eventKey, $channel, 0, 'automatic_reply', $send + lead_agent_draft_conversion_meta($draft));
        return $send + ['sent' => true];
    }
}

if (!function_exists('lead_agent_save_scheduling_preferences')) {
    function lead_agent_save_scheduling_preferences(int $leadId, array $preferences): void
    {
        $sets = [];
        $params = ['id' => $leadId];
        if (function_exists('leads_has_column') && leads_has_column('scheduling_preferred_day') && trim((string) ($preferences['day'] ?? '')) !== '') {
            $sets[] = 'scheduling_preferred_day = :preferred_day';
            $params['preferred_day'] = ucfirst((string) $preferences['day']);
        }
        if (function_exists('leads_has_column') && leads_has_column('scheduling_preferred_time')) {
            $preferredTime = trim((string) ($preferences['specific_time'] ?? '')) ?: trim((string) ($preferences['period'] ?? ''));
            if ($preferredTime !== '') {
                $sets[] = 'scheduling_preferred_time = :preferred_time';
                $params['preferred_time'] = $preferredTime;
            }
        }
        if ($sets !== []) {
            db_execute('UPDATE leads SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :id LIMIT 1', $params);
        }
    }
}

if (!function_exists('lead_agent_handle_scheduling_intent')) {
    function lead_agent_handle_scheduling_intent(array $lead, string $body, string $channel, string $eventKey): array
    {
        $leadId = (int) ($lead['id'] ?? 0);
        $preferences = lead_agent_merge_scheduling_preferences(
            lead_agent_historical_scheduling_preferences($leadId),
            lead_agent_scheduling_preferences($body)
        );
        if (trim((string) ($preferences['day'] ?? '')) === '' && trim((string) ($lead['scheduling_preferred_day'] ?? '')) !== '') {
            $preferences['day'] = strtolower(trim((string) $lead['scheduling_preferred_day']));
        }
        if (trim((string) ($preferences['specific_time'] ?? '')) === '' && trim((string) ($preferences['period'] ?? '')) === '') {
            $knownTime = trim((string) ($lead['scheduling_preferred_time'] ?? ''));
            if (in_array(strtolower($knownTime), ['morning', 'afternoon', 'evening'], true)) {
                $preferences['period'] = strtolower($knownTime);
            } elseif ($knownTime !== '') {
                $preferences['specific_time'] = $knownTime;
            }
        }
        $preferences['has_preference'] = trim((string) ($preferences['day'] ?? '')) !== ''
            || trim((string) ($preferences['period'] ?? '')) !== ''
            || trim((string) ($preferences['specific_time'] ?? '')) !== '';
        $preferences['ready_for_availability'] = lead_agent_scheduling_preferences_complete($preferences);
        lead_agent_save_scheduling_preferences($leadId, $preferences);
        lead_lifecycle_mark_scheduling($leadId, 'lead_agent_scheduling_intent');
        $message = lead_agent_scheduling_acknowledgment($lead, $preferences);
        $draft = $channel === 'email'
            ? ['subject' => lead_language_text($lead, 'Your Elite Smiles consultation', 'Su consulta con Elite Smiles'), 'body' => $message . "\n\nElite Smiles"]
            : ['subject' => '', 'body' => $message];
        $sendKey = 'scheduling-reply-' . $eventKey;
        $send = lead_agent_send_natural_reply($lead, $channel, $draft, $sendKey, 'ready_to_schedule');
        if (empty($send['ok'])) {
            return lead_agent_internal_handoff($lead, 'needs_attention', 'Natural scheduling acknowledgment could not be delivered.')
                + ['intent' => 'ready_to_schedule', 'handled' => true];
        }

        $preferenceLabel = lead_agent_scheduling_preference_label($preferences);
        if (empty($preferences['ready_for_availability'])) {
            $nextPreferenceFollowup = lead_agent_align_contact_time((new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->modify('+36 hours'))->format('Y-m-d H:i:s');
            db_execute("UPDATE lead_agent_states SET status = 'engaged', human_takeover = 0, human_takeover_until = NULL, scheduling_phase = 'awaiting_preference', scheduling_context = :context, next_action_at = :next_action_at, last_action_at = NOW(), last_decision = 'asked_for_missing_scheduling_preference', updated_at = NOW() WHERE lead_id = :lead_id", [
                'context' => substr($preferenceLabel, 0, 500),
                'next_action_at' => $nextPreferenceFollowup,
                'lead_id' => $leadId,
            ]);
            if (function_exists('leads_has_column') && leads_has_column('follow_up_status')) {
                db_execute("UPDATE leads SET follow_up_status = 'reply_received', next_follow_up_at = :next_action_at, updated_at = NOW() WHERE id = :id LIMIT 1", [
                    'next_action_at' => $nextPreferenceFollowup,
                    'id' => $leadId,
                ]);
            }
            return ['ok' => true, 'handled' => true, 'intent' => 'ready_to_schedule', 'sent' => !empty($send['sent']), 'status' => 'awaiting_preference', 'preference' => $preferenceLabel];
        }

        db_execute("UPDATE lead_agent_states SET scheduling_phase = 'awaiting_availability', scheduling_context = :context, next_action_at = NULL, last_action_at = NOW(), last_decision = 'availability_requested', updated_at = NOW() WHERE lead_id = :lead_id", [
            'context' => substr($preferenceLabel, 0, 500),
            'lead_id' => $leadId,
        ]);
        $handoff = lead_agent_internal_handoff($lead, 'ready_to_schedule', 'Inbound message includes a scheduling preference.', [
            'stage' => 'availability',
            'preference' => $preferenceLabel,
        ]);
        return $handoff + ['intent' => 'ready_to_schedule', 'handled' => true, 'sent' => !empty($send['sent'])];
    }
}

if (!function_exists('lead_agent_offer_availability')) {
    function lead_agent_offer_availability(int $leadId, string $option1, string $option2, int $actorUserId = 0, array $availabilityPool = []): array
    {
        lead_agent_ensure_schema();
        $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        $state = db_one('SELECT * FROM lead_agent_states WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]);
        if (!$lead || !$state) {
            return ['ok' => false, 'message' => 'Lead scheduling state was not found.'];
        }
        if ((string) ($state['status'] ?? '') !== 'ready_to_schedule' || (string) ($state['scheduling_phase'] ?? '') !== 'awaiting_availability') {
            return ['ok' => false, 'message' => 'This lead is not waiting for availability options.'];
        }
        $time1 = strtotime($option1);
        $time2 = strtotime($option2);
        if ($time1 === false || $time2 === false || $time1 <= time() || $time2 <= time() || $time1 === $time2) {
            return ['ok' => false, 'message' => 'Choose two different future appointment times.'];
        }
        if ($time2 < $time1) {
            [$time1, $time2] = [$time2, $time1];
        }
        $normalized1 = date('Y-m-d H:i:s', $time1);
        $normalized2 = date('Y-m-d H:i:s', $time2);
        $body = lead_agent_availability_offer_message($lead, $normalized1, $normalized2);
        $channel = lead_agent_sms_blocked($lead) ? 'email' : 'sms';
        if ($channel === 'email' && lead_agent_email_blocked($lead)) {
            return ['ok' => false, 'message' => 'No consented delivery channel is available.'];
        }
        $draft = $channel === 'email'
            ? ['subject' => lead_language_text($lead, 'Two consultation times for you', 'Dos horarios de consulta para usted'), 'body' => $body . "\n\nElite Smiles"]
            : ['subject' => '', 'body' => $body];
        $eventKey = 'availability-offer-' . $leadId . '-' . hash('sha256', $normalized1 . '|' . $normalized2);
        $send = lead_agent_send_natural_reply($lead, $channel, $draft, $eventKey, 'availability_offered');
        if (empty($send['ok'])) {
            return ['ok' => false, 'message' => 'The availability options could not be delivered.'];
        }
        if (!empty($send['shadow'])) {
            return ['ok' => true, 'message' => 'Shadow mode: the two options were prepared but not sent.', 'shadow' => true, 'channel' => $channel];
        }
        $normalizedPool = array_values(array_unique(array_filter(array_map(static function ($value): string {
            $timestamp = strtotime((string) $value);
            return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : '';
        }, $availabilityPool))));
        if ($normalizedPool === []) {
            $normalizedPool = [$normalized1, $normalized2];
        }
        db_execute("UPDATE lead_agent_states SET status = 'awaiting_slot_selection', scheduling_phase = 'awaiting_slot_selection', availability_option_1 = :option1, availability_option_2 = :option2, availability_pool_json = :availability_pool_json, selected_availability = NULL, human_takeover = 0, human_takeover_until = NULL, pause_reason = '', next_action_at = NULL, last_action_at = NOW(), last_decision = 'availability_offered', updated_at = NOW() WHERE lead_id = :lead_id", [
            'option1' => $normalized1,
            'option2' => $normalized2,
            'availability_pool_json' => json_encode($normalizedPool, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'lead_id' => $leadId,
        ]);
        if (function_exists('leads_has_column') && leads_has_column('follow_up_status')) {
            db_execute("UPDATE leads SET follow_up_status = 'reply_received', next_follow_up_at = NULL, updated_at = NOW() WHERE id = :id LIMIT 1", ['id' => $leadId]);
        }
        lead_agent_event($leadId, $eventKey . '-recorded', 'availability_offered', $channel, !empty($send['shadow']) ? 'would_send' : 'sent', 'operator_supplied_two_options', [
            'option_1' => $normalized1,
            'option_2' => $normalized2,
            'actor_user_id' => $actorUserId,
        ]);
        lead_comm_insert_activity($leadId, 'lead_agent_availability_offered', 'Rod supplied two appointment options and the Lead Agent presented them naturally.', [
            'option_1' => $normalized1,
            'option_2' => $normalized2,
            'actor_user_id' => $actorUserId,
        ], 'Lead Agent');
        return ['ok' => true, 'message' => 'Two appointment options were sent.', 'channel' => $channel, 'option_1' => $normalized1, 'option_2' => $normalized2];
    }
}

if (!function_exists('lead_agent_handle_slot_selection')) {
    function lead_agent_handle_slot_selection(array $lead, array $state, string $body, string $channel, string $eventKey): array
    {
        $leadId = (int) ($lead['id'] ?? 0);
        $option1 = (string) ($state['availability_option_1'] ?? '');
        $option2 = (string) ($state['availability_option_2'] ?? '');
        $selectedNumber = lead_agent_match_availability_selection($body, $option1, $option2);
        if ($selectedNumber === 0) {
            $asksForDifferentTime = (bool) preg_match('/\b(neither|none|other|later|earlier|different|do not work|don\'t work|does not work|doesn\'t work|not work|ninguno|ninguna|otro|otra|m[aá]s tarde|m[aá]s temprano|diferente|no funciona|no me funciona)\b/iu', $body);
            if ($asksForDifferentTime) {
                $pool = json_decode((string) ($state['availability_pool_json'] ?? ''), true);
                $pool = is_array($pool) ? array_values(array_diff($pool, [$option1, $option2])) : [];
                $poolWindows = [];
                foreach ($pool as $poolSlot) {
                    $timestamp = strtotime((string) $poolSlot);
                    if ($timestamp !== false) {
                        $poolWindows[] = ['start' => date('Y-m-d H:i:s', $timestamp), 'end' => date('Y-m-d H:i:s', $timestamp + 1800)];
                    }
                }
                $freshPool = $poolWindows !== [] ? lead_agent_slots_for_operator_windows($poolWindows) : ['available' => [], 'chosen' => []];
                $freshAvailable = (array) ($freshPool['available'] ?? []);
                $nextChoices = (array) ($freshPool['chosen'] ?? []);
                if (count($nextChoices) >= 2) {
                    db_execute("UPDATE lead_agent_states SET status = 'ready_to_schedule', scheduling_phase = 'awaiting_availability', human_takeover = 1, next_action_at = NULL, updated_at = NOW() WHERE lead_id = :lead_id", ['lead_id' => $leadId]);
                    $offer = lead_agent_offer_availability($leadId, (string) $nextChoices[0], (string) $nextChoices[1], 0, $freshAvailable);
                    return ['ok' => !empty($offer['ok']), 'handled' => true, 'intent' => 'alternate_slots_offered', 'sent' => !empty($offer['ok']), 'status' => 'awaiting_slot_selection'];
                }
                $message = lead_language_text($lead,
                    'No problem—let me check a different time window for you.',
                    'No hay problema. Permítame revisar otro horario para usted.'
                );
                $draft = $channel === 'email' ? ['subject' => lead_language_text($lead, 'Your consultation time', 'El horario de su consulta'), 'body' => $message . "\n\nElite Smiles"] : ['subject' => '', 'body' => $message];
                $send = lead_agent_send_natural_reply($lead, $channel, $draft, 'slot-alternatives-needed-' . $eventKey, 'alternate_slots_needed');
                db_execute("UPDATE lead_agent_states SET status = 'ready_to_schedule', scheduling_phase = 'awaiting_availability', availability_option_1 = NULL, availability_option_2 = NULL, availability_pool_json = NULL, human_takeover = 1, next_action_at = NULL, last_decision = 'alternate_window_needed', updated_at = NOW() WHERE lead_id = :lead_id", ['lead_id' => $leadId]);
                return lead_agent_internal_handoff($lead, 'ready_to_schedule', 'The lead declined the available pool and needs a different time window.', [
                    'stage' => 'availability',
                    'preference' => trim((string) ($state['scheduling_context'] ?? '')),
                ]) + ['handled' => true, 'intent' => 'alternate_slots_needed', 'sent' => !empty($send['sent'])];
            }
            $message = lead_language_is_spanish($lead)
                ? 'Claro. ¿Cuál le funciona mejor: ' . lead_agent_format_availability($option1, 'es') . ' o ' . lead_agent_format_availability($option2, 'es') . '?'
                : 'Of course—which works better for you: ' . lead_agent_format_availability($option1) . ' or ' . lead_agent_format_availability($option2) . '?';
            $draft = $channel === 'email' ? ['subject' => lead_language_text($lead, 'Your consultation time', 'El horario de su consulta'), 'body' => $message . "\n\nElite Smiles"] : ['subject' => '', 'body' => $message];
            $send = lead_agent_send_natural_reply($lead, $channel, $draft, 'slot-clarify-' . $eventKey, 'slot_clarification');
            return ['ok' => !empty($send['ok']), 'handled' => true, 'intent' => 'slot_clarification', 'sent' => !empty($send['sent']), 'status' => 'awaiting_slot_selection'];
        }

        $selected = $selectedNumber === 1 ? $option1 : $option2;
        $formatted = lead_agent_format_availability($selected, lead_language_preference($lead));
        $selectedTimestamp = strtotime($selected);
        $selectedWindow = $selectedTimestamp !== false
            ? [['start' => date('Y-m-d H:i:s', $selectedTimestamp), 'end' => date('Y-m-d H:i:s', $selectedTimestamp + 1800)]]
            : [];
        $availabilityCheck = $selectedWindow !== [] ? lead_agent_slots_for_operator_windows($selectedWindow) : ['available' => []];
        if (!in_array($selected, (array) ($availabilityCheck['available'] ?? []), true)) {
            $message = lead_language_text($lead,
                'That time just became unavailable. Let me check two fresh options for you.',
                'Ese horario acaba de dejar de estar disponible. Permítame revisar dos opciones nuevas para usted.'
            );
            $draft = $channel === 'email' ? ['subject' => lead_language_text($lead, 'Your consultation request', 'Su solicitud de consulta'), 'body' => $message . "\n\nElite Smiles"] : ['subject' => '', 'body' => $message];
            $send = lead_agent_send_natural_reply($lead, $channel, $draft, 'slot-no-longer-open-' . $eventKey, 'availability_changed');
            db_execute("UPDATE lead_agent_states SET status = 'ready_to_schedule', scheduling_phase = 'awaiting_availability', availability_option_1 = NULL, availability_option_2 = NULL, selected_availability = NULL, human_takeover = 1, human_takeover_until = NULL, next_action_at = NULL, last_decision = 'selected_slot_no_longer_available', updated_at = NOW() WHERE lead_id = :lead_id", ['lead_id' => $leadId]);
            return lead_agent_internal_handoff($lead, 'ready_to_schedule', 'The selected calendar slot became occupied and needs two replacement options.', [
                'stage' => 'availability',
                'preference' => trim((string) ($state['scheduling_context'] ?? '')),
            ]) + ['handled' => true, 'intent' => 'availability_changed', 'sent' => !empty($send['sent'])];
        }
        $hasDob = trim((string) ($lead['date_of_birth'] ?? '')) !== '';
        $message = lead_language_is_spanish($lead)
            ? ($hasDob
                ? 'Perfecto. Tengo ' . $formatted . ' como su elección. Rod lo confirmará en breve.'
                : 'Perfecto. Tengo ' . $formatted . ' como su elección. ¿Cuál es su fecha de nacimiento para terminar la solicitud de cita?')
            : ($hasDob
                ? 'Perfect—I have ' . $formatted . ' as your choice. Rod will confirm it shortly.'
                : 'Perfect—I have ' . $formatted . ' as your choice. What is your date of birth so I can finish the appointment request?');
        $draft = $channel === 'email' ? ['subject' => lead_language_text($lead, 'Your consultation request', 'Su solicitud de consulta'), 'body' => $message . "\n\nElite Smiles"] : ['subject' => '', 'body' => $message];
        $send = lead_agent_send_natural_reply($lead, $channel, $draft, 'slot-selected-' . $eventKey, 'slot_selected');
        if (empty($send['ok'])) {
            return lead_agent_internal_handoff($lead, 'needs_attention', 'The selected appointment option could not be acknowledged.') + ['handled' => true, 'intent' => 'slot_selected'];
        }
        db_execute("UPDATE lead_agent_states SET status = :status, scheduling_phase = :phase, selected_availability = :selected, scheduling_context = :context, next_action_at = NULL, last_action_at = NOW(), last_decision = :decision, updated_at = NOW() WHERE lead_id = :lead_id", [
            'status' => $hasDob ? 'ready_to_schedule' : 'awaiting_dob',
            'phase' => $hasDob ? 'ready_to_confirm' : 'awaiting_dob',
            'selected' => $selected,
            'context' => substr($formatted, 0, 500),
            'decision' => $hasDob ? 'ready_to_confirm' : 'dob_requested_after_slot',
            'lead_id' => $leadId,
        ]);
        lead_agent_event($leadId, 'slot-choice-' . $eventKey, 'slot_selected', $channel, 'recorded', 'lead_selected_operator_option', ['selected_option' => $selected]);
        if ($hasDob) {
            return lead_agent_internal_handoff($lead, 'ready_to_schedule', 'Lead selected an appointment option and DOB is already on file.', [
                'stage' => 'confirmation',
                'selected_option' => $formatted,
            ]) + ['handled' => true, 'intent' => 'slot_selected', 'sent' => !empty($send['sent'])];
        }
        return ['ok' => true, 'handled' => true, 'intent' => 'slot_selected', 'sent' => !empty($send['sent']), 'status' => 'awaiting_dob'];
    }
}

if (!function_exists('lead_agent_handle_dob_reply')) {
    function lead_agent_handle_dob_reply(array $lead, array $state, string $body, string $channel, string $eventKey): array
    {
        $leadId = (int) ($lead['id'] ?? 0);
        if (preg_match('/\b(why|what for|why do you need|why is.*needed|por qu[eé]|para qu[eé]|por qu[eé] la necesitan)\b/iu', $body)) {
            $message = lead_language_text($lead,
                'We use it to create the appointment record. If you prefer, you can provide it by phone instead.',
                'La usamos para crear el registro de la cita. Si prefiere, puede proporcionarla por teléfono.'
            );
            $draft = $channel === 'email' ? ['subject' => lead_language_text($lead, 'Your consultation request', 'Su solicitud de consulta'), 'body' => $message . "\n\nElite Smiles"] : ['subject' => '', 'body' => $message];
            $send = lead_agent_send_natural_reply($lead, $channel, $draft, 'dob-explain-' . $eventKey, 'dob_explanation');
            return ['ok' => !empty($send['ok']), 'handled' => true, 'intent' => 'dob_explanation', 'sent' => !empty($send['sent']), 'status' => 'awaiting_dob'];
        }
        $dob = lead_agent_parse_dob($body);
        if ($dob === '') {
            $looksLikeDate = (bool) preg_match('/\d|jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre/iu', $body);
            if (!$looksLikeDate) {
                return lead_agent_internal_handoff($lead, 'needs_attention', 'Lead replied while DOB was pending, but the message was not a recognizable date.') + ['handled' => true, 'intent' => 'needs_attention'];
            }
            $message = lead_language_text($lead,
                'Thanks. Please send your date of birth as MM/DD/YYYY so I can add it correctly.',
                'Gracias. Envíe su fecha de nacimiento como MM/DD/AAAA para que pueda registrarla correctamente.'
            );
            $draft = $channel === 'email' ? ['subject' => lead_language_text($lead, 'Your consultation request', 'Su solicitud de consulta'), 'body' => $message . "\n\nElite Smiles"] : ['subject' => '', 'body' => $message];
            $send = lead_agent_send_natural_reply($lead, $channel, $draft, 'dob-format-' . $eventKey, 'dob_format');
            return ['ok' => !empty($send['ok']), 'handled' => true, 'intent' => 'dob_format', 'sent' => !empty($send['sent']), 'status' => 'awaiting_dob'];
        }

        db_execute('UPDATE leads SET date_of_birth = :dob, updated_at = NOW() WHERE id = :id LIMIT 1', ['dob' => $dob, 'id' => $leadId]);
        $selected = (string) ($state['selected_availability'] ?? '');
        $formatted = lead_agent_format_availability($selected, lead_language_preference($lead));
        $message = lead_language_is_spanish($lead)
            ? 'Gracias, ya la tengo. Rod confirmará ' . ($formatted !== '' ? $formatted : 'el horario de su cita') . ' en breve.'
            : 'Thank you—I have that. Rod will confirm ' . ($formatted !== '' ? $formatted : 'your appointment time') . ' shortly.';
        $draft = $channel === 'email' ? ['subject' => lead_language_text($lead, 'Your consultation request', 'Su solicitud de consulta'), 'body' => $message . "\n\nElite Smiles"] : ['subject' => '', 'body' => $message];
        $send = lead_agent_send_natural_reply($lead, $channel, $draft, 'dob-received-' . $eventKey, 'dob_received');
        db_execute("UPDATE lead_agent_states SET status = 'ready_to_schedule', scheduling_phase = 'ready_to_confirm', next_action_at = NULL, last_action_at = NOW(), last_decision = 'dob_received_ready_to_confirm', updated_at = NOW() WHERE lead_id = :lead_id", ['lead_id' => $leadId]);
        lead_comm_insert_activity($leadId, 'lead_agent_dob_received', 'Lead Agent securely saved DOB after the lead selected an appointment option.', ['selected_option' => $selected], 'Lead Agent');
        return lead_agent_internal_handoff(array_merge($lead, ['date_of_birth' => $dob]), 'ready_to_schedule', 'Lead selected an appointment option and supplied DOB.', [
            'stage' => 'confirmation',
            'selected_option' => $formatted,
        ]) + ['handled' => true, 'intent' => 'dob_received', 'sent' => !empty($send['sent'])];
    }
}

if (!function_exists('lead_agent_handle_inbound')) {
    function lead_agent_handle_inbound(int $leadId, string $body, string $channel = 'sms', string $eventKey = ''): array
    {
        if (!lead_agent_enabled() || lead_agent_mode() === 'off' || lead_agent_is_globally_paused()) {
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
        $conversionMemory = lead_conversion_refresh($lead, 0);
        if (!empty($state['human_takeover'])) {
            lead_agent_release_expired_human_takeovers($leadId);
            $state = db_one('SELECT * FROM lead_agent_states WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]);
        }
        if ((string) ($state['last_inbound_event_key'] ?? '') === $eventKey) {
            return ['ok' => true, 'handled' => false, 'duplicate' => true];
        }

        $languageSignal = lead_language_record_inbound($leadId, $body);
        $detectedLanguage = (string)($languageSignal['language'] ?? 'unknown');
        if ($detectedLanguage !== 'unknown') {
            $languageSource = (string)($languageSignal['source'] ?? 'inbound_detected');
            $lead['preferred_language'] = $detectedLanguage;
            $lead['preferred_language_source'] = $languageSource;
        }

        db_execute('UPDATE lead_agent_states SET last_inbound_event_key = :event_key, next_action_at = NULL, updated_at = NOW() WHERE lead_id = :lead_id', [
            'event_key' => substr($eventKey, 0, 160),
            'lead_id' => $leadId,
        ]);
        $intent = lead_agent_classify_inbound($body);
        lead_agent_event($leadId, $eventKey, 'inbound_classified', $channel, 'recorded', $intent);
        lead_agent_record_learning($intent, $channel, 'observed');
        lead_agent_attribute_outcome($leadId, 'reply');
        lead_agent_record_learning_outcome($intent, $channel, 'lead_replied');

        if ($intent === 'opt_out') {
            lead_agent_attribute_outcome($leadId, 'opt_out');
            lead_agent_pause($leadId, 'inbound_opt_out', 'opted_out');
            return ['ok' => true, 'handled' => true, 'intent' => $intent, 'sent' => false];
        }
        if ($intent === 'pause') {
            $requestedFollowupAt = lead_agent_requested_followup_at($body);
            if ($requestedFollowupAt !== '') {
                $hold = lead_agent_hold_until($leadId, $requestedFollowupAt, 'lead_agent_inbound');
                return [
                    'ok' => !empty($hold['ok']),
                    'handled' => true,
                    'intent' => $intent,
                    'sent' => false,
                    'status' => !empty($hold['ok']) ? 'patient_hold' : 'paused',
                    'hold_until' => (string)($hold['hold_until'] ?? ''),
                ];
            }
            $declineKind = lead_agent_decline_kind($body);
            lead_agent_pause($leadId, 'lead_' . $declineKind, 'paused');
            if ($declineKind === 'declined') {
                db_execute("UPDATE leads SET status = 'lost_lead', lost_reason = 'not_interested', follow_up_status = 'closed', next_follow_up_at = NULL, updated_at = NOW() WHERE id = :id LIMIT 1", ['id' => $leadId]);
                lead_comm_insert_activity($leadId, 'lead_agent_explicit_decline', 'Lead Agent closed automated follow-up after the patient explicitly declined.', ['body' => mb_substr($body, 0, 250)], 'Lead Agent');
            }
            return ['ok' => true, 'handled' => true, 'intent' => $intent, 'sent' => false];
        }
        if (lead_agent_state_is_patient_hold($state)) {
            lead_lifecycle_mark_inbound_answer($leadId, 'lead_agent_patient_hold_reopened');
            db_execute("UPDATE lead_agent_states
                SET status = 'needs_attention', human_takeover = 1,
                    human_takeover_until = NULL, next_action_at = NULL,
                    pause_reason = 'patient_requested_hold_reopened_by_inbound',
                    last_decision = 'patient_requested_hold_reopened_by_inbound',
                    lock_token = '', locked_at = NULL, updated_at = NOW()
                WHERE lead_id = :lead_id", ['lead_id' => $leadId]);
            db_execute("UPDATE leads SET follow_up_status = 'reply_received',
                    next_follow_up_at = NULL, updated_at = NOW()
                WHERE id = :lead_id LIMIT 1", ['lead_id' => $leadId]);
            lead_agent_event(
                $leadId,
                'patient-hold-reopened-' . $eventKey,
                'inbound_routed_to_human',
                $channel,
                'recorded',
                'patient_requested_hold_reopened_by_inbound'
            );
            lead_comm_insert_activity(
                $leadId,
                'lead_agent_patient_hold_reopened',
                'The patient replied before the hold date. Lead Agent stayed silent and returned the conversation to Rod.',
                ['channel' => $channel, 'event_key' => $eventKey],
                'Lead Agent'
            );
            return ['ok' => true, 'handled' => true, 'intent' => $intent, 'sent' => false, 'status' => 'human_takeover'];
        }
        if (!empty($state['human_takeover']) || in_array((string) ($state['status'] ?? ''), ['human_takeover', 'ready_to_schedule', 'needs_attention'], true)) {
            lead_agent_event($leadId, 'human-owned-' . $eventKey, 'inbound_routed_to_human', $channel, 'recorded', 'human_takeover_active');
            lead_comm_insert_activity($leadId, 'lead_agent_human_owned_inbound', 'Lead Agent stayed silent because Rod owns this conversation.', [
                'channel' => $channel,
                'event_key' => $eventKey,
            ], 'Lead Agent');
            return ['ok' => true, 'handled' => true, 'intent' => $intent, 'sent' => false, 'status' => 'human_takeover'];
        }
        if ($intent === 'needs_attention') {
            lead_agent_record_learning($intent, $channel, 'human_review');
            $normalizedBody = strtolower(trim(preg_replace('/\s+/', ' ', $body) ?? $body));
            $handoffContext = [];
            $handoffReason = 'Inbound message requires human judgment.';
            if (lead_call_consent_requested($normalizedBody)) {
                $handoffContext['stage'] = 'call_requested';
                $handoffReason = 'The lead explicitly requested or accepted a phone call.';
            } elseif (preg_match('/\b(brother|sister|husband|wife|son|daughter|friend|patient)\b/i', $normalizedBody)
                && preg_match('/(?:\+?1[\s.\-]?)?\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}/', $normalizedBody)) {
                $handoffContext['stage'] = 'third_party_referral';
                $handoffReason = 'A third-party referral must be transferred to the correct patient record.';
            }
            return lead_agent_internal_handoff($lead, 'needs_attention', $handoffReason, $handoffContext) + ['intent' => $intent, 'handled' => true];
        }
        $schedulingPhase = (string) ($state['scheduling_phase'] ?? '');
        if ($schedulingPhase !== '' && $intent === 'cost_redirect') {
            $first = lead_agent_first_name($lead);
            if (lead_language_is_spanish($lead)) {
                $hello = $first !== '' ? 'Hola ' . $first . ',' : 'Hola,';
                $nextStep = match ($schedulingPhase) {
                    'awaiting_slot_selection' => '¿Cuál de los dos horarios de consulta le funciona mejor?',
                    'awaiting_dob' => 'Para terminar la solicitud de cita, ¿cuál es su fecha de nacimiento?',
                    'ready_to_confirm' => 'Rod confirmará pronto el horario de consulta que seleccionó.',
                    default => '¿Generalmente le funciona mejor por la mañana o por la tarde?',
                };
                $message = $hello . ' cada sonrisa es diferente, por eso el Dr. Meden revisa sus metas y necesidades durante la consulta gratis. ' . $nextStep;
            } else {
                $hello = $first !== '' ? 'Hi ' . $first . ',' : 'Hi,';
                $nextStep = match ($schedulingPhase) {
                    'awaiting_slot_selection' => 'Which of the two consultation times works better for you?',
                    'awaiting_dob' => 'To finish the appointment request, what is your date of birth?',
                    'ready_to_confirm' => 'Rod will confirm the consultation time you selected shortly.',
                    default => 'Would mornings or afternoons usually work better for you?',
                };
                $message = $hello . ' every smile is different, so Dr. Meden reviews your goals and clinical needs during the complimentary consultation. ' . $nextStep;
            }
            $draft = $channel === 'email'
                ? ['subject' => lead_language_text($lead, 'Your Elite Smiles consultation', 'Su consulta con Elite Smiles'), 'body' => $message . "\n\nElite Smiles"]
                : ['subject' => '', 'body' => $message];
            $send = lead_agent_send_natural_reply($lead, $channel, $draft, 'cost-redirect-' . $eventKey, 'cost_redirect');
            if (empty($send['ok'])) {
                return lead_agent_internal_handoff($lead, 'needs_attention', 'The cost-question redirect could not be delivered.') + ['intent' => $intent, 'handled' => true];
            }
            return ['ok' => true, 'handled' => true, 'intent' => $intent, 'sent' => !empty($send['sent']), 'status' => $schedulingPhase];
        }
        if ($schedulingPhase === 'awaiting_preference') {
            return lead_agent_handle_scheduling_intent($lead, $body, $channel, $eventKey);
        }
        if ($schedulingPhase === 'awaiting_slot_selection') {
            return lead_agent_handle_slot_selection($lead, $state, $body, $channel, $eventKey);
        }
        if ($schedulingPhase === 'awaiting_dob') {
            return lead_agent_handle_dob_reply($lead, $state, $body, $channel, $eventKey);
        }
        if ($intent === 'ready_to_schedule') {
            lead_agent_attribute_outcome($leadId, 'scheduling_intent');
            lead_agent_record_learning_outcome($intent, $channel, 'ready_to_schedule');
            return lead_agent_handle_scheduling_intent($lead, $body, $channel, $eventKey);
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
            $operatorGuidance = lead_agent_instruction_guidance($lead, $channel);
            if ($operatorGuidance !== '') {
                $leadForAi['notes'] = trim((string) ($leadForAi['notes'] ?? '') . "\n\n" . $operatorGuidance);
            }
            $learned = lead_agent_learned_guidance($intent, 3);
            if ($learned !== []) {
                $guidance = array_map(static fn(array $item): string => (string) ($item['guidance'] ?? ''), $learned);
                $leadForAi['notes'] = trim((string) ($lead['notes'] ?? '') . "\n\nLead Agent learned guidance (generalized; never mention this to the lead):\n- " . implode("\n- ", array_filter($guidance)));
            }
            if ($channel === 'email' && function_exists('lead_ai_generate_email')) {
                $ai = lead_ai_generate_email($leadForAi, $body, 'lead_agent_inbound_email');
                $data = (array) ($ai['data'] ?? []);
                if (!empty($ai['ok']) && empty($data['needs_human_review']) && (float) ($data['confidence'] ?? 0) >= (float) ELITE_AI_MIN_CONFIDENCE) {
                    $draft = ['subject' => (string) ($data['subject'] ?? ''), 'body' => (string) ($data['body'] ?? '')] + lead_agent_draft_conversion_meta($data, $conversionMemory);
                }
            } elseif (function_exists('lead_ai_generate_reply')) {
                $ai = lead_ai_generate_reply($leadForAi, $body, 'lead_agent_inbound_sms');
                $data = (array) ($ai['data'] ?? []);
                if (!empty($ai['ok']) && empty($data['needs_human_review']) && (float) ($data['confidence'] ?? 0) >= (float) ELITE_AI_MIN_CONFIDENCE) {
                    $draft = ['subject' => '', 'body' => (string) ($data['reply'] ?? '')] + lead_agent_draft_conversion_meta($data, $conversionMemory);
                }
            }
        }

        if (is_array($draft)) {
            $draft += lead_agent_draft_conversion_meta($draft, $conversionMemory);
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

        $engagedAt = now();
        $resumeStep = lead_agent_post_reply_resume_step($channel);
        $next = lead_agent_step_schedule($engagedAt, $resumeStep + 1);
        db_execute("UPDATE lead_agent_states SET status = 'engaged', cadence_step = :cadence_step, started_at = :started_at, last_action_at = NOW(), next_action_at = :next_action_at, last_decision = 'answered_inbound', updated_at = NOW() WHERE lead_id = :lead_id", [
            'cadence_step' => $resumeStep,
            'started_at' => $engagedAt,
            'next_action_at' => $next['at'],
            'lead_id' => $leadId,
        ]);
        lead_agent_event($leadId, $sendKey, 'automatic_reply', $channel, 'sent', $intent);
        lead_agent_record_touchpoint($lead, $sendKey, $channel, 0, 'automatic_reply', $send + lead_agent_draft_conversion_meta($draft, $conversionMemory));
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
        // SMS + email are one coordinated outreach touch. Count the larger
        // channel total so paired deliveries do not consume two daily touches.
        return max($sms, $email);
    }
}

if (!function_exists('lead_agent_daily_sms_outbound_count')) {
    function lead_agent_daily_sms_outbound_count(int $leadId, string $date): int
    {
        return (int)db_value(
            "SELECT COUNT(*) FROM lead_messages WHERE lead_id = :lead_id AND direction = 'outbound' AND DATE(created_at) = :day",
            ['lead_id' => $leadId, 'day' => $date]
        );
    }
}

if (!function_exists('lead_agent_latest_inbound_is_sms')) {
    function lead_agent_latest_inbound_is_sms(int $leadId): bool
    {
        if ($leadId <= 0) {
            return false;
        }
        try {
            $latest = db_one(
                "SELECT channel FROM (
                    SELECT 'sms' AS channel, created_at, id FROM lead_messages WHERE lead_id = :sms_lead_id AND direction = 'inbound'
                    UNION ALL
                    SELECT 'email' AS channel, created_at, id FROM lead_emails WHERE lead_id = :email_lead_id AND direction = 'inbound'
                 ) inbound_replies
                 ORDER BY created_at DESC, id DESC LIMIT 1",
                ['sms_lead_id' => $leadId, 'email_lead_id' => $leadId]
            );
            return (string)($latest['channel'] ?? '') === 'sms';
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('lead_agent_latest_patient_direction')) {
    function lead_agent_latest_patient_direction(int $leadId): array
    {
        if ($leadId <= 0) {
            return [];
        }
        try {
            $row = db_one(
                "SELECT direction, channel, created_at, id FROM (
                    SELECT direction, 'sms' AS channel, created_at, id FROM lead_messages WHERE lead_id = :sms_lead_id
                    UNION ALL
                    SELECT direction, 'email' AS channel, created_at, id FROM lead_emails WHERE lead_id = :email_lead_id
                 ) patient_events
                 ORDER BY created_at DESC, id DESC LIMIT 1",
                ['sms_lead_id' => $leadId, 'email_lead_id' => $leadId]
            );
            return $row ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('lead_agent_latest_inbound_closure_reason')) {
    /** Respect unresolved explicit declines even when a later courtesy reply exists. */
    function lead_agent_latest_inbound_closure_reason(int $leadId): string
    {
        if ($leadId <= 0) {
            return '';
        }
        try {
            $rows = db_all(
                "SELECT body FROM (
                    SELECT body, created_at, id FROM lead_messages WHERE lead_id = :sms_lead_id AND direction = 'inbound'
                    UNION ALL
                    SELECT body, created_at, id FROM lead_emails WHERE lead_id = :email_lead_id AND direction = 'inbound'
                  ) inbound_events
                 ORDER BY created_at DESC, id DESC LIMIT 50",
                ['sms_lead_id' => $leadId, 'email_lead_id' => $leadId]
            );
            // Walk oldest to newest. A later explicit scheduling request can
            // reopen a declined thread; "thanks" and "you too" cannot.
            $closed = false;
            foreach (array_reverse($rows) as $row) {
                $body = strtolower(trim(preg_replace('/\s+/', ' ', substr((string) ($row['body'] ?? ''), 0, 1200)) ?? ''));
                if ($body === '') {
                    continue;
                }
                if (preg_match('/\b(not interested|no longer interested|no thank you|do not want|don\'t want|too far|farther than|cannot travel|can\'t travel|please stop|do not contact|don\'t contact|wrong number)\b/i', $body)
                    || preg_match('/^(?:no|nope|nah)(?:[.! ,]+(?:thanks|thank you))?[.! ]*$/i', $body)) {
                    $closed = true;
                    continue;
                }
                if (preg_match('/\b(i am|i\'m|im)\s+(still\s+)?interested\b|\b(?:yes|ready|want|would like|i\'d like)\b.{0,50}\b(?:schedule|book|consult|appointment|veneers?|implants?)\b|\b(?:schedule|book)\s+(?:me|it|a|an|the)\b/i', $body)) {
                    $closed = false;
                }
            }
            return $closed ? 'explicit_decline_or_distance' : '';
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('lead_agent_recent_sms_delivery_issue')) {
    /** Route to email after a recent SMS failure instead of repeating the bad path. */
    function lead_agent_recent_sms_delivery_issue(int $leadId, int $days = 14): bool
    {
        if ($leadId <= 0) {
            return false;
        }
        $days = max(1, min(30, $days));
        try {
            return (int) db_value("SELECT
                EXISTS(
                    SELECT 1 FROM lead_messages
                    WHERE lead_id = :message_lead_id AND direction = 'outbound'
                      AND created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                      AND LOWER(COALESCE(twilio_status, '')) IN ('failed','undelivered')
                ) OR EXISTS(
                    SELECT 1 FROM lead_activities
                    WHERE lead_id = :activity_lead_id AND type = 'sms_delivery_issue'
                      AND created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                )", ['message_lead_id' => $leadId, 'activity_lead_id' => $leadId]) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('lead_agent_confirmed_unreachable_contact')) {
    /** True only when no channel remains and at least one route has real delivery-invalid evidence. */
    function lead_agent_confirmed_unreachable_contact(array $lead, array $state = [], bool $recentSmsIssue = false): bool
    {
        $lastDecision = trim((string)($state['last_decision'] ?? ''));
        $smsFailureDecision = in_array($lastDecision, [
            'sms_delivery_failed_needs_attention',
            'sms_unreachable_email_cycle_resumed',
            'unreachable_no_delivery_channel',
            'unreachable_invalid_contact',
        ], true);
        $smsDeliveryInvalid = !elite_phone_is_valid_us((string)($lead['phone'] ?? ''))
            || $recentSmsIssue
            || $smsFailureDecision;
        $emailStatus = strtolower(trim((string)($lead['email_opt_status'] ?? '')));
        $emailDeliveryInvalid = !filter_var(trim((string)($lead['email'] ?? '')), FILTER_VALIDATE_EMAIL)
            || in_array($emailStatus, ['bounced', 'blocked', 'dropped', 'invalid'], true);
        $smsUnavailable = lead_agent_sms_blocked($lead) || $recentSmsIssue || $smsFailureDecision;
        $emailUnavailable = lead_agent_email_blocked($lead);

        return $smsUnavailable && $emailUnavailable && ($smsDeliveryInvalid || $emailDeliveryInvalid);
    }
}

if (!function_exists('lead_agent_reconcile_unreachable_contact')) {
    /**
     * Stop outreach once every known channel is confirmed unusable. The record
     * remains available for deduplication or later corrected contact details,
     * but it is not operator work and must never create an attention halo.
     */
    function lead_agent_reconcile_unreachable_contact(int $leadId, string $source = 'lead_agent_delivery'): array
    {
        if ($leadId <= 0) {
            return ['ok' => false, 'classified' => false, 'reason' => 'lead_not_found'];
        }

        lead_agent_ensure_schema();
        $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$lead) {
            return ['ok' => false, 'classified' => false, 'reason' => 'lead_not_found'];
        }

        $state = db_one('SELECT * FROM lead_agent_states WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]) ?: [];
        $lastDecision = trim((string)($state['last_decision'] ?? ''));
        $recentSmsIssue = lead_agent_recent_sms_delivery_issue($leadId);
        if (!lead_agent_confirmed_unreachable_contact($lead, $state, $recentSmsIssue)) {
            $smsUnavailable = lead_agent_sms_blocked($lead) || $recentSmsIssue;
            return [
                'ok' => true,
                'classified' => false,
                'reason' => 'confirmed_unreachable_not_met',
                'route' => !$smsUnavailable ? 'sms' : (!lead_agent_email_blocked($lead) ? 'email' : 'protected'),
            ];
        }

        $closureReason = trim((string)($lead['last_inbound_at'] ?? '')) !== ''
            ? lead_agent_latest_inbound_closure_reason($leadId)
            : '';
        $assessment = lead_agent_cycle_assessment($lead, $state, true, $closureReason);
        if ((string)($assessment['category'] ?? '') !== 'unreachable') {
            return [
                'ok' => true,
                'classified' => false,
                'reason' => (string)($assessment['reason'] ?? 'protected_state'),
                'route' => 'protected',
            ];
        }

        $alreadyClassified = trim((string)($lead['status'] ?? '')) === 'no_answer'
            && trim((string)($lead['follow_up_status'] ?? '')) === 'unreachable'
            && $lastDecision === 'unreachable_invalid_contact';
        if ($alreadyClassified) {
            return ['ok' => true, 'classified' => true, 'changed' => false, 'reason' => 'already_unreachable', 'route' => 'unreachable'];
        }

        $startedAt = trim((string)($state['started_at'] ?? ''));
        if ($startedAt === '' || strtotime($startedAt) === false) {
            $startedAt = trim((string)($lead['created_at'] ?? ''));
        }
        if ($startedAt === '' || strtotime($startedAt) === false) {
            $startedAt = now();
        }

        db_query(
            "INSERT INTO lead_agent_states
                (lead_id, status, cadence_step, started_at, next_action_at, last_action_at, last_decision,
                 human_takeover, human_takeover_until, pause_reason, lock_token, locked_at, created_at, updated_at)
             VALUES
                (:lead_id, 'paused', :cadence_step, :started_at, NULL, :last_action_at, 'unreachable_invalid_contact',
                 0, NULL, 'No deliverable SMS or email channel remains.', '', NULL, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                status = 'paused', human_takeover = 0, human_takeover_until = NULL,
                next_action_at = NULL, last_decision = 'unreachable_invalid_contact',
                pause_reason = 'No deliverable SMS or email channel remains.',
                lock_token = '', locked_at = NULL, updated_at = NOW()",
            [
                'lead_id' => $leadId,
                'cadence_step' => max(0, (int)($state['cadence_step'] ?? 0)),
                'started_at' => $startedAt,
                'last_action_at' => trim((string)($state['last_action_at'] ?? '')) ?: null,
            ]
        );
        lead_lifecycle_transition_status(
            $leadId,
            'no_answer',
            'No deliverable contact channel remains; Lead Agent classified the record as Unreachable / Invalid Contact.',
            $source,
            ['new_lead', 'attempted_contact', 'contacted', 'in_contact', 'no_answer', '']
        );
        db_execute("UPDATE leads SET follow_up_status = 'unreachable', next_follow_up_at = NULL, updated_at = NOW() WHERE id = :id LIMIT 1", ['id' => $leadId]);
        lead_agent_event(
            $leadId,
            'contactability-unreachable-' . $leadId,
            'cycle_closed',
            '',
            'recorded',
            'unreachable_invalid_contact',
            ['source' => substr(trim($source), 0, 80), 'automatic_outreach' => 'stopped']
        );
        if (function_exists('lead_comm_insert_activity')) {
            lead_comm_insert_activity(
                $leadId,
                'lead_unreachable',
                'No deliverable SMS or email channel remains. Lead Agent stopped automated outreach and classified this record as Unreachable / Invalid Contact.',
                ['source' => substr(trim($source), 0, 80), 'automatic_outreach' => 'stopped'],
                'Lead Agent'
            );
        }

        return ['ok' => true, 'classified' => true, 'changed' => true, 'reason' => 'unreachable_invalid_contact', 'route' => 'unreachable'];
    }
}

if (!function_exists('lead_agent_latest_inbound_message')) {
    function lead_agent_latest_inbound_message(int $leadId): array
    {
        if ($leadId <= 0) {
            return [];
        }
        try {
            return db_one("SELECT body, channel, created_at, id FROM (
                SELECT body, 'sms' AS channel, created_at, id FROM lead_messages WHERE lead_id = :sms_lead_id AND direction = 'inbound'
                UNION ALL
                SELECT body, 'email' AS channel, created_at, id FROM lead_emails WHERE lead_id = :email_lead_id AND direction = 'inbound'
            ) inbound_events ORDER BY created_at DESC, id DESC LIMIT 1", ['sms_lead_id' => $leadId, 'email_lead_id' => $leadId]) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('lead_agent_historical_referral_contact')) {
    function lead_agent_historical_referral_contact(int $leadId): string
    {
        try {
            $rows = db_all("SELECT body FROM lead_messages WHERE lead_id = :lead_id AND direction = 'inbound' ORDER BY created_at ASC, id ASC LIMIT 30", ['lead_id' => $leadId]);
            $referralContext = '';
            foreach ($rows as $row) {
                $body = trim((string) ($row['body'] ?? ''));
                if (preg_match('/\b(brother|sister|husband|wife|son|daughter|friend|patient)\b/i', $body)) {
                    $referralContext = $body;
                }
                if ($referralContext !== '' && preg_match('/(?:\+?1[\s.\-]?)?\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}/', $body)) {
                    return trim($referralContext . ($body !== $referralContext ? ' | ' . $body : ''));
                }
            }
        } catch (Throwable $e) {
            return '';
        }
        return '';
    }
}

if (!function_exists('lead_agent_recovered_scheduling_handoff_is_active')) {
    /** A recovered scheduling handoff is a state transition, not a recurring reminder. */
    function lead_agent_recovered_scheduling_handoff_is_active(array $lead): bool
    {
        return trim((string) ($lead['agent_status'] ?? '')) === 'ready_to_schedule'
            && trim((string) ($lead['scheduling_phase'] ?? '')) === 'awaiting_availability'
            && !empty($lead['human_takeover']);
    }
}

if (!function_exists('lead_agent_repair_scheduling_queue')) {
    /** Reconcile the Scheduling pipeline with durable conversation state. */
    function lead_agent_repair_scheduling_queue(int $limit = 200, bool $dryRun = false): array
    {
        lead_agent_ensure_schema();
        $limit = max(1, min(500, $limit));
        $rows = db_all("SELECT l.*, s.status AS agent_status, s.scheduling_phase, s.human_takeover, s.next_action_at AS agent_next_action
            FROM leads l
            LEFT JOIN lead_agent_states s ON s.lead_id = l.id
            WHERE l.status = 'in_contact'
              AND COALESCE(l.consultation_date, '') = ''
              AND COALESCE(l.consultation_status, '') NOT IN ('scheduled','booked','confirmed','completed')
            ORDER BY l.updated_at ASC, l.id ASC LIMIT {$limit}");
        $result = ['evaluated' => count($rows), 'closed' => 0, 'preferences_saved' => 0, 'handoffs_repaired' => 0, 'followups_repaired' => 0];
        foreach ($rows as $lead) {
            if (lead_agent_internal_or_test_record($lead)) {
                continue;
            }
            $leadId = (int) ($lead['id'] ?? 0);
            if (lead_agent_latest_inbound_closure_reason($leadId) !== '') {
                $result['closed']++;
                if (!$dryRun) {
                    lead_agent_pause($leadId, 'historical_explicit_decline', 'paused');
                    db_execute("UPDATE leads SET status = 'lost_lead', lost_reason = 'not_interested', follow_up_status = 'closed', next_follow_up_at = NULL, updated_at = NOW() WHERE id = :id LIMIT 1", ['id' => $leadId]);
                    lead_comm_insert_activity($leadId, 'lead_agent_historical_decline_repaired', 'Lead Agent removed this explicitly declined lead from Scheduling.', [], 'Lead Agent');
                }
                continue;
            }
            $referral = lead_agent_historical_referral_contact($leadId);
            if ($referral !== '') {
                $result['handoffs_repaired']++;
                if (!$dryRun && trim((string) ($lead['agent_status'] ?? '')) !== 'needs_attention') {
                    lead_agent_internal_handoff($lead, 'needs_attention', 'A third-party referral phone number needs to be transferred to the correct patient record.', [
                        'referral_message' => mb_substr($referral, 0, 300),
                    ]);
                }
                continue;
            }
            $preferences = lead_agent_historical_scheduling_preferences($leadId);
            if (!empty($preferences['has_preference'])) {
                $result['preferences_saved']++;
                if (!$dryRun) {
                    lead_agent_save_scheduling_preferences($leadId, $preferences);
                }
            }
            $agentStatus = trim((string) ($lead['agent_status'] ?? ''));
            if ($agentStatus === '') {
                continue; // Safe backfill enrolls it later in the same worker run.
            }
            $phase = trim((string) ($lead['scheduling_phase'] ?? ''));
            if (lead_agent_scheduling_preferences_complete($preferences)
                && in_array($agentStatus, ['active', 'engaged', 'ready_to_schedule'], true)
                && in_array($phase, ['', 'awaiting_preference', 'awaiting_availability'], true)) {
                $result['handoffs_repaired']++;
                if (!$dryRun) {
                    $handoffAlreadyActive = lead_agent_recovered_scheduling_handoff_is_active($lead);
                    $label = lead_agent_scheduling_preference_label($preferences);
                    db_execute("UPDATE lead_agent_states SET status = 'ready_to_schedule', human_takeover = 1, human_takeover_until = NULL, scheduling_phase = 'awaiting_availability', scheduling_context = :context, next_action_at = NULL, last_decision = 'historical_preference_repaired', updated_at = NOW() WHERE lead_id = :lead_id", [
                        'context' => substr($label, 0, 500), 'lead_id' => $leadId,
                    ]);
                    if ($handoffAlreadyActive) {
                        // Keep the original operator request usable without
                        // re-sending the same SMS and push every two days.
                        db_execute("UPDATE lead_agent_operator_requests
                            SET expires_at = DATE_ADD(NOW(), INTERVAL 2 DAY), updated_at = NOW()
                            WHERE lead_id = :lead_id AND request_type = 'availability' AND status = 'pending'
                            ORDER BY id DESC LIMIT 1", ['lead_id' => $leadId]);
                    } else {
                        lead_agent_internal_handoff($lead, 'ready_to_schedule', 'Recovered scheduling preferences from the existing conversation.', ['stage' => 'availability', 'preference' => $label]);
                    }
                }
                continue;
            }
            if ($agentStatus === 'ready_to_schedule' && in_array($phase, ['', 'awaiting_preference'], true)
                && !lead_agent_scheduling_preferences_complete($preferences)) {
                $result['followups_repaired']++;
                if (!$dryRun) {
                    $next = lead_agent_align_contact_time((new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->modify('+30 minutes'))->format('Y-m-d H:i:s');
                    db_execute("UPDATE lead_agent_states SET status = 'engaged', human_takeover = 0, human_takeover_until = NULL, scheduling_phase = 'awaiting_preference', next_action_at = :next_action_at, pause_reason = '', last_decision = 'incomplete_scheduling_handoff_repaired', updated_at = NOW() WHERE lead_id = :lead_id", ['next_action_at' => $next, 'lead_id' => $leadId]);
                    $agentStatus = 'engaged';
                }
            }
            $latestDirection = lead_agent_latest_patient_direction($leadId);
            if (in_array($agentStatus, ['active', 'engaged'], true) && (string) ($latestDirection['direction'] ?? '') === 'inbound') {
                $result['followups_repaired']++;
                if (!$dryRun) {
                    $latestInbound = lead_agent_latest_inbound_message($leadId);
                    if ($latestInbound) {
                        lead_agent_handle_inbound(
                            $leadId,
                            (string) ($latestInbound['body'] ?? ''),
                            (string) ($latestInbound['channel'] ?? 'sms'),
                            'historical-' . (string) ($latestInbound['channel'] ?? 'sms') . '-' . $leadId . '-' . (string) ($latestInbound['id'] ?? 0)
                        );
                    }
                }
                continue;
            }
            if (in_array($agentStatus, ['active', 'engaged'], true) && trim((string) ($lead['agent_next_action'] ?? '')) === '') {
                $result['followups_repaired']++;
                if (!$dryRun) {
                    $next = lead_agent_align_contact_time((new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->modify('+30 minutes'))->format('Y-m-d H:i:s');
                    db_execute("UPDATE lead_agent_states SET status = 'engaged', scheduling_phase = IF(scheduling_phase = '', 'awaiting_preference', scheduling_phase), next_action_at = :next_action_at, last_decision = 'scheduling_followup_repaired', updated_at = NOW() WHERE lead_id = :lead_id", ['next_action_at' => $next, 'lead_id' => $leadId]);
                }
            }
        }
        return $result;
    }
}

if (!function_exists('lead_agent_followup_context_reason')) {
    /** Guard follow-up with the current conversation, not cadence state alone. */
    function lead_agent_followup_context_reason(array $lead, array $state): string
    {
        $status = trim((string) ($state['status'] ?? ''));
        if (!in_array($status, ['active', 'engaged', 'nurture'], true)) {
            return 'conversation_owned_or_paused';
        }
        $schedulingPhase = trim((string) ($state['scheduling_phase'] ?? ''));
        if ($schedulingPhase !== '' && $schedulingPhase !== 'awaiting_preference') {
            return 'scheduling_in_progress';
        }
        if (in_array(trim((string) ($lead['follow_up_status'] ?? '')), ['ready_to_schedule', 'needs_attention'], true)) {
            return 'human_follow_up_state';
        }
        $latest = lead_agent_latest_patient_direction((int) ($lead['id'] ?? 0));
        if ((string) ($latest['direction'] ?? '') === 'inbound') {
            return 'unanswered_inbound';
        }
        return '';
    }
}

if (!function_exists('lead_agent_contextual_followup')) {
    function lead_agent_contextual_followup(array $lead, string $channel, int $step): ?array
    {
        $leadId = (int) ($lead['id'] ?? 0);
        $conversionMemory = $leadId > 0 ? lead_conversion_refresh($lead, $step) : [];
        $hasConversation = false;
        try {
            $hasConversation = ((int) db_value('SELECT COUNT(*) FROM lead_messages WHERE lead_id = :lead_id', ['lead_id' => $leadId])
                + (int) db_value('SELECT COUNT(*) FROM lead_emails WHERE lead_id = :lead_id', ['lead_id' => $leadId])) > 0;
        } catch (Throwable $e) {
            $hasConversation = false;
        }
        if (!$hasConversation) {
            return lead_agent_approved_followup($lead, $channel, $step) + ['draft_source' => 'approved_template'] + lead_agent_draft_conversion_meta([], $conversionMemory);
        }

        $strategyDraft = lead_agent_strategy_followup_draft($lead, $channel, $step, $conversionMemory);

        $leadAiPath = __DIR__ . '/lead_ai.php';
        if (is_file($leadAiPath)) {
            require_once $leadAiPath;
        }
        $instruction = 'Lead Agent instruction: Write the next natural follow-up after reading the complete patient_conversation. '
            . 'Continue from what was actually discussed; do not repeat a question already answered or introduce a fresh conversation. '
            . 'Ask no more than one question. Do not ask for a preferred day or time until the lead has expressed interest in scheduling. '
            . 'If the agent is waiting for a missing scheduling preference, ask only for the missing day or morning/afternoon preference. '
            . 'If the latest patient message still needs an answer, confirmed availability is being checked, appointment options were offered, or a staff member owns the thread, do not send.';
        $leadForAi = $lead;
        $leadForAi['notes'] = trim((string) ($lead['notes'] ?? '') . "\n\n" . $instruction);
        $operatorGuidance = lead_agent_instruction_guidance($lead, $channel);
        if ($operatorGuidance !== '') {
            $leadForAi['notes'] .= "\n\n" . $operatorGuidance;
        }
        if ($conversionMemory !== []) {
            $leadForAi['notes'] .= "\n\nConversion decision (never mention this internal analysis):\n"
                . 'Strategy: ' . (string) ($conversionMemory['strategy_key'] ?? '') . "\n"
                . 'Why: ' . (string) ($conversionMemory['strategy_reason'] ?? '') . "\n"
                . 'Next action: ' . (string) ($conversionMemory['recommended_action'] ?? '') . "\n"
                . 'Known goal: ' . (string) ($conversionMemory['treatment_goal'] ?? '') . "\n"
                . 'Known objection: ' . (string) ($conversionMemory['primary_objection'] ?? '') . "\n"
                . 'Language: ' . (string) ($conversionMemory['language'] ?? 'en');
        }
        $learned = lead_agent_cadence_learning_guidance($channel, 3);
        if ($learned !== []) {
            $guidance = array_values(array_filter(array_map(static fn(array $item): string => trim((string) ($item['guidance'] ?? '')), $learned)));
            if ($guidance !== []) {
                $leadForAi['notes'] .= "\n\nAggregated Lead Agent learning (never mention this to the lead):\n- " . implode("\n- ", $guidance);
            }
        }
        if ($channel === 'email' && function_exists('lead_ai_generate_email')) {
            $ai = lead_ai_generate_email($leadForAi, '', 'lead_agent_follow_up');
            $data = (array) ($ai['data'] ?? []);
            if (!empty($ai['ok']) && !empty($data['should_send']) && empty($data['needs_human_review']) && (float) ($data['confidence'] ?? 0) >= (float) ELITE_AI_MIN_CONFIDENCE) {
                return ['subject' => (string) ($data['subject'] ?? ''), 'body' => (string) ($data['body'] ?? ''), 'draft_source' => 'ai'] + lead_agent_draft_conversion_meta($data, $conversionMemory);
            }
            if (!empty($strategyDraft) && lead_agent_policy_flags((string) ($strategyDraft['subject'] ?? '') . ' ' . (string) ($strategyDraft['body'] ?? '')) === []) {
                return $strategyDraft + lead_agent_draft_conversion_meta($strategyDraft, $conversionMemory);
            }
            return lead_agent_safe_contextual_fallback($lead, $channel, $step) + lead_agent_draft_conversion_meta([], $conversionMemory);
        }
        if ($channel === 'sms' && function_exists('lead_ai_generate_reply')) {
            $ai = lead_ai_generate_reply($leadForAi, '', 'lead_agent_follow_up');
            $data = (array) ($ai['data'] ?? []);
            if (!empty($ai['ok']) && !empty($data['should_send']) && empty($data['needs_human_review']) && (float) ($data['confidence'] ?? 0) >= (float) ELITE_AI_MIN_CONFIDENCE) {
                return ['subject' => '', 'body' => (string) ($data['reply'] ?? ''), 'draft_source' => 'ai'] + lead_agent_draft_conversion_meta($data, $conversionMemory);
            }
            if (!empty($strategyDraft) && lead_agent_policy_flags((string) ($strategyDraft['body'] ?? '')) === []) {
                return $strategyDraft + lead_agent_draft_conversion_meta($strategyDraft, $conversionMemory);
            }
        }
        return lead_agent_safe_contextual_fallback($lead, $channel, $step) + lead_agent_draft_conversion_meta([], $conversionMemory);
    }
}

if (!function_exists('lead_agent_guardrail_reason')) {
    function lead_agent_guardrail_reason(array $lead, array $state, array $schedule): string
    {
        if (!empty($state['human_takeover'])) {
            return 'human_takeover';
        }
        if (lead_agent_latest_inbound_closure_reason((int) ($lead['id'] ?? 0)) !== '') {
            return 'conversation_closed';
        }
        $contextReason = lead_agent_followup_context_reason($lead, $state);
        if ($contextReason !== '') {
            return $contextReason;
        }
        $stage = trim((string) ($lead['status'] ?? ''));
        if (in_array($stage, ['opted_out', 'consultation_booked', 'consult_completed', 'treatment_accepted', 'treatment_completed', 'lost_lead'], true)) {
            return 'terminal_or_human_stage';
        }
        if (in_array(strtolower(trim((string) ($lead['consultation_status'] ?? ''))), ['scheduled', 'booked', 'confirmed', 'completed'], true)) {
            return 'terminal_or_human_stage';
        }
        if (trim((string) ($lead['consultation_date'] ?? '')) !== '') {
            return 'consultation_date_present';
        }
        if (lead_agent_sms_blocked($lead) && lead_agent_email_blocked($lead)) {
            $smsExplicitlyBlocked = in_array(strtolower(trim((string)($lead['sms_opt_status'] ?? ''))), ['dnd', 'opted_out'], true);
            $emailExplicitlyBlocked = in_array(strtolower(trim((string)($lead['email_opt_status'] ?? ''))), ['unsubscribed', 'opted_out'], true);
            return $smsExplicitlyBlocked && $emailExplicitlyBlocked ? 'all_channels_opted_out' : 'no_delivery_channel';
        }
        $now = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
        $today = $now->format('Y-m-d');
        $smsUnavailable = lead_agent_sms_blocked($lead) || lead_agent_recent_sms_delivery_issue((int)($lead['id'] ?? 0));
        $effectiveChannel = lead_agent_prioritize_first_two_day_sms(
            (string)($schedule['channel'] ?? ''),
            (string)($state['started_at'] ?? ''),
            lead_agent_daily_sms_outbound_count((int)($lead['id'] ?? 0), $today),
            $smsUnavailable,
            $now,
            lead_agent_latest_inbound_is_sms((int)($lead['id'] ?? 0))
        );
        if ($effectiveChannel === 'email' && function_exists('lead_email_automation_authentication_status')) {
            $authentication = lead_email_automation_authentication_status();
            if (empty($authentication['ready'])) {
                return 'email_authentication_hold';
            }
        }
        $hour = (int) $now->format('G');
        if ($hour < 9 || $hour >= 20) {
            return 'quiet_hours';
        }
        $max = lead_agent_daily_outbound_limit((string) ($state['started_at'] ?? ''));
        if (lead_agent_daily_outbound_count((int) $lead['id'], $today) >= $max) {
            return 'daily_cap';
        }
        return '';
    }
}

if (!function_exists('lead_agent_recover_drafting_exceptions')) {
    /**
     * Model/provider drafting failures are not human decisions. Requeue only
     * those historical exceptions; clinical, inbound, scheduling, consent,
     * and delivery exceptions remain untouched.
     */
    function lead_agent_recover_drafting_exceptions(int $limit = 100): int
    {
        lead_agent_ensure_schema();
        $limit = max(1, min(500, $limit));
        $consultationDateGuard = lead_agent_leads_has_column('consultation_date')
            ? " AND COALESCE(l.consultation_date, '') = ''"
            : '';
        $consultationStatusGuard = lead_agent_leads_has_column('consultation_status')
            ? " AND COALESCE(l.consultation_status, '') NOT IN ('scheduled','booked','confirmed','completed')"
            : '';
        $rows = db_all("SELECT s.lead_id
            FROM lead_agent_states s
            INNER JOIN leads l ON l.id = s.lead_id
            WHERE s.status = 'needs_attention'
              AND s.pause_reason IN ('Context-aware follow-up could not produce a safe message.', 'Cadence content failed a policy gate.')
              AND l.status NOT IN ('opted_out','consultation_booked','consult_completed','treatment_accepted','treatment_completed','lost_lead')
              {$consultationDateGuard}
              {$consultationStatusGuard}
            ORDER BY s.updated_at ASC LIMIT {$limit}");
        $recovered = 0;
        foreach ($rows as $row) {
            $leadId = (int) ($row['lead_id'] ?? 0);
            if ($leadId <= 0) {
                continue;
            }
            $next = lead_agent_align_contact_time(new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d H:i:s');
            $changed = db_execute("UPDATE lead_agent_states SET status = 'active', human_takeover = 0, human_takeover_until = NULL,
                    pause_reason = '', next_action_at = :next_action_at, last_decision = 'recovered_with_approved_fallback',
                    lock_token = '', locked_at = NULL, updated_at = NOW()
                WHERE lead_id = :lead_id AND status = 'needs_attention'
                  AND pause_reason IN ('Context-aware follow-up could not produce a safe message.', 'Cadence content failed a policy gate.')", [
                'next_action_at' => $next,
                'lead_id' => $leadId,
            ]);
            if ($changed > 0) {
                $recovered += $changed;
                lead_agent_event($leadId, 'drafting-exception-recovered-' . $leadId . '-' . date('YmdHis'), 'resumed', '', 'recorded', 'approved_fallback_available');
            }
        }
        return $recovered;
    }
}

if (!function_exists('lead_agent_monthly_email_due')) {
    /** Pure 30-day eligibility gate for low-frequency Nurture/Lost email. */
    function lead_agent_monthly_email_due(
        array $lead,
        string $lastSuccessfulEmailAt = '',
        string $conversationClosureReason = '',
        ?DateTimeImmutable $now = null
    ): bool {
        $status = strtolower(trim((string)($lead['status'] ?? '')));
        if (!in_array($status, ['no_answer', 'lost_lead'], true)
            || lead_agent_internal_or_test_record($lead)
            || lead_agent_email_blocked($lead)
            || trim($conversationClosureReason) !== '') {
            return false;
        }

        // A business-stage loss can remain marketable, but a wrong recipient,
        // explicit decline, or no-treatment record must never be reactivated.
        $lostReason = strtolower(trim((string)($lead['lost_reason'] ?? '')));
        if ($status === 'lost_lead' && in_array($lostReason, [
            'wrong_lead',
            'treatment_not_needed',
            'not_interested',
            'do_not_contact',
            'email_unsubscribe',
        ], true)) {
            return false;
        }
        if (trim((string)($lead['consultation_date'] ?? '')) !== ''
            || in_array(strtolower(trim((string)($lead['consultation_status'] ?? ''))), ['scheduled', 'booked', 'confirmed', 'completed'], true)) {
            return false;
        }

        $lastInbound = trim((string)($lead['last_inbound_at'] ?? ''));
        $lastOutbound = trim((string)($lead['last_outbound_at'] ?? ''));
        if ($lastInbound !== '' && strtotime($lastInbound) !== false
            && ($lastOutbound === '' || strtotime($lastOutbound) === false || strtotime($lastInbound) >= strtotime($lastOutbound))) {
            return false;
        }

        $anchor = trim($lastSuccessfulEmailAt);
        if ($anchor === '' || strtotime($anchor) === false) {
            $anchor = $status === 'lost_lead'
                ? trim((string)($lead['updated_at'] ?? ''))
                : trim((string)($lead['created_at'] ?? ''));
        }
        if ($anchor === '' || strtotime($anchor) === false) {
            return false;
        }

        $timezone = new DateTimeZone(APP_TIMEZONE);
        $now = $now ?? new DateTimeImmutable('now', $timezone);
        try {
            $lastEligibleTouch = new DateTimeImmutable($anchor, $timezone);
        } catch (Throwable $e) {
            return false;
        }
        return $lastEligibleTouch <= $now->modify('-30 days');
    }
}

if (!function_exists('lead_agent_monthly_email_template')) {
    /** Approved rotating copy; compliance footer and unsubscribe are added at send time. */
    function lead_agent_monthly_email_template(array $lead, int $rotation = 0): array
    {
        $rotation = (($rotation % 4) + 4) % 4;
        $first = lead_agent_first_name($lead);
        if (lead_language_is_spanish($lead)) {
            $hello = $first !== '' ? 'Hola ' . $first . ',' : 'Hola,';
            $subjects = [
                'Un paso sencillo para su sonrisa',
                'Qué esperar en Elite Smiles',
                'Mantenga abiertas sus opciones para la sonrisa',
                'Aquí estamos cuando sea el momento adecuado',
            ];
            $bodies = [
                'Cuando sea el momento adecuado, una consulta gratis puede ayudarle a entender sus opciones con claridad y sin presión. Puede responder a este correo si desea retomar sus metas para la sonrisa.',
                'Cada sonrisa es diferente. El Dr. Meden comienza por entender sus metas y revisar qué opciones podrían ser apropiadas antes de recomendar un próximo paso. Estamos disponibles cuando quiera continuar.',
                'No necesita decidir nada ahora. Si todavía está considerando un cambio en su sonrisa, podemos ayudarle a entender qué podría ser posible durante una consulta gratis.',
                'Solo queremos mantener la puerta abierta. Si el momento es mejor ahora, responda a este correo y continuaremos desde donde lo dejamos.',
            ];
            return [
                'subject' => $subjects[$rotation],
                'body' => $hello . "\n\n" . $bodies[$rotation] . "\n\nEl equipo de Elite Smiles",
            ];
        }

        $hello = $first !== '' ? 'Hi ' . $first . ',' : 'Hi,';
        $subjects = [
            'A simple next step for your smile',
            'What to expect at Elite Smiles',
            'Keeping your smile options open',
            'Here when the timing feels right',
        ];
        $bodies = [
            'When the timing feels right, a complimentary consultation can help you understand your options clearly and without pressure. You can reply to this email whenever you would like to revisit your smile goals.',
            'Every smile is different. Dr. Meden starts by understanding your goals and reviewing which options may be appropriate before recommending a next step. We are available whenever you would like to continue.',
            'You do not need to decide anything now. If you are still considering a change to your smile, we can help you understand what may be possible during a complimentary consultation.',
            'We simply wanted to keep the door open. If the timing is better now, reply to this email and we can continue from where we left off.',
        ];
        return [
            'subject' => $subjects[$rotation],
            'body' => $hello . "\n\n" . $bodies[$rotation] . "\n\nThe Elite Smiles Team",
        ];
    }
}

if (!function_exists('lead_agent_run_monthly_email_outreach')) {
    /**
     * Send at most a small daily batch of 30-day Nurture/Lost reactivation
     * emails. The last successful lead email is authoritative, so this never
     * adds a second email inside the same 30-day window.
     */
    function lead_agent_run_monthly_email_outreach(int $dailyLimit = 10, bool $dryRun = false): array
    {
        $dailyLimit = max(1, min(25, $dailyLimit));
        $now = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
        $result = [
            'due' => 0,
            'processed' => 0,
            'sent' => 0,
            'dry_run' => $dryRun,
            'reason' => '',
            'results' => [],
        ];
        $hour = (int)$now->format('G');
        if (!lead_agent_enabled() || lead_agent_mode() === 'off') {
            $result['reason'] = 'agent_disabled';
            return $result;
        }
        if ($hour < 9 || $hour >= 20) {
            $result['reason'] = 'quiet_hours';
            return $result;
        }
        if (function_exists('lead_email_automation_authentication_status')) {
            $authentication = lead_email_automation_authentication_status();
            if (empty($authentication['ready'])) {
                $result['reason'] = 'email_authentication_hold';
                return $result + ['authentication' => $authentication];
            }
        }
        if (function_exists('lead_email_ensure_schema')) {
            lead_email_ensure_schema();
        }

        $attemptedToday = $dryRun ? 0 : (int)db_value("SELECT COUNT(*) FROM lead_agent_events
            WHERE event_type IN ('monthly_email_reserved','monthly_email_sent','monthly_email_failed')
              AND DATE(created_at) = :day", ['day' => $now->format('Y-m-d')]);
        $remaining = max(0, $dailyLimit - $attemptedToday);
        if ($remaining < 1) {
            $result['reason'] = 'daily_batch_cap';
            return $result;
        }

        $candidateLimit = max($remaining, min(250, $remaining * 8));
        try {
            $candidates = db_all("SELECT l.*, email_history.last_successful_email_at
                FROM leads l
                LEFT JOIN (
                    SELECT lead_id, MAX(created_at) AS last_successful_email_at
                    FROM lead_emails
                    WHERE direction = 'outbound' AND LOWER(COALESCE(status, '')) IN ('sent','delivered','accepted')
                    GROUP BY lead_id
                ) email_history ON email_history.lead_id = l.id
                LEFT JOIN lead_agent_states hold_state ON hold_state.lead_id = l.id
                WHERE l.status IN ('no_answer','lost_lead')
                  AND COALESCE(hold_state.pause_reason, '') NOT IN (
                      'patient_requested_future_followup',
                      'patient_requested_future_followup_due',
                      'patient_requested_hold_reopened_by_inbound'
                  )
                ORDER BY COALESCE(email_history.last_successful_email_at, l.updated_at, l.created_at) ASC, l.id ASC
                LIMIT {$candidateLimit}");
        } catch (Throwable $e) {
            $result['reason'] = 'candidate_query_failed';
            return $result;
        }

        foreach ($candidates as $lead) {
            if ($result['processed'] >= $remaining) {
                break;
            }
            $leadId = (int)($lead['id'] ?? 0);
            if ($leadId <= 0) {
                continue;
            }
            $closureReason = trim((string)($lead['last_inbound_at'] ?? '')) !== ''
                ? lead_agent_latest_inbound_closure_reason($leadId)
                : '';
            if (!lead_agent_monthly_email_due(
                $lead,
                (string)($lead['last_successful_email_at'] ?? ''),
                $closureReason,
                $now
            )) {
                continue;
            }

            $result['due']++;
            $result['processed']++;
            $rotation = (((int)$now->format('Y') * 12) + (int)$now->format('n') + $leadId) % 4;
            $draft = lead_agent_monthly_email_template($lead, $rotation);
            $flags = lead_agent_policy_flags((string)$draft['subject'] . ' ' . (string)$draft['body']);
            if ($flags !== []) {
                $result['results'][] = ['lead_id' => $leadId, 'action' => 'skipped', 'reason' => 'monthly_email_policy_gate', 'channel' => 'email'];
                continue;
            }
            if ($dryRun || lead_agent_mode() === 'shadow') {
                $result['results'][] = ['lead_id' => $leadId, 'action' => 'would_send', 'reason' => 'monthly_lifecycle_email', 'channel' => 'email'];
                continue;
            }

            $eventKey = 'monthly-email-' . $leadId . '-' . $now->format('Ymd');
            if (!lead_agent_event($leadId, $eventKey, 'monthly_email_reserved', 'email', 'pending', 'monthly_lifecycle_email')) {
                $result['results'][] = ['lead_id' => $leadId, 'action' => 'skipped', 'reason' => 'duplicate_monthly_email', 'channel' => 'email'];
                continue;
            }
            $send = lead_agent_email_send($lead, (string)$draft['subject'], (string)$draft['body'], $eventKey);
            if (empty($send['ok'])) {
                db_execute("UPDATE lead_agent_events SET event_type = 'monthly_email_failed', status = 'failed', reason = :reason WHERE event_key = :event_key", [
                    'reason' => substr((string)($send['message'] ?? 'delivery_failed'), 0, 190),
                    'event_key' => $eventKey,
                ]);
                $result['results'][] = ['lead_id' => $leadId, 'action' => 'skipped', 'reason' => 'monthly_email_delivery_failed', 'channel' => 'email'];
                continue;
            }

            db_execute("UPDATE lead_agent_events SET event_type = 'monthly_email_sent', status = 'sent', reason = 'monthly_lifecycle_email' WHERE event_key = :event_key", ['event_key' => $eventKey]);
            lead_agent_record_touchpoint($lead, $eventKey, 'email', 0, 'monthly_lifecycle_email', $send);
            lead_agent_record_learning('monthly_reactivation', 'email', 'sent');
            $result['sent']++;
            $result['results'][] = ['lead_id' => $leadId, 'action' => 'sent', 'reason' => 'monthly_lifecycle_email', 'channel' => 'email'];
        }
        return $result;
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
            $decisionType = 'deferred';
            $nextActionAt = null;
            if ($reason === 'no_delivery_channel') {
                lead_agent_pause($leadId, $reason, 'paused');
                lead_lifecycle_transition_status(
                    $leadId,
                    'no_answer',
                    'No deliverable contact channel remains; lead moved to Nurture without another send.',
                    'lead_agent_guardrail',
                    ['new_lead', 'attempted_contact', 'contacted', 'in_contact', 'no_answer', '']
                );
                lead_agent_reconcile_unreachable_contact($leadId, 'lead_agent_guardrail');
                $decisionType = 'paused';
            } elseif (in_array($reason, ['terminal_or_human_stage', 'consultation_date_present', 'conversation_closed', 'all_channels_opted_out', 'human_takeover', 'conversation_owned_or_paused', 'scheduling_in_progress', 'human_follow_up_state', 'unanswered_inbound'], true)) {
                lead_agent_pause($leadId, $reason, $reason === 'all_channels_opted_out' ? 'opted_out' : 'paused');
                $decisionType = 'paused';
            } else {
                $deferOneDay = in_array($reason, ['daily_cap', 'email_authentication_hold'], true);
                $deferred = lead_agent_align_contact_time((new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))->modify($deferOneDay ? '+1 day' : '+1 hour'));
                $nextActionAt = $deferred->format('Y-m-d H:i:s');
                db_execute('UPDATE lead_agent_states SET next_action_at = :next_action_at, last_decision = :decision, lock_token = \'\', locked_at = NULL, updated_at = NOW() WHERE lead_id = :lead_id', [
                    'next_action_at' => $nextActionAt, 'decision' => 'deferred_' . $reason, 'lead_id' => $leadId,
                ]);
            }
            lead_agent_event($leadId, 'decision-' . $leadId . '-' . $reason . '-' . date('YmdHi'), $decisionType, '', 'recorded', $reason, ['next_action_at' => $nextActionAt]);
            return ['lead_id' => $leadId, 'action' => 'skipped', 'reason' => $reason];
        }

        $smsUnavailable = lead_agent_sms_blocked($lead) || lead_agent_recent_sms_delivery_issue($leadId);
        $emailUnavailable = lead_agent_email_blocked($lead);
        if ($smsUnavailable && $emailUnavailable) {
            lead_agent_pause($leadId, 'no_delivery_channel', 'paused');
            lead_lifecycle_transition_status(
                $leadId,
                'no_answer',
                'No deliverable contact channel remains; lead moved to Nurture without another send.',
                'lead_agent_delivery_route',
                ['new_lead', 'attempted_contact', 'contacted', 'in_contact', 'no_answer', '']
            );
            lead_agent_reconcile_unreachable_contact($leadId, 'lead_agent_delivery_route');
            return ['lead_id' => $leadId, 'action' => 'paused', 'reason' => 'no_delivery_channel'];
        }

        $channel = lead_agent_prioritize_first_two_day_sms(
            (string)$schedule['channel'],
            (string)($state['started_at'] ?? ''),
            lead_agent_daily_sms_outbound_count($leadId, date('Y-m-d')),
            $smsUnavailable,
            null,
            lead_agent_latest_inbound_is_sms($leadId)
        );
        if ($channel === 'sms' && $smsUnavailable) {
            $channel = 'email';
        }
        if ($channel === 'email' && $emailUnavailable) {
            $channel = 'sms';
        }

        $eventKey = 'cadence-' . $leadId . '-' . $nextStep;
        $draft = lead_agent_contextual_followup($lead, $channel, $nextStep);
        if ($draft === null) {
            $draft = lead_agent_safe_contextual_fallback($lead, $channel, $nextStep);
        }
        $draftSource = (string) ($draft['draft_source'] ?? 'ai');
        $flags = lead_agent_policy_flags((string) ($draft['subject'] ?? '') . ' ' . (string) ($draft['body'] ?? ''));
        if ($flags !== [] && $draftSource === 'ai') {
            $draft = lead_agent_safe_contextual_fallback($lead, $channel, $nextStep);
            $draftSource = 'approved_fallback';
            $flags = lead_agent_policy_flags((string) ($draft['subject'] ?? '') . ' ' . (string) ($draft['body'] ?? ''));
        }
        if ($flags !== []) {
            lead_agent_internal_handoff($lead, 'needs_attention', 'Cadence content failed a policy gate.');
            return ['lead_id' => $leadId, 'action' => 'handoff', 'reason' => 'policy_gate', 'flags' => $flags];
        }
        if (in_array($draftSource, ['approved_fallback', 'approved_template'], true)) {
            lead_agent_event($leadId, $eventKey . '-draft-' . date('YmdHis'), 'draft_fallback_used', $channel, 'recorded', $draftSource, ['step' => $nextStep]);
        }
        if ($dryRun || lead_agent_mode() === 'shadow') {
            lead_agent_event($leadId, $eventKey . '-shadow-' . date('YmdHi'), 'shadow_cadence', $channel, 'would_send', (string) $schedule['phase'], ['step' => $nextStep] + lead_agent_draft_conversion_meta($draft));
            db_execute('UPDATE lead_agent_states SET lock_token = \'\', locked_at = NULL, last_decision = :decision, updated_at = NOW() WHERE lead_id = :lead_id', [
                'decision' => 'shadow_would_send_step_' . $nextStep, 'lead_id' => $leadId,
            ]);
            return ['lead_id' => $leadId, 'action' => 'would_send', 'channel' => $channel, 'step' => $nextStep] + lead_agent_draft_conversion_meta($draft);
        }

        if (!lead_agent_event($leadId, $eventKey, 'cadence_reserved', $channel, 'pending', (string) $schedule['phase'], ['step' => $nextStep])) {
            return ['lead_id' => $leadId, 'action' => 'skipped', 'reason' => 'duplicate_event'];
        }
        $send = $channel === 'email'
            ? lead_agent_email_send($lead, (string) $draft['subject'], (string) $draft['body'], $eventKey)
            : lead_agent_sms_send($lead, (string) $draft['body'], $eventKey);
        $smsFailureNeedsAttention = $channel === 'sms'
            && empty($send['ok'])
            && !empty($send['requires_attention']);
        if (empty($send['ok'])) {
            db_execute("UPDATE lead_agent_events SET event_type = 'cadence_failed', status = 'failed', reason = :reason WHERE event_key = :event_key", [
                'reason' => substr((string) ($send['message'] ?? 'delivery_failed'), 0, 190), 'event_key' => $eventKey,
            ]);
            lead_agent_record_learning('cadence_followup', $channel, 'delivery_failed');
            $alternate = $channel === 'sms' ? 'email' : 'sms';
            $alternateBlocked = $alternate === 'sms'
                ? (lead_agent_sms_blocked($lead) || lead_agent_recent_sms_delivery_issue($leadId))
                : lead_agent_email_blocked($lead);
            if (!$alternateBlocked) {
                $alternateKey = $eventKey . '-failover-' . $alternate;
                $alternateDraft = lead_agent_safe_contextual_fallback($lead, $alternate, $nextStep);
                if (lead_agent_policy_flags((string) ($alternateDraft['subject'] ?? '') . ' ' . (string) ($alternateDraft['body'] ?? '')) === []
                    && lead_agent_event($leadId, $alternateKey, 'cadence_reserved', $alternate, 'pending', 'provider_failover', ['step' => $nextStep, 'failed_channel' => $channel])) {
                    $alternateSend = $alternate === 'email'
                        ? lead_agent_email_send($lead, (string) ($alternateDraft['subject'] ?? 'Whenever you are ready'), (string) $alternateDraft['body'], $alternateKey)
                        : lead_agent_sms_send($lead, (string) $alternateDraft['body'], $alternateKey);
                    if (!empty($alternateSend['ok'])) {
                        $channel = $alternate;
                        $eventKey = $alternateKey;
                        $draft = $alternateDraft;
                        $draftSource = 'provider_failover';
                        $send = $alternateSend;
                    } else {
                        db_execute("UPDATE lead_agent_events SET event_type = 'cadence_failed', status = 'failed', reason = :reason WHERE event_key = :event_key", [
                            'reason' => substr((string) ($alternateSend['message'] ?? 'delivery_failed'), 0, 190),
                            'event_key' => $alternateKey,
                        ]);
                    }
                }
            }
            if (empty($send['ok'])) {
                lead_agent_internal_handoff($lead, 'needs_attention', 'Automated follow-up failed on every consented delivery channel.');
                return ['lead_id' => $leadId, 'action' => 'handoff', 'reason' => 'delivery_failed_all_channels'];
            }
        }

        if ($smsFailureNeedsAttention) {
            db_execute("UPDATE lead_agent_events SET event_type = 'cadence_sent', status = 'sent', reason = 'provider_failover_needs_attention' WHERE event_key = :event_key", ['event_key' => $eventKey]);
            lead_agent_record_touchpoint($lead, $eventKey, $channel, $nextStep, (string) $schedule['phase'], $send + lead_agent_draft_conversion_meta($draft));
            lead_agent_record_learning('cadence_followup', $channel, 'provider_failover_sent');
            lead_agent_sync_crm_followup_schedule($leadId);
            return [
                'lead_id' => $leadId,
                'action' => 'handoff',
                'reason' => 'sms_delivery_failed_needs_attention',
                'fallback_channel' => $channel,
                'step' => $nextStep,
            ] + lead_agent_draft_conversion_meta($draft);
        }

        // A cadence step is one coordinated outreach touch. Keep SMS and email
        // on the same clock so a lead with both consented channels is not
        // silently skipped on email every other step.
        $pairedChannel = $channel === 'sms' ? 'email' : 'sms';
        $pairedBlocked = $pairedChannel === 'sms'
            ? (lead_agent_sms_blocked($lead) || lead_agent_recent_sms_delivery_issue($leadId))
            : lead_agent_email_blocked($lead);
        if (!$pairedBlocked && !empty($send['ok'])) {
            $pairedKey = 'cadence-' . $leadId . '-' . $nextStep . '-' . $pairedChannel;
            if (lead_agent_event($leadId, $pairedKey, 'cadence_reserved', $pairedChannel, 'pending', (string) $schedule['phase'], ['step' => $nextStep, 'paired_with' => $eventKey])) {
                $pairedDraft = lead_agent_contextual_followup($lead, $pairedChannel, $nextStep);
                if ($pairedDraft === null) {
                    $pairedDraft = lead_agent_safe_contextual_fallback($lead, $pairedChannel, $nextStep);
                }
                $pairedFlags = lead_agent_policy_flags((string) ($pairedDraft['subject'] ?? '') . ' ' . (string) ($pairedDraft['body'] ?? ''));
                if ($pairedFlags === []) {
                    $pairedSend = $pairedChannel === 'email'
                        ? lead_agent_email_send($lead, (string) ($pairedDraft['subject'] ?? 'Whenever you are ready'), (string) ($pairedDraft['body'] ?? ''), $pairedKey)
                        : lead_agent_sms_send($lead, (string) ($pairedDraft['body'] ?? ''), $pairedKey);
                    if (!empty($pairedSend['ok'])) {
                        db_execute("UPDATE lead_agent_events SET event_type = 'cadence_sent', status = 'sent', reason = 'delivered_to_provider' WHERE event_key = :event_key", ['event_key' => $pairedKey]);
                        lead_agent_record_touchpoint($lead, $pairedKey, $pairedChannel, $nextStep, (string) $schedule['phase'], $pairedSend + lead_agent_draft_conversion_meta($pairedDraft));
                        lead_agent_record_learning('cadence_followup', $pairedChannel, (string) ($pairedDraft['draft_source'] ?? 'paired_channel') . '_sent');
                    } else {
                        db_execute("UPDATE lead_agent_events SET event_type = 'cadence_failed', status = 'failed', reason = :reason WHERE event_key = :event_key", [
                            'reason' => substr((string) ($pairedSend['message'] ?? 'paired_delivery_failed'), 0, 190),
                            'event_key' => $pairedKey,
                        ]);
                        lead_agent_record_learning('cadence_followup', $pairedChannel, 'delivery_failed');
                    }
                } else {
                    db_execute("UPDATE lead_agent_events SET event_type = 'cadence_failed', status = 'failed', reason = 'paired_content_policy_gate' WHERE event_key = :event_key", ['event_key' => $pairedKey]);
                }
            }
        }

        $following = lead_agent_step_schedule((string) $state['started_at'], $nextStep + 1);
        if (strtotime((string) $following['at']) <= time()) {
            $following = lead_agent_incremental_schedule(now(), $nextStep);
        }
        $activeStepCount = count(lead_agent_cadence_plan());
        $nextStateStatus = $nextStep >= $activeStepCount || (string)($state['status'] ?? '') === 'nurture' ? 'nurture' : 'active';
        db_execute("UPDATE lead_agent_states SET status = :status, cadence_step = :step, last_action_at = NOW(), next_action_at = :next_action_at, last_decision = :decision, lock_token = '', locked_at = NULL, updated_at = NOW() WHERE lead_id = :lead_id", [
            'status' => $nextStateStatus,
            'step' => $nextStep,
            'next_action_at' => $following['at'],
            'decision' => 'sent_step_' . $nextStep,
            'lead_id' => $leadId,
        ]);
        db_execute("UPDATE lead_agent_events SET event_type = 'cadence_sent', status = 'sent', reason = 'delivered_to_provider' WHERE event_key = :event_key", ['event_key' => $eventKey]);
        lead_agent_record_touchpoint($lead, $eventKey, $channel, $nextStep, (string) $schedule['phase'], $send + lead_agent_draft_conversion_meta($draft));
        lead_agent_record_learning('cadence_followup', $channel, $draftSource . '_sent');
        if ($nextStep === $activeStepCount) {
            lead_lifecycle_transition_status(
                $leadId,
                'no_answer',
                'The six-day active follow-up sprint ended without a reply; twice-weekly Nurture continues.',
                'lead_agent_cadence',
                ['new_lead', 'attempted_contact', 'contacted', 'in_contact', '']
            );
        }
        lead_agent_sync_crm_followup_schedule($leadId);
        return ['lead_id' => $leadId, 'action' => 'sent', 'channel' => $channel, 'step' => $nextStep, 'next_action_at' => $following['at'], 'draft_source' => $draftSource, 'lifecycle_stage' => $nextStateStatus === 'nurture' ? 'nurture' : 'active_follow_up'] + lead_agent_draft_conversion_meta($draft);
    }
}

if (!function_exists('lead_agent_run_due')) {
    function lead_agent_run_due(int $limit = 20, bool $dryRun = false): array
    {
        lead_agent_ensure_schema();
        $latestBeforeRun = lead_agent_latest_run();
        $staleAlert = $dryRun ? ['sent' => false, 'reason' => 'dry_run'] : lead_agent_maybe_alert_stale_run($latestBeforeRun);
        if (lead_agent_is_globally_paused()) {
            return ['ok' => true, 'paused' => true, 'mode' => lead_agent_mode(), 'dry_run' => $dryRun, 'processed' => 0, 'results' => []];
        }
        if (!$dryRun) {
            lead_agent_prune_retention();
            lead_agent_backfill_touchpoints(5000);
            lead_agent_refresh_cadence_learning(30);
            lead_agent_release_expired_human_takeovers();
            lead_agent_release_due_patient_holds();
        }
        $limit = max(1, min(50, $limit));
        $run = lead_agent_run_start($dryRun);
        $lifecycle = [];
        $backfill = [];
        $coverageRepair = [];
        $repairedCatchup = 0;
        $repairedFirstDay = 0;
        $repairedSlowSprint = 0;
        $recoveredDraftingExceptions = 0;
        $conversionMemoriesRefreshed = 0;
        $schedulingRepair = [];
        $monthlyEmail = [];
        $dueCount = 0;
        $results = [];
        try {
            $lifecycle = lead_agent_reconcile_lifecycle(500, $dryRun);
            $backfill = lead_agent_backfill_eligible(200, $dryRun);
            $coverageRepair = lead_agent_repair_cycle_coverage(500, $dryRun);
            $schedulingRepair = lead_agent_repair_scheduling_queue(200, $dryRun);
            $conversionMemoriesRefreshed = $dryRun ? 0 : lead_conversion_refresh_active_memories(40);
            $recoveredDraftingExceptions = $dryRun ? 0 : lead_agent_recover_drafting_exceptions(100);
            $repairedFirstDay = $dryRun ? 0 : lead_agent_repair_first_day_schedule(200);
            $repairedSlowSprint = $dryRun ? 0 : lead_agent_repair_slow_active_sprint(500);
            $repairedCatchup = $dryRun ? 0 : lead_agent_repair_compressed_catchup();
            $rows = db_all("SELECT * FROM lead_agent_states
                WHERE status IN ('active', 'engaged', 'nurture')
                  AND human_takeover = 0
                  AND next_action_at IS NOT NULL
                  AND next_action_at <= NOW()
                  AND (locked_at IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))
                ORDER BY next_action_at ASC, id ASC
                LIMIT {$limit}");
            $dueCount = count($rows);

            foreach ($rows as $row) {
                $leadId = (int) ($row['lead_id'] ?? 0);
                $token = bin2hex(random_bytes(16));
                $locked = db_execute("UPDATE lead_agent_states SET lock_token = :token, locked_at = NOW(), updated_at = NOW()
                    WHERE lead_id = :lead_id AND (locked_at IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))", [
                    'token' => $token, 'lead_id' => $leadId,
                ]);
                if ($locked < 1) continue;
                $fresh = db_one('SELECT * FROM lead_agent_states WHERE lead_id = :lead_id AND lock_token = :token LIMIT 1', ['lead_id' => $leadId, 'token' => $token]);
                if (!$fresh) continue;
                try {
                    $results[] = lead_agent_process_state($fresh, $dryRun);
                } catch (Throwable $e) {
                    db_execute("UPDATE lead_agent_states SET lock_token = '', locked_at = NULL, last_decision = 'worker_error', updated_at = NOW() WHERE lead_id = :lead_id", ['lead_id' => $leadId]);
                    lead_agent_event($leadId, 'worker-error-' . $leadId . '-' . date('YmdHis'), 'worker_error', '', 'failed', 'worker_exception');
                    esm_log('lead_agent', 'Lead agent worker failed.', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
                    $results[] = ['lead_id' => $leadId, 'action' => 'error', 'reason' => 'worker_exception'];
                }
            }
            $monthlyEmail = lead_agent_run_monthly_email_outreach(10, $dryRun);
            $dueCount += (int)($monthlyEmail['due'] ?? 0);
            $results = array_merge($results, (array)($monthlyEmail['results'] ?? []));
            if (!$dryRun) {
                lead_agent_sync_crm_followup_schedule();
            }
            lead_agent_run_finish($run, 'completed', $dueCount, $results, $backfill, $repairedCatchup + $repairedFirstDay + $repairedSlowSprint);
        } catch (Throwable $e) {
            lead_agent_run_finish($run, 'failed', $dueCount, $results, $backfill, $repairedCatchup + $repairedFirstDay + $repairedSlowSprint, $e->getMessage());
            throw $e;
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
        return ['ok' => true, 'run_id' => (int)$run['id'], 'mode' => lead_agent_mode(), 'dry_run' => $dryRun, 'stale_alert' => $staleAlert, 'lifecycle' => $lifecycle, 'backfill' => $backfill, 'coverage_repair' => $coverageRepair, 'scheduling_repair' => $schedulingRepair, 'monthly_email' => $monthlyEmail, 'conversion_memories_refreshed' => $conversionMemoriesRefreshed, 'recovered_drafting_exceptions' => $recoveredDraftingExceptions, 'repaired_first_day' => $repairedFirstDay, 'repaired_slow_sprint' => $repairedSlowSprint, 'repaired_catchup' => $repairedCatchup, 'due' => $dueCount, 'processed' => count($results), 'results' => $results];
    }
}

if (!function_exists('lead_agent_daily_metrics')) {
    function lead_agent_lead_is_already_scheduled(array $lead): bool
    {
        return in_array(trim((string) ($lead['status'] ?? '')), ['consultation_booked', 'consult_completed', 'treatment_accepted', 'treatment_completed'], true)
            || in_array(trim((string) ($lead['consultation_status'] ?? '')), ['scheduled', 'booked', 'confirmed', 'completed'], true)
            || trim((string) ($lead['consultation_date'] ?? '')) !== '';
    }

    function lead_agent_daily_metrics(string $date): array
    {
        lead_agent_ensure_schema();
        lead_agent_close_scheduling_handoff();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Invalid report date.');
        }
        $eventCount = static function (string $where, array $params = []) use ($date): int {
            return (int) db_value("SELECT COUNT(*) FROM lead_agent_events WHERE DATE(created_at) = :report_date AND {$where}", ['report_date' => $date] + $params);
        };
        $eventLeads = static function (string $where) use ($date): array {
            $consultationStatusSelect = lead_agent_leads_has_column('consultation_status') ? 'l.consultation_status' : "'' AS consultation_status";
            $consultationDateSelect = lead_agent_leads_has_column('consultation_date') ? 'l.consultation_date' : "'' AS consultation_date";
            return db_all("SELECT l.id, l.full_name, l.status, {$consultationStatusSelect}, {$consultationDateSelect}
                FROM lead_agent_events e
                INNER JOIN leads l ON l.id = e.lead_id
                WHERE DATE(e.created_at) = :report_date AND {$where}
                GROUP BY l.id, l.full_name
                ORDER BY MAX(e.created_at) DESC, l.id DESC", ['report_date' => $date]);
        };
        $schedulingLeads = array_values(array_filter(
            $eventLeads("e.event_type = 'handoff' AND (e.reason LIKE '%scheduling%' OR e.reason LIKE 'Lead selected an appointment option%' OR e.reason LIKE 'Lead selected an appointment option and supplied DOB.%')"),
            static fn(array $lead): bool => !lead_agent_lead_is_already_scheduled($lead)
        ));
        $exceptionLeads = $eventLeads("e.event_type = 'handoff'
            AND e.reason NOT LIKE '%scheduling%'
            AND e.reason NOT LIKE 'Lead selected an appointment option%'
            AND e.reason NOT LIKE 'Lead selected an appointment option and supplied DOB.%'
            AND e.reason NOT IN ('Context-aware follow-up could not produce a safe message.', 'Cadence content failed a policy gate.')");
        $metrics = [
            'enrolled' => $eventCount("event_type = 'enrolled'"),
            'cadence_sent' => $eventCount("event_type IN ('cadence_reserved', 'cadence_sent') AND status = 'sent'"),
            'automatic_replies' => $eventCount("event_type = 'automatic_reply' AND status = 'sent'"),
            'sms_sent' => $eventCount("event_type IN ('cadence_reserved', 'cadence_sent', 'automatic_reply') AND status = 'sent' AND channel = 'sms'"),
            'emails_sent' => $eventCount("((event_type IN ('cadence_reserved', 'cadence_sent', 'automatic_reply') AND channel = 'email') OR event_type = 'monthly_email_sent') AND status = 'sent'"),
            'inbound_handled' => $eventCount("event_type = 'inbound_classified'"),
            // These are people, not event totals. A lead can generate more than one
            // handoff event during the same conversation and must only be counted once.
            'ready_to_schedule_today' => count($schedulingLeads),
            'needs_attention_today' => count($exceptionLeads),
            'scheduling_leads' => $schedulingLeads,
            'exception_leads' => $exceptionLeads,
            'policy_blocks' => $eventCount("event_type = 'handoff' AND reason LIKE '%policy%'"),
            'delivery_failures' => $eventCount("event_type = 'handoff' AND reason LIKE '%deliver%'"),
            'deferred_today' => $eventCount("event_type = 'deferred'"),
            'paused_today' => $eventCount("event_type = 'paused'"),
            'worker_errors_today' => $eventCount("event_type = 'worker_error'"),
            'approved_fallbacks_today' => $eventCount("event_type = 'draft_fallback_used'"),
            'learning_observations' => $eventCount("event_type = 'inbound_classified'"),
            'active_now' => (int) db_value("SELECT COUNT(*) FROM lead_agent_states WHERE status IN ('active', 'engaged') AND human_takeover = 0"),
            'ready_to_schedule_now' => (int) db_value("SELECT COUNT(*) FROM lead_agent_states s
                INNER JOIN leads l ON l.id = s.lead_id
                WHERE s.status = 'ready_to_schedule'
                  AND NOT " . lead_agent_scheduled_sql_condition('l')),
            'needs_attention_now' => (int) db_value("SELECT COUNT(*) FROM lead_agent_states
                WHERE status = 'needs_attention'
                  AND COALESCE(last_decision, '') <> 'sms_delivery_failed_needs_attention'"),
            'overdue_now' => (int) db_value("SELECT COUNT(*) FROM lead_agent_states WHERE status IN ('active', 'engaged') AND human_takeover = 0 AND next_action_at IS NOT NULL AND next_action_at < NOW()"),
            'oldest_overdue_minutes' => (int) db_value("SELECT COALESCE(MAX(TIMESTAMPDIFF(MINUTE, next_action_at, NOW())), 0) FROM lead_agent_states WHERE status IN ('active', 'engaged') AND human_takeover = 0 AND next_action_at IS NOT NULL AND next_action_at < NOW()"),
        ];
        $metrics['outbound_total'] = $metrics['sms_sent'] + $metrics['emails_sent'];
        $metrics['actions_completed'] = $metrics['outbound_total'] + $metrics['inbound_handled'];
        return $metrics;
    }
}

if (!function_exists('lead_agent_report_copy')) {
    function lead_agent_report_lead_names(array $rows): string
    {
        $names = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['full_name'] ?? ''));
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        if (count($names) < 2) {
            return $names[0] ?? '';
        }
        if (count($names) === 2) {
            return $names[0] . ' and ' . $names[1];
        }
        $last = array_pop($names);
        return implode(', ', $names) . ', and ' . $last;
    }

    function lead_agent_report_copy(string $date, array $metrics): array
    {
        $label = (new DateTimeImmutable($date, new DateTimeZone(APP_TIMEZONE)))->format('F j');
        $textLabel = (int) $metrics['sms_sent'] === 1 ? 'text' : 'texts';
        $emailLabel = (int) $metrics['emails_sent'] === 1 ? 'email' : 'emails';
        $inboundLabel = (int) $metrics['inbound_handled'] === 1 ? 'conversation' : 'conversations';
        $summary = $metrics['actions_completed'] > 0
            ? "Lead Agent completed {$metrics['actions_completed']} communication actions on {$label}: {$metrics['sms_sent']} {$textLabel}, {$metrics['emails_sent']} {$emailLabel}, and {$metrics['inbound_handled']} inbound {$inboundLabel} reviewed."
            : "Lead Agent recorded no communication actions on {$label}. The system remained available and monitored enrolled leads.";
        $approvedFallbacks = (int) ($metrics['approved_fallbacks_today'] ?? 0);
        if ($approvedFallbacks > 0) {
            $summary .= " {$approvedFallbacks} follow-up" . ($approvedFallbacks === 1 ? ' used' : 's used')
                . ' approved fallback copy because personalized AI drafting was unavailable.';
        }
        if ($metrics['ready_to_schedule_today'] > 0) {
            $names = lead_agent_report_lead_names((array) ($metrics['scheduling_leads'] ?? []));
            $summary .= " {$metrics['ready_to_schedule_today']} lead" . ($metrics['ready_to_schedule_today'] === 1 ? ' is' : 's are') . ' ready for Rod to schedule'
                . ($names !== '' ? ": {$names}." : '.');
        }
        if ($metrics['needs_attention_today'] > 0) {
            $names = lead_agent_report_lead_names((array) ($metrics['exception_leads'] ?? []));
            $summary .= " {$metrics['needs_attention_today']} exception" . ($metrics['needs_attention_today'] === 1 ? ' requires' : 's require') . ' human judgment'
                . ($names !== '' ? ": {$names}." : '.');
        } else {
            $summary .= ' No new exception required human judgment.';
        }
        $overdueNow = (int)($metrics['overdue_now'] ?? 0);
        $workerErrors = (int)($metrics['worker_errors_today'] ?? 0);
        if ($overdueNow > 0) {
            $summary .= " {$overdueNow} automated follow-up" . ($overdueNow === 1 ? ' is' : 's are') . ' currently overdue and queued for the next run.';
        }
        if ($workerErrors > 0) {
            $summary .= " {$workerErrors} worker error" . ($workerErrors === 1 ? ' was' : 's were') . ' recorded today.';
        }
        $review = "Yesterday the agent handled {$metrics['inbound_handled']} inbound conversation" . ($metrics['inbound_handled'] === 1 ? '' : 's')
            . " and sent {$metrics['outbound_total']} approved follow-up" . ($metrics['outbound_total'] === 1 ? '' : 's') . '.';
        $schedulingNames = lead_agent_report_lead_names((array) ($metrics['scheduling_leads'] ?? []));
        $review .= $metrics['ready_to_schedule_today'] > 0
            ? " Start with the {$metrics['ready_to_schedule_today']} scheduling handoff" . ($metrics['ready_to_schedule_today'] === 1 ? '' : 's') . ($schedulingNames !== '' ? ": {$schedulingNames}." : '.')
            : ' There are no new scheduling handoffs from that day.';
        $exceptionNames = lead_agent_report_lead_names((array) ($metrics['exception_leads'] ?? []));
        $review .= $metrics['needs_attention_today'] > 0
            ? " Then review {$metrics['needs_attention_today']} agent exception" . ($metrics['needs_attention_today'] === 1 ? '' : 's') . ($exceptionNames !== '' ? ": {$exceptionNames}." : '.')
            : ' The agent completed its work without a new exception.';
        return ['executive_summary' => $summary, 'morning_review' => $review];
    }
}

if (!function_exists('lead_agent_linked_report_text')) {
    function lead_agent_linked_report_text(string $text, array $metrics, string $className = ''): string
    {
        $leadMap = [];
        foreach (['scheduling_leads', 'exception_leads'] as $key) {
            foreach ((array) ($metrics[$key] ?? []) as $lead) {
                $leadId = (int) ($lead['id'] ?? 0);
                $name = trim((string) ($lead['full_name'] ?? ''));
                if ($leadId > 0 && $name !== '') {
                    $leadMap[$name] = $leadId;
                }
            }
        }
        if ($leadMap === []) {
            return e($text);
        }
        uksort($leadMap, static fn(string $left, string $right): int => strlen($right) <=> strlen($left));
        $parts = preg_split('/(' . implode('|', array_map(static fn(string $name): string => preg_quote($name, '/'), array_keys($leadMap))) . ')/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return e($text);
        }
        $html = '';
        foreach ($parts as $part) {
            if (array_key_exists($part, $leadMap)) {
                $html .= '<a href="' . e(base_url('leads.php?lead_id=' . $leadMap[$part])) . '" class="' . e($className) . '">' . e($part) . '</a>';
            } else {
                $html .= e($part);
            }
        }
        return $html;
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
              AND NOT (e.event_type = 'cadence_reserved' AND e.status = 'pending')
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
              AND COALESCE(s.last_decision, '') <> 'sms_delivery_failed_needs_attention'
            ORDER BY COALESCE(s.handoff_notified_at, s.updated_at) DESC LIMIT {$limit}");
        foreach ($rows as &$lead) {
            $reason = trim((string) ($lead['agent_attention_reason'] ?? '')) ?: 'Lead Agent cannot safely determine the next step.';
            if ($reason === 'patient_requested_future_followup_due') {
                $reason = 'The patient-requested hold has ended. Review the conversation before contacting this lead.';
            } elseif ($reason === 'patient_requested_hold_reopened_by_inbound') {
                $reason = 'The patient replied before the hold date. Review the new message before responding.';
            }
            $isSmsDeliveryFailure = str_contains(strtolower($reason), 'sms delivery failed');
            $lead['_action_queue'] = [
                'priority' => 100,
                'action_key' => $isSmsDeliveryFailure ? 'delivery_issue' : 'agent_exception',
                'action_label' => $isSmsDeliveryFailure ? 'SMS delivery failed' : 'Agent needs help',
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
