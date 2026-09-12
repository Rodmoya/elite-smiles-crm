<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
$user = smile_design_internal_boot('Real Results');
$results = db_all('SELECT * FROM real_result_cases ORDER BY is_featured DESC, created_at DESC, id DESC LIMIT 50');
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= e(APP_NAME) ?> | Real Results</title><script src="https://cdn.tailwindcss.com"></script><meta name="robots" content="noindex,nofollow"></head>
<body class="min-h-screen bg-black text-white antialiased">
    <main class="mx-auto max-w-7xl px-4 py-6">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div><p class="text-xs uppercase tracking-[0.24em] text-white/50">Actual Patient Result</p><h1 class="text-3xl font-semibold">Real Patient Results</h1><p class="mt-2 text-sm text-white/60">Individual results may vary.</p></div>
            <div class="flex gap-2"><a class="rounded-md border border-white/25 px-4 py-2 text-sm font-semibold" href="<?= e(base_url('smile-design')) ?>">Dashboard</a><a class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-black" href="<?= e(base_url('smile-design/real-results/new')) ?>">New Real Result</a></div>
        </div>
        <?php if ($message = flash_get('success')): ?><div class="mb-5 rounded-md border border-emerald-300/25 bg-emerald-400/10 px-4 py-3 text-sm leading-6 text-emerald-100"><?= e($message) ?></div><?php endif; ?>
        <?php if ($message = flash_get('error')): ?><div class="mb-5 rounded-md border border-red-300/25 bg-red-400/10 px-4 py-3 text-sm leading-6 text-red-100"><?= e($message) ?></div><?php endif; ?>
        <div class="mb-5 grid gap-2 text-sm md:grid-cols-5">
            <?php foreach (['Procedure', 'LVI style', 'Full Head / Smile Close-Up', 'Doctor approved', 'Marketing approved'] as $filter): ?><button class="rounded-md border border-white/20 px-3 py-2 text-white/75" type="button"><?= e($filter) ?></button><?php endforeach; ?>
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($results as $result): ?>
                <div class="relative rounded-md border border-white/15 bg-white/5 p-5">
                    <form method="POST" action="<?= e(base_url('app/actions/smile_design_real_result_delete.php')) ?>" class="absolute right-3 top-3 z-10" onsubmit="return confirm('Delete this real result case? This removes its title, story, and any saved before/after photos permanently.');">
                        <?= csrf_input() ?>
                        <input type="hidden" name="result_id" value="<?= e((string)$result['id']) ?>">
                        <button class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/20 bg-black/60 text-rose-300 hover:bg-black/80" type="submit" aria-label="Delete <?= e((string)$result['title']) ?>">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4 7h16" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                                <path d="M9.5 7V5.75A1.75 1.75 0 0 1 11.25 4h1.5a1.75 1.75 0 0 1 1.75 1.75V7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                                <path d="M18 7 17.2 18.2A2 2 0 0 1 15.2 20H8.8a2 2 0 0 1-2-1.8L6 7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M10 11v5M14 11v5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </form>
                    <a class="block" href="<?= e(base_url('smile-design/real-results/' . (int)$result['id'])) ?>">
                        <img class="mb-4 h-auto w-36 rounded-md bg-white p-2" src="<?= e(SMILE_DESIGN_LOGO_URL) ?>" alt="Elite Smiles">
                        <p class="pr-8 text-lg font-semibold"><?= e((string)$result['title']) ?></p>
                        <p class="mt-1 text-sm text-white/60"><?= e((string)$result['procedure_label']) ?> · <?= e((string)$result['style_label']) ?></p>
                        <p class="mt-3 text-sm leading-6 text-white/60"><?= e(str_limit((string)$result['story'], 140)) ?></p>
                    </a>
                </div>
            <?php endforeach; ?>
            <?php if (!$results): ?><p class="rounded-md border border-dashed border-white/25 p-5 text-white/60">No real patient result cases yet.</p><?php endif; ?>
        </div>
    </main>
</body>
</html>
