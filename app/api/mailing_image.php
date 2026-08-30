<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/mailings/mailing_service.php';

$campaignId = (int)($_GET['campaign_id'] ?? 0);
$campaign = $campaignId > 0 ? mailing_campaign($campaignId) : null;
$key = trim((string)($campaign['image_storage_key'] ?? ''));
$path = $key !== '' ? social_studio_safe_storage_path($key) : null;
// The integer campaign ID selects a DB-owned key; safe_storage_path constrains it to STORAGE_PATH.
if (!$path || !is_file($path)) { // nosemgrep: php.lang.security.injection.tainted-filename.tainted-filename
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
readfile($path); // nosemgrep: php.lang.security.injection.tainted-filename.tainted-filename
