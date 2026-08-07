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
    'Pixel-locked SVG should be created.'
);
$svg = (string)file_get_contents($targetPath);
social_studio_exact_assert(substr_count($svg, '<image ') === 2, 'Pixel-locked output must include the generated photo and original template pixels.');
social_studio_exact_assert(substr_count($svg, '<use href="#approved-template-source"') === 1, 'Each approved overlay element must reuse the saved template pixels.');
social_studio_exact_assert(!str_contains($svg, '<text '), 'Preserve mode must not reconstruct approved text with substitute fonts.');
social_studio_exact_assert(str_contains($svg, 'viewBox="0.065 0.69 1 1"'), 'Source artwork region must remain tied to the approved template coordinates.');

$multiRegionTemplate = $template;
$multiRegionTemplate['elements'][] = array_merge($template['elements'][0], [
    'text' => 'THE POWER OF VENEERS', 'x' => 7, 'y' => 8, 'width' => 45, 'height' => 12,
]);
$regions = social_studio_overlay_pixel_regions($multiRegionTemplate, $multiRegionTemplate);
social_studio_exact_assert(count($regions) === 2, 'Main artwork and bottom CTA must be preserved as coherent regions instead of text strips.');

@unlink($targetPath);
@unlink($sourcePath);
@unlink($rawPath);
@rmdir($tempRoot);

fwrite(STDOUT, "Social Studio exact overlay tests passed.\n");
