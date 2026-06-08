<?php
declare(strict_types=1);

/**
 * Elite Smiles Marketing CRM
 * File: app/core/auth.php
 *
 * Authentication, session, role access control,
 * invite/reset helper functions, and password helpers.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!function_exists('auth_regenerate')) {
    function auth_regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}

if (!function_exists('auth_create_password_hash')) {
    function auth_create_password_hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_ALGO);
    }
}

if (!function_exists('auth_password_needs_rehash')) {
    function auth_password_needs_rehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ALGO);
    }
}

if (!function_exists('auth_find_user_by_email')) {
    function auth_find_user_by_email(string $email): ?array
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        $sql = "
            SELECT
                id,
                first_name,
                last_name,
                email,
                password_hash,
                role,
                is_active,
                last_login_at
            FROM users
            WHERE email = :email
            LIMIT 1
        ";

        $user = db_one($sql, ['email' => $email]);

        return $user ?: null;
    }
}

if (!function_exists('auth_find_user_by_id')) {
    function auth_find_user_by_id(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $sql = "
            SELECT
                id,
                first_name,
                last_name,
                email,
                password_hash,
                role,
                is_active,
                last_login_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ";

        $user = db_one($sql, ['id' => $userId]);

        return $user ?: null;
    }
}

if (!function_exists('auth_update_last_login')) {
    function auth_update_last_login(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        db_execute(
            "UPDATE users SET last_login_at = NOW() WHERE id = :id",
            ['id' => $userId]
        );
    }
}

if (!function_exists('auth_update_password_hash')) {
    function auth_update_password_hash(int $userId, string $newHash): bool
    {
        if ($userId <= 0 || trim($newHash) === '') {
            return false;
        }

        db_execute(
            "UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id",
            [
                'id' => $userId,
                'password_hash' => $newHash,
            ]
        );

        return true;
    }
}

if (!function_exists('auth_change_password')) {
    function auth_change_password(int $userId, string $currentPassword, string $newPassword): array
    {
        $user = auth_find_user_by_id($userId);

        if (!$user) {
            return ['ok' => false, 'message' => 'User not found.'];
        }

        if (!password_verify($currentPassword, (string)($user['password_hash'] ?? ''))) {
            return ['ok' => false, 'message' => 'Current password is incorrect.'];
        }

        if (strlen($newPassword) < 8) {
            return ['ok' => false, 'message' => 'New password must be at least 8 characters.'];
        }

        $newHash = auth_create_password_hash($newPassword);
        auth_update_password_hash((int)$user['id'], $newHash);

        esm_log('auth', 'Password changed by user', [
            'user_id' => (int)$user['id'],
            'email'   => (string)$user['email'],
            'ip'      => client_ip(),
        ]);

        return ['ok' => true, 'message' => 'Password updated successfully.'];
    }
}

if (!function_exists('auth_generate_token')) {
    function auth_generate_token(int $bytes = 32): string
    {
        return bin2hex(random_bytes(max(16, $bytes)));
    }
}

if (!function_exists('auth_token_expires_at')) {
    function auth_token_expires_at(int $hours = 24): string
    {
        $hours = max(1, $hours);
        return date('Y-m-d H:i:s', time() + ($hours * 3600));
    }
}

if (!function_exists('auth_attempt')) {
    /**
     * Attempt login using email and password.
     */
    function auth_attempt(string $email, string $password): bool
    {
        $email = strtolower(trim($email));

        if ($email === '' || $password === '') {
            return false;
        }

        $user = auth_find_user_by_email($email);

        if (!$user) {
            esm_log('auth', 'Login failed: user not found', [
                'email' => $email,
                'ip'    => client_ip(),
            ]);
            return false;
        }

        if ((int)$user['is_active'] !== 1) {
            esm_log('auth', 'Login failed: inactive user', [
                'user_id' => (int)$user['id'],
                'email'   => $email,
                'ip'      => client_ip(),
            ]);
            return false;
        }

        if (!password_verify($password, (string)$user['password_hash'])) {
            esm_log('auth', 'Login failed: invalid password', [
                'user_id' => (int)$user['id'],
                'email'   => $email,
                'ip'      => client_ip(),
            ]);
            return false;
        }

        if (auth_password_needs_rehash((string)$user['password_hash'])) {
            auth_update_password_hash((int)$user['id'], auth_create_password_hash($password));
        }

        auth_regenerate();

        $_SESSION['auth'] = [
            'user_id'      => (int)$user['id'],
            'first_name'   => (string)$user['first_name'],
            'last_name'    => (string)$user['last_name'],
            'full_name'    => trim(((string)$user['first_name']) . ' ' . ((string)$user['last_name'])),
            'email'        => (string)$user['email'],
            'role'         => (string)$user['role'],
            'logged_in_at' => time(),
            'last_seen_at' => time(),
            'ip'           => client_ip(),
            'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        auth_update_last_login((int)$user['id']);

        esm_log('auth', 'Login successful', [
            'user_id' => (int)$user['id'],
            'email'   => $email,
            'role'    => (string)$user['role'],
            'ip'      => client_ip(),
        ]);

        return true;
    }
}

