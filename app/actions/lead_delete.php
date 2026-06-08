<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/actions/lead_delete.php
 *
 * AJAX endpoint to permanently delete a lead.
 */

ob_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');

function lead_delete_collect_buffer(): string
{
    $contents = '';

    while (ob_get_level() > 0) {
        $chunk = (string) ob_get_contents();
        if ($chunk !== '') {
            $contents .= $chunk;
        }
        ob_end_clean();
    }

    return trim($contents);
}

function lead_delete_json_response(int $statusCode, array $payload): void
{
    $buffer = lead_delete_collect_buffer();

    if ($buffer !== '') {
        $payload['buffer_output'] = $buffer;
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

register_shutdown_function(function (): void {
    $error = error_get_last();

    if ($error === null) {
        return;
    }

    $fatalTypes = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR,
        E_RECOVERABLE_ERROR,
    ];

    if (!in_array((int) $error['type'], $fatalTypes, true)) {
        return;
    }

    lead_delete_json_response(500, [
        'ok' => false,
        'message' => 'Fatal error while deleting lead.',
        'debug' => [
            'type' => $error['type'] ?? null,
            'message' => $error['message'] ?? '',
            'file' => $error['file'] ?? '',
            'line' => $error['line'] ?? 0,
        ],
    ]);
});

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/leads/lead_meta.php';
require_once dirname(__DIR__) . '/leads/lead_service.php';

if (!function_exists('auth_check')) {
    lead_delete_json_response(500, [
        'ok' => false,
        'message' => 'Auth helper not available.',
    ]);
}

if (!auth_check()) {
    lead_delete_json_response(401, [
        'ok' => false,
        'message' => 'Unauthorized.',
    ]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    lead_delete_json_response(405, [
        'ok' => false,
        'message' => 'Method not allowed.',
    ]);
}

try {
    require_csrf();
} catch (Throwable $e) {
    lead_delete_json_response(419, [
        'ok' => false,
        'message' => 'Invalid session token.',
        'debug' => [
            'exception' => $e->getMessage(),
        ],
    ]);
}

if (!function_exists('leads_table_exists') || !leads_table_exists()) {
    lead_delete_json_response(500, [
        'ok' => false,
        'message' => 'Leads table not found.',
    ]);
}

$leadId = (int) post('lead_id');

if ($leadId <= 0) {
    lead_delete_json_response(422, [
        'ok' => false,
        'message' => 'Invalid lead selected.',
    ]);
}

try {
    $leadRow = db_one(
        "SELECT id, full_name FROM leads WHERE id = :id LIMIT 1",
        ['id' => $leadId]
    );
} catch (Throwable $e) {
    lead_delete_json_response(500, [
        'ok' => false,
        'message' => 'Could not verify lead before deletion.',
        'debug' => [
            'exception' => $e->getMessage(),
        ],
    ]);
}

if (!$leadRow) {
    lead_delete_json_response(404, [
        'ok' => false,
        'message' => 'Lead not found.',
    ]);
}

try {
    db_execute(
        "DELETE FROM leads WHERE id = :id LIMIT 1",
        ['id' => $leadId]
    );

    lead_delete_json_response(200, [
        'ok' => true,
        'message' => 'Lead deleted successfully.',
        'lead_id' => $leadId,
        'full_name' => (string) ($leadRow['full_name'] ?? ''),
    ]);
} catch (Throwable $e) {
    lead_delete_json_response(500, [
        'ok' => false,
        'message' => 'Failed to delete lead.',
        'debug' => [
            'exception' => $e->getMessage(),
            'lead_id' => $leadId,
        ],
    ]);
}