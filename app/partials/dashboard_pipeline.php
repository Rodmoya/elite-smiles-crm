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
$pipelineCounts = $pipelineCounts ?? [];
$pipelineRows = $pipelineRows ?? [];
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
                <button
                    type="button"
                    id="open-new-lead-modal"
                    class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                >
                    + New Lead
                </button>

                <button
                    type="button"
                    id="run-followup-check"
                    class="inline-flex items-center justify-center rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 transition hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Check Follow-Ups
                </button>

                <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-600">
                    Fixed board height + drag auto-scroll
                </div>
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
                class="overflow-x-auto overflow-y-hidden rounded-[1.5rem] border border-slate-200 bg-slate-50/50 pb-3"
            >
                <div
                    id="lead-pipeline-board"
                    class="flex min-w-[1500px] items-start gap-4 p-4"
                >
                    <?php foreach ($stageMap as $stageKey => $stageLabel): ?>
                        <?php $rows = $pipelineRows[$stageKey] ?? []; ?>

                        <div
                            class="pipeline-column flex h-[560px] w-[300px] shrink-0 flex-col rounded-[1.5rem] border border-slate-200 bg-slate-50/80 p-3 transition"
                            data-stage-key="<?= e($stageKey) ?>"
                            data-stage-label="<?= e($stageLabel) ?>"
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

                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-medium <?= e(lead_stage_badge_class($stageKey)) ?>">
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

            <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-700">
                Board is fixed-height, columns scroll vertically, and dragging near the left/right edge auto-scrolls horizontally.
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
                                        <?php foreach ($stageMap as $stageKey => $stageLabel): ?>
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
    <div class="h-screen overflow-y-auto">
        <div class="min-h-screen w-full bg-white">
            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 bg-white px-6 py-4 shadow-sm">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Lead Workspace</p>
                    <h3 id="modal-lead-name" class="mt-2 text-2xl font-semibold text-slate-900">Lead</h3>
                    <p id="modal-lead-stage" class="mt-1 text-sm text-slate-500">Stage</p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        id="lead-delete-button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-red-200 bg-red-50 text-red-600 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60"
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
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-600 transition hover:bg-slate-100"
                        aria-label="Close"
                    >
                        x
                    </button>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-scroll px-6 py-5 pb-24">
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

                <div class="mb-5 flex flex-wrap items-center gap-2 border-b border-slate-200 pb-4">
                    <button
                        type="button"
                        class="workspace-tab-button inline-flex items-center justify-center rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-medium text-white"
                        data-tab-target="details"
                    >
                        Details
                    </button>

                    <button
                        type="button"
                        class="workspace-tab-button inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600"
                        data-tab-target="communications"
                    >
                        Communications
                    </button>

                    <button
                        type="button"
                        class="workspace-tab-button inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600"
                        data-tab-target="notes"
                    >
                        Notes
                    </button>
                </div>

                <div id="workspace-tab-details" class="workspace-tab-panel">
                    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                        <div class="space-y-5">
                            <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4">
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

                            <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4">
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
                            <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4">
                                <div class="mb-3">
                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Source & Attribution</p>
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

                            <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50/70 p-4">
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
                                                <?php foreach ($stageMap as $stageKey => $stageLabel): ?>
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

                <div id="workspace-tab-communications" class="workspace-tab-panel hidden">
                    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[320px_minmax(460px,1fr)_340px]">
                        <div class="contents">
                            <div class="hidden">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Communication Center</p>
                            </div>

                            <div class="contents">

                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm xl:col-start-2">

                                    <div class="flex items-center justify-between gap-3">

                                        <div>

                                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Unified Timeline</p>

                                            <p class="mt-1 text-sm text-slate-500">Latest patient touchpoints and CRM notes.</p>

                                        </div>

                                        <span class="text-[11px] font-medium text-slate-400">Latest first</span>

                                    </div>

                                    <div id="modal-unified-timeline" class="mt-3 max-h-[340px] space-y-3 overflow-y-auto pr-2">

                                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">

                                            Open a lead to load the timeline.

                                        </div>

                                    </div>

                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm text-slate-600 shadow-sm xl:col-start-1 xl:row-start-1">
                                    <p class="text-xs uppercase tracking-[0.16em] text-slate-400">Selected Lead</p>

                                    <p id="modal-sms-lead-name" class="mt-2 font-semibold text-slate-900">Lead</p>

                                    <p id="modal-sms-lead-phone" class="mt-1 text-slate-500">No phone selected</p>

                                    <p id="modal-sms-opt-status" class="mt-3 inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">SMS status unknown</p>

                                    <div id="modal-sms-dnd-control" class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                        <p class="text-xs uppercase tracking-[0.16em] text-slate-400">DND Status</p>
                                        <div class="mt-3 grid gap-2">
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
                                        <p class="mt-3 text-[11px] leading-5 text-slate-500">DND disables the SMS composer until the patient opts back in.</p>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm xl:col-start-1">

                                    <div class="flex items-center justify-between gap-3">

                                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Scheduling Details</p>

                                        <span class="text-[11px] font-medium text-slate-400">Appointment prep</span>

                                    </div>

                                    <div id="modal-message-thread" class="hidden mt-3 space-y-3 pr-1">

                                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">

                                            Open a lead to load SMS history.

                                        </div>

                                    </div>

                                    <div class="grid grid-cols-1 gap-4">

                                        <div class="rounded-2xl bg-white px-4 py-4">
                                            <label for="modal-lead-dob-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Date of Birth</label>
                                            <input type="date" id="modal-lead-dob-input" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none">
                                        </div>

                                        <div class="rounded-2xl bg-white px-4 py-4">
                                            <label for="modal-lead-preferred-day-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Preferred Day</label>
                                            <input type="text" id="modal-lead-preferred-day-input" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none" placeholder="Example: Thursday">
                                        </div>

                                        <div class="rounded-2xl bg-white px-4 py-4">
                                            <label for="modal-lead-preferred-time-input" class="text-xs uppercase tracking-[0.18em] text-slate-400">Preferred Time</label>
                                            <input type="text" id="modal-lead-preferred-time-input" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none" placeholder="Morning or afternoon">
                                        </div>

                                    </div>

                                </div>



                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm xl:col-start-3 xl:row-start-1">

                                    <div class="flex items-center justify-between gap-3">

                                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Internal Activity</p>

                                        <span class="text-[11px] font-medium text-slate-400">Calls, texts, stages</span>

                                    </div>

                                    <div id="modal-activity-feed" class="mt-3 max-h-[340px] space-y-3 overflow-y-auto pr-2">

                                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">

                                            Activity will appear after calls, texts, replies, and stage moves.

                                        </div>

                                    </div>

                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm xl:col-start-3">

                                    <div class="flex items-center justify-between gap-3">

                                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Email History</p>

                                        <span class="text-[11px] font-medium text-slate-400">Patient email</span>

                                    </div>

                                    <div id="modal-email-history" class="mt-3 max-h-[340px] space-y-3 overflow-y-auto pr-2">

                                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">

                                            Sent patient emails will appear here.

                                        </div>

                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-start-2 xl:row-start-2">

                            <div class="mb-2 flex flex-wrap items-center justify-between gap-3">

                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Conversation Composer</p>

                                <button
                                    type="button"
                                    id="modal-composer-collapse-toggle"
                                    class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                                    aria-expanded="true"
                                >
                                    Hide
                                </button>

                                <div class="ml-auto flex flex-wrap gap-2" id="modal-composer-mode-controls">
                                    <button type="button" data-composer-mode="sms" class="composer-mode-button rounded-full border border-slate-900 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">SMS</button>
                                    <button type="button" data-composer-mode="email" class="composer-mode-button rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600">Email</button>
                                    <button type="button" data-composer-mode="note" class="composer-mode-button rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600">Note</button>
                                </div>
                            </div>

                            <div id="modal-composer-body">
                            <div id="modal-composer-panel-sms" data-composer-panel="sms" class="hidden">
                                <label for="modal-sms-template-select" class="sr-only">Answer Template</label>
                                <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                    <select
                                        id="modal-sms-template-select"
                                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"
                                    >
                                        <option value="">Write custom message</option>
                                        <?php foreach ($smsTemplateOptions as $templateKey => $template): ?>
                                            <option value="<?= e($templateKey) ?>"><?= e((string)($template['label'] ?? $templateKey)) ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <textarea
                                        rows="5"
                                        aria-label="Text message"
                                        id="modal-lead-sms-input"
                                        class="min-h-[150px] w-full resize-y rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm leading-6 outline-none"
                                        placeholder="Type a message..."
                                    ></textarea>

                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p id="modal-lead-sms-status" class="min-h-4 text-xs text-slate-500"></p>

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

                                <div class="mt-3 flex flex-wrap gap-2">
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

                            <div id="modal-composer-panel-email" data-composer-panel="email" class="mt-4 hidden rounded-[1.5rem] border border-slate-200 bg-white p-4">

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
                                    rows="9"
                                    aria-label="Patient email"
                                    id="modal-lead-email-body-input"
                                    class="mt-2 w-full resize-none rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm leading-6 outline-none"
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
                                        id="modal-lead-send-email-button"
                                        class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        Send Email
                                    </button>

                                </div>

                            </div>

                            <div id="modal-composer-panel-note" data-composer-panel="note" class="mt-4 hidden rounded-[1.5rem] border border-slate-200 bg-white p-4">

                                <div class="hidden">
                                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Internal Note</p>
                                    <p class="mt-1 text-sm text-slate-500">Log a call, decision, objection, or next step.</p>
                                </div>

                                <textarea
                                    rows="7"
                                    id="modal-communication-note-input"
                                    class="w-full resize-none rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm leading-6 outline-none"
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


                            <div class="mt-4 rounded-[1.5rem] border border-slate-200 bg-white px-4 py-4">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Next Action</p>
                                <label for="modal-lead-next-follow-up-input" class="mt-4 block text-xs uppercase tracking-[0.18em] text-slate-400">Next Follow-Up</label>
                                <input
                                    type="datetime-local"
                                    id="modal-lead-next-follow-up-input"
                                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none"
                                >

                                <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-6 text-amber-900">
                                    Save every meaningful contact, confirm DOB before scheduling, and move the lead only after the next step is clear.
                                </div>
                                <ul class="hidden mt-3 space-y-2 text-sm text-slate-600">
                                    <li>Complete missing intake fields first.</li>
                                    <li>Confirm service interest and preferred contact method.</li>
                                    <li>Capture source cleanly for attribution reporting.</li>
                                    <li>Move the lead to the correct stage after follow-up.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="shrink-0 border-t border-slate-200 bg-white px-6 py-4">
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        id="workspace-save-main"
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
    const modal = document.getElementById('lead-detail-modal');
    const closeTop = document.getElementById('lead-detail-close');
    const closeBottom = document.getElementById('lead-detail-close-bottom');
    const deleteLeadButton = document.getElementById('lead-delete-button');
    const saveButton = document.getElementById('workspace-save-main');
    const saveButtonNotes = document.getElementById('modal-lead-save-button');
    const saveButtonNotesSmall = document.getElementById('modal-lead-save-notes-button');
    const saveButtonCommunications = document.getElementById('modal-lead-save-button-communications');

    const sendSmsButton = document.getElementById('modal-lead-send-sms-button');
    const draftEmailButton = document.getElementById('modal-lead-draft-email-button');
    const sendEmailButton = document.getElementById('modal-lead-send-email-button');
    const loadThreadButton = document.getElementById('modal-lead-load-thread-button');
    const followupCheckButton = document.getElementById('run-followup-check');
    const saveStatus = document.getElementById('modal-save-status-footer');

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

    const modalLeadDobInput = document.getElementById('modal-lead-dob-input');

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

    const messageThread = document.getElementById('modal-message-thread');

    const activityFeed = document.getElementById('modal-activity-feed');
    const unifiedTimeline = document.getElementById('modal-unified-timeline');
    const emailHistory = document.getElementById('modal-email-history');

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
    const tabPanels = {
        details: document.getElementById('workspace-tab-details'),
        notes: document.getElementById('workspace-tab-notes'),
        communications: document.getElementById('workspace-tab-communications')
    };

    if (!board || !viewport) return;

    let draggedCard = null;
    let sourceDropzone = null;
    let activeCard = null;
    let dragMouseX = null;
    let autoScrollRaf = null;
    let isSaving = false;
    let isCreatingLead = false;
    let isDeletingLead = false;

    let isSendingSms = false;
    let isDraftingEmail = false;
    let isSendingEmail = false;
    let composerMode = 'sms';

    const csrfToken = <?= json_encode(csrf_token()) ?>;
    const saveDetailsUrl = <?= json_encode(base_url('app/actions/lead_update_details.php')) ?>;
    const saveStageUrl = <?= json_encode(base_url('app/actions/lead_update_stage.php')) ?>;
    const createLeadUrl = <?= json_encode(base_url('app/actions/lead_create.php')) ?>;
    const deleteLeadUrl = <?= json_encode(base_url('app/actions/lead_delete.php')) ?>;

    const sendSmsUrl = <?= json_encode(base_url('app/actions/lead_send_sms.php')) ?>;
    const emailDraftUrl = <?= json_encode(base_url('app/actions/lead_email_draft.php')) ?>;
    const sendEmailUrl = <?= json_encode(base_url('app/actions/lead_send_email.php')) ?>;
    const threadUrl = <?= json_encode(base_url('app/actions/lead_get_thread.php')) ?>;
    const followupCheckUrl = <?= json_encode(base_url('app/actions/lead_followup_check.php')) ?>;
    const smsTemplates = <?= json_encode($smsTemplateOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const stageLabelMap = <?= json_encode($stageMap) ?>;

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

        if (composerMode === 'email' && activeCard && emailBodyInput && emailBodyInput.value.trim() === '') {
            emailBodyInput.value = defaultEmailBody(activeCard);
        }

    }



    function setComposerCollapsed(collapsed) {

        if (!composerBody || !composerCollapseToggle) return;

        composerBody.classList.toggle('hidden', collapsed);
        composerCollapseToggle.textContent = collapsed ? 'Show' : 'Hide';
        composerCollapseToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

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

            follow_up_check: 'Follow-Up Check',

            note: 'Note'

        };

        return labels[type] || String(type || 'Activity').replaceAll('_', ' ');

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

        if (sendSmsButton) {

            sendSmsButton.disabled = safeStatus === 'opted_out' || isSendingSms || isSaving || isDeletingLead;

        }

        if (smsInput) {

            smsInput.disabled = safeStatus === 'opted_out';

            smsInput.placeholder = safeStatus === 'opted_out'

                ? 'This lead opted out of SMS. Do not text unless they opt back in.'

                : 'Write a polished text to this lead...';

        }

    }



    function renderMessageThread(messages) {

        if (!messageThread) return;

        if (!Array.isArray(messages) || messages.length === 0) {

            messageThread.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">No SMS messages logged yet.</div>';

            return;

        }

        messageThread.innerHTML = messages.map((message) => {

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

    }



    function renderActivityFeed(activities) {

        if (!activityFeed) return;

        if (!Array.isArray(activities) || activities.length === 0) {

            activityFeed.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">No activity logged yet.</div>';

            return;

        }

        activityFeed.innerHTML = activities.map((activity) => `

            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">

                <div class="flex flex-wrap items-center justify-between gap-2">

                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">${escapeHtml(activityLabel(activity.type))}</p>

                    <p class="text-[11px] text-slate-400">${escapeHtml(formatThreadTime(activity.created_at || ''))}</p>

                </div>

                <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">${escapeHtml(activity.body || '')}</p>

                ${activity.created_by ? '<p class="mt-2 text-[11px] font-medium text-slate-400">By ' + escapeHtml(activity.created_by) + '</p>' : ''}

            </div>

        `).join('');

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

        (thread?.activities || []).forEach((activity) => {
            items.push({
                type: activityLabel(activity.type),
                tone: String(activity.type || '').includes('failed') || String(activity.type || '').includes('issue') ? 'rose' : 'slate',
                time: activity.created_at || '',
                title: activity.created_by ? 'By ' + activity.created_by : 'CRM activity',
                body: activity.body || '',
                meta: '',
            });
        });

        items.sort((a, b) => {
            const bTime = new Date(String(b.time || '').replace(' ', 'T')).getTime() || 0;
            const aTime = new Date(String(a.time || '').replace(' ', 'T')).getTime() || 0;
            return bTime - aTime;
        });

        if (!items.length) {
            unifiedTimeline.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">No communication history yet.</div>';
            return;
        }

        const toneClasses = {
            blue: 'border-blue-100 bg-blue-50 text-blue-950',
            emerald: 'border-emerald-100 bg-emerald-50 text-emerald-950',
            rose: 'border-rose-100 bg-rose-50 text-rose-950',
            slate: 'border-slate-200 bg-slate-50 text-slate-800',
        };

        unifiedTimeline.innerHTML = items.map((item) => `
            <div class="rounded-2xl border px-4 py-3 ${toneClasses[item.tone] || toneClasses.slate}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] opacity-80">${escapeHtml(item.type || 'Activity')}</p>
                    <p class="text-[11px] opacity-70">${escapeHtml(formatThreadTime(item.time || ''))}</p>
                </div>
                <p class="mt-2 text-sm font-semibold">${escapeHtml(item.title || '')}</p>
                <p class="mt-2 whitespace-pre-wrap text-sm leading-6">${escapeHtml(item.body || '')}</p>
                ${item.meta ? `<p class="mt-2 text-[11px] font-medium opacity-70">${escapeHtml(item.meta)}</p>` : ''}
            </div>
        `).join('');

    }

    function renderEmailHistory(emails) {

        if (!emailHistory) return;

        if (!Array.isArray(emails) || emails.length === 0) {

            emailHistory.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">No patient emails logged yet.</div>';

            return;

        }

        emailHistory.innerHTML = emails.map((email) => `

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
                ? 'workspace-tab-button inline-flex items-center justify-center rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-medium text-white'
                : 'workspace-tab-button inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600';
        });

        Object.entries(tabPanels).forEach(([key, panel]) => {
            if (!panel) return;
            panel.classList.toggle('hidden', key !== tabName);
        });
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
                ? 'flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 bg-white px-6 py-4 shadow-sm'
                : 'flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5';
        }

        if (body) {
            body.className = 'px-6 py-5 pb-24';
        }

        if (footer) {
            footer.className = isScreen
                ? 'shrink-0 border-t border-slate-200 bg-white px-6 py-4'
                : 'border-t border-slate-200 bg-white px-6 py-5';
        }
    }

    function openLeadModal(card, preferredTab = 'communications') {
        if (!modal || !card) return;

        const requestedTab = ['communications', 'details', 'notes'].includes(preferredTab) ? preferredTab : 'details';

        setWorkspacePresentation('screen');

        activeCard = card;

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

        if (modalLeadDobInput) modalLeadDobInput.value = card.dataset.leadDateOfBirth || '';

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

        if (smsInput) smsInput.value = defaultSmsMessage(card);

        if (smsTemplateSelect) smsTemplateSelect.value = 'first_follow_up';

        if (smsStatus) smsStatus.textContent = '';
        if (emailSubjectInput) emailSubjectInput.value = 'Your Elite Smiles consultation request';
        if (emailBodyInput) emailBodyInput.value = defaultEmailBody(card);
        if (emailStatus) emailStatus.textContent = '';
        if (communicationNoteInput) communicationNoteInput.value = '';
        if (communicationNoteStatus) communicationNoteStatus.textContent = '';
        setComposerMode('sms');
        setComposerCollapsed(true);

        setSmsOptUi(card.dataset.leadSmsOptStatus || 'unknown');

        renderThreadSnapshot({ messages: [], activities: [], emails: [] });

        loadLeadThread();



        buildNotesHistory(card.dataset.leadNotes || '');
        updateMissingPanel();
        setActiveTab(requestedTab);

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
        clearMissingHighlights();
        setDeleteButtonState(false);
        if (saveStatus) saveStatus.textContent = '';
    }

    function resetNewLeadForm() {
        if (newLeadFullName) newLeadFullName.value = '';
        if (newLeadPhone) newLeadPhone.value = '';
        if (newLeadEmail) newLeadEmail.value = '';
        if (newLeadPreferredContact) newLeadPreferredContact.value = '';
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
        const preferredContact = newLeadPreferredContact ? newLeadPreferredContact.value : '';
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

                try {
                    await saveLeadStage(activeCard, requestedStageKey, requestedStageLabel);
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

        if (!activeCard || isSendingSms || isDeletingLead || isSaving) return false;

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

        }

    }

    async function draftLeadEmail() {

        if (!activeCard || isDraftingEmail || isDeletingLead || isSaving) return false;

        const leadId = activeCard.dataset.leadId || '';
        const email = modalLeadEmailInput ? modalLeadEmailInput.value.trim() : (activeCard.dataset.leadEmail || '');

        if (!leadId) {
            if (emailStatus) emailStatus.textContent = 'Could not determine which lead to email.';
            return false;
        }

        if (!email) {
            if (emailStatus) emailStatus.textContent = 'Add an email address before drafting.';
            return false;
        }

        if (isDirty()) {
            if (emailStatus) emailStatus.textContent = 'Saving lead details before drafting...';
            const saved = await saveLeadDetails();
            if (!saved) {
                if (emailStatus) emailStatus.textContent = 'Save the lead details before drafting email.';
                return false;
            }
        }

        isDraftingEmail = true;
        if (draftEmailButton) draftEmailButton.disabled = true;
        if (sendEmailButton) sendEmailButton.disabled = true;
        if (emailStatus) emailStatus.textContent = 'Drafting email with AI...';

        try {
            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);
            formData.append('lead_id', leadId);
            formData.append('mode', 'first_touch_email');
            formData.append('instruction', 'Draft a concise first follow-up email for this lead. Invite them to schedule a free consultation.');

            const response = await fetch(emailDraftUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await parseJsonResponse(response);
            if (!response.ok || !data.ok) throw new Error(data.message || 'Failed to draft email.');

            if (emailSubjectInput) emailSubjectInput.value = data.draft?.subject || '';
            if (emailBodyInput) emailBodyInput.value = data.draft?.body || '';
            if (emailStatus) emailStatus.textContent = 'Email drafted. Review before sending.';

            await loadLeadThread();
            return true;
        } catch (error) {
            if (emailStatus) emailStatus.textContent = error.message || 'Failed to draft email.';
            return false;
        } finally {
            isDraftingEmail = false;
            if (draftEmailButton) draftEmailButton.disabled = false;
            if (sendEmailButton) sendEmailButton.disabled = false;
        }

    }

    async function sendLeadEmail() {

        if (!activeCard || isSendingEmail || isDraftingEmail || isDeletingLead || isSaving) return false;

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

            if (emailStatus) emailStatus.textContent = data.message || 'Email sent.';
            await loadLeadThread();
            return true;
        } catch (error) {
            if (emailStatus) emailStatus.textContent = error.message || 'Failed to send email.';
            return false;
        } finally {
            isSendingEmail = false;
            if (draftEmailButton) draftEmailButton.disabled = false;
            if (sendEmailButton) sendEmailButton.disabled = false;
            if (saveButton) saveButton.disabled = false;
            if (saveButtonCommunications) saveButtonCommunications.disabled = false;
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

    async function saveLeadStage(card, newStageKey, newStageLabel) {
        const leadId = card.dataset.leadId || '';
        const formData = new FormData();
        formData.append('_csrf_token', csrfToken);
        formData.append('lead_id', leadId);
        formData.append('status', newStageKey);

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
        modalLeadCampaignInput
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
    if (saveButtonNotes) saveButtonNotes.addEventListener('click', saveLeadDetails);
    if (saveButtonNotesSmall) saveButtonNotesSmall.addEventListener('click', saveLeadDetails);
    if (saveButtonCommunications) saveButtonCommunications.addEventListener('click', saveLeadDetails);

    if (sendSmsButton) sendSmsButton.addEventListener('click', sendLeadSms);
    if (draftEmailButton) draftEmailButton.addEventListener('click', draftLeadEmail);
    if (sendEmailButton) sendEmailButton.addEventListener('click', sendLeadEmail);
    if (saveCommunicationNoteButton) saveCommunicationNoteButton.addEventListener('click', saveCommunicationNote);

    if (loadThreadButton) loadThreadButton.addEventListener('click', loadLeadThread);

    smsOptStatusInputs.forEach((input) => {
        input.addEventListener('change', function () {
            setSmsOptUi(input.value || 'unknown');
            if (saveStatus && activeCard) {
                saveStatus.textContent = 'DND status changed. Save changes to keep it.';
            }
        });
    });

    if (smsTemplateSelect) smsTemplateSelect.addEventListener('change', applySelectedSmsTemplate);
    composerModeButtons.forEach((button) => {
        button.addEventListener('click', function () {
            setComposerCollapsed(false);
            setComposerMode(button.dataset.composerMode || 'sms');
        });
    });

    if (composerCollapseToggle) {
        composerCollapseToggle.addEventListener('click', function () {
            setComposerCollapsed(composerCollapseToggle.getAttribute('aria-expanded') === 'true');
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

    if (openNewLeadButton) openNewLeadButton.addEventListener('click', openNewLeadModal);
    if (closeNewLeadButton) closeNewLeadButton.addEventListener('click', closeNewLeadModal);
    if (cancelNewLeadButton) cancelNewLeadButton.addEventListener('click', closeNewLeadModal);
    if (saveNewLeadButton) saveNewLeadButton.addEventListener('click', createLead);
    if (deleteLeadButton) deleteLeadButton.addEventListener('click', requestDeleteLead);

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

                openLeadModal(card, openBtn.dataset.openTab || 'communications');

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

            const emptyState = dropzone.querySelector('.empty-state');
            if (emptyState) emptyState.remove();

            dropzone.prepend(draggedCard);
            updateColumnCounts();

            try {
                await saveLeadStage(draggedCard, newStageKey, newStageLabel);
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
    setActiveTab('details');
})();
</script>


