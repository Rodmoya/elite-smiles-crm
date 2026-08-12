<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/social_studio/social_studio_publisher.php';

function social_publish_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$caption = social_studio_meta_caption([
    'caption' => "Approved caption.\nSecond line.",
    'hashtags' => '#EliteSmiles #Veneers',
]);
social_publish_assert(
    $caption === "Approved caption.\nSecond line.\n\n#EliteSmiles #Veneers",
    'The publisher must preserve the approved caption and hashtag blocks.'
);

$signature = social_studio_media_signature(34, 2000000000, 'test-signing-key');
social_publish_assert(strlen($signature) === 64, 'Signed media URLs must use a SHA-256 HMAC.');
social_publish_assert(
    hash_equals($signature, social_studio_media_signature(34, 2000000000, 'test-signing-key')),
    'Signed media URL signatures must be deterministic.'
);
social_publish_assert(
    !hash_equals($signature, social_studio_media_signature(35, 2000000000, 'test-signing-key')),
    'A signature must be bound to one draft.'
);

$publisherSource = file_get_contents(dirname(__DIR__) . '/app/social_studio/social_studio_publisher.php') ?: '';
social_publish_assert(str_contains($publisherSource, "'/media_publish'"), 'Instagram publishing must finalize the media container.');
social_publish_assert(str_contains($publisherSource, "'/photos'"), 'Facebook publishing must use the Page photos endpoint.');
social_publish_assert(str_contains($publisherSource, 'meta_instagram_post_id'), 'Instagram post IDs must be persisted for retry safety.');
social_publish_assert(str_contains($publisherSource, 'meta_facebook_post_id'), 'Facebook post IDs must be persisted for retry safety.');

$workflow = file_get_contents(dirname(__DIR__) . '/.github/workflows/social-studio-publisher.yml') ?: '';
social_publish_assert(str_contains($workflow, '*/5 * * * *'), 'The publishing worker must run every five minutes.');

echo "Social Studio publishing tests passed.\n";
