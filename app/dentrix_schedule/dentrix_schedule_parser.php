<?php
declare(strict_types=1);

/**
 * Elite Smiles CRM
 * DentrixScheduleParser — turns a Dentrix Appointment Book Day View PDF
 * into structured appointment blocks.
 *
 * Implemented as a function set (dentrix_schedule_parser_*), matching this
 * codebase's convention of function-file "services" rather than classes.
 *
 * CALIBRATION NOTE: this parser was rewritten against a real, anonymized
 * Dentrix Appointment Book Day View export. The real layout is a genuine
 * time x operatory GRID: 10-minute row markers down the left edge, OP-1..
 * OP-5 as five columns, with each appointment's cell spanning 2-3 wrapped
 * text lines (name, procedure/reason, phone). It is NOT a sequence of
 * "OP-N header line, then appointment lines below it" — that was an
 * earlier, uncalibrated assumption and has been replaced.
 *
 * `pdftotext` on this project's target environment is xpdf's build (not
 * poppler's), which has no `-bbox` word-coordinate mode. Without true (x,y)
 * glyph coordinates, column assignment is done by bucketing each text run's
 * horizontal character offset (from `-layout` output) against the OP-1..
 * OP-5 header offsets, using the midpoint between adjacent headers as the
 * column boundary. This works well in practice but is inherently approximate
 * near a boundary — those blocks are flagged with a warning and a lower
 * confidence score rather than silently guessed. Likewise, a cell's start
 * time is read from the last time label seen at or above its first content
 * row (labels can visually "drift" down the page once another column's cell
 * wraps across more lines than the nominal 10-minute row height allows) —
 * so start_time is a best effort, not a guarantee, for blocks that begin on
 * an unlabeled/continuation row. If Dentrix's PDF export or the available
 * `pdftotext` build ever exposes real glyph coordinates, replacing the
 * offset-bucketing here with true coordinate clustering would remove this
 * whole class of approximation.
 *
 * dentrix_schedule_parser_parse() operates on already-extracted plain text
 * so it can be unit tested with fixture strings, without depending on the
 * pdftotext binary being present in every environment (e.g. CI runners).
 * dentrix_schedule_parser_parse_pdf() is the thin wrapper that shells out to
 * pdftotext and then calls the text parser.
 */

if (!function_exists('dentrix_schedule_parser_version')) {
    function dentrix_schedule_parser_version(): string
    {
        return 'dentrix-schedule-parser-2';
    }
}

if (!function_exists('dentrix_schedule_parser_known_operatories')) {
    function dentrix_schedule_parser_known_operatories(): array
    {
        return ['OP-1', 'OP-2', 'OP-3', 'OP-4', 'OP-5'];
    }
}

if (!function_exists('dentrix_schedule_parser_guard_keywords')) {
    function dentrix_schedule_parser_guard_keywords(): array
    {
        return [
            'sedation', 'sed', 'surgery', 'surgical', 'implant', 'implants',
            'extraction', 'extractions', 'ext', 'wisdom', 'full arch', 'all on',
            'iv', 'oral surgery', 'graft', 'bone graft',
        ];
    }
}

// A column-boundary bucketing decision this close to a midpoint is treated
// as ambiguous (flagged, lower confidence) rather than asserted with confidence.
if (!defined('DENTRIX_SCHEDULE_PARSER_COLUMN_BOUNDARY_MARGIN')) {
    define('DENTRIX_SCHEDULE_PARSER_COLUMN_BOUNDARY_MARGIN', 6);
}

// ---------------------------------------------------------------------
// PDF text extraction (pdftotext) — isolated so it can fail cleanly in
// environments without the binary, without touching the text parser.
// ---------------------------------------------------------------------

if (!function_exists('dentrix_schedule_parser_pdftotext_binary')) {
    function dentrix_schedule_parser_pdftotext_binary(): string
    {
        $configured = trim((string)(getenv('DENTRIX_SCHEDULE_PDFTOTEXT_BIN') ?: ''));
        return $configured !== '' ? $configured : 'pdftotext';
    }
}

