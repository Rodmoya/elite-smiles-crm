<?php
declare(strict_types=1);

/**
 * Elite Smiles Marketing CRM
 * File: app/config/config.php
 * Secrets read from .env — never hardcoded here.
 */

if (!defined('ESM_CONFIG_LOADED')) {
    define('ESM_CONFIG_LOADED', true);

    require_once __DIR__ . '/../core/env.php';
    esm_load_env(dirname(__DIR__, 2) . '/.env');

    define('APP_NAME',  'Elite Smiles Marketing CRM');
    define('APP_ENV',   $_ENV['APP_ENV']   ?? 'production');
    define('APP_DEBUG', ($_ENV['APP_DEBUG'] ?? 'false') === 'true');
    define('APP_URL',   $_ENV['APP_URL']   ?? 'https://hi.elitesmilesutah.com/crm');

    define('ROOT_PATH',    dirname(__DIR__, 2));
    define('APP_PATH',     ROOT_PATH . '/app');
    define('PUBLIC_PATH',  ROOT_PATH . '/public');
    define('STORAGE_PATH', ROOT_PATH . '/storage');
    define('LOG_PATH',     STORAGE_PATH . '/logs');
    define('UPLOAD_PATH',  ROOT_PATH . '/public/uploads');

    define('APP_TIMEZONE', 'America/Denver');
    date_default_timezone_set(APP_TIMEZONE);

    define('SESSION_NAME',     'elite_smiles_mktg_session');
    define('SESSION_LIFETIME', 7200);

    define('APP_KEY',       $_ENV['APP_KEY']       ?? '');
    define('PASSWORD_ALGO', PASSWORD_DEFAULT);

    define('DB_HOST',    $_ENV['DB_HOST']    ?? 'localhost');
    define('DB_PORT',    $_ENV['DB_PORT']    ?? '3306');
    define('DB_NAME',    $_ENV['DB_NAME']    ?? '');
    define('DB_USER',    $_ENV['DB_USER']    ?? '');
    define('DB_PASS',    $_ENV['DB_PASS']    ?? '');
    define('DB_CHARSET', 'utf8mb4');

    define('OPENAI_API_KEY',          $_ENV['OPENAI_API_KEY']          ?? '');
    define('OPENAI_MODEL_CHAT',       $_ENV['OPENAI_MODEL_CHAT']       ?? 'gpt-4o');
    define('OPENAI_MODEL_TRANSCRIBE', $_ENV['OPENAI_MODEL_TRANSCRIBE'] ?? 'whisper-1');
    define('GOOGLE_GEMINI_API_KEY', $_ENV['GOOGLE_GEMINI_API_KEY'] ?? '');
    define('GOOGLE_GEMINI_IMAGE_MODEL', $_ENV['GOOGLE_GEMINI_IMAGE_MODEL'] ?? 'gemini-2.5-flash-image');
    define('ELITE_AI_AUTOREPLY_ENABLED', ($_ENV['ELITE_AI_AUTOREPLY_ENABLED'] ?? 'false') === 'true');
    define('ELITE_AI_NEW_LEAD_AUTOTEXT_ENABLED', ($_ENV['ELITE_AI_NEW_LEAD_AUTOTEXT_ENABLED'] ?? 'false') === 'true');
    define('ELITE_AI_MIN_CONFIDENCE', is_numeric($_ENV['ELITE_AI_MIN_CONFIDENCE'] ?? null) ? (float) $_ENV['ELITE_AI_MIN_CONFIDENCE'] : 0.82);

    define('ELITE_PUSHOVER_APP_TOKEN', $_ENV['ELITE_PUSHOVER_APP_TOKEN'] ?? '');
    define('ELITE_PUSHOVER_USER_KEY',  $_ENV['ELITE_PUSHOVER_USER_KEY']  ?? '');

    define('TWILIO_ACCOUNT_SID',           $_ENV['TWILIO_ACCOUNT_SID']           ?? '');
    define('TWILIO_AUTH_TOKEN',            $_ENV['TWILIO_AUTH_TOKEN']            ?? '');
    define('TWILIO_FROM_NUMBER',           $_ENV['TWILIO_FROM_NUMBER']           ?? '');
    define('TWILIO_MESSAGING_SERVICE_SID', $_ENV['TWILIO_MESSAGING_SERVICE_SID'] ?? '');
    define('TWILIO_ENABLED',              ($_ENV['TWILIO_ENABLED'] ?? 'false') === 'true');

    define('ELITE_QUICK_ACTION_SECRET',      $_ENV['ELITE_QUICK_ACTION_SECRET'] ?? '');
    define('ELITE_QUICK_ACTION_TTL_SECONDS', 86400);

    define('ELITE_LEAD_ALERT_FROM_EMAIL', $_ENV['ELITE_LEAD_ALERT_FROM_EMAIL'] ?? 'leads@elitesmilesutah.com');
    define('ELITE_LEAD_EMAIL_TO_TEXT_RECIPIENT', $_ENV['ELITE_LEAD_EMAIL_TO_TEXT_RECIPIENT'] ?? '8016037011@txt.att.net');
    define('ELITE_WEBSITE_WEBHOOK_SECRET', $_ENV['ELITE_WEBSITE_WEBHOOK_SECRET'] ?? '');
    define('ELITE_CODEX_API_TOKEN', $_ENV['ELITE_CODEX_API_TOKEN'] ?? '');

    define('META_WEBHOOK_SECRET',          $_ENV['META_WEBHOOK_SECRET']          ?? '');
    define('META_VERIFY_TOKEN',            $_ENV['META_VERIFY_TOKEN']            ?? '');
    define('META_APP_SECRET',              $_ENV['META_APP_SECRET']              ?? '');
    define('META_ACCESS_TOKEN',            $_ENV['META_ACCESS_TOKEN']            ?? '');
    define('META_GRAPH_VERSION',           $_ENV['META_GRAPH_VERSION']           ?? 'v23.0');
    define('META_LEAD_NOTIFICATION_RECIPIENT', $_ENV['META_LEAD_NOTIFICATION_RECIPIENT'] ?? 'leads@elitesmilesutah.com');
    define('META_NOTIFICATION_FROM_EMAIL',   $_ENV['META_NOTIFICATION_FROM_EMAIL'] ?? ELITE_LEAD_ALERT_FROM_EMAIL);

    define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? '');
    define('SMTP_PORT', is_numeric($_ENV['SMTP_PORT'] ?? null) ? (int) $_ENV['SMTP_PORT'] : 587);
    define('SMTP_ENCRYPTION', strtolower(trim((string) ($_ENV['SMTP_ENCRYPTION'] ?? 'tls'))));
    define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
    define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
    define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? 'hello@hi.elitesmilesutah.com');
    define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'Elite Smiles');
    define('ELITE_EMAIL_AUTO_FIRST_TOUCH_ENABLED', ($_ENV['ELITE_EMAIL_AUTO_FIRST_TOUCH_ENABLED'] ?? 'false') === 'true');
    define('ELITE_EMAIL_AUTOFOLLOWUP_ENABLED', ($_ENV['ELITE_EMAIL_AUTOFOLLOWUP_ENABLED'] ?? 'false') === 'true');
    define('ELITE_EMAIL_FOLLOWUP_CRON_SECRET', $_ENV['ELITE_EMAIL_FOLLOWUP_CRON_SECRET'] ?? '');
    define('ELITE_EMAIL_INBOUND_CRON_SECRET', $_ENV['ELITE_EMAIL_INBOUND_CRON_SECRET'] ?? '');
    define('ELITE_CONSULTATION_REMINDER_CRON_SECRET', $_ENV['ELITE_CONSULTATION_REMINDER_CRON_SECRET'] ?? ELITE_EMAIL_FOLLOWUP_CRON_SECRET);
    define('ELITE_CONSULTATION_REMINDER_SMS_ENABLED', ($_ENV['ELITE_CONSULTATION_REMINDER_SMS_ENABLED'] ?? 'false') === 'true');
    define('ELITE_EMAIL_LOGO_URL', $_ENV['ELITE_EMAIL_LOGO_URL'] ?? APP_URL . '/assets/img/ES-Logo-Stack-500-x-150-px.png');
    define('IMAP_HOST', $_ENV['IMAP_HOST'] ?? SMTP_HOST);
    define('IMAP_PORT', is_numeric($_ENV['IMAP_PORT'] ?? null) ? (int) $_ENV['IMAP_PORT'] : 993);
    define('IMAP_ENCRYPTION', strtolower(trim((string) ($_ENV['IMAP_ENCRYPTION'] ?? 'ssl'))));
    define('IMAP_USER', $_ENV['IMAP_USER'] ?? SMTP_USER);
    define('IMAP_PASS', $_ENV['IMAP_PASS'] ?? SMTP_PASS);

    define('DEFAULT_USER_ROLE',   'viewer');
    define('DEFAULT_LEAD_STATUS', 'new_lead');

    if (APP_DEBUG) {
        ini_set('display_errors', '1');
        error_reporting(E_ALL);
    } else {
        ini_set('display_errors', '0');
        error_reporting(E_ALL);
    }

    ini_set('default_charset',          'UTF-8');
    ini_set('session.use_strict_mode',  '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly',  '1');
    ini_set('session.cookie_samesite',  'Lax');

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }

    foreach ([STORAGE_PATH, LOG_PATH, UPLOAD_PATH] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    $GLOBALS['esm_config'] = [
        'app'      => ['name' => APP_NAME, 'env' => APP_ENV, 'debug' => APP_DEBUG, 'url' => APP_URL],
        'db'       => ['host' => DB_HOST, 'port' => DB_PORT, 'name' => DB_NAME, 'user' => DB_USER],
        'openai'   => [
            'api_key' => OPENAI_API_KEY,
            'chat_model' => OPENAI_MODEL_CHAT,
            'autoreply_enabled' => ELITE_AI_AUTOREPLY_ENABLED,
            'new_lead_autotext_enabled' => ELITE_AI_NEW_LEAD_AUTOTEXT_ENABLED,
            'min_confidence' => ELITE_AI_MIN_CONFIDENCE,
        ],
        'pushover' => ['app_token' => ELITE_PUSHOVER_APP_TOKEN, 'user_key' => ELITE_PUSHOVER_USER_KEY],
    'twilio'   => [
            'account_sid' => TWILIO_ACCOUNT_SID,
            'from_number' => TWILIO_FROM_NUMBER,
            'messaging_service_sid' => TWILIO_MESSAGING_SERVICE_SID,
            'enabled' => TWILIO_ENABLED,
        ],
        'meta' => [
            'webhook_secret' => META_WEBHOOK_SECRET,
            'verify_token' => META_VERIFY_TOKEN,
            'app_secret' => META_APP_SECRET,
            'access_token' => META_ACCESS_TOKEN,
            'graph_version' => META_GRAPH_VERSION,
            'notification_recipient' => META_LEAD_NOTIFICATION_RECIPIENT,
            'notification_from_email' => META_NOTIFICATION_FROM_EMAIL,
        ],
        'quick_actions' => ['secret' => ELITE_QUICK_ACTION_SECRET, 'ttl_seconds' => ELITE_QUICK_ACTION_TTL_SECONDS],
        'lead_alerts' => [
            'from_email' => ELITE_LEAD_ALERT_FROM_EMAIL,
            'email_to_text_recipient' => ELITE_LEAD_EMAIL_TO_TEXT_RECIPIENT,
        ],
        'codex_api' => [
            'enabled' => ELITE_CODEX_API_TOKEN !== '',
        ],
        'smtp' => [
            'host' => SMTP_HOST,
            'port' => SMTP_PORT,
            'encryption' => SMTP_ENCRYPTION,
            'user' => SMTP_USER,
            'from_email' => SMTP_FROM_EMAIL,
            'from_name' => SMTP_FROM_NAME,
            'auto_first_touch_enabled' => ELITE_EMAIL_AUTO_FIRST_TOUCH_ENABLED,
            'autofollowup_enabled' => ELITE_EMAIL_AUTOFOLLOWUP_ENABLED,
            'imap_host' => IMAP_HOST,
            'imap_user' => IMAP_USER,
        ],
    ];
}
