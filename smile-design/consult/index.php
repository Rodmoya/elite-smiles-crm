<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
$user = smile_design_internal_boot('Doctor Consult');
$q = trim((string)get('q', ''));
$selectedId = (int)get('id', 0);
$cases = smile_design_search_cases($q, 80);
if ($selectedId <= 0 && $cases) {
    $selectedId = (int)$cases[0]['id'];
}
$case = $selectedId > 0 ? smile_design_case($selectedId) : null;
$photos = $case ? smile_design_case_photos((int)$case['id']) : [];
$afterVersions = $case ? smile_design_after_versions((int)$case['id']) : [];
$selectedAfter = $case ? smile_design_selected_after_version((int)$case['id']) : null;
$beforeUrl = '';
$afterUrl = $selectedAfter ? smile_design_after_url((int)$selectedAfter['id']) : '';
foreach ($photos as $photo) {
    if ((string)$photo['kind'] === 'before' && $beforeUrl === '') $beforeUrl = smile_design_photo_url((int)$photo['id']);
}
$alignment = $selectedAfter ? smile_design_alignment_for_after($selectedAfter) : smile_design_alignment_defaults();
$alignmentEdit = ($case && $selectedAfter) ? [
    'pair_type' => 'case_after',
    'case_id' => (int)$case['id'],
    'before_photo_id' => (int)($selectedAfter['before_photo_id'] ?? 0),
    'after_version_id' => (int)$selectedAfter['id'],
    'photo_type' => (string)($selectedAfter['photo_type'] ?? ''),
    'return_url' => $_SERVER['REQUEST_URI'] ?? base_url('smile-design/consult/' . (int)$case['id']),
] : [];
$samples = db_all('SELECT * FROM lvi_style_samples WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 8');
$sampleCases = db_all('SELECT * FROM smile_sample_cases WHERE active = 1 ORDER BY sort_order ASC, id DESC LIMIT 6');
$results = smile_design_real_after_gallery_versions(6);
smile_design_render_shell_start('Doctor Consult');
smile_design_page_header('Doctor Consult Tool', 'Patient selector, viewer modes, style references, and closing actions for live case review.');
?>
<form class="mb-5 flex flex-col gap-2 sm:flex-row" method="GET">
    <input name="q" value="<?= e($q) ?>" class="w-full rounded-md border border-slate-300 px-3 py-3" placeholder="Search name, email, phone, case ID, procedure, status">
    <button class="rounded-md bg-slate-950 px-5 py-3 text-sm font-semibold text-white">Search</button>
</form>

<div class="mb-5 grid gap-3 md:grid-cols-4">
    <div class="rounded-md border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-[0.16em] text-slate-500">Patient</p><p class="mt-1 font-semibold"><?= e((string)($case['patient_name'] ?? 'Select a case')) ?></p></div>
    <div class="rounded-md border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-[0.16em] text-slate-500">Procedure</p><p class="mt-1 font-semibold"><?= e((string)($case['procedure_interest'] ?? '')) ?></p></div>
    <div class="rounded-md border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-[0.16em] text-slate-500">LVI Style</p><p class="mt-1 font-semibold"><?= e((string)($case['lvi_style_key'] ?? '')) ?></p></div>
    <div class="rounded-md border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-[0.16em] text-slate-500">Selected After</p><p class="mt-1 font-semibold"><?= e($selectedAfter ? ('#' . (string)$selectedAfter['version_number'] . ' ' . (string)$selectedAfter['version_title']) : 'None') ?></p></div>
</div>

