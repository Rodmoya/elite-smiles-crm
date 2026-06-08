<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$token = (string)get('token', '');
$link = smile_design_verify_token($token, 'preview');
if (!$link) {
    http_response_code(404);
    exit('Preview link not found or expired.');
}

$case = smile_design_case((int)$link['case_id']);
smile_design_record_preview_view($link, $token);
$caseId = (int)$link['case_id'];
$photos = smile_design_case_photos($caseId);
$approvedAfter = smile_design_patient_preview_version((int)$link['case_id']);
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
        'url' => smile_design_photo_url((int)$photo['id'], $token),
        'after_url' => $angleAfter ? smile_design_after_url((int)$angleAfter['id'], $token) : '',
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
if (!$viewerAfterVersion) {
    $viewerAfterVersion = $approvedAfter ?: $selectedAfter;
}
if (!$viewerAfterVersion) {
    $allAfterVersions = smile_design_after_versions($caseId, false);
    $viewerAfterVersion = $allAfterVersions[0] ?? null;
}

$displayAfter = $viewerAfterVersion;
$beforeUrl = $frontViewerPhoto ? smile_design_photo_url((int)$frontViewerPhoto['id'], $token) : '';
$afterUrl = $displayAfter ? smile_design_after_url((int)$displayAfter['id'], $token) : '';
$alignment = $displayAfter ? smile_design_alignment_for_after($displayAfter) : smile_design_alignment_defaults();
$practicePhone = '(801) 572-6262';
$practiceEmail = 'elitesmilesutah@gmail.com';
$practiceAddress1 = '11762 South State, Suite 300';
$practiceAddress2 = 'Draper, UT 84020';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elite Smiles | Smile Preview</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-stone-50 text-slate-900 antialiased">
    <header class="border-b border-stone-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-4">
                <img class="h-auto w-40" src="<?= e(SMILE_DESIGN_LOGO_URL) ?>" alt="Elite Smiles">
                <div class="hidden text-sm text-slate-600 sm:block">
                    <p>Elite Smiles by Walter Meden DDS</p>
                    <p><?= e($practiceAddress1) ?>, <?= e($practiceAddress2) ?></p>
                </div>
            </div>
            <div class="text-right text-sm text-slate-600">
                <p class="font-semibold text-slate-900"><?= e($practicePhone) ?></p>
                <p><?= e($practiceEmail) ?></p>
            </div>
        </div>
    </header>

    <main>
        <section class="bg-white">
            <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">Smile Preview</p>
                    <h1 class="mt-3 max-w-4xl text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl lg:text-5xl">
                        <?= e(trim((string)($case['patient_name'] ?? 'Your'))) ?> Your Elite Smiles consultation preview
                    </h1>
                    <p class="mt-4 max-w-3xl text-base leading-7 text-slate-600 sm:text-lg sm:leading-8">
                        This private preview page was prepared by Elite Smiles to help you review your potential smile direction before your consultation with Dr. Meden.
                    </p>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-0 pb-10 sm:px-6 lg:px-8 lg:pb-14">
            <?php if (!$displayAfter): ?>
                <div class="mx-4 rounded-xl border border-stone-200 bg-white p-8 shadow-sm sm:mx-0">
                    <h2 class="text-2xl font-semibold text-slate-950">Your smile preview is not ready yet.</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Elite Smiles will share your secure preview as soon as it is ready for review.</p>
                </div>
            <?php else: ?>
                <?php [$label, $disclaimer] = smile_design_after_label($displayAfter); ?>
                <div class="overflow-hidden border-y border-stone-200 bg-white p-0 shadow-sm sm:rounded-xl sm:border sm:p-5 lg:p-6">
                    <?php smile_before_after_viewer($beforeUrl, $afterUrl, ['title' => 'Your Smile Preview', 'mode' => 'ba', 'alignment' => $alignment, 'watermark' => false, 'input_gallery' => $inputGallery]); ?>
                </div>
                <div class="mt-6 grid gap-6 px-4 sm:px-0 lg:grid-cols-[1.2fr_.8fr]">
                    <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                        <h2 class="text-xl font-semibold text-slate-950">What you are viewing</h2>
                        <p class="mt-3 text-sm leading-7 text-slate-600">
                            This preview is intended to help you visualize your consultation direction. It is not a final clinical guarantee, but it gives you and Dr. Meden a shared starting point for discussing your ideal smile.
                        </p>
                        <?php if (!$approvedAfter): ?>
                            <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">This share link is currently showing the best available internal preview while the final patient-approved version is still being finalized.</p>
                        <?php endif; ?>
                        <p class="mt-4 text-sm leading-7 text-slate-600"><?= e($disclaimer) ?></p>
                    </div>
                    <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                        <h2 class="text-xl font-semibold text-slate-950">Next steps</h2>
                        <ol class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                            <li>1. Review your preview and note what you like most.</li>
                            <li>2. Bring your questions and preferences to your consultation.</li>
                            <li>3. Meet with Dr. Meden to refine the final treatment plan.</li>
                        </ol>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="border-t border-stone-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-stone-200 bg-stone-50 p-6">
                <h2 class="text-lg font-semibold text-slate-950">Elite Smiles</h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
                    Elite Smiles by Walter Meden DDS is a high-end cosmetic dentistry practice in Draper, Utah specializing in veneers, implants, and full-mouth restorations.
                </p>
                <dl class="mt-5 grid gap-5 text-sm md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <dt class="font-semibold text-slate-900">Phone</dt>
                        <dd class="mt-1 text-slate-600"><?= e($practicePhone) ?></dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">Email</dt>
                        <dd class="mt-1 text-slate-600"><?= e($practiceEmail) ?></dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">Address</dt>
                        <dd class="mt-1 text-slate-600"><?= e($practiceAddress1) ?><br><?= e($practiceAddress2) ?></dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">Office Hours</dt>
                        <dd class="mt-1 text-slate-600">Mon-Thurs 8:30 to 6:30<br>Friday 9:00 to 1:00 by appointment</dd>
                    </div>
                </dl>
            </div>
        </div>
    </footer>
</body>
</html>
