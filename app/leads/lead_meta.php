<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/leads/lead_meta.php
 *
 * Marketing / lead-gen pipeline only.
 * Practice-side stages are intentionally excluded.
 */

if (!function_exists('lead_stage_labels')) {
    function lead_stage_labels(): array
    {
        return [
            'new_lead'            => 'New Lead',
            'attempted_contact'   => 'Attempted Contact',
            'contacted'           => 'Contacted',
            'in_contact'          => 'In Contact',
            'consultation_booked' => 'Consultation Booked',
            'treatment_accepted'  => 'Sale Closed',
            'no_answer'           => 'No Answer',
            'opted_out'           => 'Opted Out',

            'lost_lead'           => 'Lead Lost',
        ];
    }
}

if (!function_exists('lead_stage_order')) {
    function lead_stage_order(): array
    {
        return [
            'new_lead',
            'attempted_contact',
            'contacted',
            'in_contact',
            'consultation_booked',
            'treatment_accepted',
            'no_answer',
            'opted_out',

            'lost_lead',
        ];
    }
}

if (!function_exists('lead_stage_badge_class')) {
    function lead_stage_badge_class(string $status): string
    {
        return match ($status) {
            'new_lead'            => 'border-sky-200 bg-sky-50 text-sky-700',
            'attempted_contact'   => 'border-indigo-200 bg-indigo-50 text-indigo-700',
            'in_contact'          => 'border-teal-200 bg-teal-50 text-teal-700',
            'contacted'           => 'border-violet-200 bg-violet-50 text-violet-700',
            'consultation_booked' => 'border-purple-200 bg-purple-50 text-purple-700',
            'treatment_accepted'  => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'no_answer'           => 'border-amber-200 bg-amber-50 text-amber-800',
            'opted_out'           => 'border-slate-300 bg-slate-100 text-slate-700',

            'lost_lead'           => 'border-rose-200 bg-rose-50 text-rose-700',
            default               => 'border-slate-200 bg-slate-100 text-slate-700',
        };
    }
}

if (!function_exists('lead_financing_needed_options')) {
    function lead_financing_needed_options(): array
    {
        return [
            'yes'    => 'Yes',
            'no'     => 'No',
            'unsure' => 'Unsure',
        ];
    }
}

if (!function_exists('lead_financing_needed_badge_class')) {
    function lead_financing_needed_badge_class(string $value): string
    {
        return match ($value) {
            'yes'    => 'border-amber-200 bg-amber-50 text-amber-700',
            'no'     => 'border-slate-200 bg-slate-100 text-slate-700',
            'unsure' => 'border-purple-200 bg-purple-50 text-purple-700',
            default  => 'border-slate-200 bg-slate-100 text-slate-500',
        };
    }
}

if (!function_exists('lead_financing_option_labels')) {
    function lead_financing_option_labels(): array
    {
        return [
            'none'             => 'None',
            'mountain_america' => 'Mountain America Credit Union',
            'sunbit'           => 'Sunbit',
            'cherry'           => 'Cherry',
            'carecredit'       => 'CareCredit',
            'other'            => 'Other',
            ''                 => 'Not set',
        ];
    }
}

if (!function_exists('lead_financing_option_badge_class')) {
    function lead_financing_option_badge_class(string $value): string
    {
        return match ($value) {
            'mountain_america' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'sunbit'           => 'border-blue-200 bg-blue-50 text-blue-700',
            'cherry'           => 'border-pink-200 bg-pink-50 text-pink-700',
            'carecredit'       => 'border-teal-200 bg-teal-50 text-teal-700',
            'other'            => 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-700',
            'none'             => 'border-slate-200 bg-slate-100 text-slate-700',
            default            => 'border-slate-200 bg-slate-100 text-slate-500',
        };
    }
}

if (!function_exists('lead_lost_reason_options')) {
    function lead_lost_reason_options(): array
    {
        return [
            ''                     => 'Not set',
            'price'                => 'Price',
            'no_answer'            => 'No Answer',
            'went_elsewhere'       => 'Went Somewhere Else',
            'financing_declined'   => 'Financing Declined',
            'not_ready'            => 'Not Ready',
            'wrong_lead'           => 'Wrong Lead',
            'treatment_not_needed' => 'Treatment Not Needed',
            'scheduling_conflict'  => 'Scheduling Conflict',
            'other'                => 'Other',
        ];
    }
}

