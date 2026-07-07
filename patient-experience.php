<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/patient_experience/patient_experience_service.php';

require_auth();

if (is_post() && post('action') === 'logout') {
    require_csrf();
    auth_logout();
    flash_set('success', 'You have been logged out.');
    redirect(base_url('login.php'));
}

patient_experience_ensure_schema();

$currentPage = 'patient_experience';
$pageTitle = 'Patient Experience';
$logoutAction = base_url('patient-experience.php');
$successMessage = flash_get('success') ?? '';
$errorMessage = flash_get('error') ?? '';
$setupPreviewDeviceId = (int)get('setup_device_id', '0');
$setupPreviewToken = trim((string)get('setup_token', ''));

if (is_post() && post('action') === 'start_checkin') {
    require_csrf();
    $leadId = (int)post('lead_id', '0');
    $patientName = trim((string)post('patient_name', ''));
    $kioskDeviceId = (int)post('kiosk_device_id', '0');
    $session = patient_experience_start_placeholder_session($leadId > 0 ? $leadId : null, $patientName, auth_user_id(), $kioskDeviceId > 0 ? $kioskDeviceId : null);
    if (!empty($session['error'])) {
        flash_set('error', (string)$session['error']);
        redirect(base_url('patient-experience.php'));
    }
    flash_set('success', 'Check-in session created and assigned to the selected kiosk.');
    redirect(base_url('patient-experience.php'));
}

if (is_post() && post('action') === 'cancel_session') {
    require_csrf();
    $sessionId = (int)post('session_id', '0');
    if ($sessionId > 0 && patient_experience_cancel_session($sessionId, auth_user_id())) {
        flash_set('success', 'Check-in session cancelled.');
    } else {
        flash_set('error', 'Could not cancel that session.');
    }
    redirect(base_url('patient-experience.php'));
}

if (is_post() && post('action') === 'expire_stale') {
    require_csrf();
    $expired = patient_experience_expire_stale_sessions(auth_user_id());
    flash_set('success', $expired . ' stale check-in session(s) expired.');
    redirect(base_url('patient-experience.php'));
}

if (is_post() && post('action') === 'mark_reviewed') {
    require_csrf();
    $sessionId = (int)post('session_id', '0');
    $staffNotes = trim((string)post('staff_notes', ''));
    if ($sessionId > 0 && patient_experience_mark_reviewed($sessionId, auth_user_id(), $staffNotes)) {
        flash_set('success', 'Staff review saved.');
    } else {
        flash_set('error', 'Could not save staff review.');
    }
    redirect(base_url('patient-experience.php?session_id=' . $sessionId));
}

if (is_post() && post('action') === 'create_kiosk_device') {
    require_csrf();
    $deviceLabel = trim((string)post('device_label', ''));
    $locationLabel = trim((string)post('location_label', ''));
    $deviceId = patient_experience_create_kiosk_device($deviceLabel, $locationLabel, auth_user_id());
    $setupToken = patient_experience_issue_setup_token($deviceId, auth_user_id());
    flash_set('success', 'iPad setup link created.');
    redirect(base_url('patient-experience.php?setup_device_id=' . $deviceId . '&setup_token=' . rawurlencode($setupToken)));
}

if (is_post() && post('action') === 'regenerate_setup_token') {
    require_csrf();
    $deviceId = (int)post('device_id', '0');
    if ($deviceId <= 0 || !patient_experience_kiosk_device_by_id($deviceId)) {
        flash_set('error', 'Could not find that kiosk device.');
        redirect(base_url('patient-experience.php'));
    }
    $setupToken = patient_experience_issue_setup_token($deviceId, auth_user_id());
    flash_set('success', 'Setup link regenerated.');
    redirect(base_url('patient-experience.php?setup_device_id=' . $deviceId . '&setup_token=' . rawurlencode($setupToken)));
}

if (is_post() && post('action') === 'revoke_setup_token') {
    require_csrf();
    $setupTokenId = (int)post('setup_token_id', '0');
    if ($setupTokenId > 0 && patient_experience_revoke_setup_token($setupTokenId, auth_user_id())) {
        flash_set('success', 'Setup link revoked.');
    } else {
        flash_set('error', 'Could not revoke that setup link.');
    }
    redirect(base_url('patient-experience.php'));
}

