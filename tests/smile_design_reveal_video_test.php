<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/smile_design/smile_design_service.php';

function smile_video_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$source = file_get_contents(dirname(__DIR__) . '/app/smile_design/smile_design_service.php');
smile_video_expect(is_string($source), 'Smile Design source must be readable.');
$reveal = substr($source, strpos($source, 'function smile_design_generate_case_reveal_video('), strpos($source, 'function smile_design_video_postprocess_commands(') - strpos($source, 'function smile_design_generate_case_reveal_video('));
smile_video_expect(str_contains($reveal, "'model' => 'veo-3.1-fast-generate-preview'"), 'Reveal generation must use Veo Fast even when the server still has a Standard model environment setting.');
smile_video_expect(str_contains($reveal, 'removable dentures, prostheses, dental appliances'), 'The reveal prompt must exclude removable prostheses and appliances.');
smile_video_expect(str_contains($reveal, 'teeth being held, inserted, or removed'), 'The reveal prompt must exclude teeth appearing outside the mouth.');
smile_video_expect(str_contains($reveal, 'if (empty($silentVideo[\'ok\']))'), 'Post-processing failure must not replace an existing video with an unprocessed one.');

$commands = smile_design_video_postprocess_commands('source.mp4', 'ready.mp4');
smile_video_expect(array_keys($commands) === ['h264_crf22', 'stream_copy'], 'Compression must be attempted before a silent stream-copy fallback.');
smile_video_expect(str_contains($commands['h264_crf22'], '-c:v libx264 -preset slow -crf 22 -pix_fmt yuv420p -movflags +faststart'), 'The compressed MP4 must use high-quality H.264 with a front-loaded index.');
foreach ($commands as $command) {
    smile_video_expect(str_contains($command, '-map 0:v:0 -an'), 'Every stored video variant must remove audio.');
}

echo "Smile Design reveal video tests passed.\n";
