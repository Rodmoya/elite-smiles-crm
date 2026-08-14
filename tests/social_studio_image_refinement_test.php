<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/db.php';
require_once dirname(__DIR__) . '/app/social_studio/social_studio_service.php';

function social_refinement_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

social_studio_ensure_schema();
$columns = array_column(db_all('SHOW COLUMNS FROM social_studio_drafts'), 'Field');
foreach (['image_revision_count', 'last_image_revision_instruction', 'image_revision_history_json'] as $column) {
    social_refinement_assert(in_array($column, $columns, true), "Missing image refinement column: {$column}");
}

$draft = [
    'title' => 'Natural Veneers',
    'content_focus' => 'veneers',
    'caption' => 'A natural smile designed for you.',
    'base_post_prompt' => 'Premium close portrait with negative space on the left.',
    'creative_brief_json' => json_encode(['focus' => 'veneers', 'audience' => 'woman', 'age_range' => '35-44']),
];
$instruction = 'Move the camera closer to the smile while keeping both eyes visible.';
$prompt = social_studio_image_revision_prompt($draft, $instruction, 'Premium clean photo with a close, natural smile.');
social_refinement_assert(str_contains($prompt, $instruction), 'The image edit prompt must carry the staff instruction verbatim.');
social_refinement_assert(str_contains($prompt, 'current clean photograph'), 'The prompt must edit the current clean photograph.');
social_refinement_assert(str_contains($prompt, 'overlay copy, typography, font sizes, colors, positions, CTA, caption, and hashtags are locked'), 'Approved post layers must be locked during image refinement.');
social_refinement_assert(str_contains($prompt, 'both eyes visible'), 'Portrait framing guardrails must remain active.');
social_refinement_assert(str_contains($prompt, 'binding production policy'), 'The active Brand Book must be included in every refinement prompt.');

$page = file_get_contents(dirname(__DIR__) . '/social-studio.php') ?: '';
$action = file_get_contents(dirname(__DIR__) . '/app/actions/social_studio_refine_image.php') ?: '';
$service = file_get_contents(dirname(__DIR__) . '/app/social_studio/social_studio_service.php') ?: '';
social_refinement_assert(str_contains($page, 'Refine this image'), 'The full post preview must expose the image refinement composer.');
social_refinement_assert(str_contains($page, 'data-social-refine-form'), 'The refinement composer must provide submit feedback.');
social_refinement_assert(str_contains($page, 'Revision history'), 'The preview must expose saved revision history.');
social_refinement_assert(str_contains($action, 'social_studio_refine_image_for_draft'), 'The refinement action must use the dedicated service workflow.');
social_refinement_assert(str_contains($service, '$referenceImage = $revisionReference'), 'Refinements must send the current clean image back to the image model.');
social_refinement_assert(str_contains($service, 'visible pores, fine expression lines, slight natural asymmetry'), 'Image prompts must explicitly prevent beauty-filter AI portraits.');
social_refinement_assert(str_contains($service, 'subtle translucency'), 'Image prompts must require natural dental translucency.');
social_refinement_assert(str_contains($page, 'Polish typography &amp; layout'), 'Original drafts must expose deterministic editorial layout polish.');
$editorialTemplate = social_studio_editorial_overlay_template($draft + ['creation_mode' => 'original']);
social_refinement_assert(social_studio_overlay_template_fits($editorialTemplate), 'Canonical editorial overlay must fit the 4:5 canvas.');
$displayBlocks = array_filter((array)($editorialTemplate['elements'] ?? []), static fn(array $element): bool => (string)($element['type'] ?? '') === 'text' && (float)($element['font_size'] ?? 0) >= 5);
social_refinement_assert(count($displayBlocks) === 1, 'Canonical editorial overlay must have one dominant display headline.');
$textByValue = [];
foreach ((array)($editorialTemplate['elements'] ?? []) as $element) {
    if ((string)($element['type'] ?? '') === 'text') $textByValue[(string)($element['text'] ?? '')] = $element;
}
social_refinement_assert((float)($textByValue['NATURAL SMILE DESIGN']['font_size'] ?? 0) >= 1.8, 'Editorial eyebrow must remain readable at Instagram review scale.');
social_refinement_assert((float)($textByValue['REFINE SHAPE & SYMMETRY']['font_size'] ?? 0) >= 2.1, 'Editorial benefit copy must remain readable at Instagram review scale.');
social_refinement_assert((int)($textByValue['REFINE SHAPE & SYMMETRY']['font_weight'] ?? 0) >= 700, 'Editorial benefit copy must carry enough visual weight.');
social_refinement_assert((float)($textByValue["COMPLIMENTARY\nCONSULTATION"]['font_size'] ?? 0) >= 3.2, 'Editorial CTA must have clear visual weight.');
social_refinement_assert((float)($textByValue['DRAPER, UTAH']['font_size'] ?? 0) >= 1.7, 'Editorial location must remain readable.');
social_refinement_assert(!isset($textByValue['EXPERTS NEAR YOU.']), 'Canonical layout must not include a tiny redundant footer line.');

echo "Social Studio image refinement tests passed.\n";
