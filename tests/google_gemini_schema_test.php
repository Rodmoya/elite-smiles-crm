<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/core/google_gemini.php';

function gemini_schema_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$schema = [
    'type' => 'object',
    'additionalProperties' => false,
    'properties' => [
        'items' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => ['name' => ['type' => 'string']],
                'required' => ['name'],
            ],
        ],
    ],
    'required' => ['items'],
];

$sanitized = elite_gemini_response_schema($schema);
gemini_schema_expect(!array_key_exists('additionalProperties', $sanitized), 'Top-level additionalProperties must not be sent to Gemini responseSchema.');
gemini_schema_expect(!array_key_exists('additionalProperties', $sanitized['properties']['items']['items']), 'Nested additionalProperties must not be sent to Gemini responseSchema.');
gemini_schema_expect(($sanitized['properties']['items']['items']['properties']['name']['type'] ?? '') === 'string', 'Supported nested schema properties must be preserved.');
gemini_schema_expect(($sanitized['required'][0] ?? '') === 'items', 'Required fields must be preserved.');

echo "Gemini schema compatibility tests passed.\n";
