<?php
declare(strict_types=1);

/**
 * Durable, privacy-minimized conversion memory for the Lead Agent.
 *
 * This stores extracted operational signals—not full patient messages—so every
 * follow-up can continue the relationship without repeatedly reinterpreting
 * the lead from scratch.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once __DIR__ . '/lead_language.php';

if (!function_exists('lead_conversion_ensure_schema')) {
    function lead_conversion_ensure_schema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        db_query("CREATE TABLE IF NOT EXISTS lead_agent_conversion_memories (
            lead_id INT UNSIGNED NOT NULL,
            language VARCHAR(12) NOT NULL DEFAULT 'en',
            treatment_goal VARCHAR(190) NOT NULL DEFAULT '',
            primary_objection VARCHAR(80) NOT NULL DEFAULT '',
            readiness_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            conversation_state VARCHAR(40) NOT NULL DEFAULT 'exploring',
            answered_questions_json TEXT NULL,
            preferences_json TEXT NULL,
            recommended_action VARCHAR(190) NOT NULL DEFAULT '',
            strategy_key VARCHAR(60) NOT NULL DEFAULT '',
            strategy_reason VARCHAR(500) NOT NULL DEFAULT '',
            confidence DECIMAL(4,3) NOT NULL DEFAULT 0.500,
            conversation_hash CHAR(64) NOT NULL DEFAULT '',
            last_analyzed_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (lead_id),
            KEY idx_conversion_readiness (readiness_score, updated_at),
            KEY idx_conversion_strategy (strategy_key, updated_at),
            KEY idx_conversion_state (conversation_state, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('lead_conversion_strategy_labels')) {
    function lead_conversion_strategy_labels(): array
    {
        return [
            'goal_discovery' => 'Understand the smile goal',
            'education' => 'Helpful education',
            'trust_credibility' => 'Build trust and confidence',
            'objection_resolution' => 'Address the concern',
            'consultation_value' => 'Explain consultation value',
            'scheduling_preference' => 'Move toward scheduling',
            'open_door' => 'Keep the door open',
        ];
    }
}

if (!function_exists('lead_conversion_clean_text')) {
    function lead_conversion_clean_text(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}

if (!function_exists('lead_conversion_conversation')) {
    function lead_conversion_conversation(int $leadId): array
    {
        if ($leadId <= 0) {
            return [];
        }
        $events = [];
        try {
            foreach (db_all("SELECT id, direction, body, created_at FROM lead_messages WHERE lead_id = :lead_id ORDER BY created_at ASC, id ASC LIMIT 500", ['lead_id' => $leadId]) as $row) {
                $events[] = ['id' => (int) $row['id'], 'channel' => 'sms', 'direction' => (string) $row['direction'], 'body' => lead_conversion_clean_text((string) $row['body']), 'created_at' => (string) $row['created_at']];
            }
        } catch (Throwable $e) {
            // A partially installed communication table must not stop the agent.
        }
        try {
            foreach (db_all("SELECT id, direction, subject, body, created_at FROM lead_emails WHERE lead_id = :lead_id ORDER BY created_at ASC, id ASC LIMIT 500", ['lead_id' => $leadId]) as $row) {
                $events[] = ['id' => (int) $row['id'], 'channel' => 'email', 'direction' => (string) $row['direction'], 'body' => lead_conversion_clean_text(trim((string) $row['subject'] . ' ' . (string) $row['body'])), 'created_at' => (string) $row['created_at']];
            }
        } catch (Throwable $e) {
            // Email history is optional during incremental setup.
        }
        usort($events, static function (array $left, array $right): int {
            $time = strcmp((string) ($left['created_at'] ?? ''), (string) ($right['created_at'] ?? ''));
            return $time !== 0 ? $time : ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));
        });
        return $events;
    }
}

if (!function_exists('lead_conversion_recent_strategies')) {
    function lead_conversion_recent_strategies(int $leadId, int $limit = 3): array
    {
        if ($leadId <= 0) {
            return [];
        }
        $limit = max(1, min(10, $limit));
        try {
            return array_values(array_filter(array_map(
                static fn(array $row): string => trim((string) ($row['strategy_key'] ?? '')),
                db_all("SELECT strategy_key FROM lead_agent_touchpoints WHERE lead_id = :lead_id AND strategy_key <> '' ORDER BY sent_at DESC, id DESC LIMIT {$limit}", ['lead_id' => $leadId])
            )));
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('lead_conversion_strategy_rankings')) {
    /** Rank only strategies with enough evidence; opt-outs count as a strong negative. */
    function lead_conversion_strategy_rankings(int $days = 60, int $minimumTouches = 5): array
    {
        $days = max(14, min(180, $days));
        $minimumTouches = max(3, min(50, $minimumTouches));
        try {
            $rows = db_all("SELECT strategy_key, COUNT(*) AS touches,
                    SUM(replied_at IS NOT NULL) AS replies,
                    SUM(scheduling_intent_at IS NOT NULL) AS scheduling_intents,
                    SUM(consultation_booked_at IS NOT NULL) AS bookings,
                    SUM(opted_out_at IS NOT NULL) AS opt_outs
                FROM lead_agent_touchpoints
                WHERE sent_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY) AND strategy_key <> ''
                GROUP BY strategy_key HAVING COUNT(*) >= {$minimumTouches}");
        } catch (Throwable $e) {
            return [];
        }
        foreach ($rows as &$row) {
            $touches = max(1, (int) ($row['touches'] ?? 0));
            $row['score'] = round((((int) ($row['bookings'] ?? 0) * 5) + ((int) ($row['scheduling_intents'] ?? 0) * 3) + (int) ($row['replies'] ?? 0) - ((int) ($row['opt_outs'] ?? 0) * 4)) / $touches, 4);
        }
        unset($row);
        usort($rows, static fn(array $left, array $right): int => ((float) ($right['score'] ?? 0)) <=> ((float) ($left['score'] ?? 0)));
        return array_values(array_filter(array_map(static fn(array $row): string => (string) ($row['strategy_key'] ?? ''), $rows)));
    }
}

