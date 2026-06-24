<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/mobile_ai_auth.php';
require_once __DIR__ . '/app/leads/lead_meta.php';
require_once __DIR__ . '/app/leads/lead_service.php';
require_once __DIR__ . '/app/leads/lead_communications.php';
require_once __DIR__ . '/app/leads/lead_email.php';
require_once __DIR__ . '/app/ai/elite_ai_service.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function elite_ai_api_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = (string) file_get_contents('php://input');
$decoded = $raw !== '' ? json_decode($raw, true) : null;
$request = is_array($decoded) ? $decoded : ($_POST ?: $_GET);

lead_comm_ensure_schema();
lead_email_ensure_schema();
mobile_ai_ensure_schema();
elite_ai_ensure_schema();

$assistantToken = trim((string) ($_SERVER['HTTP_X_ELITE_AI_TOKEN'] ?? $request['assistant_token'] ?? ''));
$user = null;
$surface = 'desktop';

if ($assistantToken !== '' && function_exists('auth_verify_assistant_api_token')) {
    $user = auth_verify_assistant_api_token($assistantToken);
}

if (!$user) {
    $user = auth_user();
}

if ($user && (int) ($user['id'] ?? 0) <= 0 && function_exists('auth_user_id')) {
    $sessionUserId = auth_user_id();
    if ($sessionUserId && $sessionUserId > 0) {
        $user['id'] = $sessionUserId;
    }
}

if (!$user) {
    $user = mobile_ai_auth_user();
    $surface = 'mobile';
}

if (!$user || (int) ($user['id'] ?? 0) <= 0) {
    elite_ai_api_response(['ok' => false, 'message' => 'Unauthorized.'], 401);
}

$request['surface'] = strtolower(trim((string) ($request['surface'] ?? $surface))) === 'mobile' ? 'mobile' : 'desktop';

try {
    $assistantAction = trim((string) ($request['assistant_action'] ?? ''));
    if ($assistantAction !== '') {
        elite_ai_api_response(elite_ai_handle_action_request((array) $user, (array) $request));
    }

    elite_ai_api_response(elite_ai_handle_request((array) $user, (array) $request));
} catch (Throwable $e) {
    esm_log('elite_ai', 'Assistant API request failed.', ['error' => $e->getMessage()]);
    elite_ai_api_response(['ok' => false, 'message' => 'Assistant request failed.'], 500);
}