<section class="grid gap-5 xl:grid-cols-[0.78fr_1.45fr_0.86fr]">
    <aside class="space-y-4">
        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Case Selector</h2>
            <div class="mt-3 max-h-[380px] space-y-2 overflow-auto">
                <?php foreach ($cases as $row): ?>
                    <a class="block rounded-md border <?= (int)$row['id'] === $selectedId ? 'border-slate-900 bg-slate-100' : 'border-slate-200 bg-white' ?> p-3" href="<?= e(base_url('smile-design/consult/' . (int)$row['id'])) ?>">
                        <span class="block text-sm font-semibold"><?= e((string)$row['patient_name']) ?></span>
                        <span class="block text-xs text-slate-500">#<?= e((string)$row['id']) ?> · <?= e((string)$row['procedure_interest']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Uploaded Photos</h2>
            <div class="mt-3 space-y-2 text-sm text-slate-600">
                <?php foreach ($photos as $photo): ?><p><?= e(ucwords((string)$photo['kind'])) ?> · <?= e((string)($photo['photo_type'] ?? 'Front')) ?></p><?php endforeach; ?>
                <?php if (!$photos): ?><p>No photos uploaded yet.</p><?php endif; ?>
            </div>
        </div>
        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="font-semibold">AFTER Versions</h2>
            <div class="mt-3 space-y-2 text-sm text-slate-600">
                <?php foreach ($afterVersions as $version): ?>
                    <a class="block rounded-md border border-slate-200 p-2" href="<?= e(base_url('smile-design/cases/' . (int)$version['case_id'])) ?>">
                        #<?= e((string)$version['version_number']) ?> <?= e((string)$version['version_title']) ?>
                        <?= (int)$version['selected_by_doctor'] === 1 ? ' · Selected' : '' ?>
                        <?= (int)$version['approved_for_patient_preview'] === 1 ? ' · Patient Approved' : '' ?>
                    </a>
                <?php endforeach; ?>
                <?php if (!$afterVersions): ?><p>No after versions yet.</p><?php endif; ?>
            </div>
        </div>
        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Doctor Notes</h2>
            <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-600"><?= e((string)($case['doctor_notes'] ?? $case['notes'] ?? '')) ?></p>
        </div>
    </aside>

    <main class="space-y-4">
        <div class="rounded-md bg-black p-3">
            <?php smile_before_after_viewer($beforeUrl, $afterUrl, ['title' => (string)($case['patient_name'] ?? 'Select a case'), 'mode' => 'ba', 'alignment' => $alignment, 'alignment_edit' => $alignmentEdit]); ?>
            <p class="mt-3 px-1 text-xs leading-5 text-white/65">AI Smile Preview. Cosmetic visualization. Not a clinical guarantee. Individual results may vary.</p>
        </div>
        <div class="grid grid-cols-3 gap-2 text-xs font-semibold text-slate-600 sm:grid-cols-6">
            <?php foreach (['Front', 'Close-up', 'Left 45', 'Right 45', 'Full Head', 'Smile Close-Up'] as $angle): ?>
                <button class="rounded-md border border-slate-300 bg-white px-2 py-2" type="button"><?= e($angle) ?></button>
            <?php endforeach; ?>
        </div>
    </main>

    <aside class="space-y-4">
        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Closing Actions</h2>
            <div class="mt-3 grid gap-2">
                <?php if ($case): ?>
                    <form method="POST" action="<?= e(base_url('app/actions/smile_design_preview_link.php')) ?>"><?= csrf_input() ?><input type="hidden" name="case_id" value="<?= e((string)$case['id']) ?>"><button class="w-full rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Create Patient Preview Link</button></form>
                    <a class="rounded-md border border-slate-300 px-3 py-2 text-center text-sm font-semibold" href="<?= e(base_url('smile-design/cases/' . (int)$case['id'] . '/present')) ?>">Present</a>
                    <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" type="button">Copy Preview Link placeholder</button>
                    <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" type="button">View as Patient placeholder</button>
                    <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" type="button">Add Note placeholder</button>
                    <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" type="button">Mark Interested placeholder</button>
                    <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" type="button">Schedule Consultation placeholder</button>
                    <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" type="button">Create Treatment Plan placeholder</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="font-semibold">LVI Standard Style Chart</h2>
            <div class="mt-3 grid gap-2">
                <?php foreach ($samples as $sample): ?>
                    <button class="rounded-md border border-slate-200 bg-slate-50 p-3 text-left" type="button">
                        <span class="block text-sm font-semibold"><?= e((string)$sample['title']) ?></span>
                        <span class="block text-xs leading-5 text-slate-500"><?= e(str_limit((string)$sample['description'], 86)) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Real Patient Samples</h2>
            <div class="mt-3 space-y-2 text-sm text-slate-600">
                <?php foreach ($results as $result): ?><p><?= e((string)$result['patient_name']) ?> · <?= e((string)$result['procedure_interest']) ?></p><?php endforeach; ?>
                <?php if (!$results): ?><p>Approved real clinical afters will appear here.</p><?php endif; ?>
            </div>
        </div>
        <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Preloaded Sample Cases</h2>
            <div class="mt-3 space-y-2 text-sm text-slate-600">
                <?php foreach ($sampleCases as $sample): ?><p><?= e((string)$sample['title']) ?> · <?= e((string)$sample['procedure_label']) ?></p><?php endforeach; ?>
                <?php if (!$sampleCases): ?><p>Sample cases will appear here.</p><?php endif; ?>
            </div>
        </div>
    </aside>
</section>
<?php if ($case) smile_design_audit((int)$case['id'], 'presentation_opened', ['mode' => 'consult'], auth_user_id()); ?>
<?php smile_design_render_shell_end(); ?>
