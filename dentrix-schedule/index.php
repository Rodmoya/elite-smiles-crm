<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

dentrix_schedule_internal_boot('Dentrix Schedule Bridge');

$weekdaySnapshot = dentrix_schedule_weekday_status_snapshot();
$recentImports = dentrix_schedule_recent_imports(50);

function dentrix_schedule_status_badge(?string $status): string
{
    $status = $status ?: 'pending';
    $classes = [
        'parsed' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'skipped' => 'bg-slate-50 text-slate-600 border-slate-200',
        'failed' => 'bg-red-50 text-red-800 border-red-200',
        'pending' => 'bg-amber-50 text-amber-800 border-amber-200',
    ];
    $class = $classes[$status] ?? $classes['pending'];
    return '<span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ' . $class . '">' . e(ucfirst($status)) . '</span>';
}

dentrix_schedule_render_shell_start('Dentrix Schedule Bridge');
?>

<section class="mb-6 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-8">
    <h2 class="text-xl font-semibold text-slate-900">Upload a Dentrix Day View PDF</h2>
    <p class="mt-2 max-w-2xl text-sm text-slate-600">
        Print the Dentrix Appointment Book Day View for a weekday and upload it here. The Drive folder sync
        (<code class="rounded bg-slate-100 px-1 py-0.5 text-xs">1 Appointments CRM</code>) is a Phase 2 addition — for now, upload manually.
    </p>

    <form
        class="mt-5 space-y-5 rounded-2xl border border-slate-200 bg-slate-50/60 p-5"
        method="POST"
        action="<?= e(base_url('dentrix-schedule/upload')) ?>"
        enctype="multipart/form-data"
    >
        <?= csrf_input() ?>

        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            <label class="space-y-2 text-sm font-semibold text-slate-700">
                Day View PDF
                <input
                    required
                    name="schedule_pdf"
                    type="file"
                    accept=".pdf,application/pdf"
                    class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                >
            </label>
            <label class="space-y-2 text-sm font-semibold text-slate-700">
                Weekday
                <select name="day_index" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2">
                    <option value="auto">Auto-detect from filename (1.pdf-5.pdf)</option>
                    <?php foreach (dentrix_schedule_weekday_names() as $dayIndex => $label): ?>
                        <option value="<?= e((string)$dayIndex) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="flex items-center gap-2 self-end pb-3 text-sm font-semibold text-slate-700">
                <input type="checkbox" name="force" value="1" class="rounded border-slate-300">
                Force re-parse (ignore unchanged-hash skip)
            </label>
            <div class="flex items-end">
                <button
                    class="inline-flex h-11 w-full items-center justify-center rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white"
                    type="submit"
                >
                    Upload &amp; Import
                </button>
            </div>
        </div>
        <p class="text-xs text-slate-500">
            Re-uploading the same weekday overwrites only that weekday's imported Dentrix appointments for the date printed
            on the PDF. Appointments booked through the CRM are never overwritten.
        </p>
    </form>
</section>

<section class="mb-6 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-8">
    <h2 class="text-xl font-semibold text-slate-900">This week's weekday status</h2>
    <div class="mt-4 overflow-hidden rounded-[1.5rem] border border-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Weekday</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Schedule date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Last import status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Source file</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Imported appointments</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Imported at</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php foreach ($weekdaySnapshot as $row): ?>
                        <?php $latest = $row['latest_import']; ?>
                        <tr>
                            <td class="px-4 py-4 align-top text-sm font-semibold"><?= e($row['label']) ?></td>
                            <td class="px-4 py-4 align-top text-sm"><?= e((string)($latest['schedule_date'] ?? '—')) ?></td>
                            <td class="px-4 py-4 align-top text-sm"><?= dentrix_schedule_status_badge($latest['parsed_status'] ?? null) ?></td>
                            <td class="px-4 py-4 align-top text-sm text-slate-600"><?= e((string)($latest['source_file_name'] ?? '—')) ?></td>
                            <td class="px-4 py-4 align-top text-sm"><?= e((string)$row['appointment_count']) ?></td>
                            <td class="px-4 py-4 align-top text-sm text-slate-500"><?= e((string)($latest['imported_at'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-8">
    <h2 class="text-xl font-semibold text-slate-900">Import history</h2>
    <p class="mt-1 text-sm text-slate-600">Every sync attempt is kept for audit/debugging, including skipped and failed ones.</p>
    <div class="mt-4 overflow-hidden rounded-[1.5rem] border border-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Created</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">File</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Weekday</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Schedule date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Source</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php if (empty($recentImports)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">No imports yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentImports as $import): ?>
                            <tr>
                                <td class="px-4 py-4 align-top text-sm text-slate-500"><?= e((string)($import['created_at'] ?? '')) ?></td>
                                <td class="px-4 py-4 align-top text-sm"><?= e((string)($import['source_file_name'] ?? '')) ?></td>
                                <td class="px-4 py-4 align-top text-sm"><?= e(dentrix_schedule_day_name((int)($import['source_day_index'] ?? 0))) ?></td>
                                <td class="px-4 py-4 align-top text-sm"><?= e((string)($import['schedule_date'] ?? '—')) ?></td>
                                <td class="px-4 py-4 align-top text-sm"><?= dentrix_schedule_status_badge($import['parsed_status'] ?? null) ?></td>
                                <td class="px-4 py-4 align-top text-sm text-slate-500"><?= e(ucfirst((string)($import['source_type'] ?? ''))) ?></td>
                                <td class="px-4 py-4 align-top text-sm text-slate-600"><?= e((string)($import['error_message'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php
dentrix_schedule_render_shell_end();
