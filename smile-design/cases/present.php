<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_auth();
$caseId = (int)get('id', 0);
$case = smile_design_case($caseId);
if (!$case) { http_response_code(404); exit('Case not found.'); }
smile_design_audit($caseId, 'presentation_opened', ['mode' => 'present'], auth_user_id());
$photos = smile_design_case_photos($caseId);
$primaryBefore = smile_design_primary_before_photo($caseId);
$displayBeforePhoto = smile_design_find_before_photo_by_type($caseId, 'front', true) ?: $primaryBefore;
$selectedAfter = smile_design_selected_after_version($caseId);
$angleDefinitions = [
    'front' => 'Front',
    'left_45' => 'Left 45',
    'right_45' => 'Right 45',
];
$angleBeforePhotos = [];
foreach ($angleDefinitions as $photoType => $label) {
    $photo = smile_design_find_before_photo_by_type($caseId, $photoType, true);
    if ($photo) {
        $angleBeforePhotos[$photoType] = $photo;
    }
}
$frontViewerPhoto = $angleBeforePhotos['front'] ?? $displayBeforePhoto;
$inputGallery = [];
$viewerAfterVersion = null;
foreach ($angleBeforePhotos as $photoType => $photo) {
    $angleAfter = smile_design_selected_after_version($caseId, $photoType);
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
if (!$viewerAfterVersion) {
    foreach ($angleDefinitions as $photoType => $label) {
        $angleAfter = smile_design_selected_after_version($caseId, $photoType);
        if ($angleAfter) {
            $viewerAfterVersion = $angleAfter;
            $frontViewerPhoto = $angleBeforePhotos[$photoType] ?? $frontViewerPhoto;
            break;
        }
    }
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
    'return_url' => $_SERVER['REQUEST_URI'] ?? base_url('smile-design/cases/' . $caseId . '/present'),
] : [];
$angleReadyLabels = [];
foreach ($inputGallery as $item) {
    if (trim((string)($item['after_url'] ?? '')) !== '') {
        $angleReadyLabels[] = (string)($item['label'] ?? 'Angle');
    }
}
$styleTitle = (string)($case['lvi_style_key'] ?? 'Smile Preview');
if ((string)($case['selected_style'] ?? '') !== '') {
    $styleDetail = smile_design_style_detail((string)$case['selected_style']);
    $styleTitle = (string)($styleDetail['title'] ?? $styleTitle);
}
$presentationLabel = $viewerAfterVersion ? smile_design_after_label($viewerAfterVersion) : ['AI Smile Preview', 'Cosmetic visualization. Not a clinical guarantee. Individual results may vary.'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Presentation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-black text-white antialiased">
    <main class="mx-auto max-w-7xl px-4 py-5">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.24em] text-white/50">Elite Smiles Presentation</p>
                <h1 class="text-2xl font-semibold"><?= e((string)$case['patient_name']) ?></h1>
                <p class="mt-1 text-sm text-white/60"><?= e((string)$case['procedure_interest']) ?> · <?= e($styleTitle) ?></p>
                <p class="mt-2 text-xs leading-5 text-white/45"><?= $angleReadyLabels ? e('Ready angles: ' . implode(', ', $angleReadyLabels)) : 'No generated after preview is ready yet.' ?></p>
            </div>
            <a class="rounded-md border border-white/25 px-4 py-2 text-sm font-semibold text-white" href="<?= e(base_url('smile-design/cases/' . $caseId)) ?>">Back</a>
        </div>
        <?php smile_before_after_viewer($beforeUrl, $afterUrl, ['title' => $styleTitle, 'mode' => 'ba', 'alignment' => $alignment, 'alignment_edit' => $alignmentEdit, 'input_gallery' => $inputGallery]); ?>
        <p class="mt-4 text-sm leading-6 text-white/60"><?= e($presentationLabel[1]) ?></p>
    </main>
</body>
</html>
