<?php
declare(strict_types=1);

/**
 * Meta webhook queue processor.
 *
 * Can be run:
 * - via CLI: php app/api/meta_webhook_process.php
 * - via HTTP/cron with META_WEBHOOK_SECRET
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/meta/meta_config.php';
require_once dirname(__DIR__) . '/meta/meta_queue.php';
require_once dirname(__DIR__) . '/meta/meta_lead_service.php';

header('Content-Type: application/json; charset=utf-8');

function meta_processor_is_cli(): bool
{
    return PHP_SAPI === 'cli';
}

function meta_processor_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function meta_processor_secret_input(): string
{
    $header = trim((string) ($_SERVER['HTTP_X_ELITE_CRON_SECRET'] ?? $_SERVER['HTTP_X_META_PROCESSOR_SECRET'] ?? ''));
    if ($header !== '') {
        return $header;
    }

    return trim((string) ($_GET['secret'] ?? $_POST['secret'] ?? ''));
}

function meta_processor_require_auth(): void
{
    if (meta_processor_is_cli()) {
        return;
    }

    $secret = trim((string) meta_cfg_meta_webhook_secret());
    if ($secret === '') {
        meta_processor_json(['ok' => false, 'message' => 'Processor secret is not configured.'], 503);
    }

    if (!hash_equals($secret, meta_processor_secret_input())) {
        meta_processor_json(['ok' => false, 'message' => 'Unauthorized.'], 401);
    }
}

function meta_processor_limit(): int
{
    if (meta_processor_is_cli()) {
        global $argv;
        foreach (($argv ?? []) as $arg) {
            if (str_starts_with((string) $arg, '--limit=')) {
                return max(1, min(100, (int) substr((string) $arg, 8)));
            }
        }
    }

    return max(1, min(100, (int) ($_GET['limit'] ?? $_POST['limit'] ?? 10)));
}

function meta_processor_db_ready(): array
{
    if (trim((string) DB_HOST) === '' || trim((string) DB_NAME) === '' || trim((string) DB_USER) === '') {
        return ['ok' => false, 'message' => 'Database connection settings are incomplete.'];
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->query('SELECT 1');

        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Database is not reachable: ' . $e->getMessage()];
    }
}

function meta_processor_fail_claim(array $claim, string $message): array
{
    $record = $claim['record'];
    $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;
    $record['last_attempt_at'] = now();
    $record['error_summary'] = $message;
    meta_queue_finalize_record((string) $claim['path'], 'failed', $record);

    return [
        'event_id' => (string) ($record['event_id'] ?? ''),
        'status' => 'failed',
        'message' => $message,
    ];
}

function meta_processor_is_retryable_message(string $message): bool
{
    $message = strtolower(trim($message));
    if ($message === '') {
        return false;
    }

    foreach ([
        'database',
        'not reachable',
        'connection',
        'timed out',
        'timeout',
        'tempor',
        'meta_access_token',
        'graph api',
        'processor secret',
        'lookup did not return usable lead details',
    ] as $needle) {
        if (str_contains($message, $needle)) {
            return true;
        }
    }

    return false;
}

function meta_processor_retry_or_fail_claim(array $claim, string $message, bool $forceFail = false): array
{
    $record = $claim['record'];
    $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;
    $record['last_attempt_at'] = now();
    $record['error_summary'] = $message;

    $maxAttempts = meta_cfg_queue_max_attempts();
    $shouldRetry = !$forceFail
        && meta_processor_is_retryable_message($message)
        && $record['attempts'] < $maxAttempts;

    if ($shouldRetry) {
        meta_queue_requeue_record((string) $claim['path'], $record, $message);
        return [
            'event_id' => (string) ($record['event_id'] ?? ''),
            'status' => 'retrying',
            'attempts' => (int) $record['attempts'],
            'message' => $message,
        ];
    }

    meta_queue_finalize_record((string) $claim['path'], 'failed', $record);
    return [
        'event_id' => (string) ($record['event_id'] ?? ''),
        'status' => 'failed',
        'attempts' => (int) $record['attempts'],
        'message' => $message,
    ];
}

function meta_processor_claims(int $limit): array
{
    $claims = [];

    foreach (meta_queue_list_pending_paths($limit) as $path) {
        $claim = meta_queue_claim_record($path);
        if ($claim !== null) {
            $claims[] = $claim;
        }
    }

    return $claims;
}

function meta_processor_lead_exists(string $externalLeadId): bool
{
    $externalLeadId = trim($externalLeadId);
    if ($externalLeadId === '' || !function_exists('leads_has_column') || !leads_has_column('external_lead_id')) {
        return false;
    }

    try {
        $row = db_one('SELECT id FROM leads WHERE external_lead_id = :external_lead_id LIMIT 1', [
            'external_lead_id' => $externalLeadId,
        ]);
    } catch (Throwable $e) {
        return false;
    }

    return !empty($row['id']);
}

function meta_processor_poll_form_leads(int $limit): array
{
    $formIds = meta_cfg_form_ids();
    if ($formIds === []) {
        return [];
    }

    $results = [];
    $remaining = max(1, $limit);

    foreach ($formIds as $formId) {
        if ($remaining <= 0) {
            break;
        }

        $fetch = meta_graph_fetch_form_leads($formId, min(25, $remaining));
        if (empty($fetch['ok'])) {
            $results[] = [
                'source' => 'form_poll',
                'form_id' => $formId,
                'status' => 'failed',
                'message' => (string) ($fetch['message'] ?? 'Could not fetch Meta form leads.'),
            ];
            continue;
        }

        foreach (($fetch['leads'] ?? []) as $lead) {
            if (!is_array($lead)) {
                continue;
            }

            $leadgenId = trim((string) ($lead['id'] ?? $lead['leadgen_id'] ?? ''));
            if ($leadgenId === '') {
                continue;
            }

            if (meta_processor_lead_exists($leadgenId)) {
                continue;
            }

            $lead['leadgen_id'] = $leadgenId;
            $lead['form_id'] = trim((string) ($lead['form_id'] ?? $formId));

            try {
                $processed = meta_lead_process(json_encode($lead, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', $lead);
            } catch (Throwable $e) {
                $results[] = [
                    'source' => 'form_poll',
                    'form_id' => $formId,
                    'leadgen_id' => $leadgenId,
                    'status' => 'failed',
                    'message' => 'Meta form poll processing threw an exception: ' . $e->getMessage(),
                ];
                continue;
            }

            foreach (($processed['results'] ?? []) as $item) {
                $results[] = [
                    'source' => 'form_poll',
                    'form_id' => $formId,
                    'leadgen_id' => (string) ($item['leadgen_id'] ?? $leadgenId),
                    'lead_id' => (int) ($item['lead_id'] ?? 0),
                    'status' => !empty($item['ok']) ? (!empty($item['duplicate_found']) ? 'duplicate' : 'processed') : 'failed',
                    'message' => (string) ($item['message'] ?? 'Processed.'),
                ];
            }

            $remaining--;
            if ($remaining <= 0) {
                break;
            }
        }
    }

    return $results;
}

function meta_processor_payload_from_record(array $record): array
{
    $payload = is_array($record['payload'] ?? null) ? $record['payload'] : [];
    $candidates = is_array($record['candidates'] ?? null) ? $record['candidates'] : [];

    if ($candidates === []) {
        return $payload;
    }

    $hasLeadgenInPayload = false;
    foreach (meta_queue_extract_candidates($payload) as $candidate) {
        if (trim((string) ($candidate['leadgen_id'] ?? '')) !== '') {
            $hasLeadgenInPayload = true;
            break;
        }
    }

    if ($hasLeadgenInPayload) {
        return $payload;
    }

    $entryId = trim((string) ($record['page_id'] ?? ''));
    if ($entryId === '' && !empty($payload['entry'][0]['id'])) {
        $entryId = trim((string) $payload['entry'][0]['id']);
    }
    if ($entryId === '') {
        $entryId = 'queued-meta-event';
    }

    $changes = [];
    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }

        $leadgenId = trim((string) ($candidate['leadgen_id'] ?? ''));
        if ($leadgenId === '') {
            continue;
        }

        $changes[] = [
            'field' => 'leadgen',
            'value' => [
                'leadgen_id' => $leadgenId,
                'form_id' => trim((string) ($candidate['form_id'] ?? '')),
                'page_id' => trim((string) ($candidate['page_id'] ?? '')),
            ],
        ];
    }

    if ($changes === []) {
        return $payload;
    }

    return [
        'object' => is_scalar($payload['object'] ?? null) ? (string) $payload['object'] : 'page',
        'entry' => [[
            'id' => $entryId,
            'changes' => $changes,
        ]],
    ];
}

try {
    meta_processor_require_auth();
    meta_queue_ensure_directories();

    $limit = meta_processor_limit();
    $pollResults = [];
    $dbReady = meta_processor_db_ready();

    if (trim((string) meta_cfg_access_token()) !== '' && !empty($dbReady['ok'])) {
        $pollResults = meta_processor_poll_form_leads($limit);
    }

    $claims = meta_processor_claims($limit);
    $results = [];

    foreach ($claims as $claim) {
        $record = $claim['record'];
        $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;
        $record['last_attempt_at'] = now();

        $payload = meta_processor_payload_from_record($record);
        $candidateCount = (int) ($record['candidate_count'] ?? 0);

        if ($candidateCount <= 0) {
            $record['error_summary'] = '';
            meta_queue_finalize_record((string) $claim['path'], 'ignored', $record);
            $results[] = [
                'event_id' => (string) ($record['event_id'] ?? ''),
                'status' => 'ignored',
                'message' => 'No leadgen payload found.',
            ];
            continue;
        }

        if (trim((string) meta_cfg_access_token()) === '') {
            $results[] = meta_processor_retry_or_fail_claim($claim, 'META_ACCESS_TOKEN is required for processing Meta lead events.');
            continue;
        }

        $dbReady = meta_processor_db_ready();
        if (empty($dbReady['ok'])) {
            $results[] = meta_processor_retry_or_fail_claim($claim, (string) ($dbReady['message'] ?? 'Database is not reachable.'));
            continue;
        }

        try {
            $result = meta_lead_process(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', $payload);
        } catch (Throwable $e) {
            $results[] = meta_processor_retry_or_fail_claim($claim, 'Meta processing threw an exception: ' . $e->getMessage());
            continue;
        }

        $failedMessages = [];
        foreach (($result['results'] ?? []) as $item) {
            if (empty($item['ok'])) {
                $failedMessages[] = trim((string) ($item['message'] ?? 'Unknown processing failure.'));
            }
        }

        if ($failedMessages !== []) {
            $results[] = meta_processor_retry_or_fail_claim(
                $claim,
                implode(' | ', array_slice($failedMessages, 0, 5))
            );
            continue;
        }

        $record['error_summary'] = '';
        meta_queue_finalize_record((string) $claim['path'], 'processed', $record);
        $results[] = [
            'event_id' => (string) ($record['event_id'] ?? ''),
            'status' => 'processed',
            'message' => (string) ($result['message'] ?? 'Processed.'),
            'result_count' => count($result['results'] ?? []),
        ];
    }

    meta_processor_json([
        'ok' => true,
        'polled' => count($pollResults),
        'claimed' => count($claims),
        'results' => array_merge($pollResults, $results),
    ]);
} catch (Throwable $e) {
    if (function_exists('esm_log')) {
        esm_log('meta_webhook_process', 'Meta processor crashed.', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    meta_processor_json([
        'ok' => false,
        'message' => 'Meta processor crashed.',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ], 500);
}
