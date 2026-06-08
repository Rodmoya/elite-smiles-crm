<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: landing_pages.php
 *
 * Professional landing pages admin panel.
 * Matrix view: procedure tabs x city rows x angle columns.
 * Slide-out detail panel for editing individual pages.
 * Bulk activate/deactivate by procedure or city.
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';

require_auth();

if (!auth_has_role('admin')) {
    http_response_code(403);
    exit('Access denied.');
}

// ── Config ────────────────────────────────────────────────────────────────────
$procedures = [
    'veneers'           => 'Veneers',
    'implants'          => 'Implants',
    'all_on_x'          => 'All-on-X',
    'smile_makeover'    => 'Smile Makeover',
    'lip_repositioning' => 'Lip Repositioning',
];

$cities = [
    'draper'       => 'Draper',
    'lehi'         => 'Lehi',
    'south-jordan' => 'South Jordan',
    'highland'     => 'Highland',
    'alpine'       => 'Alpine',
    'park-city'    => 'Park City',
    'farmington'   => 'Farmington',
    'cedar-hills'  => 'Cedar Hills',
];

$angles = [
    ''                    => 'Base',
    'premium_trust'       => 'Premium Trust',
    'financing'           => 'Financing',
    'transformation'      => 'Transformation',
    'education_comparison'=> 'Education',
];

