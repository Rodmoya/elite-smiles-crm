<?php declare(strict_types=1);
return [
    'label' => 'Smile Makeover',
    'template' => 'smile-makeover-master-base.php',
    'default_form_variant' => 'quiz-standard.php',
    'default_handler' => 'submit-quiz-standard.php',
    'section_order' => ['hero','offer','authority','text_block','reviews','faq','final_cta'],
    'sections' => [
        'hero' => [
            'eyebrow' => 'PREMIUM SMILE MAKEOVER',
            'title' => 'A Complete Smile Makeover in {city_label} — Designed Around You',
            'body' => 'A smile makeover combines multiple treatments into one personalized plan. Discover what is possible in a private, pressure-free consultation.',
            'cta_key' => 'hero',
        ],
        'offer' => [
            'label' => '$750 VALUE',
            'title' => 'What the $750 Offer May Include',
            'body' => 'Our team will review your smile goals and help plan the right combination of treatments for your budget and timeline.',
            'items' => ['Comprehensive smile makeover consultation','Multi-treatment planning review','Financial options review','Private personalized experience'],
            'cta_key' => 'offer',
        ],
        'final_cta' => [
            'label' => 'ELITE SMILES',
            'title' => 'Ready to Explore Your Smile Makeover Options?',
            'body' => 'Take the short quiz and our team will review your goals, timing, and the best treatment path for you.',
            'cta_key' => 'final_cta',
        ],
    ],
];
