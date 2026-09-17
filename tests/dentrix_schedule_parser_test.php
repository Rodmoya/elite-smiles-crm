<?php
declare(strict_types=1);

/**
 * Pure text-parsing tests for the DentrixScheduleParser (dentrix_schedule_parser_*).
 * Deliberately exercises dentrix_schedule_parser_parse() with fixture text
 * rather than a real PDF, so this test has no dependency on the pdftotext
 * binary being installed (it may not be, e.g. on CI runners).
 *
 * The main fixture below is not invented: it is the exact `pdftotext -layout`
 * output of a REAL Dentrix Appointment Book Day View export the office
 * provided, with every patient name and phone number mechanically replaced
 * by a same-length synthetic placeholder (verified, programmatically, to
 * contain zero real names or phone numbers before being committed here).
 * Column positions, row spacing, and the overall grid layout are otherwise
 * byte-for-byte what Dentrix actually prints, so this test exercises the
 * parser's real column/time-grid logic, not a guessed format.
 */

require_once dirname(__DIR__) . '/app/dentrix_schedule/dentrix_schedule_parser.php';

function parser_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$fixture = <<<'TEXT'
                                APPOINTMENT BOOK VIEW (09/16/2026)

                                                               Walter Meden D.D.S., P.C.

  Date:   09/16/2026                   OP-2             Wednesday - September 16, 2026       OP-4            Page: 1
                          OP-1                                                  OP-3                          OP-5
 8:00am
     :10        Testxx, Alexx                                              Samplexx, Jamiex        NP-Example, Taylo
     :20        Placeh, Mor                                                Observ                  Fixtur, Casey, PA1st, 4
     :30        H:
     :40                               NP-Anon, Rile                                               Reda, Jord
     :50        NP-Maskedxx, Averyx    Veneers Full Mouth Consult                                  seating
                Consult                H:(801)555-0101                                             H:(801)555-0102
 9:00am         H:(801)555-0103
     :10
     :20                               Synthx, Drewxx
     :30                               Seat Crwn#32
     :40                               H:(801)555-0104
     :50
                Genericxxx, Reesexx                     Dummyx, Quinnxxx
10:00am         ContEnd#15                              Delvr
     :10        H:                                      H:(801)555-0105
     :20        DDS1
     :30        General
     :40
     :50        Blankx, Harperxxx A
                Emax# 6, Emax#11
11:00am         H:(801)555-0106
     :10        DDS1
     :20        General
     :30
     :40        Testxxx, Alexxx                         Samplexxx, Jamie
     :50        Exam, Taylor                            Observ
                H:(801)555-0107
12:00pm
     :10
     :20
     :30
     :40
     :50

 1:00pm
     :10
     :20
     :30
     :40
     :50

 2:00pm
     :10
     :20
     :30
     :40
     :50

 3:00pm
     :10
     :20
     :30
     :40
     :50

 4:00pm
     :10
     :20
     :30
     :40
     :50

          DDS1  DDS2             DDS3
TEXT;

$result = dentrix_schedule_parser_parse($fixture, '1.pdf', 1);

// Schedule date, detected from "Date:   09/16/2026" (MM/DD/YYYY).
parser_expect($result['schedule_date'] === '2026-09-16', 'Must detect the schedule date from the real header. Got: ' . var_export($result['schedule_date'], true));
parser_expect((int)$result['source_day_index'] === 1, 'Must pass the expected day index through untouched.');
parser_expect(str_contains($result['raw_text'], 'APPOINTMENT BOOK VIEW'), 'Parser output must preserve the raw extracted text.');

foreach (['OP-1', 'OP-2', 'OP-3', 'OP-4', 'OP-5'] as $op) {
    parser_expect(in_array($op, $result['operatories'], true), "Must detect {$op} column header.");
}

