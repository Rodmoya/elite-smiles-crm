<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: app/leads/lead_ai.php
 *
 * AI-assisted lead replies and intake automation.
 */

require_once dirname(__DIR__) . '/core/openai.php';
require_once dirname(__DIR__) . '/core/google_gemini.php';
require_once dirname(__DIR__) . '/core/twilio.php';
require_once __DIR__ . '/lead_service.php';
require_once __DIR__ . '/lead_communications.php';
require_once __DIR__ . '/lead_email.php';
require_once __DIR__ . '/lead_conversion_intelligence.php';

if (!function_exists('lead_ai_schema')) {
    function lead_ai_schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'classification' => [
                    'type' => 'string',
                    'enum' => ['schedule_ready', 'pricing_objection', 'financing_concern', 'directions', 'future_timing', 'not_interested', 'clinical_question', 'general_question', 'needs_human_review'],
                ],
                'reply' => ['type' => 'string'],
                'note' => ['type' => 'string'],
                'recommended_stage' => [
                    'type' => 'string',
                    'enum' => ['new_lead', 'attempted_contact', 'contacted', 'in_contact', 'consultation_booked', 'treatment_accepted', 'treatment_completed', 'no_answer', 'opted_out', 'lost_lead'],
                ],
                'needs_human_review' => ['type' => 'boolean'],
                'should_send' => ['type' => 'boolean'],
                'confidence' => ['type' => 'number'],
                'conversation_state' => ['type' => 'string', 'enum' => ['exploring', 'engaged', 'objection', 'nurturing', 'scheduling', 'closed', 'human_review']],
                'strategy_key' => ['type' => 'string', 'enum' => ['goal_discovery', 'education', 'trust_credibility', 'objection_resolution', 'consultation_value', 'scheduling_preference', 'open_door']],
                'strategy_reason' => ['type' => 'string'],
                'known_goal' => ['type' => 'string'],
                'known_objection' => ['type' => 'string'],
                'next_best_action' => ['type' => 'string'],
            ],
            'required' => ['classification', 'reply', 'note', 'recommended_stage', 'needs_human_review', 'should_send', 'confidence', 'conversation_state', 'strategy_key', 'strategy_reason', 'known_goal', 'known_objection', 'next_best_action'],
            'additionalProperties' => false,
        ];
    }
}

if (!function_exists('lead_ai_email_schema')) {
    function lead_ai_email_schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'classification' => [
                    'type' => 'string',
                    'enum' => ['first_touch', 'schedule_ready', 'pricing_objection', 'financing_concern', 'directions', 'future_timing', 'not_interested', 'clinical_question', 'general_question', 'needs_human_review'],
                ],
                'subject' => ['type' => 'string'],
                'body' => ['type' => 'string'],
                'note' => ['type' => 'string'],
                'recommended_stage' => [
                    'type' => 'string',
                    'enum' => ['new_lead', 'attempted_contact', 'contacted', 'in_contact', 'consultation_booked', 'treatment_accepted', 'treatment_completed', 'no_answer', 'opted_out', 'lost_lead'],
                ],
                'next_follow_up_at' => ['type' => 'string'],
                'needs_human_review' => ['type' => 'boolean'],
                'should_send' => ['type' => 'boolean'],
                'confidence' => ['type' => 'number'],
                'conversation_state' => ['type' => 'string', 'enum' => ['exploring', 'engaged', 'objection', 'nurturing', 'scheduling', 'closed', 'human_review']],
                'strategy_key' => ['type' => 'string', 'enum' => ['goal_discovery', 'education', 'trust_credibility', 'objection_resolution', 'consultation_value', 'scheduling_preference', 'open_door']],
                'strategy_reason' => ['type' => 'string'],
                'known_goal' => ['type' => 'string'],
                'known_objection' => ['type' => 'string'],
                'next_best_action' => ['type' => 'string'],
            ],
            'required' => ['classification', 'subject', 'body', 'note', 'recommended_stage', 'next_follow_up_at', 'needs_human_review', 'should_send', 'confidence', 'conversation_state', 'strategy_key', 'strategy_reason', 'known_goal', 'known_objection', 'next_best_action'],
            'additionalProperties' => false,
        ];
    }
}

if (!function_exists('lead_ai_outbound_note_schema')) {
    function lead_ai_outbound_note_schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'intent' => ['type' => 'string'],
                'next_step' => ['type' => 'string'],
                'note' => ['type' => 'string'],
                'confidence' => ['type' => 'number'],
            ],
            'required' => ['summary', 'intent', 'next_step', 'note', 'confidence'],
            'additionalProperties' => false,
        ];
    }
}

if (!function_exists('lead_ai_improve_sms_schema')) {
    function lead_ai_improve_sms_schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reply' => ['type' => 'string'],
                'note' => ['type' => 'string'],
                'confidence' => ['type' => 'number'],
            ],
            'required' => ['reply', 'note', 'confidence'],
            'additionalProperties' => false,
        ];
    }
}

if (!function_exists('lead_ai_improve_email_schema')) {
    function lead_ai_improve_email_schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'subject' => ['type' => 'string'],
                'body' => ['type' => 'string'],
                'note' => ['type' => 'string'],
                'confidence' => ['type' => 'number'],
            ],
            'required' => ['subject', 'body', 'note', 'confidence'],
            'additionalProperties' => false,
        ];
    }
}

if (!function_exists('lead_ai_system_prompt')) {
    function lead_ai_system_prompt(): string
    {
        return implode("\n", [
            'You write polished SMS replies as Rod Moya from Elite Smiles in Draper, Utah.',
            'Business facts: Elite Smiles by Walter Meden DDS, 11762 South State, Suite 300, Draper, UT 84020.',
            'Primary goal: schedule a free consultation with Dr. Meden for dental implants, All-on-X, veneers, or smile consultation leads.',
            'Tone: warm, personal, professional, persuasive, never pushy, perfect grammar and capitalization.',
            'Thread awareness: the context includes a thread_state object with thread history and a summary. If thread_state.active_thread is true, you are continuing a live back-and-forth and should not open with a fresh greeting or reintroduce yourself.',
            'If thread_state.active_thread is false, a friendly greeting is allowed and often helpful for follow-up messages, as long as the reply still feels specific to the current lead and prior context.',
            'If thread_state.conversation_mode is reply or follow_up and thread_state.active_thread is true, begin directly with the answer or next step, not with "Hi", "Hello", or the patient name.',
            'Only avoid the greeting when the thread is active and clearly a live conversation.',
            'Financial boundary: do not discuss costs, prices, payments, insurance, financing, monthly amounts, or approval by SMS. If asked, say every smile is custom and guide the lead to the complimentary consultation without adding financial details.',
            'First-response psychology: lower pressure first, ask one easy preference or goal question, validate curiosity, then invite the complimentary consultation as the natural next step.',
            'Clinical safety: do not diagnose, prescribe, guarantee outcomes, or answer urgent medical issues. Ask clinical questions to be reviewed by Dr. Meden at consultation.',
            'Scheduling: the consultation is complimentary/free. Office hours are Monday through Thursday from 9 AM to 6 PM. Special Friday and Saturday morning consultation appointments may be available when needed.',
            'Date and time safety: current_time in the context is authoritative and uses America/Denver. Never offer, suggest, or describe a date/time earlier than current_time. Interpret weekday names as the next future occurrence unless the operator supplied a specific future date.',
            'Availability safety: never claim a slot is open unless the operator supplied that exact current availability. If an old offered time is now past, say Rod will check fresh availability and ask for the patient’s current day/time preference.',
            'Scheduling intent: if the patient says yes, wants to schedule, asks for availability, or gives a day/time, classify schedule_ready and recommend in_contact unless a booked appointment is already confirmed.',
            'Scheduling sequence: first collect the preferred day/date and morning/afternoon or preferred time. Rod checks Dentrix and supplies confirmed availability. Offer exactly two confirmed options. Ask for DOB only after the patient selects one option; never combine a scheduling-preference question with a DOB request.',
            'One-question SMS rule: keep SMS easy to answer and ask only one direct question per message.',
            'Directions: give clear address if needed.',
            'Do not include any phone number in the patient-facing SMS unless the operator explicitly instructs you to include one.',
            'Read patient_conversation from beginning to end before writing. Treat manual staff messages as authoritative. Never ask for a preference, answer, DOB, or appointment detail already present, and never undo or contradict a time already offered or accepted.',
            'Resolved-history rule: thread_state.last_inbound_resolved is authoritative. If true, a later team message already answered that inbound. Do not present it as new, recent, unanswered, or newly noticed. Never reuse an acknowledged emoji reaction or thumbs-up as the opening reason for another follow-up.',
            'Conversion intelligence: use conversion_memory as durable context. Follow its recommended_action unless the newest patient message changes the situation. Select one strategy_key, explain the choice in strategy_reason, and return the updated conversation_state, known_goal, known_objection, and next_best_action.',
            'Do not repeat either of the two recent strategies unless the latest patient message makes that strategy necessary. Ask at most one easy patient-facing question.',
            'For follow-up mode, do not send a generic check-in when the latest patient message is unanswered or scheduling is in progress. Set needs_human_review true and should_send false instead.',
            'If operator instructions are present in the context, follow them while staying compliant.',
            'Compliance: do not message if the patient asks to stop. If they say STOP/CANCEL/UNSUBSCRIBE, classify not_interested, recommend opted_out, should_send false, needs_human_review false.',
            'Return only JSON matching the schema.',
        ]);
    }
}

