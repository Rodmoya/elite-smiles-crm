<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/leads/lead_meta.php
 *
 * Marketing / lead-gen pipeline only.
 * Practice-side stages are intentionally excluded.
 */

require_once dirname(__DIR__) . '/core/helpers.php';

if (!function_exists('lead_stage_labels')) {
    function lead_stage_labels(): array
    {
        return [
            'new_lead'            => 'New Lead',
            'attempted_contact'   => 'First Touch Attempted',
            'contacted'           => 'Active Follow-Up',
            'in_contact'          => 'Lead Answered / Scheduling',
            'consultation_booked' => 'Consultation Booked',
            'no_show_reschedule'  => 'No Show / Reschedule',
            'consult_completed'   => 'Consult Completed',
            'treatment_accepted'  => 'Treatment Accepted',
            'treatment_completed' => 'Treatment Completed',
            'no_answer'           => 'Nurture',
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
            'consult_completed',
            'treatment_accepted',
            'treatment_completed',
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
            'consult_completed'   => 'border-indigo-200 bg-indigo-50 text-indigo-700',
            'treatment_accepted'  => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'treatment_completed' => 'border-green-300 bg-green-50 text-green-800',
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

if (!function_exists('lead_operator_source_type_label')) {
    function lead_operator_source_type_label(array $lead): string
    {
        $sourceType = strtolower(trim((string)($lead['source_type'] ?? '')));

        if ($sourceType === '' || preg_match('/^\d{6,}$/', $sourceType)) {
            return '';
        }

        return match ($sourceType) {
            'meta_instant_form' => 'Meta Instant Form',
            'instagram_instant_form' => 'Instagram Instant Form',
            'facebook_instant_form' => 'Facebook Instant Form',
            'paid_social' => 'Paid Social',
            'website_form' => 'Website Form',
            'quiz_form' => 'Landing Quiz',
            'manual_entry' => 'Manual Entry',
            'inbound_sms' => 'Inbound SMS',
            'smile_design' => 'Smile Design',
            'internal' => 'Internal',
            default => ucwords(str_replace('_', ' ', $sourceType)),
        };
    }
}

if (!function_exists('lead_operator_source_label')) {
    function lead_operator_source_label(array $lead): string
    {
        $source = strtolower(trim((string)($lead['source'] ?? '')));
        $sourceType = strtolower(trim((string)($lead['source_type'] ?? '')));
        $landingPage = trim((string)($lead['landing_page'] ?? ''));
        $campaign = trim((string)($lead['source_campaign'] ?? ($lead['campaign'] ?? '')));

        if (in_array($sourceType, ['meta_instant_form', 'instagram_instant_form', 'facebook_instant_form'], true)) {
            return $landingPage !== '' ? 'Meta Form: ' . $landingPage : 'Meta Instant Form';
        }
        if (in_array($source, ['meta', 'meta_lead_form', 'facebook', 'instagram'], true)) {
            return $campaign !== '' ? 'Meta: ' . $campaign : match ($source) {
                'instagram' => 'Instagram',
                'facebook' => 'Facebook',
                default => 'Meta',
            };
        }
        if (in_array($source, ['google', 'google_ads'], true)) {
            return $landingPage !== '' ? 'Google: ' . $landingPage : 'Google Ads';
        }
        if ($source === 'website' || $sourceType === 'website_form') {
            return $landingPage !== '' ? 'Website: ' . $landingPage : 'Website';
        }
        if ($source === 'smile_design_intake' || $sourceType === 'smile_design') {
            return 'Smile Design';
        }
        if ($landingPage !== '') {
            return $landingPage;
        }

        return match ($source) {
            'landing_page' => 'Landing Page',
            'ringcentral' => 'RingCentral',
            'referral' => 'Referral',
            'walk_in' => 'Walk-In',
            'twilio_sms' => 'Inbound SMS',
            'manual' => 'Manual',
            '' => 'Unknown',
            default => ucwords(str_replace('_', ' ', $source)),
        };
    }
}

if (!function_exists('lead_operator_data_quality_flags')) {
    function lead_operator_data_quality_flags(array $lead): array
    {
        $flags = [];
        if (lead_conversion_bad_phone($lead)) {
            $flags[] = 'bad_phone';
        }
        if (lead_conversion_missing_email($lead)) {
            $flags[] = 'missing_email';
        }
        if (trim((string)($lead['source'] ?? '')) === 'manual'
            && in_array(strtolower(trim((string)($lead['source_type'] ?? ''))), ['meta_instant_form', 'instagram_instant_form', 'paid_social'], true)
        ) {
            $flags[] = 'source_needs_normalization';
        }
        if (trim((string)($lead['status'] ?? '')) === 'consult_completed'
            && trim((string)($lead['consultation_status'] ?? '')) !== 'completed'
        ) {
            $flags[] = 'consult_status_mismatch';
        }
        return array_values(array_unique($flags));
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
            'phone_raw'          => '',
            'phone_validation_status' => 'missing',
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
            'lead_answered' => 'Lead Answered',
            'active_follow_up' => 'Active Follow-Up',
            'scheduling' => 'Scheduling',
            'consultation_booked' => 'Consultation Booked',
            'no_show_reschedule' => 'No Show / Reschedule',
            'consult_completed' => 'Consult Completed',
            'treatment_accepted' => 'Treatment Accepted',
            'treatment_completed' => 'Treatment Completed',
            'nurture' => 'Nurture',
            'lost' => 'Lost',
            'opted_out' => 'Opted Out',
        ];
    }
}

if (!function_exists('lead_conversion_stage_order')) {
    function lead_conversion_stage_order(): array
    {
        return [
            'new_lead',
            'lead_answered',
            'active_follow_up',
            'scheduling',
            'consultation_booked',
            'no_show_reschedule',
            'consult_completed',
            'treatment_accepted',
            'treatment_completed',
            'nurture',
            'lost',
            'opted_out',
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
            'lead_answered' => 'in_contact',
            // Active Follow-Up is time-sensitive and recalculates from the
            // conversation timestamps even while legacy status compatibility remains.
            'active_follow_up' => 'contacted',
            'scheduling' => 'in_contact',
            'no_show_reschedule' => 'no_show_reschedule',
            'consultation_booked' => 'consultation_booked',
            'consult_completed' => 'consult_completed',
            'treatment_accepted' => 'treatment_accepted',
            'treatment_completed' => 'treatment_completed',
            'nurture' => 'no_answer',
            'lost' => 'lost_lead',
            'opted_out' => 'opted_out',
            default => $conversionStageKey,
        };
    }
}

if (!function_exists('lead_conversion_stage_badge_class')) {
    function lead_conversion_stage_badge_class(string $conversionStageKey): string
    {
        return match ($conversionStageKey) {
            'new_lead' => 'border-sky-200 bg-sky-50 text-sky-700',
            'lead_answered' => 'border-cyan-200 bg-cyan-50 text-cyan-700',
            'active_follow_up' => 'border-blue-200 bg-blue-50 text-blue-700',
            'scheduling' => 'border-teal-200 bg-teal-50 text-teal-700',
            'consultation_booked' => 'border-purple-200 bg-purple-50 text-purple-700',
            'no_show_reschedule' => 'border-orange-200 bg-orange-50 text-orange-800',
            'consult_completed' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
            'treatment_accepted' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'treatment_completed' => 'border-green-300 bg-green-50 text-green-800',
            'nurture' => 'border-slate-300 bg-slate-100 text-slate-700',
            'lost' => 'border-rose-200 bg-rose-50 text-rose-700',
            'opted_out' => 'border-slate-300 bg-slate-100 text-slate-600',
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
        return !elite_phone_is_valid_us((string)($lead['phone'] ?? ''));
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
        $lastInbound = lead_conversion_datetime($lead['last_inbound_at'] ?? '');
        $lastOutbound = lead_conversion_datetime($lead['last_outbound_at'] ?? '');
        if ($lastInbound !== null) {
            return $lastOutbound === null || $lastInbound > $lastOutbound;
        }
        return (int)($lead['unread_message_count'] ?? 0) > 0;
    }
}

if (!function_exists('lead_conversion_follow_up_needed')) {
    function lead_conversion_follow_up_needed(array $lead): bool
    {
        // This is an action queue, not a stored stage. It should appear and
        // disappear based on live timing, replies, DND, and appointments.
        $status = trim((string)($lead['status'] ?? ''));
        if (!in_array($status, ['new_lead', 'contacted', 'attempted_contact', 'in_contact'], true)) {
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
            // A successful outbound touch resolves any older due marker. This
            // protects the live queue even if a legacy row still has a stale
            // next_follow_up_at value.
            $lastOutbound = lead_conversion_datetime($lead['last_outbound_at'] ?? '');
            if ($lastOutbound !== null && $lastOutbound >= $nextFollowUp) {
                return false;
            }
            return true;
        }

        return false;
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
        if (lead_conversion_is_unreachable_invalid_contact($lead)) {
            return ['key' => 'unreachable', 'label' => 'Unreachable', 'tone' => 'slate'];
        }
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

        return $status === 'no_show_reschedule' || $consultationStatus === 'no_show';
    }
}

if (!function_exists('lead_conversion_has_scheduling_context')) {
    function lead_conversion_has_scheduling_context(array $lead): bool
    {
        if (lead_conversion_has_future_consult($lead)) {
            return true;
        }
        $consultationStatus = trim((string)($lead['consultation_status'] ?? ''));
        if (in_array($consultationStatus, ['scheduling', 'scheduled', 'booked', 'confirmed'], true)) {
            return true;
        }
        $agentPhase = trim((string)($lead['agent_scheduling_phase'] ?? $lead['scheduling_phase'] ?? ''));
        if ($agentPhase !== '') {
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

if (!function_exists('lead_conversion_is_first_24_hours')) {
    /** First-touch delivery is an event; it does not end the New Lead window. */
    function lead_conversion_is_first_24_hours(array $lead, ?DateTimeImmutable $now = null): bool
    {
        $status = trim((string)($lead['status'] ?? ''));
        if (!in_array($status, ['new_lead', 'attempted_contact', 'contacted', ''], true)) {
            return false;
        }
        if (lead_conversion_datetime($lead['last_inbound_at'] ?? '') !== null) {
            return false;
        }
        $createdAt = lead_conversion_datetime($lead['created_at'] ?? '');
        if ($createdAt === null) {
            return $status === 'new_lead' || $status === '';
        }
        $now = $now ?? new DateTimeImmutable('now');
        return $createdAt > $now->modify('-24 hours');
    }
}

if (!function_exists('lead_conversion_conversation_stalled')) {
    /** A replied conversation becomes Active Follow-Up after our answer sits for two hours. */
    function lead_conversion_conversation_stalled(array $lead, ?DateTimeImmutable $now = null): bool
    {
        $lastInbound = lead_conversion_datetime($lead['last_inbound_at'] ?? '');
        $lastOutbound = lead_conversion_datetime($lead['last_outbound_at'] ?? '');
        if ($lastInbound === null || $lastOutbound === null || $lastOutbound <= $lastInbound) {
            return false;
        }
        $now = $now ?? new DateTimeImmutable('now');
        return $lastOutbound <= $now->modify('-2 hours');
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
    function lead_conversion_stage_key(array $lead, ?DateTimeImmutable $now = null): string
    {
        // Order matters: durable milestones win first, then relationship state.
        // First touch, Reply Needed, and Needs Attention remain badges/overlays.
        $status = trim((string)($lead['status'] ?? ''));

        if ($status === 'treatment_completed') {
            return 'treatment_completed';
        }
        if ($status === 'treatment_accepted') {
            return 'treatment_accepted';
        }
        if ($status === 'consult_completed') {
            return 'consult_completed';
        }
        if ($status === 'no_show_reschedule' || lead_conversion_missed_consult_needs_reschedule($lead)) {
            return 'no_show_reschedule';
        }
        if ($status === 'opted_out') {
            return 'opted_out';
        }
        if ($status === 'lost_lead') {
            return 'lost';
        }
        if ($status === 'consultation_booked') {
            return lead_conversion_consult_completed($lead) ? 'consult_completed' : 'consultation_booked';
        }
        if ($status === 'no_answer') {
            return 'nurture';
        }
        if (lead_conversion_has_future_consult($lead)) {
            return 'consultation_booked';
        }
        if (lead_conversion_has_scheduling_context($lead)) {
            return 'scheduling';
        }
        if (lead_conversion_is_first_24_hours($lead, $now)) {
            return 'new_lead';
        }
        if (lead_conversion_reply_needed($lead)) {
            return 'lead_answered';
        }
        if (lead_conversion_conversation_stalled($lead, $now)) {
            return 'active_follow_up';
        }
        if (lead_conversion_datetime($lead['last_inbound_at'] ?? '') !== null) {
            return 'lead_answered';
        }
        if (in_array($status, ['new_lead', 'contacted', 'attempted_contact', 'in_contact', ''], true)) {
            $hasOutbound = lead_conversion_datetime($lead['last_outbound_at'] ?? '') !== null
                || lead_conversion_datetime($lead['last_contacted_at'] ?? '') !== null;
            return $hasOutbound ? 'active_follow_up' : 'new_lead';
        }
        return 'active_follow_up';
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

if (!function_exists('lead_lifecycle_column_exists')) {
    function lead_lifecycle_column_exists(string $column): bool
    {
        if (function_exists('leads_has_column')) {
            return leads_has_column($column);
        }
        try {
            return (bool) db_one("SHOW COLUMNS FROM leads LIKE '" . str_replace("'", "''", $column) . "'");
        } catch (Throwable) {
            return false;
        }
    }
}

if (!function_exists('lead_lifecycle_transition_status')) {
    /** Persist one audited lifecycle transition and reject stale/concurrent source state. */
    function lead_lifecycle_transition_status(
        int $leadId,
        string $toStatus,
        string $reason,
        string $source,
        array $allowedFrom
    ): array {
        if ($leadId <= 0 || $toStatus === '') {
            return ['ok' => false, 'changed' => false, 'reason' => 'invalid_transition'];
        }
        $lead = db_one('SELECT id, status FROM leads WHERE id = :id LIMIT 1', ['id' => $leadId]);
        if (!$lead) {
            return ['ok' => false, 'changed' => false, 'reason' => 'lead_not_found'];
        }
        $fromStatus = trim((string)($lead['status'] ?? ''));
        if ($fromStatus === $toStatus) {
            return ['ok' => true, 'changed' => false, 'from' => $fromStatus, 'to' => $toStatus];
        }
        if ($allowedFrom !== [] && !in_array($fromStatus, $allowedFrom, true)) {
            return ['ok' => true, 'changed' => false, 'reason' => 'source_stage_not_allowed', 'from' => $fromStatus, 'to' => $toStatus];
        }

        $sets = ['status = :to_status'];
        $params = ['to_status' => $toStatus, 'id' => $leadId, 'from_status' => $fromStatus];
        if (lead_lifecycle_column_exists('pipeline_position')) {
            try {
                $position = function_exists('lead_pipeline_next_position')
                    ? lead_pipeline_next_position($toStatus)
                    : (int)db_value('SELECT COALESCE(MAX(pipeline_position), 0) + 1 FROM leads WHERE status = :target_status', ['target_status' => $toStatus]);
                $sets[] = 'pipeline_position = :pipeline_position';
                $params['pipeline_position'] = $position;
            } catch (Throwable) {
                // Stage ordering is secondary to the authoritative status transition.
            }
        }
        if (lead_lifecycle_column_exists('updated_at')) {
            $sets[] = 'updated_at = :updated_at';
            $params['updated_at'] = now();
        }
        $changed = db_execute(
            'UPDATE leads SET ' . implode(', ', $sets) . ' WHERE id = :id AND status = :from_status LIMIT 1',
            $params
        );
        if ($changed > 0 && function_exists('lead_comm_insert_activity')) {
            lead_comm_insert_activity($leadId, 'lifecycle_stage_change', $reason, [
                'from' => $fromStatus,
                'to' => $toStatus,
                'source' => $source,
            ], 'System');
        }
        return ['ok' => true, 'changed' => $changed > 0, 'from' => $fromStatus, 'to' => $toStatus];
    }
}

if (!function_exists('lead_lifecycle_mark_inbound_answer')) {
    function lead_lifecycle_mark_inbound_answer(int $leadId, string $source, bool $explicitOptIn = false): array
    {
        $allowed = ['new_lead', 'attempted_contact', 'contacted', 'no_answer', 'lost_lead', ''];
        if ($explicitOptIn) {
            $allowed[] = 'opted_out';
        }
        $transition = lead_lifecycle_transition_status(
            $leadId,
            'in_contact',
            'Lead answered; the conversation is open again.',
            $source,
            $allowed
        );
        if (!empty($transition['changed']) && (string)($transition['from'] ?? '') === 'lost_lead'
            && lead_lifecycle_column_exists('lost_reason')) {
            db_execute('UPDATE leads SET lost_reason = NULL WHERE id = :id LIMIT 1', ['id' => $leadId]);
        }
        return $transition;
    }
}

if (!function_exists('lead_lifecycle_mark_scheduling')) {
    function lead_lifecycle_mark_scheduling(int $leadId, string $source): array
    {
        $transition = lead_lifecycle_transition_status(
            $leadId,
            'in_contact',
            'Lead expressed explicit scheduling intent.',
            $source,
            ['new_lead', 'attempted_contact', 'contacted', 'no_answer', 'in_contact', '']
        );
        if (lead_lifecycle_column_exists('consultation_status')) {
            db_execute(
                "UPDATE leads SET consultation_status = 'scheduling', updated_at = NOW()
                 WHERE id = :id AND COALESCE(consultation_status, '') NOT IN ('scheduled','booked','confirmed','completed')",
                ['id' => $leadId]
            );
        }
        return $transition;
    }
}

if (!function_exists('lead_conversion_is_unreachable_invalid_contact')) {
    function lead_conversion_is_unreachable_invalid_contact(array $lead): bool
    {
        return trim((string)($lead['status'] ?? '')) === 'no_answer'
            && trim((string)($lead['follow_up_status'] ?? '')) === 'unreachable';
    }
}

if (!function_exists('lead_conversion_next_action')) {
    function lead_conversion_next_action(array $lead): array
    {
        $status = trim((string)($lead['status'] ?? ''));
        $stageKey = lead_conversion_stage_key($lead);

        if (lead_conversion_is_unreachable_invalid_contact($lead)) {
            return ['key' => 'invalid_contact', 'label' => 'Invalid contact', 'tone' => 'slate'];
        }

        if ($status === 'treatment_completed') {
            return ['key' => 'completed_tracking', 'label' => 'Completed', 'tone' => 'emerald'];
        }
        if (lead_conversion_bad_phone($lead)) {
            return ['key' => 'bad_phone', 'label' => 'Bad phone', 'tone' => 'rose'];
        }
        if (trim((string)($lead['sms_opt_status'] ?? 'unknown')) === 'opted_out') {
            return ['key' => 'dnd', 'label' => 'Do not text', 'tone' => 'slate'];
        }
        if ($status === 'consult_completed' && !lead_conversion_consult_completed($lead)) {
            return ['key' => 'close_consult_status', 'label' => 'Close consult status', 'tone' => 'amber'];
        }
        if ($status === 'consultation_booked' && lead_conversion_needs_dob($lead)) {
            return ['key' => 'ask_dob', 'label' => 'Ask DOB', 'tone' => 'amber'];
        }
        if ($status === 'no_show_reschedule' || lead_conversion_missed_consult_needs_reschedule($lead)) {
            return ['key' => 'reschedule', 'label' => 'Reschedule', 'tone' => 'orange'];
        }
        if (lead_conversion_reply_needed($lead)) {
            return ['key' => 'reply_needed', 'label' => 'Reply / set next step', 'tone' => 'blue'];
        }
        if (lead_conversion_appointment_tomorrow($lead)) {
            return ['key' => 'confirm_appointment', 'label' => 'Confirm appt', 'tone' => 'emerald'];
        }
        if (lead_conversion_needs_dob($lead)) {
            return ['key' => 'ask_dob', 'label' => 'Ask DOB', 'tone' => 'amber'];
        }
        if ($stageKey === 'new_lead') {
            return trim((string)($lead['last_outbound_at'] ?? '')) === ''
                ? ['key' => 'first_touch', 'label' => 'Send first touch', 'tone' => 'sky']
                : ['key' => 'wait_for_reply', 'label' => 'First-day follow-up active', 'tone' => 'emerald'];
        }
        if (lead_conversion_follow_up_needed($lead)) {
            $lastTouch = lead_conversion_datetime($lead['last_outbound_at'] ?? '') ?? lead_conversion_datetime($lead['last_contacted_at'] ?? '');
            $hours = lead_conversion_age_hours($lastTouch);
            return ($hours !== null && $hours >= 72)
                ? ['key' => 'overdue_follow_up', 'label' => 'Send overdue follow-up', 'tone' => 'rose']
                : ['key' => 'second_follow_up', 'label' => 'Send 2nd follow-up', 'tone' => 'amber'];
        }
        if ($stageKey === 'scheduling') {
            if (lead_conversion_missing_email($lead)) {
                return ['key' => 'ask_email', 'label' => 'Ask email', 'tone' => 'amber'];
            }
            return ['key' => 'offer_dates', 'label' => 'Offer dates', 'tone' => 'teal'];
        }
        if ($stageKey === 'lead_answered') {
            return ['key' => 'conversation_open', 'label' => 'Continue conversation', 'tone' => 'blue'];
        }
        if ($stageKey === 'active_follow_up' || in_array($status, ['contacted', 'attempted_contact'], true)) {
            return trim((string)($lead['last_outbound_at'] ?? '')) !== ''
                ? ['key' => 'wait_for_reply', 'label' => 'Wait for reply', 'tone' => 'emerald']
                : ['key' => 'first_touch', 'label' => 'Send first touch', 'tone' => 'sky'];
        }
        if ($status === 'consultation_booked') {
            return ['key' => 'appointment_ready', 'label' => 'Prep consult', 'tone' => 'purple'];
        }
        if ($status === 'consult_completed') {
            return ['key' => 'review_treatment_plan', 'label' => 'Review treatment', 'tone' => 'indigo'];
        }
        if ($status === 'no_answer') {
            return ['key' => 'nurture_reactivate', 'label' => 'Nurture / reactivate', 'tone' => 'slate'];
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
            'cyan' => 'border-cyan-200 bg-cyan-50 text-cyan-700',
            'sky' => 'border-sky-200 bg-sky-50 text-sky-700',
            'teal' => 'border-teal-200 bg-teal-50 text-teal-700',
            'orange' => 'border-orange-200 bg-orange-50 text-orange-800',
            'violet' => 'border-violet-200 bg-violet-50 text-violet-700',
            'indigo' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
            'purple' => 'border-purple-200 bg-purple-50 text-purple-700',
            default => 'border-slate-200 bg-slate-50 text-slate-600',
        };
    }
}

if (!function_exists('lead_conversion_badges')) {
    function lead_conversion_badges(array $lead): array
    {
        $badges = [];
        if (lead_conversion_is_unreachable_invalid_contact($lead)) {
            $badges[] = ['key' => 'unreachable', 'label' => 'Unreachable', 'tone' => 'slate'];
        }
        if (lead_conversion_bad_phone($lead)) {
            $badges[] = ['key' => 'bad_phone', 'label' => 'Bad Phone', 'tone' => 'rose'];
        }
        if (lead_conversion_missing_email($lead)) {
            $badges[] = ['key' => 'missing_email', 'label' => 'Missing Email', 'tone' => 'amber'];
        }
        if (trim((string)($lead['last_outbound_at'] ?? '')) !== '' || trim((string)($lead['last_contacted_at'] ?? '')) !== '') {
            $badges[] = ['key' => 'first_touch_sent', 'label' => 'First Touch Sent', 'tone' => 'cyan'];
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
            $badges[] = ['key' => 'nurture', 'label' => 'Nurture', 'tone' => 'slate'];
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
