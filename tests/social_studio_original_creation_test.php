<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/db.php';
require_once dirname(__DIR__) . '/app/social_studio/social_studio_service.php';

function social_original_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

social_studio_ensure_schema();

$schema = social_studio_creative_brief_schema();
social_original_assert(isset($schema['properties']['focus']), 'Creative brief schema must type the treatment focus.');
social_original_assert(in_array('clinical_3d', (array)$schema['properties']['visual_format']['enum'], true), 'Creative brief must support clinical 3D education.');
social_original_assert(in_array('dental_education', (array)$schema['properties']['focus']['enum'], true), 'Creative brief must support general dental education.');

$columns = array_column(db_all('SHOW COLUMNS FROM social_studio_drafts'), 'Field');
foreach (['creation_mode', 'creative_brief_json', 'reference_reason', 'guardrail_json', 'parent_draft_id', 'version_number'] as $column) {
    social_original_assert(in_array($column, $columns, true), "Missing Social Studio lineage column: {$column}");
}
$baseColumns = array_column(db_all('SHOW COLUMNS FROM social_studio_base_creatives'), 'Field');
social_original_assert(in_array('clean_image_key', $baseColumns, true), 'Brand Library must preserve the clean photographic layer separately.');
foreach (social_studio_ready_brand_library() as $readyBase) {
    $readyTemplate = json_decode((string)$readyBase['overlay_template_json'], true);
    social_original_assert(is_array($readyTemplate) && (social_studio_normalize_overlay_template($readyTemplate)['elements'] ?? []) !== [], 'Ready Brand Library choices must have a reusable overlay.');
}

$template = social_studio_normalize_overlay_template([
    'version' => 1,
    'aspect_ratio' => '4:5',
    'elements' => [[
        'type' => 'text', 'text' => 'COMPLIMENTARY CONSULTATION', 'x' => 8, 'y' => 80, 'width' => 35, 'height' => 6,
        'font_role' => 'sans', 'font_family' => 'helvetica', 'font_style' => 'normal', 'font_size' => 2,
        'font_weight' => 600, 'line_height' => 1.2, 'letter_spacing' => 0, 'color' => '#111827',
        'background_color' => 'transparent', 'border_color' => 'transparent', 'border_width' => 0,
        'border_radius' => 0, 'align' => 'left', 'uppercase' => true,
    ]],
]);
$draft = [
    'caption' => 'Learn how thoughtful smile planning supports natural-looking results.',
    'cta' => 'Schedule a complimentary consultation.',
    'overlay_template_json' => social_studio_encode_overlay_template($template),
];
$visualPass = ['no_text_or_logo' => true, 'sharp_focus' => true, 'credible_anatomy' => true, 'realistic_appearance' => true, 'natural_dental_aesthetics' => true, 'framing_pass' => true, 'notes' => 'Pass'];
$guardrails = social_studio_draft_guardrails($draft, $visualPass);
social_original_assert((string)$guardrails['status'] === 'pass', 'A compliant original draft should pass guardrails.');
social_original_assert(social_studio_overlay_text_fits($template['elements'][0]), 'Short overlay copy should fit its approved text box.');
$overflow = $template;
$overflow['elements'][0]['text'] = 'THIS LINE IS FAR TOO LONG TO FIT INSIDE THE APPROVED TEXT BOX';
social_original_assert(!social_studio_overlay_text_fits($overflow['elements'][0]), 'Overflowing overlay copy must be detected for deterministic SVG constraint.');
social_original_assert(social_studio_overlay_template_fits($overflow), 'Constrained text must retain valid approved canvas geometry.');
$fittable = $template;
$fittable['elements'][0]['width'] = 30;
$fitted = social_studio_fit_original_overlay_template($fittable);
social_original_assert($fitted !== [] && social_studio_overlay_template_fits($fitted), 'Original overlays should receive a bounded font-size adjustment when needed.');
social_original_assert((float)$fitted['elements'][0]['font_size'] < (float)$fittable['elements'][0]['font_size'], 'Fit adjustment must preserve typography while reducing only the overflowing size.');
$approximateHeight = $template;
$approximateHeight['elements'][0]['height'] = 1;
social_original_assert(social_studio_overlay_template_fits($approximateHeight), 'Approved source line count must not be rejected because imported height metadata is approximate.');
$unsafe = $draft;
$unsafe['caption'] = 'Guaranteed results for only $999.';
$unsafeGuardrails = social_studio_draft_guardrails($unsafe, $visualPass);
social_original_assert((string)$unsafeGuardrails['status'] === 'review', 'Price or guarantee claims must trigger review.');

$pageSource = file_get_contents(dirname(__DIR__) . '/social-studio.php') ?: '';
$actionSource = file_get_contents(dirname(__DIR__) . '/app/actions/social_studio_generate_original.php') ?: '';
social_original_assert(str_contains($pageSource, 'data-social-mode="original"'), 'Original Creation mode is missing from Social Studio.');
social_original_assert(str_contains($pageSource, 'What should we create?'), 'Original Creation needs a natural-language brief.');
social_original_assert(str_contains($actionSource, 'social_studio_create_original_drafts'), 'Original Creation action is not connected to the service.');
social_original_assert(
    preg_match('/id="social-template-carousel"[^>]*\boverflow-y-hidden\b/', $pageSource) === 1,
    'The horizontal template carousel must not trap vertical page scrolling.'
);
social_original_assert(
    str_contains($pageSource, 'max-h-[92dvh] overflow-y-auto') && str_contains($pageSource, 'lg:overflow-hidden'),
    'The post preview must scroll as one surface on smaller viewports.'
);
social_original_assert(str_contains($pageSource, 'name="novelty_mode"'), 'Original form must expose a creative novelty mode selector.');
social_original_assert(str_contains($pageSource, 'name="novelty_avoid"'), 'Original form must expose a novelty anti-repetition input.');
social_original_assert(str_contains($pageSource, 'name="model_profile"'), 'Original form must expose model profile control.');
social_original_assert(str_contains($pageSource, 'name="color_mood"'), 'Original form must expose color mood control.');
social_original_assert(str_contains($pageSource, 'name="style_reference_mode"'), 'Original form must expose style reference mode control.');
social_original_assert(str_contains($pageSource, 'name="reference_caption"'), 'Original form must expose reference caption field.');
social_original_assert(str_contains($actionSource, 'novelty_mode') && str_contains($actionSource, 'novelty_avoid'), 'Original generation action should pass creative novelty settings.');
social_original_assert(
    str_contains($pageSource, 'data-social-review-list')
    && preg_match('/data-social-review-list[^>]*(?:max-h-|overflow-y-auto)/', $pageSource) !== 1,
    'Review queue must use the document scroll instead of trapping the wheel in a nested desktop scroller.'
);
social_original_assert(
    str_contains($pageSource, "templateCarousel?.addEventListener('wheel'")
    && str_contains($pageSource, "window.scrollBy({top:event.deltaY"),
    'Vertical wheel input over the horizontal template carousel must continue scrolling the page.'
);

echo "Social Studio original creation tests passed.\n";
