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
$pageSource = file_get_contents(dirname(__DIR__) . '/social-studio.php') ?: '';
$publishActionSource = file_get_contents(dirname(__DIR__) . '/app/actions/social_studio_publish.php') ?: '';
$mediaActionSource = file_get_contents(dirname(__DIR__) . '/app/api/social_studio_media.php') ?: '';
$serviceSource = file_get_contents(dirname(__DIR__) . '/app/social_studio/social_studio_service.php') ?: '';
social_publish_assert(str_contains($publisherSource, "'/media_publish'"), 'Instagram publishing must finalize the media container.');
social_publish_assert(str_contains($publisherSource, "'/photos'"), 'Facebook publishing must use the Page photos endpoint.');
social_publish_assert(str_contains($publisherSource, 'meta_instagram_post_id'), 'Instagram post IDs must be persisted for retry safety.');
social_publish_assert(str_contains($publisherSource, 'meta_facebook_post_id'), 'Facebook post IDs must be persisted for retry safety.');
social_publish_assert(str_contains($pageSource, '>Post now</button>'), 'Stage 3 review cards must expose an immediate Post now action.');
social_publish_assert(str_contains($pageSource, 'name="approve_first" value="1"'), 'Immediate publishing must explicitly request approval before Meta delivery.');
social_publish_assert(str_contains($pageSource, 'id="social-action-loader"'), 'Immediate publish and delete actions must show a centered blocking loader.');
social_publish_assert(str_contains($pageSource, 'data-social-loading-message="Publishing to Facebook and Instagram…"'), 'Immediate publishing must explain what is happening while Meta delivery is in progress.');
social_publish_assert(str_contains($pageSource, 'data-social-loading-message="Deleting draft…"'), 'Draft deletion must explain what is happening while the request is in progress.');
social_publish_assert(str_contains($pageSource, 'data-crm-confirm="Delete this draft?"'), 'Draft deletion must use the shared branded CRM confirmation dialog.');
social_publish_assert(!str_contains($pageSource, 'window.confirm('), 'Social Studio must not use native browser confirmation dialogs.');
social_publish_assert(str_contains($pageSource, 'id="social-toast"'), 'Social Studio flash results must render as a centered toast.');
social_publish_assert(str_contains($pageSource, 'aria-live="<?= $toastIsError ? \'assertive\' : \'polite\' ?>"'), 'The centered result toast must announce success and failure accessibly.');
social_publish_assert(str_contains($pageSource, "document.querySelectorAll('[data-social-action-form]')"), 'Action forms must share double-submit prevention and loader behavior.');
social_publish_assert(str_contains($pageSource, 'if (toast) window.setTimeout(dismissToast, 5000);'), 'The centered result toast must dismiss automatically after five seconds.');
social_publish_assert(str_contains($publishActionSource, "social_studio_update_status(\$draftId, 'approved'"), 'Immediate publishing must approve the reviewed draft before claiming it for Meta delivery.');
social_publish_assert(str_contains($publishActionSource, 'Generate a finished image before posting this draft.'), 'Immediate publishing must fail safely when the finished image is missing.');
social_publish_assert(str_contains($publisherSource, 'function social_studio_meta_prepare_image'), 'Publishing must prepare a Meta-compatible raster image before delivery.');
social_publish_assert(str_contains($publisherSource, "setImageFormat('jpeg')"), 'Exact SVG overlays must be rasterized to JPEG without removing their approved design.');
social_publish_assert(str_contains($mediaActionSource, "filename=\"elite-smiles-post-"), 'The public Meta endpoint must identify the finished media as a JPEG file.');
social_publish_assert(str_contains($serviceSource, '"review", "draft", "publish_failed"'), 'Failed immediate posts must remain visible in the review queue for recovery.');
social_publish_assert(str_contains($publishActionSource, "!empty(\$result['ok']) ? 'published' : 'create'"), 'Failed immediate posts must return to Create and review instead of an empty Published screen.');

$jpegFixture = tempnam(sys_get_temp_dir(), 'social-meta-jpeg-');
social_publish_assert(is_string($jpegFixture), 'A JPEG fixture path must be available.');
file_put_contents($jpegFixture, base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EH//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EH//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EH//2Q==', true));
social_publish_assert(social_studio_meta_prepare_image($jpegFixture) === $jpegFixture, 'Existing JPEG posts must pass through without unnecessary conversion.');
@unlink($jpegFixture);

$workflow = file_get_contents(dirname(__DIR__) . '/.github/workflows/social-studio-publisher.yml') ?: '';
social_publish_assert(str_contains($workflow, '*/5 * * * *'), 'The publishing worker must run every five minutes.');

$serverCron = file_get_contents(dirname(__DIR__) . '/app/cron/social_studio_publisher.php') ?: '';
social_publish_assert(str_contains($serverCron, "PHP_SAPI !== 'cli'"), 'The punctual server publisher must be CLI-only.');
social_publish_assert(str_contains($serverCron, 'social_studio_publish_due(25)'), 'The server publisher must process overdue scheduled posts.');
social_publish_assert(str_contains($serverCron, "exit(!empty(\$result['ok']) ? 0 : 1)"), 'The server publisher must return a failing exit code when Meta publishing fails.');

$httpCron = file_get_contents(dirname(__DIR__) . '/app/api/social_studio_publish_cron.php') ?: '';
social_publish_assert(str_contains($httpCron, "!empty(\$result['ok']) ? 200 : 500"), 'The GitHub backup must fail visibly when a scheduled post cannot publish.');

echo "Social Studio publishing tests passed.\n";
