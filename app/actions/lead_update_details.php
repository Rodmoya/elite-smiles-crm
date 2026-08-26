<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * File: /app/actions/lead_update_details.php
 *
 * AJAX endpoint to save:
 * - full_name
 * - phone
 * - email
 * - preferred_contact
 * - procedure_interest
 * - financing_needed
 * - financing_option
 * - consultation_status
 * - consultation_date
 * - source
 * - landing_page
 * - campaign
 * - notes
 * - lead_value
 * - lost_reason
 */

ob_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');

function lead_update_collect_buffer(): string
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

function lead_update_json_response(int $statusCode, array $payload): void
{
    $buffer = lead_update_collect_buffer();

    if ($buffer !== '') {
        $payload['buffer_output'] = $buffer;
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function lead_update_normalize_datetime(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $formats = [
        'Y-m-d\TH:i',
        'Y-m-d\TH:i:s',
        'Y-m-d H:i',
        'Y-m-d H:i:s',
        'Y-m-d',
    ];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            if ($format === 'Y-m-d') {
                $dt->setTime(0, 0, 0);
            }
            return $dt->format('Y-m-d H:i:s');
        }
    }

    $timestamp = strtotime($value);
    if ($timestamp !== false) {
        return date('Y-m-d H:i:s', $timestamp);
    }

    return null;
}

