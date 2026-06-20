<?php
declare(strict_types=1);

/**
 * Elite Smiles Marketing CRM
 * File: app/core/mobile_ai_auth.php
 *
 * Phase 1 foundation for:
 * - one-time QR setup tokens
 * - trusted mobile sessions
 * - push subscription scaffolding
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (!defined('MOBILE_AI_SETUP_TTL_SECONDS')) {
    define('MOBILE_AI_SETUP_TTL_SECONDS', 15 * 60);
}

if (!defined('MOBILE_AI_SESSION_TTL_SECONDS')) {
    define('MOBILE_AI_SESSION_TTL_SECONDS', 60 * 24 * 60 * 60);
}

if (!defined('MOBILE_AI_SESSION_COOKIE_NAME')) {
    define('MOBILE_AI_SESSION_COOKIE_NAME', 'esm_mobile_ai');
}

if (!defined('MOBILE_AI_SESSION_COOKIE_PATH')) {
    define('MOBILE_AI_SESSION_COOKIE_PATH', '/crm');
}

if (!function_exists('mobile_ai_key')) {
    function mobile_ai_key(): string
    {
        return defined('APP_KEY') && APP_KEY !== '' ? APP_KEY : 'elite-smiles-mobile-ai';
    }
}

if (!function_exists('mobile_ai_generate_token')) {
    function mobile_ai_generate_token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes(max(16, $bytes))), '+/', '-_'), '=');
    }
}

if (!function_exists('mobile_ai_hash_token')) {
    function mobile_ai_hash_token(string $token): string
    {
        return hash_hmac('sha256', $token, mobile_ai_key());
    }
}

if (!function_exists('mobile_ai_cookie_secure')) {
    function mobile_ai_cookie_secure(): bool
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}

if (!function_exists('mobile_ai_cookie_options')) {
    function mobile_ai_cookie_options(int $expiresAt): array
    {
        return [
            'expires' => $expiresAt,
            'path' => MOBILE_AI_SESSION_COOKIE_PATH,
            'domain' => '',
            'secure' => mobile_ai_cookie_secure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}

if (!function_exists('mobile_ai_set_cookie')) {
    function mobile_ai_set_cookie(string $token, int $expiresAt): void
    {
        setcookie(MOBILE_AI_SESSION_COOKIE_NAME, $token, mobile_ai_cookie_options($expiresAt));
    }
}

if (!function_exists('mobile_ai_clear_cookie')) {
    function mobile_ai_clear_cookie(): void
    {
        setcookie(MOBILE_AI_SESSION_COOKIE_NAME, '', mobile_ai_cookie_options(time() - 3600));
    }
}

if (!function_exists('mobile_ai_qr_setup_url')) {
    function mobile_ai_qr_setup_url(string $token): string
    {
        return base_url('mobile-ai/setup/' . rawurlencode(trim($token)));
    }
}

if (!function_exists('mobile_ai_qr_image_url')) {
    function mobile_ai_qr_image_url(string $token): string
    {
        $setupUrl = mobile_ai_qr_setup_url($token);
        return 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=10&data=' . rawurlencode($setupUrl);
    }
}

if (!function_exists('mobile_ai_table_exists')) {
    function mobile_ai_table_exists(string $table): bool
    {
        try {
            return (bool) db_value(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table LIMIT 1',
                ['schema' => DB_NAME, 'table' => $table]
            );
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('mobile_ai_ensure_schema')) {
    function mobile_ai_ensure_schema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        try {
            db_query(
                "CREATE TABLE IF NOT EXISTS user_mobile_access_tokens (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id INT UNSIGNED NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    token_plaintext TEXT NULL,
                    expires_at DATETIME NULL,
                    used_at DATETIME NULL,
                    revoked_at DATETIME NULL,
                    created_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_mobile_access_hash (token_hash),
                    KEY idx_mobile_access_user (user_id),
                    KEY idx_mobile_access_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            db_query(
                "CREATE TABLE IF NOT EXISTS user_mobile_sessions (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id INT UNSIGNED NOT NULL,
                    session_token_hash CHAR(64) NOT NULL,
                    device_label VARCHAR(190) NOT NULL DEFAULT 'Mobile Device',
                    user_agent VARCHAR(255) NOT NULL DEFAULT '',
                    ip_address VARCHAR(64) NOT NULL DEFAULT '',
                    last_seen_at DATETIME NULL,
                    expires_at DATETIME NULL,
                    revoked_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_mobile_session_hash (session_token_hash),
                    KEY idx_mobile_session_user (user_id),
                    KEY idx_mobile_session_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            db_query(
                "CREATE TABLE IF NOT EXISTS user_push_subscriptions (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id INT UNSIGNED NOT NULL,
                    endpoint_hash CHAR(64) NOT NULL,
                    endpoint TEXT NOT NULL,
                    subscription_json LONGTEXT NULL,
                    browser VARCHAR(150) NOT NULL DEFAULT '',
                    device_label VARCHAR(190) NOT NULL DEFAULT '',
                    enabled TINYINT(1) NOT NULL DEFAULT 1,
                    push_enabled TINYINT(1) NOT NULL DEFAULT 1,
                    sound_enabled TINYINT(1) NOT NULL DEFAULT 1,
                    quiet_hours_json VARCHAR(255) NOT NULL DEFAULT '',
                    high_priority_only TINYINT(1) NOT NULL DEFAULT 0,
                    last_seen_at DATETIME NULL,
                    revoked_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_push_endpoint_hash (endpoint_hash),
                    KEY idx_push_subscription_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            esm_log('mobile_ai', 'Failed to ensure mobile schema', ['error' => $e->getMessage()]);
        }
    }
}

if (!function_exists('mobile_ai_issue_setup_token')) {
    function mobile_ai_issue_setup_token(int $userId, ?int $createdBy = null): string
    {
        if ($userId <= 0) {
            return '';
        }

        mobile_ai_ensure_schema();

        db_query(
            "UPDATE user_mobile_access_tokens
             SET revoked_at = NOW(), updated_at = NOW()
             WHERE user_id = :user_id
               AND used_at IS NULL
               AND revoked_at IS NULL",
            ['user_id' => $userId]
        );

        $token = mobile_ai_generate_token(32);
        $expiresAt = date('Y-m-d H:i:s', time() + MOBILE_AI_SETUP_TTL_SECONDS);

        db_insert(
            "INSERT INTO user_mobile_access_tokens
                (user_id, token_hash, token_plaintext, expires_at, created_by)
             VALUES
                (:user_id, :token_hash, :token_plaintext, :expires_at, :created_by)",
            [
                'user_id' => $userId,
                'token_hash' => mobile_ai_hash_token($token),
                'token_plaintext' => '',
                'expires_at' => $expiresAt,
                'created_by' => $createdBy,
            ]
        );

        return $token;
    }
}

if (!function_exists('mobile_ai_find_valid_setup_token')) {
    function mobile_ai_find_valid_setup_token(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        mobile_ai_ensure_schema();

        $row = db_one(
            "SELECT *
             FROM user_mobile_access_tokens
             WHERE token_hash = :token_hash
             LIMIT 1",
            ['token_hash' => mobile_ai_hash_token($token)]
        );

        if (!$row) {
            return null;
        }

        if (trim((string) ($row['used_at'] ?? '')) !== '') {
            return null;
        }

        if (trim((string) ($row['revoked_at'] ?? '')) !== '') {
            return null;
        }

        $expiresAt = trim((string) ($row['expires_at'] ?? ''));
        if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
            return null;
        }

        $user = db_one(
            "SELECT id, first_name, last_name, email, role, is_active, last_login_at
             FROM users
             WHERE id = :id
             LIMIT 1",
            ['id' => (int) ($row['user_id'] ?? 0)]
        );

        if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
            return null;
        }

        $row['user'] = $user;
        return $row;
    }
}

if (!function_exists('mobile_ai_mark_setup_token_used')) {
    function mobile_ai_mark_setup_token_used(int $tokenId): void
    {
        if ($tokenId <= 0) {
            return;
        }

        db_query(
            "UPDATE user_mobile_access_tokens
             SET used_at = NOW(), updated_at = NOW()
             WHERE id = :id
             LIMIT 1",
            ['id' => $tokenId]
        );
    }
}

if (!function_exists('mobile_ai_create_session')) {
    function mobile_ai_create_session(int $userId, string $deviceLabel = ''): string
    {
        if ($userId <= 0) {
            return '';
        }

        mobile_ai_ensure_schema();

        $token = mobile_ai_generate_token(48);
        $expiresTs = time() + MOBILE_AI_SESSION_TTL_SECONDS;
        $expiresAt = date('Y-m-d H:i:s', $expiresTs);

        db_insert(
            "INSERT INTO user_mobile_sessions
                (user_id, session_token_hash, device_label, user_agent, ip_address, last_seen_at, expires_at)
             VALUES
                (:user_id, :session_token_hash, :device_label, :user_agent, :ip_address, NOW(), :expires_at)",
            [
                'user_id' => $userId,
                'session_token_hash' => mobile_ai_hash_token($token),
                'device_label' => trim($deviceLabel) !== '' ? trim($deviceLabel) : 'Trusted Mobile Device',
                'user_agent' => substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255),
                'ip_address' => function_exists('client_ip') ? client_ip() : '',
                'expires_at' => $expiresAt,
            ]
        );

        mobile_ai_set_cookie($token, $expiresTs);

        return $token;
    }
}

if (!function_exists('mobile_ai_current_session_token')) {
    function mobile_ai_current_session_token(): string
    {
        return trim((string) ($_COOKIE[MOBILE_AI_SESSION_COOKIE_NAME] ?? ''));
    }
}

if (!function_exists('mobile_ai_find_session')) {
    function mobile_ai_find_session(string $sessionToken): ?array
    {
        $sessionToken = trim($sessionToken);
        if ($sessionToken === '') {
            return null;
        }

        mobile_ai_ensure_schema();

        $row = db_one(
            "SELECT *
             FROM user_mobile_sessions
             WHERE session_token_hash = :session_token_hash
             LIMIT 1",
            ['session_token_hash' => mobile_ai_hash_token($sessionToken)]
        );

        if (!$row) {
            return null;
        }

        if (trim((string) ($row['revoked_at'] ?? '')) !== '') {
            return null;
        }

        $expiresAt = trim((string) ($row['expires_at'] ?? ''));
        if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
            return null;
        }

        $user = db_one(
            "SELECT id, first_name, last_name, email, role, is_active
             FROM users
             WHERE id = :id
             LIMIT 1",
            ['id' => (int) ($row['user_id'] ?? 0)]
        );

        if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
            return null;
        }

        $row['user'] = $user;
        return $row;
    }
}

if (!function_exists('mobile_ai_touch_session')) {
    function mobile_ai_touch_session(int $sessionId, string $rawToken): void
    {
        if ($sessionId <= 0 || trim($rawToken) === '') {
            return;
        }

        $expiresTs = time() + MOBILE_AI_SESSION_TTL_SECONDS;
        $expiresAt = date('Y-m-d H:i:s', $expiresTs);

        db_query(
            "UPDATE user_mobile_sessions
             SET last_seen_at = NOW(),
                 expires_at = :expires_at,
                 updated_at = NOW()
             WHERE id = :id
             LIMIT 1",
            ['id' => $sessionId, 'expires_at' => $expiresAt]
        );

        mobile_ai_set_cookie($rawToken, $expiresTs);
    }
}

if (!function_exists('mobile_ai_auth_user')) {
    function mobile_ai_auth_user(): ?array
    {
        $rawToken = mobile_ai_current_session_token();
        if ($rawToken === '') {
            return null;
        }

        $session = mobile_ai_find_session($rawToken);
        if (!$session) {
            mobile_ai_clear_cookie();
            return null;
        }

        mobile_ai_touch_session((int) ($session['id'] ?? 0), $rawToken);

        return [
            'id' => (int) ($session['user']['id'] ?? 0),
            'first_name' => (string) ($session['user']['first_name'] ?? ''),
            'last_name' => (string) ($session['user']['last_name'] ?? ''),
            'email' => (string) ($session['user']['email'] ?? ''),
            'role' => (string) ($session['user']['role'] ?? 'viewer'),
            'session_id' => (int) ($session['id'] ?? 0),
            'device_label' => (string) ($session['device_label'] ?? ''),
            'last_seen_at' => (string) ($session['last_seen_at'] ?? ''),
            'expires_at' => (string) ($session['expires_at'] ?? ''),
        ];
    }
}

if (!function_exists('mobile_ai_require_user_session')) {
    function mobile_ai_require_user_session(): array
    {
        $user = mobile_ai_auth_user();
        if ($user) {
            return $user;
        }

        http_response_code(401);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a;padding:24px;">';
        echo '<h1 style="margin:0 0 12px;">Elite AI session required</h1>';
        echo '<p style="margin:0 0 8px;">This mobile session is missing, expired, or revoked.</p>';
        echo '<p style="margin:0;">Please scan a fresh QR code from the CRM Users page.</p>';
        echo '</body></html>';
        exit;
    }
}

if (!function_exists('mobile_ai_logout_current_session')) {
    function mobile_ai_logout_current_session(): void
    {
        $token = mobile_ai_current_session_token();
        if ($token !== '') {
            db_query(
                "UPDATE user_mobile_sessions
                 SET revoked_at = NOW(), updated_at = NOW()
                 WHERE session_token_hash = :session_token_hash
                   AND revoked_at IS NULL",
                ['session_token_hash' => mobile_ai_hash_token($token)]
            );
        }

        mobile_ai_clear_cookie();
    }
}

if (!function_exists('mobile_ai_revoke_user_access')) {
    function mobile_ai_revoke_user_access(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        mobile_ai_ensure_schema();

        db_query(
            "UPDATE user_mobile_sessions
             SET revoked_at = NOW(), updated_at = NOW()
             WHERE user_id = :user_id
               AND revoked_at IS NULL",
            ['user_id' => $userId]
        );

        db_query(
            "UPDATE user_mobile_access_tokens
             SET revoked_at = NOW(), updated_at = NOW()
             WHERE user_id = :user_id
               AND revoked_at IS NULL
               AND used_at IS NULL",
            ['user_id' => $userId]
        );
    }
}

if (!function_exists('mobile_ai_latest_setup_token_for_user')) {
    function mobile_ai_latest_setup_token_for_user(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        mobile_ai_ensure_schema();

        $row = db_one(
            "SELECT *
             FROM user_mobile_access_tokens
             WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC
             LIMIT 1",
            ['user_id' => $userId]
        );

        return $row ?: null;
    }
}

if (!function_exists('mobile_ai_latest_session_for_user')) {
    function mobile_ai_latest_session_for_user(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        mobile_ai_ensure_schema();

        $row = db_one(
            "SELECT *
             FROM user_mobile_sessions
             WHERE user_id = :user_id
             ORDER BY COALESCE(last_seen_at, created_at) DESC, id DESC
             LIMIT 1",
            ['user_id' => $userId]
        );

        return $row ?: null;
    }
}

if (!function_exists('mobile_ai_save_push_subscription')) {
    function mobile_ai_save_push_subscription(int $userId, array $subscription, string $browser = '', string $deviceLabel = ''): bool
    {
        if ($userId <= 0) {
            return false;
        }

        mobile_ai_ensure_schema();

        $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
        if ($endpoint === '') {
            return false;
        }

        db_query(
            "INSERT INTO user_push_subscriptions
                (user_id, endpoint_hash, endpoint, subscription_json, browser, device_label, enabled, push_enabled, sound_enabled, last_seen_at)
             VALUES
                (:user_id, :endpoint_hash, :endpoint, :subscription_json, :browser, :device_label, 1, 1, 1, NOW())
             ON DUPLICATE KEY UPDATE
                subscription_json = VALUES(subscription_json),
                browser = VALUES(browser),
                device_label = VALUES(device_label),
                enabled = 1,
                push_enabled = 1,
                last_seen_at = NOW(),
                revoked_at = NULL,
                updated_at = NOW()",
            [
                'user_id' => $userId,
                'endpoint_hash' => mobile_ai_hash_token($endpoint),
                'endpoint' => $endpoint,
                'subscription_json' => json_encode($subscription, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'browser' => substr(trim($browser), 0, 150),
                'device_label' => substr(trim($deviceLabel), 0, 190),
            ]
        );

        return true;
    }
}

if (!function_exists('mobile_ai_remove_push_subscription')) {
    function mobile_ai_remove_push_subscription(int $userId, string $endpoint): bool
    {
        if ($userId <= 0 || trim($endpoint) === '') {
            return false;
        }

        mobile_ai_ensure_schema();

        db_query(
            "UPDATE user_push_subscriptions
             SET revoked_at = NOW(), enabled = 0, push_enabled = 0, updated_at = NOW()
             WHERE user_id = :user_id
               AND endpoint_hash = :endpoint_hash",
            [
                'user_id' => $userId,
                'endpoint_hash' => mobile_ai_hash_token($endpoint),
            ]
        );

        return true;
    }
}
