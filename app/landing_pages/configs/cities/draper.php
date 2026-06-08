<?php
declare(strict_types=1);

return [
    'label'            => 'Draper',
    'state'            => 'Utah',
    'seo_title_suffix' => 'Draper, Utah',
    'hero_note'        => 'Serving Draper and nearby South Valley communities.',
    'language'         => [
        'primary'               => 'en',
        'secondary'             => null,
        'strategy'              => 'premium_consultation',
        'demographic_targeting' => 'affluent_family_professional',
    ],

    // Shared city behavior used by all procedures.
    'local_positioning' => [
        'style'                  => 'home_base',
        'why_here'               => 'Elite Smiles is based in Draper, making this the home-market location for patients who want a premium cosmetic consultation close to where they live or work.',
        'travel_frame'           => 'Patients in Draper usually value convenience without giving up clinical quality, especially when the goal is natural-looking cosmetic dentistry.',
        'cta_visit_note'         => 'A private consultation in Draper makes it easier to review goals, options, and next steps in one place.',
        'drive_time_direction'   => 'local',
        'map_enabled'            => true,
        'location_module_variant'=> 'convenience',
    ],

    'intent_modifiers' => [
        'premium_trust' => [
            'local_hook' => 'For Draper patients, choosing the right cosmetic dentist often matters more than choosing the fastest appointment.',
        ],
        'financing' => [
            'local_hook' => 'For Draper patients balancing quality with budget planning, a clear consultation can help make veneers feel more approachable.',
        ],
        'transformation' => [
            'local_hook' => 'For Draper patients who care about confidence in daily life, cosmetic dentistry is often about ease, polish, and feeling comfortable being seen.',
        ],
        'education_comparison' => [
            'local_hook' => 'For Draper patients comparing veneers with whitening or bonding, clarity matters more than pressure.',
        ],
    ],

    'nearby_areas' => [
        'South Jordan',
        'Sandy',
        'Riverton',
        'Herriman',
    ],
];
