<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/twilio.php';
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

if (is_post() && post('action') === 'start_test_intake') {
    require_csrf();
    $patientName = trim((string)post('test_patient_name', ''));
    if ($patientName === '') {
        $patientName = 'Test Patient';
    }
    $session = patient_experience_start_placeholder_session(null, $patientName, auth_user_id(), null);
    if (!empty($session['error'])) {
        flash_set('error', (string)$session['error']);
    } else {
        flash_set('success', 'Direct test session created.');
    }
    redirect(base_url('patient-experience.php?direct_test_token=' . rawurlencode((string)($session['token'] ?? '')) . '&direct_test_patient_name=' . rawurlencode($patientName)));
}

if (is_post() && post('action') === 'start_direct_test_intake') {
    require_csrf();
    $patientName = trim((string)post('test_patient_name', ''));
    if ($patientName === '') {
        $patientName = 'Test Patient';
    }
    $session = patient_experience_start_placeholder_session(null, $patientName, auth_user_id(), null);
    if (!empty($session['error'])) {
        flash_set('error', (string)$session['error']);
        redirect(base_url('patient-experience.php'));
    }
    flash_set('success', 'Direct forms QR created.');
    redirect(base_url('patient-experience.php?direct_test_token=' . rawurlencode((string)($session['token'] ?? '')) . '&direct_test_patient_name=' . rawurlencode($patientName)));
}

