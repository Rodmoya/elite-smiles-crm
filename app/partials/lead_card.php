<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/partials/lead_card.php
 *
 * Premium compact lead card:
 * - cleaner outside presentation
 * - minimal color usage
 * - single missing badge only
 * - no notes outside
 * - date moved to top
 * - service shown clearly
 * - assigned + value kept
 * - modal-ready data attributes preserved
 * - neutral attribution for website / landing / Meta / Google / manual sources
 */

$lead = $lead ?? [];
$stageKey = $stageKey ?? '_blank';

if (!function_exists('lead_card_value')) {
    function lead_card_value(array $lead, string $key, string $default = ''): string
    {
        return isset($lead[$key]) ? trim((string)$lead[$key]) : $default;
    }
}

if (!function_exists('lead_card_money')) {
    function lead_card_money($amount): string
    {
        $value = 10000;

        if ($amount !== null && $amount !== '' && is_numeric($amount)) {
            $value = (float)$amount;
        }

        return '$' . number_format($value, 0);
    }
}

if (!function_exists('lead_card_badge_class')) {
    function lead_card_badge_class(string $tone): string
    {
        return match ($tone) {
            'amber'   => 'border-amber-200 bg-amber-50 text-amber-700',
            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'blue'    => 'border-blue-200 bg-blue-50 text-blue-700',
            'violet'  => 'border-violet-200 bg-violet-50 text-violet-700',
            'slate'   => 'border-slate-200 bg-slate-50 text-slate-600',
            default   => 'border-slate-200 bg-slate-50 text-slate-600',
        };
    }
}

if (!function_exists('lead_card_source_label')) {
    function lead_card_source_label(string $source, string $landingPage = ''): string
    {
        $landingPage = trim($landingPage);
        if ($landingPage !== '') {
            return $landingPage;
        }

        $source = strtolower(trim($source));

        return match ($source) {
            'website'                 => 'Website',
            'landing_page'            => 'Landing Page',
            'google'                  => 'Google',
            'google_ads'              => 'Google Ads',
            'google_business_profile' => 'Google Business',
            'facebook'                => 'Facebook',
            'facebook_ads'            => 'Facebook Ads',
            'instagram'               => 'Instagram',
            'meta'                    => 'Meta',
            'meta_lead_form'          => 'Meta Lead Form',
            'ringcentral'             => 'RingCentral',
            'referral'                => 'Referral',
            'walk_in'                 => 'Walk-In',
            'manual'                  => 'Manual',
            ''                        => 'Unknown',
            default                   => ucwords(str_replace('_', ' ', $source)),
        };
    }
}

if (!function_exists('lead_card_source_type_label')) {
    function lead_card_source_type_label(string $sourceType): string
    {
        $sourceType = strtolower(trim($sourceType));

        return match ($sourceType) {
            'organic_comment'   => 'Organic Comment',
            'organic_dm'        => 'Organic DM',
            'paid_ad'           => 'Paid Ad',
            'paid_dm'           => 'Paid DM',
            'ad_campaign'       => 'Ad Campaign',
            'meta_instant_form' => 'Meta Instant Form',
            'quiz_form'         => 'Quiz Form',
            'website_form'      => 'Website Form',
            'phone_call'        => 'Phone Call',
            'manual_entry'      => 'Manual Entry',
            ''                  => '',
            default             => ucwords(str_replace('_', ' ', $sourceType)),
        };
    }
}

if (!function_exists('lead_card_short_datetime')) {
    function lead_card_short_datetime(string $value): string
    {
        $ts = strtotime($value);
        if (!$ts) return '';

        $diff = time() - $ts;
        if ($diff >= 0 && $diff < 3600) {
            $mins = max(1, (int) floor($diff / 60));
            return $mins . 'm ago';
        }
        if ($diff >= 0 && $diff < 86400) {
            return (int) floor($diff / 3600) . 'h ago';
        }
        return date('M j', $ts);
    }
}

if (!function_exists('lead_card_appointment_datetime')) {
    function lead_card_appointment_datetime(string $value): string
    {
        $ts = strtotime($value);
        if (!$ts) return '';

        return date('M j, g:i A', $ts);
    }
}

$leadId = (int)($lead['id'] ?? 0);

