<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/db.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/patient_experience/patient_experience_forms.php';
require_once __DIR__ . '/app/patient_experience/patient_experience_service.php';

require_auth();

$sessionId = max(0, (int)get('session_id', '0'));
$signedPacket = $sessionId > 0 ? patient_experience_signed_packet_for_session($sessionId) : null;
if (!$signedPacket) {
    http_response_code(404);
    exit('Signed patient forms were not found.');
}

$snapshot = (array)($signedPacket['snapshot'] ?? []);
$definition = (array)($snapshot['definition'] ?? patient_experience_packet_definition());
$answers = (array)($snapshot['answers'] ?? []);
$signatures = (array)($snapshot['signatures'] ?? []);
$patientName = trim((string)($snapshot['session']['patient_name'] ?? $signedPacket['patient_name'] ?? 'Patient'));
$signedAt = (string)($signedPacket['signed_at'] ?? $snapshot['signed_at'] ?? '');
$snapshotHash = (string)($signedPacket['snapshot_hash'] ?? '');
$logoUrl = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');

$answerValue = static function (string $key) use ($answers): mixed {
    $answer = $answers[$key] ?? null;
    return is_array($answer) && array_key_exists('value', $answer) ? $answer['value'] : $answer;
};
$answerText = static function (mixed $value): string {
    if (is_array($value)) {
        return implode(', ', array_map(static fn(mixed $item): string => trim((string)$item), $value));
    }
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }
    $text = trim((string)$value);
    if ($text === '1') return 'Acknowledged';
    if ($text === '0') return 'No';
    return $text;
};
$signatureForSection = static function (string $sectionKey) use ($signatures): ?array {
    foreach ($signatures as $signature) {
        if ((string)($signature['section_key'] ?? '') === $sectionKey) {
            return (array)$signature;
        }
    }
    return null;
};
$isChoiceType = static fn(string $type): bool => in_array($type, ['radio', 'yes_no', 'checkbox_group', 'dropdown'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($patientName) ?> | Signed Patient Forms</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef1f4; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 10pt; line-height: 1.38; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: center; gap: 10px; padding: 12px; background: #111827; }
        .toolbar button, .toolbar a { border: 0; border-radius: 7px; padding: 10px 18px; background: #fff; color: #111827; font: 700 10pt Arial, sans-serif; text-decoration: none; cursor: pointer; }
        .packet { width: 8.5in; margin: 18px auto; background: #fff; box-shadow: 0 10px 30px rgba(15,23,42,.13); }
        .form-page { position: relative; min-height: 10in; padding: .45in .55in .55in; border-bottom: 1px solid #d1d5db; break-after: page; page-break-after: always; }
        .form-page:last-child { border-bottom: 0; break-after: auto; page-break-after: auto; }
        .letterhead { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; padding-bottom: 11px; border-bottom: 2px solid #111; }
        .brand { font-family: Georgia, serif; font-size: 22pt; font-weight: 700; letter-spacing: -.5px; }
        .brand small { display: block; margin-top: 1px; font: 7.5pt Arial, sans-serif; letter-spacing: 1.4px; text-transform: uppercase; }
        .brand-logo { display:block; width:172px; height:auto; }
        .document-meta { text-align: right; color: #374151; font-size: 8pt; }
        h1 { margin: 18px 0 3px; font-size: 18pt; }
        .description { margin: 0 0 16px; color: #4b5563; }
        .static-heading { margin: 17px 0 5px; padding-bottom: 4px; border-bottom: 1px solid #9ca3af; font-size: 12pt; font-weight: 700; }
        .legal-copy { margin: 0 0 10px; text-align: justify; }
        .divider { margin: 17px 0 9px; padding: 6px 8px; background: #e5e7eb; font-weight: 700; }
        .fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 9px 12px; }
        .field { min-height: 48px; padding: 7px 9px; border: 1px solid #9ca3af; border-radius: 4px; break-inside: avoid; page-break-inside: avoid; }
        .field.wide { grid-column: 1 / -1; }
        .label { margin-bottom: 4px; color: #374151; font-size: 7.5pt; font-weight: 700; letter-spacing: .3px; text-transform: uppercase; }
        .value { min-height: 15px; font-size: 10pt; font-weight: 600; white-space: pre-wrap; }
        .choices { display: flex; flex-wrap: wrap; gap: 5px 13px; font-size: 8.5pt; }
        .choice { white-space: nowrap; }
        .choice::before { content: '□'; margin-right: 4px; font-size: 11pt; }
        .choice.selected::before { content: '☒'; }
        .signature-block { grid-column: 1 / -1; min-height: 130px; padding: 10px 12px; border: 1px solid #6b7280; break-inside: avoid; page-break-inside: avoid; }
        .signature-image { display: block; width: auto; max-width: 320px; height: 70px; margin: 5px 0; object-fit: contain; object-position: left center; }
        .signature-line { display: flex; flex-wrap: wrap; gap: 18px; padding-top: 6px; border-top: 1px solid #9ca3af; font-size: 8pt; }
        .packet-footer { position: absolute; right: .55in; bottom: .24in; left: .55in; display: flex; justify-content: space-between; gap: 12px; color: #6b7280; font-size: 6.8pt; }
        /* Official patient form system: compact, legible, and faithful to the saved form. */
        body { background: #e5e7eb; font-size: 11px; line-height: 1.25; }
        .form-page { width: 8.5in; min-height: 11in; padding: .28in .34in .38in; overflow: hidden; }
        .letterhead { gap: 18px; padding-bottom: 7px; border-bottom: 2px solid #111; }
        .brand { font-size: 21px; letter-spacing: -.3px; }
        .brand small { font-size: 7px; letter-spacing: 1.2px; }
        .document-meta { color: #111; font-size: 8px; line-height: 1.35; }
        h1 { margin: 10px 0 2px; font-size: 16px; line-height: 1.15; }
        .description { margin: 0 0 8px; color: #374151; font-size: 9px; }
        .static-heading { margin: 7px 0 0; padding: 5px 7px; border: 0; border-left: 4px solid #111; background: #d1d5db; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .legal-copy { margin: 0; padding: 7px 8px; border: 1px solid #9ca3af; text-align: justify; font-size: 9px; line-height: 1.3; }
        .divider { margin: 7px 0 0; padding: 5px 7px; border-left: 4px solid #111; background: #e5e7eb; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .fields { gap: 0; border-top: 1px solid #9ca3af; border-left: 1px solid #9ca3af; }
        .field { min-height: 34px; margin: -1px 0 0 -1px; padding: 5px 7px; border: 1px solid #9ca3af; border-radius: 0; }
        .label { margin-bottom: 2px; color: #374151; font-size: 8px; font-weight: 800; letter-spacing: .25px; }
        .value { min-height: 13px; font-size: 11px; font-weight: 700; }
        .choices { gap: 4px 12px; font-size: 9px; }
        .choice::before { display: inline-flex; width: 10px; height: 10px; margin-right: 4px; align-items: center; justify-content: center; border: 1px solid #111; vertical-align: -1px; font-size: 9px; font-weight: 900; line-height: 1; }
        .choice.selected::before { content: 'X'; }
        .signature-block { min-height: 88px; margin: -1px 0 0 -1px; padding: 7px 8px; }
        .signature-image { max-width: 290px; height: 48px; margin: 3px 0; }
        .signature-line { gap: 16px; padding-top: 4px; font-size: 8px; }
        .packet-footer { right: .34in; bottom: .16in; left: .34in; color: #4b5563; font-size: 7px; }
        @page { size: Letter; margin: 0; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .packet { width: auto; margin: 0; box-shadow: none; }
            .form-page { width: 8.5in; height: 11in; min-height: 11in; padding: .28in .34in .38in; border: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Print Signed Forms</button>
        <a href="<?= e(base_url('patient-experience.php?tab=patients&session_id=' . $sessionId . '#consent-review')) ?>">Back to Patient Chart</a>
    </div>
    <main class="packet">
        <?php foreach ((array)($definition['sections'] ?? []) as $section): ?>
            <?php
            $sectionKey = (string)($section['section_key'] ?? '');
            if (in_array($sectionKey, ['welcome', 'final_review', 'final_signature'], true)) continue;
            if (function_exists('patient_experience_section_is_visible') && !patient_experience_section_is_visible($section, $answers)) continue;
            $fields = function_exists('patient_experience_visible_section_fields')
                ? patient_experience_visible_section_fields($section, $answers)
                : (array)($section['fields'] ?? []);
            $sectionSignature = $signatureForSection($sectionKey);
            ?>
            <section class="form-page">
                <header class="letterhead">
                    <div class="brand"><img class="brand-logo" src="<?= e($logoUrl) ?>" alt="Elite Smiles by Walter Meden DDS"></div>
                    <div class="document-meta">
                        Patient: <?= e($patientName) ?><br>
                        Signed: <?= e(format_datetime($signedAt)) ?><br>
                        Record #<?= e(str_pad((string)$sessionId, 4, '0', STR_PAD_LEFT)) ?>
                    </div>
                </header>
                <h1><?= e((string)($section['title'] ?? 'Patient Form')) ?></h1>
                <?php if (trim((string)($section['description'] ?? '')) !== ''): ?>
                    <p class="description"><?= e((string)$section['description']) ?></p>
                <?php endif; ?>
                <div class="fields">
                    <?php foreach ($fields as $field): ?>
                        <?php
                        $type = (string)($field['type'] ?? 'text');
                        $key = (string)($field['key'] ?? '');
                        $label = (string)($field['label'] ?? $key);
                        if ($type === 'heading') {
                            echo '<div class="static-heading wide">' . e($label) . '</div>';
                            continue;
                        }
                        if ($type === 'paragraph') {
                            echo '<p class="legal-copy wide">' . e($label) . '</p>';
                            continue;
                        }
                        if ($type === 'divider') {
                            echo '<div class="divider wide">' . e($label) . '</div>';
                            continue;
                        }
                        if ($type === 'digital_signature') {
                            $signatureImage = (string)($sectionSignature['image_data_url'] ?? '');
                            ?>
                            <div class="signature-block">
                                <div class="label"><?= e($label) ?></div>
                                <?php if ($signatureImage !== ''): ?>
                                    <img class="signature-image" src="<?= e($signatureImage) ?>" alt="Signed by <?= e((string)($sectionSignature['signer_name'] ?? $patientName)) ?>">
                                <?php else: ?>
                                    <div class="value">Digitally signed</div>
                                <?php endif; ?>
                                <div class="signature-line">
                                    <span>Signer: <strong><?= e((string)($sectionSignature['signer_name'] ?? $patientName)) ?></strong></span>
                                    <span>Relationship: <strong><?= e((string)($sectionSignature['signer_relationship'] ?? 'Self')) ?></strong></span>
                                    <span>Date: <strong><?= e(format_datetime((string)($sectionSignature['signed_at'] ?? $signedAt))) ?></strong></span>
                                </div>
                            </div>
                            <?php
                            continue;
                        }
                        $children = function_exists('patient_experience_field_children') ? patient_experience_field_children($field) : [];
                        if ($children !== []) {
                            foreach ($children as $child) {
                                $childValue = $answerValue((string)($child['key'] ?? ''));
                                echo '<div class="field"><div class="label">' . e((string)($child['label'] ?? $child['key'] ?? '')) . '</div><div class="value">' . e($answerText($childValue)) . '</div></div>';
                            }
                            continue;
                        }
                        $value = $answerValue($key);
                        $wide = in_array($type, ['textarea', 'checkbox_group', 'acknowledgement_checkbox'], true) ? ' wide' : '';
                        ?>
                        <div class="field<?= e($wide) ?>">
                            <div class="label"><?= e($label) ?></div>
                            <?php if ($isChoiceType($type) && !empty($field['options'])): ?>
                                <?php $selected = is_array($value) ? array_map('strval', $value) : [(string)$value]; ?>
                                <div class="choices">
                                    <?php foreach ((array)$field['options'] as $option): ?>
                                        <span class="choice <?= in_array((string)$option, $selected, true) ? 'selected' : '' ?>"><?= e((string)$option) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="value"><?= e($answerText($value)) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <footer class="packet-footer">
                    <span>Signed patient form · Packet v<?= e((string)($signedPacket['packet_version'] ?? 1)) ?></span>
                    <span>Verification <?= e(substr($snapshotHash, 0, 16)) ?></span>
                </footer>
            </section>
        <?php endforeach; ?>
    </main>
</body>
</html>
