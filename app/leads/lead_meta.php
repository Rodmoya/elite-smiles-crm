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
            'attempted_contact'   => 'First Touch Attempted',
            'contacted'           => 'First Touch Sent',
            'in_contact'          => 'Scheduling',
            'consultation_booked' => 'Consultation Booked',
            'no_show_reschedule'  => 'No Show / Reschedule',
            'treatment_accepted'  => 'Treatment Accepted',
            'no_answer'           => 'No Answer / Nurture',
            'opted_out'           => 'Opted Out',

            'lost_lead'           => 'Lost / Archived',
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
            'no_show_reschedule',
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
            'no_show_reschedule'  => 'border-orange-200 bg-orange-50 text-orange-800',
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
            'follow_up_needed' => 'Follow-Up Needed',
            'scheduling' => 'Scheduling',
            'consultation_booked' => 'Consultation Booked',
            'no_show_reschedule' => 'No Show / Reschedule',
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
            'follow_up_needed',
            'scheduling',
            'consultation_booked',
            'no_show_reschedule',
            'consult_completed',
            'treatment_accepted',
            'nurture_lost',
        ];
    }
}

if (!function_exists('lead_conversion_stage_legacy_target')) {
    function lead_conversion_stage_legacy_target(string $conversionStageKey): string
    {
        // Conversion stages are an operator-facing display layer. Keep the saved
        // database status on durable legacy milestones until we intentionally
        // migrate the schema and every webhook/API/cron path together.
        return match ($conversionStageKey) {
            'new_lead' => 'new_lead',
            // Follow-Up Needed is time-sensitive and should recalculate as
            // replies, appointments, and last-touch timestamps change.
            'first_touch_sent', 'follow_up_needed' => 'contacted',
            'scheduling' => 'in_contact',
            'no_show_reschedule' => 'no_show_reschedule',
            // Consult Completed is derived from consultation timing for now.
            'consultation_booked', 'consult_completed' => 'consultation_booked',
            'treatment_accepted' => 'treatment_accepted',
            // Nurture / Lost is intentionally conservative for drag/drop: no
            // bulk move should silently mark a lead permanently lost.
            'nurture_lost' => 'no_answer',
            default => $conversionStageKey,
        };
    }
}

