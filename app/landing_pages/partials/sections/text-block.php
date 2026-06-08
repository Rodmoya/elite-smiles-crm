<?php declare(strict_types=1); ?>

<?php
$sectionData = $section ?? [];

$textEyebrow = $textEyebrow
    ?? ($sectionData['eyebrow'] ?? '');

$textTitle = $textTitle
    ?? ($sectionData['title'] ?? '');

$textBody = $textBody
    ?? ($sectionData['body'] ?? '');

$textClasses = trim((string) ($sectionData['classes'] ?? 'bg-white'));
$textTrack   = trim((string) ($sectionData['track'] ?? 'text_block'));

if ($textTitle === '' && $textBody === '') {
    return;
}
?>

<section class="<?= e($textClasses) ?>" data-track-section="<?= e($textTrack) ?>">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="rounded-[2rem] bg-white px-6 py-7 ring-1 ring-eliteBorder shadow-sm sm:px-8 sm:py-9">
            <?php if (!empty($textEyebrow)): ?>
                <div class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500 sm:text-[11px]">
                    <?= e((string) $textEyebrow) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($textTitle)): ?>
                <h2 class="mt-3 font-site-serif text-3xl font-semibold leading-tight text-eliteInk sm:text-4xl">
                    <?= e((string) $textTitle) ?>
                </h2>
            <?php endif; ?>

            <?php if (!empty($textBody)): ?>
                <p class="mt-4 text-base leading-7 text-eliteBody sm:text-lg sm:leading-8">
                    <?= e((string) $textBody) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($primaryCtaText)): ?>
                <div class="mt-6">
                    <button
                        type="button"
                        data-open-quiz="1"
                        data-track="cta_click"
                        class="cta-pill inline-flex w-full max-w-[420px] items-center justify-center bg-eliteRose px-6 py-3 text-sm font-semibold uppercase tracking-[0.06em] text-white transition hover:bg-eliteRoseDark sm:w-auto"
                    >
                        <?= e((string) $primaryCtaText) ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
