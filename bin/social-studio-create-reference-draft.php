<?php
declare(strict_types=1);

/**
 * Create a deterministic Social Studio draft that reuses an existing approved
 * base creative overlay. Useful for quick, style-consistent post creation.
 *
 * Usage (example):
 * php bin/social-studio-create-reference-draft.php \
 *   --reference=DZME24slvGK \
 *   --title="Your Confidence Starts With Your Smile" \
 *   --caption="Your confidence starts with a smile you love..."
 *
 * Optional:
 *   --reference-id=49            (numeric base creative id)
 *   --focus=veneers
 *   --purpose=social_ad
 *   --audience=any|woman|man
 *   --text-position=left|right|source
 *   --model=woman|man|mixed|auto
 *   --color=warm_ivory|neutral|dark_luxury|cool_minimal|studio|auto
 *   --hashtags="#EliteSmilesUtah #PorcelainVeneers ..."
 *   --created-by=1
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/social_studio/social_studio_service.php';

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('This script must be run from the command line.');
}

function ss_arg(string $key, ?string $default = null): ?string
{
    global $argv;
    $prefix = '--' . $key . '=';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $prefix)) {
            return (string)substr($arg, strlen($prefix));
        }
    }
    return $default;
}

$referencePostId = (string)ss_arg('reference', 'DZME24slvGK');
$referenceId = (string)ss_arg('reference-id', '');
$focus = (string)ss_arg('focus', 'veneers');
$purpose = (string)ss_arg('purpose', 'social_ad');
$audience = (string)ss_arg('audience', 'any');
$textPosition = (string)ss_arg('text-position', 'left');
$modelProfile = (string)ss_arg('model', 'auto');
$colorMood = (string)ss_arg('color', 'warm_ivory');
$title = (string)ss_arg('title', 'Your Confidence Starts With Your Smile');
$caption = (string)ss_arg('caption', 'Your confidence starts with a smile you love. Elite Smiles creates natural-feeling veneers designed for real people, natural beauty, and confident moments in everyday life. We start with a complimentary consultation to review your goals, options, and timeline.');
$cta = (string)ss_arg('cta', 'Book a Complimentary Consultation');
$hashtags = (string)ss_arg('hashtags', '#EliteSmilesUtah #EliteSmiles #PorcelainVeneers #SmileMakeover #DraperUtah #DentalConfidence');
$createdBy = (int)max(1, (int)(ss_arg('created-by', '1') ?? '1'));
$dryRun = in_array('--dry-run', $argv, true);

social_studio_ensure_schema();

$where = $referenceId !== '' ? 'id = :referenceId' : 'source_post_id = :referencePostId';
$params = $referenceId !== '' ? ['referenceId' => (int)$referenceId] : ['referencePostId' => $referencePostId];
$base = db_one("SELECT * FROM social_studio_base_creatives WHERE {$where} LIMIT 1", $params);
if (!$base) {
    throw new RuntimeException('Reference post not found. Check --reference or --reference-id.');
}

$baseTemplate = json_decode((string)($base['overlay_template_json'] ?? ''), true);
if (!is_array($baseTemplate)) {
    throw new RuntimeException('Reference post does not have a usable overlay template.');
}

if (!in_array($textPosition, ['source', 'left', 'right'], true)) {
    $textPosition = 'left';
}

$positionedTemplate = social_studio_position_overlay_template($baseTemplate, $textPosition);

$hashtags = trim($hashtags);
$hashtags = $hashtags === '' ? social_studio_default_hashtags($focus) : preg_split('/\s+/', $hashtags);
if (!is_array($hashtags) || $hashtags === []) {
    $hashtags = social_studio_default_hashtags($focus);
}

$imagePrompt = sprintf(
    'Clean, editorial dental portrait, %s, premium cosmetic dentistry aesthetic, %s smiling naturally, close-up smile and facial features, natural skin texture, realistic eyes visible, premium lifestyle environment, soft shadows, slightly off-center composition, 4:5 Instagram format. Do not include logos, text, watermarks, icons, or graphic overlays.',
    $colorMood === 'warm_ivory' ? 'warm ivory lighting' : 'clean editorial lighting',
    $modelProfile === 'man' ? 'a man' : 'a woman'
);

$basePrompt = trim((string)($base['base_prompt'] ?? ''));
$overlaySpec = trim((string)($base['overlay_spec'] ?? ''));
$overlayBlocks = json_encode([
    'CUSTOM VENEERS',
    'NATURAL. BEAUTIFUL. YOU.',
    'COMPLIMENTARY CONSULTATION',
    'FLEXIBLE FINANCING OPTIONS',
    'DRAPER, UTAH',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$payload = [
    'title' => $title,
    'platform' => 'facebook_instagram',
    'content_focus' => $focus,
    'post_type' => $purpose === 'social_ad' ? 'social_ad' : 'education',
    'caption' => $caption,
    'cta' => $cta,
    'hashtags' => implode(' ', array_values(array_filter((array)$hashtags, static fn(string $tag): bool => str_starts_with(trim($tag), '#')))),
    'image_prompt' => $imagePrompt,
    'base_reference_key' => 'base_' . (int)($base['id'] ?? 0),
    'base_post_prompt' => $basePrompt,
    'overlay_spec' => $overlaySpec,
    'overlay_eyebrow' => 'YOUR',
    'overlay_blocks_json' => $overlayBlocks,
    'overlay_template_json' => social_studio_encode_overlay_template((array)$positionedTemplate),
    'copy_mode' => 'preserve',
    'text_position' => $textPosition,
    'creation_mode' => 'original',
    'created_by' => $createdBy,
    'reference' => 'base_' . (int)($base['id'] ?? 0),
];

if ($dryRun) {
    echo "DRY RUN" . PHP_EOL;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

$createdId = (int)db_insert(
    "INSERT INTO social_studio_drafts
        (title, status, platform, content_focus, post_type, caption, cta, hashtags, image_prompt, base_reference_key, base_post_prompt, overlay_spec, overlay_eyebrow, overlay_blocks_json, overlay_template_json, copy_mode, text_position, creation_mode, created_by)
     VALUES
        (:title, 'review', :platform, :content_focus, :post_type, :caption, :cta, :hashtags, :image_prompt, :base_reference_key, :base_post_prompt, :overlay_spec, :overlay_eyebrow, :overlay_blocks_json, :overlay_template_json, :copy_mode, :text_position, :creation_mode, :created_by)",
    [
        'title' => $payload['title'],
        'platform' => $payload['platform'],
        'content_focus' => $payload['content_focus'],
        'post_type' => $payload['post_type'],
        'caption' => $payload['caption'],
        'cta' => $payload['cta'],
        'hashtags' => $payload['hashtags'],
        'image_prompt' => $payload['image_prompt'],
        'base_reference_key' => $payload['base_reference_key'],
        'base_post_prompt' => $payload['base_post_prompt'],
        'overlay_spec' => $payload['overlay_spec'],
        'overlay_eyebrow' => $payload['overlay_eyebrow'],
        'overlay_blocks_json' => $payload['overlay_blocks_json'],
        'overlay_template_json' => $payload['overlay_template_json'],
        'copy_mode' => $payload['copy_mode'],
        'text_position' => $payload['text_position'],
        'creation_mode' => $payload['creation_mode'],
        'created_by' => $payload['created_by'],
    ]
);

echo 'created=' . $createdId . PHP_EOL;
echo 'reference=' . $payload['reference'] . PHP_EOL;
echo 'draft_link=' . APP_URL . '/crm/social-studio.php?view=create&mode=original&draft=' . $createdId . PHP_EOL;
