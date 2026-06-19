<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

smile_design_ensure_schema();

$token = trim((string)get('token', post('token', '')));
$link = $token !== '' ? smile_design_verify_token($token, 'mobile_upload') : null;
$message = '';
$error = '';

if (!$link) {
    http_response_code(404);
    $error = 'This mobile upload link is expired. Please scan a fresh QR code from the staff intake screen.';
} elseif (is_post()) {
    $photoType = (string)post('photo_type', 'front');
    $upload = smile_design_store_mobile_upload($token, $_FILES['photo'] ?? [], $photoType);
    if (!empty($upload['ok'])) {
        $message = 'Photo uploaded. You can upload another angle or return to the desktop case form.';
    } else {
        $error = (string)($upload['message'] ?? 'Could not upload photo.');
    }
}

$uploads = $link ? smile_design_mobile_uploads_for_token($token, true) : [];
$slots = [
    'front' => 'Front',
    'left_45' => 'Left 45',
    'right_45' => 'Right 45',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elite Smiles | Mobile Photo Upload</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-slate-950 text-white antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-xl flex-col px-4 py-5">
        <header class="pb-5">
            <img class="h-auto w-40 rounded bg-white p-2" src="<?= e(SMILE_DESIGN_LOGO_URL) ?>" alt="Elite Smiles">
            <p class="mt-5 text-xs font-semibold uppercase tracking-[0.24em] text-white/45">Smile Design Upload</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">Upload Before Photos</h1>
            <p class="mt-2 text-sm leading-6 text-white/65">This page only uploads photos to the current staff intake. It does not open the CRM.</p>
        </header>

        <?php if ($message !== ''): ?>
            <div class="mb-4 rounded-md border border-emerald-300/30 bg-emerald-400/10 px-4 py-3 text-sm leading-6 text-emerald-100"><?= e($message) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="mb-4 rounded-md border border-red-300/30 bg-red-400/10 px-4 py-3 text-sm leading-6 text-red-100"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($link): ?>
            <section class="grid gap-3">
                <?php foreach ($slots as $photoType => $label): ?>
                    <?php $uploaded = $uploads[$photoType] ?? null; ?>
                    <form class="rounded-md border border-white/10 bg-white/[0.04] p-4" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <input type="hidden" name="photo_type" value="<?= e($photoType) ?>">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold"><?= e($label) ?></h2>
                                <p class="mt-1 text-xs text-white/50"><?= $uploaded ? 'Uploaded. Re-upload to replace this angle.' : 'Not uploaded yet.' ?></p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.12em] <?= $uploaded ? 'bg-emerald-200 text-emerald-950' : 'bg-white/10 text-white/55' ?>"><?= $uploaded ? 'Ready' : 'Needed' ?></span>
                        </div>
                        <?php if ($uploaded): ?>
                            <p class="mt-3 truncate text-xs text-white/45"><?= e((string)$uploaded['original_name']) ?></p>
                        <?php endif; ?>
                        <label class="mt-4 flex min-h-32 cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed border-white/20 bg-black px-4 py-5 text-center">
                            <span class="text-sm font-semibold">Choose <?= e($label) ?> Photo</span>
                            <span class="mt-1 text-xs leading-5 text-white/45">Camera or photo library. JPG, PNG, WebP, HEIC, or HEIF.</span>
                            <input required name="photo" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" capture="environment" class="sr-only">
                        </label>
                        <button class="mt-3 w-full rounded-md bg-white px-4 py-3 text-sm font-bold text-black" type="submit">Upload <?= e($label) ?></button>
                    </form>
                <?php endforeach; ?>
            </section>
            <p class="mt-5 text-center text-xs leading-5 text-white/40">After uploading, return to the desktop intake form and click Create Smile Design Case.</p>
        <?php endif; ?>
    </main>
</body>
</html>
