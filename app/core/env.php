<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: app/core/env.php
 *
 * Simple .env loader. Call once from config.php.
 * Reads KEY=VALUE pairs, ignores comments and blanks.
 */

if (!function_exists('esm_load_env')) {
    function esm_load_env(string $envFile): void
    {
        if (!is_file($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key   = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Strip surrounding quotes
            if (
                strlen($value) >= 2 &&
                (
                    ($value[0] === '"' && $value[-1] === '"') ||
                    ($value[0] === "'" && $value[-1] === "'")
                )
            ) {
                $value = substr($value, 1, -1);
            }

            if ($key !== '' && !array_key_exists($key, $_ENV)) {
                $_ENV[$key]    = $value;
                putenv($key . '=' . $value);
            }
        }
    }
}
