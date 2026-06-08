<?php
declare(strict_types=1);

return [
    [
        'field' => 'q_goal',
        'title' => 'What is your main veneers goal?',
        'text' => 'Choose the option that best matches what you want to improve.',
        'options' => [
            'A more polished overall smile',
            'Whiter and brighter front teeth',
            'Fix chips, wear, or uneven edges',
            'Feel more confident smiling',
        ],
    ],
    [
        'field' => 'financing_needed',
        'title' => 'Would you like to review financing options?',
        'text' => 'We can help you understand payment pathways, including 0% financing for qualified patients.',
        'options' => [
            'yes' => 'Yes, I would like to review financing',
            'no' => 'No, I do not think I will need financing',
            'unsure' => 'I am not sure yet',
        ],
    ],
    [
        'field' => 'financing_partner_interest',
        'title' => 'Which option would you like to hear more about?',
        'text' => 'Choose the option that sounds most relevant right now.',
        'options' => [
            '0_percent' => '0% financing for qualified patients',
            'monthly_payments' => 'Monthly payment options',
            'best_fit' => 'Whichever option may fit me best',
            'not_sure' => 'I am not sure yet',
        ],
    ],
    [
        'field' => 'q_timeline',
        'title' => 'How soon are you hoping to get started?',
        'text' => 'This helps us understand your timing and the best next step.',
        'options' => [
            'As soon as possible',
            'Within the next 1 to 3 months',
            'Within the next 6 months',
            'Just researching for now',
        ],
    ],
];
