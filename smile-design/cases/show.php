<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

$user = smile_design_internal_boot('Smile Case');
$caseId = (int)get('id', 0);
$case = smile_design_case($caseId);
if (!$case) {
    http_response_code(404);
    exit('Case not found.');
}

$photos = smile_design_case_photos($caseId);
$beforePhotos = smile_design_before_photos($caseId);
$uploadedBeforePhotos = array_values(array_filter($beforePhotos, static function (array $photo): bool {
    return ((string)($photo['source_type'] ?? 'uploaded')) !== 'ai_reference';
}));
$primaryBefore = smile_design_primary_before_photo($caseId);
$displayBeforePhoto = smile_design_find_before_photo_by_type($caseId, 'front', true) ?: $primaryBefore;
$afterVersions = smile_design_after_versions($caseId, true);
$selectedAfter = smile_design_selected_after_version($caseId);
$previewAfter = smile_design_patient_preview_version($caseId);
$galleryLinkResult = smile_design_issue_or_reuse_gallery_link(auth_user_id(), 90);
$consultRoomGalleryUrl = is_array($galleryLinkResult['link'] ?? null) ? (string)($galleryLinkResult['link']['gallery_url'] ?? '') : '';
$consultRoomCaseUrl = $consultRoomGalleryUrl !== ''
    ? $consultRoomGalleryUrl . (str_contains($consultRoomGalleryUrl, '?') ? '&' : '?') . 'case_id=' . $caseId
    : base_url('smile-design/gallery?case_id=' . $caseId);
$previewLinks = smile_design_preview_links($caseId, true, 10);
$activePreviewLink = smile_design_active_preview_link($caseId);
$activePreviewUrl = $activePreviewLink ? smile_design_preview_link_url($activePreviewLink) : null;
$hasLegacyActivePreview = $activePreviewLink && !$activePreviewUrl;
$activePreviewQrUrl = $activePreviewUrl ? 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=10&data=' . rawurlencode($activePreviewUrl) : null;
$activity = smile_design_case_activity($caseId, 20);
$caseAnalysis = smile_design_case_analysis($caseId);
$caseProcedureMode = smile_design_procedure_mode((string)($case['procedure_interest'] ?? ''));
$isLipRepositionOnlyCase = $caseProcedureMode === 'lip_repositioning';
$caseShadeDetail = smile_design_shade_detail((string)($case['shade_goal'] ?? '110'), (string)($case['selected_style'] ?? 'natural'));
$caseTreatmentScope = smile_design_normalize_treatment_scope((string)($case['treatment_scope'] ?? ''), (string)($case['procedure_interest'] ?? ''));
$caseSmileWidthGoal = smile_design_normalize_smile_width_goal((string)($case['smile_width_goal'] ?? ''));
$angleDefinitions = [
    'front' => 'Front',
    'left_45' => 'Left 45',
    'right_45' => 'Right 45',
];
$requestedOpenSetKeys = [];
$openSetsParam = trim((string)get('open_sets', ''));
if ($openSetsParam !== '') {
    foreach (explode(',', $openSetsParam) as $rawOpenSet) {
        $rawOpenSet = trim((string)$rawOpenSet);
        if (preg_match('/^(?:set_)?([0-9]+)$/', $rawOpenSet, $matches)) {
            $requestedOpenSetKeys['set_' . (string)$matches[1]] = true;
        }
    }
}
$angleSortOrder = array_flip(array_keys($angleDefinitions));
$afterVersionsByAngle = $afterVersions;
usort($afterVersionsByAngle, static function (array $a, array $b) use ($angleSortOrder): int {
    $aVersion = (int)($a['version_number'] ?? 0);
    $bVersion = (int)($b['version_number'] ?? 0);
    if ($aVersion !== $bVersion) {
        return $aVersion <=> $bVersion;
    }
    $aPhotoType = (string)($a['photo_type'] ?? '');
    $bPhotoType = (string)($b['photo_type'] ?? '');
    $aAngle = $angleSortOrder[$aPhotoType] ?? 99;
    $bAngle = $angleSortOrder[$bPhotoType] ?? 99;
    return $aAngle <=> $bAngle;
});
$angleBeforePhotos = [];
foreach ($angleDefinitions as $photoType => $label) {
    $photo = smile_design_find_before_photo_by_type($caseId, $photoType, true);
    if ($photo) {
        $angleBeforePhotos[$photoType] = $photo;
    }
}
$selectedAfterByAngle = [];
foreach ($angleDefinitions as $photoType => $label) {
    $selectedAfterByAngle[$photoType] = smile_design_selected_after_version($caseId, $photoType);
}
$selectedRevealAnglesReady = count(array_filter($selectedAfterByAngle, static fn($version): bool => is_array($version))) === count($angleDefinitions);
$latestRevealVideo = smile_design_latest_case_video($caseId);
$latestRevealVideoUrl = $latestRevealVideo ? smile_design_case_video_url((int)$latestRevealVideo['id']) : '';
$frontViewerPhoto = $angleBeforePhotos['front'] ?? $displayBeforePhoto;
$inputGallery = [];
$viewerAfterVersion = null;
foreach ($angleBeforePhotos as $photoType => $photo) {
    $angleAfter = $selectedAfterByAngle[$photoType] ?? null;
    if (!$viewerAfterVersion && $photoType === 'front') {
        $viewerAfterVersion = $angleAfter;
    }
    $inputGallery[] = [
        'label' => $angleDefinitions[$photoType] ?? (smile_design_photo_type_options()[$photoType] ?? 'Before'),
        'url' => smile_design_photo_url((int)$photo['id']),
        'after_url' => $angleAfter ? smile_design_after_url((int)$angleAfter['id']) : '',
        'after_label' => $angleAfter ? ('After #' . (string)$angleAfter['version_number']) : 'After pending',
        'before_photo_id' => (int)$photo['id'],
        'after_version_id' => $angleAfter ? (int)$angleAfter['id'] : '',
        'photo_type' => $photoType,
        'alignment' => $angleAfter ? smile_design_alignment_for_after($angleAfter) : smile_design_alignment_defaults(),
    ];
}
if (!$viewerAfterVersion && $selectedAfter) {
    $viewerAfterVersion = $selectedAfter;
}

$beforeUrl = $frontViewerPhoto ? smile_design_photo_url((int)$frontViewerPhoto['id']) : '';
$afterUrl = $viewerAfterVersion ? smile_design_after_url((int)$viewerAfterVersion['id']) : '';
$alignment = $viewerAfterVersion ? smile_design_alignment_for_after($viewerAfterVersion) : smile_design_alignment_defaults();
$alignmentEdit = $viewerAfterVersion ? [
    'pair_type' => 'case_after',
    'case_id' => $caseId,
    'before_photo_id' => (int)($viewerAfterVersion['before_photo_id'] ?? 0),
    'after_version_id' => (int)$viewerAfterVersion['id'],
    'photo_type' => (string)($viewerAfterVersion['photo_type'] ?? ''),
    'return_url' => $_SERVER['REQUEST_URI'] ?? base_url('smile-design/cases/' . $caseId),
] : [];

$activeAfterIds = array_values(array_filter(array_map(
    static fn($version): int => is_array($version) ? (int)($version['id'] ?? 0) : 0,
    $selectedAfterByAngle
)));
$workingSimulationAngles = [];
foreach ($angleDefinitions as $photoType => $label) {
    $workingSimulationAngles[$photoType] = [
        'label' => $label,
        'before' => $angleBeforePhotos[$photoType] ?? null,
        'after' => $selectedAfterByAngle[$photoType] ?? null,
    ];
}

$afterSetSize = max(1, count($angleDefinitions));
$afterSets = [];
foreach ($afterVersionsByAngle as $version) {
    $versionNumber = max(1, (int)($version['version_number'] ?? 1));
    $setNumber = intdiv($versionNumber - 1, $afterSetSize) + 1;
    $setKey = 'set_' . (string)$setNumber;
    if (!isset($afterSets[$setKey])) {
        $afterSets[$setKey] = [
            'key' => $setKey,
            'number' => $setNumber,
            'label' => 'Set ' . (string)$setNumber,
            'versions' => [],
            'ids' => [],
            'selected_count' => 0,
            'angles' => [],
            'missing_angles' => [],
        ];
    }
    $afterSets[$setKey]['versions'][] = $version;
    $afterSets[$setKey]['ids'][] = (int)$version['id'];
    $photoType = (string)($version['photo_type'] ?? '');
    if ($photoType !== '' && !isset($afterSets[$setKey]['angles'][$photoType])) {
        $afterSets[$setKey]['angles'][$photoType] = $angleDefinitions[$photoType] ?? (smile_design_photo_type_options()[$photoType] ?? $photoType);
    }
}
foreach ($afterSets as $setKey => $set) {
    usort($afterSets[$setKey]['versions'], static function (array $a, array $b) use ($angleSortOrder): int {
        $aAngle = $angleSortOrder[(string)($a['photo_type'] ?? '')] ?? 99;
        $bAngle = $angleSortOrder[(string)($b['photo_type'] ?? '')] ?? 99;
        if ($aAngle !== $bAngle) {
            return $aAngle <=> $bAngle;
        }
        return ((int)($a['version_number'] ?? 0)) <=> ((int)($b['version_number'] ?? 0));
    });
    $afterSets[$setKey]['selected_count'] = count(array_intersect($afterSets[$setKey]['ids'], $activeAfterIds));
    $afterSets[$setKey]['missing_angles'] = array_values(array_diff(array_values($angleDefinitions), array_values($afterSets[$setKey]['angles'])));
}
$activeAfterSetKeys = [];
foreach ($afterSets as $setKey => $set) {
    if ((int)($set['selected_count'] ?? 0) > 0) {
        $activeAfterSetKeys[] = $setKey;
    }
}
$activeSimulationUsesMixedSets = count(array_unique($activeAfterSetKeys)) > 1;
uasort($afterSets, static fn(array $a, array $b): int => (int)$b['number'] <=> (int)$a['number']);

$sectionLinks = [
    ['Source', '#source'],
    ['Generate', '#generate'],
    ['Compare', '#compare'],
    ['Adjustments', '#adjustments'],
    ['Share', '#share'],
    ['Activity', '#activity'],
    ['Versions', '#versions'],
];

smile_design_render_shell_start('Smile Case');
smile_design_page_header((string)$case['patient_name'], 'Phase 1 smile case workspace for before photos, after versions, compare modes, doctor approval, and patient-ready sharing.');
?>

<section class="mb-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Status</p>
        <p class="mt-2 text-xl font-semibold"><?= e(smile_design_status_labels()[(string)$case['status']] ?? (string)$case['status']) ?></p>
        <p class="mt-2 text-sm text-slate-500"><?= e((string)$case['visibility']) ?></p>
    </div>
    <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Source</p>
        <p class="mt-2 text-xl font-semibold"><?= e(count($uploadedBeforePhotos) . ' before photo' . (count($uploadedBeforePhotos) === 1 ? '' : 's')) ?></p>
        <p class="mt-2 text-sm text-slate-500"><?= e((string)$case['procedure_interest']) ?></p>
    </div>
    <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Versions</p>
        <p class="mt-2 text-xl font-semibold"><?= e((string)count($afterVersions)) ?></p>
        <p class="mt-2 text-sm text-slate-500"><?= $selectedAfter ? e('Doctor selected #' . (string)$selectedAfter['version_number']) : 'No doctor-selected version yet' ?></p>
    </div>
    <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Preview Ready</p>
        <p class="mt-2 text-xl font-semibold"><?= $previewAfter ? 'Yes' : 'Not yet' ?></p>
        <p class="mt-2 text-sm text-slate-500"><?= $previewAfter ? e('Patient version #' . (string)$previewAfter['version_number']) : 'Approve one version for patient preview' ?></p>
    </div>
</section>

