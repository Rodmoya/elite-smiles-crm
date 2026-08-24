<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/leads/lead_playbooks.php
 *
 * Guided questions and polished response templates for lead follow-up.
 */

if (!function_exists('lead_playbook_sms_templates')) {
    function lead_playbook_sms_templates(): array
    {
        return [
            'first_follow_up' => [
                'label' => 'First Follow-Up',
                'body' => 'Hi {first_name}, this is Rod with Elite Smiles. I saw your request about veneers/smile options. What are you hoping to improve most: color, shape, spacing, worn teeth, or just exploring what is possible? Reply STOP to opt out.',
            ],
            'price_objection' => [
                'label' => 'Price Question',
                'body' => 'Totally understand, {first_name}. Every smile case is custom, so Dr. Meden first needs to review your teeth, bite, and goals. The consultation is complimentary and gives you a clearer idea of what may make sense for your smile. Would you like me to help with that next step?',
            ],
            'scheduling_info' => [
                'label' => 'Scheduling Info',
                'body' => 'Perfect, {first_name}. I can help with that. What day usually works best for your complimentary consultation with Dr. Meden?',
            ],
            'no_answer' => [
                'label' => 'Active Follow-Up',
                'body' => 'No pressure, {first_name}. Most people who reach out are simply trying to understand what is possible. What would you most like to improve about your smile?',
            ],
            'nurture_reactivation' => [
                'label' => 'Nurture Reactivation',
                'body' => 'Hi {first_name}, Rod from Elite Smiles. Just checking in softly. If you are still curious what veneers could look like for your smile, the consultation is complimentary and completely custom. Want me to send a couple times this week? Reply STOP to opt out.',
            ],
            'financing_concern' => [
                'label' => 'Financing Concern',
                'body' => 'That makes sense, {first_name}. Financing depends on the treatment plan, and the plan depends on what Dr. Meden sees clinically. The complimentary consultation is where we can review what fits your smile and what payment options may apply.',
            ],
            'not_ready_check_in' => [
                'label' => 'Not Ready Check-In',
                'body' => 'Hi {first_name}, just checking in gently. If now is not the right time, that is completely okay. If you still want to understand what is possible for your smile, I can help set up the complimentary consultation with Dr. Meden. Reply STOP to opt out.',
            ],
            'appointment_confirmation' => [
                'label' => 'Appointment Confirmation',
                'body' => 'Perfect, {first_name}. I have you scheduled for {appointment_time} with Dr. Meden for your free dental implant consultation. We will see you at 11762 South State, Suite 300, Draper, UT 84020. If you need a quick call before then or anything changes, just let me know.',
            ],
        ];
    }
}

if (!function_exists('lead_playbook_scheduling_questions')) {
    function lead_playbook_scheduling_questions(): array
    {
        return [
            'date_of_birth' => 'Date of birth for the appointment record',
            'preferred_day' => 'Preferred day or date',
            'preferred_time' => 'Morning, afternoon, or exact time',
            'service_need' => 'What they want Dr. Meden to evaluate',
            'financing' => 'Whether financing should be reviewed',
        ];
    }
}
