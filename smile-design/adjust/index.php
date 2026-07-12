<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

$user = smile_design_internal_boot('Adjust Smile');
$caseId = (int)get('case_id', 0);
$versionId = (int)get('version_id', 0);
$case = smile_design_case($caseId);
$version = $versionId > 0 ? db_one('SELECT * FROM smile_after_versions WHERE id = :id LIMIT 1', ['id' => $versionId]) : null;

if (!$case || !$version || (int)($version['case_id'] ?? 0) !== $caseId) {
    http_response_code(404);
    exit('Smile version not found.');
}

$caseProcedureMode = smile_design_procedure_mode((string)($case['procedure_interest'] ?? ''));
$isLipRepositionOnlyCase = $caseProcedureMode === 'lip_repositioning';
$caseShadeDetail = smile_design_shade_detail((string)($case['shade_goal'] ?? '110'), (string)($case['selected_style'] ?? 'natural'));
$caseTreatmentScope = smile_design_normalize_treatment_scope((string)($case['treatment_scope'] ?? ''), (string)($case['procedure_interest'] ?? ''));
$caseSmileWidthGoal = smile_design_normalize_smile_width_goal((string)($case['smile_width_goal'] ?? ''));
$primaryBefore = smile_design_primary_before_photo($caseId);
$beforePhotoId = (int)($version['before_photo_id'] ?? 0);
$beforePhoto = $beforePhotoId > 0 ? db_one('SELECT * FROM smile_case_photos WHERE id = :id LIMIT 1', ['id' => $beforePhotoId]) : null;
if (!$beforePhoto) {
    $beforePhoto = $primaryBefore;
    $beforePhotoId = (int)($beforePhoto['id'] ?? 0);
}

$beforeUrl = $beforePhotoId > 0 ? smile_design_photo_url($beforePhotoId) : '';
$afterUrl = smile_design_after_url((int)$version['id']);
$afterDownloadUrl = $afterUrl . '&download=1';
$afterWidth = (int)($version['width'] ?? 0);
$afterHeight = (int)($version['height'] ?? 0);
$afterDimensionLabel = $afterWidth > 0 && $afterHeight > 0
    ? $afterWidth . ' x ' . $afterHeight . ' pixels'
    : 'the original pixel dimensions';