$leadName = lead_card_value($lead, 'full_name');
if ($leadName === '') {
    $leadName = lead_card_value($lead, 'name', 'Unnamed Lead');
}

$leadPhone = lead_card_value($lead, 'phone');
$leadEmail = lead_card_value($lead, 'email');
$leadProcedure = lead_card_value($lead, 'procedure_interest');
$leadSource = lead_card_value($lead, 'source', 'manual');
$leadSourceMedium = lead_card_value($lead, 'source_medium');
$leadSourceType = lead_card_value($lead, 'source_type');
$leadLandingPage = lead_card_value($lead, 'landing_page');
$leadAssigned = lead_card_value($lead, 'assigned_to', '-');
$leadNotes = lead_card_value($lead, 'notes');
$leadCreated = lead_card_value($lead, 'created_at');
$leadConsult = lead_card_value($lead, 'consultation_status');
$leadConsultationDate = lead_card_value($lead, 'consultation_date');
$leadCampaign = lead_card_value($lead, 'campaign');
if ($leadCampaign === '') {
    $leadCampaign = lead_card_value($lead, 'campaign_name', '');
}
$leadSourceCampaign = lead_card_value($lead, 'source_campaign');
if ($leadSourceCampaign === '') {
    $leadSourceCampaign = $leadCampaign;
}
$leadSourceAdSet = lead_card_value($lead, 'source_ad_set');
$leadSourceAdName = lead_card_value($lead, 'source_ad_name');
$leadSourcePostId = lead_card_value($lead, 'source_post_id');
$leadSourcePostLabel = lead_card_value($lead, 'source_post_label');
$leadExternalLeadId = lead_card_value($lead, 'external_lead_id');
$leadInstagramUsername = lead_card_value($lead, 'instagram_username');
$leadTriggerKeyword = lead_card_value($lead, 'trigger_keyword');

$leadFinancingNeeded = lead_card_value($lead, 'financing_needed', 'unsure');
$leadFinancingOption = lead_card_value($lead, 'financing_option', 'none');
$leadValue = lead_card_value($lead, 'lead_value');
$leadLostReason = lead_card_value($lead, 'lost_reason');
$leadPreferredContact = lead_card_value($lead, 'preferred_contact');
$leadPreferredContactText = $leadPreferredContact !== '' ? $leadPreferredContact : 'Text';
$leadConsultText = $leadConsult !== '' ? $leadConsult : 'requested';
$leadInsuranceStatus = lead_card_value($lead, 'insurance_status');
$leadIntentType = lead_card_value($lead, 'intent_type');
$leadIntentionDisplay = $leadIntentType;
if ($leadIntentionDisplay === '' && $leadNotes !== '') {
    if (preg_match('/Intention:\s*([^\r\n;]+)/i', $leadNotes, $intentMatch)) {
        $leadIntentionDisplay = trim((string)($intentMatch[1] ?? ''));
    }
}

if (!function_exists('lead_card_initials')) {
    function lead_card_initials(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if ($name === '') return 'LD';

        $parts = explode(' ', $name);
        $first = mb_substr((string)($parts[0] ?? 'L'), 0, 1);
        $last = count($parts) > 1 ? mb_substr((string)($parts[count($parts) - 1] ?? ''), 0, 1) : '';
        $initials = strtoupper($first . $last);

        return $initials !== '' ? $initials : 'LD';
    }
}
$leadIntentionDisplay = trim(str_replace('_', ' ', $leadIntentionDisplay));
$leadIntentionDisplay = preg_replace('/\s+/', ' ', $leadIntentionDisplay);
$leadSmsOptStatus = lead_card_value($lead, 'sms_opt_status', 'unknown');
$leadLastContactedAt = lead_card_value($lead, 'last_contacted_at');
$leadLastInboundAt = lead_card_value($lead, 'last_inbound_at');
$leadLastOutboundAt = lead_card_value($lead, 'last_outbound_at');
$leadUnreadMessageCount = (int) lead_card_value($lead, 'unread_message_count', '0');
$leadNextFollowUpAt = lead_card_value($lead, 'next_follow_up_at');
$leadDateOfBirth = lead_card_value($lead, 'date_of_birth');
$leadSchedulingPreferredDay = lead_card_value($lead, 'scheduling_preferred_day');
$leadSchedulingPreferredTime = lead_card_value($lead, 'scheduling_preferred_time');
$leadFollowUpStatus = lead_card_value($lead, 'follow_up_status', 'not_checked');
$leadLastFollowUpCheckAt = lead_card_value($lead, 'last_follow_up_check_at');

