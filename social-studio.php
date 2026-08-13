<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/social_studio/social_studio_service.php';

require_auth();
social_studio_ensure_schema();

if (is_post() && post('action') === 'logout') {
    require_csrf();
    auth_logout();
    redirect(base_url('login.php'));
}

$user = auth_user() ?: [];
$currentPage = 'social_studio';
$pageTitle = 'Social Studio';
$logoutAction = base_url('social-studio.php');
$successMessage = flash_get('success') ?? '';
$errorMessage = flash_get('error') ?? '';
$autoGenerateIds = array_values(array_filter(array_map('intval', explode(',', (string)(flash_get('social_auto_generate_ids') ?? ''))), static fn(int $id): bool => $id > 0));
$data = social_studio_dashboard_data();
$visualReferences = social_studio_visual_references();
uasort($visualReferences, static fn(array $left, array $right): int => (int)!empty($right['ready']) <=> (int)!empty($left['ready']));
$counts = $data['counts'];
$drafts = $data['drafts'];
$selected = $data['selected'];
$schedule = $data['schedule'];
$approvedUnscheduled = $data['approved_unscheduled'];
$calendarItems = $data['calendar_items'];
$publishedDrafts = $data['published_drafts'];
$weekStart = $data['week_start'];
$weekEnd = $data['week_end'];
$weekDays = social_studio_week_days($weekStart);
$activeView = strtolower(trim((string)get('view', 'create')));
if (!in_array($activeView, ['create', 'calendar', 'published'], true)) $activeView = 'create';
$calendarByDay = [];
foreach ($calendarItems as $calendarItem) {
    $calendarByDay[date('Y-m-d', strtotime((string)$calendarItem['scheduled_at']))][] = $calendarItem;
}
$baseAnalysisProgress = social_studio_base_analysis_progress();
$readyReferences = array_filter($visualReferences, static fn(array $reference): bool => !empty($reference['ready']));
$pendingBaseIds = array_values(array_map(static fn(string $key): int => (int)substr($key, 5), array_filter(array_keys($visualReferences), static fn(string $key): bool => str_starts_with($key, 'base_') && empty($visualReferences[$key]['ready']))));
$defaultReferenceKey = (string)(array_key_first($readyReferences) ?? '');
$groups = array_values(array_unique(array_filter(array_map(static fn(array $reference): string => trim((string)($reference['group'] ?? '')), $visualReferences))));
sort($groups);

function social_studio_badge_class(string $status): string
{
    return match ($status) {
        'approved', 'scheduled' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'review' => 'border-amber-200 bg-amber-50 text-amber-800',
        'published', 'publishing' => 'border-blue-200 bg-blue-50 text-blue-700',
        'rejected', 'publish_failed' => 'border-rose-200 bg-rose-50 text-rose-700',
        default => 'border-slate-200 bg-slate-100 text-slate-600',
    };
}

