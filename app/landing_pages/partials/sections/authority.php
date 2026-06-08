<?php declare(strict_types=1); ?>

<?php
$sectionData = $section ?? [];

$authorityEyebrow = $authorityEyebrow
    ?? ($sectionData['eyebrow'] ?? $sectionData['authority_eyebrow'] ?? 'Why Elite Smiles');

$authorityTitle = $authorityTitle
    ?? ($sectionData['title'] ?? $sectionData['authority_title'] ?? '');

$authorityBody = $authorityBody
    ?? ($sectionData['body'] ?? $sectionData['authority_body'] ?? '');

$authorityItems = $authorityItems
    ?? ($sectionData['items'] ?? $sectionData['authority_items'] ?? []);

if (!is_array($authorityItems)) {
    $authorityItems = [];
}
?>

<section class="bg-white" data-track-section="authority">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="rounded-[2rem] bg-[#faf8f5] px-6 py-7 ring-1 ring-eliteBorder shadow-sm sm:px-8 sm:py-9">
            <?php if (!empty($authorityEyebrow)): ?>
                <div class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500 sm:text-[11px]">
                    <?= e((string) $authorityEyebrow) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($authorityTitle)): ?>
                <h2 class="mt-3 font-site-serif text-3xl font-semibold leading-tight text-eliteInk sm:text-4xl">
                    <?= e((string) $authorityTitle) ?>
                </h2>
            <?php endif; ?>

            <?php if (!empty($authorityBody)): ?>
                <p class="mt-4 text-base leading-7 text-eliteBody sm:text-lg sm:leading-8">
                    <?= e((string) $authorityBody) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($authorityItems)): ?>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <?php foreach ($authorityItems as $item): ?>
                        <?php if (trim((string) $item) === '') continue; ?>
                        <div class="rounded-[1.25rem] bg-white px-4 py-4 text-sm leading-6 text-eliteBody ring-1 ring-eliteBorder">
                            <?= e((string) $item) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
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
