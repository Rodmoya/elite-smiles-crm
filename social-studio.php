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
    flash_set('success', 'You have been logged out.');
    redirect(base_url('login.php'));
}

$user = auth_user() ?: [];
$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$currentPage = 'social_studio';
$pageTitle = 'Social Studio';
$logoutAction = base_url('social-studio.php');
$successMessage = flash_get('success') ?? '';
$errorMessage = flash_get('error') ?? '';
$data = social_studio_dashboard_data();
$counts = $data['counts'];
$drafts = $data['drafts'];
$selected = $data['selected'];
$schedule = $data['schedule'];

function social_studio_badge_class(string $status): string
{
    return match ($status) {
        'approved', 'scheduled' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'review' => 'border-amber-200 bg-amber-50 text-amber-700',
        'published' => 'border-blue-200 bg-blue-50 text-blue-700',
        'rejected' => 'border-rose-200 bg-rose-50 text-rose-700',
        default => 'border-slate-200 bg-slate-100 text-slate-600',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Social Studio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
    <style>
        @media (min-width: 1280px) {
            .social-workspace { display: grid; grid-template-columns: minmax(0, 1fr) 360px; align-items: start; }
            .social-workspace > .social-main, .social-workspace > .social-rail { display: contents; }
            .social-main > section:nth-child(1) { grid-column: 2; grid-row: 1; }
            .social-main > section:nth-child(2) { grid-column: 1; grid-row: 2; }
            .social-rail > section:nth-child(1) { grid-column: 1; grid-row: 1; }
            .social-rail > section:nth-child(2) { grid-column: 2; grid-row: 2; }
            .social-rail > section:nth-child(3) { grid-column: 2; grid-row: 3; }
        }
        .instagram-review { max-width: 430px; margin: 0 auto; }
        .instagram-review .review-image { aspect-ratio: 4 / 5; object-fit: cover; }
        .creative-frame { position: relative; overflow: hidden; }
        .creative-overlay { position: absolute; inset: 0; display: flex; flex-direction: column; justify-content: space-between; padding: 1.25rem; background: linear-gradient(180deg, rgba(255,255,255,.86) 0%, rgba(255,255,255,0) 38%, rgba(2,6,23,.08) 68%, rgba(2,6,23,.72) 100%); pointer-events: none; }
        .creative-overlay h3 { max-width: 15rem; font-family: Georgia, serif; font-size: clamp(1.5rem, 3vw, 2.25rem); line-height: .95; letter-spacing: -.04em; color: #1e293b; }
        .creative-overlay .benefits { align-self: flex-start; margin-top: auto; margin-bottom: .8rem; display: grid; gap: .35rem; color: #1e293b; font-size: .68rem; font-weight: 700; }
        .creative-overlay .benefits span { display: block; padding: .35rem .55rem; border-left: 2px solid #b08b62; background: rgba(255,255,255,.84); }
        .creative-overlay .creative-cta { align-self: flex-start; border: 1px solid #b08b62; background: #1e293b; color: white; padding: .55rem .75rem; font-size: .62rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <?php require __DIR__ . '/app/partials/crm_sidebar.php'; ?>

    <main class="px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
        <?php if ($successMessage !== ''): ?>
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e((string)$successMessage) ?></div>
        <?php endif; ?>
        <?php if ($errorMessage !== ''): ?>
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= e((string)$errorMessage) ?></div>
        <?php endif; ?>

        <section class="mb-8">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:p-8">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Marketing</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 lg:text-4xl">Social Studio</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                            Create AI-assisted Facebook and Instagram drafts inside the CRM. Drafts stay internal until Rod approves and schedules them.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-100" disabled>Import Meta Posts</button>
                        <button type="submit" form="social-generate-form" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Generate This Week</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Waiting Review</p>
                <p class="mt-2 text-3xl font-semibold"><?= e((string)($counts['review'] ?? 0)) ?></p>
                <p class="mt-1 text-sm text-slate-500">AI drafts ready</p>
            </div>
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Approved</p>
                <p class="mt-2 text-3xl font-semibold"><?= e((string)($counts['approved'] ?? 0)) ?></p>
                <p class="mt-1 text-sm text-slate-500">Ready to schedule</p>
            </div>
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Scheduled</p>
                <p class="mt-2 text-3xl font-semibold"><?= e((string)($counts['scheduled'] ?? 0)) ?></p>
                <p class="mt-1 text-sm text-slate-500">Queued, not published</p>
            </div>
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Frequency</p>
                <p class="mt-2 text-3xl font-semibold">1/day</p>
                <p class="mt-1 text-sm text-slate-500">Default rule</p>
            </div>
        </section>

        <section class="social-workspace grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
            <div class="social-main space-y-5">
                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Create</p>
                            <h2 class="mt-2 text-xl font-semibold text-slate-900">Generate social drafts</h2>
                            <p class="mt-1 text-sm text-slate-500">One focused form. No publishing from this screen.</p>
                        </div>
                    </div>
                    <form id="social-generate-form" class="grid gap-4 lg:grid-cols-[220px_1fr]" method="POST" action="<?= e(base_url('app/actions/social_studio_generate.php')) ?>">
                        <?= csrf_input() ?>
                        <div class="space-y-2">
                            <?php foreach ([['1', 'Define story'], ['2', 'Create copy'], ['3', 'Create visual'], ['4', 'Assemble post'], ['5', 'Review & approve']] as [$number, $label]): ?>
                                <div class="<?= $number === '1' ? 'bg-slate-950 text-white' : 'bg-white text-slate-700' ?> flex items-center gap-3 rounded-2xl border border-slate-200 px-3 py-3 text-sm font-semibold">
                                    <span class="<?= $number === '1' ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-700' ?> grid h-8 w-8 shrink-0 place-items-center rounded-xl text-xs font-bold"><?= e($number) ?></span>
                                    <?= e($label) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block text-sm font-semibold text-slate-800">Focus
                                <select name="focus" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3">
                                    <option value="veneers">Veneers consults</option>
                                    <option value="smile_makeover">Smile makeover</option>
                                    <option value="implants">Implants</option>
                                    <option value="lip_repositioning">Lip repositioning</option>
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-slate-800">How many?
                                <select name="count" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3">
                                    <?php for ($postCount = 1; $postCount <= 7; $postCount++): ?>
                                        <option value="<?= $postCount ?>" <?= $postCount === 7 ? 'selected' : '' ?>><?= $postCount ?> <?= $postCount === 1 ? 'post' : 'posts' ?></option>
                                    <?php endfor; ?>
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-slate-800 sm:col-span-2">Instruction
                                <textarea name="instruction" rows="3" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3" placeholder="Use Elite Smiles voice. Keep it friendly, premium, and conversion focused. Goal: schedule veneer consults."></textarea>
                            </label>
                            <div class="rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm leading-6 text-blue-800 sm:col-span-2">
                                OpenAI writes the post title, caption, CTA, hashtags, and exact image prompt. Nano Banana creates the image with no text or logo, then the CRM adds the Elite Smiles logo.
                            </div>
                            <div class="flex flex-wrap justify-end gap-3 sm:col-span-2">
                                <button type="submit" class="rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white">Generate Drafts</button>
                            </div>
                        </div>
                    </form>
                </section>

                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Drafts</p>
                            <h2 class="mt-2 text-xl font-semibold text-slate-900">Review queue</h2>
                            <p class="mt-1 text-sm text-slate-500">Approve, schedule, or reject. Meta publishing is not enabled in this first slice.</p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <?php if ($drafts === []): ?>
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No social drafts yet. Generate this week to start.</div>
                        <?php endif; ?>
                        <?php foreach ($drafts as $draft): ?>
                            <?php $status = (string)($draft['status'] ?? 'draft'); ?>
                            <?php $draftImageUrl = social_studio_image_url($draft); ?>
                            <article class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[76px_1fr_auto] sm:items-center">
                                <?php if ($draftImageUrl !== ''): ?>
                                    <img class="h-[76px] w-[76px] rounded-2xl bg-slate-100 object-cover" src="<?= e($draftImageUrl) ?>" alt="">
                                <?php else: ?>
                                    <div class="h-[76px] w-[76px] rounded-2xl bg-gradient-to-br from-slate-950 via-slate-600 to-amber-300"></div>
                                <?php endif; ?>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-sm font-semibold text-slate-950"><?= e((string)$draft['title']) ?></h3>
                                        <span class="<?= e(social_studio_badge_class($status)) ?> rounded-full border px-2.5 py-1 text-[11px] font-semibold"><?= e(social_studio_status_labels()[$status] ?? $status) ?></span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500"><?= e(social_studio_focus_label((string)$draft['content_focus'])) ?> &middot; <?= e((string)$draft['platform']) ?><?= !empty($draft['scheduled_at']) ? ' &middot; ' . e(format_datetime((string)$draft['scheduled_at'])) : '' ?></p>
                                    <p class="mt-2 text-sm leading-6 text-slate-600"><?= e(str_limit((string)$draft['caption'], 170)) ?></p>
                                </div>
                                <div class="flex flex-wrap gap-2 sm:justify-end">
                                    <button type="button" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" data-social-open data-title="<?= e((string)$draft['title']) ?>" data-caption="<?= e((string)$draft['caption']) ?>" data-image="<?= e($draftImageUrl) ?>" data-status="<?= e(social_studio_status_labels()[$status] ?? $status) ?>">Open post</button>
                                    <form method="POST" action="<?= e(base_url('app/actions/social_studio_delete.php')) ?>" onsubmit="return confirm('Delete this draft? This cannot be undone.');">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="draft_id" value="<?= e((string)$draft['id']) ?>">
                                        <button class="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 bg-white text-slate-400 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600" type="submit" aria-label="Delete draft" title="Delete draft">
                                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </button>
                                    </form>
                                    <form method="POST" action="<?= e(base_url('app/actions/social_studio_generate_image.php')) ?>">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="draft_id" value="<?= e((string)$draft['id']) ?>">
                                        <button class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700" type="submit"><?= $draftImageUrl !== '' ? 'Regenerate Image' : 'Generate Image' ?></button>
                                    </form>
                                    <?php foreach ([['approved', 'Approve'], ['scheduled', 'Schedule'], ['rejected', 'Reject']] as [$nextStatus, $buttonLabel]): ?>
                                        <form method="POST" action="<?= e(base_url('app/actions/social_studio_status.php')) ?>">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="draft_id" value="<?= e((string)$draft['id']) ?>">
                                            <input type="hidden" name="status" value="<?= e($nextStatus) ?>">
                                            <button class="<?= $nextStatus === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($nextStatus === 'rejected' ? 'border-rose-200 bg-white text-rose-700' : 'border-slate-300 bg-white text-slate-700') ?> rounded-xl border px-3 py-2 text-xs font-semibold" type="submit"><?= e($buttonLabel) ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <aside class="social-rail space-y-5">
                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Preview</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">Selected draft</h2>
                    </div>
                    <?php if ($selected): ?>
                        <?php $selectedImageUrl = social_studio_image_url($selected); ?>
                        <div class="instagram-review overflow-hidden rounded-2xl border border-slate-200">
                                <div class="flex items-center gap-3 border-b border-slate-100 px-3 py-3">
                                <img class="h-7 w-7 rounded-full object-cover" src="<?= e(base_url('assets/img/elite-smiles-instagram-avatar.jpg')) ?>" alt="Elite Smiles Instagram avatar">
                                <div class="text-xs font-semibold text-slate-900">elite.smiles.utah<span class="block text-[10px] font-normal text-slate-500">Elite Smiles</span></div>
                                <div class="ml-auto text-sm font-bold tracking-[0.2em] text-slate-500">···</div>
                            </div>
                            <?php if ($selectedImageUrl !== ''): ?>
                                <div class="creative-frame"><img class="review-image w-full bg-slate-100" src="<?= e($selectedImageUrl) ?>" alt="<?= e((string)$selected['title']) ?>"><div class="creative-overlay"><h3>The Power of Veneers</h3><div class="benefits"><span>Natural-looking results</span><span>Customized shape and shade</span><span>Confidence for every moment</span></div><div class="creative-cta">Complimentary consultation</div></div></div>
                            <?php else: ?>
                                <div class="review-image flex items-end bg-gradient-to-br from-slate-950 via-slate-700 to-amber-300 p-5 text-white">
                                    <h3 class="max-w-sm text-2xl font-semibold leading-tight tracking-tight"><?= e((string)$selected['title']) ?></h3>
                                </div>
                            <?php endif; ?>
                            <div class="border-b border-slate-100 px-3 py-2 text-lg tracking-[0.35em] text-slate-900">♡　◌　➤ <span class="float-right tracking-normal">⌑</span></div>
                            <div class="space-y-3 p-4">
                                <p class="text-xs font-semibold text-slate-900">128 likes</p>
                                <p class="text-sm leading-6 text-slate-700"><?= e((string)$selected['caption']) ?></p>
                                <?php if (!empty($selected['cta'])): ?><p class="text-sm font-semibold text-slate-900">CTA: <?= e((string)$selected['cta']) ?></p><?php endif; ?>
                                <p class="text-xs leading-5 text-slate-500"><?= e((string)$selected['hashtags']) ?></p>
                                <?php if (!empty($selected['image_prompt'])): ?>
                                    <details class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-5 text-slate-600">
                                        <summary class="cursor-pointer font-semibold text-slate-700">OpenAI image prompt for Nano Banana</summary>
                                        <p class="mt-2"><?= e((string)$selected['image_prompt']) ?></p>
                                    </details>
                                <?php endif; ?>
                                <form method="POST" action="<?= e(base_url('app/actions/social_studio_generate_image.php')) ?>">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="draft_id" value="<?= e((string)$selected['id']) ?>">
                                    <button class="w-full rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-700" type="submit"><?= $selectedImageUrl !== '' ? 'Regenerate Image' : 'Generate Image' ?></button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Generate drafts to preview one here.</div>
                    <?php endif; ?>
                </section>

                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Schedule</p>
                            <h2 class="mt-2 text-xl font-semibold text-slate-900">Upcoming</h2>
                        </div>
                        <span class="rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">1/day</span>
                    </div>
                    <div class="space-y-2">
                        <?php if ($schedule === []): ?>
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">No scheduled drafts yet.</div>
                        <?php endif; ?>
                        <?php foreach ($schedule as $item): ?>
                            <div class="rounded-2xl border border-slate-200 bg-white p-3">
                                <p class="text-sm font-semibold text-slate-900"><?= e(format_datetime((string)$item['scheduled_at'], 'D, M j')) ?> &middot; <?= e(format_datetime((string)$item['scheduled_at'], 'g:i A')) ?></p>
                                <p class="mt-1 text-sm text-slate-600"><?= e((string)$item['title']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Settings</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">Default rules</h2>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <p><span class="font-semibold text-slate-900">Publishing:</span> require approval before schedule.</p>
                        <p><span class="font-semibold text-slate-900">Frequency:</span> 1 post per day.</p>
                        <p><span class="font-semibold text-slate-900">Images:</span> OpenAI prompt first, Nano Banana art second, CRM logo last.</p>
                        <p><span class="font-semibold text-slate-900">Meta:</span> publishing disabled for this MVP.</p>
                    </div>
                </section>
            </aside>
        </section>
    </main>
    <dialog id="social-post-modal" class="w-[min(94vw,1180px)] rounded-[1.5rem] border-0 bg-transparent p-0 shadow-2xl backdrop:bg-slate-950/60">
        <div class="grid max-h-[92vh] overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white lg:grid-cols-[minmax(0,1.18fr)_minmax(360px,.82fr)]">
            <div class="flex min-h-[420px] items-center justify-center bg-slate-950 p-4 lg:min-h-[760px] lg:p-0"><img id="social-modal-image" class="max-h-[86vh] w-full object-contain" alt=""></div>
            <div class="flex min-h-0 flex-col bg-white">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div class="flex items-center gap-3"><img class="h-9 w-9 rounded-full object-cover" src="<?= e(base_url('assets/img/elite-smiles-instagram-avatar.jpg')) ?>" alt="Elite Smiles Instagram avatar"><div><p class="text-sm font-semibold text-slate-900">elitesmilesutah</p><p class="text-xs text-slate-500">Elite Smiles by Walter Meden DDS</p></div></div><div class="flex items-center gap-3"><span id="social-modal-status" class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700">Review</span><button type="button" data-social-close class="text-2xl leading-none text-slate-500 hover:text-slate-900" aria-label="Close post review">×</button></div></div>
                <div class="min-h-0 flex-1 overflow-y-auto p-5"><p id="social-modal-caption" class="whitespace-pre-line text-sm leading-6 text-slate-700"></p><p class="mt-5 text-xs text-slate-400">8w</p></div>
                <div class="border-t border-slate-100 px-5 py-4"><div class="mb-4 flex items-center gap-5 text-2xl text-slate-900">♡　◌　➤ <span class="ml-auto">⌑</span></div><p class="text-xs font-semibold text-slate-900">128 likes</p><button type="button" class="mt-4 w-full rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-social-close>Close preview</button></div>
            </div>
        </div>
    </dialog>
    <script>
        const socialModal = document.getElementById('social-post-modal');
        const socialModalTitle = document.getElementById('social-modal-title');
        const socialModalCaption = document.getElementById('social-modal-caption');
        const socialModalImage = document.getElementById('social-modal-image');
        const socialModalStatus = document.getElementById('social-modal-status');
        document.querySelectorAll('[data-social-open]').forEach((button) => button.addEventListener('click', () => {
            socialModalTitle.textContent = button.dataset.title || 'Selected draft';
            socialModalCaption.textContent = button.dataset.caption || '';
            socialModalStatus.textContent = button.dataset.status || 'Review';
            socialModalImage.src = button.dataset.image || '';
            socialModalImage.alt = button.dataset.title || 'Social post image';
            socialModal.showModal();
        }));
        document.querySelector('[data-social-close]')?.addEventListener('click', () => socialModal.close());
        socialModal?.addEventListener('click', (event) => { if (event.target === socialModal) socialModal.close(); });
    </script>
</body>
</html>
