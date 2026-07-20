<?php

declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/partials/dashboard_action_queue.php
 *
 * Expected variables:
 * - $actionQueueRows (array)
 * - $actionQueueSummary (array)
 */

$actionQueueRows = $actionQueueRows ?? (function_exists('lead_action_queue_rows') ? lead_action_queue_rows(12) : []);
$actionQueueSummary = $actionQueueSummary ?? (function_exists('lead_action_queue_summary') ? lead_action_queue_summary($actionQueueRows) : ['total' => count($actionQueueRows)]);
$actionQueueCompact = !empty($actionQueueCompact);
$actionQueueDisplayLimit = isset($actionQueueDisplayLimit) ? max(1, (int)$actionQueueDisplayLimit) : ($actionQueueCompact ? 12 : 9);
$actionQueueDisplayRows = array_slice($actionQueueRows, 0, $actionQueueDisplayLimit);
$actionQueueTitle = $actionQueueTitle ?? 'Needs Attention Today';
$actionQueueSubtitle = $actionQueueSubtitle ?? 'Open CRM and start here: replies, due follow-ups, scheduling recovery, and cleanup items.';

$queueMetricItems = [
    ['label' => 'Reply', 'value' => (int)($actionQueueSummary['reply_needed'] ?? 0), 'class' => 'border-blue-200 bg-blue-50 text-blue-700'],
    ['label' => 'First touch', 'value' => (int)($actionQueueSummary['first_touch'] ?? 0), 'class' => 'border-sky-200 bg-sky-50 text-sky-700'],
    ['label' => 'Follow-up', 'value' => (int)($actionQueueSummary['follow_up'] ?? 0), 'class' => 'border-amber-200 bg-amber-50 text-amber-800'],
    ['label' => 'Schedule', 'value' => (int)($actionQueueSummary['schedule'] ?? 0), 'class' => 'border-teal-200 bg-teal-50 text-teal-700'],
    ['label' => 'Cleanup', 'value' => (int)($actionQueueSummary['cleanup'] ?? 0), 'class' => 'border-rose-200 bg-rose-50 text-rose-700'],
];

if (!function_exists('lead_action_queue_contact_line')) {
    function lead_action_queue_contact_line(array $lead): string
    {
        $phone = trim((string)($lead['phone'] ?? ''));
        $email = trim((string)($lead['email'] ?? ''));
        if ($phone !== '' && $email !== '') {
            return $phone . ' / ' . $email;
        }
        return $phone !== '' ? $phone : $email;
    }
}

if (!function_exists('lead_action_queue_link')) {
    function lead_action_queue_link(int $leadId): string
    {
        return base_url('leads.php') . ($leadId > 0 ? '?lead_id=' . rawurlencode((string)$leadId) : '');
    }
}

?>

<section class="<?= $actionQueueCompact ? 'mb-4' : 'mb-6' ?>">
    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Action Queue</p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-semibold text-slate-950"><?= e((string)$actionQueueTitle) ?></h2>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">
                        <?= e((string)count($actionQueueDisplayRows)) ?> shown
                    </span>
                </div>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500"><?= e((string)$actionQueueSubtitle) ?></p>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-5 xl:min-w-[34rem]">
                <?php foreach ($queueMetricItems as $metric): ?>
                    <div class="rounded-2xl border <?= e($metric['class']) ?> px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em]"><?= e($metric['label']) ?></p>
                        <p class="mt-1 text-xl font-semibold leading-none"><?= e((string)$metric['value']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (empty($actionQueueDisplayRows)): ?>
            <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                Nothing urgent right now. The pipeline is clean for the moment.
            </div>
        <?php else: ?>
            <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-2 2xl:grid-cols-3">
                <?php foreach ($actionQueueDisplayRows as $lead): ?>
                    <?php
                    $queue = (array)($lead['_action_queue'] ?? []);
                    $leadId = (int)($lead['id'] ?? 0);
                    $leadName = trim((string)($lead['full_name'] ?? ''));
                    $leadName = $leadName !== '' ? $leadName : 'Unnamed Lead';
                    $actionLabel = (string)($queue['action_label'] ?? 'Review next step');
                    $actionTone = (string)($queue['action_tone'] ?? 'slate');
                    $stageLabel = (string)($queue['stage_label'] ?? '');
                    $sourceLabel = (string)($queue['source_label'] ?? '');
                    $reason = (string)($queue['reason'] ?? 'Review next step.');
                    $tab = (string)($queue['tab'] ?? 'communications');
                    $contactLine = lead_action_queue_contact_line($lead);
                    $lastTouch = trim((string)($queue['last_touch_at'] ?? ''));
                    $link = lead_action_queue_link($leadId);
                    $badgeClass = function_exists('lead_conversion_badge_class')
                        ? lead_conversion_badge_class($actionTone)
                        : 'border-slate-200 bg-slate-50 text-slate-600';
                    ?>
                    <article class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 transition hover:border-slate-300 hover:bg-white hover:shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a
                                    href="<?= e($link) ?>"
                                    class="block truncate text-sm font-semibold text-slate-950 hover:text-blue-700"
                                    data-open-action-lead="<?= e((string)$leadId) ?>"
                                    data-open-action-tab="<?= e($tab) ?>"
                                >
                                    <?= e($leadName) ?>
                                </a>
                                <?php if ($contactLine !== ''): ?>
                                    <p class="mt-1 truncate text-xs text-slate-500"><?= e($contactLine) ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="shrink-0 rounded-md border <?= e($badgeClass) ?> px-2 py-1 text-[10px] font-bold">
                                <?= e($actionLabel) ?>
                            </span>
                        </div>

                        <p class="mt-3 line-clamp-2 text-xs leading-5 text-slate-600"><?= e($reason) ?></p>

                        <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500">
                            <?php if ($stageLabel !== ''): ?>
                                <span class="rounded-md border border-slate-200 bg-white px-2 py-1 font-semibold text-slate-600"><?= e($stageLabel) ?></span>
                            <?php endif; ?>
                            <?php if ($sourceLabel !== ''): ?>
                                <span class="max-w-full truncate rounded-md border border-slate-200 bg-white px-2 py-1"><?= e($sourceLabel) ?></span>
                            <?php endif; ?>
                            <?php if ($lastTouch !== ''): ?>
                                <span class="rounded-md border border-slate-200 bg-white px-2 py-1">Last <?= e(format_datetime($lastTouch, 'M j g:i A')) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2">
                            <a
                                href="<?= e($link) ?>"
                                class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2"
                                data-open-action-lead="<?= e((string)$leadId) ?>"
                                data-open-action-tab="<?= e($tab) ?>"
                            >
                                Open
                            </a>
                            <a
                                href="<?= e($link) ?>"
                                class="inline-flex h-10 items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-3 text-xs font-semibold text-blue-800 transition hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                data-open-action-lead="<?= e((string)$leadId) ?>"
                                data-open-action-tab="communications"
                            >
                                Text
                            </a>
                            <a
                                href="<?= e($link) ?>"
                                class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
                                data-open-action-lead="<?= e((string)$leadId) ?>"
                                data-open-action-tab="details"
                            >
                                Details
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
