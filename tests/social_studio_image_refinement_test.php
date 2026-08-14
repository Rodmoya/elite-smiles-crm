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

echo "Social Studio image refinement tests passed.\n";
