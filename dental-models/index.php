<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

dental_models_internal_boot('Dental Models');

if (is_post() && post('action') === 'create_model') {
    require_csrf();

    $patientName = (string) post('patient_name', '');
    $smileDesignCaseId = (int) post('smile_design_case_id', 0);
    if ($smileDesignCaseId <= 0) {
        $smileDesignCaseId = null;
    }

    $result = dental_models_create_from_upload($_FILES['stl_file'] ?? [], $patientName, $smileDesignCaseId, auth_user_id());
    if (empty($result['ok'])) {
        flash_set('error', (string)($result['message'] ?? 'Upload failed.'));
        redirect(base_url('dental-models/new'));
    }

    flash_set('success', 'STL uploaded to secure staff vault. Open Builder to preview.');
    redirect(base_url('dental-models/' . (int)$result['model_id'] . '/builder'));
}

$action = (string)post('action', '');
$modelId = (int)post('model_id', 0);
if (is_post() && in_array($action, ['mark_missing', 'archive_record'], true)) {
    require_csrf();

    if ($modelId <= 0) {
        flash_set('error', 'Missing or invalid record.');
        redirect(base_url('dental-models'));
    }

    $target = dental_models_find($modelId);
    if (!$target) {
        flash_set('error', 'That model record no longer exists.');
        redirect(base_url('dental-models'));
    }

    $targetStatus = $action === 'archive_record' ? 'archived' : 'missing_file';
    if (dental_models_update_processing_status($modelId, $targetStatus)) {
        if ($targetStatus === 'archived') {
            flash_set('success', 'Model record archived. Storage path remains untouched.');
        } else {
            flash_set('success', 'Model marked as missing file.');
        }
    } else {
        flash_set('error', 'Unable to update the record status.');
    }
    redirect(base_url('dental-models'));
}

$models = dental_models_list(120);
$activeModels = [];
$needsCleanupModels = [];
foreach ($models as $model) {
    $status = strtolower(trim((string)($model['processing_status'] ?? 'original')));
    $resolved = dental_models_resolve_model_file($model);
    $isMissingFromStorage = $resolved === null || !is_file($resolved);
    if ($isMissingFromStorage && $status !== 'missing_file') {
        $status = 'missing_file';
    }
    $model = array_merge($model, ['processing_status' => $status, 'is_missing_file' => $isMissingFromStorage]);

    if ($status === 'missing_file' || $status === 'archived') {
        $needsCleanupModels[] = $model;
    } else {
        $activeModels[] = $model;
    }
}

