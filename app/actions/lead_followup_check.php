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

if (!function_exists('auth_can_manage_leads') || !auth_can_manage_leads()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden. Your role is read-only.']);
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
    'updated_at',
] as $field) {
    if (leads_has_column($field)) {
        $fields[] = $field;
    }
}
if (lead_related_table_exists('lead_agent_states')) {
    $fields[] = "(SELECT las.status FROM lead_agent_states las WHERE las.lead_id = leads.id LIMIT 1) AS agent_status";
    $fields[] = "(SELECT las.pause_reason FROM lead_agent_states las WHERE las.lead_id = leads.id LIMIT 1) AS agent_pause_reason";
    $fields[] = "(SELECT las.next_action_at FROM lead_agent_states las WHERE las.lead_id = leads.id LIMIT 1) AS agent_next_action_at";
}

$openStages = ['new_lead', 'attempted_contact', 'contacted'];
$nurtureStages = ['no_answer'];
$now = time();
$marked = [];
$checked = 0;

function lead_followup_check_touch_count(int $leadId): int
{
    if (!lead_related_table_exists('lead_messages')) {
        return 0;
    }

    $row = db_one(
        "SELECT COUNT(*) AS total
         FROM lead_messages
         WHERE lead_id = :lead_id
           AND direction = 'outbound'",
        ['lead_id' => $leadId]
    );

    return (int)($row['total'] ?? 0);
}

function lead_followup_check_nurture_interval_days(array $lead, int $touchCount): int
{
    $createdAt = trim((string)($lead['created_at'] ?? ''));
    $updatedAt = trim((string)($lead['updated_at'] ?? ''));
    $anchor = $updatedAt !== '' ? strtotime($updatedAt) : false;
    if ($anchor === false && $createdAt !== '') {
        $anchor = strtotime($createdAt);
    }

    $daysInNurture = $anchor !== false ? (int)floor((time() - $anchor) / 86400) : 0;

    if ($daysInNurture < 14) {
        return 3;
    }
    if ($daysInNurture < 42) {
        return 7;
    }
    if ($touchCount >= 10) {
        return 28;
    }
    return 14;
}

try {
    $orderBy = leads_has_column('updated_at') ? 'updated_at DESC, id DESC' : 'id DESC';
    $rows = db_all('SELECT ' . implode(', ', $fields) . ' FROM leads ORDER BY ' . $orderBy . ' LIMIT 500');

    foreach ($rows as $lead) {
        $leadId = (int)($lead['id'] ?? 0);
        $stage = trim((string)($lead['status'] ?? ''));
        if ($leadId <= 0 || (!in_array($stage, $openStages, true) && !in_array($stage, $nurtureStages, true))) {
            continue;
        }

        $checked++;
        $reasons = [];
        $nextFollowUp = trim((string)($lead['next_follow_up_at'] ?? ''));
        $lastContacted = trim((string)($lead['last_contacted_at'] ?? ''));
        $lastOutbound = trim((string)($lead['last_outbound_at'] ?? ''));
        $createdAt = trim((string)($lead['created_at'] ?? ''));
        $unread = (int)($lead['unread_message_count'] ?? 0);
        $isNurtureLead = in_array($stage, $nurtureStages, true);
        $followUpStatus = trim((string)($lead['follow_up_status'] ?? ''));

        // Confirmed invalid contact data is retained for deduplication, not
        // repeatedly promoted back into the human attention queue.
        if ($followUpStatus === 'unreachable') {
            continue;
        }

        if (function_exists('lead_conversion_patient_hold_active') && lead_conversion_patient_hold_active($lead)) {
            $clearParts = [];
            $clearParams = ['id' => $leadId, 'checked_at' => now()];
            if (leads_has_column('follow_up_status')) {
                $clearParts[] = "follow_up_status = 'ok'";
            }
            if (leads_has_column('last_follow_up_check_at')) {
                $clearParts[] = 'last_follow_up_check_at = :checked_at';
            }
            if ($clearParts) {
                db_query('UPDATE leads SET ' . implode(', ', $clearParts) . ' WHERE id = :id LIMIT 1', $clearParams);
            }
            continue;
        }

        if ($unread > 0) {
            $reasons[] = 'Unread patient reply';
        }

        $nextFollowUpTs = $nextFollowUp !== '' ? strtotime($nextFollowUp) : false;
        $lastOutboundTs = $lastOutbound !== '' ? strtotime($lastOutbound) : false;
        $staleDueResolvedByOutbound = $nextFollowUpTs !== false
            && $nextFollowUpTs <= $now
            && $lastOutboundTs !== false
            && $lastOutboundTs >= $nextFollowUpTs;

        if ($nextFollowUpTs !== false && $nextFollowUpTs <= $now && !$staleDueResolvedByOutbound) {
            $reasons[] = 'Follow-up due';
        }

        if (!$isNurtureLead && $lastContacted === '' && $createdAt !== '' && strtotime($createdAt) !== false && ($now - strtotime($createdAt)) >= 1800) {
            $reasons[] = 'New lead not contacted yet';
        }

        if (!$isNurtureLead && $lastContacted !== '' && strtotime($lastContacted) !== false && ($now - strtotime($lastContacted)) >= 86400) {
            $reasons[] = 'No touch in 24 hours';
        }

        if ($isNurtureLead && $unread <= 0 && $nextFollowUp === '') {
            $lastOutbound = trim((string)($lead['last_outbound_at'] ?? ''));
            $lastTouch = $lastOutbound !== '' ? strtotime($lastOutbound) : false;
            if ($lastTouch === false && $lastContacted !== '') {
                $lastTouch = strtotime($lastContacted);
            }
            if ($lastTouch === false && $createdAt !== '') {
                $lastTouch = strtotime($createdAt);
            }

            $touchCount = lead_followup_check_touch_count($leadId);
            $intervalDays = lead_followup_check_nurture_interval_days($lead, $touchCount);
            if ($lastTouch === false || ($now - $lastTouch) >= ($intervalDays * 86400)) {
                $reasons[] = 'Nurture reactivation due (' . $intervalDays . '-day cadence)';
            }
        }

        if (!$reasons) {
            $clearParts = [];
            $clearParams = ['id' => $leadId, 'checked_at' => now()];
            if (leads_has_column('follow_up_status')) {
                $clearParts[] = "follow_up_status = 'ok'";
            }
            if (leads_has_column('last_follow_up_check_at')) {
                $clearParts[] = 'last_follow_up_check_at = :checked_at';
            }
            if ($staleDueResolvedByOutbound && leads_has_column('next_follow_up_at')) {
                $clearParts[] = 'next_follow_up_at = NULL';
            }
            if ($clearParts) {
                db_query(
                    'UPDATE leads SET ' . implode(', ', $clearParts) . ' WHERE id = :id LIMIT 1',
                    $clearParams
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
