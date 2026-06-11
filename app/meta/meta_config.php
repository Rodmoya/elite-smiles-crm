<?php
declare(strict_types=1);

/**
 * Meta (Facebook/Instagram) integration settings.
 *
 * Uses app config/env only; no secrets are hard-coded here.
 */

if (!function_exists('meta_cfg_value')) {
    function meta_cfg_value(string $key, mixed $default = null): mixed
    {
        if (!function_exists('config_value')) {
            return $default;
        }

        return config_value('meta.' . $key, $default);
    }
}

if (!function_exists('meta_cfg_string')) {
    function meta_cfg_string(string $key, string $default = ''): string
    {
        $value = meta_cfg_value($key, $default);
        return trim((string) $value);
    }
}

if (!function_exists('meta_cfg_bool')) {
    function meta_cfg_bool(string $key, bool $default = false): bool
    {
        $value = meta_cfg_value($key, $default);
        if (is_bool($value)) {
            return $value;
        }

        return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}

if (!function_exists('meta_cfg_meta_webhook_secret')) {
    function meta_cfg_meta_webhook_secret(): string
    {
        return meta_cfg_string('webhook_secret', '');
    }
}

if (!function_exists('meta_cfg_verify_token')) {
    function meta_cfg_verify_token(): string
    {
        return meta_cfg_string('verify_token', '');
    }
}

if (!function_exists('meta_cfg_app_secret')) {
    function meta_cfg_app_secret(): string
    {
        return meta_cfg_string('app_secret', '');
    }
}

if (!function_exists('meta_cfg_access_token')) {
    function meta_cfg_access_token(): string
    {
        return meta_cfg_string('access_token', '');
    }
}

if (!function_exists('meta_cfg_graph_version')) {
    function meta_cfg_graph_version(): string
    {
        $value = meta_cfg_string('graph_version', 'v23.0');
        return $value !== '' ? $value : 'v23.0';
    }
}

if (!function_exists('meta_cfg_notification_recipient')) {
    function meta_cfg_notification_recipient(): string
    {
        return meta_cfg_string('notification_recipient', '');
    }
}

if (!function_exists('meta_cfg_notification_from_email')) {
    function meta_cfg_notification_from_email(): string
    {
        return meta_cfg_string('notification_from_email', defined('ELITE_LEAD_ALERT_FROM_EMAIL') ? (string) ELITE_LEAD_ALERT_FROM_EMAIL : 'leads@elitesmilesutah.com');
    }
}

if (!function_exists('meta_cfg_twilio_enabled')) {
    function meta_cfg_twilio_enabled(): bool
    {
        return meta_cfg_bool('twilio_enabled', false);
    }
}