dental_models_render_shell_start('Dental Models');
?>
<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold text-slate-900">Dental Models</h2>
            <p class="mt-1 text-sm text-slate-600">Upload and manage internal .stl models for V1 preview workflows only.</p>
        </div>
        <a class="inline-flex rounded-2xl border border-slate-300 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700" href="<?= e(base_url('dental-models/new')) ?>">
            Upload new STL
        </a>
    </div>

    <div class="mt-6 overflow-hidden rounded-[1.5rem] border border-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Patient</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Original filename</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Case</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Size</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Uploaded</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php if (empty($activeModels)): ?>
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">
                                No preview-ready models yet. Start by uploading your first STL.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activeModels as $model): ?>
                            <?php
                                $caseId = (int)($model['smile_design_case_id'] ?? 0);
                                $size = (int)($model['file_size'] ?? 0);
                                $filename = trim((string)($model['original_filename'] ?? ''));
                                $status = strtolower(trim((string)($model['processing_status'] ?? 'original')));
                            ?>
                            <tr>
                                <td class="px-4 py-4 align-top text-sm"><?= e((string)($model['id'] ?? '')) ?></td>
                                <td class="px-4 py-4 align-top">
                                    <div class="font-semibold text-slate-900"><?= e((string)($model['patient_name'] ?? 'Unspecified patient')) ?></div>
                                </td>
                                <td class="px-4 py-4 align-top text-sm text-slate-700 break-all"><?= e($filename !== '' ? $filename : 'model.stl') ?></td>
                                <td class="px-4 py-4 align-top text-sm text-slate-700">
                                    <?= $caseId > 0 ? '#' . e((string)$caseId) : 'N/A' ?>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-medium <?= e(dental_models_status_badge_class($status)) ?>">
                                        <?= e((string)dental_models_status_label($status === 'original' ? 'original' : 'preview_ready')) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 align-top text-sm text-slate-700"><?= e(dental_models_format_bytes($size)) ?></td>
                                <td class="px-4 py-4 align-top text-sm text-slate-500"><?= e((string)($model['created_at'] ?? '')) ?></td>
                                <td class="px-4 py-4 align-top">
                                    <div class="flex flex-wrap gap-2">
                                        <a class="inline-flex rounded-2xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700" href="<?= e(base_url('dental-models/' . (int)($model['id'] ?? 0) . '/builder')) ?>">
                                            Open Builder
                                        </a>
                                        <a class="inline-flex rounded-2xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700" href="<?= e(base_url('dental-models/' . (int)($model['id'] ?? 0) . '/download-original')) ?>">
                                            Download Original STL
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php if (!empty($needsCleanupModels)): ?>
    <section class="mt-6 rounded-[2rem] border border-amber-200 bg-amber-50 p-6">
        <div class="mb-3 flex items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-amber-900">Needs cleanup</h3>
                <p class="mt-1 text-sm text-amber-800">These records are missing files or are intentionally archived test records.</p>
            </div>
        </div>
        <div class="overflow-hidden rounded-[1.5rem] border border-amber-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Patient</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Original filename</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Case</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Required action</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <?php foreach ($needsCleanupModels as $model): ?>
                            <?php
                                $caseId = (int)($model['smile_design_case_id'] ?? 0);
                                $status = strtolower(trim((string)($model['processing_status'] ?? 'missing_file')));
                                $isMissing = (bool)($model['is_missing_file'] ?? false);
                                $statusLabel = $isMissing ? 'missing_file' : $status;
                                $requiredAction = $isMissing
                                    ? 'Missing file - Re-upload required'
                                    : 'Record archived';
                            ?>
                            <tr>
                                <td class="px-4 py-4 align-top text-sm"><?= e((string)($model['id'] ?? '')) ?></td>
                                <td class="px-4 py-4 align-top">
                                    <div class="font-semibold text-slate-900"><?= e((string)($model['patient_name'] ?? 'Unspecified patient')) ?></div>
                                </td>
                                <td class="px-4 py-4 align-top text-sm text-slate-700 break-all"><?= e(trim((string)($model['original_filename'] ?? 'model.stl'))) ?></td>
                                <td class="px-4 py-4 align-top text-sm text-slate-700">
                                    <?= $caseId > 0 ? '#' . e((string)$caseId) : 'N/A' ?>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-medium <?= e(dental_models_status_badge_class($statusLabel)) ?>">
                                        <?= e((string)dental_models_status_label($statusLabel === 'original' ? 'missing_file' : $statusLabel)) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 align-top text-sm text-slate-700"><?= e($requiredAction) ?></td>
                                <td class="px-4 py-4 align-top">
                                    <div class="flex flex-wrap gap-2">
                                        <a class="inline-flex rounded-2xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700" href="<?= e(base_url('dental-models/new')) ?>">
                                            Upload New STL
                                        </a>
                                        <?php if ($status !== 'archived'): ?>
                                            <form method="post" action="<?= e(base_url('dental-models')) ?>">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="archive_record">
                                                <input type="hidden" name="model_id" value="<?= e((int)($model['id'] ?? 0)) ?>">
                                                <button class="rounded-2xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700" type="submit">
                                                    Archive Test Record
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php dental_models_render_shell_end(); ?>
