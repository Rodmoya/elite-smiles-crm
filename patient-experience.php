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

$currentPage = strtolower(trim((string)get('tab', 'patients'))) === 'contracts' ? 'patient_contracts' : 'patient_experience';
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

if (is_post() && post('action') === 'save_contract') {
    require_csrf();
    $result = patient_experience_contract_save($_POST, auth_user_id());
    if (!empty($result['ok'])) {
        $contractId = (int)($result['contract_id'] ?? 0);
        flash_set('success', 'Contract draft saved. Review the document before sending it for signature.');
        redirect(base_url('patient-experience.php?tab=contracts&contract_id=' . $contractId));
    }
    $errors = (array)($result['errors'] ?? []);
    flash_set('error', $errors ? implode(' ', array_values($errors)) : 'Could not save the contract draft.');
    redirect(base_url('patient-experience.php?tab=contracts'));
}

if (is_post() && post('action') === 'send_contract') {
    require_csrf();
    $contractId = (int)post('contract_id', '0');
    $channels = array_values(array_intersect((array)($_POST['channels'] ?? []), ['sms', 'email']));
    $result = patient_experience_contract_prepare_delivery($contractId, $channels, auth_user_id());
    if (!empty($result['ok'])) {
        flash_set('contract_share_url', (string)($result['url'] ?? ''));
        $sent = (array)($result['sent'] ?? []);
        $issues = (array)($result['issues'] ?? []);
        $message = $sent ? ('Secure contract link sent by ' . implode(' and ', $sent) . '.') : 'Secure contract link created.';
        if ($issues) $message .= ' ' . implode(' ', $issues);
        flash_set('success', $message);
    } else {
        flash_set('error', (string)($result['message'] ?? 'Could not create the signing link.'));
    }
    redirect(base_url('patient-experience.php?tab=contracts&contract_id=' . $contractId));
}

$recentSessions = patient_experience_recent_sessions(100);
$selectedSessionId = (int)get('session_id', '0');
$selectedReview = $selectedSessionId > 0 ? patient_experience_staff_review_context($selectedSessionId) : null;
$kioskUrl = base_url('patient-experience/kiosk/');
$secureConsentToken = trim((string)get('secure_consent_token', ''));
$secureConsentPatientName = trim((string)get('secure_consent_patient_name', ''));
$secureConsentPhone = trim((string)get('secure_consent_phone', ''));
$secureConsentEmail = trim((string)get('secure_consent_email', ''));
$secureConsentUrl = $secureConsentToken !== ''
    ? base_url('patient-experience/kiosk/?direct=1&auto_begin=1&kiosk_token=' . rawurlencode($secureConsentToken))
    : '';
$secureConsentQrUrl = $secureConsentUrl !== '' ? patient_experience_contract_qr_data_url($secureConsentUrl) : '';
$walkInIntakeUrl = base_url('patient-experience/kiosk/?direct=1&auto_begin=1&walk_in=1');
$walkInIntakeQrUrl = patient_experience_contract_qr_data_url($walkInIntakeUrl);
$contractDefinitions = patient_experience_contract_definitions();
$contractPatients = patient_experience_contract_patient_options();
$contracts = patient_experience_contract_list(100);
$selectedContractId = (int)get('contract_id', '0');
$selectedContract = $selectedContractId > 0 ? patient_experience_contract_by_id($selectedContractId) : null;
$contractShareUrl = (string)(flash_get('contract_share_url') ?? '');
$activeTab = strtolower(trim((string)get('tab', 'patients')));
if (!in_array($activeTab, ['setup', 'patients', 'contracts'], true)) {
    $activeTab = 'patients';
}

