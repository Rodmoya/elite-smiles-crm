<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/helpers.php';

$pollUrl = parse_url(base_url('app/api/patient_experience_kiosk.php'), PHP_URL_PATH) ?: '/crm/app/api/patient_experience_kiosk.php';
$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$manifestUrl = base_url('patient-experience/manifest.webmanifest');
$serviceWorkerUrl = base_url('patient-experience/sw.js');
$setupHintUrl = base_url('patient-experience.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Elite Smiles | Check-In Kiosk</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#050505">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Elite Smiles Check-In">
    <link rel="manifest" href="<?= e($manifestUrl) ?>">
    <link rel="apple-touch-icon" href="<?= e($logoUrl) ?>">
    <style>
        :root { color-scheme: light; }
        .kiosk-input { min-height: 54px; width: 100%; border-radius: 18px; border: 1px solid rgb(203 213 225); padding: 12px 16px; font-size: 18px; outline: none; }
        .kiosk-input:focus { border-color: rgb(217 119 6); box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.18); }
        .kiosk-label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: rgb(71 85 105); }
        .kiosk-option { min-height: 54px; border-radius: 18px; border: 1px solid rgb(203 213 225); background: white; padding: 14px 16px; font-size: 17px; font-weight: 700; color: rgb(15 23 42); }
        .signature-canvas { touch-action: none; width: 100%; height: 48vh; min-height: 340px; border-radius: 28px; background: #fff; }
        .field-wrapper.hidden { display: none; }
    </style>
</head>
<body class="min-h-screen bg-[#060606] text-white antialiased">
    <main class="flex min-h-screen items-center justify-center px-6 py-8">
        <section class="w-full max-w-6xl overflow-hidden rounded-[3rem] border border-amber-200/25 bg-white shadow-2xl">
            <div class="grid min-h-[760px] lg:grid-cols-[0.82fr_1.18fr]">
                <aside class="flex flex-col justify-between bg-[#0d0d0d] p-8 lg:p-12">
                    <div>
                        <img src="<?= e($logoUrl) ?>" alt="Elite Smiles" class="w-64 max-w-full rounded-2xl bg-white p-4">
                        <p class="mt-8 text-xs font-semibold uppercase tracking-[0.32em] text-amber-300">Private Check-In</p>
                        <div class="mt-6 h-2 overflow-hidden rounded-full bg-white/10">
                            <div id="progress-bar" class="h-full rounded-full bg-amber-300 transition-all duration-300" style="width: 0%"></div>
                        </div>
                        <p id="progress-label" class="mt-3 text-sm text-slate-300">Waiting for front desk</p>
                    </div>
                    <div class="rounded-[2rem] border border-white/10 bg-white/5 p-6 text-sm leading-7 text-slate-200">
                        This kiosk is for secure check-in only. It does not show patient lists, CRM navigation, or private records while idle.
                    </div>
                </aside>

                <section id="kiosk-app" class="bg-gradient-to-br from-white via-stone-50 to-amber-50 p-8 text-slate-950 lg:p-12">
                    <div id="kiosk-state" class="flex min-h-[680px] flex-col items-center justify-center text-center">
                        <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-amber-100 text-3xl font-semibold text-amber-800">ES</div>
                        <h1 class="mt-8 text-4xl font-semibold tracking-tight lg:text-6xl">Welcome to Elite Smiles.</h1>
                        <p class="mt-6 max-w-2xl text-xl leading-9 text-slate-600">Please see our front desk to begin your check-in.</p>
                        <div class="mt-10 rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-500 shadow-sm">Waiting for front desk</div>
                    </div>
                </section>
            </div>
        </section>
    </main>

    <div id="signature-modal" class="fixed inset-0 z-[100] hidden bg-slate-950/80 p-4 backdrop-blur-sm">
        <div class="mx-auto flex h-full max-w-6xl flex-col rounded-[2rem] bg-stone-50 p-5 shadow-2xl">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-700">Signature Required</p>
                    <h2 id="signature-title" class="mt-1 text-2xl font-semibold text-slate-950">Please sign below</h2>
                </div>
                <button type="button" id="signature-cancel-top" class="min-h-12 rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700">Cancel</button>
            </div>
            <div class="flex flex-1 flex-col py-5">
                <canvas id="signature-canvas" class="signature-canvas border-2 border-slate-300 shadow-inner"></canvas>
                <p class="mt-3 text-sm text-slate-500">Use your finger or stylus. Your signature will be saved securely with this check-in session.</p>
            </div>
            <div class="grid gap-3 border-t border-slate-200 pt-4 sm:grid-cols-3">
                <button type="button" id="signature-clear" class="min-h-14 rounded-2xl border border-slate-300 bg-white px-6 py-4 text-lg font-semibold text-slate-700">Clear</button>
                <button type="button" id="signature-cancel" class="min-h-14 rounded-2xl border border-red-200 bg-red-50 px-6 py-4 text-lg font-semibold text-red-700">Cancel</button>
                <button type="button" id="signature-confirm" class="min-h-14 rounded-2xl bg-slate-950 px-6 py-4 text-lg font-semibold text-white">Confirm Signature</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const endpoint = '<?= e($pollUrl) ?>';
            const serviceWorkerUrl = '<?= e($serviceWorkerUrl) ?>';
            const staffSetupUrl = '<?= e($setupHintUrl) ?>';
            const app = document.getElementById('kiosk-state');
            const progressBar = document.getElementById('progress-bar');
            const progressLabel = document.getElementById('progress-label');
            const deviceTokenStorageKey = 'patient_experience_device_token';
            const autoBeginForms = (new URLSearchParams(window.location.search).get('auto_begin') || '') === '1';
            let kioskToken = window.sessionStorage.getItem('patient_experience_kiosk_token') || '';
            let currentSessionId = Number(window.sessionStorage.getItem('patient_experience_session_id') || 0);
            let deviceToken = '';
            let currentDevice = null;
            let polling = true;
            let autoBeginTriggered = false;

            function readCookie(name) {
                const prefix = name + '=';
                const parts = document.cookie ? document.cookie.split('; ') : [];
                for (let index = 0; index < parts.length; index += 1) {
                    if (parts[index].indexOf(prefix) === 0) {
                        return decodeURIComponent(parts[index].substring(prefix.length));
                    }
                }
                return '';
            }

            function setDeviceToken(token) {
                deviceToken = String(token || '');
                try {
                    if (deviceToken) {
                        window.localStorage.setItem(deviceTokenStorageKey, deviceToken);
                    } else {
                        window.localStorage.removeItem(deviceTokenStorageKey);
                    }
                } catch (error) {}
                if (deviceToken) {
                    document.cookie = 'patient_experience_device_token=' + encodeURIComponent(deviceToken) + '; path=/; SameSite=Lax';
                } else {
                    document.cookie = 'patient_experience_device_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax';
                }
            }

            try {
                deviceToken = window.localStorage.getItem(deviceTokenStorageKey) || '';
            } catch (error) {
                deviceToken = '';
            }
            if (!deviceToken) {
                deviceToken = readCookie('patient_experience_device_token') || '';
            }
            if (deviceToken) {
                setDeviceToken(deviceToken);
            }

            function escapeHtml(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function setProgress(percent, label) {
                progressBar.style.width = String(Math.max(0, Math.min(100, Number(percent || 0)))) + '%';
                progressLabel.textContent = label || 'Waiting for front desk';
            }

            function button(label, className) {
                return '<button type="button" class="' + className + '">' + label + '</button>';
            }

            function answerValue(answers, key) {
                if (!answers || !key || !Object.prototype.hasOwnProperty.call(answers, key)) {
                    return null;
                }
                const value = answers[key];
                return value && typeof value === 'object' && Object.prototype.hasOwnProperty.call(value, 'value') ? value.value : value;
            }

            function answersStateMap(answers) {
                const mapped = {};
                Object.keys(answers || {}).forEach(function (key) {
                    mapped[key] = answerValue(answers, key);
                });
                return mapped;
            }

            function conditionMatches(condition, answers) {
                if (!condition || typeof condition !== 'object') {
                    return true;
                }
                if (Array.isArray(condition.all)) {
                    return condition.all.every(function (child) { return conditionMatches(child, answers); });
                }
                if (Array.isArray(condition.any)) {
                    return condition.any.some(function (child) { return conditionMatches(child, answers); });
                }
                const field = String(condition.field || '');
                const actual = answers[field];
                const expected = condition.value;
                const operator = String(condition.operator || 'equals');
                if (operator === 'not_equals') return actual !== expected;
                if (operator === 'contains') return Array.isArray(actual) ? actual.includes(expected) : String(actual || '').toLowerCase().includes(String(expected || '').toLowerCase());
                if (operator === 'in') return Array.isArray(expected) && expected.includes(actual);
                if (operator === 'empty') return actual === null || actual === undefined || actual === '' || (Array.isArray(actual) && actual.length === 0);
                if (operator === 'not_empty') return !(actual === null || actual === undefined || actual === '' || (Array.isArray(actual) && actual.length === 0));
                return actual === expected;
            }

            function fieldVisible(field, answers) {
                return !field.visible_if || conditionMatches(field.visible_if, answers);
            }

            function fieldChildren(field) {
                if (field.type === 'emergency_contact') {
                    return [
                        { key: field.key + '_name', type: 'text', label: field.label + ' Name', required: field.required },
                        { key: field.key + '_relationship', type: 'text', label: field.label + ' Relationship', required: field.required },
                        { key: field.key + '_phone', type: 'phone', label: field.label + ' Phone', required: field.required }
                    ];
                }
                if (field.type === 'insurance') {
                    return [
                        { key: field.key + '_provider', type: 'text', label: 'Insurance Provider', required: field.required },
                        { key: field.key + '_subscriber_name', type: 'text', label: 'Subscriber Name', required: field.required },
                        { key: field.key + '_member_id', type: 'text', label: 'Member ID', required: field.required },
                        { key: field.key + '_group_number', type: 'text', label: 'Group Number' },
                        { key: field.key + '_subscriber_dob', type: 'dob', label: 'Subscriber DOB' }
                    ];
                }
                return [];
            }

            function renderIdle() {
                kioskToken = '';
                currentSessionId = 0;
                autoBeginTriggered = false;
                window.sessionStorage.removeItem('patient_experience_kiosk_token');
                window.sessionStorage.removeItem('patient_experience_session_id');
                setProgress(0, 'Waiting for front desk');
                app.className = 'flex min-h-[680px] flex-col items-center justify-center text-center';
                const deviceLabel = currentDevice && currentDevice.label ? escapeHtml(currentDevice.label) : 'This iPad';
                const locationLabel = currentDevice && currentDevice.location_label ? '<p class="mt-2 text-sm uppercase tracking-[0.2em] text-slate-400">' + escapeHtml(currentDevice.location_label) + '</p>' : '';
                app.innerHTML = '<div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-amber-100 text-3xl font-semibold text-amber-800">ES</div>'
                    + '<h1 class="mt-8 text-4xl font-semibold tracking-tight lg:text-6xl">Welcome to Elite Smiles.</h1>'
                    + '<p class="mt-6 max-w-2xl text-xl leading-9 text-slate-600">Please see our front desk to begin your check-in.</p>'
                    + locationLabel
                    + '<div class="mt-4 rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">' + deviceLabel + ' installed</div>'
                    + '<div class="mt-10 rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-500 shadow-sm">Waiting for front desk</div>';
            }

            function renderSetupRequired(message) {
                kioskToken = '';
                currentSessionId = 0;
                currentDevice = null;
                autoBeginTriggered = false;
                window.sessionStorage.removeItem('patient_experience_kiosk_token');
                window.sessionStorage.removeItem('patient_experience_session_id');
                setProgress(0, 'Setup needed');
                app.className = 'flex min-h-[680px] flex-col items-center justify-center text-center';
                app.innerHTML = '<div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-amber-100 text-3xl font-semibold text-amber-800">ES</div>'
                    + '<p class="mt-8 text-xs font-semibold uppercase tracking-[0.28em] text-amber-700">Setup Required</p>'
                    + '<h1 class="mt-4 text-4xl font-semibold tracking-tight lg:text-6xl">Register this iPad first.</h1>'
                    + '<p class="mt-6 max-w-2xl text-xl leading-9 text-slate-600">' + escapeHtml(message || 'This kiosk is not linked to Elite Smiles yet.') + '</p>'
                    + '<div class="mt-8 rounded-[2rem] border border-slate-200 bg-white px-6 py-5 text-left text-sm leading-7 text-slate-600 shadow-sm">'
                    + '<p class="font-semibold text-slate-900">Staff instructions</p>'
                    + '<p class="mt-2">1. In the CRM, open Patient Experience.</p>'
                    + '<p>2. Name the kiosk and generate the QR code.</p>'
                    + '<p>3. Open this QR code on the iPad, then tap Share -> Add to Home Screen.</p>'
                    + '</div>'
                    + '<div class="mt-6 rounded-full border border-slate-200 bg-slate-50 px-5 py-3 text-sm font-semibold text-slate-500 shadow-sm">' + escapeHtml(staffSetupUrl) + '</div>';
            }

            function renderWelcome(session) {
                kioskToken = session.kiosk_token || kioskToken;
                currentSessionId = Number(session.id || currentSessionId || 0);
                window.sessionStorage.setItem('patient_experience_kiosk_token', kioskToken);
                window.sessionStorage.setItem('patient_experience_session_id', String(currentSessionId));
                setProgress(session.percent_complete || 0, 'Ready for check-in');
                app.className = 'flex min-h-[680px] flex-col items-center justify-center text-center';
                app.innerHTML = '<p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-700">Secure session ready</p>'
                    + '<h1 class="mt-5 text-4xl font-semibold tracking-tight lg:text-6xl">Hi ' + escapeHtml(session.display_name || 'there') + '.</h1>'
                    + '<p class="mt-6 max-w-2xl text-xl leading-9 text-slate-600">Tap Begin when you are ready. Your answers save as you move through each step.</p>'
                    + '<div class="mt-10 flex flex-wrap justify-center gap-4">'
                    + button('Begin Check-In', 'begin-checkin min-h-14 rounded-2xl bg-slate-950 px-8 py-4 text-lg font-semibold text-white shadow-lg shadow-slate-950/20')
                    + button('Cancel', 'cancel-checkin min-h-14 rounded-2xl border border-slate-300 bg-white px-8 py-4 text-lg font-semibold text-slate-700')
                    + '</div>';
                app.querySelector('.begin-checkin').addEventListener('click', beginSession);
                app.querySelector('.cancel-checkin').addEventListener('click', cancelSession);
                if (autoBeginForms && !autoBeginTriggered) {
                    autoBeginTriggered = true;
                    window.setTimeout(function () {
                        if (kioskToken && currentSessionId === Number(session.id || currentSessionId || 0)) {
                            beginSession();
                        }
                    }, 250);
                }
            }

            function renderComplete() {
                setProgress(100, 'Completed');
                app.className = 'flex min-h-[680px] flex-col items-center justify-center text-center';
                app.innerHTML = '<p class="text-xs font-semibold uppercase tracking-[0.28em] text-emerald-700">Complete</p>'
                    + '<h1 class="mt-5 text-4xl font-semibold tracking-tight lg:text-6xl">Thank you.</h1>'
                    + '<p class="mt-6 max-w-2xl text-xl leading-9 text-slate-600">Your check-in has been submitted. Please return the iPad to the front desk.</p>';
                window.setTimeout(renderIdle, 6500);
            }

            function renderReviewSummary(review) {
                let html = '<div class="mt-6 rounded-[2rem] border border-slate-200 bg-white/80 p-5"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Review your packet</p><div class="mt-4 space-y-4">';
                (review.sections || []).forEach(function (section) {
                    html += '<div class="rounded-2xl border border-slate-100 bg-slate-50 p-4"><p class="text-sm font-semibold text-slate-900">' + escapeHtml(section.title || 'Section') + '</p>';
                    if (Array.isArray(section.rows) && section.rows.length) {
                        html += '<div class="mt-3 space-y-2">';
                        section.rows.forEach(function (row) {
                            html += '<div class="flex justify-between gap-4 text-sm"><span class="text-slate-500">' + escapeHtml(row.label || '') + '</span><span class="text-right text-slate-800">' + escapeHtml(row.value || '') + '</span></div>';
                        });
                        html += '</div>';
                    } else {
                        html += '<p class="mt-3 text-sm text-slate-500">No answers entered in this section.</p>';
                    }
                    html += '</div>';
                });
                return html + '</div></div>';
            }

            function renderField(field, value, answers) {
                const key = escapeHtml(field.key || '');
                const label = escapeHtml(field.label || '');
                const type = String(field.type || 'text');
                const required = field.required ? ' required' : '';
                const isVisible = fieldVisible(field, answersStateMap(answers));
                const wrapperStart = '<div class="field-wrapper' + (isVisible ? '' : ' hidden') + '" data-visible-if="' + escapeHtml(JSON.stringify(field.visible_if || null)) + '">';
                const wrapperEnd = '</div>';

                if (type === 'heading') {
                    return wrapperStart + '<h2 class="text-2xl font-semibold tracking-tight text-slate-950">' + label + '</h2>' + wrapperEnd;
                }
                if (type === 'paragraph' || type === 'text_block') {
                    return wrapperStart + '<div class="rounded-[2rem] border border-amber-200 bg-amber-50 p-6 text-lg leading-8 text-slate-800">' + escapeHtml(field.body || '') + '</div>' + wrapperEnd;
                }
                if (type === 'divider') {
                    return wrapperStart + '<div class="h-px bg-slate-200"></div>' + wrapperEnd;
                }
                if (type === 'signature_capture' || type === 'digital_signature') {
                    return wrapperStart + '<div class="rounded-[2rem] border border-dashed border-slate-300 bg-white p-6">'
                        + '<p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Signature</p>'
                        + '<input type="hidden" name="' + key + '" value="' + escapeHtml(value || '') + '" data-signature-input="1"' + required + '>'
                        + '<div class="mt-4 rounded-2xl bg-slate-50 p-4">'
                        + '<div class="signature-preview min-h-24 rounded-xl border border-slate-200 bg-white p-3 text-slate-500">' + (value ? '<img src="' + escapeHtml(value) + '" alt="Captured signature" class="max-h-28">' : 'No signature captured yet.') + '</div>'
                        + '<button type="button" data-target="' + key + '" data-label="' + label + '" class="open-signature mt-4 min-h-14 w-full rounded-2xl bg-slate-950 px-6 py-4 text-lg font-semibold text-white">Capture Signature</button>'
                        + '</div></div>' + wrapperEnd;
                }
                if (type === 'digital_initials') {
                    return wrapperStart + '<div><label class="kiosk-label" for="' + key + '">' + label + '</label><input id="' + key + '" name="' + key + '" type="text" maxlength="8" value="' + escapeHtml(value || '') + '" class="kiosk-input"' + required + '></div>' + wrapperEnd;
                }
                if (type === 'acknowledgement_checkbox' || type === 'checkbox_ack') {
                    return wrapperStart + '<label class="flex cursor-pointer items-start gap-4 rounded-[2rem] border border-slate-200 bg-white p-5 text-xl font-semibold text-slate-900"><input class="mt-1 h-7 w-7 rounded border-slate-300 text-amber-600" type="checkbox" name="' + key + '" value="1"' + (value ? ' checked' : '') + required + '><span>' + label + '</span></label>' + wrapperEnd;
                }
                if (type === 'checkbox_group' || type === 'multi_select') {
                    let html = wrapperStart + '<fieldset><legend class="kiosk-label">' + label + '</legend><div class="grid gap-3 sm:grid-cols-2">';
                    const selected = Array.isArray(value) ? value : [];
                    Object.keys(field.options || {}).forEach(function (optionKey) {
                        html += '<label class="kiosk-option flex cursor-pointer items-center gap-3"><input class="h-6 w-6" type="checkbox" name="' + key + '" value="' + escapeHtml(optionKey) + '"' + (selected.includes(optionKey) ? ' checked' : '') + '><span>' + escapeHtml(field.options[optionKey]) + '</span></label>';
                    });
                    return html + '</div></fieldset>' + wrapperEnd;
                }
                if (type === 'yes_no' || type === 'radio') {
                    let html = wrapperStart + '<fieldset><legend class="kiosk-label">' + label + '</legend><div class="grid gap-3 sm:grid-cols-3">';
                    const options = field.options || { yes: 'Yes', no: 'No' };
                    Object.keys(options).forEach(function (optionKey) {
                        html += '<label class="kiosk-option flex cursor-pointer items-center gap-3"><input class="h-6 w-6" type="radio" name="' + key + '" value="' + escapeHtml(optionKey) + '"' + (String(value || '') === optionKey ? ' checked' : '') + required + '><span>' + escapeHtml(options[optionKey]) + '</span></label>';
                    });
                    return html + '</div></fieldset>' + wrapperEnd;
                }
                if (type === 'dropdown') {
                    let html = wrapperStart + '<div><label class="kiosk-label" for="' + key + '">' + label + '</label><select id="' + key + '" name="' + key + '" class="kiosk-input"' + required + '>';
                    html += '<option value="">Select an option</option>';
                    Object.keys(field.options || {}).forEach(function (optionKey) {
                        html += '<option value="' + escapeHtml(optionKey) + '"' + (String(value || '') === optionKey ? ' selected' : '') + '>' + escapeHtml(field.options[optionKey]) + '</option>';
                    });
                    return html + '</select></div>' + wrapperEnd;
                }
                if (type === 'textarea' || type === 'medication_list' || type === 'allergy_list') {
                    const body = Array.isArray(value) ? value.join('\n') : (value || '');
                    return wrapperStart + '<div><label class="kiosk-label" for="' + key + '">' + label + '</label><textarea id="' + key + '" name="' + key + '" rows="4" class="kiosk-input min-h-32 resize-none"' + required + '>' + escapeHtml(body) + '</textarea></div>' + wrapperEnd;
                }
                if (type === 'emergency_contact' || type === 'insurance') {
                    let html = wrapperStart + '<div class="rounded-[2rem] border border-slate-200 bg-white p-5"><p class="kiosk-label">' + label + '</p><div class="grid gap-4">';
                    fieldChildren(field).forEach(function (child) {
                        html += renderField(child, answerValue(answers, child.key), answers);
                    });
                    return html + '</div></div>' + wrapperEnd;
                }

                const inputType = type === 'phone' ? 'tel' : (type === 'email' ? 'email' : ((type === 'date' || type === 'dob') ? 'date' : 'text'));
                return wrapperStart + '<div><label class="kiosk-label" for="' + key + '">' + label + '</label><input id="' + key + '" name="' + key + '" type="' + inputType + '" value="' + escapeHtml(value || '') + '" class="kiosk-input"' + required + '></div>' + wrapperEnd;
            }

            function collectAnswersFromForm(form) {
                const answers = {};
                if (!form) {
                    return answers;
                }
                form.querySelectorAll('input, textarea, select').forEach(function (field) {
                    if (!field.name || field.closest('.field-wrapper.hidden')) {
                        return;
                    }
                    if (field.type === 'checkbox') {
                        const sameName = form.querySelectorAll('input[type="checkbox"][name="' + CSS.escape(field.name) + '"]');
                        if (sameName.length > 1) {
                            answers[field.name] = Array.from(sameName).filter(function (item) { return item.checked; }).map(function (item) { return item.value; });
                        } else {
                            answers[field.name] = field.checked ? field.value : '';
                        }
                        return;
                    }
                    if (field.type === 'radio') {
                        if (field.checked) {
                            answers[field.name] = field.value;
                        } else if (!Object.prototype.hasOwnProperty.call(answers, field.name)) {
                            answers[field.name] = '';
                        }
                        return;
                    }
                    if (field.tagName === 'TEXTAREA' && (field.name === 'medications' || field.name === 'allergies')) {
                        answers[field.name] = String(field.value || '').split(/\r?\n/).map(function (item) { return item.trim(); }).filter(Boolean);
                        return;
                    }
                    answers[field.name] = field.value;
                });
                return answers;
            }

            function collectAnswers() {
                return collectAnswersFromForm(app.querySelector('#kiosk-form'));
            }

            function refreshConditionalFields(form) {
                const answers = collectAnswersFromForm(form);
                form.querySelectorAll('.field-wrapper').forEach(function (wrapper) {
                    const raw = wrapper.getAttribute('data-visible-if');
                    let visible = true;
                    if (!raw || raw === 'null') {
                        visible = true;
                    } else {
                        let condition = null;
                        try {
                            condition = JSON.parse(raw);
                        } catch (error) {
                            condition = null;
                        }
                        visible = conditionMatches(condition, answers);
                    }
                    wrapper.classList.toggle('hidden', !visible);
                });

                form.querySelectorAll('input, textarea, select').forEach(function (field) {
                    const hiddenByAncestor = !!field.closest('.field-wrapper.hidden');
                    if (!hiddenByAncestor) {
                        field.disabled = false;
                        if (field.dataset.requiredOriginal === '1') {
                            field.setAttribute('required', 'required');
                        }
                        return;
                    }

                    if (field.hasAttribute('required')) {
                        field.dataset.requiredOriginal = '1';
                        field.removeAttribute('required');
                    }
                    field.disabled = true;
                });
            }

            function renderForm(payload) {
                const form = payload.form || {};
                const step = form.step || {};
                const session = payload.session || {};
                const fields = Array.isArray(step.fields) ? step.fields : [];
                const answers = form.answers || {};
                const review = form.review || null;
                setProgress(session.percent_complete || 5, 'Step: ' + String(step.title || 'Check-in'));
                app.className = 'min-h-[680px]';
                let html = '<div class="mx-auto max-w-3xl">'
                    + '<p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-700">Elite Smiles check-in</p>'
                    + '<h1 class="mt-3 text-3xl font-semibold tracking-tight lg:text-5xl">' + escapeHtml(step.title || 'Check-in') + '</h1>';
                if (review && Array.isArray(review.sections)) {
                    html += renderReviewSummary(review);
                }
                html += '<form id="kiosk-form" class="mt-8 space-y-6">';
                fields.forEach(function (field) {
                    html += renderField(field, answerValue(answers, field.key), answers);
                });
                html += '<div id="form-error" class="hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-base font-semibold text-red-700"></div>'
                    + '<div class="flex flex-wrap gap-4 pt-4">'
                    + '<button type="submit" class="min-h-14 flex-1 rounded-2xl bg-slate-950 px-8 py-4 text-lg font-semibold text-white shadow-lg shadow-slate-950/20">Save and Continue</button>'
                    + '<button type="button" class="cancel-checkin min-h-14 rounded-2xl border border-slate-300 bg-white px-8 py-4 text-lg font-semibold text-slate-700">Cancel</button>'
                    + '</div></form></div>';
                app.innerHTML = html;
                const formElement = app.querySelector('#kiosk-form');
                formElement.querySelectorAll('[required]').forEach(function (field) {
                    field.dataset.requiredOriginal = '1';
                });
                formElement.addEventListener('submit', function (event) {
                    event.preventDefault();
                    saveStep(form.current_step || step.current_step || '');
                });
                formElement.addEventListener('change', function () { refreshConditionalFields(formElement); });
                formElement.addEventListener('input', function () { refreshConditionalFields(formElement); });
                refreshConditionalFields(formElement);
                app.querySelector('.cancel-checkin').addEventListener('click', cancelSession);
                app.querySelectorAll('.open-signature').forEach(function (item) {
                    item.addEventListener('click', function () {
                        openSignatureModal(item.dataset.target || '', item.dataset.label || 'Please sign below');
                    });
                });
            }

            let signatureTargetName = '';
            let signatureDrawing = false;
            let signatureHasInk = false;
            const signatureModal = document.getElementById('signature-modal');
            const signatureCanvas = document.getElementById('signature-canvas');
            const signatureCtx = signatureCanvas.getContext('2d');
            const signatureTitle = document.getElementById('signature-title');

            function resizeSignatureCanvas() {
                const rect = signatureCanvas.getBoundingClientRect();
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                signatureCanvas.width = Math.max(1, Math.floor(rect.width * ratio));
                signatureCanvas.height = Math.max(1, Math.floor(rect.height * ratio));
                signatureCtx.setTransform(ratio, 0, 0, ratio, 0, 0);
                clearSignatureCanvas(false);
            }

            function clearSignatureCanvas(markEmpty) {
                signatureCtx.fillStyle = '#ffffff';
                signatureCtx.fillRect(0, 0, signatureCanvas.width, signatureCanvas.height);
                signatureCtx.strokeStyle = '#0f172a';
                signatureCtx.lineWidth = 3.4;
                signatureCtx.lineCap = 'round';
                signatureCtx.lineJoin = 'round';
                if (markEmpty !== false) {
                    signatureHasInk = false;
                }
            }

            function canvasPoint(event) {
                const rect = signatureCanvas.getBoundingClientRect();
                return { x: event.clientX - rect.left, y: event.clientY - rect.top };
            }

            function openSignatureModal(targetName, title) {
                signatureTargetName = targetName;
                signatureTitle.textContent = title || 'Please sign below';
                signatureModal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                window.setTimeout(resizeSignatureCanvas, 40);
            }

            function closeSignatureModal() {
                signatureModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                signatureDrawing = false;
                signatureTargetName = '';
            }

            signatureCanvas.addEventListener('pointerdown', function (event) {
                event.preventDefault();
                signatureCanvas.setPointerCapture(event.pointerId);
                const point = canvasPoint(event);
                signatureCtx.beginPath();
                signatureCtx.moveTo(point.x, point.y);
                signatureDrawing = true;
            });

            signatureCanvas.addEventListener('pointermove', function (event) {
                if (!signatureDrawing) {
                    return;
                }
                event.preventDefault();
                const point = canvasPoint(event);
                signatureCtx.lineTo(point.x, point.y);
                signatureCtx.stroke();
                signatureHasInk = true;
            });

            ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (eventName) {
                signatureCanvas.addEventListener(eventName, function () {
                    signatureDrawing = false;
                });
            });

            document.getElementById('signature-clear').addEventListener('click', function () {
                clearSignatureCanvas(true);
            });
            document.getElementById('signature-cancel').addEventListener('click', closeSignatureModal);
            document.getElementById('signature-cancel-top').addEventListener('click', closeSignatureModal);
            document.getElementById('signature-confirm').addEventListener('click', function () {
                if (!signatureHasInk || !signatureTargetName) {
                    return;
                }
                const dataUrl = signatureCanvas.toDataURL('image/png');
                const input = app.querySelector('input[name="' + CSS.escape(signatureTargetName) + '"]');
                if (input) {
                    input.value = dataUrl;
                    const wrapper = input.closest('.rounded-\\[2rem\\]');
                    const preview = wrapper ? wrapper.querySelector('.signature-preview') : null;
                    if (preview) {
                        preview.innerHTML = '<img src="' + dataUrl + '" alt="Captured signature" class="max-h-28">';
                    }
                }
                closeSignatureModal();
            });
            window.addEventListener('resize', function () {
                if (!signatureModal.classList.contains('hidden')) {
                    resizeSignatureCanvas();
                }
            });

            async function post(action, body) {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(Object.assign({ action: action, kiosk_token: kioskToken, device_token: deviceToken }, body || {}))
                });
                return response.json();
            }

            async function beginSession() {
                if (!deviceToken) {
                    renderSetupRequired('This iPad is not registered. Open the setup QR code first.');
                    return;
                }
                polling = false;
                const data = await post('begin');
                if (data.ok) {
                    renderForm(data);
                } else {
                    renderIdle();
                }
                polling = true;
            }

            async function saveStep(stepKey) {
                if (!deviceToken) {
                    renderSetupRequired('This iPad is not registered. Open the setup QR code first.');
                    return;
                }
                const error = document.getElementById('form-error');
                if (error) {
                    error.classList.add('hidden');
                }
                const data = await post('save_step', { step_key: stepKey, answers: collectAnswers() });
                if (!data.ok) {
                    if (error) {
                        error.textContent = data.message || 'Please review this step.';
                        error.classList.remove('hidden');
                    }
                    return;
                }
                if (data.completed) {
                    renderComplete();
                    return;
                }
                renderForm(data);
            }

            async function cancelSession() {
                if (!deviceToken) {
                    renderSetupRequired('This iPad is not registered. Open the setup QR code first.');
                    return;
                }
                await post('cancel');
                renderIdle();
            }

            async function poll() {
                if (!polling) {
                    return;
                }
                try {
                    const url = endpoint + '?device_token=' + encodeURIComponent(deviceToken || '');
                    const response = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
                    const data = await response.json();
                    if (!data) {
                        return;
                    }
                    if (data.clear_device) {
                        setDeviceToken('');
                    }
                    currentDevice = data.device || null;
                    if (data.state === 'setup_required') {
                        renderSetupRequired(data.message || 'This iPad is not registered yet.');
                        return;
                    }
                    if (data.state !== 'assigned') {
                        if (!kioskToken) {
                            renderIdle();
                        }
                        return;
                    }
                    if (!kioskToken || currentSessionId !== Number(data.session.id || 0)) {
                        renderWelcome(data.session);
                        return;
                    }
                    if (data.session.status === 'waiting' && !app.querySelector('.begin-checkin')) {
                        renderWelcome(data.session);
                    }
                } catch (error) {
                    if (!kioskToken) {
                        if (deviceToken) {
                            renderSetupRequired('We could not reach the kiosk service. Check the local server and try again.');
                        } else {
                            renderSetupRequired('This kiosk still needs to be registered.');
                        }
                    }
                }
            }

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register(serviceWorkerUrl).catch(function () {});
            }
            poll();
            window.setInterval(poll, 3000);
        })();
    </script>
</body>
</html>
