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
            ],
            'stage_rules' => [
                'new_lead' => 'New leads should be reviewed for first contact needs before anything else.',
                'contacted' => 'Contacted leads should be checked for follow-up timing and inbound replies.',
                'in_contact' => 'In Communication leads should be reviewed for the latest reply and next response timing.',
                'consultation_booked' => 'Consultation Booked leads should be protected from No Answer review.',
                'sale_closed' => 'Closed sales are not active follow-up candidates.',
                'no_answer' => 'No Answer is a protected review stage and should never be auto-set by the assistant.',
            ],
        ];
    }
}

