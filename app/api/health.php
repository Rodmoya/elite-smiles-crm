<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/meta/meta_config.php';
require_once dirname(__DIR__) . '/meta/meta_queue.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$provided = trim((string) ($_SERVER['HTTP_X_ELITE_HEALTH_SECRET'] ?? ''));
$expected = trim((string) meta_cfg_meta_webhook_secret());
if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized.']);
    exit;
}

$checks = ['database' => false, 'queue' => false];
try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    $checks['database'] = (bool) $pdo->query('SELECT 1')->fetchColumn();
} catch (Throwable $e) {
    $checks['database'] = false;
}

try {
    meta_queue_ensure_directories();
    $checks['queue'] = is_dir(meta_queue_dir('pending')) && is_writable(meta_queue_dir('pending'));
} catch (Throwable $e) {
    $checks['queue'] = false;
}

$ok = !in_array(false, $checks, true);
http_response_code($ok ? 200 : 503);
echo json_encode(['ok' => $ok, 'service' => 'elite-smiles-crm', 'checks' => $checks], JSON_UNESCAPED_SLASHES);
