<?php
declare(strict_types=1);

$landingContext = $landingContext ?? ($ctx ?? []);
$sections = is_array($landingContext['sections'] ?? null) ? $landingContext['sections'] : [];
$sectionOrder = is_array($landingContext['section_order'] ?? null) ? $landingContext['section_order'] : [];
$head = is_array($landingContext['head'] ?? null) ? $landingContext['head'] : [];
$logoUrl = (string) ($landingContext['logoUrl'] ?? base_url('assets/img/ES-Logo-Stack-500-x-150-px.png'));
$primaryCtaText = (string) ($landingContext['primaryCtaText'] ?? 'Request My Complimentary Consultation');
$pageSlug = (string) ($landingContext['slug'] ?? '');
$procedureKey = (string) ($landingContext['procedure_key'] ?? '');
$procedureLabel = (string) ($landingContext['procedureLabel'] ?? 'Dental Treatment');
$modal = is_array($landingContext['modal'] ?? null) ? $landingContext['modal'] : [];
$submittedDetailsView = (bool) ($landingContext['submittedDetailsView'] ?? false);
$detailsUrl = (string) ($form_detailsUrl ?? '');

$partialBase = dirname(__DIR__) . '/partials/sections';
$sectionMap = [
    'hero' => $partialBase . '/hero.php',
    'local_intro' => $partialBase . '/text-block.php',
    'longform' => $partialBase . '/longform.php',
    'offer' => $partialBase . '/offer.php',
    'authority' => $partialBase . '/authority.php',
    'location_convenience' => $partialBase . '/location-convenience.php',
    'reviews' => $partialBase . '/reviews.php',
    'faq' => $partialBase . '/faq.php',
    'final_cta' => $partialBase . '/final-cta.php',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require dirname(__DIR__) . '/partials/head-standard.php'; ?>
    <?php lp_tracking_head(); ?>
    <script>
    window.eliteLandingTracking = {
        endpoint: <?= json_encode(base_url('app/api/landing_event.php'), JSON_UNESCAPED_SLASHES) ?>,
        csrf: <?= json_encode(function_exists('csrf_token') ? (string) csrf_token() : '', JSON_UNESCAPED_SLASHES) ?>
    };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    eliteRose: '#bc3f60',
                    eliteRoseDark: '#96334d',
                    eliteInk: '#171717',
                    eliteBody: '#333333',
                    eliteBorder: '#e7e7e2',
                    eliteStone: '#f4f4f1'
                },
                fontFamily: {
                    sansElite: ['Montserrat', 'system-ui', 'sans-serif'],
                    siteSerif: ['Playfair Display', 'Georgia', 'serif']
                }
            }
        }
    };
    </script>
    <style>
        *,*::before,*::after{box-sizing:border-box}html{scroll-behavior:smooth}html,body{max-width:100%;overflow-x:hidden}
        img,iframe{max-width:100%}button,input,select,textarea{font:inherit}.cta-pill{border-radius:999px;min-height:44px}
        :focus-visible{outline:3px solid #bc3f60;outline-offset:3px}@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*::before,*::after{transition:none!important;animation:none!important}}
    </style>
</head>
<body class="bg-eliteStone font-sansElite text-eliteInk antialiased">
<div class="min-h-screen bg-eliteStone">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-3">Skip to content</a>
    <header class="sticky top-0 z-40 border-b border-eliteBorder bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
            <a href="https://elitesmilesutah.com/" aria-label="Elite Smiles home" class="shrink-0">
                <img src="<?= e($logoUrl) ?>" alt="Elite Smiles by Walter Meden DDS" width="180" height="54" class="h-auto w-[132px] sm:w-[180px]">
            </a>
            <button type="button" data-open-quiz="1" data-track="header_cta_click" class="cta-pill inline-flex max-w-[220px] items-center justify-center bg-eliteRose px-4 py-2.5 text-center text-[11px] font-semibold uppercase leading-tight tracking-[0.04em] text-white transition hover:bg-eliteRoseDark sm:max-w-none sm:px-5 sm:text-sm">
                <?= e($primaryCtaText) ?>
            </button>
        </div>
    </header>

    <?php if ($successMessage !== ''): ?>
        <div role="status" class="border-b border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            <div class="mx-auto max-w-5xl"><?= e($successMessage) ?></div>
        </div>
    <?php elseif ($errorMessage !== ''): ?>
        <div role="alert" class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
            <div class="mx-auto max-w-5xl"><?= e($errorMessage) ?></div>
        </div>
    <?php endif; ?>

    <main id="main-content" tabindex="-1">
        <?php foreach ($sectionOrder as $sectionName): ?>
            <?php
            $section = is_array($sections[$sectionName] ?? null) ? $sections[$sectionName] : [];
            $partial = $sectionMap[$sectionName] ?? '';
            if ($section === [] || !is_file($partial) || (array_key_exists('enabled', $section) && $section['enabled'] === false)) {
                continue;
            }
            require $partial;
            ?>
        <?php endforeach; ?>
    </main>

    <footer class="border-t border-eliteBorder bg-white">
        <div class="mx-auto grid max-w-7xl gap-5 px-4 py-8 text-sm leading-6 text-slate-600 sm:px-6 md:grid-cols-2 lg:px-8">
            <div>
                <strong class="text-eliteInk">Elite Smiles by Walter Meden DDS</strong><br>
                11762 South State Street, Suite 300, Draper, UT 84020<br>
                Information on this page is educational and does not replace an examination or diagnosis.
            </div>
            <nav aria-label="Legal" class="flex flex-wrap content-start gap-x-4 gap-y-2 md:justify-end">
                <a href="https://elitesmilesutah.com/privacy/" class="font-medium text-eliteRose underline">Privacy</a>
                <a href="https://elitesmilesutah.com/terms/" class="font-medium text-eliteRose underline">Terms</a>
                <a href="https://hi.elitesmilesutah.com/sms-privacy/" class="font-medium text-eliteRose underline">SMS Privacy</a>
                <a href="https://hi.elitesmilesutah.com/sms-terms/" class="font-medium text-eliteRose underline">SMS Terms</a>
            </nav>
        </div>
    </footer>
</div>

<?php if (!empty($modal['steps']) && !$submittedDetailsView): ?>
    <?php $totalSteps = count($quizSteps ?? []) + 1; ?>
    <?php require dirname(__DIR__) . '/partials/quiz-modal.php'; ?>
<?php else: ?>
    <script>
    (function () {
        <?php lp_tracking_js_fn(); ?>
        <?php lp_tracking_page_view($landingContext); ?>
        const leadId = <?= json_encode((string) ($submittedLeadId ?? ''), JSON_UNESCAPED_SLASHES) ?>;
        if (!leadId) return;
        const key = 'elite_lead_conversion_' + leadId;
        if (window.sessionStorage && sessionStorage.getItem(key) === '1') return;
        if (window.sessionStorage) sessionStorage.setItem(key, '1');
        const payload = Object.assign({}, <?= json_encode($attribution ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}' ?>, {
            landing_page: <?= json_encode($pageSlug, JSON_UNESCAPED_SLASHES) ?>,
            procedure_type: <?= json_encode($procedureKey, JSON_UNESCAPED_SLASHES) ?>,
            lead_id: leadId
        });
        trackEvent('lead_success', payload);
        trackEvent('generate_lead', payload);
    })();
    </script>
<?php endif; ?>
<?php if ($detailsUrl !== ''): ?>
    <script>
    window.setTimeout(function () {
        window.location.href = <?= json_encode($detailsUrl, JSON_UNESCAPED_SLASHES) ?>;
    }, 900);
    </script>
<?php endif; ?>
</body>
</html>