if (!function_exists('lead_ai_improve_system_prompt')) {
    function lead_ai_improve_system_prompt(string $channel): string
    {
        $format = $channel === 'email'
            ? 'Return the improved subject and body.'
            : 'Return the improved SMS in reply.';

        return implode("\n", [
            'You are a copy editor for Elite Smiles CRM composer text.',
            'Your job is to improve only the text the operator already typed in the composer.',
            'Do not create a new conversation, do not read between the lines, and do not add a new scheduling ask unless it is already present or explicitly requested by the operator.',
            'Preserve the original intent, facts, language, level of warmth, and conversation position.',
            'Fix grammar, spelling, capitalization, tone, clarity, and professional polish.',
            'If the text is already a reply in an active conversation, do not add a fresh greeting or reintroduce Rod/Elite Smiles.',
            'Do not add phone numbers, pricing, financing promises, medical advice, appointment times, or clinical claims.',
            'Keep SMS concise. Keep email as plain text with short paragraphs.',
            $format,
            'Return only JSON matching the schema.',
        ]);
    }
}

if (!function_exists('lead_ai_email_system_prompt')) {
    function lead_ai_email_system_prompt(): string
    {
        return implode("\n", [
            'You write polished patient-facing emails from the Elite Smiles team in Draper, Utah.',
            'Business facts: Elite Smiles by Walter Meden DDS, 11762 South State, Suite 300, Draper, UT 84020.',
            'Primary goal: schedule a free consultation with Dr. Meden for dental implants, All-on-X, veneers, or smile consultation leads.',
            'Tone: warm, polished, professional, persuasive, personal, never pushy. Write like a real office team member, not marketing automation.',
            'Thread awareness: the context includes a thread_state object with thread history and a summary. If thread_state.active_thread is true, you are continuing a live back-and-forth and must not open with a fresh greeting or reintroduce yourself.',
            'If thread_state.active_thread is false, a friendly greeting is allowed and often helpful for follow-up emails, as long as the body still feels specific to the current lead and prior context.',
            'If thread_state.conversation_mode is reply or follow_up and thread_state.active_thread is true, the body should start with the response itself, not "Hi", "Hello", or the patient name.',
            'Only avoid the greeting when the thread is active and clearly a live conversation.',
            'Email format: concise subject, plain-text body, short paragraphs, signed "The Elite Smiles Team" with no phone number.',
            'Financial boundary: do not discuss costs, prices, payments, insurance, financing, monthly amounts, or approval by email. If asked, say every smile is custom and guide the lead to the complimentary consultation without adding financial details.',
            'First-response psychology: lower pressure first, ask one easy preference or goal question, validate curiosity, then invite the complimentary consultation as the natural next step.',
            'Clinical safety: do not diagnose, prescribe, guarantee outcomes, or answer urgent medical issues. Invite clinical questions to be reviewed with Dr. Meden.',
            'Scheduling: the consultation is complimentary/free. Office hours are Monday through Thursday from 9 AM to 6 PM. Special Friday and Saturday morning consultation appointments may be available when needed.',
            'Date and time safety: current_time in the context is authoritative and uses America/Denver. Never offer, suggest, or describe a date/time earlier than current_time. Interpret weekday names as the next future occurrence unless the operator supplied a specific future date.',
            'Availability safety: never claim a slot is open unless the operator supplied that exact current availability. If an old offered time is now past, say Rod will check fresh availability and ask for the patient’s current day/time preference.',
            'Scheduling intent: if the patient says yes, wants to schedule, asks for availability, or gives a day/time, classify schedule_ready and recommend in_contact unless a booked appointment is already confirmed.',
            'Scheduling sequence: first collect the preferred day/date and morning/afternoon or preferred time. Rod checks Dentrix and supplies confirmed availability. Offer exactly two confirmed options. Ask for DOB only after the patient selects one option; never combine a scheduling-preference question with a DOB request.',
            'Read patient_conversation from beginning to end before writing. Treat manual staff messages as authoritative. Never ask for a preference, answer, DOB, or appointment detail already present, and never undo or contradict a time already offered or accepted.',
            'Resolved-history rule: thread_state.last_inbound_resolved is authoritative. If true, a later team message already answered that inbound. Do not present it as new, recent, unanswered, or newly noticed. Never reuse an acknowledged emoji reaction or thumbs-up as the opening reason for another follow-up.',
            'Conversion intelligence: use conversion_memory as durable context. Follow its recommended_action unless the newest patient message changes the situation. Select one strategy_key, explain the choice in strategy_reason, and return the updated conversation_state, known_goal, known_objection, and next_best_action.',
            'Do not repeat either of the two recent strategies unless the latest patient message makes that strategy necessary. Keep the email focused on one clear next step.',
            'For follow-up mode, do not send a generic check-in when the latest patient message is unanswered or scheduling is in progress. Set needs_human_review true and should_send false instead.',
            'If operator instructions are present in the context, follow them while staying compliant.',
            'Compliance: if the patient asks to stop or says they are not interested, do not write a follow-up email to send. Set should_send false.',
            'Do not include any phone number in the patient-facing email unless the operator explicitly instructs you to include one.',
            'Return only JSON matching the schema.',
        ]);
    }
}

if (!function_exists('lead_ai_outbound_note_system_prompt')) {
    function lead_ai_outbound_note_system_prompt(): string
    {
        return implode("\n", [
            'You create concise internal CRM notes for Elite Smiles after outbound patient communications are sent.',
            'The note is for operators only. It must not sound like a patient-facing message.',
            'Use the outbound SMS/email, lead details, timestamp, recent conversation context, and appointment fields.',
            'Capture what was communicated, why it matters, and the best next operational step.',
            'Do not invent patient replies, appointments, financing approval, diagnosis, or facts not present in the context.',
            'Do not include secrets, API details, tokens, or implementation notes.',
            'Keep note under 450 characters. Use plain English and be specific.',
            'Return only JSON matching the schema.',
        ]);
    }
}