function lead_update_normalize_date(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    foreach (['Y-m-d', 'm/d/Y', 'm-d-Y'] as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
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

    lead_update_json_response(500, [
        'ok' => false,
        'message' => 'Fatal error while saving lead details.',
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

require_once dirname(__DIR__) . '/leads/lead_communications.php';
require_once dirname(__DIR__) . '/smile_design/smile_design_service.php';
require_once dirname(__DIR__) . '/dentrix/dentrix_bridge.php';

if (!function_exists('auth_check')) {
    lead_update_json_response(500, [
        'ok' => false,
        'message' => 'Auth helper not available.',
    ]);
}

if (!auth_check()) {
    lead_update_json_response(401, [
        'ok' => false,
        'message' => 'Unauthorized.',
    ]);
}

if (!function_exists('auth_can_manage_leads') || !auth_can_manage_leads()) {

    lead_update_json_response(403, [

        'ok' => false,

        'message' => 'Forbidden. Your role is read-only.',

    ]);

}



if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    lead_update_json_response(405, [
        'ok' => false,
        'message' => 'Method not allowed.',
    ]);
}

try {
    require_csrf();
} catch (Throwable $e) {
    lead_update_json_response(419, [
        'ok' => false,
        'message' => 'Invalid session token.',
        'debug' => [
            'exception' => $e->getMessage(),
        ],
    ]);
}

if (!function_exists('leads_table_exists') || !leads_table_exists()) {
    lead_update_json_response(500, [
        'ok' => false,
        'message' => 'Leads table not found.',
    ]);
}

if (function_exists('lead_pipeline_ensure_schema')) {
    lead_pipeline_ensure_schema();
}

$leadId = (int) post('lead_id');

$fullName = trim((string) post('full_name'));
$phone = trim((string) post('phone'));
$email = trim((string) post('email'));
$preferredContact = trim((string) post('preferred_contact'));
$procedureInterest = trim((string) post('procedure_interest'));
$financingNeeded = trim((string) post('financing_needed'));
$financingOption = trim((string) post('financing_option'));
$consultationStatus = trim((string) post('consultation_status'));
$consultationDateRaw = trim((string) post('consultation_date'));
$source = trim((string) post('source'));
$landingPage = trim((string) post('landing_page'));
$campaign = trim((string) post('campaign'));
$notes = trim((string) post('notes'));
$leadValueRaw = trim((string) post('lead_value'));
$lostReason = trim((string) post('lost_reason'));

$smsOptStatus = trim((string) post('sms_opt_status'));

$dateOfBirthRaw = trim((string) post('date_of_birth'));
$intentType = trim((string) post('intent_type'));

$schedulingPreferredDay = trim((string) post('scheduling_preferred_day'));

$schedulingPreferredTime = trim((string) post('scheduling_preferred_time'));

$nextFollowUpRaw = trim((string) post('next_follow_up_at'));

if ($leadId <= 0) {
    lead_update_json_response(422, [
        'ok' => false,
        'message' => 'Invalid lead selected.',
    ]);
}

try {
    $leadRow = db_one(
        "SELECT * FROM leads WHERE id = :id LIMIT 1",
        ['id' => $leadId]
    );
} catch (Throwable $e) {
    lead_update_json_response(500, [
        'ok' => false,
        'message' => 'Could not verify lead.',
        'debug' => [
            'exception' => $e->getMessage(),
        ],
    ]);
}

if (!$leadRow) {
    lead_update_json_response(404, [
        'ok' => false,
        'message' => 'Lead not found.',
    ]);
}

$phoneRawInput = $phone;
$phoneAnalysis = elite_phone_us_analysis($phoneRawInput);
$phone = elite_phone_storage_value($phoneRawInput);
$phoneChanged = $phone !== trim((string)($leadRow['phone'] ?? ''));

$preferredContactOptions = [
    '',
    'call',
    'text',
    'email',
    'instagram_dm',
    'facebook_message',
    'whatsapp',
];
if (!in_array($preferredContact, $preferredContactOptions, true)) {
    $preferredContact = '';
}

$financingNeededAllowed = ['yes', 'no', 'unsure'];
if ($financingNeeded === '' || !in_array($financingNeeded, $financingNeededAllowed, true)) {
    $financingNeeded = 'unsure';
}

$financingOptionAllowed = [
    'none',
    'mountain_america',
    'sunbit',
    'cherry',
    'carecredit',
];
if ($financingOption === '' || !in_array($financingOption, $financingOptionAllowed, true)) {
    $financingOption = 'none';
}

$consultationStatusAllowed = [
    '',
    'requested',
    'scheduled',
    'completed',
    'no_show',
    'not_interested',
];
if (!in_array($consultationStatus, $consultationStatusAllowed, true)) {
    $consultationStatus = '';
}

$lostReasonOptions = function_exists('lead_lost_reason_options')
    ? lead_lost_reason_options()
    : [];
if ($lostReason !== '' && !empty($lostReasonOptions) && !array_key_exists($lostReason, $lostReasonOptions)) {
    $lostReason = '';
}

$smsOptStatusAllowed = ['unknown', 'opted_in', 'opted_out'];
if ($smsOptStatus === '' || !in_array($smsOptStatus, $smsOptStatusAllowed, true)) {
    $smsOptStatus = (string)($leadRow['sms_opt_status'] ?? 'unknown');
    if (!in_array($smsOptStatus, $smsOptStatusAllowed, true)) {
        $smsOptStatus = 'unknown';
    }
}
$validSources = [
    'manual',
    'website',
    'landing_page',
    'google',
    'google_ads',
    'facebook',
    'instagram',
    'ringcentral',
    'referral',
    'walk_in',
];
if ($source !== '' && !in_array($source, $validSources, true)) {
    $source = 'manual';
}
if ($source === '') {
    $source = 'manual';
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    lead_update_json_response(422, [
        'ok' => false,
        'message' => 'Email format is invalid.',
    ]);
}

$consultationDate = lead_update_normalize_datetime($consultationDateRaw);
if ($consultationDateRaw !== '' && $consultationDate === null) {
    lead_update_json_response(422, [
        'ok' => false,
        'message' => 'Consultation date must be a valid date/time.',
        'debug' => [
            'received' => $consultationDateRaw,
        ],
    ]);
}

try {
    $duplicateLead = lead_find_duplicate([
        'full_name' => $fullName,
        'phone' => $phone,
        'email' => $email,
        'external_lead_id' => '',
    ], $leadId);
    if ($duplicateLead) {
        lead_update_json_response(409, [
            'ok' => false,
            'duplicate_found' => true,
            'duplicate_match_type' => (string)($duplicateLead['duplicate_match_type'] ?? ''),
            'duplicate_lead_id' => (int)($duplicateLead['id'] ?? 0),
            'message' => lead_duplicate_message($duplicateLead),
        ]);
    }
} catch (Throwable $e) {
    esm_log('lead_duplicates', 'Duplicate check failed during lead update.', [
        'lead_id' => $leadId,
        'message' => $e->getMessage(),
    ]);
}

lead_comm_ensure_schema();

$dateOfBirth = lead_update_normalize_date($dateOfBirthRaw);

if ($dateOfBirthRaw !== '' && $dateOfBirth === null) {
    lead_update_json_response(422, [
        'ok' => false,
        'message' => 'Date of birth must be a valid date.',
        'debug' => [
            'received' => $dateOfBirthRaw,
        ],
    ]);
}

$nextFollowUpAt = lead_update_normalize_datetime($nextFollowUpRaw);

if ($nextFollowUpRaw !== '' && $nextFollowUpAt === null) {
    lead_update_json_response(422, [
        'ok' => false,
        'message' => 'Next follow-up must be a valid date/time.',
        'debug' => [
            'received' => $nextFollowUpRaw,
        ],
    ]);
}

$leadValue = null;
if ($leadValueRaw !== '') {
    if (!is_numeric($leadValueRaw)) {
        lead_update_json_response(422, [
            'ok' => false,
            'message' => 'Lead value must be numeric.',
        ]);
    }

    $leadValue = number_format((float) $leadValueRaw, 2, '.', '');

    if ((float) $leadValue < 0) {
        lead_update_json_response(422, [
            'ok' => false,
            'message' => 'Lead value cannot be negative.',
        ]);
    }
}

$setParts = [];
$params = ['id' => $leadId];

if (function_exists('leads_has_column') && leads_has_column('full_name')) {
    $setParts[] = "full_name = :full_name";
    $params['full_name'] = $fullName;
}

if (function_exists('leads_has_column') && leads_has_column('phone')) {
    $setParts[] = "phone = :phone";
    $params['phone'] = ($phone !== '' ? $phone : null);
}

if ($phoneChanged && function_exists('leads_has_column') && leads_has_column('phone_raw')) {
    $setParts[] = "phone_raw = :phone_raw";
    $params['phone_raw'] = ($phoneRawInput !== '' ? $phoneRawInput : null);
}

if (function_exists('leads_has_column') && leads_has_column('phone_validation_status')) {
    $setParts[] = "phone_validation_status = :phone_validation_status";
    $params['phone_validation_status'] = (string)$phoneAnalysis['status'];
}

if (function_exists('leads_has_column') && leads_has_column('email')) {
    $setParts[] = "email = :email";
    $params['email'] = ($email !== '' ? $email : null);
}

if (function_exists('leads_has_column') && leads_has_column('preferred_contact')) {
    $setParts[] = "preferred_contact = :preferred_contact";
    $params['preferred_contact'] = ($preferredContact !== '' ? $preferredContact : null);
}

if (function_exists('leads_has_column') && leads_has_column('procedure_interest')) {
    $setParts[] = "procedure_interest = :procedure_interest";
    $params['procedure_interest'] = ($procedureInterest !== '' ? $procedureInterest : null);
}

if (function_exists('leads_has_column') && leads_has_column('source')) {
    $setParts[] = "source = :source";
    $params['source'] = $source;
}

if (function_exists('leads_has_column') && leads_has_column('landing_page')) {
    $setParts[] = "landing_page = :landing_page";
    $params['landing_page'] = ($landingPage !== '' ? $landingPage : null);
}

if (function_exists('leads_has_column') && leads_has_column('campaign')) {
    $setParts[] = "campaign = :campaign";
    $params['campaign'] = ($campaign !== '' ? $campaign : null);
}

if (function_exists('leads_has_column') && leads_has_column('financing_needed')) {
    $setParts[] = "financing_needed = :financing_needed";
    $params['financing_needed'] = $financingNeeded;
}

if (function_exists('leads_has_column') && leads_has_column('financing_option')) {
    $setParts[] = "financing_option = :financing_option";
    $params['financing_option'] = $financingOption;
}

if (function_exists('leads_has_column') && leads_has_column('consultation_status')) {
    $setParts[] = "consultation_status = :consultation_status";
    $params['consultation_status'] = ($consultationStatus !== '' ? $consultationStatus : null);
}

if (function_exists('leads_has_column') && leads_has_column('consultation_date')) {
    $setParts[] = "consultation_date = :consultation_date";
    $params['consultation_date'] = $consultationDate;
}

if (function_exists('leads_has_column') && leads_has_column('lead_value')) {
    $setParts[] = "lead_value = :lead_value";
    $params['lead_value'] = $leadValue;
}

if (function_exists('leads_has_column') && leads_has_column('lost_reason')) {
    $setParts[] = "lost_reason = :lost_reason";
    $params['lost_reason'] = ($lostReason !== '' ? $lostReason : null);
}

if (function_exists('leads_has_column') && leads_has_column('date_of_birth')) {

    $setParts[] = "date_of_birth = :date_of_birth";

    $params['date_of_birth'] = $dateOfBirth;

}

if (function_exists('leads_has_column') && leads_has_column('intent_type')) {
    $setParts[] = "intent_type = :intent_type";
    $params['intent_type'] = ($intentType !== '' ? mb_substr($intentType, 0, 120) : null);
}

if (function_exists('leads_has_column') && leads_has_column('scheduling_preferred_day')) {

    $setParts[] = "scheduling_preferred_day = :scheduling_preferred_day";

    $params['scheduling_preferred_day'] = ($schedulingPreferredDay !== '' ? $schedulingPreferredDay : null);

}

if (function_exists('leads_has_column') && leads_has_column('scheduling_preferred_time')) {

    $setParts[] = "scheduling_preferred_time = :scheduling_preferred_time";

    $params['scheduling_preferred_time'] = ($schedulingPreferredTime !== '' ? $schedulingPreferredTime : null);

}

if (function_exists('leads_has_column') && leads_has_column('next_follow_up_at')) {

    $setParts[] = "next_follow_up_at = :next_follow_up_at";

    $params['next_follow_up_at'] = $nextFollowUpAt;

}

if (function_exists('leads_has_column') && leads_has_column('notes')) {
    $setParts[] = "notes = :notes";
    $params['notes'] = $notes;
}

if (function_exists('leads_has_column') && leads_has_column('sms_opt_status')) {
    $setParts[] = "sms_opt_status = :sms_opt_status";
    $params['sms_opt_status'] = $smsOptStatus;
}

if (function_exists('leads_has_column') && leads_has_column('sms_opted_out_at')) {
    $setParts[] = "sms_opted_out_at = :sms_opted_out_at";
    $params['sms_opted_out_at'] = $smsOptStatus === 'opted_out' ? date('Y-m-d H:i:s') : null;
}
if (function_exists('leads_has_column') && leads_has_column('updated_at')) {
    $setParts[] = "updated_at = :updated_at";
    $params['updated_at'] = date('Y-m-d H:i:s');
}

if (empty($setParts)) {
    lead_update_json_response(500, [
        'ok' => false,
        'message' => 'No compatible fields available to update.',
    ]);
}

try {
    db_execute(
        "UPDATE leads SET " . implode(', ', $setParts) . " WHERE id = :id LIMIT 1",
        $params
    );

    if ($consultationStatus === 'scheduled' && $consultationDate !== null) {
        $bookingSets = ["status = 'consultation_booked'", 'next_follow_up_at = NULL', 'updated_at = NOW()'];
        $bookingParams = ['id' => $leadId];
        if (function_exists('leads_has_column') && leads_has_column('pipeline_position') && function_exists('lead_pipeline_next_position')) {
            $bookingSets[] = 'pipeline_position = :pipeline_position';
            $bookingParams['pipeline_position'] = lead_pipeline_next_position('consultation_booked');
        }
        db_execute('UPDATE leads SET ' . implode(', ', $bookingSets) . ' WHERE id = :id LIMIT 1', $bookingParams);
        if (function_exists('lead_send_consultation_booked_internal_sms')) {
            try {
                $bookingUser = auth_user();
                lead_send_consultation_booked_internal_sms($leadId, trim((string) ($leadRow['status'] ?? '')), [
                    'source' => 'lead_update_details',
                    'created_by' => (string) ($bookingUser['name'] ?? $bookingUser['email'] ?? 'CRM'),
                ]);
            } catch (Throwable $notificationError) {
                esm_log('consultation_booked', 'Appointment saved, but Dr. Meden notification failed.', [
                    'lead_id' => $leadId,
                    'error' => $notificationError->getMessage(),
                ]);
            }
        }
    }

    try {
        if (function_exists('smile_design_ensure_schema')) {
            smile_design_ensure_schema();
        }
        $syncedName = trim($fullName);
        $nameParts = preg_split('/\s+/', $syncedName) ?: [];
        $syncedFirstName = trim((string)($nameParts[0] ?? ''));
        $syncedLastName = count($nameParts) > 1 ? trim(implode(' ', array_slice($nameParts, 1))) : '';
        if ($syncedName !== '') {
            db_execute(
                "UPDATE smile_cases
                 SET first_name = :first_name,
                     last_name = :last_name,
                     patient_name = :patient_name,
                     email = :email,
                     phone = :phone,
                     procedure_interest = :procedure_interest
                 WHERE lead_id = :lead_id",
                [
                    'lead_id' => $leadId,
                    'first_name' => $syncedFirstName !== '' ? $syncedFirstName : null,
                    'last_name' => $syncedLastName !== '' ? $syncedLastName : null,
                    'patient_name' => $syncedName,
                    'email' => $email !== '' ? strtolower($email) : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'procedure_interest' => $procedureInterest !== '' ? $procedureInterest : null,
                ]
            );
        }
    } catch (Throwable $syncException) {
        if (function_exists('esm_log')) {
            esm_log('smile_design', 'Could not sync lead details to Smile Design cases.', [
                'lead_id' => $leadId,
                'message' => $syncException->getMessage(),
            ]);
        }
    }

    $previousSmsOptStatus = (string)($leadRow['sms_opt_status'] ?? 'unknown');
    if ($previousSmsOptStatus !== $smsOptStatus && function_exists('lead_comm_insert_activity')) {
        $statusLabels = [
            'unknown' => 'Unknown',
            'opted_in' => 'OK to Text',
            'opted_out' => 'DND / Do Not Text',
        ];
        lead_comm_insert_activity(
            $leadId,
            'dnd_status_change',
            'DND status changed from ' . ($statusLabels[$previousSmsOptStatus] ?? $previousSmsOptStatus) . ' to ' . ($statusLabels[$smsOptStatus] ?? $smsOptStatus) . '.',
            [
                'previous_sms_opt_status' => $previousSmsOptStatus,
                'sms_opt_status' => $smsOptStatus,
            ]
        );
    }
    $dentrixBridge = null;
    if ($consultationStatus === 'scheduled' && $consultationDate !== null && function_exists('dentrix_bridge_create_schedule_job')) {
        $currentUser = auth_user();
        $dentrixBridge = dentrix_bridge_create_schedule_job($leadId, [
            'created_by' => (string)($currentUser['name'] ?? $currentUser['email'] ?? 'CRM'),
            'consultation_date' => $consultationDate,
            'date_of_birth' => $dateOfBirth,
        ]);
    }

    lead_update_json_response(200, [
        'ok' => true,
        'message' => 'Lead details saved.',
        'lead_id' => $leadId,
        'consultation_status' => $consultationStatus,
        'consultation_date' => $consultationDate,
        'date_of_birth' => $dateOfBirth,
        'intent_type' => ($intentType !== '' ? mb_substr($intentType, 0, 120) : ''),

        'scheduling_preferred_day' => $schedulingPreferredDay,

        'scheduling_preferred_time' => $schedulingPreferredTime,

        'next_follow_up_at' => $nextFollowUpAt,

        'sms_opt_status' => $smsOptStatus,

        'dentrix_bridge' => $dentrixBridge,

        'updated_fields' => array_keys($params),
    ]);
} catch (Throwable $e) {
    lead_update_json_response(500, [
        'ok' => false,
        'message' => 'Failed to save lead details.',
        'debug' => [
            'exception' => $e->getMessage(),
            'lead_id' => $leadId,
            'set_parts' => $setParts,
            'params' => $params,
        ],
    ]);
}






