<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/leads/lead_agent.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$provided = trim((string) ($_SERVER['HTTP_X_ELITE_CRON_SECRET'] ?? $_GET['secret'] ?? $_POST['secret'] ?? ''));
$configured = trim((string) (defined('ELITE_LEAD_AGENT_CRON_SECRET') ? ELITE_LEAD_AGENT_CRON_SECRET : ''));
if ($configured === '' || !hash_equals($configured, $provided)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized.']);
    exit;
}
if (!lead_agent_enabled() || lead_agent_mode() === 'off') {
    echo json_encode(['ok' => true, 'message' => 'Lead Agent is disabled.', 'processed' => 0]);
    exit;
}

$limit = max(1, min(50, (int) ($_GET['limit'] ?? $_POST['limit'] ?? 20)));
$dryRun = filter_var($_GET['dry_run'] ?? $_POST['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

try {
    file_put_contents('php://output', (string) json_encode(lead_agent_run_due($limit, $dryRun), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
} catch (Throwable $e) {
    esm_log('lead_agent', 'Lead Agent cron failed.', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Lead Agent run failed.']);
}