if (!function_exists('lead_conversion_detect_language')) {
    function lead_conversion_detect_language(string $inboundText): string
    {
        $signal = lead_language_detect_message_signal($inboundText);
        return (string)($signal['language'] ?? 'unknown') === 'es' ? 'es' : 'en';
    }
}

if (!function_exists('lead_conversion_extract_signals')) {
    function lead_conversion_extract_signals(array $lead, array $conversation): array
    {
        $inbound = array_values(array_filter($conversation, static fn(array $event): bool => ($event['direction'] ?? '') === 'inbound'));
        $outbound = array_values(array_filter($conversation, static fn(array $event): bool => ($event['direction'] ?? '') === 'outbound'));
        $inboundText = strtolower(implode(' ', array_map(static fn(array $event): string => (string) ($event['body'] ?? ''), $inbound)));
        $allText = strtolower(implode(' ', array_map(static fn(array $event): string => (string) ($event['body'] ?? ''), $conversation)));
        $interest = trim((string) ($lead['procedure_interest'] ?? ''));
        $goal = $interest;
        if ($goal === '') {
            foreach ([
                'veneers' => '/\b(?:veneers?|carillas?)\b/iu',
                'dental implants' => '/\b(?:dental implants?|implantes?)\b/iu',
                'All-on-X' => '/\b(?:all[ -]?on[ -]?(?:4|x)|full arch)\b/iu',
                'smile makeover' => '/\b(?:smile makeover|improve my smile|better smile|sonrisa)\b/iu',
            ] as $label => $pattern) {
                if (preg_match($pattern, $inboundText)) {
                    $goal = $label;
                    break;
                }
            }
        }

        $objection = '';
        foreach ([
            'distance' => '/\b(?:too far|farther|distance|cannot travel|can\'t travel|muy lejos)\b/iu',
            'timing' => '/\b(?:not ready|later|next year|busy|need time|ahora no|más adelante)\b/iu',
            'fear_or_anxiety' => '/\b(?:afraid|scared|nervous|anxiety|painful|miedo|nervioso)\b/iu',
            'cost_question' => '/\b(?:cost|price|expensive|afford|financ|cuanto|cuánto|precio|costo)\b/iu',
            'trust' => '/\b(?:reviews?|experience|before and after|results?|how long have|reseñas|resultados)\b/iu',
        ] as $key => $pattern) {
            if (preg_match($pattern, $inboundText)) {
                $objection = $key;
                break;
            }
        }

        $day = trim((string) ($lead['scheduling_preferred_day'] ?? ''));
        $time = trim((string) ($lead['scheduling_preferred_time'] ?? ''));
        $answered = [
            'goal' => $goal !== '',
            'day_preference' => $day !== '' || (bool) preg_match('/\b(?:mon(?:day)?|tue(?:sday)?|wed(?:nesday)?|thu(?:rsday)?|fri(?:day)?|sat(?:urday)?|lunes|martes|miércoles|miercoles|jueves|viernes|sábado|sabado)\b/iu', $inboundText),
            'time_preference' => $time !== '' || (bool) preg_match('/\b(?:morning|afternoon|evening|am|pm|mañana|tarde)\b/iu', $inboundText),
            'consultation_interest' => (bool) preg_match('/\b(?:schedule|appointment|consult|come in|available|book|cita|consulta|disponib)\b/iu', $inboundText),
        ];
        $lastInbound = $inbound !== [] ? $inbound[array_key_last($inbound)] : null;
        $lastOutbound = $outbound !== [] ? $outbound[array_key_last($outbound)] : null;
        $closed = (bool) preg_match('/\b(?:stop|unsubscribe|do not contact|don\'t contact|not interested|no thank you|wrong number|too far|muy lejos|no me interesa)\b/iu', (string) ($lastInbound['body'] ?? ''));
        $unansweredInbound = $lastInbound !== null && ($lastOutbound === null || strcmp((string) $lastInbound['created_at'], (string) $lastOutbound['created_at']) >= 0);

        $readiness = min(100, count($inbound) * 12);
        $readiness += $answered['goal'] ? 12 : 0;
        $readiness += $answered['consultation_interest'] ? 35 : 0;
        $readiness += $answered['day_preference'] ? 12 : 0;
        $readiness += $answered['time_preference'] ? 12 : 0;
        $readiness -= $objection !== '' ? 8 : 0;
        $readiness = max(0, min(100, $closed ? 0 : $readiness));

        $state = $closed ? 'closed' : ($answered['consultation_interest'] || $answered['day_preference'] || $answered['time_preference'] ? 'scheduling' : ($objection !== '' ? 'objection' : (count($inbound) > 0 ? 'engaged' : 'exploring')));
        $preferredLanguage = lead_language_preference($lead);
        return [
            'language' => $preferredLanguage !== 'unknown' ? $preferredLanguage : lead_conversion_detect_language($inboundText),
            'treatment_goal' => mb_substr($goal, 0, 190),
            'primary_objection' => $objection,
            'readiness_score' => $readiness,
            'conversation_state' => $state,
            'answered_questions' => $answered,
            'preferences' => ['day' => $day, 'time' => $time],
            'inbound_count' => count($inbound),
            'outbound_count' => count($outbound),
            'unanswered_inbound' => $unansweredInbound,
            'conversation_hash' => hash('sha256', $allText),
        ];
    }
}