$stats = patient_experience_stats();
$activeSession = patient_experience_active_session();
$activeProgress = $activeSession ? patient_experience_session_progress($activeSession) : ['status' => 'idle', 'current_step' => 'idle', 'percent_complete' => 0];
$activeSignatureSummary = $activeSession ? patient_experience_signature_summary((int)$activeSession['id']) : ['total' => 0, 'latest_signed_at' => ''];
$recentSessions = patient_experience_recent_sessions(30);
$selectedSessionId = (int)get('session_id', '0');
$selectedReview = $selectedSessionId > 0 ? patient_experience_staff_review_context($selectedSessionId) : null;
$kioskUrl = base_url('patient-experience/kiosk/');
$setupRouteExample = base_url('patient-experience/setup/example-token');
$kioskDevices = patient_experience_kiosk_devices();
$registeredDeviceOptions = patient_experience_registered_device_options();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Patient Experience</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <?php require __DIR__ . '/app/partials/crm_sidebar.php'; ?>

    <main class="px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
        <?php if ($errorMessage !== ''): ?>
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= e($errorMessage) ?></div>
        <?php endif; ?>
        <?php if ($successMessage !== ''): ?>
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <section class="mb-8">
            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                <div class="grid gap-0 xl:grid-cols-[1.1fr_0.9fr]">
                    <div class="p-6 lg:p-8">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">Patient Experience</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 lg:text-4xl">Waiting-room check-in control</h1>
                        <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-600">
                            Start a secure iPad session, watch live packet progress, and review a reusable digital forms packet built for long-term intake, consent, and policy workflows.
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="<?= e($kioskUrl) ?>" target="_blank" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Open Kiosk</a>
                            <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="expire_stale">
                                <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Expire Stale Sessions</button>
                            </form>
                        </div>
                    </div>
                    <div class="bg-slate-950 p-6 text-white lg:p-8">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">Current kiosk state</p>
                        <h2 class="mt-3 text-2xl font-semibold"><?= e(ucwords(str_replace('_', ' ', (string)$activeProgress['status']))) ?></h2>
                        <div class="mt-5 h-3 overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full bg-amber-300" style="width: <?= e((string)$activeProgress['percent_complete']) ?>%"></div>
                        </div>
                        <div class="mt-4 grid gap-2 text-sm text-slate-200">
                            <p>Step: <?= e(ucwords(str_replace('_', ' ', (string)$activeProgress['current_step']))) ?></p>
                            <p>Progress: <?= e((string)$activeProgress['percent_complete']) ?>%</p>
                            <p>Signature: <?= ((int)$activeSignatureSummary['total'] > 0) ? 'Captured ' . e(format_datetime((string)$activeSignatureSummary['latest_signed_at'])) : 'Not captured yet' ?></p>
                            <?php if ($activeSession): ?>
                                <p>Session #<?= e((string)$activeSession['id']) ?><?= trim((string)$activeSession['patient_name']) !== '' ? ' - ' . e((string)$activeSession['patient_name']) : '' ?></p>
                                <p>Kiosk: <?= e(trim((string)($activeSession['device_label'] ?? '')) !== '' ? (string)$activeSession['device_label'] : 'Waiting room') ?></p>
                                <div class="mt-3 space-y-2 rounded-2xl border border-white/10 bg-white/5 p-3">
                                    <?php foreach ((array)($activeProgress['sections'] ?? []) as $section): ?>
                                        <?php
                                        $sectionStatus = (string)($section['status'] ?? 'waiting');
                                        $tone = $sectionStatus === 'completed'
                                            ? 'bg-emerald-400'
                                            : ($sectionStatus === 'in_progress' ? 'bg-amber-300' : 'bg-white/20');
                                        ?>
                                        <div>
                                            <div class="flex items-center justify-between text-xs uppercase tracking-[0.16em] text-slate-200">
                                                <span><?= e((string)($section['title'] ?? 'Section')) ?></span>
                                                <span><?= e(str_replace('_', ' ', $sectionStatus)) ?></span>
                                            </div>
                                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white/10">
                                                <div class="h-full rounded-full <?= e($tone) ?>" style="width: <?= $sectionStatus === 'completed' ? '100' : ($sectionStatus === 'in_progress' ? '55' : '10') ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p>No active patient session assigned.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-8 grid gap-4 md:grid-cols-4">
            <?php foreach ([['Waiting', 'waiting'], ['In Progress', 'in_progress'], ['Completed Today', 'completed'], ['Cancelled / Expired', 'cancelled_expired']] as $item): ?>
                <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500"><?= e($item[0]) ?></p>
                    <p class="mt-3 text-3xl font-semibold text-slate-950"><?= e((string)($stats[$item[1]] ?? 0)) ?></p>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="mb-8 grid gap-6 xl:grid-cols-[0.75fr_1.25fr]">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Start Check-In</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Assign to kiosk</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">Creates a live waiting-room session. The kiosk only sees a display name and form state.</p>
                <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>" class="mt-5 space-y-4">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="start_checkin">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600" for="kiosk-device-id">Assigned kiosk</label>
                        <select id="kiosk-device-id" name="kiosk_device_id" class="min-h-12 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" required>
                            <option value="">Select a registered iPad</option>
                            <?php foreach ($registeredDeviceOptions as $deviceOption): ?>
                                <option value="<?= e((string)$deviceOption['id']) ?>"><?= e((string)$deviceOption['device_label']) ?> - <?= e((string)$deviceOption['location_label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$registeredDeviceOptions): ?>
                            <p class="mt-2 text-xs text-amber-700">Register an iPad below before assigning a live check-in.</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600" for="patient-name">Patient display name</label>
                        <input id="patient-name" name="patient_name" class="min-h-12 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" placeholder="Example: Rodrigo M.">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600" for="lead-id">Lead ID optional</label>
                        <input id="lead-id" name="lead_id" inputmode="numeric" class="min-h-12 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" placeholder="Example: 131">
                    </div>
                    <button class="min-h-12 w-full rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" type="submit"<?= $registeredDeviceOptions ? '' : ' disabled' ?>>Start and Assign</button>
                </form>

                <?php if ($activeSession): ?>
                    <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>" class="mt-4">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="cancel_session">
                        <input type="hidden" name="session_id" value="<?= e((string)$activeSession['id']) ?>">
                        <button type="submit" class="min-h-12 w-full rounded-2xl border border-red-200 bg-red-50 px-5 py-3 text-sm font-semibold text-red-700 hover:bg-red-100">Cancel Active Session</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Recent Sessions</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Live progress and history</h2>
                <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Session</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Progress</th>
                                <th class="px-4 py-3">Signature</th>
                                <th class="px-4 py-3">Lead</th>
                                <th class="px-4 py-3">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php if (!$recentSessions): ?>
                                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No check-in sessions yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentSessions as $session): ?>
                                    <?php $progress = patient_experience_session_progress($session); ?>
                                    <?php $signatureSummary = patient_experience_signature_summary((int)$session['id']); ?>
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-slate-900">#<?= e((string)$session['id']) ?><div class="text-xs font-normal text-slate-500"><?= e((string)$session['patient_name']) ?></div></td>
                                        <td class="px-4 py-3"><span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700"><?= e(ucwords(str_replace('_', ' ', (string)$progress['status']))) ?></span></td>
                                        <td class="px-4 py-3 text-slate-600"><?= e(ucwords(str_replace('_', ' ', (string)$progress['current_step']))) ?><div class="mt-1 h-1.5 w-28 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-amber-400" style="width: <?= e((string)$progress['percent_complete']) ?>%"></div></div></td>
                                        <td class="px-4 py-3 text-slate-600">
                                            <?php if ((int)$signatureSummary['total'] > 0): ?>
                                                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Captured</span>
                                                <div class="mt-1 text-xs text-slate-500"><?= e(format_datetime((string)$signatureSummary['latest_signed_at'])) ?></div>
                                            <?php else: ?>
                                                <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Missing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600"><?= ((int)($session['lead_id'] ?? 0) > 0) ? '#' . e((string)$session['lead_id']) : 'Not linked' ?></td>
                                        <td class="px-4 py-3 text-slate-500">
                                            <?= e(format_datetime((string)$session['created_at'])) ?>
                                            <div class="mt-2">
                                                <a href="<?= e(base_url('patient-experience.php?session_id=' . (int)$session['id'])) ?>" class="inline-flex items-center rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Review</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="mb-8 grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Kiosk Devices</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Register iPad kiosk</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">Create a secure setup link, open it on the iPad, then tap Share → Add to Home Screen.</p>
                <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>" class="mt-5 space-y-4">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="create_kiosk_device">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600" for="device-label">Device label</label>
                        <input id="device-label" name="device_label" class="min-h-12 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" placeholder="Example: Front Desk iPad">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600" for="location-label">Location label</label>
                        <input id="location-label" name="location_label" class="min-h-12 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" placeholder="Example: Front Desk">
                    </div>
                    <button class="min-h-12 w-full rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800" type="submit">Add / Register iPad</button>
                </form>

                <?php if ($setupPreviewDeviceId > 0 && $setupPreviewToken !== ''): ?>
                    <?php $setupPreviewUrl = patient_experience_kiosk_setup_url($setupPreviewToken); ?>
                    <div class="mt-6 rounded-[2rem] border border-amber-200 bg-amber-50 p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Setup Ready</p>
                        <p class="mt-2 text-sm text-slate-700">Open this QR code on the iPad, then tap Share → Add to Home Screen.</p>
                        <div class="mt-4 flex flex-col gap-4 md:flex-row md:items-center">
                            <img src="<?= e(patient_experience_kiosk_setup_qr_url($setupPreviewToken)) ?>" alt="Kiosk setup QR code" class="h-44 w-44 rounded-2xl border border-amber-200 bg-white p-2">
                            <div class="min-w-0 flex-1 space-y-3">
                                <label class="block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500" for="setup-link-preview">Setup link</label>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <input id="setup-link-preview" value="<?= e($setupPreviewUrl) ?>" readonly class="min-h-12 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-xs text-slate-700 outline-none">
                                    <button type="button" class="copy-setup-link min-h-12 rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100" data-copy-value="<?= e($setupPreviewUrl) ?>">Copy setup link</button>
                                </div>
                                <p class="text-xs leading-6 text-slate-600">Public route pattern: <span class="font-mono"><?= e($setupRouteExample) ?></span></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Device Status</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Registered kiosk inventory</h2>
                <div class="mt-5 space-y-4">
                    <?php if (!$kioskDevices): ?>
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">No kiosk devices have been created yet.</div>
                    <?php else: ?>
                        <?php foreach ($kioskDevices as $device): ?>
                            <?php
                            $deviceId = (int)$device['id'];
                            $isRegistered = patient_experience_kiosk_device_registered($device);
                            $statusLabel = !$isRegistered ? 'Not installed' : ((int)($device['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive');
                            $sessionStatus = trim((string)($device['active_session_status'] ?? ''));
                            ?>
                            <div class="rounded-[1.5rem] border border-slate-200 p-5">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-950"><?= e((string)$device['device_label']) ?></h3>
                                        <p class="mt-1 text-sm text-slate-500"><?= e((string)$device['location_label']) ?></p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <span class="rounded-full border px-3 py-1 text-xs font-semibold <?= $isRegistered ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' ?>"><?= e($statusLabel) ?></span>
                                        <?php if ($sessionStatus !== ''): ?>
                                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700"><?= e(ucwords(str_replace('_', ' ', $sessionStatus))) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-3 text-sm text-slate-600 md:grid-cols-2 xl:grid-cols-4">
                                    <div><span class="font-semibold text-slate-900">Last seen:</span> <?= e(trim((string)($device['last_seen_at'] ?? '')) !== '' ? format_datetime((string)$device['last_seen_at']) : 'Never') ?></div>
                                    <div><span class="font-semibold text-slate-900">Installed:</span> <?= e(trim((string)($device['registered_at'] ?? '')) !== '' ? format_datetime((string)$device['registered_at']) : 'No') ?></div>
                                    <div><span class="font-semibold text-slate-900">Current session:</span> <?= e($sessionStatus !== '' ? '#' . (string)$device['active_session_id'] . ' - ' . ((string)$device['active_session_patient_name'] ?: 'Patient') : 'Idle') ?></div>
                                    <div><span class="font-semibold text-slate-900">Current step:</span> <?= e($sessionStatus !== '' ? ucwords(str_replace('_', ' ', (string)$device['active_session_step_key'])) : 'Idle') ?></div>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-3">
                                    <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="regenerate_setup_token">
                                        <input type="hidden" name="device_id" value="<?= e((string)$deviceId) ?>">
                                        <button type="submit" class="min-h-11 rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"><?= $isRegistered ? 'Regenerate setup link' : 'Generate setup link' ?></button>
                                    </form>
                                    <?php if ((int)($device['active_setup_token_id'] ?? 0) > 0): ?>
                                        <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="revoke_setup_token">
                                            <input type="hidden" name="setup_token_id" value="<?= e((string)$device['active_setup_token_id']) ?>">
                                            <button type="submit" class="min-h-11 rounded-2xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">Revoke setup link</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <?php if ((int)($device['active_setup_token_id'] ?? 0) > 0): ?>
                                    <p class="mt-3 text-xs text-slate-500">Pending setup link expires <?= e(format_datetime((string)$device['active_setup_expires_at'])) ?>.</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($selectedReview): ?>
            <?php $review = $selectedReview; ?>
            <section class="mb-8 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Staff Review</p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950">Session #<?= e((string)$review['session']['id']) ?> review</h2>
                        <p class="mt-2 text-sm text-slate-600">
                            Completion <?= e((string)$review['progress']['percent_complete']) ?>% · Review status <?= e((string)($review['session']['review_status'] ?? 'pending')) ?>
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <?= trim((string)($review['session']['reviewed_at'] ?? '')) !== '' ? 'Reviewed ' . e(format_datetime((string)$review['session']['reviewed_at'])) : 'Not reviewed yet' ?>
                    </div>
                </div>

                <div class="mt-6 grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
                    <div class="space-y-6">
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Patient Summary</h3>
                            <div class="mt-3 grid gap-2 text-sm text-slate-700">
                                <?php foreach ($review['patient_summary'] as $label => $value): ?>
                                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-2">
                                        <span class="font-medium text-slate-500"><?= e((string)$label) ?></span>
                                        <span class="text-right"><?= e(trim((string)$value) !== '' ? (string)$value : 'Not provided') ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-4">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Clinical Snapshot</h3>
                            <div class="mt-3 space-y-3 text-sm text-slate-700">
                                <p><span class="font-medium text-slate-500">Medical alerts:</span> <?= e($review['medical_alerts'] ? implode(' | ', $review['medical_alerts']) : 'None flagged') ?></p>
                                <p><span class="font-medium text-slate-500">Interested services:</span> <?= e($review['interested_services'] ? implode(', ', $review['interested_services']) : 'None selected') ?></p>
                                <p><span class="font-medium text-slate-500">Financing interest:</span> <?= e((string)$review['financing_interest'] !== '' ? (string)$review['financing_interest'] : 'Not answered') ?></p>
                                <p><span class="font-medium text-slate-500">Smile goals:</span> <?= e((string)($review['treatment_goals']['Smile goals'] ?? '') !== '' ? (string)$review['treatment_goals']['Smile goals'] : 'Not provided') ?></p>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-4">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Missing Items</h3>
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <div class="rounded-2xl bg-amber-50 p-3 text-sm text-amber-800">
                                    <p class="font-semibold">Missing fields</p>
                                    <p class="mt-2"><?= e($review['missing_fields'] ? implode(', ', $review['missing_fields']) : 'None') ?></p>
                                </div>
                                <div class="rounded-2xl bg-amber-50 p-3 text-sm text-amber-800">
                                    <p class="font-semibold">Missing signatures</p>
                                    <p class="mt-2"><?= e($review['missing_signatures'] ? implode(', ', $review['missing_signatures']) : 'None') ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Section Progress</h3>
                            <div class="mt-4 space-y-3">
                                <?php foreach ((array)$review['progress']['sections'] as $section): ?>
                                    <div class="rounded-2xl border border-slate-100 p-3">
                                        <div class="flex items-center justify-between gap-3 text-sm">
                                            <span class="font-semibold text-slate-900"><?= e((string)$section['title']) ?></span>
                                            <span class="text-slate-500"><?= e(str_replace('_', ' ', (string)$section['status'])) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-4">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Consent & Photo Permissions</h3>
                            <div class="mt-3 grid gap-2 text-sm text-slate-700">
                                <?php foreach ($review['consent_status'] as $label => $value): ?>
                                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-2">
                                        <span><?= e((string)$label) ?></span>
                                        <span><?= e(trim((string)$value) !== '' ? (string)$value : 'Not answered') ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach ($review['photo_permissions'] as $label => $value): ?>
                                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-2">
                                        <span><?= e((string)$label) ?></span>
                                        <span><?= e(trim((string)$value) !== '' ? (string)$value : 'Not answered') ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-4">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Staff Review</h3>
                            <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>" class="mt-4 space-y-4">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="mark_reviewed">
                                <input type="hidden" name="session_id" value="<?= e((string)$review['session']['id']) ?>">
                                <textarea name="staff_notes" rows="4" class="min-h-28 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" placeholder="Staff notes, follow-up flags, or handoff context..."><?= e((string)($review['session']['staff_notes'] ?? '')) ?></textarea>
                                <button type="submit" class="min-h-12 rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">Mark Reviewed</button>
                            </form>
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-4">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Audit Timeline</h3>
                            <div class="mt-4 space-y-3 text-sm text-slate-700">
                                <?php foreach ((array)$review['audit_timeline'] as $event): ?>
                                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="font-semibold text-slate-900"><?= e((string)($event['event_label'] ?: $event['event_key'])) ?></span>
                                            <span class="text-xs text-slate-500"><?= e(format_datetime((string)$event['created_at'])) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <script>
        document.querySelectorAll('.copy-setup-link').forEach(function (button) {
            button.addEventListener('click', async function () {
                const value = button.getAttribute('data-copy-value') || '';
                if (!value) {
                    return;
                }
                try {
                    await navigator.clipboard.writeText(value);
                    const previous = button.textContent;
                    button.textContent = 'Copied';
                    window.setTimeout(function () {
                        button.textContent = previous;
                    }, 1400);
                } catch (error) {
                    button.textContent = 'Copy failed';
                }
            });
        });
    </script>
</body>
</html>
