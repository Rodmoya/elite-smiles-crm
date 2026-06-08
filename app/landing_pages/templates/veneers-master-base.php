<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$runtime = [
    'page' => $page ?? [],
    'pageSlug' => $pageSlug ?? ((is_array($page ?? null) ? (string) ($page['slug'] ?? '') : '')),
    'heroImageUrl' => $heroImageUrl ?? '',
    'galleryImages' => $galleryImages ?? [],
    'beforeAfterImage' => $beforeAfterImage ?? '',
    'beforeAfterAlt' => $beforeAfterAlt ?? '',
    'faqItems' => $faqItems ?? [],
];

$landingContext = $landingContext ?? ($ctx ?? landing_pages_build_context(
    (string) ($runtime['pageSlug'] ?? ''),
    is_array($runtime['page']) ? $runtime['page'] : [],
    $runtime
));

$landingView = $landingView ?? landing_pages_build_veneers_view($landingContext, $runtime);
$sections = $landingView['sections'] ?? [];
$sectionOrder = $landingView['section_order'] ?? [];
$head = $head ?? ($landingContext['head'] ?? []);
$logoUrl = $logoUrl ?? ($landingContext['logoUrl'] ?? base_url('assets/img/ES-Logo-Stack-500-x-150-px.png'));
$primaryCtaText = $primaryCtaText ?? ($landingContext['primaryCtaText'] ?? 'Take Advantage of the $750 Offer');
$pageSlug = $pageSlug ?? ($landingContext['slug'] ?? '');
$procedureKey = $procedureKey ?? ($landingContext['procedure_key'] ?? '');
$modal = $modal ?? ($landingContext['modal'] ?? []);
$miniLandingGate = (bool) ($landingContext['miniLandingGate'] ?? false);
$submittedDetailsView = (bool) ($landingContext['submittedDetailsView'] ?? false);
$detailsUrl = (string) ($form_detailsUrl ?? '');

$sectionMap = [
    'hero' => __DIR__ . '/../partials/sections/hero.php',
    'quiz_entry' => __DIR__ . '/../partials/sections/quiz-entry.php',
    'doctor_trust_compact' => __DIR__ . '/../partials/sections/doctor-trust-compact.php',
    'offer' => __DIR__ . '/../partials/sections/offer.php',
    'authority' => __DIR__ . '/../partials/sections/authority.php',
    'premium_smile_design' => __DIR__ . '/../partials/sections/text-block.php',
    'gallery' => __DIR__ . '/../partials/sections/gallery.php',
    'visual_direction' => __DIR__ . '/../partials/sections/gallery.php',
    'before_after' => __DIR__ . '/../partials/sections/before-after.php',
    'reviews' => __DIR__ . '/../partials/sections/reviews.php',
    'cost_value' => __DIR__ . '/../partials/sections/text-block.php',
    'confidence_transformation' => __DIR__ . '/../partials/sections/text-block.php',
    'who_veneers_are_for' => __DIR__ . '/../partials/sections/text-block.php',
    'longform' => __DIR__ . '/../partials/sections/longform.php',
    'location_convenience' => __DIR__ . '/../partials/sections/location-convenience.php',
    'faq' => __DIR__ . '/../partials/sections/faq.php',
    'final_cta' => __DIR__ . '/../partials/sections/final-cta.php',
];

