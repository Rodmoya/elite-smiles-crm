<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
$user = smile_design_internal_boot('LVI Library');
$samples = db_all('SELECT * FROM lvi_style_samples WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
$sampleImages = db_all('SELECT * FROM lvi_sample_images WHERE is_active = 1 ORDER BY style_key ASC, sort_order ASC, id DESC LIMIT 60');
$sampleImagesByStyle = [];
foreach ($sampleImages as $image) {
    $sampleImagesByStyle[(string)$image['style_key']][] = $image;
}
smile_design_render_shell_start('LVI Library');
smile_design_page_header('LVI Standard Library', 'Doctor closing-tool reference cards for smile design conversations.');
?>
<form class="mb-6 grid gap-4 rounded-md border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-4" method="POST" enctype="multipart/form-data" action="<?= e(base_url('app/actions/smile_design_lvi_sample_upload.php')) ?>">
    <?= csrf_input() ?>
    <label class="block text-sm font-semibold">Style<select name="style_key" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><?php foreach ($samples as $sample): ?><option value="<?= e((string)$sample['style_key']) ?>"><?= e((string)$sample['title']) ?></option><?php endforeach; ?></select></label>
    <label class="block text-sm font-semibold">Title<input name="title" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
    <label class="block text-sm font-semibold">Procedure<input name="procedure_label" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
    <label class="block text-sm font-semibold">Tags<input name="tags" placeholder="Worn teeth, gaps" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
    <label class="block text-sm font-semibold lg:col-span-2">Description<input name="description" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
    <label class="block text-sm font-semibold">Sample image<input required name="sample_image" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
    <button class="self-end rounded-md bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white" type="submit">Upload LVI Sample</button>
</form>
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($samples as $sample): ?>
        <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex aspect-[4/3] items-center justify-center rounded-md border border-dashed border-slate-300 bg-slate-50 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Sample image</div>
            <?php if (!empty($sampleImagesByStyle[(string)$sample['style_key']])): ?>
                <div class="mt-3 grid grid-cols-3 gap-2">
                    <?php foreach (array_slice($sampleImagesByStyle[(string)$sample['style_key']], 0, 3) as $image): ?>
                        <img class="h-16 w-full rounded-md object-cover" src="<?= e(base_url('app/actions/smile_design_photo.php?lvi_sample_id=' . (int)$image['id'])) ?>" alt="<?= e((string)$image['title']) ?>">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <p class="mt-4 text-xs uppercase tracking-[0.18em] text-slate-500"><?= e((string)$sample['style_key']) ?></p>
            <h2 class="mt-2 text-lg font-semibold"><?= e((string)$sample['title']) ?></h2>
            <p class="mt-3 min-h-20 text-sm leading-6 text-slate-600"><?= e((string)$sample['description']) ?></p>
            <div class="mt-4 grid gap-2">
                <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" type="button">View samples placeholder</button>
                <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white" type="button">Apply to case placeholder</button>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php smile_design_audit(null, 'lvi_style_viewed', ['count' => count($samples)], auth_user_id()); ?>
<?php smile_design_render_shell_end(); ?>
