<?php
declare(strict_types=1);

/**
 * Elite Smiles Landing Partial
 * File: app/landing_pages/partials/sections/location-convenience.php
 *
 * Shared local convenience section for any procedure.
 * Safe to include even when no location data is present.
 *
 * Expected sources (in priority order):
 * - $sections['location_convenience']
 * - $locationConvenience
 * - $localConvenience
 */

$locationSection = [];

if (isset($sections) && is_array($sections) && isset($sections['location_convenience']) && is_array($sections['location_convenience'])) {
    $locationSection = $sections['location_convenience'];
} elseif (isset($locationConvenience) && is_array($locationConvenience)) {
    $locationSection = $locationConvenience;
} elseif (isset($localConvenience) && is_array($localConvenience)) {
    $locationSection = $localConvenience;
}

$enabled = (bool)($locationSection['enabled'] ?? false);
if (!$enabled) {
    return;
}

$title       = trim((string)($locationSection['title'] ?? 'Conveniently Located in Draper'));
$eyebrow     = trim((string)($locationSection['eyebrow'] ?? 'LOCATION'));
$body        = trim((string)($locationSection['body'] ?? 'Elite Smiles is located in Draper and welcomes patients from nearby Utah communities seeking premium cosmetic and restorative dental care.'));
$ctaLabel    = trim((string)($locationSection['cta_label'] ?? 'Plan Your Visit'));
$ctaHref     = trim((string)($locationSection['cta_href'] ?? '#quiz'));
$driveTime   = trim((string)($locationSection['drive_time'] ?? ''));
$addressLine = trim((string)($locationSection['address'] ?? ''));
$mapEmbedUrl = trim((string)($locationSection['map_embed_url'] ?? ''));
$showMap     = (bool)($locationSection['show_map'] ?? ($mapEmbedUrl !== ''));

$chips = [];
if ($driveTime !== '') {
    $chips[] = $driveTime;
}
if ($addressLine !== '') {
    $chips[] = $addressLine;
}
?>
<section class="lp-section lp-section--location" id="location-convenience">
    <div class="mx-auto max-w-6xl px-4 py-12 md:px-6 md:py-16">
        <div class="overflow-hidden rounded-3xl border border-stone-200 bg-white shadow-sm">
            <div class="grid gap-0 lg:grid-cols-[1.1fr_0.9fr]">
                <div class="p-6 md:p-8 lg:p-10">
                    <?php if ($eyebrow !== ''): ?>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.24em] text-stone-500">
                            <?= htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    <?php endif; ?>

                    <h2 class="text-2xl font-semibold tracking-tight text-stone-900 md:text-3xl">
                        <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                    </h2>

                    <?php if ($body !== ''): ?>
                        <div class="mt-4 max-w-2xl text-base leading-7 text-stone-600">
                            <p><?= nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($chips)): ?>
                        <div class="mt-6 flex flex-wrap gap-2">
                            <?php foreach ($chips as $chip): ?>
                                <span class="inline-flex items-center rounded-full border border-stone-200 bg-stone-50 px-3 py-1 text-sm text-stone-700">
                                    <?= htmlspecialchars($chip, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($ctaLabel !== ''): ?>
                        <div class="mt-8">
                            <a
                                href="<?= htmlspecialchars($ctaHref, ENT_QUOTES, 'UTF-8') ?>"
                                class="inline-flex items-center rounded-full bg-stone-900 px-5 py-3 text-sm font-medium text-white transition hover:bg-stone-800"
                            >
                                <?= htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="min-h-[320px] border-t border-stone-200 bg-stone-100 lg:min-h-full lg:border-l lg:border-t-0">
                    <?php if ($showMap && $mapEmbedUrl !== ''): ?>
                        <iframe
                            src="<?= htmlspecialchars($mapEmbedUrl, ENT_QUOTES, 'UTF-8') ?>"
                            width="100%"
                            height="100%"
                            style="border:0;min-height:320px;"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen
                            title="Map to Elite Smiles"
                        ></iframe>
                    <?php else: ?>
                        <div class="flex h-full min-h-[320px] items-center justify-center p-8 text-center text-sm leading-6 text-stone-500">
                            <div>
                                <p class="font-medium text-stone-700">Map module ready</p>
                                <p class="mt-2">Add <code>map_embed_url</code> in the location section data when you are ready to show a city-specific map.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