// ── AJAX / POST handlers ───────────────────────────────────────────────────────
if (is_post()) {
    $action = (string) post('action');
    header('Content-Type: application/json; charset=UTF-8');

    try {
        require_csrf();

        // Toggle single page active/inactive
        if ($action === 'toggle') {
            $id = (int) post('id');
            $row = db_one('SELECT id, is_active FROM landing_pages WHERE id = :id LIMIT 1', ['id' => $id]);
            if (!$row) throw new RuntimeException('Page not found.');
            $new = (int)$row['is_active'] === 1 ? 0 : 1;
            db_execute('UPDATE landing_pages SET is_active = :s, updated_at = NOW() WHERE id = :id', ['s' => $new, 'id' => $id]);
            echo json_encode(['ok' => true, 'is_active' => $new]);
            exit;
        }

        // Bulk activate/deactivate by procedure
        if ($action === 'bulk_procedure') {
            $proc   = (string) post('procedure');
            $status = (int) post('status');
            db_execute('UPDATE landing_pages SET is_active = :s, updated_at = NOW() WHERE procedure_type = :p', ['s' => $status, 'p' => $proc]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // Bulk activate/deactivate by city
        if ($action === 'bulk_city') {
            $city   = (string) post('city');
            $status = (int) post('status');
            db_execute('UPDATE landing_pages SET is_active = :s, updated_at = NOW() WHERE city = :c', ['s' => $status, 'c' => $city]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // Bulk activate/deactivate by angle
        if ($action === 'bulk_angle') {
            $angle  = (string) post('angle');
            $proc   = (string) post('procedure');
            $status = (int) post('status');
            db_execute('UPDATE landing_pages SET is_active = :s, updated_at = NOW() WHERE procedure_type = :p AND angle = :a', ['s' => $status, 'p' => $proc, 'a' => $angle]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // Save page detail (hero image, title, cta override)
        if ($action === 'save') {
            $id = (int) post('id');
            $row = db_one('SELECT id FROM landing_pages WHERE id = :id LIMIT 1', ['id' => $id]);
            if (!$row) throw new RuntimeException('Page not found.');

            db_execute(
                'UPDATE landing_pages SET
                    hero_title       = :hero_title,
                    hero_subtitle    = :hero_subtitle,
                    hero_image       = :hero_image,
                    primary_cta_text = :primary_cta_text,
                    offer_badge      = :offer_badge,
                    offer_title      = :offer_title,
                    offer_description= :offer_description,
                    is_active        = :is_active,
                    updated_at       = NOW()
                WHERE id = :id LIMIT 1',
                [
                    'id'               => $id,
                    'hero_title'       => trim((string) post('hero_title')),
                    'hero_subtitle'    => trim((string) post('hero_subtitle')),
                    'hero_image'       => trim((string) post('hero_image')),
                    'primary_cta_text' => trim((string) post('primary_cta_text')),
                    'offer_badge'      => trim((string) post('offer_badge')),
                    'offer_title'      => trim((string) post('offer_title')),
                    'offer_description'=> trim((string) post('offer_description')),
                    'is_active'        => (int) post('is_active'),
                ]
            );
            echo json_encode(['ok' => true, 'message' => 'Saved successfully.']);
            exit;
        }

        // Load single page data for slide-out panel
        if ($action === 'load') {
            $id  = (int) post('id');
            $row = db_one('SELECT * FROM landing_pages WHERE id = :id LIMIT 1', ['id' => $id]);
            if (!$row) throw new RuntimeException('Page not found.');
            echo json_encode(['ok' => true, 'page' => $row]);
            exit;
        }

        throw new RuntimeException('Unknown action.');

    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ── Load all pages into a matrix ──────────────────────────────────────────────
$allPages = db_all('SELECT id, slug, procedure_type, city, angle, is_active, hero_title, hero_image, updated_at FROM landing_pages ORDER BY procedure_type, city, angle');

// Index: $matrix[procedure][city][angle] = page row
$matrix = [];
$stats  = ['total' => 0, 'active' => 0];

foreach ($allPages as $page) {
    $proc  = (string) ($page['procedure_type'] ?? 'general');
    $city  = (string) ($page['city']           ?? '');
    $angle = (string) ($page['angle']          ?? '');
    $matrix[$proc][$city][$angle] = $page;
    $stats['total']++;
    if ((int)$page['is_active'] === 1) $stats['active']++;
}

// Count leads this month
$leadsThisMonth = (int) db_value(
    "SELECT COUNT(*) FROM leads WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')"
) ?: 0;

$user      = auth_user();
$firstName = $user['first_name'] ?? 'User';
$logoUrl   = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');
$csrfToken = csrf_token();
$activeProcedure = trim((string) ($_GET['proc'] ?? 'veneers'));
if (!isset($procedures[$activeProcedure])) $activeProcedure = 'veneers';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Landing Pages — Elite Smiles CRM</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                eliteRose: '#bc3f60',
                eliteRoseDark: '#a93654',
                eliteInk: '#171717',
                eliteBody: '#333333',
                eliteBorder: '#e7e7e2',
                eliteStone: '#f4f4f1',
            }
        }
    }
};
</script>
<style>
body { font-family: system-ui, -apple-system, sans-serif; }
.cell-active   { background: #d1fae5; border-color: #6ee7b7; }
.cell-inactive { background: #f3f4f6; border-color: #e5e7eb; }
.dot-active    { background: #10b981; }
.dot-inactive  { background: #d1d5db; }
.panel-open    { transform: translateX(0); }
.panel-closed  { transform: translateX(100%); }
</style>
</head>
<body class="bg-gray-50 text-eliteInk antialiased">

<!-- ── HEADER ── -->
<header class="sticky top-0 z-40 border-b border-eliteBorder bg-white shadow-sm">
    <div class="mx-auto flex max-w-screen-xl items-center justify-between px-4 py-3">
        <div class="flex items-center gap-4">
            <img src="<?= e($logoUrl) ?>" alt="Elite Smiles" class="h-8 w-auto">
            <span class="text-sm font-semibold text-gray-700">Landing Pages</span>
        </div>
        <div class="flex items-center gap-6">
            <!-- Stats -->
            <div class="hidden items-center gap-6 sm:flex">
                <div class="text-center">
                    <div class="text-lg font-bold text-eliteInk"><?= $stats['active'] ?></div>
                    <div class="text-xs text-gray-500">Live pages</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold text-eliteInk"><?= $stats['total'] ?></div>
                    <div class="text-xs text-gray-500">Total pages</div>
                </div>
                <div class="text-center">
                    <div class="text-lg font-bold text-eliteRose"><?= $leadsThisMonth ?></div>
                    <div class="text-xs text-gray-500">Leads this month</div>
                </div>
            </div>
            <a href="<?= e(base_url('dashboard.php')) ?>"
                class="rounded-full border border-eliteBorder bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                ← Dashboard
            </a>
        </div>
    </div>
</header>

<!-- ── PROCEDURE TABS ── -->
<div class="border-b border-eliteBorder bg-white">
    <div class="mx-auto max-w-screen-xl px-4">
        <div class="flex gap-1 overflow-x-auto">
            <?php foreach ($procedures as $procKey => $procLabel): ?>
            <?php
                $procPages  = $matrix[$procKey] ?? [];
                $procActive = 0;
                $procTotal  = 0;
                foreach ($procPages as $cityPages) {
                    foreach ($cityPages as $p) {
                        $procTotal++;
                        if ((int)($p['is_active'] ?? 0) === 1) $procActive++;
                    }
                }
            ?>
            <a href="?proc=<?= e($procKey) ?>"
                class="flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition
                    <?= $activeProcedure === $procKey
                        ? 'border-eliteRose text-eliteRose'
                        : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                <?= e($procLabel) ?>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                    <?= $activeProcedure === $procKey ? 'bg-eliteRose text-white' : 'bg-gray-100 text-gray-600' ?>">
                    <?= $procActive ?>/<?= $procTotal ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── BULK ACTIONS BAR ── -->
<div class="border-b border-eliteBorder bg-eliteStone px-4 py-2">
    <div class="mx-auto flex max-w-screen-xl flex-wrap items-center gap-3">
        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Bulk actions for <?= e($procedures[$activeProcedure]) ?>:</span>
        <button onclick="bulkProcedure('<?= e($activeProcedure) ?>', 1)"
            class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
            Activate all
        </button>
        <button onclick="bulkProcedure('<?= e($activeProcedure) ?>', 0)"
            class="rounded-full border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">
            Deactivate all
        </button>
        <span class="text-gray-300">|</span>
        <?php foreach ($cities as $cityKey => $cityLabel): ?>
        <button onclick="bulkCity('<?= e($cityKey) ?>', 1, this)"
            class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100 border border-blue-200">
            + <?= e($cityLabel) ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── MATRIX GRID ── -->
<div class="mx-auto max-w-screen-xl px-4 py-6">
    <div class="overflow-x-auto rounded-2xl border border-eliteBorder bg-white shadow-sm">
        <table class="w-full min-w-[900px] border-collapse text-sm">
            <!-- Column headers (angles) -->
            <thead>
                <tr class="border-b border-eliteBorder bg-eliteStone">
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-32">City</th>
                    <?php foreach ($angles as $angleKey => $angleLabel): ?>
                    <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <div><?= e($angleLabel) ?></div>
                        <div class="mt-1 flex justify-center gap-1">
                            <button onclick="bulkAngle('<?= e($activeProcedure) ?>', '<?= e($angleKey) ?>', 1)"
                                class="rounded px-1.5 py-0.5 text-[10px] font-medium bg-emerald-50 text-emerald-700 hover:bg-emerald-100">on</button>
                            <button onclick="bulkAngle('<?= e($activeProcedure) ?>', '<?= e($angleKey) ?>', 0)"
                                class="rounded px-1.5 py-0.5 text-[10px] font-medium bg-gray-100 text-gray-500 hover:bg-gray-200">off</button>
                        </div>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cities as $cityKey => $cityLabel): ?>
                <tr class="border-b border-eliteBorder hover:bg-gray-50/50">
                    <!-- City label + bulk toggle -->
                    <td class="px-4 py-3">
                        <div class="font-medium text-eliteInk"><?= e($cityLabel) ?></div>
                        <div class="mt-1 flex gap-1">
                            <button onclick="bulkCity('<?= e($cityKey) ?>', 1, this)"
                                class="rounded px-1.5 py-0.5 text-[10px] font-medium bg-emerald-50 text-emerald-700 hover:bg-emerald-100">all on</button>
                            <button onclick="bulkCity('<?= e($cityKey) ?>', 0, this)"
                                class="rounded px-1.5 py-0.5 text-[10px] font-medium bg-gray-100 text-gray-500 hover:bg-gray-200">all off</button>
                        </div>
                    </td>

                    <!-- Angle cells -->
                    <?php foreach ($angles as $angleKey => $angleLabel): ?>
                    <?php
                        $page     = $matrix[$activeProcedure][$cityKey][$angleKey] ?? null;
                        $isActive = $page ? (int)($page['is_active'] ?? 0) === 1 : false;
                        $pageId   = $page ? (int)$page['id'] : 0;
                    ?>
                    <td class="px-3 py-3 text-center">
                        <?php if ($page): ?>
                        <div class="group relative inline-flex flex-col items-center gap-1.5 rounded-xl border p-2 transition cursor-pointer
                            <?= $isActive ? 'cell-active' : 'cell-inactive' ?>"
                            style="min-width: 120px;"
                            id="cell-<?= $pageId ?>">

                            <!-- Status dot + toggle -->
                            <button onclick="togglePage(<?= $pageId ?>, this)"
                                class="flex items-center gap-1.5 text-xs font-medium"
                                title="Click to toggle">
                                <span class="h-2.5 w-2.5 rounded-full dot flex-shrink-0 <?= $isActive ? 'dot-active' : 'dot-inactive' ?>"></span>
                                <span class="status-label"><?= $isActive ? 'Live' : 'Off' ?></span>
                            </button>

                            <!-- Edit button -->
                            <button onclick="openPanel(<?= $pageId ?>)"
                                class="rounded-lg bg-white/80 px-2 py-1 text-[11px] font-medium text-gray-600 hover:bg-white border border-gray-200 transition">
                                Edit
                            </button>

                            <!-- Preview link -->
                            <a href="<?= e(base_url('landing.php?slug=' . urlencode((string)($page['slug'] ?? '')))) ?>"
                                target="_blank"
                                class="text-[10px] text-blue-600 hover:underline truncate max-w-[110px]"
                                title="<?= e((string)($page['slug'] ?? '')) ?>">
                                Preview ↗
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="inline-flex items-center justify-center rounded-xl border border-dashed border-gray-200 p-3 text-xs text-gray-300" style="min-width:120px; min-height: 72px;">
                            No page
                        </div>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Legend -->
    <div class="mt-4 flex items-center gap-6 text-xs text-gray-500">
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full dot-active inline-block"></span> Live — visible to visitors</span>
        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full dot-inactive inline-block"></span> Off — not visible</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded border border-dashed border-gray-300"></span> No page record</span>
    </div>
</div>

<!-- ── SLIDE-OUT DETAIL PANEL ── -->
<div id="overlay" class="fixed inset-0 z-40 bg-black/30 hidden" onclick="closePanel()"></div>

<div id="panel" class="fixed right-0 top-0 z-50 h-full w-full max-w-lg bg-white shadow-2xl transition-transform duration-300 panel-closed overflow-y-auto">
    <div class="flex items-center justify-between border-b border-eliteBorder px-5 py-4">
        <h2 class="text-base font-semibold text-eliteInk" id="panelTitle">Page Details</h2>
        <button onclick="closePanel()" class="rounded-full p-2 hover:bg-gray-100 text-gray-500 text-xl leading-none">&times;</button>
    </div>

    <div id="panelLoading" class="flex items-center justify-center py-16 text-sm text-gray-400">Loading...</div>

    <div id="panelContent" class="hidden px-5 py-5 space-y-5">

        <!-- Status toggle -->
        <div class="flex items-center justify-between rounded-xl bg-eliteStone p-4">
            <div>
                <div class="font-medium text-eliteInk">Page Status</div>
                <div id="panelSlug" class="text-xs text-gray-500 mt-0.5 font-mono"></div>
            </div>
            <label class="relative inline-flex cursor-pointer items-center">
                <input type="checkbox" id="panelIsActive" class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-eliteRose
                    after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                    after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all
                    peer-checked:after:translate-x-5"></div>
                <span class="ml-2 text-sm font-medium text-gray-700" id="panelStatusLabel">Inactive</span>
            </label>
        </div>

        <input type="hidden" id="panelId">

        <!-- Hero image -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Hero Image URL</label>
            <input type="text" id="panelHeroImage" placeholder="assets/img/landings/your-image.jpg"
                class="w-full rounded-xl border border-eliteBorder px-4 py-2.5 text-sm focus:outline-none focus:border-eliteRose">
            <p class="mt-1 text-xs text-gray-400">Relative path from /crm/ or full https:// URL</p>
        </div>

        <!-- Hero title -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Hero Title Override</label>
            <input type="text" id="panelHeroTitle" placeholder="Leave empty to use procedure default"
                class="w-full rounded-xl border border-eliteBorder px-4 py-2.5 text-sm focus:outline-none focus:border-eliteRose">
        </div>

        <!-- Hero subtitle -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Hero Subtitle Override</label>
            <textarea id="panelHeroSubtitle" rows="2" placeholder="Leave empty to use procedure default"
                class="w-full rounded-xl border border-eliteBorder px-4 py-2.5 text-sm focus:outline-none focus:border-eliteRose resize-none"></textarea>
        </div>

        <!-- CTA text -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">CTA Button Text Override</label>
            <input type="text" id="panelCtaText" placeholder="Take Advantage of the $750 Offer"
                class="w-full rounded-xl border border-eliteBorder px-4 py-2.5 text-sm focus:outline-none focus:border-eliteRose">
        </div>

        <!-- Offer badge -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Offer Badge</label>
            <input type="text" id="panelOfferBadge" placeholder="$750 VALUE"
                class="w-full rounded-xl border border-eliteBorder px-4 py-2.5 text-sm focus:outline-none focus:border-eliteRose">
        </div>

        <!-- Offer title -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Offer Title</label>
            <input type="text" id="panelOfferTitle" placeholder="What the $750 Offer May Include"
                class="w-full rounded-xl border border-eliteBorder px-4 py-2.5 text-sm focus:outline-none focus:border-eliteRose">
        </div>

        <!-- Offer description -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Offer Description</label>
            <textarea id="panelOfferDesc" rows="3" placeholder="Leave empty to use procedure default"
                class="w-full rounded-xl border border-eliteBorder px-4 py-2.5 text-sm focus:outline-none focus:border-eliteRose resize-none"></textarea>
        </div>

        <!-- Save + preview buttons -->
        <div class="flex gap-3 pt-2">
            <button onclick="savePage()"
                class="flex-1 rounded-full bg-eliteRose py-2.5 text-sm font-semibold text-white hover:bg-eliteRoseDark transition">
                Save Changes
            </button>
            <a id="panelPreviewLink" href="#" target="_blank"
                class="flex-1 rounded-full border border-eliteBorder bg-white py-2.5 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                Preview ↗
            </a>
        </div>

        <div id="panelMessage" class="hidden rounded-xl px-4 py-3 text-sm font-medium"></div>
    </div>
</div>

<!-- ── JAVASCRIPT ── -->
<script>
const CSRF = '<?= e($csrfToken) ?>';
const BASE = '<?= e(base_url('landing_pages.php')) ?>';
const LANDING_BASE = '<?= e(base_url('landing.php')) ?>';

async function api(payload) {
    const fd = new FormData();
    fd.append('_csrf_token', CSRF);
    for (const [k, v] of Object.entries(payload)) fd.append(k, v);
    const res = await fetch(BASE, { method: 'POST', body: fd });
    return res.json();
}

// Toggle single page
async function togglePage(id, btn) {
    btn.disabled = true;
    const data = await api({ action: 'toggle', id });
    if (!data.ok) { alert(data.message); btn.disabled = false; return; }

    const cell   = document.getElementById('cell-' + id);
    const dot    = btn.querySelector('.dot');
    const label  = btn.querySelector('.status-label');
    const active = data.is_active === 1;

    cell.className  = cell.className.replace(/cell-(active|inactive)/g, active ? 'cell-active' : 'cell-inactive');
    dot.className   = dot.className.replace(/dot-(active|inactive)/g, active ? 'dot-active' : 'dot-inactive');
    label.textContent = active ? 'Live' : 'Off';
    btn.disabled    = false;

    // Update panel if open
    if (document.getElementById('panelId').value == id) {
        document.getElementById('panelIsActive').checked = active;
        document.getElementById('panelStatusLabel').textContent = active ? 'Live' : 'Inactive';
    }
}

// Bulk procedure
async function bulkProcedure(proc, status) {
    if (!confirm((status ? 'Activate' : 'Deactivate') + ' all pages for this procedure?')) return;
    const data = await api({ action: 'bulk_procedure', procedure: proc, status });
    if (data.ok) location.reload();
    else alert(data.message);
}

// Bulk city
async function bulkCity(city, status) {
    if (!confirm((status ? 'Activate' : 'Deactivate') + ' all pages for this city?')) return;
    const data = await api({ action: 'bulk_city', city, status });
    if (data.ok) location.reload();
    else alert(data.message);
}

// Bulk angle
async function bulkAngle(proc, angle, status) {
    if (!confirm((status ? 'Activate' : 'Deactivate') + ' all ' + (angle || 'base') + ' pages?')) return;
    const data = await api({ action: 'bulk_angle', procedure: proc, angle, status });
    if (data.ok) location.reload();
    else alert(data.message);
}

// Open slide-out panel
async function openPanel(id) {
    document.getElementById('overlay').classList.remove('hidden');
    document.getElementById('panel').classList.remove('panel-closed');
    document.getElementById('panel').classList.add('panel-open');
    document.getElementById('panelLoading').classList.remove('hidden');
    document.getElementById('panelContent').classList.add('hidden');
    document.getElementById('panelMessage').classList.add('hidden');

    const data = await api({ action: 'load', id });
    if (!data.ok) { alert(data.message); closePanel(); return; }

    const p = data.page;
    document.getElementById('panelId').value             = p.id;
    document.getElementById('panelTitle').textContent    = p.slug;
    document.getElementById('panelSlug').textContent     = p.slug;
    document.getElementById('panelIsActive').checked     = p.is_active == 1;
    document.getElementById('panelStatusLabel').textContent = p.is_active == 1 ? 'Live' : 'Inactive';
    document.getElementById('panelHeroImage').value      = p.hero_image    || '';
    document.getElementById('panelHeroTitle').value      = p.hero_title    || '';
    document.getElementById('panelHeroSubtitle').value   = p.hero_subtitle || '';
    document.getElementById('panelCtaText').value        = p.primary_cta_text  || '';
    document.getElementById('panelOfferBadge').value     = p.offer_badge   || '';
    document.getElementById('panelOfferTitle').value     = p.offer_title   || '';
    document.getElementById('panelOfferDesc').value      = p.offer_description || '';
    document.getElementById('panelPreviewLink').href     = LANDING_BASE + '?slug=' + encodeURIComponent(p.slug);

    document.getElementById('panelLoading').classList.add('hidden');
    document.getElementById('panelContent').classList.remove('hidden');

    // Sync toggle change
    document.getElementById('panelIsActive').onchange = function() {
        document.getElementById('panelStatusLabel').textContent = this.checked ? 'Live' : 'Inactive';
    };
}

function closePanel() {
    document.getElementById('overlay').classList.add('hidden');
    document.getElementById('panel').classList.add('panel-closed');
    document.getElementById('panel').classList.remove('panel-open');
}

async function savePage() {
    const id = document.getElementById('panelId').value;
    const msg = document.getElementById('panelMessage');
    msg.classList.add('hidden');

    const data = await api({
        action:            'save',
        id:                id,
        hero_image:        document.getElementById('panelHeroImage').value,
        hero_title:        document.getElementById('panelHeroTitle').value,
        hero_subtitle:     document.getElementById('panelHeroSubtitle').value,
        primary_cta_text:  document.getElementById('panelCtaText').value,
        offer_badge:       document.getElementById('panelOfferBadge').value,
        offer_title:       document.getElementById('panelOfferTitle').value,
        offer_description: document.getElementById('panelOfferDesc').value,
        is_active:         document.getElementById('panelIsActive').checked ? 1 : 0,
    });

    msg.classList.remove('hidden', 'bg-green-50', 'text-green-700', 'bg-red-50', 'text-red-700');
    if (data.ok) {
        msg.classList.add('bg-green-50', 'text-green-700');
        msg.textContent = data.message || 'Saved.';
        // Update cell status on grid
        const isActive = document.getElementById('panelIsActive').checked;
        const cell = document.getElementById('cell-' + id);
        if (cell) {
            cell.className = cell.className.replace(/cell-(active|inactive)/g, isActive ? 'cell-active' : 'cell-inactive');
            const dot   = cell.querySelector('.dot');
            const label = cell.querySelector('.status-label');
            if (dot)   dot.className   = dot.className.replace(/dot-(active|inactive)/g, isActive ? 'dot-active' : 'dot-inactive');
            if (label) label.textContent = isActive ? 'Live' : 'Off';
        }
    } else {
        msg.classList.add('bg-red-50', 'text-red-700');
        msg.textContent = data.message || 'Error saving.';
    }
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closePanel(); });
</script>

</body>
</html>
