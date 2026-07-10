<?php
declare(strict_types=1);

/**
 * Security boundary for the versioned Codex operator API.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

if (!function_exists('codex_security_ensure_schema')) {
    function codex_security_ensure_schema(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        db_query("CREATE TABLE IF NOT EXISTS codex_api_clients (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            label VARCHAR(120) NOT NULL,
            token_prefix VARCHAR(24) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            scopes_json TEXT NOT NULL,
            status ENUM('active','revoked') NOT NULL DEFAULT 'active',
            rate_limit_per_minute INT UNSIGNED NOT NULL DEFAULT 60,
            expires_at DATETIME NULL,
            last_used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            UNIQUE KEY uq_codex_client_token_hash (token_hash),
            KEY idx_codex_client_status (status),
            KEY idx_codex_client_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS codex_api_nonces (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            nonce_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_codex_nonce (client_id, nonce_hash),
            KEY idx_codex_nonce_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS codex_api_rate_limits (
            client_id BIGINT UNSIGNED NOT NULL,
            bucket_start DATETIME NOT NULL,
            request_count INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (client_id, bucket_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS codex_api_idempotency (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            key_hash CHAR(64) NOT NULL,
            request_hash CHAR(64) NOT NULL,
            state ENUM('processing','completed','failed') NOT NULL DEFAULT 'processing',
            response_code SMALLINT UNSIGNED NULL,
            response_body MEDIUMTEXT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_codex_idempotency (client_id, key_hash),
            KEY idx_codex_idempotency_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS codex_api_audit_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            request_id CHAR(36) NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            action VARCHAR(80) NOT NULL,
            method VARCHAR(10) NOT NULL,
            request_path VARCHAR(255) NOT NULL,
            lead_id BIGINT UNSIGNED NULL,
            source_ip VARCHAR(64) NOT NULL,
            user_agent VARCHAR(255) NOT NULL,
            request_hash CHAR(64) NOT NULL,
            status_code SMALLINT UNSIGNED NULL,
            outcome VARCHAR(40) NOT NULL DEFAULT 'started',
            metadata_json TEXT NULL,
            created_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            UNIQUE KEY uq_codex_audit_request (request_id),
            KEY idx_codex_audit_client (client_id),
            KEY idx_codex_audit_action (action),
            KEY idx_codex_audit_lead (lead_id),
            KEY idx_codex_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $ready = true;
    }
}

if (!function_exists('codex_security_header')) {
    function codex_security_header(string $name): string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return trim((string)($_SERVER[$serverKey] ?? ''));
    }
}

if (!function_exists('codex_security_request_path')) {
    function codex_security_request_path(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '/';
    }
}

if (!function_exists('codex_security_request_target')) {
    function codex_security_request_target(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        return $uri !== '' ? $uri : '/';
    }
}

if (!function_exists('codex_security_is_https')) {
    function codex_security_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        return defined('ELITE_CODEX_TRUST_PROXY_HTTPS')
            && ELITE_CODEX_TRUST_PROXY_HTTPS
            && strtolower(codex_security_header('X-Forwarded-Proto')) === 'https';
    }
}

if (!function_exists('codex_security_json')) {
    function codex_security_json(array $payload, int $statusCode): never
    {
        if (function_exists('codex_security_finalize') && isset($GLOBALS['codex_api_security_context'])) {
            codex_security_finalize($statusCode, $payload);
        }
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('codex_security_bearer_token')) {
    function codex_security_bearer_token(): string
    {
        $authorization = codex_security_header('Authorization');
        if (!preg_match('/^Bearer\\s+([A-Za-z0-9._~-]{32,512})$/', $authorization, $matches)) {
            return '';
        }
        return (string)$matches[1];
    }
}

if (!function_exists('codex_security_action_scopes')) {
    function codex_security_action_scopes(string $action, array $body): array
    {
        $map = [
            'health' => ['system:read'],
            'capabilities' => ['system:read'],
            'stages' => ['leads:read'],
            'pipeline_snapshot' => ['leads:read'],
            'list_leads' => ['leads:read'],
            'inbox' => ['leads:read'],
            'get_lead' => ['leads:read'],
            'get_thread' => ['leads:read'],
            'find_lead' => ['leads:read'],
            'search_leads' => ['leads:read'],
            'find_duplicates' => ['leads:read'],
            'mobile_notifications' => ['leads:read'],
            'elite_ai_audit_recent' => ['audit:read'],
            'assistant_prompt' => ['messages:draft'],
            'draft_email' => ['messages:draft'],
            'prepare_sms_followup' => ['messages:draft'],
            'create_lead' => ['leads:write'],
            'import_meta_leads' => ['leads:write'],
            'add_note' => ['leads:write'],
            'update_lead' => ['leads:write'],
            'mark_notification_reviewed' => ['leads:write'],
            'move_stage' => ['stages:write'],
            'send_sms' => ['messages:send'],
            'send_email' => ['messages:send'],
            'follow_up_lead' => ['messages:send'],
            'operator_follow_up' => ['messages:send'],
            'merge_leads' => ['leads:merge'],
            'merge_all_duplicates' => ['leads:merge'],
            'mobile_setup_token' => ['admin:write'],
            'mobile_push_save' => ['admin:write'],
            'mobile_push_remove' => ['admin:write'],
        ];

        $required = $map[$action] ?? ['admin:write'];
        if (in_array($action, ['follow_up_lead', 'operator_follow_up'], true)) {
            if (trim((string)($body['status'] ?? '')) !== '') {
                $required[] = 'stages:write';
            }
            if (trim((string)($body['next_follow_up_at'] ?? '')) !== '' || trim((string)($body['follow_up_status'] ?? '')) !== '') {
                $required[] = 'leads:write';
            }
        }
        return array_values(array_unique($required));
    }
}

if (!function_exists('codex_security_scope_allowed')) {
    function codex_security_scope_allowed(array $granted, array $required): bool
    {
        if (in_array('*', $granted, true)) {
            return true;
        }
        foreach ($required as $scope) {
            if (!in_array($scope, $granted, true)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('codex_security_uuid')) {
    function codex_security_uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}

if (!function_exists('codex_security_signature_payload')) {
    function codex_security_signature_payload(string $timestamp, string $nonce, string $method, string $path, string $rawBody): string
    {
        return $timestamp . "\n" . $nonce . "\n" . strtoupper($method) . "\n" . $path . "\n" . hash('sha256', $rawBody);
    }
}

if (!function_exists('codex_security_audit_start')) {
    function codex_security_audit_start(array $context): void
    {
        db_query(
            'INSERT INTO codex_api_audit_logs (request_id, client_id, action, method, request_path, lead_id, source_ip, user_agent, request_hash, status_code, outcome, metadata_json, created_at) VALUES (:request_id, :client_id, :action, :method, :request_path, :lead_id, :source_ip, :user_agent, :request_hash, NULL, :outcome, :metadata_json, :created_at)',
            [
                'request_id' => $context['request_id'],
                'client_id' => $context['client_id'],
                'action' => $context['action'],
                'method' => $context['method'],
                'request_path' => $context['path'],
                'lead_id' => $context['lead_id'] ?: null,
                'source_ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
                'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'request_hash' => $context['request_hash'],
                'outcome' => 'started',
                'metadata_json' => json_encode(['scopes' => $context['required_scopes']], JSON_UNESCAPED_SLASHES),
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
    }
}

if (!function_exists('codex_security_finalize')) {
    function codex_security_finalize(int $statusCode, array $payload): void
    {
        $context = $GLOBALS['codex_api_security_context'] ?? null;
        if (!is_array($context) || empty($context['request_id'])) {
            return;
        }
        $responseBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $outcome = $statusCode >= 200 && $statusCode < 300 ? 'success' : 'rejected';
        try {
            db_query(
                'UPDATE codex_api_audit_logs SET status_code = :status_code, outcome = :outcome, completed_at = :completed_at WHERE request_id = :request_id LIMIT 1',
                ['status_code' => $statusCode, 'outcome' => $outcome, 'completed_at' => date('Y-m-d H:i:s'), 'request_id' => $context['request_id']]
            );
            if (!empty($context['idempotency_id'])) {
                db_query(
                    'UPDATE codex_api_idempotency SET state = :state, response_code = :response_code, response_body = :response_body, updated_at = :updated_at WHERE id = :id LIMIT 1',
                    [
                        'state' => $statusCode >= 200 && $statusCode < 500 ? 'completed' : 'failed',
                        'response_code' => $statusCode,
                        'response_body' => $responseBody,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'id' => (int)$context['idempotency_id'],
                    ]
                );
            }
        } catch (Throwable $e) {
            esm_log('codex_api_security', 'Could not finalize Codex API audit.', ['request_id' => $context['request_id'], 'error' => $e->getMessage()]);
        }
        $GLOBALS['codex_api_security_context']['finalized'] = true;
    }
}

if (!function_exists('codex_security_authenticate')) {
    function codex_security_authenticate(string $action, string $method, array $body, string $rawBody): array
    {
        codex_security_ensure_schema();
        if (APP_ENV === 'production' && !codex_security_is_https()) {
            codex_security_json(['ok' => false, 'message' => 'HTTPS is required.'], 400);
        }

        $token = codex_security_bearer_token();
        if ($token === '') {
            codex_security_json(['ok' => false, 'message' => 'Unauthorized.'], 401);
        }
        $client = db_one('SELECT * FROM codex_api_clients WHERE token_hash = :token_hash AND status = :status LIMIT 1', [
            'token_hash' => hash('sha256', $token),
            'status' => 'active',
        ]);
        if (!$client) {
            $activeClientCount = (int)db_value("SELECT COUNT(*) FROM codex_api_clients WHERE status = 'active' AND (expires_at IS NULL OR expires_at >= :now)", [
                'now' => date('Y-m-d H:i:s'),
            ]);
            $legacyToken = trim((string)(defined('ELITE_CODEX_API_TOKEN') ? ELITE_CODEX_API_TOKEN : ''));
            if ($activeClientCount === 0 && $legacyToken !== '' && hash_equals(hash('sha256', $legacyToken), hash('sha256', $token))) {
                $bootstrapScopes = ['system:read', 'leads:read', 'leads:write', 'messages:draft', 'messages:send', 'stages:write', 'audit:read'];
                db_query(
                    'INSERT INTO codex_api_clients (label, token_prefix, token_hash, scopes_json, status, rate_limit_per_minute, expires_at, created_at) VALUES (:label, :token_prefix, :token_hash, :scopes_json, :status, :rate_limit, :expires_at, :created_at)',
                    [
                        'label' => 'Migrated Codex Operator',
                        'token_prefix' => substr($token, 0, 20),
                        'token_hash' => hash('sha256', $token),
                        'scopes_json' => json_encode($bootstrapScopes, JSON_UNESCAPED_SLASHES),
                        'status' => 'active',
                        'rate_limit' => 90,
                        'expires_at' => date('Y-m-d H:i:s', strtotime('+90 days')),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]
                );
                $client = db_one('SELECT * FROM codex_api_clients WHERE token_hash = :token_hash AND status = :status LIMIT 1', [
                    'token_hash' => hash('sha256', $token),
                    'status' => 'active',
                ]);
                esm_log('codex_api_security', 'Migrated the configured legacy Codex secret into the v1 client registry.', [
                    'client_id' => (int)($client['id'] ?? 0),
                    'token_prefix' => substr($token, 0, 12),
                ]);
            }
        }
        if (!$client || (!empty($client['expires_at']) && strtotime((string)$client['expires_at']) < time())) {
            codex_security_json(['ok' => false, 'message' => 'Unauthorized.'], 401);
        }

        $timestamp = codex_security_header('X-Elite-Timestamp');
        $nonce = codex_security_header('X-Elite-Nonce');
        $signature = strtolower(codex_security_header('X-Elite-Signature'));
        if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > ELITE_CODEX_V1_REQUEST_TTL_SECONDS || !preg_match('/^[A-Za-z0-9._~-]{16,160}$/', $nonce) || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
            codex_security_json(['ok' => false, 'message' => 'Invalid or expired request signature.'], 401);
        }

        $path = codex_security_request_path();
        $requestTarget = codex_security_request_target();
        $expectedSignature = hash_hmac('sha256', codex_security_signature_payload($timestamp, $nonce, $method, $requestTarget, $rawBody), $token);
        if (!hash_equals($expectedSignature, $signature)) {
            codex_security_json(['ok' => false, 'message' => 'Invalid or expired request signature.'], 401);
        }

        $grantedScopes = json_decode((string)$client['scopes_json'], true);
        $grantedScopes = is_array($grantedScopes) ? array_values(array_filter(array_map('strval', $grantedScopes))) : [];
        $requiredScopes = codex_security_action_scopes($action, $body);
        $requestHash = hash('sha256', strtoupper($method) . "\n" . $requestTarget . "\n" . $rawBody);
        $context = [
            'request_id' => codex_security_uuid(),
            'client_id' => (int)$client['id'],
            'client_label' => (string)$client['label'],
            'action' => $action,
            'method' => strtoupper($method),
            'path' => $path,
            'lead_id' => (int)($body['lead_id'] ?? $body['id'] ?? 0),
            'request_hash' => $requestHash,
            'required_scopes' => $requiredScopes,
            'granted_scopes' => $grantedScopes,
            'idempotency_id' => null,
            'finalized' => false,
        ];
        codex_security_audit_start($context);
        $GLOBALS['codex_api_security_context'] = $context;

        try {
            db_query(
                'INSERT INTO codex_api_nonces (client_id, nonce_hash, expires_at, created_at) VALUES (:client_id, :nonce_hash, :expires_at, :created_at)',
                [
                    'client_id' => (int)$client['id'],
                    'nonce_hash' => hash('sha256', $nonce),
                    'expires_at' => date('Y-m-d H:i:s', time() + ELITE_CODEX_V1_REQUEST_TTL_SECONDS + 60),
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (Throwable $e) {
            codex_security_json(['ok' => false, 'message' => 'Replay detected.'], 409);
        }

        $bucket = date('Y-m-d H:i:00');
        db_query(
            'INSERT INTO codex_api_rate_limits (client_id, bucket_start, request_count, updated_at) VALUES (:client_id, :bucket_start, 1, :updated_at) ON DUPLICATE KEY UPDATE request_count = request_count + 1, updated_at = VALUES(updated_at)',
            ['client_id' => (int)$client['id'], 'bucket_start' => $bucket, 'updated_at' => date('Y-m-d H:i:s')]
        );
        $requestCount = (int)db_value('SELECT request_count FROM codex_api_rate_limits WHERE client_id = :client_id AND bucket_start = :bucket_start', ['client_id' => (int)$client['id'], 'bucket_start' => $bucket]);
        if ($requestCount > max(1, (int)$client['rate_limit_per_minute'])) {
            header('Retry-After: 60');
            codex_security_json(['ok' => false, 'message' => 'Rate limit exceeded.'], 429);
        }

        if (!codex_security_scope_allowed($grantedScopes, $requiredScopes)) {
            codex_security_json(['ok' => false, 'message' => 'Forbidden.'], 403);
        }

        $idempotencyId = null;
        if ($method === 'POST') {
            $idempotencyKey = codex_security_header('Idempotency-Key');
            if (!preg_match('/^[A-Za-z0-9._~-]{16,160}$/', $idempotencyKey)) {
                codex_security_json(['ok' => false, 'message' => 'A valid Idempotency-Key header is required for write requests.'], 400);
            }
            $keyHash = hash('sha256', $idempotencyKey);
            $existing = db_one('SELECT * FROM codex_api_idempotency WHERE client_id = :client_id AND key_hash = :key_hash LIMIT 1', ['client_id' => (int)$client['id'], 'key_hash' => $keyHash]);
            if ($existing) {
                if (!hash_equals((string)$existing['request_hash'], $requestHash)) {
                    codex_security_json(['ok' => false, 'message' => 'Idempotency key was already used for a different request.'], 409);
                }
                if ((string)$existing['state'] === 'completed' && !empty($existing['response_body'])) {
                    $replayStatus = (int)($existing['response_code'] ?? 200);
                    $replayPayload = json_decode((string)$existing['response_body'], true);
                    codex_security_finalize($replayStatus, is_array($replayPayload) ? $replayPayload : ['ok' => $replayStatus >= 200 && $replayStatus < 300]);
                    http_response_code($replayStatus);
                    header('Content-Type: application/json; charset=utf-8');
                    header('Cache-Control: no-store');
                    header('X-Idempotent-Replay: true');
                    echo (string)$existing['response_body'];
                    exit;
                }
                codex_security_json(['ok' => false, 'message' => 'An identical request is already processing.'], 409);
            }
            $idempotencyId = db_insert(
                'INSERT INTO codex_api_idempotency (client_id, key_hash, request_hash, state, expires_at, created_at, updated_at) VALUES (:client_id, :key_hash, :request_hash, :state, :expires_at, :created_at, :updated_at)',
                [
                    'client_id' => (int)$client['id'],
                    'key_hash' => $keyHash,
                    'request_hash' => $requestHash,
                    'state' => 'processing',
                    'expires_at' => date('Y-m-d H:i:s', time() + 86400),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
            $context['idempotency_id'] = $idempotencyId;
            $GLOBALS['codex_api_security_context']['idempotency_id'] = $idempotencyId;
        }

        db_query('UPDATE codex_api_clients SET last_used_at = :last_used_at WHERE id = :id LIMIT 1', ['last_used_at' => date('Y-m-d H:i:s'), 'id' => (int)$client['id']]);

        if (random_int(1, 100) <= 5) {
            db_query('DELETE FROM codex_api_nonces WHERE expires_at < :now', ['now' => date('Y-m-d H:i:s')]);
            db_query('DELETE FROM codex_api_idempotency WHERE expires_at < :now', ['now' => date('Y-m-d H:i:s')]);
            db_query('DELETE FROM codex_api_rate_limits WHERE bucket_start < :cutoff', ['cutoff' => date('Y-m-d H:i:s', time() - 86400)]);
        }
        return $context;
    }
}

if (!function_exists('codex_security_generate_client')) {
    function codex_security_generate_client(string $label, array $scopes, ?string $expiresAt = null, int $rateLimit = 60): array
    {
        codex_security_ensure_schema();
        $token = 'codex_v1_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $id = db_insert(
            'INSERT INTO codex_api_clients (label, token_prefix, token_hash, scopes_json, status, rate_limit_per_minute, expires_at, created_at) VALUES (:label, :token_prefix, :token_hash, :scopes_json, :status, :rate_limit, :expires_at, :created_at)',
            [
                'label' => substr(trim($label), 0, 120),
                'token_prefix' => substr($token, 0, 20),
                'token_hash' => hash('sha256', $token),
                'scopes_json' => json_encode(array_values(array_unique($scopes)), JSON_UNESCAPED_SLASHES),
                'status' => 'active',
                'rate_limit' => max(1, min(600, $rateLimit)),
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
        return ['id' => $id, 'token' => $token, 'token_prefix' => substr($token, 0, 20), 'scopes' => array_values(array_unique($scopes)), 'expires_at' => $expiresAt];
    }
}
