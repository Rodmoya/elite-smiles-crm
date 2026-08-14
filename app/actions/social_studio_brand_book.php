<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_marketing_access();
require_csrf();

$active = social_studio_active_brand_book();
$rules = (array)($active['rules'] ?? social_studio_brand_book_default());
$hex = static function (string $value, string $fallback): string {
    $value = strtoupper(trim($value));
    return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : $fallback;
};
$number = static fn(string $key, float $fallback): float => max(.8, min(12, (float)post($key, (string)$fallback)));
$lines = static function (string $value): array {
    return array_values(array_filter(array_map('trim', preg_split('/\R/u', $value) ?: []), static fn(string $line): bool => $line !== ''));
};

$rules['colors'] = [
    'ivory' => $hex((string)post('color_ivory'), '#F5F1E9'),
    'warm_white' => $hex((string)post('color_warm_white'), '#FFFDFC'),
    'charcoal' => $hex((string)post('color_charcoal'), '#20252D'),
    'black' => $hex((string)post('color_black'), '#080B12'),
    'champagne_gold' => $hex((string)post('color_champagne_gold'), '#9B794E'),
    'burgundy' => $hex((string)post('color_burgundy'), '#A93455'),
];
$rules['typography']['display_font'] = mb_substr((string)post('display_font'), 0, 180);
$rules['typography']['support_font'] = mb_substr((string)post('support_font'), 0, 180);
$rules['typography']['accent_font'] = mb_substr((string)post('accent_font'), 0, 180);
$rules['typography']['sizes_percent_canvas_width'] = [
    'eyebrow' => $number('size_eyebrow', 2), 'headline' => $number('size_headline', 6.8),
    'subhead' => $number('size_subhead', 3.2), 'body' => $number('size_body', 2.1),
    'cta' => $number('size_cta', 2.4), 'location' => $number('size_location', 1.7),
];
foreach (array_keys((array)($rules['scenarios'] ?? [])) as $scenario) {
    $rules['scenarios'][$scenario] = mb_substr((string)post('scenario_' . $scenario, (string)$rules['scenarios'][$scenario]), 0, 1200);
}
$voice = $lines((string)post('voice', implode("\n", (array)($rules['identity']['voice'] ?? []))));
if ($voice !== []) $rules['identity']['voice'] = $voice;
$rules['copy']['never'] = $lines((string)post('copy_never', implode("\n", (array)($rules['copy']['never'] ?? []))));
$rules['photography']['never'] = $lines((string)post('visual_never', implode("\n", (array)($rules['photography']['never'] ?? []))));

$note = trim((string)post('change_note', ''));
if ($note === '') {
    flash_set('error', 'Describe what changed before activating a new Brand Book version.');
    redirect(base_url('social-studio.php?view=brand-book'));
}

$user = auth_user() ?: [];
$id = social_studio_save_brand_book($rules, $note, (int)($user['id'] ?? 0));
flash_set('success', 'Brand Book updated and activated. New drafts will use version ' . ((int)($active['version'] ?? 1) + 1) . '.');
redirect(base_url('social-studio.php?view=brand-book&brand_book=' . $id));