if (!function_exists('lead_default_stage')) {
    function lead_default_stage(): string
    {
        return 'new_lead';
    }
}

if (!function_exists('lead_default_assigned_to')) {
    function lead_default_assigned_to(array $user = []): string
    {
        $full = trim(((string)($user['first_name'] ?? '')) . ' ' . ((string)($user['last_name'] ?? '')));
        if ($full !== '') {
            return $full;
        }

        $first = trim((string)($user['first_name'] ?? ''));
        if ($first !== '') {
            return $first;
        }

        return 'Rodrigo';
    }
}

if (!function_exists('lead_empty_record')) {
    function lead_empty_record(array $user = []): array
    {
        return [
            'full_name'          => '',
            'phone'              => '',
            'email'              => '',
            'procedure_interest' => '',
            'source'             => 'manual',
            'landing_page'       => '',
            'campaign'           => '',
            'status'             => lead_default_stage(),
            'assigned_to'        => lead_default_assigned_to($user),
            'financing_needed'   => 'unsure',
            'financing_option'   => 'none',
            'preferred_contact'  => 'Text',
            'consultation_status' => 'requested',
            'consultation_date'  => '',
            'lead_value'         => '',
            'lost_reason'        => '',
            'notes'              => '',
        ];
    }
}

if (!function_exists('lead_min_capture_fields')) {
    function lead_min_capture_fields(): array
    {
        return ['full_name', 'phone', 'email'];
    }
}

