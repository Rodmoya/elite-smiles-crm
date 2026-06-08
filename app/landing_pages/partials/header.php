<?php declare(strict_types=1); ?>
<header class="sticky top-0 z-40 border-b border-eliteBorder bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <a href="https://elitesmilesutah.com" title="Elite Smiles Utah — Official Website" class="shrink-0">
            <img src="<?= e($logoUrl) ?>" alt="Elite Smiles Utah" class="h-auto w-[150px] max-w-full sm:w-[180px]">
        </a>
        <button type="button" data-open-quiz="1" data-track="cta_click"
            class="cta-pill inline-flex items-center justify-center bg-eliteRose px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.08em] text-white transition hover:bg-eliteRoseDark sm:px-5 sm:text-sm">
            <?= e($primaryCtaText) ?>
        </button>
    </div>
</header>