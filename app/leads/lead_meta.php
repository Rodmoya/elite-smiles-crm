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
            'consultation_booked' => 'Consultation Booked',
            'treatment_accepted'  => 'Sale Closed',
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
            'consultation_booked',
            'treatment_accepted',
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
            'contacted'           => 'border-violet-200 bg-violet-50 text-violet-700',
            'consultation_booked' => 'border-purple-200 bg-purple-50 text-purple-700',
            'treatment_accepted'  => 'border-emerald-200 bg-emerald-50 text-emerald-700',
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
