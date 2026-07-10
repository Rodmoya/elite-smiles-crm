<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/helpers.php';
require_once dirname(__DIR__) . '/app/core/db.php';
require_once dirname(__DIR__) . '/app/core/codex_api_security.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$command = strtolower((string)($argv[1] ?? 'help'));
$options = [];
foreach (array_slice($argv, 2) as $argument) {
    if (preg_match('/^--([a-z0-9_-]+)=(.*)$/i', $argument, $matches)) {
        $options[strtolower($matches[1])] = $matches[2];
    }
}

codex_security_ensure_schema();

if ($command === 'create') {
    $label = trim((string)($options['label'] ?? 'Codex Operator'));
    $scopes = array_values(array_filter(array_map('trim', explode(',', (string)($options['scopes'] ?? 'system:read,leads:read,leads:write,messages:draft,messages:send,stages:write,audit:read')))));
    $expiresAt = trim((string)($options['expires'] ?? '')) ?: date('Y-m-d H:i:s', strtotime('+90 days'));
    $rateLimit = (int)($options['rate-limit'] ?? 60);
    $output = trim((string)($options['output'] ?? ''));
    if ($output === '') {
        fwrite(STDERR, "Create requires --output=<private credential file>.\n");
        exit(2);
    }
    $result = codex_security_generate_client($label, $scopes, $expiresAt, $rateLimit);
    $credential = [
        'client_id' => $result['id'],
        'label' => $label,
        'token' => $result['token'],
        'endpoint' => rtrim(APP_URL, '/') . '/app/api/codex/v1/',
        'scopes' => $result['scopes'],
        'expires_at' => $result['expires_at'],
        'created_at' => date(DATE_ATOM),
    ];
    $directory = dirname($output);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create credential directory.');
    }
    if (file_put_contents($output, json_encode($credential, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
        throw new RuntimeException('Could not write credential file.');
    }
    @chmod($output, 0600);
    echo 'Created Codex API client #' . $result['id'] . ' (' . $result['token_prefix'] . "...)\n";
    echo 'Credential written to ' . $output . "\n";
    exit(0);
}

if ($command === 'list') {
    $rows = db_all('SELECT id, label, token_prefix, scopes_json, status, rate_limit_per_minute, expires_at, last_used_at, created_at, revoked_at FROM codex_api_clients ORDER BY id DESC');
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

if ($command === 'revoke') {
    $id = (int)($options['id'] ?? 0);
    if ($id <= 0) {
        fwrite(STDERR, "Revoke requires --id=<client id>.\n");
        exit(2);
    }
    db_query('UPDATE codex_api_clients SET status = :status, revoked_at = :revoked_at WHERE id = :id LIMIT 1', [
        'status' => 'revoked',
        'revoked_at' => date('Y-m-d H:i:s'),
        'id' => $id,
    ]);
    echo 'Revoked Codex API client #' . $id . ".\n";
    exit(0);
}

echo "Usage:\n";
echo "  php bin/codex-api-client.php create --label=Codex --output=.secrets/codex-v1.json [--scopes=...] [--expires=YYYY-MM-DD HH:MM:SS]\n";
echo "  php bin/codex-api-client.php list\n";
echo "  php bin/codex-api-client.php revoke --id=1\n";
