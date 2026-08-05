<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /leads.php
 *
 * Dedicated lead pipeline board.
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/leads/lead_meta.php';
require_once __DIR__ . '/app/leads/lead_service.php';
require_once __DIR__ . '/app/leads/lead_communications.php';
require_once __DIR__ . '/app/leads/lead_agent.php';
require_once __DIR__ . '/app/dentrix/dentrix_bridge.php';

require_auth();
lead_comm_ensure_schema();
dentrix_bridge_ensure_schema();

if (!function_exists('lead_pipeline_version_snapshot')) {
    function lead_pipeline_version_snapshot(): array
    {
        lead_pipeline_ensure_schema();
        $updatedExpr = leads_has_column('updated_at') ? "COALESCE(MAX(updated_at), '')" : "''";
        $unreadExpr = leads_has_column('unread_message_count')
            ? 'COALESCE(SUM(unread_message_count), 0)'
            : '0';
        $row = db_one(
            "SELECT COUNT(*) AS total, COALESCE(MAX(id), 0) AS max_id, {$updatedExpr} AS latest_update, {$unreadExpr} AS unread_total "
            . 'FROM leads ' . lead_pipeline_visibility_sql('WHERE')
        ) ?? [];
        $state = [
            'total' => (int)($row['total'] ?? 0),
            'max_id' => (int)($row['max_id'] ?? 0),
            'latest_update' => (string)($row['latest_update'] ?? ''),
            'unread_total' => (int)($row['unread_total'] ?? 0),
        ];
        $state['version'] = hash('sha256', json_encode($state, JSON_UNESCAPED_SLASHES));
        return $state;
    }
}

if (get('action') === 'pipeline_version') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode(['ok' => true] + lead_pipeline_version_snapshot(), JSON_UNESCAPED_SLASHES);
    exit;
}

if (is_post() && post('action') === 'logout') {
    require_csrf();
    auth_logout();
    flash_set('success', 'You have been logged out.');
    redirect(base_url('login.php'));
}

$user = auth_user();
$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$currentPage = 'leads';
$pageTitle = 'Leads';
$logoutAction = base_url('leads.php');

