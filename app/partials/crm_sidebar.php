<?php
declare(strict_types=1);

$currentPage = $currentPage ?? 'dashboard';
$pageTitle = $pageTitle ?? 'Elite Smiles CRM';
$user = $user ?? (function_exists('auth_user') ? auth_user() : []);
$firstName = trim((string)($user['first_name'] ?? 'User'));
$role = trim((string)($user['role'] ?? 'viewer'));
$logoUrl = $logoUrl ?? base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$logoutAction = $logoutAction ?? ($_SERVER['PHP_SELF'] ?? base_url('dashboard.php'));

$crmNavItems = [
    ['key' => 'dashboard', 'label' => 'Home', 'href' => base_url('dashboard.php'), 'icon' => 'M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V10.5z', 'show' => true],
    ['key' => 'leads', 'label' => 'Leads', 'href' => base_url('leads.php'), 'icon' => 'M4 6h16M4 12h16M4 18h10', 'show' => true],
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
    body.crm-sidebar-collapsed .crm-sidebar-user,
    body.crm-sidebar-collapsed .crm-sidebar-logo-text { display: none; }
    body.crm-sidebar-collapsed .crm-sidebar-link { justify-content: center; }
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

            <div class="crm-sidebar-user border-t border-slate-200 p-4">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-sm font-semibold text-slate-900"><?= e($firstName) ?></p>
                    <p class="mt-1 text-[11px] uppercase tracking-[0.18em] text-slate-500"><?= e($role) ?></p>
                </div>
                <form method="POST" action="<?= e((string)$logoutAction) ?>" class="mt-3">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                        Logout
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
