<?php
declare(strict_types=1);

/**
 * Elite Smiles Marketing CRM
 * File: app/core/db.php
 */

require_once __DIR__ . '/../config/config.php';

if (!function_exists('db')) {
    function db(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Use named timezone (handles DST automatically)
            $pdo->exec("SET time_zone = 'America/Denver'");
        } catch (PDOException $e) {
            esm_log('database', 'Connection failed', ['message' => $e->getMessage()]);
            if (APP_DEBUG) die('DB error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
            http_response_code(500);
            die('Database connection error.');
        }

        return $pdo;
    }
}

if (!function_exists('db_query')) {
    function db_query(string $sql, array $params = []): PDOStatement
    {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}

if (!function_exists('db_one')) {
    function db_one(string $sql, array $params = []): ?array
    {
        $row = db_query($sql, $params)->fetch();
        return $row !== false ? $row : null;
    }
}

if (!function_exists('db_all')) {
    function db_all(string $sql, array $params = []): array
    {
        return db_query($sql, $params)->fetchAll();
    }
}

if (!function_exists('db_value')) {
    function db_value(string $sql, array $params = [])
    {
        $value = db_query($sql, $params)->fetchColumn();
        return $value !== false ? $value : null;
    }
}

if (!function_exists('db_execute')) {
    /** Execute a write query. Returns true only if rows were actually affected. */
    function db_execute(string $sql, array $params = []): bool
    {
        return db_query($sql, $params)->rowCount() > 0; // Fixed: was >= 0 (always true)
    }
}

if (!function_exists('db_insert')) {
    function db_insert(string $sql, array $params = []): int
    {
        db_query($sql, $params);
        return (int) db()->lastInsertId();
    }
}

if (!function_exists('db_begin'))    { function db_begin(): bool    { return db()->beginTransaction(); } }
if (!function_exists('db_commit'))   { function db_commit(): bool   { return db()->commit(); } }
if (!function_exists('db_rollBack')) {
    function db_rollBack(): bool
    {
        if (db()->inTransaction()) return db()->rollBack();
        return false;
    }
}

if (!function_exists('esm_log')) {
    function esm_log(string $channel, string $message, array $context = []): void
    {
        $safeChannel = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $channel);
        $logFile = rtrim(LOG_PATH, '/\\') . '/' . $safeChannel . '.log';
        $entry = ['timestamp' => date('Y-m-d H:i:s'), 'message' => $message, 'context' => $context];
        @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    }
}

if (!function_exists('db_test_connection')) {
    function db_test_connection(): bool
    {
        try { return (int) db_value('SELECT 1') === 1; }
        catch (Throwable $e) { esm_log('database', 'Health check failed', ['message' => $e->getMessage()]); return false; }
    }
}
