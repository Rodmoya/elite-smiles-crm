<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/leads/lead_meta.php';
require_once __DIR__ . '/app/leads/lead_service.php';
require_once __DIR__ . '/app/leads/lead_communications.php';
require_once __DIR__ . '/app/leads/lead_email.php';
require_once __DIR__ . '/app/ai/elite_ai_service.php';

require_auth();
require_role('admin', 'marketing_manager');

$currentPage = 'elite_ai_lab';
$pageTitle = 'Elite AI Lab';
$logoutAction = base_url('elite-ai-lab.php');
$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');

lead_comm_ensure_schema();
lead_email_ensure_schema();
elite_ai_ensure_schema();

$defaultPrompts = [
    ['label' => 'Control Center', 'prompt' => '', 'quick_action' => 'control-center'],
    ['label' => 'Check CRM', 'prompt' => 'check crm', 'quick_action' => ''],
    ['label' => 'Elite AI Health', 'prompt' => 'check elite ai', 'quick_action' => ''],
    ['label' => 'Replies Today', 'prompt' => 'who replied today?', 'quick_action' => ''],
    ['label' => 'Follow-Ups Due', 'prompt' => 'what follow ups are due?', 'quick_action' => ''],
    ['label' => 'Natural Help', 'prompt' => 'what can you help me with right now?', 'quick_action' => ''],
    [
        'label' => 'Notification: New Message',
        'prompt' => 'review this',
        'quick_action' => '',
        'notification' => [
            'id' => 'lab-reply-example',
            'type' => 'reply',
            'title' => 'New message from Jordan Example',
            'message' => 'Can I see Dr. Meden Friday at 3pm?',
            'lead_id' => 0,
            'lead_name' => 'Jordan Example',
            'is_new' => true,
        ],
        'expect' => ['rod, we got a new message from jordan example', 'what do you want me to do?'],
    ],
    [
        'label' => 'Notification: New Lead',
        'prompt' => 'review this',
        'quick_action' => '',
        'notification' => [
            'id' => 'lab-lead-example',
            'type' => 'new_lead',
            'title' => 'New lead from Meta Lead Form',
            'message' => 'Taylor Example. I sent first message.',
            'lead_id' => 0,
            'lead_name' => 'Taylor Example',
            'is_new' => true,
        ],
        'expect' => ['rod, we got a new lead', 'first message was sent successfully', 'what do you want me to do next?'],
    ],
    [
        'label' => 'Notification: STOP',
        'prompt' => 'review this',
        'quick_action' => '',
        'notification' => [
            'id' => 'lab-stop-example',
            'type' => 'reply',
            'title' => 'New message from Casey Example',
            'message' => 'STOP',
            'lead_id' => 0,
            'lead_name' => 'Casey Example',
            'is_new' => true,
        ],
        'expect' => ['rod, casey example replied stop', 'blocked from sms', 'what do you want me to do?'],
    ],
    [
        'label' => 'Notification: Follow-Up',
        'prompt' => 'review this',
        'quick_action' => '',
        'notification' => [
            'id' => 'lab-follow-up-example',
            'type' => 'follow_up_due',
            'title' => 'Follow-up alert: Morgan Example',
            'message' => 'Follow-up is due today.',
            'lead_id' => 0,
            'lead_name' => 'Morgan Example',
            'is_new' => true,
        ],
        'expect' => ['rod,', 'what do you want me to do?'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Elite AI Lab</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<?php require __DIR__ . '/app/partials/crm_sidebar_live.php'; ?>

<main class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
    <div class="mx-auto min-w-0 max-w-7xl">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Elite AI</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Conversation Lab</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Shared test cockpit for Codex and Rod: I can run prompts and inspect quality signals while you watch the phone-style conversation and give input on what feels natural.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="lab-run-all" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">Run Core Test</button>
                <button type="button" id="lab-run-notifications" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-100">Run Notification Test</button>
                <button type="button" id="lab-clear" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-100">Clear</button>
            </div>
        </div>

        <div class="grid min-w-0 gap-5 xl:grid-cols-[430px_minmax(0,1fr)]">
            <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mx-auto w-full max-w-[390px] rounded-[2rem] border border-slate-300 bg-slate-950 p-2 shadow-xl">
                    <div class="overflow-hidden rounded-[1.5rem] bg-white">
                        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-950">Elite AI</p>
                                <p class="text-xs text-slate-500">Live assistant test</p>
                            </div>
                            <span id="lab-status" class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">Ready</span>
                        </div>
                        <div id="lab-thread" class="h-[560px] space-y-3 overflow-y-auto bg-slate-50 p-4">
                            <article class="max-w-[86%] rounded-2xl rounded-tl-md bg-white px-4 py-3 text-sm leading-6 text-slate-700 shadow-sm ring-1 ring-slate-200">
                                This lab is mainly for Codex testing while Rod watches and gives input. Run a test or ask a custom prompt.
                            </article>
                        </div>
                        <form id="lab-form" class="flex gap-2 border-t border-slate-200 bg-white p-3">
                            <input id="lab-input" class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-slate-500" placeholder="Ask Elite AI..." autocomplete="off" autocorrect="off" autocapitalize="sentences" spellcheck="false" inputmode="text" enterkeyhint="send">
                            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Send</button>
                        </form>
                    </div>
                </div>
            </section>

            <section class="min-w-0 space-y-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Prompt Controls</h2>
                            <p class="mt-1 text-sm text-slate-500">Codex can run these repeatedly, then Rod can point out answers that feel robotic, too long, or not useful.</p>
                        </div>
                        <span id="lab-score" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">No run yet</span>
                    </div>
                    <div id="lab-prompts" class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3"></div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-3 md:flex-row md:items-end">
                        <div class="min-w-0 flex-1">
                            <label for="lab-lead-id" class="text-sm font-semibold text-slate-950">Lead Context</label>
                            <p class="mt-1 text-sm text-slate-500">Optional sandbox lead for custom prompts and single prompt buttons.</p>
                            <input id="lab-lead-id" type="number" min="1" inputmode="numeric" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-slate-500" placeholder="Enter a lead ID only when testing real context">
                        </div>
                    </div>
                    <p id="lab-lead-context-label" class="mt-3 text-sm font-medium text-slate-600">No lead context selected.</p>
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                    <div class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-950">Quality Signals</h2>
                        <div id="lab-quality" class="mt-4 space-y-2 text-sm text-slate-600">
                            <p>No answers reviewed yet.</p>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-950">Latest Result</h2>
                        <pre id="lab-json" class="mt-4 max-h-[320px] w-full max-w-full overflow-auto rounded-xl bg-slate-950 p-4 text-xs leading-5 text-slate-100">Waiting for a test run.</pre>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>

<script>
(function () {
    var endpoint = <?= json_encode((string) (parse_url(base_url('assistant-api-live.php'), PHP_URL_PATH) ?: '/crm/assistant-api-live.php')) ?>;
    var assistantCsrfToken = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var prompts = <?= json_encode($defaultPrompts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var thread = document.getElementById('lab-thread');
    var form = document.getElementById('lab-form');
    var input = document.getElementById('lab-input');
    var runAll = document.getElementById('lab-run-all');
    var runNotifications = document.getElementById('lab-run-notifications');
    var clear = document.getElementById('lab-clear');
    var promptWrap = document.getElementById('lab-prompts');
    var status = document.getElementById('lab-status');
    var score = document.getElementById('lab-score');
    var quality = document.getElementById('lab-quality');
    var jsonBox = document.getElementById('lab-json');
    var leadInput = document.getElementById('lab-lead-id');
    var leadLabel = document.getElementById('lab-lead-context-label');
    var results = [];
    var conversation = [];

    function setStatus(text, tone) {
        status.textContent = text;
        status.className = 'rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ' + (tone === 'bad'
            ? 'bg-red-50 text-red-700 ring-red-200'
            : tone === 'busy'
                ? 'bg-amber-50 text-amber-700 ring-amber-200'
                : 'bg-emerald-50 text-emerald-700 ring-emerald-200');
    }

    function bubble(role, text, meta) {
        var article = document.createElement('article');
        var isUser = role === 'user';
        article.className = 'max-w-[86%] whitespace-pre-wrap rounded-2xl px-4 py-3 text-sm leading-6 shadow-sm ring-1 ' + (isUser
            ? 'ml-auto rounded-tr-md bg-slate-950 text-white ring-slate-950'
            : 'rounded-tl-md bg-white text-slate-700 ring-slate-200');
        article.textContent = text || '';
        if (meta) {
            var footer = document.createElement('div');
            footer.className = 'mt-2 text-[11px] font-semibold uppercase tracking-[0.14em] ' + (isUser ? 'text-slate-300' : 'text-slate-400');
            footer.textContent = meta;
            article.appendChild(footer);
        }
        thread.appendChild(article);
        thread.scrollTop = thread.scrollHeight;
        conversation.push({ role: isUser ? 'user' : 'assistant', text: String(text || '').slice(0, 700) });
        conversation = conversation.slice(-10);
        return article;
    }

    function renderActions(article, data) {
        var actions = (Array.isArray(data && data.actions) ? data.actions : []).filter(function (action) {
            var actionType = String(action && action.type ? action.type : '');
            var actionId = Number(action && (action.action_id || action.actionId) ? (action.action_id || action.actionId) : 0);
            return actionId > 0 && ['send_draft', 'use_draft', 'edit_draft', 'cancel_draft'].indexOf(actionType) !== -1;
        });
        if (!article || !actions.length) return;
        var wrap = document.createElement('div');
        wrap.className = 'mt-3 flex flex-wrap gap-2 whitespace-normal';
        actions.slice(0, 5).forEach(function (action) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-100';
            button.textContent = action.label || action.type || 'Action';
            button.addEventListener('click', function () {
                runAction(action);
            });
            wrap.appendChild(button);
        });
        article.appendChild(wrap);
    }

    function activeLeadId() {
        var value = Number(leadInput && leadInput.value ? leadInput.value : 0);
        return Number.isFinite(value) && value > 0 ? Math.floor(value) : 0;
    }

    function updateLeadLabel() {
        var leadId = activeLeadId();
        if (!leadLabel) return;
        leadLabel.textContent = leadId > 0 ? 'Testing with lead #' + leadId + ' in context.' : 'No lead context selected.';
    }

    function syncLeadContext(data) {
        if (!leadInput || !data) return;
        var currentSubject = data.current_subject && typeof data.current_subject === 'object' ? data.current_subject : {};
        var leadId = Number(currentSubject.lead_id || data.lead_id || 0);
        if (!Number.isFinite(leadId) || leadId <= 0) return;
        leadInput.value = String(Math.floor(leadId));
        updateLeadLabel();
    }

    function grade(answer, data, elapsed, scenario) {
        var clean = String(answer || '').replace(/\s+/g, ' ').trim();
        var lower = clean.toLowerCase();
        var issues = [];
        ['try one of these prompts', 'as an ai language model', 'i cannot access', "i don't have access"].forEach(function (pattern) {
            if (lower.indexOf(pattern) !== -1) issues.push('Generic/off-topic phrase: ' + pattern);
        });
        if (!clean) issues.push('Empty answer');
        if (clean.length > 1400) issues.push('Too long for phone chat');
        if (elapsed > 2500) issues.push('Slow response: ' + elapsed + ' ms');
        if ((data.cards || []).length > 3) issues.push('Too many cards for a conversational answer');
        (Array.isArray(scenario && scenario.expect) ? scenario.expect : []).forEach(function (expected) {
            if (lower.indexOf(String(expected).toLowerCase()) === -1) {
                issues.push('Missing expected phrase: ' + expected);
            }
        });
        if (scenario && scenario.notification) {
            var visibleActions = (Array.isArray(data.actions) ? data.actions : []).filter(function (action) {
                var actionType = String(action && action.type ? action.type : '');
                var actionId = Number(action && (action.action_id || action.actionId) ? (action.action_id || action.actionId) : 0);
                return actionId > 0 && ['send_draft', 'use_draft', 'edit_draft', 'cancel_draft'].indexOf(actionType) !== -1;
            });
            if ((data.cards || []).length > 0) issues.push('Notification answer should not show cards');
            if (visibleActions.length > 0) issues.push('Notification answer should not show option buttons');
        }
        return { ok: issues.length === 0, issues: issues, chars: clean.length };
    }

    function updateQuality() {
        var passed = results.filter(function (item) { return item.grade.ok; }).length;
        var total = results.length;
        score.textContent = total ? passed + '/' + total + ' passed' : 'No run yet';
        score.className = 'rounded-full px-3 py-1 text-xs font-semibold ' + (total && passed === total ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700');
        quality.innerHTML = '';
        if (!total) {
            quality.innerHTML = '<p>No answers reviewed yet.</p>';
            return;
        }
        results.slice(-8).reverse().forEach(function (item) {
            var row = document.createElement('div');
            row.className = 'rounded-xl border p-3 ' + (item.grade.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900');
            row.innerHTML = '<div class="font-semibold">' + item.label + ' - ' + (item.grade.ok ? 'Passed' : 'Review') + '</div>'
                + '<div class="mt-1 text-xs">' + item.elapsed + ' ms, ' + item.grade.chars + ' chars</div>'
                + (item.grade.issues.length ? '<div class="mt-2 text-xs">' + item.grade.issues.join('<br>') + '</div>' : '');
            quality.appendChild(row);
        });
    }

    async function ask(label, prompt, quickAction, isolated, scenario) {
        var display = prompt || label || quickAction || 'Run test';
        var notification = scenario && scenario.notification ? scenario.notification : null;
        if (notification) {
            bubble('assistant', 'Notification arrived: ' + String(notification.title || label) + (notification.message ? '\n"' + notification.message + '"' : ''), 'notification');
        } else {
            bubble('user', display);
        }
        setStatus('Thinking', 'busy');
        var started = Date.now();
        var threadContext = isolated ? [] : conversation.slice(-8);
        var leadId = Number(notification && notification.lead_id ? notification.lead_id : activeLeadId());
        try {
            var response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'include',
                cache: 'no-store',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': assistantCsrfToken },
                body: JSON.stringify({
                    surface: 'mobile',
                    prompt: prompt || '',
                    quick_action: quickAction || '',
                    context: {
                        page: 'elite-ai-lab',
                        page_title: 'Elite AI Lab',
                        current_url: window.location.href,
                        lead_id: leadId,
                        notification: notification,
                        assistant_thread: threadContext
                    }
                })
            });
            var data = await response.json();
            syncLeadContext(data);
            var elapsed = Date.now() - started;
            var answer = data.answer || data.message || 'No answer returned.';
            var gradeResult = grade(answer, data, elapsed, scenario);
            results.push({ label: label || display, prompt: prompt || '', quick_action: quickAction || '', elapsed: elapsed, grade: gradeResult, data: data });
            var assistantBubble = bubble('assistant', answer, (gradeResult.ok ? 'passed' : 'review') + ' - ' + elapsed + ' ms');
            renderActions(assistantBubble, data);
            jsonBox.textContent = JSON.stringify(data, null, 2);
            setStatus(gradeResult.ok ? 'Passed' : 'Review', gradeResult.ok ? 'ok' : 'bad');
            updateQuality();
        } catch (error) {
            results.push({ label: label || display, elapsed: 0, grade: { ok: false, issues: ['Request failed'], chars: 0 }, data: { error: String(error) } });
            bubble('assistant', 'I could not reach Elite AI from the lab.');
            setStatus('Error', 'bad');
            updateQuality();
        }
    }

    async function runAction(action) {
        var leadId = Number(action.lead_id || action.leadId || activeLeadId() || 0);
        var actionId = Number(action.action_id || action.actionId || 0);
        var label = String(action.label || action.type || 'Action');
        bubble('user', label + (leadId ? ' for lead #' + leadId : ''));
        setStatus('Thinking', 'busy');
        var started = Date.now();
        try {
            var response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'include',
                cache: 'no-store',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': assistantCsrfToken },
                body: JSON.stringify({
                    surface: 'mobile',
                    assistant_action: String(action.type || ''),
                    lead_id: leadId,
                    action_id: actionId,
                    target_status: String(action.target_status || action.targetStatus || ''),
                    consultation_date: String(action.consultation_date || action.appointment_at || ''),
                    note: String(action.note || ''),
                    send_approved: String(action.type || '') === 'send_draft',
                    stage_approved: String(action.type || '') === 'move_stage',
                    schedule_approved: String(action.type || '') === 'schedule_consultation',
                    prompt: String(action.help || ''),
                    instruction: String(action.help || ''),
                    context: {
                        page: 'elite-ai-lab',
                        page_title: 'Elite AI Lab',
                        current_url: window.location.href,
                        lead_id: leadId,
                        assistant_thread: conversation.slice(-8)
                    }
                })
            });
            var data = await response.json();
            syncLeadContext(data);
            var elapsed = Date.now() - started;
            var answer = data.answer || data.message || 'No answer returned.';
            var gradeResult = grade(answer, data, elapsed);
            results.push({ label: label, prompt: '', quick_action: '', elapsed: elapsed, grade: gradeResult, data: data });
            var assistantBubble = bubble('assistant', answer, (gradeResult.ok ? 'passed' : 'review') + ' - ' + elapsed + ' ms');
            renderActions(assistantBubble, data);
            jsonBox.textContent = JSON.stringify(data, null, 2);
            setStatus(gradeResult.ok ? 'Passed' : 'Review', gradeResult.ok ? 'ok' : 'bad');
            updateQuality();
        } catch (error) {
            bubble('assistant', 'I could not run that Elite AI action from the lab.');
            setStatus('Error', 'bad');
        }
    }

    prompts.forEach(function (item) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-left text-sm font-semibold text-slate-800 transition hover:bg-slate-100';
        button.textContent = item.label;
        button.addEventListener('click', function () { ask(item.label, item.prompt, item.quick_action, true, item); });
        promptWrap.appendChild(button);
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var text = input.value.trim();
        if (!text) return;
        input.value = '';
        ask('Custom', text, '', false, null);
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            }
        }
    });

    runAll.addEventListener('click', async function () {
        results = [];
        updateQuality();
        for (var i = 0; i < prompts.length; i += 1) {
            await ask(prompts[i].label, prompts[i].prompt, prompts[i].quick_action, true, prompts[i]);
        }
    });

    runNotifications.addEventListener('click', async function () {
        results = [];
        updateQuality();
        var notificationPrompts = prompts.filter(function (item) { return Boolean(item.notification); });
        for (var i = 0; i < notificationPrompts.length; i += 1) {
            await ask(
                notificationPrompts[i].label,
                notificationPrompts[i].prompt,
                notificationPrompts[i].quick_action,
                true,
                notificationPrompts[i]
            );
        }
    });

    clear.addEventListener('click', function () {
        results = [];
        conversation = [];
        thread.innerHTML = '';
        bubble('assistant', 'Lab cleared. Codex can run a test, and Rod can watch the phone-style answer here.');
        jsonBox.textContent = 'Waiting for a test run.';
        setStatus('Ready', 'ok');
        updateQuality();
    });
    if (leadInput) {
        leadInput.addEventListener('input', updateLeadLabel);
    }
    updateLeadLabel();
}());
</script>
</body>
</html>