$versionPhotoType = (string)($version['photo_type'] ?? 'front');
$versionPhotoLabel = smile_design_photo_type_options()[$versionPhotoType] ?? ucwords(str_replace('_', ' ', $versionPhotoType));
$caseBackUrl = base_url('smile-design/cases/' . $caseId . '#compare');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Adjust Smile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="h-screen overflow-hidden bg-slate-950 text-slate-950 antialiased">
    <?php if (($message = flash_get('success'))): ?>
        <div class="fixed left-4 right-4 top-4 z-[90] mx-auto max-w-2xl rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-lg"><?= e((string)$message) ?></div>
    <?php endif; ?>
    <?php if (($message = flash_get('error'))): ?>
        <div class="fixed left-4 right-4 top-4 z-[90] mx-auto max-w-2xl rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-lg"><?= e((string)$message) ?></div>
    <?php endif; ?>

    <form id="adjust-workspace-form" class="grid h-screen min-h-0 grid-cols-[320px_minmax(0,1fr)]" method="POST" action="<?= e(base_url('app/actions/smile_design_after_adjust.php')) ?>">
        <?= csrf_input() ?>
        <input type="hidden" name="after_version_id" value="<?= e((string)$versionId) ?>">
        <input type="hidden" name="before_photo_id" value="<?= e((string)$beforePhotoId) ?>">
        <input type="hidden" name="procedure_label" value="<?= e((string)($version['procedure_label'] ?? $case['procedure_interest'] ?? '')) ?>">
        <input type="hidden" name="lvi_style_key" value="<?= $isLipRepositionOnlyCase ? '' : e((string)($version['lvi_style_key'] ?? $case['lvi_style_key'] ?? '')) ?>">
        <input type="hidden" name="shade_goal" value="<?= e((string)$caseShadeDetail['code']) ?>">
        <input type="hidden" name="photo_type" value="<?= e((string)($version['photo_type'] ?? 'front')) ?>">
        <input type="hidden" name="return_url" value="<?= e($caseBackUrl) ?>">
        <input type="hidden" name="shape_scale_delta" value="0">
        <input type="hidden" name="smile_length_delta" value="0">
        <input type="hidden" name="smile_width_delta" value="0">
        <input type="hidden" name="shade_brightness_delta" value="0">
        <input type="hidden" name="anchor_points" value="">
        <input type="hidden" name="contour_points" value="">
        <input type="hidden" name="selection_mode" value="contour">
        <input type="hidden" name="brush_mask_data" value="">
        <input type="hidden" name="brush_overlay_data" value="">
        <input type="hidden" name="editor_mode" value="automatic">
        <input type="hidden" name="selected_teeth" value="[8]">
        <input type="hidden" name="tooth_offsets" value="{}">
        <input type="hidden" name="tooth_adjustments" value="{}">
        <input type="hidden" name="precision_mode" value="balanced">

        <aside class="flex min-h-0 flex-col border-r border-white/10 bg-white">
            <div class="border-b border-slate-200 px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Adjust and resend</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Edit version #<?= e((string)($version['version_number'] ?? '0')) ?></h1>
                        <p class="mt-2 text-sm text-slate-500"><?= e((string)$case['patient_name']) ?> · <?= e((string)($version['photo_type'] ?? 'front')) ?></p>
                    </div>
                    <a href="<?= e($caseBackUrl) ?>" class="rounded-full border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Back</a>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
                <div class="space-y-5">
                    <section>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Quick settings</p>
                        <div class="mt-3 space-y-3">
                            <label class="block text-sm font-semibold text-slate-900">
                                New version title
                                <input name="version_title" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal" value="<?= e('Revision of #' . (string)($version['version_number'] ?? '0') . ' ' . (string)($version['version_title'] ?? '')) ?>">
                            </label>
                            <label class="block text-sm font-semibold text-slate-900">
                                Treatment scope
                                <select name="treatment_scope" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal" <?= $isLipRepositionOnlyCase ? 'disabled' : '' ?>>
                                    <?php foreach (smile_design_treatment_scope_options() as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= selected($caseTreatmentScope, $key) ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-slate-900">
                                Smile width
                                <select name="smile_width_goal" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal" <?= $isLipRepositionOnlyCase ? 'disabled' : '' ?>>
                                    <?php foreach (smile_design_smile_width_options() as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= selected($caseSmileWidthGoal, $key) ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                    </section>

                    <label class="flex items-start gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="refresh_analysis" value="1" class="mt-0.5 h-4 w-4 rounded border-slate-300">
                        Re-run AI case analysis before this revision
                    </label>

                    <label class="block text-sm font-semibold text-slate-900">
                        Internal note
                        <input name="notes" value="" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal" placeholder="Optional note for the team.">
                    </label>

                    <label class="block text-sm font-semibold text-slate-900">
                        Make adjustments and resend
                        <textarea name="adjustment_request" rows="7" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal" placeholder="Example: widen the smile slightly, keep the exact same face and camera position, brighten the veneers one step, and refine the upper incisal edge."></textarea>
                    </label>

                    <div class="rounded-md border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium leading-5 text-slate-500">
                        Automatic sends the selected teeth and instructions to AI. Use External Edit when you need pixel-level corrections in Photoshop.
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-200 p-5">
                <button class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-slate-950 px-4 py-3 text-sm font-semibold text-white" type="submit">
                    <span class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" data-ai-spinner></span>
                    <span data-ai-label>Resend with Adjustments</span>
                </button>
            </div>
        </aside>

        <section class="flex min-h-0 flex-col bg-slate-950">
            <div class="flex items-center justify-between border-b border-white/10 px-6 py-4 text-white">
                <div>
                    <p class="text-sm font-semibold">Smile mask editor</p>
                    <p class="mt-1 text-xs text-white/60">Select the exact teeth for an AI revision, or move the full-resolution image through an external editor.</p>
                </div>
                <label class="flex items-center gap-3 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-sm font-semibold">
                    <span>Zoom</span>
                    <input id="adjust-zoom-range" type="range" min="100" max="600" step="5" value="150" class="w-40 accent-white">
                    <span id="adjust-zoom-value" class="min-w-[48px] text-right">150%</span>
                </label>
            </div>

            <div class="min-h-0 flex-1 p-6">
                <div id="adjust-work-frame" class="relative h-full overflow-hidden rounded-2xl border border-white/10 bg-black shadow-2xl">
                    <div id="adjust-work-stage" class="absolute inset-0 transition-transform duration-150 ease-out">
                        <img id="adjust-work-preview" class="h-full w-full object-contain select-none" src="<?= e($afterUrl !== '' ? $afterUrl : $beforeUrl) ?>" alt="Current after smile edit reference" draggable="false">
                        <img id="adjust-detect-preview" class="hidden" src="<?= e($afterUrl !== '' ? $afterUrl : ($beforeUrl !== '' ? $beforeUrl : '')) ?>" alt="" aria-hidden="true">
                        <div id="adjust-konva-layer" class="absolute inset-0 z-20"></div>
                        <div id="adjust-brush-layer" class="absolute z-[25]">
                            <canvas id="adjust-brush-canvas" class="h-full w-full"></canvas>
                        </div>
                        <svg id="adjust-tooth-select-layer" class="absolute inset-0 z-[24] h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                            <polygon id="adjust-auto-tooth-region" points="" fill="rgba(244,63,94,0.50)" stroke="transparent" stroke-width="0" stroke-linejoin="round" class="cursor-pointer"></polygon>
                        </svg>
                        <div id="adjust-anchor-overlay" class="absolute inset-0"></div>
                        <svg id="adjust-anchor-path" class="absolute inset-0 h-full w-full pointer-events-none" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                            <polygon fill="rgba(244,63,94,0.26)" stroke="rgba(255,255,255,0.92)" stroke-width="0.12" stroke-linejoin="round"></polygon>
                            <polyline fill="none" stroke="rgba(255,255,255,0.92)" stroke-width="0.18" stroke-linecap="round" stroke-linejoin="round"></polyline>
                        </svg>
                    </div>
                    <div id="tooth-edge-cursor" class="pointer-events-none absolute z-[29] hidden rounded-full border-2 border-white bg-rose-400/25 shadow-[0_0_0_1px_rgba(15,23,42,0.75)]"></div>
                    <details class="absolute bottom-4 right-4 z-30 max-h-[calc(100%-2rem)] w-[320px] max-w-[calc(100%-2rem)] overflow-y-auto rounded-2xl border border-white/15 bg-slate-950/82 text-white shadow-2xl backdrop-blur" id="adjust-floating-controls" open>
                        <summary class="cursor-pointer list-none px-4 py-3 text-xs font-semibold uppercase tracking-[0.14em] text-white/72">Precision controls</summary>
                        <div class="grid gap-3 border-t border-white/10 p-4">
                            <div class="grid gap-2 rounded-2xl border border-white/10 bg-white/5 p-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/70">Editor mode</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <button id="adjust-mode-automatic" data-editor-mode="automatic" type="button" class="inline-flex items-center justify-center rounded-full border border-sky-300/30 bg-sky-400/10 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-sky-100">
                                        Automatic
                                    </button>
                                    <button id="external-edit-open" type="button" class="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/10 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-white/88">
                                        External Edit
                                    </button>
                                </div>
                                <p id="adjust-mode-help" class="text-[11px] leading-5 text-white/60">Automatic starts with one detected tooth. Add or remove teeth from the map before sending the correction to AI.</p>
                            </div>
                            <div class="grid gap-2 rounded-2xl border border-white/10 bg-white/5 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-white/70">Tooth map</p>
                                    <span id="adjust-selected-tooth-label" class="rounded-full border border-rose-300/30 bg-rose-400/18 px-2 py-1 text-[11px] font-bold text-rose-50">#8</span>
                                </div>
                                <div class="grid grid-cols-5 gap-1">
                                    <?php for ($toothNumber = 4; $toothNumber <= 13; $toothNumber++): ?>
                                        <button type="button" data-tooth-number="<?= (int) $toothNumber ?>" class="inline-flex min-h-8 items-center justify-center rounded-lg border border-white/12 bg-white/8 px-2 py-1 text-[11px] font-bold text-white/78">
                                            #<?= (int) $toothNumber ?>
                                        </button>
                                    <?php endfor; ?>
                                </div>
                                <p class="text-[10px] leading-4 text-white/50">Front view: #8 and #9 are the two center teeth. Drag a highlighted tooth to refine its target position.</p>
                            </div>
                            <div class="grid gap-3 rounded-2xl border border-rose-300/20 bg-rose-400/8 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-rose-100">Edge tools</p>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <button id="tooth-edge-eraser" type="button" class="rounded-full border border-white/15 bg-white/10 px-3 py-2 text-[10px] font-bold uppercase tracking-[0.12em] text-white">Eraser</button>
                                    <button id="tooth-edge-smoother" type="button" class="rounded-full border border-white/15 bg-white/10 px-3 py-2 text-[10px] font-bold uppercase tracking-[0.12em] text-white">Smoother</button>
                                </div>
                                <label class="text-xs font-semibold text-white">
                                    Eraser size: <span id="tooth-edge-size-value" class="font-bold">10</span>px
                                    <input id="tooth-edge-size" type="range" min="3" max="20" value="10" class="mt-1 w-full accent-rose-300">
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button id="tooth-edge-undo" type="button" class="rounded-full border border-white/15 bg-white/10 px-3 py-2 text-[10px] font-bold uppercase tracking-[0.12em] text-white/90">Undo</button>
                                    <button id="tooth-edge-reset" type="button" class="rounded-full border border-white/15 bg-white/10 px-3 py-2 text-[10px] font-bold uppercase tracking-[0.12em] text-white/90">Reset</button>
                                </div>
                                <label class="text-xs font-semibold text-white">
                                    Edge smoothing: <span id="adjust-smoothing-value" class="font-bold">45</span>%
                                    <input type="range" min="0" max="100" value="45" class="mt-1 w-full accent-rose-300" data-adjust-range="edge_smoothing">
                                </label>
                            </div>
                            <label class="text-xs font-semibold text-white">
                                Shape shift: <span id="adjust-shape-value" class="font-bold">0</span>%
                                <input type="range" min="-30" max="30" value="0" class="mt-1 w-full accent-white" data-adjust-range="shape_scale_delta">
                            </label>
                            <label class="text-xs font-semibold text-white">
                                Smile length: <span id="adjust-length-value" class="font-bold">0</span>%
                                <input type="range" min="-30" max="30" value="0" class="mt-1 w-full accent-white" data-adjust-range="smile_length_delta">
                            </label>
                            <label class="text-xs font-semibold text-white">
                                Smile width: <span id="adjust-width-value" class="font-bold">0</span>%
                                <input type="range" min="-40" max="40" value="0" class="mt-1 w-full accent-white" data-adjust-range="smile_width_delta">
                            </label>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-3 text-[11px] leading-5 text-white/60">
                                AI changes are saved as a new version. External edits are also saved as a new version, so the current result is never overwritten.
                            </div>
                        </div>
                    </details>
                </div>
            </div>
        </section>
    </form>

    <div id="external-edit-modal" class="fixed inset-0 z-[100] hidden bg-black/90 p-4 md:p-6" role="dialog" aria-modal="true" aria-labelledby="external-edit-title" data-expected-width="<?= e((string)$afterWidth) ?>" data-expected-height="<?= e((string)$afterHeight) ?>">
        <form id="external-edit-form" class="mx-auto flex h-full max-w-[1500px] flex-col overflow-hidden rounded-xl border border-white/15 bg-slate-950 text-white shadow-2xl" method="POST" action="<?= e(base_url('app/actions/smile_design_external_edit_upload.php')) ?>" enctype="multipart/form-data">
            <?= csrf_input() ?>
            <input type="hidden" name="source_after_version_id" value="<?= e((string)$versionId) ?>">
            <div class="flex items-center justify-between gap-4 border-b border-white/10 px-5 py-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-white/50">External correction</p>
                    <h2 id="external-edit-title" class="mt-1 text-xl font-semibold">Edit <?= e($versionPhotoLabel) ?> Version #<?= e((string)($version['version_number'] ?? '0')) ?></h2>
                </div>
                <button id="external-edit-close" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/20 text-xl text-white" aria-label="Close external edit">&times;</button>
            </div>

            <div class="grid min-h-0 flex-1 gap-4 overflow-y-auto p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_300px]">
                <figure class="flex min-h-[320px] flex-col overflow-hidden rounded-lg border border-white/10 bg-black">
                    <figcaption class="border-b border-white/10 px-4 py-3 text-sm font-semibold text-white/75">Current after</figcaption>
                    <img class="min-h-0 flex-1 object-contain" src="<?= e($afterUrl) ?>" alt="Current after for external editing">
                </figure>
                <figure class="flex min-h-[320px] flex-col overflow-hidden rounded-lg border border-white/10 bg-black">
                    <figcaption class="border-b border-white/10 px-4 py-3 text-sm font-semibold text-white/75">Corrected upload</figcaption>
                    <div id="external-edit-empty" class="flex min-h-0 flex-1 items-center justify-center px-8 text-center text-sm leading-6 text-white/45">Choose the corrected image to preview it here before saving.</div>
                    <img id="external-edit-preview" class="hidden min-h-0 flex-1 object-contain" alt="Corrected external edit preview">
                </figure>

                <aside class="space-y-4 rounded-lg border border-white/10 bg-white/5 p-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-white/55">1. Download</p>
                        <a href="<?= e($afterDownloadUrl) ?>" class="mt-2 inline-flex w-full items-center justify-center rounded-md border border-white/20 bg-white/10 px-4 py-3 text-sm font-semibold text-white">Download Full Image</a>
                        <p class="mt-2 text-xs leading-5 text-white/45">Edit this exact file in Photoshop. Keep the canvas at <?= e($afterDimensionLabel) ?>.</p>
                    </div>

                    <label class="block text-sm font-semibold">
                        2. Upload corrected image
                        <input id="external-edit-file" required name="after_photo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full rounded-md border border-white/15 bg-black/30 px-3 py-2 text-xs text-white">
                    </label>
                    <p id="external-edit-file-status" class="rounded-md border border-white/10 bg-black/25 px-3 py-2 text-xs leading-5 text-white/55">Waiting for a corrected image.</p>

                    <label class="block text-sm font-semibold">
                        New version title
                        <input name="version_title" value="<?= e('External edit of #' . (string)($version['version_number'] ?? '0') . ' ' . (string)($version['version_title'] ?? '')) ?>" class="mt-2 w-full rounded-md border border-white/15 bg-black/30 px-3 py-2 text-sm font-normal text-white">
                    </label>
                    <label class="block text-sm font-semibold">
                        Edit note
                        <textarea name="notes" rows="3" class="mt-2 w-full rounded-md border border-white/15 bg-black/30 px-3 py-2 text-sm font-normal text-white" placeholder="What was corrected in Photoshop?"></textarea>
                    </label>

                    <button id="external-edit-submit" disabled class="inline-flex w-full items-center justify-center rounded-md bg-white px-4 py-3 text-sm font-semibold text-slate-950 disabled:cursor-not-allowed disabled:opacity-40" type="submit">Save as New Revision</button>
                    <p class="text-xs leading-5 text-white/45">The current version stays unchanged. The corrected upload will appear as a separate after version for review.</p>
                </aside>
            </div>
        </form>
    </div>

    <div id="smile-action-loader" class="fixed inset-0 z-[95] hidden items-center justify-center bg-slate-950/60 px-4">
        <div class="flex flex-col items-center gap-4 rounded-xl bg-white px-8 py-7 text-center shadow-2xl">
            <div class="h-12 w-12 animate-spin rounded-full border-4 border-slate-200 border-t-slate-950"></div>
            <p id="smile-action-loader-label" class="text-sm font-semibold text-slate-800">Working...</p>
        </div>
    </div>

    <style>
        #adjust-anchor-overlay {
            display: none;
        }
        #adjust-anchor-path {
            display: none;
        }
        #adjust-konva-layer {
            display: none;
            pointer-events: none;
        }
        #adjust-konva-layer.refine {
            pointer-events: auto;
        }
        #adjust-brush-layer {
            display: none;
            pointer-events: none;
        }
        #adjust-brush-layer.active {
            display: block;
            pointer-events: auto;
        }
        #adjust-brush-canvas {
            cursor: crosshair;
            pointer-events: auto;
            touch-action: none;
        }
        #adjust-tooth-select-layer {
            display: none;
            pointer-events: none;
        }
        #adjust-tooth-select-layer.active {
            display: block;
        }
        #adjust-auto-tooth-region {
            pointer-events: auto;
        }
        [data-editor-mode]:disabled,
        [data-brush-action]:disabled,
        #adjust-brush-size:disabled {
            opacity: 0.42;
            cursor: not-allowed;
        }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
      const form = document.getElementById('adjust-workspace-form');
      const workFrame = document.getElementById('adjust-work-frame');
      const workStage = document.getElementById('adjust-work-stage');
      const workPreview = document.getElementById('adjust-work-preview');
      const detectPreview = document.getElementById('adjust-detect-preview');
      const konvaHolder = document.getElementById('adjust-konva-layer');
      const overlay = document.getElementById('adjust-anchor-overlay');
      const pathSvg = document.getElementById('adjust-anchor-path');
      const pathLine = pathSvg ? pathSvg.querySelector('polyline') : null;
      const pathFill = pathSvg ? pathSvg.querySelector('polygon') : null;
      const toothSelectLayer = document.getElementById('adjust-tooth-select-layer');
      const autoToothRegion = document.getElementById('adjust-auto-tooth-region');
      const selectedToothLabel = document.getElementById('adjust-selected-tooth-label');
      const toothSelectButtons = Array.from(document.querySelectorAll('[data-tooth-number]'));
      const floatingControls = document.getElementById('adjust-floating-controls');
      const automaticModeButton = document.getElementById('adjust-mode-automatic');
      const manualModeButton = document.getElementById('adjust-mode-manual');
      const modeHelp = document.getElementById('adjust-mode-help');
      const externalEditOpen = document.getElementById('external-edit-open');
      const externalEditModal = document.getElementById('external-edit-modal');
      const externalEditClose = document.getElementById('external-edit-close');
      const externalEditForm = document.getElementById('external-edit-form');
      const externalEditFile = document.getElementById('external-edit-file');
      const externalEditPreview = document.getElementById('external-edit-preview');
      const externalEditEmpty = document.getElementById('external-edit-empty');
      const externalEditStatus = document.getElementById('external-edit-file-status');
      const externalEditSubmit = document.getElementById('external-edit-submit');
      const zoomRange = document.getElementById('adjust-zoom-range');
      const zoomValue = document.getElementById('adjust-zoom-value');
      const brushLayer = document.getElementById('adjust-brush-layer');
      const brushCanvas = document.getElementById('adjust-brush-canvas');
      const brushEnableButton = document.getElementById('adjust-brush-enable');
      const brushEraseToggle = document.getElementById('adjust-brush-erase-toggle');
      const brushDoneButton = document.getElementById('adjust-brush-done');
      const brushClearButton = document.getElementById('adjust-brush-clear');
      const brushUndoButton = document.getElementById('adjust-brush-undo');
      const brushSizeRange = document.getElementById('adjust-brush-size');
      const brushSizeValue = document.getElementById('adjust-brush-size-value');
      const brushSizePreview = document.getElementById('adjust-brush-size-preview');
      const toothEdgeEraserButton = document.getElementById('tooth-edge-eraser');
      const toothEdgeSmootherButton = document.getElementById('tooth-edge-smoother');
      const toothEdgeUndoButton = document.getElementById('tooth-edge-undo');
      const toothEdgeResetButton = document.getElementById('tooth-edge-reset');
      const toothEdgeSizeRange = document.getElementById('tooth-edge-size');
      const toothEdgeSizeValue = document.getElementById('tooth-edge-size-value');
      const toothEdgeCursor = document.getElementById('tooth-edge-cursor');
      const brushMaskInput = form ? form.querySelector('input[name="brush_mask_data"]') : null;
      const loader = document.getElementById('smile-action-loader');
      const loaderLabel = document.getElementById('smile-action-loader-label');
      let baseAnchorPoints = [];
      let baseContourPoints = [];
      let faceLandmarkerPromise = null;
      let brushMode = false;
      let brushEraseMode = false;
      let brushDrawing = false;
      let editorMode = 'automatic';
      let autoToothSelection = null;
      let currentSelectedToothRegions = [];
      let selectedToothNumber = 8;
      let selectedTeeth = new Set([8]);
      let toothSeedPoints = {};
      let toothManualOffsets = {};
      let toothPrecisionAdjustments = {};
      let toothContourOverrides = {};
      let toothContourHistory = {};
      let toothEraseStrokes = {};
      let toothEdgeTool = '';
      let toothEdgeDrawing = false;
      let draggingTooth = null;
      let suppressToothClick = false;
      let detectedTeethBounds = null;
      let openCvScriptRequested = false;
      let brushContext = null;
      let brushHistory = [];
      const visibleUpperTeeth = [4, 5, 6, 7, 8, 9, 10, 11, 12, 13];
      const openCvScriptUrl = <?= json_encode(base_url('assets/vendor/opencv-wasm/opencv.js'), JSON_UNESCAPED_SLASHES) ?>;

      const defaultZoom = 150;
      if (floatingControls) {
        floatingControls.open = true;
      }

      let externalEditObjectUrl = '';

      function loadOpenCvInBackground() {
        if (openCvScriptRequested || (window.cv && typeof window.cv.Mat === 'function')) return;
        openCvScriptRequested = true;
        document.body.dataset.toothSegmentation = 'loading';
        const script = document.createElement('script');
        script.async = true;
        script.src = openCvScriptUrl;
        script.addEventListener('load', function () {
          const ready = function (module) {
            window.cv = module || window.cv;
            if (!window.cv || typeof window.cv.Mat !== 'function') return;
            document.body.dataset.toothSegmentation = 'opencv-ready';
            detectedTeethBounds = null;
            render(readAnchorPoints(), baseContourPoints);
          };
          if (window.cv && typeof window.cv.then === 'function') {
            window.cv.then(ready);
          } else {
            ready(window.cv);
          }
        });
        script.addEventListener('error', function () {
          document.body.dataset.toothSegmentation = 'error';
        });
        document.head.appendChild(script);
      }

      function clearExternalEditPreview() {
        if (externalEditObjectUrl) {
          URL.revokeObjectURL(externalEditObjectUrl);
          externalEditObjectUrl = '';
        }
        if (externalEditPreview) {
          externalEditPreview.removeAttribute('src');
          externalEditPreview.classList.add('hidden');
        }
        if (externalEditEmpty) {
          externalEditEmpty.classList.remove('hidden');
        }
        if (externalEditStatus) {
          externalEditStatus.textContent = 'Waiting for a corrected image.';
          externalEditStatus.className = 'rounded-md border border-white/10 bg-black/25 px-3 py-2 text-xs leading-5 text-white/55';
        }
        if (externalEditSubmit) {
          externalEditSubmit.disabled = true;
        }
      }

      function openExternalEdit() {
        if (!externalEditModal) return;
        externalEditModal.classList.remove('hidden');
        if (externalEditFile) {
          window.setTimeout(function () {
            externalEditFile.focus();
          }, 0);
        }
      }

      function closeExternalEdit() {
        if (!externalEditModal) return;
        externalEditModal.classList.add('hidden');
      }

      if (externalEditOpen) {
        externalEditOpen.addEventListener('click', openExternalEdit);
      }
      if (externalEditClose) {
        externalEditClose.addEventListener('click', closeExternalEdit);
      }
      if (externalEditModal) {
        externalEditModal.addEventListener('click', function (event) {
          if (event.target === externalEditModal) {
            closeExternalEdit();
          }
        });
      }
      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && externalEditModal && !externalEditModal.classList.contains('hidden')) {
          closeExternalEdit();
        }
      });

      if (externalEditFile) {
        externalEditFile.addEventListener('change', function () {
          clearExternalEditPreview();
          const file = externalEditFile.files && externalEditFile.files[0] ? externalEditFile.files[0] : null;
          if (!file) return;
          if (!['image/jpeg', 'image/png', 'image/webp'].includes(String(file.type || '').toLowerCase())) {
            if (externalEditStatus) {
              externalEditStatus.textContent = 'Choose a JPG, PNG, or WebP image.';
              externalEditStatus.className = 'rounded-md border border-rose-300/25 bg-rose-400/10 px-3 py-2 text-xs leading-5 text-rose-100';
            }
            externalEditFile.value = '';
            return;
          }

          externalEditObjectUrl = URL.createObjectURL(file);
          const image = new Image();
          image.onload = function () {
            const expectedWidth = Number.parseInt(externalEditModal ? externalEditModal.dataset.expectedWidth || '0' : '0', 10);
            const expectedHeight = Number.parseInt(externalEditModal ? externalEditModal.dataset.expectedHeight || '0' : '0', 10);
            const dimensionsMatch = expectedWidth <= 0 || expectedHeight <= 0
              || (image.naturalWidth === expectedWidth && image.naturalHeight === expectedHeight);
            if (!dimensionsMatch) {
              if (externalEditStatus) {
                externalEditStatus.textContent = 'Size mismatch: this file is ' + image.naturalWidth + ' x ' + image.naturalHeight + ' pixels. It must remain ' + expectedWidth + ' x ' + expectedHeight + ' pixels.';
                externalEditStatus.className = 'rounded-md border border-rose-300/25 bg-rose-400/10 px-3 py-2 text-xs leading-5 text-rose-100';
              }
              URL.revokeObjectURL(externalEditObjectUrl);
              externalEditObjectUrl = '';
              externalEditFile.value = '';
              return;
            }
            if (externalEditPreview) {
              externalEditPreview.src = externalEditObjectUrl;
              externalEditPreview.classList.remove('hidden');
            }
            if (externalEditEmpty) {
              externalEditEmpty.classList.add('hidden');
            }
            if (externalEditStatus) {
              externalEditStatus.textContent = 'Ready: ' + image.naturalWidth + ' x ' + image.naturalHeight + ' pixels. The original version will remain unchanged.';
              externalEditStatus.className = 'rounded-md border border-emerald-300/25 bg-emerald-400/10 px-3 py-2 text-xs leading-5 text-emerald-100';
            }
            if (externalEditSubmit) {
              externalEditSubmit.disabled = false;
            }
          };
          image.onerror = function () {
            if (externalEditStatus) {
              externalEditStatus.textContent = 'This image could not be previewed. Please export it again as JPG, PNG, or WebP.';
              externalEditStatus.className = 'rounded-md border border-rose-300/25 bg-rose-400/10 px-3 py-2 text-xs leading-5 text-rose-100';
            }
            URL.revokeObjectURL(externalEditObjectUrl);
            externalEditObjectUrl = '';
            externalEditFile.value = '';
          };
          image.src = externalEditObjectUrl;
        });
      }

      if (externalEditForm) {
        externalEditForm.addEventListener('submit', function (event) {
          if (!externalEditFile || !externalEditFile.files || !externalEditFile.files[0] || (externalEditSubmit && externalEditSubmit.disabled)) {
            event.preventDefault();
            return;
          }
          if (externalEditSubmit) {
            externalEditSubmit.disabled = true;
            externalEditSubmit.textContent = 'Saving New Revision...';
          }
        });
      }

      const anchorDefaults = [
        { key: 'upper_left', label: 'Upper left lip edge', x: 34, y: 45, size: 9 },
        { key: 'upper_center', label: 'Upper center lip edge', x: 50, y: 44, size: 9 },
        { key: 'upper_right', label: 'Upper right lip edge', x: 66, y: 45, size: 9 },
        { key: 'right_inner', label: 'Right smile corner', x: 68, y: 50, size: 9 },
        { key: 'lower_right', label: 'Lower right lip edge', x: 66, y: 57, size: 9 },
        { key: 'lower_center', label: 'Lower center lip edge', x: 50, y: 58, size: 9 },
        { key: 'lower_left', label: 'Lower left lip edge', x: 34, y: 57, size: 9 },
        { key: 'left_inner', label: 'Left smile corner', x: 32, y: 50, size: 9 }
      ];
      const anchorOrder = ['upper_left', 'upper_center', 'upper_right', 'right_inner', 'lower_right', 'lower_center', 'lower_left', 'left_inner', 'upper_left'];

      function normalize(value, min, max) {
        const parsed = Number.parseFloat(String(value || '0'));
        if (!Number.isFinite(parsed)) return min;
        return Math.max(min, Math.min(max, parsed));
      }

      function cloneDefaults() {
        return anchorDefaults.map(function (point) {
          return { key: point.key, label: point.label, x: point.x, y: point.y, size: point.size };
        });
      }

      function normalizeToothNumber(toothNumber) {
        const parsed = Number(toothNumber);
        return visibleUpperTeeth.includes(parsed) ? parsed : null;
      }

      function getSelectedTeethArray() {
        return visibleUpperTeeth.filter(function (toothNumber) {
          return selectedTeeth.has(toothNumber);
        });
      }

      function getSelectedToothNumber() {
        const normalized = normalizeToothNumber(selectedToothNumber);
        if (normalized !== null) {
          return normalized;
        }
        const selected = getSelectedTeethArray();
        return selected.length ? selected[0] : 8;
      }

      function setSelectedTeeth(nextValues, preferredToothNumber) {
        const normalized = [];
        (Array.isArray(nextValues) ? nextValues : []).forEach(function (value) {
          const toothNumber = normalizeToothNumber(value);
          if (toothNumber === null || normalized.includes(toothNumber)) return;
          normalized.push(toothNumber);
        });
        selectedTeeth = new Set(normalized);
        const preferred = normalizeToothNumber(preferredToothNumber);
        if (preferred !== null && selectedTeeth.has(preferred)) {
          selectedToothNumber = preferred;
        } else if (selectedTeeth.has(selectedToothNumber)) {
          selectedToothNumber = Number(selectedToothNumber);
        } else if (normalized.length) {
          selectedToothNumber = normalized[0];
        } else {
          selectedToothNumber = preferred !== null ? preferred : 8;
        }
      }

      function toggleToothSelection(toothNumber, seedPoint, additive) {
        const normalized = normalizeToothNumber(toothNumber);
        if (normalized === null) return;
        if (!additive) {
          toothSeedPoints = {};
          if (seedPoint) toothSeedPoints[normalized] = seedPoint;
          setSelectedTeeth([normalized], normalized);
          return;
        }
        const next = getSelectedTeethArray();
        const index = next.indexOf(normalized);
        if (index >= 0) {
          next.splice(index, 1);
          delete toothSeedPoints[normalized];
        } else {
          next.push(normalized);
          if (seedPoint) {
            toothSeedPoints[normalized] = seedPoint;
          }
        }
        setSelectedTeeth(next, normalized);
      }

      function getSelectedTeethPayload() {
        return JSON.stringify(getSelectedTeethArray());
      }

      function writeToothOffsets() {
        const input = form.querySelector('input[name="tooth_offsets"]');
        if (input) input.value = JSON.stringify(toothManualOffsets);
      }

      function getToothAdjustment(toothNumber) {
        const normalized = normalizeToothNumber(toothNumber);
        const stored = normalized !== null && toothPrecisionAdjustments[normalized]
          ? toothPrecisionAdjustments[normalized]
          : {};
        return {
          shape_scale_delta: Number(stored.shape_scale_delta || 0),
          smile_length_delta: Number(stored.smile_length_delta || 0),
          smile_width_delta: Number(stored.smile_width_delta || 0),
          edge_smoothing: stored.edge_smoothing === undefined ? 45 : Number(stored.edge_smoothing || 0)
        };
      }

      function writeToothAdjustments() {
        const input = form.querySelector('input[name="tooth_adjustments"]');
        if (input) input.value = JSON.stringify(toothPrecisionAdjustments);
      }

      function syncPrecisionControlsForActiveTooth() {
        const activeTooth = getSelectedToothNumber();
        const values = getToothAdjustment(activeTooth);
        document.querySelectorAll('[data-adjust-range]').forEach(function (range) {
          const key = range.getAttribute('data-adjust-range');
          if (!key || !Object.prototype.hasOwnProperty.call(values, key)) return;
          const value = String(values[key]);
          range.value = value;
          const config = getRangeConfig(key);
          if (!config) return;
          const label = document.getElementById(config.label);
          if (label) label.textContent = value + config.suffix;
        });
        const useLegacyValues = getSelectedTeethArray().length === 1;
        Object.keys(values).forEach(function (key) {
          const hidden = form.querySelector('input[name="' + key + '"]');
          if (hidden) hidden.value = useLegacyValues ? String(values[key]) : '0';
        });
      }

      function updateToothMapUi() {
        const current = getSelectedToothNumber();
        if (selectedToothLabel) {
          const selected = getSelectedTeethArray();
          selectedToothLabel.textContent = selected.length <= 0
            ? 'None'
            : (selected.length === 1 ? ('#' + selected[0]) : ('#' + current + ' +' + (selected.length - 1)));
        }
        toothSelectButtons.forEach(function (button) {
          const toothNumber = Number(button.getAttribute('data-tooth-number') || 0);
          const isCurrent = toothNumber === current;
          const isSelected = selectedTeeth.has(toothNumber);
          button.className = isCurrent
            ? 'inline-flex min-h-8 items-center justify-center rounded-lg border border-rose-200/40 bg-rose-400/24 px-2 py-1 text-[11px] font-bold text-white shadow-[0_0_0_1px_rgba(255,255,255,0.10)]'
            : (isSelected
              ? 'inline-flex min-h-8 items-center justify-center rounded-lg border border-rose-200/26 bg-rose-400/14 px-2 py-1 text-[11px] font-bold text-white/92'
              : 'inline-flex min-h-8 items-center justify-center rounded-lg border border-white/12 bg-white/8 px-2 py-1 text-[11px] font-bold text-white/78 hover:border-white/30 hover:bg-white/12');
          button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });
      }

      function chooseToothNumber(toothNumber, additive) {
        toggleToothSelection(toothNumber, null, additive);
        autoToothSelection = null;
        updateToothMapUi();
        syncPrecisionControlsForActiveTooth();
        render(readAnchorPoints());
      }

      function pointInPolygon(point, polygon) {
        if (!point || !Array.isArray(polygon) || polygon.length < 3) {
          return false;
        }
        let inside = false;
        for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
          const xi = Number(polygon[i].x || 0);
          const yi = Number(polygon[i].y || 0);
          const xj = Number(polygon[j].x || 0);
          const yj = Number(polygon[j].y || 0);
          const intersects = ((yi > point.y) !== (yj > point.y))
            && (point.x < (((xj - xi) * (point.y - yi)) / Math.max(0.00001, (yj - yi))) + xi);
          if (intersects) inside = !inside;
        }
        return inside;
      }

      function getToothNumberFromClick(clientX, clientY) {
        const imageRect = getVisibleImageRect();
        const teethBounds = getDetectedTeethBounds();
        if (!imageRect || imageRect.width <= 0 || imageRect.height <= 0) {
          return getSelectedToothNumber();
        }
        const xPct = normalize(((clientX - imageRect.left) / imageRect.width) * 100, 0, 100);
        const yPct = normalize(((clientY - imageRect.top) / imageRect.height) * 100, 0, 100);
        const slots = teethBounds && teethBounds.slots ? teethBounds.slots : null;
        if (slots) {
          const point = { x: xPct, y: yPct };
          for (let index = 0; index < visibleUpperTeeth.length; index += 1) {
            const number = visibleUpperTeeth[index];
            const slot = slots[number];
            if (!slot || !Array.isArray(slot.contour) || slot.contour.length < 3) continue;
            if (pointInPolygon(point, slot.contour)) {
              return number;
            }
          }
          let bestNumber = getSelectedToothNumber();
          let bestScore = Number.POSITIVE_INFINITY;
          visibleUpperTeeth.forEach(function (number) {
            const slot = slots[number];
            if (!slot) return;
            const centerX = (slot.left + slot.right) / 2;
            const centerY = (slot.top + slot.bottom) / 2;
            const inside = xPct >= slot.left && xPct <= slot.right && yPct >= slot.top && yPct <= slot.bottom;
            const dx = xPct - centerX;
            const dy = yPct - centerY;
            const score = ((dx * dx) * 1.15) + ((dy * dy) * 0.70) - (inside ? 45 : 0);
            if (score < bestScore) {
              bestScore = score;
              bestNumber = number;
            }
          });
          return bestNumber;
        }
        const bucket = Math.max(0, Math.min(visibleUpperTeeth.length - 1, Math.round((xPct / 100) * (visibleUpperTeeth.length - 1))));
        return visibleUpperTeeth[bucket] || 8;
      }

      function seedToothSelectionFromClick(clientX, clientY, additive) {
        const seedPoint = displayPointToImage(clientX, clientY);
        const parsed = getToothNumberFromClick(clientX, clientY);
        if (normalizeToothNumber(parsed) !== null) {
          toothSeedPoints[parsed] = seedPoint;
        }
        toggleToothSelection(parsed, seedPoint, additive);
        autoToothSelection = null;
        updateToothMapUi();
        render(readAnchorPoints());
      }

      function clonePoints(points) {
        return points.map(function (point) {
          return { key: point.key, label: point.label, x: point.x, y: point.y, size: point.size };
        });
      }

      function clonePlainPoints(points) {
        return (points || []).map(function (point) {
          return { x: point.x, y: point.y };
        });
      }

      function densifyPolygonPoints(points) {
        const source = clonePlainPoints(points);
        if (source.length < 4) {
          return source;
        }
        const dense = [];
        for (let index = 0; index < source.length; index += 1) {
          const current = source[index];
          const next = source[(index + 1) % source.length];
          dense.push(current);
          dense.push(clampPoint({
            x: current.x + ((next.x - current.x) * 0.333),
            y: current.y + ((next.y - current.y) * 0.333)
          }));
          dense.push(clampPoint({
            x: current.x + ((next.x - current.x) * 0.667),
            y: current.y + ((next.y - current.y) * 0.667)
          }));
        }
        return dense;
      }

      function getDisplayImageRect() {
        if (!workFrame || !workPreview) {
          return { left: 0, top: 0, width: 1, height: 1 };
        }
        const frameRect = workFrame.getBoundingClientRect();
        const naturalWidth = Math.max(1, Number(workPreview.naturalWidth || 1));
        const naturalHeight = Math.max(1, Number(workPreview.naturalHeight || 1));
        const frameRatio = frameRect.width / Math.max(1, frameRect.height);
        const imageRatio = naturalWidth / naturalHeight;
        let width = frameRect.width;
        let height = frameRect.height;
        if (imageRatio > frameRatio) {
          height = width / imageRatio;
        } else {
          width = height * imageRatio;
        }
        return {
          left: (frameRect.width - width) / 2,
          top: (frameRect.height - height) / 2,
          width: Math.max(1, width),
          height: Math.max(1, height)
        };
      }

      function getVisibleImageRect() {
        if (!workPreview || typeof workPreview.getBoundingClientRect !== 'function') {
          return getDisplayImageRect();
        }
        const rect = workPreview.getBoundingClientRect();
        if (!rect || rect.width <= 0 || rect.height <= 0) {
          return getDisplayImageRect();
        }
        return rect;
      }

      function imagePointToDisplay(point) {
        const rect = getDisplayImageRect();
        return {
          x: rect.left + ((point.x / 100) * rect.width),
          y: rect.top + ((point.y / 100) * rect.height)
        };
      }

      function imagePointToStage(point) {
        const rect = getDisplayImageRect();
        return {
          x: (point.x / 100) * rect.width,
          y: (point.y / 100) * rect.height
        };
      }

      function displayPointToImage(clientX, clientY) {
        const imageRect = getVisibleImageRect();
        return {
          x: Math.max(0, Math.min(100, (((clientX - imageRect.left) / imageRect.width) * 100))),
          y: Math.max(0, Math.min(100, (((clientY - imageRect.top) / imageRect.height) * 100)))
        };
      }

      function writeAnchorPoints(points) {
        const input = form.querySelector('input[name="anchor_points"]');
        if (input) input.value = JSON.stringify(points);
      }

      function writeContourPoints(points) {
        const input = form.querySelector('input[name="contour_points"]');
        if (input) input.value = JSON.stringify(points || []);
      }

      function readContourPoints() {
        const input = form.querySelector('input[name="contour_points"]');
        if (!input || !input.value) return [];
        try {
          const parsed = JSON.parse(input.value);
          return Array.isArray(parsed) ? parsed.map(clampPoint) : [];
        } catch (error) {
          return [];
        }
      }

      function normalizeSelectedTeeth(value) {
        const raw = String(value || '').trim();
        if (!raw) {
          return '[8]';
        }
        try {
          const parsed = JSON.parse(raw);
          if (Array.isArray(parsed)) {
            const normalized = [];
            parsed.forEach(function (entry) {
              const number = Number.parseInt(entry, 10);
              if (!Number.isFinite(number) || number <= 0 || number > 32) {
                return;
              }
              if (!normalized.includes(number)) {
                normalized.push(number);
              }
            });
            return JSON.stringify(normalized);
          }
        } catch (error) {
          // continue with fallback parsing below
        }
        const matches = raw.match(/-?\d+/g) || [];
        const normalized = [];
        matches.forEach(function (entry) {
          const number = Number.parseInt(entry, 10);
          if (!Number.isFinite(number) || number <= 0 || number > 32) {
            return;
          }
          if (!normalized.includes(number)) {
            normalized.push(number);
          }
        });
        return JSON.stringify(normalized);
      }

      function writeSelectionMode(value) {
        const input = form.querySelector('input[name="selection_mode"]');
        if (input) input.value = value;
      }

      function writeEditorMode(value) {
        const input = form.querySelector('input[name="editor_mode"]');
        if (input) input.value = value;
      }

      function writeSelectedTeeth(value) {
        const input = form.querySelector('input[name="selected_teeth"]');
        if (input) input.value = normalizeSelectedTeeth(value);
      }

      function hydrateSelectedTeethFromInput() {
        const input = form.querySelector('input[name="selected_teeth"]');
        const raw = input ? normalizeSelectedTeeth(input.value) : '[8]';
        try {
          const parsed = JSON.parse(raw);
          setSelectedTeeth(Array.isArray(parsed) ? parsed : [8]);
        } catch (error) {
          setSelectedTeeth([8]);
        }
      }

      hydrateSelectedTeethFromInput();

      function writeBrushPayload(maskData, overlayData) {
        const maskInput = form.querySelector('input[name="brush_mask_data"]');
        const overlayInput = form.querySelector('input[name="brush_overlay_data"]');
        if (maskInput) maskInput.value = maskData || '';
        if (overlayInput) overlayInput.value = overlayData || '';
      }

      function readAnchorPoints() {
        const input = form.querySelector('input[name="anchor_points"]');
        if (!input || !input.value) return cloneDefaults();
        try {
          const parsed = JSON.parse(input.value);
          if (!Array.isArray(parsed) || !parsed.length) return cloneDefaults();
          const mapped = {};
          parsed.forEach(function (point) {
            if (!point || typeof point !== 'object') return;
            const key = String(point.key || '').trim();
            if (!key) return;
            mapped[key] = {
              key: key,
              label: String(point.label || key),
              x: normalize(point.x, 0, 100),
              y: normalize(point.y, 0, 100),
              size: normalize(point.size || 9, 7, 14)
            };
          });
          return cloneDefaults().map(function (point) {
            return mapped[point.key] || point;
          });
        } catch (error) {
          return cloneDefaults();
        }
      }

      function keyedAnchors(points) {
        return points.reduce(function (carry, point) {
          carry[point.key] = point;
          return carry;
        }, {});
      }

      function clampPoint(point) {
        return {
          x: Math.max(0, Math.min(100, Math.round(point.x * 100) / 100)),
          y: Math.max(0, Math.min(100, Math.round(point.y * 100) / 100))
        };
      }

      function getAnchorBounds(points) {
        const keyed = keyedAnchors(points);
        const rawLeft = (keyed.upper_left.x + keyed.left_inner.x + keyed.lower_left.x) / 3;
        const rawRight = (keyed.upper_right.x + keyed.right_inner.x + keyed.lower_right.x) / 3;
        const rawTop = (keyed.upper_left.y + keyed.upper_center.y + keyed.upper_right.y) / 3;
        const rawBottom = (keyed.lower_left.y + keyed.lower_center.y + keyed.lower_right.y) / 3;
        const left = normalize(rawLeft, 0, 100);
        const right = normalize(rawRight, 0, 100);
        const top = normalize(rawTop, 0, 100);
        const bottom = normalize(rawBottom, 0, 100);
        return {
          left: Math.min(left, right - 4),
          right: Math.max(right, left + 4),
          top: Math.min(top, bottom - 3),
          bottom: Math.max(bottom, top + 3)
        };
      }

      function anchorsFromBounds(bounds, size) {
        const centerX = (bounds.left + bounds.right) / 2;
        const centerY = (bounds.top + bounds.bottom) / 2;
        const anchorSize = size || 9;
        return [
          { key: 'upper_left', label: 'Upper left lip edge', x: bounds.left, y: bounds.top, size: anchorSize },
          { key: 'upper_center', label: 'Upper center lip edge', x: centerX, y: bounds.top, size: anchorSize },
          { key: 'upper_right', label: 'Upper right lip edge', x: bounds.right, y: bounds.top, size: anchorSize },
          { key: 'right_inner', label: 'Right smile corner', x: bounds.right, y: centerY, size: anchorSize },
          { key: 'lower_right', label: 'Lower right lip edge', x: bounds.right, y: bounds.bottom, size: anchorSize },
          { key: 'lower_center', label: 'Lower center lip edge', x: centerX, y: bounds.bottom, size: anchorSize },
          { key: 'lower_left', label: 'Lower left lip edge', x: bounds.left, y: bounds.bottom, size: anchorSize },
          { key: 'left_inner', label: 'Left smile corner', x: bounds.left, y: centerY, size: anchorSize }
        ];
      }

      function getPointByKey(points, key) {
        return points.find(function (point) { return point.key === key; }) || null;
      }

      function getPointBounds(points) {
        if (!points || !points.length) {
          return { left: 0, right: 100, top: 0, bottom: 100 };
        }
        return points.reduce(function (bounds, point) {
          return {
            left: Math.min(bounds.left, point.x),
            right: Math.max(bounds.right, point.x),
            top: Math.min(bounds.top, point.y),
            bottom: Math.max(bounds.bottom, point.y)
          };
        }, { left: 100, right: 0, top: 100, bottom: 0 });
      }

      function getVisibleToothWidthRatios() {
        return {
          4: 0.62,
          5: 0.76,
          6: 0.96,
          7: 0.84,
          8: 1.34,
          9: 1.34,
          10: 0.84,
          11: 0.96,
          12: 0.76,
          13: 0.62
        };
      }

      function getExpectedVisibleToothBounds(toothNumber, mouthBounds, verticalBounds) {
        if (!mouthBounds) return null;
        const ratios = getVisibleToothWidthRatios();
        const mouthWidth = Math.max(1, mouthBounds.right - mouthBounds.left);
        const mouthHeight = Math.max(1, mouthBounds.bottom - mouthBounds.top);
        const totalRatio = visibleUpperTeeth.reduce(function (sum, number) {
          return sum + ratios[number];
        }, 0);
        const unit = mouthWidth / Math.max(1, totalRatio);
        const centerSeam = (mouthBounds.left + mouthBounds.right) / 2;
        let left = centerSeam;
        let right = centerSeam;
        if (toothNumber <= 8) {
          right = centerSeam;
          for (let number = 8; number >= toothNumber; number -= 1) {
            left = right - (unit * ratios[number]);
            if (number === toothNumber) break;
            right = left;
          }
        } else {
          left = centerSeam;
          for (let number = 9; number <= toothNumber; number += 1) {
            right = left + (unit * ratios[number]);
            if (number === toothNumber) break;
            left = right;
          }
        }
        const referenceTop = verticalBounds ? verticalBounds.top : mouthBounds.top + (mouthHeight * 0.20);
        const referenceBottom = verticalBounds ? verticalBounds.bottom : mouthBounds.bottom - (mouthHeight * 0.18);
        const referenceHeight = Math.max(1, referenceBottom - referenceTop);
        const targetHeight = Math.max(mouthHeight * 0.18, Math.min(referenceHeight, mouthHeight * 0.46));
        const centerY = (referenceTop + referenceBottom) / 2;
        return {
          left: normalize(left, 0, 100),
          right: normalize(right, 0, 100),
          top: normalize(centerY - (targetHeight / 2), 0, 100),
          bottom: normalize(centerY + (targetHeight / 2), 0, 100)
        };
      }

      function transformContourPoints(points, fromBounds, toBounds) {
        if (!points || !points.length) return [];
        const fromWidth = Math.max(1, fromBounds.right - fromBounds.left);
        const fromHeight = Math.max(1, fromBounds.bottom - fromBounds.top);
        const toWidth = Math.max(1, toBounds.right - toBounds.left);
        const toHeight = Math.max(1, toBounds.bottom - toBounds.top);
        return points.map(function (point) {
          const nx = (point.x - fromBounds.left) / fromWidth;
          const ny = (point.y - fromBounds.top) / fromHeight;
          return clampPoint({
            x: toBounds.left + (nx * toWidth),
            y: toBounds.top + (ny * toHeight)
          });
        });
      }

      function rgbToHsv(r, g, b) {
        const red = r / 255;
        const green = g / 255;
        const blue = b / 255;
        const max = Math.max(red, green, blue);
        const min = Math.min(red, green, blue);
        const delta = max - min;
        let hue = 0;
        if (delta !== 0) {
          if (max === red) {
            hue = 60 * (((green - blue) / delta) % 6);
          } else if (max === green) {
            hue = 60 * (((blue - red) / delta) + 2);
          } else {
            hue = 60 * (((red - green) / delta) + 4);
          }
        }
        if (hue < 0) {
          hue += 360;
        }
        return {
          h: hue,
          s: max === 0 ? 0 : delta / max,
          v: max,
        };
      }

      function isLikelyToothPixel(r, g, b) {
        const max = Math.max(r, g, b);
        const min = Math.min(r, g, b);
        const spread = max - min;
        const saturation = max === 0 ? 0 : spread / max;
        const brightEnough = max >= 152 && r >= 132 && g >= 127 && b >= 112;
        const neutralEnough = saturation <= 0.36 && spread <= 70;
        const tooPink = r > g + 42 && r > b + 52 && b < 170;
        return brightEnough && neutralEnough && !tooPink;
      }

      function isPossibleToothPixel(r, g, b) {
        const max = Math.max(r, g, b);
        const min = Math.min(r, g, b);
        const spread = max - min;
        const saturation = max === 0 ? 0 : spread / max;
        const hsv = rgbToHsv(r, g, b);
        const brightEnough = max >= 120 && r >= 100 && g >= 96 && b >= 84;
        const neutralEnough = saturation <= 0.42 && spread <= 88;
        const notPinkGum = !(hsv.h >= 330 || hsv.h <= 24) || saturation <= 0.30 || max >= 182;
        const notLipShadow = !(r > g + 38 && r > b + 48 && b < 158);
        return brightEnough && neutralEnough && notPinkGum && notLipShadow;
      }

      function buildToothPixelContour(mask, width, height, bounds) {
        const minX = Math.max(0, Math.floor(bounds.minX));
        const maxX = Math.min(width - 1, Math.ceil(bounds.maxX));
        const minY = Math.max(0, Math.floor(bounds.minY));
        const maxY = Math.min(height - 1, Math.ceil(bounds.maxY));
        const roiHeight = Math.max(1, maxY - minY + 1);
        const columns = [];
        for (let x = minX; x <= maxX; x += 1) {
          let top = -1;
          let bottom = -1;
          let hits = 0;
          for (let y = minY; y <= maxY; y += 1) {
            if (!mask[y * width + x]) continue;
            if (top < 0) top = y;
            bottom = y;
            hits += 1;
          }
          if (hits >= Math.max(2, Math.round(roiHeight * 0.08))) {
            columns.push({ x, top, bottom, hits });
          }
        }
        if (columns.length < 4) return [];
        const maxHits = columns.reduce(function (carry, column) {
          return Math.max(carry, column.hits);
        }, 0);
        const filtered = columns.filter(function (column) {
          return column.hits >= Math.max(2, Math.round(maxHits * 0.20));
        });
        const usable = filtered.length >= 4 ? filtered : columns;
        if (usable.length < 4) return [];

        const smoothValue = function (index, key) {
          let sum = 0;
          let count = 0;
          for (let offset = -4; offset <= 4; offset += 1) {
            const item = usable[index + offset];
            if (!item) continue;
            sum += item[key];
            count += 1;
          }
          return count ? sum / count : usable[index][key];
        };
        const sampleCount = Math.min(31, Math.max(17, usable.length));
        const topPoints = [];
        const bottomPoints = [];
        for (let i = 0; i < sampleCount; i += 1) {
          const index = Math.round(i * (usable.length - 1) / Math.max(1, sampleCount - 1));
          const column = usable[index];
          topPoints.push(clampPoint({
            x: (column.x / width) * 100,
            y: ((smoothValue(index, 'top') - 0.86) / height) * 100
          }));
          bottomPoints.unshift(clampPoint({
            x: (column.x / width) * 100,
            y: ((smoothValue(index, 'bottom') + 0.84) / height) * 100
          }));
        }
        const rawContour = topPoints.concat(bottomPoints);
        const contour = densifyPolygonPoints(rawContour);
        const contourBounds = getPointBounds(contour);
        if ((contourBounds.right - contourBounds.left) < 1.6 || (contourBounds.bottom - contourBounds.top) < 1.8) {
          return [];
        }
        return contour;
      }

      function buildMaskBoundaryContour(mask, width, height, bounds) {
        const minX = Math.max(1, Math.floor(bounds.minX));
        const maxX = Math.min(width - 2, Math.ceil(bounds.maxX));
        const minY = Math.max(1, Math.floor(bounds.minY));
        const maxY = Math.min(height - 2, Math.ceil(bounds.maxY));
        let sumX = 0;
        let sumY = 0;
        let pixelCount = 0;
        for (let y = minY; y <= maxY; y += 1) {
          for (let x = minX; x <= maxX; x += 1) {
            if (!mask[(y * width) + x]) continue;
            sumX += x;
            sumY += y;
            pixelCount += 1;
          }
        }
        if (pixelCount < 12) return [];

        const centerX = sumX / pixelCount;
        const centerY = sumY / pixelCount;
        const binCount = 64;
        const bins = new Array(binCount).fill(null);
        for (let y = minY; y <= maxY; y += 1) {
          for (let x = minX; x <= maxX; x += 1) {
            const index = (y * width) + x;
            if (!mask[index]) continue;
            const isBoundary = !mask[index - 1]
              || !mask[index + 1]
              || !mask[index - width]
              || !mask[index + width]
              || !mask[index - width - 1]
              || !mask[index - width + 1]
              || !mask[index + width - 1]
              || !mask[index + width + 1];
            if (!isBoundary) continue;
            const dx = x - centerX;
            const dy = y - centerY;
            const angle = Math.atan2(dy, dx);
            const normalizedAngle = (angle + Math.PI) / (Math.PI * 2);
            const binIndex = Math.max(0, Math.min(binCount - 1, Math.floor(normalizedAngle * binCount)));
            const distance = (dx * dx) + (dy * dy);
            if (!bins[binIndex] || distance > bins[binIndex].distance) {
              bins[binIndex] = { x, y, distance };
            }
          }
        }

        const ordered = bins.filter(Boolean).map(function (point) {
          return { x: point.x, y: point.y };
        });
        if (ordered.length < 12) return [];
        const smoothed = ordered.map(function (point, index) {
          const previous = ordered[(index - 1 + ordered.length) % ordered.length];
          const next = ordered[(index + 1) % ordered.length];
          return {
            x: (point.x * 0.68) + (previous.x * 0.16) + (next.x * 0.16),
            y: (point.y * 0.68) + (previous.y * 0.16) + (next.y * 0.16)
          };
        });
        return smoothed.map(function (point) {
          return canvasPointToImagePct(point, width, height);
        });
      }

      function clampRect(rect, width, height) {
        const minX = Math.max(0, Math.min(width - 1, Math.floor(rect.minX)));
        const minY = Math.max(0, Math.min(height - 1, Math.floor(rect.minY)));
        const maxX = Math.max(minX + 1, Math.min(width - 1, Math.ceil(rect.maxX)));
        const maxY = Math.max(minY + 1, Math.min(height - 1, Math.ceil(rect.maxY)));
        return { minX, minY, maxX, maxY };
      }

      function imagePctPointToCanvas(point, width, height) {
        return {
          x: normalize(((point.x || 0) / 100) * width, 0, Math.max(0, width - 1)),
          y: normalize(((point.y || 0) / 100) * height, 0, Math.max(0, height - 1))
        };
      }

      function canvasPointToImagePct(point, width, height) {
        return clampPoint({
          x: (point.x / Math.max(1, width)) * 100,
          y: (point.y / Math.max(1, height)) * 100
        });
      }

      function findNearestToothMaskPixel(mask, width, height, seedX, seedY, maxRadius) {
        const searchRadius = Math.max(3, Math.round(maxRadius || 8));
        let best = null;
        let bestDistance = Number.POSITIVE_INFINITY;
        for (let radius = 0; radius <= searchRadius; radius += 1) {
          for (let y = Math.max(0, seedY - radius); y <= Math.min(height - 1, seedY + radius); y += 1) {
            for (let x = Math.max(0, seedX - radius); x <= Math.min(width - 1, seedX + radius); x += 1) {
              if (!mask[y * width + x]) continue;
              const distance = Math.abs(x - seedX) + Math.abs(y - seedY);
              if (distance < bestDistance) {
                bestDistance = distance;
                best = { x, y };
              }
            }
          }
          if (best) break;
        }
        return best;
      }

      function floodFillComponentMask(mask, width, height, seedX, seedY) {
        const seedIndex = (seedY * width) + seedX;
        if (!mask[seedIndex]) return null;
        const visited = new Uint8Array(width * height);
        const component = new Uint8Array(width * height);
        const queue = [seedIndex];
        visited[seedIndex] = 1;
        component[seedIndex] = 1;
        let head = 0;
        let count = 1;
        let minX = seedX;
        let maxX = seedX;
        let minY = seedY;
        let maxY = seedY;
        const offsets = [
          -width - 1, -width, -width + 1,
          -1, 1,
          width - 1, width, width + 1
        ];
        while (head < queue.length) {
          const index = queue[head++];
          const y = Math.floor(index / width);
          const x = index - (y * width);
          if (x < minX) minX = x;
          if (x > maxX) maxX = x;
          if (y < minY) minY = y;
          if (y > maxY) maxY = y;
          for (let i = 0; i < offsets.length; i += 1) {
            const neighbor = index + offsets[i];
            if (neighbor < 0 || neighbor >= mask.length || visited[neighbor]) continue;
            const ny = Math.floor(neighbor / width);
            const nx = neighbor - (ny * width);
            if (Math.abs(nx - x) > 1 || Math.abs(ny - y) > 1) continue;
            visited[neighbor] = 1;
            if (!mask[neighbor]) continue;
            component[neighbor] = 1;
            queue.push(neighbor);
            count += 1;
          }
        }
        return {
          mask: component,
          count: count,
          bounds: { minX, maxX, minY, maxY }
        };
      }

      function translateContourPointsToImagePct(points, cropRect, cropWidth, cropHeight, canvasWidth, canvasHeight) {
        return (points || []).map(function (point) {
          const absoluteX = cropRect.minX + ((point.x / 100) * cropWidth);
          const absoluteY = cropRect.minY + ((point.y / 100) * cropHeight);
          return canvasPointToImagePct({ x: absoluteX, y: absoluteY }, canvasWidth, canvasHeight);
        });
      }

      function detectSingleToothRegionFromSeed(sourceImage, seedPoint, toothNumber, mouthReferenceBounds, sourceBounds, options) {
        const detectorImage = sourceImage || detectPreview || workPreview;
        if (!detectorImage || !detectorImage.naturalWidth || !detectorImage.naturalHeight) {
          return null;
        }
        const canvas = document.createElement('canvas');
        const maxWidth = 880;
        const scale = Math.min(1, maxWidth / Math.max(1, detectorImage.naturalWidth));
        const canvasWidth = Math.max(1, Math.round(detectorImage.naturalWidth * scale));
        const canvasHeight = Math.max(1, Math.round(detectorImage.naturalHeight * scale));
        canvas.width = canvasWidth;
        canvas.height = canvasHeight;
        const context = canvas.getContext('2d', { willReadFrequently: true });
        if (!context) {
          return null;
        }
        try {
          context.drawImage(detectorImage, 0, 0, canvasWidth, canvasHeight);
        } catch (error) {
          return null;
        }
        const imageData = context.getImageData(0, 0, canvasWidth, canvasHeight).data;
        const expectedToothBounds = getExpectedVisibleToothBounds(toothNumber, mouthReferenceBounds, sourceBounds);
        const fallbackCenter = expectedToothBounds
          ? {
            x: ((expectedToothBounds.left + expectedToothBounds.right) / 2) * 0.01 * canvasWidth,
            y: ((expectedToothBounds.top + expectedToothBounds.bottom) / 2) * 0.01 * canvasHeight
          }
          : {
            x: ((sourceBounds.left + sourceBounds.right) / 2) * 0.01 * canvasWidth,
            y: ((sourceBounds.top + sourceBounds.bottom) / 2) * 0.01 * canvasHeight
          };
        const seedCanvasPoint = seedPoint
          ? imagePctPointToCanvas(seedPoint, canvasWidth, canvasHeight)
          : fallbackCenter;
        const expectedWidth = expectedToothBounds ? Math.max(1, ((expectedToothBounds.right - expectedToothBounds.left) / 100) * canvasWidth) : Math.max(8, (sourceBounds.right - sourceBounds.left) * 0.10 * canvasWidth / 100);
        const expectedHeight = expectedToothBounds ? Math.max(1, ((expectedToothBounds.bottom - expectedToothBounds.top) / 100) * canvasHeight) : Math.max(8, (sourceBounds.bottom - sourceBounds.top) * 0.12 * canvasHeight / 100);
        const cropWidth = normalize(expectedWidth * 2.45, Math.max(18, canvasWidth * 0.024), Math.min(canvasWidth, canvasWidth * 0.24));
        const cropHeight = normalize(expectedHeight * 2.35, Math.max(18, canvasHeight * 0.040), Math.min(canvasHeight, canvasHeight * 0.24));
        const cropRect = clampRect({
          minX: seedCanvasPoint.x - (cropWidth / 2),
          maxX: seedCanvasPoint.x + (cropWidth / 2),
          minY: seedCanvasPoint.y - (cropHeight * 0.42),
          maxY: seedCanvasPoint.y + (cropHeight * 0.58)
        }, canvasWidth, canvasHeight);
        const focusRectSource = expectedToothBounds || {
          left: (cropRect.minX / canvasWidth) * 100,
          right: (cropRect.maxX / canvasWidth) * 100,
          top: (cropRect.minY / canvasHeight) * 100,
          bottom: (cropRect.maxY / canvasHeight) * 100
        };
        const focusRect = clampRect({
          minX: ((focusRectSource.left / 100) * canvasWidth) - Math.max(3, expectedWidth * 0.14),
          maxX: ((focusRectSource.right / 100) * canvasWidth) + Math.max(3, expectedWidth * 0.14),
          minY: ((focusRectSource.top / 100) * canvasHeight) - Math.max(3, expectedHeight * 0.16),
          maxY: ((focusRectSource.bottom / 100) * canvasHeight) + Math.max(3, expectedHeight * 0.20)
        }, canvasWidth, canvasHeight);
        const localWidth = cropRect.maxX - cropRect.minX + 1;
        const localHeight = cropRect.maxY - cropRect.minY + 1;
        if (localWidth < 8 || localHeight < 8) {
          return null;
        }
        const localMask = new Uint8Array(localWidth * localHeight);
        let toothHits = 0;
        for (let y = 0; y < localHeight; y += 1) {
          const globalY = cropRect.minY + y;
          for (let x = 0; x < localWidth; x += 1) {
            const globalX = cropRect.minX + x;
            if (globalX < focusRect.minX || globalX > focusRect.maxX || globalY < focusRect.minY || globalY > focusRect.maxY) {
              continue;
            }
            const index = ((globalY * canvasWidth) + globalX) * 4;
            const r = imageData[index];
            const g = imageData[index + 1];
            const b = imageData[index + 2];
            const brightness = (0.2126 * r) + (0.7152 * g) + (0.0722 * b);
            const hsv = rgbToHsv(r, g, b);
            const tooPink = r > g + 44 && r > b + 52 && b < 168;
            const likelyTooth = hsv.v >= 0.58 && hsv.s <= 0.30 && brightness >= 142 && !tooPink;
            if (!likelyTooth) continue;
            localMask[(y * localWidth) + x] = 1;
            toothHits += 1;
          }
        }
        if (toothHits < 8) {
          return null;
        }
        let localSeedX = Math.max(0, Math.min(localWidth - 1, Math.round(seedCanvasPoint.x - cropRect.minX)));
        let localSeedY = Math.max(0, Math.min(localHeight - 1, Math.round(seedCanvasPoint.y - cropRect.minY)));
        if (!localMask[(localSeedY * localWidth) + localSeedX]) {
          const nearest = findNearestToothMaskPixel(localMask, localWidth, localHeight, localSeedX, localSeedY, Math.max(6, Math.round(Math.min(localWidth, localHeight) * 0.28)));
          if (nearest) {
            localSeedX = nearest.x;
            localSeedY = nearest.y;
          }
        }
        if (!localMask[(localSeedY * localWidth) + localSeedX]) {
          return null;
        }
        const component = floodFillComponentMask(localMask, localWidth, localHeight, localSeedX, localSeedY);
        if (!component || component.count < 10) {
          return null;
        }
        const componentBounds = component.bounds;
        const paddedBounds = clampRect({
          minX: componentBounds.minX - Math.max(1, localWidth * 0.04),
          maxX: componentBounds.maxX + Math.max(1, localWidth * 0.04),
          minY: componentBounds.minY - Math.max(1, localHeight * 0.06),
          maxY: componentBounds.maxY + Math.max(1, localHeight * 0.07)
        }, localWidth, localHeight);
        let contour = buildToothPixelContour(component.mask, localWidth, localHeight, paddedBounds);
        if (!contour.length) {
          contour = densifyPolygonPoints([
            clampPoint({ x: (componentBounds.minX / localWidth) * 100, y: (componentBounds.minY / localHeight) * 100 }),
            clampPoint({ x: (componentBounds.maxX / localWidth) * 100, y: (componentBounds.minY / localHeight) * 100 }),
            clampPoint({ x: (componentBounds.maxX / localWidth) * 100, y: (componentBounds.maxY / localHeight) * 100 }),
            clampPoint({ x: (componentBounds.minX / localWidth) * 100, y: (componentBounds.maxY / localHeight) * 100 })
          ]);
        }
        const translatedContour = translateContourPointsToImagePct(contour, cropRect, localWidth, localHeight, canvasWidth, canvasHeight);
        const contourBounds = getPointBounds(translatedContour);
        if ((contourBounds.right - contourBounds.left) < 0.75 || (contourBounds.bottom - contourBounds.top) < 0.9) {
          return null;
        }
        return {
          number: toothNumber,
          label: '#' + toothNumber,
          polygon: translatedContour.length ? densifyPolygonPoints(translatedContour) : translatedContour,
          source: 'single_tooth_seeded_crop'
        };
      }

      function pointBoundsFromPixelBounds(bounds, width, height) {
        return {
          left: normalize((bounds.minX / width) * 100, 0, 100),
          right: normalize((bounds.maxX / width) * 100, 0, 100),
          top: normalize((bounds.minY / height) * 100, 0, 100),
          bottom: normalize((bounds.maxY / height) * 100, 0, 100)
        };
      }

      function findColumnValley(colHits, minX, maxX, expectedX, windowSize) {
        const searchMin = Math.max(minX + 1, Math.round(expectedX - windowSize));
        const searchMax = Math.min(maxX - 1, Math.round(expectedX + windowSize));
        let bestX = normalize(expectedX, searchMin, searchMax);
        let bestScore = Number.POSITIVE_INFINITY;
        for (let x = searchMin; x <= searchMax; x += 1) {
          const current = colHits[x] || 0;
          const left = colHits[x - 1] || 0;
          const right = colHits[x + 1] || 0;
          const valleyDepth = Math.max(0, ((left + right) / 2) - current);
          const distancePenalty = Math.abs(x - expectedX) * 0.11;
          const score = current - (valleyDepth * 0.42) + distancePenalty;
          if (score < bestScore) {
            bestScore = score;
            bestX = x;
          }
        }
        return bestX;
      }

      function buildToothSegment(contourMask, width, height, smileBounds, bounds, hitCounts) {
        const segmentWidth = Math.max(2, bounds.maxX - bounds.minX + 1);
        const paddedBounds = {
          minX: Math.max(smileBounds.minX, Math.floor(bounds.minX)),
          maxX: Math.min(smileBounds.maxX, Math.ceil(bounds.maxX)),
          minY: Math.max(0, Math.floor(bounds.minY !== undefined ? bounds.minY : smileBounds.minY)),
          maxY: Math.min(height - 1, Math.ceil(bounds.maxY !== undefined ? bounds.maxY : smileBounds.maxY))
        };
        const boundaryContour = buildMaskBoundaryContour(contourMask, width, height, paddedBounds);
        const columnContour = buildToothPixelContour(contourMask, width, height, paddedBounds);
        const rawContour = boundaryContour.length >= 12 ? boundaryContour : columnContour;
        const fallback = pointBoundsFromPixelBounds(paddedBounds, width, height);
        const insetX = Math.max(0.05, (fallback.right - fallback.left) * 0.08);
        const insetY = Math.max(0.04, (fallback.bottom - fallback.top) * 0.05);
        const fallbackContour = densifyPolygonPoints([
          { x: fallback.left + insetX, y: fallback.top + insetY },
          { x: (fallback.left + fallback.right) / 2, y: fallback.top },
          { x: fallback.right - insetX, y: fallback.top + insetY },
          { x: fallback.right, y: fallback.top + ((fallback.bottom - fallback.top) * 0.32) },
          { x: fallback.right - (insetX * 0.35), y: fallback.bottom - insetY },
          { x: (fallback.left + fallback.right) / 2, y: fallback.bottom },
          { x: fallback.left + (insetX * 0.35), y: fallback.bottom - insetY },
          { x: fallback.left, y: fallback.top + ((fallback.bottom - fallback.top) * 0.32) }
        ]);
        const rawContourBounds = rawContour.length ? getPointBounds(rawContour) : null;
        const rawWidth = rawContourBounds ? rawContourBounds.right - rawContourBounds.left : 0;
        const rawHeight = rawContourBounds ? rawContourBounds.bottom - rawContourBounds.top : 0;
        const fallbackWidth = fallback.right - fallback.left;
        const fallbackHeight = fallback.bottom - fallback.top;
        const usesPixelContour = rawContour.length >= 8
          && rawWidth >= Math.max(0.28, fallbackWidth * 0.16)
          && rawHeight >= Math.max(0.42, fallbackHeight * 0.22)
          && rawWidth <= fallbackWidth * 1.08;
        const contour = usesPixelContour ? rawContour : fallbackContour;
        const contourBounds = getPointBounds(contour);
        return {
          bounds: paddedBounds,
          contour,
          count: hitCounts || 0,
          cx: (paddedBounds.minX + paddedBounds.maxX) / 2,
          width: paddedBounds.maxX - paddedBounds.minX + 1,
          left: contourBounds.left,
          right: contourBounds.right,
          top: contourBounds.top,
          bottom: contourBounds.bottom,
          source: usesPixelContour
            ? (boundaryContour.length >= 12 ? 'upper_tooth_boundary_contour' : 'upper_tooth_slot_contour')
            : 'upper_tooth_slot_fallback'
        };
      }

      function findUpperToothValleys(colHits, separatorScores, minX, maxX, peakHit) {
        const valleys = [];
        const threshold = Math.max(1, peakHit * 0.58);
        for (let x = minX + 2; x <= maxX - 2; x += 1) {
          const current = colHits[x] || 0;
          const left = colHits[x - 1] || 0;
          const right = colHits[x + 1] || 0;
          const prev = colHits[x - 2] || left;
          const next = colHits[x + 2] || right;
          const shoulder = Math.max(left, right, prev, next);
          const valleyDepth = shoulder - current;
          if (current > threshold) continue;
          if (current > left || current > right) continue;
          if (valleyDepth < Math.max(2, peakHit * 0.10)) continue;
          const separatorBoost = separatorScores && Number.isFinite(separatorScores[x]) ? separatorScores[x] : 0;
          valleys.push({ x, score: valleyDepth - (current * 0.30) + (separatorBoost * 1.15) });
        }
        valleys.sort(function (a, b) { return a.x - b.x; });
        const filtered = [];
        valleys.forEach(function (valley) {
          const previous = filtered[filtered.length - 1];
          if (!previous || Math.abs(previous.x - valley.x) > 5) {
            filtered.push(valley);
          } else if (valley.score > previous.score) {
            filtered[filtered.length - 1] = valley;
          }
        });
        return filtered;
      }

      function buildSeparatorScores(data, width, smileBounds, smileHeight) {
        const scores = [];
        const imageHeight = Math.max(1, Math.round(data.length / Math.max(1, width * 4)));
        const sampleTop = Math.max(smileBounds.minY, Math.round(smileBounds.minY + (smileHeight * 0.12)));
        const sampleBottom = Math.min(smileBounds.maxY, Math.round(smileBounds.minY + (smileHeight * 0.78)));
        const shoulderDistance = Math.max(4, Math.round((smileBounds.maxX - smileBounds.minX + 1) * 0.017));
        for (let x = smileBounds.minX; x <= smileBounds.maxX; x += 1) {
          const darknessSamples = [];
          for (let y = sampleTop; y <= sampleBottom; y += 1) {
            const center = pixelBrightnessAt(data, width, imageHeight, x, y);
            const left = pixelBrightnessAt(data, width, imageHeight, x - shoulderDistance, y);
            const right = pixelBrightnessAt(data, width, imageHeight, x + shoulderDistance, y);
            const localValley = Math.max(0, Math.min(left, right) - center);
            if (localValley > 1.5) darknessSamples.push(localValley);
          }
          darknessSamples.sort(function (a, b) { return b - a; });
          const sampleCount = Math.max(3, Math.ceil(darknessSamples.length * 0.28));
          const strongest = darknessSamples.slice(0, sampleCount);
          const strongTotal = strongest.reduce(function (sum, value) { return sum + (value * value); }, 0);
          const continuity = darknessSamples.length / Math.max(1, sampleBottom - sampleTop + 1);
          scores[x] = strongTotal + (continuity * 180);
        }
        return scores;
      }

      function findCentralSeam(valleys, smileCenter, minX, maxX) {
        if (Array.isArray(valleys) && valleys.length) {
          return valleys.reduce(function (best, valley) {
            if (!best) return valley;
            return Math.abs(valley.x - smileCenter) < Math.abs(best.x - smileCenter) ? valley : best;
          }, null);
        }
        return { x: normalize(smileCenter, minX, maxX), score: 0 };
      }

      function findNeighborValley(valleys, seamX, direction, fallbackX, minGap, maxGap) {
        const filtered = (valleys || []).filter(function (valley) {
          const gap = direction < 0 ? seamX - valley.x : valley.x - seamX;
          return gap >= minGap && gap <= maxGap;
        });
        if (!filtered.length) {
          return fallbackX;
        }
        const sorted = filtered.sort(function (a, b) {
          const gapA = Math.abs(a.x - seamX);
          const gapB = Math.abs(b.x - seamX);
          if (gapA !== gapB) return gapA - gapB;
          return (b.score || 0) - (a.score || 0);
        });
        return sorted[0].x;
      }

      function buildNeighborToothBounds(innerBounds, outerValley, smileBounds, expectedWidth, direction, innerGap) {
        const width = Math.max(5, Math.round(expectedWidth));
        const gap = Math.max(1, innerGap || 1);
        if (direction < 0) {
          const maxX = Math.max(smileBounds.minX + 1, innerBounds.minX - gap);
          const minX = Math.max(smileBounds.minX, Math.min(outerValley + 1, maxX - width + 1));
          return {
            minX,
            maxX
          };
        }
        const minX = Math.min(smileBounds.maxX - 1, innerBounds.maxX + gap);
        const maxX = Math.min(smileBounds.maxX, Math.max(outerValley - 1, minX + width - 1));
        return {
          minX,
          maxX
        };
      }

      function findBestToothSeparator(colHits, separatorScores, expectedX, minX, maxX, windowSize, peakHit) {
        const searchMin = Math.max(minX + 2, Math.round(expectedX - windowSize));
        const searchMax = Math.min(maxX - 2, Math.round(expectedX + windowSize));
        if (searchMax <= searchMin) {
          return Math.round(normalize(expectedX, minX + 1, maxX - 1));
        }
        let bestX = Math.round(normalize(expectedX, searchMin, searchMax));
        let bestScore = Number.NEGATIVE_INFINITY;
        const safePeak = Math.max(1, peakHit || 1);
        for (let x = searchMin; x <= searchMax; x += 1) {
          const current = colHits[x] || 0;
          const shoulder = Math.max(
            colHits[x - 4] || 0,
            colHits[x - 3] || 0,
            colHits[x - 2] || 0,
            colHits[x + 2] || 0,
            colHits[x + 3] || 0,
            colHits[x + 4] || 0
          );
          const valleyDepth = Math.max(0, shoulder - current) / safePeak;
          const separator = Math.max(0, separatorScores[x] || 0) / Math.max(1, (maxX - minX) * 0.025);
          const occupancyPenalty = current / safePeak;
          const distancePenalty = Math.abs(x - expectedX) / Math.max(1, windowSize);
          const score = (separator * 1.45) + (valleyDepth * 3.2) - (occupancyPenalty * 1.15) - (distancePenalty * 0.72);
          if (score > bestScore) {
            bestScore = score;
            bestX = x;
          }
        }
        return bestX;
      }

      function findBestContrastSeparator(separatorScores, expectedX, minX, maxX, windowSize, distanceWeight) {
        const searchMin = Math.max(minX, Math.round(expectedX - windowSize));
        const searchMax = Math.min(maxX, Math.round(expectedX + windowSize));
        if (searchMax <= searchMin) {
          return Math.round(normalize(expectedX, minX, maxX));
        }

        let peakScore = 0;
        for (let x = searchMin; x <= searchMax; x += 1) {
          peakScore = Math.max(peakScore, separatorScores[x] || 0);
        }
        if (peakScore <= 0) {
          return Math.round(normalize(expectedX, searchMin, searchMax));
        }

        const positionWeight = Number.isFinite(distanceWeight) ? distanceWeight : 0.58;
        let bestX = Math.round(normalize(expectedX, searchMin, searchMax));
        let bestScore = Number.NEGATIVE_INFINITY;
        for (let x = searchMin; x <= searchMax; x += 1) {
          const contrast = (separatorScores[x] || 0) / peakScore;
          const distance = Math.abs(x - expectedX) / Math.max(1, windowSize);
          const score = contrast - (distance * positionWeight);
          if (score > bestScore) {
            bestScore = score;
            bestX = x;
          }
        }
        return bestX;
      }

      function buildConnectedToothSlotMask(hardMask, softMask, width, height, bounds) {
        const slotMinX = Math.max(0, Math.floor(bounds.minX));
        const slotMaxX = Math.min(width - 1, Math.ceil(bounds.maxX));
        const minY = Math.max(0, Math.floor(bounds.minY));
        const maxY = Math.min(height - 1, Math.ceil(bounds.maxY));
        const slotWidth = Math.max(2, slotMaxX - slotMinX + 1);
        const minX = Math.max(0, Math.floor(slotMinX - (slotWidth * 0.42)));
        const maxX = Math.min(width - 1, Math.ceil(slotMaxX + (slotWidth * 0.42)));
        const output = new Uint8Array(width * height);
        const queued = new Uint8Array(width * height);
        const queue = [];
        const targetX = (slotMinX + slotMaxX) / 2;
        const targetY = minY + ((maxY - minY) * 0.48);
        let seed = null;
        let seedDistance = Number.POSITIVE_INFINITY;
        for (let y = minY; y <= maxY; y += 1) {
          for (let x = slotMinX; x <= slotMaxX; x += 1) {
            const index = (y * width) + x;
            if (!hardMask[index]) continue;
            const distance = ((x - targetX) * (x - targetX)) + (((y - targetY) * 0.7) * ((y - targetY) * 0.7));
            if (distance < seedDistance) {
              seedDistance = distance;
              seed = { x, y, index };
            }
          }
        }
        if (!seed) {
          return hardMask;
        }
        output[seed.index] = 1;
        queued[seed.index] = 1;
        queue.push(seed.index);
        let head = 0;
        while (head < queue.length) {
          const index = queue[head++];
          const y = Math.floor(index / width);
          const x = index - (y * width);
          const neighbors = [index - 1, index + 1, index - width, index + width];
          neighbors.forEach(function (neighbor) {
            if (neighbor < 0 || neighbor >= softMask.length || queued[neighbor] || !softMask[neighbor]) return;
            const nextY = Math.floor(neighbor / width);
            const nextX = neighbor - (nextY * width);
            if (nextX < minX || nextX > maxX || nextY < minY || nextY > maxY) return;
            if (Math.abs(nextX - x) + Math.abs(nextY - y) !== 1) return;
            queued[neighbor] = 1;
            output[neighbor] = 1;
            queue.push(neighbor);
          });
        }
        return output;
      }

      function extractOpenCvMaskContour(binaryMask, width, height) {
        if (!window.cv || typeof window.cv.Mat !== 'function') return [];
        let source = null;
        let cleaned = null;
        let cleanKernel = null;
        let contours = null;
        let hierarchy = null;
        let simplified = null;
        try {
          source = window.cv.Mat.zeros(height, width, window.cv.CV_8UC1);
          for (let index = 0; index < binaryMask.length; index += 1) {
            source.data[index] = binaryMask[index] ? 255 : 0;
          }
          cleaned = new window.cv.Mat();
          cleanKernel = window.cv.getStructuringElement(window.cv.MORPH_ELLIPSE, new window.cv.Size(3, 3));
          window.cv.morphologyEx(source, cleaned, window.cv.MORPH_CLOSE, cleanKernel);
          window.cv.morphologyEx(cleaned, cleaned, window.cv.MORPH_OPEN, cleanKernel);
          contours = new window.cv.MatVector();
          hierarchy = new window.cv.Mat();
          window.cv.findContours(cleaned, contours, hierarchy, window.cv.RETR_EXTERNAL, window.cv.CHAIN_APPROX_NONE);
          if (!contours.size()) return [];
          let bestIndex = 0;
          let bestArea = 0;
          for (let index = 0; index < contours.size(); index += 1) {
            const candidate = contours.get(index);
            const area = Math.abs(window.cv.contourArea(candidate, false));
            candidate.delete();
            if (area > bestArea) {
              bestArea = area;
              bestIndex = index;
            }
          }
          const contour = contours.get(bestIndex);
          simplified = new window.cv.Mat();
          const perimeter = window.cv.arcLength(contour, true);
          window.cv.approxPolyDP(contour, simplified, Math.max(0.35, perimeter * 0.0012), true);
          contour.delete();
          const points = [];
          for (let index = 0; index < simplified.data32S.length; index += 2) {
            points.push(canvasPointToImagePct({
              x: simplified.data32S[index],
              y: simplified.data32S[index + 1]
            }, width, height));
          }
          return points.length >= 8 ? points : [];
        } catch (error) {
          return [];
        } finally {
          if (source) source.delete();
          if (cleaned) cleaned.delete();
          if (cleanKernel) cleanKernel.delete();
          if (contours) contours.delete();
          if (hierarchy) hierarchy.delete();
          if (simplified) simplified.delete();
        }
      }

      function pixelBrightnessAt(data, width, height, x, y) {
        const safeX = Math.max(0, Math.min(width - 1, Math.round(x)));
        const safeY = Math.max(0, Math.min(height - 1, Math.round(y)));
        const offset = ((safeY * width) + safeX) * 4;
        return (0.2126 * data[offset]) + (0.7152 * data[offset + 1]) + (0.0722 * data[offset + 2]);
      }

      function traceDarkToothSeparator(data, width, height, upperBand, expectedX, searchRadius) {
        const curve = new Array(height).fill(Math.round(expectedX));
        const centerY = Math.round(upperBand.minY + ((upperBand.maxY - upperBand.minY) * 0.52));
        const pickX = function (y, previousX) {
          const minX = Math.max(upperBand.minX + 1, Math.round(expectedX - searchRadius));
          const maxX = Math.min(upperBand.maxX - 1, Math.round(expectedX + searchRadius));
          let bestX = Math.round(expectedX);
          let bestScore = Number.NEGATIVE_INFINITY;
          for (let x = minX; x <= maxX; x += 1) {
            const center = pixelBrightnessAt(data, width, height, x, y);
            const leftNear = pixelBrightnessAt(data, width, height, x - 1, y);
            const rightNear = pixelBrightnessAt(data, width, height, x + 1, y);
            const leftShoulder = pixelBrightnessAt(data, width, height, x - 3, y);
            const rightShoulder = pixelBrightnessAt(data, width, height, x + 3, y);
            const valleyDepth = Math.max(0, Math.min(leftShoulder, rightShoulder) - center);
            const horizontalGradient = Math.abs(leftNear - rightNear);
            const darkness = Math.max(0, 218 - center);
            const expectedPenalty = Math.abs(x - expectedX) * 1.15;
            const continuityPenalty = Math.abs(x - previousX) * 3.2;
            const score = (valleyDepth * 3.4)
              + (horizontalGradient * 0.55)
              + (darkness * 0.42)
              - expectedPenalty
              - continuityPenalty;
            if (score > bestScore) {
              bestScore = score;
              bestX = x;
            }
          }
          return bestX;
        };

        let previousX = Math.round(expectedX);
        for (let y = centerY; y >= upperBand.minY; y -= 1) {
          previousX = pickX(y, previousX);
          curve[y] = previousX;
        }
        previousX = curve[centerY];
        for (let y = centerY + 1; y <= upperBand.maxY; y += 1) {
          previousX = pickX(y, previousX);
          curve[y] = previousX;
        }

        const smoothed = curve.slice();
        for (let y = upperBand.minY; y <= upperBand.maxY; y += 1) {
          let weightedSum = 0;
          let weightTotal = 0;
          for (let offset = -2; offset <= 2; offset += 1) {
            const sampleY = Math.max(upperBand.minY, Math.min(upperBand.maxY, y + offset));
            const weight = offset === 0 ? 3 : (Math.abs(offset) === 1 ? 2 : 1);
            weightedSum += curve[sampleY] * weight;
            weightTotal += weight;
          }
          smoothed[y] = weightedSum / Math.max(1, weightTotal);
        }
        return smoothed;
      }

      function buildDarkToothSeparatorCurves(data, width, height, upperBand, boundaries) {
        const curves = [];
        const centerBoundaryIndex = visibleUpperTeeth.indexOf(9);
        for (let index = 1; index < boundaries.length - 1; index += 1) {
          const leftWidth = Math.max(2, boundaries[index] - boundaries[index - 1]);
          const rightWidth = Math.max(2, boundaries[index + 1] - boundaries[index]);
          const radius = index === centerBoundaryIndex
            ? 2
            : Math.max(3, Math.round(Math.min(leftWidth, rightWidth) * 0.12));
          curves.push(traceDarkToothSeparator(
            data,
            width,
            height,
            upperBand,
            boundaries[index],
            radius
          ));
        }
        return curves;
      }

      function buildOpenCvToothMasks(data, hardMask, softMask, width, height, upperBand, boundaries) {
        if (!window.cv || typeof window.cv.watershed !== 'function') return null;
        let rgba = null;
        let rgb = null;
        let markers = null;
        let enamel = null;
        let closedEnamel = null;
        let allowedRegion = null;
        let closeKernel = null;
        let growKernel = null;
        try {
          const rgbaData = new Uint8ClampedArray(data.length);
          rgbaData.set(data);
          rgba = window.cv.matFromImageData(new ImageData(rgbaData, width, height));
          rgb = new window.cv.Mat();
          window.cv.cvtColor(rgba, rgb, window.cv.COLOR_RGBA2RGB);
          markers = window.cv.Mat.ones(height, width, window.cv.CV_32SC1);

          enamel = window.cv.Mat.zeros(height, width, window.cv.CV_8UC1);
          for (let y = upperBand.minY; y <= upperBand.maxY; y += 1) {
            for (let x = upperBand.minX; x <= upperBand.maxX; x += 1) {
              const index = (y * width) + x;
              enamel.data[index] = softMask[index] ? 255 : 0;
            }
          }
          closeKernel = window.cv.getStructuringElement(window.cv.MORPH_ELLIPSE, new window.cv.Size(3, 3));
          growKernel = window.cv.getStructuringElement(window.cv.MORPH_ELLIPSE, new window.cv.Size(7, 7));
          closedEnamel = new window.cv.Mat();
          allowedRegion = new window.cv.Mat();
          window.cv.morphologyEx(enamel, closedEnamel, window.cv.MORPH_CLOSE, closeKernel);
          window.cv.dilate(closedEnamel, allowedRegion, growKernel);
          const separatorCurves = buildDarkToothSeparatorCurves(data, width, height, upperBand, boundaries);

          for (let y = upperBand.minY; y <= upperBand.maxY; y += 1) {
            for (let x = upperBand.minX; x <= upperBand.maxX; x += 1) {
              const index = (y * width) + x;
              if (allowedRegion.data[index]) markers.data32S[index] = 0;
            }
          }

          const seeds = [];
          visibleUpperTeeth.forEach(function (toothNumber, slotIndex) {
            const left = boundaries[slotIndex];
            const right = boundaries[slotIndex + 1];
            const targetX = Math.round((left + right) / 2);
            const targetY = Math.round(upperBand.minY + ((upperBand.maxY - upperBand.minY) * 0.48));
            const nearest = findNearestToothMaskPixel(
              hardMask,
              width,
              height,
              targetX,
              targetY,
              Math.max(5, Math.round((right - left) * 0.55))
            );
            if (!nearest || nearest.x < left - 2 || nearest.x > right + 2) return;
            const label = slotIndex + 2;
            const radiusX = Math.max(1, Math.round((right - left) * 0.10));
            const radiusY = Math.max(1, Math.round((upperBand.maxY - upperBand.minY) * 0.08));
            for (let y = Math.max(upperBand.minY, nearest.y - radiusY); y <= Math.min(upperBand.maxY, nearest.y + radiusY); y += 1) {
              for (let x = Math.max(left, nearest.x - radiusX); x <= Math.min(right, nearest.x + radiusX); x += 1) {
                const index = (y * width) + x;
                if (hardMask[index]) markers.data32S[index] = label;
              }
            }
            markers.data32S[(nearest.y * width) + nearest.x] = label;
            seeds.push({ toothNumber: toothNumber, label: label, slotIndex: slotIndex });
          });
          if (seeds.length < 8) return null;

          const enamelTop = [];
          const enamelBottom = [];
          for (let x = upperBand.minX; x <= upperBand.maxX; x += 1) {
            let top = -1;
            let bottom = -1;
            for (let y = upperBand.minY; y <= upperBand.maxY; y += 1) {
              if (!closedEnamel.data[(y * width) + x]) continue;
              if (top < 0) top = y;
              bottom = y;
            }
            enamelTop[x] = top;
            enamelBottom[x] = bottom;
          }

          const output = {};
          seeds.forEach(function (seed) {
            const toothMask = new Uint8Array(width * height);
            let count = 0;
            const leftCurve = seed.slotIndex > 0 ? separatorCurves[seed.slotIndex - 1] : null;
            const rightCurve = seed.slotIndex < separatorCurves.length ? separatorCurves[seed.slotIndex] : null;
            for (let y = upperBand.minY; y <= upperBand.maxY; y += 1) {
              const leftLimit = leftCurve ? leftCurve[y] + 1 : upperBand.minX;
              const rightLimit = rightCurve ? rightCurve[y] - 1 : upperBand.maxX;
              for (let x = upperBand.minX; x <= upperBand.maxX; x += 1) {
                const index = (y * width) + x;
                if (!allowedRegion.data[index]) continue;
                if (x < leftLimit || x > rightLimit) continue;
                if (enamelTop[x] < 0 || y < enamelTop[x] || y > enamelBottom[x]) continue;
                toothMask[index] = 1;
                count += 1;
              }
            }
            if (count >= 10) {
              output[seed.toothNumber] = {
                mask: toothMask,
                contour: extractOpenCvMaskContour(toothMask, width, height),
                count: count
              };
            }
          });
          const outputCount = Object.keys(output).length;
          document.body.dataset.toothSegmentation = outputCount >= 8 ? 'opencv' : 'fallback';
          document.body.dataset.toothSegmentationCount = String(outputCount);
          return outputCount >= 8 ? output : null;
        } catch (error) {
          document.body.dataset.toothSegmentation = 'error';
          document.body.dataset.toothSegmentationError = String(error && error.message ? error.message : error);
          return null;
        } finally {
          if (rgba) rgba.delete();
          if (rgb) rgb.delete();
          if (markers) markers.delete();
          if (enamel) enamel.delete();
          if (closedEnamel) closedEnamel.delete();
          if (allowedRegion) allowedRegion.delete();
          if (closeKernel) closeKernel.delete();
          if (growKernel) growKernel.delete();
        }
      }

      function requestOpenCvToothContours(data, hardMask, softMask, width, height, upperBand, boundaries) {
        const requestKey = [width, height, upperBand.minX, upperBand.maxX, upperBand.minY, upperBand.maxY]
          .concat(boundaries.map(function (value) { return Math.round(value); }))
          .join(':');
        if (openCvContourCache && openCvContourCache.key === requestKey) {
          return openCvContourCache.contours;
        }
        if (openCvRequestKey === requestKey) return null;
        openCvRequestKey = requestKey;
        try {
          if (!openCvWorker) {
            openCvWorker = new Worker(openCvWorkerUrl);
            openCvWorker.addEventListener('message', function (event) {
              const result = event.data || {};
              if (!result.key || !result.contours) return;
              openCvContourCache = { key: result.key, contours: result.contours };
              openCvRequestKey = '';
              if (autoToothRegion) {
                autoToothRegion.dataset.segmentation = Object.keys(result.contours).length >= 8 ? 'opencv' : 'fallback';
                autoToothRegion.dataset.segmentationCount = String(Object.keys(result.contours).length);
              }
              document.body.dataset.toothSegmentation = Object.keys(result.contours).length >= 8 ? 'opencv' : 'fallback';
              document.body.dataset.toothSegmentationCount = String(Object.keys(result.contours).length);
              if (result.error) document.body.dataset.toothSegmentationError = String(result.error);
              detectedTeethBounds = null;
              render(readAnchorPoints(), baseContourPoints);
            });
            openCvWorker.addEventListener('error', function () {
              openCvRequestKey = '';
              if (autoToothRegion) autoToothRegion.dataset.segmentation = 'error';
              document.body.dataset.toothSegmentation = 'error';
            });
          }
          const imageCopy = new Uint8ClampedArray(data);
          const hardCopy = new Uint8Array(hardMask);
          const softCopy = new Uint8Array(softMask);
          openCvWorker.postMessage({
            key: requestKey,
            imageData: imageCopy,
            hardMask: hardCopy,
            softMask: softCopy,
            width: width,
            height: height,
            upperBand: upperBand,
            boundaries: boundaries,
            teeth: visibleUpperTeeth
          }, [imageCopy.buffer, hardCopy.buffer, softCopy.buffer]);
          if (autoToothRegion) autoToothRegion.dataset.segmentation = 'loading';
          document.body.dataset.toothSegmentation = 'loading';
        } catch (error) {
          openCvRequestKey = '';
          if (autoToothRegion) {
            autoToothRegion.dataset.segmentation = 'error';
            autoToothRegion.dataset.segmentationError = String(error && error.message ? error.message : error);
          }
          document.body.dataset.toothSegmentation = 'error';
          document.body.dataset.toothSegmentationError = String(error && error.message ? error.message : error);
        }
        return null;
      }

      function buildVisibleUpperToothSlots(mask, softMask, data, width, height, smileBounds, smileHeight) {
        const smileWidth = smileBounds.maxX - smileBounds.minX + 1;
        if (smileWidth < 24 || smileHeight < 8) return null;

        const colHits = [];
        let peakHit = 0;
        for (let x = smileBounds.minX; x <= smileBounds.maxX; x += 1) {
          let hits = 0;
          for (let y = smileBounds.minY; y <= smileBounds.maxY; y += 1) {
            if (!softMask[y * width + x]) continue;
            const verticalRatio = (y - smileBounds.minY) / Math.max(1, smileHeight);
            hits += verticalRatio <= 0.62 ? 1.15 : 0.82;
          }
          colHits[x] = hits;
          peakHit = Math.max(peakHit, hits);
        }

        const separatorScores = buildSeparatorScores(data, width, smileBounds, smileHeight);
        const ratios = getVisibleToothWidthRatios();
        const totalRatio = visibleUpperTeeth.reduce(function (sum, toothNumber) {
          return sum + ratios[toothNumber];
        }, 0);
        const ratioUnit = smileWidth / Math.max(1, totalRatio);
        const centerBoundaryIndex = visibleUpperTeeth.indexOf(9);
        const boundaries = new Array(visibleUpperTeeth.length + 1);
        boundaries[0] = smileBounds.minX;
        boundaries[boundaries.length - 1] = smileBounds.maxX;

        const leftCenterRatio = visibleUpperTeeth.slice(0, centerBoundaryIndex).reduce(function (sum, toothNumber) {
          return sum + ratios[toothNumber];
        }, 0);
        const proportionalCenter = smileBounds.minX + (ratioUnit * leftCenterRatio);
        const facialCenter = normalize(width * 0.5, smileBounds.minX, smileBounds.maxX);
        const expectedCenter = (facialCenter * 0.72) + (proportionalCenter * 0.28);
        const centerWidth = ratioUnit * ((ratios[8] + ratios[9]) / 2);
        boundaries[centerBoundaryIndex] = findBestContrastSeparator(
          separatorScores,
          expectedCenter,
          Math.round(smileBounds.minX + (smileWidth * 0.38)),
          Math.round(smileBounds.minX + (smileWidth * 0.62)),
          Math.max(5, centerWidth * 0.32),
          0.86
        );

        for (let boundaryIndex = centerBoundaryIndex - 1; boundaryIndex >= 1; boundaryIndex -= 1) {
          const crossedTooth = visibleUpperTeeth[boundaryIndex];
          const expectedWidth = ratioUnit * ratios[crossedTooth];
          const expectedX = boundaries[boundaryIndex + 1] - expectedWidth;
          const minX = smileBounds.minX + Math.max(3, boundaryIndex * 3);
          const maxX = boundaries[boundaryIndex + 1] - Math.max(4, expectedWidth * 0.48);
          boundaries[boundaryIndex] = findBestContrastSeparator(
            separatorScores,
            expectedX,
            minX,
            maxX,
            Math.max(4, expectedWidth * 0.30),
            0.62
          );
        }

        for (let boundaryIndex = centerBoundaryIndex + 1; boundaryIndex < boundaries.length - 1; boundaryIndex += 1) {
          const crossedTooth = visibleUpperTeeth[boundaryIndex - 1];
          const expectedWidth = ratioUnit * ratios[crossedTooth];
          const expectedX = boundaries[boundaryIndex - 1] + expectedWidth;
          const minX = boundaries[boundaryIndex - 1] + Math.max(4, expectedWidth * 0.48);
          const remaining = (boundaries.length - 1) - boundaryIndex;
          const maxX = smileBounds.maxX - Math.max(3, remaining * 3);
          boundaries[boundaryIndex] = findBestContrastSeparator(
            separatorScores,
            expectedX,
            minX,
            maxX,
            Math.max(4, expectedWidth * 0.30),
            0.62
          );
        }

        for (let index = 1; index < boundaries.length; index += 1) {
          boundaries[index] = Math.round(Math.max(boundaries[index], boundaries[index - 1] + 3));
        }
        boundaries[boundaries.length - 1] = smileBounds.maxX;

        const rowHits = [];
        let peakRowHit = 0;
        let peakRowY = smileBounds.minY;
        for (let y = smileBounds.minY; y <= smileBounds.maxY; y += 1) {
          let hits = 0;
          for (let x = smileBounds.minX; x <= smileBounds.maxX; x += 1) {
            hits += softMask[y * width + x] ? 1 : 0;
          }
          rowHits[y] = hits;
          if (hits > peakRowHit) {
            peakRowHit = hits;
            peakRowY = y;
          }
        }
        let upperBandBottom = smileBounds.maxY;
        const valleyStart = peakRowY + Math.max(3, Math.round(smileHeight * 0.16));
        const valleyEnd = Math.min(smileBounds.maxY - 1, peakRowY + Math.round(smileHeight * 0.72));
        let lowestRowY = -1;
        let lowestRowHits = Number.POSITIVE_INFINITY;
        for (let y = valleyStart; y <= valleyEnd; y += 1) {
          if ((rowHits[y] || 0) < lowestRowHits) {
            lowestRowHits = rowHits[y] || 0;
            lowestRowY = y;
          }
        }
        if (lowestRowY > peakRowY && lowestRowHits <= peakRowHit * 0.28 && lowestRowY < smileBounds.maxY - 2) {
          upperBandBottom = Math.min(smileBounds.maxY, lowestRowY + 1);
        }

        const upperBand = {
          minX: smileBounds.minX,
          maxX: smileBounds.maxX,
          minY: smileBounds.minY,
          maxY: upperBandBottom
        };
        const openCvMasks = buildOpenCvToothMasks(data, mask, softMask, width, height, upperBand, boundaries);
        const slots = {};
        visibleUpperTeeth.forEach(function (toothNumber, index) {
          const leftBoundary = boundaries[index];
          const rightBoundary = boundaries[index + 1];
          if (!Number.isFinite(leftBoundary) || !Number.isFinite(rightBoundary) || rightBoundary - leftBoundary < 3) return;
          const segmentBounds = {
            minX: index === 0 ? leftBoundary : leftBoundary + 1,
            maxX: index === visibleUpperTeeth.length - 1 ? rightBoundary : rightBoundary - 1,
            minY: upperBand.minY,
            maxY: upperBand.maxY
          };
          let hitCount = 0;
          for (let x = segmentBounds.minX; x <= segmentBounds.maxX; x += 1) {
            hitCount += colHits[x] || 0;
          }
          const openCvTooth = openCvMasks && openCvMasks[toothNumber] ? openCvMasks[toothNumber] : null;
          const connectedSlotMask = openCvTooth
            ? openCvTooth.mask
            : buildConnectedToothSlotMask(mask, softMask, width, height, segmentBounds);
          const segmentWidth = Math.max(2, segmentBounds.maxX - segmentBounds.minX + 1);
          const contourBounds = {
            minX: Math.max(upperBand.minX, segmentBounds.minX - (segmentWidth * 0.42)),
            maxX: Math.min(upperBand.maxX, segmentBounds.maxX + (segmentWidth * 0.42)),
            minY: segmentBounds.minY,
            maxY: segmentBounds.maxY
          };
          const segment = buildToothSegment(connectedSlotMask, width, height, upperBand, contourBounds, hitCount);
          if (!segment || !Array.isArray(segment.contour) || segment.contour.length < 8) return;
          const exactContour = openCvTooth && openCvTooth.contour.length >= 8
            ? openCvTooth.contour
            : segment.contour;
          const exactBounds = getPointBounds(exactContour);
          slots[toothNumber] = {
            number: toothNumber,
            label: '#' + toothNumber,
            left: exactBounds.left,
            right: exactBounds.right,
            top: exactBounds.top,
            bottom: exactBounds.bottom,
            contour: exactContour,
            source: openCvTooth ? 'opencv_watershed_contour' : segment.source
          };
        });

        return Object.keys(slots).length >= 8 ? slots : buildLegacyCentralToothSlots(mask, softMask, data, width, height, smileBounds, smileHeight);
      }

      function buildLegacyCentralToothSlots(mask, softMask, data, width, height, smileBounds, smileHeight) {
        const smileWidth = smileBounds.maxX - smileBounds.minX + 1;
        if (smileWidth < 8 || smileHeight < 4) return null;
        const colHits = [];
        let peakHit = 0;
        for (let x = smileBounds.minX; x <= smileBounds.maxX; x += 1) {
          let hits = 0;
          for (let y = smileBounds.minY; y <= smileBounds.maxY; y += 1) {
            if (!softMask[y * width + x]) continue;
            const heightRatio = (y - smileBounds.minY) / Math.max(1, smileHeight);
            hits += heightRatio <= 0.55 ? 1.18 : 0.92;
          }
          colHits[x] = hits;
          peakHit = Math.max(peakHit, hits);
        }
        const smileCenter = (smileBounds.minX + smileBounds.maxX) / 2;
        const separatorScores = buildSeparatorScores(data, width, smileBounds, smileHeight);
        const valleys = findUpperToothValleys(colHits, separatorScores, smileBounds.minX, smileBounds.maxX, peakHit);
        const centerSeam = findCentralSeam(valleys, smileCenter, smileBounds.minX, smileBounds.maxX);
        const expectedCentralWidth = Math.max(7, Math.round(smileWidth * 0.105));
        const minGap = Math.max(5, Math.round(expectedCentralWidth * 0.82));
        const maxGap = Math.max(10, Math.round(expectedCentralWidth * 1.95));
        const leftFallback = Math.max(smileBounds.minX, Math.round(centerSeam.x - expectedCentralWidth));
        const rightFallback = Math.min(smileBounds.maxX, Math.round(centerSeam.x + expectedCentralWidth));
        const leftBoundary = findNeighborValley(valleys, centerSeam.x, -1, leftFallback, minGap, maxGap);
        const rightBoundary = findNeighborValley(valleys, centerSeam.x, 1, rightFallback, minGap, maxGap);
        const seamGap = Math.max(1, Math.round(expectedCentralWidth * 0.07));
        const dividerInset = Math.max(1, Math.round(expectedCentralWidth * 0.04));
        const buildSegmentBounds = function (leftCut, rightCut, fallbackCenter, fallbackWidth) {
          const safeLeft = Math.max(smileBounds.minX, Math.min(leftCut, rightCut - 2));
          const safeRight = Math.min(smileBounds.maxX, Math.max(rightCut, safeLeft + 2));
          const naturalWidth = safeRight - safeLeft + 1;
          if (naturalWidth >= Math.max(5, fallbackWidth * 0.70)) {
            return {
              minX: safeLeft,
              maxX: safeRight
            };
          }
          const halfWidth = Math.max(3, Math.round(fallbackWidth / 2));
          return {
            minX: Math.max(smileBounds.minX, Math.round(fallbackCenter - halfWidth)),
            maxX: Math.min(smileBounds.maxX, Math.round(fallbackCenter + halfWidth))
          };
        };
        const bounds8 = buildSegmentBounds(
          leftBoundary + dividerInset,
          centerSeam.x - seamGap,
          (leftBoundary + centerSeam.x) / 2,
          expectedCentralWidth
        );
        const bounds9 = buildSegmentBounds(
          centerSeam.x + seamGap,
          rightBoundary - dividerInset,
          (centerSeam.x + rightBoundary) / 2,
          expectedCentralWidth
        );
        const harmonizeCentralBounds = function (leftBounds, rightBounds) {
          const rightWidth = Math.max(1, rightBounds.maxX - rightBounds.minX + 1);
          const targetWidth = Math.max(
            Math.round(expectedCentralWidth * 0.92),
            Math.min(
              Math.round(expectedCentralWidth * 1.18),
              rightWidth
            )
          );
          const rightEdge = Math.round(centerSeam.x - seamGap);
          const leftEdge = Math.max(smileBounds.minX, rightEdge - targetWidth + 1);
          return {
            minX: leftEdge,
            maxX: rightEdge
          };
        };
        const normalizedBounds8 = harmonizeCentralBounds(bounds8, bounds9);
        const minSegmentWidth = Math.max(6, Math.round(smileWidth * 0.06));
        const boundsList = [normalizedBounds8, bounds9].filter(function (bounds) {
          return bounds.maxX > bounds.minX && (bounds.maxX - bounds.minX + 1) >= minSegmentWidth;
        });
        if (!boundsList.length) return null;
        const slots = {};
        const assignSegment = function (segment, toothNumber) {
          if (!segment || toothNumber < 4 || toothNumber > 13) return;
          slots[toothNumber] = {
            number: toothNumber,
            label: '#' + toothNumber,
            left: segment.left,
            right: segment.right,
            top: segment.top,
            bottom: segment.bottom,
            contour: segment.contour,
            source: segment.source
          };
        };
        let hitCount8 = 0;
        for (let x = normalizedBounds8.minX; x <= normalizedBounds8.maxX; x += 1) hitCount8 += colHits[x] || 0;
        let hitCount9 = 0;
        for (let x = bounds9.minX; x <= bounds9.maxX; x += 1) hitCount9 += colHits[x] || 0;
        assignSegment(buildToothSegment(mask, width, height, smileBounds, normalizedBounds8, hitCount8), 8);
        assignSegment(buildToothSegment(mask, width, height, smileBounds, bounds9, hitCount9), 9);

        const expectedLateralWidth = Math.max(6, Math.round(expectedCentralWidth * 0.82));
        const lateralMinGap = Math.max(4, Math.round(expectedLateralWidth * 0.65));
        const lateralMaxGap = Math.max(9, Math.round(expectedLateralWidth * 1.95));
        const lateralInnerGap = Math.max(1, Math.round(expectedLateralWidth * 0.10));
        const leftOuterValley = findNeighborValley(
          valleys,
          normalizedBounds8.minX,
          -1,
          Math.max(smileBounds.minX, normalizedBounds8.minX - expectedLateralWidth),
          lateralMinGap,
          lateralMaxGap
        );
        const rightOuterValley = findNeighborValley(
          valleys,
          bounds9.maxX,
          1,
          Math.min(smileBounds.maxX, bounds9.maxX + expectedLateralWidth),
          lateralMinGap,
          lateralMaxGap
        );
        const bounds7 = buildSegmentBounds(
          leftOuterValley + dividerInset,
          leftBoundary - lateralInnerGap,
          (leftOuterValley + leftBoundary) / 2,
          expectedLateralWidth
        );
        const bounds10 = buildSegmentBounds(
          rightBoundary + lateralInnerGap,
          rightOuterValley - dividerInset,
          (rightBoundary + rightOuterValley) / 2,
          expectedLateralWidth
        );
        if (bounds7.maxX > bounds7.minX && (bounds7.maxX - bounds7.minX + 1) >= Math.max(4, minSegmentWidth - 1)) {
          let hitCount7 = 0;
          for (let x = bounds7.minX; x <= bounds7.maxX; x += 1) hitCount7 += colHits[x] || 0;
          assignSegment(buildToothSegment(mask, width, height, smileBounds, bounds7, hitCount7), 7);
        }
        if (bounds10.maxX > bounds10.minX && (bounds10.maxX - bounds10.minX + 1) >= Math.max(4, minSegmentWidth - 1)) {
          let hitCount10 = 0;
          for (let x = bounds10.minX; x <= bounds10.maxX; x += 1) hitCount10 += colHits[x] || 0;
          assignSegment(buildToothSegment(mask, width, height, smileBounds, bounds10, hitCount10), 10);
        }
        return Object.keys(slots).length ? slots : null;
      }

      function buildUpperTeethMassContour(mask, width, height, smileBounds) {
        const expandedBounds = {
          minX: Math.max(0, smileBounds.minX - 2),
          maxX: Math.min(width - 1, smileBounds.maxX + 2),
          minY: Math.max(0, smileBounds.minY - 2),
          maxY: Math.min(height - 1, smileBounds.maxY + 2)
        };
        const contour = buildToothPixelContour(mask, width, height, expandedBounds);
        if (contour.length >= 8) {
          return {
            contour,
            bounds: getPointBounds(contour),
            source: 'upper_teeth_mass_contour'
          };
        }
        const fallback = pointBoundsFromPixelBounds(expandedBounds, width, height);
        return {
          contour: densifyPolygonPoints([
            { x: fallback.left, y: fallback.top },
            { x: fallback.right, y: fallback.top },
            { x: fallback.right, y: fallback.bottom },
            { x: fallback.left, y: fallback.bottom }
          ]),
          bounds: fallback,
          source: 'upper_teeth_mass_box'
        };
      }

      function detectTeethBoundsFromImage(sourceImage) {
        if (!sourceImage || !sourceImage.naturalWidth || !sourceImage.naturalHeight) {
          return null;
        }
        const scale = Math.min(1, 960 / Math.max(1, sourceImage.naturalWidth));
        const width = Math.max(1, Math.round(sourceImage.naturalWidth * scale));
        const height = Math.max(1, Math.round(sourceImage.naturalHeight * scale));
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        if (!ctx) return null;
        try {
          ctx.drawImage(sourceImage, 0, 0, width, height);
        } catch (error) {
          return null;
        }
        const data = ctx.getImageData(0, 0, width, height).data;
        const xStart = Math.round(width * 0.16);
        const xEnd = Math.round(width * 0.84);
        const yStart = Math.round(height * 0.43);
        const yEnd = Math.round(height * 0.80);
        const mask = new Uint8Array(width * height);
        const softMask = new Uint8Array(width * height);
        for (let y = yStart; y < yEnd; y += 1) {
          for (let x = xStart; x < xEnd; x += 1) {
            const offset = (y * width + x) * 4;
            const r = data[offset];
            const g = data[offset + 1];
            const b = data[offset + 2];
            const likelyTooth = isLikelyToothPixel(r, g, b);
            if (likelyTooth) {
              mask[y * width + x] = 1;
            }
            if (likelyTooth || isPossibleToothPixel(r, g, b)) {
              softMask[y * width + x] = 1;
            }
          }
        }

        const visited = new Uint8Array(width * height);
        const components = [];
        for (let y = yStart; y < yEnd; y += 1) {
          for (let x = xStart; x < xEnd; x += 1) {
            const startIndex = y * width + x;
            if (!mask[startIndex] || visited[startIndex]) continue;
            const stack = [startIndex];
            visited[startIndex] = 1;
            let count = 0;
            let sumX = 0;
            let sumY = 0;
            let minX = x;
            let maxX = x;
            let minY = y;
            let maxY = y;
            while (stack.length) {
              const current = stack.pop();
              const px = current % width;
              const py = Math.floor(current / width);
              count += 1;
              sumX += px;
              sumY += py;
              minX = Math.min(minX, px);
              maxX = Math.max(maxX, px);
              minY = Math.min(minY, py);
              maxY = Math.max(maxY, py);
              const neighbors = [current - 1, current + 1, current - width, current + width];
              neighbors.forEach(function (next) {
                if (next < 0 || next >= mask.length || visited[next] || !mask[next]) return;
                const nx = next % width;
                const ny = Math.floor(next / width);
                if (nx < xStart || nx >= xEnd || ny < yStart || ny >= yEnd) return;
                visited[next] = 1;
                stack.push(next);
              });
            }
            const componentWidth = maxX - minX + 1;
            const componentHeight = maxY - minY + 1;
            if (count < 16 || componentWidth < 3 || componentHeight < 3) continue;
            const cx = sumX / count;
            const cy = sumY / count;
            const centerBias = 1 - Math.min(0.85, Math.abs((cx / width) - 0.5) * 1.35);
            components.push({ count, minX, maxX, minY, maxY, cx, cy, score: count * centerBias });
          }
        }
        if (!components.length) return null;
        components.sort(function (a, b) { return b.score - a.score; });
        const primary = components[0];
        const verticalWindow = Math.max(14, height * 0.075, (primary.maxY - primary.minY) * 1.6);
        const selected = components.filter(function (component) {
          return Math.abs(component.cy - primary.cy) <= verticalWindow
            && component.count >= 10
            && component.maxY >= primary.minY - verticalWindow
            && component.minY <= primary.maxY + verticalWindow;
        });
        const bounds = selected.reduce(function (carry, component) {
          return {
            minX: Math.min(carry.minX, component.minX),
            maxX: Math.max(carry.maxX, component.maxX),
            minY: Math.min(carry.minY, component.minY),
            maxY: Math.max(carry.maxY, component.maxY)
          };
        }, { minX: primary.minX, maxX: primary.maxX, minY: primary.minY, maxY: primary.maxY });
        let maxRowHits = 0;
        const rowHits = [];
        for (let y = bounds.minY; y <= bounds.maxY; y += 1) {
          let hits = 0;
          for (let x = bounds.minX; x <= bounds.maxX; x += 1) {
            hits += mask[y * width + x] ? 1 : 0;
          }
          rowHits[y] = hits;
          maxRowHits = Math.max(maxRowHits, hits);
        }
        const rowThreshold = Math.max(3, Math.round(maxRowHits * 0.20));
        for (let y = bounds.minY; y <= bounds.maxY; y += 1) {
          if ((rowHits[y] || 0) >= rowThreshold) {
            bounds.minY = y;
            break;
          }
        }
        for (let y = bounds.maxY; y >= bounds.minY; y -= 1) {
          if ((rowHits[y] || 0) >= rowThreshold) {
            bounds.maxY = y;
            break;
          }
        }
        let maxColHits = 0;
        const colHits = [];
        for (let x = bounds.minX; x <= bounds.maxX; x += 1) {
          let hits = 0;
          for (let y = bounds.minY; y <= bounds.maxY; y += 1) {
            hits += mask[y * width + x] ? 1 : 0;
          }
          colHits[x] = hits;
          maxColHits = Math.max(maxColHits, hits);
        }
        const colThreshold = Math.max(2, Math.round(maxColHits * 0.14));
        for (let x = bounds.minX; x <= bounds.maxX; x += 1) {
          if ((colHits[x] || 0) >= colThreshold) {
            bounds.minX = x;
            break;
          }
        }
        for (let x = bounds.maxX; x >= bounds.minX; x -= 1) {
          if ((colHits[x] || 0) >= colThreshold) {
            bounds.maxX = x;
            break;
          }
        }
        const smileBounds = {
          minX: bounds.minX,
          maxX: bounds.maxX,
          minY: bounds.minY,
          maxY: bounds.maxY
        };
        const smileWidth = smileBounds.maxX - smileBounds.minX + 1;
        const smileHeight = smileBounds.maxY - smileBounds.minY + 1;
        if (smileWidth < width * 0.035 || smileHeight < height * 0.018) {
          return null;
        }
        const upperTeethMass = buildUpperTeethMassContour(mask, width, height, smileBounds);
        const upperToothSlots = buildVisibleUpperToothSlots(mask, softMask, data, width, height, smileBounds, smileHeight);
        if (upperToothSlots) {
          const selectedSlot = upperToothSlots[8] || upperToothSlots[9] || upperToothSlots[Number(Object.keys(upperToothSlots)[0])];
          return {
            left: upperTeethMass.bounds.left,
            right: upperTeethMass.bounds.right,
            top: upperTeethMass.bounds.top,
            bottom: upperTeethMass.bounds.bottom,
            contour: upperTeethMass.contour,
            slots: upperToothSlots,
            source: upperTeethMass.source
          };
        }
        const targetX = ((smileBounds.minX + smileBounds.maxX) / 2) - (smileWidth * 0.055);
        const targetY = (smileBounds.minY + smileBounds.maxY) / 2;
        const candidates = selected.filter(function (component) {
          return component.maxX >= smileBounds.minX
            && component.minX <= smileBounds.maxX
            && component.maxY >= smileBounds.minY
            && component.minY <= smileBounds.maxY
            && component.count >= Math.max(10, primary.count * 0.06);
        });
        candidates.sort(function (a, b) {
          const aDistance = Math.abs(a.cx - targetX) + (Math.abs(a.cy - targetY) * 0.25) - Math.min(a.count, primary.count) * 0.002;
          const bDistance = Math.abs(b.cx - targetX) + (Math.abs(b.cy - targetY) * 0.25) - Math.min(b.count, primary.count) * 0.002;
          return aDistance - bDistance;
        });
        const target = candidates[0] || primary;
        const rawTargetWidth = target.maxX - target.minX + 1;
        const isConnectedSmileBand = rawTargetWidth > (smileWidth * 0.28);
        const targetWidth = isConnectedSmileBand
          ? Math.max(smileWidth * 0.075, width * 0.020)
          : Math.max(Math.min(rawTargetWidth, smileWidth * 0.095), smileWidth * 0.065);
        const targetHeight = Math.max(target.maxY - target.minY + 1, smileHeight * 0.62);
        const targetCenterX = normalize(isConnectedSmileBand ? targetX : target.cx, smileBounds.minX, smileBounds.maxX);
        const targetCenterY = normalize(target.cy, smileBounds.minY, smileBounds.maxY);
        const singleBounds = {
          minX: Math.max(smileBounds.minX, targetCenterX - (targetWidth * 0.44)),
          maxX: Math.min(smileBounds.maxX, targetCenterX + (targetWidth * 0.44)),
          minY: Math.max(smileBounds.minY, targetCenterY - (targetHeight * 0.42)),
          maxY: Math.min(smileBounds.maxY, targetCenterY + (targetHeight * 0.46))
        };
        const contourBounds = {
          minX: Math.max(smileBounds.minX, singleBounds.minX - (targetWidth * 0.04)),
          maxX: Math.min(smileBounds.maxX, singleBounds.maxX + (targetWidth * 0.04)),
          minY: Math.max(smileBounds.minY, singleBounds.minY - (targetHeight * 0.10)),
          maxY: Math.min(smileBounds.maxY, singleBounds.maxY + (targetHeight * 0.10))
        };
        const pixelContour = buildToothPixelContour(softMask, width, height, contourBounds);
        const outputWidth = singleBounds.maxX - singleBounds.minX + 1;
        const outputHeight = singleBounds.maxY - singleBounds.minY + 1;
        return {
          left: normalize(((singleBounds.minX - (outputWidth * 0.012)) / width) * 100, 0, 100),
          right: normalize(((singleBounds.maxX + (outputWidth * 0.012)) / width) * 100, 0, 100),
          top: normalize(((singleBounds.minY - (outputHeight * 0.006)) / height) * 100, 0, 100),
          bottom: normalize(((singleBounds.maxY + (outputHeight * 0.018)) / height) * 100, 0, 100),
          contour: pixelContour,
          source: pixelContour.length ? 'single_tooth_pixel_contour' : 'single_tooth_pixels'
        };
      }

      function getDetectedTeethBounds() {
        if (detectedTeethBounds) return detectedTeethBounds;
        const detectorSource = (detectPreview && detectPreview.naturalWidth) ? detectPreview : workPreview;
        detectedTeethBounds = detectTeethBoundsFromImage(detectorSource);
        return detectedTeethBounds;
      }

      function getSmileMaskPolygon(points) {
        if (!points || points.length < 8) {
          return points;
        }
        const ordered = [
          getPointByKey(points, 'left_inner'),
          getPointByKey(points, 'upper_left'),
          getPointByKey(points, 'upper_center'),
          getPointByKey(points, 'upper_right'),
          getPointByKey(points, 'right_inner'),
          getPointByKey(points, 'lower_right'),
          getPointByKey(points, 'lower_center'),
          getPointByKey(points, 'lower_left')
        ].filter(Boolean);
        if (ordered.length < 8) {
          return points;
        }
        return [
          clampPoint(ordered[0]),
          clampPoint({
            x: ordered[0].x + ((ordered[1].x - ordered[0].x) * 0.55),
            y: ordered[0].y + ((ordered[1].y - ordered[0].y) * 0.55)
          }),
          clampPoint(ordered[1]),
          clampPoint({
            x: ordered[1].x + ((ordered[2].x - ordered[1].x) * 0.5),
            y: ordered[1].y + ((ordered[2].y - ordered[1].y) * 0.5)
          }),
          clampPoint(ordered[2]),
          clampPoint({
            x: ordered[2].x + ((ordered[3].x - ordered[2].x) * 0.5),
            y: ordered[2].y + ((ordered[3].y - ordered[2].y) * 0.5)
          }),
          clampPoint(ordered[3]),
          clampPoint({
            x: ordered[3].x + ((ordered[4].x - ordered[3].x) * 0.55),
            y: ordered[3].y + ((ordered[4].y - ordered[3].y) * 0.55)
          }),
          clampPoint(ordered[4]),
          clampPoint(ordered[5]),
          clampPoint(ordered[6]),
          clampPoint(ordered[7])
        ];
      }

      function applyAnchorDrag(points, key, nextPoint) {
        const keyed = keyedAnchors(points);
        if (!keyed[key]) return;
        const leftX = Math.min(keyed.left_inner.x, keyed.right_inner.x - 4);
        const rightX = Math.max(keyed.right_inner.x, keyed.left_inner.x + 4);
        const topY = Math.min(keyed.upper_left.y, keyed.lower_center.y - 3);
        const bottomY = Math.max(keyed.lower_center.y, keyed.upper_center.y + 3);
        switch (key) {
          case 'left_inner':
            keyed.left_inner.x = normalize(Math.min(nextPoint.x, rightX - 4), 0, 100);
            keyed.left_inner.y = keyed.left_inner.y;
            keyed.upper_left.x = normalize(Math.min(keyed.upper_left.x, keyed.right_inner.x - 4), 0, 100);
            keyed.lower_left.x = normalize(Math.min(keyed.lower_left.x, keyed.right_inner.x - 4), 0, 100);
            break;
          case 'right_inner':
            keyed.right_inner.x = normalize(Math.max(nextPoint.x, leftX + 4), 0, 100);
            break;
          case 'upper_center':
            keyed.upper_center.y = normalize(Math.min(nextPoint.y, bottomY - 3), 0, 100);
            break;
          case 'lower_center':
            keyed.lower_center.y = normalize(Math.max(nextPoint.y, topY + 3), 0, 100);
            break;
          case 'upper_right':
            keyed.upper_right.x = normalize(Math.max(nextPoint.x, keyed.upper_center.x + 1), 0, 100);
            keyed.upper_right.y = normalize(Math.min(nextPoint.y, keyed.lower_right.y - 2), 0, 100);
            break;
          case 'upper_left':
            keyed.upper_left.x = normalize(Math.min(nextPoint.x, keyed.upper_center.x - 1), 0, 100);
            keyed.upper_left.y = normalize(Math.min(nextPoint.y, keyed.lower_left.y - 2), 0, 100);
            break;
          case 'lower_left':
            keyed.lower_left.x = normalize(Math.min(nextPoint.x, keyed.lower_center.x - 1), 0, 100);
            keyed.lower_left.y = normalize(Math.max(nextPoint.y, keyed.upper_left.y + 2), 0, 100);
            break;
          case 'lower_right':
            keyed.lower_right.x = normalize(Math.max(nextPoint.x, keyed.lower_center.x + 1), 0, 100);
            keyed.lower_right.y = normalize(Math.max(nextPoint.y, keyed.upper_right.y + 2), 0, 100);
            break;
          default:
            break;
        }
        if (key === 'left_inner' || key === 'right_inner') {
          const widthLeft = keyed.upper_left.x - leftX;
          const widthLowerLeft = keyed.lower_left.x - leftX;
          const widthRight = rightX - keyed.upper_right.x;
          const widthLowerRight = rightX - keyed.lower_right.x;
          keyed.upper_left.x = normalize(keyed.left_inner.x + widthLeft, 0, 100);
          keyed.lower_left.x = normalize(keyed.left_inner.x + widthLowerLeft, 0, 100);
          keyed.upper_right.x = normalize(keyed.right_inner.x - widthRight, 0, 100);
          keyed.lower_right.x = normalize(keyed.right_inner.x - widthLowerRight, 0, 100);
          keyed.upper_center.x = normalize((keyed.left_inner.x + keyed.right_inner.x) / 2, 0, 100);
          keyed.lower_center.x = normalize((keyed.left_inner.x + keyed.right_inner.x) / 2, 0, 100);
        }
      }

      function svgPoints(points) {
        return points.map(function (point) {
          const displayPoint = imagePointToDisplay(point);
          return {
            x: (displayPoint.x * 100) / Math.max(1, overlay.clientWidth),
            y: (displayPoint.y * 100) / Math.max(1, overlay.clientHeight)
          };
        }).map(function (point) {
          return point.x + ',' + point.y;
        }).join(' ');
      }

      function updateRefineToggle() {
        if (konvaHolder) {
          konvaHolder.classList.remove('refine');
        }
      }

      function getContourDetail() {
        return 72;
      }

      function updateMaskSettingLabels() {
        const size = getBrushSize();
        if (brushSizeRange && String(brushSizeRange.value) !== String(size)) {
          brushSizeRange.value = String(size);
        }
        if (brushSizeValue) brushSizeValue.textContent = String(size);
        if (brushSizePreview) {
          brushSizePreview.style.width = size + 'px';
          brushSizePreview.style.height = size + 'px';
        }
      }

      function getBrushSize() {
        return Math.round(normalize(brushSizeRange ? brushSizeRange.value : 12, 2, 20));
      }

      function ensureBrushLayer() {
        if (!brushLayer || !brushCanvas) return false;
        const imageRect = getDisplayImageRect();
        const width = Math.max(1, Math.round(imageRect.width));
        const height = Math.max(1, Math.round(imageRect.height));
        brushLayer.style.left = imageRect.left + 'px';
        brushLayer.style.top = imageRect.top + 'px';
        brushLayer.style.width = width + 'px';
        brushLayer.style.height = height + 'px';
        if (brushCanvas.width !== width || brushCanvas.height !== height) {
          const previous = brushCanvas.width > 0 && brushCanvas.height > 0 ? brushCanvas.toDataURL('image/png') : '';
          brushCanvas.width = width;
          brushCanvas.height = height;
          brushHistory = [];
          brushContext = brushCanvas.getContext('2d');
          if (previous) {
            const img = new Image();
            img.onload = function () {
              if (brushContext) brushContext.drawImage(img, 0, 0, width, height);
            };
            img.src = previous;
          }
        } else if (!brushContext) {
          brushContext = brushCanvas.getContext('2d');
        }
        return !!brushContext;
      }

      function updateBrushUi() {
        const brushActive = editorMode === 'manual' && brushMode;
        if (brushLayer) {
          brushLayer.classList.toggle('active', brushActive);
          brushLayer.style.display = brushActive ? 'block' : 'none';
          brushLayer.style.pointerEvents = brushActive ? 'auto' : 'none';
        }
        if (brushCanvas) {
          brushCanvas.style.pointerEvents = brushActive ? 'auto' : 'none';
          brushCanvas.style.touchAction = 'none';
          brushCanvas.style.cursor = brushEraseMode ? 'cell' : 'crosshair';
        }
        if (workPreview) {
          workPreview.style.pointerEvents = brushActive ? 'none' : 'auto';
        }
        if (konvaHolder) {
          konvaHolder.style.pointerEvents = 'none';
        }
        if (brushEnableButton) brushEnableButton.textContent = brushActive ? 'Fallback Paint On' : 'Enable Fallback Paint';
        if (brushEraseToggle) brushEraseToggle.textContent = brushEraseMode ? 'Erase On' : 'Erase';
        if (brushDoneButton) brushDoneButton.textContent = brushActive ? 'Use Fallback' : 'Apply Fallback';
        if (brushUndoButton) brushUndoButton.disabled = editorMode !== 'manual' || brushHistory.length === 0;
      }

      function updateModeUi() {
        if (automaticModeButton) {
          automaticModeButton.className = editorMode === 'automatic'
            ? 'inline-flex items-center justify-center rounded-full border border-sky-300/30 bg-sky-400/10 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-sky-100'
            : 'inline-flex items-center justify-center rounded-full border border-white/15 bg-white/10 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-white/88';
        }
        if (manualModeButton) {
          manualModeButton.className = editorMode === 'manual'
            ? 'inline-flex items-center justify-center rounded-full border border-sky-300/30 bg-sky-400/10 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-sky-100'
            : 'inline-flex items-center justify-center rounded-full border border-white/15 bg-white/10 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-white/88';
        }
        if (modeHelp) {
          modeHelp.textContent = editorMode === 'automatic'
            ? 'Click a tooth to select only that tooth. Hold Ctrl, Cmd, or Shift while clicking to select multiple teeth.'
            : 'Manual mode starts with a tooth click seed. Use paint only if the click needs cleanup.';
        }
        if (brushEnableButton) brushEnableButton.disabled = editorMode !== 'manual';
        if (brushEraseToggle) brushEraseToggle.disabled = editorMode !== 'manual';
        if (brushClearButton) brushClearButton.disabled = editorMode !== 'manual';
        if (brushDoneButton) brushDoneButton.disabled = editorMode !== 'manual';
        if (brushUndoButton) brushUndoButton.disabled = editorMode !== 'manual' || brushHistory.length === 0;
        if (brushSizeRange) brushSizeRange.disabled = editorMode !== 'manual';
        updateToothMapUi();
      }

      function setEditorMode(nextMode) {
        editorMode = nextMode === 'manual' ? 'manual' : 'automatic';
        writeEditorMode(editorMode);
        if (editorMode === 'automatic') {
          brushMode = false;
          brushEraseMode = false;
          clearBrushSelection();
        } else {
          clearBrushSelection();
          brushMode = false;
          brushEraseMode = false;
        }
        updateBrushUi();
        updateModeUi();
        render(readAnchorPoints());
      }

      function polygonPointsAttribute(points) {
        return (points || []).map(function (point) {
          return point.x + ',' + point.y;
        }).join(' ');
      }

      function detectSingleToothRegion(anchorPoints, contourPoints, seedPoint, toothNumberOverride) {
        const toothNumber = normalizeToothNumber(toothNumberOverride) || getSelectedToothNumber();
        const teethBounds = getDetectedTeethBounds();
        const slotBounds = teethBounds && teethBounds.slots ? teethBounds.slots[toothNumber] : null;
        if (slotBounds && Array.isArray(slotBounds.contour) && slotBounds.contour.length >= 8) {
          return {
            number: toothNumber,
            label: '#' + toothNumber,
            polygon: densifyPolygonPoints(slotBounds.contour),
            source: slotBounds.source || 'upper_tooth_slot_contour'
          };
        }
        let sourceBounds = slotBounds || teethBounds || getPointBounds(contourPoints && contourPoints.length ? contourPoints : anchorPoints);
        const mouthReferencePoints = contourPoints && contourPoints.length
          ? contourPoints
          : (anchorPoints && anchorPoints.length ? getSmileMaskPolygon(anchorPoints) : []);
        const mouthReferenceBounds = mouthReferencePoints.length ? getPointBounds(mouthReferencePoints) : null;
        const expectedToothBounds = getExpectedVisibleToothBounds(toothNumber, mouthReferenceBounds, sourceBounds);
        const autoSeedPoint = seedPoint || (expectedToothBounds ? {
          x: (expectedToothBounds.left + expectedToothBounds.right) / 2,
          y: (expectedToothBounds.top + expectedToothBounds.bottom) / 2
        } : null);
        const seededSelection = detectSingleToothRegionFromSeed((detectPreview && detectPreview.naturalWidth) ? detectPreview : workPreview, autoSeedPoint, toothNumber, mouthReferenceBounds, sourceBounds, {
          detail: getContourDetail()
        });
        if (seededSelection && Array.isArray(seededSelection.polygon) && seededSelection.polygon.length) {
          return seededSelection;
        }
        const expectedWidth = expectedToothBounds ? Math.max(1, expectedToothBounds.right - expectedToothBounds.left) : 0;
        const sourceWidthBeforeGuard = Math.max(1, sourceBounds.right - sourceBounds.left);
        const sourceCenterXBeforeGuard = (sourceBounds.left + sourceBounds.right) / 2;
        const expectedCenterX = expectedToothBounds ? (expectedToothBounds.left + expectedToothBounds.right) / 2 : sourceCenterXBeforeGuard;
        const sourceFitsExpectedTooth = !expectedToothBounds
          || (
            sourceWidthBeforeGuard <= (expectedWidth * 1.70)
            && Math.abs(sourceCenterXBeforeGuard - expectedCenterX) <= (expectedWidth * 0.95)
          );
        const hasPixelContour = sourceBounds
          && Array.isArray(sourceBounds.contour)
          && sourceBounds.contour.length >= 8
            && (
              sourceBounds.source === 'single_tooth_pixel_contour'
              || sourceBounds.source === 'upper_tooth_slot_contour'
              || sourceBounds.source === 'upper_tooth_slot_fallback'
            )
          && sourceFitsExpectedTooth;
        if (hasPixelContour) {
          return {
            number: toothNumber,
            label: '#' + toothNumber,
            polygon: densifyPolygonPoints(sourceBounds.contour),
            source: sourceBounds.source
          };
        }
        if (expectedToothBounds && !sourceFitsExpectedTooth) {
          sourceBounds = expectedToothBounds;
        }
        const isSingleToothBounds = Boolean(slotBounds) || (teethBounds
          && (teethBounds.source === 'single_tooth_pixels' || teethBounds.source === 'single_tooth_pixel_contour'));
        const sourceWidth = Math.max(1, sourceBounds.right - sourceBounds.left);
        const sourceHeight = Math.max(1, sourceBounds.bottom - sourceBounds.top);
        const width = isSingleToothBounds
          ? Math.max(3.2, Math.min(sourceWidth * 0.98, 6.2))
          : (teethBounds ? Math.max(3.4, sourceWidth * 0.14) : Math.max(6, sourceWidth * 0.15));
        const height = isSingleToothBounds
          ? Math.max(4.5, sourceHeight * 1.02)
          : (teethBounds ? Math.max(4.4, sourceHeight * 1.02) : Math.max(7, sourceHeight * 0.92));
        const centerX = isSingleToothBounds
          ? (sourceBounds.left + sourceBounds.right) / 2
          : ((sourceBounds.left + sourceBounds.right) / 2) - (sourceWidth * (teethBounds ? 0.055 : 0.085));
        const top = isSingleToothBounds
          ? normalize(((sourceBounds.top + sourceBounds.bottom) / 2) - (height / 2), 0, 100)
          : (teethBounds
          ? normalize(sourceBounds.top - (sourceHeight * 0.015), 0, 100)
          : sourceBounds.top + (sourceHeight * 0.04));
        const bottom = Math.min(100, top + height);
        const left = centerX - (width / 2);
        const right = centerX + (width / 2);
        const midX = centerX;
        const shoulder = width * 0.18;
        const baseInset = width * 0.08;
        const buildCentralIncisorFallback = function (variant) {
          const isTooth9 = variant === 9;
          const crownInset = width * 0.13;
          const topLift = height * 0.10;
          const gumDip = width * (isTooth9 ? 0.07 : 0.08);
          const sideBulge = width * 0.020;
          const lowerCurve = height * 0.06;
          return {
            number: variant,
            label: '#' + variant,
            polygon: densifyPolygonPoints([
              clampPoint({ x: left + crownInset, y: top + topLift }),
              clampPoint({ x: midX - (width * 0.18), y: top }),
              clampPoint({ x: midX - gumDip, y: top + (topLift * 0.45) }),
              clampPoint({ x: midX + gumDip, y: top + (topLift * 0.45) }),
              clampPoint({ x: midX + (width * 0.18), y: top }),
              clampPoint({ x: right - crownInset, y: top + topLift }),
              clampPoint({ x: right + sideBulge, y: top + (height * 0.30) }),
              clampPoint({ x: right, y: bottom - (height * 0.16) }),
              clampPoint({ x: right - (width * 0.045), y: bottom - lowerCurve }),
              clampPoint({ x: midX + (width * 0.16), y: bottom }),
              clampPoint({ x: midX, y: bottom + (height * 0.008) }),
              clampPoint({ x: midX - (width * 0.16), y: bottom }),
              clampPoint({ x: left + (width * 0.045), y: bottom - lowerCurve }),
              clampPoint({ x: left, y: bottom - (height * 0.16) }),
              clampPoint({ x: left - sideBulge, y: top + (height * 0.30) })
            ]),
            source: 'central_incisor_fallback'
          };
        };
        const buildTooth8Fallback = function () {
          const crownInset = width * 0.16;
          const topLift = height * 0.11;
          const seamDip = width * 0.050;
          const outerDip = width * 0.120;
          const outerBulge = width * 0.018;
          const innerBulge = width * 0.010;
          const lowerCurve = height * 0.055;
          const centerBias = width * 0.018;
          return {
            number: 8,
            label: '#8',
            polygon: densifyPolygonPoints([
              clampPoint({ x: left + crownInset, y: top + (topLift * 1.08) }),
              clampPoint({ x: (midX - centerBias) - (width * 0.15), y: top + (topLift * 0.15) }),
              clampPoint({ x: (midX - centerBias) - outerDip, y: top }),
              clampPoint({ x: (midX - centerBias) + seamDip, y: top + (topLift * 0.48) }),
              clampPoint({ x: (midX - centerBias) + (width * 0.145), y: top + (topLift * 0.24) }),
              clampPoint({ x: right - crownInset, y: top + (topLift * 0.98) }),
              clampPoint({ x: right + innerBulge, y: top + (height * 0.27) }),
              clampPoint({ x: right, y: bottom - (height * 0.13) }),
              clampPoint({ x: right - (width * 0.032), y: bottom - lowerCurve }),
              clampPoint({ x: (midX - centerBias) + (width * 0.108), y: bottom - (height * 0.004) }),
              clampPoint({ x: (midX - centerBias) - (width * 0.008), y: bottom + (height * 0.004) }),
              clampPoint({ x: (midX - centerBias) - (width * 0.132), y: bottom - (height * 0.010) }),
              clampPoint({ x: left + (width * 0.046), y: bottom - lowerCurve }),
              clampPoint({ x: left, y: bottom - (height * 0.17) }),
              clampPoint({ x: left - outerBulge, y: top + (height * 0.31) })
            ]),
            source: 'tooth_8_fallback'
          };
        };
        if (isSingleToothBounds) {
          if (toothNumber === 8) {
            return buildTooth8Fallback();
          }
          if (toothNumber === 9) {
            return buildCentralIncisorFallback(9);
          }
          const crownInset = width * 0.08;
          const sideBulge = width * 0.025;
          return {
            number: toothNumber,
            label: '#' + toothNumber,
            polygon: densifyPolygonPoints([
              clampPoint({ x: left + crownInset, y: top + (height * 0.02) }),
              clampPoint({ x: midX - (width * 0.18), y: top }),
              clampPoint({ x: midX + (width * 0.18), y: top }),
              clampPoint({ x: right - crownInset, y: top + (height * 0.02) }),
              clampPoint({ x: right + sideBulge, y: top + (height * 0.30) }),
              clampPoint({ x: right, y: top + (height * 0.66) }),
              clampPoint({ x: right - (crownInset * 0.55), y: bottom - (height * 0.06) }),
              clampPoint({ x: midX + (width * 0.17), y: bottom }),
              clampPoint({ x: midX, y: bottom + (height * 0.015) }),
              clampPoint({ x: midX - (width * 0.17), y: bottom }),
              clampPoint({ x: left + (crownInset * 0.55), y: bottom - (height * 0.06) }),
              clampPoint({ x: left, y: top + (height * 0.66) }),
              clampPoint({ x: left - sideBulge, y: top + (height * 0.30) })
            ]),
            source: 'single_tooth_geometry'
          };
        }
        return {
          number: toothNumber,
          label: '#' + toothNumber,
          polygon: densifyPolygonPoints([
            clampPoint({ x: left + shoulder, y: top }),
            clampPoint({ x: left + (width * 0.32), y: top - 0.24 }),
            clampPoint({ x: midX - (width * 0.10), y: top - 0.3 }),
            clampPoint({ x: midX + (width * 0.10), y: top - 0.3 }),
            clampPoint({ x: right - (width * 0.32), y: top - 0.24 }),
            clampPoint({ x: right - shoulder, y: top }),
            clampPoint({ x: right, y: top + (height * 0.30) }),
            clampPoint({ x: right - (width * 0.02), y: top + (height * 0.58) }),
            clampPoint({ x: right - baseInset, y: bottom - (height * 0.08) }),
            clampPoint({ x: midX + (width * 0.12), y: bottom }),
            clampPoint({ x: midX, y: bottom + 0.18 }),
            clampPoint({ x: midX - (width * 0.12), y: bottom }),
            clampPoint({ x: left + baseInset, y: bottom - (height * 0.08) }),
            clampPoint({ x: left + (width * 0.02), y: top + (height * 0.58) }),
            clampPoint({ x: left, y: top + (height * 0.30) })
          ]),
          source: 'single_tooth_geometry'
        };
      }

      function buildSelectedToothSelections(anchorPoints, contourPoints) {
        return getSelectedTeethArray().map(function (toothNumber) {
          const selection = detectSingleToothRegion(
            anchorPoints,
            contourPoints,
            toothSeedPoints[toothNumber] || null,
            toothNumber
          );
          return applyPrecisionToToothSelection(selection);
        }).filter(function (selection) {
          return selection && Array.isArray(selection.polygon) && selection.polygon.length;
        });
      }

      function smoothClosedToothContour(points, strength) {
        const amount = normalize(Number(strength || 0), 0, 100);
        if (!Array.isArray(points) || points.length < 5 || amount <= 0) {
          return Array.isArray(points) ? points : [];
        }
        const cleaned = [];
        points.forEach(function (point) {
          const previous = cleaned.length ? cleaned[cleaned.length - 1] : null;
          if (previous && Math.abs(previous.x - point.x) < 0.015 && Math.abs(previous.y - point.y) < 0.015) return;
          cleaned.push({ x: point.x, y: point.y });
        });
        if (cleaned.length < 5) return points;

        const resampleClosed = function (source, targetCount) {
          const segments = [];
          let perimeter = 0;
          for (let index = 0; index < source.length; index += 1) {
            const start = source[index];
            const end = source[(index + 1) % source.length];
            const length = Math.hypot(end.x - start.x, end.y - start.y);
            segments.push({ start, end, length, from: perimeter });
            perimeter += length;
          }
          if (perimeter <= 0) return source;
          const output = [];
          let segmentIndex = 0;
          for (let sample = 0; sample < targetCount; sample += 1) {
            const distance = (sample / targetCount) * perimeter;
            while (segmentIndex < segments.length - 1 && distance > segments[segmentIndex].from + segments[segmentIndex].length) {
              segmentIndex += 1;
            }
            const segment = segments[segmentIndex];
            const ratio = segment.length > 0 ? normalize((distance - segment.from) / segment.length, 0, 1) : 0;
            output.push(clampPoint({
              x: segment.start.x + ((segment.end.x - segment.start.x) * ratio),
              y: segment.start.y + ((segment.end.y - segment.start.y) * ratio)
            }));
          }
          return output;
        };

        const sampleCount = Math.min(64, Math.max(32, Math.round(cleaned.length * 0.42)));
        const baseline = resampleClosed(cleaned, sampleCount);
        const passes = Math.min(4, 1 + Math.floor(amount / 28));
        let curved = baseline;
        for (let pass = 0; pass < passes; pass += 1) {
          const next = [];
          for (let index = 0; index < curved.length; index += 1) {
            const current = curved[index];
            const following = curved[(index + 1) % curved.length];
            next.push(clampPoint({
              x: (current.x * 0.75) + (following.x * 0.25),
              y: (current.y * 0.75) + (following.y * 0.25)
            }));
            next.push(clampPoint({
              x: (current.x * 0.25) + (following.x * 0.75),
              y: (current.y * 0.25) + (following.y * 0.75)
            }));
          }
          curved = resampleClosed(next, sampleCount);
        }

        let closestIndex = 0;
        let closestDistance = Number.POSITIVE_INFINITY;
        curved.forEach(function (point, index) {
          const distance = Math.hypot(point.x - baseline[0].x, point.y - baseline[0].y);
          if (distance < closestDistance) {
            closestDistance = distance;
            closestIndex = index;
          }
        });
        curved = curved.slice(closestIndex).concat(curved.slice(0, closestIndex));

        const blend = 0.35 + ((amount / 100) * 0.65);
        return baseline.map(function (point, index) {
          const smoothPoint = curved[index] || point;
          return clampPoint({
            x: point.x + ((smoothPoint.x - point.x) * blend),
            y: point.y + ((smoothPoint.y - point.y) * blend)
          });
        });
      }

      function prepareActiveToothForEdgeEditing() {
        const toothNumber = getSelectedToothNumber();
        if (!Array.isArray(toothContourOverrides[toothNumber]) && autoToothSelection && Array.isArray(autoToothSelection.polygon)) {
          toothContourOverrides[toothNumber] = clonePlainPoints(autoToothSelection.polygon);
          toothPrecisionAdjustments[toothNumber] = {
            shape_scale_delta: 0,
            smile_length_delta: 0,
            smile_width_delta: 0,
            edge_smoothing: 0
          };
          delete toothManualOffsets[toothNumber];
          writeToothOffsets();
          writeToothAdjustments();
          syncPrecisionControlsForActiveTooth();
        }
        return toothNumber;
      }

      function saveToothEdgeHistory(toothNumber) {
        if (!toothContourHistory[toothNumber]) toothContourHistory[toothNumber] = [];
        const current = Array.isArray(toothContourOverrides[toothNumber])
          ? clonePlainPoints(toothContourOverrides[toothNumber])
          : null;
        toothContourHistory[toothNumber].push(current);
        if (toothContourHistory[toothNumber].length > 30) toothContourHistory[toothNumber].shift();
      }

      function undoActiveToothEdge() {
        const toothNumber = getSelectedToothNumber();
        const history = toothContourHistory[toothNumber] || [];
        if (!history.length) return false;
        const previous = history.pop();
        if (Array.isArray(previous)) {
          toothContourOverrides[toothNumber] = previous;
        } else {
          delete toothContourOverrides[toothNumber];
        }
        render(baseAnchorPoints.length ? clonePoints(baseAnchorPoints) : readAnchorPoints());
        return true;
      }

      function updateToothEdgeCursor(event) {
        if (!toothEdgeCursor || !workFrame || !toothEdgeTool || !event) {
          if (toothEdgeCursor) toothEdgeCursor.classList.add('hidden');
          return;
        }
        const imageRect = getVisibleImageRect();
        const frameRect = workFrame.getBoundingClientRect();
        const clientX = Number(event.clientX || 0);
        const clientY = Number(event.clientY || 0);
        const inside = clientX >= imageRect.left && clientX <= imageRect.right
          && clientY >= imageRect.top && clientY <= imageRect.bottom;
        if (!inside) {
          toothEdgeCursor.classList.add('hidden');
          return;
        }
        const size = toothEdgeSizeRange ? Number(toothEdgeSizeRange.value || 10) : 10;
        toothEdgeCursor.style.width = size + 'px';
        toothEdgeCursor.style.height = size + 'px';
        toothEdgeCursor.style.left = (clientX - frameRect.left - (size / 2)) + 'px';
        toothEdgeCursor.style.top = (clientY - frameRect.top - (size / 2)) + 'px';
        toothEdgeCursor.classList.remove('hidden');
      }

      function applyToothEdgeBrush(clientX, clientY) {
        const toothNumber = getSelectedToothNumber();
        const contour = toothContourOverrides[toothNumber];
        if (!Array.isArray(contour) || contour.length < 5 || !toothEdgeTool) return;
        const imageRect = getVisibleImageRect();
        if (!imageRect || imageRect.width <= 0 || imageRect.height <= 0) return;
        const brushPixels = toothEdgeSizeRange ? Number(toothEdgeSizeRange.value || 10) : 10;
        const radiusX = Math.max(0.15, (brushPixels / imageRect.width) * 100);
        const radiusY = Math.max(0.15, (brushPixels / imageRect.height) * 100);
        const target = displayPointToImage(clientX, clientY);
        const contourCenter = centroid(contour);
        const source = clonePlainPoints(contour);
        const next = source.map(function (point, index) {
          const dx = (point.x - target.x) / radiusX;
          const dy = (point.y - target.y) / radiusY;
          const distance = Math.sqrt((dx * dx) + (dy * dy));
          if (distance > 1.35) return point;
          const influence = Math.pow(1 - normalize(distance / 1.35, 0, 1), 1.4);
          if (toothEdgeTool === 'smooth') {
            const previous = source[(index - 2 + source.length) % source.length];
            const nextPoint = source[(index + 2) % source.length];
            const averageX = (previous.x + point.x + nextPoint.x) / 3;
            const averageY = (previous.y + point.y + nextPoint.y) / 3;
            return clampPoint({
              x: point.x + ((averageX - point.x) * influence * 0.72),
              y: point.y + ((averageY - point.y) * influence * 0.72)
            });
          }
          return clampPoint({
            x: point.x + ((contourCenter.x - point.x) * influence * 0.34),
            y: point.y + ((contourCenter.y - point.y) * influence * 0.34)
          });
        });
        toothContourOverrides[toothNumber] = next;
        render(baseAnchorPoints.length ? clonePoints(baseAnchorPoints) : readAnchorPoints());
      }

      function setToothEdgeTool(tool) {
        toothEdgeTool = tool === 'erase' || tool === 'smooth' ? tool : '';
        draggingTooth = null;
        toothEdgeDrawing = false;
        if (toothSelectLayer) toothSelectLayer.style.cursor = toothEdgeTool ? 'none' : '';
        if (!toothEdgeTool && toothEdgeCursor) toothEdgeCursor.classList.add('hidden');
        document.body.dataset.toothEdgeTool = toothEdgeTool;
        if (toothEdgeEraserButton) {
          toothEdgeEraserButton.classList.toggle('bg-rose-400/30', toothEdgeTool === 'erase');
          toothEdgeEraserButton.classList.toggle('border-rose-200/50', toothEdgeTool === 'erase');
          toothEdgeEraserButton.setAttribute('aria-pressed', toothEdgeTool === 'erase' ? 'true' : 'false');
        }
        if (toothEdgeSmootherButton) {
          toothEdgeSmootherButton.classList.toggle('bg-rose-400/30', toothEdgeTool === 'smooth');
          toothEdgeSmootherButton.classList.toggle('border-rose-200/50', toothEdgeTool === 'smooth');
          toothEdgeSmootherButton.setAttribute('aria-pressed', toothEdgeTool === 'smooth' ? 'true' : 'false');
        }
      }

      function applyPrecisionToToothSelection(selection) {
        if (!selection || !Array.isArray(selection.polygon) || selection.polygon.length < 3) {
          return selection;
        }
        const toothNumber = Number(selection.number || 0);
        const teethBounds = getDetectedTeethBounds();
        const contourOverride = Array.isArray(toothContourOverrides[toothNumber]) ? toothContourOverrides[toothNumber] : null;
        let sourcePolygon = (contourOverride || selection.polygon).map(function (point) {
          return { x: point.x, y: point.y };
        });
        const neighborSlot = teethBounds && teethBounds.slots
          ? (toothNumber === 4 ? teethBounds.slots[5] : (toothNumber === 13 ? teethBounds.slots[12] : null))
          : null;
        if (!contourOverride && neighborSlot && Array.isArray(neighborSlot.contour) && neighborSlot.contour.length >= 8) {
          sourcePolygon = neighborSlot.contour.map(function (point) {
            return { x: point.x, y: point.y };
          });
        }
        const originalBounds = getPointBounds(sourcePolygon);
        if (!contourOverride && neighborSlot && (toothNumber === 4 || toothNumber === 13)) {
          const originalWidth = Math.max(0.2, originalBounds.right - originalBounds.left);
          const neighborWidth = Math.max(0.8, neighborSlot.right - neighborSlot.left);
          const targetWidth = Math.max(0.9, neighborWidth * 0.86);
          const targetLeft = toothNumber === 4
            ? neighborSlot.left - targetWidth
            : neighborSlot.right - 0.05;
          const targetRight = toothNumber === 4
            ? neighborSlot.left + 0.05
            : neighborSlot.right + targetWidth;
          sourcePolygon = sourcePolygon.map(function (point) {
            const xRatio = normalize((point.x - originalBounds.left) / originalWidth, 0, 1);
            return { x: targetLeft + ((targetRight - targetLeft) * xRatio), y: point.y };
          });
        }
        const toothAdjustment = getToothAdjustment(toothNumber);
        const shapeDelta = toothAdjustment.shape_scale_delta / 100;
        const lengthDelta = toothAdjustment.smile_length_delta / 100;
        const widthDelta = toothAdjustment.smile_width_delta / 100;
        const bounds = getPointBounds(sourcePolygon);
        const centerX = (bounds.left + bounds.right) / 2;
        const centerY = (bounds.top + bounds.bottom) / 2;
        const toothHeight = Math.max(0.2, bounds.bottom - bounds.top);
        const archLeft = teethBounds && Number.isFinite(teethBounds.left) ? teethBounds.left : 35;
        const archRight = teethBounds && Number.isFinite(teethBounds.right) ? teethBounds.right : 65;
        const archWidth = Math.max(8, archRight - archLeft);
        const slot8 = teethBounds && teethBounds.slots ? teethBounds.slots[8] : null;
        const slot9 = teethBounds && teethBounds.slots ? teethBounds.slots[9] : null;
        const smileCenter = slot8 && slot9
          ? ((slot8.right + slot9.left) / 2)
          : ((archLeft + archRight) / 2);
        const sideDirection = toothNumber <= 8 ? -1 : 1;
        const distanceRatio = normalize(Math.abs(centerX - smileCenter) / Math.max(1, archWidth / 2), 0, 1);
        const posteriorEmphasis = toothNumber === 4 || toothNumber === 13 ? 1 : Math.max(0.18, distanceRatio);
        const outwardShift = widthDelta * archWidth * 0.045 * posteriorEmphasis * sideDirection;
        const widthScale = Math.max(0.55, 1 + (widthDelta * 0.72) + (shapeDelta * 0.24));
        const heightScale = Math.max(0.60, 1 + (shapeDelta * 0.34));

        const polygon = sourcePolygon.map(function (point) {
          const relativeY = (point.y - bounds.top) / toothHeight;
          const scaledX = centerX + ((point.x - centerX) * widthScale) + outwardShift;
          const shapeY = centerY + ((point.y - centerY) * heightScale);
          const lengthY = shapeY + (toothHeight * lengthDelta * 0.70 * Math.pow(normalize(relativeY, 0, 1), 1.35));
          return clampPoint({ x: scaledX, y: lengthY });
        });
        const manualOffset = toothManualOffsets[toothNumber] || { x: 0, y: 0 };
        const positionedPolygon = polygon.map(function (point) {
          return clampPoint({
            x: point.x + Number(manualOffset.x || 0),
            y: point.y + Number(manualOffset.y || 0)
          });
        });
        const smoothedPolygon = smoothClosedToothContour(positionedPolygon, toothAdjustment.edge_smoothing);

        return Object.assign({}, selection, {
          polygon: densifyPolygonPoints(smoothedPolygon),
          source: (selection.source || 'tooth_contour') + '_precision'
        });
      }

      function renderAutoToothSelection(anchorPoints, contourPoints, selections) {
        if (!toothSelectLayer) return;
        const imageRect = getDisplayImageRect();
        toothSelectLayer.style.left = imageRect.left + 'px';
        toothSelectLayer.style.top = imageRect.top + 'px';
        toothSelectLayer.style.width = imageRect.width + 'px';
        toothSelectLayer.style.height = imageRect.height + 'px';
        toothSelectLayer.style.right = 'auto';
        toothSelectLayer.style.bottom = 'auto';
        const showSelection = editorMode === 'automatic' || editorMode === 'manual';
        toothSelectLayer.classList.toggle('active', showSelection);
        toothSelectLayer.style.display = showSelection ? 'block' : 'none';
        toothSelectLayer.style.pointerEvents = editorMode === 'automatic' ? 'auto' : 'none';
        const selectedMarkup = (Array.isArray(selections) ? selections : []).map(function (selection) {
          const isCurrent = selection.number === getSelectedToothNumber();
          return ''
            + '<g data-selected-tooth="' + selection.number + '">'
            + '<polygon data-selected-tooth="' + selection.number + '" points="' + polygonPointsAttribute(selection.polygon) + '" fill="'
            + (editorMode === 'automatic'
              ? (isCurrent ? 'rgba(244,63,94,0.56)' : 'rgba(244,63,94,0.34)')
              : 'rgba(244,63,94,0.42)')
            + '" stroke="transparent" stroke-width="0" stroke-linejoin="round" class="' + (editorMode === 'automatic' ? 'cursor-move' : '') + '"></polygon>'
            + '</g>';
        }).join('');
        toothSelectLayer.innerHTML = selectedMarkup;
      }

      function getBrushPoint(event) {
        if (!brushCanvas) return null;
        const canvasRect = brushCanvas.getBoundingClientRect();
        const source = event && event.touches && event.touches[0]
          ? event.touches[0]
          : (event && event.changedTouches && event.changedTouches[0]
            ? event.changedTouches[0]
            : event);
        const clientX = source && typeof source.clientX === 'number' ? source.clientX : 0;
        const clientY = source && typeof source.clientY === 'number' ? source.clientY : 0;
        return {
          x: normalize(((clientX - canvasRect.left) / Math.max(1, canvasRect.width)) * brushCanvas.width, 0, brushCanvas.width),
          y: normalize(((clientY - canvasRect.top) / Math.max(1, canvasRect.height)) * brushCanvas.height, 0, brushCanvas.height)
        };
      }

      function paintBrushPoint(point) {
        if (!brushContext || !point) return;
        const radius = getBrushSize() / 2;
        brushContext.save();
        brushContext.globalCompositeOperation = brushEraseMode ? 'destination-out' : 'source-over';
        brushContext.fillStyle = 'rgba(244,63,94,0.72)';
        brushContext.beginPath();
        brushContext.arc(point.x, point.y, radius, 0, Math.PI * 2);
        brushContext.fill();
        brushContext.restore();
      }

      function buildBrushExports() {
        if (!brushCanvas) return { mask: '', overlay: '' };
        const maskCanvas = document.createElement('canvas');
        maskCanvas.width = brushCanvas.width;
        maskCanvas.height = brushCanvas.height;
        const maskCtx = maskCanvas.getContext('2d');
        if (!maskCtx) return { mask: '', overlay: '' };
        maskCtx.drawImage(brushCanvas, 0, 0);
        const imageData = maskCtx.getImageData(0, 0, maskCanvas.width, maskCanvas.height);
        let hasSelection = false;
        for (let index = 0; index < imageData.data.length; index += 4) {
          const alpha = imageData.data[index + 3];
          const value = alpha > 8 ? 255 : 0;
          if (value > 0) hasSelection = true;
          imageData.data[index] = value;
          imageData.data[index + 1] = value;
          imageData.data[index + 2] = value;
          imageData.data[index + 3] = value > 0 ? 255 : 0;
        }
        if (!hasSelection) {
          return { mask: '', overlay: '' };
        }
        maskCtx.putImageData(imageData, 0, 0);

        const overlayCanvas = document.createElement('canvas');
        overlayCanvas.width = brushCanvas.width;
        overlayCanvas.height = brushCanvas.height;
        const overlayCtx = overlayCanvas.getContext('2d');
        if (!overlayCtx) return { mask: maskCanvas.toDataURL('image/png'), overlay: '' };
        overlayCtx.drawImage(brushCanvas, 0, 0);
        overlayCtx.globalCompositeOperation = 'destination-over';
        overlayCtx.fillStyle = 'rgba(0,0,0,0)';
        overlayCtx.fillRect(0, 0, overlayCanvas.width, overlayCanvas.height);
        return {
          mask: maskCanvas.toDataURL('image/png'),
          overlay: overlayCanvas.toDataURL('image/png')
        };
      }

      function buildAutomaticToothMaskExports() {
        if (!workPreview || !workPreview.naturalWidth || !workPreview.naturalHeight || !currentSelectedToothRegions.length) {
          return { mask: '', overlay: '' };
        }
        const scale = Math.min(1, 1200 / Math.max(workPreview.naturalWidth, workPreview.naturalHeight));
        const width = Math.max(1, Math.round(workPreview.naturalWidth * scale));
        const height = Math.max(1, Math.round(workPreview.naturalHeight * scale));
        const maskCanvas = document.createElement('canvas');
        const overlayCanvas = document.createElement('canvas');
        maskCanvas.width = overlayCanvas.width = width;
        maskCanvas.height = overlayCanvas.height = height;
        const maskContext = maskCanvas.getContext('2d');
        const overlayContext = overlayCanvas.getContext('2d');
        if (!maskContext || !overlayContext) return { mask: '', overlay: '' };
        overlayContext.drawImage(workPreview, 0, 0, width, height);

        const drawPolygon = function (context, polygon) {
          if (!Array.isArray(polygon) || polygon.length < 3) return;
          context.beginPath();
          polygon.forEach(function (point, index) {
            const x = (point.x / 100) * width;
            const y = (point.y / 100) * height;
            if (index === 0) context.moveTo(x, y);
            else context.lineTo(x, y);
          });
          context.closePath();
          context.fill();
        };

        maskContext.fillStyle = '#ffffff';
        overlayContext.fillStyle = 'rgba(244,63,94,0.58)';
        currentSelectedToothRegions.forEach(function (selection) {
          drawPolygon(maskContext, selection.polygon);
          drawPolygon(overlayContext, selection.polygon);
        });
        return {
          mask: maskCanvas.toDataURL('image/png'),
          overlay: overlayCanvas.toDataURL('image/jpeg', 0.90)
        };
      }

      function commitBrushSelection() {
        const payload = buildBrushExports();
        writeBrushPayload(payload.mask, payload.overlay);
        if (payload.mask) {
          writeSelectionMode('brush');
          writeSelectedTeeth(getManualSelectionToothPayload());
        } else {
          writeSelectionMode(editorMode === 'manual' ? 'manual_single_tooth' : 'auto_single_tooth');
          writeSelectedTeeth(getManualSelectionToothPayload());
        }
      }

      function getManualSelectionToothPayload() {
        return getSelectedTeethPayload();
      }

      function saveBrushHistory() {
        if (!brushContext || !brushCanvas || brushCanvas.width <= 0 || brushCanvas.height <= 0) return;
        try {
          brushHistory.push({
            width: brushCanvas.width,
            height: brushCanvas.height,
            imageData: brushContext.getImageData(0, 0, brushCanvas.width, brushCanvas.height)
          });
          if (brushHistory.length > 20) {
            brushHistory.shift();
          }
          updateBrushUi();
        } catch (error) {
        }
      }

      function undoBrushStroke() {
        if (editorMode !== 'manual' || !ensureBrushLayer() || !brushContext || !brushCanvas) return;
        const previous = brushHistory.pop();
        if (!previous) {
          updateBrushUi();
          return;
        }
        brushContext.clearRect(0, 0, brushCanvas.width, brushCanvas.height);
        if (previous.width === brushCanvas.width && previous.height === brushCanvas.height) {
          brushContext.putImageData(previous.imageData, 0, 0);
        }
        commitBrushSelection();
        if (!brushMaskInput || brushMaskInput.value === '') {
          writeSelectionMode('manual');
        }
        updateBrushUi();
      }

      function clearBrushSelection() {
        if (!ensureBrushLayer() || !brushContext) return;
        brushContext.clearRect(0, 0, brushCanvas.width, brushCanvas.height);
        brushHistory = [];
        writeBrushPayload('', '');
        writeSelectionMode(editorMode === 'manual' ? 'manual' : 'auto_single_tooth');
        writeSelectedTeeth(getManualSelectionToothPayload());
      }

      function toggleBrushMode() {
        if (editorMode !== 'manual') return;
        brushMode = !brushMode;
        ensureBrushLayer();
        updateBrushUi();
      }

      function toggleBrushEraseMode() {
        if (editorMode !== 'manual') return;
        brushEraseMode = !brushEraseMode;
        updateBrushUi();
      }

      function applyBrushMask() {
        if (editorMode !== 'manual') return;
        commitBrushSelection();
        brushMode = false;
        brushEraseMode = false;
        render(readAnchorPoints());
        updateBrushUi();
      }

      function isContourSane(points, anchorPoints) {
        if (!Array.isArray(points) || points.length < 8 || !Array.isArray(anchorPoints) || !anchorPoints.length) {
          return false;
        }
        const contourBounds = getPointBounds(points);
        const anchorBounds = getAnchorBounds(anchorPoints);
        const contourWidth = contourBounds.right - contourBounds.left;
        const contourHeight = contourBounds.bottom - contourBounds.top;
        const anchorWidth = anchorBounds.right - anchorBounds.left;
        const anchorHeight = anchorBounds.bottom - anchorBounds.top;
        if (contourWidth <= 0 || contourHeight <= 0 || anchorWidth <= 0 || anchorHeight <= 0) {
          return false;
        }
        const contourCenterX = (contourBounds.left + contourBounds.right) / 2;
        const contourCenterY = (contourBounds.top + contourBounds.bottom) / 2;
        const anchorCenterX = (anchorBounds.left + anchorBounds.right) / 2;
        const anchorCenterY = (anchorBounds.top + anchorBounds.bottom) / 2;
        return Math.abs(contourCenterX - anchorCenterX) <= 12
          && Math.abs(contourCenterY - anchorCenterY) <= 10
          && contourWidth <= (anchorWidth * 1.55)
          && contourWidth >= (anchorWidth * 0.45)
          && contourHeight <= (anchorHeight * 1.75)
          && contourHeight >= (anchorHeight * 0.28);
      }

      function clearRetiredEditorLayer() {
        if (konvaHolder) {
          konvaHolder.innerHTML = '';
          konvaHolder.style.pointerEvents = 'none';
        }
      }

      function centroid(points) {
        const total = points.reduce(function (carry, point) {
          return { x: carry.x + point.x, y: carry.y + point.y };
        }, { x: 0, y: 0 });
        return { x: total.x / points.length, y: total.y / points.length };
      }

      function readDelta(name) {
        const input = form.querySelector('input[name="' + name + '"]');
        return input ? normalize(input.value, -100, 100) : 0;
      }

      function applyPrecisionPreview() {
        if (!baseAnchorPoints.length) return;
        const shapeDelta = readDelta('shape_scale_delta') / 100;
        const lengthDelta = readDelta('smile_length_delta') / 100;
        const widthDelta = readDelta('smile_width_delta') / 100;
        const bounds = getAnchorBounds(baseAnchorPoints);
        const centerX = (bounds.left + bounds.right) / 2;
        const baseWidth = bounds.right - bounds.left;
        const baseHeight = bounds.bottom - bounds.top;
        const halfWidth = Math.max(3, (baseWidth / 2) * (1 + (shapeDelta * 0.35) + (widthDelta * 0.60)));
        const top = normalize(bounds.top - (baseHeight * shapeDelta * 0.10), 0, 100);
        const bottom = normalize(
          bounds.bottom + (baseHeight * shapeDelta * 0.18) + (baseHeight * lengthDelta * 0.55),
          0,
          100
        );
        const nextPoints = anchorsFromBounds({
          left: normalize(centerX - halfWidth, 0, 100),
          right: normalize(centerX + halfWidth, 0, 100),
          top: Math.min(top, bottom - 3),
          bottom: Math.max(bottom, top + 3)
        }, baseAnchorPoints[0] && baseAnchorPoints[0].size ? baseAnchorPoints[0].size : 9);
        const nextContour = baseContourPoints.length
          ? transformContourPoints(baseContourPoints, getAnchorBounds(baseAnchorPoints), getAnchorBounds(nextPoints))
          : [];
        render(nextPoints, nextContour);
      }

      function syncZoom(points) {
        if (!workStage || !workFrame) return;
        ensureBrushLayer();
        const zoom = zoomRange ? normalize(zoomRange.value, 100, 600) : defaultZoom;
        const scale = zoom / 100;
        let center = centroid(points);
        const teethBounds = detectedTeethBounds && detectedTeethBounds.slots ? detectedTeethBounds : null;
        if (teethBounds) {
          const visibleSlots = visibleUpperTeeth.map(function (toothNumber) {
            return teethBounds.slots[toothNumber] || null;
          }).filter(Boolean);
          if (visibleSlots.length) {
            const left = Math.min.apply(null, visibleSlots.map(function (slot) { return slot.left; }));
            const right = Math.max.apply(null, visibleSlots.map(function (slot) { return slot.right; }));
            const top = Math.min.apply(null, visibleSlots.map(function (slot) { return slot.top; }));
            const bottom = Math.max.apply(null, visibleSlots.map(function (slot) { return slot.bottom; }));
            const slot8 = teethBounds.slots[8] || null;
            const slot9 = teethBounds.slots[9] || null;
            center = {
              x: slot8 && slot9 ? (slot8.right + slot9.left) / 2 : (left + right) / 2,
              y: normalize(((top + bottom) / 2) + 0.8, 0, 100)
            };
          }
        }
        const rect = workFrame.getBoundingClientRect();
        const imageRect = getDisplayImageRect();
        const centerX = imageRect.left + ((center.x / 100) * imageRect.width);
        const centerY = imageRect.top + ((center.y / 100) * imageRect.height);
        let offsetX = (rect.width * 0.5) - (centerX * scale);
        let offsetY = (rect.height * 0.5) - (centerY * scale);
        const minX = rect.width - (rect.width * scale);
        const minY = (rect.height * 0.78) - (rect.height * scale);
        offsetX = scale <= 1 ? 0 : Math.max(minX, Math.min(0, offsetX));
        offsetY = scale <= 1 ? 0 : Math.max(minY, Math.min(0, offsetY));
        workStage.style.transformOrigin = '0 0';
        workStage.style.transform = 'translate(' + offsetX + 'px, ' + offsetY + 'px) scale(' + scale + ')';
        if (zoomValue) zoomValue.textContent = Math.round(zoom) + '%';
        updateBrushUi();
      }

      function render(points, contourOverride) {
        let maskPolygon = (contourOverride && contourOverride.length)
          ? contourOverride
          : (baseContourPoints.length
            ? transformContourPoints(baseContourPoints, getAnchorBounds(baseAnchorPoints.length ? baseAnchorPoints : points), getAnchorBounds(points))
            : getSmileMaskPolygon(points));
        if (!isContourSane(maskPolygon, points)) {
          maskPolygon = getSmileMaskPolygon(points);
        }
        const selectedToothList = getSelectedTeethArray();
        const activeToothNumber = getSelectedToothNumber();
        const selectedToothRegions = selectedToothList.length
          ? buildSelectedToothSelections(points, maskPolygon)
          : [];
        currentSelectedToothRegions = selectedToothRegions;
        const activeSelection = selectedToothList.length
          ? (selectedToothRegions.find(function (selection) {
              return Number(selection.number) === Number(activeToothNumber);
            }) || null)
          : null;
        if (editorMode === 'automatic') {
          autoToothSelection = activeSelection;
          renderAutoToothSelection(points, maskPolygon, selectedToothRegions);
          writeBrushPayload('', '');
          writeSelectionMode('auto_single_tooth');
          writeSelectedTeeth(getSelectedTeethPayload());
          writeToothOffsets();
          writeToothAdjustments();
          writeContourPoints(selectedToothList.length === 1 && activeSelection ? activeSelection.polygon : []);
        } else {
          autoToothSelection = activeSelection;
          renderAutoToothSelection(points, maskPolygon, selectedToothRegions);
          if (brushMaskInput && brushMaskInput.value !== '') {
            writeSelectionMode('brush');
            writeContourPoints([]);
            writeSelectedTeeth(getSelectedTeethPayload());
          } else {
            writeSelectionMode('manual_single_tooth');
            writeSelectedTeeth(getSelectedTeethPayload());
            writeContourPoints(selectedToothList.length === 1 && activeSelection && activeSelection.polygon ? activeSelection.polygon : []);
          }
        }
        clearRetiredEditorLayer();
        writeAnchorPoints(points);
        updateToothMapUi();
        syncZoom(points);
      }

      function buildDetectedAnchors(bounds) {
        const width = Math.max(12, bounds.maxX - bounds.minX);
        const height = Math.max(6, bounds.maxY - bounds.minY);
        const left = Math.max(0, bounds.minX - (width * 0.035));
        const right = Math.min(100, bounds.maxX + (width * 0.035));
        const top = Math.max(0, bounds.minY - (height * 0.10));
        const bottom = Math.min(100, bounds.maxY + (height * 0.10));
        return anchorsFromBounds({
          left: Math.round(left * 100) / 100,
          right: Math.round(right * 100) / 100,
          top: Math.round(top * 100) / 100,
          bottom: Math.round(bottom * 100) / 100
        }, 9);
      }

      async function getFaceLandmarker() {
        if (faceLandmarkerPromise) {
          return faceLandmarkerPromise;
        }
        faceLandmarkerPromise = import('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@latest/vision_bundle.mjs')
          .then(async function (module) {
            const vision = await module.FilesetResolver.forVisionTasks('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@latest/wasm');
            return module.FaceLandmarker.createFromOptions(vision, {
              baseOptions: {
                modelAssetPath: 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task'
              },
              runningMode: 'IMAGE',
              numFaces: 1
            });
          })
          .catch(function () {
            return null;
          });
        return faceLandmarkerPromise;
      }

      function estimateUpperTeethLowerEdge(context, canvasWidth, canvasHeight, leftPoint, rightPoint, upperCenterPoint, lowerCenterPoint) {
        const imageData = context.getImageData(0, 0, canvasWidth, canvasHeight).data;
        const samples = [0.25, 0.5, 0.75].map(function (ratio) {
          const x = Math.round(leftPoint.x + ((rightPoint.x - leftPoint.x) * ratio));
          const scanTop = Math.max(0, Math.round(upperCenterPoint.y + ((lowerCenterPoint.y - upperCenterPoint.y) * 0.18)));
          const scanBottom = Math.min(canvasHeight - 1, Math.round(lowerCenterPoint.y - ((lowerCenterPoint.y - upperCenterPoint.y) * 0.10)));
          let lastHit = scanTop;
          for (let y = scanTop; y <= scanBottom; y++) {
            const index = ((y * canvasWidth) + x) * 4;
            const r = imageData[index];
            const g = imageData[index + 1];
            const b = imageData[index + 2];
            const hsv = rgbToHsv(r, g, b);
            if (hsv.v >= 0.60 && hsv.s <= 0.22) {
              lastHit = y;
            }
          }
          return {
            ratio: ratio,
            x: x,
            y: lastHit
          };
        });
        return samples;
      }

      function detectTeethContour(context, canvasWidth, canvasHeight, leftPoint, rightPoint, upperCenterPoint, lowerCenterPoint, options) {
        const imageData = context.getImageData(0, 0, canvasWidth, canvasHeight).data;
        const minX = Math.max(0, Math.floor(leftPoint.x));
        const maxX = Math.min(canvasWidth - 1, Math.ceil(rightPoint.x));
        const minY = Math.max(0, Math.floor(upperCenterPoint.y - ((lowerCenterPoint.y - upperCenterPoint.y) * 0.10)));
        const maxY = Math.min(canvasHeight - 1, Math.ceil(lowerCenterPoint.y));
        const columns = [];
        const contourDetail = Math.round(normalize(options && options.detail ? options.detail : 72, 24, 120));

        for (let x = minX; x <= maxX; x++) {
          let top = null;
          let bottom = null;
          for (let y = minY; y <= maxY; y++) {
            const index = ((y * canvasWidth) + x) * 4;
            const r = imageData[index];
            const g = imageData[index + 1];
            const b = imageData[index + 2];
            const brightness = (0.2126 * r) + (0.7152 * g) + (0.0722 * b);
            const hsv = rgbToHsv(r, g, b);
            const isToothPixel = hsv.v >= 0.60 && hsv.s <= 0.22 && brightness >= 150;
            if (!isToothPixel) continue;
            if (top === null) top = y;
            bottom = y;
          }
          if (top !== null && bottom !== null && (bottom - top) >= 4) {
            columns.push({ x: x, top: top, bottom: bottom });
          }
        }

        if (columns.length < 10) {
          return [];
        }

        const sampleCount = Math.min(Math.max(16, Math.floor(columns.length / 2)), Math.max(24, contourDetail));
        const topPoints = [];
        const bottomPoints = [];
        const bucketSize = Math.max(1, columns.length / sampleCount);
        for (let index = 0; index < sampleCount; index++) {
          const start = Math.floor(index * bucketSize);
          const end = Math.min(columns.length, Math.floor((index + 1) * bucketSize));
          const bucket = columns.slice(start, Math.max(start + 1, end));
          const sampleColumn = bucket.reduce(function (carry, column) {
            return {
              x: carry.x + column.x,
              top: Math.min(carry.top, column.top),
              bottom: Math.max(carry.bottom, column.bottom)
            };
          }, { x: 0, top: canvasHeight, bottom: 0 });
          const bucketCount = Math.max(1, bucket.length);
          const averageX = sampleColumn.x / bucketCount;
          topPoints.push({
            x: (averageX / canvasWidth) * 100,
            y: (sampleColumn.top / canvasHeight) * 100
          });
          bottomPoints.push({
            x: (averageX / canvasWidth) * 100,
            y: (sampleColumn.bottom / canvasHeight) * 100
          });
        }

        const smoothPass = function (points, mode) {
          return points.map(function (point, index) {
            const prev = points[Math.max(0, index - 1)];
            const next = points[Math.min(points.length - 1, index + 1)];
            const neighborY = (prev.y + point.y + next.y) / 3;
            return clampPoint({
              x: point.x,
              y: mode === 'top'
                ? Math.min(point.y, neighborY)
                : Math.max(point.y, neighborY)
            });
          });
        };

        const smoothedTop = smoothPass(topPoints, 'top');
        const smoothedBottom = smoothPass(bottomPoints, 'bottom');
        const rawContour = smoothedTop.concat(smoothedBottom.reverse()).map(clampPoint);
        const bounds = getPointBounds(rawContour);
        const height = Math.max(1, bounds.bottom - bounds.top);
        const tightenedTop = smoothedTop.map(function (point, index) {
          const prevX = index > 0 ? smoothedTop[index - 1].x : point.x;
          const nextX = index < smoothedTop.length - 1 ? smoothedTop[index + 1].x : point.x;
          return clampPoint({
            x: normalize(point.x, prevX, nextX),
            y: point.y + (height * 0.01)
          });
        });
        const tightenedBottom = smoothedBottom.map(function (point, index) {
          const prevX = index > 0 ? smoothedBottom[index - 1].x : point.x;
          const nextX = index < smoothedBottom.length - 1 ? smoothedBottom[index + 1].x : point.x;
          return clampPoint({
            x: normalize(point.x, prevX, nextX),
            y: point.y - (height * 0.04)
          });
        });

        return tightenedTop.concat(tightenedBottom.reverse()).map(clampPoint);
      }

      async function detectMouthAnchorsWithLandmarks(sourceImage, options) {
        const detectorImage = sourceImage || detectPreview || workPreview;
        if (!detectorImage || !detectorImage.naturalWidth || !detectorImage.naturalHeight) {
          return null;
        }
        const faceLandmarker = await getFaceLandmarker();
        if (!faceLandmarker) {
          return null;
        }
        const result = faceLandmarker.detect(detectorImage);
        if (!result || !result.faceLandmarks || !result.faceLandmarks.length) {
          return null;
        }
        const landmarks = result.faceLandmarks[0];
        if (!landmarks || !landmarks.length) {
          return null;
        }
        const canvas = document.createElement('canvas');
        const maxWidth = 420;
        const scale = Math.min(1, maxWidth / detectorImage.naturalWidth);
        canvas.width = Math.max(1, Math.round(detectorImage.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(detectorImage.naturalHeight * scale));
        const context = canvas.getContext('2d', { willReadFrequently: true });
        if (!context) {
          return null;
        }
        context.drawImage(detectorImage, 0, 0, canvas.width, canvas.height);
        const pointAt = function (index) {
          const point = landmarks[index];
          return {
            x: point.x * canvas.width,
            y: point.y * canvas.height
          };
        };
        const leftCommissure = pointAt(61);
        const rightCommissure = pointAt(291);
        const upperCenter = pointAt(13);
        const lowerCenterLip = pointAt(14);
        const upperLeftMid = pointAt(81);
        const upperRightMid = pointAt(311);
        const toothSamples = estimateUpperTeethLowerEdge(context, canvas.width, canvas.height, leftCommissure, rightCommissure, upperCenter, lowerCenterLip);
        const contourPoints = detectTeethContour(context, canvas.width, canvas.height, leftCommissure, rightCommissure, upperCenter, lowerCenterLip, options || {});
        const lowerLeftMid = toothSamples[0];
        const lowerCenter = toothSamples[1];
        const lowerRightMid = toothSamples[2];
        return {
          anchors: [
          { key: 'upper_left', label: 'Upper left lip edge', x: (upperLeftMid.x / canvas.width) * 100, y: (upperLeftMid.y / canvas.height) * 100, size: 9 },
          { key: 'upper_center', label: 'Upper center lip edge', x: (upperCenter.x / canvas.width) * 100, y: (upperCenter.y / canvas.height) * 100, size: 9 },
          { key: 'upper_right', label: 'Upper right lip edge', x: (upperRightMid.x / canvas.width) * 100, y: (upperRightMid.y / canvas.height) * 100, size: 9 },
          { key: 'right_inner', label: 'Right smile corner', x: (rightCommissure.x / canvas.width) * 100, y: (rightCommissure.y / canvas.height) * 100, size: 9 },
          { key: 'lower_right', label: 'Lower right tooth edge', x: (lowerRightMid.x / canvas.width) * 100, y: (lowerRightMid.y / canvas.height) * 100, size: 9 },
          { key: 'lower_center', label: 'Lower tooth center', x: (lowerCenter.x / canvas.width) * 100, y: (lowerCenter.y / canvas.height) * 100, size: 9 },
          { key: 'lower_left', label: 'Lower left tooth edge', x: (lowerLeftMid.x / canvas.width) * 100, y: (lowerLeftMid.y / canvas.height) * 100, size: 9 },
          { key: 'left_inner', label: 'Left smile corner', x: (leftCommissure.x / canvas.width) * 100, y: (leftCommissure.y / canvas.height) * 100, size: 9 }
        ].map(clampPoint).map(function (point, index) {
          point.key = ['upper_left', 'upper_center', 'upper_right', 'right_inner', 'lower_right', 'lower_center', 'lower_left', 'left_inner'][index];
          point.label = [
            'Upper left lip edge',
            'Upper center lip edge',
            'Upper right lip edge',
            'Right smile corner',
            'Lower right tooth edge',
            'Lower tooth center',
            'Lower left tooth edge',
            'Left smile corner'
          ][index];
          point.size = 9;
          return point;
        }),
          contour: contourPoints
        };
      }

      function detectMouthAnchorsFromImage(sourceImage) {
        const detectorImage = sourceImage || detectPreview || workPreview;
        if (!detectorImage || !detectorImage.naturalWidth || !detectorImage.naturalHeight) {
          return cloneDefaults();
        }
        const canvas = document.createElement('canvas');
        const maxWidth = 420;
        const scale = Math.min(1, maxWidth / detectorImage.naturalWidth);
        canvas.width = Math.max(1, Math.round(detectorImage.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(detectorImage.naturalHeight * scale));
        const context = canvas.getContext('2d', { willReadFrequently: true });
        if (!context) {
          return cloneDefaults();
        }
        context.drawImage(detectorImage, 0, 0, canvas.width, canvas.height);
        const imageData = context.getImageData(0, 0, canvas.width, canvas.height).data;
        const xStart = Math.floor(canvas.width * 0.18);
        const xEnd = Math.ceil(canvas.width * 0.82);
        const yStart = Math.floor(canvas.height * 0.38);
        const yEnd = Math.ceil(canvas.height * 0.80);
        let minX = canvas.width;
        let maxX = 0;
        let minY = canvas.height;
        let maxY = 0;
        let hits = 0;
        const rowHits = new Array(canvas.height).fill(0);
        const colHits = new Array(canvas.width).fill(0);

        for (let y = yStart; y < yEnd; y++) {
          for (let x = xStart; x < xEnd; x++) {
            const index = ((y * canvas.width) + x) * 4;
            const r = imageData[index];
            const g = imageData[index + 1];
            const b = imageData[index + 2];
            const brightness = (0.2126 * r) + (0.7152 * g) + (0.0722 * b);
            const hsv = rgbToHsv(r, g, b);
            const isNeutralHue = hsv.h <= 55 || hsv.h >= 330;
            const isToothPixel = hsv.v >= 0.68
              && hsv.s <= 0.24
              && brightness >= 158
              && r >= 140
              && g >= 132
              && b >= 118
              && isNeutralHue;
            if (!isToothPixel) continue;
            hits++;
            rowHits[y]++;
            colHits[x]++;
            if (x < minX) minX = x;
            if (x > maxX) maxX = x;
            if (y < minY) minY = y;
            if (y > maxY) maxY = y;
          }
        }

        if (hits < 180 || minX >= maxX || minY >= maxY) {
          return cloneDefaults();
        }

        let weightedTop = minY;
        let weightedBottom = maxY;
        const rowThreshold = Math.max(3, Math.round((maxX - minX) * 0.06));
        for (let y = minY; y <= maxY; y++) {
          if (rowHits[y] >= rowThreshold) {
            weightedTop = y;
            break;
          }
        }
        for (let y = maxY; y >= minY; y--) {
          if (rowHits[y] >= rowThreshold) {
            weightedBottom = y;
            break;
          }
        }
        let weightedLeft = minX;
        let weightedRight = maxX;
        const colThreshold = Math.max(2, Math.round((weightedBottom - weightedTop) * 0.08));
        for (let x = minX; x <= maxX; x++) {
          if (colHits[x] >= colThreshold) {
            weightedLeft = x;
            break;
          }
        }
        for (let x = maxX; x >= minX; x--) {
          if (colHits[x] >= colThreshold) {
            weightedRight = x;
            break;
          }
        }

        return buildDetectedAnchors({
          minX: (weightedLeft / canvas.width) * 100,
          maxX: (weightedRight / canvas.width) * 100,
          minY: (weightedTop / canvas.height) * 100,
          maxY: (weightedBottom / canvas.height) * 100
        });
      }

      function getRangeConfig(key) {
        const configs = {
          shape_scale_delta: { label: 'adjust-shape-value', input: 'shape_scale_delta', suffix: '' },
          smile_length_delta: { label: 'adjust-length-value', input: 'smile_length_delta', suffix: '' },
          smile_width_delta: { label: 'adjust-width-value', input: 'smile_width_delta', suffix: '' },
          edge_smoothing: { label: 'adjust-smoothing-value', input: 'edge_smoothing', suffix: '' }
        };
        return configs[key] || null;
      }

      document.querySelectorAll('[data-adjust-range]').forEach(function (range) {
        const apply = function () {
          const key = range.getAttribute('data-adjust-range');
          const config = getRangeConfig(key);
          if (!config) return;
          const value = Number(range.value || 0);
          const activeTooth = getSelectedToothNumber();
          const next = getToothAdjustment(activeTooth);
          next[key] = value;
          toothPrecisionAdjustments[activeTooth] = next;
          const label = document.getElementById(config.label);
          if (label) label.textContent = String(value) + config.suffix;
          writeToothAdjustments();
          syncPrecisionControlsForActiveTooth();
          render(baseAnchorPoints.length ? clonePoints(baseAnchorPoints) : readAnchorPoints());
        };
        range.addEventListener('input', apply);
      });
      syncPrecisionControlsForActiveTooth();

      if (zoomRange) {
        zoomRange.addEventListener('input', function () {
          syncZoom(readAnchorPoints());
        });
      }

      window.addEventListener('resize', function () {
        render(readAnchorPoints());
      });

      async function refreshDetectedMask() {
        const detectorSource = (detectPreview && detectPreview.naturalWidth) ? detectPreview : workPreview;
        if (!detectorSource) return;
        if (loader) {
          if (loaderLabel) loaderLabel.textContent = 'Refreshing smile mask...';
          loader.classList.remove('hidden');
          loader.classList.add('flex');
        }
        try {
          const currentPoints = readAnchorPoints();
          const landmarkResult = await detectMouthAnchorsWithLandmarks(detectorSource, { detail: getContourDetail() });
          if (!landmarkResult || !landmarkResult.anchors || !landmarkResult.anchors.length || !landmarkResult.contour || !landmarkResult.contour.length) {
            return;
          }
          const detectedAnchors = clonePoints(landmarkResult.anchors);
          const detectedContour = clonePlainPoints(landmarkResult.contour);
          const targetPoints = currentPoints && currentPoints.length ? currentPoints : detectedAnchors;
          baseAnchorPoints = detectedAnchors;
          baseContourPoints = detectedContour;
          const transformedContour = transformContourPoints(detectedContour, getAnchorBounds(detectedAnchors), getAnchorBounds(targetPoints));
          render(targetPoints, transformedContour);
        } finally {
          if (loader) {
            loader.classList.add('hidden');
            loader.classList.remove('flex');
          }
        }
      }

      if (brushSizeRange) {
        brushSizeRange.addEventListener('input', updateMaskSettingLabels);
      }

      if (brushCanvas) {
        const startPaint = function (event) {
          if (editorMode !== 'manual' || !brushMode) return;
          if (brushDrawing) return;
          event.preventDefault();
          ensureBrushLayer();
          if (typeof brushCanvas.setPointerCapture === 'function' && typeof event.pointerId !== 'undefined') {
            try {
              brushCanvas.setPointerCapture(event.pointerId);
            } catch (error) {
            }
          }
          saveBrushHistory();
          brushDrawing = true;
          paintBrushPoint(getBrushPoint(event));
        };
        const movePaint = function (event) {
          if (editorMode !== 'manual' || !brushMode || !brushDrawing) return;
          event.preventDefault();
          paintBrushPoint(getBrushPoint(event));
        };
        const endPaint = function (event) {
          if (editorMode !== 'manual' || !brushMode) return;
          if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
          }
          brushDrawing = false;
          commitBrushSelection();
          updateBrushUi();
        };
        brushCanvas.addEventListener('pointerdown', startPaint);
        brushCanvas.addEventListener('pointermove', movePaint);
        window.addEventListener('pointerup', endPaint);
        brushCanvas.addEventListener('pointerup', endPaint);
        brushCanvas.addEventListener('pointercancel', endPaint);
        brushCanvas.addEventListener('pointerleave', function () {
          if (brushDrawing) {
            commitBrushSelection();
          }
          brushDrawing = false;
          updateBrushUi();
        });
        brushCanvas.addEventListener('mouseleave', function () {
          if (brushDrawing) {
            commitBrushSelection();
          }
          brushDrawing = false;
          updateBrushUi();
        });
      }

      document.addEventListener('click', function (event) {
        const actionButton = event.target && event.target.closest ? event.target.closest('[data-brush-action]') : null;
        if (!actionButton) return;
        event.preventDefault();
        event.stopPropagation();
        const action = actionButton.getAttribute('data-brush-action');
        if (action === 'toggle-paint') {
          toggleBrushMode();
        } else if (action === 'toggle-erase') {
          toggleBrushEraseMode();
        } else if (action === 'clear') {
          clearBrushSelection();
          updateBrushUi();
        } else if (action === 'undo') {
          undoBrushStroke();
        } else if (action === 'apply') {
          applyBrushMask();
        }
      });

      document.addEventListener('keydown', function (event) {
        if (!(event.ctrlKey || event.metaKey) || String(event.key || '').toLowerCase() !== 'z') {
          return;
        }
        const target = event.target;
        const tagName = target && target.tagName ? String(target.tagName).toLowerCase() : '';
        if (tagName === 'input' || tagName === 'textarea' || tagName === 'select' || (target && target.isContentEditable)) {
          return;
        }
        event.preventDefault();
        if (editorMode === 'automatic' && toothEdgeTool) {
          undoActiveToothEdge();
        } else if (editorMode === 'manual') {
          undoBrushStroke();
        }
      });

      document.addEventListener('click', function (event) {
        const modeButton = event.target && event.target.closest ? event.target.closest('[data-editor-mode]') : null;
        if (!modeButton) return;
        event.preventDefault();
        event.stopPropagation();
        setEditorMode(String(modeButton.getAttribute('data-editor-mode') || 'automatic'));
      });

      document.addEventListener('click', function (event) {
        const toothButton = event.target && event.target.closest ? event.target.closest('[data-tooth-number]') : null;
        if (!toothButton) return;
        event.preventDefault();
        event.stopPropagation();
        chooseToothNumber(
          toothButton.getAttribute('data-tooth-number'),
          Boolean(event.ctrlKey || event.metaKey || event.shiftKey)
        );
      });

      if (workFrame) {
        workFrame.addEventListener('click', function (event) {
          if (editorMode !== 'manual' || brushMode) return;
          const target = event.target;
          if (target && target.closest && (
            target.closest('#adjust-floating-controls')
            || target.closest('[data-brush-action]')
            || target.closest('[data-editor-mode]')
            || target.closest('[data-tooth-number]')
          )) {
            return;
          }
          const imageRect = getVisibleImageRect();
          if (!imageRect || imageRect.width <= 0 || imageRect.height <= 0) return;
          const clientX = typeof event.clientX === 'number' ? event.clientX : 0;
          const clientY = typeof event.clientY === 'number' ? event.clientY : 0;
          if (clientX < imageRect.left || clientX > imageRect.right || clientY < imageRect.top || clientY > imageRect.bottom) {
            return;
          }
          seedToothSelectionFromClick(
            clientX,
            clientY,
            Boolean(event.ctrlKey || event.metaKey || event.shiftKey)
          );
        });
      }

      if (toothEdgeEraserButton) {
        toothEdgeEraserButton.addEventListener('click', function () {
          setToothEdgeTool('erase');
        });
      }
      if (toothEdgeSmootherButton) {
        toothEdgeSmootherButton.addEventListener('click', function () {
          setToothEdgeTool('smooth');
        });
      }
      if (toothEdgeSizeRange) {
        toothEdgeSizeRange.addEventListener('input', function () {
          if (toothEdgeSizeValue) toothEdgeSizeValue.textContent = String(toothEdgeSizeRange.value || 10);
        });
      }
      if (toothEdgeUndoButton) {
        toothEdgeUndoButton.addEventListener('click', function () {
          undoActiveToothEdge();
        });
      }
      if (toothEdgeResetButton) {
        toothEdgeResetButton.addEventListener('click', function () {
          const toothNumber = getSelectedToothNumber();
          delete toothContourOverrides[toothNumber];
          delete toothContourHistory[toothNumber];
          setToothEdgeTool('');
          render(baseAnchorPoints.length ? clonePoints(baseAnchorPoints) : readAnchorPoints());
        });
      }

      if (toothSelectLayer) {
        toothSelectLayer.addEventListener('pointerdown', function (event) {
          if (editorMode !== 'automatic') return;
          const target = event.target;
          const toothNumber = target && typeof target.getAttribute === 'function'
            ? Number(target.getAttribute('data-selected-tooth') || 0)
            : 0;
          if (normalizeToothNumber(toothNumber) === null) return;
          const imageRect = getVisibleImageRect();
          if (!imageRect || imageRect.width <= 0 || imageRect.height <= 0) return;
          event.preventDefault();
          event.stopPropagation();
          if (toothEdgeTool) {
            setSelectedTeeth(getSelectedTeethArray(), toothNumber);
            prepareActiveToothForEdgeEditing();
            saveToothEdgeHistory(toothNumber);
            toothEdgeDrawing = true;
            suppressToothClick = true;
            applyToothEdgeBrush(event.clientX, event.clientY);
            return;
          }
          const existing = toothManualOffsets[toothNumber] || { x: 0, y: 0 };
          draggingTooth = {
            number: toothNumber,
            startClientX: event.clientX,
            startClientY: event.clientY,
            startOffsetX: Number(existing.x || 0),
            startOffsetY: Number(existing.y || 0),
            imageWidth: imageRect.width,
            imageHeight: imageRect.height,
            moved: false
          };
        });

        window.addEventListener('pointermove', function (event) {
          updateToothEdgeCursor(event);
          if (toothEdgeDrawing && toothEdgeTool && editorMode === 'automatic') {
            event.preventDefault();
            applyToothEdgeBrush(event.clientX, event.clientY);
            return;
          }
          if (!draggingTooth || editorMode !== 'automatic') return;
          const deltaX = ((event.clientX - draggingTooth.startClientX) / Math.max(1, draggingTooth.imageWidth)) * 100;
          const deltaY = ((event.clientY - draggingTooth.startClientY) / Math.max(1, draggingTooth.imageHeight)) * 100;
          if (Math.abs(deltaX) + Math.abs(deltaY) < 0.08) return;
          event.preventDefault();
          draggingTooth.moved = true;
          toothManualOffsets[draggingTooth.number] = {
            x: Math.round(normalize(draggingTooth.startOffsetX + deltaX, -15, 15) * 100) / 100,
            y: Math.round(normalize(draggingTooth.startOffsetY + deltaY, -12, 12) * 100) / 100
          };
          render(readAnchorPoints());
        });

        window.addEventListener('pointerup', function () {
          if (toothEdgeDrawing) {
            toothEdgeDrawing = false;
            writeToothAdjustments();
            writeToothOffsets();
            suppressToothClick = true;
            window.setTimeout(function () {
              suppressToothClick = false;
            }, 0);
            return;
          }
          if (!draggingTooth) return;
          suppressToothClick = Boolean(draggingTooth.moved);
          draggingTooth = null;
          writeToothOffsets();
          window.setTimeout(function () {
            suppressToothClick = false;
          }, 0);
        });

        workFrame.addEventListener('pointerleave', function () {
          if (toothEdgeCursor) toothEdgeCursor.classList.add('hidden');
        });

        toothSelectLayer.addEventListener('click', function (event) {
          if (editorMode !== 'automatic') return;
          if (suppressToothClick) {
            event.preventDefault();
            event.stopPropagation();
            return;
          }
          const target = event.target;
          const toothNumber = target && typeof target.getAttribute === 'function'
            ? (target.getAttribute('data-selected-tooth')
              || (target.parentNode && typeof target.parentNode.getAttribute === 'function'
                ? target.parentNode.getAttribute('data-selected-tooth')
                : null))
            : null;
          if (!toothNumber) return;
          event.preventDefault();
          event.stopPropagation();
          toggleToothSelection(
            toothNumber,
            null,
            Boolean(event.ctrlKey || event.metaKey || event.shiftKey)
          );
          syncPrecisionControlsForActiveTooth();
          render(readAnchorPoints());
        });
      }

      form.addEventListener('submit', function () {
        if (editorMode === 'manual' && brushCanvas) {
          commitBrushSelection();
        } else if (editorMode === 'automatic') {
          const automaticPayload = buildAutomaticToothMaskExports();
          writeBrushPayload(automaticPayload.mask, automaticPayload.overlay);
          writeSelectionMode(automaticPayload.mask ? 'brush' : 'auto_single_tooth');
          writeSelectedTeeth(getSelectedTeethPayload());
          writeContourPoints(getSelectedTeethArray().length === 1 && autoToothSelection ? autoToothSelection.polygon : []);
        } else {
          writeBrushPayload('', '');
          writeSelectionMode(editorMode === 'manual' ? 'manual_single_tooth' : 'auto_single_tooth');
          writeSelectedTeeth(getSelectedTeethPayload());
          writeContourPoints(getSelectedTeethArray().length === 1 && autoToothSelection ? autoToothSelection.polygon : []);
        }
        if (loader) {
          if (loaderLabel) loaderLabel.textContent = 'Generating revision...';
          loader.classList.remove('hidden');
          loader.classList.add('flex');
        }
      });

      async function initializeAnchors() {
        const anchorInput = form.querySelector('input[name="anchor_points"]');
        const contourInput = form.querySelector('input[name="contour_points"]');
        if (!anchorInput.value) {
          const detectorSource = (detectPreview && detectPreview.naturalWidth) ? detectPreview : workPreview;
          const landmarkResult = await detectMouthAnchorsWithLandmarks(detectorSource, { detail: getContourDetail() });
          const detectedAnchors = landmarkResult && landmarkResult.anchors && landmarkResult.anchors.length
            ? landmarkResult.anchors
            : detectMouthAnchorsFromImage(detectorSource);
          baseContourPoints = landmarkResult && landmarkResult.contour && landmarkResult.contour.length
            ? clonePlainPoints(landmarkResult.contour)
            : [];
          writeAnchorPoints(detectedAnchors);
        } else if (contourInput && contourInput.value) {
          baseContourPoints = readContourPoints();
        }
        const initialPoints = readAnchorPoints();
        baseAnchorPoints = clonePoints(initialPoints);
        updateRefineToggle();
        writeEditorMode(editorMode);
        updateBrushUi();
        updateModeUi();
        updateMaskSettingLabels();
        render(initialPoints, baseContourPoints);
        loadOpenCvInBackground();
      }

      if ((detectPreview && !detectPreview.complete) || (workPreview && !workPreview.complete)) {
        const maybeInit = function () {
          const previewReady = !workPreview || workPreview.complete;
          const detectReady = !detectPreview || detectPreview.complete;
          if (previewReady && detectReady) {
            initializeAnchors();
          }
        };
        if (detectPreview && !detectPreview.complete) {
          detectPreview.addEventListener('load', maybeInit, { once: true });
        }
        if (workPreview && !workPreview.complete) {
          workPreview.addEventListener('load', maybeInit, { once: true });
        }
      } else {
        initializeAnchors();
      }
    });
    </script>
</body>
</html>