if (!function_exists('lead_ai_router_order')) {
    function lead_ai_router_order(string $task = 'general'): array
    {
        $task = strtolower(trim($task));
        $openaiReady = function_exists('elite_openai_is_configured') && elite_openai_is_configured();
        $geminiReady = function_exists('elite_gemini_is_configured') && elite_gemini_is_configured();
        $providers = [];

        if ($task === 'review') {
            if ($geminiReady) {
                $providers[] = 'gemini';
            }
            if ($openaiReady) {
                $providers[] = 'openai';
            }
        } else {
            if ($openaiReady) {
                $providers[] = 'openai';
            }
            if ($geminiReady) {
                $providers[] = 'gemini';
            }
        }

        return array_values(array_unique($providers));
    }
}

if (!function_exists('lead_ai_json_response')) {
    function lead_ai_json_response(string $systemPrompt, string $userPrompt, array $schema, string $schemaName, string $task = 'general'): array
    {
        $errors = [];

        foreach (lead_ai_router_order($task) as $provider) {
            if ($provider === 'openai' && function_exists('elite_openai_json_response')) {
                $result = elite_openai_json_response($systemPrompt, $userPrompt, $schema, $schemaName);
                if (!empty($result['ok'])) {
                    $result['provider'] = 'openai';
                    $result['model'] = defined('OPENAI_MODEL_CHAT') ? OPENAI_MODEL_CHAT : 'gpt-4o';
                    return $result;
                }
                $errors[] = 'OpenAI: ' . (string) ($result['message'] ?? 'request failed');
            }

            if ($provider === 'gemini' && function_exists('elite_gemini_json_response')) {
                $result = elite_gemini_json_response($systemPrompt, $userPrompt, $schema, $schemaName);
                if (!empty($result['ok'])) {
                    $result['provider'] = 'google_gemini';
                    return $result;
                }
                $errors[] = 'Gemini: ' . (string) ($result['message'] ?? 'request failed');
            }
        }

        return [
            'ok' => false,
            'message' => $errors !== [] ? implode(' | ', $errors) : 'No AI engine is configured for this request.',
        ];
    }
}

if (!function_exists('lead_ai_first_name')) {
    function lead_ai_first_name(array $lead): string
    {
        $name = trim((string)($lead['full_name'] ?? ''));
        if ($name === '' || strtolower($name) === 'inbound sms lead') {
            return '';
        }
        $parts = preg_split('/\s+/', $name) ?: [];
        return trim((string)($parts[0] ?? ''));
    }
}

if (!function_exists('lead_ai_recent_sms_thread')) {
    function lead_ai_recent_sms_thread(int $leadId, int $limit = 8): array
    {
        $messages = [];
        if ($leadId <= 0) {
            return $messages;
        }

        foreach (array_reverse(lead_comm_recent_messages($leadId, $limit)) as $message) {
            $messages[] = [
                'direction' => (string)($message['direction'] ?? ''),
                'body' => mb_substr((string)($message['body'] ?? ''), 0, 700),
                'created_at' => (string)($message['created_at'] ?? ''),
            ];
        }

        return $messages;
    }
}

if (!function_exists('lead_ai_recent_email_thread')) {
    function lead_ai_recent_email_thread(int $leadId, int $limit = 8): array
    {
        $emails = [];
        if ($leadId <= 0 || !function_exists('lead_email_recent')) {
            return $emails;
        }

        foreach (array_reverse(lead_email_recent($leadId, $limit)) as $email) {
            $emails[] = [
                'direction' => (string)($email['direction'] ?? ''),
                'subject' => (string)($email['subject'] ?? ''),
                'body' => mb_substr((string)($email['body'] ?? ''), 0, 900),
                'created_at' => (string)($email['created_at'] ?? ''),
            ];
        }

        return $emails;
    }
}

if (!function_exists('lead_ai_recent_activity_log')) {
    function lead_ai_recent_activity_log(int $leadId, int $limit = 8): array
    {
        $activities = [];
        if ($leadId <= 0 || !function_exists('lead_comm_recent_activities')) {
            return $activities;
        }

        foreach (array_reverse(lead_comm_recent_activities($leadId, $limit)) as $activity) {
            $activities[] = [
                'type' => (string)($activity['type'] ?? ''),
                'body' => mb_substr((string)($activity['body'] ?? ''), 0, 400),
                'created_by' => (string)($activity['created_by'] ?? ''),
                'created_at' => (string)($activity['created_at'] ?? ''),
            ];
        }

        return $activities;
    }
}