// The 7 page-header/letterhead text runs above the time grid (title, doctor
// name, "Date:"/"Page:" labels, the split OP-header line itself) must be
// excluded as page furniture, not stored as junk appointments.
parser_expect(count($result['appointments']) === 12, 'Must extract exactly the 12 real appointment cells, excluding page-header noise. Got: ' . count($result['appointments']));

$byPatient = [];
foreach ($result['appointments'] as $appt) {
    if ($appt['patient_name']) {
        $byPatient[$appt['patient_name']] = $appt;
    }
}

// A cell with no "NP-" prefix: name, reason, no phone printed at all.
parser_expect(isset($byPatient['Testxx, Alexx']), 'Must extract the first OP-1 cell.');
$a = $byPatient['Testxx, Alexx'];
parser_expect($a['operatory'] === 'OP-1', 'First cell must be assigned to OP-1 by column offset.');
parser_expect($a['start_time'] === '08:10:00', 'Start time must come from the row label, not the earlier 8:00am hour header. Got: ' . $a['start_time']);
parser_expect($a['phone'] === null, 'A cell with no phone digits printed must not fabricate a phone number.');
parser_expect($a['confidence'] >= 0.9, 'A complete, unambiguous cell must score high confidence.');

// A cell with the "NP-" (New Patient) marker attached to the name.
parser_expect(isset($byPatient['Example, Taylo']), 'The "NP-" marker must be stripped from the stored patient name.');
$a = $byPatient['Example, Taylo'];
parser_expect($a['appointment_type'] === 'New Patient', 'An "NP-" prefixed name must set appointment_type to New Patient.');
parser_expect($a['confidence'] === 0.8, 'The boundary-ambiguous OP-4/OP-5 cell must be penalized to 0.80 confidence, reflecting the ambiguous column assignment. Got: ' . $a['confidence']);

// A cell with a real phone number and a multi-word reason.
parser_expect(isset($byPatient['Anon, Rile']), 'Must extract the Veneers Full Mouth Consult cell.');
$a = $byPatient['Anon, Rile'];
parser_expect($a['operatory'] === 'OP-2', 'Must assign to OP-2.');
parser_expect($a['start_time'] === '08:40:00', 'Start time must be 08:40:00.');
parser_expect($a['phone'] === '(801)555-0101', 'Must extract the attached "H:(...)..." phone format with no space after the label.');
parser_expect($a['appointment_reason'] === 'Veneers Full Mouth Consult', 'Must extract the full multi-word reason line.');
parser_expect($a['appointment_type'] === 'New Patient', 'This cell is also NP-prefixed.');

// A cell whose only "extra" line is a blank "H:" (phone field present, no
// number on file) — that must not leak into the reason text.
parser_expect(isset($byPatient['Genericxxx, Reesexx']), 'Must extract the multi-line OP-1 cell.');
$a = $byPatient['Genericxxx, Reesexx'];
parser_expect($a['phone'] === null, 'A bare "H:" with no digits must not be read as a phone number.');
parser_expect(!str_contains((string)$a['appointment_reason'], 'H:'), 'A bare "H:" marker must never leak into the reason text.');
parser_expect(str_contains((string)$a['appointment_reason'], 'ContEnd#15'), 'The real procedure code must still be present in the reason.');

// Every appointment must carry a start_time — a block with none is page
// furniture and was already excluded above, not left in with a null time.
foreach ($result['appointments'] as $appt) {
    parser_expect($appt['start_time'] !== null, 'Every retained appointment must have a start_time; page-header blocks must be filtered out, not merely low-confidence.');
}

// Warnings: 4 boundary-ambiguous cells + 1 summary line for the excluded
// page-header runs.
$boundaryWarnings = array_filter($result['warnings'], static fn($w) => str_contains($w, 'Ambiguous operatory-column'));
$headerWarnings = array_filter($result['warnings'], static fn($w) => str_contains($w, 'page-header text run'));
parser_expect(count($boundaryWarnings) === 4, 'Must warn once per boundary-ambiguous cell. Got: ' . count($boundaryWarnings));
parser_expect(count($headerWarnings) === 1, 'Must summarize excluded page-header runs in exactly one warning. Got: ' . count($headerWarnings));

