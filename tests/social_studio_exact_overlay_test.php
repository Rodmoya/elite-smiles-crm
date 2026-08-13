<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/config/config.php';
require dirname(__DIR__) . '/app/social_studio/social_studio_service.php';

function social_studio_exact_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$template = social_studio_normalize_overlay_template([
    'version' => 1,
    'aspect_ratio' => '1:1',
    'canvas_background' => 'transparent',
    'image_fit' => 'cover',
    'elements' => [[
        'type' => 'text', 'text' => "COMPLIMENTARY\nCONSULTATION",
        'x' => 8, 'y' => 70, 'width' => 32, 'height' => 10,
        'font_role' => 'sans', 'font_family' => 'helvetica', 'font_style' => 'normal',
        'font_size' => 2.2, 'font_weight' => 700, 'line_height' => 1.1,
        'letter_spacing' => .08, 'color' => '#111111', 'background_color' => 'transparent',
        'border_color' => 'transparent', 'border_width' => 0, 'border_radius' => 0,
        'align' => 'left', 'uppercase' => true,
    ]],
]);

social_studio_exact_assert(
    social_studio_overlay_template_cta($template, 'fallback') === "COMPLIMENTARY\nCONSULTATION",
    'Template CTA must remain verbatim, including its manual line break.'
);
social_studio_exact_assert(
    social_studio_should_send_reference_image($template),
    'Nano Banana must receive a cleaned source reference so composition remains anchored to the selected ad.'
);
$versionedUrl = social_studio_image_url([
    'id' => 30,
    'image_storage_key' => 'social-studio/draft-30/raw-a.png',
    'branded_image_storage_key' => 'social-studio/draft-30/branded-a.svg',
]);
social_studio_exact_assert(str_contains($versionedUrl, '&v='), 'Regenerated branded previews must use a cache-busting asset URL.');

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nWQAAAAASUVORK5CYII=', true);
$tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'social-studio-exact-' . bin2hex(random_bytes(4));
mkdir($tempRoot, 0777, true);
$rawPath = $tempRoot . DIRECTORY_SEPARATOR . 'raw.png';
$sourcePath = $tempRoot . DIRECTORY_SEPARATOR . 'source.png';
$targetPath = $tempRoot . DIRECTORY_SEPARATOR . 'locked.svg';
file_put_contents($rawPath, (string)$png);
file_put_contents($sourcePath, (string)$png);

social_studio_exact_assert(
    social_studio_create_branded_svg($rawPath, $targetPath, $template, $sourcePath, $template),
    'Reusable overlay SVG should be created.'
);
$svg = (string)file_get_contents($targetPath);
social_studio_exact_assert(substr_count($svg, '<image ') === 1, 'Output must include only the newly generated photo.');
social_studio_exact_assert(!str_contains($svg, 'approved-template-source'), 'Original Instagram pixels must never be embedded in the reusable overlay.');
social_studio_exact_assert(str_contains($svg, '<text '), 'Approved wording must be rendered as an independent overlay element.');
social_studio_exact_assert(str_contains($svg, 'COMPLIMENTARY'), 'Approved overlay wording must remain verbatim in the output.');

$seasonalTemplate = $template;
$seasonalTemplate['elements'][0]['text'] = "UTAH SPRING\nSMILE EVENT";
$replacement = social_studio_replace_overlay_text($seasonalTemplate, 'spring', 'Summer');
social_studio_exact_assert(!empty($replacement['ok']), 'An exact case-insensitive overlay replacement must succeed.');
social_studio_exact_assert(
    (string)$replacement['template']['elements'][0]['text'] === "UTAH Summer\nSMILE EVENT",
    'Text replacement must change only the requested phrase and preserve line structure.'
);
$missingReplacement = social_studio_replace_overlay_text($seasonalTemplate, 'Winter', 'Summer');
social_studio_exact_assert(empty($missingReplacement['ok']), 'A missing source phrase must fail instead of silently changing unrelated copy.');

$multiRegionTemplate = $template;
$multiRegionTemplate['elements'][] = array_merge($template['elements'][0], [
    'text' => 'THE POWER OF VENEERS', 'x' => 7, 'y' => 8, 'width' => 45, 'height' => 12,
]);
$subjectInstruction = social_studio_overlay_subject_instruction($multiRegionTemplate);
social_studio_exact_assert(str_contains($subjectInstruction, 'right-side photo area'), 'A left-side approved overlay must reserve the right side for a complete, unobstructed face.');
social_studio_exact_assert(social_studio_base_is_ready([
    'analysis_version' => 4,
    'overlay_template_json' => social_studio_encode_overlay_template($template),
    'base_prompt' => 'Clean portrait with right-side subject placement.',
]), 'A fully analyzed template with overlay geometry and a clean-photo prompt must be selectable.');
social_studio_exact_assert(!social_studio_base_is_ready([
    'analysis_version' => 0,
    'overlay_template_json' => null,
    'base_prompt' => '',
]), 'A pending Instagram image must never be selectable as a production template.');

$directDraft = [
    'copy_mode' => 'preserve',
    'text_position' => 'source',
    'base_reference_key' => 'base_48',
    'content_focus' => 'veneers',
    'image_prompt' => "Controlled substitutions only — Focus: Veneers; Purpose: educational; Audience: man; Age range: 35-44; Text position: source.\nTreatment-specific subject direction: Warm Draper interior.",
];
social_studio_exact_assert(
    social_studio_should_direct_edit_template($directDraft, $sourcePath),
    'Preserve mode with original text position must edit the complete approved template directly.'
);
$directPrompt = social_studio_direct_template_edit_prompt($directDraft, $template);
social_studio_exact_assert(str_contains($directPrompt, 'PROTECTED DESIGN LOCK'), 'Direct template edits must lock all approved design content.');
social_studio_exact_assert(str_contains($directPrompt, 'Audience: man'), 'Direct template edits must carry the selected demographic into the photo request.');
social_studio_exact_assert(str_contains($directPrompt, 'COMPLIMENTARY'), 'Direct template edits must enumerate approved wording for preservation.');
$replacementDraft = $directDraft;
$replacementDraft['copy_mode'] = 'replace';
$replacementDraft['overlay_template_json'] = social_studio_encode_overlay_template((array)$replacement['template']);
social_studio_exact_assert(social_studio_should_direct_edit_template($replacementDraft, $sourcePath), 'Exact replacement mode must retain direct template editing.');
$replacementPrompt = social_studio_direct_template_edit_prompt($replacementDraft, $seasonalTemplate);
social_studio_exact_assert(str_contains($replacementPrompt, 'PERMITTED TEXT SUBSTITUTION'), 'Direct editing must explicitly isolate the permitted text change.');
social_studio_exact_assert(str_contains($replacementPrompt, 'UTAH Summer'), 'The direct-edit prompt must carry the exact replacement wording.');
$movedDraft = $directDraft; $movedDraft['text_position'] = 'right';
social_studio_exact_assert(
    !social_studio_should_direct_edit_template($movedDraft, $sourcePath),
    'Moving overlay geometry must use editable overlay mode instead of claiming an immutable direct edit.'
);

@unlink($targetPath);
@unlink($sourcePath);
@unlink($rawPath);
@rmdir($tempRoot);

fwrite(STDOUT, "Social Studio exact overlay tests passed.\n");
