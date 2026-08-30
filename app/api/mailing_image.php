<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/mailings/mailing_service.php';

$campaignId = (int)($_GET['campaign_id'] ?? 0);
$campaign = $campaignId > 0 ? mailing_campaign($campaignId) : null;
$key = trim((string)($campaign['image_storage_key'] ?? ''));
$candidatePath = $key !== '' ? social_studio_safe_storage_path($key) : null;
$path = social_studio_verified_storage_file($candidatePath);
if ($path === null) {
    http_response_code(404);
    exit;
}

$ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'svg' => 'image/svg+xml',
    'jpg', 'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    default => 'image/png',
};

header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
if (!social_studio_stream_storage_file($path)) {
    http_response_code(500);
}