$selectedImageUrl = $selected ? social_studio_image_url($selected) : '';
$defaultScheduleLocal = date('Y-m-d\TH:i', strtotime(social_studio_next_slot(0)));
$calendarNow = new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
$calendarDefaultSlot = null;
foreach ($weekDays as $calendarDay) {
    $candidateSlot = $calendarDay->setTime(10, 30);
    if ($candidateSlot > $calendarNow->modify('+1 minute')) {
        $calendarDefaultSlot = $candidateSlot;
        break;
    }
}
$calendarDefaultSlot ??= $weekStart->modify('+7 days')->setTime(10, 30);
$calendarDefaultScheduleLocal = $calendarDefaultSlot->format('Y-m-d\TH:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e(APP_NAME) ?> | Social Studio</title>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/lead-agent.css?v=' . (string)(@filemtime(__DIR__ . '/assets/css/lead-agent.css') ?: '1'))) ?>">
    <style>
        .social-template-card[aria-pressed="true"] { border-color:#0f172a; box-shadow:0 0 0 2px #0f172a; }
        .social-template-card:disabled { cursor:not-allowed; opacity:.68; }
        .social-template-card[hidden] { display:none; }
        .social-preview-image { aspect-ratio:4/5; width:100%; object-fit:contain; background:#f1f5f9; }
        .social-scrollbar { scrollbar-width:thin; scrollbar-color:#94a3b8 transparent; }
        .social-workspace-tab[aria-current="page"] { background:#0f172a; border-color:#0f172a; color:#fff; }
        .social-calendar-day-today { border-color:#0f172a; box-shadow:0 0 0 1px #0f172a; }
        .social-calendar-card { transition:transform .2s ease, box-shadow .2s ease; }
        .social-calendar-card:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(15,23,42,.08); }
        @media (prefers-reduced-motion:reduce) { .social-calendar-card { transition:none; } .social-calendar-card:hover { transform:none; } }
        @media (min-width:1280px) { .social-production-grid { grid-template-columns:300px minmax(420px,1fr) 360px; } }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<?php require __DIR__ . '/app/partials/crm_sidebar.php'; ?>

<main class="px-4 py-5 sm:px-6 lg:pl-80 lg:pr-8 lg:py-6">
    <header class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Marketing / Social Studio</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Create from what already works</h1>
            <p class="mt-1 text-sm text-slate-600">Choose an approved post, change the photo direction, then review the exact overlay before approval.</p>
        </div>
        <div class="flex flex-wrap gap-2 text-xs font-semibold">
            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800"><?= e((string)$baseAnalysisProgress['ready']) ?> ready</span>
            <span class="rounded-full border border-slate-200 bg-white px-3 py-2 text-slate-700"><?= e((string)$baseAnalysisProgress['remaining']) ?> analyzing</span>
            <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800"><?= e((string)($counts['review'] ?? 0)) ?> in review</span>
        </div>
    </header>

    <nav class="mb-5 grid gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm sm:grid-cols-3" aria-label="Social Studio workspace">
        <a class="social-workspace-tab flex min-h-12 items-center justify-between rounded-xl border border-transparent px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2" href="<?= e(base_url('social-studio.php?view=create')) ?>" aria-current="<?= $activeView === 'create' ? 'page' : 'false' ?>"><span>Create &amp; review</span><span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700"><?= e((string)($counts['review'] ?? 0)) ?></span></a>
        <a class="social-workspace-tab flex min-h-12 items-center justify-between rounded-xl border border-transparent px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2" href="<?= e(base_url('social-studio.php?view=calendar&week=' . $weekStart->format('Y-m-d'))) ?>" aria-current="<?= $activeView === 'calendar' ? 'page' : 'false' ?>"><span>Content calendar</span><span class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-700"><?= e((string)($counts['scheduled'] ?? 0)) ?></span></a>
        <a class="social-workspace-tab flex min-h-12 items-center justify-between rounded-xl border border-transparent px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2" href="<?= e(base_url('social-studio.php?view=published')) ?>" aria-current="<?= $activeView === 'published' ? 'page' : 'false' ?>"><span>Published</span><span class="rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-700"><?= e((string)($counts['published'] ?? 0)) ?></span></a>
    </nav>

    <?php if ($successMessage !== ''): ?><div role="status" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e((string)$successMessage) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div role="alert" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?= e((string)$errorMessage) ?></div><?php endif; ?>
    <?php if ($autoGenerateIds !== []): ?><div id="social-generation-progress" role="status" aria-live="polite" class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-800">Preparing image generation…</div><?php endif; ?>

    <?php if ($activeView === 'create'): ?>
    <section class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="template-library-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Step 1</p>
                <h2 id="template-library-title" class="mt-1 text-lg font-semibold text-slate-950">Choose the approved Instagram template</h2>
                <p class="mt-1 text-sm text-slate-600">Ready templates preserve approved wording and artwork. Pending templates stay visible but cannot generate yet.</p>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
                <label class="text-xs font-semibold text-slate-700">Search templates
                    <input id="social-template-search" type="search" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" placeholder="Veneers, implants, confidence…">
                </label>
                <label class="text-xs font-semibold text-slate-700">Angle
                    <select id="social-template-group" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm">
                        <option value="">All angles</option>
                        <?php foreach ($groups as $group): ?><option value="<?= e(mb_strtolower($group)) ?>"><?= e($group) ?></option><?php endforeach; ?>
                    </select>
                </label>
            </div>
        </div>
        <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100" aria-label="Template analysis progress">
            <?php $progressPercent = $baseAnalysisProgress['total'] > 0 ? (int)round(($baseAnalysisProgress['ready'] / $baseAnalysisProgress['total']) * 100) : 0; ?>
            <div class="h-full rounded-full bg-emerald-500" style="width:<?= e((string)$progressPercent) ?>%"></div>
        </div>
        <div id="social-template-carousel" class="social-scrollbar mt-4 flex gap-3 overflow-x-auto pb-3">
            <?php foreach ($visualReferences as $referenceKey => $reference): ?>
                <?php $isReady = !empty($reference['ready']); ?>
                <button type="button" class="social-template-card w-40 shrink-0 overflow-hidden rounded-xl border-2 border-transparent bg-slate-50 text-left transition hover:border-slate-400 disabled:hover:border-transparent" data-social-reference="<?= e($referenceKey) ?>" data-ready="<?= $isReady ? '1' : '0' ?>" data-search="<?= e(mb_strtolower(implode(' ', [(string)$reference['label'], (string)$reference['group'], (string)($reference['description'] ?? '')]))) ?>" data-group="<?= e(mb_strtolower((string)$reference['group'])) ?>" aria-pressed="<?= $referenceKey === $defaultReferenceKey ? 'true' : 'false' ?>" <?= $isReady ? '' : 'disabled' ?>>
                    <?php if (!empty($reference['image_url'])): ?>
                        <img src="<?= e((string)$reference['image_url']) ?>" class="h-32 w-full bg-slate-100 object-cover" width="160" height="128" loading="lazy" alt="<?= e((string)$reference['label']) ?>">
                    <?php else: ?>
                        <div class="grid h-32 place-items-center bg-slate-100 px-3 text-center text-xs text-slate-500">Image unavailable</div>
                    <?php endif; ?>
                    <span class="block px-3 pt-2 text-xs font-semibold leading-4 text-slate-900"><?= e((string)$reference['label']) ?></span>
                    <span class="block px-3 pb-3 pt-1 text-[11px] font-semibold <?= $isReady ? 'text-emerald-700' : 'text-amber-700' ?>"><?= $isReady ? 'Ready · exact overlay' : 'Pending analysis' ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <p id="social-template-empty" class="mt-3 hidden rounded-xl border border-dashed border-slate-300 p-4 text-center text-sm text-slate-500">No templates match this filter.</p>
    </section>

    <div class="social-production-grid grid gap-5">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="controls-title">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Step 2</p>
            <h2 id="controls-title" class="mt-1 text-lg font-semibold text-slate-950">Create the new version</h2>
            <p id="social-selected-template" class="mt-2 rounded-xl bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700"><?= $defaultReferenceKey !== '' ? e((string)$visualReferences[$defaultReferenceKey]['label']) : 'No ready template selected' ?></p>

            <form id="social-generate-form" class="mt-4 space-y-4" method="POST" enctype="multipart/form-data" action="<?= e(base_url('app/actions/social_studio_generate.php')) ?>">
                <?= csrf_input() ?>
                <input type="hidden" name="visual_reference" id="social-visual-reference" value="<?= e($defaultReferenceKey) ?>">
                <input type="hidden" name="creation_mode" value="remix">
                <input type="hidden" name="copy_mode" value="preserve">

                <label class="block text-sm font-semibold text-slate-800">Focus
                    <select name="focus" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3">
                        <option value="veneers">Veneers</option><option value="smile_makeover">Smile makeover</option><option value="implants">Implants / All-on-X</option><option value="lip_repositioning">Lip repositioning</option>
                    </select>
                </label>
                <label class="block text-sm font-semibold text-slate-800">Purpose
                    <select name="purpose" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3"><option value="educational">Educational</option><option value="social_ad">Social media ad</option></select>
                </label>
                <label class="block text-sm font-semibold text-slate-800">Audience
                    <select name="audience" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3"><option value="any">Any adult</option><option value="woman">Woman</option><option value="man">Man</option></select>
                </label>
                <label class="block text-sm font-semibold text-slate-800">Age range
                    <select name="age_range" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3"><option value="any">Any adult</option><option value="25-34">25–34</option><option value="35-44">35–44</option><option value="45-54">45–54</option><option value="55+">55+</option></select>
                </label>
                <label class="block text-sm font-semibold text-slate-800">Text position
                    <select name="text_position" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3"><option value="source">Original position</option><option value="left">Move overlay left</option><option value="right">Move overlay right</option></select>
                </label>

                <div class="grid grid-cols-[1fr_92px] gap-2">
                    <label class="block text-sm font-semibold text-slate-800">Photo direction <span class="font-normal text-slate-500">(optional)</span>
                        <textarea name="instruction" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Different background, wardrobe, expression, or life moment."></textarea>
                    </label>
                    <label class="block text-sm font-semibold text-slate-800">Posts
                        <select name="count" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3"><?php for ($i=1;$i<=7;$i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select>
                    </label>
                </div>

                <details class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-700">Advanced: new wording or uploaded inspiration</summary>
                    <div class="mt-3 space-y-3">
                        <label class="block text-xs font-semibold text-slate-700">Overlay wording
                            <select id="social-copy-mode-advanced" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option value="preserve">Keep approved wording exactly</option><option value="rewrite">Explicitly create new wording</option></select>
                        </label>
                        <label class="block text-xs font-semibold text-slate-700">Inspiration image
                            <input type="file" name="inspiration_image" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full rounded-xl border border-dashed border-slate-300 bg-white px-3 py-3 text-xs">
                        </label>
                    </div>
                </details>

                <button id="social-generate-button" type="submit" class="min-h-12 w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" <?= $defaultReferenceKey === '' ? 'disabled' : '' ?>>Generate clean photo + exact overlay</button>
                <p class="text-xs leading-5 text-slate-500">The photo remains text-free. The CRM applies the selected approved overlay afterward.</p>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="preview-title">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Step 3</p><h2 id="preview-title" class="mt-1 text-lg font-semibold text-slate-950">Full post preview</h2></div>
                <?php if ($selected): ?><span class="<?= e(social_studio_badge_class((string)$selected['status'])) ?> rounded-full border px-3 py-1 text-xs font-semibold"><?= e(social_studio_status_labels()[(string)$selected['status']] ?? (string)$selected['status']) ?></span><?php endif; ?>
            </div>
            <?php if ($selected): ?>
                <article class="mx-auto max-w-[620px] overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <header class="flex items-center gap-3 border-b border-slate-100 px-4 py-3"><img class="h-9 w-9 rounded-full object-cover" src="<?= e(base_url('assets/img/elite-smiles-instagram-avatar.jpg')) ?>" alt="Elite Smiles"><div><p class="text-sm font-semibold">elitesmilesutah</p><p class="text-xs text-slate-500">Elite Smiles by Walter Meden DDS</p></div><span class="ml-auto font-bold tracking-[0.2em]">···</span></header>
                    <?php if ($selectedImageUrl !== ''): ?><img class="social-preview-image" src="<?= e($selectedImageUrl) ?>" alt="<?= e((string)$selected['title']) ?>"><?php else: ?><div class="grid aspect-[4/5] place-items-center bg-slate-100 p-8 text-center text-sm text-slate-500">Generate the clean photo to assemble this post.</div><?php endif; ?>
                    <div class="border-t border-slate-100 px-4 py-3"><div class="mb-3 flex text-2xl"><span>♡　◯　➤</span><span class="ml-auto">⌑</span></div><p class="whitespace-pre-line text-sm leading-6 text-slate-700"><strong class="text-slate-950">elitesmilesutah</strong> <?= e((string)$selected['caption']) ?></p><p class="mt-3 text-xs leading-5 text-blue-700"><?= e((string)$selected['hashtags']) ?></p></div>
                </article>
            <?php else: ?>
                <div class="grid min-h-[560px] place-items-center rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center"><div><p class="text-base font-semibold text-slate-800">No draft selected</p><p class="mt-2 text-sm text-slate-500">Choose a ready template and generate a version.</p></div></div>
            <?php endif; ?>
        </section>

        <aside class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="queue-title">
            <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Step 4</p><h2 id="queue-title" class="mt-1 text-lg font-semibold text-slate-950">Review queue</h2></div><form method="POST" action="<?= e(base_url('app/actions/social_studio_clear.php')) ?>" onsubmit="return confirm('Clear every social draft? This cannot be undone.');"><?= csrf_input() ?><button class="min-h-11 rounded-xl border border-rose-200 px-3 text-xs font-semibold text-rose-700" type="submit">Clear</button></form></div>
            <div class="social-scrollbar mt-4 max-h-[740px] space-y-3 overflow-y-auto pr-1">
                <?php if ($drafts === []): ?><div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-center text-sm text-slate-500">No drafts waiting.</div><?php endif; ?>
                <?php foreach ($drafts as $draft): ?>
                    <?php $status=(string)($draft['status'] ?? 'review'); $imageUrl=social_studio_image_url($draft); $canPublish=in_array($status, ['approved','scheduled','publish_failed'], true); ?>
                    <article class="rounded-xl border border-slate-200 p-3">
                        <div class="flex gap-3"><?php if ($imageUrl !== ''): ?><img class="h-20 w-16 shrink-0 rounded-lg bg-slate-100 object-cover" src="<?= e($imageUrl) ?>" alt=""><?php else: ?><div class="grid h-20 w-16 shrink-0 place-items-center rounded-lg bg-slate-100 text-[10px] text-slate-500">No image</div><?php endif; ?><div class="min-w-0"><div class="flex flex-wrap items-center gap-1"><h3 class="text-sm font-semibold leading-5 text-slate-950"><?= e((string)$draft['title']) ?></h3><span class="<?= e(social_studio_badge_class($status)) ?> rounded-full border px-2 py-0.5 text-[10px] font-semibold"><?= e(social_studio_status_labels()[$status] ?? $status) ?></span></div><p class="mt-1 text-xs leading-5 text-slate-500"><?= e(str_limit((string)$draft['caption'], 90)) ?></p><?php if ((string)($draft['generation_status'] ?? '') === 'failed'): ?><p class="mt-1 text-xs font-semibold text-rose-700"><?= e((string)($draft['generation_error'] ?? 'Image generation failed')) ?></p><?php endif; ?><?php if (trim((string)($draft['publish_error'] ?? '')) !== ''): ?><p class="mt-1 text-xs font-semibold text-rose-700"><?= e(str_limit((string)$draft['publish_error'], 140)) ?></p><?php endif; ?><?php if ($status === 'scheduled' && !empty($draft['scheduled_at'])): ?><p class="mt-1 text-xs font-semibold text-emerald-700"><?= e(date('M j, g:i A T', strtotime((string)$draft['scheduled_at']))) ?></p><?php endif; ?></div></div>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button type="button" class="min-h-11 rounded-lg border border-slate-300 text-xs font-semibold" data-social-open data-draft-id="<?= e((string)$draft['id']) ?>" data-title="<?= e((string)$draft['title']) ?>" data-caption="<?= e((string)$draft['caption']) ?>" data-hashtags="<?= e((string)$draft['hashtags']) ?>" data-image="<?= e($imageUrl) ?>" data-status="<?= e(social_studio_status_labels()[$status] ?? $status) ?>">Open post</button>
                            <form method="POST" action="<?= e(base_url('app/actions/social_studio_generate_image.php')) ?>"><?= csrf_input() ?><input type="hidden" name="draft_id" value="<?= e((string)$draft['id']) ?>"><button class="min-h-11 w-full rounded-lg border border-blue-200 bg-blue-50 text-xs font-semibold text-blue-700" type="submit"><?= $imageUrl !== '' ? 'Regenerate' : 'Generate image' ?></button></form>
                            <?php if (in_array($status, ['review','draft'], true)): ?>
                                <form method="POST" action="<?= e(base_url('app/actions/social_studio_status.php')) ?>"><?= csrf_input() ?><input type="hidden" name="draft_id" value="<?= e((string)$draft['id']) ?>"><input type="hidden" name="status" value="approved"><button class="min-h-11 w-full rounded-lg border border-emerald-200 bg-emerald-50 px-2 text-xs font-semibold text-emerald-700 disabled:opacity-50" type="submit" <?= $imageUrl === '' ? 'disabled' : '' ?>>Approve &amp; schedule</button></form>
                            <?php elseif ($canPublish): ?>
                                <form method="POST" action="<?= e(base_url('app/actions/social_studio_publish.php')) ?>" onsubmit="return confirm('Publish this approved post now to Elite Smiles on Facebook and Instagram?');"><?= csrf_input() ?><input type="hidden" name="draft_id" value="<?= e((string)$draft['id']) ?>"><input type="hidden" name="mode" value="now"><button class="min-h-11 w-full rounded-lg bg-emerald-600 text-xs font-semibold text-white" type="submit"><?= $status === 'publish_failed' ? 'Retry publish' : 'Publish now' ?></button></form>
                            <?php else: ?>
                                <button class="min-h-11 rounded-lg border border-slate-200 text-xs font-semibold text-slate-500" type="button" disabled><?= e(social_studio_status_labels()[$status] ?? $status) ?></button>
                            <?php endif; ?>
                            <form method="POST" action="<?= e(base_url('app/actions/social_studio_delete.php')) ?>" onsubmit="return confirm('Delete this draft?');"><?= csrf_input() ?><input type="hidden" name="draft_id" value="<?= e((string)$draft['id']) ?>"><button class="min-h-11 w-full rounded-lg border border-rose-200 text-xs font-semibold text-rose-700" type="submit">Delete</button></form>
                        </div>
                        <?php if ($canPublish): ?><form method="POST" action="<?= e(base_url('app/actions/social_studio_publish.php')) ?>" class="mt-2 grid grid-cols-[1fr_auto] gap-2"><?= csrf_input() ?><input type="hidden" name="draft_id" value="<?= e((string)$draft['id']) ?>"><input type="hidden" name="mode" value="schedule"><label class="sr-only" for="schedule-<?= e((string)$draft['id']) ?>">Schedule publishing time</label><input id="schedule-<?= e((string)$draft['id']) ?>" name="scheduled_at" type="datetime-local" value="<?= e(!empty($draft['scheduled_at']) ? date('Y-m-d\TH:i', strtotime((string)$draft['scheduled_at'])) : $defaultScheduleLocal) ?>" min="<?= e(date('Y-m-d\TH:i', time()+60)) ?>" class="min-h-11 min-w-0 rounded-lg border border-slate-300 px-2 text-xs"><button class="min-h-11 rounded-lg border border-slate-300 px-3 text-xs font-semibold" type="submit">Schedule</button></form><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>

    <details class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <summary class="cursor-pointer text-sm font-semibold text-slate-800">Template library maintenance</summary>
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 p-4"><p class="text-sm font-semibold">Complete template analysis</p><p id="social-analysis-progress" class="mt-1 text-xs leading-5 text-slate-500"><?= e((string)$baseAnalysisProgress['ready']) ?> of <?= e((string)$baseAnalysisProgress['total']) ?> ready. Templates are processed one at a time so the page remains responsive.</p><button id="social-run-analysis" class="mt-3 min-h-11 rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" type="button" <?= $baseAnalysisProgress['remaining'] <= 0 ? 'disabled' : '' ?>>Analyze all <?= e((string)$baseAnalysisProgress['remaining']) ?> pending templates</button></div>
            <form method="POST" action="<?= e(base_url('app/actions/social_studio_import_instagram.php')) ?>" class="rounded-xl border border-slate-200 p-4"><?= csrf_input() ?><input type="hidden" name="batch_index" value="0"><label class="text-sm font-semibold">Import Instagram inventory JSON<textarea name="posts_json" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-xs" placeholder='[{"post_id":"...","published_at":"2026-03-16","caption":"...","hashtags":["#EliteSmiles"],"image_url":"..."}]'></textarea></label><button class="mt-3 min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold" type="submit">Import first item</button></form>
        </div>
    </details>
    <?php elseif ($activeView === 'calendar'): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="calendar-title">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Content planner</p>
                    <h2 id="calendar-title" class="mt-1 text-xl font-semibold text-slate-950">Week of <?= e($weekStart->format('F j')) ?>–<?= e($weekStart->modify('+6 days')->format('F j, Y')) ?></h2>
                    <p class="mt-1 text-sm text-slate-600">Approved posts wait above the timeline until you assign a date. Scheduled posts publish automatically through Meta.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a class="grid min-h-11 place-items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700" href="<?= e(base_url('social-studio.php?view=calendar&week=' . $weekStart->modify('-7 days')->format('Y-m-d'))) ?>">Previous</a>
                    <a class="grid min-h-11 place-items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700" href="<?= e(base_url('social-studio.php?view=calendar')) ?>">Today</a>
                    <a class="grid min-h-11 place-items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700" href="<?= e(base_url('social-studio.php?view=calendar&week=' . $weekStart->modify('+7 days')->format('Y-m-d'))) ?>">Next</a>
                </div>
            </div>

            <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="text-sm font-semibold text-slate-950">Ready to schedule</h3><p class="mt-1 text-xs text-slate-600"><?= e((string)count($approvedUnscheduled)) ?> approved post<?= count($approvedUnscheduled) === 1 ? '' : 's' ?> waiting for a time.</p></div><a class="text-sm font-semibold text-slate-700 underline underline-offset-4" href="<?= e(base_url('social-studio.php?view=create')) ?>">Create more posts</a></div>
                    <?php if ($approvedUnscheduled === []): ?><div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white p-5 text-center text-sm text-slate-500">No approved posts are waiting. Approve a finished post and it will appear here.</div><?php endif; ?>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($approvedUnscheduled as $draft): ?><?php $imageUrl=social_studio_image_url($draft); ?>
                            <article class="rounded-xl border border-slate-200 bg-white p-3 <?= (int)get('draft',0)===(int)$draft['id'] ? 'ring-2 ring-slate-900' : '' ?>">
                                <div class="flex gap-3"><?php if ($imageUrl !== ''): ?><img class="h-20 w-16 rounded-lg bg-slate-100 object-cover" src="<?= e($imageUrl) ?>" width="64" height="80" alt="<?= e((string)$draft['title']) ?>"><?php endif; ?><div class="min-w-0"><h4 class="text-sm font-semibold text-slate-950"><?= e((string)$draft['title']) ?></h4><p class="mt-1 text-xs leading-5 text-slate-500"><?= e(str_limit((string)$draft['caption'], 65)) ?></p></div></div>
                                <form method="POST" action="<?= e(base_url('app/actions/social_studio_publish.php')) ?>" class="mt-3 grid gap-2"><?= csrf_input() ?><input type="hidden" name="draft_id" value="<?= e((string)$draft['id']) ?>"><input type="hidden" name="mode" value="schedule"><label class="text-xs font-semibold text-slate-700" for="calendar-schedule-<?= e((string)$draft['id']) ?>">Publish date and time</label><input id="calendar-schedule-<?= e((string)$draft['id']) ?>" name="scheduled_at" type="datetime-local" value="<?= e($calendarDefaultScheduleLocal) ?>" min="<?= e(date('Y-m-d\TH:i', time()+60)) ?>" class="min-h-11 rounded-lg border border-slate-300 px-2 text-sm"><button class="min-h-11 rounded-lg bg-slate-950 px-3 text-sm font-semibold text-white" type="submit">Add to calendar</button></form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
                <form method="POST" action="<?= e(base_url('app/actions/social_studio_schedule_week.php')) ?>" class="rounded-xl border border-slate-200 p-4">
                    <?= csrf_input() ?><input type="hidden" name="week_start" value="<?= e($weekStart->format('Y-m-d')) ?>">
                    <h3 class="text-sm font-semibold text-slate-950">Fill this week</h3><p class="mt-1 text-xs leading-5 text-slate-600">Place one approved post per future day. You can adjust each time afterward.</p>
                    <div class="mt-4 grid grid-cols-2 gap-3"><label class="text-xs font-semibold text-slate-700">Posts<select name="count" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"><?php for($i=1;$i<=7;$i++): ?><option value="<?= $i ?>" <?= $i===min(7,max(1,count($approvedUnscheduled)))?'selected':'' ?>><?= $i ?></option><?php endfor; ?></select></label><label class="text-xs font-semibold text-slate-700">Daily time<input name="publish_time" type="time" value="10:30" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"></label></div>
                    <button type="submit" class="mt-3 min-h-11 w-full rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-sm font-semibold text-emerald-800" <?= $approvedUnscheduled === [] ? 'disabled' : '' ?>>Fill week with approved posts</button>
                </form>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-7" aria-label="Weekly publishing timeline">
                <?php $today=(new DateTimeImmutable('now',new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d'); foreach ($weekDays as $day): ?><?php $dayKey=$day->format('Y-m-d'); $dayItems=$calendarByDay[$dayKey]??[]; ?>
                    <section class="min-h-[360px] rounded-xl border bg-slate-50 p-3 <?= $dayKey===$today?'social-calendar-day-today':'border-slate-200' ?>" aria-labelledby="day-<?= e($dayKey) ?>">
                        <div class="flex items-center justify-between"><div><p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500"><?= e($day->format('D')) ?></p><h3 id="day-<?= e($dayKey) ?>" class="text-lg font-semibold text-slate-950"><?= e($day->format('j')) ?></h3></div><?php if($dayKey===$today): ?><span class="rounded-full bg-slate-950 px-2 py-1 text-[10px] font-semibold text-white">Today</span><?php endif; ?></div>
                        <div class="mt-3 space-y-3"><?php if($dayItems===[]): ?><div class="rounded-lg border border-dashed border-slate-300 bg-white p-4 text-center text-xs text-slate-500">Open day</div><?php endif; ?>
                            <?php foreach($dayItems as $draft): ?><?php $imageUrl=social_studio_image_url($draft); $status=(string)$draft['status']; ?>
                                <article class="social-calendar-card rounded-lg border border-slate-200 bg-white p-2"><div class="flex gap-2"><?php if($imageUrl!==''): ?><img class="h-14 w-11 rounded-md object-cover" src="<?= e($imageUrl) ?>" width="44" height="56" alt=""><?php endif; ?><div class="min-w-0"><p class="text-xs font-semibold text-slate-950"><?= e(date('g:i A',strtotime((string)$draft['scheduled_at']))) ?></p><p class="mt-1 text-xs leading-4 text-slate-600"><?= e(str_limit((string)$draft['title'],35)) ?></p></div></div><div class="mt-2 flex items-center justify-between gap-2"><span class="<?= e(social_studio_badge_class($status)) ?> rounded-full border px-2 py-1 text-[10px] font-semibold"><?= e(social_studio_status_labels()[$status]??$status) ?></span><button type="button" class="min-h-11 px-2 text-xs font-semibold text-slate-700" data-social-open data-draft-id="<?= e((string)$draft['id']) ?>" data-title="<?= e((string)$draft['title']) ?>" data-caption="<?= e((string)$draft['caption']) ?>" data-hashtags="<?= e((string)$draft['hashtags']) ?>" data-image="<?= e($imageUrl) ?>" data-status="<?= e(social_studio_status_labels()[$status]??$status) ?>">Open</button></div><?php if(in_array($status,['scheduled','publish_failed'],true)): ?><form method="POST" action="<?= e(base_url('app/actions/social_studio_publish.php')) ?>" class="mt-2 grid gap-2"><?= csrf_input() ?><input type="hidden" name="draft_id" value="<?= e((string)$draft['id']) ?>"><input type="hidden" name="mode" value="schedule"><label class="sr-only" for="move-<?= e((string)$draft['id']) ?>">Reschedule post</label><input id="move-<?= e((string)$draft['id']) ?>" name="scheduled_at" type="datetime-local" value="<?= e(date('Y-m-d\TH:i',strtotime((string)$draft['scheduled_at']))) ?>" min="<?= e(date('Y-m-d\TH:i',time()+60)) ?>" class="min-h-11 min-w-0 rounded-md border border-slate-300 px-2 text-xs"><button type="submit" class="min-h-11 rounded-md border border-slate-300 text-xs font-semibold">Update time</button></form><?php endif; ?></article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </section>
    <?php else: ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="published-title">
            <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Content archive</p><h2 id="published-title" class="mt-1 text-xl font-semibold text-slate-950">Published posts</h2><p class="mt-1 text-sm text-slate-600">A permanent record of posts successfully sent through Meta, newest first.</p></div>
            <?php if($publishedDrafts===[]): ?><div class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">No posts have been published yet.</div><?php endif; ?>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><?php foreach($publishedDrafts as $draft): ?><?php $imageUrl=social_studio_image_url($draft); ?><article class="overflow-hidden rounded-xl border border-slate-200 bg-white"><?php if($imageUrl!==''): ?><img class="aspect-[4/5] w-full bg-slate-100 object-contain" src="<?= e($imageUrl) ?>" alt="<?= e((string)$draft['title']) ?>" loading="lazy"><?php endif; ?><div class="p-4"><div class="flex items-start justify-between gap-2"><h3 class="text-sm font-semibold text-slate-950"><?= e((string)$draft['title']) ?></h3><span class="rounded-full border border-blue-200 bg-blue-50 px-2 py-1 text-[10px] font-semibold text-blue-700">Published</span></div><p class="mt-2 text-xs leading-5 text-slate-500"><?= e(str_limit((string)$draft['caption'],100)) ?></p><p class="mt-3 text-xs font-semibold text-slate-700"><?= e(!empty($draft['published_at'])?date('M j, Y · g:i A',strtotime((string)$draft['published_at'])):'Published') ?></p><button type="button" class="mt-3 min-h-11 w-full rounded-lg border border-slate-300 text-xs font-semibold" data-social-open data-draft-id="<?= e((string)$draft['id']) ?>" data-title="<?= e((string)$draft['title']) ?>" data-caption="<?= e((string)$draft['caption']) ?>" data-hashtags="<?= e((string)$draft['hashtags']) ?>" data-image="<?= e($imageUrl) ?>" data-status="Published">Open post</button></div></article><?php endforeach; ?></div>
        </section>
    <?php endif; ?>
</main>

<dialog id="social-post-modal" class="w-[min(94vw,1180px)] rounded-2xl border-0 bg-transparent p-0 shadow-2xl backdrop:bg-slate-950/60">
    <div class="grid max-h-[92vh] overflow-hidden rounded-2xl bg-white lg:grid-cols-[minmax(0,1.2fr)_minmax(340px,.8fr)]"><div class="grid min-h-[420px] place-items-center bg-slate-950"><img id="social-modal-image" class="max-h-[88vh] w-full object-contain" alt=""></div><div class="flex min-h-0 flex-col"><header class="flex items-center gap-3 border-b border-slate-100 p-4"><img class="h-9 w-9 rounded-full" src="<?= e(base_url('assets/img/elite-smiles-instagram-avatar.jpg')) ?>" alt="Elite Smiles"><div><p class="text-sm font-semibold">elitesmilesutah</p><p class="text-xs text-slate-500">Elite Smiles by Walter Meden DDS</p></div><span id="social-modal-status" class="ml-auto rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold"></span><button type="button" data-social-close class="grid h-11 w-11 place-items-center rounded-full text-2xl" aria-label="Close preview">×</button></header><form method="POST" action="<?= e(base_url('app/actions/social_studio_update_draft.php')) ?>" class="flex min-h-0 flex-1 flex-col"><?= csrf_input() ?><input id="social-modal-draft-id" type="hidden" name="draft_id" value=""><div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5"><label class="block text-sm font-semibold text-slate-800">Caption<textarea id="social-modal-caption" name="caption" rows="10" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm leading-6"></textarea></label><label class="block text-sm font-semibold text-slate-800">Hashtags<textarea id="social-modal-hashtags" name="hashtags" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm leading-6"></textarea></label></div><footer class="grid grid-cols-2 gap-2 border-t border-slate-100 p-4"><button type="button" data-social-close class="min-h-11 rounded-xl border border-slate-300 text-sm font-semibold">Close</button><button type="submit" class="min-h-11 rounded-xl bg-slate-950 text-sm font-semibold text-white">Save copy</button></footer></form></div></div>
</dialog>

<script>
(() => {
    const autoGenerateIds = <?= json_encode($autoGenerateIds, JSON_UNESCAPED_SLASHES) ?>;
    const pendingBaseIds = <?= json_encode($pendingBaseIds, JSON_UNESCAPED_SLASHES) ?>;
    const referenceInput = document.getElementById('social-visual-reference');
    const selectedLabel = document.getElementById('social-selected-template');
    const generateButton = document.getElementById('social-generate-button');
    const cards = [...document.querySelectorAll('[data-social-reference]')];
    const search = document.getElementById('social-template-search');
    const group = document.getElementById('social-template-group');
    const empty = document.getElementById('social-template-empty');
    const copyMode = document.querySelector('input[name="copy_mode"]');
    const analysisButton = document.getElementById('social-run-analysis');
    const analysisProgress = document.getElementById('social-analysis-progress');
    document.getElementById('social-copy-mode-advanced')?.addEventListener('change', event => { copyMode.value = event.target.value; });

    cards.filter(card => card.dataset.ready === '1').forEach(card => card.addEventListener('click', () => {
        cards.forEach(item => item.setAttribute('aria-pressed', 'false'));
        card.setAttribute('aria-pressed', 'true');
        referenceInput.value = card.dataset.socialReference || '';
        selectedLabel.textContent = card.querySelector('span')?.textContent?.trim() || 'Selected template';
        generateButton.disabled = false;
    }));

    const filterTemplates = () => {
        const query = (search.value || '').trim().toLowerCase();
        const selectedGroup = group.value || '';
        let visible = 0;
        cards.forEach(card => {
            const matches = (!query || (card.dataset.search || '').includes(query)) && (!selectedGroup || card.dataset.group === selectedGroup);
            card.hidden = !matches;
            if (matches) visible++;
        });
        empty.classList.toggle('hidden', visible > 0);
    };
    search?.addEventListener('input', filterTemplates);
    group?.addEventListener('change', filterTemplates);

    document.getElementById('social-generate-form')?.addEventListener('submit', event => {
        if (!referenceInput.value) { event.preventDefault(); selectedLabel.textContent = 'Choose a Ready template first'; return; }
        generateButton.disabled = true;
        generateButton.textContent = 'Creating drafts…';
    });

    const modal = document.getElementById('social-post-modal');
    document.querySelectorAll('[data-social-open]').forEach(button => button.addEventListener('click', () => {
        document.getElementById('social-modal-caption').textContent = button.dataset.caption || '';
        document.getElementById('social-modal-caption').value = button.dataset.caption || '';
        document.getElementById('social-modal-hashtags').value = button.dataset.hashtags || '';
        document.getElementById('social-modal-draft-id').value = button.dataset.draftId || '';
        document.getElementById('social-modal-status').textContent = button.dataset.status || 'Review';
        const isPublished = (button.dataset.status || '').toLowerCase() === 'published';
        document.getElementById('social-modal-caption').readOnly = isPublished;
        document.getElementById('social-modal-hashtags').readOnly = isPublished;
        const saveButton = modal.querySelector('button[type="submit"]');
        saveButton?.classList.toggle('invisible', isPublished);
        if (saveButton) saveButton.disabled = isPublished;
        const image = document.getElementById('social-modal-image');
        image.src = button.dataset.image || '';
        image.alt = button.dataset.title || 'Social post preview';
        modal.showModal();
    }));
    document.querySelectorAll('[data-social-close]').forEach(button => button.addEventListener('click', () => modal.close()));
    modal?.addEventListener('click', event => { if (event.target === modal) modal.close(); });

    analysisButton?.addEventListener('click', async () => {
        analysisButton.disabled = true;
        const csrfField = document.querySelector('#social-generate-form input[type="hidden"]');
        const csrfName = csrfField?.name || '_csrf';
        const csrfValue = csrfField?.value || '';
        let ready = <?= (int)$baseAnalysisProgress['ready'] ?>;
        const total = <?= (int)$baseAnalysisProgress['total'] ?>;
        let failed = 0;
        for (const baseId of pendingBaseIds) {
            analysisProgress.textContent = `Analyzing template ${ready + 1} of ${total}… Keep this page open.`;
            analysisButton.textContent = `Analyzing ${ready + 1} of ${total}…`;
            const body = new FormData(); body.append(csrfName, csrfValue); body.append('base_id', String(baseId));
            try {
                const response = await fetch('<?= e(base_url('app/actions/social_studio_analyze_template_api.php')) ?>', {method:'POST', body, credentials:'same-origin'});
                const result = await response.json();
                ready = Number(result.ready ?? ready);
                if (!response.ok || !result.ok) failed++;
                if (!response.ok && Array.isArray(result.errors) && result.errors.length) analysisProgress.textContent = result.errors[0];
            } catch (error) { failed++; }
        }
        if (failed === 0 && ready >= total) {
            analysisProgress.textContent = `All ${total} templates are ready. Reloading the library…`;
        } else {
            analysisProgress.textContent = `Analysis completed with ${failed} source failure${failed === 1 ? '' : 's'}. ${ready} of ${total} are ready; retry to reprocess only those items.`;
            analysisButton.textContent = 'Retry pending templates';
            analysisButton.disabled = false;
            return;
        }
        window.setTimeout(() => window.location.reload(), 900);
    });

    if (autoGenerateIds.length) {
        const progress = document.getElementById('social-generation-progress');
        const csrfField = document.querySelector('#social-generate-form input[type="hidden"]');
        const csrfName = csrfField?.name || '_csrf';
        const csrfValue = csrfField?.value || '';
        (async () => {
            let completed = 0;
            let failed = 0;
            for (const draftId of autoGenerateIds) {
                progress.textContent = `Generating image ${completed + 1} of ${autoGenerateIds.length}… Keep this page open.`;
                const body = new FormData();
                body.append(csrfName, csrfValue);
                body.append('draft_id', String(draftId));
                try {
                    const response = await fetch('<?= e(base_url('app/actions/social_studio_generate_image_api.php')) ?>', {method:'POST', body, credentials:'same-origin'});
                    const result = await response.json();
                    if (!response.ok || !result.ok) failed++;
                } catch (error) { failed++; }
                completed++;
            }
            progress.className = failed ? 'mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800' : 'mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800';
            progress.textContent = failed ? `${completed - failed} images completed; ${failed} failed. Reloading the review queue…` : `All ${completed} images are ready. Reloading the review queue…`;
            window.setTimeout(() => window.location.reload(), 900);
        })();
    }
})();
</script>
</body>
</html>