if (!function_exists('lead_is_min_capture_complete')) {
    function lead_is_min_capture_complete(array $data): bool
    {
        $name  = trim((string)($data['full_name'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));

        return ($name !== '' || $phone !== '' || $email !== '');
    }
}

if (!function_exists('lead_conversion_stage_labels')) {
    function lead_conversion_stage_labels(): array
    {
        return [
            'new_lead' => 'New Lead',
            'first_touch_sent' => 'First Touch Sent',
            'scheduling' => 'Scheduling',
            'consultation_booked' => 'Consultation Booked',
            'consult_completed' => 'Consult Completed',
            'treatment_accepted' => 'Treatment Accepted',
            'nurture_lost' => 'Nurture / Lost',
        ];
    }
}

if (!function_exists('lead_conversion_stage_order')) {
    function lead_conversion_stage_order(): array
    {
        return [
            'new_lead',
            'first_touch_sent',
            'scheduling',
            'consultation_booked',
            'consult_completed',
            'treatment_accepted',
            'nurture_lost',
        ];
    }
}

if (!function_exists('lead_conversion_phone_digits')) {
    function lead_conversion_phone_digits(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}

if (!function_exists('lead_conversion_bad_phone')) {
    function lead_conversion_bad_phone(array $lead): bool
    {
        $digits = lead_conversion_phone_digits((string)($lead['phone'] ?? ''));
        if (strlen($digits) < 10) {
            return true;
        }
        if (preg_match('/^0+$/', $digits)) {
            return true;
        }
        if (preg_match('/^0{6,}\d+$/', $digits)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('lead_conversion_missing_email')) {
    function lead_conversion_missing_email(array $lead): bool
    {
        return trim((string)($lead['email'] ?? '')) === '';
    }
}

if (!function_exists('lead_conversion_datetime')) {
    function lead_conversion_datetime(mixed $value): ?DateTimeImmutable
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($raw);
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('lead_conversion_reply_needed')) {
    function lead_conversion_reply_needed(array $lead): bool
    {
        if ((int)($lead['unread_message_count'] ?? 0) > 0) {
            return true;
        }
        $lastInbound = lead_conversion_datetime($lead['last_inbound_at'] ?? '');
        $lastOutbound = lead_conversion_datetime($lead['last_outbound_at'] ?? '');
        return $lastInbound !== null && ($lastOutbound === null || $lastInbound > $lastOutbound);
    }
}

if (!function_exists('lead_conversion_has_future_consult')) {
    function lead_conversion_has_future_consult(array $lead): bool
    {
        $appointment = lead_conversion_datetime($lead['consultation_date'] ?? '');
        if ($appointment === null) {
            return false;
        }
        return $appointment >= new DateTimeImmutable('today');
    }
}

if (!function_exists('lead_conversion_has_past_consult')) {
    function lead_conversion_has_past_consult(array $lead): bool
    {
        $appointment = lead_conversion_datetime($lead['consultation_date'] ?? '');
        if ($appointment === null) {
            return false;
        }
        return $appointment < new DateTimeImmutable('today');
    }
}

if (!function_exists('lead_conversion_appointment_tomorrow')) {
    function lead_conversion_appointment_tomorrow(array $lead): bool
    {
        $appointment = lead_conversion_datetime($lead['consultation_date'] ?? '');
        if ($appointment === null) {
            return false;
        }
        return $appointment->format('Y-m-d') === (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
    }
}

if (!function_exists('lead_conversion_needs_dob')) {
    function lead_conversion_needs_dob(array $lead): bool
    {
        if (trim((string)($lead['date_of_birth'] ?? '')) !== '') {
            return false;
        }
        $status = trim((string)($lead['status'] ?? ''));
        return $status === 'consultation_booked' || trim((string)($lead['consultation_date'] ?? '')) !== '';
    }
}

if (!function_exists('lead_conversion_stage_key')) {
    function lead_conversion_stage_key(array $lead): string
    {
        $status = trim((string)($lead['status'] ?? ''));

        if ($status === 'new_lead') {
            return 'new_lead';
        }
        if ($status === 'treatment_accepted') {
            return 'treatment_accepted';
        }
        if (in_array($status, ['lost_lead', 'opted_out'], true)) {
            return 'nurture_lost';
        }
        if (lead_conversion_bad_phone($lead) && in_array($status, ['contacted', 'no_answer', 'lost_lead'], true)) {
            return 'nurture_lost';
        }
        if ($status === 'consultation_booked') {
            return lead_conversion_has_past_consult($lead) ? 'consult_completed' : 'consultation_booked';
        }
        if (lead_conversion_has_future_consult($lead)) {
            return 'consultation_booked';
        }
        if ($status === 'in_contact' || lead_conversion_reply_needed($lead)) {
            return 'scheduling';
        }
        if (in_array($status, ['contacted', 'attempted_contact'], true)) {
            return 'first_touch_sent';
        }
        if ($status === 'no_answer') {
            return 'nurture_lost';
        }

        return 'first_touch_sent';
    }
}

if (!function_exists('lead_conversion_stage_label')) {
    function lead_conversion_stage_label(array $lead): string
    {
        $labels = lead_conversion_stage_labels();
        $key = lead_conversion_stage_key($lead);
        return (string)($labels[$key] ?? ucwords(str_replace('_', ' ', $key)));
    }
}

if (!function_exists('lead_conversion_next_action')) {
    function lead_conversion_next_action(array $lead): array
    {
        $status = trim((string)($lead['status'] ?? ''));

        if (lead_conversion_bad_phone($lead)) {
            return ['key' => 'bad_phone', 'label' => 'Bad phone', 'tone' => 'rose'];
        }
        if (trim((string)($lead['sms_opt_status'] ?? 'unknown')) === 'opted_out') {
            return ['key' => 'dnd', 'label' => 'Do not text', 'tone' => 'slate'];
        }
        if ($status === 'consultation_booked' && lead_conversion_needs_dob($lead)) {
            return ['key' => 'ask_dob', 'label' => 'Ask DOB', 'tone' => 'amber'];
        }
        if (lead_conversion_reply_needed($lead)) {
            return ['key' => 'reply_needed', 'label' => 'Reply needed', 'tone' => 'blue'];
        }
        if (lead_conversion_appointment_tomorrow($lead)) {
            return ['key' => 'confirm_appointment', 'label' => 'Confirm appt', 'tone' => 'emerald'];
        }
        if (lead_conversion_needs_dob($lead)) {
            return ['key' => 'ask_dob', 'label' => 'Ask DOB', 'tone' => 'amber'];
        }
        if ($status === 'new_lead') {
            return ['key' => 'first_touch', 'label' => 'Send first touch', 'tone' => 'sky'];
        }
        if ($status === 'in_contact') {
            if (lead_conversion_missing_email($lead)) {
                return ['key' => 'ask_email', 'label' => 'Ask email', 'tone' => 'amber'];
            }
            return ['key' => 'offer_dates', 'label' => 'Offer dates', 'tone' => 'teal'];
        }
        if (in_array($status, ['contacted', 'attempted_contact'], true)) {
            return trim((string)($lead['last_outbound_at'] ?? '')) !== ''
                ? ['key' => 'send_follow_up', 'label' => 'Send follow-up', 'tone' => 'violet']
                : ['key' => 'first_touch', 'label' => 'Send first touch', 'tone' => 'sky'];
        }
        if ($status === 'consultation_booked') {
            return ['key' => 'appointment_ready', 'label' => 'Prep consult', 'tone' => 'purple'];
        }
        if ($status === 'no_answer') {
            return ['key' => 'nurture_review', 'label' => 'Nurture review', 'tone' => 'amber'];
        }
        if (in_array($status, ['lost_lead', 'opted_out'], true)) {
            return ['key' => 'lost_review', 'label' => 'Review reason', 'tone' => 'rose'];
        }

        return ['key' => 'review_next_step', 'label' => 'Review next step', 'tone' => 'slate'];
    }
}

if (!function_exists('lead_conversion_badge_class')) {
    function lead_conversion_badge_class(string $tone): string
    {
        return match ($tone) {
            'rose' => 'border-rose-200 bg-rose-50 text-rose-700',
            'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'blue' => 'border-blue-200 bg-blue-50 text-blue-700',
            'sky' => 'border-sky-200 bg-sky-50 text-sky-700',
            'teal' => 'border-teal-200 bg-teal-50 text-teal-700',
            'violet' => 'border-violet-200 bg-violet-50 text-violet-700',
            'purple' => 'border-purple-200 bg-purple-50 text-purple-700',
            default => 'border-slate-200 bg-slate-50 text-slate-600',
        };
    }
}

if (!function_exists('lead_conversion_badges')) {
    function lead_conversion_badges(array $lead): array
    {
        $badges = [];
        if (lead_conversion_bad_phone($lead)) {
            $badges[] = ['key' => 'bad_phone', 'label' => 'Bad Phone', 'tone' => 'rose'];
        }
        if (lead_conversion_missing_email($lead)) {
            $badges[] = ['key' => 'missing_email', 'label' => 'Missing Email', 'tone' => 'amber'];
        }
        if (lead_conversion_needs_dob($lead)) {
            $badges[] = ['key' => 'needs_dob', 'label' => 'Needs DOB', 'tone' => 'amber'];
        }
        if (lead_conversion_reply_needed($lead)) {
            $badges[] = ['key' => 'replied', 'label' => 'Replied', 'tone' => 'blue'];
        }
        if (lead_conversion_appointment_tomorrow($lead)) {
            $badges[] = ['key' => 'appointment_tomorrow', 'label' => 'Appt Tomorrow', 'tone' => 'emerald'];
        }
        if (trim((string)($lead['status'] ?? '')) === 'no_answer') {
            $badges[] = ['key' => 'no_answer_5_plus', 'label' => 'No Answer 5+', 'tone' => 'amber'];
        }
        if (trim((string)($lead['sms_opt_status'] ?? 'unknown')) === 'opted_out' || trim((string)($lead['status'] ?? '')) === 'opted_out') {
            $badges[] = ['key' => 'dnd_opted_out', 'label' => 'DND / Opted Out', 'tone' => 'slate'];
        }
        return $badges;
    }
}

if (!function_exists('lead_conversion_summary')) {
    function lead_conversion_summary(array $lead): array
    {
        $stageKey = lead_conversion_stage_key($lead);
        $stageLabels = lead_conversion_stage_labels();
        $nextAction = lead_conversion_next_action($lead);
        return [
            'stage_key' => $stageKey,
            'stage_label' => (string)($stageLabels[$stageKey] ?? $stageKey),
            'next_action' => $nextAction,
            'badges' => lead_conversion_badges($lead),
        ];
    }
}
