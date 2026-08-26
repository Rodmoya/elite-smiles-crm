<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/actions/lead_update_stage.php
 *
 * AJAX endpoint to save lead stage changes.
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/helpers.php';
require_once dirname(__DIR__) . '/core/db.php';
require_once dirname(__DIR__) . '/core/auth.php';
require_once dirname(__DIR__) . '/core/mailer.php';
require_once dirname(__DIR__) . '/leads/lead_meta.php';
require_once dirname(__DIR__) . '/leads/lead_service.php';
require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/leads/lead_agent_observability.php';

header('Content-Type: application/json; charset=utf-8');

if (!auth_check()) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'message' => 'Unauthorized.',
    ]);
    exit;
}

if (!function_exists('auth_can_manage_leads') || !auth_can_manage_leads()) {

    http_response_code(403);

    echo json_encode([

        'ok' => false,

        'message' => 'Forbidden. Your role is read-only.',

    ]);

    exit;

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Method not allowed.',
    ]);
    exit;
}

try {
    require_csrf();
} catch (Throwable $e) {
    http_response_code(419);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid session token.',
    ]);
    exit;
}

if (!leads_table_exists()) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Leads table not found.',
    ]);
    exit;
}

$leadId = (int) post('lead_id');
$newStage = trim((string) post('status'));
$displayStage = trim((string) post('display_stage'));
$orderedIds = $_POST['ordered_ids'] ?? [];
$sourceOrderedIds = $_POST['source_ordered_ids'] ?? [];

if ($leadId <= 0) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid lead selected.',
    ]);
    exit;
}

if ($newStage === '' || $newStage === '_blank') {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid stage selected.',
    ]);
    exit;
}

$allowedStages = lead_stage_labels();
if (!isset($allowedStages[$newStage])) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Stage is not allowed.',
    ]);
    exit;
}

try {
    lead_pipeline_ensure_schema();
    lead_comm_ensure_schema();
    $existingLead = db_one(
        "SELECT * FROM leads WHERE id = :id LIMIT 1",
        ['id' => $leadId]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Could not verify lead.',
    ]);
    exit;
}
$displayStages = function_exists('lead_conversion_stage_labels') ? lead_conversion_stage_labels() : [];
if ($displayStage !== '') {
    $expectedLegacyStage = function_exists('lead_conversion_stage_legacy_target')
        ? lead_conversion_stage_legacy_target($displayStage)
        : $displayStage;
    if (!isset($displayStages[$displayStage]) || $expectedLegacyStage !== $newStage) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Display stage is not allowed.',
        ]);
        exit;
    }
}

if (!$existingLead) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'message' => 'Lead not found.',
    ]);
    exit;
}

$setParts = [];
$params = [
    'id' => $leadId,
    'status' => $newStage,
];

if (leads_has_column('status')) {
    $setParts[] = "status = :status";
}

if (leads_has_column('updated_at')) {
    $setParts[] = "updated_at = :updated_at";
    $params['updated_at'] = now();
}

if (leads_has_column('pipeline_position')) {
    $pipelinePosition = lead_pipeline_next_position($newStage);
    $normalizedOrderedIds = is_array($orderedIds) ? $orderedIds : [$orderedIds];
    foreach ($normalizedOrderedIds as $index => $orderedId) {
        if ((int) $orderedId === $leadId) {
            $pipelinePosition = max(1, count($normalizedOrderedIds) - (int) $index);
            break;
        }
    }
    $setParts[] = 'pipeline_position = :pipeline_position';
    $params['pipeline_position'] = $pipelinePosition;
}

if ($displayStage === 'scheduling' && leads_has_column('consultation_status')) {
    $setParts[] = "consultation_status = 'scheduling'";
} elseif ($displayStage !== ''
    && in_array($displayStage, ['new_lead', 'lead_answered', 'active_follow_up', 'nurture', 'lost', 'opted_out'], true)
    && leads_has_column('consultation_status')
    && trim((string)($existingLead['consultation_status'] ?? '')) === 'scheduling'
    && trim((string)($existingLead['consultation_date'] ?? '')) === '') {
    $setParts[] = "consultation_status = 'requested'";
}

