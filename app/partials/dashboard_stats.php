<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/partials/dashboard_stats.php
 *
 * Expected variables:
 * - $stats (array)
 */

$stats = $stats ?? [
    'total_leads' => 0,
    'leads_today' => 0,
    'leads_week' => 0,
    'conversion_rate' => 0,
    'missed_leads' => 0,
    'active_pages' => 0,
    'pipeline_value_total' => 0.00,
    'closed_value_total' => 0.00,
    'lost_value_total' => 0.00,
    'avg_lead_value' => 0.00,
    'default_opportunity_value' => 10000.00,
];

$statsVariant = $statsVariant ?? 'cards';

if (!function_exists('elite_money')) {
    function elite_money($amount): string
    {
        return '$' . number_format((float)$amount, 0);
    }
}
if ($statsVariant === 'compact') {
    $compactStats = [
        ['label' => 'Total', 'value' => (string)($stats['total_leads'] ?? 0)],
        ['label' => 'Today', 'value' => (string)($stats['leads_today'] ?? 0)],
        ['label' => 'Week', 'value' => (string)($stats['leads_week'] ?? 0)],
        ['label' => 'Open', 'value' => elite_money((float)($stats['pipeline_value_total'] ?? 0))],
        ['label' => 'Closed', 'value' => elite_money((float)($stats['closed_value_total'] ?? 0))],
        ['label' => 'Conv.', 'value' => percent((float)($stats['conversion_rate'] ?? 0))],
    ];
    ?>

<section class="mb-4 overflow-x-auto rounded-[1.25rem] border border-slate-200 bg-white px-3 py-2 shadow-sm">
    <div class="grid min-w-[720px] grid-cols-6 divide-x divide-slate-100">
        <?php foreach ($compactStats as $item): ?>
            <div class="px-3 py-2">
                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400"><?= e($item['label']) ?></p>
                <p class="mt-1 truncate text-lg font-semibold leading-none text-slate-900"><?= e($item['value']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php
    return;
}

?>

<section class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Total Leads</p>
        <p class="mt-3 text-3xl font-semibold leading-none text-slate-900"><?= e((string)($stats['total_leads'] ?? 0)) ?></p>
        <p class="mt-2 text-sm leading-6 text-slate-500">All captured leads</p>
    </div>

    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Today</p>
        <p class="mt-3 text-3xl font-semibold leading-none text-slate-900"><?= e((string)($stats['leads_today'] ?? 0)) ?></p>
        <p class="mt-2 text-sm leading-6 text-slate-500">Created today</p>
    </div>

    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">This Week</p>
        <p class="mt-3 text-3xl font-semibold leading-none text-slate-900"><?= e((string)($stats['leads_week'] ?? 0)) ?></p>
        <p class="mt-2 text-sm leading-6 text-slate-500">Weekly lead volume</p>
    </div>

    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Conversion</p>
        <p class="mt-3 text-3xl font-semibold leading-none text-slate-900"><?= e(percent((float)($stats['conversion_rate'] ?? 0))) ?></p>
        <p class="mt-2 text-sm leading-6 text-slate-500">Won leads rate</p>
    </div>

    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Open Pipeline</p>
        <p class="mt-3 text-2xl font-semibold leading-none text-slate-900"><?= e(elite_money((float)($stats['pipeline_value_total'] ?? 0))) ?></p>
        <p class="mt-3 text-sm leading-6 text-slate-500">Estimated opportunity value</p>
    </div>

    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Closed Value</p>
        <p class="mt-3 text-2xl font-semibold leading-none text-slate-900"><?= e(elite_money((float)($stats['closed_value_total'] ?? 0))) ?></p>
        <p class="mt-3 text-sm leading-6 text-slate-500">Sale closed total</p>
    </div>

    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Avg Lead Value</p>
        <p class="mt-3 text-2xl font-semibold leading-none text-slate-900"><?= e(elite_money((float)($stats['avg_lead_value'] ?? 0))) ?></p>
        <p class="mt-3 text-sm leading-6 text-slate-500">Includes default estimate</p>
    </div>

    <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Default Per Lead</p>
        <p class="mt-3 text-2xl font-semibold leading-none text-slate-900"><?= e(elite_money((float)($stats['default_opportunity_value'] ?? 10000))) ?></p>
        <p class="mt-3 text-sm leading-6 text-slate-500">Starter opportunity amount</p>
    </div>
</section>