$financingOptionLabels = function_exists('lead_financing_option_labels') ? lead_financing_option_labels() : [];
$leadFinancingOptionLabel = $financingOptionLabels[$leadFinancingOption]
    ?? ucfirst(str_replace('_', ' ', $leadFinancingOption));

$stageLabels = function_exists('lead_stage_labels') ? lead_stage_labels() : [];
$stageLabel = $stageLabels[$stageKey] ?? ucwords(str_replace('_', ' ', $stageKey));

$displaySource = lead_card_source_label($leadSource, $leadLandingPage);
$displaySourceType = lead_card_source_type_label($leadSourceType);
$displayValue = lead_card_money($leadValue);
$leadInitials = lead_card_initials($leadName);
$lastTouchLabel = $leadLastInboundAt !== ''
    ? 'Reply ' . lead_card_short_datetime($leadLastInboundAt)
    : ($leadLastOutboundAt !== '' ? 'Texted ' . lead_card_short_datetime($leadLastOutboundAt) : '');
$nextFollowUpLabel = $leadNextFollowUpAt !== '' ? 'Due ' . lead_card_short_datetime($leadNextFollowUpAt) : '';
$appointmentLabel = $leadConsultationDate !== '' ? lead_card_appointment_datetime($leadConsultationDate) : '';

$missingFields = [];
if ($leadName === '' || strtolower($leadName) === 'unnamed lead') $missingFields[] = 'Name';
if ($leadPhone === '') $missingFields[] = 'Phone';
if ($leadEmail === '') $missingFields[] = 'Email';
if ($leadProcedure === '') $missingFields[] = 'Service';
if ($leadPreferredContact === '') $missingFields[] = 'Preferred Contact';
if ($leadConsult === '') $missingFields[] = 'Consultation';

$missingCount = count($missingFields);
$isIncomplete = $missingCount > 0;

$contactLineParts = [];
if ($leadPhone !== '') {
    $contactLineParts[] = function_exists('format_phone_us') ? format_phone_us($leadPhone) : $leadPhone;
}
if ($leadEmail !== '') {
    $contactLineParts[] = $leadEmail;
}
$contactLine = implode(' / ', $contactLineParts);

$serviceLabel = $leadProcedure !== '' ? $leadProcedure : 'Service not set';

$createdLabel = '';
if ($leadCreated !== '') {
    if (function_exists('format_datetime')) {
        $createdLabel = format_datetime($leadCreated, 'M j');
    } else {
        $ts = strtotime($leadCreated);
        $createdLabel = $ts ? date('M j', $ts) : $leadCreated;
    }
}

$campaignShort = '';
if ($leadSourceCampaign !== '') {
    $campaignShort = mb_strlen($leadSourceCampaign) > 26 ? mb_substr($leadSourceCampaign, 0, 26) . '...' : $leadSourceCampaign;
}

$postShort = '';
if ($leadSourcePostLabel !== '') {
    $postShort = mb_strlen($leadSourcePostLabel) > 26 ? mb_substr($leadSourcePostLabel, 0, 26) . '...' : $leadSourcePostLabel;
}

$showAttributionDetails = (
    $leadTriggerKeyword !== ''
    || $campaignShort !== ''
    || $postShort !== ''
    || $leadInstagramUsername !== ''
    || strtolower($leadSource) === 'instagram'
    || strtolower($leadSource) === 'facebook'
    || strtolower($leadSource) === 'meta'
    || strtolower($leadSource) === 'meta_lead_form'
    || strtolower($leadSourceMedium) === 'paid'
    || strtolower($leadSourceMedium) === 'social'
);
?>

