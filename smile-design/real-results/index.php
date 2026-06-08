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
        <div class="mb-5 grid gap-2 text-sm md:grid-cols-5">
            <?php foreach (['Procedure', 'LVI style', 'Full Head / Smile Close-Up', 'Doctor approved', 'Marketing approved'] as $filter): ?><button class="rounded-md border border-white/20 px-3 py-2 text-white/75" type="button"><?= e($filter) ?></button><?php endforeach; ?>
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($results as $result): ?>
                <a class="rounded-md border border-white/15 bg-white/5 p-5" href="<?= e(base_url('smile-design/real-results/' . (int)$result['id'])) ?>">
                    <img class="mb-4 h-auto w-36 rounded-md bg-white p-2" src="<?= e(SMILE_DESIGN_LOGO_URL) ?>" alt="Elite Smiles">
                    <p class="text-lg font-semibold"><?= e((string)$result['title']) ?></p>
                    <p class="mt-1 text-sm text-white/60"><?= e((string)$result['procedure_label']) ?> · <?= e((string)$result['style_label']) ?></p>
                    <p class="mt-3 text-sm leading-6 text-white/60"><?= e(str_limit((string)$result['story'], 140)) ?></p>
                </a>
            <?php endforeach; ?>
            <?php if (!$results): ?><p class="rounded-md border border-dashed border-white/25 p-5 text-white/60">No real patient result cases yet.</p><?php endif; ?>
        </div>
    </main>
</body>
</html>
