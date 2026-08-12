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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e(APP_NAME) ?> | Social Studio</title>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/lead-agent.css')) ?>">
    <style>
        .social-template-card[aria-pressed="true"] { border-color:#0f172a; box-shadow:0 0 0 2px #0f172a; }
        .social-template-card:disabled { cursor:not-allowed; opacity:.68; }
        .social-template-card[hidden] { display:none; }
        .social-preview-image { aspect-ratio:4/5; width:100%; object-fit:contain; background:#f1f5f9; }
        .social-scrollbar { scrollbar-width:thin; scrollbar-color:#94a3b8 transparent; }
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

    <?php if ($successMessage !== ''): ?><div role="status" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e((string)$successMessage) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div role="alert" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?= e((string)$errorMessage) ?></div><?php endif; ?>
    <?php if ($autoGenerateIds !== []): ?><div id="social-generation-progress" role="status" aria-live="polite" class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-800">Preparing image generation…</div><?php endif; ?>

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
                                <form method="POST" action="<?= e(base_url('app/actions/social_studio_status.php')) ?>"><?= csrf_input() ?><input type="hidden" name="draft_id" value="<?= e((string)$draft['id']) ?>"><input type="hidden" name="status" value="approved"><button class="min-h-11 w-full rounded-lg border border-emerald-200 bg-emerald-50 text-xs font-semibold text-emerald-700 disabled:opacity-50" type="submit" <?= $imageUrl === '' ? 'disabled' : '' ?>>Approve</button></form>
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
