<?php
declare(strict_types=1);

$currentPage = $currentPage ?? 'dashboard';
$pageTitle = $pageTitle ?? 'Elite Smiles CRM';
$user = $user ?? (function_exists('auth_user') ? auth_user() : []);
$firstName = trim((string)($user['first_name'] ?? 'User'));
$role = trim((string)($user['role'] ?? 'viewer'));
$logoUrl = $logoUrl ?? base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$logoutAction = $logoutAction ?? ($_SERVER['PHP_SELF'] ?? base_url('dashboard.php'));
$assistantLeadId = 0;
if (isset($_GET['lead_id']) && ctype_digit((string) $_GET['lead_id'])) {
    $assistantLeadId = (int) $_GET['lead_id'];
} elseif ($currentPage === 'leads' && isset($_GET['id']) && ctype_digit((string) $_GET['id'])) {
    $assistantLeadId = (int) $_GET['id'];
}
$assistantCurrentUrl = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
$assistantAuthToken = function_exists('auth_issue_assistant_api_token')
    ? auth_issue_assistant_api_token((int)($user['id'] ?? 0))
    : '';

$crmNavItems = [
    ['key' => 'dashboard', 'label' => 'Home', 'href' => base_url('dashboard.php'), 'icon' => 'M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V10.5z', 'show' => true],
    ['key' => 'leads', 'label' => 'Leads', 'href' => base_url('leads.php'), 'icon' => 'M4 6h16M4 12h16M4 18h10', 'show' => true],
    ['key' => 'dental_models', 'label' => '3D Design', 'href' => base_url('dental-models'), 'icon' => 'M4 3h16v8H4zM4 13h16v8H4zm2 2h12M4 7h4v4M16 7h2M20 7h0M20 15h-2M4 16h3M4 20h3', 'show' => function_exists('auth_has_role') ? auth_has_role('admin', 'marketing_manager', 'staff') : false],
    ['key' => 'smile_design', 'label' => 'Smile Design', 'href' => base_url('smile-design'), 'icon' => 'M12 3c3.5 0 6.5 2.1 7.8 5.1C18.4 15.2 15.8 21 12 21S5.6 15.2 4.2 8.1C5.5 5.1 8.5 3 12 3zM8.5 10c.8 1.2 2 1.8 3.5 1.8s2.7-.6 3.5-1.8', 'show' => true],
    ['key' => 'email_status', 'label' => 'Email Status', 'href' => base_url('email_status.php'), 'icon' => 'M4 6h16v12H4V6zm0 0 8 7 8-7', 'show' => true],
    ['key' => 'landing_pages', 'label' => 'Landing Pages', 'href' => base_url('landing_pages.php'), 'icon' => 'M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14H4V5zm4 3h8M8 12h8M8 16h5', 'show' => true],
    ['key' => 'users', 'label' => 'Users', 'href' => base_url('users.php'), 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75', 'show' => function_exists('auth_has_role') ? auth_has_role('admin') : false],
];
$crmNavItems = array_values(array_filter($crmNavItems, static fn(array $item): bool => !empty($item['show'])));
?>

<style>
@media (min-width: 1024px) {
    body.crm-sidebar-collapsed #crm-sidebar { width: 5.5rem; }
    body.crm-sidebar-collapsed main { padding-left: 7rem !important; }
    body.crm-sidebar-collapsed .crm-sidebar-label,
    body.crm-sidebar-collapsed .crm-sidebar-title,
    body.crm-sidebar-collapsed .crm-sidebar-top-user,
    body.crm-sidebar-collapsed .crm-sidebar-logo-text { display: none; }
    body.crm-sidebar-collapsed .crm-sidebar-link { justify-content: center; }
    body.crm-sidebar-collapsed .crm-sidebar-ai-copy { display: none; }
}
</style>

<div class="lg:pl-72">
    <aside id="crm-sidebar" class="fixed inset-y-0 left-0 z-50 hidden w-72 border-r border-slate-200 bg-white lg:block">
        <div class="flex h-full flex-col">
            <div class="flex h-20 items-center justify-between gap-3 border-b border-slate-200 px-5">
                <a href="<?= e(base_url('dashboard.php')) ?>" class="crm-sidebar-logo-text shrink-0">
                    <img src="<?= e((string)$logoUrl) ?>" alt="Elite Smiles" class="h-auto w-[170px] max-w-full">
                </a>
                <button
                    type="button"
                    id="crm-sidebar-toggle"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-slate-300 bg-white text-slate-700 transition hover:bg-slate-100"
                    aria-label="Collapse sidebar"
                    aria-pressed="false"
                >
                    <svg class="h-5 w-5 transition-transform" id="crm-sidebar-toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 18l-6-6 6-6"></path>
                    </svg>
                </button>
            </div>

            <div class="crm-sidebar-title px-5 py-5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">CRM</p>
                <h1 class="mt-2 text-xl font-semibold tracking-tight text-slate-900"><?= e((string)$pageTitle) ?></h1>
            </div>

            <nav class="flex-1 space-y-1 px-3" aria-label="CRM navigation">
                <?php foreach ($crmNavItems as $item): ?>
                    <?php $isActive = $currentPage === $item['key']; ?>
                    <a
                        href="<?= e($item['href']) ?>"
                        class="<?= $isActive
                            ? 'crm-sidebar-link flex items-center gap-3 rounded-2xl bg-slate-900 px-3 py-3 text-sm font-semibold text-white'
                            : 'crm-sidebar-link flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900'
                        ?>"
                        <?= $isActive ? 'aria-current="page"' : '' ?>
                        title="<?= e($item['label']) ?>"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="<?= e($item['icon']) ?>"></path>
                        </svg>
                        <span class="crm-sidebar-label"><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="border-t border-slate-200 px-4 py-4">
                <button
                    type="button"
                    id="crm-sidebar-ai-launch"
                    class="flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-left transition hover:border-slate-300 hover:bg-slate-100"
                    aria-expanded="false"
                    aria-controls="crm-ai-panel"
                >
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 3l1.8 4.2L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9l4.2-1.8L12 3z"></path>
                            <path d="M5 18l.8 1.9L8 21l-2.2 1.1L5 24l-.8-1.9L2 21l2.2-1.1L5 18z"></path>
                        </svg>
                    </span>
                    <span class="min-w-0 flex-1 crm-sidebar-ai-copy">
                        <span class="block text-sm font-semibold text-slate-900">Elite AI</span>
                        <span class="block text-xs text-slate-500">Assistant</span>
                    </span>
                </button>
            </div>

            <div class="crm-sidebar-top-user border-t border-slate-200 px-4 py-3">
                <div class="mb-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[11px] font-semibold leading-tight text-slate-900"><?= e($firstName) ?></p>
                    <p class="mt-0.5 text-[9px] uppercase tracking-[0.14em] text-slate-500"><?= e($role) ?></p>
                </div>
                <form method="POST" action="<?= e((string)$logoutAction) ?>">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-100">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <aside
        id="crm-ai-panel"
        class="pointer-events-none fixed top-4 z-50 hidden w-[380px] max-w-[calc(100vw-2rem)] overflow-hidden rounded-[26px] border border-slate-200 bg-white opacity-0 shadow-2xl transition duration-200 ease-out lg:flex"
        data-endpoint="<?= e((string) (parse_url(base_url('assistant-api.php'), PHP_URL_PATH) ?: '/crm/assistant-api.php')) ?>"
        data-auth-token="<?= e($assistantAuthToken) ?>"
        data-page="<?= e((string) $currentPage) ?>"
        data-page-title="<?= e((string) $pageTitle) ?>"
        data-current-url="<?= e($assistantCurrentUrl) ?>"
        data-lead-id="<?= e((string) $assistantLeadId) ?>"
        aria-hidden="true"
    >
        <div class="flex h-[min(78vh,720px)] w-full flex-col">
            <div class="border-b border-slate-200 px-5 py-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold tracking-tight text-slate-900">Elite AI</h2>
                    <div class="flex items-center gap-2">
                        <button type="button" id="crm-ai-notifications" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-100" aria-label="Open notifications">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
                                <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                            </svg>
                        </button>
                        <button type="button" id="crm-ai-close" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-100" aria-label="Close assistant">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                <path d="M6 6l12 12M18 6 6 18"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div id="crm-ai-thread" class="flex-1 space-y-3 overflow-y-auto bg-slate-50 px-5 py-4">
                <article class="max-w-[88%] rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-sm leading-6 text-slate-700">Good morning, <?= e($firstName !== '' ? $firstName : 'Rodrigo') ?>. What do you want to do?</p>
                </article>
            </div>
            <div class="border-t border-slate-200 bg-white px-5 py-4">
                <div id="crm-ai-quick-actions" class="mb-3 flex flex-wrap gap-2"></div>
                <form id="crm-ai-form" class="flex items-center gap-3">
                    <input id="crm-ai-input" type="text" enterkeyhint="send" class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-300" placeholder="Ask Elite AI what to do...">
                    <button type="button" id="crm-ai-mic" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-100" aria-label="Microphone placeholder">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"></path>
                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                            <path d="M12 19v3"></path>
                        </svg>
                    </button>
                    <button type="submit" id="crm-ai-send" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-900 text-white transition hover:bg-slate-800" aria-label="Send">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M22 2 11 13"></path>
                            <path d="m22 2-7 20-4-9-9-4 20-7z"></path>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur lg:hidden">
        <div class="flex items-center justify-between gap-3 px-4 py-3">
            <a href="<?= e(base_url('dashboard.php')) ?>" class="shrink-0">
                <img src="<?= e((string)$logoUrl) ?>" alt="Elite Smiles" class="h-auto w-[150px] max-w-full">
            </a>
            <div class="ml-auto lg:hidden"></div>
            <button
                type="button"
                id="crm-mobile-nav-toggle"
                class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-300 bg-white text-slate-700"
                aria-controls="crm-mobile-nav"
                aria-expanded="false"
                aria-label="Open navigation"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16"></path>
                </svg>
            </button>
        </div>
        <nav id="crm-mobile-nav" class="hidden border-t border-slate-200 bg-white px-4 py-3" aria-label="Mobile CRM navigation">
            <div class="grid gap-2">
                <?php foreach ($crmNavItems as $item): ?>
                    <?php $isActive = $currentPage === $item['key']; ?>
                    <a
                        href="<?= e($item['href']) ?>"
                        class="<?= $isActive
                            ? 'rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white'
                            : 'rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700'
                        ?>"
                        <?= $isActive ? 'aria-current="page"' : '' ?>
                    >
                        <?= e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
                <div class="mt-2 border-t border-slate-200 pt-3">
                    <div class="mb-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-semibold leading-tight text-slate-900"><?= e($firstName) ?></p>
                        <p class="mt-0.5 text-[9px] uppercase tracking-[0.14em] text-slate-500"><?= e($role) ?></p>
                    </div>
                    <form method="POST" action="<?= e((string)$logoutAction) ?>">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-100">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </nav>
    </header>
</div>

<script>
(function () {
    const desktopToggle = document.getElementById('crm-sidebar-toggle');
    const desktopIcon = document.getElementById('crm-sidebar-toggle-icon');
    const toggle = document.getElementById('crm-mobile-nav-toggle');
    const nav = document.getElementById('crm-mobile-nav');
    const aiLaunch = document.getElementById('crm-sidebar-ai-launch');
    const aiPanel = document.getElementById('crm-ai-panel');
    const aiClose = document.getElementById('crm-ai-close');
    const aiThread = document.getElementById('crm-ai-thread');
    const aiForm = document.getElementById('crm-ai-form');
    const aiInput = document.getElementById('crm-ai-input');
    const aiSend = document.getElementById('crm-ai-send');
    const aiMic = document.getElementById('crm-ai-mic');
    const aiNotifications = document.getElementById('crm-ai-notifications');
    const aiQuickActions = [
        { label: 'Morning Sweep', quick_action: 'morning-sweep' },
        { label: 'New Leads', quick_action: 'new-leads' },
        { label: 'Replies', quick_action: 'replies' },
        { label: 'Follow-ups', quick_action: 'follow-ups' },
        { label: 'No Answer Review', quick_action: 'no-answer-review' },
        { label: 'Notifications', quick_action: 'notifications' },
    ];

    function aiContext() {
        return {
            page: aiPanel && aiPanel.dataset ? aiPanel.dataset.page || '' : '',
            page_title: aiPanel && aiPanel.dataset ? aiPanel.dataset.pageTitle || '' : '',
            current_url: aiPanel && aiPanel.dataset ? aiPanel.dataset.currentUrl || window.location.href : window.location.href,
            lead_id: Number(aiPanel && aiPanel.dataset ? (aiPanel.dataset.leadId || 0) : 0)
        };
    }

    function applyDesktopCollapsed(collapsed) {
        document.body.classList.toggle('crm-sidebar-collapsed', collapsed);
        if (desktopToggle) {
            desktopToggle.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
            desktopToggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        }
        if (desktopIcon) desktopIcon.style.transform = collapsed ? 'rotate(180deg)' : 'rotate(0deg)';
    }

    const saved = window.localStorage ? localStorage.getItem('elite_crm_sidebar_collapsed') : null;
    applyDesktopCollapsed(saved === '1');

    if (desktopToggle) {
        desktopToggle.addEventListener('click', function () {
            const next = !document.body.classList.contains('crm-sidebar-collapsed');
            applyDesktopCollapsed(next);
            if (window.localStorage) localStorage.setItem('elite_crm_sidebar_collapsed', next ? '1' : '0');
            if (aiPanel && !aiPanel.classList.contains('pointer-events-none')) {
                positionAssistantPanel();
            }
        });
    }

    function positionAssistantPanel() {
        if (!aiPanel) return;
        const sidebar = document.getElementById('crm-sidebar');
        if (!sidebar) return;
        const rect = sidebar.getBoundingClientRect();
        aiPanel.style.left = (rect.right + 16) + 'px';
    }

    function setAssistantOpen(open) {
        if (!aiPanel || !aiLaunch) return;
        aiLaunch.setAttribute('aria-expanded', open ? 'true' : 'false');
        aiPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
        aiPanel.classList.toggle('pointer-events-none', !open);
        aiPanel.classList.toggle('opacity-0', !open);
        aiPanel.classList.toggle('translate-y-2', !open);
        if (open) {
            positionAssistantPanel();
            buildQuickActions();
            if (aiInput) aiInput.focus();
        }
    }

    function assistantBubble(label, text, role, cards, loading, actions) {
        if (!aiThread) return null;
        const article = document.createElement('article');
        article.className = 'max-w-[88%] rounded-3xl border border-slate-200 p-4 shadow-sm ' + (role === 'user' ? 'ml-auto bg-slate-900 text-white' : 'bg-white text-slate-700');
        if (loading) article.classList.add('opacity-70');

        const body = document.createElement('p');
        body.className = 'text-sm leading-6 whitespace-pre-line';
        body.textContent = text;
        article.appendChild(body);

        if (cards && cards.length) {
            const wrap = document.createElement('div');
            wrap.className = 'mt-3 grid gap-2';
            cards.forEach(function (card) {
                const cardEl = document.createElement('div');
                cardEl.className = 'rounded-2xl border border-slate-200 bg-slate-50 p-3 text-slate-700';

                const title = document.createElement('p');
                title.className = 'text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500';
                title.textContent = card.title || 'Summary';
                cardEl.appendChild(title);

                const list = document.createElement('ul');
                list.className = 'mt-2 list-disc pl-5 text-xs leading-5';
                (card.items || []).forEach(function (item) {
                    const li = document.createElement('li');
                    li.textContent = item;
                    list.appendChild(li);
                });
                cardEl.appendChild(list);
                wrap.appendChild(cardEl);
            });
            article.appendChild(wrap);
        }

        if (actions && actions.length) {
            const actionWrap = document.createElement('div');
            actionWrap.className = 'mt-3 flex flex-wrap gap-2';
            actions.forEach(function (action) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium';
                button.textContent = action.label || ('Action: ' + String(action.type || ''));
                button.title = action.help || '';
                button.addEventListener('click', function () {
                    runAssistantAction(action);
                });
                actionWrap.appendChild(button);
            });
            article.appendChild(actionWrap);
        }

        aiThread.appendChild(article);
        aiThread.scrollTop = aiThread.scrollHeight;
        return article;
    }

    function buildQuickActions() {
        const quickActionContainer = document.getElementById('crm-ai-quick-actions');
        if (!quickActionContainer) return;

        quickActionContainer.innerHTML = '';
        aiQuickActions.forEach(function (entry) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'rounded-xl border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs font-medium';
            button.textContent = entry.label;
            button.addEventListener('click', function () {
                runAssistant('', entry.quick_action);
            });
            quickActionContainer.appendChild(button);
        });
    }

    function setBusy(isBusy) {
        if (!aiPanel) return;
        aiInput.disabled = isBusy;
        aiForm.querySelectorAll('button').forEach(function (btn) {
            btn.disabled = isBusy;
        });
        const quickActionContainer = document.getElementById('crm-ai-quick-actions');
        if (quickActionContainer) {
            quickActionContainer.querySelectorAll('button').forEach(function (btn) {
                btn.disabled = isBusy;
            });
        }
        if (aiNotifications) aiNotifications.disabled = isBusy;
    }

    function assistantHeaders() {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        const token = assistantToken();
        if (token) {
            headers['X-Elite-AI-Token'] = token;
        }
        return headers;
    }

    function assistantToken() {
        return String(aiPanel ? (aiPanel.dataset.authToken || '') : '').trim();
    }

    function parseDraftCandidate(candidate) {
        if (!candidate) {
            return {};
        }

        if (typeof candidate === 'string') {
            try {
                const decoded = JSON.parse(candidate);
                return decoded && typeof decoded === 'object' ? decoded : {};
            } catch (error) {
                return candidate.trim() !== '' ? { __preview: candidate } : {};
            }
        }

        return typeof candidate === 'object' ? candidate : {};
    }

    function formatDraftPreview(draft, actionType) {
        if (!draft || typeof draft !== 'object') {
            return '';
        }

        if (typeof draft.__preview === 'string' && draft.__preview.trim() !== '') {
            return draft.__preview.trim();
        }

        const nestedSms = draft.sms && typeof draft.sms === 'object' ? draft.sms : {};
        const nestedEmail = draft.email && typeof draft.email === 'object' ? draft.email : {};
        const smsText = String(draft.reply || draft.message || draft.text || draft.draft_text || draft.body || nestedSms.reply || nestedSms.message || nestedSms.text || nestedSms.body || '').trim();

        if (actionType === 'draft_sms') {
            return smsText ? 'Suggested SMS draft:\n\n' + smsText : '';
        }

        if (actionType === 'draft_email') {
            const subject = String(draft.subject || nestedEmail.subject || '').trim();
            const body = String(draft.body || draft.message || draft.text || nestedEmail.body || nestedEmail.message || nestedEmail.text || '').trim();
            return 'Suggested Email draft:\n\nSubject: ' + (subject || '(no subject)') + '\n\n' + (body || '(no body)');
        }

        return '';
    }

    function resolveDraftPayload(data) {
        if (!data) {
            return {};
        }

        const candidates = [
            data.draft,
            data.draft_payload,
            data.draft_payload_json,
            data.payload,
            data.item && data.item.draft_payload_json,
            data.queue_item && data.queue_item.draft_payload_json,
            data.action_item && data.action_item.draft_payload_json,
            data.data && data.data.draft,
            data.data && data.data.payload,
            data.draft_preview
        ];

        for (let i = 0; i < candidates.length; i += 1) {
            const resolved = parseDraftCandidate(candidates[i]);
            if (resolved && typeof resolved === 'object' && Object.keys(resolved).length > 0) {
                return resolved;
            }
        }

        return {};
    }

    async function runAssistantAction(action) {
        if (!aiPanel || !aiThread || !action || !action.type || !action.lead_id) return;

        assistantBubble('You', 'Prepare ' + (action.label || 'draft') + ' for lead #' + Number(action.lead_id), 'user');
        const loading = assistantBubble('Elite AI', 'Preparing draft for approval...', 'assistant', [], true);
        setBusy(true);

        try {
            const response = await fetch(aiPanel.dataset.endpoint, {
                method: 'POST',
                credentials: 'include',
                cache: 'no-store',
                headers: assistantHeaders(),
                body: JSON.stringify({
                    surface: 'desktop',
                    assistant_token: assistantToken(),
                    assistant_action: action.type,
                    lead_id: Number(action.lead_id || 0),
                    prompt: action.help || '',
                    instruction: action.help || '',
                    quick_action: '',
                    context: {
                        page: aiPanel.dataset.page || '',
                        page_title: aiPanel.dataset.pageTitle || '',
                        current_url: aiPanel.dataset.currentUrl || window.location.href,
                        lead_id: Number(action.lead_id || 0)
                    }
                })
            });
            const data = await response.json();
            if (loading) loading.remove();

            if (!response.ok || !data.ok) {
                assistantBubble('Elite AI', data.message || 'Draft action failed.', 'assistant');
                return;
            }

            const draftPayload = resolveDraftPayload(data);
            const preview = formatDraftPreview(draftPayload, action.type || '');
            if (preview) {
                assistantBubble('Elite AI', preview + '\n\nDraft ready. Pending approval before send.', 'assistant');
                assistantBubble('Elite AI', 'Action queued: #' + String(data.action_id || 0), 'assistant');
                if (typeof data.warning === 'string' && data.warning.trim() !== '') {
                            assistantBubble('Elite AI', 'Note: ' + String(data.warning), 'assistant');
                        }
            } else {
                assistantBubble('Elite AI', 'The draft action completed, but no usable preview text came back. Nothing was sent. Please try again or open the lead directly. Queue item: #' + String(data.action_id || 0), 'assistant');
            }
        } catch (error) {
            if (loading) loading.remove();
            assistantBubble('Elite AI', 'I hit an assistant error while preparing the draft.', 'assistant');
        } finally {
            setBusy(false);
            aiInput.focus();
        }
    }

    async function runAssistant(prompt, quickAction) {
        if (!aiPanel || !aiInput || !aiThread) return;
        const text = (prompt || '').trim();
        if (!text && !quickAction) return;

        assistantBubble('You', text || 'Run quick action', 'user');
        const loading = assistantBubble('Elite AI', 'Reviewing CRM context...', 'assistant', [], true);
        setBusy(true);

        try {
            const response = await fetch(aiPanel.dataset.endpoint, {
                method: 'POST',
                credentials: 'include',
                cache: 'no-store',
                headers: assistantHeaders(),
                body: JSON.stringify({
                    surface: 'desktop',
                    assistant_token: assistantToken(),
                    prompt: text,
                    quick_action: quickAction || '',
                    context: {
                        page: aiPanel.dataset.page || '',
                        page_title: aiPanel.dataset.pageTitle || '',
                        current_url: aiPanel.dataset.currentUrl || window.location.href,
                        lead_id: Number(aiPanel.dataset.leadId || 0)
                    }
                })
            });
            const data = await response.json();
            if (loading) loading.remove();

            if (!response.ok || !data.ok) {
                assistantBubble('Elite AI', data.message || 'I could not load an assistant answer right now.', 'assistant');
                return;
            }

            assistantBubble('Elite AI', data.answer || 'Assistant response ready.', 'assistant', data.cards || [], false, data.actions || []);
        } catch (error) {
            if (loading) loading.remove();
            assistantBubble('Elite AI', 'I hit an assistant error while loading CRM context. Please try again.', 'assistant');
        } finally {
            setBusy(false);
            aiInput.focus();
        }
    }

    if (aiLaunch && aiPanel) {
        aiLaunch.addEventListener('click', function () {
            const open = aiPanel.getAttribute('aria-hidden') !== 'false';
            setAssistantOpen(open);
        });
        if (aiClose) {
            aiClose.addEventListener('click', function () {
                setAssistantOpen(false);
            });
        }
        window.addEventListener('resize', function () {
            if (aiPanel.getAttribute('aria-hidden') === 'false') {
                positionAssistantPanel();
            }
        });
    }

    if (aiForm && aiInput) {
        aiForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const prompt = aiInput.value;
            aiInput.value = '';
            runAssistant(prompt, '');
        });
    }

    if (!toggle || !nav) return;

    toggle.addEventListener('click', function () {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        nav.classList.toggle('hidden', expanded);
    });
})();
</script>