$textBlockSections = [
    'premium_smile_design',
    'cost_value',
    'confidence_transformation',
    'who_veneers_are_for',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require dirname(__DIR__) . '/partials/head-standard.php'; ?>
    <?php lp_tracking_head(); ?>
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
                    eliteSageSoft: '#eef0ec'
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
        *, *::before, *::after { box-sizing: border-box; }
        html, body { max-width: 100%; overflow-x: hidden; }
        img, video, canvas, svg { max-width: 100%; }
        button, input, select, textarea { font: inherit; }
    </style>
</head>
<body class="bg-eliteStone font-sansElite text-eliteInk antialiased<?= $submittedDetailsView ? ' is-submitted-details' : '' ?>">
<div class="min-h-screen bg-eliteStone text-eliteInk antialiased font-sansElite">
    <header class="sticky top-0 z-40 border-b border-eliteBorder bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl flex-col items-stretch justify-between gap-3 px-4 py-3 sm:flex-row sm:items-center sm:gap-4 sm:px-6 lg:px-8">
            <a href="https://elitesmilesutah.com/" class="shrink-0">
                <img
                    src="<?= e((string) $logoUrl) ?>"
                    alt="Elite Smiles"
                    class="h-auto w-[128px] max-w-full sm:w-[180px]"
                >
            </a>

            <?php if (!$miniLandingGate && !$submittedDetailsView): ?>
                <button
                    type="button"
                    data-scroll-intake="1"
                    data-track="intake_cta_click"
                    class="cta-pill inline-flex w-full min-w-0 items-center justify-center bg-eliteRose px-3 py-2.5 text-center text-[11px] font-semibold uppercase leading-tight tracking-[0.04em] text-white transition hover:bg-eliteRoseDark sm:w-auto sm:px-5 sm:text-sm sm:tracking-[0.08em]"
                >
                    <?= e((string) ($landingView['header_cta_text'] ?? 'Reserve Your Private Consultation')) ?>
                </button>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($successMessage !== ''): ?>
        <div class="border-b border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <div class="mx-auto max-w-5xl"><?= e((string) $successMessage) ?></div>
        </div>
    <?php endif; ?>

    <main>
        <?php
        if ($miniLandingGate && $successMessage === '') {
            $visibleSectionOrder = array_values(array_filter($sectionOrder, static fn($name): bool => in_array($name, ['hero', 'doctor_trust_compact'], true)));
        } elseif ($submittedDetailsView) {
            $visibleSectionOrder = array_values(array_filter($sectionOrder, static fn($name): bool => !in_array($name, ['quiz_entry', 'final_cta'], true)));
        } else {
            $visibleSectionOrder = $sectionOrder;
        }
        ?>
        <?php foreach ($visibleSectionOrder as $sectionName): ?>
            <?php
            $section = $sections[$sectionName] ?? null;
            $partial = $sectionMap[$sectionName] ?? null;

            if (!is_array($section) || !is_string($partial) || !is_file($partial)) {
                continue;
            }

            if (array_key_exists('enabled', $section) && $section['enabled'] === false) {
                continue;
            }

            if (in_array($sectionName, $textBlockSections, true)) {
                $section['classes'] = in_array($sectionName, ['premium_smile_design', 'confidence_transformation'], true)
                    ? 'bg-[#faf8f5]'
                    : 'bg-white';
                $section['track'] = $sectionName;
            }

            require $partial;
            ?>
        <?php endforeach; ?>
    </main>

    <footer class="border-t border-eliteBorder bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-sm leading-6 text-slate-600 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <div>
                Elite Smiles by Walter Meden DDS, Draper, Utah. Consultation availability and financing depend on individual qualification and clinical review.
            </div>
            <nav class="flex flex-wrap gap-x-4 gap-y-2 font-medium">
                <a href="https://elitesmilesutah.com/privacy/" class="text-eliteRose underline">Privacy Policy</a>
                <a href="https://elitesmilesutah.com/terms/" class="text-eliteRose underline">Terms</a>
                <a href="https://hi.elitesmilesutah.com/sms-privacy/" class="text-eliteRose underline">SMS Privacy</a>
                <a href="https://hi.elitesmilesutah.com/sms-terms/" class="text-eliteRose underline">SMS Terms</a>
            </nav>
        </div>
    </footer>
</div>

<?php if (!empty($modal['steps']) && !$submittedDetailsView && !$miniLandingGate): ?>
    <?php $totalSteps = count($quizSteps ?? []) + 1; ?>
    <?php require dirname(__DIR__) . '/partials/quiz-modal.php'; ?>
<?php elseif ($submittedDetailsView): ?>
    <script>
    (function () {
        <?php lp_tracking_js_fn(); ?>
        <?php lp_tracking_page_view($ctx ?? []); ?>
    })();
    </script>
<?php endif; ?>
<?php if ($detailsUrl !== ''): ?>
    <script>
    window.setTimeout(function () {
        window.location.href = <?= json_encode($detailsUrl, JSON_UNESCAPED_SLASHES) ?>;
    }, 1100);
    </script>
<?php endif; ?>
</body>
</html>