if (!function_exists('dentrix_schedule_parser_extract_text')) {
    function dentrix_schedule_parser_extract_text(string $pdfPath): array
    {
        if (!is_file($pdfPath)) {
            return ['ok' => false, 'text' => '', 'message' => 'PDF file not found: ' . $pdfPath];
        }
        if (!function_exists('proc_open')) {
            return ['ok' => false, 'text' => '', 'message' => 'PHP proc_open is disabled; cannot run pdftotext.'];
        }

        $bin = dentrix_schedule_parser_pdftotext_binary();
        // -layout preserves horizontal spacing so operatory columns and
        // time-led rows stay recognizable in the extracted text.
        $cmd = [$bin, '-layout', '-enc', 'UTF-8', $pdfPath, '-'];

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['ok' => false, 'text' => '', 'message' => 'Could not start pdftotext. Is it installed and on PATH?'];
        }

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            return ['ok' => false, 'text' => '', 'message' => 'pdftotext failed (exit ' . $exitCode . '): ' . trim($stderr)];
        }

        return ['ok' => true, 'text' => $stdout, 'message' => ''];
    }
}

if (!function_exists('dentrix_schedule_parser_parse_pdf')) {
    function dentrix_schedule_parser_parse_pdf(string $pdfPath, string $sourceFileName, int $expectedDayIndex): array
    {
        $extraction = dentrix_schedule_parser_extract_text($pdfPath);
        if (empty($extraction['ok'])) {
            return [
                'ok' => false,
                'message' => $extraction['message'],
                'raw_text' => null,
                'parser_version' => dentrix_schedule_parser_version(),
            ];
        }

        $result = dentrix_schedule_parser_parse($extraction['text'], $sourceFileName, $expectedDayIndex);
        $result['ok'] = true;
        $result['parser_version'] = dentrix_schedule_parser_version();
        return $result;
    }
}

// ---------------------------------------------------------------------
// Text parsing (pure, testable)
// ---------------------------------------------------------------------

if (!function_exists('dentrix_schedule_parser_redact_ssn')) {
    /**
     * Never store SSN / Soc. Sec. values. Redacts a labeled SSN field
     * ("SSN: 123-45-6789", "Soc. Sec. #: 123456789") and bare SSN-shaped
     * tokens (123-45-6789) from any text before it is persisted.
     */
    function dentrix_schedule_parser_redact_ssn(string $text): string
    {
        $text = preg_replace('/\b(SSN|Soc\.?\s*Sec\.?|Social\s+Security)\s*#?\s*:?\s*[\d\-]{4,}\b/i', '$1: [REDACTED]', $text) ?? $text;
        $text = preg_replace('/\b\d{3}-\d{2}-\d{4}\b/', '[REDACTED]', $text) ?? $text;
        return $text;
    }
}

