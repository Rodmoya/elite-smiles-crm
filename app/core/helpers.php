<?php
declare(strict_types=1);

/**
 * Elite Smiles Marketing CRM
 * File: app/core/helpers.php
 *
 * General helper functions used across the project.
 */

require_once __DIR__ . '/../config/config.php';

if (!function_exists('e')) {
    /**
     * Escape output safely for HTML.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    /**
     * Generate absolute URL from project base.
     */
    function base_url(string $path = ''): string
    {
        $path = '/' . ltrim($path, '/');
        return rtrim(APP_URL, '/') . $path;
    }
}

if (!function_exists('asset_url')) {
    /**
     * Generate asset URL.
     */
    function asset_url(string $path = ''): string
    {
        return base_url('public/assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('upload_url')) {
    /**
     * Generate public upload URL.
     */
    function upload_url(string $path = ''): string
    {
        return base_url('public/uploads/' . ltrim($path, '/'));
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return rtrim(PUBLIC_PATH, '/\\') . ($path ? '/' . ltrim($path, '/\\') : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return rtrim(STORAGE_PATH, '/\\') . ($path ? '/' . ltrim($path, '/\\') : '');
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return rtrim(APP_PATH, '/\\') . ($path ? '/' . ltrim($path, '/\\') : '');
    }
}

if (!function_exists('request_method')) {
    function request_method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }
}

if (!function_exists('is_post')) {
    function is_post(): bool
    {
        return request_method() === 'POST';
    }
}

if (!function_exists('is_get')) {
    function is_get(): bool
    {
        return request_method() === 'GET';
    }
}

if (!function_exists('input')) {
    /**
     * Get request input from POST first, then GET.
     */
    function input(string $key, mixed $default = ''): mixed
    {
        if (isset($_POST[$key])) {
            return is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
        }

        if (isset($_GET[$key])) {
            return is_string($_GET[$key]) ? trim($_GET[$key]) : $_GET[$key];
        }

        return $default;
    }
}

if (!function_exists('post')) {
    function post(string $key, mixed $default = ''): mixed
    {
        if (!isset($_POST[$key])) {
            return $default;
        }

        return is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
    }
}

if (!function_exists('get')) {
    function get(string $key, mixed $default = ''): mixed
    {
        if (!isset($_GET[$key])) {
            return $default;
        }

        return is_string($_GET[$key]) ? trim($_GET[$key]) : $_GET[$key];
    }
}

if (!function_exists('has_input')) {
    function has_input(string $key): bool
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }
}

if (!function_exists('old_value')) {
    /**
     * Safe sticky form value.
     */
    function old_value(string $key, string $default = ''): string
    {
        if (isset($_POST[$key])) {
            return trim((string)$_POST[$key]);
        }

        return $default;
    }
}

if (!function_exists('selected')) {
    function selected(mixed $value, mixed $expected): string
    {
        return (string)$value === (string)$expected ? 'selected' : '';
    }
}

if (!function_exists('checked')) {
    function checked(mixed $value, mixed $expected = '1'): string
    {
        return (string)$value === (string)$expected ? 'checked' : '';
    }
}

if (!function_exists('now')) {
    function now(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format);
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $datetime, string $format = 'M j, Y g:i A'): string
    {
        if (!$datetime) {
            return '';
        }

        $timestamp = strtotime($datetime);
        return $timestamp ? date($format, $timestamp) : '';
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, string $format = 'M j, Y'): string
    {
        if (!$date) {
            return '';
        }

        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : '';
    }
}

if (!function_exists('format_phone_us')) {
    function format_phone_us(?string $phone): string
    {
        if (!$phone) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6, 4)
            );
        }

        return (string)$phone;
    }
}

if (!function_exists('only_digits')) {
    function only_digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string)$value) ?? '';
    }
}

if (!function_exists('slugify')) {
    function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
        $value = trim($value, '-');
        return $value !== '' ? $value : 'item';
    }
}

if (!function_exists('str_limit')) {
    function str_limit(string $value, int $limit = 100, string $suffix = '...'): string
    {
        $value = trim($value);

        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit) . $suffix;
    }
}

if (!function_exists('is_active_path')) {
    /**
     * Useful for nav menu highlighting.
     */
    function is_active_path(string $path): bool
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        return str_contains($requestUri, $path);
    }
}

if (!function_exists('active_class')) {
    function active_class(string $path, string $class = 'bg-slate-900 text-white'): string
    {
        return is_active_path($path) ? $class : '';
    }
}

if (!function_exists('ensure_directory')) {
    function ensure_directory(string $directory): bool
    {
        if (is_dir($directory)) {
            return true;
        }

        return mkdir($directory, 0775, true);
    }
}

if (!function_exists('json_response')) {
    function json_response(array $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('redirect_back')) {
    function redirect_back(string $fallback = ''): never
    {
        $target = $_SERVER['HTTP_REFERER'] ?? '';
        if ($target === '') {
            $target = $fallback !== '' ? $fallback : base_url();
        }

        header('Location: ' . $target);
        exit;
    }
}

if (!function_exists('money')) {
    function money(float|int|string|null $amount, string $symbol = '$'): string
    {
        if ($amount === null || $amount === '') {
            return $symbol . '0.00';
        }

        return $symbol . number_format((float)$amount, 2);
    }
}

if (!function_exists('percent')) {
    function percent(float|int|string|null $value, int $decimals = 1): string
    {
        if ($value === null || $value === '') {
            return '0%';
        }

        return number_format((float)$value, $decimals) . '%';
    }
}

if (!function_exists('config_value')) {
    /**
     * Read from global config array using dot notation.
     * Example: config_value('app.name')
     */
    function config_value(string $key, mixed $default = null): mixed
    {
        $config = $GLOBALS['esm_config'] ?? [];
        $segments = explode('.', $key);
        $value = $config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}