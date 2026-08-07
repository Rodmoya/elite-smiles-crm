<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__) . '/app/config/config.php';
require dirname(__DIR__) . '/app/leads/lead_agent.php';

$options = getopt('', ['limit::', 'dry-run::']);
$limit = max(1, min(50, (int) ($options['limit'] ?? 20)));
$dryRunOption = $options['dry-run'] ?? null;
$dryRun = array_key_exists('dry-run', $options)
    && ($dryRunOption === false || filter_var($dryRunOption, FILTER_VALIDATE_BOOLEAN));

$lockPath = storage_path('logs/lead-agent-cron.lock');
ensure_directory(dirname($lockPath));
$lock = fopen($lockPath, 'c+');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, json_encode([
        'ok' => true,
        'skipped' => true,
        'message' => 'Lead Agent worker is already running.',
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
}

try {
    $result = lead_agent_run_due($limit, $dryRun);
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(!empty($result['ok']) ? 0 : 1);
} catch (Throwable $e) {
    esm_log('lead_agent', 'Server Lead Agent worker crashed.', ['message' => $e->getMessage()]);
    fwrite(STDERR, json_encode([
        'ok' => false,
        'message' => 'Lead Agent worker crashed.',
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
