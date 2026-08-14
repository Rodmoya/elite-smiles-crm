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
if (!in_array($activeView, ['create', 'calendar', 'published', 'brand-book'], true)) $activeView = 'create';
$createMode = strtolower(trim((string)get('mode', 'remix')));
if (!in_array($createMode, ['remix', 'original'], true)) $createMode = 'remix';
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
$selectedGuardrails = $selected ? json_decode((string)($selected['guardrail_json'] ?? ''), true) : null;
$selectedGuardrails = is_array($selectedGuardrails) ? $selectedGuardrails : null;
$selectedBrief = $selected ? json_decode((string)($selected['creative_brief_json'] ?? ''), true) : null;
$selectedBrief = is_array($selectedBrief) ? $selectedBrief : null;
$selectedImageRevisions = $selected ? json_decode((string)($selected['image_revision_history_json'] ?? ''), true) : null;
$selectedImageRevisions = is_array($selectedImageRevisions) ? array_reverse($selectedImageRevisions) : [];
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
$calendarTimeOptions = [];
for ($minutes = 0; $minutes < 24 * 60; $minutes += 30) {
    $calendarTimeOptions[sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60)] = date('g:i A', strtotime(sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60)));
}
$calendarWorkspaceCount = count($approvedUnscheduled) + (int)($counts['scheduled'] ?? 0);
$brandBook = social_studio_active_brand_book();
$brandRules = (array)($brandBook['rules'] ?? social_studio_brand_book_default());
$brandColors = (array)($brandRules['colors'] ?? []);
$brandType = (array)($brandRules['typography'] ?? []);
$brandSizes = (array)($brandType['sizes_percent_canvas_width'] ?? []);
$brandScenarios = (array)($brandRules['scenarios'] ?? []);
$brandHistory = db_all('SELECT version,change_note,activated_at,created_at,status FROM social_studio_brand_books ORDER BY version DESC LIMIT 8');
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
        [data-social-mode-panel][hidden], [data-social-mode-only][hidden] { display:none; }
        .social-mode-button[aria-pressed="true"] { border-color:#0f172a; background:#0f172a; color:#fff; }
        .social-preview-image { aspect-ratio:4/5; width:100%; object-fit:contain; background:#f1f5f9; }
        .social-scrollbar { scrollbar-width:thin; scrollbar-color:#94a3b8 transparent; }
        .social-workspace-tab[aria-current="page"] { background:#0f172a; border-color:#0f172a; color:#fff; }
        .social-calendar-day-today { border-color:#0f172a; box-shadow:0 0 0 1px #0f172a; }
        .social-calendar-card { transition:transform .2s ease, box-shadow .2s ease; }
        .social-calendar-card:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(15,23,42,.08); }
        .social-schedule-card[draggable="true"] { cursor:grab; }
        .social-schedule-card[draggable="true"]:active { cursor:grabbing; }
        .social-schedule-card.is-dragging { opacity:.45; transform:scale(.98); }
        .social-calendar-dropzone.is-dragover { border-color:#059669; background:#ecfdf5; box-shadow:0 0 0 3px rgba(5,150,105,.15); }
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
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Create inside the Elite Smiles editorial line</h1>
            <p class="mt-1 text-sm text-slate-600">Remix an approved post or describe an original idea. Both use the same review, calendar, and Meta publishing pipeline.</p>
        </div>
        <div class="flex flex-wrap gap-2 text-xs font-semibold">
            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800"><?= e((string)$baseAnalysisProgress['ready']) ?> ready</span>
            <span class="rounded-full border border-slate-200 bg-white px-3 py-2 text-slate-700"><?= e((string)$baseAnalysisProgress['remaining']) ?> analyzing</span>
            <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800"><?= e((string)($counts['review'] ?? 0)) ?> in review</span>
        </div>
    </header>

    <nav class="mb-5 grid gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm sm:grid-cols-2 xl:grid-cols-4" aria-label="Social Studio workspace">
        <a class="social-workspace-tab flex min-h-12 items-center justify-between rounded-xl border border-transparent px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2" href="<?= e(base_url('social-studio.php?view=create')) ?>" aria-current="<?= $activeView === 'create' ? 'page' : 'false' ?>"><span>Create &amp; review</span><span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700"><?= e((string)($counts['review'] ?? 0)) ?></span></a>
        <a class="social-workspace-tab flex min-h-12 items-center justify-between rounded-xl border border-transparent px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2" href="<?= e(base_url('social-studio.php?view=calendar&week=' . $weekStart->format('Y-m-d'))) ?>" aria-current="<?= $activeView === 'calendar' ? 'page' : 'false' ?>"><span>Content calendar</span><span class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-700"><?= e((string)$calendarWorkspaceCount) ?></span></a>
        <a class="social-workspace-tab flex min-h-12 items-center justify-between rounded-xl border border-transparent px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2" href="<?= e(base_url('social-studio.php?view=published')) ?>" aria-current="<?= $activeView === 'published' ? 'page' : 'false' ?>"><span>Published</span><span class="rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-700"><?= e((string)($counts['published'] ?? 0)) ?></span></a>
        <a class="social-workspace-tab flex min-h-12 items-center justify-between rounded-xl border border-transparent px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2" href="<?= e(base_url('social-studio.php?view=brand-book')) ?>" aria-current="<?= $activeView === 'brand-book' ? 'page' : 'false' ?>"><span>Brand Book</span><span class="rounded-full bg-violet-50 px-2 py-1 text-xs text-violet-700">v<?= e((string)($brandBook['version'] ?? 1)) ?></span></a>
    </nav>

    <?php if ($successMessage !== ''): ?><div role="status" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e((string)$successMessage) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div role="alert" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?= e((string)$errorMessage) ?></div><?php endif; ?>
    <?php if ($autoGenerateIds !== []): ?><div id="social-generation-progress" role="status" aria-live="polite" class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-800">Preparing image generation…</div><?php endif; ?>

    <?php if ($activeView === 'create'): ?>
    <section class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="creation-path-title">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Stage 1 · Brief</p>
        <h2 id="creation-path-title" class="mt-1 text-lg font-semibold text-slate-950">How do you want to create?</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-2" role="group" aria-label="Creation mode">
            <button type="button" class="social-mode-button min-h-20 rounded-xl border border-slate-300 px-4 py-3 text-left transition hover:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2" data-social-mode="remix" aria-pressed="<?= $createMode === 'remix' ? 'true' : 'false' ?>"><span class="block text-sm font-semibold">Remix approved post</span><span class="mt-1 block text-xs leading-5 opacity-75">Keep its approved copy and design; change only what you select.</span></button>
            <button type="button" class="social-mode-button min-h-20 rounded-xl border border-slate-300 px-4 py-3 text-left transition hover:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2" data-social-mode="original" aria-pressed="<?= $createMode === 'original' ? 'true' : 'false' ?>"><span class="block text-sm font-semibold">Create original</span><span class="mt-1 block text-xs leading-5 opacity-75">Describe the idea; the CMO selects a brand system and creates new copy and photography.</span></button>
        </div>
    </section>

    <section data-social-mode-only="remix" <?= $createMode === 'remix' ? '' : 'hidden' ?> class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="template-library-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Brand Library</p>
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
        <div id="social-template-carousel" class="social-scrollbar mt-4 flex gap-3 overflow-x-auto overflow-y-hidden pb-3">
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
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Stage 2 · Create</p>
            <h2 id="controls-title" class="mt-1 text-lg font-semibold text-slate-950"><?= $createMode === 'original' ? 'Describe the original post' : 'Create the new version' ?></h2>
            <p id="social-selected-template" data-social-mode-only="remix" <?= $createMode === 'remix' ? '' : 'hidden' ?> class="mt-2 rounded-xl bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700"><?= $defaultReferenceKey !== '' ? e((string)$visualReferences[$defaultReferenceKey]['label']) : 'No ready template selected' ?></p>

            <form id="social-generate-form" data-social-mode-panel="remix" <?= $createMode === 'remix' ? '' : 'hidden' ?> class="mt-4 space-y-4" method="POST" enctype="multipart/form-data" action="<?= e(base_url('app/actions/social_studio_generate.php')) ?>">
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

                <fieldset class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <legend class="px-1 text-sm font-semibold text-slate-800">Replace text <span class="font-normal text-slate-500">(optional)</span></legend>
                    <p class="mb-3 text-xs leading-5 text-slate-600">Change only an exact word or phrase while preserving the approved font, size, style, and position. Example: Spring → Summer.</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="block text-xs font-semibold text-slate-700">Current approved text<input id="social-replace-from" name="replace_text_from" type="text" maxlength="120" autocomplete="off" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm" placeholder="Spring"></label>
                        <label class="block text-xs font-semibold text-slate-700">Replace with<input id="social-replace-to" name="replace_text_to" type="text" maxlength="120" autocomplete="off" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm" placeholder="Summer"></label>
                    </div>
                </fieldset>

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

            <form id="social-original-form" data-social-mode-panel="original" <?= $createMode === 'original' ? '' : 'hidden' ?> class="mt-4 space-y-4" method="POST" enctype="multipart/form-data" action="<?= e(base_url('app/actions/social_studio_generate_original.php')) ?>">
                <?= csrf_input() ?>
                <label class="block text-sm font-semibold text-slate-800">What should we create?
                    <textarea name="creative_request" rows="6" maxlength="1800" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-3 text-sm leading-6" placeholder="Example: Create an educational veneers post using a clean 3D model style we have used before. Explain how veneers are planned to look natural, with a confident woman in her 30s and text on the left."></textarea>
                    <span class="mt-1 block text-xs leading-5 text-slate-500">Write naturally. The Elite Smiles CMO turns this into a structured production brief.</span>
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-xs font-semibold text-slate-700">Focus<select name="focus" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option value="auto">CMO chooses</option><option value="veneers">Veneers</option><option value="smile_makeover">Smile makeover</option><option value="implants">Implants / All-on-X</option><option value="lip_repositioning">Lip repositioning</option><option value="dental_education">Dental education / 3D</option></select></label>
                    <label class="block text-xs font-semibold text-slate-700">Purpose<select name="purpose" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option value="auto">CMO chooses</option><option value="educational">Educational</option><option value="social_ad">Social media ad</option></select></label>
                    <label class="block text-xs font-semibold text-slate-700">Audience<select name="audience" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option value="auto">CMO chooses</option><option value="any">Any adult</option><option value="woman">Woman</option><option value="man">Man</option></select></label>
                    <label class="block text-xs font-semibold text-slate-700">Age range<select name="age_range" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option value="auto">CMO chooses</option><option value="25-34">25–34</option><option value="35-44">35–44</option><option value="45-54">45–54</option><option value="55+">55+</option></select></label>
                    <label class="block text-xs font-semibold text-slate-700">Text position<select name="text_position" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><option value="auto">CMO chooses</option><option value="source">Recommended position</option><option value="left">Left</option><option value="right">Right</option></select></label>
                    <label class="block text-xs font-semibold text-slate-700">Posts<select name="count" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"><?php for ($i=1;$i<=7;$i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></label>
                </div>
                <details class="rounded-xl border border-slate-200 bg-slate-50 p-3"><summary class="cursor-pointer text-sm font-semibold text-slate-700">Optional visual inspiration</summary><label class="mt-3 block text-xs font-semibold text-slate-700">Upload image<input type="file" name="inspiration_image" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full rounded-xl border border-dashed border-slate-300 bg-white px-3 py-3 text-xs"></label></details>
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs leading-5 text-blue-900"><strong class="block">The CMO will:</strong> interpret the brief, choose the strongest approved Brand Library system, write new overlay copy and caption, generate a clean image, assemble the typography, and run guardrail checks.</div>
                <button id="social-original-button" type="submit" class="min-h-12 w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">Create original post</button>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="preview-title">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Stage 3 · Review</p><h2 id="preview-title" class="mt-1 text-lg font-semibold text-slate-950">Full post preview</h2></div>
                <?php if ($selected): ?><span class="<?= e(social_studio_badge_class((string)$selected['status'])) ?> rounded-full border px-3 py-1 text-xs font-semibold"><?= e(social_studio_status_labels()[(string)$selected['status']] ?? (string)$selected['status']) ?></span><?php endif; ?>
            </div>
            <?php if ($selected): ?>
                <?php if ($selectedBrief): ?><div class="mx-auto mb-4 max-w-[620px] rounded-xl border border-violet-200 bg-violet-50 p-4"><div class="flex items-center justify-between gap-3"><p class="text-sm font-semibold text-violet-950">CMO interpretation</p><div class="flex gap-2"><span class="rounded-full bg-white px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-violet-700">Original</span><span class="rounded-full bg-white px-2 py-1 text-[10px] font-semibold text-violet-700">Brand Book v<?= e((string)($selected['brand_book_version'] ?? 1)) ?></span></div></div><dl class="mt-3 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4"><div><dt class="text-violet-600">Focus</dt><dd class="mt-1 font-semibold text-violet-950"><?= e(social_studio_focus_label((string)($selectedBrief['focus'] ?? ''))) ?></dd></div><div><dt class="text-violet-600">Purpose</dt><dd class="mt-1 font-semibold capitalize text-violet-950"><?= e(str_replace('_', ' ', (string)($selectedBrief['purpose'] ?? ''))) ?></dd></div><div><dt class="text-violet-600">Audience</dt><dd class="mt-1 font-semibold capitalize text-violet-950"><?= e((string)($selectedBrief['audience'] ?? 'Any')) ?></dd></div><div><dt class="text-violet-600">Text</dt><dd class="mt-1 font-semibold capitalize text-violet-950"><?= e((string)($selectedBrief['text_position'] ?? 'Recommended')) ?></dd></div></dl><?php if (trim((string)($selected['reference_reason'] ?? '')) !== ''): ?><p class="mt-3 border-t border-violet-200 pt-3 text-xs leading-5 text-violet-900"><strong>Brand Library choice:</strong> <?= e((string)$selected['reference_reason']) ?></p><?php endif; ?></div><?php endif; ?>
                <article class="mx-auto max-w-[620px] overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <header class="flex items-center gap-3 border-b border-slate-100 px-4 py-3"><img class="h-9 w-9 rounded-full object-cover" src="<?= e(base_url('assets/img/elite-smiles-instagram-avatar.jpg')) ?>" alt="Elite Smiles"><div><p class="text-sm font-semibold">elitesmilesutah</p><p class="text-xs text-slate-500">Elite Smiles by Walter Meden DDS</p></div><span class="ml-auto font-bold tracking-[0.2em]">···</span></header>
                    <?php if ($selectedImageUrl !== ''): ?><img class="social-preview-image" src="<?= e($selectedImageUrl) ?>" alt="<?= e((string)$selected['title']) ?>"><?php else: ?><div class="grid aspect-[4/5] place-items-center bg-slate-100 p-8 text-center text-sm text-slate-500">Generate the clean photo to assemble this post.</div><?php endif; ?>
                    <div class="border-t border-slate-100 px-4 py-3"><div class="mb-3 flex text-2xl"><span>♡　◯　➤</span><span class="ml-auto">⌑</span></div><p class="whitespace-pre-line text-sm leading-6 text-slate-700"><strong class="text-slate-950">elitesmilesutah</strong> <?= e((string)$selected['caption']) ?></p><p class="mt-3 text-xs leading-5 text-blue-700"><?= e((string)$selected['hashtags']) ?></p></div>
                </article>
                <?php if ($selectedGuardrails): ?><div class="mx-auto mt-4 max-w-[620px] rounded-xl border <?= ($selectedGuardrails['status'] ?? '') === 'pass' ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' ?> p-4"><div class="flex items-center justify-between gap-3"><p class="text-sm font-semibold text-slate-900">Quality guardrails</p><span class="rounded-full bg-white px-2 py-1 text-xs font-semibold"><?= e((string)($selectedGuardrails['passed'] ?? 0)) ?>/<?= e((string)($selectedGuardrails['total'] ?? 0)) ?> passed</span></div><ul class="mt-3 grid gap-2 sm:grid-cols-2"><?php foreach ((array)($selectedGuardrails['checks'] ?? []) as $check): ?><li class="flex gap-2 text-xs leading-5 <?= !empty($check['pass']) ? 'text-emerald-800' : 'text-amber-900' ?>"><span aria-hidden="true"><?= !empty($check['pass']) ? '✓' : '!' ?></span><span><?= e((string)($check['label'] ?? 'Quality check')) ?></span></li><?php endforeach; ?></ul><?php if (trim((string)($selectedGuardrails['visual_notes'] ?? '')) !== ''): ?><p class="mt-3 text-xs leading-5 text-slate-700"><?= e((string)$selectedGuardrails['visual_notes']) ?></p><?php endif; ?></div><?php endif; ?>
                <?php if ($selectedImageUrl !== '' && in_array((string)($selected['status'] ?? ''), ['draft', 'review'], true)): ?>
                    <section class="mx-auto mt-4 max-w-[620px] rounded-2xl border border-slate-200 bg-slate-50 p-4" aria-labelledby="refine-image-title">
                        <div class="flex items-start gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-950 text-white" aria-hidden="true"><svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1m0-12.8-2.1 2.1m-8.6 8.6-2.1 2.1"/><circle cx="12" cy="12" r="4"/></svg></span><div><h3 id="refine-image-title" class="text-sm font-semibold text-slate-950">Refine this image</h3><p class="mt-1 text-xs leading-5 text-slate-600">Describe the visual change naturally. The approved overlay, fonts, CTA, caption, hashtags, and Brand Book rules stay locked.</p></div></div>
                        <div class="mt-3 flex flex-wrap gap-2" aria-label="Suggested refinements"><button type="button" class="min-h-9 rounded-full border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 hover:border-slate-400" data-refine-suggestion="Make the person look more natural and less AI-generated.">More realistic</button><button type="button" class="min-h-9 rounded-full border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 hover:border-slate-400" data-refine-suggestion="Move the camera closer to the smile while keeping both eyes visible and in sharp focus.">Closer to smile</button><button type="button" class="min-h-9 rounded-full border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 hover:border-slate-400" data-refine-suggestion="Use warmer natural daylight with a softer, premium background.">Warmer light</button></div>
                        <form method="POST" action="<?= e(base_url('app/actions/social_studio_refine_image.php')) ?>" class="mt-3" data-social-refine-form><?= csrf_input() ?><input type="hidden" name="draft_id" value="<?= e((string)$selected['id']) ?>"><label class="sr-only" for="social-refine-instruction">Image refinement instruction</label><div class="flex items-end gap-2 rounded-2xl border border-slate-300 bg-white p-2 shadow-sm focus-within:border-slate-500 focus-within:ring-2 focus-within:ring-slate-200"><textarea id="social-refine-instruction" name="instruction" rows="2" minlength="3" maxlength="500" required class="min-h-12 min-w-0 flex-1 resize-y border-0 bg-transparent px-2 py-2 text-sm leading-5 text-slate-900 outline-none placeholder:text-slate-400" placeholder="Example: Make her look more real and move the camera closer to her smile."></textarea><button type="submit" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-slate-950 text-white transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60" aria-label="Refine image"><svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 7-7 7 7M12 5v14"/></svg></button></div><p class="mt-2 text-[11px] leading-4 text-slate-500">The clean photo is edited first, then the exact approved overlay is reapplied automatically.</p></form>
                        <?php if ((string)($selected['creation_mode'] ?? '') === 'original'): ?><form method="POST" action="<?= e(base_url('app/actions/social_studio_polish_design.php')) ?>" class="mt-3"><?= csrf_input() ?><input type="hidden" name="draft_id" value="<?= e((string)$selected['id']) ?>"><button type="submit" class="min-h-11 w-full rounded-xl border border-amber-300 bg-amber-50 px-4 text-sm font-semibold text-amber-950 transition hover:bg-amber-100">Polish typography &amp; layout</button><p class="mt-2 text-[11px] leading-4 text-slate-500">Applies the canonical Elite Smiles editorial grid without changing the photograph, caption, or hashtags.</p></form><?php endif; ?>
                        <?php if ($selectedImageRevisions !== []): ?><details class="mt-3 border-t border-slate-200 pt-3"><summary class="cursor-pointer text-xs font-semibold text-slate-700">Revision history (<?= e((string)count($selectedImageRevisions)) ?>)</summary><ol class="mt-3 space-y-2"><?php foreach ($selectedImageRevisions as $revision): ?><li class="rounded-xl bg-white px-3 py-2 text-xs leading-5 text-slate-600"><span class="font-semibold text-slate-900">Version <?= e((string)($revision['revision'] ?? '')) ?>:</span> <?= e((string)($revision['instruction'] ?? '')) ?><span class="mt-1 block text-[10px] text-slate-400"><?= e(!empty($revision['created_at']) ? date('M j, Y g:i A', strtotime((string)$revision['created_at'])) : '') ?></span></li><?php endforeach; ?></ol></details><?php endif; ?>
                    </section>
                <?php endif; ?>
            <?php else: ?>
                <div class="grid min-h-[560px] place-items-center rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center"><div><p class="text-base font-semibold text-slate-800">No draft selected</p><p class="mt-2 text-sm text-slate-500">Create a remix or original post to begin the review.</p></div></div>
            <?php endif; ?>
        </section>

        <aside class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="queue-title">
            <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Stage 3 · Review</p><h2 id="queue-title" class="mt-1 text-lg font-semibold text-slate-950">Review queue</h2></div><form method="POST" action="<?= e(base_url('app/actions/social_studio_clear.php')) ?>" onsubmit="return confirm('Clear every social draft? This cannot be undone.');"><?= csrf_input() ?><button class="min-h-11 rounded-xl border border-rose-200 px-3 text-xs font-semibold text-rose-700" type="submit">Clear</button></form></div>
            <div class="social-scrollbar mt-4 max-h-[740px] space-y-3 overflow-y-auto pr-1">
                <?php if ($drafts === []): ?><div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-center text-sm text-slate-500">No drafts waiting.</div><?php endif; ?>
                <?php foreach ($drafts as $draft): ?>
                    <?php $status=(string)($draft['status'] ?? 'review'); $imageUrl=social_studio_image_url($draft); $canPublish=in_array($status, ['approved','scheduled','publish_failed'], true); ?>
                    <article class="rounded-xl border border-slate-200 p-3">
                        <div class="flex gap-3"><?php if ($imageUrl !== ''): ?><img class="h-20 w-16 shrink-0 rounded-lg bg-slate-100 object-cover" src="<?= e($imageUrl) ?>" alt=""><?php else: ?><div class="grid h-20 w-16 shrink-0 place-items-center rounded-lg bg-slate-100 text-[10px] text-slate-500">No image</div><?php endif; ?><div class="min-w-0"><div class="flex flex-wrap items-center gap-1"><h3 class="text-sm font-semibold leading-5 text-slate-950"><?= e((string)$draft['title']) ?></h3><span class="<?= e(social_studio_badge_class($status)) ?> rounded-full border px-2 py-0.5 text-[10px] font-semibold"><?= e(social_studio_status_labels()[$status] ?? $status) ?></span><?php if ((string)($draft['creation_mode'] ?? 'remix') === 'original'): ?><span class="rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-semibold text-violet-700">Original</span><?php endif; ?></div><p class="mt-1 text-xs leading-5 text-slate-500"><?= e(str_limit((string)$draft['caption'], 90)) ?></p><?php if ((string)($draft['generation_status'] ?? '') === 'failed'): ?><p class="mt-1 text-xs font-semibold text-rose-700"><?= e((string)($draft['generation_error'] ?? 'Image generation failed')) ?></p><?php endif; ?><?php if (trim((string)($draft['publish_error'] ?? '')) !== ''): ?><p class="mt-1 text-xs font-semibold text-rose-700"><?= e(str_limit((string)$draft['publish_error'], 140)) ?></p><?php endif; ?><?php if ($status === 'scheduled' && !empty($draft['scheduled_at'])): ?><p class="mt-1 text-xs font-semibold text-emerald-700"><?= e(date('M j, g:i A T', strtotime((string)$draft['scheduled_at']))) ?></p><?php endif; ?></div></div>
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
                    <p class="mt-1 text-sm text-slate-600">Approved posts wait above the timeline until you assign a date. Each day can hold multiple posts, and scheduled posts publish automatically through Meta.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a class="grid min-h-11 place-items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700" href="<?= e(base_url('social-studio.php?view=calendar&week=' . $weekStart->modify('-7 days')->format('Y-m-d'))) ?>">Previous</a>
                    <a class="grid min-h-11 place-items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700" href="<?= e(base_url('social-studio.php?view=calendar')) ?>">Today</a>
                    <a class="grid min-h-11 place-items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700" href="<?= e(base_url('social-studio.php?view=calendar&week=' . $weekStart->modify('+7 days')->format('Y-m-d'))) ?>">Next</a>
                </div>
            </div>

            <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="text-sm font-semibold text-slate-950">Ready to schedule</h3><p class="mt-1 text-xs text-slate-600"><?= e((string)count($approvedUnscheduled)) ?> approved post<?= count($approvedUnscheduled) === 1 ? '' : 's' ?> waiting. Choose a time, then drag a card onto a day.</p></div><a class="text-sm font-semibold text-slate-700 underline underline-offset-4" href="<?= e(base_url('social-studio.php?view=create')) ?>">Create more posts</a></div>
                    <?php if ($approvedUnscheduled === []): ?><div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white p-5 text-center text-sm text-slate-500">No approved posts are waiting. Approve a finished post and it will appear here.</div><?php endif; ?>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($approvedUnscheduled as $draft): ?><?php $imageUrl=social_studio_image_url($draft); ?>
                            <article class="social-schedule-card rounded-xl border border-slate-200 bg-white p-3 <?= (int)get('draft',0)===(int)$draft['id'] ? 'ring-2 ring-slate-900' : '' ?>" draggable="true" tabindex="0" data-schedule-card data-draft-id="<?= e((string)$draft['id']) ?>" aria-label="<?= e('Schedule ' . (string)$draft['title']) ?>">
                                <div class="flex gap-3"><?php if ($imageUrl !== ''): ?><img class="h-20 w-16 rounded-lg bg-slate-100 object-cover" src="<?= e($imageUrl) ?>" width="64" height="80" alt="<?= e((string)$draft['title']) ?>"><?php endif; ?><div class="min-w-0"><h4 class="text-sm font-semibold text-slate-950"><?= e((string)$draft['title']) ?></h4><p class="mt-1 text-xs leading-5 text-slate-500"><?= e(str_limit((string)$draft['caption'], 65)) ?></p></div></div>
                                <label class="mt-3 block text-xs font-semibold text-slate-700" for="ready-time-<?= e((string)$draft['id']) ?>">Publishing time</label>
                                <select id="ready-time-<?= e((string)$draft['id']) ?>" data-schedule-time class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"><?php foreach($calendarTimeOptions as $value=>$label): ?><option value="<?= e($value) ?>" <?= $value==='10:30'?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                                <p class="mt-2 hidden text-xs font-semibold text-emerald-700 lg:block">Drag to Monday–Sunday below</p>
                                <details class="mt-2"><summary class="min-h-11 cursor-pointer py-3 text-xs font-semibold text-slate-600">Choose day without dragging</summary><form method="POST" action="<?= e(base_url('app/actions/social_studio_publish.php')) ?>" class="grid gap-2" data-schedule-fallback><?= csrf_input() ?><input type="hidden" name="draft_id" value="<?= e((string)$draft['id']) ?>"><input type="hidden" name="mode" value="schedule"><input type="hidden" name="week" value="<?= e($weekStart->format('Y-m-d')) ?>"><label class="text-xs font-semibold text-slate-700">Day<select name="schedule_day" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"><?php foreach($weekDays as $optionDay): ?><?php if($optionDay->setTime(23,59)>$calendarNow): ?><option value="<?= e($optionDay->format('Y-m-d')) ?>"><?= e($optionDay->format('l, M j')) ?></option><?php endif; ?><?php endforeach; ?></select></label><input type="hidden" name="schedule_time" value="10:30" data-fallback-time><button class="min-h-11 rounded-lg bg-slate-950 px-3 text-sm font-semibold text-white" type="submit">Schedule post</button></form></details>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
                <form method="POST" action="<?= e(base_url('app/actions/social_studio_schedule_week.php')) ?>" class="rounded-xl border border-slate-200 p-4">
                    <?= csrf_input() ?><input type="hidden" name="week_start" value="<?= e($weekStart->format('Y-m-d')) ?>">
                    <h3 class="text-sm font-semibold text-slate-950">Fill this week</h3><p class="mt-1 text-xs leading-5 text-slate-600">Automatically spread approved posts across future days. You can then stack multiple posts on any day.</p>
                    <div class="mt-4 grid grid-cols-2 gap-3"><label class="text-xs font-semibold text-slate-700">Posts<select name="count" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"><?php for($i=1;$i<=7;$i++): ?><option value="<?= $i ?>" <?= $i===min(7,max(1,count($approvedUnscheduled)))?'selected':'' ?>><?= $i ?></option><?php endfor; ?></select></label><label class="text-xs font-semibold text-slate-700">Daily time<input name="publish_time" type="time" value="10:30" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"></label></div>
                    <button type="submit" class="mt-3 min-h-11 w-full rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-sm font-semibold text-emerald-800" <?= $approvedUnscheduled === [] ? 'disabled' : '' ?>>Fill week with approved posts</button>
                </form>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-7" aria-label="Weekly publishing timeline">
                <?php $today=(new DateTimeImmutable('now',new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d'); foreach ($weekDays as $day): ?><?php $dayKey=$day->format('Y-m-d'); $dayItems=$calendarByDay[$dayKey]??[]; ?>
                    <section class="social-calendar-dropzone min-h-[360px] rounded-xl border bg-slate-50 p-3 transition <?= $dayKey===$today?'social-calendar-day-today':'border-slate-200' ?>" aria-labelledby="day-<?= e($dayKey) ?>" data-calendar-day="<?= e($dayKey) ?>">
                        <div class="flex items-center justify-between"><div><p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500"><?= e($day->format('D')) ?></p><h3 id="day-<?= e($dayKey) ?>" class="text-lg font-semibold text-slate-950"><?= e($day->format('j')) ?></h3></div><?php if($dayKey===$today): ?><span class="rounded-full bg-slate-950 px-2 py-1 text-[10px] font-semibold text-white">Today</span><?php endif; ?></div>
                        <div class="mt-3 space-y-3"><?php if($dayItems===[]): ?><div class="rounded-lg border border-dashed border-slate-300 bg-white p-4 text-center text-xs text-slate-500">Drop post here</div><?php endif; ?>
                            <?php foreach($dayItems as $draft): ?><?php $imageUrl=social_studio_image_url($draft); $status=(string)$draft['status']; ?>
                                <article class="social-calendar-card social-schedule-card rounded-lg border border-slate-200 bg-white p-2" <?= in_array($status,['scheduled','publish_failed'],true)?'draggable="true" tabindex="0" data-schedule-card data-draft-id="'.e((string)$draft['id']).'"':'' ?>><div class="flex gap-2"><?php if($imageUrl!==''): ?><img class="h-14 w-11 rounded-md object-cover" src="<?= e($imageUrl) ?>" width="44" height="56" alt=""><?php endif; ?><div class="min-w-0"><p class="text-xs font-semibold text-slate-950"><?= e(date('g:i A',strtotime((string)$draft['scheduled_at']))) ?></p><p class="mt-1 text-xs leading-4 text-slate-600"><?= e(str_limit((string)$draft['title'],35)) ?></p></div></div><div class="mt-2 flex items-center justify-between gap-2"><span class="<?= e(social_studio_badge_class($status)) ?> rounded-full border px-2 py-1 text-[10px] font-semibold"><?= e(social_studio_status_labels()[$status]??$status) ?></span><button type="button" class="min-h-11 px-2 text-xs font-semibold text-slate-700" data-social-open data-draft-id="<?= e((string)$draft['id']) ?>" data-title="<?= e((string)$draft['title']) ?>" data-caption="<?= e((string)$draft['caption']) ?>" data-hashtags="<?= e((string)$draft['hashtags']) ?>" data-image="<?= e($imageUrl) ?>" data-status="<?= e(social_studio_status_labels()[$status]??$status) ?>">Open</button></div><?php if(in_array($status,['scheduled','publish_failed'],true)): ?><form method="POST" action="<?= e(base_url('app/actions/social_studio_publish.php')) ?>" class="mt-2 grid gap-2"><?= csrf_input() ?><input type="hidden" name="draft_id" value="<?= e((string)$draft['id']) ?>"><input type="hidden" name="mode" value="schedule"><input type="hidden" name="week" value="<?= e($weekStart->format('Y-m-d')) ?>"><input type="hidden" name="schedule_day" value="<?= e($dayKey) ?>"><label class="text-xs font-semibold text-slate-700" for="move-<?= e((string)$draft['id']) ?>">Publishing time</label><select id="move-<?= e((string)$draft['id']) ?>" name="schedule_time" data-schedule-time class="min-h-11 min-w-0 rounded-md border border-slate-300 bg-white px-2 text-xs"><?php $currentTime=date('H:i',strtotime((string)$draft['scheduled_at'])); foreach($calendarTimeOptions as $value=>$label): ?><option value="<?= e($value) ?>" <?= $value===$currentTime?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><button type="submit" class="min-h-11 rounded-md border border-slate-300 text-xs font-semibold">Update time</button></form><?php endif; ?></article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </section>
    <?php elseif ($activeView === 'brand-book'): ?>
        <section class="space-y-5" aria-labelledby="brand-book-title">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-3xl"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-700">Virtual brand memory</p><h2 id="brand-book-title" class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Elite Smiles Editorial System</h2><p class="mt-2 text-sm leading-6 text-slate-600">The binding source of truth for every CMO brief, caption, image prompt, overlay, remix, original post, and quality review. Historical drafts keep the version that created them.</p></div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3"><p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Active system</p><p class="mt-1 text-lg font-semibold text-emerald-950">Version <?= e((string)($brandBook['version'] ?? 1)) ?></p><p class="mt-1 text-xs text-emerald-800"><?= e((string)($brandBook['change_note'] ?? 'Bundled default')) ?></p></div>
                </div>
            </div>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(360px,.85fr)]">
                <div class="space-y-5">
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="brand-foundation-title">
                        <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Foundation</p><h3 id="brand-foundation-title" class="mt-1 text-lg font-semibold text-slate-950">Color and typography tokens</h3></div>
                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"><?php foreach($brandColors as $name=>$color): ?><div class="overflow-hidden rounded-xl border border-slate-200"><div class="h-16" style="background:<?= e((string)$color) ?>"></div><div class="p-2"><p class="text-[11px] font-semibold capitalize text-slate-800"><?= e(str_replace('_',' ',(string)$name)) ?></p><p class="mt-0.5 font-mono text-[10px] text-slate-500"><?= e((string)$color) ?></p></div></div><?php endforeach; ?></div>
                        <div class="mt-5 grid gap-3 md:grid-cols-3"><div class="rounded-xl border border-slate-200 p-4"><p class="text-xs font-semibold text-slate-500">Display</p><p class="mt-3 text-3xl text-slate-950" style="font-family:<?= e((string)($brandType['display_font'] ?? 'serif')) ?>">Natural confidence</p><p class="mt-2 text-xs text-slate-500"><?= e((string)($brandType['display_font'] ?? '')) ?></p></div><div class="rounded-xl border border-slate-200 p-4"><p class="text-xs font-semibold text-slate-500">Support</p><p class="mt-3 text-base leading-6 text-slate-800" style="font-family:<?= e((string)($brandType['support_font'] ?? 'sans-serif')) ?>">Clear education. One focused idea.</p><p class="mt-2 text-xs text-slate-500"><?= e((string)($brandType['support_font'] ?? '')) ?></p></div><div class="rounded-xl border border-slate-200 p-4"><p class="text-xs font-semibold text-slate-500">Accent</p><p class="mt-3 text-2xl text-amber-800" style="font-family:<?= e((string)($brandType['accent_font'] ?? 'cursive')) ?>">love your smile</p><p class="mt-2 text-xs text-slate-500"><?= e((string)($brandType['accent_font'] ?? '')) ?></p></div></div>
                        <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-6"><?php foreach($brandSizes as $role=>$size): ?><div class="rounded-lg bg-slate-50 p-3 text-center"><p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500"><?= e((string)$role) ?></p><p class="mt-1 text-sm font-semibold text-slate-950"><?= e((string)$size) ?>%</p></div><?php endforeach; ?></div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="scenario-title"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Production playbooks</p><h3 id="scenario-title" class="mt-1 text-lg font-semibold text-slate-950">Rules for every post scenario</h3><div class="mt-4 grid gap-3 md:grid-cols-2"><?php foreach($brandScenarios as $scenario=>$rule): ?><article class="rounded-xl border border-slate-200 bg-slate-50 p-4"><h4 class="text-sm font-semibold capitalize text-slate-950"><?= e(str_replace('_',' ',(string)$scenario)) ?></h4><p class="mt-2 text-xs leading-5 text-slate-600"><?= e((string)$rule) ?></p></article><?php endforeach; ?></div></section>

                    <section class="grid gap-4 md:grid-cols-2"><div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-sm font-semibold text-slate-950">Composition memory</h3><dl class="mt-3 space-y-3"><?php foreach((array)($brandRules['composition'] ?? []) as $label=>$rule): ?><div><dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500"><?= e(str_replace('_',' ',(string)$label)) ?></dt><dd class="mt-1 text-xs leading-5 text-slate-700"><?= e(is_array($rule)?implode(', ',$rule):(string)$rule) ?></dd></div><?php endforeach; ?></dl></div><div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-sm font-semibold text-slate-950">Approval policy</h3><p class="mt-3 text-xs leading-5 text-slate-700"><?= e((string)($brandRules['governance']['approval'] ?? '')) ?></p><h4 class="mt-5 text-xs font-semibold uppercase tracking-wide text-rose-700">Never</h4><ul class="mt-2 space-y-2 text-xs leading-5 text-slate-700"><?php foreach(array_merge((array)($brandRules['copy']['never'] ?? []),(array)($brandRules['photography']['never'] ?? [])) as $never): ?><li class="flex gap-2"><span aria-hidden="true">—</span><span><?= e((string)$never) ?></span></li><?php endforeach; ?></ul></div></section>
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-sm font-semibold text-slate-950">Version history</h3><div class="mt-3 divide-y divide-slate-100"><?php foreach($brandHistory as $versionRow): ?><div class="flex gap-3 py-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full <?= (string)$versionRow['status']==='active'?'bg-emerald-100 text-emerald-800':'bg-slate-100 text-slate-600' ?> text-xs font-semibold">v<?= e((string)$versionRow['version']) ?></span><div><p class="text-xs leading-5 text-slate-700"><?= e((string)($versionRow['change_note'] ?: 'No change note')) ?></p><p class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400"><?= e(date('M j, Y · g:i A',strtotime((string)($versionRow['activated_at'] ?: $versionRow['created_at'])))) ?><?= (string)$versionRow['status']==='active'?' · Active':'' ?></p></div></div><?php endforeach; ?></div></section>
                </div>

                <aside><form method="POST" action="<?= e(base_url('app/actions/social_studio_brand_book.php')) ?>" class="sticky top-5 space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><?= csrf_input() ?><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Controlled update</p><h3 class="mt-1 text-lg font-semibold text-slate-950">Create the next version</h3><p class="mt-1 text-xs leading-5 text-slate-600">Changes activate for new drafts only. Existing posts keep their original Brand Book version.</p></div>
                    <details open class="rounded-xl border border-slate-200 p-4"><summary class="min-h-11 cursor-pointer py-3 text-sm font-semibold text-slate-900">Colors</summary><div class="grid grid-cols-2 gap-3"><?php foreach($brandColors as $name=>$color): ?><label class="text-xs font-semibold capitalize text-slate-700"><?= e(str_replace('_',' ',(string)$name)) ?><span class="mt-1 flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 px-2"><input type="color" name="color_<?= e((string)$name) ?>" value="<?= e((string)$color) ?>" class="h-8 w-8 border-0 bg-transparent"><span class="font-mono text-[11px]"><?= e((string)$color) ?></span></span></label><?php endforeach; ?></div></details>
                    <details class="rounded-xl border border-slate-200 p-4"><summary class="min-h-11 cursor-pointer py-3 text-sm font-semibold text-slate-900">Fonts and sizes</summary><div class="space-y-3"><label class="block text-xs font-semibold text-slate-700">Display font<input name="display_font" value="<?= e((string)($brandType['display_font'] ?? '')) ?>" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"></label><label class="block text-xs font-semibold text-slate-700">Support font<input name="support_font" value="<?= e((string)($brandType['support_font'] ?? '')) ?>" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"></label><label class="block text-xs font-semibold text-slate-700">Accent font<input name="accent_font" value="<?= e((string)($brandType['accent_font'] ?? '')) ?>" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"></label><div class="grid grid-cols-2 gap-2"><?php foreach($brandSizes as $role=>$size): ?><label class="text-xs font-semibold capitalize text-slate-700"><?= e((string)$role) ?><input type="number" step="0.1" min="0.8" max="12" name="size_<?= e((string)$role) ?>" value="<?= e((string)$size) ?>" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3"></label><?php endforeach; ?></div></div></details>
                    <details class="rounded-xl border border-slate-200 p-4"><summary class="min-h-11 cursor-pointer py-3 text-sm font-semibold text-slate-900">Scenario instructions</summary><div class="space-y-3"><?php foreach($brandScenarios as $scenario=>$rule): ?><label class="block text-xs font-semibold capitalize text-slate-700"><?= e(str_replace('_',' ',(string)$scenario)) ?><textarea name="scenario_<?= e((string)$scenario) ?>" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs leading-5"><?= e((string)$rule) ?></textarea></label><?php endforeach; ?></div></details>
                    <details class="rounded-xl border border-slate-200 p-4"><summary class="min-h-11 cursor-pointer py-3 text-sm font-semibold text-slate-900">Voice and exclusions</summary><div class="space-y-3"><label class="block text-xs font-semibold text-slate-700">Voice — one rule per line<textarea name="voice" rows="5" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs leading-5"><?= e(implode("\n",(array)($brandRules['identity']['voice'] ?? []))) ?></textarea></label><label class="block text-xs font-semibold text-slate-700">Copy exclusions<textarea name="copy_never" rows="6" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs leading-5"><?= e(implode("\n",(array)($brandRules['copy']['never'] ?? []))) ?></textarea></label><label class="block text-xs font-semibold text-slate-700">Visual exclusions<textarea name="visual_never" rows="7" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs leading-5"><?= e(implode("\n",(array)($brandRules['photography']['never'] ?? []))) ?></textarea></label></div></details>
                    <label class="block text-sm font-semibold text-slate-800">What changed? <span class="text-rose-600">*</span><textarea name="change_note" required rows="3" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Example: Make educational headlines shorter and reserve burgundy for small accents."></textarea><span class="mt-1 block text-xs font-normal text-slate-500">This note becomes part of the version history.</span></label>
                    <button type="submit" class="min-h-12 w-full rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2">Activate new Brand Book version</button>
                </form></aside>
            </div>
        </section>
    <?php else: ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="published-title">
            <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Content archive</p><h2 id="published-title" class="mt-1 text-xl font-semibold text-slate-950">Published posts</h2><p class="mt-1 text-sm text-slate-600">A permanent record of posts successfully sent through Meta, newest first.</p></div>
            <?php if($publishedDrafts===[]): ?><div class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">No posts have been published yet.</div><?php endif; ?>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><?php foreach($publishedDrafts as $draft): ?><?php $imageUrl=social_studio_image_url($draft); ?><article class="overflow-hidden rounded-xl border border-slate-200 bg-white"><?php if($imageUrl!==''): ?><img class="aspect-[4/5] w-full bg-slate-100 object-contain" src="<?= e($imageUrl) ?>" alt="<?= e((string)$draft['title']) ?>" loading="lazy"><?php endif; ?><div class="p-4"><div class="flex items-start justify-between gap-2"><h3 class="text-sm font-semibold text-slate-950"><?= e((string)$draft['title']) ?></h3><span class="rounded-full border border-blue-200 bg-blue-50 px-2 py-1 text-[10px] font-semibold text-blue-700">Published</span></div><p class="mt-2 text-xs leading-5 text-slate-500"><?= e(str_limit((string)$draft['caption'],100)) ?></p><p class="mt-3 text-xs font-semibold text-slate-700"><?= e(!empty($draft['published_at'])?date('M j, Y · g:i A',strtotime((string)$draft['published_at'])):'Published') ?></p><button type="button" class="mt-3 min-h-11 w-full rounded-lg border border-slate-300 text-xs font-semibold" data-social-open data-draft-id="<?= e((string)$draft['id']) ?>" data-title="<?= e((string)$draft['title']) ?>" data-caption="<?= e((string)$draft['caption']) ?>" data-hashtags="<?= e((string)$draft['hashtags']) ?>" data-image="<?= e($imageUrl) ?>" data-status="Published">Open post</button></div></article><?php endforeach; ?></div>
        </section>
    <?php endif; ?>
    <form id="social-drop-schedule-form" method="POST" action="<?= e(base_url('app/actions/social_studio_publish.php')) ?>" class="hidden"><?= csrf_input() ?><input type="hidden" name="draft_id" value=""><input type="hidden" name="mode" value="schedule"><input type="hidden" name="week" value="<?= e($weekStart->format('Y-m-d')) ?>"><input type="hidden" name="schedule_day" value=""><input type="hidden" name="schedule_time" value=""></form>
    <div id="social-schedule-announcement" class="sr-only" role="status" aria-live="polite"></div>
</main>

<dialog id="social-post-modal" class="w-[min(94vw,1180px)] rounded-2xl border-0 bg-transparent p-0 shadow-2xl backdrop:bg-slate-950/60">
    <div class="grid max-h-[92dvh] overflow-y-auto rounded-2xl bg-white lg:grid-cols-[minmax(0,1.2fr)_minmax(340px,.8fr)] lg:overflow-hidden"><div class="grid min-h-[420px] place-items-center bg-slate-950"><img id="social-modal-image" class="max-h-[88vh] w-full object-contain" alt=""></div><div class="flex min-h-0 flex-col"><header class="flex items-center gap-3 border-b border-slate-100 p-4"><img class="h-9 w-9 rounded-full" src="<?= e(base_url('assets/img/elite-smiles-instagram-avatar.jpg')) ?>" alt="Elite Smiles"><div><p class="text-sm font-semibold">elitesmilesutah</p><p class="text-xs text-slate-500">Elite Smiles by Walter Meden DDS</p></div><span id="social-modal-status" class="ml-auto rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold"></span><button type="button" data-social-close class="grid h-11 w-11 place-items-center rounded-full text-2xl" aria-label="Close preview">×</button></header><form method="POST" action="<?= e(base_url('app/actions/social_studio_update_draft.php')) ?>" class="flex min-h-0 flex-1 flex-col"><?= csrf_input() ?><input id="social-modal-draft-id" type="hidden" name="draft_id" value=""><div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5"><label class="block text-sm font-semibold text-slate-800">Caption<textarea id="social-modal-caption" name="caption" rows="10" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm leading-6"></textarea></label><label class="block text-sm font-semibold text-slate-800">Hashtags<textarea id="social-modal-hashtags" name="hashtags" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm leading-6"></textarea></label></div><footer class="grid grid-cols-2 gap-2 border-t border-slate-100 p-4"><button type="button" data-social-close class="min-h-11 rounded-xl border border-slate-300 text-sm font-semibold">Close</button><button type="submit" class="min-h-11 rounded-xl bg-slate-950 text-sm font-semibold text-white">Save copy</button></footer></form></div></div>
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
    const replaceFrom = document.getElementById('social-replace-from');
    const replaceTo = document.getElementById('social-replace-to');
    const analysisButton = document.getElementById('social-run-analysis');
    const analysisProgress = document.getElementById('social-analysis-progress');
    const modeButtons = [...document.querySelectorAll('[data-social-mode]')];
    const modePanels = [...document.querySelectorAll('[data-social-mode-panel], [data-social-mode-only]')];
    const controlsTitle = document.getElementById('controls-title');
    const originalForm = document.getElementById('social-original-form');
    const originalButton = document.getElementById('social-original-button');
    const scheduleForm = document.getElementById('social-drop-schedule-form');
    const scheduleAnnouncement = document.getElementById('social-schedule-announcement');
    const scheduleCards = [...document.querySelectorAll('[data-schedule-card]')];
    const calendarDays = [...document.querySelectorAll('[data-calendar-day]')];
    let draggedScheduleCard = null;
    const setCreationMode = mode => {
        modeButtons.forEach(button => button.setAttribute('aria-pressed', button.dataset.socialMode === mode ? 'true' : 'false'));
        modePanels.forEach(panel => { panel.hidden = panel.dataset.socialModePanel !== mode && panel.dataset.socialModeOnly !== mode; });
        if (controlsTitle) controlsTitle.textContent = mode === 'original' ? 'Describe the original post' : 'Create the new version';
        const url = new URL(window.location.href);
        url.searchParams.set('mode', mode);
        window.history.replaceState({}, '', url);
    };
    modeButtons.forEach(button => button.addEventListener('click', () => setCreationMode(button.dataset.socialMode || 'remix')));
    document.getElementById('social-copy-mode-advanced')?.addEventListener('change', event => {
        copyMode.value = event.target.value;
        const rewriting = event.target.value === 'rewrite';
        [replaceFrom, replaceTo].forEach(input => { if (input) input.disabled = rewriting; });
    });

    const submitSchedule = (card, day) => {
        const time = card?.querySelector('[data-schedule-time]')?.value || '10:30';
        const scheduled = new Date(`${day}T${time}:00`);
        if (!Number.isFinite(scheduled.getTime()) || scheduled.getTime() < Date.now() + 60000) {
            window.alert('Choose a future day and time.');
            return;
        }
        scheduleForm.elements.draft_id.value = card.dataset.draftId || '';
        scheduleForm.elements.schedule_day.value = day;
        scheduleForm.elements.schedule_time.value = time;
        scheduleAnnouncement.textContent = `Scheduling post for ${day} at ${time}.`;
        scheduleForm.submit();
    };

    scheduleCards.forEach(card => {
        card.addEventListener('dragstart', event => {
            if (event.target.closest('button, select, input, summary, a')) { event.preventDefault(); return; }
            draggedScheduleCard = card;
            card.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.draftId || '');
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('is-dragging');
            calendarDays.forEach(day => day.classList.remove('is-dragover'));
            draggedScheduleCard = null;
        });
        const fallbackForm = card.querySelector('[data-schedule-fallback]');
        fallbackForm?.addEventListener('submit', () => {
            const fallbackTime = fallbackForm.querySelector('[data-fallback-time]');
            if (fallbackTime) fallbackTime.value = card.querySelector('[data-schedule-time]')?.value || '10:30';
        });
    });

    calendarDays.forEach(day => {
        day.addEventListener('dragover', event => {
            if (!draggedScheduleCard) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            day.classList.add('is-dragover');
        });
        day.addEventListener('dragleave', event => {
            if (!day.contains(event.relatedTarget)) day.classList.remove('is-dragover');
        });
        day.addEventListener('drop', event => {
            event.preventDefault();
            day.classList.remove('is-dragover');
            if (draggedScheduleCard) submitSchedule(draggedScheduleCard, day.dataset.calendarDay || '');
        });
    });

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
        if ((replaceFrom?.value.trim() === '') !== (replaceTo?.value.trim() === '')) {
            event.preventDefault();
            window.alert('Enter both the current approved text and its replacement.');
            (replaceFrom?.value.trim() === '' ? replaceFrom : replaceTo)?.focus();
            return;
        }
        generateButton.disabled = true;
        generateButton.textContent = 'Creating drafts…';
    });
    originalForm?.addEventListener('submit', event => {
        const request = originalForm.querySelector('[name="creative_request"]');
        if (!request?.value.trim()) {
            event.preventDefault();
            request?.focus();
            return;
        }
        originalButton.disabled = true;
        originalButton.textContent = 'CMO is building the brief…';
    });

    const refineForm = document.querySelector('[data-social-refine-form]');
    const refineInput = document.getElementById('social-refine-instruction');
    document.querySelectorAll('[data-refine-suggestion]').forEach(button => button.addEventListener('click', () => {
        if (!refineInput) return;
        refineInput.value = button.dataset.refineSuggestion || '';
        refineInput.focus();
    }));
    refineForm?.addEventListener('submit', event => {
        if (!refineInput?.value.trim()) {
            event.preventDefault();
            refineInput?.focus();
            return;
        }
        const button = refineForm.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-label', 'Refining image');
        }
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
