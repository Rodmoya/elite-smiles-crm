<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/core/helpers.php';
require_once __DIR__ . '/../../app/core/db.php';
require_once __DIR__ . '/../../app/patient_experience/patient_experience_service.php';

patient_experience_ensure_schema();

$setupToken = trim((string)get('token', ''));
$setup = patient_experience_find_valid_setup_token($setupToken);
$registration = null;
$errorMessage = '';
$autoBegin = trim((string)get('auto_begin', '')) === '1';

if (is_post()) {
    $submittedToken = trim((string)post('setup_token', ''));
    if ($submittedToken === '') {
        $errorMessage = 'This setup link is missing its token.';
    } else {
        $registration = patient_experience_register_device_from_setup_token($submittedToken);
        if (!$registration) {
            $errorMessage = 'This setup link is no longer valid. Generate a new one from the CRM.';
        }
    }
}

$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$kioskUrl = base_url('patient-experience/kiosk/');
$kioskRedirectUrl = $autoBegin ? $kioskUrl . (str_contains($kioskUrl, '?') ? '&' : '?') . 'auto_begin=1' : $kioskUrl;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Elite Smiles Check-In Setup</title>
    <meta name="robots" content="noindex,nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#050505] text-white antialiased">
    <main class="flex min-h-screen items-center justify-center px-5 py-8">
        <section class="w-full max-w-3xl overflow-hidden rounded-[2.5rem] border border-amber-200/20 bg-white text-slate-950 shadow-2xl">
            <div class="bg-[#0b0b0b] px-8 py-8 text-white">
                <img src="<?= e($logoUrl) ?>" alt="Elite Smiles" class="w-56 max-w-full rounded-2xl bg-white p-4">
                <p class="mt-6 text-xs font-semibold uppercase tracking-[0.28em] text-amber-300">Kiosk Setup</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight">Elite Smiles Check-In</h1>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-300">Use this one-time setup flow on the iPad you want to dedicate to patient check-in. It will register the kiosk, then open the kiosk app.</p>
            </div>

            <div class="px-8 py-8">
                <?php if ($registration): ?>
                    <div class="rounded-[2rem] border border-emerald-200 bg-emerald-50 p-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Setup Complete</p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950"><?= e((string)$registration['device_label']) ?> is ready.</h2>
                        <p class="mt-3 text-sm leading-7 text-slate-700">We are saving this kiosk on the iPad and opening it now. After it opens, tap Share -> Add to Home Screen.</p>
                        <div class="mt-4 rounded-2xl border border-emerald-200 bg-white px-4 py-3 text-sm text-slate-700">
                            Location: <?= e((string)$registration['location_label']) ?>
                        </div>
                    </div>
                    <script>
                        (function () {
                            var deviceToken = <?= json_encode((string)$registration['device_token']) ?>;
                            var kioskUrl = <?= json_encode($kioskRedirectUrl) ?>;
                            try {
                                window.localStorage.setItem('patient_experience_device_token', deviceToken);
                            } catch (error) {}
                            document.cookie = 'patient_experience_device_token=' + encodeURIComponent(deviceToken) + '; path=/; SameSite=Lax';
                            window.setTimeout(function () {
                                window.location.replace(kioskUrl);
                            }, 1200);
                        })();
                    </script>
                <?php elseif ($setup): ?>
                    <div class="rounded-[2rem] border border-slate-200 bg-slate-50 p-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Ready to Register</p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950"><?= e((string)$setup['device_label']) ?></h2>
                        <p class="mt-2 text-sm text-slate-600"><?= e((string)$setup['location_label']) ?></p>
                        <ol class="mt-5 space-y-3 text-sm leading-7 text-slate-700">
                            <li>1. Keep this page open on the iPad you want to dedicate to check-in.</li>
                            <li>2. Tap the button below to register this kiosk.</li>
                            <li>3. After it opens the kiosk, tap Share -> Add to Home Screen.</li>
                        </ol>
                        <form method="POST" action="<?= e(base_url('patient-experience/setup/' . rawurlencode($setupToken))) ?>" class="mt-6">
                            <input type="hidden" name="setup_token" value="<?= e($setupToken) ?>">
                            <button type="submit" class="min-h-14 w-full rounded-2xl bg-slate-950 px-6 py-4 text-lg font-semibold text-white hover:bg-slate-800">Register Kiosk</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="rounded-[2rem] border border-red-200 bg-red-50 p-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-red-700">Setup Link Unavailable</p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">This setup link is not valid anymore.</h2>
                        <p class="mt-3 text-sm leading-7 text-slate-700"><?= e($errorMessage !== '' ? $errorMessage : 'Generate a fresh setup link from the Patient Experience page in the CRM.') ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
