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
$visualPass = ['no_text_or_logo' => true, 'sharp_focus' => true, 'credible_anatomy' => true, 'framing_pass' => true, 'notes' => 'Pass'];
$guardrails = social_studio_draft_guardrails($draft, $visualPass);
social_original_assert((string)$guardrails['status'] === 'pass', 'A compliant original draft should pass guardrails.');
social_original_assert(social_studio_overlay_text_fits($template['elements'][0]), 'Short overlay copy should fit its approved text box.');
$overflow = $template;
$overflow['elements'][0]['text'] = 'THIS LINE IS FAR TOO LONG TO FIT INSIDE THE APPROVED TEXT BOX';
social_original_assert(!social_studio_overlay_template_fits($overflow), 'Overflowing overlay copy must fail deterministic fit validation.');
$unsafe = $draft;
$unsafe['caption'] = 'Guaranteed results for only $999.';
$unsafeGuardrails = social_studio_draft_guardrails($unsafe, $visualPass);
social_original_assert((string)$unsafeGuardrails['status'] === 'review', 'Price or guarantee claims must trigger review.');

$pageSource = file_get_contents(dirname(__DIR__) . '/social-studio.php') ?: '';
$actionSource = file_get_contents(dirname(__DIR__) . '/app/actions/social_studio_generate_original.php') ?: '';
social_original_assert(str_contains($pageSource, 'data-social-mode="original"'), 'Original Creation mode is missing from Social Studio.');
social_original_assert(str_contains($pageSource, 'What should we create?'), 'Original Creation needs a natural-language brief.');
social_original_assert(str_contains($actionSource, 'social_studio_create_original_drafts'), 'Original Creation action is not connected to the service.');

echo "Social Studio original creation tests passed.\n";