if (is_post() && post('action') === 'send_secure_consent_link') {
    require_csrf();
    $patientName = trim((string)post('secure_patient_name', ''));
    if ($patientName === '') {
        $patientName = 'Patient';
    }
    $leadId = (int)post('lead_id', '0');
    $phone = trim((string)post('secure_phone', ''));
    $email = trim((string)post('secure_email', ''));
    $session = patient_experience_start_placeholder_session($leadId > 0 ? $leadId : null, $patientName, auth_user_id(), null);
    if (!empty($session['error'])) {
        flash_set('error', (string)$session['error']);
        redirect(base_url('patient-experience.php?tab=patients'));
    }

    $token = (string)($session['token'] ?? '');
    $secureLink = base_url('patient-experience/kiosk/?direct=1&kiosk_token=' . rawurlencode($token));
    $channels = [];
    $issues = [];

    if ($phone !== '') {
        $smsBody = 'Elite Smiles secure consent link for ' . $patientName . ': ' . $secureLink;
        $smsResult = elite_twilio_send_sms($phone, $smsBody, ['source' => 'patient_experience_secure_consent']);
        if (!empty($smsResult['ok'])) {
            $channels[] = 'text';
        } else {
            $issues[] = 'SMS: ' . (string)($smsResult['message'] ?? 'Not sent');
        }
    }

    if ($email !== '') {
        $emailSubject = 'Your secure Elite Smiles consent link';
        $emailBody = "Hi {$patientName},\n\nUse this secure link to complete your consent forms:\n\n{$secureLink}\n\nIf you were not expecting this message, please ignore it.\n\nElite Smiles";
        $emailSent = elite_send_mail($email, $emailSubject, $emailBody);
        if ($emailSent) {
            $channels[] = 'email';
        } else {
            $issues[] = 'Email not sent';
        }
    }

    patient_experience_audit('secure_consent_link_sent', [
        'channels' => $channels,
        'secure_link' => $secureLink,
        'has_phone' => $phone !== '',
        'has_email' => $email !== '',
    ], (int)($session['id'] ?? 0), $leadId > 0 ? $leadId : null, auth_user_id());

    $redirectParams = [
        'tab' => 'patients',
        'secure_consent_token' => $token,
        'secure_consent_patient_name' => $patientName,
        'secure_consent_phone' => $phone,
        'secure_consent_email' => $email,
    ];
    if ($issues === []) {
        $message = $channels ? ('Secure consent link sent by ' . implode(' and ', $channels) . '.') : 'Secure consent link created.';
        flash_set('success', $message);
    } else {
        $message = 'Secure consent link created.';
        if ($channels) {
            $message .= ' Sent by ' . implode(' and ', $channels) . '.';
        }
        $message .= ' ' . implode(' ', $issues);
        flash_set('success', $message);
    }
    redirect(base_url('patient-experience.php?' . http_build_query($redirectParams)));
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
$completedSessions = array_values(array_filter(
    $recentSessions,
    static fn(array $session): bool => (string)($session['status'] ?? '') === 'completed'
));
$selectedSessionId = (int)get('session_id', '0');
$selectedReview = $selectedSessionId > 0 ? patient_experience_staff_review_context($selectedSessionId) : null;
$kioskUrl = base_url('patient-experience/kiosk/');
$setupRouteExample = base_url('patient-experience/setup/example-token');
$kioskDevices = patient_experience_kiosk_devices();
$registeredDeviceOptions = patient_experience_registered_device_options();
$testKioskSetup = patient_experience_ensure_test_kiosk_setup(auth_user_id());
$testKioskSetupUrl = (string)($testKioskSetup['setup_url'] ?? '');
$testKioskDirectFormsUrl = base_url('patient-experience/kiosk/?direct=1');
$testKioskDirectFormsQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=10&data=' . rawurlencode($testKioskDirectFormsUrl);
$directTestToken = trim((string)get('direct_test_token', ''));
$directTestPatientName = trim((string)get('direct_test_patient_name', ''));
$directTestUrl = $directTestToken !== ''
    ? base_url('patient-experience/kiosk/?direct=1&kiosk_token=' . rawurlencode($directTestToken))
    : $testKioskDirectFormsUrl;
$directTestQrUrl = $directTestUrl !== ''
    ? 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=10&data=' . rawurlencode($directTestUrl)
    : '';
$secureConsentToken = trim((string)get('secure_consent_token', ''));
$secureConsentPatientName = trim((string)get('secure_consent_patient_name', ''));
$secureConsentPhone = trim((string)get('secure_consent_phone', ''));
$secureConsentEmail = trim((string)get('secure_consent_email', ''));
$secureConsentUrl = $secureConsentToken !== ''
    ? base_url('patient-experience/kiosk/?direct=1&kiosk_token=' . rawurlencode($secureConsentToken))
    : '';
$secureConsentQrUrl = $secureConsentUrl !== ''
    ? 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=10&data=' . rawurlencode($secureConsentUrl)
    : '';
$activeTab = strtolower(trim((string)get('tab', 'patients')));
if (!in_array($activeTab, ['setup', 'patients', 'logs'], true)) {
    $activeTab = 'patients';
}
if ($selectedReview) {
    $activeTab = 'patients';
}
$auditLogs = db_all(
    "SELECT e.*, s.patient_name, s.id AS session_number, u.first_name AS user_first_name, u.last_name AS user_last_name
     FROM patient_experience_audit_events e
     LEFT JOIN patient_experience_checkin_sessions s ON s.id = e.checkin_session_id
     LEFT JOIN users u ON u.id = e.user_id
     ORDER BY e.created_at DESC, e.id DESC
     LIMIT 60"
);
$formatPatientNumber = static fn(int $id): string => 'Patient #' . str_pad((string)max(1, $id), 4, '0', STR_PAD_LEFT);
$tabUrl = static function (string $tab, array $query = []): string {
    return base_url('patient-experience.php?' . http_build_query(array_merge(['tab' => $tab], $query)));
};
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
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 lg:text-4xl">Patient intake and kiosk setup</h1>
                        <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-600">
                            Keep it simple: open the patient list, send a link, and use one kiosk QR for the iPad.
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
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">Active session</p>
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

        <div class="mb-8 rounded-[2rem] border border-slate-200 bg-white p-2 shadow-sm">
            <div class="grid gap-2 sm:grid-cols-3">
                <a href="<?= e($tabUrl('patients', $selectedReview ? ['session_id' => (int)$selectedReview['session']['id']] : [])) ?>" class="rounded-[1.5rem] px-5 py-4 text-center text-sm font-semibold transition <?= $activeTab === 'patients' ? 'bg-slate-950 text-white' : 'bg-slate-50 text-slate-700 hover:bg-slate-100' ?>">Patients</a>
                <a href="<?= e($tabUrl('setup')) ?>" class="rounded-[1.5rem] px-5 py-4 text-center text-sm font-semibold transition <?= $activeTab === 'setup' ? 'bg-slate-950 text-white' : 'bg-slate-50 text-slate-700 hover:bg-slate-100' ?>">Setup</a>
                <a href="<?= e($tabUrl('logs')) ?>" class="rounded-[1.5rem] px-5 py-4 text-center text-sm font-semibold transition <?= $activeTab === 'logs' ? 'bg-slate-950 text-white' : 'bg-slate-50 text-slate-700 hover:bg-slate-100' ?>">Logs</a>
            </div>
        </div>

        <?php if ($activeTab === 'patients'): ?>
            <section class="mb-6 grid gap-4 md:grid-cols-4">
                <?php foreach ([['Waiting', 'waiting'], ['In Progress', 'in_progress'], ['Completed', 'completed'], ['Inactive', 'cancelled_expired']] as $item): ?>
                    <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500"><?= e($item[0]) ?></p>
                        <p class="mt-3 text-3xl font-semibold text-slate-950"><?= e((string)($stats[$item[1]] ?? 0)) ?></p>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="mb-8 grid gap-6 xl:grid-cols-[0.86fr_1.14fr]">
                <div class="space-y-6">
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Send consent link</p>
                                <h2 class="mt-2 text-xl font-semibold text-slate-950">Text or email patient</h2>
                            </div>
                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">New link</span>
                        </div>
                        <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>" class="mt-5 space-y-4">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="send_secure_consent_link">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-600" for="secure-patient-name">Patient name</label>
                                    <input id="secure-patient-name" name="secure_patient_name" value="<?= e($secureConsentPatientName) ?>" class="min-h-12 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" placeholder="Maria Lopez">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-600" for="secure-lead-id">Patient # / Lead ID</label>
                                    <input id="secure-lead-id" name="lead_id" inputmode="numeric" class="min-h-12 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" placeholder="Optional">
                                </div>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-600" for="secure-phone">Text message</label>
                                    <input id="secure-phone" name="secure_phone" value="<?= e($secureConsentPhone) ?>" class="min-h-12 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" placeholder="(801) 555-0100">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-600" for="secure-email">Email</label>
                                    <input id="secure-email" name="secure_email" value="<?= e($secureConsentEmail) ?>" type="email" class="min-h-12 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" placeholder="patient@email.com">
                                </div>
                            </div>
                            <button class="min-h-12 w-full rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800" type="submit">Send link</button>
                        </form>
                        <?php if ($secureConsentUrl !== ''): ?>
                            <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Last generated link</p>
                                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                    <input value="<?= e($secureConsentUrl) ?>" readonly class="min-h-12 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-xs text-slate-700 outline-none">
                                    <button type="button" class="copy-setup-link min-h-12 rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100" data-copy-value="<?= e($secureConsentUrl) ?>">Copy</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Patient list</p>
                                <h2 class="mt-2 text-xl font-semibold text-slate-950">Open a record and start</h2>
                            </div>
                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">Latest 30</span>
                        </div>
                        <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-[0.16em] text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">Patient</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Record</th>
                                        <th class="px-4 py-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <?php if (!$recentSessions): ?>
                                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No patients yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentSessions as $session): ?>
                                            <?php
                                            $sessionId = (int)$session['id'];
                                            $signatureSummary = patient_experience_signature_summary($sessionId);
                                            $patientNumber = $formatPatientNumber($sessionId);
                                            $status = ucwords(str_replace('_', ' ', (string)($session['status'] ?? '')));
                                            ?>
                                            <tr>
                                                <td class="px-4 py-3 font-semibold text-slate-950">
                                                    <?= e($patientNumber) ?>
                                                    <div class="mt-1 text-xs font-normal text-slate-500"><?= e(trim((string)$session['patient_name']) !== '' ? (string)$session['patient_name'] : 'No name yet') ?></div>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700"><?= e($status !== '' ? $status : 'Unknown') ?></span>
                                                    <div class="mt-1 text-xs text-slate-500"><?= e((string)($session['progress_percent'] ?? 0)) ?>% complete</div>
                                                </td>
                                                <td class="px-4 py-3 text-slate-500">
                                                    <?= e(format_datetime((string)($session['created_at'] ?? ''))) ?>
                                                    <div class="mt-1 text-xs text-slate-500">
                                                        <?= ((int)$signatureSummary['total'] > 0) ? 'Signed ' . e(format_datetime((string)$signatureSummary['latest_signed_at'])) : 'No signature yet' ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <a href="<?= e(base_url('patient-experience.php?tab=patients&session_id=' . $sessionId)) ?>" class="inline-flex items-center rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Current flow</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Active patient</h2>
                    <?php if ($activeSession): ?>
                        <div class="mt-5 space-y-4">
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Patient</div>
                                <div class="mt-1 text-lg font-semibold text-slate-950"><?= e($formatPatientNumber((int)$activeSession['id'])) ?></div>
                                <div class="mt-1 text-sm text-slate-600"><?= e((string)$activeSession['patient_name']) ?></div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Step</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-950"><?= e(ucwords(str_replace('_', ' ', (string)$activeProgress['current_step']))) ?></div>
                                </div>
                                <div class="rounded-2xl bg-slate-50 p-4">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Signature</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-950"><?= ((int)$activeSignatureSummary['total'] > 0) ? 'Captured' : 'Pending' ?></div>
                                </div>
                            </div>
                            <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="cancel_session">
                                <input type="hidden" name="session_id" value="<?= e((string)$activeSession['id']) ?>">
                                <button type="submit" class="min-h-12 w-full rounded-2xl border border-red-200 bg-red-50 px-5 py-3 text-sm font-semibold text-red-700 hover:bg-red-100">Cancel active</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">
                            No active patient right now.
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php elseif ($activeTab === 'setup'): ?>
            <section class="mb-8 grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Setup</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Kiosk setup</h2>
                    <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>" class="mt-5 space-y-4">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="create_kiosk_device">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600" for="device-label">Kiosk name</label>
                            <input id="device-label" name="device_label" class="min-h-12 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" placeholder="Front Desk iPad">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600" for="location-label">Location</label>
                            <input id="location-label" name="location_label" class="min-h-12 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" placeholder="Front Desk">
                        </div>
                        <button class="min-h-12 w-full rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800" type="submit">Generate QR</button>
                    </form>
                    <?php if ($setupPreviewDeviceId > 0 && $setupPreviewToken !== ''): ?>
                        <?php $setupPreviewUrl = patient_experience_kiosk_setup_url($setupPreviewToken); ?>
                        <div class="mt-6 rounded-[2rem] border border-slate-200 bg-slate-50 p-5">
                            <div class="flex flex-col gap-4 md:flex-row md:items-center">
                                <img src="<?= e(patient_experience_kiosk_setup_qr_url($setupPreviewToken)) ?>" alt="Kiosk setup QR code" class="h-44 w-44 rounded-2xl border border-slate-200 bg-white p-2">
                                <div class="min-w-0 flex-1 space-y-3">
                                    <div class="text-sm text-slate-700">Open this QR on the iPad and add it to Home Screen.</div>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <input id="setup-link-preview" value="<?= e($setupPreviewUrl) ?>" readonly class="min-h-12 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-xs text-slate-700 outline-none">
                                        <button type="button" class="copy-setup-link min-h-12 rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100" data-copy-value="<?= e($setupPreviewUrl) ?>">Copy</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Kiosk list</p>
                    <div class="mt-5 space-y-4">
                        <?php if (!$kioskDevices): ?>
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">No kiosks saved yet.</div>
                        <?php else: ?>
                            <?php foreach ($kioskDevices as $device): ?>
                                <?php
                                $deviceId = (int)$device['id'];
                                $isRegistered = patient_experience_kiosk_device_registered($device);
                                ?>
                                <div class="rounded-[1.5rem] border border-slate-200 p-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-lg font-semibold text-slate-950"><?= e((string)$device['device_label']) ?></h3>
                                            <p class="mt-1 text-sm text-slate-500"><?= e((string)$device['location_label']) ?></p>
                                        </div>
                                        <span class="rounded-full border px-3 py-1 text-xs font-semibold <?= $isRegistered ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' ?>"><?= e($isRegistered ? 'Installed' : 'Pending') ?></span>
                                    </div>
                                    <div class="mt-4 flex flex-wrap gap-3">
                                        <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="regenerate_setup_token">
                                            <input type="hidden" name="device_id" value="<?= e((string)$deviceId) ?>">
                                            <button type="submit" class="min-h-11 rounded-2xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"><?= $isRegistered ? 'Regenerate QR' : 'Generate QR' ?></button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

        <?php else: ?>
            <section class="mb-8 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Logs</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Recent activity</h2>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">Latest 60</span>
                </div>
                <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Time</th>
                                <th class="px-4 py-3">Patient</th>
                                <th class="px-4 py-3">Event</th>
                                <th class="px-4 py-3">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php if (!$auditLogs): ?>
                                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No logs yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($auditLogs as $entry): ?>
                                    <?php $sessionNumber = (int)($entry['session_number'] ?? 0); ?>
                                    <tr>
                                        <td class="px-4 py-3 text-slate-500"><?= e(format_datetime((string)$entry['created_at'])) ?></td>
                                        <td class="px-4 py-3 font-semibold text-slate-950">
                                            <?= e($sessionNumber > 0 ? $formatPatientNumber($sessionNumber) : 'System') ?>
                                            <div class="mt-1 text-xs font-normal text-slate-500"><?= e(trim((string)($entry['patient_name'] ?? '')) !== '' ? (string)$entry['patient_name'] : 'No patient') ?></div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700"><?= e((string)($entry['event_label'] ?: $entry['event_key'])) ?></span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-500">
                                            <?php
                                            $payload = trim((string)($entry['payload_json'] ?? ''));
                                            echo $payload !== '' ? e($payload) : '—';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

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
                    <div class="space-y-2 text-sm">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">
                            <?= trim((string)($review['session']['reviewed_at'] ?? '')) !== '' ? 'Reviewed ' . e(format_datetime((string)$review['session']['reviewed_at'])) : 'Not reviewed yet' ?>
                        </div>
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900">
                            <?php if (!empty($review['signed_packet'])): ?>
                                <div class="font-semibold">Signed packet saved in database</div>
                                <div class="mt-1 text-xs text-emerald-800">
                                    <?= e((string)($review['signed_packet']['packet_title'] ?? 'Patient Packet')) ?> ·
                                    <?= e((string)($review['signed_packet']['signature_count'] ?? 0)) ?> signatures ·
                                    <?= e(format_datetime((string)($review['signed_packet']['signed_at'] ?? ''))) ?>
                                </div>
                            <?php else: ?>
                                <div class="font-semibold">Signed packet not saved yet</div>
                                <div class="mt-1 text-xs text-emerald-800">It will appear here after the patient finishes the consent flow.</div>
                            <?php endif; ?>
                        </div>
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
