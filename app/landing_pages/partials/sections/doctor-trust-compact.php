<?php declare(strict_types=1); ?>

<?php
$items = [
    'Reviewed personally by Dr. Walter Meden DDS',
    'Advanced aesthetic training through LVI',
    '20+ years of cosmetic dentistry experience',
    'Draper, Utah',
];
?>

<section class="bg-white" data-track-section="doctor_trust_compact">
    <div class="mx-auto max-w-5xl px-4 pb-6 sm:px-6 sm:pb-8 lg:px-8">
        <div class="rounded-[1.5rem] border border-eliteBorder bg-white px-5 py-5 shadow-sm sm:rounded-[2rem] sm:px-7">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <?php foreach ($items as $item): ?>
                    <div class="min-w-0 rounded-[1.1rem] bg-[#faf8f5] px-4 py-3 text-sm font-medium leading-6 text-eliteInk">
                        <?= e($item) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
