<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

$galleryToken = trim((string)get('token', ''));
$galleryTokenLink = $galleryToken !== '' ? smile_design_verify_token($galleryToken, 'gallery') : null;
$isGalleryTokenAccess = is_array($galleryTokenLink) && (int)($galleryTokenLink['case_id'] ?? -1) === 0;
if ($isGalleryTokenAccess) {
    $user = [];
    $GLOBALS['currentPage'] = 'smile_design';
    $GLOBALS['pageTitle'] = 'Gallery';
    $GLOBALS['logoUrl'] = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
    smile_design_record_preview_view($galleryTokenLink, $galleryToken);
} else {
    $user = smile_design_internal_boot('Gallery');
}
$caseId = (int)get('case_id', 0);
$query = trim((string)get('q', ''));
$showLviCatalog = strtolower(trim((string)get('catalog', ''))) === 'lvi';
$selectedAngle = strtolower(trim((string)get('angle', 'front')));
$angleDefinitions = [
    'front' => 'Front',
    'left_45' => 'Left 45',
    'right_45' => 'Right 45',
];
if (!array_key_exists($selectedAngle, $angleDefinitions)) {
    $selectedAngle = 'front';
}

$cases = $query !== '' ? smile_design_search_cases($query, 72) : smile_design_recent_cases(72);
$case = $caseId > 0 ? smile_design_case($caseId) : null;
$tokenQuery = $isGalleryTokenAccess ? '&token=' . rawurlencode($galleryToken) : '';
$tokenFirstQuery = $isGalleryTokenAccess ? '?token=' . rawurlencode($galleryToken) : '';
$lviCatalogImages = [];
if ($showLviCatalog && !$case) {
    $lviCatalogImages = db_all(
        "SELECT li.*, ls.title AS style_title, ls.sort_order AS style_sort_order
         FROM lvi_sample_images li
         LEFT JOIN lvi_style_samples ls ON ls.style_key = li.style_key
         WHERE li.is_active = 1
         ORDER BY COALESCE(ls.sort_order, 999) ASC, li.sort_order ASC, li.id DESC
         LIMIT 48"
    );
}

$beforeUrl = '';
$afterUrl = '';
$alignment = smile_design_alignment_defaults();
$videoUrl = '';
$angleThumbs = [];

