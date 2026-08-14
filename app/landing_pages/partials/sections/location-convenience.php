<?php declare(strict_types=1); ?>

<?php
$sectionData = $section ?? [];

$cityLabel = trim((string) ($sectionData['city_label'] ?? ''));
$variant = trim((string) ($sectionData['variant'] ?? 'convenience'));
$travelFrame = trim((string) ($sectionData['travel_frame'] ?? ''));
$convenienceNote = trim((string) ($sectionData['convenience_note'] ?? ''));
$visitPlanningNote = trim((string) ($sectionData['visit_planning_note'] ?? ''));
$address = trim((string) ($sectionData['address'] ?? ''));
$mapEmbedUrl = trim((string) ($sectionData['map_embed_url'] ?? ''));
$showMap = (bool) ($sectionData['show_map'] ?? false);
$travelTime = trim((string) ($sectionData['travel_time'] ?? ''));
$distance = trim((string) ($sectionData['distance'] ?? ''));
$directionsUrl = trim((string) ($sectionData['directions_url'] ?? ''));
$nearbyAreas = is_array($sectionData['nearby'] ?? null) ? $sectionData['nearby'] : [];

if ($cityLabel === '' && $travelFrame === '' && $convenienceNote === '' && $visitPlanningNote === '') {
    return;
}

$eyebrow = 'LOCATION AND VISIT PLANNING';
$title = 'Planning A Visit To Elite Smiles';

if ($variant === 'travel_for_quality') {
    $eyebrow = 'WHY PATIENTS MAKE THE DRIVE';
    $title = 'Some Patients Prioritize The Right Cosmetic Fit';
} elseif ($variant === 'professional_lifestyle') {
    $eyebrow = 'FOR BUSY PROFESSIONALS';
    $title = 'A Thoughtful Consultation Can Be Worth Planning Around';
} elseif ($variant === 'visit_planning') {
    $eyebrow = 'VISIT PLANNING';
    $title = 'Clear Planning Helps The Process Feel Easier';
}
?>

<section class="bg-white" data-track-section="location_convenience">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="rounded-[2rem] bg-[#faf8f5] px-6 py-7 ring-1 ring-eliteBorder shadow-sm sm:px-8 sm:py-9">
            <div class="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-500 sm:text-[11px]">
                <?= e($eyebrow) ?>
            </div>

            <h2 class="mt-3 font-site-serif text-3xl font-semibold leading-tight text-eliteInk sm:text-4xl">
                <?= e($title) ?>
            </h2>

            <?php if ($travelFrame !== ''): ?>
                <p class="mt-4 text-base leading-7 text-eliteBody sm:text-lg sm:leading-8">
                    <?= e($travelFrame) ?>
                </p>
            <?php endif; ?>

            <?php if ($travelTime !== '' || $distance !== ''): ?>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <?php if ($travelTime !== ''): ?>
                        <div class="rounded-[1.25rem] border border-eliteBorder bg-white px-5 py-4">
                            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Typical drive</div>
                            <div class="mt-2 text-lg font-semibold text-eliteInk"><?= e($travelTime) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($distance !== ''): ?>
                        <div class="rounded-[1.25rem] border border-eliteBorder bg-white px-5 py-4">
                            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Approximate distance</div>
                            <div class="mt-2 text-lg font-semibold text-eliteInk"><?= e($distance) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="mt-3 text-xs leading-5 text-slate-500">Travel estimates are general planning ranges, not live traffic guarantees.</p>
            <?php endif; ?>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <?php if ($convenienceNote !== ''): ?>
                    <div class="rounded-[1.5rem] bg-white px-5 py-5 ring-1 ring-eliteBorder">
                        <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500 sm:text-[11px]">
                            Convenience
                        </div>
                        <p class="mt-3 text-sm leading-7 text-eliteBody sm:text-base">
                            <?= e($convenienceNote) ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($visitPlanningNote !== ''): ?>
                    <div class="rounded-[1.5rem] bg-white px-5 py-5 ring-1 ring-eliteBorder">
                        <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500 sm:text-[11px]">
                            Planning
                        </div>
                        <p class="mt-3 text-sm leading-7 text-eliteBody sm:text-base">
                            <?= e($visitPlanningNote) ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($showMap && $mapEmbedUrl !== ''): ?>
                <div class="mt-6 overflow-hidden rounded-[1.75rem] border border-eliteBorder bg-white shadow-sm">
                    <div class="border-b border-eliteBorder bg-[#f6f1ea] px-5 py-4">
                        <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500 sm:text-[11px]">
                            Office Location
                        </div>
                        <div class="mt-2 text-sm leading-6 text-eliteBody sm:text-[15px]">
                            <?= e($address !== '' ? $address : 'Elite Smiles, Draper, Utah') ?>
                        </div>
                    </div>
                    <iframe
                        src="<?= e($mapEmbedUrl) ?>"
                        class="block aspect-[16/8] w-full"
                        style="border:0;"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        allowfullscreen
                        title="Map to Elite Smiles"
                    ></iframe>
                </div>
            <?php endif; ?>

            <?php if ($nearbyAreas !== []): ?>
                <div class="mt-5 text-sm leading-6 text-slate-600">
                    <span class="font-semibold text-eliteInk">Nearby communities and route areas:</span>
                    <?= e(implode(', ', array_map('strval', $nearbyAreas))) ?>
                </div>
            <?php endif; ?>

            <?php if ($directionsUrl !== ''): ?>
                <div class="mt-6">
                    <a
                        href="<?= e($directionsUrl) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        data-track="directions_click"
                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-full border border-eliteRose bg-white px-6 py-3 text-center text-sm font-semibold text-eliteRose transition hover:bg-eliteRose hover:text-white sm:w-auto"
                    >Check Current Traffic in Google Maps</a>
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