if (!function_exists('lead_conversion_choose_strategy')) {
    function lead_conversion_choose_strategy(array $signals, int $cadenceStep = 0, array $recentStrategies = [], array $rankedStrategies = []): array
    {
        $answered = (array) ($signals['answered_questions'] ?? []);
        $state = (string) ($signals['conversation_state'] ?? 'exploring');
        $objection = (string) ($signals['primary_objection'] ?? '');
        if ($state === 'closed') {
            return ['strategy_key' => 'open_door', 'recommended_action' => 'Do not send; keep the conversation closed.', 'strategy_reason' => 'The latest inbound message closed the conversation.'];
        }
        if (!empty($signals['unanswered_inbound'])) {
            return ['strategy_key' => 'objection_resolution', 'recommended_action' => 'Answer the lead before any routine follow-up.', 'strategy_reason' => 'The lead has an unanswered inbound message.'];
        }
        if ($state === 'scheduling') {
            return ['strategy_key' => 'scheduling_preference', 'recommended_action' => 'Collect only the missing scheduling preference or ask Rod to check availability.', 'strategy_reason' => 'The lead has shown scheduling intent.'];
        }
        if ($objection !== '') {
            return ['strategy_key' => 'objection_resolution', 'recommended_action' => 'Acknowledge the concern and lower pressure without discussing treatment cost.', 'strategy_reason' => 'The conversation contains a ' . str_replace('_', ' ', $objection) . ' concern.'];
        }
        if (empty($answered['goal'])) {
            return ['strategy_key' => 'goal_discovery', 'recommended_action' => 'Ask one easy question about what the lead wants to improve.', 'strategy_reason' => 'The lead’s smile goal has not been captured yet.'];
        }

        $rotation = $cadenceStep >= 9
            ? ['open_door', 'consultation_value', 'education', 'trust_credibility']
            : ['consultation_value', 'education', 'trust_credibility', 'open_door'];
        $eligibleRanked = array_values(array_intersect($rankedStrategies, $rotation));
        if ($eligibleRanked !== []) {
            $rotation = array_values(array_unique(array_merge($eligibleRanked, $rotation)));
        }
        foreach ($rotation as $strategy) {
            if (!in_array($strategy, array_slice($recentStrategies, 0, 2), true)) {
                $reason = match ($strategy) {
                    'education' => 'A concise useful insight can create value without repeating the scheduling question.',
                    'trust_credibility' => 'The next touch should reduce uncertainty and build confidence.',
                    'open_door' => 'A low-pressure message is appropriate at this point in the cadence.',
                    default => 'The lead knows their goal; explain why a complimentary consultation is the useful next step.',
                };
                $action = match ($strategy) {
                    'education' => 'Share one relevant, non-clinical educational point and ask one easy question.',
                    'trust_credibility' => 'Build confidence in the personalized consultation process and ask one next-step question.',
                    'open_door' => 'Keep the door open with a warm, low-pressure invitation.',
                    default => 'Explain the value of the complimentary consultation and ask one scheduling-preference question.',
                };
                return ['strategy_key' => $strategy, 'recommended_action' => $action, 'strategy_reason' => $reason];
            }
        }
        return ['strategy_key' => 'consultation_value', 'recommended_action' => 'Invite the lead to a complimentary consultation.', 'strategy_reason' => 'Default safe conversion strategy.'];
    }
}