if ($case) {
    foreach ($angleDefinitions as $photoType => $label) {
        $before = smile_design_find_before_photo_by_type($caseId, $photoType, true);
        $after = smile_design_selected_after_version($caseId, $photoType);
        $angleThumbs[$photoType] = [
            'label' => $label,
            'before' => $before,
            'after' => $after,
            'before_url' => $before ? smile_design_photo_url((int)$before['id'], $isGalleryTokenAccess ? $galleryToken : '') : '',
            'after_url' => $after ? smile_design_after_url((int)$after['id'], $isGalleryTokenAccess ? $galleryToken : '') : '',
            'after_label' => $after ? 'Result' : 'Result pending',
            'alignment' => $after ? smile_design_alignment_for_after($after) : smile_design_alignment_defaults(),
        ];
    }

    $selectedBefore = $angleThumbs[$selectedAngle]['before'] ?? null;
    $selectedAfter = $angleThumbs[$selectedAngle]['after'] ?? null;
    if (!$selectedBefore) {
        $selectedBefore = smile_design_primary_before_photo($caseId);
    }
    if (!$selectedAfter) {
        $selectedAfter = smile_design_selected_after_version($caseId, $selectedAngle) ?: smile_design_selected_after_version($caseId);
    }

    $beforeUrl = $selectedBefore ? smile_design_photo_url((int)$selectedBefore['id'], $isGalleryTokenAccess ? $galleryToken : '') : '';
    $afterUrl = $selectedAfter ? smile_design_after_url((int)$selectedAfter['id'], $isGalleryTokenAccess ? $galleryToken : '') : '';
    $alignment = $selectedAfter ? smile_design_alignment_for_after($selectedAfter) : smile_design_alignment_defaults();
    $latestVideo = smile_design_latest_case_video($caseId);
    $videoUrl = $latestVideo ? smile_design_case_video_url((int)$latestVideo['id'], $isGalleryTokenAccess ? $galleryToken : '') : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Doctor Gallery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
    <style>
        html, body { background: #000; }
        .gallery-scroll { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.35) transparent; }
        .gallery-shell { min-height: 100dvh; background: #000; color: #fff; }
        .gallery-app-controls { position: fixed; right: 14px; bottom: 14px; z-index: 70; display: flex; gap: 8px; }
        .gallery-app-control { min-height: 42px; border-radius: 6px; border: 1px solid rgba(255,255,255,.24); background: rgba(0,0,0,.78); padding: 0 12px; color: #fff; font-size: 12px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; backdrop-filter: blur(10px); }
        .gallery-app-control:hover { border-color: rgba(255,255,255,.55); background: rgba(255,255,255,.1); }
        .gallery-app-control[data-gallery-exit-fullscreen] { display: none; }
        .gallery-is-fullscreen .gallery-app-control[data-gallery-fullscreen] { display: none; }
        .gallery-is-fullscreen .gallery-app-control[data-gallery-exit-fullscreen] { display: inline-flex; align-items: center; }
        .gallery-picker { min-height: 100dvh; display: grid; grid-template-rows: auto minmax(0, 1fr); gap: 18px; padding: 18px; }
        .gallery-picker-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); grid-auto-rows: max-content; align-content: start; align-items: start; gap: 12px; overflow: auto; padding-right: 4px; }
        .gallery-lvi-card { position: relative; min-height: 198px; overflow: hidden; border-radius: 6px; border: 1px solid rgba(255,255,255,.16); background: #050505; }
        .gallery-lvi-card img { height: 100%; width: 100%; object-fit: cover; opacity: .86; transition: transform 220ms ease, opacity 220ms ease; }
        .gallery-lvi-card:hover img { transform: scale(1.04); opacity: 1; }
        .gallery-lvi-card::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.05), rgba(0,0,0,.74)); }
        .gallery-lvi-card-content { position: absolute; inset: auto 12px 12px 12px; z-index: 1; }
        .gallery-catalog { height: 100dvh; overflow: hidden; background: #000; color: #fff; padding: 12px; }
        .gallery-catalog-head { height: 52px; display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 1px solid rgba(255,255,255,.1); padding: 0 4px 12px; }
        .gallery-catalog-grid { height: calc(100dvh - 76px); display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); grid-template-rows: repeat(4, minmax(0, 1fr)); gap: 10px; padding-top: 12px; }
        .gallery-catalog-tile { position: relative; min-width: 0; overflow: hidden; border: 1px solid rgba(255,255,255,.12); border-radius: 6px; background: #000; cursor: pointer; transition: transform 180ms ease, border-color 180ms ease, z-index 180ms ease; }
        .gallery-catalog-tile:hover { z-index: 3; transform: scale(1.025); border-color: rgba(255,255,255,.42); }
        .gallery-catalog-tile img { height: 100%; width: 100%; object-fit: cover; }
        .gallery-catalog-caption { position: absolute; left: 0; right: 0; bottom: 0; padding: 18px 10px 7px; background: linear-gradient(180deg, transparent, rgba(0,0,0,.72)); }
        .gallery-catalog-modal { position: fixed; inset: 0; z-index: 80; display: none; background: rgba(0,0,0,.96); }
        .gallery-catalog-modal[aria-hidden="false"] { display: block; }
        .gallery-catalog-modal img { height: 100dvh; width: 100vw; object-fit: contain; }
        .gallery-catalog-close { position: fixed; right: 18px; top: 18px; z-index: 81; display: grid; min-height: 44px; min-width: 44px; place-items: center; border-radius: 999px; border: 1px solid rgba(255,255,255,.28); background: rgba(0,0,0,.72); font-size: 28px; line-height: 1; color: #fff; }
        .gallery-present { height: 100dvh; display: grid; grid-template-columns: 250px minmax(0, 1fr); gap: 12px; padding: 12px; overflow: hidden; }
        .gallery-left { min-height: 0; display: grid; grid-template-rows: auto auto minmax(0, 1fr) auto; gap: 14px; border: 1px solid rgba(255,255,255,.1); border-radius: 8px; background: #050505; padding: 14px; }
        .gallery-logo { width: 154px; height: auto; border-radius: 4px; background: #fff; padding: 7px; }
        .gallery-angle { display: block; width: 100%; text-align: left; border: 1px solid rgba(255,255,255,.1); border-radius: 6px; background: rgba(255,255,255,.035); padding: 8px; }
        .gallery-angle:hover { border-color: rgba(255,255,255,.3); }
        .gallery-angle[aria-pressed="true"] { border-color: #fff; background: rgba(255,255,255,.1); }
        .gallery-stage { min-height: 0; height: calc(100dvh - 24px); overflow: hidden; border: 1px solid rgba(255,255,255,.1); border-radius: 8px; background: #050505; }
        .gallery-stage .sd-viewer-wrap { height: 100%; }
        .gallery-stage .sd-viewer-shell { height: 100%; display: block; }
        .gallery-stage .sd-frame,
        .gallery-stage .sd-side-panel,
        .gallery-stage .sd-opacity-shell,
        .gallery-stage .sd-zoom-panel,
        .gallery-stage .sd-zoom-frame,
        .gallery-stage .sd-video-player,
        .gallery-stage .sd-placeholder { background: #000; }
        .gallery-stage .sd-viewer { height: 100%; display: grid; grid-template-columns: minmax(0, 1fr) 92px; grid-template-rows: minmax(0, 1fr); border-radius: 8px; }
        .gallery-stage .sd-toolbar { grid-column: 2; grid-row: 1; flex-direction: column; align-items: stretch; justify-content: center; gap: 10px; border-bottom: 0; border-left: 1px solid rgba(255,255,255,.1); padding: 10px; }
        .gallery-stage .sd-toolbar > div:first-child { display: none; }
        .gallery-stage .sd-mode-group { display: grid; gap: 8px; width: 100%; }
        .gallery-stage .sd-mode-btn { width: 100%; min-height: 44px; padding: 8px 6px; font-size: 11px; line-height: 1.1; }
        .gallery-stage [data-sd-mode-panel] { grid-column: 1; grid-row: 1; min-height: 0; height: 100%; overflow: hidden; }
        .gallery-stage .sd-label-row { min-height: 42px; }
        .gallery-stage .sd-frame { height: calc(100% - 42px); min-height: 0; aspect-ratio: auto; }
        .gallery-stage .sd-side, .gallery-stage .sd-side-panel, .gallery-stage .sd-opacity-shell { height: 100%; }
        .gallery-stage .sd-zoom-stack { height: 100%; grid-template-rows: minmax(0, 1fr) minmax(0, 1fr); }
        .gallery-stage .sd-zoom-panel { min-height: 0; display: grid; grid-template-rows: auto minmax(0, 1fr); }
        .gallery-stage .sd-zoom-frame { height: 100%; min-height: 0; aspect-ratio: auto; }
        .gallery-stage .sd-video-panel { min-height: 0; height: calc(100% - 42px); padding: 10px; }
        .gallery-stage .sd-video-player { width: 100%; height: 100%; max-height: none; }
        @media (max-width: 900px) {
            body { overflow: auto; }
            .gallery-catalog { height: auto; min-height: 100dvh; overflow: auto; }
            .gallery-catalog-grid { height: auto; grid-template-columns: 1fr; grid-template-rows: none; overflow: visible; }
            .gallery-catalog-tile { aspect-ratio: 4 / 3; }
            .gallery-present { height: auto; min-height: 100dvh; grid-template-columns: 1fr; overflow: visible; }
            .gallery-left { grid-template-rows: auto auto auto auto; }
            .gallery-stage { height: 72dvh; min-height: 520px; }
            .gallery-stage .sd-viewer { grid-template-columns: 1fr; grid-template-rows: auto minmax(0, 1fr); }
            .gallery-stage .sd-toolbar { grid-column: 1; grid-row: 1; border-left: 0; border-bottom: 1px solid rgba(255,255,255,.1); }
            .gallery-stage .sd-mode-group { display: flex; overflow-x: auto; }
            .gallery-stage [data-sd-mode-panel] { grid-column: 1; grid-row: 2; }
        }
    </style>
</head>
<body class="bg-black text-white antialiased <?= ($case || $showLviCatalog) ? 'overflow-hidden' : '' ?>">
<main class="gallery-shell">
    <div class="gallery-app-controls">
        <button type="button" class="gallery-app-control" data-gallery-fullscreen>Fullscreen</button>
        <button type="button" class="gallery-app-control" data-gallery-exit-fullscreen>Exit Fullscreen</button>
    </div>
    <?php if ($showLviCatalog && !$case): ?>
        <section class="gallery-catalog" data-gallery-catalog>
            <div class="gallery-catalog-head">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/45">Elite Smiles</p>
                    <h1 class="text-xl font-semibold tracking-tight">LVI Catalog</h1>
                </div>
                <?php $catalogBackParams = trim(($query !== '' ? 'q=' . rawurlencode($query) : '') . ($isGalleryTokenAccess ? (($query !== '' ? '&' : '') . 'token=' . rawurlencode($galleryToken)) : '')); ?>
                <a class="grid h-11 place-items-center rounded-md border border-white/25 px-4 text-sm font-semibold text-white/85 hover:border-white/50" href="<?= e(base_url('smile-design/gallery' . ($catalogBackParams !== '' ? '?' . $catalogBackParams : ''))) ?>">Back</a>
            </div>
            <div class="gallery-catalog-grid">
                <?php foreach (array_slice($lviCatalogImages, 0, 12) as $image): ?>
                    <?php
                    $styleTitle = (string)($image['style_title'] ?? $image['style_key'] ?? 'LVI');
                    $imageTitle = trim((string)($image['title'] ?? ''));
                    $imageUrl = base_url('app/actions/smile_design_photo.php?lvi_sample_id=' . (int)$image['id'] . $tokenQuery);
                    ?>
                    <button type="button" class="gallery-catalog-tile" data-lvi-fullscreen="<?= e($imageUrl) ?>" data-lvi-title="<?= e($imageTitle !== '' ? $imageTitle : $styleTitle) ?>" aria-label="Open <?= e($styleTitle) ?> LVI reference image">
                        <img src="<?= e($imageUrl) ?>" alt="<?= e($imageTitle !== '' ? $imageTitle : $styleTitle) ?>" loading="eager">
                        <span class="gallery-catalog-caption">
                            <span class="block text-sm font-bold leading-tight"><?= e($styleTitle) ?></span>
                        </span>
                    </button>
                <?php endforeach; ?>
                <?php if ($lviCatalogImages === []): ?>
                    <div class="rounded-md border border-dashed border-white/20 p-6 text-sm leading-6 text-white/60">No LVI catalog images are active yet.</div>
                <?php endif; ?>
            </div>
            <div class="gallery-catalog-modal" data-lvi-modal aria-hidden="true">
                <button type="button" class="gallery-catalog-close" data-lvi-close aria-label="Close image">&times;</button>
                <img data-lvi-modal-image src="" alt="">
            </div>
        </section>
    <?php elseif (!$case): ?>
        <section class="gallery-picker">
            <div class="flex flex-col gap-4 border-b border-white/10 pb-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/45">Elite Smiles</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight">Doctor Presentation Gallery</h1>
                </div>
                <form class="flex w-full max-w-xl gap-2" method="GET" action="<?= e(base_url('smile-design/gallery')) ?>">
                    <label class="sr-only" for="gallery-search">Search cases</label>
                    <input id="gallery-search" name="q" value="<?= e($query) ?>" class="h-11 min-w-0 flex-1 rounded-md border border-white/15 bg-white/[0.06] px-3 text-sm text-white outline-none placeholder:text-white/35 focus:border-white/45" placeholder="Search patient, procedure, style...">
                    <?php if ($isGalleryTokenAccess): ?><input type="hidden" name="token" value="<?= e($galleryToken) ?>"><?php endif; ?>
                    <button class="h-11 rounded-md bg-white px-4 text-sm font-bold text-black" type="submit">Search</button>
                    <?php if (!$isGalleryTokenAccess): ?><a class="grid h-11 place-items-center rounded-md border border-white/20 px-4 text-sm font-semibold text-white/75" href="<?= e(base_url('smile-design/cases')) ?>">Cases</a><?php endif; ?>
                </form>
            </div>
            <?php if (!$isGalleryTokenAccess && ($message = flash_get('success'))): ?>
                <div class="rounded-md border border-emerald-300/25 bg-emerald-400/10 px-4 py-3 text-sm leading-6 text-emerald-100"><?= e((string)$message) ?></div>
            <?php endif; ?>
            <?php if (!$isGalleryTokenAccess && ($message = flash_get('error'))): ?>
                <div class="rounded-md border border-red-300/25 bg-red-400/10 px-4 py-3 text-sm leading-6 text-red-100"><?= e((string)$message) ?></div>
            <?php endif; ?>
            <div class="gallery-picker-grid gallery-scroll">
                <?php
                $lviHero = db_one(
                    "SELECT id, title
                     FROM lvi_sample_images
                     WHERE is_active = 1
                     ORDER BY id DESC
                     LIMIT 1"
                );
                $lviHeroUrl = $lviHero ? base_url('app/actions/smile_design_photo.php?lvi_sample_id=' . (int)$lviHero['id'] . $tokenQuery) : '';
                ?>
                <a class="gallery-lvi-card group" href="<?= e(base_url('smile-design/gallery?catalog=lvi' . ($query !== '' ? '&q=' . rawurlencode($query) : '') . $tokenQuery)) ?>">
                    <?php if ($lviHeroUrl !== ''): ?>
                        <img src="<?= e($lviHeroUrl) ?>" alt="LVI Catalog" loading="eager">
                    <?php endif; ?>
                    <span class="gallery-lvi-card-content">
                        <span class="block text-xs font-bold uppercase tracking-[0.22em] text-white/55">Shape Reference</span>
                        <span class="mt-2 block text-xl font-semibold leading-tight text-white">LVI Catalog</span>
                        <span class="mt-2 block text-xs leading-5 text-white/65">Open the doctor reference library.</span>
                    </span>
                </a>
                <?php foreach ($cases as $galleryCase): ?>
                    <?php
                    $frontBefore = smile_design_find_before_photo_by_type((int)$galleryCase['id'], 'front', true) ?: smile_design_primary_before_photo((int)$galleryCase['id']);
                    $frontAfter = smile_design_selected_after_version((int)$galleryCase['id'], 'front') ?: smile_design_selected_after_version((int)$galleryCase['id']);
                    $video = smile_design_latest_case_video((int)$galleryCase['id']);
                    ?>
                    <a class="group rounded-md border border-white/10 bg-white/[0.04] p-2 transition hover:border-white/35 hover:bg-white/[0.07]" href="<?= e(base_url('smile-design/gallery?case_id=' . (int)$galleryCase['id'] . ($query !== '' ? '&q=' . rawurlencode($query) : '') . $tokenQuery)) ?>">
                        <div class="grid grid-cols-2 gap-2">
                            <div class="aspect-[4/5] overflow-hidden rounded bg-white/5">
                                <?php if ($frontBefore): ?><img class="h-full w-full object-cover" src="<?= e(smile_design_photo_url((int)$frontBefore['id'], $isGalleryTokenAccess ? $galleryToken : '')) ?>" alt="Before"><?php endif; ?>
                            </div>
                            <div class="aspect-[4/5] overflow-hidden rounded bg-white/5">
                                <?php if ($frontAfter): ?><img class="h-full w-full object-cover" src="<?= e(smile_design_after_url((int)$frontAfter['id'], $isGalleryTokenAccess ? $galleryToken : '')) ?>" alt="After"><?php endif; ?>
                            </div>
                        </div>
                        <div class="mt-3 flex items-start justify-between gap-3 px-1 pb-1">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-white"><?= e((string)$galleryCase['patient_name']) ?></p>
                                <p class="mt-1 truncate text-xs text-white/55"><?= e((string)$galleryCase['procedure_interest']) ?></p>
                            </div>
                            <?php if ($video): ?><span class="rounded-full bg-white px-2 py-1 text-[10px] font-bold uppercase tracking-[0.12em] text-black">Video</span><?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
                <?php if ($cases === []): ?>
                    <div class="rounded-md border border-dashed border-white/20 p-6 text-sm leading-6 text-white/60">No smile cases matched this search.</div>
                <?php endif; ?>
            </div>
        </section>
    <?php else: ?>
        <section class="gallery-present">
            <aside class="gallery-left">
                <div>
                    <img class="gallery-logo" src="<?= e(SMILE_DESIGN_LOGO_URL) ?>" alt="Elite Smiles">
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-white/40">Presentation Case</p>
                    <h1 class="mt-2 text-xl font-semibold leading-tight"><?= e((string)$case['patient_name']) ?></h1>
                    <p class="mt-1 text-xs leading-5 text-white/55"><?= e(format_datetime((string)$case['created_at'])) ?></p>
                    <p class="mt-2 text-xs leading-5 text-white/55"><?= e((string)$case['procedure_interest']) ?> &middot; <?= e((string)$case['lvi_style_key']) ?></p>
                </div>
                <div class="gallery-scroll min-h-0 overflow-auto">
                    <p class="mb-3 text-xs font-bold uppercase tracking-[0.22em] text-white/40">Before Pictures</p>
                    <div class="grid gap-3">
                        <?php foreach ($angleThumbs as $photoType => $thumb): ?>
                            <?php $active = $photoType === $selectedAngle; ?>
                            <?php $thumbAlignment = (string)json_encode($thumb['alignment'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
                            <button type="button" class="gallery-angle" data-gallery-angle="<?= e($photoType) ?>" data-before-url="<?= e((string)$thumb['before_url']) ?>" data-after-url="<?= e((string)$thumb['after_url']) ?>" data-before-label="<?= e((string)$thumb['label']) ?>" data-after-label="<?= e((string)$thumb['after_label']) ?>" data-alignment="<?= e($thumbAlignment) ?>" aria-pressed="<?= $active ? 'true' : 'false' ?>">
                                <div class="aspect-[4/3] overflow-hidden rounded bg-white/5">
                                    <?php if ($thumb['before_url'] !== ''): ?><img class="h-full w-full object-cover" src="<?= e((string)$thumb['before_url']) ?>" alt="<?= e((string)$thumb['label']) ?> before"><?php endif; ?>
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <span class="text-xs font-bold text-white"><?= e((string)$thumb['label']) ?></span>
                                    <span class="text-[10px] font-bold uppercase tracking-[0.1em] <?= $thumb['after_url'] !== '' ? 'text-emerald-200' : 'text-white/35' ?>"><?= $thumb['after_url'] !== '' ? 'After' : 'Pending' ?></span>
                                </div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="grid gap-2">
                    <?php $changeCaseParams = trim(($query !== '' ? 'q=' . rawurlencode($query) : '') . ($isGalleryTokenAccess ? (($query !== '' ? '&' : '') . 'token=' . rawurlencode($galleryToken)) : '')); ?>
                    <a class="rounded-md border border-white/20 px-3 py-2 text-center text-xs font-semibold text-white/75" href="<?= e(base_url('smile-design/gallery' . ($changeCaseParams !== '' ? '?' . $changeCaseParams : ''))) ?>">Change Case</a>
                    <?php if (!$isGalleryTokenAccess): ?><a class="rounded-md border border-white/20 px-3 py-2 text-center text-xs font-semibold text-white/75" href="<?= e(base_url('smile-design/cases/' . $caseId . '#compare')) ?>">Open Case</a><?php endif; ?>
                </div>
            </aside>
            <div class="gallery-stage">
                <?php smile_before_after_viewer($beforeUrl, $afterUrl, [
                    'title' => (string)$case['patient_name'],
                    'mode' => 'compare',
                    'alignment' => $alignment,
                    'input_gallery' => [],
                    'photo_type' => $selectedAngle,
                    'video_url' => $videoUrl,
                ]); ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<script>
function syncGalleryFullscreenState() {
    document.body.classList.toggle('gallery-is-fullscreen', !!document.fullscreenElement);
}

document.addEventListener('fullscreenchange', syncGalleryFullscreenState);

document.addEventListener('click', async function (event) {
    const fullButton = event.target.closest('[data-gallery-fullscreen]');
    if (!fullButton) return;
    try {
        await document.documentElement.requestFullscreen({ navigationUI: 'hide' });
    } catch (error) {
        try { await document.documentElement.requestFullscreen(); } catch (fallbackError) {}
    }
    syncGalleryFullscreenState();
});

document.addEventListener('click', async function (event) {
    const navButton = event.target.closest('[data-gallery-exit-fullscreen]');
    if (!navButton) return;
    if (document.fullscreenElement) {
        try { await document.exitFullscreen(); } catch (error) {}
    }
    syncGalleryFullscreenState();
});

document.addEventListener('click', function (event) {
    const tile = event.target.closest('[data-lvi-fullscreen]');
    if (!tile) return;
    const modal = document.querySelector('[data-lvi-modal]');
    const image = document.querySelector('[data-lvi-modal-image]');
    if (!modal || !image) return;
    image.setAttribute('src', tile.getAttribute('data-lvi-fullscreen') || '');
    image.setAttribute('alt', tile.getAttribute('data-lvi-title') || 'LVI reference image');
    modal.setAttribute('aria-hidden', 'false');
});

document.addEventListener('click', function (event) {
    const close = event.target.closest('[data-lvi-close]');
    const modal = document.querySelector('[data-lvi-modal]');
    const image = document.querySelector('[data-lvi-modal-image]');
    if (!modal || !image) return;
    if (!close && event.target !== modal) return;
    modal.setAttribute('aria-hidden', 'true');
    image.setAttribute('src', '');
});

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    const modal = document.querySelector('[data-lvi-modal]');
    const image = document.querySelector('[data-lvi-modal-image]');
    if (!modal || modal.getAttribute('aria-hidden') === 'true') return;
    modal.setAttribute('aria-hidden', 'true');
    if (image) image.setAttribute('src', '');
});

document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-gallery-angle]');
    if (!button) return;
    const shell = button.closest('.gallery-present');
    if (!shell) return;

    const beforeUrl = button.getAttribute('data-before-url') || '';
    const afterUrl = button.getAttribute('data-after-url') || '';
    const beforeLabel = button.getAttribute('data-before-label') || 'Before';
    const afterLabel = button.getAttribute('data-after-label') || (afterUrl ? 'Result' : 'Result pending');
    const viewerWrap = shell.querySelector('[data-sd-viewer-wrap]');
    if (!viewerWrap) return;

    shell.querySelectorAll('[data-gallery-angle]').forEach(function (node) {
        node.setAttribute('aria-pressed', node === button ? 'true' : 'false');
    });
    viewerWrap.querySelectorAll('[data-sd-before-image]').forEach(function (img) {
        if (beforeUrl) img.setAttribute('src', beforeUrl);
    });
    viewerWrap.querySelectorAll('[data-sd-after-image]').forEach(function (img) {
        if (afterUrl) {
            img.setAttribute('src', afterUrl);
            img.classList.remove('sd-hidden');
        } else {
            img.removeAttribute('src');
            img.classList.add('sd-hidden');
        }
    });
    viewerWrap.querySelectorAll('[data-sd-after-placeholder]').forEach(function (node) {
        node.classList.toggle('sd-hidden', !!afterUrl);
    });
    viewerWrap.querySelectorAll('[data-sd-after-layer], [data-sd-handle]').forEach(function (node) {
        node.classList.toggle('sd-hidden', !afterUrl);
    });
    viewerWrap.querySelectorAll('[data-sd-before-label]').forEach(function (node) {
        node.textContent = beforeLabel;
    });
    viewerWrap.querySelectorAll('[data-sd-after-label]').forEach(function (node) {
        node.textContent = afterLabel;
    });

    let alignment = null;
    try { alignment = JSON.parse(button.getAttribute('data-alignment') || ''); } catch (error) { alignment = null; }
    if (alignment) {
        const map = {
            before_x: ['--sd-before-x', '%'],
            before_y: ['--sd-before-y', '%'],
            before_zoom: ['--sd-before-zoom', ''],
            after_x: ['--sd-after-x', '%'],
            after_y: ['--sd-after-y', '%'],
            after_zoom: ['--sd-after-zoom', ''],
            rotation: ['--sd-before-rotate', 'deg']
        };
        Object.keys(map).forEach(function (name) {
            if (typeof alignment[name] === 'undefined') return;
            const value = parseFloat(alignment[name]);
            if (!Number.isFinite(value)) return;
            viewerWrap.style.setProperty(map[name][0], value + map[name][1]);
            if (name === 'rotation') viewerWrap.style.setProperty('--sd-after-rotate', value + map[name][1]);
        });
        if (alignment.crop_aspect_ratio) {
            const parts = String(alignment.crop_aspect_ratio).split(':');
            if (parts.length === 2) viewerWrap.style.setProperty('--sd-frame-aspect', parts[0] + ' / ' + parts[1]);
        }
    }

    const url = new URL(window.location.href);
    url.searchParams.set('angle', button.getAttribute('data-gallery-angle') || 'front');
    window.history.replaceState({}, '', url.toString());
});
</script>
</body>
</html>