<nav class="mb-5 flex flex-wrap gap-2 rounded-md border border-slate-200 bg-white p-3 shadow-sm" aria-label="Smile case workspace">
    <?php foreach ($sectionLinks as [$label, $href]): ?>
        <a class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" href="<?= e($href) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <div class="ml-auto flex flex-wrap gap-2">
        <a class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white" href="<?= e(base_url('smile-design/cases/' . $caseId . '/present')) ?>">Present</a>
        <a class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700" href="<?= e($consultRoomCaseUrl) ?>">Consult Room</a>
        <form method="POST" action="<?= e(base_url('app/actions/smile_design_case_delete.php')) ?>" data-confirm="Delete this entire smile case? This will remove before photos, after versions, links, and activity for this case.">
            <?= csrf_input() ?>
            <input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>">
            <button class="rounded-md border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-700" type="submit">Delete Case</button>
        </form>
    </div>
</nav>

<div class="grid gap-5 xl:grid-cols-[1.45fr_0.85fr]">
    <div class="space-y-5">
        <section id="source" class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Source</p>
                    <h2 class="mt-2 text-lg font-semibold">Before photos and intake context</h2>
                </div>
                <p class="text-sm text-slate-500">Internal-only staff intake for doctor review.</p>
            </div>
            <div class="mt-5 grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
                <div>
                    <div class="mb-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-3 text-xs leading-5 text-slate-600">
                        Use a real <span class="font-semibold text-slate-800">Front</span> photo first. Add real <span class="font-semibold text-slate-800">Left 45</span>, <span class="font-semibold text-slate-800">Right 45</span>, and smile close-up photos only when you have them. We are not generating those reference angles with AI.
                    </div>
                    <?php if ($displayBeforePhoto): ?>
                        <img class="aspect-[4/3] w-full rounded-md border border-slate-200 bg-slate-50 object-contain" src="<?= e(smile_design_photo_url((int)$displayBeforePhoto['id'])) ?>" alt="Primary before photo" data-lightbox-src="<?= e(smile_design_photo_url((int)$displayBeforePhoto['id'])) ?>" data-lightbox-alt="Primary before photo">
                        <div class="mt-3 rounded-md border border-slate-200 bg-slate-50 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Primary before photo</p>
                                    <p class="mt-1 text-xs text-slate-500"><?= e(smile_design_photo_type_options()[(string)($displayBeforePhoto['photo_type'] ?? 'front')] ?? 'Front') ?> ? #<?= e((string)$displayBeforePhoto['id']) ?></p>
                                </div>
                                <form method="POST" enctype="multipart/form-data" action="<?= e(base_url('app/actions/smile_design_before_photo_update.php')) ?>" class="flex flex-wrap items-center gap-2">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="photo_id" value="<?= e((string)$displayBeforePhoto['id']) ?>">
                                    <input type="hidden" name="photo_action" value="replace">
                                    <input name="replacement_photo" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" class="block max-w-[220px] rounded-md border border-slate-300 px-3 py-2 text-xs">
                                    <button class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700" type="submit">Replace</button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex aspect-[4/3] items-center justify-center rounded-md border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">No before photo uploaded yet.</div>
                    <?php endif; ?>
                    <?php if ($uploadedBeforePhotos): ?>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <?php foreach ($uploadedBeforePhotos as $photo): ?>
                                <div class="rounded-md border border-slate-200 p-3">
                                    <img class="aspect-square w-full rounded object-cover" src="<?= e(smile_design_photo_url((int)$photo['id'])) ?>" alt="Before photo thumbnail" data-lightbox-src="<?= e(smile_design_photo_url((int)$photo['id'])) ?>" data-lightbox-alt="<?= e(smile_design_photo_type_options()[(string)($photo['photo_type'] ?? 'front')] ?? 'Before') ?> before photo">
                                    <p class="mt-2 text-xs font-semibold text-slate-600"><?= e(smile_design_photo_type_options()[(string)($photo['photo_type'] ?? 'front')] ?? 'Before') ?> Â· #<?= e((string)$photo['id']) ?></p>
                                    <form class="mt-3 grid gap-2" method="POST" enctype="multipart/form-data" action="<?= e(base_url('app/actions/smile_design_before_photo_update.php')) ?>">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="photo_id" value="<?= e((string)$photo['id']) ?>">
                                        <input type="hidden" name="photo_action" value="replace">
                                        <input name="replacement_photo" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" class="block w-full rounded-md border border-slate-300 px-3 py-2 text-xs">
                                        <button class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700" type="submit">Replace</button>
                                    </form>
                                    <form class="mt-2" method="POST" action="<?= e(base_url('app/actions/smile_design_before_photo_update.php')) ?>" data-confirm="Delete this before photo? If it is linked to after versions, delete will be blocked and you should replace it instead.">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="photo_id" value="<?= e((string)$photo['id']) ?>">
                                        <input type="hidden" name="photo_action" value="delete">
                                        <button class="w-full rounded-md border border-rose-300 bg-white px-3 py-2 text-xs font-semibold text-rose-700" type="submit">Delete</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="grid gap-3 text-sm">
                    <form class="rounded-md bg-slate-50 p-4" method="POST" action="<?= e(base_url('app/actions/smile_design_case_update.php')) ?>">
                        <?= csrf_input() ?>
                        <input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-slate-500">Case details and design defaults</p>
                            <button class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700" type="submit">Save Case Details</button>
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                                First name
                                <input name="first_name" value="<?= e((string)($case['first_name'] ?? '')) ?>" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal normal-case tracking-normal text-slate-900">
                            </label>
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                                Last name
                                <input name="last_name" value="<?= e((string)($case['last_name'] ?? '')) ?>" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal normal-case tracking-normal text-slate-900">
                            </label>
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-600 sm:col-span-2">
                                Display name
                                <input name="patient_name" value="<?= e((string)$case['patient_name']) ?>" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal normal-case tracking-normal text-slate-900">
                            </label>
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                                Email
                                <input name="email" type="email" value="<?= e((string)$case['email']) ?>" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal normal-case tracking-normal text-slate-900">
                            </label>
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                                Phone
                                <input name="phone" value="<?= e((string)$case['phone']) ?>" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal normal-case tracking-normal text-slate-900">
                            </label>
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                                Procedure
                                <select name="procedure_interest" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal normal-case tracking-normal text-slate-900" data-case-procedure-select>
                                    <?php foreach (smile_design_procedure_options() as $key => $label): ?>
                                        <option value="<?= e($label) ?>" <?= (string)($case['procedure_interest'] ?? '') === (string)$label ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-600" data-case-lvi-style-field>
                                LVI style
                                <select name="selected_style" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal normal-case tracking-normal text-slate-900">
                                    <?php $currentCaseStyleKey = smile_design_normalize_style_key((string)($case['selected_style'] ?? $case['lvi_style_key'] ?? 'natural')); ?>
                                    <?php foreach (smile_design_style_options() as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= $currentCaseStyleKey === (string)$key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-600" data-case-shade-field>
                                Veneer shade
                                <select name="shade_goal" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal normal-case tracking-normal text-slate-900">
                                    <?php foreach (smile_design_shade_options() as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= (string)$caseShadeDetail['code'] === (string)$key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                                Treatment scope
                                <select name="treatment_scope" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal normal-case tracking-normal text-slate-900">
                                    <?php foreach (smile_design_treatment_scope_options() as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= (string)$caseTreatmentScope === (string)$key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                                Smile width
                                <select name="smile_width_goal" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal normal-case tracking-normal text-slate-900">
                                    <?php foreach (smile_design_smile_width_options() as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= (string)$caseSmileWidthGoal === (string)$key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                    </form>
                    <div class="rounded-md bg-slate-50 p-4">
                        <p class="text-slate-500">Procedure and style</p>
                        <p class="mt-1 font-semibold"><?= e((string)$case['procedure_interest']) ?></p>
                        <?php if ($isLipRepositionOnlyCase): ?>
                            <p class="mt-1 text-slate-600">Lip repositioning only Â· no LVI tooth style</p>
                        <?php else: ?>
                            <p class="mt-1 text-slate-600"><?= e((string)$case['lvi_style_key']) ?> Â· <?= e((string)$case['selected_style']) ?></p>
                            <p class="mt-1 text-slate-600"><?= e((string)$caseShadeDetail['label']) ?> Â· <?= e((string)$caseShadeDetail['title']) ?></p>
                        <?php endif; ?>
                        <p class="mt-1 text-slate-600"><?= e(smile_design_treatment_scope_label((string)$caseTreatmentScope, (string)($case['procedure_interest'] ?? ''))) ?> Ã‚Â· <?= e(smile_design_smile_width_label((string)$caseSmileWidthGoal)) ?></p>
                    </div>
                    <div class="rounded-md bg-slate-50 p-4">
                        <p class="text-slate-500">Notes</p>
                        <p class="mt-1 whitespace-pre-wrap leading-6 text-slate-700"><?= e((string)($case['notes'] ?: 'No intake notes yet.')) ?></p>
                    </div>
                    <div class="rounded-md bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-slate-500">AI case analysis</p>
                                <p class="mt-1 font-semibold">
                                    <?php if (($caseAnalysis['status'] ?? '') === 'completed'): ?>
                                        Ready
                                    <?php elseif (($caseAnalysis['status'] ?? '') === 'processing'): ?>
                                        Processing
                                    <?php elseif (($caseAnalysis['status'] ?? '') === 'failed'): ?>
                                        Failed
                                    <?php else: ?>
                                        Not run yet
                                    <?php endif; ?>
                                </p>
                                <p class="mt-1 text-sm leading-6 text-slate-600"><?= e((string)($caseAnalysis['summary'] ?? 'Run analysis so generation follows the actual dental case instead of a generic makeover.')) ?></p>
                            </div>
                            <form method="POST" action="<?= e(base_url('app/actions/smile_design_ai_analyze.php')) ?>">
                                <?= csrf_input() ?>
                                <input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>">
                                <input type="hidden" name="before_photo_id" value="<?= e((string)($displayBeforePhoto['id'] ?? 0)) ?>">
                                <button class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700" type="submit"><?= in_array((string)($caseAnalysis['status'] ?? ''), ['completed', 'failed', 'processing'], true) ? 'Re-run Analysis' : 'Analyze Case' ?></button>
                            </form>
                        </div>
                        <?php if (!empty($caseAnalysis['analysis']) && is_array($caseAnalysis['analysis'])): ?>
                            <div class="mt-3 grid gap-2 text-xs text-slate-600">
                                <div><span class="font-semibold text-slate-800">Case type:</span> <?= e((string)($caseAnalysis['analysis']['case_type'] ?? '')) ?></div>
                                <div><span class="font-semibold text-slate-800">Clinical direction:</span> <?= e((string)($caseAnalysis['analysis']['clinical_direction'] ?? '')) ?></div>
                                <div><span class="font-semibold text-slate-800">Preview suitability:</span> <?= e((string)($caseAnalysis['analysis']['preview_suitability'] ?? '')) ?></div>
                                <div><span class="font-semibold text-slate-800">Recommended procedure:</span> <?= e((string)($caseAnalysis['analysis']['recommended_procedure'] ?? '')) ?></div>
                                <div><span class="font-semibold text-slate-800">Scope:</span> <?= e((string)($caseAnalysis['analysis']['smile_scope'] ?? '')) ?></div>
                                <div><span class="font-semibold text-slate-800">Missing teeth:</span> <?= e((string)($caseAnalysis['analysis']['missing_or_compromised_teeth'] ?? '')) ?></div>
                                <div><span class="font-semibold text-slate-800">Focus:</span> <?= e((string)($caseAnalysis['analysis']['recommended_generation_focus'] ?? '')) ?></div>
                                <?php if (!empty($caseAnalysis['analysis']['doctor_review_notes']) && is_array($caseAnalysis['analysis']['doctor_review_notes'])): ?>
                                    <div><span class="font-semibold text-slate-800">Doctor review notes:</span> <?= e(implode(' | ', array_map('strval', $caseAnalysis['analysis']['doctor_review_notes']))) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section id="generate" class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
            <?php if ($afterVersions): ?>
                <div>
                    <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Generate</p>
                    <h2 class="mt-2 text-lg font-semibold text-slate-900">Generated previews and corrections</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Review each generated angle and resend corrections from the matching card. Initial generation is complete, so corrections stay here instead of inside Versions.</p>
                </div>
                <details class="mt-4 rounded-md border border-slate-200 bg-slate-50 p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-900">Generate another full set</summary>
                    <form class="mt-4 grid gap-4 md:grid-cols-2 js-ai-submit-form" method="POST" action="<?= e(base_url('app/actions/smile_design_ai_generate.php')) ?>" data-preserve-open-sets>
                        <?= csrf_input() ?><input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>"><input type="hidden" name="return_url" value="<?= e(base_url('smile-design/cases/' . $caseId . '#generate')) ?>">
                        <div class="rounded-md border border-slate-200 bg-white p-4 text-sm md:col-span-2">
                            <p class="font-semibold text-slate-900">Angles that will generate</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <?php foreach ($angleDefinitions as $photoType => $label): ?>
                                    <?php $anglePhoto = $angleBeforePhotos[$photoType] ?? null; ?>
                                    <span class="rounded-full px-3 py-1.5 text-xs font-semibold <?= $anglePhoto ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-500' ?>">
                                        <?= e($label) ?><?= $anglePhoto ? ' #' . e((string)$anglePhoto['id']) : ' missing' ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <label class="block text-sm font-semibold">Version title<input name="version_title" value="After Preview" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
                        <label class="block text-sm font-semibold">Procedure<input name="procedure_label" value="<?= e((string)$case['procedure_interest']) ?>" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
                        <?php if ($isLipRepositionOnlyCase): ?>
                            <input type="hidden" name="lvi_style_key" value="">
                            <input type="hidden" name="shade_goal" value="<?= e((string)$caseShadeDetail['code']) ?>">
                            <input type="hidden" name="treatment_scope" value="<?= e((string)$caseTreatmentScope) ?>">
                            <input type="hidden" name="smile_width_goal" value="<?= e((string)$caseSmileWidthGoal) ?>">
                        <?php else: ?>
                            <label class="block text-sm font-semibold">LVI style<select name="lvi_style_key" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><?php foreach (smile_design_lvi_catalog() as $key => $meta): ?><?php $styleTitle = (string)($meta['title'] ?? $key); ?><option value="<?= e($key) ?>" <?= ((string)($case['selected_style'] ?? '') === (string)$key || (string)($case['lvi_style_key'] ?? '') === (string)$key || (string)($case['lvi_style_key'] ?? '') === 'LVI ' . $styleTitle || (string)($case['lvi_style_key'] ?? '') === $styleTitle) ? 'selected' : '' ?>><?= e($styleTitle) ?> Â· <?= e((string)($meta['category'] ?? 'Style')) ?></option><?php endforeach; ?></select></label>
                            <label class="block text-sm font-semibold">Veneer shade<select name="shade_goal" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><?php foreach (smile_design_shade_options() as $key => $label): ?><option value="<?= e($key) ?>" <?= (string)$caseShadeDetail['code'] === (string)$key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                            <label class="block text-sm font-semibold">Treatment scope<select name="treatment_scope" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><?php foreach (smile_design_treatment_scope_options() as $key => $label): ?><option value="<?= e($key) ?>" <?= (string)$caseTreatmentScope === (string)$key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                            <label class="block text-sm font-semibold">Smile width<select name="smile_width_goal" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><?php foreach (smile_design_smile_width_options() as $key => $label): ?><option value="<?= e($key) ?>" <?= (string)$caseSmileWidthGoal === (string)$key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                        <?php endif; ?>
                        <label class="block text-sm font-semibold md:col-span-2">Custom request<textarea name="custom_request" rows="3" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="Example: upper veneers only, natural white, close small gaps, keep it subtle. Do not change face, hair, skin, lips, or overall identity."></textarea></label>
                        <label class="flex items-center gap-2 text-sm font-semibold md:col-span-2"><input type="checkbox" name="refresh_analysis" value="1" class="h-4 w-4 rounded border-slate-300"> Re-run AI case analysis before generating</label>
                        <label class="block text-sm font-semibold md:col-span-2">Internal notes<textarea name="notes" rows="2" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="Optional note saved on the generated version."></textarea></label>
                        <button class="rounded-md bg-slate-950 px-5 py-3 text-sm font-semibold text-white md:col-span-2 inline-flex items-center justify-center gap-2" type="submit" data-ai-submit-button data-default-label="Generate Available Angles">
                            <span class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" data-ai-spinner></span>
                            <span data-ai-label>Generate Available Angles</span>
                        </button>
                    </form>
                </details>

                <?php $allAngleLipCorrection = $isLipRepositionOnlyCase ? 'Lip repositioning only: make the lip repositioning effect clearly visible. Lower the upper-lip smile line about 2 to 3 mm, up to 4 mm if needed and still natural, so the inferior border of the upper lip sits around the natural arch/cervical top-edge area of the upper teeth. Reduce any broad gummy band by roughly half to two-thirds, while allowing only a tiny natural scalloped gum reveal if needed. Make the upper lip less curled/retracted and more softly unfolded over the gum line; a slight natural increase in superior-lip fullness is expected when the curled lip roll unfolds downward, but do not make it look swollen or filler-like. Preserve the original teeth exactly; do not whiten, straighten, reshape, resize, replace, or redesign any teeth.' : ''; ?>
                <form class="mt-5 grid gap-4 rounded-md border border-slate-200 bg-slate-50 p-4 js-ai-submit-form" method="POST" action="<?= e(base_url('app/actions/smile_design_after_adjust_all.php')) ?>">
                    <?= csrf_input() ?>
                    <input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>">
                    <input type="hidden" name="shade_goal" value="<?= e((string)$caseShadeDetail['code']) ?>">
                    <?php if ($isLipRepositionOnlyCase): ?>
                        <input type="hidden" name="treatment_scope" value="<?= e((string)$caseTreatmentScope) ?>">
                        <input type="hidden" name="smile_width_goal" value="<?= e((string)$caseSmileWidthGoal) ?>">
                    <?php endif; ?>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Apply correction to all generated angles in use</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Creates a new revised after for Front, Left 45, and Right 45 using the same correction note.</p>
                    </div>
                    <?php if (!$isLipRepositionOnlyCase): ?>
                        <label class="block text-sm font-semibold text-slate-900">
                            Treatment scope
                            <select name="treatment_scope" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal">
                                <?php foreach (smile_design_treatment_scope_options() as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= (string)$caseTreatmentScope === (string)$key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-slate-900">
                            Smile width
                            <select name="smile_width_goal" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal">
                                <?php foreach (smile_design_smile_width_options() as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= (string)$caseSmileWidthGoal === (string)$key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <label class="block text-sm font-semibold text-slate-900">
                        Correction for all angles
                        <textarea name="adjustment_request" rows="3" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal" placeholder="Describe the correction to apply to every generated angle."><?= e($allAngleLipCorrection) ?></textarea>
                    </label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="refresh_analysis" value="1" class="h-4 w-4 rounded border-slate-300"> Re-run AI case analysis before all-angle correction</label>
                    <button class="rounded-md bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white inline-flex items-center justify-center gap-2" type="submit" data-ai-submit-button data-default-label="Resend Correction to All Angles">
                        <span class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" data-ai-spinner></span>
                        <span data-ai-label>Resend Correction to All Angles</span>
                    </button>
                </form>

                <div class="mt-5 rounded-md border border-slate-200 bg-white p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Working simulation loaded in Compare</p>
                            <p class="mt-1 text-sm leading-6 text-slate-600">These doctor-selected angles are the set the viewer uses. Change the active set below; inactive sets stay collapsed.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <?php if ($activeSimulationUsesMixedSets): ?>
                                <form method="POST" action="<?= e(base_url('app/actions/smile_design_after_set_clone_active.php')) ?>" data-preserve-open-sets>
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>">
                                    <input type="hidden" name="return_url" value="<?= e(base_url('smile-design/cases/' . $caseId . '#generate')) ?>">
                                    <button class="rounded-md bg-slate-950 px-3 py-2 text-xs font-semibold text-white" type="submit">Create Clean Set</button>
                                </form>
                            <?php endif; ?>
                            <a class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700" href="#compare">Jump to Compare</a>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3 md:grid-cols-3">
                        <?php foreach ($workingSimulationAngles as $photoType => $item): ?>
                            <?php $workingAfter = $item['after']; ?>
                            <div class="rounded-md border <?= $workingAfter ? 'border-slate-300 bg-slate-50' : 'border-dashed border-slate-300 bg-white' ?> p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500"><?= e((string)$item['label']) ?></p>
                                <?php if ($workingAfter): ?>
                                    <img class="mt-2 h-24 w-full rounded-md bg-white object-contain" src="<?= e(smile_design_after_url((int)$workingAfter['id'])) ?>" alt="<?= e((string)$item['label']) ?> selected after" data-lightbox-src="<?= e(smile_design_after_url((int)$workingAfter['id'])) ?>" data-lightbox-alt="<?= e((string)$item['label']) ?> selected after">
                                    <p class="mt-2 text-sm font-semibold text-slate-900">#<?= e((string)$workingAfter['version_number']) ?> <?= e((string)$workingAfter['version_title']) ?></p>
                                    <p class="mt-1 text-xs text-emerald-700">Selected for simulation</p>
                                <?php else: ?>
                                    <div class="mt-2 flex h-24 items-center justify-center rounded-md bg-slate-100 text-xs font-semibold text-slate-500">No after selected</div>
                                    <p class="mt-2 text-sm text-slate-600">Generate or select an after for this angle.</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    <?php $firstSetKey = array_key_first($afterSets); ?>
                    <?php foreach ($afterSets as $setKey => $set): ?>
                        <?php
                        $setPanelId = 'after-set-panel-' . (string)$set['number'];
                        $setLabelId = 'after-set-label-' . (string)$set['number'];
                        $setSelectedCount = (int)$set['selected_count'];
                        $setVersionCount = count($set['versions']);
                        $setIsActive = $setSelectedCount > 0;
                        $setIsFullyActive = $setVersionCount > 0 && $setSelectedCount === $setVersionCount;
                        $setStartsOpen = false;
                        ?>
                        <section class="rounded-md border <?= $setIsActive ? 'border-slate-400 bg-slate-50' : 'border-slate-200 bg-white' ?> p-4">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-semibold text-slate-900"><?= e((string)$set['label']) ?></p>
                                        <?php if ($setIsFullyActive): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-950 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-white">
                                                <svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"></path></svg>
                                                In use
                                            </span>
                                        <?php elseif ($setIsActive): ?>
                                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-amber-800">Part of active set</span>
                                        <?php endif; ?>
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-600"><?= e((string)$setVersionCount) ?>/<?= e((string)count($angleDefinitions)) ?> angles</span>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <?php foreach ($set['versions'] as $setVersion): ?>
                                            <?php $isAngleSelected = (int)($setVersion['selected_by_doctor'] ?? 0) === 1; ?>
                                            <span class="inline-flex items-center gap-1 rounded-full <?= $isAngleSelected ? 'bg-emerald-100 text-emerald-800' : 'bg-white text-slate-600' ?> px-2.5 py-1 text-xs font-semibold">
                                                <?php if ($isAngleSelected): ?><svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"></path></svg><?php endif; ?>
                                                #<?= e((string)$setVersion['version_number']) ?> <?= e(smile_design_photo_type_options()[(string)$setVersion['photo_type']] ?? (string)$setVersion['photo_type']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php foreach ($set['missing_angles'] as $missingAngle): ?>
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-400"><?= e((string)$missingAngle) ?> missing</span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="<?= e(base_url('app/actions/smile_design_after_set_select.php')) ?>" data-preserve-open-sets>
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>">
                                        <input type="hidden" name="return_url" value="<?= e(base_url('smile-design/cases/' . $caseId . '#generate')) ?>">
                                        <?php foreach ($set['ids'] as $setVersionId): ?>
                                            <input type="hidden" name="after_version_ids[]" value="<?= e((string)$setVersionId) ?>">
                                        <?php endforeach; ?>
                                        <button class="inline-flex items-center gap-2 rounded-md <?= $setIsFullyActive ? 'border border-slate-300 bg-white text-slate-500' : 'bg-slate-950 text-white' ?> px-3 py-2 text-xs font-semibold" type="submit" <?= $setIsFullyActive ? 'disabled' : '' ?>>
                                            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"></path></svg>
                                            <?= $setIsFullyActive ? 'Using This Set' : 'Use This Set' ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="<?= e(base_url('app/actions/smile_design_after_set_delete.php')) ?>" data-preserve-open-sets data-confirm="Delete this generated set? This removes every after preview in this set. If this is the only set, the case will return to the original Generate step.">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>">
                                        <input type="hidden" name="return_url" value="<?= e(base_url('smile-design/cases/' . $caseId . '#generate')) ?>">
                                        <?php foreach ($set['ids'] as $setVersionId): ?>
                                            <input type="hidden" name="after_version_ids[]" value="<?= e((string)$setVersionId) ?>">
                                        <?php endforeach; ?>
                                        <button class="rounded-md border border-rose-300 bg-white px-3 py-2 text-xs font-semibold text-rose-700 hover:border-rose-400" type="submit">Delete Set</button>
                                    </form>
                                    <button type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700" data-toggle-target="<?= e($setPanelId) ?>" data-toggle-label="<?= e($setLabelId) ?>" aria-expanded="<?= $setStartsOpen ? 'true' : 'false' ?>" aria-controls="<?= e($setPanelId) ?>">
                                        <span id="<?= e($setLabelId) ?>"><?= $setStartsOpen ? 'Collapse' : 'Expand' ?></span>
                                    </button>
                                </div>
                            </div>
                            <div id="<?= e($setPanelId) ?>" class="<?= $setStartsOpen ? '' : 'hidden' ?> mt-4 grid gap-4 md:grid-cols-2 2xl:grid-cols-3" data-after-set-panel data-after-set-key="<?= e($setKey) ?>">
                                <?php foreach ($set['versions'] as $version): ?>
                                    <?php
                                    [$label, $disclaimer] = smile_design_after_label($version);
                                    $versionPhotoType = (string)($version['photo_type'] ?? 'front');
                                    $versionAngleLabel = smile_design_photo_type_options()[$versionPhotoType] ?? $versionPhotoType;
                                    $isVersionSelected = (int)$version['selected_by_doctor'] === 1;
                                    ?>
                                    <article class="relative rounded-md border <?= $isVersionSelected ? 'border-slate-400 bg-white' : 'border-slate-200 bg-white' ?> p-4">
                                        <?php if ($isVersionSelected): ?>
                                            <span class="absolute right-3 top-3 inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-950 text-white shadow-sm" title="Selected for simulation">
                                                <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"></path></svg>
                                            </span>
                                        <?php endif; ?>
                                        <img class="h-44 w-full rounded-md bg-slate-100 object-contain" src="<?= e(smile_design_after_url((int)$version['id'])) ?>" alt="After version" data-lightbox-src="<?= e(smile_design_after_url((int)$version['id'])) ?>" data-lightbox-alt="#<?= e((string)$version['version_number']) ?> <?= e((string)$version['version_title']) ?>">
                                        <div class="mt-3 flex items-start justify-between gap-3">
                                            <div class="flex min-w-0 items-start gap-2">
                                                <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded border <?= $isVersionSelected ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-400' ?>" title="<?= $isVersionSelected ? 'Selected for simulation' : 'Not selected' ?>">
                                                    <?php if ($isVersionSelected): ?><svg aria-hidden="true" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"></path></svg><?php endif; ?>
                                                </span>
                                                <div class="min-w-0">
                                                <p class="text-sm font-semibold">#<?= e((string)$version['version_number']) ?> <?= e((string)$version['version_title']) ?></p>
                                                <p class="mt-1 text-xs text-slate-500"><?= e($label) ?> Â· <?= e(smile_design_source_type_labels()[(string)$version['source_type']] ?? (string)$version['source_type']) ?></p>
                                                <p class="mt-1 text-xs text-slate-500"><?= $isLipRepositionOnlyCase ? 'No LVI tooth style' : e((string)$version['lvi_style_key']) ?> Â· <?= e($versionAngleLabel) ?></p>
                                                </div>
                                            </div>
                                            <div class="flex flex-col items-end gap-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
                                                <?php if ((string)($version['source_type'] ?? '') === 'actual_clinical_after'): ?><span class="rounded-full bg-blue-100 px-2 py-1 text-blue-700">Actual Result</span><?php endif; ?>
                                                <?php if ((int)$version['approved_for_patient_preview'] === 1): ?><span class="rounded-full bg-emerald-100 px-2 py-1 text-emerald-700">Preview</span><?php endif; ?>
                                                <?php if ((int)$version['approved_for_office_gallery'] === 1): ?><span class="rounded-full bg-violet-100 px-2 py-1 text-violet-700">Gallery</span><?php endif; ?>
                                                <?php if ((int)$version['archived'] === 1): ?><span class="rounded-full bg-amber-100 px-2 py-1 text-amber-700">Archived</span><?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($version['notes'])): ?><p class="mt-3 text-sm leading-6 text-slate-600"><?= e((string)$version['notes']) ?></p><?php endif; ?>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <?php foreach ([['archive', (int)$version['archived'] === 1 ? 'Archived' : 'Archive'], ['delete', 'Delete']] as [$action, $text]): ?>
                                                <form method="POST" action="<?= e(base_url('app/actions/smile_design_after_update.php')) ?>">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="after_version_id" value="<?= e((string)$version['id']) ?>">
                                                    <input type="hidden" name="after_action" value="<?= e($action) ?>">
                                                    <button class="rounded-md border <?= $action === 'delete' ? 'border-rose-300 text-rose-700' : 'border-slate-300 text-slate-700' ?> px-2 py-1.5 text-xs font-semibold" type="submit" <?= $action === 'delete' ? 'onclick="return confirm(\'Delete this after version? This cannot be undone.\')"' : '' ?> <?= $action === 'archive' && (int)$version['archived'] === 1 ? 'disabled' : '' ?>><?= e($text) ?></button>
                                                </form>
                                            <?php endforeach; ?>
                                        </div>
                                        <a
                                            class="mt-4 inline-flex w-full items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700"
                                            href="<?= e(base_url('smile-design/adjust?case_id=' . $caseId . '&version_id=' . (int)$version['id'])) ?>">
                                            Open fullscreen edit
                                        </a>
                                        <p class="mt-3 text-xs leading-5 text-slate-500"><?= e($disclaimer) ?></p>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
            <form class="grid gap-4 md:grid-cols-2 js-ai-submit-form" method="POST" action="<?= e(base_url('app/actions/smile_design_ai_generate.php')) ?>" data-preserve-open-sets>
                <?= csrf_input() ?><input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>"><input type="hidden" name="return_url" value="<?= e(base_url('smile-design/cases/' . $caseId . '#generate')) ?>">
                <div class="md:col-span-2">
                    <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Generate</p>
                    <h2 class="mt-2 text-lg font-semibold text-slate-900">Generate After Preview</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">OpenAI analyzes the case first, then Gemini renders one after preview per uploaded angle: Front first, then Left 45 and Right 45 when those real photos are present.</p>
                </div>
                <div class="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm md:col-span-2">
                    <p class="font-semibold text-slate-900">Angles that will generate</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <?php foreach ($angleDefinitions as $photoType => $label): ?>
                            <?php $anglePhoto = $angleBeforePhotos[$photoType] ?? null; ?>
                            <span class="rounded-full px-3 py-1.5 text-xs font-semibold <?= $anglePhoto ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-500' ?>">
                                <?= e($label) ?><?= $anglePhoto ? ' #' . e((string)$anglePhoto['id']) : ' missing' ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-3 text-xs leading-5 text-slate-500">Only uploaded real angles are sent. Generated afters are saved with the same angle so Compare can switch the matching before/after pair.</p>
                </div>
                <label class="block text-sm font-semibold">Version title<input name="version_title" value="After Preview" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
                <label class="block text-sm font-semibold">Procedure<input name="procedure_label" value="<?= e((string)$case['procedure_interest']) ?>" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
                <label class="block text-sm font-semibold">Treatment scope<select name="treatment_scope" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><?php foreach (smile_design_treatment_scope_options() as $key => $label): ?><option value="<?= e($key) ?>" <?= (string)$caseTreatmentScope === (string)$key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                <label class="block text-sm font-semibold">Smile width<select name="smile_width_goal" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><?php foreach (smile_design_smile_width_options() as $key => $label): ?><option value="<?= e($key) ?>" <?= (string)$caseSmileWidthGoal === (string)$key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                <?php if ($isLipRepositionOnlyCase): ?>
                    <input type="hidden" name="lvi_style_key" value="">
                    <input type="hidden" name="shade_goal" value="<?= e((string)$caseShadeDetail['code']) ?>">
                <?php else: ?>
                    <label class="block text-sm font-semibold">LVI style<select name="lvi_style_key" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><?php foreach (smile_design_lvi_catalog() as $key => $meta): ?><?php $styleTitle = (string)($meta['title'] ?? $key); ?><option value="<?= e($key) ?>" <?= ((string)($case['selected_style'] ?? '') === (string)$key || (string)($case['lvi_style_key'] ?? '') === (string)$key || (string)($case['lvi_style_key'] ?? '') === 'LVI ' . $styleTitle || (string)($case['lvi_style_key'] ?? '') === $styleTitle) ? 'selected' : '' ?>><?= e($styleTitle) ?> Â· <?= e((string)($meta['category'] ?? 'Style')) ?></option><?php endforeach; ?></select></label>
                    <label class="block text-sm font-semibold">Veneer shade<select name="shade_goal" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><?php foreach (smile_design_shade_options() as $key => $label): ?><option value="<?= e($key) ?>" <?= (string)$caseShadeDetail['code'] === (string)$key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                <?php endif; ?>
                <label class="block text-sm font-semibold md:col-span-2">Custom request<textarea name="custom_request" rows="3" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="Example: upper veneers only, natural white, close small gaps, keep it subtle. Do not change face, hair, skin, lips, or overall identity."></textarea></label>
                <label class="flex items-center gap-2 text-sm font-semibold md:col-span-2"><input type="checkbox" name="refresh_analysis" value="1" class="h-4 w-4 rounded border-slate-300"> Re-run AI case analysis before generating</label>
                <label class="block text-sm font-semibold md:col-span-2">Internal notes<textarea name="notes" rows="2" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="Optional note saved on the generated version."></textarea></label>
                <button class="rounded-md bg-slate-950 px-5 py-3 text-sm font-semibold text-white md:col-span-2 inline-flex items-center justify-center gap-2" type="submit" data-ai-submit-button data-default-label="Generate Available Angles">
                    <span class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" data-ai-spinner></span>
                    <span data-ai-label>Generate Available Angles</span>
                </button>
            </form>
            <?php endif; ?>
        </section>

        <section id="compare" class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Compare</p>
                    <h2 class="mt-2 text-lg font-semibold">Review source and selected result</h2>
                </div>
                <p class="text-sm text-slate-500">Viewer modes: Input, Result, Compare, B/A, Opacity, Zoom, and Video.</p>
            </div>
            <div class="mt-5 overflow-hidden rounded-md bg-black p-3">
                <?php smile_before_after_viewer($beforeUrl, $afterUrl, [
                    'title' => (string)$case['patient_name'],
                    'mode' => 'ba',
                    'alignment' => $alignment,
                    'alignment_edit' => $alignmentEdit,
                    'input_gallery' => $inputGallery,
                    'video_url' => $latestRevealVideoUrl,
                    'video_generate' => $selectedRevealAnglesReady ? [
                        'action' => base_url('app/actions/smile_design_reveal_video_generate.php'),
                        'loading_label' => $latestRevealVideo ? 'Re-generating smile reveal video...' : 'Generating smile reveal video...',
                        'hidden' => [
                            'case_id' => $caseId,
                            'return_url' => base_url('smile-design/cases/' . $caseId . '#compare'),
                        ],
                    ] : [],
                    'video_delete' => $latestRevealVideo ? [
                        'action' => base_url('app/actions/smile_design_reveal_video_delete.php'),
                        'hidden' => [
                            'case_id' => $caseId,
                            'video_id' => (int)$latestRevealVideo['id'],
                            'return_url' => base_url('smile-design/cases/' . $caseId . '#compare'),
                        ],
                    ] : [],
                ]); ?>
                <?php if ($latestRevealVideo): ?>
                    <div class="mt-3 rounded-md border border-white/10 bg-white/5 px-3 py-2 text-xs leading-5 text-white/70">
                        Latest reveal video: <span class="font-semibold text-white"><?= e((string)($latestRevealVideo['video_title'] ?? 'Smile Reveal Video')) ?></span>
                        <?php if (!empty($latestRevealVideo['created_at'])): ?> &middot; <?= e(format_datetime((string)$latestRevealVideo['created_at'])) ?><?php endif; ?>
                    </div>
                <?php elseif (!$selectedRevealAnglesReady): ?>
                    <div class="mt-3 rounded-md border border-amber-300/30 bg-amber-500/10 px-3 py-2 text-xs leading-5 text-amber-100">
                        Select generated after images for Front, Left 45, and Right 45 to enable the reveal video button.
                    </div>
                <?php endif; ?>
                <p class="mt-3 px-1 text-xs leading-5 text-white/65">Internal smile design preview. Use alignment controls to keep the doctor-selected version presentation-ready.</p>
            </div>
        </section>

        <section id="adjustments" class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Adjustments</p>
                    <h2 class="mt-2 text-lg font-semibold">Doctor approval and presentation prep</h2>
                </div>
                <p class="text-sm text-slate-500">Phase 1 keeps adjustments simple: choose the best version, align it well, then approve it for patient preview.</p>
            </div>
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                <div class="rounded-md bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-900">Doctor-selected version</p>
                    <p class="mt-2 text-sm text-slate-600"><?= $selectedAfter ? e('#' . (string)$selectedAfter['version_number'] . ' ' . (string)$selectedAfter['version_title']) : 'No version selected yet.' ?></p>
                </div>
                <div class="rounded-md bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-900">Patient preview version</p>
                    <p class="mt-2 text-sm text-slate-600"><?= $previewAfter ? e('#' . (string)$previewAfter['version_number'] . ' ' . (string)$previewAfter['version_title']) : 'No patient preview version approved yet.' ?></p>
                </div>
                <div class="rounded-md bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-900">Alignment workflow</p>
                    <p class="mt-2 text-sm text-slate-600">Use the viewerâ€™s alignment controls below Compare to refine framing before presenting or sharing.</p>
                </div>
            </div>
        </section>
    </div>

    <div class="space-y-5">
        <section id="share" class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Share</p>
            <h2 class="mt-2 text-lg font-semibold">Open patient preview link</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Keep one branded public preview link on for the patient, copy it to your phone, or scan the QR code when you are ready to text or share it.</p>
            <?php if ($activePreviewUrl): ?>
                <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/70 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Open link is on</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.16em] text-emerald-700">Active</p>
                        </div>
                        <form method="POST" action="<?= e(base_url('app/actions/smile_design_preview_link.php')) ?>">
                            <?= csrf_input() ?>
                            <input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>">
                            <input type="hidden" name="preview_action" value="disable">
                            <button class="inline-flex items-center gap-2 rounded-full border border-emerald-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-emerald-800 shadow-sm" type="submit" role="switch" aria-checked="true">
                                <span class="relative h-5 w-9 rounded-full bg-emerald-500"><span class="absolute right-0.5 top-0.5 h-4 w-4 rounded-full bg-white shadow-sm"></span></span>
                                <span>On</span>
                            </button>
                        </form>
                    </div>
                    <div class="mt-4 grid gap-4 xl:grid-cols-[1fr_150px]">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">Actual preview link</label>
                            <div class="mt-2 flex gap-2">
                                <input id="active-preview-link" readonly value="<?= e($activePreviewUrl) ?>" class="min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900">
                                <button type="button" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700" data-copy-target="active-preview-link" title="Copy link">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                </button>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white" href="<?= e($activePreviewUrl) ?>" target="_blank" rel="noopener">Open Preview</a>
                                <form method="POST" action="<?= e(base_url('app/actions/smile_design_preview_link.php')) ?>" class="inline">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>">
                                    <input type="hidden" name="preview_action" value="delete">
                                    <input type="hidden" name="preview_link_id" value="<?= e((string)$activePreviewLink['id']) ?>">
                                    <button class="rounded-md border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-700" type="submit" >Delete Link</button>
                                </form>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-500">
                                <div>Views: <span class="font-semibold text-slate-700"><?= e((string)($activePreviewLink['view_count'] ?? 0)) ?></span></div>
                                <div>Expires: <span class="font-semibold text-slate-700"><?= e(!empty($activePreviewLink['expires_at']) ? format_datetime((string)$activePreviewLink['expires_at']) : 'No expiry') ?></span></div>
                                <div>Last viewed: <span class="font-semibold text-slate-700"><?= e(!empty($activePreviewLink['last_viewed_at']) ? format_datetime((string)$activePreviewLink['last_viewed_at']) : 'Not yet') ?></span></div>
                                <div>Created: <span class="font-semibold text-slate-700"><?= e(format_datetime((string)$activePreviewLink['created_at'])) ?></span></div>
                            </div>
                        </div>
                        <?php if ($activePreviewQrUrl): ?>
                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                <p class="text-center text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">QR Code</p>
                                <img class="mx-auto mt-2 h-32 w-32 rounded-md border border-slate-200 bg-white p-1" src="<?= e($activePreviewQrUrl) ?>" alt="QR code for preview link">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($hasLegacyActivePreview): ?>
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/70 p-4">
                    <p class="text-sm font-semibold text-slate-900">Legacy open link found</p>
                    <p class="mt-1 text-sm text-slate-600">This case has an older active link from before reusable preview URLs were added. Switch it on once to refresh it into the new copyable format with QR support.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <form method="POST" action="<?= e(base_url('app/actions/smile_design_preview_link.php')) ?>">
                            <?= csrf_input() ?>
                            <input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>">
                            <button class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 shadow-sm" type="submit" role="switch" aria-checked="false">
                                <span class="relative h-5 w-9 rounded-full bg-slate-300"><span class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white shadow-sm"></span></span>
                                <span>Off</span>
                            </button>
                        </form>
                        <form method="POST" action="<?= e(base_url('app/actions/smile_design_preview_link.php')) ?>">
                            <?= csrf_input() ?>
                            <input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>">
                            <input type="hidden" name="preview_action" value="disable">
                            <button class="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700" type="submit">Disable old link</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Open link is off</p>
                            <p class="mt-1 text-sm text-slate-600">Switch it on when the patient preview version is ready and you want one shareable public link.</p>
                        </div>
                        <form method="POST" action="<?= e(base_url('app/actions/smile_design_preview_link.php')) ?>">
                            <?= csrf_input() ?>
                            <input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>">
                            <button class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 shadow-sm" type="submit" role="switch" aria-checked="false">
                                <span class="relative h-5 w-9 rounded-full bg-slate-300"><span class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white shadow-sm"></span></span>
                                <span>Off</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <div class="mt-5 space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Link history</p>
                <?php foreach ($previewLinks as $link): ?>
                    <?php
                    $isRevoked = !empty($link['revoked_at']);
                    $isExpired = !empty($link['expires_at']) && strtotime((string)$link['expires_at']) < time();
                    ?>
                    <div class="rounded-md border border-slate-200 p-3 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold"><?= $isRevoked ? 'Disabled preview link' : 'Preview link issued' ?></p>
                                <p class="mt-1 text-slate-500">Created <?= e(format_datetime((string)$link['created_at'])) ?></p>
                            </div>
                            <span class="rounded-full px-2 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] <?= $isRevoked ? 'bg-amber-100 text-amber-700' : ($isExpired ? 'bg-slate-200 text-slate-700' : 'bg-emerald-100 text-emerald-700') ?>">
                                <?= $isRevoked ? 'Off' : ($isExpired ? 'Expired' : 'Active') ?>
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-500">
                            <div>Views: <span class="font-semibold text-slate-700"><?= e((string)($link['view_count'] ?? 0)) ?></span></div>
                            <div>Expires: <span class="font-semibold text-slate-700"><?= e(!empty($link['expires_at']) ? format_datetime((string)$link['expires_at']) : 'No expiry') ?></span></div>
                            <div>Last viewed: <span class="font-semibold text-slate-700"><?= e(!empty($link['last_viewed_at']) ? format_datetime((string)$link['last_viewed_at']) : 'Not yet') ?></span></div>
                            <div>Purpose: <span class="font-semibold text-slate-700"><?= e((string)$link['purpose']) ?></span></div>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <form method="POST" action="<?= e(base_url('app/actions/smile_design_preview_link.php')) ?>" data-confirm="Delete this preview link?">
                                <?= csrf_input() ?>
                                <input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>">
                                <input type="hidden" name="preview_action" value="delete">
                                <input type="hidden" name="preview_link_id" value="<?= e((string)$link['id']) ?>">
                                <button class="rounded-md border border-rose-300 bg-white px-3 py-2 text-xs font-semibold text-rose-700" type="submit" >Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$previewLinks): ?><p class="text-sm text-slate-500">No preview links created yet.</p><?php endif; ?>
            </div>
        </section>

        <section id="activity" class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
            <details>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Activity</p>
                        <h2 class="mt-2 text-lg font-semibold">Case timeline</h2>
                    </div>
                    <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Expand / Collapse</span>
                </summary>
            <div class="mt-4 space-y-3">
                <?php foreach ($activity as $event): ?>
                    <?php
                    $payload = json_decode((string)($event['payload_json'] ?? ''), true);
                    $payloadSummary = '';
                    if (is_array($payload) && $payload) {
                        $pairs = [];
                        foreach (array_slice($payload, 0, 3, true) as $key => $value) {
                            $pairs[] = $key . ': ' . (is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_SLASHES));
                        }
                        $payloadSummary = implode(' Â· ', $pairs);
                    }
                    ?>
                    <div class="rounded-md border border-slate-200 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold"><?= e(str_replace('_', ' ', (string)$event['event_key'])) ?></p>
                                <p class="mt-1 text-xs text-slate-500"><?= e((string)($event['user_name'] ?: 'System')) ?> Â· <?= e(format_datetime((string)$event['created_at'])) ?></p>
                            </div>
                        </div>
                        <?php if ($payloadSummary !== ''): ?><p class="mt-2 text-xs leading-5 text-slate-500"><?= e($payloadSummary) ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$activity): ?><p class="text-sm text-slate-500">No activity recorded yet.</p><?php endif; ?>
            </div>


            </details>
        </section>

                <section id="versions" class="mt-5 rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                    <button type="button" class="flex w-full items-center justify-between gap-3 text-left" data-toggle-target="versions-panel" data-toggle-label="versions-toggle-label" aria-expanded="false" aria-controls="versions-panel">
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Versions</p>
                            <h2 class="mt-2 text-lg font-semibold">Late-stage afters and revisions</h2>
                            <p class="mt-2 text-sm text-slate-500">Open this only when the patient moves forward later and you need a real after, revision, or archive work.</p>
                        </div>
                        <span id="versions-toggle-label" class="shrink-0 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Expand</span>
                    </button>
                    <div id="versions-panel" class="mt-5 hidden">

                            <form class="mt-5 grid gap-4 rounded-md border border-slate-200 bg-slate-50 p-4 md:grid-cols-2" method="POST" enctype="multipart/form-data" action="<?= e(base_url('app/actions/smile_design_after_upload.php')) ?>">
                                <?= csrf_input() ?><input type="hidden" name="case_id" value="<?= e((string)$caseId) ?>">
                                <div class="md:col-span-2">
                                    <p class="text-sm font-semibold text-slate-900">Upload Real After</p>
                                    <p class="mt-1 text-sm leading-6 text-slate-600">Use this after treatment is completed. Attach the real clinical after to the matching before angle so it can be used on the customer link and in the office gallery.</p>
                                </div>
                                <input type="hidden" name="source_type" value="actual_clinical_after">
                                <label class="block text-sm font-semibold">Match before photo<select name="before_photo_id" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><?php foreach ($uploadedBeforePhotos as $photo): ?><option value="<?= e((string)$photo['id']) ?>"><?= e(smile_design_photo_type_options()[(string)($photo['photo_type'] ?? 'front')] ?? 'Front') ?> Â· #<?= e((string)$photo['id']) ?></option><?php endforeach; ?></select></label>
                                <label class="block text-sm font-semibold">Photo type / angle<select name="photo_type" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><?php foreach (smile_design_photo_type_options() as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                                <label class="block text-sm font-semibold">Version title<input name="version_title" value="Real After" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
                                <label class="block text-sm font-semibold">Procedure<input name="procedure_label" value="<?= e((string)$case['procedure_interest']) ?>" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></label>
                                <?php if ($isLipRepositionOnlyCase): ?>
                                    <input type="hidden" name="lvi_style_key" value="">
                                <?php else: ?>
                                    <label class="block text-sm font-semibold">LVI style<select name="lvi_style_key" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><?php foreach (smile_design_lvi_catalog() as $key => $meta): ?><option value="<?= e($key) ?>" <?= (string)$case['lvi_style_key'] === (string)$key ? 'selected' : '' ?>><?= e((string)($meta['key'] ?? $key)) ?> Â· <?= e((string)($meta['name'] ?? $key)) ?></option><?php endforeach; ?></select></label>
                                <?php endif; ?>
                                <label class="block text-sm font-semibold md:col-span-2">After image<input required name="after_photo" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2"></label>
                                <label class="block text-sm font-semibold md:col-span-2">Notes<textarea name="notes" rows="3" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="Completed treatment note, date, shade, or any clinical context."></textarea></label>
                                <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="approve_preview" value="1" class="h-4 w-4 rounded border-slate-300"> Use on customer link</label>
                                <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="approve_gallery" value="1" class="h-4 w-4 rounded border-slate-300"> Use in office gallery</label>
                                <button class="rounded-md bg-slate-950 px-5 py-3 text-sm font-semibold text-white md:col-span-2" type="submit">Upload Real After</button>
                            </form>

                            <div class="mt-5 rounded-md border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-600">
                                Generated AI previews and correction forms now live in the Generate section above. Use Versions only for completed real after uploads, archiving, and late-stage records.
                            </div>
                        </div>
                    </div>
                </section>

    </div>
</div>



<div id="image-lightbox" class="fixed inset-0 z-[70] hidden bg-slate-950/90 p-4">
    <button id="image-lightbox-close" type="button" class="absolute right-4 top-4 inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/25 bg-white/10 text-lg font-semibold text-white shadow-2xl" aria-label="Close image preview">X</button>
    <div class="flex h-full w-full items-center justify-center">
        <img id="image-lightbox-image" class="max-h-[92vh] max-w-[92vw] rounded-md bg-white object-contain shadow-2xl" src="" alt="">
    </div>
</div>

<div id="confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/55 px-4">
    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
        <h3 class="text-lg font-semibold text-slate-950">Please confirm</h3>
        <p id="confirm-modal-message" class="mt-3 text-sm leading-6 text-slate-600">Are you sure?</p>
        <div class="mt-6 flex justify-end gap-3">
            <button id="confirm-modal-cancel" type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
            <button id="confirm-modal-continue" type="button" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Delete</button>
        </div>
    </div>
</div>

<style>
#adjust-fullscreen-modal .adjust-overlay-anchor-layer {
    pointer-events: none;
}
#adjust-fullscreen-modal .adjust-overlay-anchor-layer button {
    border: 2px solid rgba(255, 255, 255, 0.95);
    border-radius: 999px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.25);
    color: #fff;
    display: grid;
    font-size: 12px;
    font-weight: 700;
    line-height: 1;
    place-items: center;
    transform: translate(-50%, -50%);
    transition: transform 120ms ease;
}
#adjust-fullscreen-modal .adjust-overlay-anchor-layer .adjust-anchor-point {
    position: absolute;
}
#adjust-fullscreen-modal .adjust-overlay-anchor-layer .adjust-anchor-point[data-editable="true"] {
    pointer-events: auto;
    cursor: grab;
}
#adjust-fullscreen-modal .adjust-overlay-anchor-layer .adjust-anchor-point[data-editable="true"].active {
    cursor: grabbing;
    transform: translate(-50%, -50%) scale(1.15);
}
#adjust-fullscreen-modal .adjust-anchor-path {
    pointer-events: none;
    mix-blend-mode: screen;
}
#adjust-fullscreen-modal .adjust-mask-fill {
    pointer-events: none;
    mix-blend-mode: multiply;
}
</style>

