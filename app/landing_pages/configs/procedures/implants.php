<?php
declare(strict_types=1);

return [
    'slug' => 'implants',
    'label' => 'Dental Implants',
    'city_label' => '{city_label}',
    'template' => 'implants-master-base.php',
    'section_order' => [
        'hero',
        'offer',
        'authority',
        'longform',
        'gallery',
        'before_after',
        'location_convenience',
        'reviews',
        'faq',
        'final_cta',
    ],
    'defaults' => [
        'show_offer' => true,
        'show_authority' => true,
        'show_reviews' => true,
        'show_faq' => true,
        'show_final_cta' => true,
        'show_location_convenience' => true,
        'location_section_key' => 'location_convenience',
        'offer_badge' => '$750 VALUE',
        'offer_cta' => 'Reserve Your Implant Consultation',
    ],
    'shared_refs' => [
        'authority_ref' => 'implants_dr_meden',
        'reviews_ref' => 'implants_google_proof',
    ],
    'default_angle' => 'premium_trust',
    'cta' => [
        'quiz_label' => 'Reserve Your Implant Consultation',
        'button_label' => 'Reserve Your Implant Consultation',
        'final_button_label' => 'Reserve Your Implant Consultation',
    ],
    'location' => [
        'enabled' => true,
        'default_variant' => 'convenience',
        'partial' => 'sections/location-convenience.php',
    ],
    'seo' => [
        'title_template' => 'Dental Implants in {city_label} | Elite Smiles',
        'description_template' => 'Explore dental implants for patients in {city_label} with a doctor-led, long-term approach to replacement, planning, and candidacy at Elite Smiles in Draper.',
    ],
];