if (!function_exists('lead_conversion_refresh')) {
    function lead_conversion_refresh(array $lead, int $cadenceStep = 0): array
    {
        lead_conversion_ensure_schema();
        $leadId = (int) ($lead['id'] ?? 0);
        if ($leadId <= 0) {
            return [];
        }
        $signals = lead_conversion_extract_signals($lead, lead_conversion_conversation($leadId));
        $decision = lead_conversion_choose_strategy($signals, $cadenceStep, lead_conversion_recent_strategies($leadId), lead_conversion_strategy_rankings());
        $memory = $signals + $decision + ['confidence' => 0.72];
        db_query("INSERT INTO lead_agent_conversion_memories
            (lead_id, language, treatment_goal, primary_objection, readiness_score, conversation_state, answered_questions_json, preferences_json, recommended_action, strategy_key, strategy_reason, confidence, conversation_hash, last_analyzed_at, created_at, updated_at)
            VALUES (:lead_id, :language, :goal, :objection, :readiness, :state, :answered, :preferences, :action, :strategy, :reason, :confidence, :hash, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE language = VALUES(language), treatment_goal = VALUES(treatment_goal), primary_objection = VALUES(primary_objection), readiness_score = VALUES(readiness_score), conversation_state = VALUES(conversation_state), answered_questions_json = VALUES(answered_questions_json), preferences_json = VALUES(preferences_json), recommended_action = VALUES(recommended_action), strategy_key = VALUES(strategy_key), strategy_reason = VALUES(strategy_reason), confidence = VALUES(confidence), conversation_hash = VALUES(conversation_hash), last_analyzed_at = NOW(), updated_at = NOW()", [
                'lead_id' => $leadId,
                'language' => $memory['language'],
                'goal' => $memory['treatment_goal'],
                'objection' => $memory['primary_objection'],
                'readiness' => $memory['readiness_score'],
                'state' => $memory['conversation_state'],
                'answered' => json_encode($memory['answered_questions'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'preferences' => json_encode($memory['preferences'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'action' => mb_substr($memory['recommended_action'], 0, 190),
                'strategy' => mb_substr($memory['strategy_key'], 0, 60),
                'reason' => mb_substr($memory['strategy_reason'], 0, 500),
                'confidence' => $memory['confidence'],
                'hash' => $memory['conversation_hash'],
            ]);
        return $memory;
    }
}

if (!function_exists('lead_conversion_load')) {
    function lead_conversion_load(int $leadId): array
    {
        lead_conversion_ensure_schema();
        $row = $leadId > 0 ? (db_one('SELECT * FROM lead_agent_conversion_memories WHERE lead_id = :lead_id LIMIT 1', ['lead_id' => $leadId]) ?: []) : [];
        if ($row !== []) {
            $row['answered_questions'] = json_decode((string) ($row['answered_questions_json'] ?? '{}'), true) ?: [];
            $row['preferences'] = json_decode((string) ($row['preferences_json'] ?? '{}'), true) ?: [];
        }
        return $row;
    }
}

if (!function_exists('lead_conversion_apply_ai_result')) {
    function lead_conversion_apply_ai_result(int $leadId, array $data): void
    {
        if ($leadId <= 0 || $data === []) {
            return;
        }
        lead_conversion_ensure_schema();
        $allowedStrategies = array_keys(lead_conversion_strategy_labels());
        $allowedStates = ['exploring', 'engaged', 'objection', 'nurturing', 'scheduling', 'closed', 'human_review'];
        $strategy = (string) ($data['strategy_key'] ?? '');
        $state = (string) ($data['conversation_state'] ?? '');
        $sets = [];
        $params = ['lead_id' => $leadId];
        if (in_array($strategy, $allowedStrategies, true)) {
            $sets[] = 'strategy_key = :strategy';
            $params['strategy'] = $strategy;
        }
        if (in_array($state, $allowedStates, true)) {
            $sets[] = 'conversation_state = :state';
            $params['state'] = $state;
        }
        foreach ([
            'strategy_reason' => ['strategy_reason', 500],
            'next_best_action' => ['recommended_action', 190],
            'known_goal' => ['treatment_goal', 190],
            'known_objection' => ['primary_objection', 80],
        ] as $source => [$column, $limit]) {
            $value = trim((string) ($data[$source] ?? ''));
            if ($value !== '') {
                $sets[] = "{$column} = :{$source}";
                $params[$source] = mb_substr($value, 0, $limit);
            }
        }
        if (isset($data['confidence'])) {
            $sets[] = 'confidence = :confidence';
            $params['confidence'] = max(0.0, min(1.0, (float) $data['confidence']));
        }
        if ($sets !== []) {
            db_execute('UPDATE lead_agent_conversion_memories SET ' . implode(', ', $sets) . ', last_analyzed_at = NOW(), updated_at = NOW() WHERE lead_id = :lead_id', $params);
        }
    }
}

if (!function_exists('lead_conversion_priority_rows')) {
    function lead_conversion_priority_rows(int $limit = 8): array
    {
        lead_conversion_ensure_schema();
        $limit = max(1, min(50, $limit));
        return db_all("SELECT m.*, l.full_name, l.status AS lead_status, l.procedure_interest
            FROM lead_agent_conversion_memories m INNER JOIN leads l ON l.id = m.lead_id
            INNER JOIN lead_agent_states s ON s.lead_id = m.lead_id
            WHERE s.status IN ('active','engaged') AND s.human_takeover = 0
              AND l.status NOT IN ('opted_out','lost_lead','consultation_booked','consult_completed','treatment_accepted','treatment_completed')
            ORDER BY m.readiness_score DESC, m.updated_at DESC LIMIT {$limit}");
    }
}

if (!function_exists('lead_conversion_refresh_active_memories')) {
    /** Incrementally hydrate existing active leads without changing cadence or sending. */
    function lead_conversion_refresh_active_memories(int $limit = 40): int
    {
        lead_conversion_ensure_schema();
        $limit = max(1, min(100, $limit));
        $rows = db_all("SELECT l.*, s.cadence_step
            FROM lead_agent_states s
            INNER JOIN leads l ON l.id = s.lead_id
            LEFT JOIN lead_agent_conversion_memories m ON m.lead_id = l.id
            WHERE s.status IN ('active','engaged') AND s.human_takeover = 0
              AND l.status NOT IN ('opted_out','lost_lead','consultation_booked','consult_completed','treatment_accepted','treatment_completed')
              AND (m.lead_id IS NULL OR m.updated_at < l.updated_at OR m.updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
            ORDER BY m.updated_at IS NULL DESC, COALESCE(m.updated_at, '2000-01-01') ASC
            LIMIT {$limit}");
        $refreshed = 0;
        foreach ($rows as $lead) {
            try {
                if (lead_conversion_refresh($lead, (int) ($lead['cadence_step'] ?? 0)) !== []) {
                    $refreshed++;
                }
            } catch (Throwable $e) {
                esm_log('lead_agent', 'Could not refresh conversion memory.', ['lead_id' => (int) ($lead['id'] ?? 0), 'error' => $e->getMessage()]);
            }
        }
        return $refreshed;
    }
}