<div id="adjust-fullscreen-modal" class="fixed inset-0 z-[80] hidden overflow-hidden bg-slate-950/90 p-3 lg:p-5">
    <div class="mx-auto flex h-full w-full max-w-7xl flex-col overflow-hidden rounded-2xl border border-white/20 bg-white shadow-2xl">
        <div class="flex flex-wrap items-center justify-between border-b border-slate-200 px-4 py-3">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Adjust and resend</p>
                <h2 class="mt-0.5 text-lg font-semibold text-slate-900" id="adjust-modal-title">Edit preview version</h2>
            </div>
            <div class="flex items-center gap-2">
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700" id="adjust-modal-badge">Version #0</span>
                <button id="adjust-modal-close" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 bg-white text-sm font-semibold text-slate-700" aria-label="Close adjust editor">×</button>
            </div>
        </div>
        <form id="adjust-fullscreen-form" class="js-ai-submit-form min-h-0 flex-1 p-4 md:pt-3" method="POST" action="<?= e(base_url('app/actions/smile_design_after_adjust.php')) ?>" data-preserve-open-sets>
            <?= csrf_input() ?>
            <input type="hidden" name="after_version_id" value="">
            <input type="hidden" name="before_photo_id" value="">
            <input type="hidden" name="procedure_label" value="">
            <input type="hidden" name="lvi_style_key" value="">
            <input type="hidden" name="shade_goal" value="">
            <input type="hidden" name="photo_type" value="">
            <input type="hidden" name="return_url" value="<?= e(base_url('smile-design/cases/' . $caseId . '#compare')) ?>">
            <input type="hidden" name="shape_scale_delta" value="0">
            <input type="hidden" name="smile_length_delta" value="0">
            <input type="hidden" name="smile_width_delta" value="0">
            <input type="hidden" name="shade_brightness_delta" value="0">
            <input type="hidden" name="anchor_points" value="">
            <input type="hidden" name="precision_mode" value="balanced">
            <div class="grid min-h-0 gap-3 lg:grid-cols-[300px_minmax(0,1fr)]">
                <aside class="flex min-h-0 flex-col justify-between rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="space-y-4">
                        <section>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Quick settings</p>
                            <div class="mt-3 space-y-3">
                                <label class="block text-sm font-semibold text-slate-900">
                                    New version title
                                    <input name="version_title" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal" value="">
                                </label>
                                <label class="block text-sm font-semibold text-slate-900">
                                    Treatment scope
                                    <select name="treatment_scope" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal">
                                        <?php foreach (smile_design_treatment_scope_options() as $key => $label): ?>
                                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="block text-sm font-semibold text-slate-900">
                                    Smile width
                                    <select name="smile_width_goal" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal">
                                        <?php foreach (smile_design_smile_width_options() as $key => $label): ?>
                                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
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
                    </div>
                    <div>
                        <button class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white" type="submit" data-ai-submit-button data-default-label="Resend with Adjustments">
                            <span class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" data-ai-spinner></span>
                            <span data-ai-label>Resend with Adjustments</span>
                        </button>
                    </div>
                </aside>
                <div class="grid min-h-0 grid-rows-[minmax(0,1fr)_auto] gap-3">
                    <section class="grid min-h-0 gap-3 rounded-xl border border-slate-200 bg-slate-950/5 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold text-slate-700">Smile edit mask</p>
                                <p class="mt-1 text-[11px] font-medium text-slate-500">Work on one photo only. Drag the 8 anchors to fit the mouth, then resend.</p>
                            </div>
                            <label class="flex items-center gap-3 rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">
                                Zoom
                                <input id="adjust-modal-zoom-range" type="range" min="100" max="240" step="5" value="145" class="w-32 accent-slate-900">
                                <span id="adjust-modal-zoom-value" class="min-w-[42px] text-right">145%</span>
                            </label>
                        </div>
                        <div id="adjust-modal-work-frame" class="relative min-h-0 flex-1 overflow-hidden rounded-md border border-slate-200 bg-slate-950/5" style="min-height: 62vh;">
                            <div id="adjust-modal-work-stage" class="absolute inset-0 transition-transform duration-150 ease-out">
                                <img id="adjust-modal-work-preview" class="h-full w-full object-contain select-none" src="" alt="Smile edit reference" draggable="false">
                                <div id="adjust-modal-anchor-overlay" class="adjust-overlay-anchor-layer absolute inset-0"></div>
                                <svg id="adjust-modal-anchor-path" class="adjust-anchor-path absolute inset-0" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                                    <polygon class="adjust-mask-fill" fill="rgba(244,63,94,0.22)" stroke="none"></polygon>
                                    <polyline fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="0.42" stroke-linecap="round" stroke-linejoin="round"></polyline>
                                </svg>
                            </div>
                        </div>
                    </section>
                    <section class="rounded-md border border-slate-200 bg-white p-3">
                        <label class="block text-sm font-semibold text-slate-900">
                            Make adjustments and resend
                            <textarea name="adjustment_request" rows="4" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal" placeholder="Example: keep the same person and same smile direction, but shorten the upper centrals a little, soften the canines, and make the shade slightly brighter."></textarea>
                        </label>
                        <details class="mt-3 rounded-md border border-slate-200 bg-slate-50">
                            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">Precision controls</summary>
                            <div class="mt-3 grid gap-3 border-t border-slate-200 p-3 sm:grid-cols-2">
                                <label class="text-xs font-semibold text-slate-800">
                                    Shape shift: <span id="adjust-modal-shape-value" class="font-bold">0</span>%
                                    <input type="range" min="-30" max="30" value="0" class="mt-1 w-full" data-adjust-range="shape_scale_delta">
                                </label>
                                <label class="text-xs font-semibold text-slate-800">
                                    Smile length: <span id="adjust-modal-length-value" class="font-bold">0</span>%
                                    <input type="range" min="-30" max="30" value="0" class="mt-1 w-full" data-adjust-range="smile_length_delta">
                                </label>
                                <label class="text-xs font-semibold text-slate-800">
                                    Smile width: <span id="adjust-modal-width-value" class="font-bold">0</span>%
                                    <input type="range" min="-40" max="40" value="0" class="mt-1 w-full" data-adjust-range="smile_width_delta">
                                </label>
                                <label class="text-xs font-semibold text-slate-800">
                                    Shade brightness: <span id="adjust-modal-shade-value" class="font-bold">0</span>
                                    <input type="range" min="-25" max="25" value="0" class="mt-1 w-full" data-adjust-range="shade_brightness_delta">
                                </label>
                            </div>
                        </details>
                    </section>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const caseReviewBaseUrl = <?= json_encode(base_url('smile-design/cases/' . $caseId), JSON_UNESCAPED_SLASHES) ?>;
  let pendingConfirmForm = null;
  const confirmModal = document.getElementById('confirm-modal');
  const confirmMessage = document.getElementById('confirm-modal-message');
  const confirmCancel = document.getElementById('confirm-modal-cancel');
  const confirmContinue = document.getElementById('confirm-modal-continue');
  const adjustModal = document.getElementById('adjust-fullscreen-modal');
  const adjustModalClose = document.getElementById('adjust-modal-close');
  const adjustModalTitle = document.getElementById('adjust-modal-title');
  const adjustModalBadge = document.getElementById('adjust-modal-badge');
  const adjustModalForm = document.getElementById('adjust-fullscreen-form');
  const adjustWorkPreview = document.getElementById('adjust-modal-work-preview');
  const adjustWorkFrame = document.getElementById('adjust-modal-work-frame');
  const adjustWorkStage = document.getElementById('adjust-modal-work-stage');
  const adjustAnchorOverlay = document.getElementById('adjust-modal-anchor-overlay');
  const adjustAnchorPath = document.getElementById('adjust-modal-anchor-path');
  const adjustZoomRange = document.getElementById('adjust-modal-zoom-range');
  const adjustZoomValue = document.getElementById('adjust-modal-zoom-value');
  const lightbox = document.getElementById('image-lightbox');
  const lightboxImage = document.getElementById('image-lightbox-image');
  const lightboxClose = document.getElementById('image-lightbox-close');
  const caseProcedureSelect = document.querySelector('[data-case-procedure-select]');
  const caseLviStyleField = document.querySelector('[data-case-lvi-style-field]');
  const caseShadeField = document.querySelector('[data-case-shade-field]');
  function isLipRepositionOnly(value) {
    const text = String(value || '').toLowerCase();
    return text.includes('lip reposition') && !text.includes('veneer');
  }
  function syncCasePreferenceFields() {
    const hideDentalStyle = caseProcedureSelect && isLipRepositionOnly(caseProcedureSelect.value);
    if (caseLviStyleField) caseLviStyleField.classList.toggle('hidden', !!hideDentalStyle);
    if (caseShadeField) caseShadeField.classList.toggle('hidden', !!hideDentalStyle);
  }
  if (caseProcedureSelect) {
    caseProcedureSelect.addEventListener('change', syncCasePreferenceFields);
    syncCasePreferenceFields();
  }
  const adjustDefaultZoom = 145;
  const adjustAnchorDefaults = [
    { key: 'upper_left', label: 'Upper left lip edge', x: 31, y: 43, size: 18, color: '#ef4444' },
    { key: 'upper_center', label: 'Upper center lip edge', x: 50, y: 42, size: 18, color: '#ef4444' },
    { key: 'upper_right', label: 'Upper right lip edge', x: 69, y: 43, size: 18, color: '#ef4444' },
    { key: 'right_inner', label: 'Right smile corner', x: 73, y: 50, size: 18, color: '#ef4444' },
    { key: 'lower_right', label: 'Lower right lip edge', x: 69, y: 59, size: 18, color: '#ef4444' },
    { key: 'lower_center', label: 'Lower center lip edge', x: 50, y: 61, size: 18, color: '#ef4444' },
    { key: 'lower_left', label: 'Lower left lip edge', x: 31, y: 59, size: 18, color: '#ef4444' },
    { key: 'left_inner', label: 'Left smile corner', x: 27, y: 50, size: 18, color: '#ef4444' },
  ];
  const adjustAnchorPathOrder = ['upper_left', 'upper_center', 'upper_right', 'right_inner', 'lower_right', 'lower_center', 'lower_left', 'left_inner', 'upper_left'];
  const normalizeAdjustAnchor = function (value, min, max) {
    const parsed = Number.parseFloat(value || '');
    if (!Number.isFinite(parsed)) {
      return 0;
    }
    return Math.max(min, Math.min(max, parsed));
  };
  const getAdjustAnchorValue = function () {
    return adjustAnchorDefaults.map(function (point) {
      return {
        key: point.key,
        label: point.label,
        x: point.x,
        y: point.y,
        size: point.size,
        color: point.color,
      };
    });
  };
  const readAnchorPoints = function (raw) {
    try {
      if (!raw || typeof raw !== 'string') return getAdjustAnchorValue();
      const parsed = JSON.parse(raw);
      if (!Array.isArray(parsed) || !parsed.length) return getAdjustAnchorValue();
      const keyed = {};
      parsed.forEach(function (point) {
        const key = typeof point === 'object' && point !== null ? String(point.key || '') : '';
        if (!key) return;
        const x = normalizeAdjustAnchor(point.x ?? point.px ?? 0, 0, 100);
        const y = normalizeAdjustAnchor(point.y ?? point.py ?? 0, 0, 100);
        keyed[key] = {
          key,
          x,
          y,
          label: String(point.label || key).trim() || key,
          size: Number.parseFloat(point.size || 14),
          color: String(point.color || '#ffffff'),
        };
      });
      const base = getAdjustAnchorValue();
      return base.map(function (point) {
        return keyed[point.key] || point;
      });
    } catch (error) {
      return getAdjustAnchorValue();
    }
  };
  const writeAnchorPoints = function (points) {
    const input = adjustModalForm && adjustModalForm.querySelector('input[name="anchor_points"]') ? adjustModalForm.querySelector('input[name="anchor_points"]') : null;
    if (!input) return;
    input.value = JSON.stringify(points);
  };
  const getAnchorLinePoints = function (points) {
    const values = {};
    points.forEach(function (point) {
      values[String(point.key || '').trim()] = point;
    });
    return adjustAnchorPathOrder
      .map(function (key) { return values[key] ? `${values[key].x},${values[key].y}` : ''; })
      .filter(Boolean)
      .join(' ');
  };
  const getAnchorCentroid = function (points) {
    if (!Array.isArray(points) || !points.length) {
      return { x: 50, y: 50 };
    }
    const total = points.reduce(function (carry, point) {
      return {
        x: carry.x + Number(point.x || 0),
        y: carry.y + Number(point.y || 0),
      };
    }, { x: 0, y: 0 });
    return {
      x: total.x / points.length,
      y: total.y / points.length,
    };
  };
  const syncAdjustZoom = function (points) {
    if (!adjustWorkStage) return;
    const zoomValue = adjustZoomRange ? normalizeAdjustAnchor(adjustZoomRange.value, 100, 240) : adjustDefaultZoom;
    const scale = zoomValue / 100;
    const centroid = getAnchorCentroid(points);
    const frameRect = adjustWorkFrame ? adjustWorkFrame.getBoundingClientRect() : null;
    const frameWidth = frameRect ? frameRect.width : 0;
    const frameHeight = frameRect ? frameRect.height : 0;
    const centroidX = (centroid.x / 100) * frameWidth;
    const centroidY = (centroid.y / 100) * frameHeight;
    const desiredX = frameWidth * 0.5;
    const desiredY = frameHeight * 0.5;
    let offsetX = desiredX - (centroidX * scale);
    let offsetY = desiredY - (centroidY * scale);
    const minOffsetX = frameWidth - (frameWidth * scale);
    const minOffsetY = frameHeight - (frameHeight * scale);
    offsetX = Math.max(minOffsetX, Math.min(0, offsetX));
    offsetY = Math.max(minOffsetY, Math.min(0, offsetY));
    adjustWorkStage.style.transformOrigin = '0 0';
    adjustWorkStage.style.transform = 'translate(' + offsetX + 'px, ' + offsetY + 'px) scale(' + scale + ')';
    if (adjustZoomValue) {
      adjustZoomValue.textContent = Math.round(zoomValue) + '%';
    }
  };
  const buildAnchorButton = function (point, editable) {
    const marker = document.createElement('button');
    const size = Math.max(10, normalizeAdjustAnchor(point.size || 14, 10, 22));
    marker.type = 'button';
    marker.className = 'adjust-anchor-point';
    marker.style.left = point.x + '%';
    marker.style.top = point.y + '%';
    marker.style.width = size + 'px';
    marker.style.height = size + 'px';
    marker.style.backgroundColor = String(point.color || '#ffffff');
    marker.dataset.anchorKey = String(point.key || '');
    marker.dataset.anchorLabel = String(point.label || '');
    marker.dataset.editable = String(editable);
    marker.style.touchAction = 'none';
    marker.setAttribute('title', String(point.label || point.key || 'Anchor point'));
    marker.textContent = '\u2022';
    return marker;
  };
  const renderAdjustAnchors = function (points, editable) {
    const path = adjustAnchorPath ? adjustAnchorPath.querySelector('polyline') : null;
    const fill = adjustAnchorPath ? adjustAnchorPath.querySelector('polygon') : null;
    if (!adjustAnchorOverlay) return;
    adjustAnchorOverlay.innerHTML = '';
    if (!points || !points.length) return;
    points.forEach(function (point) {
      const afterMarker = buildAnchorButton(point, editable);
      adjustAnchorOverlay.appendChild(afterMarker);
      if (editable) {
        afterMarker.setAttribute('data-editable', 'true');
      }
    });
    if (editable) {
      adjustAnchorOverlay.classList.remove('pointer-events-none');
      adjustAnchorOverlay.querySelectorAll('.adjust-anchor-point[data-editable="true"]').forEach(function (anchor) {
        anchor.addEventListener('pointerdown', function (event) {
          event.preventDefault();
          event.stopPropagation();
          anchor.setPointerCapture(event.pointerId);
          anchor.setAttribute('touch-action', 'none');
          anchor.classList.add('active');
          const startMove = function (moveEvent) {
            if (!adjustAnchorOverlay || !adjustAnchorOverlay.parentElement) return;
            const containerRect = adjustAnchorOverlay.parentElement.getBoundingClientRect();
            const nextX = Math.max(0, Math.min(100, ((moveEvent.clientX - containerRect.left) / containerRect.width) * 100));
            const nextY = Math.max(0, Math.min(100, ((moveEvent.clientY - containerRect.top) / containerRect.height) * 100));
            anchor.style.left = nextX + '%';
            anchor.style.top = nextY + '%';
            const key = anchor.getAttribute('data-anchor-key');
            const point = points.find(function (entry) { return String(entry.key) === String(key); });
            if (point) {
              point.x = Math.round(nextX * 100) / 100;
              point.y = Math.round(nextY * 100) / 100;
            }
            if (path) {
              path.setAttribute('points', getAnchorLinePoints(points));
            }
            if (fill) {
              fill.setAttribute('points', getAnchorLinePoints(points));
            }
            syncAdjustZoom(points);
            writeAnchorPoints(points);
          };
          const finishMove = function () {
            anchor.classList.remove('active');
            window.removeEventListener('pointermove', startMove);
            window.removeEventListener('pointerup', finishMove);
            window.removeEventListener('pointercancel', finishMove);
          };
          window.addEventListener('pointermove', startMove);
          window.addEventListener('pointerup', finishMove, { once: true });
          window.addEventListener('pointercancel', finishMove, { once: true });
        });
      });
    } else {
      adjustAnchorOverlay.classList.add('pointer-events-none');
    }
    if (path) {
      path.setAttribute('points', getAnchorLinePoints(points));
    }
    if (fill) {
      fill.setAttribute('points', getAnchorLinePoints(points));
    }
    adjustAnchorOverlay.classList.toggle('pointer-events-none', !editable);
    syncAdjustZoom(points);
    writeAnchorPoints(points);
  };
  function getRangeConfig(rangeName) {
    const configs = {
      shape_scale_delta: { label: 'adjust-modal-shape-value', input: 'shape_scale_delta', suffix: '%' },
      smile_length_delta: { label: 'adjust-modal-length-value', input: 'smile_length_delta', suffix: '%' },
      smile_width_delta: { label: 'adjust-modal-width-value', input: 'smile_width_delta', suffix: '%' },
      shade_brightness_delta: { label: 'adjust-modal-shade-value', input: 'shade_brightness_delta', suffix: '' },
    };
    return configs[rangeName] || null;
  }
  function syncAdjustRange(range) {
    const key = range.getAttribute('data-adjust-range');
    const config = getRangeConfig(key);
    if (!config || !adjustModalForm) return;
    const value = String(range.value || 0);
    const display = document.getElementById(config.label);
    const hidden = adjustModalForm.querySelector('input[name="' + config.input + '"]');
    if (display) {
      display.textContent = value + config.suffix;
    }
    if (hidden) {
      hidden.value = value;
    }
  }
  function resetAdjustRangeInputs() {
    document.querySelectorAll('[data-adjust-range]').forEach(function (range) {
      range.value = '0';
      syncAdjustRange(range);
    });
  }
  document.querySelectorAll('[data-adjust-range]').forEach(function (range) {
    syncAdjustRange(range);
    range.addEventListener('input', function () {
      syncAdjustRange(range);
    });
  });
  if (adjustZoomRange) {
    adjustZoomRange.addEventListener('input', function () {
      const currentPoints = readAnchorPoints(adjustModalForm && adjustModalForm.querySelector('input[name="anchor_points"]') ? adjustModalForm.querySelector('input[name="anchor_points"]').value : '');
      syncAdjustZoom(currentPoints);
    });
  }
  window.addEventListener('resize', function () {
    if (!adjustModal || adjustModal.classList.contains('hidden')) return;
    const currentPoints = readAnchorPoints(adjustModalForm && adjustModalForm.querySelector('input[name="anchor_points"]') ? adjustModalForm.querySelector('input[name="anchor_points"]').value : '');
    syncAdjustZoom(currentPoints);
  });
  function closeAdjustModal() {
    if (!adjustModal) return;
    adjustModal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    if (adjustAnchorOverlay) {
      adjustAnchorOverlay.innerHTML = '';
      adjustAnchorOverlay.classList.add('pointer-events-none');
    }
    if (adjustWorkPreview) {
      adjustWorkPreview.setAttribute('src', '');
    }
  }
  function openAdjustModal(button) {
    if (!adjustModal || !adjustModalForm || !adjustModalTitle) return;
    const getter = function (key) { return button.getAttribute('data-adjust-' + key) || ''; };
    const versionId = getter('version-id');
    const versionNumber = getter('version-number');
    const versionTitle = getter('version-title');
    const procedureLabel = getter('procedure-label');
    const treatmentScope = getter('treatment-scope');
    const widthGoal = getter('width-goal');
    const photoType = getter('photo-type');
    const lviStyle = getter('lvi-style');
    const shadeGoal = getter('shade');
    const beforePhotoId = getter('before-photo-id');
    const beforePhotoUrl = getter('before-url');
    const afterPhotoUrl = getter('photo-url');
    const isLipRepositionOnly = String(procedureLabel).toLowerCase().includes('lip reposition') && !String(procedureLabel).toLowerCase().includes('veneer');

    adjustModalTitle.textContent = 'Edit version #' + (versionNumber || '0');
    if (adjustModalBadge) {
      adjustModalBadge.textContent = 'Version #' + (versionNumber || '0');
    }
    if (adjustModalForm.querySelector('input[name=\"after_version_id\"]')) {
      adjustModalForm.querySelector('input[name=\"after_version_id\"]').value = versionId;
    }
    if (adjustModalForm.querySelector('input[name=\"before_photo_id\"]')) {
      adjustModalForm.querySelector('input[name=\"before_photo_id\"]').value = beforePhotoId;
    }
    if (adjustModalForm.querySelector('input[name=\"procedure_label\"]')) {
      adjustModalForm.querySelector('input[name=\"procedure_label\"]').value = procedureLabel;
    }
    if (adjustModalForm.querySelector('input[name=\"lvi_style_key\"]')) {
      adjustModalForm.querySelector('input[name=\"lvi_style_key\"]').value = lviStyle;
    }
    if (adjustModalForm.querySelector('input[name=\"shade_goal\"]')) {
      adjustModalForm.querySelector('input[name=\"shade_goal\"]').value = shadeGoal;
    }
    if (adjustModalForm.querySelector('input[name=\"photo_type\"]')) {
      adjustModalForm.querySelector('input[name=\"photo_type\"]').value = photoType;
    }
    if (adjustModalForm.querySelector('input[name=\"precision_mode\"]')) {
      adjustModalForm.querySelector('input[name=\"precision_mode\"]').value = 'balanced';
    }
    const anchorPointsInput = adjustModalForm.querySelector('input[name=\"anchor_points\"]');
    const anchorPoints = readAnchorPoints(anchorPointsInput ? anchorPointsInput.value : '');
    writeAnchorPoints(anchorPoints);
    if (adjustModalForm.querySelector('input[name=\"version_title\"]')) {
      adjustModalForm.querySelector('input[name=\"version_title\"]').value = 'Revision of #' + (versionNumber || '0') + (versionTitle ? ' ' + versionTitle : '');
    }
    if (adjustModalForm.querySelector('textarea[name=\"adjustment_request\"]')) {
      adjustModalForm.querySelector('textarea[name=\"adjustment_request\"]').value = '';
    }
    if (adjustModalForm.querySelector('input[name=\"notes\"]')) {
      adjustModalForm.querySelector('input[name=\"notes\"]').value = '';
    }
    if (adjustModalForm.querySelector('select[name=\"treatment_scope\"]')) {
      adjustModalForm.querySelector('select[name=\"treatment_scope\"]').value = treatmentScope;
      adjustModalForm.querySelector('select[name=\"treatment_scope\"]').disabled = isLipRepositionOnly;
    }
    if (adjustModalForm.querySelector('select[name=\"smile_width_goal\"]')) {
      adjustModalForm.querySelector('select[name=\"smile_width_goal\"]').value = widthGoal;
      adjustModalForm.querySelector('select[name=\"smile_width_goal\"]').disabled = isLipRepositionOnly;
    }
    if (adjustModalForm.querySelector('input[name=\"shape_scale_delta\"]')) {
      adjustModalForm.querySelector('input[name=\"shape_scale_delta\"]').value = '0';
    }
    if (adjustModalForm.querySelector('input[name=\"smile_length_delta\"]')) {
      adjustModalForm.querySelector('input[name=\"smile_length_delta\"]').value = '0';
    }
    if (adjustModalForm.querySelector('input[name=\"smile_width_delta\"]')) {
      adjustModalForm.querySelector('input[name=\"smile_width_delta\"]').value = '0';
    }
    if (adjustModalForm.querySelector('input[name=\"shade_brightness_delta\"]')) {
      adjustModalForm.querySelector('input[name=\"shade_brightness_delta\"]').value = '0';
    }
    resetAdjustRangeInputs();
    if (adjustZoomRange) {
      adjustZoomRange.value = String(adjustDefaultZoom);
    }
    if (adjustWorkPreview) {
      adjustWorkPreview.setAttribute('src', beforePhotoUrl || afterPhotoUrl || '');
      adjustWorkPreview.setAttribute('alt', 'Version #' + (versionNumber || '0') + ' smile edit reference');
    }
    renderAdjustAnchors(anchorPoints || getAdjustAnchorValue(), true);
    const returnUrlInput = adjustModalForm.querySelector('input[name=\"return_url\"]');
    if (returnUrlInput) {
      returnUrlInput.value = reviewReturnUrl().replace(/#.*$/, '#compare');
    }
    adjustModal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    const firstField = adjustModalForm.querySelector('textarea[name=\"adjustment_request\"]');
    if (firstField) {
      firstField.focus();
    }
  }
  if (adjustModalClose) {
    adjustModalClose.addEventListener('click', closeAdjustModal);
  }
  if (adjustModal) {
    adjustModal.addEventListener('click', function (event) {
      if (event.target === adjustModal) {
        closeAdjustModal();
      }
    });
  }
  document.querySelectorAll('[data-open-adjust-modal]').forEach(function (button) {
    button.addEventListener('click', function () {
      openAdjustModal(button);
    });
  });
  function closeLightbox() {
    if (!lightbox || !lightboxImage) return;
    lightbox.classList.add('hidden');
    lightbox.classList.remove('flex');
    lightboxImage.setAttribute('src', '');
    lightboxImage.setAttribute('alt', '');
    document.body.classList.remove('overflow-hidden');
  }
  document.querySelectorAll('img[data-lightbox-src]').forEach(function (image) {
    image.classList.add('cursor-zoom-in');
    image.addEventListener('click', function () {
      if (!lightbox || !lightboxImage) return;
      lightboxImage.setAttribute('src', image.getAttribute('data-lightbox-src') || image.getAttribute('src') || '');
      lightboxImage.setAttribute('alt', image.getAttribute('data-lightbox-alt') || image.getAttribute('alt') || 'Expanded image preview');
      lightbox.classList.remove('hidden');
      lightbox.classList.add('flex');
      document.body.classList.add('overflow-hidden');
    });
  });
  if (lightboxClose) {
    lightboxClose.addEventListener('click', closeLightbox);
  }
  if (lightbox) {
    lightbox.addEventListener('click', function (event) {
      if (event.target === lightbox) {
        closeLightbox();
      }
    });
  }
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      if (adjustModal && !adjustModal.classList.contains('hidden')) {
        closeAdjustModal();
        return;
      }
      closeLightbox();
    }
  });
  function currentOpenSetKeys() {
    return Array.from(document.querySelectorAll('[data-after-set-panel]'))
      .filter(function (panel) { return !panel.classList.contains('hidden'); })
      .map(function (panel) { return panel.getAttribute('data-after-set-key') || ''; })
      .filter(Boolean);
  }
  function reviewReturnUrl() {
    const openSetKeys = currentOpenSetKeys();
    const query = openSetKeys.length ? '?open_sets=' + encodeURIComponent(openSetKeys.join(',')) : '';
    return caseReviewBaseUrl + query + '#generate';
  }
  document.querySelectorAll('form[data-preserve-open-sets]').forEach(function (form) {
    form.addEventListener('submit', function () {
      const input = form.querySelector('input[name="return_url"]');
      if (input) {
        input.value = reviewReturnUrl();
      }
    });
  });
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      pendingConfirmForm = form;
      if (confirmMessage) {
        confirmMessage.textContent = form.getAttribute('data-confirm') || 'Are you sure?';
      }
      if (confirmModal) {
        confirmModal.classList.remove('hidden');
        confirmModal.classList.add('flex');
      }
    });
  });
  if (confirmCancel) {
    confirmCancel.addEventListener('click', function () {
      pendingConfirmForm = null;
      if (confirmModal) {
        confirmModal.classList.add('hidden');
        confirmModal.classList.remove('flex');
      }
    });
  }
  if (confirmContinue) {
    confirmContinue.addEventListener('click', function () {
      const form = pendingConfirmForm;
      pendingConfirmForm = null;
      if (confirmModal) {
        confirmModal.classList.add('hidden');
        confirmModal.classList.remove('flex');
      }
      if (form) {
        if (window.smileDesignShowActionLoader) {
          window.smileDesignShowActionLoader('Deleting...');
        }
        form.submit();
      }
    });
  }
  if (confirmModal) {
    confirmModal.addEventListener('click', function (event) {
      if (event.target === confirmModal) {
        pendingConfirmForm = null;
        confirmModal.classList.add('hidden');
        confirmModal.classList.remove('flex');
      }
    });
  }
  document.querySelectorAll('[data-copy-target]').forEach(function (button) {
    button.addEventListener('click', async function () {
      const targetId = button.getAttribute('data-copy-target');
      const input = targetId ? document.getElementById(targetId) : null;
      if (!input) return;
      try {
        await navigator.clipboard.writeText(input.value);
        const original = button.innerHTML;
        button.textContent = 'Copied';
        window.setTimeout(function () {
          button.innerHTML = original;
        }, 1400);
      } catch (error) {
        input.focus();
        input.select();
      }
    });
  });
  document.querySelectorAll('.js-ai-submit-form').forEach(function (form) {
    form.addEventListener('submit', function () {
      const button = form.querySelector('[data-ai-submit-button]');
      if (!button) return;
      const spinner = button.querySelector('[data-ai-spinner]');
      const label = button.querySelector('[data-ai-label]');
      if (form === adjustModalForm) {
        closeAdjustModal();
      }
      button.disabled = true;
      button.classList.add('opacity-80', 'cursor-wait');
      if (spinner) spinner.classList.remove('hidden');
      if (label) label.textContent = 'Generating...';
    });
  });
  document.querySelectorAll('[data-toggle-target]').forEach(function (button) {
    button.addEventListener('click', function () {
      const targetId = button.getAttribute('data-toggle-target');
      const labelId = button.getAttribute('data-toggle-label');
      const panel = targetId ? document.getElementById(targetId) : null;
      const label = labelId ? document.getElementById(labelId) : null;
      if (!panel) return;
      const isHidden = panel.classList.contains('hidden');
      panel.classList.toggle('hidden', !isHidden);
      button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
      if (label) {
        label.textContent = isHidden ? 'Collapse' : 'Expand';
      }
    });
  });
});
</script>

<?php smile_design_render_shell_end(); ?>
