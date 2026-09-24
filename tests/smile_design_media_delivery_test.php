<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/smile_design/smile_design_service.php';
require_once dirname(__DIR__) . '/app/partials/smile_before_after_viewer.php';

function media_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

media_expect(smile_design_video_byte_range('bytes=0-1023', 8000) === [0, 1023], 'An opening video range must be parsed.');
media_expect(smile_design_video_byte_range('bytes=4096-', 8000) === [4096, 7999], 'An open-ended video range must finish at EOF.');
media_expect(smile_design_video_byte_range('bytes=-500', 8000) === [7500, 7999], 'A suffix video range must be parsed.');
media_expect(smile_design_video_byte_range('bytes=0-9999', 8000) === [0, 7999], 'A range past EOF must be capped.');
media_expect(smile_design_video_byte_range('bytes=8000-', 8000) === null, 'A range starting at EOF must be rejected.');
media_expect(smile_design_video_byte_range('bytes=100-10', 8000) === null, 'An inverted range must be rejected.');
media_expect(smile_design_video_byte_range('bytes=0-10,20-30', 8000) === null, 'Multiple ranges must not be mis-served as one.');

$endpoint = (string)file_get_contents(dirname(__DIR__) . '/app/actions/smile_design_photo.php');
$gallery = (string)file_get_contents(dirname(__DIR__) . '/smile-design/gallery/index.php');
$patientPreview = (string)file_get_contents(dirname(__DIR__) . '/smile-design/preview.php');
$viewer = (string)file_get_contents(dirname(__DIR__) . '/app/partials/smile_before_after_viewer.php');
media_expect(str_contains($endpoint, 'smile_design_image_delivery_variant($path, $variant)'), 'Photo delivery must use cached display variants.');
media_expect(str_contains($endpoint, "http_response_code(206)"), 'Video requests must return partial-content status.');
media_expect(str_contains($gallery, "smile_design_url_with_variant(\$beforeFullUrl, 'display')"), 'Consult Room should load a bounded delivery image first.');
media_expect(str_contains($gallery, 'syncGalleryMediaQuality(shell)'), 'Consult Room should upgrade to full resolution when needed.');
media_expect(str_contains($gallery, 'loading="lazy" decoding="async"'), 'The Consult Room case picker should defer off-screen thumbnails.');
media_expect(str_contains($gallery, "shell.querySelector('[data-sd-mode=\"ba\"]')"), 'Phone-sized Consult Room should start with the larger B/A slider.');
media_expect(str_contains($patientPreview, "smile_design_after_url((int)\$displayAfter['id'], \$token, 'display')"), 'Shared previews must initially request the lighter after image.');
media_expect(str_contains($patientPreview, "'thumb_url' => smile_design_photo_url((int)\$photo['id'], \$token, 'thumb')"), 'Shared preview angle thumbnails must use the thumbnail variant.');
media_expect(str_contains($viewer, "img.getAttribute('src') !== afterUrl"), 'Viewer startup must not restart an already-loading after image.');
ob_start();
smile_before_after_viewer('https://example.test/before.jpg', 'https://example.test/after.jpg', ['patient_loading' => true]);
$patientViewer = (string)ob_get_clean();
media_expect(str_contains($patientViewer, 'data-sd-patient-loading'), 'A patient preview must render its image-loading state.');
media_expect(str_contains($patientViewer, 'Try loading the photo again'), 'A patient preview must offer image retry without refreshing the page.');

if (extension_loaded('gd')) {
    $directory = sys_get_temp_dir() . '/esm-media-test-' . bin2hex(random_bytes(6));
    media_expect(mkdir($directory, 0700), 'Test image directory must be created.');
    $sourcePath = $directory . '/master.png';
    $image = imagecreatetruecolor(2400, 1600);
    imagefill($image, 0, 0, imagecolorallocate($image, 230, 222, 210));
    media_expect(imagepng($image, $sourcePath), 'Test master must be saved.');
    unset($image);
    $masterHash = hash_file('sha256', $sourcePath);
    $display = smile_design_image_delivery_variant($sourcePath, 'display');
    $thumb = smile_design_image_delivery_variant($sourcePath, 'thumb');
    media_expect(is_array($display) && is_file((string)$display['path']), 'A display copy must be generated.');
    media_expect(is_array($thumb) && is_file((string)$thumb['path']), 'A thumbnail must be generated.');
    media_expect(getimagesize((string)$display['path'])[0] === 2048, 'Display copy must have a 2048-pixel maximum edge.');
    media_expect(getimagesize((string)$thumb['path'])[0] === 480, 'Thumbnail must have a 480-pixel maximum edge.');
    media_expect(hash_file('sha256', $sourcePath) === $masterHash, 'Derivatives must never change the original master.');
    $again = smile_design_image_delivery_variant($sourcePath, 'display');
    media_expect((string)($again['path'] ?? '') === (string)$display['path'], 'Repeated delivery should reuse the cached copy.');
    @unlink((string)$display['path']);
    @unlink((string)$thumb['path']);
    @rmdir($directory . '/.delivery-cache');
    @unlink($sourcePath);
    @rmdir($directory);
}

echo "Smile Design media delivery tests passed.\n";
