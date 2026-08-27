<?php
declare(strict_types=1);

/**
 * Explicit, source-backed patient language preferences.
 *
 * A name is never a language signal. Unknown leads receive one neutral
 * English/Spanish option inside an already-authorized message, and the CRM
 * stores a preference only from intake metadata or the lead's own words.
 */

require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/helpers.php';

if (!function_exists('lead_language_normalize')) {
    function lead_language_normalize(string $value): string
    {
        $value = strtolower(trim($value));
        return match ($value) {
            'es', 'spa', 'spanish', 'espanol', 'español' => 'es',
            'en', 'eng', 'english', 'ingles', 'inglés' => 'en',
            default => 'unknown',
        };
    }
}

if (!function_exists('lead_language_preference')) {
    function lead_language_preference(array $lead): string
    {
        $stored = lead_language_normalize((string)($lead['preferred_language'] ?? ''));
        if ($stored !== 'unknown') {
            return $stored;
        }

        // Preserve explicit choices captured by older landing pages before the
        // dedicated column existed. This is intake evidence, not name inference.
        $notes = strtolower((string)($lead['notes'] ?? ''));
        if (str_contains($notes, 'preferred language: spanish')
            || str_contains($notes, 'idioma preferido: español')
            || str_contains($notes, 'idioma preferido: espanol')) {
            return 'es';
        }
        if (str_contains($notes, 'preferred language: english')
            || str_contains($notes, 'idioma preferido: english')
            || str_contains($notes, 'idioma preferido: ingles')
            || str_contains($notes, 'idioma preferido: inglés')) {
            return 'en';
        }
        return 'unknown';
    }
}

if (!function_exists('lead_language_is_spanish')) {
    function lead_language_is_spanish(array $lead): bool
    {
        return lead_language_preference($lead) === 'es';
    }
}

if (!function_exists('lead_language_text')) {
    function lead_language_text(array $lead, string $english, string $spanish): string
    {
        return lead_language_is_spanish($lead) ? $spanish : $english;
    }
}

if (!function_exists('lead_language_detect_message_signal')) {
    /**
     * Detect language only from what the lead wrote. Names and phone geography
     * are deliberately absent. General detection requires multiple Spanish
     * markers so a lone courtesy word cannot relabel the conversation.
     */
    function lead_language_detect_message_signal(string $body): array
    {
        $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $body) ?? $body));
        if ($text === '') {
            return ['language' => 'unknown', 'source' => '', 'confidence' => 0.0];
        }

        if (preg_match('/^(?:español|espanol|spanish)[.! ]*$/u', $text)) {
            return ['language' => 'es', 'source' => 'inbound_explicit', 'confidence' => 1.0];
        }
        if (preg_match('/^(?:english|inglés|ingles)[.! ]*$/u', $text)) {
            return ['language' => 'en', 'source' => 'inbound_explicit', 'confidence' => 1.0];
        }

        if (preg_match('/\b(?:español|espanol|spanish)\b/u', $text)
            && preg_match('/\b(?:prefiero|prefer|please|por favor|hablar|continuar|responder|reply|en)\b/u', $text)) {
            return ['language' => 'es', 'source' => 'inbound_explicit', 'confidence' => 1.0];
        }
        if (preg_match('/\b(?:english|inglés|ingles)\b/u', $text)
            && preg_match('/\b(?:prefiero|prefer|please|por favor|hablar|continue|reply|responder|en)\b/u', $text)) {
            return ['language' => 'en', 'source' => 'inbound_explicit', 'confidence' => 1.0];
        }

        $spanishMarkers = [
            '/\b(?:hola|buenos dias|buenas tardes|buenas noches)\b/u',
            '/\b(?:me interesa|estoy interesado|estoy interesada|quisiera|quiero|necesito)\b/u',
            '/\b(?:puede ayudarme|pueden ayudarme|me puede ayudar|me pueden ayudar)\b/u',
            '/\b(?:cita|consulta|sonrisa|dientes|carillas|implantes)\b/u',
            '/\b(?:lunes|martes|miércoles|miercoles|jueves|viernes|sábado|sabado|domingo)\b/u',
            '/\b(?:mañana|manana|tarde|noche|esta semana|la próxima semana|la proxima semana)\b/u',
            '/\b(?:gracias|por favor|si por favor|claro que si)\b/u',
        ];
        $matches = 0;
        foreach ($spanishMarkers as $pattern) {
            if (preg_match($pattern, $text)) {
                $matches++;
            }
        }
        if ($matches >= 2) {
            return ['language' => 'es', 'source' => 'inbound_detected', 'confidence' => min(0.95, 0.72 + (($matches - 2) * 0.08))];
        }

        $englishMarkers = [
            '/\b(?:hello|hi|good morning|good afternoon|good evening)\b/u',
            '/\b(?:i am interested|i\'m interested|i would like|i\'d like|i want|i need)\b/u',
            '/\b(?:can you help|could you help|please help)\b/u',
            '/\b(?:appointment|consultation|smile|teeth|veneers|implants)\b/u',
            '/\b(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/u',
            '/\b(?:tomorrow|morning|afternoon|evening|this week|next week)\b/u',
            '/\b(?:thank you|thanks|please|yes please|that works for me)\b/u',
        ];
        $matches = 0;
        foreach ($englishMarkers as $pattern) {
            if (preg_match($pattern, $text)) {
                $matches++;
            }
        }
        if ($matches >= 2) {
            return ['language' => 'en', 'source' => 'inbound_detected', 'confidence' => min(0.95, 0.72 + (($matches - 2) * 0.08))];
        }

        return ['language' => 'unknown', 'source' => '', 'confidence' => 0.0];
    }
}

