<?php

declare(strict_types=1);



/**

 * Elite Smiles CRM

 * File: /app/partials/dashboard_pipeline.php

 *

 * Expected variables:

 * - $stageMap (array)

 * - $pipelineCounts (array)

 * - $pipelineRows (array)

 */



$stageMap = $stageMap ?? [];

$legacyStageMap = function_exists('lead_stage_map_ordered') ? lead_stage_map_ordered() : $stageMap;

$pipelineCounts = $pipelineCounts ?? [];

$pipelineRows = $pipelineRows ?? [];
$defaultMobileStageFilter = key($stageMap) ?: 'new_lead';

$lostReasonOptions = function_exists('lead_lost_reason_options') ? lead_lost_reason_options() : [];

$financingNeededOptions = function_exists('lead_financing_needed_options') ? lead_financing_needed_options() : [];

$financingOptionLabels = function_exists('lead_financing_option_labels') ? lead_financing_option_labels() : [];

require_once dirname(__DIR__) . '/leads/lead_playbooks.php';

$smsTemplateOptions = function_exists('lead_playbook_sms_templates') ? lead_playbook_sms_templates() : [];
$schedulingQuestions = function_exists('lead_playbook_scheduling_questions') ? lead_playbook_scheduling_questions() : [];


$serviceNeededOptions = [

    'All-on-X',

    'Veneers',

    'Implants',

    'Invisalign',

    'Teeth Whitening',

    'Smile Makeover',

    'Emergency Visit',

    'Consultation',

    'Cleaning',

    'Root Canal',

    'Crown / Bridge',

    'Dentures',

    'Other',

];



$preferredContactOptions = [

    'call' => 'Call',

    'text' => 'Text',

    'email' => 'Email',

    'instagram_dm' => 'Instagram DM',

    'facebook_message' => 'Facebook Message',

    'whatsapp' => 'WhatsApp',

];



$sourceOptions = [

    'manual' => 'Manual',

    'website' => 'Website',

    'landing_page' => 'Landing Page',

    'google' => 'Google',

    'google_ads' => 'Google Ads',

    'facebook' => 'Facebook',

    'instagram' => 'Instagram',

    'meta_lead_form' => 'Meta Lead Form',

    'ringcentral' => 'RingCentral',

    'referral' => 'Referral',

    'walk_in' => 'Walk-In',

];



$consultationOptions = [

    '' => 'Not set',

    'requested' => 'Requested',

    'scheduled' => 'Scheduled',

    'completed' => 'Completed',

    'no_show' => 'No Show',

    'not_interested' => 'Not Interested',

];

?>