if (empty($setParts)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'No compatible stage field available to update.',
    ]);
    exit;
}

try {
    db_execute(
        "UPDATE leads SET " . implode(', ', $setParts) . " WHERE id = :id LIMIT 1",
        $params
    );

    $oldStage = trim((string)($existingLead['status'] ?? ''));
    $oldDisplayStage = function_exists('lead_conversion_stage_key') ? lead_conversion_stage_key($existingLead) : $oldStage;
    if ($oldStage !== $newStage || ($displayStage !== '' && $oldDisplayStage !== $displayStage)) {
        $fromLabel = $displayStages[$oldDisplayStage] ?? ($allowedStages[$oldStage] ?? ($oldStage !== '' ? $oldStage : 'Unstaged'));
        $toLabel = $displayStage !== ''
            ? ($displayStages[$displayStage] ?? $displayStage)
            : ($allowedStages[$newStage] ?? $newStage);
        lead_comm_insert_activity(
            $leadId,
            'stage_change',
            'Moved stage from ' . $fromLabel . ' to ' . $toLabel . '.',
            [
                'from' => $oldStage,
                'to' => $newStage,
                'display_from' => $oldDisplayStage,
                'display_to' => $displayStage,
            ]
        );

            if ($newStage === 'attempted_contact') {
                $attemptedPushResult = [];
                $attemptedPushSent = elite_send_attempted_contact_pushover(
                    $existingLead,
                    [
                        'lead_id' => (string) $leadId,
                        'from_stage' => $oldStage,
                        'to_stage' => $newStage,
                        'updated_by_name' => auth_name(),
                    ],
                    $attemptedPushResult
                );

                $recipientLabels = is_array($attemptedPushResult['recipients'] ?? null) ? $attemptedPushResult['recipients'] : [];
                $sentLabels = is_array($attemptedPushResult['sent'] ?? null) ? $attemptedPushResult['sent'] : [];
                $failedLabels = is_array($attemptedPushResult['failed'] ?? null) ? $attemptedPushResult['failed'] : [];
                $recipientText = !empty($recipientLabels) ? implode(', ', $recipientLabels) : 'no configured recipient labels';

                if ($attemptedPushSent) {
                    $pushStatusMessage = !empty($sentLabels) ? ('Attempted-contact push delivered to ' . implode(', ', $sentLabels) . '.') : 'Attempted-contact push delivered.';
                } else {
                    $error = trim((string) ($attemptedPushResult['error'] ?? 'Push delivery failed.'));
                    if (!empty($failedLabels)) {
                        $pushStatusMessage = 'Attempted-contact push failed for ' . implode(', ', $failedLabels) . '. ' . $error;
                    } else {
                        $pushStatusMessage = 'Attempted-contact push failed. ' . $error;
                    }
                }

                lead_comm_insert_activity(
                    $leadId,
                    'attempted_contact_push',
                    $pushStatusMessage,
                    [
                        'recipients' => $recipientText,
                    ]
                );
            }

            if ($newStage === 'consultation_booked') {
                lead_agent_attribute_outcome($leadId, 'consultation_booked');
                if (function_exists('lead_send_consultation_booked_internal_sms')) {
                    lead_send_consultation_booked_internal_sms($leadId, $oldStage, [
                        'source' => 'lead_update_stage',
                        'created_by' => auth_name(),
                    ]);
                }
            }
        }

    if (is_array($orderedIds) && $orderedIds !== []) {
        lead_pipeline_save_stage_order($newStage, $orderedIds);
    }

    if ($oldStage !== '' && $oldStage !== $newStage && is_array($sourceOrderedIds) && $sourceOrderedIds !== []) {
        lead_pipeline_save_stage_order($oldStage, $sourceOrderedIds);
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Lead stage updated.',
        'lead_id' => $leadId,
        'status' => $newStage,
        'status_label' => $displayStage !== '' ? ($displayStages[$displayStage] ?? $displayStage) : ($allowedStages[$newStage] ?? $newStage),
        'display_stage' => $displayStage,
        'display_stage_label' => $displayStage !== '' ? ($displayStages[$displayStage] ?? $displayStage) : '',
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Failed to update lead stage.',
    ]);
    exit;
}
