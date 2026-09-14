<style>
    #crm-sidebar > div { min-height:0; }
    #crm-sidebar nav { min-height:0; overflow-y:auto; overscroll-behavior:contain; }
    #crm-sidebar > div > :not(nav) { flex-shrink:0; }
</style>
<script>
(() => {
    const sidebarNav = document.querySelector('#crm-sidebar nav');
    if (!sidebarNav) return;
    const key = 'crm-sidebar-scroll';
    try { sidebarNav.scrollTop = Number(sessionStorage.getItem(key) || 0); } catch (_) {}
    sidebarNav.addEventListener('scroll', () => {
        try { sessionStorage.setItem(key, String(sidebarNav.scrollTop)); } catch (_) {}
    }, {passive:true});
})();
</script>
