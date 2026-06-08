<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/actions/lead_followup_check.php
 *
 * Checks open leads and marks the ones that need follow-up attention.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/leads/lead_service.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';

header('Content-Type: application/json; charset=utf-8');

if (!auth_check()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    require_csrf();
} catch (Throwable $e) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'Invalid session token.']);
    exit;
}

lead_comm_ensure_schema();

if (!leads_table_exists()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Leads table not found.']);
    exit;
}

$fields = ['id'];
foreach ([
    'full_name',
    'status',
    'created_at',
    'last_contacted_at',
    'last_inbound_at',
    'last_outbound_at',
    'unread_message_count',
    'next_follow_up_at',
    'follow_up_status',
] as $field) {
    if (leads_has_column($field)) {
        $fields[] = $field;
    }
}

$openStages = ['new_lead', 'attempted_contact', 'contacted'];
$now = time();
$marked = [];
$checked = 0;

try {
    $orderBy = leads_has_column('updated_at') ? 'updated_at DESC, id DESC' : 'id DESC';
    $rows = db_all('SELECT ' . implode(', ', $fields) . ' FROM leads ORDER BY ' . $orderBy . ' LIMIT 500');

    foreach ($rows as $lead) {
        $leadId = (int)($lead['id'] ?? 0);
        $stage = trim((string)($lead['status'] ?? ''));
        if ($leadId <= 0 || !in_array($stage, $openStages, true)) {
            continue;
        }

        $checked++;
        $reasons = [];
        $nextFollowUp = trim((string)($lead['next_follow_up_at'] ?? ''));
        $lastContacted = trim((string)($lead['last_contacted_at'] ?? ''));
        $createdAt = trim((string)($lead['created_at'] ?? ''));
        $unread = (int)($lead['unread_message_count'] ?? 0);

        if ($unread > 0) {
            $reasons[] = 'Unread patient reply';
        }

        if ($nextFollowUp !== '' && strtotime($nextFollowUp) !== false && strtotime($nextFollowUp) <= $now) {
            $reasons[] = 'Follow-up due';
        }

        if ($lastContacted === '' && $createdAt !== '' && strtotime($createdAt) !== false && ($now - strtotime($createdAt)) >= 1800) {
            $reasons[] = 'New lead not contacted yet';
        }

        if ($lastContacted !== '' && strtotime($lastContacted) !== false && ($now - strtotime($lastContacted)) >= 86400) {
            $reasons[] = 'No touch in 24 hours';
        }

        if (!$reasons) {
            if (leads_has_column('last_follow_up_check_at')) {
                db_query(
                    "UPDATE leads SET follow_up_status = 'ok', last_follow_up_check_at = :checked_at WHERE id = :id LIMIT 1",
                    ['id' => $leadId, 'checked_at' => now()]
                );
            }
            continue;
        }

        $params = [
            'id' => $leadId,
            'checked_at' => now(),
            'next_follow_up_at' => date('Y-m-d H:i:s', $now),
        ];
        $setParts = [];
        if (leads_has_column('follow_up_status')) {
            $setParts[] = "follow_up_status = 'needs_follow_up'";
        }
        if (leads_has_column('last_follow_up_check_at')) {
            $setParts[] = 'last_follow_up_check_at = :checked_at';
        }
        if (leads_has_column('next_follow_up_at')) {
            $setParts[] = 'next_follow_up_at = :next_follow_up_at';
        }
        if (leads_has_column('updated_at')) {
            $setParts[] = 'updated_at = :checked_at';
        }

        if ($setParts) {
            db_query('UPDATE leads SET ' . implode(', ', $setParts) . ' WHERE id = :id LIMIT 1', $params);
        }

        lead_comm_insert_activity($leadId, 'follow_up_check', 'Automatic follow-up check marked this lead for attention: ' . implode('; ', $reasons) . '.', [
            'reasons' => $reasons,
        ], 'System');

        $marked[] = [
            'lead_id' => $leadId,
            'name' => (string)($lead['full_name'] ?? 'Lead'),
            'reasons' => $reasons,
        ];
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Follow-up check failed.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => count($marked) . ' lead' . (count($marked) === 1 ? '' : 's') . ' marked for follow-up.',
    'checked' => $checked,
    'marked' => $marked,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
