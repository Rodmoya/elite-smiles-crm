<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

/** @var array<int, mixed>|null $user */
$user = dental_models_internal_boot('Upload Dental Model');

$maxLabel = dental_models_upload_limit_label();
$limitWarning = dental_models_upload_limit_warning();
$settings = dental_models_upload_settings();
$targetLabel = dental_models_format_bytes(dental_models_recommended_upload_limit_bytes()) . ' target';
$memoryLimitLabel = $settings['php_memory_limit'] < 0 ? 'unlimited' : dental_models_format_bytes($settings['php_memory_limit']);

dental_models_render_shell_start('Upload Dental Model');
?>

<section class="grid gap-6 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-8">
    <div>
        <h2 class="text-2xl font-semibold text-slate-900">Upload New STL</h2>
        <p class="mt-2 max-w-2xl text-sm text-slate-600">
            Staff-only preview-only upload for dental models. Files stay in protected CRM storage and are not publicly exposed.
            V1 is viewer and preview only -- no mesh changes are applied yet.
        </p>
    </div>

    <form
        class="space-y-5 rounded-2xl border border-slate-200 bg-slate-50/60 p-5"
        method="POST"
        action="<?= e(base_url('dental-models')) ?>"
        enctype="multipart/form-data"
    >
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="create_model">

        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            <label class="space-y-2 text-sm font-semibold text-slate-700">
                STL File
                <input
                    required
                    name="stl_file"
                    type="file"
                    accept=".stl,model/stl,application/vnd.ms-pki.stl"
                    class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2"
                >
            </label>
            <label class="space-y-2 text-sm font-semibold text-slate-700">
                Patient name (optional)
                <input
                    name="patient_name"
                    type="text"
                    maxlength="190"
                    value="<?= e(old_value('patient_name')) ?>"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2"
                    placeholder="Jane Doe"
                >
            </label>
            <label class="space-y-2 text-sm font-semibold text-slate-700">
                Smile Design Case ID (optional)
                <input
                    name="smile_design_case_id"
                    type="number"
                    min="1"
                    value="<?= e(old_value('smile_design_case_id')) ?>"
                    class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2"
                    placeholder="Case #"
                >
            </label>
            <div class="flex items-end">
                <button
                    class="inline-flex h-11 w-full items-center justify-center rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white"
                    type="submit"
                >
                    Upload STL
                </button>
            </div>
        </div>
        <div class="space-y-1 text-xs text-slate-500">
            <p>Accepted file type: .stl only.</p>
            <p>Current effective upload limit: <?= e($maxLabel) ?> (recommended target: <?= e($targetLabel) ?>).</p>
            <p>Server upload settings: upload_max_filesize=<?= e(dental_models_format_bytes($settings['php_upload_max'])) ?>,
                post_max_size=<?= e(dental_models_format_bytes($settings['php_post_max'])) ?>,
                max_execution_time=<?= e((string)$settings['php_execution_time']) ?>s,
                memory_limit=<?= e($memoryLimitLabel) ?>.</p>
            <?php if ($limitWarning !== ''): ?>
                <p class="font-semibold text-amber-700">Warning: <?= e($limitWarning) ?></p>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="mt-6 rounded-[2rem] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:p-5">
    <p class="font-semibold">V1 note:</p>
    <p class="mt-1 leading-6">
        This first release is a secure STL viewer with preview tooling only. Mesh edits, tool-driven extrusion, and drill planning
        operations are intentionally deferred to V2.
    </p>
</section>

<?php
dental_models_render_shell_end();
