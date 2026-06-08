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
                    'enum' => ['new_lead', 'attempted_contact', 'contacted', 'consultation_booked', 'treatment_accepted', 'opted_out', 'lost_lead'],
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
                    'enum' => ['new_lead', 'attempted_contact', 'contacted', 'consultation_booked', 'treatment_accepted', 'opted_out', 'lost_lead'],
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
            'Compliance: if the patient asks to stop or says they are not interested, do not write a follow-up email to send. Set should_send false.',
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

if (!function_exists('lead_ai_context')) {
    function lead_ai_context(array $lead, string $latestMessage = '', string $mode = 'inbound_sms'): string
    {
        $messages = [];
        $leadId = (int)($lead['id'] ?? 0);
        if ($leadId > 0) {
            foreach (array_reverse(lead_comm_recent_messages($leadId, 8)) as $message) {
                $messages[] = [
                    'direction' => (string)($message['direction'] ?? ''),
                    'body' => (string)($message['body'] ?? ''),
                    'created_at' => (string)($message['created_at'] ?? ''),
                ];
            }
        }

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
            'latest_patient_message' => $latestMessage,
            'recent_sms_thread' => $messages,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}

if (!function_exists('lead_ai_email_context')) {
    function lead_ai_email_context(array $lead, string $latestMessage = '', string $mode = 'email_draft'): string
    {
        $emails = [];
        $leadId = (int)($lead['id'] ?? 0);
        if ($leadId > 0 && function_exists('lead_email_recent')) {
            foreach (array_reverse(lead_email_recent($leadId, 8)) as $email) {
                $emails[] = [
                    'direction' => (string)($email['direction'] ?? ''),
                    'subject' => (string)($email['subject'] ?? ''),
                    'body' => mb_substr((string)($email['body'] ?? ''), 0, 900),
                    'created_at' => (string)($email['created_at'] ?? ''),
                ];
            }
        }

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
            'latest_context_or_instruction' => $latestMessage,
            'recent_email_thread' => $emails,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
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

        $sendResult = elite_twilio_send_sms((string)($lead['phone'] ?? ''), (string)$data['reply']);
        if (empty($sendResult['ok'])) {
            return ['ok' => false, 'sent' => false, 'data' => $data, 'message' => (string)($sendResult['message'] ?? 'SMS failed.')];
        }

        $messageId = lead_comm_insert_message([
            'lead_id' => $leadId,
            'direction' => 'outbound',
            'channel' => 'sms',
            'from_number' => (string)($sendResult['from'] ?? ''),
            'to_number' => (string)($sendResult['to'] ?? $lead['phone'] ?? ''),
            'body' => (string)$data['reply'],
            'twilio_message_sid' => (string)($sendResult['twilio_sid'] ?? ''),
            'twilio_status' => (string)($sendResult['twilio_status'] ?? ''),
            'is_read' => 1,
        ]);

        lead_comm_insert_activity($leadId, 'ai_sms_outbound', 'AI sent SMS to ' . ($sendResult['to'] ?? '') . ': ' . mb_substr((string)$data['reply'], 0, 240), [
            'message_id' => $messageId,
            'classification' => $data['classification'] ?? '',
            'twilio_sid' => $sendResult['twilio_sid'] ?? '',
        ], 'OpenAI');
        lead_comm_update_rollup($leadId);

        return ['ok' => true, 'sent' => true, 'data' => $data, 'message' => 'AI reply sent.'];
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

if (!function_exists('lead_ai_maybe_send_new_lead_sms')) {
    function lead_ai_maybe_send_new_lead_sms(int $leadId): void
    {
        if (!ELITE_AI_NEW_LEAD_AUTOTEXT_ENABLED) {
            return;
        }

        $result = lead_ai_send_reply_if_safe($leadId, 'New landing page lead submitted. Send the first friendly follow-up text.', 'new_lead');
        if (empty($result['ok'])) {
            esm_log('openai', 'New lead AI text failed.', [
                'lead_id' => $leadId,
                'message' => $result['message'] ?? '',
            ]);
        }
    }
}
