<?php declare(strict_types=1);
return [
    'label' => 'All-on-X',
    'template' => 'all-on-x-master-base.php',
    'default_form_variant' => 'quiz-standard.php',
    'default_handler' => 'submit-quiz-standard.php',
    'default_cta_set' => 'all_on_x_consultation',
    'default_authority_ref' => 'all_on_x_dr_meden',
    'default_reviews_ref' => 'all_on_x_google_proof',
    'section_order' => ['hero','offer','authority','text_block','reviews','faq','final_cta'],
    'sections' => [
        'hero' => [
            'eyebrow' => 'LIMITED-TIME ALL-ON-X OFFER',
            'title' => 'All-on-X Full-Arch Implants in {city_label} — Stability, Confidence, and Function',
            'body' => 'If you are dealing with major tooth loss, failing teeth, or denture frustration, All-on-X may offer a more permanent, confident path forward.',
            'note' => 'Complete the short quiz to explore whether All-on-X may be right for you.',
            'cta_key' => 'hero',
        ],
        'offer' => [
            'label' => '$750 VALUE',
            'title' => 'What the $750 Offer May Include',
            'body' => 'Our team will review your full-arch goals, timing, and whether All-on-X may be the right fit.',
            'items' => ['Comprehensive full-arch consultation','Treatment planning review','Diagnostic imaging or records as clinically appropriate','Financial options review'],
            'cta_key' => 'offer',
        ],
        'final_cta' => [
            'label' => 'ELITE SMILES',
            'title' => 'Ready to Explore Your All-on-X Options?',
            'body' => 'Take the short quiz and our team will review your goals, timing, and best next step.',
            'cta_key' => 'final_cta',
        ],
    ],
];
