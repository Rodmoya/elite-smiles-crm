<?php declare(strict_types=1); ?>
<section class="border-t border-eliteBorder bg-white" data-track-section="qualification">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="rounded-[2rem] bg-eliteStone px-6 py-7 text-center ring-1 ring-eliteBorder sm:px-8 sm:py-9">
            <h2 class="font-site-serif text-3xl font-semibold leading-tight text-eliteInk sm:text-4xl">
                <?= e($qualificationTitle) ?>
            </h2>
            <p class="mt-4 text-base leading-7 text-eliteBody sm:text-lg sm:leading-8">
                <?= e($qualificationText) ?>
            </p>
            <button type="button" data-open-quiz="1" data-track="cta_click"
                class="cta-pill mt-6 inline-flex w-full max-w-[420px] items-center justify-center bg-eliteRose px-6 py-3 text-sm font-semibold uppercase tracking-[0.06em] text-white transition hover:bg-eliteRoseDark">
                <?= e($primaryCtaText) ?>
            </button>
        </div>
    </div>
</section>
