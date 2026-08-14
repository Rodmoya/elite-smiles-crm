<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/social_studio/social_studio_service.php';

require_marketing_access();
require_csrf();
header('Content-Type: application/json; charset=utf-8');

$draftId = (int)post('draft_id', 0);
$result = social_studio_generate_image_for_draft($draftId);
http_response_code(!empty($result['ok']) ? 200 : 422);
echo json_encode([
    'ok' => !empty($result['ok']),
    'draft_id' => $draftId,
    'message' => (string)($result['message'] ?? (!empty($result['ok']) ? 'Image generated.' : 'Image generation failed.')),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