// --- Supplementary fixture: SSN redaction + guard keywords + single-column fallback ---
$guardFixture = <<<'TEXT'
                          OP-1

 8:00am
     :10        Roberts, Casey
                Wisdom Teeth Extraction
                SSN: 123-45-6789
TEXT;

$guardResult = dentrix_schedule_parser_parse($guardFixture, '1.pdf', 1);
parser_expect(count($guardResult['appointments']) === 1, 'Single-column fixture must still produce exactly one appointment.');
$guardAppt = $guardResult['appointments'][0];
parser_expect($guardAppt['operatory'] === 'OP-1', 'A single detected column must still be assigned correctly.');
parser_expect($guardAppt['start_time'] === '08:10:00', 'Start time must resolve for the single-column fixture.');
parser_expect($guardAppt['appointment_reason'] !== null, 'Must surface a reason for a guard-keyword block.');
$reason = strtolower((string)$guardAppt['appointment_reason']);
parser_expect(str_contains($reason, 'wisdom') || str_contains($reason, 'extraction'), 'Reason must surface the guard keyword present in the block, got: ' . $guardAppt['appointment_reason']);
parser_expect(!str_contains($guardAppt['raw_block_text'], '123-45-6789'), 'SSN digits must never appear in stored block text.');
parser_expect(!str_contains($guardResult['raw_text'], '123-45-6789'), 'SSN digits must be redacted from the stored raw text.');

// --- Fallback: a page with no OP-N headers at all must still work (single flat block stream) ---
$noColumnsFixture = <<<'TEXT'
Elite Smiles Dental

 8:00am
     :10        Fallback, Patient
                General checkup
TEXT;

$fallbackResult = dentrix_schedule_parser_parse($noColumnsFixture, '1.pdf', 1);
parser_expect(count($fallbackResult['appointments']) === 1, 'A page with no column headers must still produce a record, not silently drop it.');
parser_expect($fallbackResult['appointments'][0]['operatory'] === null, 'With no columns detected, operatory must be null rather than guessed.');
parser_expect(!empty(array_filter($fallbackResult['warnings'], static fn($w) => str_contains($w, 'No OP-1..OP-5 column headers'))), 'Must warn when no column headers are found at all.');

// Weekday <-> filename mapping (pure function, no DB).
require_once dirname(__DIR__) . '/app/dentrix_schedule/dentrix_schedule_service.php';
parser_expect(dentrix_schedule_day_index_from_filename('1.pdf') === 1, '1.pdf must map to Monday (1).');
parser_expect(dentrix_schedule_day_index_from_filename('2.pdf') === 2, '2.pdf must map to Tuesday (2).');
parser_expect(dentrix_schedule_day_index_from_filename('3.pdf') === 3, '3.pdf must map to Wednesday (3).');
parser_expect(dentrix_schedule_day_index_from_filename('4.pdf') === 4, '4.pdf must map to Thursday (4).');
parser_expect(dentrix_schedule_day_index_from_filename('5.pdf') === 5, '5.pdf must map to Friday (5).');
parser_expect(dentrix_schedule_day_index_from_filename('1.PDF') === 1, 'Extension match must be case-insensitive.');
parser_expect(dentrix_schedule_day_index_from_filename('6.pdf') === null, 'Out-of-range weekday files must not map to a day index.');
parser_expect(dentrix_schedule_day_index_from_filename('monday.pdf') === null, 'Non-numeric file names must not guess a weekday.');
parser_expect(dentrix_schedule_day_index_from_filename('1 (1).pdf') === null, 'Browser-duplicate-style file names must not silently map to a weekday.');
parser_expect(dentrix_schedule_day_name(1) === 'Monday', 'Day index 1 must label as Monday.');
parser_expect(dentrix_schedule_day_name(5) === 'Friday', 'Day index 5 must label as Friday.');

echo "Dentrix schedule parser tests passed.\n";
