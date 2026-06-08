<?php
declare(strict_types=1);

return [
    'slug' => 'veneers',
    'label' => 'Porcelain Veneers',
    'city_label' => '{city_label}',

    /*
     * Template used by renderer.php when no page-level template override exists.
     * Keep this pointed at your veneers master shell.
     */
    'template' => 'veneers-master-base.php',

    /*
     * Default section order for veneer landings.
     * Pages may override this in landing-map.php or angle configs later.
     */
    'section_order' => [
        'hero',
        'quiz_entry',
        'doctor_trust_compact',
        'offer',
        'longform',
        'before_after',
        'authority',
        'reviews',
        'faq',
        'final_cta',
    ],

    /*
     * Shared defaults used by the landing engine.
     */
    'defaults' => [
        'show_offer' => true,
        'show_authority' => true,
        'show_reviews' => true,
        'show_faq' => true,
        'show_final_cta' => true,
        'show_location_convenience' => true,
        'location_section_key' => 'location_convenience',
        'offer_badge' => 'FREE PRIVATE CONSULTATION',
        'offer_cta' => 'Start My Free Veneers Consultation',
    ],

    /*
     * Procedure-level shared references.
     * These should match your shared content registry keys if already used.
     */
    'shared_refs' => [
        'authority_ref' => 'dr_meden_master',
        'reviews_ref' => 'elite_smiles_master',
    ],

    /*
     * Default longform angle when page config does not provide one.
     */
    'default_angle' => 'premium_trust',

    /*
     * CTA text fallback.
     */
    'cta' => [
        'quiz_label' => 'Start My Free Veneers Consultation',
        'button_label' => 'Start My Free Veneers Consultation',
        'final_button_label' => 'Start My Free Veneers Consultation',
    ],

    /*
     * Local module behavior.
     */
    'location' => [
        'enabled' => true,
        'default_variant' => 'convenience',
        'partial' => 'sections/location-convenience.php',
    ],

    /*
     * SEO fallbacks.
     */
    'seo' => [
        'title_template' => 'Porcelain Veneers in {city_label} | Elite Smiles',
        'description_template' => 'Explore porcelain veneers for patients in {city_label} with a natural-looking, doctor-led cosmetic approach at Elite Smiles in Draper.',
        'image' => 'assets/img/landings/veneers-ad-final.png',
    ],
];
