<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
$user = smile_design_internal_boot('Smile Cases');
$cases = smile_design_recent_cases(50);
smile_design_render_shell_start('Smile Cases');
smile_design_page_header('Smile Cases', 'All internal smile design cases and intake status.');
?>
<div class="rounded-md border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-[0.16em] text-slate-500">
                <tr><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Style</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Created</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($cases as $case): ?>
                    <?php $frontAfter = smile_design_selected_after_version((int)$case['id'], 'front'); ?>
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <?php if ($frontAfter): ?>
                                    <img class="h-14 w-14 shrink-0 rounded-md border border-slate-200 bg-slate-50 object-cover" src="<?= e(smile_design_after_url((int)$frontAfter['id'])) ?>" alt="Front after thumbnail for <?= e((string)$case['patient_name']) ?>">
                                <?php else: ?>
                                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-md border border-dashed border-slate-300 bg-slate-50 text-slate-400" aria-label="No front after photo">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M4 7.75A2.75 2.75 0 0 1 6.75 5h10.5A2.75 2.75 0 0 1 20 7.75v8.5A2.75 2.75 0 0 1 17.25 19H6.75A2.75 2.75 0 0 1 4 16.25v-8.5Z" stroke="currentColor" stroke-width="1.8"/>
                                            <path d="m7 15 2.75-2.75 2.1 2.1L14.5 11 17 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M8.75 9.5h.01" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <a class="font-semibold text-slate-900 hover:underline" href="<?= e(base_url('smile-design/cases/' . (int)$case['id'])) ?>"><?= e((string)$case['patient_name']) ?></a>
                                    <div class="text-xs font-normal text-slate-500"><?= e((string)$case['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3"><?= e((string)$case['lvi_style_key']) ?></td>
                        <td class="px-4 py-3"><?= e(smile_design_status_labels()[(string)$case['status']] ?? (string)$case['status']) ?></td>
                        <td class="px-4 py-3"><?= e(format_datetime((string)$case['created_at'])) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" href="<?= e(base_url('smile-design/cases/' . (int)$case['id'])) ?>">Open</a>
                                <form method="POST" action="<?= e(base_url('app/actions/smile_design_case_delete.php')) ?>" data-confirm="Delete this entire smile case? This will remove before photos, after versions, links, and activity for this case.">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="case_id" value="<?= e((string)$case['id']) ?>">
                                    <button class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-rose-200 bg-white text-rose-700 hover:bg-rose-50" type="submit" aria-label="Delete <?= e((string)$case['patient_name']) ?>">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M4 7h16" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                                            <path d="M9.5 7V5.75A1.75 1.75 0 0 1 11.25 4h1.5a1.75 1.75 0 0 1 1.75 1.75V7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                                            <path d="M18 7 17.2 18.2A2 2 0 0 1 15.2 20H8.8a2 2 0 0 1-2-1.8L6 7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M10 11v5M14 11v5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$cases): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No cases yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php smile_design_render_shell_end(); ?>