if (!function_exists('lead_language_ensure_schema')) {
    function lead_language_ensure_schema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        try {
            $hasLeads = (bool)db_value(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
                ['table' => 'leads']
            );
            if (!$hasLeads) {
                return;
            }
            foreach ([
                'preferred_language' => "VARCHAR(12) NOT NULL DEFAULT 'unknown'",
                'preferred_language_source' => "VARCHAR(40) NOT NULL DEFAULT ''",
            ] as $column => $definition) {
                $exists = (bool)db_value(
                    'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
                    ['table' => 'leads', 'column' => $column]
                );
                if (!$exists) {
                    db_query("ALTER TABLE leads ADD COLUMN {$column} {$definition}");
                }
            }
            db_execute("UPDATE leads
                SET preferred_language = 'es', preferred_language_source = 'legacy_intake_notes'
                WHERE preferred_language = 'unknown'
                  AND (LOWER(COALESCE(notes, '')) LIKE '%preferred language: spanish%'
                    OR LOWER(COALESCE(notes, '')) LIKE '%idioma preferido: espanol%'
                    OR LOWER(COALESCE(notes, '')) LIKE '%idioma preferido: español%')");
            db_execute("UPDATE leads
                SET preferred_language = 'en', preferred_language_source = 'legacy_intake_notes'
                WHERE preferred_language = 'unknown'
                  AND (LOWER(COALESCE(notes, '')) LIKE '%preferred language: english%'
                    OR LOWER(COALESCE(notes, '')) LIKE '%idioma preferido: english%'
                    OR LOWER(COALESCE(notes, '')) LIKE '%idioma preferido: ingles%'
                    OR LOWER(COALESCE(notes, '')) LIKE '%idioma preferido: inglés%')");
            if (function_exists('leads_table_columns')) {
                leads_table_columns(true);
            }
        } catch (Throwable $e) {
            if (function_exists('esm_log')) {
                esm_log('lead_language', 'Could not ensure language preference columns.', ['error' => $e->getMessage()]);
            }
        }
    }
}

if (!function_exists('lead_language_set_preference')) {
    function lead_language_set_preference(int $leadId, string $language, string $source): bool
    {
        $language = lead_language_normalize($language);
        $source = mb_substr(trim($source), 0, 40);
        if ($leadId <= 0 || $language === 'unknown' || $source === '') {
            return false;
        }
        lead_language_ensure_schema();
        try {
            $current = db_one('SELECT preferred_language, preferred_language_source FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
            if (!$current) {
                return false;
            }
            $changed = lead_language_normalize((string)($current['preferred_language'] ?? '')) !== $language
                || trim((string)($current['preferred_language_source'] ?? '')) !== $source;
            if (!$changed) {
                return false;
            }
            db_execute('UPDATE leads SET preferred_language = :language, preferred_language_source = :source, updated_at = NOW() WHERE id = :id LIMIT 1', [
                'language' => $language,
                'source' => $source,
                'id' => $leadId,
            ]);
            if (function_exists('lead_comm_insert_activity')) {
                lead_comm_insert_activity(
                    $leadId,
                    'language_preference_updated',
                    'Lead language preference updated from the lead\'s own intake or message.',
                    ['language' => $language, 'source' => $source],
                    'Lead Agent'
                );
            }
            return true;
        } catch (Throwable $e) {
            if (function_exists('esm_log')) {
                esm_log('lead_language', 'Could not save language preference.', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
            }
            return false;
        }
    }
}

if (!function_exists('lead_language_source_priority')) {
    function lead_language_source_priority(string $source): int
    {
        $source = strtolower(trim($source));
        if ($source === '') {
            return 0;
        }
        if (in_array($source, [
            'landing_page_default',
            'landing_page_browser',
            'landing_page_link',
            'landing_page_remembered',
            'landing_page_ui',
        ], true)) {
            return 5;
        }
        if (str_contains($source, 'manual')
            || str_contains($source, 'operator')
            || str_contains($source, 'intake')
            || str_contains($source, 'selected')) {
            return 40;
        }
        if ($source === 'inbound_explicit') {
            return 30;
        }
        if ($source === 'inbound_detected' || str_contains($source, 'conversation')) {
            return 10;
        }
        return 20;
    }
}

if (!function_exists('lead_language_apply_signal')) {
    /**
     * Save a language signal without allowing passive detection to overwrite a
     * stronger manual, intake, or explicit preference. A newer explicit request
     * may change an older explicit request because it is the patient's choice.
     */
    function lead_language_apply_signal(int $leadId, array $signal): array
    {
        $language = lead_language_normalize((string)($signal['language'] ?? ''));
        $source = mb_substr(trim((string)($signal['source'] ?? '')), 0, 40);
        if ($leadId <= 0 || $language === 'unknown' || $source === '') {
            return ['language' => 'unknown', 'source' => '', 'saved' => false, 'reason' => 'no_clear_signal'];
        }

        lead_language_ensure_schema();
        try {
            $current = db_one('SELECT preferred_language, preferred_language_source FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
            if (!$current) {
                return ['language' => 'unknown', 'source' => '', 'saved' => false, 'reason' => 'lead_not_found'];
            }

            $currentLanguage = lead_language_normalize((string)($current['preferred_language'] ?? ''));
            $currentSource = trim((string)($current['preferred_language_source'] ?? ''));
            $incomingPriority = lead_language_source_priority($source);
            $currentPriority = lead_language_source_priority($currentSource);
            $explicitSwitch = $source === 'inbound_explicit';

            if ($currentLanguage !== 'unknown'
                && !$explicitSwitch
                && ($currentPriority > $incomingPriority || $currentSource === 'inbound_explicit')) {
                return ['language' => $currentLanguage, 'source' => $currentSource, 'saved' => false, 'reason' => 'stronger_preference_retained'];
            }
            if ($currentLanguage === $language && $currentSource === $source) {
                return ['language' => $currentLanguage, 'source' => $currentSource, 'saved' => false, 'reason' => 'unchanged'];
            }

            $saved = lead_language_set_preference($leadId, $language, $source);
            return ['language' => $language, 'source' => $source, 'saved' => $saved, 'reason' => $saved ? 'saved' : 'unchanged'];
        } catch (Throwable $e) {
            if (function_exists('esm_log')) {
                esm_log('lead_language', 'Could not apply inbound language signal.', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
            }
            return ['language' => 'unknown', 'source' => '', 'saved' => false, 'reason' => 'storage_error'];
        }
    }
}

if (!function_exists('lead_language_record_inbound')) {
    /** Record a preference from one inbound SMS or the new portion of an email. */
    function lead_language_record_inbound(int $leadId, string $body): array
    {
        return lead_language_apply_signal($leadId, lead_language_detect_message_signal($body));
    }
}

if (!function_exists('lead_language_conversation_signal')) {
    /**
     * Recover language from recent inbound conversation history. The newest
     * explicit request wins; otherwise the newest clearly detected message wins.
     */
    function lead_language_conversation_signal(int $leadId): array
    {
        if ($leadId <= 0) {
            return ['language' => 'unknown', 'source' => '', 'confidence' => 0.0];
        }

        $items = [];
        try {
            foreach (db_all(
                "SELECT id, body, created_at FROM lead_messages WHERE lead_id = :lead_id AND direction = 'inbound' ORDER BY created_at DESC, id DESC LIMIT 20",
                ['lead_id' => $leadId]
            ) as $row) {
                $items[] = ['id' => (int)($row['id'] ?? 0), 'created_at' => (string)($row['created_at'] ?? ''), 'body' => (string)($row['body'] ?? '')];
            }
        } catch (Throwable $e) {
            // A missing legacy table must not block an appointment reminder.
        }

        try {
            foreach (db_all(
                "SELECT id, subject, body, created_at FROM lead_emails WHERE lead_id = :lead_id AND direction = 'inbound' ORDER BY created_at DESC, id DESC LIMIT 20",
                ['lead_id' => $leadId]
            ) as $row) {
                $subject = (string)($row['subject'] ?? '');
                $body = (string)($row['body'] ?? '');
                $newReply = function_exists('lead_email_new_reply_text')
                    ? lead_email_new_reply_text($subject, $body)
                    : trim($subject . "\n" . $body);
                $items[] = ['id' => (int)($row['id'] ?? 0), 'created_at' => (string)($row['created_at'] ?? ''), 'body' => $newReply];
            }
        } catch (Throwable $e) {
            // Email history is optional for older installations.
        }

        usort($items, static function (array $a, array $b): int {
            $byTime = strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
            return $byTime !== 0 ? $byTime : ((int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0));
        });

        $newestDetected = null;
        foreach ($items as $item) {
            $signal = lead_language_detect_message_signal((string)($item['body'] ?? ''));
            if (($signal['source'] ?? '') === 'inbound_explicit') {
                return $signal;
            }
            if ($newestDetected === null && ($signal['language'] ?? 'unknown') !== 'unknown') {
                $newestDetected = $signal;
            }
        }

        return $newestDetected ?? ['language' => 'unknown', 'source' => '', 'confidence' => 0.0];
    }
}

if (!function_exists('lead_language_sync_from_conversation')) {
    /** Return a lead row with the best source-backed conversation preference. */
    function lead_language_sync_from_conversation(array $lead): array
    {
        $leadId = (int)($lead['id'] ?? 0);
        if ($leadId <= 0) {
            return $lead;
        }

        $currentLanguage = lead_language_preference($lead);
        $currentSource = trim((string)($lead['preferred_language_source'] ?? ''));
        if ($currentLanguage !== 'unknown' && lead_language_source_priority($currentSource) >= 40) {
            return $lead;
        }

        $applied = lead_language_apply_signal($leadId, lead_language_conversation_signal($leadId));
        $resolvedLanguage = lead_language_normalize((string)($applied['language'] ?? ''));
        if ($resolvedLanguage !== 'unknown') {
            $lead['preferred_language'] = $resolvedLanguage;
            $lead['preferred_language_source'] = (string)($applied['source'] ?? $currentSource);
        }
        return $lead;
    }
}

if (!function_exists('lead_language_offer_already_sent')) {
    function lead_language_offer_already_sent(int $leadId, string $channel): bool
    {
        if ($leadId <= 0) {
            return false;
        }
        try {
            if ($channel === 'email') {
                return (int)db_value("SELECT COUNT(*) FROM lead_emails WHERE lead_id = :lead_id AND direction = 'outbound' AND LOWER(body) LIKE '%espanol%'", ['lead_id' => $leadId]) > 0;
            }
            return (int)db_value("SELECT COUNT(*) FROM lead_messages WHERE lead_id = :lead_id AND direction = 'outbound' AND LOWER(body) LIKE '%espanol%'", ['lead_id' => $leadId]) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('lead_language_maybe_add_sms_offer')) {
    function lead_language_maybe_add_sms_offer(array $lead, string $body): string
    {
        if (lead_language_preference($lead) !== 'unknown'
            || stripos($body, 'ESPANOL') !== false
            || lead_language_offer_already_sent((int)($lead['id'] ?? 0), 'sms')) {
            return $body;
        }
        return rtrim($body) . ' English or Spanish is welcome. Responda ESPANOL si prefiere.';
    }
}

if (!function_exists('lead_language_maybe_add_email_offer')) {
    function lead_language_maybe_add_email_offer(array $lead, string $body): string
    {
        if (lead_language_preference($lead) !== 'unknown'
            || stripos($body, 'espanol') !== false
            || lead_language_offer_already_sent((int)($lead['id'] ?? 0), 'email')) {
            return $body;
        }
        return rtrim($body) . "\n\nWe are happy to help in English or Spanish. Si prefiere, puede responder en ESPANOL.";
    }
}
