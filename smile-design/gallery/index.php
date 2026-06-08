<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

$user = smile_design_internal_boot('Gallery');
$results = smile_design_real_after_gallery_versions(24);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= e(APP_NAME) ?> | Gallery</title><script src="https://cdn.tailwindcss.com"></script><meta name="robots" content="noindex,nofollow"></head>
<body class="min-h-screen bg-black text-white antialiased">
    <main class="mx-auto max-w-7xl px-4 py-6">
        <div class="mb-6 flex items-center justify-between">
            <div><p class="text-xs uppercase tracking-[0.24em] text-white/50">Real Patient Result Gallery</p><h1 class="text-3xl font-semibold">Internal Gallery</h1></div>
            <a class="rounded-md border border-white/25 px-4 py-2 text-sm font-semibold" href="<?= e(base_url('smile-design')) ?>">Back</a>
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($results as $result): ?>
                <a class="rounded-md border border-white/15 bg-white/5 p-5" href="<?= e(base_url('smile-design/cases/' . (int)$result['case_id'] . '/present')) ?>">
                    <img class="mb-4 h-48 w-full rounded-md bg-white/5 object-contain" src="<?= e(smile_design_after_url((int)$result['id'])) ?>" alt="Actual patient result">
                    <p class="text-lg font-semibold"><?= e((string)$result['patient_name']) ?></p>
                    <p class="mt-1 text-sm text-white/60"><?= e((string)$result['procedure_interest']) ?> · <?= e((string)$result['lvi_style_key']) ?></p>
                </a>
            <?php endforeach; ?>
            <?php if (!$results): ?><p class="rounded-md border border-dashed border-white/25 p-5 text-white/60">No approved real clinical afters yet.</p><?php endif; ?>
        </div>
    </main>
</body>
</html>
