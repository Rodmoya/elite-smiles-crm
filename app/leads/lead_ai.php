<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: app/leads/lead_ai.php
 *
 * AI-assisted lead replies and intake automation.
 */

require_once dirname(__DIR__) . '/core/openai.php';
require_once dirname(__DIR__) . '/core/twilio.php';
require_once __DIR__ . '/lead_service.php';
require_once __DIR__ . '/lead_communications.php';
require_once __DIR__ . '/lead_email.php';

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
                    'enum' => ['new_lead', 'attempted_contact', 'contacted', 'in_contact', 'consultation_booked', 'treatment_accepted', 'no_answer', 'opted_out', 'lost_lead'],
                ],
                'needs_human_review' => ['type' => 'boolean'],
                'should_send' => ['type' => 'boolean'],
                'confidence' => ['type' => 'number'],
            ],
            'required' => ['classification', 'reply', 'note', 'recommended_stage', 'needs_human_review', 'should_send', 'confidence'],
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
                    'enum' => ['new_lead', 'attempted_contact', 'contacted', 'in_contact', 'consultation_booked', 'treatment_accepted', 'no_answer', 'opted_out', 'lost_lead'],
                ],
                'next_follow_up_at' => ['type' => 'string'],
                'needs_human_review' => ['type' => 'boolean'],
                'should_send' => ['type' => 'boolean'],
                'confidence' => ['type' => 'number'],
            ],
            'required' => ['classification', 'subject', 'body', 'note', 'recommended_stage', 'next_follow_up_at', 'needs_human_review', 'should_send', 'confidence'],
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

if (!function_exists('lead_ai_system_prompt')) {
    function lead_ai_system_prompt(): string
    {
        return implode("\n", [
            'You write polished SMS replies as Rod Moya from Elite Smiles in Draper, Utah.',
            'Business facts: Elite Smiles by Walter Meden DDS, 11762 South State, Suite 300, Draper, UT 84020. Phone: (801) 572-6262.',
            'Primary goal: schedule a free consultation with Dr. Meden for dental implants, All-on-X, veneers, or smile consultation leads.',
            'Tone: warm, personal, professional, persuasive, never pushy, perfect grammar and capitalization.',
            'Financing: 0% interest may be available for qualified patients. Do not promise approval.',
            'Pricing: never give exact pricing without an exam. Explain that each case is evaluated personally and the free consultation reviews options, pricing, and financing case by case.',
            'Clinical safety: do not diagnose, prescribe, guarantee outcomes, or answer urgent medical issues. Ask clinical questions to be reviewed by Dr. Meden at consultation.',
            'Scheduling: if the patient wants to schedule, ask for date of birth and preferred day/time unless those are already known. If a specific time is confirmed by the office context, confirm it clearly.',
            'Directions: give clear address and offer to help by phone if needed.',
            'Use the recent SMS, email, and activity context to avoid repeating yourself and to continue the conversation naturally.',
            'If operator instructions are present in the context, follow them while staying compliant.',
            'Compliance: do not message if the patient asks to stop. If they say STOP/CANCEL/UNSUBSCRIBE, classify not_interested, recommend opted_out, should_send false, needs_human_review false.',
            'Return only JSON matching the schema.',
        ]);
    }
}

