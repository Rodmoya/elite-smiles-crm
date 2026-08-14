<?php
declare(strict_types=1);

/**
 * Organic landing registry.
 *
 * One canonical page is published for every treatment/city pair. Historical
 * marketing-angle URLs remain registered so the router can permanently redirect
 * old links, but they are not independently indexable or active.
 */
$procedures = [
    'veneers' => 'veneers-premium-trust.php',
    'implants' => 'implants-premium-trust.php',
    'all_on_x' => 'organic-consultation.php',
    'smile_makeover' => 'organic-consultation.php',
    'lip_repositioning' => 'organic-consultation.php',
];
$cities = ['draper', 'lehi', 'south-jordan', 'highland', 'alpine', 'park-city', 'farmington', 'cedar-hills'];
$angles = ['premium_trust', 'financing', 'transformation', 'education_comparison'];
$map = [];

foreach ($procedures as $procedure => $baseQuestionSet) {
    $procedureSlug = str_replace('_', '-', $procedure);

    foreach ($cities as $city) {
        $baseSlug = $procedureSlug . '-' . $city . '-v1';
        $map[$baseSlug] = [
            'procedure' => $procedure,
            'city' => $city,
            'angle' => null,
            'is_active' => true,
            'question_set' => $baseQuestionSet,
            'form_variant' => 'quiz-standard.php',
            'handler' => 'submit-quiz-standard.php',
            'source' => 'organic',
            'source_medium' => 'organic_search',
            'source_type' => 'local_seo',
        ];

        foreach ($angles as $angle) {
            $angleSlug = str_replace('_', '-', $angle);
            $questionSet = in_array($procedure, ['veneers', 'implants'], true)
                ? $procedureSlug . '-' . $angleSlug . '.php'
                : 'organic-consultation.php';
            $map[$procedureSlug . '-' . $city . '-' . $angleSlug . '-v1'] = [
                'procedure' => $procedure,
                'city' => $city,
                'angle' => $angle,
                'is_active' => false,
                'canonical_slug' => $baseSlug,
                'question_set' => $questionSet,
                'form_variant' => 'quiz-standard.php',
                'handler' => 'submit-quiz-standard.php',
            ];
        }
    }
}

// Preserve the previous Google Ads URL as a redirect alias to the Draper organic page.
$map['veneers-draper-google-v2'] = [
    'procedure' => 'veneers',
    'city' => 'draper',
    'angle' => 'legacy_google',
    'is_active' => false,
    'canonical_slug' => 'veneers-draper-v1',
    'question_set' => 'veneers-premium-trust.php',
    'form_variant' => 'quiz-standard.php',
    'handler' => 'submit-quiz-standard.php',
];

return $map;
