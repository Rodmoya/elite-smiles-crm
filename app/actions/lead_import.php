<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../leads/lead_meta.php';
require_once __DIR__ . '/../leads/lead_service.php';

header('Content-Type: application/json; charset=UTF-8');

function lead_import_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!is_post()) {
    lead_import_json_response(['ok' => false, 'message' => 'Invalid request method.'], 405);
}

if (!is_logged_in()) {
    lead_import_json_response(['ok' => false, 'message' => 'Unauthorized.'], 401);
}

try {
    require_csrf();
} catch (Throwable $e) {
    lead_import_json_response(['ok' => false, 'message' => 'Invalid security token.'], 419);
}

$rawRows = trim((string) post('rows_json', ''));
$rows = $rawRows !== '' ? json_decode($rawRows, true) : [];
if (!is_array($rows) || $rows === []) {
    lead_import_json_response(['ok' => false, 'message' => 'No lead rows were provided.'], 422);
}

$user = auth_user();
$result = lead_import_meta_rows($rows, is_array($user) ? $user : []);

lead_import_json_response([
    'ok' => true,
    'message' => 'Lead import completed.',
    'result' => $result,
]);
