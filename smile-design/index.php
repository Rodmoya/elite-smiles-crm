<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
$user = smile_design_internal_boot('Smile Design');
$counts = smile_design_counts();
$cases = smile_design_recent_cases(8);
smile_design_render_shell_start('Smile Design');
smile_design_page_header('Smile Design Hub', 'Phase 1 workspace for staff intake, smile cases, compare-ready previews, doctor review, and patient sharing.');
?>
<section class="grid gap-4 md:grid-cols-4">
    <?php foreach ([['Smile Cases', $counts['cases']], ['Intake Submitted', $counts['submitted']], ['Preview Ready', $counts['preview_ready']], ['Real Results', $counts['real_results']]] as $card): ?>
        <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.18em] text-slate-500"><?= e($card[0]) ?></p>
            <p class="mt-3 text-3xl font-semibold"><?= e((string)$card[1]) ?></p>
        </div>
    <?php endforeach; ?>
</section>
<section class="mt-6 grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
    <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Recent Smile Cases</h2>
            <a class="text-sm font-semibold text-slate-700 underline" href="<?= e(base_url('smile-design/cases')) ?>">View all</a>
        </div>
        <div class="mt-4 divide-y divide-slate-100">
            <?php if (!$cases): ?>
                <p class="rounded-md border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500">No smile cases yet.</p>
            <?php endif; ?>
            <?php foreach ($cases as $case): ?>
                <a class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between" href="<?= e(base_url('smile-design/cases/' . (int)$case['id'])) ?>">
                    <span>
                        <span class="block text-sm font-semibold"><?= e((string)$case['patient_name']) ?></span>
                        <span class="block text-xs text-slate-500"><?= e((string)$case['lvi_style_key']) ?> · <?= e(format_datetime((string)$case['created_at'])) ?></span>
                    </span>
                    <span class="w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700"><?= e(smile_design_status_labels()[(string)$case['status']] ?? (string)$case['status']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="space-y-4">
        <a class="block rounded-md border border-slate-200 bg-white p-5 shadow-sm" href="<?= e(base_url('smile-design/staff-intake')) ?>">
            <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Case Intake</p>
            <h2 class="mt-2 text-lg font-semibold">Create smile cases and upload before photos</h2>
        </a>
        <a class="block rounded-md border border-slate-200 bg-white p-5 shadow-sm" href="<?= e(base_url('smile-design/cases')) ?>">
            <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Smile Cases</p>
            <h2 class="mt-2 text-lg font-semibold">Open the Phase 1 case workspace</h2>
        </a>
        <a class="block rounded-md border border-slate-200 bg-white p-5 shadow-sm" href="<?= e(base_url('smile-design/consult')) ?>">
            <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Doctor Consult</p>
            <h2 class="mt-2 text-lg font-semibold">Patient selector, viewer modes, and closing actions</h2>
        </a>
        <a class="block rounded-md border border-slate-200 bg-white p-5 shadow-sm" href="<?= e(base_url('smile-design/lvi-library')) ?>">
            <p class="text-xs uppercase tracking-[0.18em] text-slate-500">LVI Library</p>
            <h2 class="mt-2 text-lg font-semibold">Style references and sample cards</h2>
        </a>
    </div>
</section>
<?php smile_design_render_shell_end(); ?>