if (!function_exists('dentrix_schedule_parser_find_schedule_date')) {
    function dentrix_schedule_parser_find_schedule_date(string $text): ?string
    {
        // "Monday, September 14, 2026" / "Monday - September 14, 2026" / "September 14, 2026"
        if (preg_match('/\b(?:Sun|Mon|Tue|Wed|Thu|Fri|Sat)[a-z]*\s*[,\-]?\s+([A-Z][a-z]+)\s+(\d{1,2}),?\s+(\d{4})\b/', $text, $m) === 1
            || preg_match('/\b([A-Z][a-z]+)\s+(\d{1,2}),?\s+(\d{4})\b/', $text, $m) === 1
        ) {
            $timestamp = strtotime($m[1] . ' ' . $m[2] . ', ' . $m[3]);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        // MM/DD/YYYY
        if (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/', $text, $m) === 1) {
            $timestamp = mktime(0, 0, 0, (int)$m[1], (int)$m[2], (int)$m[3]);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        return null;
    }
}

if (!function_exists('dentrix_schedule_parser_match_time_label')) {
    /**
     * Matches a time-grid row label anchored at the START of a raw (untrimmed)
     * line, within Dentrix's narrow left-hand time column: either a full label
     * ("8:00am", " 8:00am") or a bare continuation ("     :10", "     :50").
     * Returns null if the line doesn't open with one (i.e. it's a wrapped
     * continuation row with no fresh label — the caller carries forward
     * whatever time was last seen).
     */
    function dentrix_schedule_parser_match_time_label(string $line): ?array
    {
        if (preg_match('/^\s{0,4}(\d{1,2}):(\d{2})\s*([AaPp][Mm])\b/', $line, $m, PREG_OFFSET_CAPTURE) === 1) {
            return [
                'hour' => (int)$m[1][0],
                'minute' => (int)$m[2][0],
                'meridiem' => strtoupper((string)$m[3][0]),
                'is_full' => true,
                'span' => [$m[0][1], $m[0][1] + strlen((string)$m[0][0])],
            ];
        }
        if (preg_match('/^\s{0,9}:(\d{2})\b/', $line, $m, PREG_OFFSET_CAPTURE) === 1) {
            return [
                'hour' => null,
                'minute' => (int)$m[1][0],
                'meridiem' => null,
                'is_full' => false,
                'span' => [$m[0][1], $m[0][1] + strlen((string)$m[0][0])],
            ];
        }
        return null;
    }
}

if (!function_exists('dentrix_schedule_parser_format_time')) {
    function dentrix_schedule_parser_format_time(int $hour, int $minute, string $meridiem): ?string
    {
        $meridiem = strtoupper($meridiem);
        if ($meridiem === 'PM' && $hour < 12) {
            $hour += 12;
        } elseif ($meridiem === 'AM' && $hour === 12) {
            $hour = 0;
        }
        if ($hour > 23 || $minute > 59) {
            return null;
        }
        return sprintf('%02d:%02d:00', $hour, $minute);
    }
}

if (!function_exists('dentrix_schedule_parser_find_column_offsets')) {
    /**
     * Scans the page header (first ~12 lines) for OP-1..OP-5 tokens and
     * records each one's character offset from the `-layout` text, however
     * the header text happens to be split across physical lines.
     */
    function dentrix_schedule_parser_find_column_offsets(array $lines): array
    {
        $offsets = [];
        foreach (array_slice($lines, 0, 12) as $line) {
            if (preg_match_all('/OP-([1-5])\b/', $line, $m, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($m[0] as $i => $match) {
                    $label = 'OP-' . $m[1][$i][0];
                    if (!isset($offsets[$label])) {
                        $offsets[$label] = (int)$match[1];
                    }
                }
            }
        }
        asort($offsets);
        return $offsets;
    }
}

if (!function_exists('dentrix_schedule_parser_column_boundaries')) {
    function dentrix_schedule_parser_column_boundaries(array $sortedOffsets): array
    {
        $offsets = array_values($sortedOffsets);
        $boundaries = [];
        for ($i = 0; $i < count($offsets) - 1; $i++) {
            $boundaries[] = ($offsets[$i] + $offsets[$i + 1]) / 2;
        }
        return $boundaries;
    }
}

if (!function_exists('dentrix_schedule_parser_column_for_offset')) {
    /**
     * Buckets a text run's start offset into one of the ordered column
     * labels using midpoint boundaries. Returns [label, isNearBoundary].
     */
    function dentrix_schedule_parser_column_for_offset(int $offset, array $labelsByOffset, array $boundaries): array
    {
        $labels = array_keys($labelsByOffset);
        $idx = 0;
        foreach ($boundaries as $bi => $boundary) {
            if ($offset >= $boundary) {
                $idx = $bi + 1;
            } else {
                break;
            }
        }
        $nearBoundary = false;
        foreach ($boundaries as $boundary) {
            if (abs($offset - $boundary) <= DENTRIX_SCHEDULE_PARSER_COLUMN_BOUNDARY_MARGIN) {
                $nearBoundary = true;
                break;
            }
        }
        return [$labels[$idx] ?? null, $nearBoundary];
    }
}

if (!function_exists('dentrix_schedule_parser_find_runs')) {
    /**
     * Finds non-whitespace runs (words/phrases separated by 2+ spaces) in a
     * line along with their character offsets, so each run can be bucketed
     * into a column by position.
     */
    function dentrix_schedule_parser_find_runs(string $line): array
    {
        if (preg_match_all('/\S.*?(?=\s{2,}|$)/', $line, $m, PREG_OFFSET_CAPTURE) === false || empty($m[0])) {
            return [];
        }
        $runs = [];
        foreach ($m[0] as $match) {
            $text = trim((string)$match[0]);
            if ($text !== '') {
                $runs[] = ['text' => $text, 'offset' => (int)$match[1]];
            }
        }
        return $runs;
    }
}

if (!function_exists('dentrix_schedule_parser_last_grid_line_index')) {
    /**
     * Dentrix prints a fixed 6-row block per hour (":00" header + :10..:50).
     * The page footer (a provider legend, e.g. "DDS1 DDS2 DDS3") appears
     * after the last hour's ":50" row and must not be scanned as content.
     * Finds the last full-hour label's line index and returns that index + 5
     * (its ":50" row) as the last line still inside the grid.
     */
    function dentrix_schedule_parser_last_grid_line_index(array $lines): int
    {
        $lastHourLine = -1;
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s{0,4}\d{1,2}:00\s*[AaPp][Mm]\b/', $line) === 1) {
                $lastHourLine = $i;
            }
        }
        return $lastHourLine >= 0 ? min($lastHourLine + 5, count($lines) - 1) : count($lines) - 1;
    }
}

if (!function_exists('dentrix_schedule_parser_extract_phone')) {
    function dentrix_schedule_parser_extract_phone(string $text): ?string
    {
        if (preg_match('/\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]\d{4}\b/', $text, $m) === 1) {
            return $m[0];
        }
        return null;
    }
}

if (!function_exists('dentrix_schedule_parser_extract_provider')) {
    function dentrix_schedule_parser_extract_provider(string $text): ?string
    {
        if (preg_match('/\bDr\.?\s+[A-Z][a-zA-Z\-\']+/', $text, $m) === 1) {
            return trim($m[0]);
        }
        return null;
    }
}

if (!function_exists('dentrix_schedule_parser_guess_reason')) {
    function dentrix_schedule_parser_guess_reason(string $text, array $guardKeywords): ?string
    {
        foreach ($guardKeywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                if (preg_match('/([A-Za-z][A-Za-z \-\/]{0,40}' . preg_quote($keyword, '/') . '[A-Za-z \-\/]{0,20})/i', $text, $m) === 1) {
                    return trim($m[1]);
                }
                return ucfirst($keyword);
            }
        }
        return null;
    }
}

