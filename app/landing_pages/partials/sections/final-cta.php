<?php declare(strict_types=1); ?>

<?php
$sectionData = $section ?? [];

$finalCtaTitle = $finalCtaTitle
    ?? ($sectionData['title'] ?? $sectionData['final_cta_title'] ?? 'Schedule Your Private Consultation');

$finalCtaBody = $finalCtaBody
    ?? ($sectionData['body'] ?? $sectionData['final_cta_body'] ?? '');

$finalCtaButton = $finalCtaButton
    ?? ($sectionData['button_text'] ?? $primaryCtaText ?? 'Reserve Your Private Consultation');
?>

<section class="bg-[#faf8f5]" data-track-section="final_cta">
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
        <div class="rounded-[2rem] bg-eliteInk px-6 py-8 text-white shadow-sm sm:px-8 sm:py-10">
            <h2 class="font-site-serif text-3xl font-semibold leading-tight sm:text-4xl">
                <?= e((string) $finalCtaTitle) ?>
            </h2>

            <?php if (!empty($finalCtaBody)): ?>
                <p class="mt-4 max-w-3xl text-base leading-7 text-white/85 sm:text-lg sm:leading-8">
                    <?= e((string) $finalCtaBody) ?>
                </p>
            <?php endif; ?>

            <div class="mt-6">
                <button
                    type="button"
                    data-open-quiz="1"
                    data-track="cta_click"
                    class="cta-pill inline-flex w-full max-w-[420px] items-center justify-center bg-eliteRose px-4 py-3 text-center text-xs font-semibold uppercase leading-tight tracking-[0.04em] text-white transition hover:bg-eliteRoseDark sm:w-auto sm:px-6 sm:text-sm sm:tracking-[0.06em]"
                >
                    <?= e((string) $finalCtaButton) ?>
                </button>
            </div>
        </div>
    </div>
</section>