if (!function_exists('lead_ai_email_system_prompt')) {
    function lead_ai_email_system_prompt(): string
    {
        return implode("\n", [
            'You write polished patient-facing emails from the Elite Smiles team in Draper, Utah.',
            'Business facts: Elite Smiles by Walter Meden DDS, 11762 South State, Suite 300, Draper, UT 84020. Phone: (801) 572-6262.',
            'Primary goal: schedule a free consultation with Dr. Meden for dental implants, All-on-X, veneers, or smile consultation leads.',
            'Tone: warm, polished, professional, persuasive, personal, never pushy. Write like a real office team member, not marketing automation.',
            'Email format: concise subject, plain-text body, short paragraphs, signed "The Elite Smiles Team" with the Elite Smiles phone number.',
            'Financing: 0% interest may be available for qualified patients. Do not promise approval.',
            'Pricing: never give exact pricing without an exam. Explain that each case is evaluated personally and the free consultation reviews options, pricing, and financing case by case.',
            'Clinical safety: do not diagnose, prescribe, guarantee outcomes, or answer urgent medical issues. Invite clinical questions to be reviewed with Dr. Meden.',
            'Scheduling: if the patient wants to schedule, ask whether mornings or afternoons work better. If the office context already includes a specific confirmed time, confirm it clearly.',
            'Use the recent SMS, email, and activity context to avoid repeating yourself and to continue the conversation naturally.',
            'If operator instructions are present in the context, follow them while staying compliant.',
            'Compliance: if the patient asks to stop or says they are not interested, do not write a follow-up email to send. Set should_send false.',
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

if (!function_exists('lead_ai_context')) {
    function lead_ai_context(array $lead, string $latestMessage = '', string $mode = 'inbound_sms'): string
    {
        $leadId = (int)($lead['id'] ?? 0);

        return json_encode([
            'mode' => $mode,
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
            'prompt_context' => $latestMessage,
            'recent_sms_thread' => lead_ai_recent_sms_thread($leadId, 8),
            'recent_email_thread' => lead_ai_recent_email_thread($leadId, 6),
            'recent_activity_log' => lead_ai_recent_activity_log($leadId, 6),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}

if (!function_exists('lead_ai_email_context')) {
    function lead_ai_email_context(array $lead, string $latestMessage = '', string $mode = 'email_draft'): string
    {
        $leadId = (int)($lead['id'] ?? 0);

        return json_encode([
            'mode' => $mode,
            'current_datetime' => date('Y-m-d H:i:s'),
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
            'prompt_context' => $latestMessage,
            'recent_email_thread' => lead_ai_recent_email_thread($leadId, 8),
            'recent_sms_thread' => lead_ai_recent_sms_thread($leadId, 6),
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
                'recent_sms_thread' => lead_ai_recent_sms_thread($leadId, 6),
                'recent_email_thread' => lead_ai_recent_email_thread($leadId, 6),
                'recent_activity_log' => lead_ai_recent_activity_log($leadId, 6),
            ];

            $result = elite_openai_json_response(
                lead_ai_outbound_note_system_prompt(),
                'Create the internal CRM note for this outbound communication: ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                lead_ai_outbound_note_schema(),
                'elite_smiles_outbound_note'
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
            ], 'OpenAI');

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
        $result = elite_openai_json_response(
            lead_ai_system_prompt(),
            'Create the best CRM lead response and note for this context: ' . lead_ai_context($lead, $latestMessage, $mode),
            lead_ai_schema(),
            'elite_smiles_lead_reply'
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

        if ($data['reply'] === '') {
            $data['should_send'] = false;
            $data['needs_human_review'] = true;
        }

        return ['ok' => true, 'data' => $data];
    }
}

if (!function_exists('lead_ai_generate_email')) {
    function lead_ai_generate_email(array $lead, string $latestMessage = '', string $mode = 'email_draft'): array
    {
        $result = elite_openai_json_response(
            lead_ai_email_system_prompt(),
            'Create the best CRM patient email and internal note for this context: ' . lead_ai_email_context($lead, $latestMessage, $mode),
            lead_ai_email_schema(),
            'elite_smiles_lead_email'
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

        if ($data['subject'] === '' || $data['body'] === '') {
            $data['should_send'] = false;
            $data['needs_human_review'] = true;
        }

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
        $greeting = $firstName !== '' ? 'Hi ' . $firstName . ',' : 'Hi,';

        return $greeting . ' this is Rod from Elite Smiles. Thanks for reaching out about your smile consultation. We offer a complimentary consultation with Dr. Meden to review options and financing. What day/time works best for you? Reply STOP to opt out.';
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