if (!function_exists('dentrix_schedule_parser_extract_name')) {
    /**
     * Extracts a "LastName, FirstName" (optionally with a trailing middle
     * initial) patient name from a block's first content line, stripping a
     * leading "NP-" (New Patient) marker Dentrix prints attached to the name.
     * Returns [name, isNewPatient].
     */
    function dentrix_schedule_parser_extract_name(string $line): array
    {
        $isNewPatient = false;
        if (preg_match('/^NP[\s\-]+(.*)$/', $line, $m) === 1) {
            $isNewPatient = true;
            $line = $m[1];
        }
        if (preg_match('/^([A-Z][a-zA-Z\'\-]+,?(?:\s+[A-Z][a-zA-Z\'\-]+,?){0,3})/', trim($line), $m) === 1) {
            return [trim($m[1], " \t,"), $isNewPatient];
        }
        return [null, $isNewPatient];
    }
}

if (!function_exists('dentrix_schedule_parser_parse')) {
    /**
     * Parses already-extracted `pdftotext -layout` text into the schema:
     *   { schedule_date, source_day_index, operatories, appointments, warnings }
     *
     * Walks the time grid row by row. Each row may open with a time label
     * (full "8:00am" or bare ":10" continuation) which advances a running
     * clock; unlabeled rows carry the clock forward unchanged. The remainder
     * of each row is split into text runs and each run is bucketed into an
     * OP-1..OP-5 column by its horizontal offset relative to the header
     * offsets. A column's content is accumulated across consecutive
     * non-empty rows into one appointment block, closed as soon as that
     * column is empty on a row. See the file header comment for the
     * column-bucketing and time-drift caveats. Nothing is ever silently
     * dropped: an unclear block still becomes a low-confidence record.
     */
    function dentrix_schedule_parser_parse(string $rawText, string $sourceFileName, int $expectedDayIndex): array
    {
        $rawText = dentrix_schedule_parser_redact_ssn($rawText);
        $warnings = [];
        $guardKeywords = dentrix_schedule_parser_guard_keywords();

        $scheduleDate = dentrix_schedule_parser_find_schedule_date($rawText);
        if (!$scheduleDate) {
            $warnings[] = 'No schedule date could be confidently detected in "' . $sourceFileName . '".';
        }

        $lines = preg_split('/\r\n|\r|\n/', $rawText) ?: [];
        $columnOffsets = dentrix_schedule_parser_find_column_offsets($lines);
        $noColumns = empty($columnOffsets);
        if ($noColumns) {
            $warnings[] = 'No OP-1..OP-5 column headers were detected in "' . $sourceFileName . '"; appointment operatory assignment is unavailable.';
        }
        $boundaries = $noColumns ? [] : dentrix_schedule_parser_column_boundaries($columnOffsets);
        $columnKeys = $noColumns ? ['__all__'] : array_keys($columnOffsets);

        $lastGridLine = dentrix_schedule_parser_last_grid_line_index($lines);

        // Persistent clock state: updated whenever a row carries a label (full
        // or bare-minute continuation), otherwise carried forward unchanged —
        // this is what lets an unlabeled wrap-continuation row inherit the
        // time of whatever was last printed above it.
        $currentHour = null;
        $currentMinute = null;
        $currentMeridiem = null;
        /** @var array<string, array{start_time: ?string, lines: string[], near_boundary: bool}|null> $open */
        $open = array_fill_keys($columnKeys, null);
        $blocks = [];

        $closeColumn = static function (string $key) use (&$open, &$blocks): void {
            if ($open[$key] !== null) {
                $blocks[] = $open[$key] + ['operatory' => $key === '__all__' ? null : $key];
                $open[$key] = null;
            }
        };

        foreach ($lines as $i => $line) {
            if ($i > $lastGridLine) {
                break;
            }

            $label = dentrix_schedule_parser_match_time_label($line);
            $workingLine = $line;

            if ($label !== null) {
                if ($label['is_full']) {
                    $currentHour = $label['hour'];
                    $currentMeridiem = $label['meridiem'];
                }
                $currentMinute = $label['minute'];
                // Blank out the label span so it can't be re-matched as a content run.
                [$start, $end] = $label['span'];
                $workingLine = substr($workingLine, 0, $start) . str_repeat(' ', $end - $start) . substr($workingLine, $end);
            }

            $currentTime = ($currentHour !== null && $currentMinute !== null && $currentMeridiem !== null)
                ? dentrix_schedule_parser_format_time($currentHour, $currentMinute, $currentMeridiem)
                : null;

            if (trim($workingLine) === '') {
                foreach ($columnKeys as $key) {
                    $closeColumn($key);
                }
                continue;
            }

            $runs = dentrix_schedule_parser_find_runs($workingLine);

            if ($noColumns) {
                $text = trim(implode(' ', array_column($runs, 'text')));
                if ($text === '') {
                    $closeColumn('__all__');
                    continue;
                }
                if ($open['__all__'] === null) {
                    $open['__all__'] = ['start_time' => $currentTime, 'lines' => [], 'near_boundary' => false];
                }
                $open['__all__']['lines'][] = $text;
                continue;
            }

            $runsByColumn = array_fill_keys($columnKeys, []);
            foreach ($runs as $run) {
                [$col, $nearBoundary] = dentrix_schedule_parser_column_for_offset($run['offset'], $columnOffsets, $boundaries);
                if ($col === null) {
                    continue;
                }
                $runsByColumn[$col][] = $run['text'];
                if ($nearBoundary && isset($open[$col]) && $open[$col] !== null) {
                    $open[$col]['near_boundary'] = true;
                }
            }

            foreach ($columnKeys as $col) {
                if (empty($runsByColumn[$col])) {
                    $closeColumn($col);
                    continue;
                }
                if ($open[$col] === null) {
                    $open[$col] = ['start_time' => $currentTime, 'lines' => [], 'near_boundary' => false];
                }
                $open[$col]['lines'][] = implode(' ', $runsByColumn[$col]);
            }
        }
        foreach ($columnKeys as $key) {
            $closeColumn($key);
        }

        $appointments = [];
        $preGridBlockCount = 0;
        foreach ($blocks as $block) {
            if ($block['start_time'] === null) {
                // A block with no time-grid row ever established is page
                // furniture above the grid (title, letterhead, "Date:"/
                // "Page:" labels) that happened to fall inside a column's
                // x-range, not a real appointment — every genuine appointment
                // in this layout occurs after at least one row label has been
                // seen. Excluded (and counted) rather than stored as junk data.
                $preGridBlockCount++;
                continue;
            }

            $blockLines = array_values(array_filter($block['lines'], static fn($l) => trim((string)$l) !== ''));
            $blockText = dentrix_schedule_parser_redact_ssn(implode(' | ', $blockLines));

            [$patientName, $isNewPatient] = !empty($blockLines) ? dentrix_schedule_parser_extract_name((string)$blockLines[0]) : [null, false];
            $phone = dentrix_schedule_parser_extract_phone($blockText);
            $provider = dentrix_schedule_parser_extract_provider($blockText);
            $reason = dentrix_schedule_parser_guess_reason($blockText, $guardKeywords);
            if ($reason === null && count($blockLines) > 1) {
                // Fall back to the line(s) between the name and the phone as
                // the reason, skipping a bare "H:" (phone field on file but
                // no number printed) so it doesn't read as reason text.
                $middle = array_slice($blockLines, 1);
                $middle = array_values(array_filter($middle, static fn($l) => dentrix_schedule_parser_extract_phone((string)$l) === null && preg_match('/^H:?\s*$/i', trim((string)$l)) !== 1));
                $reason = !empty($middle) ? trim(implode(', ', $middle)) : null;
            }

            $confidenceScore = 0.0;
            $confidenceScore += $block['start_time'] !== null ? 0.30 : 0.0;
            $confidenceScore += $block['operatory'] !== null ? 0.25 : 0.0;
            $confidenceScore += $patientName !== null ? 0.30 : 0.0;
            $confidenceScore += $scheduleDate !== null ? 0.15 : 0.0;
            if (!empty($block['near_boundary'])) {
                $confidenceScore -= 0.20;
                $warnings[] = 'Ambiguous operatory-column assignment near a boundary in "' . $sourceFileName . '": "' . mb_substr($blockText, 0, 80) . '"';
            }
            $confidenceScore = max(0.0, $confidenceScore);

            if ($confidenceScore < 0.6) {
                $warnings[] = 'Low-confidence appointment block (score ' . number_format($confidenceScore, 2) . ') in "' . $sourceFileName . '": "' . mb_substr($blockText, 0, 80) . '"';
            }

            $appointments[] = [
                'operatory' => $block['operatory'],
                'start_time' => $block['start_time'],
                'end_time' => null, // Not printed per-appointment on this layout; left honest rather than guessed.
                'patient_name' => $patientName,
                'appointment_reason' => $reason,
                'phone' => $phone,
                'provider' => $provider,
                'appointment_type' => $isNewPatient ? 'New Patient' : null,
                'staff' => null,
                'raw_block_text' => $blockText,
                'confidence' => round($confidenceScore, 3),
            ];
        }

        if (empty($appointments)) {
            $warnings[] = 'No appointment blocks were detected in "' . $sourceFileName . '". The day may be genuinely empty, or the layout did not match the parser\'s heuristics.';
        }
        if ($preGridBlockCount > 0) {
            $warnings[] = $preGridBlockCount . ' page-header text run(s) above the time grid were excluded (not real appointments) in "' . $sourceFileName . '".';
        }

        return [
            'schedule_date' => $scheduleDate,
            'source_day_index' => $expectedDayIndex,
            'operatories' => $noColumns ? dentrix_schedule_parser_known_operatories() : array_keys($columnOffsets),
            'appointments' => $appointments,
            'warnings' => $warnings,
            'raw_text' => $rawText,
        ];
    }
}