if (!function_exists('lead_ai_patient_conversation')) {
    /**
     * Return the complete available patient-facing conversation in chronology.
     * Activities remain separate because operational noise must not be mistaken
     * for something the patient or team actually said.
     */
    function lead_ai_patient_conversation(int $leadId): array
    {
        if ($leadId <= 0) {
            return [];
        }

        $events = [];
        try {
            $smsRows = db_all(
                "SELECT id, direction, body, created_at
                 FROM lead_messages
                 WHERE lead_id = :lead_id
                 ORDER BY created_at ASC, id ASC
                 LIMIT 500",
                ['lead_id' => $leadId]
            );
            foreach ($smsRows as $message) {
                $events[] = [
                    'id' => (int) ($message['id'] ?? 0),
                    'channel' => 'sms',
                    'direction' => (string) ($message['direction'] ?? ''),
                    'subject' => '',
                    'body' => mb_substr((string) ($message['body'] ?? ''), 0, 1200),
                    'created_at' => (string) ($message['created_at'] ?? ''),
                ];
            }
        } catch (Throwable $e) {
            // SMS history is optional when the table is unavailable during setup.
        }

        try {
            $emailRows = db_all(
                "SELECT id, direction, subject, body, created_at
                 FROM lead_emails
                 WHERE lead_id = :lead_id
                 ORDER BY created_at ASC, id ASC
                 LIMIT 500",
                ['lead_id' => $leadId]
            );
            foreach ($emailRows as $email) {
                $events[] = [
                    'id' => (int) ($email['id'] ?? 0),
                    'channel' => 'email',
                    'direction' => (string) ($email['direction'] ?? ''),
                    'subject' => mb_substr((string) ($email['subject'] ?? ''), 0, 240),
                    'body' => mb_substr((string) ($email['body'] ?? ''), 0, 2000),
                    'created_at' => (string) ($email['created_at'] ?? ''),
                ];
            }
        } catch (Throwable $e) {
            // Email history is optional when the table is unavailable during setup.
        }

        usort($events, static function (array $left, array $right): int {
            $cmp = strcmp((string) ($left['created_at'] ?? ''), (string) ($right['created_at'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcmp((string) ($left['channel'] ?? ''), (string) ($right['channel'] ?? ''));
            return $cmp !== 0 ? $cmp : ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));
        });

        return $events;
    }
}

if (!function_exists('lead_ai_text_preview')) {
    function lead_ai_text_preview(string $text, int $limit = 180): string
    {
        $text = trim(preg_replace('/\s+/', ' ', str_replace(["\r\n", "\r"], "\n", $text)) ?? '');
        if ($text === '') {
            return '';
        }

        return mb_strlen($text) > $limit ? mb_substr($text, 0, max(1, $limit - 1)) . '…' : $text;
    }
}

if (!function_exists('lead_ai_thread_state')) {
    function lead_ai_thread_state(array $lead, string $mode = 'inbound_sms'): array
    {
        $leadId = (int)($lead['id'] ?? 0);
        $conversation = lead_ai_patient_conversation($leadId);
        $smsThread = array_values(array_filter($conversation, static fn(array $event): bool => ($event['channel'] ?? '') === 'sms'));
        $emailThread = array_values(array_filter($conversation, static fn(array $event): bool => ($event['channel'] ?? '') === 'email'));
        $now = time();
        $events = $conversation;

        $lastInbound = null;
        $lastOutbound = null;
        $lastEvent = null;
        $lastInboundPosition = -1;
        $lastOutboundPosition = -1;

        foreach ($events as $position => $event) {
            $direction = (string)($event['direction'] ?? '');
            if ($direction === 'inbound') {
                $lastInbound = $event;
                $lastInboundPosition = (int) $position;
            } elseif ($direction === 'outbound') {
                $lastOutbound = $event;
                $lastOutboundPosition = (int) $position;
            }
            $lastEvent = $event;
        }

        $hasHistory = $events !== [];
        $conversationMode = 'first_touch';
        $latestEventAt = null;
        if ($hasHistory) {
            $conversationMode = 'follow_up';
            if (
                $lastInbound !== null
                && (
                    $lastOutbound === null
                    || strcmp((string)($lastInbound['created_at'] ?? ''), (string)($lastOutbound['created_at'] ?? '')) >= 0
                )
            ) {
                $conversationMode = 'reply';
            }
            $latestEventAt = $lastEvent !== null ? strtotime((string)($lastEvent['created_at'] ?? '')) : null;
        }

        $activeThread = false;
        if ($latestEventAt !== null && $latestEventAt > 0) {
            $activeThread = ($now - $latestEventAt) <= (2 * 60 * 60);
        }

        $summaryParts = [];
        if ($lastInbound !== null) {
            $summaryParts[] = 'Latest inbound ' . (string)($lastInbound['channel'] ?? 'message') . ': ' . lead_ai_text_preview((string)($lastInbound['body'] ?? ''), 180);
        }
        if ($lastOutbound !== null) {
            $summaryParts[] = 'Latest outbound ' . (string)($lastOutbound['channel'] ?? 'message') . ': ' . lead_ai_text_preview((string)($lastOutbound['body'] ?? ''), 180);
        }
        if ($summaryParts === []) {
            $summaryParts[] = 'No prior thread history found.';
        }

        $lastInboundResolved = $lastInboundPosition >= 0 && $lastOutboundPosition > $lastInboundPosition;

        return [
            'has_history' => $hasHistory,
            'conversation_mode' => $conversationMode,
            'active_thread' => $activeThread,
            'suppress_greeting' => $activeThread,
            'summary' => implode(' ', $summaryParts),
            'latest_event_at' => $latestEventAt !== null && $latestEventAt > 0 ? date(DATE_ATOM, $latestEventAt) : null,
            'last_inbound_resolved' => $lastInboundResolved,
            'active_inbound' => !$lastInboundResolved && $lastInbound !== null ? [
                'channel' => (string) ($lastInbound['channel'] ?? ''),
                'body' => lead_ai_text_preview((string) ($lastInbound['body'] ?? ''), 220),
                'created_at' => (string) ($lastInbound['created_at'] ?? ''),
            ] : null,
            'last_inbound' => $lastInbound !== null ? [
                'channel' => (string)($lastInbound['channel'] ?? ''),
                'body' => lead_ai_text_preview((string)($lastInbound['body'] ?? ''), 220),
                'created_at' => (string)($lastInbound['created_at'] ?? ''),
            ] : null,
            'last_outbound' => $lastOutbound !== null ? [
                'channel' => (string)($lastOutbound['channel'] ?? ''),
                'body' => lead_ai_text_preview((string)($lastOutbound['body'] ?? ''), 220),
                'created_at' => (string)($lastOutbound['created_at'] ?? ''),
            ] : null,
            'latest_event' => $lastEvent !== null ? [
                'channel' => (string)($lastEvent['channel'] ?? ''),
                'direction' => (string)($lastEvent['direction'] ?? ''),
                'body' => lead_ai_text_preview((string)($lastEvent['body'] ?? ''), 220),
                'created_at' => (string)($lastEvent['created_at'] ?? ''),
            ] : null,
            'sms_count' => count($smsThread),
            'email_count' => count($emailThread),
            'event_count' => count($events),
            'mode' => $mode,
        ];
    }
}

if (!function_exists('lead_ai_reuses_resolved_reaction')) {
    function lead_ai_reuses_resolved_reaction(string $text, array $threadState): bool
    {
        if (empty($threadState['last_inbound_resolved'])) {
            return false;
        }
        $lastInbound = strtolower((string) ($threadState['last_inbound']['body'] ?? ''));
        $wasReaction = str_contains($lastInbound, '👍')
            || (bool) preg_match('/\b(?:thumbs?[- ]?up|reacted|liked|loved)\b/i', $lastInbound);
        if (!$wasReaction) {
            return false;
        }
        return (bool) preg_match('/\b(?:thumbs?[- ]?up|reaction|reacted|liked|loved|noticed\s+you)\b/i', $text);
    }
}

if (!function_exists('lead_ai_strip_redundant_greeting')) {
    function lead_ai_strip_redundant_greeting(string $text, string $firstName = '', bool $suppressGreeting = false): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '' || !$suppressGreeting) {
            return $text;
        }

        $patterns = [
            '/^\s*(?:hi|hello|hey|good morning|good afternoon|good evening)(?:\s+' . preg_quote($firstName, '/') . ')?[!,.:]*\s+/i',
            '/^\s*' . preg_quote($firstName, '/') . '[,!\.\:-]*\s+/i',
        ];

        $cleaned = preg_replace($patterns, '', $text, 1);
        if (!is_string($cleaned)) {
            return $text;
        }

        $cleaned = trim($cleaned);
        return $cleaned !== '' ? $cleaned : $text;
    }
}

if (!function_exists('lead_ai_schedule_intent_signal')) {
    function lead_ai_schedule_intent_signal(string $text): bool
    {
        $normalized = strtolower(trim($text));
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b(?:yes|yeah|yep|sure|ok|okay|sounds good|lets do|let\'?s do|schedule|book|appointment|consult|consultation|available|availability|what day|what time|morning|afternoon|monday|tuesday|wednesday|thursday|friday|saturday|tomorrow|next week)\b/i',
            $normalized
        );
    }
}

if (!function_exists('lead_ai_scheduling_context')) {
    function lead_ai_scheduling_context(array $lead, string $latestMessage = ''): array
    {
        $preferredDay = trim((string)($lead['scheduling_preferred_day'] ?? ''));
        $preferredTime = trim((string)($lead['scheduling_preferred_time'] ?? ''));
        $dob = trim((string)($lead['date_of_birth'] ?? ''));
        $consultationDate = trim((string)($lead['consultation_date'] ?? ''));
        $missing = [];

        if ($consultationDate === '') {
            if ($preferredDay === '') {
                $missing[] = 'preferred day/date';
            }
            if ($preferredTime === '') {
                $missing[] = 'morning/afternoon or preferred time';
            }
        }
        if ($dob === '') {
            $missing[] = 'DOB';
        }

        return [
            'past_dates_forbidden' => true,
            'availability_requires_operator_confirmation' => true,
            'office_hours' => 'Monday-Thursday 9 AM-6 PM',
            'special_consult_hours' => 'Friday and Saturday morning by special appointment when needed',
            'consultation_type' => 'complimentary consultation with Dr. Meden',
            'schedule_intent_detected' => lead_ai_schedule_intent_signal($latestMessage),
            'preferred_day_known' => $preferredDay !== '',
            'preferred_time_known' => $preferredTime !== '',
            'dob_known' => $dob !== '',
            'consultation_already_booked' => $consultationDate !== '',
            'missing_for_scheduling' => $missing,
            'next_best_question' => $missing === []
                ? 'Confirm the appointment details or tell Rod to check availability.'
                : 'Ask for ' . implode(' and ', array_slice($missing, 0, 2)) . '.',
        ];
    }
}