if (!function_exists('lead_conversion_stage_badge_class')) {
    function lead_conversion_stage_badge_class(string $conversionStageKey): string
    {
        return match ($conversionStageKey) {
            'new_lead' => 'border-sky-200 bg-sky-50 text-sky-700',
            'first_touch_sent' => 'border-cyan-200 bg-cyan-50 text-cyan-700',
            'follow_up_needed' => 'border-amber-200 bg-amber-50 text-amber-800',
            'scheduling' => 'border-teal-200 bg-teal-50 text-teal-700',
            'consultation_booked' => 'border-purple-200 bg-purple-50 text-purple-700',
            'no_show_reschedule' => 'border-orange-200 bg-orange-50 text-orange-800',
            'consult_completed' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
            'treatment_accepted' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'nurture_lost' => 'border-rose-200 bg-rose-50 text-rose-700',
            default => 'border-slate-200 bg-slate-50 text-slate-600',
        };
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

if (!function_exists('lead_conversion_follow_up_needed')) {
    function lead_conversion_follow_up_needed(array $lead): bool
    {
        // This is an action queue, not a stored stage. It should appear and
        // disappear based on live timing, replies, DND, and appointments.
        $status = trim((string)($lead['status'] ?? ''));
        if (!in_array($status, ['contacted', 'attempted_contact', 'in_contact'], true)) {
            return false;
        }
        if (lead_conversion_bad_phone($lead) || trim((string)($lead['sms_opt_status'] ?? 'unknown')) === 'opted_out') {
            return false;
        }
        if (lead_conversion_reply_needed($lead) || lead_conversion_has_future_consult($lead)) {
            return false;
        }

        $now = new DateTimeImmutable('now');
        $nextFollowUp = lead_conversion_datetime($lead['next_follow_up_at'] ?? '');
        if ($nextFollowUp !== null && $nextFollowUp <= $now) {
            return true;
        }

        $lastOutbound = lead_conversion_datetime($lead['last_outbound_at'] ?? '');
        if ($lastOutbound === null) {
            $lastOutbound = lead_conversion_datetime($lead['last_contacted_at'] ?? '');
        }
        if ($lastOutbound === null) {
            return false;
        }

        return $lastOutbound <= $now->modify('-24 hours');
    }
}

if (!function_exists('lead_conversion_last_touch_datetime')) {
    function lead_conversion_last_touch_datetime(array $lead): ?DateTimeImmutable
    {
        $latest = null;
        foreach (['last_inbound_at', 'last_outbound_at', 'last_contacted_at', 'updated_at', 'created_at'] as $field) {
            $value = lead_conversion_datetime($lead[$field] ?? '');
            if ($value !== null) {
                $latest = $latest === null || $value > $latest ? $value : $latest;
            }
        }
        return $latest;
    }
}

if (!function_exists('lead_conversion_age_hours')) {
    function lead_conversion_age_hours(?DateTimeImmutable $dateTime): ?int
    {
        if ($dateTime === null) {
            return null;
        }
        $seconds = (new DateTimeImmutable('now'))->getTimestamp() - $dateTime->getTimestamp();
        return max(0, (int)floor($seconds / 3600));
    }
}

if (!function_exists('lead_conversion_urgency')) {
    function lead_conversion_urgency(array $lead): array
    {
        if (lead_conversion_bad_phone($lead)) {
            return ['key' => 'cleanup', 'label' => 'Cleanup', 'tone' => 'rose'];
        }
        if (lead_conversion_reply_needed($lead)) {
            return ['key' => 'reply_now', 'label' => 'Reply now', 'tone' => 'blue'];
        }
        if (lead_conversion_appointment_tomorrow($lead)) {
            return ['key' => 'tomorrow', 'label' => 'Tomorrow', 'tone' => 'emerald'];
        }
        if (lead_conversion_follow_up_needed($lead)) {
            $lastTouch = lead_conversion_datetime($lead['last_outbound_at'] ?? '') ?? lead_conversion_datetime($lead['last_contacted_at'] ?? '');
            $hours = lead_conversion_age_hours($lastTouch);
            if ($hours !== null && $hours >= 72) {
                return ['key' => 'overdue_3d', 'label' => '3d+ overdue', 'tone' => 'rose'];
            }
            if ($hours !== null && $hours >= 48) {
                return ['key' => 'due_48h', 'label' => '48h due', 'tone' => 'amber'];
            }
            return ['key' => 'due_24h', 'label' => '24h due', 'tone' => 'amber'];
        }

        $lastTouch = lead_conversion_last_touch_datetime($lead);
        $hours = lead_conversion_age_hours($lastTouch);
        if ($hours !== null && $hours < 24) {
            return ['key' => 'recent', 'label' => 'Recent', 'tone' => 'emerald'];
        }

        return ['key' => 'normal', 'label' => 'On track', 'tone' => 'slate'];
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

if (!function_exists('lead_conversion_consult_completed')) {
    function lead_conversion_consult_completed(array $lead): bool
    {
        return trim((string)($lead['consultation_status'] ?? '')) === 'completed';
    }
}

if (!function_exists('lead_conversion_missed_consult_needs_reschedule')) {
    function lead_conversion_missed_consult_needs_reschedule(array $lead): bool
    {
        $status = trim((string)($lead['status'] ?? ''));
        $consultationStatus = trim((string)($lead['consultation_status'] ?? ''));

        if ($status !== 'consultation_booked' && $consultationStatus !== 'no_show') {
            return false;
        }
        if ($consultationStatus === 'completed') {
            return false;
        }
        if ($consultationStatus === 'no_show') {
            return true;
        }

        return lead_conversion_has_past_consult($lead);
    }
}

if (!function_exists('lead_conversion_has_scheduling_context')) {
    function lead_conversion_has_scheduling_context(array $lead): bool
    {
        if (lead_conversion_has_future_consult($lead)) {
            return true;
        }
        if (lead_conversion_reply_needed($lead)) {
            return true;
        }
        if (trim((string)($lead['scheduling_preferred_day'] ?? '')) !== '') {
            return true;
        }
        if (trim((string)($lead['scheduling_preferred_time'] ?? '')) !== '') {
            return true;
        }
        if (trim((string)($lead['scheduling_preference'] ?? '')) !== '') {
            return true;
        }
        return false;
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
        // Order matters: durable milestones win first, then dynamic action
        // states like replies/follow-ups, then default compatibility labels.
        $status = trim((string)($lead['status'] ?? ''));

        if ($status === 'new_lead') {
            return 'new_lead';
        }
        if ($status === 'treatment_accepted') {
            return 'treatment_accepted';
        }
        if ($status === 'no_show_reschedule' || lead_conversion_missed_consult_needs_reschedule($lead)) {
            return 'no_show_reschedule';
        }
        if (in_array($status, ['lost_lead', 'opted_out'], true)) {
            return 'nurture_lost';
        }
        if (lead_conversion_bad_phone($lead) && in_array($status, ['contacted', 'no_answer', 'lost_lead'], true)) {
            return 'nurture_lost';
        }
        if ($status === 'consultation_booked') {
            return lead_conversion_consult_completed($lead) ? 'consult_completed' : 'consultation_booked';
        }
        if (lead_conversion_has_future_consult($lead)) {
            return 'consultation_booked';
        }
        if ($status === 'in_contact' && lead_conversion_has_scheduling_context($lead)) {
            return 'scheduling';
        }
        if ($status === 'in_contact') {
            return 'follow_up_needed';
        }
        if (lead_conversion_reply_needed($lead)) {
            return 'scheduling';
        }
        if (lead_conversion_follow_up_needed($lead)) {
            return 'follow_up_needed';
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
        if ($status === 'no_show_reschedule' || lead_conversion_missed_consult_needs_reschedule($lead)) {
            return ['key' => 'reschedule', 'label' => 'Reschedule', 'tone' => 'orange'];
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
        if (lead_conversion_follow_up_needed($lead)) {
            $lastTouch = lead_conversion_datetime($lead['last_outbound_at'] ?? '') ?? lead_conversion_datetime($lead['last_contacted_at'] ?? '');
            $hours = lead_conversion_age_hours($lastTouch);
            return ($hours !== null && $hours >= 72)
                ? ['key' => 'overdue_follow_up', 'label' => 'Send overdue follow-up', 'tone' => 'rose']
                : ['key' => 'second_follow_up', 'label' => 'Send 2nd follow-up', 'tone' => 'amber'];
        }
        if ($status === 'in_contact') {
            if (lead_conversion_missing_email($lead)) {
                return ['key' => 'ask_email', 'label' => 'Ask email', 'tone' => 'amber'];
            }
            return ['key' => 'offer_dates', 'label' => 'Offer dates', 'tone' => 'teal'];
        }
        if (in_array($status, ['contacted', 'attempted_contact'], true)) {
            return trim((string)($lead['last_outbound_at'] ?? '')) !== ''
                ? ['key' => 'wait_for_reply', 'label' => 'Wait for reply', 'tone' => 'emerald']
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
            'orange' => 'border-orange-200 bg-orange-50 text-orange-800',
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
        if (lead_conversion_follow_up_needed($lead)) {
            $badges[] = ['key' => 'follow_up_due', 'label' => 'Follow-Up Due', 'tone' => 'amber'];
        }
        if (lead_conversion_appointment_tomorrow($lead)) {
            $badges[] = ['key' => 'appointment_tomorrow', 'label' => 'Appt Tomorrow', 'tone' => 'emerald'];
        }
        if (trim((string)($lead['status'] ?? '')) === 'no_show_reschedule' || lead_conversion_missed_consult_needs_reschedule($lead)) {
            $badges[] = ['key' => 'no_show_reschedule', 'label' => 'No Show', 'tone' => 'orange'];
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
            'urgency' => lead_conversion_urgency($lead),
        ];
    }
}