<section class="mb-8">

    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">

        <div class="mb-5 flex items-center justify-between gap-3">

            <div>

                <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Lead Flow</p>

                <h3 class="mt-2 text-xl font-semibold text-slate-900">Pipeline Board</h3>

            </div>



            <div class="flex items-center gap-3">

                <div class="relative">
                    <button
                        type="button"
                        id="pipeline-notifications-button"
                        class="relative inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-blue-300 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        aria-haspopup="true"
                        aria-expanded="false"
                        title="Pipeline notifications"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.27 21a2 2 0 0 0 3.46 0"></path>
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                        </svg>
                        <span id="pipeline-notifications-count" class="hidden absolute -right-2 -top-2 h-6 min-w-6 items-center justify-center rounded-full bg-blue-600 px-1.5 text-[11px] font-bold text-white shadow-sm">0</span>
                    </button>

                    <div
                        id="pipeline-notifications-menu"
                        class="hidden absolute right-0 top-13 z-40 w-80 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/15"
                    >
                        <div class="border-b border-slate-100 px-4 py-3">
                            <p class="text-sm font-semibold text-slate-900">Pipeline notifications</p>
                            <p class="mt-1 text-xs text-slate-500">New communications and new leads.</p>
                        </div>
                        <div id="pipeline-notifications-list" class="max-h-96 overflow-y-auto p-2"></div>
                    </div>
                </div>
                <button
                    type="button"
                    id="open-new-lead-modal"
                    class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                >
                    + New Lead
                </button>

                <button
                    type="button"
                    id="open-import-leads-picker"
                    class="inline-flex items-center justify-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100"
                >
                    Import Leads
                </button>
                <input
                    type="file"
                    id="import-leads-file"
                    class="hidden"
                    accept=".csv,.txt"
                >

                <button
                    type="button"
                    id="pipeline-calendar-button"
                    class="inline-flex items-center justify-center rounded-2xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-800 transition hover:bg-blue-100"
                    title="View appointments calendar"
                >
                    Calendar
                </button>

                <button
                    type="button"
                    id="run-followup-check"
                    class="inline-flex items-center justify-center rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 transition hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Check Follow-Ups
                </button>

                <div class="w-full rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 sm:hidden">
                    <label for="pipeline-mobile-stage-filter" class="mr-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Mobile stage list</label>
                    <select
                        id="pipeline-mobile-stage-filter"
                        class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700 outline-none"
                    >
                        <option value="__all__">All stages</option>
                        <?php foreach ($stageMap as $stageFilterKey => $stageFilterLabel): ?>
                            <option value="<?= e((string)$stageFilterKey) ?>" <?= ((string)$stageFilterKey === (string)$defaultMobileStageFilter) ? 'selected' : '' ?>>
                                <?= e((string)$stageFilterLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-600">

                    Fixed board height + drag auto-scroll

                </div>

                <p id="import-leads-status" class="w-full text-xs text-slate-500"></p>

            </div>

        </div>



        <div
            id="pipeline-calendar-overlay"
            class="hidden fixed inset-0 z-50 bg-slate-900/50 p-3 backdrop-blur-sm"
        >
            <div class="mx-auto mt-12 h-[calc(100vh-5rem)] w-[min(96vw,1100px)] rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-2xl sm:p-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Scheduling overview</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">Appointment Calendar</h3>
                        <p id="pipeline-calendar-subtitle" class="mt-1 text-sm text-slate-500">Showing appointments by the selected view.</p>
                    </div>
                    <button
                        type="button"
                        id="pipeline-calendar-close"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-100"
                    >
                        Close
                    </button>
                </div>

                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="inline-flex rounded-xl border border-slate-200 bg-white">
                        <button
                            type="button"
                            data-calendar-view="day"
                            class="pipeline-calendar-view-btn rounded-l-xl px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                        >
                            Day
                        </button>
                        <button
                            type="button"
                            data-calendar-view="week"
                            class="pipeline-calendar-view-btn px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 border-x border-slate-200"
                        >
                            Week
                        </button>
                        <button
                            type="button"
                            data-calendar-view="month"
                            class="pipeline-calendar-view-btn rounded-r-xl px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100"
                        >
                            Month
                        </button>
                    </div>

                    <div class="inline-flex items-center gap-2">
                        <button
                            type="button"
                            id="pipeline-calendar-prev"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-100"
                        >
                            ←
                        </button>
                        <button
                            type="button"
                            id="pipeline-calendar-today"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-100"
                        >
                            Today
                        </button>
                        <button
                            type="button"
                            id="pipeline-calendar-next"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-100"
                        >
                            →
                        </button>
                    </div>
                </div>

                <p id="pipeline-calendar-range" class="text-sm font-semibold text-slate-900"></p>
                <p class="mt-1 text-xs text-slate-500">Open hours shown: 8:00 AM - 7:00 PM. Other times are marked as Emergency slots.</p>

                <div
                    id="pipeline-calendar-view"
                    class="mt-3 h-[calc(100%-8rem)] overflow-auto rounded-xl border border-slate-200 bg-slate-50 p-3"
                ></div>
            </div>
        </div>

        <?php if (empty($stageMap)): ?>
            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">

                <p class="text-lg font-medium text-slate-900">No stages available</p>

                <p class="mt-2 text-sm text-slate-500">The lead stage map is empty.</p>

            </div>

        <?php else: ?>

            <div

                id="pipeline-board-viewport"

                class="overflow-x-hidden overflow-y-hidden rounded-[1.5rem] border border-slate-200 bg-slate-50/50 pb-3 md:overflow-x-auto"

            >

                <div

                    id="lead-pipeline-board"

                    class="pipeline-board-layout flex min-w-0 flex-wrap items-start gap-4 p-4 md:min-w-[1500px] md:flex-nowrap"

                >

                    <?php foreach ($stageMap as $stageKey => $stageLabel): ?>

                        <?php $rows = $pipelineRows[$stageKey] ?? []; ?>
                        <?php
                            $legacyDropStageKey = function_exists('lead_conversion_stage_legacy_target')
                                ? lead_conversion_stage_legacy_target((string)$stageKey)
                                : (string)$stageKey;
                            $legacyDropStageLabel = $legacyStageMap[$legacyDropStageKey]
                                ?? ucwords(str_replace('_', ' ', $legacyDropStageKey));
                            $stageBadgeClass = function_exists('lead_conversion_stage_badge_class')
                                ? lead_conversion_stage_badge_class((string)$stageKey)
                                : lead_stage_badge_class($legacyDropStageKey);
                        ?>



                        <div

                            class="pipeline-column flex h-[560px] w-full shrink-0 flex-col rounded-[1.5rem] border border-slate-200 bg-slate-50/80 p-3 transition md:w-[300px]"

                            data-stage-key="<?= e($legacyDropStageKey) ?>"

                            data-stage-label="<?= e($legacyDropStageLabel) ?>"
                            data-display-stage-key="<?= e($stageKey) ?>"
                            data-display-stage-label="<?= e($stageLabel) ?>"

                        >

                            <div class="mb-3 flex items-center justify-between gap-3">

                                <div>

                                    <h4 class="text-sm font-semibold text-slate-900"><?= e($stageLabel) ?></h4>

                                    <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">

                                        <span class="pipeline-count" data-count-for="<?= e($stageKey) ?>">

                                            <?= e((string)($pipelineCounts[$stageKey] ?? 0)) ?>

                                        </span>

                                        lead<?= ((int)($pipelineCounts[$stageKey] ?? 0) === 1 ? '' : 's') ?>

                                    </p>

                                </div>



                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-medium <?= e($stageBadgeClass) ?>" title="Display stage. Drag/drop still saves as <?= e($legacyDropStageLabel) ?>.">

                                    <?= e($stageLabel) ?>

                                </span>

                            </div>



                            <div

                                class="pipeline-dropzone min-h-0 flex-1 space-y-3 overflow-y-auto pr-1"

                                data-dropzone="<?= e($stageKey) ?>"

                            >

                                <?php if (!empty($rows)): ?>

                                    <?php foreach ($rows as $lead): ?>

                                        <?php require __DIR__ . '/lead_card.php'; ?>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <div class="empty-state rounded-[1.25rem] border border-dashed border-slate-300 bg-white/70 p-5 text-center">

                                        <p class="text-sm font-medium text-slate-700">No leads here</p>

                                        <p class="mt-1 text-xs text-slate-500">Drop a lead here.</p>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php endif; ?>

    </div>

</section>



<!-- New Lead Modal -->

<div

    id="new-lead-modal"

    class="fixed inset-0 z-50 hidden bg-slate-900/50"
    aria-hidden="true"

>

    <div class="flex min-h-screen items-start justify-center overflow-y-auto p-3 sm:p-4 lg:p-6">
        <div class="my-4 flex max-h-[92vh] w-full max-w-4xl flex-col rounded-[2rem] border border-slate-200 bg-white shadow-2xl">

            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                <div>

                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Quick Intake</p>

                    <h3 class="mt-2 text-2xl font-semibold text-slate-900">Create New Lead</h3>

                    <p class="mt-1 text-sm text-slate-500">Fast intake for website, landing pages, Meta, Google, calls, walk-ins, RingCentral, and manual follow-up.</p>

                </div>



                <button

                    type="button"

                    id="new-lead-close"

                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-600 transition hover:bg-slate-100"

                    aria-label="Close"

                >

                    x

                </button>

            </div>



            <div class="min-h-0 flex-1 overflow-y-scroll px-6 py-5 pb-24">
                <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">

                    <div class="space-y-5">

                        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4">

                            <div class="mb-3">

                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Identity</p>

                            </div>



                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                                <div class="rounded-2xl bg-white px-4 py-4">

                                    <label for="new-lead-full-name" class="text-xs uppercase tracking-[0.18em] text-slate-400">Full Name</label>

                                    <input

                                        type="text"

                                        id="new-lead-full-name"

                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                        placeholder="Patient name"

                                    >

                                </div>



                                <div class="rounded-2xl bg-white px-4 py-4">

                                    <label for="new-lead-phone" class="text-xs uppercase tracking-[0.18em] text-slate-400">Phone</label>

                                    <input

                                        type="text"

                                        id="new-lead-phone"

                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                        placeholder="Phone number"

                                    >

                                </div>



                                <div class="rounded-2xl bg-white px-4 py-4">

                                    <label for="new-lead-email" class="text-xs uppercase tracking-[0.18em] text-slate-400">Email</label>

                                    <input

                                        type="email"

                                        id="new-lead-email"

                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                        placeholder="Email address"

                                    >

                                </div>



                                <div class="rounded-2xl bg-white px-4 py-4">

                                    <label for="new-lead-preferred-contact" class="text-xs uppercase tracking-[0.18em] text-slate-400">Preferred Contact</label>

                                    <select

                                        id="new-lead-preferred-contact"

                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                    >

                                        <option value="">Not set</option>

                                        <?php foreach ($preferredContactOptions as $optionKey => $optionLabel): ?>

                                            <option value="<?= e($optionKey) ?>"><?= e($optionLabel) ?></option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>

                        </div>



                        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4">

                            <div class="mb-3">

                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Treatment & Qualification</p>

                            </div>



                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                                <div class="rounded-2xl bg-white px-4 py-4">

                                    <label for="new-lead-procedure-interest" class="text-xs uppercase tracking-[0.18em] text-slate-400">Service Needed</label>

                                    <select

                                        id="new-lead-procedure-interest"

                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                    >

                                        <option value="">Select service</option>

                                        <?php foreach ($serviceNeededOptions as $serviceOption): ?>

                                            <option value="<?= e($serviceOption) ?>"><?= e($serviceOption) ?></option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>



                                <div class="rounded-2xl bg-white px-4 py-4">

                                    <label for="new-lead-consult-status" class="text-xs uppercase tracking-[0.18em] text-slate-400">Consultation Status</label>

                                    <select

                                        id="new-lead-consult-status"

                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                    >

                                        <?php foreach ($consultationOptions as $optionKey => $optionLabel): ?>

                                            <option value="<?= e($optionKey) ?>"><?= e($optionLabel) ?></option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>



                                <div class="rounded-2xl bg-white px-4 py-4">

                                    <label for="new-lead-consultation-date" class="text-xs uppercase tracking-[0.18em] text-slate-400">Scheduled Consultation</label>

                                    <input

                                        type="datetime-local"

                                        id="new-lead-consultation-date"

                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                    >

                                </div>



                                <div class="rounded-2xl bg-white px-4 py-4">

                                    <label for="new-lead-financing-needed" class="text-xs uppercase tracking-[0.18em] text-slate-400">Financing Needed</label>
                                    <select

                                        id="new-lead-financing-needed"

                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                    >

                                        <?php foreach ($financingNeededOptions as $optionKey => $optionLabel): ?>

                                            <option value="<?= e($optionKey) ?>" <?= $optionKey === 'unsure' ? 'selected' : '' ?>><?= e($optionLabel) ?></option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>



                                <div class="rounded-2xl bg-white px-4 py-4">

                                    <label for="new-lead-financing-option" class="text-xs uppercase tracking-[0.18em] text-slate-400">Financing Option</label>

                                    <select

                                        id="new-lead-financing-option"

                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                    >

                                        <?php foreach ($financingOptionLabels as $optionKey => $optionLabel): ?>

                                            <option value="<?= e($optionKey) ?>" <?= $optionKey === 'none' ? 'selected' : '' ?>><?= e($optionLabel) ?></option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>



                                <div class="rounded-2xl bg-white px-4 py-4 md:col-span-2">

                                    <label for="new-lead-notes" class="text-xs uppercase tracking-[0.18em] text-slate-400">Notes</label>

                                    <textarea

                                        id="new-lead-notes"

                                        rows="4"

                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm leading-6 outline-none"

                                        placeholder="Quick notes from phone call, treatment goals, objections, timing..."

                                    ></textarea>

                                </div>

                            </div>

                        </div>

                    </div>



                    <div class="space-y-5">

                        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4">

                            <div class="mb-3">

                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Source & Attribution</p>

                            </div>



                            <div class="space-y-4">

                                <div class="rounded-2xl bg-white px-4 py-4">

                                    <label for="new-lead-source" class="text-xs uppercase tracking-[0.18em] text-slate-400">Source</label>

                                    <select

                                        id="new-lead-source"

                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                    >

                                        <?php foreach ($sourceOptions as $optionKey => $optionLabel): ?>

                                            <option value="<?= e($optionKey) ?>" <?= $optionKey === 'manual' ? 'selected' : '' ?>><?= e($optionLabel) ?></option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>



                                <div class="rounded-2xl bg-white px-4 py-4">

                                    <label for="new-lead-landing-page" class="text-xs uppercase tracking-[0.18em] text-slate-400">Landing Page</label>

                                    <input

                                        type="text"

                                        id="new-lead-landing-page"

                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                        placeholder="Page slug or URL"

                                    >

                                </div>



                                <div class="rounded-2xl bg-white px-4 py-4">

                                    <label for="new-lead-campaign" class="text-xs uppercase tracking-[0.18em] text-slate-400">Campaign</label>

                                    <input

                                        type="text"

                                        id="new-lead-campaign"

                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                        placeholder="Campaign name"

                                    >

                                </div>

                            </div>

                        </div>



                        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4">

                            <div class="mb-3">

                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Workflow</p>

                            </div>



                            <div class="space-y-4">

                                <div class="rounded-2xl bg-white px-4 py-4">

                                    <label for="new-lead-stage" class="text-xs uppercase tracking-[0.18em] text-slate-400">Stage</label>

                                    <select

                                        id="new-lead-stage"

                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                    >

                                        <?php foreach ($legacyStageMap as $stageKey => $stageLabel): ?>

                                            <option value="<?= e($stageKey) ?>" <?= $stageKey === 'new_lead' ? 'selected' : '' ?>><?= e($stageLabel) ?></option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>



                                <div class="rounded-2xl bg-white px-4 py-4">

                                    <label for="new-lead-value" class="text-xs uppercase tracking-[0.18em] text-slate-400">Lead Value</label>

                                    <input

                                        type="number"

                                        step="0.01"

                                        id="new-lead-value"

                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                        value="10000"

                                    >

                                </div>

                            </div>

                        </div>



                        <div class="rounded-[1.5rem] border border-blue-200 bg-blue-50 px-4 py-4">

                            <p class="text-xs uppercase tracking-[0.18em] text-blue-700">Intake Philosophy</p>

                            <p class="mt-2 text-sm text-blue-900">

                                Capture the lead fast first. Complete missing details inside the workspace after intake.

                            </p>

                        </div>

                    </div>

                </div>

            </div>



            <div class="border-t border-slate-200 px-6 py-5">

                <div class="flex flex-wrap items-center gap-3">

                    <button

                        type="button"

                        id="new-lead-save"

                        class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"

                    >

                        Create Lead

                    </button>



                    <button

                        type="button"

                        id="new-lead-cancel"

                        class="rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-medium text-slate-700"

                    >

                        Cancel

                    </button>

                </div>



                <p id="new-lead-status" class="mt-3 text-xs text-slate-500"></p>

            </div>

        </div>

    </div>

</div>



<!-- Lead Workspace Modal -->

<div

    id="lead-detail-modal"

    class="fixed inset-0 z-50 hidden bg-slate-100"

    aria-hidden="true"

>

    <div class="h-screen overflow-hidden">
        <div class="flex h-screen w-full flex-col bg-white">
            <div id="lead-detail-header" class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 bg-white px-6 py-3 shadow-sm">

                <div class="flex flex-wrap items-end gap-x-6 gap-y-2">
                    <div>

                        <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Lead Workspace</p>

                        <h3 id="modal-lead-name" class="mt-1 text-xl font-semibold text-slate-900">Lead</h3>

                        <p id="modal-lead-stage" class="mt-0.5 text-xs text-slate-500">Stage</p>
                    </div>

                    <nav class="flex items-center gap-4 pb-0.5" aria-label="Lead workspace sections">
                        <button type="button" class="workspace-tab-button text-sm font-semibold text-slate-900" data-tab-target="details">Contact Details</button>
                        <button type="button" class="workspace-tab-button text-sm font-semibold text-slate-500" data-tab-target="communications">Communication</button>
                        <button type="button" class="workspace-tab-button text-sm font-semibold text-slate-500" data-tab-target="notes">Notes</button>
                    </nav>

                </div>



                <div class="flex items-center gap-2">

                    <button

                        type="button"

                        id="workspace-ai-button"

                        class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"

                        aria-label="Open Elite AI"

                        title="Open Elite AI"

                        onclick="if (window.eliteAiSetOpen) { window.eliteAiSetOpen(true); } return false;"

                    >

                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">

                            <path d="M12 3l1.8 4.2L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9l4.2-1.8L12 3z"></path>

                            <path d="M5 18l.8 1.9L8 21l-2.2 1.1L5 24l-.8-1.9L2 21l2.2-1.1L5 18z"></path>

                        </svg>

                        <span>Elite AI</span>

                    </button>

                    <button

                        type="button"

                        id="workspace-save-main"

                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700 transition hover:bg-emerald-100"

                        aria-label="Save changes"

                        title="Save changes"

                    >

                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">

                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"></path>

                            <path d="M17 21v-8H7v8"></path>

                            <path d="M7 3v5h8"></path>

                        </svg>

                    </button>

                    <button

                        type="button"

                        id="lead-delete-button"

                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-red-200 bg-red-50 text-red-600 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60"

                        aria-label="Delete lead"

                        title="Delete lead"

                    >

                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">

                            <path d="M3 6h18"></path>

                            <path d="M8 6V4.5A1.5 1.5 0 0 1 9.5 3h5A1.5 1.5 0 0 1 16 4.5V6"></path>

                            <path d="M19 6l-1 13a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>

                            <path d="M10 11v6"></path>

                            <path d="M14 11v6"></path>

                        </svg>

                    </button>



                    <button

                        type="button"

                        id="lead-detail-close"

                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-600 transition hover:bg-slate-100"

                        aria-label="Close"

                    >

                        x

                    </button>

                </div>

            </div>



            <div id="lead-detail-body" class="min-h-0 flex-1 overflow-hidden px-4 py-4">
                <div

                    id="modal-missing-panel"

                    class="mb-5 hidden rounded-[1.5rem] border border-amber-200 bg-amber-50 px-4 py-4"

                >

                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">

                        <div>

                            <p class="text-xs uppercase tracking-[0.18em] text-amber-700">Complete Lead</p>

                            <p class="mt-1 text-sm font-medium text-amber-900">This lead is missing important intake details.</p>

                            <div id="modal-missing-list" class="mt-2 flex flex-wrap items-center gap-2 text-xs text-amber-800">Missing fields</div>
                        </div>



                        <span class="inline-flex rounded-full border border-amber-200 bg-white px-3 py-1 text-xs font-semibold text-amber-700">

                            Needs Completion

                        </span>

                    </div>

                </div>



                <div class="mb-3 hidden flex-wrap items-center gap-2 border-b border-slate-200 pb-3">

                    <button

                        type="button"

                        class="workspace-tab-button inline-flex items-center justify-center rounded-full border border-slate-900 bg-slate-900 px-3.5 py-1.5 text-sm font-medium text-white"

                        data-tab-target="details"

                    >

                        Contact Details
                    </button>



                    <button

                        type="button"

                        class="workspace-tab-button inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-600"
                        data-tab-target="communications"
                    >

                        Communication
                    </button>



                    <button

                        type="button"

                        class="workspace-tab-button hidden"
                        data-tab-target="notes"
                    >

                        Notes
                    </button>

                </div>



                <div class="hidden mb-4 rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm text-slate-600 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Selected Lead</p>
                            <p id="modal-sms-lead-name" class="mt-1 text-lg font-semibold text-slate-900">Lead</p>
                            <p id="modal-sms-lead-phone" class="mt-1 text-slate-500">No phone selected</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" data-composer-mode="sms" class="composer-mode-button rounded-full border border-slate-900 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">SMS</button>
                            <button type="button" data-composer-mode="email" class="composer-mode-button rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600">Email</button>
                            <button type="button" data-composer-mode="note" class="composer-mode-button rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600">Note</button>
                        </div>
                    </div>

                    <p id="modal-sms-opt-status" class="mt-3 inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">SMS status unknown</p>
                </div>

                <div id="workspace-tab-details" class="workspace-tab-panel">

                    <div class="mb-5 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
                        <a href="#lead-detail-contact-section" data-detail-window-target="contact" class="lead-detail-window-button rounded-xl border border-slate-900 bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Contact</a>
                        <a href="#lead-detail-opportunity-section" data-detail-window-target="opportunity" class="lead-detail-window-button rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Opportunity</a>
                        <a href="#lead-detail-appointment-section" data-detail-window-target="appointment" class="lead-detail-window-button rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Appointment</a>
                        <a href="#lead-detail-tasks-section" data-detail-window-target="tasks" class="lead-detail-window-button rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Tasks</a>
                        <a href="#lead-detail-source-section" data-detail-window-target="source" class="lead-detail-window-button rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Source</a>
                        <a href="#lead-detail-workflow-section" data-detail-window-target="workflow" class="lead-detail-window-button rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Workflow</a>
                    </div>
                    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">

                        <div class="space-y-5">

                            <div id="lead-detail-contact-section" data-detail-window="contact" class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4">
                                <div class="mb-3">

                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Identity</p>

                                </div>



                                <div class="space-y-4">

                                    <div id="wrap-modal-lead-name-input" class="rounded-2xl bg-white px-4 py-4">

                                        <label for="modal-lead-name-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Full Name</label>

                                        <input

                                            type="text"

                                            id="modal-lead-name-input"

                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                            placeholder="Patient name"

                                        >

                                    </div>



                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                                        <div id="wrap-modal-lead-phone-input" class="rounded-2xl bg-white px-4 py-4">

                                            <label for="modal-lead-phone-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Phone</label>

                                            <input

                                                type="text"

                                                id="modal-lead-phone-input"

                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                                placeholder="Phone number"

                                            >

                                        </div>



                                        <div id="wrap-modal-lead-email-input" class="rounded-2xl bg-white px-4 py-4">

                                            <label for="modal-lead-email-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Email</label>

                                            <input

                                                type="email"

                                                id="modal-lead-email-input"

                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                                placeholder="Email address"

                                            >

                                        </div>

                                    </div>



                                    <div id="wrap-modal-lead-preferred-contact-input" class="rounded-2xl bg-white px-4 py-4">
                                        <label for="modal-lead-preferred-contact-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Preferred Contact</label>
                                        <select

                                            id="modal-lead-preferred-contact-input"

                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                        >

                                            <option value="">Not set</option>

                                            <?php foreach ($preferredContactOptions as $optionKey => $optionLabel): ?>

                                                <option value="<?= e($optionKey) ?>"><?= e($optionLabel) ?></option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>

                                </div>

                            </div>



                                    <div data-detail-window="contact" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="rounded-2xl bg-white px-4 py-4">
                                            <label for="modal-lead-dob-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Date of Birth</label>
                                            <input type="date" id="modal-lead-dob-input" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none">
                                        </div>

                                        <div class="rounded-2xl bg-white px-4 py-4">
                                            <label for="modal-lead-intent-type-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Intention</label>
                                            <input
                                                type="text"
                                                id="modal-lead-intent-type-input"
                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"
                                                placeholder="Treatment intent or lead goal"
                                            >
                                        </div>
                                    </div>

                            <div id="lead-detail-opportunity-section" data-detail-window="opportunity" class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4 hidden">
                                <div class="mb-3">

                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Treatment & Qualification</p>

                                </div>



                                <div class="space-y-4">

                                    <div id="wrap-modal-lead-procedure-input" class="rounded-2xl bg-white px-4 py-4">

                                        <label for="modal-lead-procedure-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Service Needed</label>

                                        <select

                                            id="modal-lead-procedure-input"

                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                        >

                                            <option value="">Select service</option>

                                            <?php foreach ($serviceNeededOptions as $serviceOption): ?>

                                                <option value="<?= e($serviceOption) ?>"><?= e($serviceOption) ?></option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>



                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                                        <div id="wrap-modal-lead-financing-needed-input" class="rounded-2xl bg-white px-4 py-4">

                                            <label for="modal-lead-financing-needed-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Financing Needed</label>

                                            <select

                                                id="modal-lead-financing-needed-input"

                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                            >

                                                <?php foreach ($financingNeededOptions as $optionKey => $optionLabel): ?>

                                                    <option value="<?= e($optionKey) ?>"><?= e($optionLabel) ?></option>

                                                <?php endforeach; ?>

                                            </select>

                                        </div>



                                        <div id="wrap-modal-lead-financing-option-input" class="rounded-2xl bg-white px-4 py-4">

                                            <label for="modal-lead-financing-option-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Financing Option</label>

                                            <select

                                                id="modal-lead-financing-option-input"

                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                            >

                                                <?php foreach ($financingOptionLabels as $optionKey => $optionLabel): ?>

                                                    <option value="<?= e($optionKey) ?>"><?= e($optionLabel) ?></option>

                                                <?php endforeach; ?>

                                            </select>

                                        </div>

                                    </div>



                                    <div id="wrap-modal-lead-consult-input" class="rounded-2xl bg-white px-4 py-4">

                                        <label for="modal-lead-consult-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Consultation Status</label>

                                        <select

                                            id="modal-lead-consult-input"

                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                        >

                                            <?php foreach ($consultationOptions as $optionKey => $optionLabel): ?>

                                                <option value="<?= e($optionKey) ?>"><?= e($optionLabel) ?></option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>

                                </div>

                            </div>

                        </div>



                        <div class="space-y-5">

                            <div id="lead-intel-panel" class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Lead intelligence</p>
                                <div class="mt-3 space-y-3 text-sm">
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Summary</p>
                                        <p id="lead-intel-summary-text" class="mt-1 text-slate-800">Open a lead to load summary.</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Missing info</p>
                                        <ul id="lead-intel-missing-list" class="mt-1 list-disc space-y-1 pl-4 text-slate-700"></ul>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Next recommended action</p>
                                        <p id="lead-intel-next-action" class="mt-1 text-slate-800">None</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500">Last touchpoint</p>
                                        <p id="lead-intel-last-touchpoint" class="mt-1 text-slate-800">No contact log yet.</p>
                                    </div>
                                </div>
                            </div>

                            <div id="lead-detail-appointment-section" data-detail-window="appointment" class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4 hidden">
                                <div class="mb-3">
                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Appointment</p>
                                </div>

                                <div class="space-y-4">
                                    <div id="wrap-modal-lead-consultation-date-input" class="rounded-2xl bg-white px-4 py-4">
                                        <label for="modal-lead-consultation-date-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Scheduled Consultation</label>

                                        <input

                                            type="datetime-local"

                                            id="modal-lead-consultation-date-input"

                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                        >

                                    </div>
                                </div>
                            </div>

                            <div id="lead-detail-source-section" data-detail-window="source" class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4 hidden">
                                <div class="mb-3">

                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Source & Attribution</p>

                                </div>



                                <div class="space-y-4">

                                    <div id="wrap-modal-lead-source-input" class="rounded-2xl bg-white px-4 py-4">

                                        <label for="modal-lead-source-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Source</label>
                                        <select

                                            id="modal-lead-source-input"

                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                        >

                                            <?php foreach ($sourceOptions as $optionKey => $optionLabel): ?>

                                                <option value="<?= e($optionKey) ?>"><?= e($optionLabel) ?></option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>



                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                                        <div class="rounded-2xl bg-white px-4 py-4">

                                            <label class="text-xs uppercase tracking-[0.18em] text-slate-400">Source Medium</label>

                                            <p id="modal-lead-source-medium" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-medium text-slate-800">-</p>

                                        </div>



                                        <div class="rounded-2xl bg-white px-4 py-4">

                                            <label class="text-xs uppercase tracking-[0.18em] text-slate-400">Source Type</label>

                                            <p id="modal-lead-source-type" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-medium text-slate-800">-</p>

                                        </div>

                                    </div>



                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                                        <div class="rounded-2xl bg-white px-4 py-4">

                                            <label class="text-xs uppercase tracking-[0.18em] text-slate-400">Instagram Username</label>

                                            <p id="modal-lead-instagram-username" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-medium text-slate-800">-</p>

                                        </div>



                                        <div class="rounded-2xl bg-white px-4 py-4">

                                            <label class="text-xs uppercase tracking-[0.18em] text-slate-400">Trigger Keyword</label>

                                            <p id="modal-lead-trigger-keyword" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-medium text-slate-800">-</p>

                                        </div>

                                    </div>



                                    <div id="wrap-modal-lead-landing-page-input" class="rounded-2xl bg-white px-4 py-4">

                                        <label for="modal-lead-landing-page-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Landing Page</label>

                                        <input

                                            type="text"

                                            id="modal-lead-landing-page-input"

                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                            placeholder="Page slug or URL"

                                        >

                                    </div>



                                    <div id="wrap-modal-lead-campaign-input" class="rounded-2xl bg-white px-4 py-4">

                                        <label for="modal-lead-campaign-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Campaign</label>

                                        <input

                                            type="text"

                                            id="modal-lead-campaign-input"

                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                            placeholder="Campaign name"

                                        >

                                    </div>



                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                                        <div class="rounded-2xl bg-white px-4 py-4">

                                            <label class="text-xs uppercase tracking-[0.18em] text-slate-400">Ad Set</label>

                                            <p id="modal-lead-source-ad-set" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-medium text-slate-800">-</p>

                                        </div>



                                        <div class="rounded-2xl bg-white px-4 py-4">

                                            <label class="text-xs uppercase tracking-[0.18em] text-slate-400">Ad Name</label>

                                            <p id="modal-lead-source-ad-name" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-medium text-slate-800">-</p>

                                        </div>

                                    </div>



                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                                        <div class="rounded-2xl bg-white px-4 py-4">

                                            <label class="text-xs uppercase tracking-[0.18em] text-slate-400">Post ID</label>

                                            <p id="modal-lead-source-post-id" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-medium text-slate-800">-</p>

                                        </div>



                                        <div class="rounded-2xl bg-white px-4 py-4">

                                            <label class="text-xs uppercase tracking-[0.18em] text-slate-400">External Lead ID</label>

                                            <p id="modal-lead-external-lead-id" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-medium text-slate-800">-</p>

                                        </div>

                                    </div>



                                    <div class="rounded-2xl bg-white px-4 py-4">

                                        <label class="text-xs uppercase tracking-[0.18em] text-slate-400">Post Reference</label>

                                        <p id="modal-lead-source-post-label" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-medium text-slate-800">-</p>

                                    </div>



                                    <div class="rounded-2xl bg-white px-4 py-4">

                                        <label class="text-xs uppercase tracking-[0.18em] text-slate-400">Assigned To</label>

                                        <p id="modal-lead-assigned" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-medium text-slate-800">-</p>

                                    </div>



                                    <div class="rounded-2xl bg-white px-4 py-4">

                                        <label class="text-xs uppercase tracking-[0.18em] text-slate-400">Created</label>

                                        <p id="modal-lead-created" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-medium text-slate-800">-</p>

                                    </div>

                                </div>

                            </div>



                            <div id="lead-detail-workflow-section" data-detail-window="workflow" class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4 hidden">
                                <div class="mb-3">

                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Workflow</p>

                                </div>



                                <div class="space-y-4">

                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                                        <div class="rounded-2xl bg-white px-4 py-4">

                                            <label for="modal-lead-stage-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Stage</label>

                                            <select

                                                id="modal-lead-stage-input"

                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                            >

                                                <?php foreach ($legacyStageMap as $stageKey => $stageLabel): ?>

                                                    <option value="<?= e($stageKey) ?>"><?= e($stageLabel) ?></option>

                                                <?php endforeach; ?>

                                            </select>

                                        </div>



                                        <div class="rounded-2xl bg-white px-4 py-4">

                                            <label for="modal-lead-value-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Lead Value</label>

                                            <input

                                                type="number"

                                                step="0.01"

                                                id="modal-lead-value-input"

                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                                placeholder="0.00"

                                            >

                                        </div>

                                    </div>



                                    <div class="rounded-2xl bg-white px-4 py-4">

                                        <label for="modal-lead-lost-reason-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Lost Reason</label>

                                        <select

                                            id="modal-lead-lost-reason-input"

                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"

                                        >

                                            <?php foreach ($lostReasonOptions as $reasonKey => $reasonLabel): ?>

                                                <option value="<?= e($reasonKey) ?>"><?= e($reasonLabel) ?></option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>

                                </div>

                            </div>
                            <div id="lead-detail-tasks-section" data-detail-window="tasks" class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4 hidden">
                                <div class="mb-3">
                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Tasks & Reminders</p>
                                </div>

                                <div class="space-y-4">
                                    <div class="rounded-2xl bg-white px-4 py-4">
                                        <label for="modal-lead-next-follow-up-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Next Follow-Up</label>
                                        <input
                                            type="datetime-local"
                                            id="modal-lead-next-follow-up-input"
                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"
                                        >
                                    </div>

                                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-6 text-amber-900">
                                        Appointment reminders use the scheduled consultation date. Set the consultation first, then save this lead.
                                    </div>
                                </div>
                            </div>


                        </div>

                    </div>

                </div>



                <div id="workspace-tab-notes" class="workspace-tab-panel hidden">
                    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[0.95fr_1.05fr]">

                        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4">

                            <div class="mb-3">

                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Follow-Up Note</p>

                            </div>



                            <div class="rounded-2xl bg-white px-4 py-4">

                                <label for="modal-lead-notes-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Notes</label>

                                <textarea

                                    id="modal-lead-notes-input"
                                    rows="10"

                                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm leading-6 outline-none"

                                    placeholder="Add call notes, treatment notes, objections, follow-up details..."

                                ></textarea>



                                <div class="mt-3 flex flex-wrap gap-3">

                                    <button

                                        type="button"

                                        id="modal-lead-save-notes-button"

                                        class="rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700"

                                    >

                                        Save Notes

                                    </button>



                                    <button

                                        type="button"

                                        id="modal-lead-save-button"

                                        class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white"

                                    >

                                        Save Changes

                                    </button>

                                </div>

                            </div>

                        </div>



                        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4">

                            <div class="mb-3 flex items-center justify-between gap-3">

                                <div>

                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Follow-Up History</p>

                                    <p class="mt-1 text-sm text-slate-500">Saved notes appear here for quick review.</p>

                                </div>

                            </div>



                            <div

                                id="modal-notes-history"

                                class="max-h-[520px] space-y-3 overflow-y-auto pr-1"

                            >

                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                                    Notes will appear here after save.

                                </div>

                            </div>

                        </div>

                    </div>

                </div>



                <div id="workspace-tab-communications" class="workspace-tab-panel hidden h-full">
                    <div id="lead-communications-grid" class="grid h-full min-h-0 grid-cols-1 gap-4 xl:grid-cols-[minmax(220px,15%)_minmax(0,1fr)_minmax(260px,15%)] xl:grid-rows-[minmax(0,1fr)_auto] xl:items-stretch">

                        <div class="contents">

                            <div class="hidden">

                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Communication Center</p>
                            </div>



                            <div class="contents">

                                <div id="lead-unified-timeline-panel" class="flex min-h-0 flex-col rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm xl:col-start-2 xl:row-start-1">

                                    <div class="flex items-center justify-between gap-3">

                                        <div>

                                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Unified Timeline</p>

                                            <p class="mt-1 text-sm text-slate-500">Latest patient touchpoints and CRM notes.</p>

                                        </div>

                                        <span class="text-[11px] font-medium text-slate-400">Latest first</span>

                                    </div>

                                    <div id="modal-unified-timeline" class="mt-3 min-h-0 flex-1 space-y-3 overflow-y-auto pr-2">

                                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">

                                            Open a lead to load the timeline.

                                        </div>
                                    </div>
                                </div>

                                <div class="min-h-0 overflow-visible rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm text-slate-600 shadow-sm xl:col-start-1 xl:row-start-1 xl:row-span-2">

                                    <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Selected Lead</p>

                                    <p id="legacy-modal-sms-lead-name" class="mt-2 font-semibold text-slate-900">Lead</p>

                                    <p id="legacy-modal-sms-lead-phone" class="mt-1 text-slate-500">No phone selected</p>

                                    <div class="mt-3 hidden flex-wrap gap-2">
                                        <button type="button" data-composer-mode="sms" class="composer-mode-button rounded-full border border-slate-900 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">SMS</button>
                                        <button type="button" data-composer-mode="email" class="composer-mode-button rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600">Email</button>
                                        <button type="button" data-composer-mode="note" class="composer-mode-button rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600">Note</button>
                                    </div>

                                <p id="legacy-modal-sms-opt-status" class="mt-3 inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">SMS status unknown</p>
                                <div id="modal-sms-dnd-control" class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                    <div class="mb-3 inline-flex w-full items-center justify-between text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                        <span>SMS Permission</span>
                                        <span id="modal-sms-dnd-summary">Status</span>
                                    </div>
                                    <div id="modal-sms-dnd-body" class="grid gap-2">
                                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300">
                                            <input type="radio" name="modal_sms_opt_status" value="unknown" class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-slate-900">
                                            Unknown
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-emerald-200 bg-white px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:border-emerald-300">
                                            <input type="radio" name="modal_sms_opt_status" value="opted_in" class="h-4 w-4 border-emerald-300 text-emerald-600 focus:ring-emerald-600">
                                            OK to Text
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-700 transition hover:border-rose-300">
                                            <input type="radio" name="modal_sms_opt_status" value="opted_out" class="h-4 w-4 border-rose-300 text-rose-600 focus:ring-rose-600">
                                            DND / Do Not Text
                                        </label>
                                    </div>
                                </div>


                                <div class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">

                                    <div class="flex items-center justify-between gap-3">

                                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Scheduling Details</p>

                                        <span class="text-[11px] font-medium text-slate-400">Appointment prep</span>

                                    </div>

                                    <div id="modal-message-thread" class="hidden mt-3 space-y-3 pr-1 max-h-[clamp(120px,24vh,200px)] overflow-y-auto">

                                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">

                                            Open a lead to load SMS history.

                                        </div>

                                    </div>

                                    <div class="grid grid-cols-1 gap-4">

                                        <div class="rounded-2xl bg-white px-4 py-4">
                                            <label for="modal-communication-consultation-date-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Scheduled Consultation</label>
                                            <input type="datetime-local" id="modal-communication-consultation-date-input" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none">
                                            <p class="mt-2 text-[11px] leading-5 text-slate-500">This saves to the real appointment field.</p>
                                        </div>

                                    </div>

                                </div>

                                </div>



                                <div id="lead-activity-panel" class="flex min-h-0 flex-col rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm xl:col-start-3 xl:row-start-1">

                                    <div class="flex items-center justify-between gap-3">

                                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Activity <span class="tracking-normal text-slate-400">(MST)</span></p>

                                        <span class="text-[11px] font-medium text-slate-400">Issues, stages, audit</span>

                                    </div>

                                    <div id="modal-activity-feed" class="mt-3 min-h-0 flex-1 space-y-3 overflow-y-auto pr-2">

                                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">

                                            Activity will appear after calls, texts, delivery issues, and stage moves.

                                        </div>

                                    </div>

                                </div>

                                <details class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm xl:col-start-3 xl:row-start-2">

                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3">

                                        <span class="text-xs uppercase tracking-[0.18em] text-slate-400">Email History</span>

                                        <span class="text-[11px] font-medium text-slate-400">Click to expand</span>

                                    </summary>

                                    <div id="modal-email-history" class="mt-3 max-h-[clamp(120px,24vh,220px)] space-y-3 overflow-y-auto pr-2">

                                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">

                                            Sent patient emails will appear here.

                                        </div>

                                    </div>

                                </details>
                            </div>
                        </div>



                        <div id="lead-communication-composer-panel" class="flex h-[300px] min-h-0 w-full flex-col self-end overflow-hidden rounded-2xl border border-blue-200 bg-white p-3 shadow-sm xl:col-start-2 xl:row-start-2">

                                <div class="mb-2 flex flex-wrap items-center justify-between gap-3">

                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Conversation Composer</p>
                                    <div class="flex items-center gap-3 text-xs font-semibold">
                                        <button type="button" data-composer-mode="sms" class="composer-mode-button text-slate-900">SMS</button>
                                        <button type="button" data-composer-mode="email" class="composer-mode-button text-slate-500">Email</button>
                                        <button type="button" data-composer-mode="note" class="composer-mode-button text-slate-500">Note</button>
                                        <button
                                            type="button"
                                            id="modal-composer-collapse-toggle"
                                            class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-white"
                                            aria-expanded="true"
                                        >
                                            Collapse
                                        </button>
                                    </div>
                                    <p id="modal-composer-send-cue" class="hidden w-full text-[11px] text-slate-500">SMS compose is manual. Review message carefully before send.</p>

                            </div>

                            <div id="modal-composer-body" class="min-h-0 w-full flex-1 overflow-hidden">
                            <div id="modal-ai-assistant-panel" class="hidden mb-4 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <label for="modal-ai-instruction-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">AI Instruction</label>
                                    <button
                                        type="button"
                                        id="modal-ai-collapse-toggle"
                                        class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                                        aria-expanded="false"
                                    >
                                        Show
                                    </button>
                                </div>

                                <div id="modal-ai-assistant-body" class="mt-3 hidden">
                                    <textarea
                                        rows="4"
                                        id="modal-ai-instruction-input"
                                        class="w-full resize-none rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm leading-6 outline-none"
                                        placeholder="Send a follow-up text and email. Mention Dr. Meden will review the case and ask what time works best."
                                    ></textarea>

                                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                        <p id="modal-ai-status" class="min-h-4 text-xs text-slate-500"></p>

                                        <button
                                            type="button"
                                            id="modal-ai-draft-both-button"
                                            class="rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700"
                                        >
                                            Draft Both
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div id="modal-composer-panel-sms" data-composer-panel="sms" class="h-full min-h-0 w-full">
                                <label for="modal-sms-template-select" class="sr-only">Answer Template</label>
                                <div class="flex h-full min-h-0 w-full flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                    <select
                                        id="modal-sms-template-select"
                                        class="hidden w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"
                                    >
                                        <option value="">Write custom message</option>
                                        <?php foreach ($smsTemplateOptions as $templateKey => $template): ?>
                                            <option value="<?= e($templateKey) ?>"><?= e((string)($template['label'] ?? $templateKey)) ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <textarea
                                        rows="4"
                                        aria-label="Text message"
                                        id="modal-lead-sms-input"
                                        class="min-h-0 flex-1 resize-none overflow-y-auto rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm leading-6 outline-none"
                                        placeholder="Type a message..."
                                    ></textarea>

                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p id="modal-lead-sms-status" class="min-h-4 text-xs text-slate-500"></p>

                                        <div class="flex flex-wrap items-center gap-2">
                                            <button
                                                type="button"
                                                id="modal-lead-draft-sms-button"
                                                class="rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700"
                                            >
                                                AI Draft
                                            </button>

                                            <button
                                                type="button"
                                                id="modal-lead-improve-sms-button"
                                                class="rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700"
                                            >
                                                Improve
                                            </button>

                                            <button
                                                type="button"
                                                id="modal-lead-send-sms-button"
                                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                                title="Send SMS"
                                                aria-label="Send SMS"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="m22 2-7 20-4-9-9-4Z"></path>
                                                    <path d="M22 2 11 13"></path>
                                                </svg>
                                                Send SMS
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 hidden flex-wrap gap-2">
                                    <button
                                        type="button"
                                        id="modal-lead-load-thread-button"
                                        class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                                    >
                                        Load Thread
                                    </button>

                                    <button
                                        type="button"
                                        id="modal-lead-save-button-communications"
                                        class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                                    >
                                        Save Changes
                                    </button>
                                </div>

                            </div>

                            <div id="modal-composer-panel-email" data-composer-panel="email" class="hidden h-full min-h-0 rounded-[1.5rem] border border-slate-200 bg-white p-4">

                                <div class="hidden">

                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Email Follow-Up</p>

                                    <p class="mt-1 text-sm text-slate-500">Premium branded email</p>

                                </div>

                                <label for="modal-lead-email-subject-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Subject</label>

                                <input
                                    type="text"
                                    id="modal-lead-email-subject-input"
                                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"
                                    placeholder="Email subject"
                                >

                                <label for="modal-lead-email-body-input" class="mt-4 block text-xs uppercase tracking-[0.18em] text-slate-400">Body</label>

                                    <textarea
                                        rows="7"
                                        aria-label="Patient email"
                                    id="modal-lead-email-body-input"
                                    class="mt-2 max-h-[118px] w-full resize-none overflow-y-auto rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm leading-6 outline-none"
                                    placeholder="Draft a polished patient email..."
                                ></textarea>

                                <p id="modal-lead-email-status" class="mt-2 min-h-4 text-xs text-slate-500"></p>

                                <div class="mt-3 flex flex-wrap gap-3">

                                    <button
                                        type="button"
                                        id="modal-lead-draft-email-button"
                                        class="rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700"
                                    >
                                        AI Draft
                                    </button>

                                    <button
                                        type="button"
                                        id="modal-lead-improve-email-button"
                                        class="rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700"
                                    >
                                        Improve
                                    </button>

                                    <button
                                        type="button"
                                        id="modal-lead-send-email-button"
                                        class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        Send Email
                                    </button>

                                </div>

                            </div>

                            <div id="modal-composer-panel-note" data-composer-panel="note" class="hidden h-full min-h-0 rounded-[1.5rem] border border-slate-200 bg-white p-4">

                                <div class="hidden">
                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Internal Note</p>
                                    <p class="mt-1 text-sm text-slate-500">Log a call, decision, objection, or next step.</p>
                                </div>

                                    <textarea
                                        rows="6"
                                        id="modal-communication-note-input"
                                    class="max-h-[150px] w-full resize-none overflow-y-auto rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm leading-6 outline-none"
                                    placeholder="Add a clear internal note..."
                                ></textarea>

                                <p id="modal-communication-note-status" class="mt-2 min-h-4 text-xs text-slate-500"></p>

                                <div class="mt-3 flex flex-wrap gap-3">
                                    <button
                                        type="button"
                                        id="modal-save-communication-note-button"
                                        class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        Save Note
                                    </button>
                                </div>

                            </div>



                        </div>

                    </div>

                </div>

            </div>



            <div id="lead-detail-footer" class="hidden shrink-0 border-t border-slate-200 bg-white px-6 py-4">

                <div class="flex flex-wrap items-center gap-3">

                    <button

                        type="button"

                        id="workspace-save-main-legacy"

                        class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"

                    >

                        Save Changes

                    </button>



                    <button

                        type="button"

                        class="rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-medium text-slate-700"

                        id="lead-detail-close-bottom"

                    >

                        Close

                    </button>

                </div>



                <p id="modal-save-status-footer" class="mt-3 text-xs text-slate-500"></p>

            </div>

        </div>

    </div>

</div>



<script>

(function () {

    const board = document.getElementById('lead-pipeline-board');

    const viewport = document.getElementById('pipeline-board-viewport');
    const pipelineNotificationsButton = document.getElementById('pipeline-notifications-button');
    const pipelineNotificationsCount = document.getElementById('pipeline-notifications-count');
    const pipelineNotificationsMenu = document.getElementById('pipeline-notifications-menu');
    const pipelineNotificationsList = document.getElementById('pipeline-notifications-list');
    const pipelineMobileStageFilter = document.getElementById('pipeline-mobile-stage-filter');

    const modal = document.getElementById('lead-detail-modal');

    const closeTop = document.getElementById('lead-detail-close');

    const closeBottom = document.getElementById('lead-detail-close-bottom');

    const deleteLeadButton = document.getElementById('lead-delete-button');

    const saveButton = document.getElementById('workspace-save-main');

    const saveButtonNotes = document.getElementById('modal-lead-save-button');

    const saveButtonNotesSmall = document.getElementById('modal-lead-save-notes-button');

    const saveButtonCommunications = document.getElementById('modal-lead-save-button-communications');

    const draftSmsButton = document.getElementById('modal-lead-draft-sms-button');
    const improveSmsButton = document.getElementById('modal-lead-improve-sms-button');
    const sendSmsButton = document.getElementById('modal-lead-send-sms-button');
    const draftEmailButton = document.getElementById('modal-lead-draft-email-button');
    const improveEmailButton = document.getElementById('modal-lead-improve-email-button');
    const draftBothButton = document.getElementById('modal-ai-draft-both-button');
    const sendEmailButton = document.getElementById('modal-lead-send-email-button');
    const loadThreadButton = document.getElementById('modal-lead-load-thread-button');
    const followupCheckButton = document.getElementById('run-followup-check');
    const saveStatus = document.getElementById('modal-save-status-footer');
    const calendarOpenButton = document.getElementById('pipeline-calendar-button');
    const calendarOverlay = document.getElementById('pipeline-calendar-overlay');
    const calendarCloseButton = document.getElementById('pipeline-calendar-close');
    const calendarRangeLabel = document.getElementById('pipeline-calendar-range');
    const calendarSubtitle = document.getElementById('pipeline-calendar-subtitle');
    const calendarViewRoot = document.getElementById('pipeline-calendar-view');
    const calendarPrevButton = document.getElementById('pipeline-calendar-prev');
    const calendarNextButton = document.getElementById('pipeline-calendar-next');
    const calendarTodayButton = document.getElementById('pipeline-calendar-today');
    const calendarViewButtons = Array.from(document.querySelectorAll('[data-calendar-view]'));


    const modalMissingPanel = document.getElementById('modal-missing-panel');

    const modalMissingList = document.getElementById('modal-missing-list');



    const modalLeadNameInput = document.getElementById('modal-lead-name-input');

    const modalLeadPhoneInput = document.getElementById('modal-lead-phone-input');

    const modalLeadEmailInput = document.getElementById('modal-lead-email-input');

    const modalLeadPreferredContactInput = document.getElementById('modal-lead-preferred-contact-input');

    const modalLeadProcedureInput = document.getElementById('modal-lead-procedure-input');

    const modalLeadFinancingNeededInput = document.getElementById('modal-lead-financing-needed-input');

    const modalLeadFinancingOptionInput = document.getElementById('modal-lead-financing-option-input');

    const modalLeadConsultInput = document.getElementById('modal-lead-consult-input');

    const modalLeadConsultationDateInput = document.getElementById('modal-lead-consultation-date-input');
    const modalCommunicationConsultationDateInput = document.getElementById('modal-communication-consultation-date-input');

    const modalLeadDobInput = document.getElementById('modal-lead-dob-input');
    const modalLeadIntentTypeInput = document.getElementById('modal-lead-intent-type-input');

    const modalLeadPreferredDayInput = document.getElementById('modal-lead-preferred-day-input');

    const modalLeadPreferredTimeInput = document.getElementById('modal-lead-preferred-time-input');

    const modalLeadNextFollowUpInput = document.getElementById('modal-lead-next-follow-up-input');
    const modalLeadSourceInput = document.getElementById('modal-lead-source-input');

    const modalLeadLandingPageInput = document.getElementById('modal-lead-landing-page-input');

    const modalLeadCampaignInput = document.getElementById('modal-lead-campaign-input');



    const notesInput = document.getElementById('modal-lead-notes-input');

    const leadValueInput = document.getElementById('modal-lead-value-input');

    const lostReasonInput = document.getElementById('modal-lead-lost-reason-input');

    const leadStageInput = document.getElementById('modal-lead-stage-input');

    const notesHistory = document.getElementById('modal-notes-history');

    const smsInput = document.getElementById('modal-lead-sms-input');

    const smsTemplateSelect = document.getElementById('modal-sms-template-select');

    const smsStatus = document.getElementById('modal-lead-sms-status');
    const aiInstructionPanel = document.getElementById('modal-ai-assistant-panel');
    const aiInstructionBody = document.getElementById('modal-ai-assistant-body');
    const aiInstructionInput = document.getElementById('modal-ai-instruction-input');
    const aiStatus = document.getElementById('modal-ai-status');
    const aiCollapseToggle = document.getElementById('modal-ai-collapse-toggle');
    const emailSubjectInput = document.getElementById('modal-lead-email-subject-input');
    const emailBodyInput = document.getElementById('modal-lead-email-body-input');
    const emailStatus = document.getElementById('modal-lead-email-status');
    const composerModeButtons = Array.from(document.querySelectorAll('[data-composer-mode]'));
    const composerPanels = Array.from(document.querySelectorAll('[data-composer-panel]'));
    const composerBody = document.getElementById('modal-composer-body');
    const composerCollapseToggle = document.getElementById('modal-composer-collapse-toggle');
    const communicationNoteInput = document.getElementById('modal-communication-note-input');
    const communicationNoteStatus = document.getElementById('modal-communication-note-status');
    const saveCommunicationNoteButton = document.getElementById('modal-save-communication-note-button');

    const smsLeadName = document.getElementById('modal-sms-lead-name');

    const smsLeadPhone = document.getElementById('modal-sms-lead-phone');

    const smsOptStatus = document.getElementById('modal-sms-opt-status');
    const smsOptStatusInputs = Array.from(document.querySelectorAll('input[name="modal_sms_opt_status"]'));
    const leadDetailHeader = document.getElementById('lead-detail-header');
    const leadDetailBody = document.getElementById('lead-detail-body');
    const leadDetailFooter = document.getElementById('lead-detail-footer');
    const leadCommunicationGrid = document.getElementById('lead-communications-grid');
    const leadUnifiedTimelinePanel = document.getElementById('lead-unified-timeline-panel');
    const leadActivityPanel = document.getElementById('lead-activity-panel');
    const leadCommunicationComposerPanel = document.getElementById('lead-communication-composer-panel');

    const messageThread = document.getElementById('modal-message-thread');

    const activityFeed = document.getElementById('modal-activity-feed');
    const unifiedTimeline = document.getElementById('modal-unified-timeline');
    const emailHistory = document.getElementById('modal-email-history');
    const leadIntelSummaryText = document.getElementById('lead-intel-summary-text');
    const leadIntelMissingList = document.getElementById('lead-intel-missing-list');
    const leadIntelNextAction = document.getElementById('lead-intel-next-action');
    const leadIntelLastTouchpoint = document.getElementById('lead-intel-last-touchpoint');

    const composerSafetyCue = document.getElementById('modal-composer-send-cue');

    const smsDndToggle = document.getElementById('modal-sms-dnd-toggle');
    const smsDndBody = document.getElementById('modal-sms-dnd-body');
    const smsDndSummary = document.getElementById('modal-sms-dnd-summary');

    const newLeadModal = document.getElementById('new-lead-modal');

    const openNewLeadButton = document.getElementById('open-new-lead-modal');

    const closeNewLeadButton = document.getElementById('new-lead-close');

    const cancelNewLeadButton = document.getElementById('new-lead-cancel');

    const saveNewLeadButton = document.getElementById('new-lead-save');

    const newLeadStatus = document.getElementById('new-lead-status');



    const newLeadFullName = document.getElementById('new-lead-full-name');

    const newLeadPhone = document.getElementById('new-lead-phone');

    const newLeadEmail = document.getElementById('new-lead-email');

    const newLeadPreferredContact = document.getElementById('new-lead-preferred-contact');

    const newLeadProcedure = document.getElementById('new-lead-procedure-interest');

    const newLeadConsultStatus = document.getElementById('new-lead-consult-status');

    const newLeadConsultationDate = document.getElementById('new-lead-consultation-date');
    const newLeadSource = document.getElementById('new-lead-source');

    const newLeadLandingPage = document.getElementById('new-lead-landing-page');

    const newLeadCampaign = document.getElementById('new-lead-campaign');

    const newLeadFinancingNeeded = document.getElementById('new-lead-financing-needed');

    const newLeadFinancingOption = document.getElementById('new-lead-financing-option');

    const newLeadValue = document.getElementById('new-lead-value');

    const newLeadStage = document.getElementById('new-lead-stage');

    const newLeadNotes = document.getElementById('new-lead-notes');



    const tabButtons = Array.from(document.querySelectorAll('.workspace-tab-button'));
    const detailWindowButtons = Array.from(document.querySelectorAll('[data-detail-window-target]'));
    const detailWindowPanels = Array.from(document.querySelectorAll('[data-detail-window]'));

    const tabPanels = {

        details: document.getElementById('workspace-tab-details'),

        notes: document.getElementById('workspace-tab-notes'),

        communications: document.getElementById('workspace-tab-communications')

    };

    function setActiveDetailWindow(windowName = 'contact') {
        const safeWindowName = ['contact', 'opportunity', 'appointment', 'tasks', 'source', 'workflow'].includes(String(windowName))
            ? String(windowName)
            : 'contact';

        detailWindowPanels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.detailWindow !== safeWindowName);
        });

        detailWindowButtons.forEach((button) => {
            const active = button.dataset.detailWindowTarget === safeWindowName;
            button.classList.toggle('border-slate-900', active);
            button.classList.toggle('bg-slate-900', active);
            button.classList.toggle('text-white', active);
            button.classList.toggle('border-slate-200', !active);
            button.classList.toggle('bg-white', !active);
            button.classList.toggle('text-slate-700', !active);
        });
    }

    function detailWindowForElement(targetId) {
        const target = document.getElementById(targetId);
        const panel = target ? target.closest('[data-detail-window]') : null;
        return panel?.dataset?.detailWindow || 'contact';
    }

    detailWindowButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const windowName = button.dataset.detailWindowTarget || 'contact';
            setActiveDetailWindow(windowName);

            const targetSelector = button.getAttribute('href') || '';
            const target = targetSelector.startsWith('#') ? document.getElementById(targetSelector.slice(1)) : null;
            if (target) {
                window.setTimeout(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }), 20);
            }
        });
    });

    setActiveDetailWindow('contact');



    if (!board || !viewport) return;



    let draggedCard = null;

    let sourceDropzone = null;

    let activeCard = null;

    let dragMouseX = null;

    let autoScrollRaf = null;

    let isSaving = false;

    let isCreatingLead = false;

    let isDeletingLead = false;

    let isDraftingSms = false;
    let isSendingSms = false;
    let isDraftingEmail = false;
    let isDraftingBoth = false;
    let isSendingEmail = false;
    let composerMode = 'sms';
    let composerDraftSources = {
        sms: 'manual',
        email: 'manual',
        note: 'manual',
    };
    const calendarStateStorageKey = 'elite-smiles-calendar-panel-state-v1';
    const calendarStateFromStorage = (() => {
        try {
            const raw = window.sessionStorage ? window.sessionStorage.getItem(calendarStateStorageKey) : null;
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') return null;
            return parsed;
        } catch (error) {
            return null;
        }
    })();
    let calendarView = ['day', 'week', 'month'].includes(calendarStateFromStorage?.view) ? calendarStateFromStorage.view : 'day';
    let calendarAnchorDate = (() => {
        const savedDate = calendarStateFromStorage?.anchorDate ? new Date(calendarStateFromStorage.anchorDate) : null;
        if (savedDate && !Number.isNaN(savedDate.getTime())) {
            savedDate.setHours(0, 0, 0, 0);
            return savedDate;
        }
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return today;
    })();
    const calendarOpenHour = 8;
    const calendarCloseHour = 19;
    const calendarSlotMinutes = 30;


    const csrfToken = <?= json_encode(csrf_token()) ?>;

    const saveDetailsUrl = <?= json_encode(base_url('app/actions/lead_update_details.php')) ?>;

    const saveStageUrl = <?= json_encode(base_url('app/actions/lead_update_stage.php')) ?>;

    const createLeadUrl = <?= json_encode(base_url('app/actions/lead_create.php')) ?>;
    const importLeadsUrl = <?= json_encode(base_url('app/actions/lead_import.php')) ?>;

    const deleteLeadUrl = <?= json_encode(base_url('app/actions/lead_delete.php')) ?>;

    const sendSmsUrl = <?= json_encode(base_url('app/actions/lead_send_sms.php')) ?>;
    const smsDraftUrl = <?= json_encode(base_url('app/actions/lead_sms_draft.php')) ?>;
    const emailDraftUrl = <?= json_encode(base_url('app/actions/lead_email_draft.php')) ?>;
    const sendEmailUrl = <?= json_encode(base_url('app/actions/lead_send_email.php')) ?>;
    const threadUrl = <?= json_encode(base_url('app/actions/lead_get_thread.php')) ?>;
    const followupCheckUrl = <?= json_encode(base_url('app/actions/lead_followup_check.php')) ?>;
    const smsTemplates = <?= json_encode($smsTemplateOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const stageLabelMap = <?= json_encode($legacyStageMap) ?>;
    const importLeadsButton = document.getElementById('open-import-leads-picker');
    const importLeadsFileInput = document.getElementById('import-leads-file');
    const importLeadsStatus = document.getElementById('import-leads-status');
    let isImportingLeads = false;

    function normalizeImportedLeadHeader(value) {
        return String(value || '').trim().toLowerCase();
    }

    function parseImportedLeadFile(text) {
        const normalizedText = String(text || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim();
        if (!normalizedText) return [];
        const lines = normalizedText.split('\n').filter(line => line.trim() !== '');
        if (!lines.length) return [];
        const delimiter = lines[0].includes('\t') ? '\t' : ',';
        const parseLine = (line) => {
            if (delimiter === '\t') return line.split('\t');
            const values = [];
            let current = '';
            let inQuotes = false;
            for (let i = 0; i < line.length; i += 1) {
                const char = line[i];
                if (char === '"') {
                    if (inQuotes && line[i + 1] === '"') {
                        current += '"';
                        i += 1;
                    } else {
                        inQuotes = !inQuotes;
                    }
                } else if (char === delimiter && !inQuotes) {
                    values.push(current);
                    current = '';
                } else {
                    current += char;
                }
            }
            values.push(current);
            return values;
        };
        const headers = parseLine(lines[0]).map(normalizeImportedLeadHeader);
        return lines.slice(1).map((line) => {
            const values = parseLine(line);
            const row = {};
            headers.forEach((header, index) => {
                row[header] = (values[index] || '').trim();
            });
            return row;
        }).filter((row) => Object.values(row).some(value => String(value || '').trim() !== ''));
    }

    async function importLeadRows(rows) {
        if (isImportingLeads) return;
        isImportingLeads = true;
        if (importLeadsButton) importLeadsButton.disabled = true;
        if (importLeadsStatus) importLeadsStatus.textContent = 'Importing leads...';
        try {
            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);
            formData.append('rows_json', JSON.stringify(rows));
            const response = await fetch(importLeadsUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await parseJsonResponse(response);
            if (!response.ok || !data.ok) throw new Error(data.message || 'Lead import failed.');
            const result = data.result || {};
            const summary = `Imported ${result.created_count || 0} new lead(s). Skipped ${result.duplicate_count || 0} duplicate(s). Failed ${result.failed_count || 0}.`;
            if (importLeadsStatus) importLeadsStatus.textContent = summary;
            window.location.reload();
            return true;
        } catch (error) {
            if (importLeadsStatus) importLeadsStatus.textContent = error.message || 'Lead import failed.';
            return false;
        } finally {
            isImportingLeads = false;
            if (importLeadsButton) importLeadsButton.disabled = false;
            if (importLeadsFileInput) importLeadsFileInput.value = '';
        }
    }

    if (importLeadsButton && importLeadsFileInput) {
        importLeadsButton.addEventListener('click', () => {
            if (isImportingLeads) return;
            importLeadsFileInput.click();
        });

        importLeadsFileInput.addEventListener('change', async (event) => {
            const file = event.target && event.target.files ? event.target.files[0] : null;
            if (!file) return;
            const text = await file.text();
            const rows = parseImportedLeadFile(text);
            if (!rows.length) {
                if (importLeadsStatus) importLeadsStatus.textContent = 'No lead rows were found in that file.';
                importLeadsFileInput.value = '';
                return;
            }
            const previewNames = rows.slice(0, 6).map(row => row.full_name || row.name || row.email || row.phone_number || row.phone || 'Unnamed lead');
            const previewText = previewNames.join('\n');
            const proceed = window.confirm(`Found ${rows.length} lead(s) in this file.\n\n${previewText}${rows.length > 6 ? `\n...and ${rows.length - 6} more.` : ''}\n\nOnly non-duplicates will be imported. Continue?`);
            if (!proceed) {
                importLeadsFileInput.value = '';
                if (importLeadsStatus) importLeadsStatus.textContent = 'Lead import cancelled.';
                return;
            }
            await importLeadRows(rows);
        });
    }


    function updateColumnCounts() {

        document.querySelectorAll('.pipeline-column').forEach((column) => {

            const countEl = column.querySelector('.pipeline-count');

            const cards = column.querySelectorAll('.lead-card').length;



            if (countEl) countEl.textContent = String(cards);



            const dropzone = column.querySelector('.pipeline-dropzone');

            if (!dropzone) return;



            let emptyState = dropzone.querySelector('.empty-state');



            if (cards === 0 && !emptyState) {

                emptyState = document.createElement('div');

                emptyState.className = 'empty-state rounded-[1.5rem] border border-dashed border-slate-300 bg-white/70 p-5 text-center';

                emptyState.innerHTML = '<p class="text-sm font-medium text-slate-700">No leads here</p><p class="mt-1 text-xs text-slate-500">Drop a lead here.</p>';

                dropzone.appendChild(emptyState);

            }



            if (cards > 0 && emptyState) {

                emptyState.remove();

            }

        });

    }



    function pipelineNotificationTimestamp(card) {
        const candidates = [
            card.dataset.leadLastInboundAt || '',
            card.dataset.leadLastOutboundAt || '',
            card.dataset.leadLastContactedAt || '',
            card.dataset.leadCreated || '',
        ];

        for (const value of candidates) {
            const timestamp = Date.parse(String(value).replace(' ', 'T'));
            if (Number.isFinite(timestamp)) {
                return timestamp;
            }
        }

        return 0;
    }

    function pipelineNotificationItems() {
        if (!board) {
            return [];
        }

        return Array.from(board.querySelectorAll('.lead-card')).flatMap((card) => {
            const unreadCount = Number.parseInt(card.dataset.leadUnreadMessageCount || '0', 10) || 0;
            const isNewLead = (card.dataset.stageKey || '') === 'new_lead';
            const items = [];
            const timestamp = pipelineNotificationTimestamp(card);
            const name = card.dataset.leadName || 'Unnamed Lead';
            const stage = card.dataset.leadStageLabel || card.dataset.stageKey || 'Pipeline';

            if (unreadCount > 0) {
                items.push({
                    card,
                    type: 'communication',
                    label: unreadCount === 1 ? 'New communication' : unreadCount + ' new communications',
                    detail: stage,
                    count: unreadCount,
                    name,
                    timestamp,
                    tab: 'communications',
                });
            }

            if (isNewLead) {
                items.push({
                    card,
                    type: 'new_lead',
                    label: 'New lead',
                    detail: stage,
                    count: 1,
                    name,
                    timestamp,
                    tab: 'details',
                });
            }

            return items;
        }).sort((a, b) => b.timestamp - a.timestamp);
    }

    function openPipelineNotification(item) {
        if (!item || !item.card) {
            return;
        }

        if (pipelineNotificationsMenu) {
            pipelineNotificationsMenu.classList.add('hidden');
        }
        if (pipelineNotificationsButton) {
            pipelineNotificationsButton.setAttribute('aria-expanded', 'false');
        }

        item.card.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
        item.card.classList.add('ring-2', 'ring-blue-500', 'ring-offset-2');
        window.setTimeout(() => {
            item.card.classList.remove('ring-2', 'ring-blue-500', 'ring-offset-2');
        }, 1800);
        window.setTimeout(() => openLeadModal(item.card, item.tab || 'communications'), 350);
    }

    function renderPipelineNotifications() {
        if (!pipelineNotificationsButton || !pipelineNotificationsCount || !pipelineNotificationsList) {
            return;
        }

        const items = pipelineNotificationItems();
        const total = items.reduce((sum, item) => sum + Math.max(1, item.count || 1), 0);
        pipelineNotificationsCount.textContent = total > 99 ? '99+' : String(total);
        pipelineNotificationsCount.classList.toggle('hidden', total === 0);
        pipelineNotificationsCount.classList.toggle('inline-flex', total > 0);

        pipelineNotificationsList.innerHTML = '';

        if (items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'px-4 py-6 text-center text-sm text-slate-500';
            empty.textContent = 'No new communications or new leads right now.';
            pipelineNotificationsList.appendChild(empty);
            return;
        }

        items.slice(0, 20).forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'flex w-full items-start gap-3 rounded-2xl px-3 py-3 text-left transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500';
            button.innerHTML = '<span class="mt-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full ' + (item.type === 'communication' ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700') + '">' + (item.type === 'communication' ? '?' : '+') + '</span>'
                + '<span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-900"></span><span class="mt-0.5 block text-xs font-medium text-slate-600"></span><span class="mt-1 block truncate text-xs text-slate-400"></span></span>';
            const labels = button.querySelectorAll('span span');
            labels[0].textContent = item.name;
            labels[1].textContent = item.label;
            labels[2].textContent = item.detail;
            button.addEventListener('click', () => openPipelineNotification(item));
            pipelineNotificationsList.appendChild(button);
        });
    }
    function setText(id, value, fallback = '-') {

        const el = document.getElementById(id);

        if (!el) return;

        el.textContent = value && String(value).trim() !== '' ? value : fallback;

    }



    function formatMoney(value) {

        const num = Number(value || 0);

        if (!Number.isFinite(num) || num <= 0) return '$10,000';

        return '$' + Math.round(num).toLocaleString();

    }



    function formatPhoneForDisplay(phone) {

        const digits = String(phone || '').replace(/\D+/g, '');

        if (digits.length === 10) {

            return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);

        }

        if (digits.length === 11 && digits.startsWith('1')) {

            return '+1 (' + digits.slice(1, 4) + ') ' + digits.slice(4, 7) + '-' + digits.slice(7);

        }

        return String(phone || '').trim();

    }



    function toDatetimeLocal(value) {

        if (!value) return '';

        const normalized = String(value).replace(' ', 'T').slice(0, 16);

        return normalized.length >= 16 ? normalized : '';

    }

    function toDateKey(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function parseConsultationDate(rawDate) {
        if (!rawDate) return null;
        const parsed = new Date(String(rawDate).replace(' ', 'T'));
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function calendarSlotMinutesIndex(date, dayStart) {
        const time = date.getHours() * 60 + date.getMinutes();
        const start = (dayStart || calendarOpenHour) * 60;
        const end = (calendarCloseHour || 19) * 60;
        if (time < start || time >= end) return null;
        return Math.floor((time - start) / calendarSlotMinutes);
    }

    function calendarSlots() {
        const totalSlots = Math.floor(((calendarCloseHour - calendarOpenHour) * 60) / calendarSlotMinutes);
        return Array.from({ length: totalSlots }).map((_, i) => {
            const minuteValue = calendarOpenHour * 60 + i * calendarSlotMinutes;
            const slotDate = new Date(2000, 0, 1, 0, 0, 0, 0);
            slotDate.setHours(Math.floor(minuteValue / 60), minuteValue % 60, 0, 0);
            return {
                index: i,
                minuteValue,
                label: slotDate.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }),
            };
        });
    }

    function calendarDayStart(date) {
        const start = new Date(date);
        start.setHours(0, 0, 0, 0);
        return start;
    }

    function calendarWeekStart(date) {
        const dayStart = calendarDayStart(date);
        const day = dayStart.getDay();
        const mondayOffset = day === 0 ? -6 : 1 - day;
        dayStart.setDate(dayStart.getDate() + mondayOffset);
        return dayStart;
    }

    function safeCardLookupById(leadId) {
        if (!board || !leadId) return null;
        const selector = `.lead-card[data-lead-id="${(window.CSS && CSS.escape ? CSS.escape(leadId) : leadId)}"]`;
        return board.querySelector(selector);
    }

    function getCalendarAppointments() {
        const slots = calendarSlots();
        const byDate = new Map();

        Array.from(board.querySelectorAll('.lead-card')).forEach((card) => {
            const rawDate = card.dataset.leadConsultationDate || '';
            const parsed = parseConsultationDate(rawDate);
            if (!parsed) return;

            const dateKey = toDateKey(calendarDayStart(parsed));
            const slotIndex = calendarSlotMinutesIndex(parsed);

            if (!byDate.has(dateKey)) {
                byDate.set(dateKey, []);
            }

            byDate.get(dateKey).push({
                leadId: card.dataset.leadId || '',
                name: card.dataset.leadName || 'Lead',
                stage: card.dataset.leadStageLabel || 'Lead',
                preferredContact: card.dataset.leadPreferredContact || '',
                date: parsed,
                slotIndex,
                phone: card.dataset.leadPhone || '',
                timeLabel: formatAppointmentForCard(rawDate),
                slotLabel: slots[Math.max(0, Math.min(slots.length - 1, slotIndex || 0))]?.label || '',
            });
        });

        byDate.forEach((items) => {
            items.sort((a, b) => a.date.getTime() - b.date.getTime());
        });

        return byDate;
    }

    function setCalendarRangeLabel(start, end, viewMode) {
        const formatter = new Intl.DateTimeFormat([], { month: 'short', day: 'numeric', year: 'numeric' });
        if (viewMode === 'month') {
            calendarRangeLabel.textContent = `${start.toLocaleString([], { month: 'long', year: 'numeric' })}`;
            return;
        }
        if (viewMode === 'week') {
            if (!end) return;
            calendarRangeLabel.textContent = `${formatter.format(start)} - ${formatter.format(end)}`;
            return;
        }
        calendarRangeLabel.textContent = formatter.format(start);
    }

    function renderCalendarView() {
        if (!calendarViewRoot || !calendarRangeLabel) return;
        const appointmentsByDate = getCalendarAppointments();
        const slots = calendarSlots();
        const now = new Date();
        const safeNow = toDateKey(calendarDayStart(now));
        const currentSlot = Math.floor(((now.getHours() * 60 + now.getMinutes()) - (calendarOpenHour * 60)) / calendarSlotMinutes);
        const isCurrentSlot = (slotIndex, dayDate) => {
            if (typeof slotIndex !== 'number' || slotIndex < 0) return false;
            if (toDateKey(dayDate) !== safeNow) return false;
            if (currentSlot < 0 || currentSlot >= slots.length) return false;
            return slotIndex === currentSlot;
        };

        if (calendarView === 'month') {
            const monthStart = calendarDayStart(calendarAnchorDate);
            const firstOfMonth = new Date(monthStart.getFullYear(), monthStart.getMonth(), 1);
            const monthStartDay = calendarWeekStart(firstOfMonth);
            const totalDays = 42;
            const dayHeaders = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            const monthDays = Array.from({ length: totalDays }).map((_, i) => {
                const dayDate = new Date(monthStartDay);
                dayDate.setDate(dayDate.getDate() + i);
                const key = toDateKey(dayDate);
                return {
                    date: dayDate,
                    key,
                    isCurrentMonth: dayDate.getMonth() === monthStart.getMonth(),
                    entries: appointmentsByDate.get(key) || [],
                };
            });

            setCalendarRangeLabel(firstOfMonth);
            if (calendarSubtitle) calendarSubtitle.textContent = `Month view | ${monthStart.getMonth() + 1}/${monthStart.getFullYear()}`;

            calendarViewRoot.innerHTML = `
                <div class="grid grid-cols-7 gap-2">
                    ${dayHeaders.map((label) => `<div class="text-center text-xs font-semibold text-slate-600 py-2">${label}</div>`).join('')}
                </div>
                <div class="mt-2 grid grid-cols-7 gap-2">
                    ${monthDays.map((dayItem) => {
                        const isSelectedMonth = dayItem.isCurrentMonth ? '' : 'opacity-40';
                        const isToday = dayItem.key === safeNow ? 'ring-2 ring-blue-500' : '';
                        const title = `${dayItem.date.toLocaleDateString([], { month: 'short', day: 'numeric' })}`;
                        const body = dayItem.entries.map((entry) => `
                            <button
                                type="button"
                                class="mb-1 inline-flex w-full rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-left text-[11px]"
                                data-calendar-lead-id="${entry.leadId}"
                            >
                                <span class="truncate">${entry.timeLabel} · ${entry.name}</span>
                            </button>
                        `).join('') || '<p class="text-[11px] text-slate-400">No appointments</p>';
                        return `
                            <div class="min-h-44 rounded-xl border border-slate-200 bg-white p-2 ${isSelectedMonth} ${isToday}">
                                <p class="text-xs font-semibold text-slate-700">${title}</p>
                                <div class="mt-2 space-y-1">${body}</div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
            return;
        }

        const slotsRows = slots.map((slot) => `
            <div class="contents">
                <p class="py-2 pr-2 text-right text-xs font-medium text-slate-500 border-b border-slate-200">${slot.label}</p>
            </div>
        `).join('');

        if (calendarView === 'week') {
            const weekStart = calendarWeekStart(calendarAnchorDate);
            const weekDays = Array.from({ length: 7 }).map((_, i) => {
                const dayDate = new Date(weekStart);
                dayDate.setDate(dayDate.getDate() + i);
                return {
                    date: dayDate,
                    key: toDateKey(dayDate),
                };
            });
            const fromDate = new Date(weekDays[0].date);
            const toDate = new Date(weekDays[6].date);
            const weekNow = new Date(safeNow ? `${safeNow}T00:00:00` : now.toISOString());
            setCalendarRangeLabel(fromDate, toDate, 'week');
            if (calendarSubtitle) calendarSubtitle.textContent = `Week view | 30-minute blocks (${calendarOpenHour}:00-${calendarCloseHour}:00)`;

            calendarViewRoot.innerHTML = `
                <div class="overflow-x-auto">
                    <div class="min-w-[760px]">
                        <div class="grid auto-cols-fr gap-2" style="grid-template-columns: 88px repeat(7, minmax(0, 1fr));">
                            <div></div>
                            ${weekDays.map((dayItem) => {
                                const dayLabel = `${dayItem.date.toLocaleDateString([], { weekday: 'short' })} ${dayItem.date.getMonth() + 1}/${dayItem.date.getDate()}`;
                                const isCurrent = toDateKey(dayItem.date) === safeNow ? 'border-blue-500 bg-blue-50' : 'bg-white';
                                return `<div class="rounded-lg border border-slate-200 p-2 text-center text-xs font-semibold text-slate-700 ${isCurrent}">${dayLabel}</div>`;
                            }).join('')}
                        </div>
                        ${slots.map((slot) => `
                        <div class="mt-2 grid gap-2" style="grid-template-columns: 88px repeat(7, minmax(0, 1fr));">
                                <p class="py-2 pr-2 text-right text-[11px] font-medium text-slate-500 ${isCurrentSlot(slot.index, weekNow) ? 'rounded-md bg-blue-50 text-blue-700' : ''}">${slot.label}</p>
                                ${weekDays.map((dayItem) => {
                                    const dayEntries = (appointmentsByDate.get(dayItem.key) || []).filter((entry) => entry.slotIndex === slot.index);
                                    return `<div class="min-h-14 rounded-lg border border-slate-200 bg-white p-2 space-y-1">
                                        ${dayEntries.map((entry) => `
                                            <button
                                                type="button"
                                                class="w-full rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-left text-[11px]"
                                                data-calendar-lead-id="${entry.leadId}"
                                            >
                                                ${entry.name}
                                            </button>
                                        `).join('') || '<p class="text-[11px] text-slate-400">--</p>'}
                                    </div>`;
                                }).join('')}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            return;
        }

        const todayDate = calendarDayStart(calendarAnchorDate);
        const todayEntries = appointmentsByDate.get(toDateKey(todayDate)) || [];
        setCalendarRangeLabel(todayDate, null, 'day');
        if (calendarSubtitle) calendarSubtitle.textContent = `Day view | ${todayDate.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' })} (${calendarOpenHour}:00-${calendarCloseHour}:00)`;
        calendarViewRoot.innerHTML = `
            <div class="space-y-2">
                ${slots.map((slot) => {
                    const rows = todayEntries.filter((entry) => entry.slotIndex === slot.index);
                    return `
                        <div class="rounded-xl border border-slate-200 ${isCurrentSlot(slot.index, todayDate) ? 'ring-2 ring-blue-500 bg-blue-50/40' : 'bg-white'}">
                            <div class="grid grid-cols-[90px_minmax(0,1fr)]">
                                <p class="border-r border-slate-200 px-3 py-2 text-xs font-medium text-slate-600">${slot.label}</p>
                                <div class="px-2 py-2">
                                    ${rows.length
                                        ? rows.map((entry) => `
                                            <button
                                                type="button"
                                                class="inline-flex w-full rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-left text-sm"
                                                data-calendar-lead-id="${entry.leadId}"
                                            >
                                                <span class="font-semibold">${entry.timeLabel}</span>
                                                <span class="ml-2 text-slate-700">${entry.name}</span>
                                                <span class="ml-2 text-xs text-slate-500">(${entry.preferredContact})</span>
                                            </button>
                                        `).join('')
                                        : '<p class="text-xs text-slate-400">No appointment booked</p>'
                                    }
                                </div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    function isCalendarOpen() {
        return !!(calendarOverlay && !calendarOverlay.classList.contains('hidden'));
    }

    function persistCalendarState() {
        if (!window.sessionStorage || !calendarOverlay) {
            return;
        }

        try {
            const state = {
                isOpen: isCalendarOpen(),
                view: calendarView,
                anchorDate: calendarAnchorDate ? calendarAnchorDate.toISOString() : null,
            };
            window.sessionStorage.setItem(calendarStateStorageKey, JSON.stringify(state));
        } catch (error) {
            return;
        }
    }

    function openCalendarPanel() {
        if (!calendarOverlay) return;
        calendarOverlay.classList.remove('hidden');
        persistCalendarState();
        renderCalendarView();
    }

    function closeCalendarPanel() {
        if (!calendarOverlay) return;
        calendarOverlay.classList.add('hidden');
        persistCalendarState();
    }

    function shiftCalendarRange(direction) {
        if (calendarView === 'month') {
            const next = new Date(calendarAnchorDate);
            next.setMonth(next.getMonth() + direction);
            calendarAnchorDate = calendarDayStart(next);
        } else if (calendarView === 'week') {
            const next = new Date(calendarAnchorDate);
            next.setDate(next.getDate() + direction * 7);
            calendarAnchorDate = calendarDayStart(next);
        } else {
            const next = new Date(calendarAnchorDate);
            next.setDate(next.getDate() + direction);
            calendarAnchorDate = calendarDayStart(next);
        }
        persistCalendarState();
        renderCalendarView();
    }

    function setCalendarView(mode) {
        if (!mode) return;
        calendarView = mode;
        calendarViewButtons.forEach((btn) => {
            const isActive = btn.dataset.calendarView === calendarView;
            const viewMode = btn.dataset.calendarView;
            const rounded = viewMode === 'day' ? 'rounded-l-xl' : (viewMode === 'month' ? 'rounded-r-xl' : '');
            const border = viewMode === 'week' ? 'border-x border-slate-200' : 'border border-slate-200';
            const state = isActive
                ? 'bg-slate-900 text-white'
                : 'bg-white text-slate-700 hover:bg-slate-100';
            btn.className = `pipeline-calendar-view-btn px-3 py-2 text-xs font-semibold transition ${rounded} ${border} ${state}`;
        });
        persistCalendarState();
        renderCalendarView();
    }

    function refreshCalendarForChanges() {
        if (calendarOverlay && !calendarOverlay.classList.contains('hidden')) {
            renderCalendarView();
        }
    }

    function jumpCalendarToToday() {
        const now = new Date();
        now.setHours(0, 0, 0, 0);
        calendarAnchorDate = now;
        persistCalendarState();
        renderCalendarView();
    }

    function formatAppointmentForCard(value) {

        const normalized = toDatetimeLocal(value);

        if (!normalized) return '';

        const date = new Date(normalized);

        if (Number.isNaN(date.getTime())) return '';

        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });

    }



    function applyTemplateTokens(body, card) {

        const fullName = (card?.dataset.leadName || '').trim();

        const firstName = fullName.split(/\s+/)[0] || 'there';

        return String(body || '')

            .replaceAll('{first_name}', firstName)

            .replaceAll('{full_name}', fullName || firstName)

            .replaceAll('{appointment_time}', formatAppointmentForCard(card?.dataset.leadConsultationDate || '') || card?.dataset.leadSchedulingPreferredTime || 'your appointment time');

    }



    function defaultSmsMessage(card) {

        return applyTemplateTokens(smsTemplates?.first_follow_up?.body || '', card);

    }

    function defaultEmailBody(card) {

        return applyTemplateTokens([
            'Hi {first_name},',
            '',
            'This is the Elite Smiles team. I wanted to follow up on your consultation request.',
            '',
            'The consultation with Dr. Meden is free, and it gives us a chance to evaluate your case properly, review your options, and go over pricing and financing based on what you actually need. 0% interest may be available for qualified patients.',
            '',
            'Would mornings or afternoons usually work better for you to come in?',
            '',
            'Warmly,',
            'The Elite Smiles Team',
            '(801) 572-6262',
        ].join('\n'), card);

    }

    function setComposerMode(mode) {

        composerMode = ['email', 'sms', 'note'].includes(mode) ? mode : 'sms';

        composerModeButtons.forEach((button) => {
            const isActive = button.dataset.composerMode === composerMode;
            button.className = isActive
                ? 'composer-mode-button rounded-full border border-slate-900 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white'
                : 'composer-mode-button rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600';
        });

        composerPanels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.composerPanel !== composerMode);
        });

        composerModeButtons.forEach((button) => {
            const isActive = button.dataset.composerMode === composerMode;
            button.className = isActive
                ? 'composer-mode-button text-xs font-semibold text-slate-950 underline decoration-blue-500 decoration-2 underline-offset-4'
                : 'composer-mode-button text-xs font-semibold text-slate-500 transition hover:text-slate-900';
        });

        if (aiInstructionPanel) {
            aiInstructionPanel.classList.add('hidden');
        }

        refreshComposerSafetyCue();

    }



    function setComposerCollapsed(collapsed) {

        if (!composerBody) return;

        const shouldCollapse = Boolean(collapsed);

        composerBody.classList.toggle('hidden', shouldCollapse);

        if (leadCommunicationComposerPanel) {
            leadCommunicationComposerPanel.classList.toggle('h-[300px]', !shouldCollapse);
            leadCommunicationComposerPanel.classList.toggle('h-auto', shouldCollapse);
            leadCommunicationComposerPanel.classList.toggle('min-h-[58px]', shouldCollapse);
        }

        if (composerCollapseToggle) {
            composerCollapseToggle.textContent = shouldCollapse ? 'Open' : 'Collapse';
            composerCollapseToggle.setAttribute('aria-expanded', shouldCollapse ? 'false' : 'true');
        }

        window.setTimeout(function () {
            applyCommunicationViewportFit();
        }, 0);

    }



    function updateCardStagePill(card, label) {
        const pills = card.querySelectorAll('span.inline-flex.rounded-full.border');

        if (pills.length > 0) pills[0].textContent = label;

    }



    function updateCardValuePreview(card, amount) {

        const valueRow = card.querySelector('.lead-card-value-preview');

        if (!valueRow) return;

        const valueText = valueRow.querySelector('[data-role="lead-card-value-text"]');

        if (valueText) valueText.textContent = formatMoney(amount);

    }



    function updateCardAppointmentPreview(card, appointmentDate) {

        if (!card) return;

        const label = formatAppointmentForCard(appointmentDate);

        let preview = card.querySelector('.lead-card-appointment-preview');

        if (!label) {

            if (preview) preview.remove();

            refreshCalendarForChanges();
            return;

        }

        if (!preview) {

            const serviceBox = card.querySelector('.mt-3.rounded-xl.border.border-slate-200.bg-slate-50');

            preview = document.createElement('div');

            preview.className = 'lead-card-appointment-preview mt-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2';

            preview.innerHTML = '<p class="text-[10px] uppercase tracking-[0.14em] text-emerald-700">Scheduled Consultation</p><p class="mt-1 truncate text-sm font-semibold text-emerald-900"></p>';

            if (serviceBox && serviceBox.parentNode) {

                serviceBox.insertAdjacentElement('afterend', preview);

            } else {

                card.appendChild(preview);

            }

        }

        const valueEl = preview.querySelector('p:last-child');

        if (valueEl) valueEl.textContent = label;
        refreshCalendarForChanges();

    }



    function updateCardIdentityPreview(card, fullName, phone, email) {
        const nameEl = card.querySelector('p.text-\\[15px\\].font-semibold.leading-5.text-slate-900');

        const contactEls = card.querySelectorAll('p.text-\\[12px\\]');



        if (nameEl) {

            nameEl.textContent = fullName && fullName.trim() !== '' ? fullName : 'Unnamed Lead';

        }



        const contactLine = [];

        if (phone && phone.trim() !== '') contactLine.push(phone.trim());

        if (email && email.trim() !== '') contactLine.push(email.trim());



        const likelyContactEl = Array.from(contactEls).find((el) => el.classList.contains('text-slate-600') || el.classList.contains('text-slate-400'));

        if (likelyContactEl) {

            likelyContactEl.textContent = contactLine.length ? contactLine.join(' / ') : 'No phone or email yet';

            likelyContactEl.classList.remove('text-slate-600', 'text-slate-400');

            likelyContactEl.classList.add(contactLine.length ? 'text-slate-600' : 'text-slate-400');

        }

    }



    function updateCardServicePreview(card, procedure) {

        const blocks = card.querySelectorAll('.rounded-xl.border.border-slate-200.bg-slate-50');

        const serviceBlock = Array.from(blocks).find((block) => {

            const label = block.querySelector('p.text-\\[10px\\]');

            return label && label.textContent.trim().toLowerCase() === 'service needed';

        });

        if (!serviceBlock) return;

        const valueEl = serviceBlock.querySelector('p.text-sm.font-medium.text-slate-800');

        if (valueEl) valueEl.textContent = procedure && procedure.trim() !== '' ? procedure : 'Service not set';

    }



    function updateCardMetaBadges(card, missingCount) {

        const missingBadge = Array.from(card.querySelectorAll('span')).find((span) => span.textContent.trim().toLowerCase().startsWith('missing'));

        if (!missingBadge) return;

        if (missingCount > 0) {

            missingBadge.textContent = 'Missing ' + String(missingCount);

            missingBadge.classList.remove('hidden');

        } else {

            missingBadge.classList.add('hidden');

        }

    }



    function updateCardNotesPreview(card, notes) {

        const notesBox = card.querySelector('.lead-card-notes-preview');

        if (notesBox) notesBox.remove();

    }



    function buildNotesHistory(notes) {

        if (!notesHistory) return;



        const raw = (notes || '').trim();

        if (!raw) {

            notesHistory.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 bg-white/70 px-4 py-4 text-sm text-slate-500">No follow-up notes yet.</div>';

            return;

        }



        const parts = raw

            .split(/\n(?=--- Note added on )/g)

            .map((item) => item.trim())

            .filter(Boolean);



        if (!parts.length) {

            notesHistory.innerHTML = '<div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm text-slate-600 whitespace-pre-wrap"></div>';

            const only = notesHistory.querySelector('div');

            if (only) only.textContent = raw;

            return;

        }



        const html = parts.reverse().map((entry) => {

            const lines = entry.split('\n');

            const header = lines.shift() || '';

            const body = lines.join('\n').trim();



            return `

                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">

                    <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">${escapeHtml(header.replace(/^---\s*|\s*---$/g, ''))}</p>

                    <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">${escapeHtml(body || '(No note body)')}</p>

                </div>

            `;

        }).join('');



        notesHistory.innerHTML = html;

    }



    function escapeHtml(str) {

        return String(str)

            .replaceAll('&', '&amp;')

            .replaceAll('<', '&lt;')

            .replaceAll('>', '&gt;')

            .replaceAll('"', '&quot;')

            .replaceAll("'", '&#039;');

    }



    function formatThreadTime(value) {

        if (!value) return '';

        const date = new Date(String(value).replace(' ', 'T'));

        if (Number.isNaN(date.getTime())) return String(value);

        return date.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });

    }



    function activityLabel(type) {

        const labels = {

            sms_inbound: 'Inbound SMS',

            sms_outbound: 'Outbound SMS',

            sms_opt_out: 'SMS Stop',

            sms_opt_in: 'SMS Start',

            sms_help: 'SMS Help',

            sms_delivery_issue: 'Delivery Issue',

            email_inbound: 'Inbound Email',

            email_outbound: 'Outbound Email',

            email_failed: 'Email Failed',

            email_unsubscribe: 'Email Opt-Out',

            ai_email_draft: 'AI Email Draft',

            ai_draft: 'AI Draft',

            stage_change: 'Stage Change',

            operator_follow_up: 'Operator Follow-Up',

            manual_sms_followup_prepared: 'Manual SMS Prepared',

            lead_updated: 'Lead Updated',

            lead_created: 'Lead Created',

            follow_up_check: 'Follow-Up Check',

            note: 'Note'

        };

        return labels[type] || String(type || 'Activity').replaceAll('_', ' ');

    }


    function unifiedTimelineActivityItem(activity) {

        const type = String(activity?.type || '');

        const communicationMarkers = {
            operator_follow_up: 'Operator Follow-Up',
            manual_sms_followup_prepared: 'Manual SMS Prepared',
            attempted_contact_push: 'Attempted Contact Alert',
            email_failed: 'Email Failed',
            sms_delivery_issue: 'Delivery Issue',
            email_unsubscribe: 'Email Opt-Out',
            sms_opt_out: 'SMS Stop',
            sms_opt_in: 'SMS Start',
            sms_help: 'SMS Help',
        };

        if (!communicationMarkers[type]) {
            return null;
        }

        const failed = type.includes('failed') || type.includes('issue');
        const opted = type.includes('opt') || type.includes('unsubscribe') || type.includes('help');

        return {
            type: communicationMarkers[type],
            tone: failed ? 'rose' : (opted ? 'amber' : 'slate'),
            time: activity.created_at || '',
            title: communicationMarkers[type],
            body: '',
            meta: [
                activity.created_by ? 'By ' + activity.created_by : '',
                'Details in Internal Activity',
            ].filter(Boolean).join(' | '),
        };

    }



    function setSmsOptUi(status) {

        const normalized = String(status || 'unknown').toLowerCase();
        const safeStatus = ['unknown', 'opted_in', 'opted_out'].includes(normalized) ? normalized : 'unknown';

        smsOptStatusInputs.forEach((input) => {
            input.checked = input.value === safeStatus;
        });

        if (smsOptStatus) {

            smsOptStatus.className = 'mt-3 inline-flex rounded-full border px-3 py-1 text-xs font-semibold';

            if (safeStatus === 'opted_out') {

                smsOptStatus.textContent = 'SMS opted out';

                smsOptStatus.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-700');

            } else if (safeStatus === 'opted_in') {

                smsOptStatus.textContent = 'SMS opted in';

                smsOptStatus.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');

            } else {

                smsOptStatus.textContent = 'SMS status unknown';

                smsOptStatus.classList.add('border-slate-200', 'bg-slate-50', 'text-slate-600');

            }

        }

        const legacySmsOptStatus = document.getElementById('legacy-modal-sms-opt-status');
        if (legacySmsOptStatus) {
            legacySmsOptStatus.className = 'mt-3 inline-flex rounded-full border px-3 py-1 text-xs font-semibold';
            if (safeStatus === 'opted_out') {
                legacySmsOptStatus.textContent = 'SMS opted out';
                legacySmsOptStatus.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-700');
            } else if (safeStatus === 'opted_in') {
                legacySmsOptStatus.textContent = 'SMS opted in';
                legacySmsOptStatus.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
            } else {
                legacySmsOptStatus.textContent = 'SMS status unknown';
                legacySmsOptStatus.classList.add('border-slate-200', 'bg-slate-50', 'text-slate-600');
            }
        }

        if (sendSmsButton) {

            sendSmsButton.disabled = safeStatus === 'opted_out' || isSendingSms || isSaving || isDeletingLead;

        }

        if (smsInput) {

            smsInput.disabled = safeStatus === 'opted_out';

            smsInput.placeholder = safeStatus === 'opted_out'

                ? 'This lead opted out of SMS. Do not text unless they opt back in.'

                : 'Write a polished text to this lead...';

        }

        if (smsDndSummary) {
            if (safeStatus === 'opted_out') {
                smsDndSummary.textContent = 'Do Not Text';
            } else if (safeStatus === 'opted_in') {
                smsDndSummary.textContent = 'Can text';
            } else {
                smsDndSummary.textContent = 'Unknown';
            }
        }

        const legacySmsDndSummary = document.getElementById('legacy-modal-sms-dnd-summary');
        if (legacySmsDndSummary) {
            legacySmsDndSummary.textContent = safeStatus === 'opted_out'
                ? 'Do Not Text'
                : (safeStatus === 'opted_in' ? 'Can text' : 'Unknown');
        }

        refreshAiDraftUi();

    }

    function refreshComposerSafetyCue() {
        if (!composerSafetyCue) return;

        if (composerMode === 'note') {
            composerSafetyCue.textContent = 'Internal notes are not sent to the lead. Save notes for CRM context.';
            return;
        }

        const modeLabel = composerMode === 'email' ? 'Email' : 'SMS';
        const source = composerDraftSources[composerMode] || 'manual';
        const phoneSafe = String(activeCard?.dataset?.leadSmsOptStatus || 'unknown').toLowerCase();

        if (composerMode === 'sms' && phoneSafe === 'opted_out') {
            composerSafetyCue.textContent = 'This lead is opted out of SMS. Do not send a text without permission.';
            return;
        }

        if (source === 'ai') {
            composerSafetyCue.textContent = `${modeLabel} draft is from AI. Please review and send only after confirming details.`;
            return;
        }

        composerSafetyCue.textContent = `${modeLabel} compose is manual. Review message carefully before send.`;
    }

    function setComposerDraftSource(mode, source) {
        if (!composerDraftSources[mode]) return;
        composerDraftSources[mode] = source === 'ai' ? 'ai' : 'manual';
        refreshComposerSafetyCue();
    }

    function applyPipelineBoardMobileMode() {

        if (!board || !pipelineMobileStageFilter) return;

        const isMobile = window.matchMedia('(max-width: 640px)').matches;
        const columns = Array.from(board.querySelectorAll('.pipeline-column[data-display-stage-key]'));

        if (!isMobile) {
            columns.forEach((column) => column.classList.remove('hidden'));
            return;
        }

        const selected = pipelineMobileStageFilter.value || '__all__';
        columns.forEach((column) => {
            const stageKey = column.dataset.displayStageKey || column.dataset.stageKey || '';
            const visible = selected === '__all__' || stageKey === selected;
            column.classList.toggle('hidden', !visible);
        });
    }

    function updateLeadIntelligencePanel() {

        if (!activeCard) {
            if (leadIntelSummaryText) leadIntelSummaryText.textContent = 'Open a lead to load summary.';
            if (leadIntelMissingList) leadIntelMissingList.innerHTML = '<li class="text-slate-500">No lead loaded.</li>';
            if (leadIntelNextAction) leadIntelNextAction.textContent = 'Open a lead.';
            if (leadIntelLastTouchpoint) leadIntelLastTouchpoint.textContent = 'No contact log yet.';
            return;
        }

        const data = activeCard.dataset || {};

        const fullName = (modalLeadNameInput?.value || data.leadName || 'Lead').trim();
        const stage = data.leadStageLabel || data.stageKey || 'Unmapped stage';
        const preferredContact = (modalLeadPreferredContactInput?.value || data.leadPreferredContact || '').trim();
        const email = (modalLeadEmailInput?.value || data.leadEmail || '').trim();
        const phone = (modalLeadPhoneInput?.value || data.leadPhone || '').trim();
        const procedure = (modalLeadProcedureInput?.value || data.leadProcedure || '').trim();
        const consult = (modalLeadConsultInput?.value || data.leadConsult || '').trim();
        const intentType = (modalLeadIntentTypeInput?.value || data.leadIntentType || '').trim();
        const dob = (modalLeadDobInput?.value || data.leadDateOfBirth || '').trim();

        const missing = [];
        if (!fullName) missing.push('Name');
        if (!phone) missing.push('Phone');
        if (!email) missing.push('Email');
        if (!procedure) missing.push('Service needed');
        if (!consult) missing.push('Consult status');
        if (!intentType) missing.push('Intention');
        if (!dob) missing.push('Date of birth');

        if (leadIntelSummaryText) {
            leadIntelSummaryText.textContent = `${fullName} • ${stage}`;
        }

        if (leadIntelMissingList) {
            leadIntelMissingList.innerHTML = missing.length
                ? missing.map((item) => `<li>${escapeHtml(item)}</li>`).join('')
                : '<li>No required item missing.</li>';
        }

        if (leadIntelNextAction) {
            if (!preferredContact && !modalLeadPreferredContactInput?.value) {
                leadIntelNextAction.textContent = 'Collect preferred contact method in Contact details.';
            } else if (!phone || !dob) {
                leadIntelNextAction.textContent = 'Collect contact detail fields before scheduling.';
            } else {
                leadIntelNextAction.textContent = preferredContact ? `Follow up by ${preferredContact}.` : 'Follow up and confirm consultation window.';
            }
        }

        if (leadIntelLastTouchpoint) {
            const lastTouchpoint = data.leadLastInboundAt || data.leadLastOutboundAt || data.leadLastContactedAt || '';
            leadIntelLastTouchpoint.textContent = lastTouchpoint ? formatThreadTime(lastTouchpoint) : 'No contact log yet.';
        }
    }

    function refreshAiDraftUi() {

        const hasLead = !!activeCard;

        const smsOptedOut = String(activeCard?.dataset?.leadSmsOptStatus || 'unknown').toLowerCase() === 'opted_out';

        const busy = isDraftingSms || isDraftingEmail || isDraftingBoth || isSendingSms || isSendingEmail || isSaving || isDeletingLead;

        if (draftSmsButton) {
            draftSmsButton.disabled = !hasLead || smsOptedOut || busy;
        }

        if (improveSmsButton) {
            improveSmsButton.disabled = !hasLead || smsOptedOut || busy;
        }

        if (draftEmailButton) {
            draftEmailButton.disabled = !hasLead || busy;
        }

        if (improveEmailButton) {
            improveEmailButton.disabled = !hasLead || busy;
        }

        if (draftBothButton) {
            draftBothButton.disabled = !hasLead || smsOptedOut || busy;
        }

        if (aiInstructionInput) {
            aiInstructionInput.disabled = busy;
        }

    }

    function setAiStatusMessage(message) {

        if (aiStatus) aiStatus.textContent = message || '';

    }

    function setAiInstructionCollapsed(collapsed) {

        if (!aiInstructionBody || !aiCollapseToggle) return;

        aiInstructionBody.classList.toggle('hidden', collapsed);
        aiCollapseToggle.textContent = collapsed ? 'Show' : 'Hide';
        aiCollapseToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

    }

    function getAiInstructionValue() {

        return aiInstructionInput ? aiInstructionInput.value.trim() : '';

    }

    function defaultAiInstruction(channel) {

        const leadName = activeCard?.dataset?.leadName || 'this lead';
        const stageLabel = activeCard?.dataset?.leadConversionStageLabel || activeCard?.dataset?.leadStageLabel || '';
        const consultationStatus = activeCard?.dataset?.leadConsult || '';
        const contextSummary = [
            stageLabel ? `Current stage: ${stageLabel}.` : '',
            consultationStatus ? `Consultation status: ${consultationStatus.replace(/_/g, ' ')}.` : '',
            'Read the latest timeline, replies, outbound touchpoints, and CRM notes before drafting.',
            'Continue the real conversation naturally instead of restarting it.',
            'Keep details accurate, avoid repeating what was already sent, and move toward the next best step.'
        ].filter(Boolean).join(' ');

        if (channel === 'sms') {
            return `Draft a warm, concise SMS follow-up for ${leadName}. ${contextSummary} Keep it short, natural, and easy to answer by text.`;
        }

        if (channel === 'email') {
            return `Draft a warm, professional follow-up email for ${leadName}. ${contextSummary} Make it feel personal, clear, and aligned with the conversation so far.`;
        }

        return `Draft both a warm SMS and a warm follow-up email for ${leadName}. ${contextSummary} Keep both channels aligned, but adapt each one to its format naturally.`;

    }

    function buildImproveInstruction(channel) {

        const extraInstruction = getAiInstructionValue();

        if (channel === 'sms') {
            const currentMessage = smsInput ? smsInput.value.trim() : '';

            if (!currentMessage) return '';

            return [
                'Improve the following SMS so it sounds warm, friendly, professional, and grammatically perfect.',
                'Keep the core meaning, keep it concise, and make it feel natural for a real patient conversation.',
                extraInstruction !== '' ? 'Operator instruction: ' + extraInstruction : '',
                'Current SMS:',
                currentMessage,
            ].filter(Boolean).join('\n\n');
        }

        const currentSubject = emailSubjectInput ? emailSubjectInput.value.trim() : '';
        const currentBody = emailBodyInput ? emailBodyInput.value.trim() : '';

        if (!currentSubject && !currentBody) return '';

        return [
            'Improve the following patient email so it sounds warm, friendly, professional, and grammatically perfect.',
            'Keep the core meaning, make it read naturally, and preserve the intent of the draft.',
            extraInstruction !== '' ? 'Operator instruction: ' + extraInstruction : '',
            'Current subject:',
            currentSubject || '(no subject)',
            'Current email body:',
            currentBody || '(no body)',
        ].filter(Boolean).join('\n\n');

    }

    function applyDraftedFollowUp(value) {

        const normalized = String(value || '').trim();

        if (!normalized || !modalLeadNextFollowUpInput) return;

        modalLeadNextFollowUpInput.value = toDatetimeLocal(normalized);

    }

    async function ensureLeadReadyForAi(channel) {

        if (!activeCard || isDeletingLead || isSaving) return null;

        const leadId = activeCard.dataset.leadId || '';

        const phone = modalLeadPhoneInput ? modalLeadPhoneInput.value.trim() : (activeCard.dataset.leadPhone || '');

        const email = modalLeadEmailInput ? modalLeadEmailInput.value.trim() : (activeCard.dataset.leadEmail || '');

        if (!leadId) {
            if (channel === 'email') {
                if (emailStatus) emailStatus.textContent = 'Could not determine which lead to email.';
            } else if (channel === 'sms') {
                if (smsStatus) smsStatus.textContent = 'Could not determine which lead to text.';
            } else {
                setAiStatusMessage('Could not determine which lead to draft for.');
            }
            return null;
        }

        if ((channel === 'sms' || channel === 'both') && !phone) {
            if (smsStatus) smsStatus.textContent = 'Add a lead phone number before drafting.';
            if (channel === 'both') setAiStatusMessage('Add a lead phone number before drafting both messages.');
            return null;
        }

        if ((channel === 'email' || channel === 'both') && !email) {
            if (emailStatus) emailStatus.textContent = 'Add an email address before drafting.';
            if (channel === 'both') setAiStatusMessage('Add an email address before drafting both messages.');
            return null;
        }

        if ((channel === 'sms' || channel === 'both') && String(activeCard.dataset.leadSmsOptStatus || 'unknown').toLowerCase() === 'opted_out') {
            if (smsStatus) smsStatus.textContent = 'This lead opted out of SMS. Do not draft or send texts unless they opt back in.';
            if (channel === 'both') setAiStatusMessage('This lead opted out of SMS, so Draft Both is unavailable.');
            return null;
        }

        if (isDirty()) {
            if (channel === 'email') {
                if (emailStatus) emailStatus.textContent = 'Saving lead details before drafting...';
            } else if (channel === 'sms') {
                if (smsStatus) smsStatus.textContent = 'Saving lead details before drafting...';
            } else {
                setAiStatusMessage('Saving lead details before drafting...');
            }

            const saved = await saveLeadDetails();
            if (!saved) {
                if (channel === 'email') {
                    if (emailStatus) emailStatus.textContent = 'Save the lead details before drafting email.';
                } else if (channel === 'sms') {
                    if (smsStatus) smsStatus.textContent = 'Save the lead details before drafting SMS.';
                } else {
                    setAiStatusMessage('Save the lead details before drafting.');
                }
                return null;
            }
        }

        return { leadId, phone, email };

    }

    async function requestSmsDraft(leadId, instruction, mode = 'operator_follow_up_sms') {

        const formData = new FormData();
        formData.append('_csrf_token', csrfToken);
        formData.append('lead_id', leadId);
        formData.append('mode', mode);
        formData.append('instruction', instruction);

        const response = await fetch(smsDraftUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await parseJsonResponse(response);
        if (!response.ok || !data.ok) throw new Error(data.message || 'Failed to draft SMS.');
        return data;

    }

    async function requestEmailDraft(leadId, instruction, mode = 'operator_follow_up_email') {

        const formData = new FormData();
        formData.append('_csrf_token', csrfToken);
        formData.append('lead_id', leadId);
        formData.append('mode', mode);
        formData.append('instruction', instruction);

        const response = await fetch(emailDraftUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await parseJsonResponse(response);
        if (!response.ok || !data.ok) throw new Error(data.message || 'Failed to draft email.');
        return data;

    }



    function threadTimeValue(value) {

        return new Date(String(value || '').replace(' ', 'T')).getTime() || 0;

    }



    function sortThreadChronologically(items, timeKey = 'created_at') {

        if (!Array.isArray(items)) return [];

        return [...items].sort((a, b) => {

            const aTime = threadTimeValue(a?.[timeKey] || '');

            const bTime = threadTimeValue(b?.[timeKey] || '');

            if (aTime !== bTime) return aTime - bTime;

            const aId = Number(a?.id || 0);

            const bId = Number(b?.id || 0);

            return aId - bId;

        });

    }



    function scrollThreadPaneToBottom(element) {

        if (!element) return;

        requestAnimationFrame(() => {

            element.scrollTop = element.scrollHeight;

        });

    }



    function renderMessageThread(messages) {

        if (!messageThread) return;

        if (!Array.isArray(messages) || messages.length === 0) {

            messageThread.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">No SMS messages logged yet.</div>';

            return;

        }

        const orderedMessages = sortThreadChronologically(messages);

        messageThread.innerHTML = orderedMessages.map((message) => {

            const isOutbound = String(message.direction || '') === 'outbound';

            const bubbleClass = isOutbound ? 'ml-auto border-blue-100 bg-blue-50 text-blue-950' : 'mr-auto border-emerald-100 bg-emerald-50 text-emerald-950';

            const meta = [

                isOutbound ? 'Rod to lead' : 'Lead reply',

                formatThreadTime(message.created_at || ''),

                message.twilio_status ? String(message.twilio_status) : ''

            ].filter(Boolean).join(' | ');

            return `

                <div class="max-w-[88%] rounded-2xl border px-4 py-3 text-sm leading-6 ${bubbleClass}">

                    <p class="whitespace-pre-wrap">${escapeHtml(message.body || '')}</p>

                    <p class="mt-2 text-[11px] font-medium opacity-70">${escapeHtml(meta)}</p>

                </div>

            `;

        }).join('');

        scrollThreadPaneToBottom(messageThread);

    }



    function renderActivityFeed(activities) {

        if (!activityFeed) return;

        if (!Array.isArray(activities) || activities.length === 0) {

            activityFeed.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">No activity logged yet.</div>';

            return;

        }

        const orderedActivities = sortThreadChronologically(activities).reverse();
        const collapsedEmailTypes = new Set(['email_outbound', 'email_inbound', 'ai_email_draft', 'email_opened']);
        const isCollapsedEmailActivity = (activity) => collapsedEmailTypes.has(String(activity?.type || ''));
        const primaryActivities = orderedActivities.filter((activity) => !isCollapsedEmailActivity(activity));
        const emailActivities = orderedActivities.filter(isCollapsedEmailActivity);

        const activityTone = (type) => {
            const normalized = String(type || '');
            if (normalized.includes('failed') || normalized.includes('issue') || normalized.includes('undelivered')) {
                return {
                    marker: 'border-rose-200 bg-rose-50 text-rose-700',
                    card: 'border-rose-100 bg-rose-50/70',
                    title: 'text-rose-800',
                    pill: 'border-rose-200 bg-white text-rose-700',
                    dot: 'bg-rose-500',
                };
            }
            if (normalized.includes('survey') || normalized.includes('form') || normalized.includes('created')) {
                return {
                    marker: 'border-blue-200 bg-blue-50 text-blue-700',
                    card: 'border-blue-100 bg-white',
                    title: 'text-slate-900',
                    pill: 'border-blue-100 bg-blue-50 text-blue-700',
                    dot: 'bg-blue-500',
                };
            }
            if (normalized.includes('note') || normalized.includes('follow') || normalized.includes('manual')) {
                return {
                    marker: 'border-amber-200 bg-amber-50 text-amber-700',
                    card: 'border-amber-100 bg-amber-50/60',
                    title: 'text-amber-900',
                    pill: 'border-amber-200 bg-white text-amber-700',
                    dot: 'bg-amber-500',
                };
            }
            if (normalized.includes('stage') || normalized.includes('move')) {
                return {
                    marker: 'border-violet-200 bg-violet-50 text-violet-700',
                    card: 'border-violet-100 bg-white',
                    title: 'text-slate-900',
                    pill: 'border-violet-100 bg-violet-50 text-violet-700',
                    dot: 'bg-violet-500',
                };
            }
            return {
                marker: 'border-slate-200 bg-slate-50 text-slate-600',
                card: 'border-slate-200 bg-white',
                title: 'text-slate-900',
                pill: 'border-slate-200 bg-slate-50 text-slate-600',
                dot: 'bg-slate-400',
            };
        };

        const activityBody = (activity) => {
            const type = String(activity?.type || '');
            const body = String(activity?.body || '');
            if (type === 'sms_delivery_issue') {
                const recovery = body.includes('30003')
                    ? 'Carrier reported undelivered. The phone may be unreachable, blocked, or unable to receive SMS right now.'
                    : 'SMS did not complete delivery. Review the phone number and consider email or manual follow-up.';
                return `${body}\n${recovery}`;
            }
            return body;
        };

        const renderActivityItem = (activity, compact = false) => {
            const type = String(activity?.type || '');
            const tone = activityTone(type);
            const label = activityLabel(type);
            const time = formatThreadTime(activity.created_at || '');
            const byline = activity.created_by ? 'By ' + activity.created_by : '';
            const body = activityBody(activity);
            const urgent = type.includes('failed') || type.includes('issue');

            return `
                <div class="relative pl-2">
                    <span class="absolute -left-[29px] top-1.5 flex h-7 w-7 items-center justify-center rounded-full border text-[11px] font-bold shadow-sm ${tone.marker}" aria-hidden="true">
                        <span class="h-2 w-2 rounded-full ${tone.dot}"></span>
                    </span>
                    <div class="rounded-2xl border px-3 py-3 shadow-sm ${tone.card}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold ${tone.title}">${escapeHtml(label)}</p>
                                ${urgent ? '<p class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] ' + tone.pill + '">Needs attention</p>' : ''}
                            </div>
                            <p class="text-[11px] font-medium text-slate-400">${escapeHtml(time)}</p>
                        </div>
                        ${body ? `<p class="mt-2 whitespace-pre-wrap text-${compact ? '[12px]' : 'sm'} leading-6 text-slate-700">${escapeHtml(body)}</p>` : ''}
                        ${byline ? `<p class="mt-2 text-[11px] font-medium text-slate-400">${escapeHtml(byline)}</p>` : ''}
                    </div>
                </div>
            `;
        };

        const primaryHtml = primaryActivities.length
            ? `<div class="relative ml-3 space-y-4 border-l border-slate-200 pl-5">${primaryActivities.map((activity) => renderActivityItem(activity)).join('')}</div>`
            : '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">No non-email activity logged yet.</div>';

        const emailHtml = emailActivities.length
            ? `
                <details class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <summary class="cursor-pointer text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Email activity (${emailActivities.length})</summary>
                    <div class="mt-4 ml-3 space-y-3 border-l border-slate-200 pl-5">
                        ${emailActivities.map((activity) => renderActivityItem(activity, true)).join('')}
                    </div>
                </details>
            `
            : '';

        activityFeed.innerHTML = primaryHtml + emailHtml;

        scrollThreadPaneToBottom(activityFeed);

    }

    function noteTimelineItems(notes) {
        const raw = String(notes || '').trim();
        if (!raw) return [];

        const parts = raw.split(/---\s*Note added on\s*/i).map((part) => part.trim()).filter(Boolean);
        if (!parts.length) {
            return [{
                type: 'Internal Note',
                tone: 'amber',
                time: '',
                title: 'Internal note',
                body: raw,
                meta: 'Saved in lead notes',
            }];
        }

        return parts.map((part) => {
            const lines = part.split(/\r?\n/);
            const header = String(lines.shift() || '').replace(/---$/, '').trim();
            const body = lines.join('\n').trim() || part;
            return {
                type: 'Internal Note',
                tone: 'amber',
                time: header,
                title: 'Internal note',
                body,
                meta: header ? 'Saved ' + header : 'Saved in lead notes',
            };
        });
    }

    function renderUnifiedTimeline(thread) {

        if (!unifiedTimeline) return;

        const items = [];

        (thread?.emails || []).forEach((email) => {
            const direction = String(email.direction || '') === 'inbound' ? 'Inbound Email' : 'Outbound Email';
            const opened = email.opened_at ? 'Opened ' + formatThreadTime(email.opened_at || '') : '';
            items.push({
                type: direction,
                tone: String(email.direction || '') === 'inbound' ? 'emerald' : 'blue',
                time: email.created_at || '',
                title: email.subject || '(no subject)',
                body: email.body || '',
                meta: [email.status || '', opened].filter(Boolean).join(' | '),
            });
        });

        (thread?.messages || []).forEach((message) => {
            const isOutbound = String(message.direction || '') === 'outbound';
            items.push({
                type: isOutbound ? 'Outbound SMS' : 'Inbound SMS',
                tone: isOutbound ? 'blue' : 'emerald',
                time: message.created_at || '',
                title: isOutbound ? 'Text sent' : 'Patient replied',
                body: message.body || '',
                meta: message.twilio_status || '',
            });
        });

        noteTimelineItems(activeCard?.dataset?.leadNotes || '').forEach((note) => {
            items.push(note);
        });

        (thread?.activities || []).forEach((activity) => {
            const item = unifiedTimelineActivityItem(activity);
            if (item) {
                items.push(item);
            }
        });

        items.sort((a, b) => {
            const aTime = threadTimeValue(a.time || '');
            const bTime = threadTimeValue(b.time || '');
            if (aTime !== bTime) return aTime - bTime;
            return Number(a.id || 0) - Number(b.id || 0);
        });

        if (!items.length) {
            unifiedTimeline.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">No communication history yet.</div>';
            return;
        }

        const toneClasses = {
            blue: 'border-blue-100 bg-blue-50 text-blue-950',
            emerald: 'border-emerald-100 bg-emerald-50 text-emerald-950',
            amber: 'border-amber-100 bg-amber-50 text-amber-950',
            rose: 'border-rose-100 bg-rose-50 text-rose-950',
            slate: 'border-slate-200 bg-slate-50 text-slate-800',
        };

        const iconClasses = {
            blue: 'border-blue-200 bg-blue-100 text-blue-700',
            emerald: 'border-emerald-200 bg-emerald-100 text-emerald-700',
            amber: 'border-amber-200 bg-amber-100 text-amber-700',
            rose: 'border-rose-200 bg-rose-100 text-rose-700',
            slate: 'border-slate-200 bg-white text-slate-500',
        };

        function timelineIcon(type) {
            const value = String(type || '').toLowerCase();
            if (value.includes('sms') || value.includes('text')) {
                return '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>';
            }
            if (value.includes('email')) {
                return '<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path><rect x="2" y="4" width="20" height="16" rx="2"></rect>';
            }
            if (value.includes('note') || value.includes('follow-up') || value.includes('prepared')) {
                return '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path><path d="M14 2v4a2 2 0 0 0 2 2h4"></path><path d="M16 13H8"></path><path d="M16 17H8"></path>';
            }
            return '<path d="M12 8v4l3 3"></path><circle cx="12" cy="12" r="10"></circle>';
        }

        unifiedTimeline.innerHTML = items.map((item) => `
            <div class="rounded-2xl border px-4 py-3 ${toneClasses[item.tone] || toneClasses.slate}">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border ${iconClasses[item.tone] || iconClasses.slate}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">${timelineIcon(item.type)}</svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] opacity-80">${escapeHtml(item.type || 'Activity')}</p>
                            <p class="text-[11px] opacity-70">${escapeHtml(formatThreadTime(item.time || ''))}</p>
                        </div>
                        <p class="mt-2 text-sm font-semibold">${escapeHtml(item.title || '')}</p>
                        ${item.body ? `<p class="mt-2 whitespace-pre-wrap text-sm leading-6">${escapeHtml(item.body || '')}</p>` : ''}
                        ${item.meta ? `<p class="mt-2 text-[11px] font-medium opacity-70">${escapeHtml(item.meta)}</p>` : ''}
                    </div>
                </div>
            </div>
        `).join('');

        scrollThreadPaneToBottom(unifiedTimeline);

    }

    function renderEmailHistory(emails) {

        if (!emailHistory) return;

        if (!Array.isArray(emails) || emails.length === 0) {

            emailHistory.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">No patient emails logged yet.</div>';

            return;

        }

        const orderedEmails = sortThreadChronologically(emails);

        emailHistory.innerHTML = orderedEmails.map((email) => `

            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">

                <div class="flex flex-wrap items-center justify-between gap-2">

                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">${escapeHtml(email.status || 'email')}</p>

                    <p class="text-[11px] text-slate-400">${escapeHtml(formatThreadTime(email.created_at || ''))}</p>

                </div>

                <p class="mt-2 text-sm font-semibold text-slate-900">${escapeHtml(email.subject || '(no subject)')}</p>

                <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">${escapeHtml(email.body || '')}</p>

                <p class="mt-2 text-[11px] font-medium text-slate-400">${escapeHtml(email.from_email || '')} to ${escapeHtml(email.to_email || '')}</p>

                ${email.opened_at ? `<p class="mt-1 text-[11px] font-semibold text-emerald-600">Opened ${escapeHtml(formatThreadTime(email.opened_at || ''))}</p>` : ''}

            </div>

        `).join('');

        scrollThreadPaneToBottom(emailHistory);

    }



    function clearUnreadBadge(card) {

        if (!card) return;

        card.dataset.leadUnreadMessageCount = '0';

        const badge = card.querySelector('.lead-unread-badge');

        if (badge) badge.remove();

    }



    function renderThreadSnapshot(thread) {

        renderUnifiedTimeline(thread || {});

        renderMessageThread(thread?.messages || []);

        renderActivityFeed(thread?.activities || []);

        renderEmailHistory(thread?.emails || []);

    }



    async function loadLeadThread() {

        if (!activeCard || !threadUrl) return false;

        const leadId = activeCard.dataset.leadId || '';

        if (!leadId) return false;

        if (messageThread) {

            messageThread.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">Loading SMS history...</div>';

        }

        if (activityFeed) {

            activityFeed.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">Loading activity...</div>';

        }

        if (loadThreadButton) loadThreadButton.disabled = true;

        try {

            const url = threadUrl + '?lead_id=' + encodeURIComponent(leadId);

            const response = await fetch(url, {

                method: 'GET',

                credentials: 'same-origin',

                headers: { 'X-Requested-With': 'XMLHttpRequest' }

            });

            const data = await parseJsonResponse(response);

            if (!response.ok || !data.ok) throw new Error(data.message || 'Failed to load SMS history.');

            renderThreadSnapshot(data.thread || {});

            if (data.sms_opt_status) {

                activeCard.dataset.leadSmsOptStatus = data.sms_opt_status;

                setSmsOptUi(data.sms_opt_status);

            }

            clearUnreadBadge(activeCard);

            return true;

        } catch (error) {

            if (messageThread) {

                messageThread.innerHTML = '<div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4 text-sm text-rose-700">' + escapeHtml(error.message || 'Failed to load SMS history.') + '</div>';

            }

            return false;

        } finally {

            if (loadThreadButton) loadThreadButton.disabled = false;

        }

    }



    function setActiveTab(tabName) {
        tabButtons.forEach((btn) => {

            const isActive = btn.dataset.tabTarget === tabName;

            btn.className = isActive

                ? 'workspace-tab-button text-sm font-semibold text-slate-950 underline decoration-blue-500 decoration-2 underline-offset-4'

                : 'workspace-tab-button text-sm font-semibold text-slate-500 transition hover:text-slate-900';

        });



        Object.entries(tabPanels).forEach(([key, panel]) => {

            if (!panel) return;

            panel.classList.toggle('hidden', key !== tabName);

        });

        window.setTimeout(function () {
            applyCommunicationViewportFit();
        }, 0);

    }



    function clearMissingHighlights() {

        [

            'wrap-modal-lead-name-input',

            'wrap-modal-lead-phone-input',

            'wrap-modal-lead-email-input',

            'wrap-modal-lead-preferred-contact-input',

            'wrap-modal-lead-procedure-input',

            'wrap-modal-lead-consult-input',

            'wrap-modal-lead-consultation-date-input'
        ].forEach((id) => {

            const el = document.getElementById(id);

            if (!el) return;

            el.classList.remove('ring-1', 'ring-amber-300', 'bg-amber-50/70');

        });

    }



    function markMissingWrap(id) {

        const el = document.getElementById(id);

        if (!el) return;

        el.classList.add('ring-1', 'ring-amber-300', 'bg-amber-50/70');

    }



    function missingField(label, target, input, tab = 'details') {

        return { label, target, input, tab };

    }



    function focusMissingField(targetId, inputId, tabName) {

        const target = document.getElementById(targetId);

        if (!target) return;

        setActiveTab(tabName || 'details');
        if ((tabName || 'details') === 'details') {
            setActiveDetailWindow(detailWindowForElement(targetId));
        }

        window.setTimeout(() => {

            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.classList.add('ring-2', 'ring-amber-400', 'bg-amber-50/80');

            const input = inputId ? document.getElementById(inputId) : target.querySelector('input, select, textarea, button');
            if (input && typeof input.focus === 'function') {
                input.focus({ preventScroll: true });
            }

            window.setTimeout(() => {
                target.classList.remove('ring-2', 'ring-amber-400', 'bg-amber-50/80');
                target.classList.add('ring-1', 'ring-amber-300', 'bg-amber-50/70');
            }, 1200);

        }, 80);

    }


    function updateMissingPanel() {

        if (!activeCard || !modalMissingPanel || !modalMissingList) return;



        clearMissingHighlights();



        const missing = [];

        const fullName = modalLeadNameInput ? modalLeadNameInput.value.trim() : '';

        const phone = modalLeadPhoneInput ? modalLeadPhoneInput.value.trim() : '';

        const email = modalLeadEmailInput ? modalLeadEmailInput.value.trim() : '';

        const preferredContact = modalLeadPreferredContactInput ? modalLeadPreferredContactInput.value.trim() : '';

        const procedure = modalLeadProcedureInput ? modalLeadProcedureInput.value.trim() : '';

        const consult = modalLeadConsultInput ? modalLeadConsultInput.value.trim() : '';

        const consultationDate = modalLeadConsultationDateInput ? modalLeadConsultationDateInput.value.trim() : '';


        if (!fullName) { missing.push(missingField('Name', 'wrap-modal-lead-name-input', 'modal-lead-name-input')); markMissingWrap('wrap-modal-lead-name-input'); }

        if (!phone) { missing.push(missingField('Phone', 'wrap-modal-lead-phone-input', 'modal-lead-phone-input')); markMissingWrap('wrap-modal-lead-phone-input'); }

        if (!email) { missing.push(missingField('Email', 'wrap-modal-lead-email-input', 'modal-lead-email-input')); markMissingWrap('wrap-modal-lead-email-input'); }

        if (!preferredContact) { missing.push(missingField('Preferred Contact', 'wrap-modal-lead-preferred-contact-input', 'modal-lead-preferred-contact-input')); markMissingWrap('wrap-modal-lead-preferred-contact-input'); }

        if (!procedure) { missing.push(missingField('Service Needed', 'wrap-modal-lead-procedure-input', 'modal-lead-procedure-input')); markMissingWrap('wrap-modal-lead-procedure-input'); }

        if (!consult) { missing.push(missingField('Consultation Status', 'wrap-modal-lead-consult-input', 'modal-lead-consult-input')); markMissingWrap('wrap-modal-lead-consult-input'); }

        if (consult === 'scheduled' && !consultationDate) { missing.push(missingField('Scheduled Consultation', 'wrap-modal-lead-consultation-date-input', 'modal-lead-consultation-date-input')); markMissingWrap('wrap-modal-lead-consultation-date-input'); }


        if (missing.length === 0) {

            modalMissingPanel.classList.add('hidden');

            modalMissingList.textContent = '';

            updateCardMetaBadges(activeCard, 0);
            updateLeadIntelligencePanel();

            return;

        }



        modalMissingPanel.classList.remove('hidden');

        modalMissingList.innerHTML = '<span class="mr-1 font-semibold text-amber-900">Missing:</span>' + missing.map((item) => `
            <button
                type="button"
                class="missing-field-jump inline-flex items-center rounded-full border border-amber-200 bg-white px-3 py-1 text-xs font-semibold text-amber-800 transition hover:border-amber-300 hover:bg-amber-100"
                data-missing-target="${escapeHtml(item.target)}"
                data-missing-input="${escapeHtml(item.input)}"
                data-missing-tab="${escapeHtml(item.tab)}"
            >${escapeHtml(item.label)}</button>
        `).join('');

        updateCardMetaBadges(activeCard, missing.length);
        updateLeadIntelligencePanel();

    }



    function getModalDraftValues() {

        const selectedSmsOptStatus = smsOptStatusInputs.find((input) => input.checked)?.value || 'unknown';

        return {

            fullName: modalLeadNameInput ? modalLeadNameInput.value : '',

            phone: modalLeadPhoneInput ? modalLeadPhoneInput.value : '',

            email: modalLeadEmailInput ? modalLeadEmailInput.value : '',

            preferredContact: modalLeadPreferredContactInput ? modalLeadPreferredContactInput.value : '',

            procedure: modalLeadProcedureInput ? modalLeadProcedureInput.value : '',

            financingNeeded: modalLeadFinancingNeededInput ? modalLeadFinancingNeededInput.value : '',

            financingOption: modalLeadFinancingOptionInput ? modalLeadFinancingOptionInput.value : '',

            consult: modalLeadConsultInput ? modalLeadConsultInput.value : '',

            consultationDate: modalLeadConsultationDateInput ? modalLeadConsultationDateInput.value : '',

            dateOfBirth: modalLeadDobInput ? modalLeadDobInput.value : '',
            intentType: modalLeadIntentTypeInput ? modalLeadIntentTypeInput.value : '',

            preferredDay: modalLeadPreferredDayInput ? modalLeadPreferredDayInput.value : '',

            preferredTime: modalLeadPreferredTimeInput ? modalLeadPreferredTimeInput.value : '',

            nextFollowUpAt: modalLeadNextFollowUpInput ? modalLeadNextFollowUpInput.value : '',
            source: modalLeadSourceInput ? modalLeadSourceInput.value : '',

            landingPage: modalLeadLandingPageInput ? modalLeadLandingPageInput.value : '',

            campaign: modalLeadCampaignInput ? modalLeadCampaignInput.value : '',

            notes: notesInput ? notesInput.value : '',

            leadValue: leadValueInput ? leadValueInput.value : '',

            lostReason: lostReasonInput ? lostReasonInput.value : '',

            stageKey: leadStageInput ? leadStageInput.value : '',

            smsOptStatus: selectedSmsOptStatus

        };

    }



    function getCardStoredValues(card) {

        return {

            fullName: card?.dataset.leadName || '',

            phone: card?.dataset.leadPhone || '',

            email: card?.dataset.leadEmail || '',

            preferredContact: card?.dataset.leadPreferredContact || '',

            procedure: card?.dataset.leadProcedure || '',

            financingNeeded: card?.dataset.leadFinancingNeeded || '',

            financingOption: card?.dataset.leadFinancingOption || '',

            consult: card?.dataset.leadConsult || '',

            consultationDate: toDatetimeLocal(card?.dataset.leadConsultationDate || ''),

            dateOfBirth: card?.dataset.leadDateOfBirth || '',
            intentType: card?.dataset.leadIntentType || '',

            preferredDay: card?.dataset.leadSchedulingPreferredDay || '',

            preferredTime: card?.dataset.leadSchedulingPreferredTime || '',

            nextFollowUpAt: toDatetimeLocal(card?.dataset.leadNextFollowUpAt || ''),
            source: card?.dataset.leadSource || '',

            landingPage: card?.dataset.leadLandingPage || '',

            campaign: card?.dataset.leadCampaign || '',

            notes: card?.dataset.leadNotes || '',

            leadValue: card?.dataset.leadValue || '',

            lostReason: card?.dataset.leadLostReason || '',

            stageKey: card?.dataset.stageKey || '',

            smsOptStatus: card?.dataset.leadSmsOptStatus || 'unknown'

        };

    }



    function isDirty() {

        if (!activeCard) return false;

        const current = getModalDraftValues();

        const original = getCardStoredValues(activeCard);



        return current.fullName !== original.fullName

            || current.phone !== original.phone

            || current.email !== original.email

            || current.preferredContact !== original.preferredContact

            || current.procedure !== original.procedure

            || current.financingNeeded !== original.financingNeeded

            || current.financingOption !== original.financingOption

            || current.consult !== original.consult

            || current.consultationDate !== original.consultationDate
            || current.dateOfBirth !== original.dateOfBirth
            || current.intentType !== original.intentType

            || current.preferredDay !== original.preferredDay

            || current.preferredTime !== original.preferredTime

            || current.nextFollowUpAt !== original.nextFollowUpAt

            || current.source !== original.source
            || current.landingPage !== original.landingPage

            || current.campaign !== original.campaign

            || current.notes !== original.notes

            || current.leadValue !== original.leadValue

            || current.lostReason !== original.lostReason

            || current.stageKey !== original.stageKey

            || current.smsOptStatus !== original.smsOptStatus;

    }



    function moveCardToStage(card, stageKey) {

        const targetColumn = board.querySelector('.pipeline-column[data-stage-key="' + CSS.escape(stageKey) + '"]');

        if (!targetColumn) return false;

        const targetDropzone = targetColumn.querySelector('.pipeline-dropzone');

        if (!targetDropzone) return false;

        const emptyState = targetDropzone.querySelector('.empty-state');

        if (emptyState) emptyState.remove();

        targetDropzone.prepend(card);

        updateColumnCounts();

        return true;

    }

    function getDropzoneLeadIds(dropzone) {
        if (!dropzone) return [];
        return Array.from(dropzone.querySelectorAll('.lead-card'))
            .map((card) => card.dataset.leadId || '')
            .filter(Boolean);
    }
    function restoreDropzoneOrder(dropzone, orderedIds) {
        if (!dropzone || !Array.isArray(orderedIds) || orderedIds.length === 0) return;
        const cardsById = new Map(
            Array.from(board.querySelectorAll('.lead-card'))
                .map((card) => [card.dataset.leadId || '', card])
                .filter(([leadId]) => leadId)
        );
        orderedIds.forEach((leadId) => {
            const card = cardsById.get(String(leadId));
            if (card) {
                dropzone.appendChild(card);
            }
        });
        updateColumnCounts();
    }
    function adjacentLeadCard(card, direction) {
        let sibling = direction === 'up' ? card.previousElementSibling : card.nextElementSibling;
        while (sibling && !sibling.classList.contains('lead-card')) {
            sibling = direction === 'up' ? sibling.previousElementSibling : sibling.nextElementSibling;
        }
        return sibling;
    }

    function setDeleteButtonState(disabled) {
        if (!deleteLeadButton) return;
        deleteLeadButton.disabled = !!disabled;
    }

    function setWorkspacePresentation(mode) {
        if (!modal) return;

        const outer = modal.firstElementChild;
        const panel = outer ? outer.firstElementChild : null;
        const header = panel ? panel.firstElementChild : null;
        const body = header ? header.nextElementSibling : null;
        const footer = panel ? panel.lastElementChild : null;
        const isScreen = true;

        modal.className = isScreen
            ? 'fixed inset-0 z-50 hidden bg-slate-100'
            : 'fixed inset-0 z-50 hidden bg-slate-900/50';

        if (outer) {
            outer.className = 'h-screen overflow-y-auto';
            outer.scrollTop = 0;
        }

        if (panel) {
            panel.className = 'min-h-screen w-full bg-white';
        }

        if (header) {
            header.className = isScreen
                ? 'flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 bg-white px-6 py-3 shadow-sm'
                : 'flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5';
        }

        if (body) {
            body.className = 'px-6 py-4 pb-20';
            body.style.maxHeight = '';
        }

        if (footer) {
            footer.className = isScreen
                ? 'shrink-0 border-t border-slate-200 bg-white px-6 py-4'
                : 'border-t border-slate-200 bg-white px-6 py-5';
        }

        applyCommunicationViewportFit();
    }

function applyCommunicationViewportFit() {
        if (!leadDetailBody || !leadDetailHeader || !leadDetailFooter) return;

        const communicationPanel = tabPanels ? tabPanels.communications : null;
        if (!communicationPanel || communicationPanel.classList.contains('hidden')) {
            leadDetailBody.style.maxHeight = '';
            leadDetailBody.style.overflowY = '';

            if (leadCommunicationComposerPanel) {
                leadCommunicationComposerPanel.style.maxHeight = '';
                leadCommunicationComposerPanel.style.overflowY = '';
            }

            if (unifiedTimeline) unifiedTimeline.style.maxHeight = '';
            if (activityFeed) activityFeed.style.maxHeight = '';
            if (emailHistory) emailHistory.style.maxHeight = '';
            if (messageThread) {
                messageThread.style.maxHeight = '';
                messageThread.style.overflowY = '';
            }

            return;
        }

        const viewportBudget = Math.max(
            380,
            window.innerHeight - (leadDetailHeader.offsetHeight || 76) - (leadDetailFooter.offsetHeight || 0) - 24
        );

        leadDetailBody.style.overflowY = 'hidden';

        const isComposerCollapsed = composerBody ? composerBody.classList.contains('hidden') : false;
        const composerBudget = isComposerCollapsed ? 58 : Math.max(240, Math.floor(viewportBudget / 3));
        const listBudget = Math.max(180, viewportBudget - composerBudget - 16);

        leadDetailBody.style.maxHeight = `${viewportBudget}px`;
        leadDetailBody.style.height = `${viewportBudget}px`;

        if (leadCommunicationGrid) {
            leadCommunicationGrid.style.height = `${viewportBudget}px`;
            leadCommunicationGrid.style.gridTemplateColumns = 'minmax(220px, 15%) minmax(0, 1fr) minmax(260px, 15%)';
            leadCommunicationGrid.style.gridTemplateRows = `${listBudget}px ${composerBudget}px`;
            leadCommunicationGrid.style.alignItems = 'stretch';
            leadCommunicationGrid.style.columnGap = '16px';
            leadCommunicationGrid.style.rowGap = '16px';
        }

        if (leadUnifiedTimelinePanel) {
            leadUnifiedTimelinePanel.style.gridColumn = '2 / 3';
            leadUnifiedTimelinePanel.style.gridRow = '1 / 2';
            leadUnifiedTimelinePanel.style.height = `${listBudget}px`;
            leadUnifiedTimelinePanel.style.minHeight = '0';
        }

        if (leadActivityPanel) {
            leadActivityPanel.style.gridColumn = '3 / 4';
            leadActivityPanel.style.gridRow = '1 / 2';
            leadActivityPanel.style.height = `${listBudget}px`;
            leadActivityPanel.style.minHeight = '0';
        }

        if (leadCommunicationComposerPanel) {
            leadCommunicationComposerPanel.style.gridColumn = '2 / 3';
            leadCommunicationComposerPanel.style.gridRow = '2 / 3';
            leadCommunicationComposerPanel.style.height = `${composerBudget}px`;
            leadCommunicationComposerPanel.style.maxHeight = `${composerBudget}px`;
            leadCommunicationComposerPanel.style.overflowY = 'hidden';
            leadCommunicationComposerPanel.style.width = '100%';
            leadCommunicationComposerPanel.style.alignSelf = 'end';
        }

        const unifiedPanelHeight = `${Math.max(200, listBudget - 36)}px`;
        const activityPanelHeight = `${Math.max(180, Math.floor(listBudget * 0.72))}px`;
        const emailPanelHeight = `${Math.max(90, Math.min(180, Math.floor(listBudget * 0.22)))}px`;
        const messageThreadHeight = `${Math.max(110, Math.min(180, Math.floor(viewportBudget * 0.22)))}px`;

        if (unifiedTimeline) {
            unifiedTimeline.style.maxHeight = unifiedPanelHeight;
        }

        if (activityFeed) {
            activityFeed.style.maxHeight = activityPanelHeight;
        }

        if (emailHistory) {
            emailHistory.style.maxHeight = emailPanelHeight;
        }

        if (messageThread) {
            messageThread.style.maxHeight = messageThreadHeight;
            messageThread.style.overflowY = 'auto';
        }
    }

    function openLeadModal(card, preferredTab = 'communications', openAction = '') {
        if (!modal || !card) return;

        const requestedTab = ['communications', 'details', 'notes'].includes(preferredTab) ? preferredTab : 'communications';

        setWorkspacePresentation('screen');

        activeCard = card;

        const assistantPanel = document.getElementById('crm-ai-panel');
        if (assistantPanel && assistantPanel.dataset) {
            const leadId = Number(card.dataset.leadId || 0);
            const baseUrl = window.location.origin + window.location.pathname;
            assistantPanel.dataset.leadId = String(leadId > 0 ? leadId : 0);
            assistantPanel.dataset.page = 'leads';
            assistantPanel.dataset.pageTitle = (card.dataset.leadName || 'Lead') + ' | Lead Workspace';
            assistantPanel.dataset.currentUrl = leadId > 0 ? (baseUrl + '?lead_id=' + encodeURIComponent(String(leadId))) : window.location.href;
        }
        if (typeof window.eliteAiSetLeadContext === 'function') {
            const leadId = Number(card.dataset.leadId || 0);
            const baseUrl = window.location.origin + window.location.pathname;
            window.eliteAiSetLeadContext({
                leadId: leadId,
                page: 'leads',
                pageTitle: (card.dataset.leadName || 'Lead') + ' | Lead Workspace',
                currentUrl: leadId > 0 ? (baseUrl + '?lead_id=' + encodeURIComponent(String(leadId))) : window.location.href,
            });
        }



        setText('modal-lead-name', card.dataset.leadName || 'Lead', 'Lead');

        setText('modal-lead-stage', card.dataset.leadStageLabel || '', '-');

        setText('modal-lead-assigned', card.dataset.leadAssigned || '');

        setText('modal-lead-created', card.dataset.leadCreated || '');

        setText('modal-lead-source-medium', card.dataset.leadSourceMedium || '');

        setText('modal-lead-source-type', card.dataset.leadSourceType || '');

        setText('modal-lead-instagram-username', card.dataset.leadInstagramUsername ? '@' + card.dataset.leadInstagramUsername : '');

        setText('modal-lead-trigger-keyword', card.dataset.leadTriggerKeyword || '');

        setText('modal-lead-source-ad-set', card.dataset.leadSourceAdSet || '');

        setText('modal-lead-source-ad-name', card.dataset.leadSourceAdName || '');

        setText('modal-lead-source-post-id', card.dataset.leadSourcePostId || '');

        setText('modal-lead-source-post-label', card.dataset.leadSourcePostLabel || '');

        setText('modal-lead-external-lead-id', card.dataset.leadExternalLeadId || '');



        if (modalLeadNameInput) modalLeadNameInput.value = card.dataset.leadName || '';

        if (modalLeadPhoneInput) modalLeadPhoneInput.value = card.dataset.leadPhone || '';

        if (modalLeadEmailInput) modalLeadEmailInput.value = card.dataset.leadEmail || '';

        if (modalLeadPreferredContactInput) modalLeadPreferredContactInput.value = card.dataset.leadPreferredContact || '';

        if (modalLeadProcedureInput) modalLeadProcedureInput.value = card.dataset.leadProcedure || '';

        if (modalLeadFinancingNeededInput) modalLeadFinancingNeededInput.value = card.dataset.leadFinancingNeeded || 'unsure';

        if (modalLeadFinancingOptionInput) modalLeadFinancingOptionInput.value = card.dataset.leadFinancingOption || 'none';

        if (modalLeadConsultInput) modalLeadConsultInput.value = card.dataset.leadConsult || '';

        if (modalLeadConsultationDateInput) modalLeadConsultationDateInput.value = toDatetimeLocal(card.dataset.leadConsultationDate || '');
        if (modalCommunicationConsultationDateInput) modalCommunicationConsultationDateInput.value = toDatetimeLocal(card.dataset.leadConsultationDate || '');

        if (modalLeadDobInput) modalLeadDobInput.value = card.dataset.leadDateOfBirth || '';
        if (modalLeadIntentTypeInput) modalLeadIntentTypeInput.value = card.dataset.leadIntentType || '';

        if (modalLeadPreferredDayInput) modalLeadPreferredDayInput.value = card.dataset.leadSchedulingPreferredDay || '';

        if (modalLeadPreferredTimeInput) modalLeadPreferredTimeInput.value = card.dataset.leadSchedulingPreferredTime || '';

        if (modalLeadSourceInput) modalLeadSourceInput.value = card.dataset.leadSource || 'manual';
        if (modalLeadLandingPageInput) modalLeadLandingPageInput.value = card.dataset.leadLandingPage || '';

        if (modalLeadCampaignInput) modalLeadCampaignInput.value = card.dataset.leadCampaign || '';



        if (notesInput) notesInput.value = card.dataset.leadNotes || '';

        if (leadValueInput) leadValueInput.value = card.dataset.leadValue || '';

        if (lostReasonInput) lostReasonInput.value = card.dataset.leadLostReason || '';

        if (leadStageInput) leadStageInput.value = card.dataset.stageKey || '';

        if (modalLeadNextFollowUpInput) modalLeadNextFollowUpInput.value = toDatetimeLocal(card.dataset.leadNextFollowUpAt || '');


        if (smsLeadName) smsLeadName.textContent = card.dataset.leadName || 'Lead';

        if (smsLeadPhone) smsLeadPhone.textContent = formatPhoneForDisplay(card.dataset.leadPhone || '') || 'No phone selected';

        setText('legacy-modal-sms-lead-name', card.dataset.leadName || 'Lead', 'Lead');

        setText('legacy-modal-sms-lead-phone', formatPhoneForDisplay(card.dataset.leadPhone || '') || 'No phone selected', 'No phone selected');

        if (smsInput) smsInput.value = '';

        if (smsTemplateSelect) smsTemplateSelect.value = '';

        if (smsStatus) smsStatus.textContent = '';
        if (aiInstructionInput) aiInstructionInput.value = '';
        setAiStatusMessage('');
        setAiInstructionCollapsed(true);
        if (emailSubjectInput) emailSubjectInput.value = '';
        if (emailBodyInput) emailBodyInput.value = '';
        if (emailStatus) emailStatus.textContent = '';
        if (communicationNoteInput) communicationNoteInput.value = '';
        if (communicationNoteStatus) communicationNoteStatus.textContent = '';
        setComposerMode('sms');
        setComposerDraftSource('sms', 'manual');
        setComposerDraftSource('email', 'manual');
        setComposerCollapsed(false);

        setSmsOptUi(card.dataset.leadSmsOptStatus || 'unknown');
        refreshAiDraftUi();
        refreshComposerSafetyCue();

        renderThreadSnapshot({ messages: [], activities: [], emails: [] });

        loadLeadThread();



        buildNotesHistory(card.dataset.leadNotes || '');
        updateMissingPanel();
        updateLeadIntelligencePanel();

        setActiveTab(requestedTab);

        if (openAction === 'rename') {
            requestAnimationFrame(() => {
                if (modalLeadNameInput) {
                    modalLeadNameInput.focus({ preventScroll: false });
                    modalLeadNameInput.select();
                }
            });
        }


        setDeleteButtonState(false);



        if (saveStatus) saveStatus.textContent = '';



        modal.classList.remove('hidden');

        modal.setAttribute('aria-hidden', 'false');

        document.body.classList.add('overflow-hidden');

    }



    function hardCloseLeadModal() {

        if (!modal) return;

        modal.classList.add('hidden');

        modal.setAttribute('aria-hidden', 'true');

        document.body.classList.remove('overflow-hidden');

        activeCard = null;

        const assistantPanel = document.getElementById('crm-ai-panel');
        if (assistantPanel && assistantPanel.dataset) {
            assistantPanel.dataset.leadId = '0';
            assistantPanel.dataset.page = 'leads';
            assistantPanel.dataset.pageTitle = 'Leads';
            assistantPanel.dataset.currentUrl = window.location.href;
        }
        if (typeof window.eliteAiSetLeadContext === 'function') {
            window.eliteAiSetLeadContext({
                leadId: 0,
                page: 'leads',
                pageTitle: 'Leads',
                currentUrl: window.location.href,
            });
        }

        clearMissingHighlights();

        setDeleteButtonState(false);

        if (saveStatus) saveStatus.textContent = '';

    }



    function resetNewLeadForm() {

        if (newLeadFullName) newLeadFullName.value = '';

        if (newLeadPhone) newLeadPhone.value = '';

        if (newLeadEmail) newLeadEmail.value = '';

        if (newLeadPreferredContact) newLeadPreferredContact.value = 'text';

        if (newLeadProcedure) newLeadProcedure.value = '';

        if (newLeadConsultStatus) newLeadConsultStatus.value = '';

        if (newLeadSource) newLeadSource.value = 'manual';

        if (newLeadLandingPage) newLeadLandingPage.value = '';

        if (newLeadCampaign) newLeadCampaign.value = '';

        if (newLeadFinancingNeeded) newLeadFinancingNeeded.value = 'unsure';

        if (newLeadFinancingOption) newLeadFinancingOption.value = 'none';

        if (newLeadValue) newLeadValue.value = '10000';

        if (newLeadStage) newLeadStage.value = 'new_lead';

        if (newLeadNotes) newLeadNotes.value = '';

        if (newLeadStatus) newLeadStatus.textContent = '';

    }



    function openNewLeadModal() {

        if (!newLeadModal) return;

        resetNewLeadForm();

        newLeadModal.classList.remove('hidden');

        newLeadModal.setAttribute('aria-hidden', 'false');

        document.body.classList.add('overflow-hidden');

        if (newLeadFullName) newLeadFullName.focus();

    }



    function closeNewLeadModal() {

        if (!newLeadModal) return;

        newLeadModal.classList.add('hidden');

        newLeadModal.setAttribute('aria-hidden', 'true');

        document.body.classList.remove('overflow-hidden');

        if (newLeadStatus) newLeadStatus.textContent = '';

    }



    async function parseJsonResponse(response) {

        const text = await response.text();



        if (!text) {

            throw new Error('Empty server response.');

        }



        try {

            return JSON.parse(text);

        } catch (e) {

            throw new Error('Server returned invalid JSON: ' + text.slice(0, 180));

        }

    }



    async function createLead() {

        if (isCreatingLead) return false;



        const fullName = newLeadFullName ? newLeadFullName.value.trim() : '';

        const phone = newLeadPhone ? newLeadPhone.value.trim() : '';

        const email = newLeadEmail ? newLeadEmail.value.trim() : '';

        const preferredContact = newLeadPreferredContact ? (newLeadPreferredContact.value || 'text') : 'text';

        const procedureInterest = newLeadProcedure ? newLeadProcedure.value.trim() : '';

        const consultStatus = newLeadConsultStatus ? newLeadConsultStatus.value : '';

        const consultationDate = newLeadConsultationDate ? newLeadConsultationDate.value : '';
        const source = newLeadSource ? newLeadSource.value.trim() : 'manual';

        const landingPage = newLeadLandingPage ? newLeadLandingPage.value.trim() : '';

        const campaign = newLeadCampaign ? newLeadCampaign.value.trim() : '';

        const financingNeeded = newLeadFinancingNeeded ? newLeadFinancingNeeded.value : 'unsure';

        const financingOption = newLeadFinancingOption ? newLeadFinancingOption.value : 'none';

        const leadValue = newLeadValue ? newLeadValue.value.trim() : '10000';

        const status = newLeadStage ? newLeadStage.value : 'new_lead';

        const notes = newLeadNotes ? newLeadNotes.value.trim() : '';



        if (!fullName && !phone && !email) {

            if (newLeadStatus) newLeadStatus.textContent = 'Please enter at least a name, phone, or email.';

            return false;

        }



        isCreatingLead = true;

        if (saveNewLeadButton) saveNewLeadButton.disabled = true;

        if (newLeadStatus) newLeadStatus.textContent = 'Creating lead...';



        try {

            const formData = new FormData();

            formData.append('_csrf_token', csrfToken);

            formData.append('full_name', fullName);

            formData.append('phone', phone);

            formData.append('email', email);

            formData.append('preferred_contact', preferredContact);

            formData.append('procedure_interest', procedureInterest);

            formData.append('consultation_status', consultStatus);

            formData.append('consultation_date', consultationDate);
            formData.append('source', source);

            formData.append('landing_page', landingPage);

            formData.append('campaign', campaign);

            formData.append('financing_needed', financingNeeded);

            formData.append('financing_option', financingOption);

            formData.append('lead_value', leadValue);

            formData.append('status', status);

            formData.append('notes', notes);



            const response = await fetch(createLeadUrl, {

                method: 'POST',

                body: formData,

                credentials: 'same-origin',

                headers: { 'X-Requested-With': 'XMLHttpRequest' }

            });



            const data = await parseJsonResponse(response);

            if (!response.ok || !data.ok) throw new Error(data.message || 'Failed to create lead.');



            if (newLeadStatus) newLeadStatus.textContent = data.message || 'Lead created successfully.';

            window.location.reload();

            return true;

        } catch (error) {

            if (newLeadStatus) newLeadStatus.textContent = error.message || 'Failed to create lead.';

            return false;

        } finally {

            isCreatingLead = false;

            if (saveNewLeadButton) saveNewLeadButton.disabled = false;

        }

    }



    async function saveLeadDetails() {

        if (!activeCard || isSaving || isDeletingLead) return false;



        const leadId = activeCard.dataset.leadId || '';

        const fullName = modalLeadNameInput ? modalLeadNameInput.value.trim() : '';

        const phone = modalLeadPhoneInput ? modalLeadPhoneInput.value.trim() : '';

        const email = modalLeadEmailInput ? modalLeadEmailInput.value.trim() : '';

        const preferredContact = modalLeadPreferredContactInput ? modalLeadPreferredContactInput.value : '';

        const procedure = modalLeadProcedureInput ? modalLeadProcedureInput.value.trim() : '';

        const financingNeeded = modalLeadFinancingNeededInput ? modalLeadFinancingNeededInput.value : 'unsure';

        const financingOption = modalLeadFinancingOptionInput ? modalLeadFinancingOptionInput.value : 'none';

        const consult = modalLeadConsultInput ? modalLeadConsultInput.value : '';

        const consultationDate = modalLeadConsultationDateInput ? modalLeadConsultationDateInput.value : '';
        const dateOfBirth = modalLeadDobInput ? modalLeadDobInput.value : '';
        const intentType = modalLeadIntentTypeInput ? modalLeadIntentTypeInput.value.trim() : '';

        const preferredDay = modalLeadPreferredDayInput ? modalLeadPreferredDayInput.value.trim() : '';

        const preferredTime = modalLeadPreferredTimeInput ? modalLeadPreferredTimeInput.value.trim() : '';

        const nextFollowUpAt = modalLeadNextFollowUpInput ? modalLeadNextFollowUpInput.value : '';

        const source = modalLeadSourceInput ? modalLeadSourceInput.value : 'manual';
        const landingPage = modalLeadLandingPageInput ? modalLeadLandingPageInput.value.trim() : '';

        const campaign = modalLeadCampaignInput ? modalLeadCampaignInput.value.trim() : '';

        const notes = notesInput ? notesInput.value : '';

        const leadValue = leadValueInput ? leadValueInput.value : '';

        const lostReason = lostReasonInput ? lostReasonInput.value : '';

        const smsOptStatusValue = smsOptStatusInputs.find((input) => input.checked)?.value || 'unknown';

        const requestedStageKey = leadStageInput ? leadStageInput.value : (activeCard.dataset.stageKey || '');

        const originalStageKey = activeCard.dataset.stageKey || '';

        const originalDropzone = activeCard.parentElement;



        if (saveStatus) saveStatus.textContent = 'Saving...';



        isSaving = true;

        if (saveButton) saveButton.disabled = true;

        if (saveButtonNotes) saveButtonNotes.disabled = true;

        if (saveButtonNotesSmall) saveButtonNotesSmall.disabled = true;

        if (saveButtonCommunications) saveButtonCommunications.disabled = true;

        if (sendSmsButton) sendSmsButton.disabled = true;
        setDeleteButtonState(true);



        try {

            const formData = new FormData();

            formData.append('_csrf_token', csrfToken);

            formData.append('lead_id', leadId);

            formData.append('full_name', fullName);

            formData.append('phone', phone);

            formData.append('email', email);

            formData.append('preferred_contact', preferredContact);

            formData.append('procedure_interest', procedure);

            formData.append('financing_needed', financingNeeded);

            formData.append('financing_option', financingOption);

            formData.append('consultation_status', consult);

            formData.append('consultation_date', consultationDate);
            formData.append('date_of_birth', dateOfBirth);
            formData.append('intent_type', intentType);

            formData.append('scheduling_preferred_day', preferredDay);

            formData.append('scheduling_preferred_time', preferredTime);

            formData.append('next_follow_up_at', nextFollowUpAt);

            formData.append('source', source);
            formData.append('landing_page', landingPage);

            formData.append('campaign', campaign);

            formData.append('notes', notes);

            formData.append('lead_value', leadValue);

            formData.append('lost_reason', lostReason);

            formData.append('sms_opt_status', smsOptStatusValue);



            const response = await fetch(saveDetailsUrl, {

                method: 'POST',

                body: formData,

                credentials: 'same-origin',

                headers: { 'X-Requested-With': 'XMLHttpRequest' }

            });



            const data = await parseJsonResponse(response);

            if (!response.ok || !data.ok) throw new Error(data.message || 'Failed to save lead details.');



            activeCard.dataset.leadName = data.full_name ?? fullName;

            activeCard.dataset.leadPhone = data.phone ?? phone;

            activeCard.dataset.leadEmail = data.email ?? email;

            activeCard.dataset.leadPreferredContact = data.preferred_contact ?? preferredContact;

            activeCard.dataset.leadProcedure = data.procedure_interest ?? procedure;

            activeCard.dataset.leadFinancingNeeded = data.financing_needed ?? financingNeeded;

            activeCard.dataset.leadFinancingOption = data.financing_option ?? financingOption;

            activeCard.dataset.leadConsult = data.consultation_status ?? consult;

            activeCard.dataset.leadConsultationDate = data.consultation_date ?? consultationDate;
            activeCard.dataset.leadDateOfBirth = data.date_of_birth ?? dateOfBirth;
            activeCard.dataset.leadIntentType = data.intent_type ?? intentType;

            activeCard.dataset.leadSchedulingPreferredDay = data.scheduling_preferred_day ?? preferredDay;

            activeCard.dataset.leadSchedulingPreferredTime = data.scheduling_preferred_time ?? preferredTime;

            activeCard.dataset.leadNextFollowUpAt = data.next_follow_up_at ?? nextFollowUpAt;

            activeCard.dataset.leadSource = data.source ?? source;
            activeCard.dataset.leadLandingPage = data.landing_page ?? landingPage;

            activeCard.dataset.leadCampaign = data.campaign ?? campaign;

            activeCard.dataset.leadNotes = data.notes ?? notes;

            activeCard.dataset.leadValue = data.lead_value ?? leadValue;

            activeCard.dataset.leadLostReason = data.lost_reason ?? lostReason;

            activeCard.dataset.leadSmsOptStatus = data.sms_opt_status ?? smsOptStatusValue;

            setSmsOptUi(activeCard.dataset.leadSmsOptStatus || 'unknown');



            updateCardIdentityPreview(activeCard, data.full_name ?? fullName, data.phone ?? phone, data.email ?? email);

            updateCardServicePreview(activeCard, data.procedure_interest ?? procedure);

            updateCardNotesPreview(activeCard, data.notes ?? notes);

            updateCardValuePreview(activeCard, data.lead_value ?? leadValue);

            updateCardAppointmentPreview(activeCard, data.consultation_date ?? consultationDate);
            buildNotesHistory(data.notes ?? notes);

            updateMissingPanel();



            setText('modal-lead-name', data.full_name ?? fullName, 'Lead');



            if (requestedStageKey && requestedStageKey !== originalStageKey) {

                const requestedStageLabel = stageLabelMap[requestedStageKey] || requestedStageKey;

                moveCardToStage(activeCard, requestedStageKey);
                const targetDropzone = activeCard.parentElement;


                try {

                    await saveLeadStage(activeCard, requestedStageKey, requestedStageLabel, {
                        orderedIds: getDropzoneLeadIds(targetDropzone),
                        sourceOrderedIds: originalDropzone ? getDropzoneLeadIds(originalDropzone).filter((id) => id !== (activeCard.dataset.leadId || '')) : [],
                    });
                } catch (stageError) {

                    if (originalDropzone) {

                        originalDropzone.prepend(activeCard);

                        updateColumnCounts();

                    }

                    if (leadStageInput) leadStageInput.value = originalStageKey;

                    throw stageError;

                }

            }



            if (saveStatus) saveStatus.textContent = data.message || 'Lead details saved.';

            return true;

        } catch (error) {

            if (saveStatus) saveStatus.textContent = error.message || 'Failed to save lead details.';

            return false;

        } finally {

            isSaving = false;

            if (saveButton) saveButton.disabled = false;

            if (saveButtonNotes) saveButtonNotes.disabled = false;

            if (saveButtonNotesSmall) saveButtonNotesSmall.disabled = false;

            if (saveButtonCommunications) saveButtonCommunications.disabled = false;

            if (sendSmsButton) sendSmsButton.disabled = false;

            setSmsOptUi(activeCard?.dataset.leadSmsOptStatus || 'unknown');

            setDeleteButtonState(false);
        }

    }



    async function sendLeadSms() {

        if (!activeCard || isSendingSms || isDraftingSms || isDraftingBoth || isDeletingLead || isSaving) return false;

        const leadId = activeCard.dataset.leadId || '';

        const phone = modalLeadPhoneInput ? modalLeadPhoneInput.value.trim() : (activeCard.dataset.leadPhone || '');

        const message = smsInput ? smsInput.value.trim() : '';

        const smsOpt = String(activeCard.dataset.leadSmsOptStatus || '').toLowerCase();

        if (!leadId) {

            if (smsStatus) smsStatus.textContent = 'Could not determine which lead to text.';

            return false;

        }

        if (!phone.trim()) {

            if (smsStatus) smsStatus.textContent = 'Add a phone number before sending a text.';

            return false;

        }

        if (!message) {

            if (smsStatus) smsStatus.textContent = 'Write a message before sending.';

            return false;

        }

        if (smsOpt === 'opted_out') {

            if (smsStatus) smsStatus.textContent = 'This lead opted out of SMS. Do not send text messages unless they opt back in.';

            setSmsOptUi('opted_out');

            return false;

        }

        if (isDirty()) {

            if (smsStatus) smsStatus.textContent = 'Saving lead details before sending...';

            const saved = await saveLeadDetails();

            if (!saved) {

                if (smsStatus) smsStatus.textContent = 'Save the lead details before sending SMS.';

                return false;

            }

        }

        isSendingSms = true;

        if (sendSmsButton) sendSmsButton.disabled = true;
        refreshAiDraftUi();

        if (saveButton) saveButton.disabled = true;

        if (saveButtonCommunications) saveButtonCommunications.disabled = true;

        if (smsStatus) smsStatus.textContent = 'Sending SMS...';

        try {

            const formData = new FormData();

            formData.append('_csrf_token', csrfToken);

            formData.append('lead_id', leadId);

            formData.append('message', message);



            const response = await fetch(sendSmsUrl, {

                method: 'POST',

                body: formData,

                credentials: 'same-origin',

                headers: { 'X-Requested-With': 'XMLHttpRequest' }

            });



            const data = await parseJsonResponse(response);

            if (!response.ok || !data.ok) throw new Error(data.message || 'Failed to send SMS.');



            if (data.notes !== undefined) {

                activeCard.dataset.leadNotes = data.notes || '';

                if (notesInput) notesInput.value = data.notes || '';

                buildNotesHistory(data.notes || '');

            }

            if (data.thread) {

                renderThreadSnapshot(data.thread);

            }

            activeCard.dataset.leadLastOutboundAt = new Date().toISOString().slice(0, 19).replace('T', ' ');

            activeCard.dataset.leadLastContactedAt = activeCard.dataset.leadLastOutboundAt;

            clearUnreadBadge(activeCard);

            if (smsInput) {
                smsInput.value = '';
            }
            setComposerDraftSource('sms', 'manual');
            refreshComposerSafetyCue();

            if (smsStatus) smsStatus.textContent = data.message || 'SMS sent.';

            return true;

        } catch (error) {

            if (smsStatus) smsStatus.textContent = error.message || 'Failed to send SMS.';

            return false;

        } finally {

            isSendingSms = false;

            if (sendSmsButton) sendSmsButton.disabled = false;

            if (saveButton) saveButton.disabled = false;

            if (saveButtonCommunications) saveButtonCommunications.disabled = false;

            setSmsOptUi(activeCard?.dataset.leadSmsOptStatus || 'unknown');
            refreshAiDraftUi();

        }

    }

    async function draftLeadSms() {

        if (isDraftingSms || isDraftingBoth) return false;

        const prepared = await ensureLeadReadyForAi('sms');
        if (!prepared) return false;

        isDraftingSms = true;
        refreshAiDraftUi();
        if (sendSmsButton) sendSmsButton.disabled = true;
        if (smsStatus) smsStatus.textContent = 'Drafting SMS with AI...';
        setAiStatusMessage('Drafting SMS...');

        try {
            const instruction = getAiInstructionValue() || defaultAiInstruction('sms');
            const data = await requestSmsDraft(prepared.leadId, instruction);

            if (smsInput) smsInput.value = data.draft?.reply || '';
            setComposerDraftSource('sms', 'ai');
            if (smsStatus) smsStatus.textContent = 'SMS drafted. Review before sending.';
            setAiStatusMessage('SMS drafted.');
            refreshComposerSafetyCue();

            await loadLeadThread();
            return true;
        } catch (error) {
            if (smsStatus) smsStatus.textContent = error.message || 'Failed to draft SMS.';
            setAiStatusMessage(error.message || 'Failed to draft SMS.');
            return false;
        } finally {
            isDraftingSms = false;
            if (sendSmsButton) sendSmsButton.disabled = false;
            refreshAiDraftUi();
            setSmsOptUi(activeCard?.dataset.leadSmsOptStatus || 'unknown');
        }

    }

    async function draftLeadEmail() {

        if (isDraftingEmail || isDraftingBoth) return false;

        const prepared = await ensureLeadReadyForAi('email');
        if (!prepared) return false;

        isDraftingEmail = true;
        refreshAiDraftUi();
        if (sendEmailButton) sendEmailButton.disabled = true;
        if (emailStatus) emailStatus.textContent = 'Drafting email with AI...';
        setAiStatusMessage('Drafting email...');

        try {
            const instruction = getAiInstructionValue() || defaultAiInstruction('email');
            const data = await requestEmailDraft(prepared.leadId, instruction);

            if (emailSubjectInput) emailSubjectInput.value = data.draft?.subject || '';
            if (emailBodyInput) emailBodyInput.value = data.draft?.body || '';
            setComposerDraftSource('email', 'ai');
            applyDraftedFollowUp(data.draft?.next_follow_up_at || '');
            if (emailStatus) emailStatus.textContent = 'Email drafted. Review before sending.';
            setAiStatusMessage('Email drafted.');
            refreshComposerSafetyCue();

            await loadLeadThread();
            return true;
        } catch (error) {
            if (emailStatus) emailStatus.textContent = error.message || 'Failed to draft email.';
            setAiStatusMessage(error.message || 'Failed to draft email.');
            return false;
        } finally {
            isDraftingEmail = false;
            if (sendEmailButton) sendEmailButton.disabled = false;
            refreshAiDraftUi();
        }

    }

    async function improveLeadSms() {

        const currentMessage = smsInput ? smsInput.value.trim() : '';

        if (!currentMessage) {
            if (smsStatus) smsStatus.textContent = 'Write an SMS first, then click Improve.';
            setAiStatusMessage('Write an SMS first, then click Improve.');
            return false;
        }

        if (smsTemplateSelect) smsTemplateSelect.value = '';

        const originalInstruction = aiInstructionInput ? aiInstructionInput.value : '';
        const improveInstruction = buildImproveInstruction('sms');

        if (!improveInstruction) {
            if (smsStatus) smsStatus.textContent = 'Write an SMS first, then click Improve.';
            return false;
        }

        if (aiInstructionInput) aiInstructionInput.value = improveInstruction;

        try {
            return await draftLeadSms();
        } finally {
            if (aiInstructionInput) aiInstructionInput.value = originalInstruction;
        }

    }

    async function improveLeadEmail() {

        const currentSubject = emailSubjectInput ? emailSubjectInput.value.trim() : '';
        const currentBody = emailBodyInput ? emailBodyInput.value.trim() : '';

        if (!currentSubject && !currentBody) {
            if (emailStatus) emailStatus.textContent = 'Write an email first, then click Improve.';
            setAiStatusMessage('Write an email first, then click Improve.');
            return false;
        }

        const originalInstruction = aiInstructionInput ? aiInstructionInput.value : '';
        const improveInstruction = buildImproveInstruction('email');

        if (!improveInstruction) {
            if (emailStatus) emailStatus.textContent = 'Write an email first, then click Improve.';
            return false;
        }

        if (aiInstructionInput) aiInstructionInput.value = improveInstruction;

        try {
            return await draftLeadEmail();
        } finally {
            if (aiInstructionInput) aiInstructionInput.value = originalInstruction;
        }

    }

    async function draftLeadBoth() {

        if (isDraftingBoth || isDraftingSms || isDraftingEmail) return false;

        const prepared = await ensureLeadReadyForAi('both');
        if (!prepared) return false;

        isDraftingBoth = true;
        refreshAiDraftUi();
        if (sendSmsButton) sendSmsButton.disabled = true;
        if (sendEmailButton) sendEmailButton.disabled = true;
        if (smsStatus) smsStatus.textContent = 'Drafting SMS with AI...';
        if (emailStatus) emailStatus.textContent = 'Drafting email with AI...';
        setAiStatusMessage('Drafting SMS and email...');

        try {
            const baseInstruction = getAiInstructionValue() || defaultAiInstruction('both');
            const emailData = await requestEmailDraft(prepared.leadId, baseInstruction, 'operator_follow_up_email');
            const smsData = await requestSmsDraft(prepared.leadId, baseInstruction, 'operator_follow_up_sms');

            if (emailSubjectInput) emailSubjectInput.value = emailData.draft?.subject || '';
            if (emailBodyInput) emailBodyInput.value = emailData.draft?.body || '';
            if (smsInput) smsInput.value = smsData.draft?.reply || '';
            setComposerDraftSource('email', 'ai');
            setComposerDraftSource('sms', 'ai');
            applyDraftedFollowUp(emailData.draft?.next_follow_up_at || '');

            if (emailStatus) emailStatus.textContent = 'Email drafted. Review before sending.';
            if (smsStatus) smsStatus.textContent = 'SMS drafted. Review before sending.';
            refreshComposerSafetyCue();
            setAiStatusMessage('SMS and email drafted. Review and send when ready.');

            await loadLeadThread();
            return true;
        } catch (error) {
            if (emailStatus) emailStatus.textContent = error.message || 'Failed to draft email.';
            if (smsStatus) smsStatus.textContent = error.message || 'Failed to draft SMS.';
            setAiStatusMessage(error.message || 'Failed to draft both messages.');
            return false;
        } finally {
            isDraftingBoth = false;
            if (sendSmsButton) sendSmsButton.disabled = false;
            if (sendEmailButton) sendEmailButton.disabled = false;
            refreshAiDraftUi();
            setSmsOptUi(activeCard?.dataset.leadSmsOptStatus || 'unknown');
        }

    }

    async function sendLeadEmail() {

        if (!activeCard || isSendingEmail || isDraftingEmail || isDraftingBoth || isDeletingLead || isSaving) return false;

        const leadId = activeCard.dataset.leadId || '';
        const email = modalLeadEmailInput ? modalLeadEmailInput.value.trim() : (activeCard.dataset.leadEmail || '');
        const subject = emailSubjectInput ? emailSubjectInput.value.trim() : '';
        const body = emailBodyInput ? emailBodyInput.value.trim() : '';

        if (!leadId) {
            if (emailStatus) emailStatus.textContent = 'Could not determine which lead to email.';
            return false;
        }

        if (!email) {
            if (emailStatus) emailStatus.textContent = 'Add an email address before sending.';
            return false;
        }

        if (!subject || !body) {
            if (emailStatus) emailStatus.textContent = 'Subject and body are required before sending.';
            return false;
        }

        if (isDirty()) {
            if (emailStatus) emailStatus.textContent = 'Saving lead details before sending...';
            const saved = await saveLeadDetails();
            if (!saved) {
                if (emailStatus) emailStatus.textContent = 'Save the lead details before sending email.';
                return false;
            }
        }

        isSendingEmail = true;
        if (draftEmailButton) draftEmailButton.disabled = true;
        if (sendEmailButton) sendEmailButton.disabled = true;
        refreshAiDraftUi();
        if (saveButton) saveButton.disabled = true;
        if (saveButtonCommunications) saveButtonCommunications.disabled = true;
        if (emailStatus) emailStatus.textContent = 'Sending email...';

        try {
            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);
            formData.append('lead_id', leadId);
            formData.append('subject', subject);
            formData.append('body', body);

            const response = await fetch(sendEmailUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await parseJsonResponse(response);
            if (!response.ok || !data.ok) throw new Error(data.message || 'Failed to send email.');

            if (data.notes !== undefined) {
                activeCard.dataset.leadNotes = data.notes || '';
                if (notesInput) notesInput.value = data.notes || '';
                buildNotesHistory(data.notes || '');
            }

            if (emailSubjectInput) {
                emailSubjectInput.value = '';
            }
            if (emailBodyInput) {
                emailBodyInput.value = '';
            }
            setComposerDraftSource('email', 'manual');
            refreshComposerSafetyCue();

            if (emailStatus) emailStatus.textContent = data.message || 'Email sent.';
            await loadLeadThread();
            return true;
        } catch (error) {
            if (emailStatus) emailStatus.textContent = error.message || 'Failed to send email.';
            return false;
        } finally {
            isSendingEmail = false;
            if (sendEmailButton) sendEmailButton.disabled = false;
            if (saveButton) saveButton.disabled = false;
            if (saveButtonCommunications) saveButtonCommunications.disabled = false;
            refreshAiDraftUi();
        }

    }

    async function saveCommunicationNote() {

        if (!activeCard || isSaving || isDeletingLead) return false;

        const note = communicationNoteInput ? communicationNoteInput.value.trim() : '';
        if (!note) {
            if (communicationNoteStatus) communicationNoteStatus.textContent = 'Write a note before saving.';
            return false;
        }

        const stamp = new Date().toLocaleString([], {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: 'numeric',
            minute: '2-digit',
        });
        const entry = '--- Note added on ' + stamp + ' ---\n' + note;
        const existing = notesInput ? notesInput.value.trim() : '';
        if (notesInput) {
            notesInput.value = existing ? existing + '\n\n' + entry : entry;
        }

        if (communicationNoteStatus) communicationNoteStatus.textContent = 'Saving note...';
        if (saveCommunicationNoteButton) saveCommunicationNoteButton.disabled = true;

        const saved = await saveLeadDetails();
        if (saved) {
            if (communicationNoteInput) communicationNoteInput.value = '';
            if (communicationNoteStatus) communicationNoteStatus.textContent = 'Note saved.';
            await loadLeadThread();
        } else if (communicationNoteStatus) {
            communicationNoteStatus.textContent = 'Could not save note.';
        }

        if (saveCommunicationNoteButton) saveCommunicationNoteButton.disabled = false;
        return saved;

    }



    function applySelectedSmsTemplate() {

        if (!activeCard || !smsTemplateSelect || !smsInput) return;

        const key = smsTemplateSelect.value || '';

        if (!key || !smsTemplates[key]) return;

        smsInput.value = applyTemplateTokens(smsTemplates[key].body || '', activeCard);
        setComposerDraftSource('sms', 'manual');
        refreshComposerSafetyCue();

        if (smsStatus) smsStatus.textContent = 'Template loaded. Review before sending.';

    }



    async function runFollowupCheck() {

        if (!followupCheckButton || !followupCheckUrl) return false;

        followupCheckButton.disabled = true;

        followupCheckButton.textContent = 'Checking...';

        try {

            const formData = new FormData();

            formData.append('_csrf_token', csrfToken);

            const response = await fetch(followupCheckUrl, {

                method: 'POST',

                body: formData,

                credentials: 'same-origin',

                headers: { 'X-Requested-With': 'XMLHttpRequest' }

            });

            const data = await parseJsonResponse(response);

            if (!response.ok || !data.ok) throw new Error(data.message || 'Follow-up check failed.');

            alert(data.message || 'Follow-up check complete.');

            window.location.reload();

            return true;

        } catch (error) {

            alert(error.message || 'Follow-up check failed.');

            return false;

        } finally {

            followupCheckButton.disabled = false;

            followupCheckButton.textContent = 'Check Follow-Ups';

        }

    }



    async function requestDeleteLead() {
        if (!activeCard || isDeletingLead || isSaving) return false;



        const leadName = (activeCard.dataset.leadName || 'this lead').trim();

        const confirmed = window.confirm(

            'Delete ' + leadName + ' permanently from the database?\n\nThis cannot be undone.'

        );



        if (!confirmed) {

            return false;

        }



        return deleteLead();

    }



    async function deleteLead() {

        if (!activeCard || isDeletingLead) return false;



        const leadId = activeCard.dataset.leadId || '';

        if (!leadId) {

            if (saveStatus) saveStatus.textContent = 'Could not determine which lead to delete.';

            return false;

        }



        isDeletingLead = true;

        if (saveStatus) saveStatus.textContent = 'Deleting lead...';



        if (saveButton) saveButton.disabled = true;

        if (saveButtonNotes) saveButtonNotes.disabled = true;

        if (saveButtonNotesSmall) saveButtonNotesSmall.disabled = true;

        if (saveButtonCommunications) saveButtonCommunications.disabled = true;

        setDeleteButtonState(true);



        try {

            const formData = new FormData();

            formData.append('_csrf_token', csrfToken);

            formData.append('lead_id', leadId);



            const cardToRemove = activeCard;



            const response = await fetch(deleteLeadUrl, {

                method: 'POST',

                body: formData,

                credentials: 'same-origin',

                headers: { 'X-Requested-With': 'XMLHttpRequest' }

            });



            const data = await parseJsonResponse(response);

            if (!response.ok || !data.ok) {

                throw new Error(data.message || 'Failed to delete lead.');

            }



            if (cardToRemove && cardToRemove.parentElement) {

                cardToRemove.remove();

                updateColumnCounts();

            }



            hardCloseLeadModal();

            return true;

        } catch (error) {

            if (saveStatus) saveStatus.textContent = error.message || 'Failed to delete lead.';

            return false;

        } finally {

            isDeletingLead = false;

            if (saveButton) saveButton.disabled = false;

            if (saveButtonNotes) saveButtonNotes.disabled = false;

            if (saveButtonNotesSmall) saveButtonNotesSmall.disabled = false;

            if (saveButtonCommunications) saveButtonCommunications.disabled = false;

            setDeleteButtonState(false);

        }

    }



    async function requestCloseLeadModal() {
        if (!activeCard || isDeletingLead) {
            hardCloseLeadModal();
            return;
        }

        if (isSaving) {
            if (saveStatus) saveStatus.textContent = 'Still saving...';
            return;
        }

        if (isDirty()) {
            if (saveStatus) saveStatus.textContent = 'Saving before closing...';
            const saved = await saveLeadDetails();
            if (!saved) {
                if (saveStatus) saveStatus.textContent = 'Could not save changes. Fix the issue before closing.';
                return;
            }
        }

        hardCloseLeadModal();
    }

    async function saveLeadStage(card, newStageKey, newStageLabel, options = {}) {

        const leadId = card.dataset.leadId || '';

        const formData = new FormData();

        formData.append('_csrf_token', csrfToken);

        formData.append('lead_id', leadId);

        formData.append('status', newStageKey);
        (Array.isArray(options.orderedIds) ? options.orderedIds : []).forEach((orderedId) => {
            formData.append('ordered_ids[]', orderedId);
        });
        (Array.isArray(options.sourceOrderedIds) ? options.sourceOrderedIds : []).forEach((orderedId) => {
            formData.append('source_ordered_ids[]', orderedId);
        });


        const response = await fetch(saveStageUrl, {

            method: 'POST',

            body: formData,

            credentials: 'same-origin',

            headers: { 'X-Requested-With': 'XMLHttpRequest' }

        });



        const data = await parseJsonResponse(response);

        if (!response.ok || !data.ok) throw new Error(data.message || 'Failed to update lead stage.');



        card.dataset.stageKey = data.status || newStageKey;

        card.dataset.leadStageLabel = data.status_label || newStageLabel;

        updateCardStagePill(card, data.status_label || newStageLabel);

        setText('modal-lead-stage', data.status_label || newStageLabel, '-');

    }



    function stopAutoScroll() {

        if (autoScrollRaf) {

            cancelAnimationFrame(autoScrollRaf);

            autoScrollRaf = null;

        }

    }



    function autoScrollBoard() {

        if (!draggedCard || dragMouseX === null) {

            stopAutoScroll();

            return;

        }



        const rect = viewport.getBoundingClientRect();

        const edgeZone = 90;

        const maxStep = 22;

        let delta = 0;



        if (dragMouseX < rect.left + edgeZone) {

            const intensity = Math.min(1, (rect.left + edgeZone - dragMouseX) / edgeZone);

            delta = -maxStep * intensity;

        } else if (dragMouseX > rect.right - edgeZone) {

            const intensity = Math.min(1, (dragMouseX - (rect.right - edgeZone)) / edgeZone);

            delta = maxStep * intensity;

        }



        if (delta !== 0) viewport.scrollLeft += delta;

        autoScrollRaf = requestAnimationFrame(autoScrollBoard);

    }



    document.addEventListener('dragover', function (event) {

        if (!draggedCard) return;

        dragMouseX = event.clientX;

        if (!autoScrollRaf) autoScrollRaf = requestAnimationFrame(autoScrollBoard);

    });



    [

        modalLeadNameInput,

        modalLeadPhoneInput,

        modalLeadEmailInput,

        modalLeadPreferredContactInput,

        modalLeadProcedureInput,

        modalLeadFinancingNeededInput,

        modalLeadFinancingOptionInput,

        modalLeadConsultInput,

        modalLeadConsultationDateInput,
        modalLeadSourceInput,

        modalLeadLandingPageInput,

        modalLeadCampaignInput,
        modalLeadDobInput,
        modalLeadIntentTypeInput,
        modalLeadPreferredDayInput,
        modalLeadPreferredTimeInput,
        modalLeadNextFollowUpInput,

    ].forEach((el) => {

        if (!el) return;

        el.addEventListener('input', updateMissingPanel);

        el.addEventListener('change', updateMissingPanel);

    });



    tabButtons.forEach((btn) => {

        btn.addEventListener('click', function () {

            setActiveTab(btn.dataset.tabTarget || 'details');

        });

    });



    if (saveButton) saveButton.addEventListener('click', saveLeadDetails);

    refreshAiDraftUi();
    setAiInstructionCollapsed(true);
    refreshComposerSafetyCue();
    applyPipelineBoardMobileMode();

    if (saveButtonNotes) saveButtonNotes.addEventListener('click', saveLeadDetails);
    if (saveButtonNotesSmall) saveButtonNotesSmall.addEventListener('click', saveLeadDetails);

    if (saveButtonCommunications) saveButtonCommunications.addEventListener('click', saveLeadDetails);

    if (draftSmsButton) draftSmsButton.addEventListener('click', draftLeadSms);
    if (improveSmsButton) improveSmsButton.addEventListener('click', improveLeadSms);
    if (sendSmsButton) sendSmsButton.addEventListener('click', sendLeadSms);
    if (draftEmailButton) draftEmailButton.addEventListener('click', draftLeadEmail);
    if (improveEmailButton) improveEmailButton.addEventListener('click', improveLeadEmail);
    if (draftBothButton) draftBothButton.addEventListener('click', draftLeadBoth);
    if (sendEmailButton) sendEmailButton.addEventListener('click', sendLeadEmail);
    if (saveCommunicationNoteButton) saveCommunicationNoteButton.addEventListener('click', saveCommunicationNote);

    if (loadThreadButton) loadThreadButton.addEventListener('click', loadLeadThread);

    if (smsInput) {
        smsInput.addEventListener('input', function () {
            setComposerDraftSource('sms', 'manual');
            if (composerMode === 'sms') {
                refreshComposerSafetyCue();
            }
        });
    }

    if (emailBodyInput) {
        emailBodyInput.addEventListener('input', function () {
            setComposerDraftSource('email', 'manual');
            if (composerMode === 'email') {
                refreshComposerSafetyCue();
            }
        });
    }

    if (emailSubjectInput) {
        emailSubjectInput.addEventListener('input', function () {
            setComposerDraftSource('email', 'manual');
            if (composerMode === 'email') {
                refreshComposerSafetyCue();
            }
        });
    }

    smsOptStatusInputs.forEach((input) => {
        input.addEventListener('change', function () {
            setSmsOptUi(input.value || 'unknown');
            if (saveStatus && activeCard) {
                saveStatus.textContent = 'DND status changed. Save changes to keep it.';
            }
        });
    });

    if (modalCommunicationConsultationDateInput) {
        modalCommunicationConsultationDateInput.addEventListener('input', function () {
            if (modalLeadConsultationDateInput) {
                modalLeadConsultationDateInput.value = modalCommunicationConsultationDateInput.value;
            }
            if (modalLeadConsultInput && modalCommunicationConsultationDateInput.value) {
                modalLeadConsultInput.value = 'scheduled';
            }
            if (saveStatus && activeCard) {
                saveStatus.textContent = 'Consultation time changed. Save changes to keep it.';
            }
        });
    }

    if (modalLeadConsultationDateInput && modalCommunicationConsultationDateInput) {
        modalLeadConsultationDateInput.addEventListener('input', function () {
            modalCommunicationConsultationDateInput.value = modalLeadConsultationDateInput.value;
        });
    }

    if (smsTemplateSelect) smsTemplateSelect.addEventListener('change', applySelectedSmsTemplate);
    composerModeButtons.forEach((button) => {
        button.addEventListener('click', function () {
            setComposerCollapsed(false);
            setComposerMode(button.dataset.composerMode || 'sms');
        });
    });

    if (composerCollapseToggle) {
        composerCollapseToggle.addEventListener('click', function () {
            const isCollapsed = composerBody ? composerBody.classList.contains('hidden') : false;
            setComposerCollapsed(!isCollapsed);
        });
    }

    if (pipelineMobileStageFilter) {
        pipelineMobileStageFilter.addEventListener('change', applyPipelineBoardMobileMode);
        window.addEventListener('resize', () => {
            if (!pipelineMobileStageFilter) return;
            applyPipelineBoardMobileMode();
        });
        window.addEventListener('resize', () => {
            applyCommunicationViewportFit();
        });
    }

    window.addEventListener('resize', function () {
        applyCommunicationViewportFit();
    });

    if (smsDndToggle && smsDndBody) {
        smsDndToggle.addEventListener('click', function () {
            const isExpanded = !smsDndBody.classList.contains('hidden');
            const nextExpanded = !isExpanded;
            smsDndBody.classList.toggle('hidden', !nextExpanded);
            smsDndToggle.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
        });
    }

    const legacySmsDndToggle = document.getElementById('legacy-modal-sms-dnd-toggle');
    const legacySmsDndBody = document.getElementById('legacy-modal-sms-dnd-body');
    if (legacySmsDndToggle && legacySmsDndBody) {
        legacySmsDndToggle.addEventListener('click', function () {
            const isExpanded = !legacySmsDndBody.classList.contains('hidden');
            const nextExpanded = !isExpanded;
            legacySmsDndBody.classList.toggle('hidden', !nextExpanded);
            legacySmsDndToggle.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
        });
    }

    if (aiCollapseToggle) {
        aiCollapseToggle.addEventListener('click', function () {
            setAiInstructionCollapsed(aiCollapseToggle.getAttribute('aria-expanded') === 'true');
        });
    }

    if (modalMissingList) {
        modalMissingList.addEventListener('click', function (event) {
            const button = event.target.closest('.missing-field-jump');
            if (!button) return;

            focusMissingField(
                button.dataset.missingTarget || '',
                button.dataset.missingInput || '',
                button.dataset.missingTab || 'details'
            );
        });
    }

    if (followupCheckButton) followupCheckButton.addEventListener('click', runFollowupCheck);

    if (calendarOpenButton) {
        calendarOpenButton.addEventListener('click', openCalendarPanel);
    }

    if (calendarCloseButton) {
        calendarCloseButton.addEventListener('click', closeCalendarPanel);
    }

    if (calendarOverlay) {
        calendarOverlay.addEventListener('click', function (event) {
            if (event.target === calendarOverlay) {
                closeCalendarPanel();
            }
        });
    }

    if (calendarPrevButton) {
        calendarPrevButton.addEventListener('click', function () {
            shiftCalendarRange(-1);
        });
    }

    if (calendarNextButton) {
        calendarNextButton.addEventListener('click', function () {
            shiftCalendarRange(1);
        });
    }

    if (calendarTodayButton) {
        calendarTodayButton.addEventListener('click', jumpCalendarToToday);
    }

    if (calendarViewButtons.length > 0) {
        calendarViewButtons.forEach((button) => {
            button.addEventListener('click', function () {
                setCalendarView(button.dataset.calendarView || 'day');
            });
        });
    }

    if (calendarViewRoot) {
        calendarViewRoot.addEventListener('click', function (event) {
            const leadButton = event.target.closest('[data-calendar-lead-id]');
            if (!leadButton) {
                return;
            }

            const card = safeCardLookupById(leadButton.dataset.calendarLeadId || '');
            if (!card || !modal) {
                return;
            }

            event.preventDefault();
            closeCalendarPanel();
            openLeadModal(card, 'communications');
        });
    }

    if (openNewLeadButton) openNewLeadButton.addEventListener('click', openNewLeadModal);
    if (closeNewLeadButton) closeNewLeadButton.addEventListener('click', closeNewLeadModal);

    if (cancelNewLeadButton) cancelNewLeadButton.addEventListener('click', closeNewLeadModal);

    if (saveNewLeadButton) saveNewLeadButton.addEventListener('click', createLead);

    if (deleteLeadButton) deleteLeadButton.addEventListener('click', requestDeleteLead);



    board.addEventListener('click', function (event) {
        const openButton = event.target.closest('[data-open-lead-modal]');
        if (!openButton) return;
        const card = openButton.closest('.lead-card');
        if (!card) return;
        event.preventDefault();
        event.stopPropagation();
        openLeadModal(card, openButton.dataset.openTab || 'communications', openButton.dataset.openAction || '');
    }, true);

    board.addEventListener('click', async function (event) {
        const moveButton = event.target.closest('[data-move-card]');
        if (!moveButton) return;
        const card = moveButton.closest('.lead-card');
        const dropzone = card ? card.parentElement : null;
        const direction = moveButton.getAttribute('data-move-card') || '';
        if (!card || !dropzone || isSaving || isDeletingLead) return;
        const adjacent = adjacentLeadCard(card, direction);
        if (!adjacent) return;
        const originalOrder = getDropzoneLeadIds(dropzone);
        if (direction === 'up') {
            dropzone.insertBefore(card, adjacent);
        } else {
            dropzone.insertBefore(adjacent, card);
        }
        updateColumnCounts();
        try {
            await saveLeadStage(
                card,
                card.dataset.stageKey || '',
                card.dataset.leadStageLabel || '',
                { orderedIds: getDropzoneLeadIds(dropzone) }
            );
        } catch (error) {
            restoreDropzoneOrder(dropzone, originalOrder);
            alert(error.message || 'Failed to move the lead.');
        }
    });

    board.querySelectorAll('.lead-card').forEach((card) => {
        card.addEventListener('dragstart', function () {

            draggedCard = card;

            sourceDropzone = card.parentElement;

            card.classList.add('opacity-60');

            if (!autoScrollRaf) autoScrollRaf = requestAnimationFrame(autoScrollBoard);

        });



        card.addEventListener('dragend', function () {

            card.classList.remove('opacity-60');

            draggedCard = null;

            sourceDropzone = null;

            dragMouseX = null;

            stopAutoScroll();

            document.querySelectorAll('.pipeline-column').forEach((col) => {

                col.classList.remove('ring-2', 'ring-slate-300', 'bg-slate-100');

            });

        });



        const openButtons = card.querySelectorAll('.lead-open-modal');

        openButtons.forEach((openBtn) => {

            openBtn.addEventListener('click', function (event) {

                event.preventDefault();

                event.stopPropagation();

                openLeadModal(card, openBtn.dataset.openTab || 'communications', openBtn.dataset.openAction || '');

            });

        });

    });



    board.querySelectorAll('.pipeline-column').forEach((column) => {

        const dropzone = column.querySelector('.pipeline-dropzone');

        if (!dropzone) return;



        column.addEventListener('dragover', function (event) {

            event.preventDefault();

            column.classList.add('ring-2', 'ring-slate-300', 'bg-slate-100');

        });



        column.addEventListener('dragleave', function () {

            column.classList.remove('ring-2', 'ring-slate-300', 'bg-slate-100');

        });



        column.addEventListener('drop', async function (event) {

            event.preventDefault();

            column.classList.remove('ring-2', 'ring-slate-300', 'bg-slate-100');



            if (!draggedCard || !sourceDropzone) return;



            const oldStageKey = draggedCard.dataset.stageKey || '';

            const newStageKey = column.dataset.stageKey || '';

            const newStageLabel = column.dataset.stageLabel || newStageKey;



            if (!newStageKey || oldStageKey === newStageKey) return;

            const sourceOrderBeforeMove = getDropzoneLeadIds(sourceDropzone);


            const emptyState = dropzone.querySelector('.empty-state');

            if (emptyState) emptyState.remove();



            dropzone.prepend(draggedCard);

            updateColumnCounts();



            try {

                await saveLeadStage(draggedCard, newStageKey, newStageLabel, {
                    orderedIds: getDropzoneLeadIds(dropzone),
                    sourceOrderedIds: sourceOrderBeforeMove.filter((id) => id !== (draggedCard.dataset.leadId || '')),
                });

                if (activeCard && activeCard === draggedCard && leadStageInput) {

                    leadStageInput.value = newStageKey;

                }

            } catch (error) {

                sourceDropzone.prepend(draggedCard);

                updateColumnCounts();

                alert(error.message || 'Failed to update lead stage.');

            }

        });

    });



    if (closeTop) closeTop.addEventListener('click', requestCloseLeadModal);

    if (closeBottom) closeBottom.addEventListener('click', requestCloseLeadModal);



    if (modal) {

        modal.addEventListener('click', function (event) {

            if (event.target === modal) requestCloseLeadModal();

        });

    }



    if (newLeadModal) {

        newLeadModal.addEventListener('click', function (event) {

            if (event.target === newLeadModal) closeNewLeadModal();

        });

    }



    document.addEventListener('keydown', function (event) {

        if (event.key !== 'Escape') return;



        if (newLeadModal && !newLeadModal.classList.contains('hidden')) {

            closeNewLeadModal();

            return;

        }



        if (modal && !modal.classList.contains('hidden')) {

            requestCloseLeadModal();

        }

    });



    updateColumnCounts();
    setActiveTab('communications');
    setCalendarView(calendarView);
    if (calendarStateFromStorage?.isOpen) {
        openCalendarPanel();
    } else if (isCalendarOpen()) {
        persistCalendarState();
    }

    const pipelineAutoRefreshMs = 60000;
    const pipelineIdleRefreshGraceMs = 15000;
    const pipelineFocusRefreshAfterMs = 120000;
    let pipelineLastInteractionAt = Date.now();
    let pipelineLastHiddenAt = 0;
    let pipelineRefreshPending = false;
    let pipelineRefreshNotice = null;

    function markPipelineInteraction() {
        pipelineLastInteractionAt = Date.now();
    }

    function isPipelineModalOpen() {
        return modal && !modal.classList.contains('hidden');
    }

    function isPipelineBusy() {
        const busyFlags = [
            typeof isSaving !== 'undefined' && isSaving,
            typeof isDeletingLead !== 'undefined' && isDeletingLead,
            typeof isSendingSms !== 'undefined' && isSendingSms,
            typeof isSendingEmail !== 'undefined' && isSendingEmail,
            typeof isDraftingSms !== 'undefined' && isDraftingSms,
            typeof isDraftingEmail !== 'undefined' && isDraftingEmail,
            typeof isDraftingBoth !== 'undefined' && isDraftingBoth,
            typeof draggedCard !== 'undefined' && !!draggedCard,
        ];

        return busyFlags.some(Boolean);
    }

    function canRefreshPipelineSafely() {
        return !document.hidden
            && !isPipelineModalOpen()
            && !isCalendarOpen()
            && !isPipelineBusy()
            && Date.now() - pipelineLastInteractionAt > pipelineIdleRefreshGraceMs;
    }

    function refreshPipelineBoard() {
        window.location.reload();
    }

    function hidePipelineRefreshNotice() {
        if (!pipelineRefreshNotice) {
            return;
        }

        pipelineRefreshNotice.remove();
        pipelineRefreshNotice = null;
    }

    function showPipelineRefreshNotice() {
        if (pipelineRefreshNotice) {
            return;
        }

        pipelineRefreshNotice = document.createElement('button');
        pipelineRefreshNotice.type = 'button';
        pipelineRefreshNotice.className = 'fixed bottom-5 right-5 z-50 rounded-full border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-xl shadow-slate-900/15 transition hover:-translate-y-0.5 hover:border-blue-300 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2';
        pipelineRefreshNotice.textContent = 'New pipeline updates available - refresh board';
        pipelineRefreshNotice.addEventListener('click', refreshPipelineBoard);
        document.body.appendChild(pipelineRefreshNotice);
    }

    function requestPipelineRefresh() {
        if (canRefreshPipelineSafely()) {
            hidePipelineRefreshNotice();
            refreshPipelineBoard();
            return;
        }

        pipelineRefreshPending = true;
        showPipelineRefreshNotice();
    }

    function retryPendingPipelineRefresh() {
        if (!pipelineRefreshPending || !canRefreshPipelineSafely()) {
            return;
        }

        pipelineRefreshPending = false;
        hidePipelineRefreshNotice();
        refreshPipelineBoard();
    }

    if (board) {
        ['mousedown', 'touchstart', 'keydown', 'dragstart', 'drop'].forEach((eventName) => {
            board.addEventListener(eventName, markPipelineInteraction, { passive: true });
        });
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            pipelineLastHiddenAt = Date.now();
            return;
        }

        if (pipelineLastHiddenAt && Date.now() - pipelineLastHiddenAt > pipelineFocusRefreshAfterMs) {
            requestPipelineRefresh();
        } else {
            retryPendingPipelineRefresh();
        }
    });

    window.addEventListener('focus', () => {
        if (pipelineLastHiddenAt && Date.now() - pipelineLastHiddenAt > pipelineFocusRefreshAfterMs) {
            requestPipelineRefresh();
            return;
        }

        retryPendingPipelineRefresh();
    });

    if (pipelineNotificationsButton && pipelineNotificationsMenu) {
        pipelineNotificationsButton.addEventListener('click', (event) => {
            event.stopPropagation();
            renderPipelineNotifications();
            const isHidden = pipelineNotificationsMenu.classList.toggle('hidden');
            pipelineNotificationsButton.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        });

        document.addEventListener('click', (event) => {
            if (pipelineNotificationsMenu.classList.contains('hidden')) {
                return;
            }
            if (pipelineNotificationsMenu.contains(event.target) || pipelineNotificationsButton.contains(event.target)) {
                return;
            }
            pipelineNotificationsMenu.classList.add('hidden');
            pipelineNotificationsButton.setAttribute('aria-expanded', 'false');
        });
    }

    if (board) {
        const pipelineNotificationObserver = new MutationObserver(() => renderPipelineNotifications());
        pipelineNotificationObserver.observe(board, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['data-stage-key', 'data-lead-unread-message-count', 'data-lead-last-inbound-at', 'data-lead-last-outbound-at'],
        });
    }

    renderPipelineNotifications();
    window.setInterval(requestPipelineRefresh, pipelineAutoRefreshMs);})();

</script>