if (is_post() && post('action') === 'continue_intake') {
    require_csrf();
    $sessionId = (int)post('session_id', '0');
    $resume = $sessionId > 0 ? patient_experience_resume_session($sessionId, auth_user_id()) : ['ok' => false];
    if (empty($resume['ok'])) {
        flash_set('error', (string)($resume['message'] ?? 'Could not open those patient forms.'));
        redirect(base_url('patient-experience.php?tab=patients'));
    }
    redirect(base_url('patient-experience/kiosk/?direct=1&auto_begin=1&kiosk_token=' . rawurlencode((string)$resume['token'])));
}
if ($selectedReview) {
    $activeTab = 'patients';
}
$formatPatientNumber = static fn(int $id): string => 'Patient #' . str_pad((string)max(1, $id), 4, '0', STR_PAD_LEFT);
$tabUrl = static function (string $tab, array $query = []): string {
    return base_url('patient-experience.php?' . http_build_query(array_merge(['tab' => $tab], $query)));
};
$pageHeading = match ($activeTab) {
    'contracts' => ['Contract creator', 'Create, send, print, and track treatment agreements.'],
    'setup' => ['Kiosk setup', 'Connect and manage secure patient check-in devices.'],
    default => ['Intake and consent forms', 'Review patient records, signed forms, and consent history.'],
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
    <style>
        @media print {
            @page { margin: 0.5in; }
            body { background: #fff !important; }
            body * { visibility: hidden !important; }
            #consent-review, #consent-review * { visibility: visible !important; }
            #consent-review { position: absolute; inset: 0; width: 100%; margin: 0; border: 0; box-shadow: none; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <?php require __DIR__ . '/app/partials/crm_sidebar_live.php'; ?>

    <main class="px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
        <?php if ($errorMessage !== ''): ?>
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= e($errorMessage) ?></div>
        <?php endif; ?>
        <?php if ($successMessage !== ''): ?>
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($successMessage) ?></div>
        <?php endif; ?>

        <section class="mb-6 no-print">
            <div class="flex flex-col gap-5 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between lg:p-8">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Patient Experience</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900"><?= e($pageHeading[0]) ?></h1>
                    <p class="mt-3 text-sm leading-6 text-slate-600"><?= e($pageHeading[1]) ?></p>
                </div>
                <?php if ($activeTab === 'patients'): ?>
                    <button id="open-intake-modal" type="button" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">New Patient Forms</button>
                <?php endif; ?>
            </div>
        </section>

        <div class="mb-6 max-w-3xl rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm no-print">
            <div class="grid grid-cols-3 gap-1.5">
                <a href="<?= e($tabUrl('patients')) ?>" class="rounded-xl px-4 py-3 text-center text-sm font-semibold transition <?= $activeTab === 'patients' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">Intake & Patients</a>
                <a href="<?= e($tabUrl('contracts')) ?>" class="rounded-xl px-4 py-3 text-center text-sm font-semibold transition <?= $activeTab === 'contracts' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">Contracts</a>
                <a href="<?= e($tabUrl('setup')) ?>" class="rounded-xl px-4 py-3 text-center text-sm font-semibold transition <?= $activeTab === 'setup' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">Kiosk Setup</a>
            </div>
        </div>

        <?php if ($activeTab === 'patients'): ?>
            <section class="mb-8 no-print">
                <div id="intake-modal" class="fixed inset-0 z-[90] <?= $secureConsentUrl !== '' ? '' : 'hidden' ?> items-center justify-center overflow-y-auto p-4 sm:p-6" aria-hidden="<?= $secureConsentUrl !== '' ? 'false' : 'true' ?>">
                    <button type="button" data-intake-modal-close class="absolute inset-0 h-full w-full bg-slate-950/60 backdrop-blur-sm" aria-label="Close send intake modal"></button>
                    <div role="dialog" aria-modal="true" aria-labelledby="intake-modal-title" class="relative mx-auto my-8 w-full max-w-xl rounded-[2rem] border border-slate-200 bg-white p-6 shadow-2xl sm:p-7">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Patient intake</p>
                                <h2 id="intake-modal-title" class="mt-2 text-2xl font-semibold text-slate-900">Open patient forms</h2>
                                <p class="mt-2 text-sm text-slate-600">Create one secure form session, then send its link or let the patient scan its QR code.</p>
                            </div>
                            <button type="button" data-intake-modal-close class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-300 bg-white text-xl text-slate-600 hover:bg-slate-100" aria-label="Close">×</button>
                        </div>
                        <div class="mt-5 grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-[136px_1fr] sm:items-center">
                            <?php if ($walkInIntakeQrUrl !== ''): ?>
                                <img src="<?= e($walkInIntakeQrUrl) ?>" alt="Walk-in patient intake QR code" class="mx-auto h-32 w-32 rounded-xl border border-slate-200 bg-white p-2">
                            <?php endif; ?>
                            <div>
                                <p class="font-semibold text-slate-950">Walk-in patient</p>
                                <p class="mt-1 text-sm leading-6 text-slate-600">Scan this permanent QR on the iPad or phone. The patient enters their information first, then completes and signs every form.</p>
                                <a href="<?= e($walkInIntakeUrl) ?>" target="_blank" class="mt-3 inline-flex min-h-10 items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Open Walk-in Forms</a>
                            </div>
                        </div>
                        <div class="my-5 flex items-center gap-3"><div class="h-px flex-1 bg-slate-200"></div><span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Send to a known patient</span><div class="h-px flex-1 bg-slate-200"></div></div>
                    <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>" class="space-y-4">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="send_secure_consent_link">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-700" for="secure-patient-name">Patient name</label>
                                <input id="secure-patient-name" name="secure_patient_name" value="<?= e($secureConsentPatientName) ?>" required class="min-h-12 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" placeholder="Maria Lopez">
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-slate-700" for="secure-phone">Mobile phone</label>
                                    <input id="secure-phone" name="secure_phone" value="<?= e($secureConsentPhone) ?>" class="min-h-12 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" placeholder="(801) 555-0100">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-slate-700" for="secure-email">Email</label>
                                    <input id="secure-email" name="secure_email" value="<?= e($secureConsentEmail) ?>" type="email" class="min-h-12 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-slate-500" placeholder="patient@email.com">
                                </div>
                            </div>
                            <button class="min-h-12 w-full rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800" type="submit">Create Patient Forms</button>
                    </form>
                        <?php if ($secureConsentUrl !== ''): ?>
                            <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                                <p class="text-sm font-semibold text-emerald-900">Link ready for <?= e($secureConsentPatientName !== '' ? $secureConsentPatientName : 'patient') ?></p>
                                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                    <button type="button" class="copy-setup-link min-h-11 flex-1 rounded-xl border border-emerald-300 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100" data-copy-value="<?= e($secureConsentUrl) ?>">Copy link</button>
                                    <a href="<?= e($secureConsentUrl) ?>" target="_blank" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Open form</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Patient records</p>
                            <h2 class="mt-2 text-xl font-semibold text-slate-900">Patient forms</h2>
                            <p class="mt-1 text-sm text-slate-500">Open, continue, review, or print each patient's saved forms.</p>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <label class="relative block min-w-0 sm:w-80">
                                <span class="sr-only">Search patients</span>
                                <input id="patient-form-search" type="search" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 pr-10 text-sm outline-none focus:border-slate-500" placeholder="Search patient or record number">
                                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">⌕</span>
                            </label>
                            <span class="self-start rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 sm:self-auto"><?= e((string)count($recentSessions)) ?> records</span>
                        </div>
                    </div>

                    <div id="patient-form-list" class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                        <?php if (!$recentSessions): ?>
                            <div class="bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">No patient intake records yet.</div>
                        <?php else: ?>
                            <?php foreach ($recentSessions as $session): ?>
                                <?php
                                $sessionId = (int)($session['id'] ?? 0);
                                $patientName = trim((string)($session['patient_name'] ?? ''));
                                $patientName = $patientName !== '' ? $patientName : 'Unnamed patient';
                                $status = (string)($session['status'] ?? 'waiting');
                                $statusLabel = $status === 'completed' ? 'Complete' : ($status === 'in_progress' ? 'In progress' : ($status === 'waiting' ? 'Link sent' : ucwords(str_replace('_', ' ', $status))));
                                $statusTone = $status === 'completed' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($status === 'in_progress' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-slate-200 bg-slate-50 text-slate-600');
                                $signedPacket = patient_experience_signed_packet_for_session($sessionId);
                                $signatureCount = (int)($signedPacket['signature_count'] ?? 0);
                                $isSigned = $signedPacket && $signatureCount > 0;
                                $progressPercent = max(0, min(100, (int)($session['progress_percent'] ?? 0)));
                                $searchValue = strtolower($patientName . ' ' . $formatPatientNumber($sessionId) . ' ' . $statusLabel);
                                ?>
                                <article data-patient-form-row data-search-value="<?= e($searchValue) ?>" class="border-b border-slate-200 bg-white p-4 last:border-b-0 hover:bg-slate-50 sm:p-5">
                                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                                        <div class="min-w-0 xl:w-1/3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-slate-900"><?= e($patientName) ?></p>
                                            <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold <?= e($statusTone) ?>"><?= e($statusLabel) ?></span>
                                        </div>
                                            <p class="mt-1 text-sm text-slate-500"><?= e($formatPatientNumber($sessionId)) ?> · Updated <?= e(format_datetime((string)($session['updated_at'] ?? $session['created_at'] ?? ''))) ?></p>
                                        </div>
                                        <div class="min-w-0 flex-1 xl:max-w-sm">
                                            <div class="flex items-center justify-between gap-3 text-xs font-semibold text-slate-500">
                                                <span><?= $isSigned ? e($signatureCount . ' forms signed') : 'Forms in progress' ?></span>
                                                <span><?= e((string)$progressPercent) ?>%</span>
                                            </div>
                                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full <?= $status === 'completed' ? 'bg-emerald-500' : 'bg-slate-900' ?>" style="width: <?= e((string)$progressPercent) ?>%"></div></div>
                                        </div>
                                        <div class="flex shrink-0 flex-wrap gap-2 xl:justify-end">
                                            <?php if (!$reviewIsComplete && $status !== 'completed'): ?>
                                                <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>" target="_blank">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="action" value="continue_intake">
                                                    <input type="hidden" name="session_id" value="<?= e((string)$sessionId) ?>">
                                                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Open Forms</button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="<?= e($tabUrl('patients', ['session_id' => $sessionId])) ?>#consent-review" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">View Chart</a>
                                        <?php if ($isSigned): ?>
                                                <a href="<?= e($tabUrl('patients', ['session_id' => $sessionId, 'print' => 1])) ?>#consent-review" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Print</a>
                                        <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                            <div id="patient-form-empty-search" class="hidden bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">No patient forms match that search.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php elseif ($activeTab === 'contracts'): ?>
            <?php require __DIR__ . '/app/patient_experience/contract_creator.php'; ?>
        <?php elseif ($activeTab === 'setup'): ?>
            <section class="mb-8 max-w-4xl rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm no-print">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Kiosk Setup</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">Connect the waiting-room iPad</h2>
                        <p class="mt-2 text-sm text-slate-600">Create one setup QR, scan it on the iPad, and add the kiosk to the Home Screen.</p>
                    </div>
                    <a href="<?= e($kioskUrl) ?>" target="_blank" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open kiosk</a>
                </div>

                <div class="mt-6 grid gap-6 <?= ($setupPreviewDeviceId > 0 && $setupPreviewToken !== '') ? 'lg:grid-cols-[0.8fr_1.2fr]' : 'max-w-xl' ?>">
                    <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="create_kiosk_device">
                        <input type="hidden" name="location_label" value="Waiting Room">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700" for="device-label">iPad name</label>
                            <input id="device-label" name="device_label" required class="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-slate-500" value="Waiting Room iPad">
                        </div>
                        <button class="mt-4 min-h-12 w-full rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800" type="submit">Generate setup QR</button>
                    </form>
                    <?php if ($setupPreviewDeviceId > 0 && $setupPreviewToken !== ''): ?>
                        <?php $setupPreviewUrl = patient_experience_kiosk_setup_url($setupPreviewToken); ?>
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                            <div class="flex flex-col gap-4 md:flex-row md:items-center">
                                <img src="<?= e(patient_experience_kiosk_setup_qr_url($setupPreviewToken)) ?>" alt="Kiosk setup QR code" class="h-44 w-44 rounded-2xl border border-slate-200 bg-white p-2">
                                <div class="min-w-0 flex-1 space-y-3">
                                    <p class="font-semibold text-emerald-900">QR ready</p>
                                    <p class="text-sm text-emerald-800">Scan this once with the iPad camera.</p>
                                    <button type="button" class="copy-setup-link min-h-11 w-full rounded-xl border border-emerald-300 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100" data-copy-value="<?= e($setupPreviewUrl) ?>">Copy setup link</button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($selectedReview): ?>
            <?php
            $review = $selectedReview;
            $signatureRecords = (array)($review['signed_packet']['snapshot']['signatures'] ?? []);
            $hasSignedPacket = !empty($review['signed_packet']);
            $reviewIsComplete = (string)($review['session']['status'] ?? '') === 'completed';
            $reviewDate = $reviewIsComplete
                ? (string)($review['session']['completed_at'] ?? '')
                : (string)($review['session']['created_at'] ?? '');
            ?>
            <section id="consent-review" class="mb-8 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500"><?= $hasSignedPacket ? 'Signed consent record' : 'Patient consent record' ?></p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-950"><?= e($formatPatientNumber((int)$review['session']['id'])) ?> · <?= e((string)($review['session']['patient_name'] ?: 'Patient')) ?></h2>
                        <p class="mt-2 text-sm text-slate-600">
                            <?= $reviewIsComplete ? 'Completed' : 'Created' ?> <?= e(format_datetime($reviewDate)) ?>
                        </p>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex gap-2 no-print">
                            <a href="<?= e($tabUrl('patients')) ?>" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 font-semibold text-slate-700 hover:bg-slate-100">Close</a>
                            <?php if (!$reviewIsComplete): ?>
                                <form method="POST" action="<?= e(base_url('patient-experience.php')) ?>" target="_blank">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="continue_intake">
                                    <input type="hidden" name="session_id" value="<?= e((string)$review['session']['id']) ?>">
                                    <button type="submit" class="min-h-11 rounded-xl bg-slate-900 px-4 py-2.5 font-semibold text-white hover:bg-slate-800">Open Forms</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($hasSignedPacket): ?>
                                <button type="button" onclick="window.print()" class="min-h-11 rounded-xl bg-slate-900 px-4 py-2.5 font-semibold text-white hover:bg-slate-800">Print consent</button>
                            <?php endif; ?>
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
                            <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Patient Signatures</h3>
                            <?php if (!$signatureRecords): ?>
                                <p class="mt-3 text-sm text-slate-500">No signature images are available for this record.</p>
                            <?php endif; ?>
                            <?php foreach ($signatureRecords as $signature): ?>
                                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex flex-wrap justify-between gap-2 text-sm text-slate-700">
                                        <span class="font-semibold"><?= e((string)($signature['section_title'] ?? 'Patient consent')) ?></span>
                                        <span><?= e(format_datetime((string)($signature['signed_at'] ?? ''))) ?></span>
                                    </div>
                                    <?php if (strpos((string)($signature['image_data_url'] ?? ''), 'data:image/') === 0): ?>
                                        <div class="mt-3 rounded-xl border border-slate-200 bg-white p-4">
                                            <img src="<?= e((string)$signature['image_data_url']) ?>" alt="<?= e((string)($signature['section_title'] ?? 'Patient')) ?> signature" class="mx-auto max-h-32 max-w-full">
                                        </div>
                                    <?php endif; ?>
                                    <p class="mt-3 text-xs text-slate-500">Signed by <?= e((string)($signature['signer_name'] ?? 'Patient')) ?></p>
                                </div>
                            <?php endforeach; ?>
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
        const intakeModal = document.getElementById('intake-modal');
        const openIntakeModal = document.getElementById('open-intake-modal');
        const setIntakeModalOpen = function (isOpen) {
            if (!intakeModal) {
                return;
            }
            intakeModal.classList.toggle('hidden', !isOpen);
            intakeModal.classList.toggle('flex', isOpen);
            intakeModal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            document.body.classList.toggle('overflow-hidden', isOpen);
            if (isOpen) {
                window.setTimeout(function () {
                    document.getElementById('secure-patient-name')?.focus();
                }, 0);
            }
        };
        openIntakeModal?.addEventListener('click', function () {
            setIntakeModalOpen(true);
        });
        document.querySelectorAll('[data-intake-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                setIntakeModalOpen(false);
            });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && intakeModal && !intakeModal.classList.contains('hidden')) {
                setIntakeModalOpen(false);
            }
        });
        if (intakeModal && !intakeModal.classList.contains('hidden')) {
            intakeModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }
        const patientFormSearch = document.getElementById('patient-form-search');
        const patientFormRows = Array.from(document.querySelectorAll('[data-patient-form-row]'));
        const patientFormEmptySearch = document.getElementById('patient-form-empty-search');
        patientFormSearch?.addEventListener('input', function () {
            const query = String(patientFormSearch.value || '').trim().toLowerCase();
            let visibleCount = 0;
            patientFormRows.forEach(function (row) {
                const matches = query === '' || String(row.getAttribute('data-search-value') || '').includes(query);
                row.classList.toggle('hidden', !matches);
                if (matches) visibleCount += 1;
            });
            patientFormEmptySearch?.classList.toggle('hidden', visibleCount !== 0);
        });
        document.querySelectorAll('input[name="test_patient_name"]').forEach(function (input) {
            input.closest('form')?.remove();
        });
        <?php if ($selectedReview && !empty($selectedReview['signed_packet']) && get('print') === '1'): ?>
        window.addEventListener('load', function () {
            window.print();
        });
        <?php endif; ?>
    </script>
</body>
</html>