<div
    class="lead-card rounded-lg border <?= $isIncomplete ? 'border-amber-200' : 'border-slate-200' ?> bg-white p-2.5 shadow-sm transition hover:-translate-y-[1px] hover:border-blue-200 hover:shadow-md cursor-pointer"
    draggable="true"
    data-open-lead-modal="1"
    data-open-tab="communications"
    data-lead-id="<?= e((string)$leadId) ?>"
    data-stage-key="<?= e($stageKey) ?>"
    data-lead-name="<?= e($leadName) ?>"
    data-lead-phone="<?= e($leadPhone) ?>"
    data-lead-email="<?= e($leadEmail) ?>"
    data-lead-procedure="<?= e($leadProcedure) ?>"
    data-lead-source="<?= e($leadSource) ?>"
    data-lead-source-medium="<?= e($leadSourceMedium) ?>"
    data-lead-source-type="<?= e($leadSourceType) ?>"
    data-lead-landing-page="<?= e($leadLandingPage) ?>"
    data-lead-assigned="<?= e($leadAssigned) ?>"
    data-lead-notes="<?= e($leadNotes) ?>"
    data-lead-created="<?= e($leadCreated) ?>"
    data-lead-consult="<?= e($leadConsult) ?>"
    data-lead-consultation-date="<?= e($leadConsultationDate) ?>"
    data-lead-campaign="<?= e($leadCampaign) ?>"
    data-lead-source-campaign="<?= e($leadSourceCampaign) ?>"
    data-lead-source-ad-set="<?= e($leadSourceAdSet) ?>"
    data-lead-source-ad-name="<?= e($leadSourceAdName) ?>"
    data-lead-source-post-id="<?= e($leadSourcePostId) ?>"
    data-lead-source-post-label="<?= e($leadSourcePostLabel) ?>"
    data-lead-financing-needed="<?= e($leadFinancingNeeded) ?>"
    data-lead-financing-option="<?= e($leadFinancingOption) ?>"
    data-lead-financing-option-label="<?= e($leadFinancingOptionLabel) ?>"
    data-lead-stage-label="<?= e($stageLabel) ?>"
    data-lead-value="<?= e($leadValue) ?>"
    data-lead-lost-reason="<?= e($leadLostReason) ?>"
    data-lead-preferred-contact="<?= e($leadPreferredContact) ?>"
    data-lead-insurance-status="<?= e($leadInsuranceStatus) ?>"
    data-lead-external-lead-id="<?= e($leadExternalLeadId) ?>"
    data-lead-intent-type="<?= e($leadIntentType) ?>"
    data-lead-instagram-username="<?= e($leadInstagramUsername) ?>"
    data-lead-trigger-keyword="<?= e($leadTriggerKeyword) ?>"
    data-lead-sms-opt-status="<?= e($leadSmsOptStatus) ?>"
    data-lead-last-contacted-at="<?= e($leadLastContactedAt) ?>"
    data-lead-last-inbound-at="<?= e($leadLastInboundAt) ?>"
    data-lead-last-outbound-at="<?= e($leadLastOutboundAt) ?>"
    data-lead-unread-message-count="<?= e((string)$leadUnreadMessageCount) ?>"
    data-lead-next-follow-up-at="<?= e($leadNextFollowUpAt) ?>"
    data-lead-date-of-birth="<?= e($leadDateOfBirth) ?>"
    data-lead-scheduling-preferred-day="<?= e($leadSchedulingPreferredDay) ?>"
    data-lead-scheduling-preferred-time="<?= e($leadSchedulingPreferredTime) ?>"
    data-lead-follow-up-status="<?= e($leadFollowUpStatus) ?>"
    data-lead-last-follow-up-check-at="<?= e($leadLastFollowUpCheckAt) ?>"
