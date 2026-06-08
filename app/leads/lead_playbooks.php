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
                'body' => 'Hi {first_name}, this is Rod Moya with Elite Smiles. I wanted to personally follow up on your dental implant consultation request. The consultation with Dr. Meden is free, and it gives us a chance to evaluate your case properly, review your options, and go over pricing and financing based on what you actually need. We may also have 0% interest options available for qualified patients. Would mornings or afternoons usually work better for you to come in?',
            ],
            'price_objection' => [
                'label' => 'Price Question',
                'body' => 'Hi {first_name}, I completely understand wanting to know the cost before coming in. Unfortunately, we cannot give an accurate price without an exam because every patient and every case is different. We do not do cookie-cutter implant work at Elite Smiles. Dr. Meden takes each case seriously and personally so he can recommend the right plan for you. The consultation is free, and we can review your options, pricing, and financing case by case. Would mornings or afternoons work better for you?',
            ],
            'scheduling_info' => [
                'label' => 'Scheduling Info',
                'body' => 'Perfect, {first_name}. I can help with that. To schedule your free consultation with Dr. Meden, what day and time usually work best for you? We will also need your date of birth for the appointment record.',
            ],
            'no_answer' => [
                'label' => 'Called No Answer',
                'body' => 'Hi {first_name}, this is Rod Moya with Elite Smiles. I just tried giving you a quick call about your free consultation request with Dr. Meden and missed you. No rush, but I would be happy to help you look at options and answer questions. Would mornings or afternoons usually work better for you?',
            ],
            'financing_concern' => [
                'label' => 'Financing Concern',
                'body' => 'Hi {first_name}, I completely understand wanting to know what financing may look like before making a decision. Every case is reviewed individually after Dr. Meden evaluates what you need, and we may have 0% interest options available for qualified patients. The consultation is free, so it is the best way to understand your actual options before committing to anything.',
            ],
            'not_ready_check_in' => [
                'label' => 'Not Ready Check-In',
                'body' => 'Hi {first_name}, this is Rod Moya with Elite Smiles. I wanted to check in and see if you are still considering dental implants or if your timing has changed. If you still have questions, I am happy to help you understand the consultation process and what Dr. Meden would review with you.',
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
