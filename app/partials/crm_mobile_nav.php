<?php
declare(strict_types=1);

$mobilePrimaryKeys = ['leads', 'dashboard'];
$mobilePrimaryItems = array_values(array_filter(
    $crmNavItems ?? [],
    static fn(array $item): bool => in_array((string)($item['key'] ?? ''), $mobilePrimaryKeys, true)
));
?>

<style>
@media (max-width: 1023px) {
    body { padding-top: 4rem; padding-bottom: calc(4.75rem + env(safe-area-inset-bottom)); }
    body.crm-mobile-menu-open { overflow: hidden; }
    #crm-mobile-drawer[aria-hidden="false"] { opacity: 1; pointer-events: auto; }
    #crm-mobile-drawer[aria-hidden="false"] .crm-mobile-drawer-panel { transform: translateX(0); }
}
@media (max-width: 640px) {
    #pipeline-notifications-menu,
    #dashboard-notifications-menu {
        position: fixed !important;
        top: var(--crm-notifications-top, 4.5rem) !important;
        right: 1rem !important;
        left: 1rem !important;
        z-index: 85 !important;
        width: auto !important;
        max-width: none !important;
        max-height: calc(100dvh - var(--crm-notifications-top, 4.5rem) - 5.5rem);
        overflow: hidden;
    }
    #pipeline-notifications-list,
    #dashboard-notifications-menu > div:last-child {
        max-height: calc(100dvh - var(--crm-notifications-top, 4.5rem) - 10rem) !important;
        overscroll-behavior: contain;
    }
}
</style>

<header class="fixed inset-x-0 top-0 z-[60] flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 shadow-sm backdrop-blur lg:hidden">
    <a href="<?= e(base_url('leads.php')) ?>" class="flex min-w-0 items-center gap-3" aria-label="Elite Smiles leads">
        <img src="<?= e((string)$logoUrl) ?>" alt="Elite Smiles" class="h-auto w-[112px] shrink-0">
        <span class="truncate border-l border-slate-200 pl-3 text-sm font-semibold text-slate-900"><?= e((string)$pageTitle) ?></span>
    </a>
    <div class="flex items-center gap-2">
        <button type="button" data-crm-mobile-ai class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-blue-200 bg-blue-50 text-blue-700 active:bg-blue-100" aria-label="Open Elite AI">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l1.8 4.2L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9l4.2-1.8L12 3z"/><path d="M5 17l1 2.3L8.5 20.5 6 21.7 5 24l-1-2.3-2.5-1.2L4 19.3 5 17z"/></svg>
        </button>
        <button type="button" data-crm-mobile-menu class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 active:bg-slate-100" aria-label="Open navigation" aria-expanded="false" aria-controls="crm-mobile-drawer">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
    </div>
</header>

<nav class="fixed inset-x-0 bottom-0 z-[60] grid grid-cols-4 border-t border-slate-200 bg-white/95 px-2 backdrop-blur lg:hidden" style="padding-bottom: env(safe-area-inset-bottom)" aria-label="Mobile CRM navigation">
    <?php foreach ($mobilePrimaryItems as $item): ?>
        <?php $isActive = $currentPage === $item['key']; ?>
        <a href="<?= e($item['href']) ?>" class="flex min-h-[68px] flex-col items-center justify-center gap-1 rounded-xl text-[11px] font-semibold <?= $isActive ? 'text-blue-700' : 'text-slate-500 active:bg-slate-100' ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= e($item['icon']) ?>"/></svg>
            <span><?= e($item['key'] === 'dashboard' ? 'Today' : $item['label']) ?></span>
        </a>
    <?php endforeach; ?>
    <a href="<?= e(base_url('leads.php#pipeline-search')) ?>" class="flex min-h-[68px] flex-col items-center justify-center gap-1 rounded-xl text-[11px] font-semibold text-slate-500 active:bg-slate-100">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><span>Search</span>
    </a>
    <button type="button" data-crm-mobile-menu class="flex min-h-[68px] flex-col items-center justify-center gap-1 rounded-xl text-[11px] font-semibold text-slate-500 active:bg-slate-100" aria-label="Open more navigation">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg><span>More</span>
    </button>
</nav>

<div id="crm-mobile-drawer" class="pointer-events-none fixed inset-0 z-[80] bg-slate-950/50 opacity-0 transition-opacity duration-200 lg:hidden" aria-hidden="true">
    <button type="button" data-crm-mobile-menu-close class="absolute inset-0 h-full w-full" aria-label="Close navigation"></button>
    <aside class="crm-mobile-drawer-panel absolute inset-y-0 right-0 flex w-[min(88vw,360px)] translate-x-full flex-col bg-white shadow-2xl transition-transform duration-200" style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
            <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Elite Smiles CRM</p><p class="mt-1 text-base font-semibold text-slate-900">Navigate</p></div>
            <button type="button" data-crm-mobile-menu-close class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-600" aria-label="Close navigation">×</button>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto p-3" aria-label="All CRM pages">
            <?php foreach ($crmNavItems as $item): ?>
                <?php $isActive = $currentPage === $item['key']; ?>
                <a href="<?= e($item['href']) ?>" class="flex min-h-12 items-center gap-3 rounded-xl px-4 text-sm font-semibold <?= $isActive ? 'bg-slate-950 text-white' : 'text-slate-700 active:bg-slate-100' ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= e($item['icon']) ?>"/></svg><span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="border-t border-slate-200 p-4"><form method="POST" action="<?= e((string)$logoutAction) ?>"><?= csrf_input() ?><input type="hidden" name="action" value="logout"><button type="submit" class="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Log out</button></form></div>
    </aside>
</div>

<script>
(() => {
    const drawer = document.getElementById('crm-mobile-drawer');
    const menuButtons = document.querySelectorAll('[data-crm-mobile-menu]');
    const setOpen = (open) => {
        if (!drawer) return;
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.classList.toggle('crm-mobile-menu-open', open);
        menuButtons.forEach((button) => button.setAttribute('aria-expanded', open ? 'true' : 'false'));
    };
    menuButtons.forEach((button) => button.addEventListener('click', () => setOpen(true)));
    document.querySelectorAll('[data-crm-mobile-menu-close]').forEach((button) => button.addEventListener('click', () => setOpen(false)));
    document.querySelectorAll('[data-crm-mobile-ai]').forEach((button) => button.addEventListener('click', () => { if (window.eliteAiSetOpen) window.eliteAiSetOpen(true); }));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setOpen(false); });
})();

document.addEventListener('DOMContentLoaded', () => {
    const pairs = [
        ['pipeline-notifications-button', 'pipeline-notifications-menu'],
        ['dashboard-notifications-button', 'dashboard-notifications-menu'],
    ];
    const positionMenu = (button, menu) => {
        if (window.innerWidth > 640) {
            menu.style.removeProperty('--crm-notifications-top');
            return;
        }
        const rect = button.getBoundingClientRect();
        menu.style.setProperty('--crm-notifications-top', `${Math.round(rect.bottom + 8)}px`);
    };
    pairs.forEach(([buttonId, menuId]) => {
        const button = document.getElementById(buttonId);
        const menu = document.getElementById(menuId);
        if (!button || !menu) return;
        button.addEventListener('click', () => positionMenu(button, menu), { capture: true });
        window.addEventListener('resize', () => positionMenu(button, menu), { passive: true });
    });
});
</script>
