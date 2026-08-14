<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/landing_pages/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

try {
    require_csrf();

    $eventName = strtolower(trim((string) post('event_name')));
    $slug = strtolower(trim((string) post('slug')));
    $allowedEvents = [
        'page_view',
        'header_cta_click',
        'cta_click',
        'directions_click',
        'wizard_start',
        'form_submit_click',
        'form_submit_attempt',
    ];

    if (!in_array($eventName, $allowedEvents, true) || preg_match('/^[a-z0-9-]{4,255}$/', $slug) !== 1) {
        throw new RuntimeException('Invalid event.');
    }

    $registry = landing_pages_registry();
    $entry = $registry['map'][$slug] ?? null;
    if (!is_array($entry) || !empty($entry['angle'])) {
        throw new RuntimeException('Unknown organic page.');
    }

    $throttleKey = 'landing_event_' . hash('sha256', $slug . '|' . $eventName);
    $lastEventAt = (int) ($_SESSION[$throttleKey] ?? 0);
    if ($lastEventAt > time() - 8) {
        echo json_encode(['ok' => true, 'deduplicated' => true]);
        exit;
    }
    $_SESSION[$throttleKey] = time();

    db_execute(
        'CREATE TABLE IF NOT EXISTS landing_page_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            landing_page VARCHAR(255) NOT NULL,
            procedure_type VARCHAR(100) NOT NULL DEFAULT \'\',
            city VARCHAR(100) NOT NULL DEFAULT \'\',
            event_name VARCHAR(64) NOT NULL,
            session_hash CHAR(64) NOT NULL,
            referrer_host VARCHAR(190) NOT NULL DEFAULT \'\',
            source VARCHAR(100) NOT NULL DEFAULT \'\',
            medium VARCHAR(100) NOT NULL DEFAULT \'\',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_landing_event_page_date (landing_page, created_at),
            KEY idx_landing_event_name_date (event_name, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $referrer = trim((string) post('referrer'));
    $referrerHost = strtolower((string) (parse_url($referrer, PHP_URL_HOST) ?? ''));
    $source = substr(trim((string) post('source')), 0, 100);
    $medium = substr(trim((string) post('medium')), 0, 100);
    $sessionHash = hash('sha256', session_id() . '|' . (defined('APP_URL') ? (string) APP_URL : 'elite-smiles'));

    db_execute(
        'INSERT INTO landing_page_events
            (landing_page, procedure_type, city, event_name, session_hash, referrer_host, source, medium)
         VALUES
            (:landing_page, :procedure_type, :city, :event_name, :session_hash, :referrer_host, :source, :medium)',
        [
            'landing_page' => $slug,
            'procedure_type' => (string) ($entry['procedure'] ?? ''),
            'city' => (string) ($entry['city'] ?? ''),
            'event_name' => $eventName,
            'session_hash' => $sessionHash,
            'referrer_host' => substr($referrerHost, 0, 190),
            'source' => $source,
            'medium' => $medium,
        ]
    );

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false]);
}