if (!function_exists('auth_logout')) {
    function auth_logout(): void
    {
        $userId = auth_user_id();
        $email  = auth_user()['email'] ?? null;

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool)($params['secure'] ?? false),
                (bool)($params['httponly'] ?? true)
            );
        }

        session_destroy();

        esm_log('auth', 'Logout successful', [
            'user_id' => $userId,
            'email'   => $email,
            'ip'      => client_ip(),
        ]);
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        if (empty($_SESSION['auth']) || !is_array($_SESSION['auth'])) {
            return false;
        }

        $loggedInAt = (int)($_SESSION['auth']['logged_in_at'] ?? 0);
        if ($loggedInAt <= 0) {
            return false;
        }

        $lastSeenAt = (int)($_SESSION['auth']['last_seen_at'] ?? $loggedInAt);
        if ((time() - $lastSeenAt) > SESSION_LIFETIME) {
            auth_logout();
            return false;
        }

        $_SESSION['auth']['last_seen_at'] = time();

        return true;
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return auth_check() ? $_SESSION['auth'] : null;
    }
}

if (!function_exists('auth_user_id')) {
    function auth_user_id(): ?int
    {
        return auth_check() ? (int)($_SESSION['auth']['user_id'] ?? 0) : null;
    }
}

if (!function_exists('auth_role')) {
    function auth_role(): ?string
    {
        return auth_check() ? (string)($_SESSION['auth']['role'] ?? '') : null;
    }
}

if (!function_exists('auth_name')) {
    function auth_name(): string
    {
        return auth_check() ? (string)($_SESSION['auth']['full_name'] ?? '') : '';
    }
}

if (!function_exists('auth_has_role')) {
    function auth_has_role(string ...$roles): bool
    {
        if (!auth_check()) {
            return false;
        }

        $currentRole = strtolower((string)auth_role());
        $allowed = array_map(
            static fn($role) => strtolower(trim((string)$role)),
            $roles
        );

        return in_array($currentRole, $allowed, true);
    }
}

if (!function_exists('require_auth')) {
    function require_auth(): void
    {
        if (!auth_check()) {
            flash_set('error', 'Please log in to continue.');
            redirect(url('/login.php'));
        }
    }
}

if (!function_exists('require_role')) {
    function require_role(string ...$roles): void
    {
        require_auth();

        if (!auth_has_role(...$roles)) {
            http_response_code(403);
            exit('403 Forbidden');
        }
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(): string
    {
        $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }
}

if (!function_exists('csrf_validate')) {
    function csrf_validate(?string $token): bool
    {
        $sessionToken = $_SESSION['_csrf_token'] ?? '';
        return is_string($token)
            && is_string($sessionToken)
            && $token !== ''
            && hash_equals($sessionToken, $token);
    }
}

if (!function_exists('require_csrf')) {
    function require_csrf(): void
    {
        $token = $_POST['_csrf_token'] ?? $_GET['_csrf_token'] ?? null;

        if (!csrf_validate(is_string($token) ? $token : null)) {
            http_response_code(419);
            exit('419 Invalid CSRF token');
        }
    }
}

if (!function_exists('client_ip')) {
    function client_ip(): string
    {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $value = trim((string)$_SERVER[$key]);

                if ($key === 'HTTP_X_FORWARDED_FOR' && str_contains($value, ',')) {
                    $parts = explode(',', $value);
                    $value = trim($parts[0]);
                }

                return substr($value, 0, 45);
            }
        }

        return '0.0.0.0';
    }
}

if (!function_exists('flash_set')) {
    function flash_set(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }
}

if (!function_exists('flash_get')) {
    function flash_get(string $key): ?string
    {
        if (!isset($_SESSION['_flash'][$key])) {
            return null;
        }

        $message = (string)$_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);

        return $message;
    }
}

if (!function_exists('old')) {
    function old(string $key, string $default = ''): string
    {
        return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $to): void
    {
        header('Location: ' . $to);
        exit;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $path = '/' . ltrim($path, '/');
        return rtrim(APP_URL, '/') . $path;
    }
}