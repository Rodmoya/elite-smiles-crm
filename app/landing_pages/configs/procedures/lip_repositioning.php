<?php declare(strict_types=1);
return [
    'label' => 'Lip Repositioning',
    'template' => 'lip-repositioning-master-base.php',
    'default_form_variant' => 'quiz-standard.php',
    'default_handler' => 'submit-quiz-standard.php',
    'section_order' => ['hero','offer','authority','text_block','reviews','faq','final_cta'],
    'sections' => [
        'hero' => [
            'eyebrow' => 'PREMIUM LIP REPOSITIONING',
            'title' => 'Lip Repositioning in {city_label} — Reduce Your Gummy Smile Naturally',
            'body' => 'Lip repositioning is a minimally invasive procedure to reduce a gummy smile and create a more balanced, confident look. Private consultation with personalized planning.',
            'cta_key' => 'hero',
        ],
        'offer' => [
            'label' => '$750 VALUE',
            'title' => 'What the $750 Offer May Include',
            'body' => 'Our team will review your goals, candidacy, and treatment options in a private, comfortable consultation.',
            'items' => ['Comprehensive lip repositioning consultation','Candidacy review','Treatment planning review','Financial options review'],
            'cta_key' => 'offer',
        ],
        'final_cta' => [
            'label' => 'ELITE SMILES',
            'title' => 'Ready to Explore Lip Repositioning?',
            'body' => 'Take the short quiz and our team will review your goals and best next step.',
            'cta_key' => 'final_cta',
        ],
    ],
];