if (!function_exists('lead_ai_current_time_context')) {
    function lead_ai_current_time_context(?DateTimeImmutable $now = null): array
    {
        $zone = new DateTimeZone(APP_TIMEZONE);
        $current = $now ? $now->setTimezone($zone) : new DateTimeImmutable('now', $zone);
        return [
            'datetime' => $current->format(DateTimeInterface::ATOM),
            'date' => $current->format('Y-m-d'),
            'time' => $current->format('g:i A'),
            'day_of_week' => $current->format('l'),
            'timezone' => APP_TIMEZONE,
            'past_dates_forbidden' => true,
        ];
    }
}

if (!function_exists('lead_ai_apply_scheduling_posture')) {
    function lead_ai_apply_scheduling_posture(array $lead, array $data, string $latestMessage = ''): array
    {
        $scheduling = lead_ai_scheduling_context($lead, $latestMessage);
        if (empty($scheduling['schedule_intent_detected']) || !empty($scheduling['consultation_already_booked'])) {
            return $data;
        }

        $data['classification'] = 'schedule_ready';
        $data['recommended_stage'] = 'in_contact';

        $missing = array_values((array)($scheduling['missing_for_scheduling'] ?? []));
        $note = trim((string)($data['note'] ?? ''));
        $next = $missing
            ? 'Need ' . implode(', ', $missing) . ' before Rod can check/confirm availability.'
            : 'Ready for Rod to check availability and prepare the Dentrix-ready scheduling package.';
        $data['note'] = trim($note !== '' ? $note . ' ' . $next : $next);

        return $data;
    }
}

