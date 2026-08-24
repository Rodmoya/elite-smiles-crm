<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/mailer.php';
require_once dirname(__DIR__) . '/notifications/internal_sms.php';
require_once dirname(__DIR__) . '/settings/crm_settings.php';

if (!function_exists('lead_agent_observability_ensure_schema')) {
    function lead_agent_observability_ensure_schema(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;

        db_query("CREATE TABLE IF NOT EXISTS lead_agent_touchpoints (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id INT UNSIGNED NOT NULL,
            event_key VARCHAR(190) NOT NULL,
            channel VARCHAR(20) NOT NULL,
            cadence_step INT UNSIGNED NOT NULL DEFAULT 0,
            phase VARCHAR(50) NOT NULL DEFAULT '',
            message_id INT UNSIGNED NULL,
            email_id INT UNSIGNED NULL,
            provider_id VARCHAR(120) NOT NULL DEFAULT '',
            delivery_status VARCHAR(40) NOT NULL DEFAULT 'accepted',
            lead_source VARCHAR(120) NOT NULL DEFAULT '',
            procedure_interest VARCHAR(190) NOT NULL DEFAULT '',
            strategy_key VARCHAR(60) NOT NULL DEFAULT '',
            strategy_reason VARCHAR(500) NOT NULL DEFAULT '',
            decision_confidence DECIMAL(4,3) NOT NULL DEFAULT 0.000,
            sent_at DATETIME NOT NULL,
            delivered_at DATETIME NULL,
            opened_at DATETIME NULL,
            clicked_at DATETIME NULL,
            replied_at DATETIME NULL,
            scheduling_intent_at DATETIME NULL,
            consultation_booked_at DATETIME NULL,
            opted_out_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_lead_agent_touchpoint_event (event_key),
            KEY idx_lead_agent_touchpoint_lead (lead_id, sent_at),
            KEY idx_lead_agent_touchpoint_message (message_id),
            KEY idx_lead_agent_touchpoint_email (email_id),
            KEY idx_lead_agent_touchpoint_sent (sent_at),
            KEY idx_lead_agent_touchpoint_delivery (delivery_status, sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $strategyColumns = [
            'strategy_key' => "ALTER TABLE lead_agent_touchpoints ADD COLUMN strategy_key VARCHAR(60) NOT NULL DEFAULT '' AFTER procedure_interest",
            'strategy_reason' => "ALTER TABLE lead_agent_touchpoints ADD COLUMN strategy_reason VARCHAR(500) NOT NULL DEFAULT '' AFTER strategy_key",
            'decision_confidence' => 'ALTER TABLE lead_agent_touchpoints ADD COLUMN decision_confidence DECIMAL(4,3) NOT NULL DEFAULT 0.000 AFTER strategy_reason',
        ];
        foreach ($strategyColumns as $column => $sql) {
            if (!db_one("SHOW COLUMNS FROM lead_agent_touchpoints LIKE '" . $column . "'")) {
                db_query($sql);
            }
        }
    }
}

if (!function_exists('lead_agent_is_globally_paused')) {
    function lead_agent_is_globally_paused(): bool
    {
        return (bool) crm_settings_get_json('lead_agent_global_pause', false);
    }
}

if (!function_exists('lead_agent_set_global_pause')) {
    function lead_agent_set_global_pause(bool $paused, int $updatedBy = 0): void
    {
        crm_settings_set_json('lead_agent_global_pause', $paused, $updatedBy);
        crm_settings_set_json('lead_agent_global_pause_changed_at', now(), $updatedBy);
    }
}

if (!function_exists('lead_agent_record_touchpoint')) {
    function lead_agent_record_touchpoint(array $lead, string $eventKey, string $channel, int $step, string $phase, array $send): int
    {
        lead_agent_observability_ensure_schema();
        $leadId = (int) ($lead['id'] ?? 0);
        if ($leadId <= 0 || $eventKey === '' || !in_array($channel, ['sms', 'email'], true)) return 0;

        db_query(
            "INSERT INTO lead_agent_touchpoints
                (lead_id, event_key, channel, cadence_step, phase, message_id, email_id, provider_id, delivery_status, lead_source, procedure_interest, strategy_key, strategy_reason, decision_confidence, sent_at, created_at, updated_at)
             VALUES
                (:lead_id, :event_key, :channel, :step, :phase, :message_id, :email_id, :provider_id, :delivery_status, :lead_source, :procedure_interest, :strategy_key, :strategy_reason, :decision_confidence, :sent_at, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                message_id = COALESCE(VALUES(message_id), message_id),
                email_id = COALESCE(VALUES(email_id), email_id),
                provider_id = IF(VALUES(provider_id) <> '', VALUES(provider_id), provider_id),
                delivery_status = IF(delivery_status IN ('delivered', 'opened', 'bounced', 'failed', 'undelivered', 'dropped'), delivery_status, VALUES(delivery_status)),
                strategy_key = IF(VALUES(strategy_key) <> '', VALUES(strategy_key), strategy_key),
                strategy_reason = IF(VALUES(strategy_reason) <> '', VALUES(strategy_reason), strategy_reason),
                decision_confidence = GREATEST(decision_confidence, VALUES(decision_confidence)),
                updated_at = NOW()",
            [
                'lead_id' => $leadId,
                'event_key' => substr($eventKey, 0, 190),
                'channel' => $channel,
                'step' => max(0, $step),
                'phase' => substr($phase, 0, 50),
                'message_id' => (int) ($send['message_id'] ?? 0) ?: null,
                'email_id' => (int) ($send['email_id'] ?? 0) ?: null,
                'provider_id' => substr((string) ($send['provider_id'] ?? ''), 0, 120),
                'delivery_status' => substr((string) ($send['delivery_status'] ?? 'accepted'), 0, 40),
                'lead_source' => substr((string) ($lead['source'] ?? ''), 0, 120),
                'procedure_interest' => substr((string) ($lead['procedure_interest'] ?? ''), 0, 190),
                'strategy_key' => substr((string) ($send['strategy_key'] ?? ''), 0, 60),
                'strategy_reason' => substr((string) ($send['strategy_reason'] ?? ''), 0, 500),
                'decision_confidence' => max(0.0, min(1.0, (float) ($send['decision_confidence'] ?? 0))),
                'sent_at' => trim((string) ($send['sent_at'] ?? '')) !== '' ? (string) $send['sent_at'] : now(),
            ]
        );
        return (int) db_value('SELECT id FROM lead_agent_touchpoints WHERE event_key = :event_key LIMIT 1', ['event_key' => $eventKey]);
    }
}

if (!function_exists('lead_agent_backfill_touchpoints')) {
    function lead_agent_backfill_touchpoints(int $limit = 1000): array
    {
        lead_agent_observability_ensure_schema();
        if ((bool) crm_settings_get_json('lead_agent_touchpoint_backfill_complete', false)) {
            return ['ran' => false, 'created' => 0];
        }
        $limit = max(1, min(5000, $limit));
        $rows = db_all("SELECT a.*, l.source, l.procedure_interest
            FROM lead_activities a
            INNER JOIN leads l ON l.id = a.lead_id
            WHERE a.type IN ('lead_agent_sms_outbound', 'lead_agent_email_outbound')
            ORDER BY a.created_at ASC, a.id ASC LIMIT {$limit}");
        $created = 0;
        foreach ($rows as $row) {
            $message = [];
            $email = [];
            $meta = json_decode((string) ($row['meta_json'] ?? ''), true);
            $meta = is_array($meta) ? $meta : [];
            $eventKey = trim((string) ($meta['event_key'] ?? ''));
            if ($eventKey === '') continue;
            $channel = (string) ($row['type'] ?? '') === 'lead_agent_sms_outbound' ? 'sms' : 'email';
            $event = db_one('SELECT reason, payload_json FROM lead_agent_events WHERE event_key = :event_key LIMIT 1', ['event_key' => $eventKey]) ?: [];
            $payload = json_decode((string) ($event['payload_json'] ?? ''), true);
            $payload = is_array($payload) ? $payload : [];
            $send = [
                'message_id' => (int) ($meta['message_id'] ?? 0),
                'email_id' => (int) ($meta['email_id'] ?? 0),
                'delivery_status' => 'accepted',
                'sent_at' => (string) ($row['created_at'] ?? now()),
            ];
            if ($channel === 'sms' && $send['message_id'] > 0) {
                $message = db_one('SELECT twilio_message_sid, twilio_status, delivered_at FROM lead_messages WHERE id = :id LIMIT 1', ['id' => $send['message_id']]);
                if ($message) {
                    $send['provider_id'] = (string) ($message['twilio_message_sid'] ?? '');
                    $send['delivery_status'] = (string) ($message['twilio_status'] ?? 'accepted');
                }
            } elseif ($channel === 'email' && $send['email_id'] > 0) {
                $email = db_one('SELECT status, opened_at FROM lead_emails WHERE id = :id LIMIT 1', ['id' => $send['email_id']]);
                if ($email) $send['delivery_status'] = (string) ($email['status'] ?? 'accepted');
            }
            $id = lead_agent_record_touchpoint(
                ['id' => (int) $row['lead_id'], 'source' => (string) ($row['source'] ?? ''), 'procedure_interest' => (string) ($row['procedure_interest'] ?? '')],
                $eventKey,
                $channel,
                (int) ($payload['step'] ?? 0),
                (string) ($event['reason'] ?? 'historical'),
                $send
            );
            if ($id > 0) {
                $created++;
                if ($channel === 'sms' && !empty($message['delivered_at'])) {
                    db_execute('UPDATE lead_agent_touchpoints SET delivered_at = COALESCE(delivered_at, :at) WHERE id = :id', ['at' => (string) $message['delivered_at'], 'id' => $id]);
                }
                if ($channel === 'email' && !empty($email['opened_at'])) {
                    db_execute('UPDATE lead_agent_touchpoints SET opened_at = COALESCE(opened_at, :at) WHERE id = :id', ['at' => (string) $email['opened_at'], 'id' => $id]);
                }
            }
        }
        crm_settings_set_json('lead_agent_touchpoint_backfill_complete', true, 0);
        return ['ran' => true, 'created' => $created, 'scanned' => count($rows)];
    }
}

if (!function_exists('lead_agent_update_touchpoint_delivery')) {
    function lead_agent_update_touchpoint_delivery(string $channel, int $recordId, string $status, string $providerId = ''): void
    {
        lead_agent_observability_ensure_schema();
        if ($recordId <= 0 || !in_array($channel, ['sms', 'email'], true)) return;
        $status = strtolower(trim($status));
        $delivered = in_array($status, ['delivered'], true);
        $opened = $status === 'opened';
        $field = $channel === 'sms' ? 'message_id' : 'email_id';
        db_execute(
            "UPDATE lead_agent_touchpoints SET
                delivery_status = :status,
                provider_id = IF(:provider_id_check <> '', :provider_id_value, provider_id),
                delivered_at = IF(:delivered = 1, COALESCE(delivered_at, NOW()), delivered_at),
                opened_at = IF(:opened = 1, COALESCE(opened_at, NOW()), opened_at),
                updated_at = NOW()
             WHERE {$field} = :record_id",
            [
                'status' => substr($status !== '' ? $status : 'unknown', 0, 40),
                'provider_id_check' => substr($providerId, 0, 120),
                'provider_id_value' => substr($providerId, 0, 120),
                'delivered' => $delivered ? 1 : 0,
                'opened' => $opened ? 1 : 0,
                'record_id' => $recordId,
            ]
        );
    }
}

if (!function_exists('lead_agent_leads_has_column')) {
    function lead_agent_leads_has_column(string $column): bool
    {
        if (function_exists('leads_has_column')) {
            return leads_has_column($column);
        }
        static $columns = null;
        if ($columns === null) {
            $columns = [];
            try {
                foreach (db_all('SHOW COLUMNS FROM leads') as $row) {
                    $field = trim((string) ($row['Field'] ?? ''));
                    if ($field !== '') {
                        $columns[$field] = true;
                    }
                }
            } catch (Throwable $e) {
                $columns = [];
            }
        }
        return isset($columns[$column]);
    }
}

if (!function_exists('lead_agent_scheduled_sql_condition')) {
    function lead_agent_scheduled_sql_condition(string $alias = 'l'): string
    {
        $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'l';
        $clauses = ["{$prefix}.status IN ('consultation_booked', 'consult_completed', 'treatment_accepted', 'treatment_completed')"];
        if (lead_agent_leads_has_column('consultation_status')) {
            $clauses[] = "COALESCE({$prefix}.consultation_status, '') IN ('scheduled', 'booked', 'confirmed', 'completed')";
        }
        if (lead_agent_leads_has_column('consultation_date')) {
            $clauses[] = "COALESCE({$prefix}.consultation_date, '') <> ''";
        }
        return '(' . implode(' OR ', $clauses) . ')';
    }
}

if (!function_exists('lead_agent_close_scheduling_handoff')) {
    /** Remove completed scheduling work from the agent's actionable queue. */
    function lead_agent_close_scheduling_handoff(int $leadId = 0): int
    {
        try {
            $params = [];
            $leadFilter = '';
            if ($leadId > 0) {
                $leadFilter = ' AND l.id = :lead_id';
                $params['lead_id'] = $leadId;
            }
            $scheduledCondition = lead_agent_scheduled_sql_condition('l');
            return db_execute("UPDATE lead_agent_states s
                INNER JOIN leads l ON l.id = s.lead_id
                SET s.status = 'scheduled', s.human_takeover = 0, s.human_takeover_until = NULL,
                    s.next_action_at = NULL, s.scheduling_phase = '', s.pause_reason = 'consultation_already_scheduled',
                    s.last_decision = 'scheduling_handoff_completed', s.lock_token = '', s.locked_at = NULL,
                    s.updated_at = NOW(), l.next_follow_up_at = NULL, l.updated_at = NOW()
                WHERE s.status IN ('ready_to_schedule', 'awaiting_slot_selection', 'awaiting_dob')
                  AND {$scheduledCondition}{$leadFilter}", $params);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('lead_agent_attribute_outcome')) {
    function lead_agent_attribute_outcome(int $leadId, string $outcome): bool
    {
        lead_agent_observability_ensure_schema();
        if ($leadId <= 0) return false;
        $field = match ($outcome) {
            'reply' => 'replied_at',
            'scheduling_intent' => 'scheduling_intent_at',
            'consultation_booked' => 'consultation_booked_at',
            'opt_out' => 'opted_out_at',
            default => '',
        };
        if ($field === '') return false;
        if ($outcome === 'consultation_booked') {
            lead_agent_close_scheduling_handoff($leadId);
        }
        $touchpoint = db_one(
            "SELECT id FROM lead_agent_touchpoints
             WHERE lead_id = :lead_id AND sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY sent_at DESC, id DESC LIMIT 1",
            ['lead_id' => $leadId]
        );
        if (!$touchpoint) return false;
        db_execute("UPDATE lead_agent_touchpoints SET {$field} = COALESCE({$field}, NOW()), updated_at = NOW() WHERE id = :id", ['id' => (int) $touchpoint['id']]);
        return true;
    }
}

if (!function_exists('lead_agent_performance_metrics')) {
    function lead_agent_performance_metrics(int $days = 30): array
    {
        lead_agent_observability_ensure_schema();
        $days = max(1, min(365, $days));
        $row = db_one("SELECT
                COUNT(*) AS touches,
                SUM(delivery_status = 'delivered') AS delivered,
                SUM(delivery_status IN ('failed', 'undelivered', 'bounced', 'dropped')) AS failed,
                SUM(opened_at IS NOT NULL) AS opened,
                SUM(replied_at IS NOT NULL) AS replied,
                SUM(scheduling_intent_at IS NOT NULL) AS scheduling_intent,
                SUM(consultation_booked_at IS NOT NULL) AS booked,
                SUM(opted_out_at IS NOT NULL) AS opted_out,
                SUM(channel = 'sms') AS sms,
                SUM(channel = 'email') AS email
            FROM lead_agent_touchpoints WHERE sent_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)") ?: [];
        $metrics = [];
        foreach (['touches','delivered','failed','opened','replied','scheduling_intent','booked','opted_out','sms','email'] as $key) {
            $metrics[$key] = (int) ($row[$key] ?? 0);
        }
        $denominator = max(1, $metrics['touches']);
        $metrics['reply_rate'] = round(($metrics['replied'] / $denominator) * 100, 1);
        $metrics['scheduling_rate'] = round(($metrics['scheduling_intent'] / $denominator) * 100, 1);
        $metrics['booking_rate'] = round(($metrics['booked'] / $denominator) * 100, 1);
        $metrics['failure_rate'] = round(($metrics['failed'] / $denominator) * 100, 1);
        $metrics['opt_out_rate'] = round(($metrics['opted_out'] / $denominator) * 100, 1);
        $metrics['days'] = $days;
        return $metrics;
    }
}

if (!function_exists('lead_agent_performance_by_channel')) {
    function lead_agent_performance_by_channel(int $days = 30): array
    {
        lead_agent_observability_ensure_schema();
        $days = max(1, min(365, $days));
        return db_all("SELECT channel, COUNT(*) AS touches,
                SUM(replied_at IS NOT NULL) AS replies,
                SUM(scheduling_intent_at IS NOT NULL) AS scheduling_intents,
                SUM(consultation_booked_at IS NOT NULL) AS bookings,
                SUM(delivery_status IN ('failed','undelivered','bounced','dropped')) AS failures
            FROM lead_agent_touchpoints
            WHERE sent_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
            GROUP BY channel ORDER BY channel");
    }
}

if (!function_exists('lead_agent_performance_by_strategy')) {
    function lead_agent_performance_by_strategy(int $days = 30): array
    {
        lead_agent_observability_ensure_schema();
        $days = max(1, min(365, $days));
        return db_all("SELECT strategy_key, COUNT(*) AS touches,
                SUM(replied_at IS NOT NULL) AS replies,
                SUM(scheduling_intent_at IS NOT NULL) AS scheduling_intents,
                SUM(consultation_booked_at IS NOT NULL) AS bookings,
                SUM(opted_out_at IS NOT NULL) AS opt_outs
            FROM lead_agent_touchpoints
            WHERE sent_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY) AND strategy_key <> ''
            GROUP BY strategy_key
            ORDER BY (SUM(consultation_booked_at IS NOT NULL) * 4 + SUM(scheduling_intent_at IS NOT NULL) * 2 + SUM(replied_at IS NOT NULL)) / GREATEST(COUNT(*), 1) DESC,
                     COUNT(*) DESC");
    }
}

if (!function_exists('lead_agent_maybe_alert_stale_run')) {
    function lead_agent_maybe_alert_stale_run(?array $latestRun): array
    {
        if (!$latestRun || lead_agent_is_globally_paused()) return ['sent' => false, 'reason' => 'not_applicable'];
        $finishedAt = trim((string) ($latestRun['finished_at'] ?? $latestRun['started_at'] ?? ''));
        $finishedTs = $finishedAt !== '' ? strtotime($finishedAt) : false;
        $failed = (string) ($latestRun['status'] ?? '') === 'failed';
        if (!$failed && ($finishedTs === false || (time() - $finishedTs) < (ELITE_LEAD_AGENT_STALE_MINUTES * 60))) {
            return ['sent' => false, 'reason' => 'healthy'];
        }
        $incidentKey = (string) ($latestRun['id'] ?? '') . '|' . $finishedAt;
        if ((string) crm_settings_get_json('lead_agent_last_stale_incident', '') === $incidentKey) {
            return ['sent' => false, 'reason' => 'already_alerted'];
        }

        $title = $failed ? 'Lead Agent worker failed' : 'Lead Agent heartbeat is stale';
        $message = $failed
            ? 'The latest Lead Agent run failed. Open the CRM operations screen to review the error and queue.'
            : 'No successful Lead Agent run has completed within ' . ELITE_LEAD_AGENT_STALE_MINUTES . ' minutes. Open the CRM operations screen to review.';
        $sms = ['ok' => false];
        $recipient = function_exists('internal_sms_find_recipient') ? internal_sms_find_recipient('rod_moya') : null;
        if (is_array($recipient)) {
            $sms = internal_sms_send($recipient, $message, 0);
        }
        $push = empty($sms['ok']) && function_exists('elite_send_pushover_notification')
            ? elite_send_pushover_notification($title . ' — SMS failed', $message, base_url('lead-agent-operations.php'), 'Open Lead Agent')
            : false;
        crm_settings_set_json('lead_agent_last_stale_incident', $incidentKey, 0);
        return ['sent' => $push || !empty($sms['ok']), 'push' => $push, 'sms' => !empty($sms['ok'])];
    }
}

if (!function_exists('lead_agent_prune_retention')) {
    function lead_agent_prune_retention(): array
    {
        lead_agent_observability_ensure_schema();
        $today = date('Y-m-d');
        if ((string) crm_settings_get_json('lead_agent_last_retention_date', '') === $today) {
            return ['ran' => false];
        }
        $eventDays = ELITE_LEAD_AGENT_EVENT_RETENTION_DAYS;
        $reportDays = ELITE_LEAD_AGENT_REPORT_RETENTION_DAYS;
        $deleted = [
            'events' => db_execute("DELETE FROM lead_agent_events WHERE created_at < DATE_SUB(NOW(), INTERVAL {$eventDays} DAY)"),
            'runs' => db_execute("DELETE FROM lead_agent_runs WHERE started_at < DATE_SUB(NOW(), INTERVAL {$eventDays} DAY)"),
            // Touchpoints back long-window performance reporting, so they follow report retention rather than raw event/run retention.
            'touchpoints' => db_execute("DELETE FROM lead_agent_touchpoints WHERE sent_at < DATE_SUB(NOW(), INTERVAL {$reportDays} DAY)"),
            'reports' => db_execute("DELETE FROM lead_agent_daily_reports WHERE report_date < DATE_SUB(CURDATE(), INTERVAL {$reportDays} DAY)"),
        ];
        crm_settings_set_json('lead_agent_last_retention_date', $today, 0);
        return ['ran' => true, 'deleted' => $deleted];
    }
}