>
    <div class="border-b border-slate-100 pb-2">
        <div class="flex min-w-0 items-start gap-2">
            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-100 to-emerald-100 text-[11px] font-bold text-slate-700 ring-1 ring-slate-200">
                <?= e($leadInitials) ?>
            </span>
            <div class="min-w-0">
                <p class="text-[14px] font-semibold leading-5 text-slate-950"><?= e($leadName) ?></p>
                <?php if ($contactLine !== ''): ?>
                    <p class="mt-0.5 truncate text-[11px] text-slate-500"><?= e($contactLine) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mt-2.5 space-y-2 text-[12px]">
        <div class="flex items-center gap-2 text-slate-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-[14px] w-[14px] shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3H4a1 1 0 0 0-1 1v5.59A2 2 0 0 0 3.59 11l9.58 9.59a2 2 0 0 0 2.83 0l4.59-4.59a2 2 0 0 0 0-2.83Z"></path>
                <path d="M7 7h.01"></path>
            </svg>
            <span class="truncate"><?= e($displaySource) ?></span>
        </div>

        <?php if ($leadIntentionDisplay !== ''): ?>
            <div class="flex items-center gap-2 text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-[14px] w-[14px] shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v20"></path>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14.5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
                <span class="truncate"><?= e(ucwords(strtolower($leadIntentionDisplay))) ?></span>
            </div>
        <?php endif; ?>

        <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
            <span class="rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-600"><?= e($leadPreferredContactText) ?></span>
            <span class="rounded-md border border-blue-100 bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700"><?= e((string)(function_exists('elite_consultation_status_label') ? elite_consultation_status_label($leadConsultText) : ucfirst(str_replace('_', ' ', $leadConsultText)))) ?></span>
            <span class="lead-card-value-preview ml-auto rounded-md border border-emerald-100 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
                <span data-role="lead-card-value-text"><?= e($displayValue) ?></span>
            </span>
        </div>
    </div>

    <div class="mt-3 hidden rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
        <p class="text-[10px] uppercase tracking-[0.14em] text-slate-400">Service Needed</p>
        <p class="mt-1 truncate text-sm font-medium text-slate-800"><?= e($serviceLabel) ?></p>
    </div>

    <?php if ($appointmentLabel !== ''): ?>
        <div class="lead-card-appointment-preview mt-2 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5">
            <p class="truncate text-[11px] font-semibold text-emerald-800">Appt: <?= e($appointmentLabel) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($leadUnreadMessageCount > 0 || $isIncomplete || $nextFollowUpLabel !== '' || $leadSmsOptStatus === 'opted_out'): ?>
        <div class="mt-2 flex flex-wrap items-center gap-1.5">
            <?php if ($nextFollowUpLabel !== ''): ?>
                <span class="rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700"><?= e($nextFollowUpLabel) ?></span>
            <?php endif; ?>
            <?php if ($leadSmsOptStatus === 'opted_out'): ?>
                <span class="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700">DND</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="mt-2 flex justify-end gap-1 text-slate-500">
        <button type="button" class="lead-open-modal relative inline-flex h-7 w-7 items-center justify-center rounded-md transition hover:bg-slate-100 hover:text-blue-700" data-open-lead-modal="1" data-open-tab="communications" title="Messages" aria-label="Open messages">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
            </svg>
            <?php if ($leadUnreadMessageCount > 0): ?>
                <span class="lead-unread-badge absolute -right-1 -top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-blue-600 px-1 text-[9px] font-bold text-white"><?= e((string)$leadUnreadMessageCount) ?></span>
            <?php endif; ?>
        </button>

        <button type="button" class="lead-open-modal inline-flex h-7 w-7 items-center justify-center rounded-md transition hover:bg-slate-100 hover:text-blue-700" data-open-lead-modal="1" data-open-tab="communications" title="Text or call" aria-label="Open text or call">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.86 19.86 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.91.32 1.8.59 2.65a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.43-1.16a2 2 0 0 1 2.11-.45c.85.27 1.74.47 2.65.59A2 2 0 0 1 22 16.92Z"></path>
            </svg>
        </button>

        <button type="button" class="lead-open-modal relative inline-flex h-7 w-7 items-center justify-center rounded-md transition hover:bg-slate-100 hover:text-blue-700" data-open-lead-modal="1" data-open-tab="details" title="Details" aria-label="Open details">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3H4a1 1 0 0 0-1 1v5.59A2 2 0 0 0 3.59 11l9.58 9.59a2 2 0 0 0 2.83 0l4.59-4.59a2 2 0 0 0 0-2.83Z"></path>
                <path d="M7 7h.01"></path>
            </svg>
            <?php if ($isIncomplete): ?>
                <span class="absolute -right-1 -top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[9px] font-bold text-white"><?= e((string)$missingCount) ?></span>
            <?php endif; ?>
        </button>

        <button type="button" class="lead-open-modal inline-flex h-7 w-7 items-center justify-center rounded-md transition hover:bg-slate-100 hover:text-blue-700" data-open-lead-modal="1" data-open-tab="notes" title="Notes" aria-label="Open notes">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                <path d="M16 13H8"></path>
                <path d="M16 17H8"></path>
            </svg>
        </button>
    </div>
</div>
