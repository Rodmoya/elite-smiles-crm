<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_auth();
$resultId = (int)get('id', 0);
$result = db_one('SELECT * FROM real_result_cases WHERE id = :id LIMIT 1', ['id' => $resultId]);
if (!$result) { http_response_code(404); exit('Result not found.'); }
$pairs = db_all('SELECT * FROM real_result_photo_pairs WHERE result_case_id = :id ORDER BY sort_order ASC, id ASC', ['id' => $resultId]);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= e(APP_NAME) ?> | Real Result</title><script src="https://cdn.tailwindcss.com"></script><meta name="robots" content="noindex,nofollow"></head>
<body class="min-h-screen bg-black text-white antialiased">
    <main class="mx-auto max-w-6xl px-4 py-6">
        <div class="mb-5 flex items-center justify-between"><div><p class="text-xs uppercase tracking-[0.24em] text-white/50">Actual Patient Result</p><h1 class="text-3xl font-semibold"><?= e((string)$result['title']) ?></h1><p class="mt-1 text-sm text-white/60"><?= e((string)$result['procedure_label']) ?> · <?= e((string)$result['style_label']) ?></p></div><a class="rounded-md border border-white/25 px-4 py-2 text-sm font-semibold" href="<?= e(base_url('smile-design/real-results')) ?>">Back</a></div>
        <?php foreach ($pairs as $pair): ?>
            <?php
                $pairAlignment = smile_design_alignment_for_real_pair((int)$pair['id']);
                $pairAlignmentEdit = [
                    'pair_type' => 'real_result',
                    'real_pair_id' => (int)$pair['id'],
                    'photo_type' => (string)($pair['photo_group'] ?? ''),
                    'return_url' => $_SERVER['REQUEST_URI'] ?? base_url('smile-design/real-results/' . $resultId),
                ];
                smile_before_after_viewer(
                    base_url('app/actions/smile_design_photo.php?real_pair_id=' . (int)$pair['id'] . '&side=before'),
                    base_url('app/actions/smile_design_photo.php?real_pair_id=' . (int)$pair['id'] . '&side=after'),
                    ['title' => (string)($pair['caption'] ?? $result['title']), 'mode' => 'side', 'alignment' => $pairAlignment, 'alignment_edit' => $pairAlignmentEdit]
                );
            ?>
            <div class="h-5"></div>
        <?php endforeach; ?>
        <?php if (!$pairs): ?><?php smile_before_after_viewer('', '', ['title' => (string)$result['title'], 'mode' => 'side']); ?><?php endif; ?>
        <p class="mt-4 text-sm leading-6 text-white/65">Actual Patient Result. Individual results may vary.</p>
    </main>
</body>
</html>
