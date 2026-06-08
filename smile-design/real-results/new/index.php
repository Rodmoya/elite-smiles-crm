<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/_bootstrap.php';
$user = smile_design_internal_boot('New Real Result');
smile_design_render_shell_start('New Real Result');
smile_design_page_header('New Real Result', 'Add shell metadata for a future real-patient before/after pair.');
?>
<form class="max-w-4xl rounded-md border border-slate-200 bg-white p-5 shadow-sm" method="POST" enctype="multipart/form-data" action="<?= e(base_url('app/actions/smile_design_real_result_create.php')) ?>">
    <?= csrf_input() ?>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-semibold">Title<input required name="title" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
        <label class="block text-sm font-semibold">Patient label<input name="patient_label" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
        <label class="block text-sm font-semibold">Procedure<input name="procedure_label" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
        <label class="block text-sm font-semibold">Style<input name="style_label" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
    </div>
    <label class="mt-4 block text-sm font-semibold">Story<textarea name="story" rows="5" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></textarea></label>
    <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-semibold">Full Head BEFORE<input name="full_head_before" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
        <label class="block text-sm font-semibold">Full Head AFTER<input name="full_head_after" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
        <label class="block text-sm font-semibold">Smile Close-Up BEFORE<input name="smile_close_up_before" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
        <label class="block text-sm font-semibold">Smile Close-Up AFTER<input name="smile_close_up_after" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
    </div>
    <button class="mt-5 rounded-md bg-slate-950 px-5 py-3 text-sm font-semibold text-white" type="submit">Save Result</button>
</form>
<?php smile_design_render_shell_end(); ?>
