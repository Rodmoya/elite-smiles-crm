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
require_once dirname(__DIR__) . '/core/mobile_ai_push.php';
require_once dirname(__DIR__) . '/leads/lead_meta.php';
require_once dirname(__DIR__) . '/leads/lead_service.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_email.php';
require_once dirname(__DIR__) . '/leads/lead_ai.php';
require_once dirname(__DIR__) . '/ai/elite_ai_service.php';
require_once dirname(__DIR__) . '/smile_design/smile_design_service.php';
require_once dirname(__DIR__) . '/dentrix/dentrix_bridge.php';
require_once dirname(__DIR__) . '/core/mailer.php';
require_once dirname(__DIR__) . '/core/twilio.php';
require_once dirname(__DIR__) . '/notifications/internal_sms.php';
if (function_exists('dentrix_bridge_ensure_schema')) {
    dentrix_bridge_ensure_schema();
}
if (defined('ELITE_CODEX_API_V1') && ELITE_CODEX_API_V1) {
    require_once dirname(__DIR__) . '/core/codex_api_security.php';
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!function_exists('codex_api_response')) {
    function codex_api_response(array $payload, int $statusCode = 200): never
    {
        if (defined('ELITE_CODEX_API_V1') && ELITE_CODEX_API_V1 && function_exists('codex_security_finalize')) {
            codex_security_finalize($statusCode, $payload);
        }
        http_response_code($statusCode);
        header('X-Content-Type-Options: nosniff');
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

        if (defined('ELITE_CODEX_API_V1') && ELITE_CODEX_API_V1 && isset($GLOBALS['codex_api_v1_body']) && is_array($GLOBALS['codex_api_v1_body'])) {
            $body = $GLOBALS['codex_api_v1_body'];
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

if (!function_exists('codex_api_has_explicit_delete_approval')) {
    function codex_api_has_explicit_delete_approval(array $request): bool
    {
        if (filter_var($request['delete_approved'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        if (filter_var($request['delete_approval'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        if (filter_var($request['approve_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        if (filter_var($request['confirm_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $executionMode = strtolower(trim((string) ($request['execution_mode'] ?? '')));
        if (in_array($executionMode, ['delete', 'delete_approved', 'delete_approval', 'remove_lead'], true)) {
            return true;
        }

        $instruction = strtolower(trim((string) ($request['instruction'] ?? '')));
        if ($instruction === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b(?:delete|remove|purge)\b.*\b(?:lead|record|card|duplicate|fake|test)\b|\bdelete\s+this\s+lead\b/i',
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

if (!function_exists('codex_api_record_outbound_note')) {
    function codex_api_record_outbound_note(int $leadId, string $channel, string $subject, string $body, string $createdBy, array $metadata = []): void
    {
        if ($leadId <= 0 || trim($body) === '' || !function_exists('lead_ai_create_outbound_note')) {
            return;
        }
        try {
            lead_ai_create_outbound_note($leadId, $channel, $subject, $body, array_merge([
                'sent_at' => date('Y-m-d H:i:s'),
                'created_by' => $createdBy !== '' ? $createdBy : 'Codex',
                'source' => 'codex_operator_api',
            ], $metadata));
        } catch (Throwable $e) {
            esm_log('codex_api', 'Outbound communication succeeded but its lead note could not be created.', [
                'lead_id' => $leadId,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
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

if (!function_exists('codex_api_notification_assistant_summary')) {
    function codex_api_notification_assistant_summary(array $row, string $type, string $sourceLabel = ''): array
    {
        $leadName = trim((string) ($row['full_name'] ?? $row['lead_name'] ?? 'this lead'));
        $body = trim((string) ($row['body'] ?? $row['message'] ?? ''));
        $quote = $body !== '' ? '"' . codex_api_text_excerpt($body, 130) . '"' : '';

        if (function_exists('elite_ai_message_is_sms_opt_out_request') && elite_ai_message_is_sms_opt_out_request($body)) {
            return [
                'summary' => 'Rod, ' . $leadName . ' replied STOP and is blocked from SMS.',
                'prompt' => 'What do you want me to do? I can mark this reviewed or draft a respectful email.',
                'push_body' => 'Rod, ' . $leadName . ' replied STOP. SMS is blocked. Open Elite AI and tell me what to do.',
                'primary_action' => 'Mark DND',
            ];
        }

        if ($type === 'reply') {
            return [
                'summary' => 'Rod, we got a new message from ' . $leadName . ($quote !== '' ? ': ' . $quote : '.'),
                'prompt' => 'What do you want me to do? I can review the conversation and draft a reply.',
                'push_body' => 'Rod, new message from ' . $leadName . ($quote !== '' ? ': ' . $quote : '.'),
                'primary_action' => 'Draft reply',
            ];
        }

        if ($type === 'lead_created' || $type === 'new_lead') {
            $source = $sourceLabel !== '' ? ' from ' . $sourceLabel : '';
            return [
                'summary' => 'Rod, we got a new lead' . $source . ': ' . $leadName . '.',
                'prompt' => 'The first message was sent successfully. What do you want me to do next?',
                'push_body' => 'Rod, new lead' . $source . ': ' . $leadName . '. First message sent.',
                'primary_action' => 'Review lead',
            ];
        }

        if ($type === 'manual_sms_followup_prepared') {
            return [
                'summary' => 'Rod, first message is ready/sent for ' . $leadName . '.',
                'prompt' => 'What do you want me to do next? I can keep watching for a reply or review the lead.',
                'push_body' => 'Rod, first message update for ' . $leadName . '.',
                'primary_action' => 'Open lead',
            ];
        }

        if ($type === 'follow_up_due') {
            return [
                'summary' => 'Rod, ' . $leadName . ' is due for follow-up.',
                'prompt' => 'What do you want me to do? I can review the conversation and draft the follow-up.',
                'push_body' => 'Rod, ' . $leadName . ' is due for follow-up.',
                'primary_action' => 'Draft follow-up',
            ];
        }

        if ($type === 'consultation_scheduled') {
            return [
                'summary' => 'Rod, consultation update for ' . $leadName . '.',
                'prompt' => 'What do you want me to do? I can check appointment readiness and missing information.',
                'push_body' => 'Rod, consultation update for ' . $leadName . '.',
                'primary_action' => 'Check readiness',
            ];
        }

        return [
            'summary' => 'Rod, CRM activity for ' . $leadName . '.',
            'prompt' => 'What do you want me to do next?',
            'push_body' => 'Rod, CRM activity for ' . $leadName . '.',
            'primary_action' => 'Review',
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

        return '';
    }
}

if (!function_exists('codex_api_auth')) {
    function codex_api_auth(): void
    {
        if (defined('ELITE_CODEX_API_V1') && ELITE_CODEX_API_V1) {
            $context = $GLOBALS['codex_api_security_context'] ?? null;
            if (is_array($context) && !empty($context['client_id'])) {
                return;
            }
            codex_api_response(['ok' => false, 'message' => 'Unauthorized.'], 401);
        }

        if (!defined('ELITE_CODEX_LEGACY_API_ENABLED') || !ELITE_CODEX_LEGACY_API_ENABLED) {
            codex_api_response([
                'ok' => false,
                'message' => 'Legacy Codex API is disabled. Use the versioned Codex API.',
                'upgrade' => '/app/api/codex/v1/',
            ], 410);
        }

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
            'dentrix_sync_status', 'dentrix_patient_key', 'dentrix_appointment_key',
            'last_dentrix_sync_at', 'appointment_source', 'occupied_slot_type',
            'external_calendar_block',
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

if (!function_exists('codex_api_capabilities')) {
    function codex_api_capabilities(): void
    {
        codex_api_response([
            'ok' => true,
            'name' => 'Elite Smiles Codex Control API',
            'version' => '2026-07-10-v1',
            'security' => [
                'header_only_bearer_token' => defined('ELITE_CODEX_API_V1') && ELITE_CODEX_API_V1,
                'signed_requests' => defined('ELITE_CODEX_API_V1') && ELITE_CODEX_API_V1,
                'replay_protection' => defined('ELITE_CODEX_API_V1') && ELITE_CODEX_API_V1,
                'scoped_permissions' => defined('ELITE_CODEX_API_V1') && ELITE_CODEX_API_V1,
                'idempotency_required_for_post' => defined('ELITE_CODEX_API_V1') && ELITE_CODEX_API_V1,
            ],
            'safety_rules' => [
                'patient_facing_send_requires_explicit_approval' => true,
                'stage_change_requires_explicit_stage_approval' => true,
                'draft_before_send' => true,
                'notes_and_read_actions_allowed' => true,
                'no_phone_numbers_in_message_body_policy' => true,
            ],
            'read_actions' => [
                'health', 'capabilities', 'stages', 'pipeline_snapshot',
                'crm_operator_brief', 'crm_operator_command_center', 'lead_queue_summary', 'list_leads', 'lead_queue', 'nurture_candidates',
                'sms_delivery_issues', 'conversation_quality', 'meta_lead_ad_correlation',
                'api_self_check', 'inbox', 'get_lead', 'get_thread',
                'find_lead', 'search_leads', 'find_duplicates',
                'mobile_notifications', 'mobile_push_status', 'elite_ai_audit_recent', 'assistant_prompt',
                'elite_ai_pending_drafts',
            ],
            'write_actions' => [
                'create_lead', 'import_meta_leads', 'add_note',
                'mark_notification_reviewed', 'update_lead', 'delete_lead', 'move_stage',
                'prepare_sms_followup', 'draft_email', 'send_sms', 'nurture_reactivation_send',
                'send_email', 'send_internal_sms', 'merge_leads',
                'elite_ai_cancel_draft', 'mobile_push_test',
            ],
            'approval_required' => [
                'send_sms' => ['send_approved' => true],
                'send_internal_sms' => ['send_approved' => true],
                'send_email' => ['send_approved' => true],
                'delete_lead' => ['delete_approved' => true],
                'move_stage' => ['stage_approved' => true],
            ],
        ]);
    }
}

if (!function_exists('codex_api_conversion_stage_key_from_label')) {
    function codex_api_conversion_stage_key_from_label(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $normalized = str_replace(['-', ' '], '_', $value);
        $normalized = (string) preg_replace('/[^a-z0-9_]+/', '', $normalized);
        $aliases = [
            'first_touch' => 'first_touch_sent',
            'first_touch_sent' => 'first_touch_sent',
            'contacted' => 'first_touch_sent',
            'active_followup' => 'active_follow_up',
            'active_follow_up' => 'active_follow_up',
            'nurture' => 'nurture_lost',
            'no_answer' => 'nurture_lost',
            'no_answer_nurture' => 'nurture_lost',
            'lost_nurture' => 'nurture_lost',
            'nurture_lost' => 'nurture_lost',
        ];
        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        if (function_exists('lead_conversion_stage_labels')) {
            foreach (lead_conversion_stage_labels() as $key => $label) {
                $labelKey = strtolower((string) preg_replace('/[^a-z0-9_]+/', '', str_replace(['-', ' '], '_', (string) $label)));
                if ($normalized === $key || $normalized === $labelKey) {
                    return (string) $key;
                }
            }
        }

        return $normalized;
    }
}

if (!function_exists('codex_api_enriched_lead')) {
    function codex_api_enriched_lead(array $lead): array
    {
        $stage = trim((string) ($lead['status'] ?? ''));
        $legacyLabels = function_exists('lead_stage_labels') ? lead_stage_labels() : [];
        $legacyLabel = (string) ($legacyLabels[$stage] ?? ($stage !== '' ? $stage : 'Unstaged'));
        $summary = function_exists('lead_conversion_summary')
            ? lead_conversion_summary($lead)
            : [
                'stage_key' => $stage,
                'stage_label' => $legacyLabel,
                'next_action' => ['key' => '', 'label' => 'Review next step'],
            ];
        $stageKey = function_exists('lead_conversion_stage_key')
            ? lead_conversion_stage_key($lead)
            : trim((string) ($summary['stage_key'] ?? $stage));
        $stageLabel = trim((string) ($summary['stage_label'] ?? ''));
        if ($stageLabel === '') {
            $stageLabel = function_exists('lead_conversion_stage_labels')
                ? (string) ((lead_conversion_stage_labels())[$stageKey] ?? $legacyLabel)
                : $legacyLabel;
        }
        $nextAction = (array) ($summary['next_action'] ?? []);
        $sourceLabel = function_exists('lead_operator_source_label')
            ? lead_operator_source_label($lead)
            : trim((string) ($lead['source'] ?? 'Unknown'));

        $lead['legacy_status'] = $stage;
        $lead['legacy_status_label'] = $legacyLabel;
        $lead['conversion_stage_key'] = $stageKey;
        $lead['conversion_stage_label'] = $stageLabel;
        $lead['conversion_stage'] = $stageLabel;
        $lead['next_action_key'] = (string) ($nextAction['key'] ?? '');
        $lead['next_action_label'] = (string) ($nextAction['label'] ?? 'Review next step');
        $lead['next_action_tone'] = (string) ($nextAction['tone'] ?? 'slate');
        $lead['next_action'] = $nextAction;
        $lead['source_label'] = $sourceLabel !== '' ? $sourceLabel : 'Unknown';
        if (function_exists('lead_operator_data_quality_flags')) {
            $lead['data_quality_flags'] = lead_operator_data_quality_flags($lead);
        }

        return $lead;
    }
}

if (!function_exists('codex_api_pipeline_snapshot')) {
    function codex_api_pipeline_snapshot(): void
    {
        if (function_exists('lead_pipeline_ensure_schema')) {
            lead_pipeline_ensure_schema();
        }

        $limit = max(1, min(500, (int) codex_api_value('limit', 250)));
        $rows = function_exists('lead_pipeline_rows')
            ? lead_pipeline_rows($limit)
            : db_all('SELECT ' . codex_api_select_fields() . ' FROM leads ORDER BY updated_at DESC, id DESC LIMIT ' . $limit);
        $firstRow = is_array($rows) ? reset($rows) : null;
        $rowsAreFlat = is_array($firstRow) && array_key_exists('id', $firstRow);
        if ($rows && !$rowsAreFlat) {
            $flattenedRows = [];
            foreach ($rows as $stageRows) {
                if (!is_array($stageRows)) {
                    continue;
                }
                foreach ($stageRows as $stageRow) {
                    if (is_array($stageRow) && array_key_exists('id', $stageRow)) {
                        $flattenedRows[] = $stageRow;
                    }
                }
            }
            $rows = $flattenedRows;
        }

        $legacyStages = lead_stage_labels();
        $stageCounts = [];
        $conversionCounts = [];
        $sourceCounts = [];
        $actionCounts = [];
        $dataQualityFlags = [];
        $latestByConversionStage = [];

        foreach ($rows as $row) {
            $stage = trim((string) ($row['status'] ?? ''));
            $stageLabel = $legacyStages[$stage] ?? ($stage !== '' ? $stage : 'Unstaged');
            $stageCounts[$stageLabel] = ($stageCounts[$stageLabel] ?? 0) + 1;

            $enrichedRow = codex_api_enriched_lead($row);
            $conversionLabel = trim((string) ($enrichedRow['conversion_stage_label'] ?? $stageLabel));
            if ($conversionLabel === '') {
                $conversionLabel = $stageLabel;
            }
            $conversionCounts[$conversionLabel] = ($conversionCounts[$conversionLabel] ?? 0) + 1;

            $sourceLabel = trim((string) ($enrichedRow['source_label'] ?? 'Unknown'));
            $sourceLabel = $sourceLabel !== '' ? $sourceLabel : 'Unknown';
            $sourceCounts[$sourceLabel] = ($sourceCounts[$sourceLabel] ?? 0) + 1;

            $nextAction = (array)($enrichedRow['next_action'] ?? []);
            $actionLabel = trim((string)($nextAction['label'] ?? 'Review next step'));
            $actionCounts[$actionLabel] = ($actionCounts[$actionLabel] ?? 0) + 1;

            if (function_exists('lead_operator_data_quality_flags')) {
                foreach (lead_operator_data_quality_flags($row) as $flag) {
                    $dataQualityFlags[$flag] = ($dataQualityFlags[$flag] ?? 0) + 1;
                }
            }

            if (!isset($latestByConversionStage[$conversionLabel])) {
                $latestByConversionStage[$conversionLabel] = [];
            }
            if (count($latestByConversionStage[$conversionLabel]) < 5) {
                $latestByConversionStage[$conversionLabel][] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'full_name' => (string) ($row['full_name'] ?? ''),
                    'legacy_status' => $stage,
                    'legacy_status_label' => $stageLabel,
                    'conversion_stage_key' => (string) ($enrichedRow['conversion_stage_key'] ?? ''),
                    'conversion_stage' => $conversionLabel,
                    'conversion_stage_label' => $conversionLabel,
                    'updated_at' => (string) ($row['updated_at'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'last_inbound_at' => (string) ($row['last_inbound_at'] ?? ''),
                    'last_outbound_at' => (string) ($row['last_outbound_at'] ?? ''),
                    'unread_message_count' => (int) ($row['unread_message_count'] ?? 0),
                    'source_label' => $sourceLabel,
                    'next_action_key' => (string) ($enrichedRow['next_action_key'] ?? ''),
                    'next_action' => $actionLabel,
                    'next_action_label' => $actionLabel,
                ];
            }
        }
        arsort($sourceCounts);
        arsort($actionCounts);
        arsort($dataQualityFlags);

        codex_api_response([
            'ok' => true,
            'generated_at' => now(),
            'limit' => $limit,
            'row_count' => count($rows),
            'legacy_stage_counts' => $stageCounts,
            'conversion_stage_counts' => $conversionCounts,
            'source_counts' => $sourceCounts,
            'next_action_counts' => $actionCounts,
            'data_quality_flags' => $dataQualityFlags,
            'latest_by_conversion_stage' => $latestByConversionStage,
            'safety_note' => 'Read-only snapshot. No lead status was changed.',
        ]);
    }
}

if (!function_exists('codex_api_lead_queue_summary')) {
    function codex_api_lead_queue_summary(): void
    {
        $limitPerQueue = max(1, min(20, (int) codex_api_value('limit_per_queue', 5)));
        $rows = function_exists('lead_pipeline_rows') ? lead_pipeline_rows(1000) : [];
        $summary = [];

        foreach ($rows as $stageRows) {
            if (!is_array($stageRows)) {
                continue;
            }
            foreach ($stageRows as $lead) {
                if (!is_array($lead)) {
                    continue;
                }
                $lead = codex_api_enriched_lead($lead);
                $key = (string)($lead['conversion_stage_key'] ?? 'unknown');
                $label = (string)($lead['conversion_stage_label'] ?? $key);
                if (!isset($summary[$key])) {
                    $summary[$key] = [
                        'conversion_stage_key' => $key,
                        'conversion_stage_label' => $label,
                        'count' => 0,
                        'due_count' => 0,
                        'delivery_issue_count' => 0,
                        'top_leads' => [],
                    ];
                }
                $summary[$key]['count']++;
                $nextFollowUp = trim((string)($lead['next_follow_up_at'] ?? ''));
                if ($nextFollowUp !== '' && strtotime($nextFollowUp) !== false && strtotime($nextFollowUp) <= time()) {
                    $summary[$key]['due_count']++;
                }
                if (codex_api_lead_has_sms_delivery_issue((int)($lead['id'] ?? 0))) {
                    $summary[$key]['delivery_issue_count']++;
                }
                if (count($summary[$key]['top_leads']) < $limitPerQueue) {
                    $summary[$key]['top_leads'][] = [
                        'id' => (int)($lead['id'] ?? 0),
                        'full_name' => (string)($lead['full_name'] ?? ''),
                        'next_action_key' => (string)($lead['next_action_key'] ?? ''),
                        'next_action_label' => (string)($lead['next_action_label'] ?? ''),
                        'last_outbound_at' => (string)($lead['last_outbound_at'] ?? ''),
                        'last_inbound_at' => (string)($lead['last_inbound_at'] ?? ''),
                        'next_follow_up_at' => (string)($lead['next_follow_up_at'] ?? ''),
                        'sms_opt_status' => (string)($lead['sms_opt_status'] ?? 'unknown'),
                    ];
                }
            }
        }

        codex_api_response(['ok' => true, 'generated_at' => now(), 'queues' => array_values($summary)]);
    }
}

if (!function_exists('codex_api_lead_has_sms_delivery_issue')) {
    function codex_api_lead_has_sms_delivery_issue(int $leadId): bool
    {
        if ($leadId <= 0) {
            return false;
        }
        if (function_exists('codex_api_load_lead') && function_exists('lead_operator_has_sms_cleanup_issue')) {
            try {
                $lead = codex_api_load_lead($leadId);
                if ($lead) {
                    return lead_operator_has_sms_cleanup_issue($lead);
                }
            } catch (Throwable $e) {
                // Fall back to the legacy activity check below.
            }
        }
        try {
            return (bool) db_value(
                "SELECT COUNT(*) FROM lead_activities WHERE lead_id = :lead_id AND type = 'sms_delivery_issue'",
                ['lead_id' => $leadId]
            );
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('codex_api_nurture_message')) {
    function codex_api_nurture_message(array $lead): string
    {
        $first = trim((string)($lead['first_name'] ?? ''));
        if ($first === '') {
            $parts = preg_split('/\s+/', trim((string)($lead['full_name'] ?? '')));
            $first = trim((string)($parts[0] ?? ''));
        }
        $first = $first !== '' ? $first : 'there';
        return "Hi {$first}, Rod from Elite Smiles. Just checking in softly. If you're still curious what's possible for your smile, the consultation is complimentary and completely custom. Want me to send a couple times this week? Reply STOP to opt out.";
    }
}

if (!function_exists('codex_api_nurture_candidates')) {
    function codex_api_nurture_candidates(): void
    {
        $limit = max(1, min(100, (int) codex_api_value('limit', 25)));
        $minDays = max(7, min(180, (int) codex_api_value('min_days', 14)));
        $rows = db_all(
            "SELECT " . codex_api_select_fields() . "
             FROM leads
             WHERE status = 'no_answer'
               AND (created_at IS NULL OR created_at <= DATE_SUB(NOW(), INTERVAL {$minDays} DAY))
               AND (sms_opt_status IS NULL OR sms_opt_status NOT IN ('opted_out', 'dnd'))
               AND phone IS NOT NULL AND TRIM(phone) <> ''
               AND (unread_message_count IS NULL OR unread_message_count = 0)
               AND (last_inbound_at IS NULL OR last_outbound_at IS NULL OR last_inbound_at < last_outbound_at)
               AND (last_outbound_at IS NULL OR last_outbound_at <= DATE_SUB(NOW(), INTERVAL {$minDays} DAY))
             ORDER BY COALESCE(last_outbound_at, updated_at, created_at, '1970-01-01') ASC, id ASC
             LIMIT {$limit}"
        );
        $candidates = [];
        foreach ($rows as $row) {
            $lead = codex_api_enriched_lead($row);
            $lead['suggested_sms'] = codex_api_nurture_message($lead);
            $lead['sms_delivery_issue'] = codex_api_lead_has_sms_delivery_issue((int)($lead['id'] ?? 0));
            $candidates[] = $lead;
        }
        codex_api_response([
            'ok' => true,
            'criteria' => "no_answer, {$minDays}+ days since outbound, SMS allowed, no unread inbound",
            'count' => count($candidates),
            'candidates' => $candidates,
        ]);
    }
}

if (!function_exists('codex_api_nurture_reactivation_send')) {
    function codex_api_nurture_reactivation_send(): void
    {
        $leadId = (int) codex_api_value('lead_id', 0);
        $lead = codex_api_load_lead($leadId);
        if ((string)($lead['status'] ?? '') !== 'no_answer') {
            codex_api_response(['ok' => false, 'message' => 'Lead is not in no-answer nurture.', 'lead_id' => $leadId], 409);
        }
        if (in_array((string)($lead['sms_opt_status'] ?? ''), ['dnd', 'opted_out'], true)) {
            codex_api_response(['ok' => false, 'message' => 'Lead is not SMS eligible.', 'lead_id' => $leadId], 409);
        }
        if (!codex_api_has_explicit_send_approval(codex_api_body())) {
            codex_api_response([
                'ok' => false,
                'message' => 'Nurture SMS send blocked until explicit send approval is provided.',
                'approval_required' => 'send_approved',
                'lead_id' => $leadId,
            ], 409);
        }

        $message = trim((string) codex_api_value('message', ''));
        if ($message === '') {
            $message = codex_api_nurture_message($lead);
        }
        $sendResult = elite_twilio_send_sms(trim((string)($lead['phone'] ?? '')), $message, [
            'lead_id' => $leadId,
            'lead' => $lead,
            'append_opt_out_notice' => false,
            'send_pushover_fallback' => true,
            'fallback_summary' => 'Twilio could not send the nurture reactivation SMS.',
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
        lead_comm_insert_activity($leadId, 'sms_outbound', 'Sent nurture reactivation SMS through Codex API.', [
            'message_id' => $messageRecordId,
            'twilio_sid' => $sendResult['twilio_sid'] ?? '',
            'source' => 'codex_api_nurture_reactivation',
        ], (string) codex_api_value('created_by', 'Codex'));
        codex_api_record_outbound_note($leadId, 'sms', '', $sentBody, (string)codex_api_value('created_by', 'Codex'), [
            'message_id' => $messageRecordId,
            'twilio_sid' => $sendResult['twilio_sid'] ?? '',
        ]);
        lead_comm_update_rollup($leadId);
        db_query(
            "UPDATE leads
             SET follow_up_status = 'not_checked',
                 next_follow_up_at = DATE_ADD(NOW(), INTERVAL 7 DAY),
                 last_follow_up_check_at = NOW()
             WHERE id = :id
             LIMIT 1",
            ['id' => $leadId]
        );
        codex_api_response(['ok' => true, 'message' => 'Nurture reactivation SMS sent and logged.', 'lead_id' => $leadId, 'thread' => codex_api_timeline($leadId)]);
    }
}

if (!function_exists('codex_api_sms_delivery_issues')) {
    function codex_api_sms_delivery_issues(): void
    {
        $limit = max(1, min(100, (int) codex_api_value('limit', 25)));
        $rows = db_all(
            "SELECT a.id AS activity_id, a.lead_id, a.body, a.meta_json, a.created_at, l.full_name, l.phone, l.email, l.sms_opt_status, l.status
             FROM lead_activities a
             LEFT JOIN leads l ON l.id = a.lead_id
             WHERE a.type = 'sms_delivery_issue'
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT {$limit}"
        );
        foreach ($rows as &$row) {
            $meta = json_decode((string)($row['meta_json'] ?? ''), true);
            $row['meta'] = is_array($meta) ? $meta : [];
            unset($row['meta_json']);
            $row['recommended_action'] = in_array((string)($row['sms_opt_status'] ?? ''), ['dnd', 'opted_out'], true)
                ? 'Use non-SMS channel.'
                : 'Verify phone, mark SMS DND if repeated, and use email.';
        }
        unset($row);
        codex_api_response(['ok' => true, 'count' => count($rows), 'issues' => $rows]);
    }
}

if (!function_exists('codex_api_conversation_quality')) {
    function codex_api_conversation_quality(): void
    {
        $limit = max(1, min(500, (int) codex_api_value('limit', 250)));
        $rows = db_all(
            "SELECT l.id, l.full_name, l.status, l.campaign, l.source_ad_set, l.source_ad_name, l.sms_opt_status,
                    l.last_inbound_at, l.last_outbound_at, l.unread_message_count,
                    SUM(CASE WHEN m.direction = 'outbound' AND m.channel = 'sms' THEN 1 ELSE 0 END) AS outbound_sms,
                    SUM(CASE WHEN m.direction = 'inbound' AND m.channel = 'sms' THEN 1 ELSE 0 END) AS inbound_sms,
                    SUM(CASE WHEN m.twilio_status IN ('failed','undelivered') OR m.twilio_error_code <> '' THEN 1 ELSE 0 END) AS sms_failures
             FROM leads l
             LEFT JOIN lead_messages m ON m.lead_id = l.id
             GROUP BY l.id
             ORDER BY l.updated_at DESC, l.id DESC
             LIMIT {$limit}"
        );
        $summary = [
            'reviewed_leads' => count($rows),
            'has_reply' => 0,
            'no_reply_after_sms' => 0,
            'sms_delivery_issue' => 0,
            'dnd_or_opted_out' => 0,
        ];
        foreach ($rows as &$row) {
            $row['outbound_sms'] = (int)($row['outbound_sms'] ?? 0);
            $row['inbound_sms'] = (int)($row['inbound_sms'] ?? 0);
            $row['sms_failures'] = (int)($row['sms_failures'] ?? 0);
            $row['quality_flags'] = [];
            if ($row['inbound_sms'] > 0) {
                $summary['has_reply']++;
            }
            if ($row['outbound_sms'] > 0 && $row['inbound_sms'] === 0) {
                $summary['no_reply_after_sms']++;
                $row['quality_flags'][] = 'no_reply_after_sms';
            }
            if ($row['sms_failures'] > 0 || codex_api_lead_has_sms_delivery_issue((int)$row['id'])) {
                $summary['sms_delivery_issue']++;
                $row['quality_flags'][] = 'sms_delivery_issue';
            }
            if (in_array((string)($row['sms_opt_status'] ?? ''), ['dnd', 'opted_out'], true)) {
                $summary['dnd_or_opted_out']++;
                $row['quality_flags'][] = 'dnd_or_opted_out';
            }
        }
        unset($row);
        codex_api_response(['ok' => true, 'summary' => $summary, 'leads' => $rows]);
    }
}

if (!function_exists('codex_api_meta_lead_ad_correlation')) {
    function codex_api_meta_lead_ad_correlation(): void
    {
        $limit = max(1, min(200, (int) codex_api_value('limit', 50)));
        $groupBy = trim((string) codex_api_value('group_by', 'source_ad_set'));
        $allowed = ['campaign', 'source_campaign', 'source_ad_set', 'source_ad_name', 'source', 'source_type'];
        if (!in_array($groupBy, $allowed, true) || !leads_has_column($groupBy)) {
            $groupBy = leads_has_column('source_ad_set') ? 'source_ad_set' : 'campaign';
        }
        $rows = db_all(
            "SELECT COALESCE(NULLIF({$groupBy}, ''), 'Unknown') AS bucket,
                    COUNT(*) AS leads,
                    SUM(CASE WHEN last_inbound_at IS NOT NULL THEN 1 ELSE 0 END) AS replied,
                    SUM(CASE WHEN consultation_status IN ('scheduled','booked','completed') OR status IN ('consultation_booked','consult_completed','treatment_accepted','treatment_completed') THEN 1 ELSE 0 END) AS advanced,
                    SUM(CASE WHEN sms_opt_status IN ('dnd','opted_out') THEN 1 ELSE 0 END) AS sms_blocked,
                    MIN(created_at) AS first_seen,
                    MAX(created_at) AS last_seen
             FROM leads
             GROUP BY COALESCE(NULLIF({$groupBy}, ''), 'Unknown')
             ORDER BY leads DESC, replied DESC
             LIMIT {$limit}"
        );
        foreach ($rows as &$row) {
            $leads = max(1, (int)$row['leads']);
            $row['reply_rate'] = round(((int)$row['replied'] / $leads) * 100, 1);
            $row['advanced_rate'] = round(((int)$row['advanced'] / $leads) * 100, 1);
        }
        unset($row);
        codex_api_response(['ok' => true, 'group_by' => $groupBy, 'rows' => $rows]);
    }
}

if (!function_exists('codex_api_self_check')) {
    function codex_api_self_check(): void
    {
        $tables = [];
        foreach (['leads', 'lead_messages', 'lead_activities', 'lead_emails', 'codex_api_clients'] as $table) {
            $tables[$table] = lead_comm_table_exists($table);
        }
        codex_api_response([
            'ok' => true,
            'time' => now(),
            'app_url' => APP_URL,
            'tables' => $tables,
            'twilio_configured' => function_exists('elite_twilio_is_configured') ? elite_twilio_is_configured() : false,
            'smtp_configured' => function_exists('elite_smtp_is_configured') ? elite_smtp_is_configured() : false,
            'meta_token_configured' => defined('META_ACCESS_TOKEN') && META_ACCESS_TOKEN !== '',
            'meta_forms_configured' => defined('META_FORM_IDS') && META_FORM_IDS !== '',
            'pushover_configured' => defined('ELITE_PUSHOVER_APP_TOKEN') && ELITE_PUSHOVER_APP_TOKEN !== '' && defined('ELITE_PUSHOVER_USER_KEY') && ELITE_PUSHOVER_USER_KEY !== '',
            'recent_delivery_issues_24h' => (int) db_value("SELECT COUNT(*) FROM lead_activities WHERE type = 'sms_delivery_issue' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"),
            'unread_inbound' => leads_has_column('unread_message_count') ? (int) db_value('SELECT COALESCE(SUM(unread_message_count),0) FROM leads') : null,
        ]);
    }
}

if (!function_exists('codex_api_operator_draft_message')) {
    function codex_api_operator_draft_message(array $lead, string $actionKey): string
    {
        $first = trim((string)($lead['first_name'] ?? ''));
        if ($first === '') {
            $parts = preg_split('/\s+/', trim((string)($lead['full_name'] ?? '')));
            $first = trim((string)($parts[0] ?? ''));
        }
        $first = $first !== '' ? $first : 'there';

        return match ($actionKey) {
            'reply_needed' => "Hi {$first}, this is Rod with Elite Smiles. I saw your message and wanted to help personally. The best next step is a complimentary consult so we can see what is actually right for you. Would morning or afternoon work better?",
            'first_touch' => "Hi {$first}, this is Rod with Elite Smiles. Thanks for reaching out. Every smile plan is custom, so the easiest next step is a complimentary consult where we can see what is possible for you. Would you prefer Draper or Park City?",
            'second_follow_up', 'overdue_follow_up' => "Hi {$first}, Rod from Elite Smiles checking back in. No pressure at all. If you are still interested, we can schedule a complimentary consult and answer your questions based on your smile, not a generic price sheet. Want me to send a couple available times?",
            'ask_dob' => "Hi {$first}, we are almost set. To finish the appointment details, can you send your date of birth?",
            'confirm_appointment' => "Hi {$first}, Rod from Elite Smiles. Just confirming your complimentary consult. Reply YES if that still works, or send me a better time if you need to move it.",
            'offer_dates' => "Hi {$first}, happy to help. The consult is complimentary and custom to your goals. Would a morning or afternoon appointment be easier for you?",
            'reschedule' => "Hi {$first}, Rod from Elite Smiles. Sorry we missed you. If you still want to explore options, I can help reschedule your complimentary consult. Would this week or next week be better?",
            'nurture_reactivate' => codex_api_nurture_message($lead),
            default => "Hi {$first}, Rod from Elite Smiles. I wanted to check in and help with the next step. Would you like me to send a couple complimentary consult times?",
        };
    }
}

if (!function_exists('codex_api_operator_action_card')) {
    function codex_api_operator_action_card(array $lead, string $category, int $priority, string $reason, string $recommendedAction): array
    {
        $lead = codex_api_enriched_lead($lead);
        $leadId = (int)($lead['id'] ?? 0);
        $actionKey = (string)($lead['next_action_key'] ?? 'review_next_step');
        $smsStatus = (string)($lead['sms_opt_status'] ?? 'unknown');
        $smsIssue = codex_api_lead_has_sms_delivery_issue($leadId);
        $preferredChannel = ($smsIssue || in_array($smsStatus, ['dnd', 'opted_out'], true)) ? 'email' : 'sms';

        return [
            'category' => $category,
            'priority' => $priority,
            'lead' => [
                'id' => $leadId,
                'full_name' => (string)($lead['full_name'] ?? ''),
                'email' => (string)($lead['email'] ?? ''),
                'phone_present' => trim((string)($lead['phone'] ?? '')) !== '',
                'conversion_stage_key' => (string)($lead['conversion_stage_key'] ?? ''),
                'conversion_stage_label' => (string)($lead['conversion_stage_label'] ?? ''),
                'next_action_key' => $actionKey,
                'next_action_label' => (string)($lead['next_action_label'] ?? ''),
                'source' => (string)($lead['source'] ?? ''),
                'campaign' => (string)($lead['campaign'] ?? ''),
                'source_ad_set' => (string)($lead['source_ad_set'] ?? ''),
                'source_ad_name' => (string)($lead['source_ad_name'] ?? ''),
                'last_inbound_at' => (string)($lead['last_inbound_at'] ?? ''),
                'last_outbound_at' => (string)($lead['last_outbound_at'] ?? ''),
                'next_follow_up_at' => (string)($lead['next_follow_up_at'] ?? ''),
                'unread_message_count' => (int)($lead['unread_message_count'] ?? 0),
                'sms_opt_status' => $smsStatus,
                'sms_delivery_issue' => $smsIssue,
            ],
            'reason' => $reason,
            'recommended_action' => $recommendedAction,
            'suggested_channel' => $preferredChannel,
            'draft' => [
                'channel' => $preferredChannel,
                'body' => codex_api_operator_draft_message($lead, $actionKey),
                'approval_required' => true,
            ],
            'safe_next_api_actions' => [
                'get_lead',
                'get_thread',
                $preferredChannel === 'email' ? 'send_email' : 'send_sms',
                'add_note',
                'update_lead',
            ],
        ];
    }
}

if (!function_exists('codex_api_operator_resolved_lost_lead')) {
    function codex_api_operator_resolved_lost_lead(array $lead): bool
    {
        $status = trim((string)($lead['status'] ?? ''));
        $stageKey = trim((string)($lead['conversion_stage_key'] ?? ''));
        $lostReason = strtolower(trim((string)($lead['lost_reason'] ?? '')));
        $notes = strtolower(trim((string)($lead['notes'] ?? '')));

        if (in_array($status, ['lost_lead', 'opted_out'], true)) {
            return true;
        }
        if ($lostReason !== '') {
            return true;
        }

        return (bool) preg_match(
            '/\b(?:decided|chose|went|going)\s+(?:to\s+)?(?:do\s+)?(?:treatment\s+)?with\s+(?:another|other)\s+provider\b|\bother\s+provider\b|\banother\s+provider\b|\bnot\s+an\s+active\s+scheduling\s+lead\b/i',
            $notes
        );
    }
}

if (!function_exists('codex_api_operator_text_contains')) {
    function codex_api_operator_text_contains(string $text, array $patterns): bool
    {
        $text = strtolower($text);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('codex_api_operator_recent_thread_signals')) {
    function codex_api_operator_recent_thread_signals(int $leadId): array
    {
        $signals = [
            'last_inbound_body' => '',
            'last_outbound_body' => '',
            'last_inbound_at' => '',
            'last_outbound_at' => '',
            'call_requested' => false,
            'pricing_question' => false,
            'location_question' => false,
            'scheduling_intent' => false,
            'stop_requested' => false,
        ];
        if ($leadId <= 0) {
            return $signals;
        }

        try {
            $messages = db_all(
                "SELECT direction, body, created_at
                 FROM lead_messages
                 WHERE lead_id = :lead_id
                 ORDER BY created_at DESC, id DESC
                 LIMIT 12",
                ['lead_id' => $leadId]
            );
        } catch (Throwable $e) {
            return $signals;
        }

        foreach ($messages as $message) {
            $direction = (string)($message['direction'] ?? '');
            $body = trim((string)($message['body'] ?? ''));
            $createdAt = (string)($message['created_at'] ?? '');
            if ($direction === 'inbound' && $signals['last_inbound_body'] === '') {
                $signals['last_inbound_body'] = codex_api_text_excerpt($body, 240);
                $signals['last_inbound_at'] = $createdAt;
            }
            if ($direction === 'outbound' && $signals['last_outbound_body'] === '') {
                $signals['last_outbound_body'] = codex_api_text_excerpt($body, 240);
                $signals['last_outbound_at'] = $createdAt;
            }
            if ($direction !== 'inbound') {
                continue;
            }
            $signals['call_requested'] = $signals['call_requested'] || codex_api_operator_text_contains($body, [
                '/\bcall\b/i', '/\bphone\b/i', '/\bll[aá]mame\b/i', '/\bllamar\b/i', '/\btalk\s+on\s+the\s+phone\b/i',
            ]);
            $signals['pricing_question'] = $signals['pricing_question'] || codex_api_operator_text_contains($body, [
                '/\bprice\b/i', '/\bcost\b/i', '/\bpricing\b/i', '/\bfinanc/i', '/\bprecio\b/i', '/\bcosto\b/i',
            ]);
            $signals['location_question'] = $signals['location_question'] || codex_api_operator_text_contains($body, [
                '/\blocation\b/i', '/\baddress\b/i', '/\bdonde\b/i', '/\bd[oó]nde\b/i', '/\bubic/i',
            ]);
            $signals['scheduling_intent'] = $signals['scheduling_intent'] || codex_api_operator_text_contains($body, [
                '/\bmorning\b/i', '/\bafternoon\b/i', '/\btomorrow\b/i', '/\bmonday\b/i', '/\btuesday\b/i', '/\bwednesday\b/i',
                '/\bjueves\b/i', '/\blunes\b/i', '/\bmartes\b/i', '/\bmi[eé]rcoles\b/i', '/\bappointment\b/i', '/\bcita\b/i',
                '/\bagend/i', '/\bschedul/i',
            ]);
            $signals['stop_requested'] = $signals['stop_requested'] || codex_api_operator_text_contains($body, [
                '/^\s*stop\s*$/i', '/\bstop\b/i', '/\bno\s+me\s+(?:text|mandes|envies)\b/i',
            ]);
        }

        return $signals;
    }
}

if (!function_exists('codex_api_operator_action_bucket')) {
    function codex_api_operator_action_bucket(array $action, string $bucket): array
    {
        $action['bucket'] = $bucket;
        return $action;
    }
}

if (!function_exists('codex_api_crm_operator_command_center')) {
    function codex_api_crm_operator_command_center(): void
    {
        $mode = strtolower(trim((string) codex_api_value('mode', 'hourly')));
        if (!in_array($mode, ['hourly', 'daily'], true)) {
            $mode = 'hourly';
        }
        $limit = max(5, min(75, (int) codex_api_value('limit', $mode === 'hourly' ? 20 : 50)));
        $rows = function_exists('lead_pipeline_rows') ? lead_pipeline_rows(1000) : [];
        $now = time();

        $buckets = [
            'do_now' => [],
            'cleanup' => [],
            'manual_review' => [],
            'nurture_candidates' => [],
            'wait' => [],
        ];
        $counts = [
            'reviewed' => 0,
            'do_now' => 0,
            'cleanup' => 0,
            'manual_review' => 0,
            'nurture_candidates' => 0,
            'wait' => 0,
            'resolved_lost_skipped' => 0,
            'sms_blocked' => 0,
        ];
        $seenDelivery = [];

        foreach ($rows as $stageRows) {
            if (!is_array($stageRows)) {
                continue;
            }
            foreach ($stageRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $lead = codex_api_enriched_lead($row);
                $leadId = (int)($lead['id'] ?? 0);
                if ($leadId <= 0) {
                    continue;
                }
                $counts['reviewed']++;
                $actionKey = (string)($lead['next_action_key'] ?? '');
                $stageKey = (string)($lead['conversion_stage_key'] ?? '');
                $status = (string)($lead['status'] ?? '');
                $smsStatus = (string)($lead['sms_opt_status'] ?? 'unknown');
                $nextFollowUp = trim((string)($lead['next_follow_up_at'] ?? ''));
                $nextFollowUpTs = $nextFollowUp !== '' ? (strtotime($nextFollowUp) ?: null) : null;
                $isDue = $nextFollowUpTs !== null && $nextFollowUpTs <= $now;
                $recentlyContacted = false;
                $firstTouchDue = false;
                $lastOutbound = trim((string)($lead['last_outbound_at'] ?? ''));
                if ($lastOutbound !== '' && ($lastOutboundTs = strtotime($lastOutbound)) !== false) {
                    $recentlyContacted = ($now - $lastOutboundTs) < 3.5 * 3600;
                    $firstTouchDue = $stageKey === 'first_touch_sent' && !$recentlyContacted;
                }
                $smsIssue = codex_api_lead_has_sms_delivery_issue($leadId);
                $signals = codex_api_operator_recent_thread_signals($leadId);

                if (in_array($smsStatus, ['dnd', 'opted_out'], true)) {
                    $counts['sms_blocked']++;
                }
                if (codex_api_operator_resolved_lost_lead($lead)) {
                    $counts['resolved_lost_skipped']++;
                    continue;
                }
                if (in_array($status, ['treatment_completed'], true) || $stageKey === 'treatment_completed') {
                    $counts['wait']++;
                    if ($mode === 'daily') {
                        $buckets['wait'][] = codex_api_operator_action_bucket(
                            codex_api_operator_action_card($lead, 'post_op_wait', 20, 'Treatment is completed; do not send sales follow-up.', 'Wait unless a post-op workflow is needed.'),
                            'wait'
                        );
                    }
                    continue;
                }

                if ($signals['call_requested'] && trim((string)($lead['last_inbound_at'] ?? '')) !== '' && trim((string)($lead['last_inbound_at'] ?? '')) >= trim((string)($lead['last_outbound_at'] ?? ''))) {
                    $buckets['do_now'][] = codex_api_operator_action_bucket(
                        codex_api_operator_action_card($lead, 'call_requested', 100, 'Lead asked for a phone call after the last outbound message.', 'Human call first. Do not send normal nurture until the call is handled.'),
                        'do_now'
                    );
                    continue;
                }

                $lastInbound = trim((string)($lead['last_inbound_at'] ?? ''));
                $inboundIsNewest = $lastInbound !== '' && ($lastOutbound === '' || strtotime($lastInbound) >= strtotime($lastOutbound));
                if ((int)($lead['unread_message_count'] ?? 0) > 0 || ($actionKey === 'reply_needed' && $inboundIsNewest)) {
                    $buckets['do_now'][] = codex_api_operator_action_bucket(
                        codex_api_operator_action_card($lead, 'reply_needed', 95, 'Lead has a reply that needs review.', 'Read context and respond with a direct, human-feeling answer.'),
                        'do_now'
                    );
                    continue;
                }

                if ($actionKey === 'first_touch') {
                    $buckets['do_now'][] = codex_api_operator_action_bucket(
                        codex_api_operator_action_card($lead, 'send_first_touch', 85, 'Lead has not received first touch.', 'Send first touch after approval.'),
                        'do_now'
                    );
                    continue;
                }

                if ($firstTouchDue) {
                    $buckets['do_now'][] = codex_api_operator_action_bucket(
                        codex_api_operator_action_card($lead, 'send_follow_up', 75, 'First touch was sent at least 3.5 hours ago without a reply.', 'Let the Lead Agent send the approved next cadence step.'),
                        'do_now'
                    );
                    continue;
                }

                if ($smsIssue && !isset($seenDelivery[$leadId])) {
                    $seenDelivery[$leadId] = true;
                    $buckets['cleanup'][] = codex_api_operator_action_bucket(
                        codex_api_operator_action_card($lead, 'verify_phone_or_email_only', 70, 'SMS delivery failed for this lead.', in_array($smsStatus, ['dnd', 'opted_out'], true) ? 'Use email only.' : 'Verify phone, mark DND if repeated, and use email fallback.'),
                        'cleanup'
                    );
                    continue;
                }

                if ($stageKey === 'nurture_lost' || $actionKey === 'nurture_reactivate') {
                    if ($mode === 'daily') {
                        $buckets['nurture_candidates'][] = codex_api_operator_action_bucket(
                            codex_api_operator_action_card($lead, 'nurture_reactivation', 40, 'Lead is in nurture/no-answer and may be worth a soft reactivation.', 'Review context before sending a low-pressure reactivation.'),
                            'nurture_candidates'
                        );
                    } else {
                        $counts['wait']++;
                    }
                    continue;
                }

                if ($isDue && in_array($actionKey, ['second_follow_up', 'overdue_follow_up', 'wait_for_reply', 'reschedule', 'ask_dob', 'offer_dates', 'nurture_reactivate'], true)) {
                    $category = in_array($actionKey, ['ask_dob', 'reschedule'], true) ? 'manual_review' : 'send_follow_up';
                    $bucket = $category === 'manual_review' ? 'manual_review' : 'do_now';
                    $buckets[$bucket][] = codex_api_operator_action_bucket(
                        codex_api_operator_action_card($lead, $category, $category === 'manual_review' ? 60 : 75, 'Follow-up date is due now.', $category === 'manual_review' ? 'Review context before sending because the next step affects appointment/procedure state.' : 'Send approved follow-up.'),
                        $bucket
                    );
                    continue;
                }

                if ($stageKey === 'active_follow_up' || $nextFollowUpTs !== null || $recentlyContacted) {
                    $counts['wait']++;
                    if ($mode === 'daily') {
                        $buckets['wait'][] = codex_api_operator_action_bucket(
                            codex_api_operator_action_card($lead, 'wait_until_due', 25, 'Lead is active but not due right now.', 'Wait until next follow-up time or an inbound reply.'),
                            'wait'
                        );
                    }
                    continue;
                }

            }
        }

        foreach ($buckets as $bucket => &$items) {
            usort($items, static function (array $a, array $b): int {
                if ((int)$a['priority'] === (int)$b['priority']) {
                    return (int)($b['lead']['id'] ?? 0) <=> (int)($a['lead']['id'] ?? 0);
                }
                return (int)$b['priority'] <=> (int)$a['priority'];
            });
            $items = array_slice($items, 0, $limit);
            $counts[$bucket] = count($items);
        }
        unset($items);

        $summaryParts = [];
        if ($counts['do_now'] > 0) {
            $summaryParts[] = $counts['do_now'] . ' action(s) to do now';
        }
        if ($counts['cleanup'] > 0) {
            $summaryParts[] = $counts['cleanup'] . ' cleanup item(s)';
        }
        if ($counts['manual_review'] > 0) {
            $summaryParts[] = $counts['manual_review'] . ' manual review item(s)';
        }
        if ($mode === 'daily' && $counts['nurture_candidates'] > 0) {
            $summaryParts[] = $counts['nurture_candidates'] . ' nurture candidate(s)';
        }
        if (!$summaryParts) {
            $summaryParts[] = 'No action needed right now';
        }

        codex_api_response([
            'ok' => true,
            'mode' => $mode,
            'generated_at' => now(),
            'summary' => implode(', ', $summaryParts) . '.',
            'counts' => $counts,
            'command_center' => $buckets,
            'operator_rules' => [
                'read_only' => true,
                'patient_facing_send_requires_explicit_approval' => true,
                'hourly_only_true_due' => true,
                'daily_includes_wait_and_nurture' => true,
                'call_request_beats_sms_follow_up' => true,
                'resolved_lost_leads_are_skipped' => true,
            ],
        ]);
    }
}

if (!function_exists('codex_api_crm_operator_brief')) {
    function codex_api_crm_operator_brief(): void
    {
        $mode = strtolower(trim((string) codex_api_value('mode', 'daily')));
        if (!in_array($mode, ['hourly', 'daily'], true)) {
            $mode = 'daily';
        }
        $limit = max(5, min(50, (int) codex_api_value('limit', $mode === 'hourly' ? 12 : 25)));
        $rows = function_exists('lead_pipeline_rows') ? lead_pipeline_rows(1000) : [];

        $actions = [];
        $counts = [
            'reviewed' => 0,
            'unread_replies' => 0,
            'first_touch_needed' => 0,
            'follow_up_due' => 0,
            'nurture_candidates' => 0,
            'delivery_issues' => 0,
            'sms_blocked' => 0,
            'resolved_lost' => 0,
        ];
        $seen = [];

        foreach ($rows as $stageRows) {
            if (!is_array($stageRows)) {
                continue;
            }
            foreach ($stageRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $lead = codex_api_enriched_lead($row);
                $leadId = (int)($lead['id'] ?? 0);
                if ($leadId <= 0) {
                    continue;
                }
                $counts['reviewed']++;
                $actionKey = (string)($lead['next_action_key'] ?? '');
                $stageKey = (string)($lead['conversion_stage_key'] ?? '');
                $smsStatus = (string)($lead['sms_opt_status'] ?? 'unknown');
                $nextFollowUp = trim((string)($lead['next_follow_up_at'] ?? ''));
                $isDue = $nextFollowUp !== '' && strtotime($nextFollowUp) !== false && strtotime($nextFollowUp) <= time();
                $smsIssue = codex_api_lead_has_sms_delivery_issue($leadId);
                $resolvedLost = codex_api_operator_resolved_lost_lead($lead);

                if ($resolvedLost) {
                    $counts['resolved_lost']++;
                    if (in_array($smsStatus, ['dnd', 'opted_out'], true)) {
                        $counts['sms_blocked']++;
                    }
                    continue;
                }

                if ((int)($lead['unread_message_count'] ?? 0) > 0 || $actionKey === 'reply_needed') {
                    $counts['unread_replies']++;
                    $actions[] = codex_api_operator_action_card($lead, 'urgent_reply', 100, 'Lead appears to have a reply or unread message.', 'Review the thread and respond with a personal scheduling-focused draft.');
                    $seen[$leadId] = true;
                    continue;
                }
                if ($actionKey === 'first_touch') {
                    $counts['first_touch_needed']++;
                    if (!isset($seen[$leadId])) {
                        $actions[] = codex_api_operator_action_card($lead, 'first_touch_needed', 85, 'Lead is ready for first touch.', 'Send the approved first-touch SMS/email and invite the complimentary custom consult.');
                        $seen[$leadId] = true;
                    }
                    continue;
                }
                if ($isDue || in_array($actionKey, ['second_follow_up', 'overdue_follow_up'], true) || $stageKey === 'active_follow_up') {
                    $counts['follow_up_due']++;
                    if (!isset($seen[$leadId])) {
                        $priority = $actionKey === 'overdue_follow_up' ? 80 : 70;
                        $actions[] = codex_api_operator_action_card($lead, 'follow_up_due', $priority, 'Follow-up is due or the lead is in active follow-up.', 'Send a soft follow-up that keeps the goal on scheduling the complimentary consult.');
                        $seen[$leadId] = true;
                    }
                    continue;
                }
                if ($stageKey === 'nurture_lost' || $actionKey === 'nurture_reactivate') {
                    $counts['nurture_candidates']++;
                    if ($mode === 'daily' && !isset($seen[$leadId])) {
                        $actions[] = codex_api_operator_action_card($lead, 'nurture_candidate', 45, 'Lead is in nurture/no-answer and may be worth a soft reactivation.', 'Review context before sending a low-pressure reactivation message.');
                        $seen[$leadId] = true;
                    }
                }
                if ($smsIssue) {
                    $counts['delivery_issues']++;
                    if (!isset($seen[$leadId])) {
                        $actions[] = codex_api_operator_action_card($lead, 'delivery_issue', 65, 'Recent SMS delivery issue found.', 'Verify phone, consider marking SMS DND, and use email if available.');
                        $seen[$leadId] = true;
                    }
                }
                if (in_array($smsStatus, ['dnd', 'opted_out'], true)) {
                    $counts['sms_blocked']++;
                }
            }
        }

        usort($actions, static function (array $a, array $b): int {
            if ((int)$a['priority'] === (int)$b['priority']) {
                return (int)($b['lead']['id'] ?? 0) <=> (int)($a['lead']['id'] ?? 0);
            }
            return (int)$b['priority'] <=> (int)$a['priority'];
        });
        $actions = array_slice($actions, 0, $limit);

        $brief = [];
        if ($counts['unread_replies'] > 0) {
            $brief[] = $counts['unread_replies'] . ' lead(s) may need a reply now.';
        }
        if ($counts['first_touch_needed'] > 0) {
            $brief[] = $counts['first_touch_needed'] . ' lead(s) need first touch.';
        }
        if ($counts['follow_up_due'] > 0) {
            $brief[] = $counts['follow_up_due'] . ' lead(s) need follow-up.';
        }
        if ($mode === 'daily' && $counts['nurture_candidates'] > 0) {
            $brief[] = $counts['nurture_candidates'] . ' nurture/no-answer lead(s) are candidates for review.';
        }
        if ($counts['delivery_issues'] > 0) {
            $brief[] = $counts['delivery_issues'] . ' SMS delivery issue(s) need cleanup.';
        }
        if (!$brief) {
            $brief[] = 'No urgent CRM action found in this pass.';
        }

        codex_api_response([
            'ok' => true,
            'mode' => $mode,
            'generated_at' => now(),
            'summary' => implode(' ', $brief),
            'counts' => $counts,
            'recommended_actions' => $actions,
            'operator_rules' => [
                'read_only' => true,
                'patient_facing_send_requires_explicit_approval' => true,
                'stage_change_requires_explicit_stage_approval' => true,
                'recommended_frequency' => $mode === 'hourly' ? 'Run hourly during business hours.' : 'Run once each morning and once late afternoon.',
            ],
        ]);
    }
}

if (!function_exists('codex_api_assistant_prompt')) {
    function codex_api_assistant_prompt(): void
    {
        $prompt = trim((string) codex_api_value('prompt', ''));
        $quickAction = trim((string) codex_api_value('quick_action', ''));
        $context = (array) codex_api_value('context', []);
        if ($prompt === '' && $quickAction === '') {
            codex_api_response(['ok' => false, 'message' => 'prompt or quick_action is required.'], 422);
        }

        $context['surface'] = 'codex_api';
        $result = elite_ai_handle_request(
            ['id' => 0, 'first_name' => 'Codex', 'last_name' => 'API', 'role' => 'operator'],
            [
                'prompt' => $prompt,
                'quick_action' => $quickAction,
                'context' => $context,
                'surface' => 'codex_api',
            ]
        );

        codex_api_response([
            'ok' => true,
            'assistant' => $result,
            'safety_note' => 'Patient-facing sends still require explicit send approval.',
        ]);
    }
}

if (!function_exists('codex_api_elite_ai_pending_drafts')) {
    function codex_api_elite_ai_pending_drafts(): void
    {
        $limit = (int) codex_api_value('limit', 12);
        $leadId = (int) codex_api_value('lead_id', codex_api_value('id', 0));
        codex_api_response([
            'ok' => true,
            'pending_drafts' => function_exists('elite_ai_pending_drafts_for_user')
                ? elite_ai_pending_drafts_for_user(['id' => 0, 'first_name' => 'Codex', 'last_name' => 'API'], $limit, $leadId > 0 ? $leadId : null)
                : [],
        ]);
    }
}

if (!function_exists('codex_api_elite_ai_cancel_draft')) {
    function codex_api_elite_ai_cancel_draft(): void
    {
        $actionId = (int) codex_api_value('action_id', codex_api_value('id', 0));
        if ($actionId <= 0) {
            codex_api_response(['ok' => false, 'message' => 'action_id is required.'], 422);
        }

        $user = ['id' => 0, 'first_name' => 'Codex', 'last_name' => 'API'];
        $item = function_exists('elite_ai_load_action_item') ? elite_ai_load_action_item($user, $actionId) : null;
        if (!$item) {
            codex_api_response(['ok' => false, 'message' => 'Draft action not found.'], 404);
        }
        if (trim((string)($item['status'] ?? '')) !== 'pending_review') {
            codex_api_response([
                'ok' => true,
                'message' => 'Draft was already not pending.',
                'action_id' => $actionId,
                'status' => trim((string)($item['status'] ?? '')),
            ]);
        }

        $cancelled = function_exists('elite_ai_mark_action_status') && elite_ai_mark_action_status($actionId, 'cancelled');
        codex_api_response([
            'ok' => $cancelled,
            'message' => $cancelled ? 'Draft cancelled.' : 'Draft could not be cancelled.',
            'action_id' => $actionId,
        ], $cancelled ? 200 : 500);
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
        $conversionStage = codex_api_conversion_stage_key_from_label((string) codex_api_value('conversion_stage', codex_api_value('queue', codex_api_value('stage_key', ''))));
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

        $sqlLimit = $conversionStage !== '' ? max($limit, 500) : $limit;
        $sql = 'SELECT ' . codex_api_select_fields() . ' FROM leads';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY updated_at DESC, id DESC LIMIT ' . $sqlLimit;

        $rows = array_map('codex_api_enriched_lead', db_all($sql, $params));
        if ($conversionStage !== '') {
            $rows = array_values(array_filter($rows, static function (array $lead) use ($conversionStage): bool {
                return (string) ($lead['conversion_stage_key'] ?? '') === $conversionStage;
            }));
        }
        $rows = array_slice($rows, 0, $limit);

        codex_api_response([
            'ok' => true,
            'leads' => $rows,
            'filters' => [
                'status' => $status,
                'conversion_stage' => $conversionStage,
                'query' => $query,
                'inbox' => $inboxOnly,
                'limit' => $limit,
            ],
            'stages' => lead_stage_labels(),
            'conversion_stages' => function_exists('lead_conversion_stage_labels') ? lead_conversion_stage_labels() : [],
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
            'follow_up_status', 'dentrix_sync_status', 'dentrix_patient_key',
            'dentrix_appointment_key', 'last_dentrix_sync_at', 'appointment_source',
            'occupied_slot_type', 'external_calendar_block',
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
                codex_api_record_outbound_note($leadId, 'email', $subject, $message, $createdBy, [
                    'email_id' => (int)($sent['email_id'] ?? 0),
                ]);
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
                if (function_exists('lead_comm_clear_follow_up_attention')) {
                    lead_comm_clear_follow_up_attention($leadId);
                }
                codex_api_record_outbound_note($leadId, 'sms', '', $sentBody, $createdBy, [
                    'message_id' => $messageRecordId,
                    'twilio_sid' => $sendResult['twilio_sid'] ?? '',
                ]);
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

            if ($newStage === 'consultation_booked' && function_exists('lead_send_consultation_booked_internal_sms')) {
                lead_send_consultation_booked_internal_sms($leadId, $oldStage, [
                    'source' => 'codex_api',
                    'created_by' => 'Codex',
                ]);
            }
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

if (!function_exists('codex_api_delete_lead')) {
    function codex_api_delete_lead(int $leadId): void
    {
        if ($leadId <= 0) {
            codex_api_response(['ok' => false, 'message' => 'Invalid lead selected.'], 422);
        }

        $request = (array) codex_api_body();
        if (!codex_api_has_explicit_delete_approval($request)) {
            codex_api_response([
                'ok' => false,
                'message' => 'Lead deletion requires explicit delete approval.',
                'approval_required' => 'delete_approved',
                'lead_id' => $leadId,
            ], 409);
        }

        $lead = codex_api_load_lead($leadId);

        try {
            $deleted = lead_delete_permanently($leadId, $lead);
        } catch (Throwable $e) {
            codex_api_response([
                'ok' => false,
                'message' => 'Failed to delete lead.',
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ], 500);
        }

        codex_api_response([
            'ok' => true,
            'message' => 'Lead deleted permanently.',
            'deleted' => $deleted,
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

    function codex_api_sync_smile_design_cases_for_lead(int $leadId, array $lead): void
    {
        try {
            if (function_exists('smile_design_ensure_schema')) {
                smile_design_ensure_schema();
            }

            $fullName = trim((string)($lead['full_name'] ?? ''));
            if ($fullName === '') {
                return;
            }

            $nameParts = preg_split('/\s+/', $fullName) ?: [];
            $firstName = trim((string)($nameParts[0] ?? ''));
            $lastName = count($nameParts) > 1 ? trim(implode(' ', array_slice($nameParts, 1))) : '';

            db_execute(
                "UPDATE smile_cases
                 SET first_name = :first_name,
                     last_name = :last_name,
                     patient_name = :patient_name,
                     email = :email,
                     phone = :phone,
                     procedure_interest = :procedure_interest
                 WHERE lead_id = :lead_id",
                [
                    'lead_id' => $leadId,
                    'first_name' => $firstName !== '' ? $firstName : null,
                    'last_name' => $lastName !== '' ? $lastName : null,
                    'patient_name' => $fullName,
                    'email' => trim((string)($lead['email'] ?? '')) !== '' ? strtolower(trim((string)$lead['email'])) : null,
                    'phone' => trim((string)($lead['phone'] ?? '')) !== '' ? trim((string)$lead['phone']) : null,
                    'procedure_interest' => trim((string)($lead['procedure_interest'] ?? '')) !== '' ? trim((string)$lead['procedure_interest']) : null,
                ]
            );
        } catch (Throwable $syncException) {
            if (function_exists('esm_log')) {
                esm_log('smile_design', 'Could not sync Codex API lead update to Smile Design cases.', [
                    'lead_id' => $leadId,
                    'message' => $syncException->getMessage(),
                ]);
            }
        }
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
            'follow_up_status', 'sms_opt_status',
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
        if (isset($update['sms_opt_status']) && !in_array((string)$update['sms_opt_status'], ['unknown', 'subscribed', 'opted_in', 'opted_out', 'dnd'], true)) {
            codex_api_response(['ok' => false, 'message' => 'SMS opt status is not allowed.'], 422);
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
        $updatedLead = codex_api_load_lead($leadId);
        codex_api_sync_smile_design_cases_for_lead($leadId, $updatedLead);
        lead_comm_insert_activity($leadId, 'lead_updated', 'Lead details updated through Codex API.', [
            'fields' => array_keys($update),
            'source' => 'codex_api',
        ], 'Codex');

        codex_api_response([
            'ok' => true,
            'message' => 'Lead updated.',
            'lead_id' => $leadId,
            'lead' => $updatedLead,
        ]);
    }
}

if (!function_exists('codex_api_mobile_notifications')) {
    function codex_api_select_notification_window(array $notifications, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $unread = array_values(array_filter($notifications, static fn (array $row): bool => !empty($row['is_new'])));
        if (count($unread) > $limit) {
            return $unread;
        }

        return array_slice($notifications, 0, $limit);
    }

    function codex_api_mobile_notifications(): void
    {
        lead_comm_ensure_schema();
        $limit = max(1, min(20, (int) codex_api_value('limit', 5)));
        $notifications = function_exists('elite_ai_notification_rows')
            ? elite_ai_notification_rows($limit)
            : [];

        $unreadCount = count(array_filter($notifications, static fn (array $row): bool => !empty($row['is_new'])));

        codex_api_response([
            'ok' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'adapter' => 'elite_ai_notification_rows',
            'draft_before_send_rule' => true,
        ]);
    }
}

if (!function_exists('codex_api_mark_notification_reviewed')) {
    function codex_api_mark_notification_reviewed(): void
    {
        $leadId = (int) codex_api_value('lead_id', 0);
        $notificationId = trim((string) codex_api_value('notification_id', ''));
        if ($notificationId !== '' && str_starts_with($notificationId, 'test-')) {
            elite_ai_test_notification_mark_reviewed($notificationId, $leadId);
            codex_api_response([
                'ok' => true,
                'message' => 'Test notification reviewed.',
                'lead_id' => $leadId,
                'thread' => $leadId > 0 ? codex_api_timeline($leadId) : [],
            ]);
        }

        if ($leadId <= 0) {
            codex_api_response(['ok' => false, 'message' => 'lead_id is required.'], 422);
        }

        codex_api_load_lead($leadId);
        lead_comm_mark_read($leadId);
        lead_comm_insert_activity($leadId, 'operator_notification_reviewed', 'Notification reviewed and cleared through Codex API.', [
            'notification_id' => $notificationId,
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
        if ($token === '') {
            codex_api_response([
                'ok' => false,
                'message' => mobile_ai_has_key()
                    ? 'Could not generate a mobile setup token right now.'
                    : 'APP_KEY is required before mobile setup tokens can be issued.',
            ], 503);
        }

        codex_api_response([
            'ok' => true,
            'user_id' => $userId,
            'setup_url' => mobile_ai_qr_setup_url($token),
            'qr_url' => mobile_ai_qr_image_url($token),
            'expires_in_seconds' => MOBILE_AI_SETUP_TTL_SECONDS,
        ]);
    }
}

if (!function_exists('codex_api_mobile_push_status')) {
    function codex_api_mobile_push_status(): void
    {
        mobile_ai_ensure_schema();
        $counts = db_one(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN enabled = 1 AND push_enabled = 1 AND revoked_at IS NULL THEN 1 ELSE 0 END) AS enabled,
                MAX(last_seen_at) AS last_seen_at
             FROM user_push_subscriptions"
        ) ?: [];

        codex_api_response([
            'ok' => true,
            'configured' => mobile_ai_web_push_ready(),
            'php_version' => PHP_VERSION,
            'vendor_autoload' => is_file(ROOT_PATH . '/vendor/autoload.php'),
            'public_key_configured' => trim((string) ELITE_WEB_PUSH_PUBLIC_KEY) !== '',
            'subscriptions_total' => (int) ($counts['total'] ?? 0),
            'subscriptions_enabled' => (int) ($counts['enabled'] ?? 0),
            'last_subscription_seen_at' => (string) ($counts['last_seen_at'] ?? ''),
        ]);
    }
}

if (!function_exists('codex_api_mobile_push_test')) {
    function codex_api_mobile_push_test(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            codex_api_response(['ok' => false, 'message' => 'Use POST for a test push.'], 405);
        }

        $leadId = (int) codex_api_value('lead_id', 0);
        $notificationType = trim((string) codex_api_value('notification_type', codex_api_value('type', '')));
        $createdNotification = null;
        $pushPayload = [
            'title' => 'Elite AI',
            'push_body' => 'Rod, Elite AI notifications are connected. Open the assistant and tell me what you want to test.',
            'tag' => 'elite-ai-codex-test',
            'url' => '/crm/mobile-ai?tab=assistant',
            'data' => ['url' => '/crm/mobile-ai?tab=assistant'],
        ];

        if ($leadId > 0 && function_exists('elite_ai_test_notification_create')) {
            $created = elite_ai_test_notification_create($leadId, $notificationType !== '' ? $notificationType : 'reply', [
                'message' => (string) codex_api_value('message', ''),
                'title' => (string) codex_api_value('title', ''),
                'source_label' => (string) codex_api_value('source_label', ''),
                'created_by' => (string) codex_api_value('created_by', 'Codex API'),
            ]);
            if (empty($created['ok'])) {
                codex_api_response([
                    'ok' => false,
                    'message' => (string) ($created['message'] ?? 'Could not create the Elite AI test notification.'),
                ], 422);
            }

            $createdNotification = (array) ($created['notification'] ?? []);
            $notificationUrl = '/crm/mobile-ai?tab=assistant';
            if (trim((string) ($createdNotification['id'] ?? '')) !== '') {
                $notificationUrl .= '&notification_id=' . rawurlencode((string) $createdNotification['id']);
            }
            $notificationUrl .= '&lead_id=' . $leadId;

            $pushPayload = [
                'title' => 'Elite AI',
                'push_body' => trim((string) ($createdNotification['push_body'] ?? '')) ?: 'Rod, Elite AI test notification is ready.',
                'tag' => 'elite-ai-codex-test-' . $leadId . '-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($createdNotification['type'] ?? 'reply')),
                'url' => $notificationUrl,
                'lead_id' => $leadId,
                'notification_id' => trim((string) ($createdNotification['id'] ?? '')),
                'badge_count' => max(1, (int) ($createdNotification['badge_count'] ?? 1)),
                'data' => [
                    'url' => $notificationUrl,
                    'notification_id' => trim((string) ($createdNotification['id'] ?? '')),
                ],
            ];
        }

        $result = mobile_ai_send_push_payload($pushPayload);
        $ok = !empty($result['sent']) || $createdNotification !== null;
        $message = !empty($result['sent'])
            ? ($createdNotification !== null
                ? 'Elite AI end-to-end test notification created and push sent.'
                : 'Elite AI test push sent.')
            : ($createdNotification !== null
                ? 'Elite AI test notification created. No connected device accepted the push, but the assistant feed is ready.'
                : 'No connected Elite AI device accepted the test push.');

        codex_api_response([
            'ok' => $ok,
            'message' => $message,
            'push' => $result,
            'notification' => $createdNotification,
        ], $ok ? 200 : 409);
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
            'capabilities_url_hint' => '?action=capabilities',
            'stages' => lead_stage_labels(),
            'smtp_configured' => function_exists('elite_smtp_is_configured') ? elite_smtp_is_configured() : false,
            'twilio_configured' => defined('TWILIO_ACCOUNT_SID') && TWILIO_ACCOUNT_SID !== '' && defined('TWILIO_AUTH_TOKEN') && TWILIO_AUTH_TOKEN !== '',
        ]);
    }

    if ($action === 'capabilities') {
        codex_api_capabilities();
    }

    if ($action === 'stages') {
        codex_api_response(['ok' => true, 'stages' => lead_stage_labels()]);
    }

    if ($action === 'pipeline_snapshot') {
        codex_api_pipeline_snapshot();
    }

    if ($action === 'crm_operator_brief') {
        codex_api_crm_operator_brief();
    }

    if ($action === 'crm_operator_command_center') {
        codex_api_crm_operator_command_center();
    }

    if ($action === 'lead_queue_summary') {
        codex_api_lead_queue_summary();
    }

    if ($action === 'nurture_candidates') {
        codex_api_nurture_candidates();
    }

    if ($action === 'sms_delivery_issues') {
        codex_api_sms_delivery_issues();
    }

    if ($action === 'conversation_quality') {
        codex_api_conversation_quality();
    }

    if ($action === 'meta_lead_ad_correlation') {
        codex_api_meta_lead_ad_correlation();
    }

    if ($action === 'api_self_check') {
        codex_api_self_check();
    }

    if ($action === 'assistant_prompt') {
        codex_api_assistant_prompt();
    }

    if ($action === 'elite_ai_pending_drafts') {
        codex_api_elite_ai_pending_drafts();
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

    if ($action === 'list_leads' || $action === 'lead_queue' || $action === 'inbox') {
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

    if ($action === 'mobile_push_status') {
        codex_api_mobile_push_status();
    }

    if ($action === 'mobile_push_test') {
        codex_api_mobile_push_test();
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

    if ($action === 'elite_ai_cancel_draft') {
        codex_api_elite_ai_cancel_draft();
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

    if ($action === 'nurture_reactivation_send') {
        codex_api_nurture_reactivation_send();
    }

    if ($action === 'move_stage') {
        codex_api_move_stage((int) codex_api_value('lead_id', 0), trim((string) codex_api_value('status', '')));
    }

    if ($action === 'update_lead') {
        codex_api_update_lead((int) codex_api_value('lead_id', 0), (array) codex_api_value('fields', []));
    }

    if ($action === 'delete_lead') {
        codex_api_delete_lead((int) codex_api_value('lead_id', 0));
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
        if (!codex_api_has_explicit_send_approval(codex_api_body())) {
            codex_api_response([
                'ok' => false,
                'message' => 'Email send blocked until explicit send approval is provided.',
                'approval_required' => 'send_approved',
                'lead_id' => $leadId,
            ], 409);
        }
        if (!elite_smtp_is_configured()) {
            codex_api_response(['ok' => false, 'message' => 'SMTP is not configured.'], 503);
        }
        $result = lead_email_send($leadId, (string) codex_api_value('subject', ''), (string) codex_api_value('body', ''), (string) codex_api_value('created_by', 'Codex'));
        if (empty($result['ok'])) {
            codex_api_response(['ok' => false, 'message' => (string)($result['message'] ?? 'Email failed.'), 'lead_id' => $leadId], 502);
        }
        $createdBy = (string) codex_api_value('created_by', 'Codex');
        codex_api_record_outbound_note($leadId, 'email', (string) codex_api_value('subject', ''), (string) codex_api_value('body', ''), $createdBy, [
            'email_id' => (int)($result['email_id'] ?? 0),
        ]);
        if (filter_var(codex_api_value('mark_inbound_reviewed', false), FILTER_VALIDATE_BOOLEAN)) {
            lead_comm_mark_read($leadId);
            lead_comm_insert_activity($leadId, 'operator_notification_reviewed', 'Inbound notification cleared after an explicitly reviewed Codex API email response.', [
                'email_id' => (int)($result['email_id'] ?? 0),
                'source' => 'codex_api',
            ], $createdBy);
        }
        lead_comm_update_rollup($leadId);
        codex_api_response(['ok' => true, 'message' => 'Email sent and logged.', 'lead_id' => $leadId, 'email_id' => (int)($result['email_id'] ?? 0), 'thread' => codex_api_timeline($leadId)]);
    }

    if ($action === 'send_internal_sms') {
        $recipientKey = trim((string) codex_api_value('recipient_key', ''));
        $message = trim((string) codex_api_value('message', codex_api_value('body', '')));

        if ($recipientKey === '') {
            codex_api_response(['ok' => false, 'message' => 'recipient_key is required.'], 422);
        }
        if ($message === '') {
            codex_api_response(['ok' => false, 'message' => 'Message cannot be empty.'], 422);
        }
        if (!codex_api_has_explicit_send_approval(codex_api_body())) {
            codex_api_response([
                'ok' => false,
                'message' => 'Internal SMS send blocked until explicit send approval is provided.',
                'approval_required' => 'send_approved',
            ], 409);
        }

        $recipient = internal_sms_find_recipient($recipientKey);
        if (!$recipient) {
            codex_api_response(['ok' => false, 'message' => 'Internal SMS recipient was not found.'], 404);
        }
        if (empty($recipient['enabled'])) {
            codex_api_response(['ok' => false, 'message' => 'Internal SMS recipient is disabled.'], 409);
        }

        $result = internal_sms_send($recipient, $message, 0);
        if (empty($result['ok'])) {
            codex_api_response([
                'ok' => false,
                'message' => (string)($result['message'] ?? 'Internal SMS failed.'),
                'recipient_key' => $recipientKey,
                'status_code' => $result['status_code'] ?? null,
            ], 502);
        }

        codex_api_response([
            'ok' => true,
            'message' => 'Internal SMS sent.',
            'recipient_key' => $recipientKey,
            'recipient_name' => (string)($recipient['name'] ?? ''),
            'to' => (string)($result['to'] ?? ''),
            'twilio_sid' => (string)($result['twilio_sid'] ?? ''),
            'twilio_status' => (string)($result['twilio_status'] ?? ''),
        ]);
    }

    if ($action === 'send_sms') {
        $leadId = (int) codex_api_value('lead_id', 0);
        $lead = codex_api_load_lead($leadId);
        $message = trim((string) codex_api_value('message', ''));
        if ($message === '') {
            codex_api_response(['ok' => false, 'message' => 'Message cannot be empty.'], 422);
        }
        if (!codex_api_has_explicit_send_approval(codex_api_body())) {
            codex_api_response([
                'ok' => false,
                'message' => 'SMS send blocked until explicit send approval is provided.',
                'approval_required' => 'send_approved',
                'lead_id' => $leadId,
            ], 409);
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
        codex_api_record_outbound_note($leadId, 'sms', '', $sentBody, (string)codex_api_value('created_by', 'Codex'), [
            'message_id' => $messageRecordId,
            'twilio_sid' => $sendResult['twilio_sid'] ?? '',
        ]);
        if (filter_var(codex_api_value('mark_inbound_reviewed', false), FILTER_VALIDATE_BOOLEAN)) {
            lead_comm_mark_read($leadId);
            lead_comm_insert_activity($leadId, 'operator_notification_reviewed', 'Inbound notification cleared after an explicitly reviewed Codex API SMS response.', [
                'message_id' => $messageRecordId,
                'source' => 'codex_api',
            ], 'Codex');
        }
        lead_comm_update_rollup($leadId);
        if (function_exists('lead_comm_clear_follow_up_attention')) {
            lead_comm_clear_follow_up_attention($leadId);
        }
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