if (!function_exists('lead_ai_context')) {
    function lead_ai_context(array $lead, string $latestMessage = '', string $mode = 'inbound_sms', ?array $threadState = null): string
    {
        $leadId = (int)($lead['id'] ?? 0);
        $threadState = $threadState ?? lead_ai_thread_state($lead, $mode);

        return json_encode([
            'mode' => $mode,
            'current_time' => lead_ai_current_time_context(),
            'lead' => [
                'id' => $leadId,
                'first_name' => lead_ai_first_name($lead),
                'full_name' => (string)($lead['full_name'] ?? ''),
                'phone' => (string)($lead['phone'] ?? ''),
                'email' => (string)($lead['email'] ?? ''),
                'procedure_interest' => (string)($lead['procedure_interest'] ?? ''),
                'source' => (string)($lead['source'] ?? ''),
                'landing_page' => (string)($lead['landing_page'] ?? ''),
                'status' => (string)($lead['status'] ?? ''),
                'financing_needed' => (string)($lead['financing_needed'] ?? ''),
                'consultation_status' => (string)($lead['consultation_status'] ?? ''),
                'consultation_date' => (string)($lead['consultation_date'] ?? ''),
                'date_of_birth' => (string)($lead['date_of_birth'] ?? ''),
                'scheduling_preferred_day' => (string)($lead['scheduling_preferred_day'] ?? ''),
                'scheduling_preferred_time' => (string)($lead['scheduling_preferred_time'] ?? ''),
                'notes' => mb_substr((string)($lead['notes'] ?? ''), 0, 1200),
            ],
            'thread_state' => $threadState,
            'conversion_memory' => $leadId > 0 ? lead_conversion_load($leadId) : [],
            'recent_conversion_strategies' => $leadId > 0 ? lead_conversion_recent_strategies($leadId, 3) : [],
            'scheduling_context' => lead_ai_scheduling_context($lead, $latestMessage),
            'prompt_context' => $latestMessage,
            'patient_conversation' => lead_ai_patient_conversation($leadId),
            'recent_activity_log' => lead_ai_recent_activity_log($leadId, 6),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}

if (!function_exists('lead_ai_email_context')) {
    function lead_ai_email_context(array $lead, string $latestMessage = '', string $mode = 'email_draft', ?array $threadState = null): string
    {
        $leadId = (int)($lead['id'] ?? 0);
        $threadState = $threadState ?? lead_ai_thread_state($lead, $mode);

        return json_encode([
            'mode' => $mode,
            'current_time' => lead_ai_current_time_context(),
            'lead' => [
                'id' => $leadId,
                'first_name' => lead_ai_first_name($lead),
                'full_name' => (string)($lead['full_name'] ?? ''),
                'phone' => (string)($lead['phone'] ?? ''),
                'email' => (string)($lead['email'] ?? ''),
                'procedure_interest' => (string)($lead['procedure_interest'] ?? ''),
                'source' => (string)($lead['source'] ?? ''),
                'landing_page' => (string)($lead['landing_page'] ?? ''),
                'campaign' => (string)($lead['campaign'] ?? ''),
                'status' => (string)($lead['status'] ?? ''),
                'financing_needed' => (string)($lead['financing_needed'] ?? ''),
                'consultation_status' => (string)($lead['consultation_status'] ?? ''),
                'consultation_date' => (string)($lead['consultation_date'] ?? ''),
                'next_follow_up_at' => (string)($lead['next_follow_up_at'] ?? ''),
                'notes' => mb_substr((string)($lead['notes'] ?? ''), 0, 1600),
            ],
            'thread_state' => $threadState,
            'conversion_memory' => $leadId > 0 ? lead_conversion_load($leadId) : [],
            'recent_conversion_strategies' => $leadId > 0 ? lead_conversion_recent_strategies($leadId, 3) : [],
            'scheduling_context' => lead_ai_scheduling_context($lead, $latestMessage),
            'prompt_context' => $latestMessage,
            'patient_conversation' => lead_ai_patient_conversation($leadId),
            'recent_activity_log' => lead_ai_recent_activity_log($leadId, 6),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}

if (!function_exists('lead_ai_outbound_note_fallback')) {
    function lead_ai_outbound_note_fallback(array $lead, string $channel, string $subject, string $body, string $sentAt): array
    {
        $channelLabel = strtoupper($channel) === 'EMAIL' ? 'email' : 'SMS';
        $subjectPart = trim($subject) !== '' ? ' Subject: ' . trim($subject) . '.' : '';
        $snippet = trim(preg_replace('/\s+/', ' ', $body) ?? '');
        $snippet = mb_substr($snippet, 0, 180);
        $name = trim((string)($lead['full_name'] ?? 'lead'));
        if ($name === '') {
            $name = 'lead';
        }

        $summary = 'Sent outbound ' . $channelLabel . ' to ' . $name . '.' . $subjectPart;
        $nextStep = 'Watch for reply and continue follow-up based on response.';
        $note = $summary . ' Message context: ' . $snippet . '. Next step: ' . $nextStep;

        return [
            'summary' => $summary,
            'intent' => 'Outbound follow-up',
            'next_step' => $nextStep,
            'note' => mb_substr($note, 0, 450),
            'confidence' => 0.35,
            'fallback' => true,
            'sent_at' => $sentAt,
        ];
    }
}

if (!function_exists('lead_ai_append_internal_note')) {
    function lead_ai_append_internal_note(int $leadId, string $note): string
    {
        $note = trim($note);
        if ($leadId <= 0 || $note === '' || !function_exists('leads_has_column') || !leads_has_column('notes')) {
            return '';
        }

        try {
            $lead = db_one('SELECT notes FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
            $existingNotes = (string)($lead['notes'] ?? '');
            $line = '[' . date('Y-m-d H:i') . '] ' . $note;
            $updatedNotes = trim($existingNotes) !== '' ? rtrim($existingNotes) . "\n\n" . $line : $line;

            $setParts = ['notes = :notes'];
            $params = ['notes' => $updatedNotes, 'id' => $leadId];
            if (leads_has_column('updated_at')) {
                $setParts[] = 'updated_at = :updated_at';
                $params['updated_at'] = date('Y-m-d H:i:s');
            }

            db_execute('UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1', $params);
            return $updatedNotes;
        } catch (Throwable $e) {
            esm_log('openai', 'AI outbound note append failed.', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }
}

if (!function_exists('lead_ai_create_outbound_note')) {
    function lead_ai_create_outbound_note(int $leadId, string $channel, string $subject, string $body, array $meta = []): array
    {
        $leadId = max(0, $leadId);
        $channel = strtolower(trim($channel));
        $subject = trim($subject);
        $body = trim($body);
        $sentAt = (string)($meta['sent_at'] ?? date('Y-m-d H:i:s'));

        if ($leadId <= 0 || $body === '') {
            return ['ok' => false, 'message' => 'Missing lead or message body for outbound AI note.'];
        }

        try {
            $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
            if (!$lead) {
                return ['ok' => false, 'message' => 'Lead not found for outbound AI note.'];
            }

            $context = [
                'mode' => 'outbound_' . ($channel === 'email' ? 'email' : 'sms') . '_note',
                'current_datetime' => date('Y-m-d H:i:s'),
                'sent_at' => $sentAt,
                'channel' => $channel === 'email' ? 'email' : 'sms',
                'operator' => (string)($meta['created_by'] ?? (function_exists('lead_comm_user_label') ? lead_comm_user_label() : 'System')),
                'lead' => [
                    'id' => $leadId,
                    'full_name' => (string)($lead['full_name'] ?? ''),
                    'phone' => (string)($lead['phone'] ?? ''),
                    'email' => (string)($lead['email'] ?? ''),
                    'procedure_interest' => (string)($lead['procedure_interest'] ?? ''),
                    'status' => (string)($lead['status'] ?? ''),
                    'consultation_status' => (string)($lead['consultation_status'] ?? ''),
                    'consultation_date' => (string)($lead['consultation_date'] ?? ''),
                    'date_of_birth' => (string)($lead['date_of_birth'] ?? ''),
                    'next_follow_up_at' => (string)($lead['next_follow_up_at'] ?? ''),
                    'notes' => mb_substr((string)($lead['notes'] ?? ''), 0, 900),
                ],
                'outbound_message' => [
                    'subject' => mb_substr($subject, 0, 255),
                    'body' => mb_substr($body, 0, 1800),
                ],
                'patient_conversation' => lead_ai_patient_conversation($leadId),
                'recent_activity_log' => lead_ai_recent_activity_log($leadId, 6),
            ];

            $result = lead_ai_json_response(
                lead_ai_outbound_note_system_prompt(),
                'Create the internal CRM note for this outbound communication: ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                lead_ai_outbound_note_schema(),
                'elite_smiles_outbound_note',
                'review'
            );

            $data = [];
            $usedFallback = true;
            if (!empty($result['ok']) && is_array($result['data'] ?? null)) {
                $data = $result['data'];
                $data['note'] = trim((string)($data['note'] ?? ''));
                $data['summary'] = trim((string)($data['summary'] ?? ''));
                $data['intent'] = trim((string)($data['intent'] ?? ''));
                $data['next_step'] = trim((string)($data['next_step'] ?? ''));
                $data['confidence'] = max(0.0, min(1.0, (float)($data['confidence'] ?? 0)));
                $usedFallback = $data['note'] === '';
            }

            if ($usedFallback) {
                $data = lead_ai_outbound_note_fallback($lead, $channel, $subject, $body, $sentAt);
            }

            $note = mb_substr(trim((string)($data['note'] ?? '')), 0, 700);
            if ($note === '') {
                return ['ok' => false, 'message' => 'Outbound AI note was empty.'];
            }

            $activityId = lead_comm_insert_activity($leadId, 'ai_outbound_note', $note, [
                'channel' => $channel === 'email' ? 'email' : 'sms',
                'subject' => $subject,
                'summary' => (string)($data['summary'] ?? ''),
                'intent' => (string)($data['intent'] ?? ''),
                'next_step' => (string)($data['next_step'] ?? ''),
                'confidence' => (float)($data['confidence'] ?? 0),
                'fallback' => !empty($data['fallback']) || $usedFallback,
                'message_id' => (int)($meta['message_id'] ?? 0),
                'email_id' => (int)($meta['email_id'] ?? 0),
                'sent_at' => $sentAt,
            ], !empty($result['provider']) ? ucfirst(str_replace('_', ' ', (string) $result['provider'])) : 'AI');

            $updatedNotes = lead_ai_append_internal_note($leadId, $note);

            return [
                'ok' => true,
                'note' => $note,
                'notes' => $updatedNotes,
                'activity_id' => $activityId,
                'fallback' => !empty($data['fallback']) || $usedFallback,
            ];
        } catch (Throwable $e) {
            esm_log('openai', 'AI outbound note creation failed.', [
                'lead_id' => $leadId,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'AI outbound note failed.'];
        }
    }
}

if (!function_exists('lead_ai_generate_reply')) {
    function lead_ai_generate_reply(array $lead, string $latestMessage = '', string $mode = 'inbound_sms'): array
    {
        $threadState = lead_ai_thread_state($lead, $mode);
        $result = lead_ai_json_response(
            lead_ai_system_prompt(),
            'Create the best CRM lead response and note for this context: ' . lead_ai_context($lead, $latestMessage, $mode, $threadState),
            lead_ai_schema(),
            'elite_smiles_lead_reply',
            'draft'
        );

        if (empty($result['ok']) || !is_array($result['data'] ?? null)) {
            return ['ok' => false, 'message' => (string)($result['message'] ?? 'AI reply failed.')];
        }

        $data = $result['data'];
        $data['reply'] = trim((string)($data['reply'] ?? ''));
        $data['note'] = trim((string)($data['note'] ?? ''));
        $data['confidence'] = max(0.0, min(1.0, (float)($data['confidence'] ?? 0)));
        $data['should_send'] = (bool)($data['should_send'] ?? false);
        $data['needs_human_review'] = (bool)($data['needs_human_review'] ?? true);
        $data = lead_ai_apply_scheduling_posture($lead, $data, $latestMessage);
        lead_conversion_apply_ai_result((int) ($lead['id'] ?? 0), $data);

        if ($data['reply'] === '') {
            $data['should_send'] = false;
            $data['needs_human_review'] = true;
        }

        $data['reply'] = lead_ai_strip_redundant_greeting(
            $data['reply'],
            lead_ai_first_name($lead),
            (bool)($threadState['suppress_greeting'] ?? false)
        );
        if (lead_ai_reuses_resolved_reaction($data['reply'], $threadState)) {
            $data['should_send'] = false;
            $data['needs_human_review'] = true;
            $data['note'] = trim($data['note'] . ' Draft blocked because it reused an already-answered reaction as fresh context.');
        }

        $data['provider'] = (string) ($result['provider'] ?? 'openai');
        $data['model'] = (string) ($result['model'] ?? (defined('OPENAI_MODEL_CHAT') ? OPENAI_MODEL_CHAT : ''));

        return ['ok' => true, 'data' => $data];
    }
}

if (!function_exists('lead_ai_improve_sms')) {
    function lead_ai_improve_sms(array $lead, string $currentMessage, string $operatorInstruction = ''): array
    {
        $currentMessage = trim($currentMessage);
        if ($currentMessage === '') {
            return ['ok' => false, 'message' => 'Write an SMS first, then click Improve.'];
        }

        $prompt = implode("\n\n", array_filter([
            'Lead first name for tone only: ' . lead_ai_first_name($lead),
            'Operator instruction, if any: ' . trim($operatorInstruction),
            'Composer SMS to improve:',
            $currentMessage,
        ], static fn (string $part): bool => trim($part) !== ''));

        $result = lead_ai_json_response(
            lead_ai_improve_system_prompt('sms'),
            $prompt,
            lead_ai_improve_sms_schema(),
            'elite_smiles_improve_sms',
            'draft'
        );

        if (empty($result['ok']) || !is_array($result['data'] ?? null)) {
            return ['ok' => false, 'message' => (string)($result['message'] ?? 'AI SMS improvement failed.')];
        }

        $data = $result['data'];
        $data['reply'] = trim((string)($data['reply'] ?? ''));
        $data['note'] = trim((string)($data['note'] ?? 'Composer SMS improved for grammar, tone, and clarity.'));
        $data['confidence'] = max(0.0, min(1.0, (float)($data['confidence'] ?? 0)));
        $data['classification'] = 'composer_improvement';
        $data['recommended_stage'] = trim((string)($lead['status'] ?? 'contacted'));
        $data['needs_human_review'] = true;
        $data['should_send'] = false;

        if ($data['reply'] === '') {
            $data['reply'] = $currentMessage;
            $data['confidence'] = 0.0;
        }

        $data['provider'] = (string) ($result['provider'] ?? 'openai');
        $data['model'] = (string) ($result['model'] ?? (defined('OPENAI_MODEL_CHAT') ? OPENAI_MODEL_CHAT : ''));

        return ['ok' => true, 'data' => $data];
    }
}

if (!function_exists('lead_ai_generate_email')) {
    function lead_ai_generate_email(array $lead, string $latestMessage = '', string $mode = 'email_draft'): array
    {
        $threadState = lead_ai_thread_state($lead, $mode);
        $result = lead_ai_json_response(
            lead_ai_email_system_prompt(),
            'Create the best CRM patient email and internal note for this context: ' . lead_ai_email_context($lead, $latestMessage, $mode, $threadState),
            lead_ai_email_schema(),
            'elite_smiles_lead_email',
            'draft'
        );

        if (empty($result['ok']) || !is_array($result['data'] ?? null)) {
            return ['ok' => false, 'message' => (string)($result['message'] ?? 'AI email failed.')];
        }

        $data = $result['data'];
        $data['subject'] = trim((string)($data['subject'] ?? ''));
        $data['body'] = trim((string)($data['body'] ?? ''));
        $data['note'] = trim((string)($data['note'] ?? ''));
        $data['next_follow_up_at'] = trim((string)($data['next_follow_up_at'] ?? ''));
        $data['confidence'] = max(0.0, min(1.0, (float)($data['confidence'] ?? 0)));
        $data['should_send'] = (bool)($data['should_send'] ?? false);
        $data['needs_human_review'] = (bool)($data['needs_human_review'] ?? true);
        $data = lead_ai_apply_scheduling_posture($lead, $data, $latestMessage);
        lead_conversion_apply_ai_result((int) ($lead['id'] ?? 0), $data);

        if ($data['subject'] === '' || $data['body'] === '') {
            $data['should_send'] = false;
            $data['needs_human_review'] = true;
        }

        $data['body'] = lead_ai_strip_redundant_greeting(
            $data['body'],
            lead_ai_first_name($lead),
            (bool)($threadState['suppress_greeting'] ?? false)
        );
        if (lead_ai_reuses_resolved_reaction($data['body'], $threadState)) {
            $data['should_send'] = false;
            $data['needs_human_review'] = true;
            $data['note'] = trim($data['note'] . ' Draft blocked because it reused an already-answered reaction as fresh context.');
        }

        $data['provider'] = (string) ($result['provider'] ?? 'openai');
        $data['model'] = (string) ($result['model'] ?? (defined('OPENAI_MODEL_CHAT') ? OPENAI_MODEL_CHAT : ''));

        return ['ok' => true, 'data' => $data];
    }
}

if (!function_exists('lead_ai_improve_email')) {
    function lead_ai_improve_email(array $lead, string $currentSubject, string $currentBody, string $operatorInstruction = ''): array
    {
        $currentSubject = trim($currentSubject);
        $currentBody = trim($currentBody);
        if ($currentSubject === '' && $currentBody === '') {
            return ['ok' => false, 'message' => 'Write an email first, then click Improve.'];
        }

        $prompt = implode("\n\n", array_filter([
            'Lead first name for tone only: ' . lead_ai_first_name($lead),
            'Operator instruction, if any: ' . trim($operatorInstruction),
            'Composer email subject:',
            $currentSubject !== '' ? $currentSubject : '(no subject)',
            'Composer email body:',
            $currentBody !== '' ? $currentBody : '(no body)',
        ], static fn (string $part): bool => trim($part) !== ''));

        $result = lead_ai_json_response(
            lead_ai_improve_system_prompt('email'),
            $prompt,
            lead_ai_improve_email_schema(),
            'elite_smiles_improve_email',
            'draft'
        );

        if (empty($result['ok']) || !is_array($result['data'] ?? null)) {
            return ['ok' => false, 'message' => (string)($result['message'] ?? 'AI email improvement failed.')];
        }

        $data = $result['data'];
        $data['subject'] = trim((string)($data['subject'] ?? $currentSubject));
        $data['body'] = trim((string)($data['body'] ?? $currentBody));
        $data['note'] = trim((string)($data['note'] ?? 'Composer email improved for grammar, tone, and clarity.'));
        $data['next_follow_up_at'] = '';
        $data['confidence'] = max(0.0, min(1.0, (float)($data['confidence'] ?? 0)));
        $data['classification'] = 'composer_improvement';
        $data['recommended_stage'] = trim((string)($lead['status'] ?? 'contacted'));
        $data['needs_human_review'] = true;
        $data['should_send'] = false;

        if ($data['body'] === '') {
            $data['body'] = $currentBody;
            $data['confidence'] = 0.0;
        }

        $data['provider'] = (string) ($result['provider'] ?? 'openai');
        $data['model'] = (string) ($result['model'] ?? (defined('OPENAI_MODEL_CHAT') ? OPENAI_MODEL_CHAT : ''));

        return ['ok' => true, 'data' => $data];
    }
}

if (!function_exists('lead_ai_send_reply_if_safe')) {
    function lead_ai_send_reply_if_safe(int $leadId, string $latestMessage, string $mode): array
    {
        $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$lead) {
            return ['ok' => false, 'message' => 'Lead not found.'];
        }

        if ((string)($lead['sms_opt_status'] ?? '') === 'opted_out') {
            return ['ok' => false, 'message' => 'Lead opted out of SMS.'];
        }

        $ai = lead_ai_generate_reply($lead, $latestMessage, $mode);
        if (empty($ai['ok'])) {
            return $ai;
        }

        $data = $ai['data'];
        lead_comm_insert_activity($leadId, 'ai_suggestion', 'AI suggested reply: ' . mb_substr((string)$data['reply'], 0, 500), [
            'classification' => $data['classification'] ?? '',
            'confidence' => $data['confidence'] ?? 0,
            'should_send' => $data['should_send'] ?? false,
            'needs_human_review' => $data['needs_human_review'] ?? true,
            'note' => $data['note'] ?? '',
        ], 'OpenAI');

        $canSend = (bool)$data['should_send']
            && !(bool)$data['needs_human_review']
            && (float)$data['confidence'] >= (float)ELITE_AI_MIN_CONFIDENCE
            && trim((string)$data['reply']) !== '';

        if (!$canSend) {
            return ['ok' => true, 'sent' => false, 'data' => $data, 'message' => 'AI suggestion saved for review.'];
        }

        $sendResult = elite_twilio_send_sms((string)($lead['phone'] ?? ''), (string)$data['reply'], [
            'lead_id' => $leadId,
            'lead' => $lead,
            'send_pushover_fallback' => true,
            'fallback_summary' => 'Twilio could not send the automatic SMS. Open lead actions to continue manually.',
            'original_body' => (string)$data['reply'],
        ]);
        if (empty($sendResult['ok'])) {
            return ['ok' => false, 'sent' => false, 'data' => $data, 'message' => (string)($sendResult['message'] ?? 'SMS failed.')];
        }
        $sentBody = (string)($sendResult['body'] ?? $data['reply']);

        $messageId = lead_comm_insert_message([
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

        lead_comm_insert_activity($leadId, 'ai_sms_outbound', 'AI sent SMS to ' . ($sendResult['to'] ?? '') . ': ' . mb_substr($sentBody, 0, 240), [
            'message_id' => $messageId,
            'classification' => $data['classification'] ?? '',
            'twilio_sid' => $sendResult['twilio_sid'] ?? '',
        ], 'OpenAI');
        lead_comm_update_rollup($leadId);

        lead_ai_create_outbound_note($leadId, 'sms', '', $sentBody, [
            'message_id' => $messageId,
            'sent_at' => date('Y-m-d H:i:s'),
            'created_by' => 'OpenAI',
        ]);

        return [
            'ok' => true,
            'sent' => true,
            'data' => $data,
            'body' => $sentBody,
            'to' => (string)($sendResult['to'] ?? $lead['phone'] ?? ''),
            'message' => 'AI reply sent.',
        ];
    }
}

if (!function_exists('lead_ai_maybe_autoreply_inbound')) {
    function lead_ai_maybe_autoreply_inbound(int $leadId, string $body, string $command = ''): void
    {
        if (!ELITE_AI_AUTOREPLY_ENABLED || $command !== '') {
            return;
        }

        $result = lead_ai_send_reply_if_safe($leadId, $body, 'inbound_sms');
        if (empty($result['ok'])) {
            esm_log('openai', 'Inbound AI autoreply failed.', [
                'lead_id' => $leadId,
                'message' => $result['message'] ?? '',
            ]);
        }
    }
}

if (!function_exists('lead_ai_default_new_lead_sms')) {
    function lead_ai_default_new_lead_sms(array $lead): string
    {
        $firstName = function_exists('lead_email_first_name') ? lead_email_first_name($lead) : '';
        $notes = strtolower((string)($lead['notes'] ?? ''));
        $prefersSpanish = str_contains($notes, 'preferred language: spanish') || str_contains($notes, 'idioma preferido: español') || str_contains($notes, 'idioma preferido: espanol');
        if ($prefersSpanish) {
            $greeting = $firstName !== '' ? 'Hola ' . $firstName . ',' : 'Hola,';
            return $greeting . ' soy Rod de Elite Smiles. Gracias por contactarnos sobre opciones para tu sonrisa. Que te gustaria mejorar mas: color, forma, espacios, dientes desgastados, o solo quieres conocer lo que es posible? Responde STOP para cancelar.';
        }

        $greeting = $firstName !== '' ? 'Hi ' . $firstName . ',' : 'Hi,';

        return $greeting . ' this is Rod with Elite Smiles. Thanks for reaching out about veneers/smile options. What are you hoping to improve most—color, shape, spacing, worn teeth, or are you just exploring what may be possible? Reply STOP to opt out.';
    }
}

if (!function_exists('lead_ai_maybe_send_new_lead_sms')) {
    function lead_ai_maybe_send_new_lead_sms(int $leadId): array
    {
        if (!ELITE_AI_NEW_LEAD_AUTOTEXT_ENABLED) {
            return [
                'attempted' => false,
                'sent' => false,
                'body' => '',
                'status_label' => 'Auto new-lead SMS disabled.',
            ];
        }

        $lead = db_one('SELECT * FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$lead) {
            return [
                'attempted' => false,
                'sent' => false,
                'body' => '',
                'status_label' => 'Lead not found.',
            ];
        }

        if (trim((string)($lead['phone'] ?? '')) === '') {
            return [
                'attempted' => false,
                'sent' => false,
                'body' => '',
                'status_label' => 'Lead has no phone number.',
            ];
        }

        if (trim((string)($lead['sms_opt_status'] ?? 'unknown')) === 'opted_out' || trim((string)($lead['status'] ?? '')) === 'opted_out') {
            return [
                'attempted' => false,
                'sent' => false,
                'body' => '',
                'status_label' => 'Lead opted out of SMS.',
            ];
        }

        $body = lead_ai_default_new_lead_sms($lead);
        $sendResult = elite_twilio_send_sms((string)($lead['phone'] ?? ''), $body, [
            'lead_id' => $leadId,
            'lead' => $lead,
            'send_pushover_fallback' => true,
            'fallback_summary' => 'Twilio could not send the automatic first-touch SMS. Open lead actions to retry manually.',
            'original_body' => $body,
        ]);

        if (empty($sendResult['ok'])) {
            if (function_exists('esm_log')) {
                esm_log('openai', 'New lead first-touch SMS failed.', [
                    'lead_id' => $leadId,
                    'message' => $sendResult['message'] ?? '',
                ]);
            }

            return [
                'attempted' => true,
                'sent' => false,
                'body' => $body,
                'status_label' => (string)($sendResult['message'] ?? 'Auto SMS failed.'),
            ];
        }

        $sentBody = (string)($sendResult['body'] ?? $body);
        $messageId = lead_comm_insert_message([
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

        lead_comm_insert_activity($leadId, 'sms_outbound', 'Auto first-touch SMS sent through new-lead workflow.', [
            'message_id' => $messageId,
            'twilio_sid' => $sendResult['twilio_sid'] ?? '',
            'source' => 'new_lead_auto_first_touch',
        ], 'System');
        lead_comm_update_rollup($leadId);

        lead_ai_create_outbound_note($leadId, 'sms', '', $sentBody, [
            'message_id' => $messageId,
            'sent_at' => date('Y-m-d H:i:s'),
            'created_by' => 'System',
            'source' => 'new_lead_auto_first_touch',
        ]);

        try {
            $updates = ['updated_at = :updated_at'];
            $params = [
                'id' => $leadId,
                'updated_at' => now(),
            ];
            if (function_exists('leads_has_column') && leads_has_column('next_follow_up_at')) {
                $updates[] = 'next_follow_up_at = :next_follow_up_at';
                $params['next_follow_up_at'] = date('Y-m-d H:i:s', strtotime('+48 hours'));
            }
            if (function_exists('leads_has_column') && leads_has_column('follow_up_status')) {
                $updates[] = "follow_up_status = 'ok'";
            }
            db_execute('UPDATE leads SET ' . implode(', ', $updates) . ' WHERE id = :id LIMIT 1', $params);
        } catch (Throwable $e) {
            if (function_exists('esm_log')) {
                esm_log('openai', 'Could not schedule first-touch SMS follow-up.', [
                    'lead_id' => $leadId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (function_exists('esm_log')) {
            esm_log('openai', 'New lead first-touch SMS sent.', [
                'lead_id' => $leadId,
                'message_id' => $messageId,
            ]);
        }

        return [
            'attempted' => true,
            'sent' => true,
            'body' => $sentBody,
            'status_label' => 'Auto SMS sent.',
        ];
    }
}