$successMessage = flash_get('success') ?? '';
$errorMessage = '';
$stats = lead_dashboard_stats();
$stageMap = function_exists('lead_pipeline_display_stage_map') ? lead_pipeline_display_stage_map() : lead_stage_map_ordered();
$pipelineCounts = lead_pipeline_counts();
$pipelineValues = lead_pipeline_stage_values();
$pipelineRows = lead_pipeline_rows(250);
$actionQueueRows = lead_agent_exception_rows(50);
$actionQueueSummary = lead_action_queue_summary($actionQueueRows);
$leadAttentionDisplayLimit = 12;
$leadAttentionVisibleCount = min($leadAttentionDisplayLimit, count($actionQueueRows));
$dentrixCalendarSlots = dentrix_bridge_calendar_slots();
$pipelineVersion = (string)(lead_pipeline_version_snapshot()['version'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> | Leads</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="robots" content="noindex,nofollow">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <?php require __DIR__ . '/app/partials/crm_sidebar.php'; ?>

    <main class="px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
        <?php if ($successMessage !== ''): ?>
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                <?= e($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= e($errorMessage) ?>
            </div>
        <?php endif; ?>

        <section class="mb-4 hidden sm:block">
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(220px,360px)_minmax(0,1fr)] xl:items-end">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Lead Flow</p>
                    <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-900 lg:text-4xl">Pipeline Board</h1>
                </div>

                <?php
                    $statsVariant = 'compact';
                    $statsInline = true;
                    require __DIR__ . '/app/partials/dashboard_stats.php';
                    unset($statsVariant, $statsInline);
                ?>
            </div>
        </section>

        <section class="mb-4" data-lead-view-tabs>
            <div class="inline-flex w-full rounded-2xl border border-slate-200 bg-white p-1 shadow-sm sm:w-auto">
                <button
                    type="button"
                    class="lead-view-tab inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white transition sm:flex-none"
                    data-lead-view-tab="pipeline"
                    aria-pressed="true"
                >
                    Pipeline Board
                </button>
                <button
                    type="button"
                    class="lead-view-tab inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 sm:flex-none"
                    data-lead-view-tab="attention"
                    aria-pressed="false"
                >
                    Needs Attention Today
                    <span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-800">
                        <?= e((string)$leadAttentionVisibleCount) ?>
                    </span>
                </button>
            </div>
        </section>

        <div data-lead-view-panel="attention" class="hidden">
            <?php
                $actionQueueCompact = true;
                $actionQueueAiEnabled = true;
                $actionQueueDisplayLimit = $leadAttentionDisplayLimit;
                require __DIR__ . '/app/partials/dashboard_action_queue.php';
                unset($actionQueueCompact, $actionQueueAiEnabled, $actionQueueDisplayLimit);
            ?>
        </div>

        <div data-lead-view-panel="pipeline">
            <?php require __DIR__ . '/app/partials/dashboard_pipeline.php'; ?>
        </div>
    </main>
    <script>
    (() => {
        const tabs = Array.from(document.querySelectorAll('[data-lead-view-tab]'));
        const panels = Array.from(document.querySelectorAll('[data-lead-view-panel]'));
        const tabStrip = document.querySelector('[data-lead-view-tabs]');
        const activeClasses = ['bg-slate-950', 'text-white'];
        const inactiveClasses = ['text-slate-600', 'hover:bg-slate-50'];

        const setLeadView = (view, shouldScroll = false) => {
            const nextView = view === 'attention' ? 'attention' : 'pipeline';
            tabs.forEach((tab) => {
                const isActive = tab.dataset.leadViewTab === nextView;
                tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                tab.classList.toggle(activeClasses[0], isActive);
                tab.classList.toggle(activeClasses[1], isActive);
                tab.classList.toggle(inactiveClasses[0], !isActive);
                tab.classList.toggle(inactiveClasses[1], !isActive);
            });
            panels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.leadViewPanel !== nextView);
            });
            if (window.history && window.history.replaceState) {
                const url = new URL(window.location.href);
                if (nextView === 'attention') {
                    url.hash = 'attention';
                } else if (url.hash === '#attention') {
                    url.hash = '';
                }
                window.history.replaceState(window.history.state, document.title, url.pathname + url.search + url.hash);
            }
            if (shouldScroll && tabStrip) {
                window.requestAnimationFrame(() => {
                    tabStrip.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }
        };

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => setLeadView(tab.dataset.leadViewTab || 'pipeline', true));
        });

        if (window.location.hash === '#attention') {
            setLeadView('attention');
        }
    })();

    (() => {
        const refreshMs = 10000;
        const quietMs = 2000;
        const versionUrl = <?= json_encode(base_url('leads.php?action=pipeline_version'), JSON_UNESCAPED_SLASHES) ?>;
        const scrollKey = 'elite-leads-scroll-y';
        let currentVersion = <?= json_encode($pipelineVersion, JSON_UNESCAPED_SLASHES) ?>;
        let lastInteractionAt = Date.now();
        let refreshPending = false;
        let requestInFlight = false;

        const savedScroll = Number(window.sessionStorage.getItem(scrollKey) || 0);
        if (savedScroll > 0) {
            window.sessionStorage.removeItem(scrollKey);
            window.requestAnimationFrame(() => window.scrollTo(0, savedScroll));
        }

        const markInteraction = () => {
            lastInteractionAt = Date.now();
        };

        ['pointerdown', 'keydown', 'input', 'dragstart', 'drop', 'scroll'].forEach((eventName) => {
            window.addEventListener(eventName, markInteraction, { passive: true, capture: true });
        });

        const isOpen = (selector) => {
            const element = document.querySelector(selector);
            return element && !element.classList.contains('hidden');
        };

        const canRefresh = () => {
            const activeElement = document.activeElement;
            const isEditing = activeElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeElement.tagName);
            if (document.hidden || isEditing) return false;
            if (isOpen('#lead-detail-modal') || isOpen('#new-lead-modal')) return false;
            return Date.now() - lastInteractionAt >= quietMs;
        };

        const reloadPipeline = () => {
            window.sessionStorage.setItem(scrollKey, String(window.scrollY || 0));
            window.location.reload();
        };

        const checkPipelineVersion = async (acceptCurrent = false) => {
            if (requestInFlight || document.hidden) return;
            requestInFlight = true;
            try {
                const response = await fetch(versionUrl, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();
                if (!response.ok || !data.ok || !data.version) return;
                if (acceptCurrent || currentVersion === '') {
                    currentVersion = String(data.version);
                    refreshPending = false;
                    return;
                }
                if (String(data.version) !== currentVersion) {
                    refreshPending = true;
                }
                if (refreshPending && canRefresh()) {
                    reloadPipeline();
                }
            } catch (error) {
                // Keep the current board usable when a background check fails.
            } finally {
                requestInFlight = false;
            }
        };

        window.eliteCheckPipelineVersion = checkPipelineVersion;

        window.addEventListener('crm:notifications-read', (event) => {
            const leadIds = Array.isArray(event.detail?.leadIds) ? event.detail.leadIds : [];
            leadIds.forEach((leadId) => {
                const card = document.querySelector(`[data-lead-id="${Number(leadId)}"]`);
                if (!card) return;
                card.dataset.leadUnreadMessageCount = '0';
                card.querySelectorAll('.lead-unread-badge').forEach((badge) => badge.remove());
            });
            checkPipelineVersion(true);
        });

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) checkPipelineVersion();
        });
        window.setInterval(() => checkPipelineVersion(), refreshMs);
    })();
    </script>
</body>
</html>

