<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

dentrix_schedule_internal_boot('Dentrix Schedule Bridge');

if (!is_post()) {
    redirect(base_url('dentrix-schedule'));
}

require_csrf();

if (!function_exists('dentrix_schedule_upload_error_message')) {
    function dentrix_schedule_upload_error_message(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That PDF is larger than this server currently allows.',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Choose a PDF file to upload.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not stage the upload. Please try again.',
            default => 'Upload failed.',
        };
    }
}

$file = $_FILES['schedule_pdf'] ?? [];
$errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

if ($errorCode !== UPLOAD_ERR_OK) {
    flash_set('error', dentrix_schedule_upload_error_message($errorCode));
    redirect(base_url('dentrix-schedule'));
}

$tmpName = (string)($file['tmp_name'] ?? '');
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    flash_set('error', 'Upload never reached secure staging storage.');
    redirect(base_url('dentrix-schedule'));
}

$originalName = trim((string)($file['name'] ?? ''));
$isPdfExtension = preg_match('/\.pdf$/i', $originalName) === 1;
$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
$detectedMime = $finfo ? (finfo_file($finfo, $tmpName) ?: '') : '';
if ($finfo) {
    finfo_close($finfo);
}
$isPdfMime = $detectedMime === '' || $detectedMime === 'application/pdf';

if (!$isPdfExtension || !$isPdfMime) {
    flash_set('error', 'Only .pdf files are supported for a Dentrix Appointment Book export.');
    redirect(base_url('dentrix-schedule'));
}

$size = (int)($file['size'] ?? 0);
if ($size <= 0) {
    flash_set('error', 'The uploaded PDF is empty.');
    redirect(base_url('dentrix-schedule'));
}

$dayIndexInput = trim((string)post('day_index', 'auto'));
$dayIndex = null;
if ($dayIndexInput !== 'auto' && $dayIndexInput !== '') {
    $candidate = (int)$dayIndexInput;
    if ($candidate >= 1 && $candidate <= 5) {
        $dayIndex = $candidate;
    }
}
if ($dayIndex === null && $dayIndexInput === 'auto') {
    $dayIndex = dentrix_schedule_day_index_from_filename($originalName);
    if ($dayIndex === null) {
        flash_set('error', 'Could not auto-detect the weekday from "' . $originalName . '". Please pick the weekday explicitly and re-upload.');
        redirect(base_url('dentrix-schedule'));
    }
}
if ($dayIndex === null) {
    flash_set('error', 'Please choose a valid weekday for this import.');
    redirect(base_url('dentrix-schedule'));
}

$force = post('force', '') === '1';

$result = dentrix_schedule_import_pdf($tmpName, $originalName !== '' ? $originalName : ($dayIndex . '.pdf'), [
    'source_type' => 'manual',
    'day_index' => $dayIndex,
    'force' => $force,
]);

if (empty($result['ok'])) {
    flash_set('error', (string)($result['message'] ?? 'Import failed.'));
    redirect(base_url('dentrix-schedule'));
}

$status = (string)($result['status'] ?? 'parsed');
if ($status === 'skipped') {
    flash_set('info', 'PDF matched the last import for ' . dentrix_schedule_day_name($dayIndex) . ' (unchanged) — nothing to re-parse. Use "Force re-parse" to override.');
} else {
    $warningCount = count($result['warnings'] ?? []);
    $message = 'Imported ' . dentrix_schedule_day_name($dayIndex) . '\'s schedule for ' . (string)($result['schedule_date'] ?? '')
        . ': ' . (int)($result['appointment_count'] ?? 0) . ' appointment(s).';
    if ($warningCount > 0) {
        $message .= ' ' . $warningCount . ' warning(s) — see import history for details.';
    }
    flash_set('success', $message);
}

redirect(base_url('dentrix-schedule'));
