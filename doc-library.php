<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/helpers.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/doc_library/doc_library_service.php';

require_auth();

$currentPage = 'doc_library';
$pageTitle = 'Doc Library';
$logoutAction = base_url('doc-library.php');

$templates = doc_library_template_options();
$defaultTemplateKey = (string)($templates[0]['key'] ?? 'welcome_patient_information');
$requestedTemplate = trim((string)get('template', ''));
$selectedTemplateKey = $requestedTemplate !== '' ? $requestedTemplate : $defaultTemplateKey;
$selectedTemplate = doc_library_template_by_key($selectedTemplateKey);
if ($selectedTemplate === null) {
    $selectedTemplate = doc_library_template_by_key($defaultTemplateKey);
}

if (!is_array($selectedTemplate)) {
    $selectedTemplate = [];
}

$selectedTemplateKey = (string)($selectedTemplate['key'] ?? $defaultTemplateKey);
$documentTitle = (string)($selectedTemplate['title'] ?? 'Document');
$documentSubtitle = (string)($selectedTemplate['subtitle'] ?? '');
$sections = (array)($selectedTemplate['sections'] ?? []);
$baseLogo = base_url('assets/img/ES-Logo-Stack-500-x-150-px.png');

$resolveSpan = static function (array $field, int $rowCount): int {
    $size = (string)($field['size'] ?? '');
    $class = (string)($field['class'] ?? '');

    if ($class === 'doc-field-full') {
        return 12;
    }

    if ($size === 'short') {
        return 3;
    }

    if ($size === 'wide') {
        return 6;
    }

    if ($size === 'full') {
        return 12;
    }

    if ($rowCount === 1) {
        return 12;
    }

    if ($rowCount === 2) {
        return 6;
    }

    if ($rowCount === 3) {
        return 4;
    }

    return 3;
};

$renderTextBlock = static function (string $text): string {
    return nl2br(e($text), false);
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Elite Smiles</title>
    <meta name="robots" content="noindex,nofollow">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --doc-paper-bg: #ffffff;
        }

        .doc-shell {
            background: var(--doc-paper-bg);
            color: #111827;
            font-family: Calibri, Arial, Helvetica, sans-serif;
            width: min(8.6in, 100%);
        }

        .doc-paper {
            position: relative;
            overflow: hidden;
            padding: 0.52in 0.58in 0.95in;
            min-height: 10.9in;
            border: 1px solid #e2e8f0;
            border-radius: 0.4rem;
            box-shadow: 0 14px 35px rgba(15, 23, 42, 0.12);
            background: var(--doc-paper-bg);
            box-sizing: border-box;
            isolation: isolate;
        }

        .doc-header {
            border-bottom: 1px solid #d9e2ec;
            margin-bottom: 0.45rem;
            padding-bottom: 0.55rem;
            text-align: center;
        }

        .doc-logo {
            width: 1.9in;
            max-width: 100%;
            margin: 0 auto;
            display: block;
        }

        .doc-page-title {
            margin-top: 0.22rem;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .doc-page-subtitle {
            margin-top: 0.18rem;
            font-size: 0.76rem;
            color: #334155;
            letter-spacing: 0.02em;
        }

        .doc-section-title {
            margin-top: 0.5rem;
            margin-bottom: 0.26rem;
            font-size: 0.85rem;
            text-transform: none;
            letter-spacing: 0.035em;
            font-weight: 700;
            color: #334155;
            border-left: 0.25rem solid #0f172a;
            padding-left: 0.4rem;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 0.2rem;
            padding-top: 0.12rem;
            padding-bottom: 0.12rem;
        }

        .doc-subtitle-block {
            font-size: 12px;
            color: #475569;
            margin-top: 0.02rem;
            margin-bottom: 0.22rem;
            line-height: 1.35;
        }

        .doc-text-block {
            font-size: 12px;
            color: #111827;
            line-height: 1.35;
            margin-top: 0.13rem;
            margin-bottom: 0.33rem;
            text-align: left;
        }

        .doc-field-row {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            column-gap: 0.32in;
            row-gap: 0.19in;
            margin-bottom: 0.15in;
        }

        .doc-paper.doc-form-large .doc-field-row {
            row-gap: 0.24in;
            margin-bottom: 0.22in;
        }

        .doc-field {
            position: relative;
            margin: 0;
            min-height: 2rem;
            border-bottom: 1px solid #0f172a;
            display: flex;
            align-items: flex-end;
            line-height: 1.25;
            min-width: 0;
            font-size: 12px;
            color: #0f172a;
        }

        .doc-paper.doc-form-large .doc-field {
            min-height: 2.35rem;
        }

        .doc-field label {
            display: flex;
            width: 100%;
            align-items: baseline;
            min-width: 0;
            gap: 0.4rem;
        }

        .doc-field label span {
            flex: 0 0 auto;
            color: #334155;
            white-space: nowrap;
            padding-right: 0.2rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            font-size: 12px;
        }

        .doc-field input {
            min-width: 0;
            flex: 1;
            border: 0;
            outline: 0;
            background: transparent;
            color: #0f172a;
            font-size: 13px;
            padding: 0.18rem 0.02rem 0.12rem;
            font-family: inherit;
            line-height: 1.35;
        }

        .doc-paper.doc-form-large .doc-field input {
            font-size: 14px;
            padding: 0.28rem 0.02rem 0.16rem;
        }

        .doc-signature-wrap {
            margin-bottom: 0.18rem;
            display: grid;
            gap: 0.13rem;
        }

        .doc-signature-row {
            border-bottom: 1px solid #0f172a;
            min-height: 2rem;
            padding-bottom: 0.12rem;
            display: flex;
            align-items: flex-end;
            gap: 0.45rem;
            font-size: 12px;
        }

        .doc-paper.doc-form-large .doc-signature-row {
            min-height: 2.3rem;
            font-size: 12px;
        }

        .doc-signature-label {
            color: #334155;
            font-weight: 600;
            white-space: nowrap;
            flex: 0 0 auto;
        }

        .doc-signature-input {
            border: 0;
            outline: 0;
            background: transparent;
            font-family: inherit;
            color: #0f172a;
            min-width: 0;
            flex: 1;
            font-size: 13px;
            padding: 0.08rem 0.02rem;
        }

        .doc-paper.doc-form-large .doc-signature-input {
            font-size: 13px;
            padding: 0.16rem 0.02rem;
        }

        .doc-plain-list {
            margin: 0.12rem 0 0.35rem;
            padding-left: 0.9rem;
            list-style: none;
            font-size: 12px;
            line-height: 1.35;
            color: #0f172a;
        }

        .doc-field-group {
            border: 1px solid #cbd5e1;
            border-radius: 0.2rem;
            padding: 0.2rem 0.28rem 0.3rem;
            margin-bottom: 0.18rem;
            background: #fff;
            box-shadow: 0 1px 6px rgba(15, 23, 42, 0.03);
        }

        .doc-field-group + .doc-field-group {
            margin-top: 0.1rem;
        }

        .doc-inline-items {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.15rem 0.5rem;
            margin-top: 0.05rem;
        }

        .doc-field-item {
            display: inline-flex;
            align-items: center;
            gap: 0.32rem;
            font-size: 12px;
            color: #334155;
            line-height: 1.3;
        }

        .doc-field-options {
            display: grid;
            gap: 0.15rem;
        }

        .doc-field-item input {
            width: 0.85rem;
            height: 0.85rem;
            border: 1px solid #0f172a;
            accent-color: #0f172a;
            flex: 0 0 auto;
        }

        .doc-footer {
            position: absolute;
            left: 0.56in;
            right: 0.56in;
            bottom: 0.22in;
            border-top: 1px solid #e2e8f0;
            padding-top: 0.18rem;
            font-size: 10px;
            text-align: center;
            color: #64748b;
            line-height: 1.35;
            letter-spacing: 0.01em;
        }

        .doc-page-break {
            break-before: page;
            page-break-before: always;
            margin-top: 0;
            margin-bottom: 0;
            padding-top: 0;
            height: 0;
            width: 100%;
        }

        @media (max-width: 1024px) {
            .doc-paper {
                border: 0;
                border-radius: 0;
                box-shadow: none;
                padding: 0.48in 0.2in 0.85in;
            }

            .doc-footer {
                left: 0.2in;
                right: 0.2in;
            }
        }

        @media print {
            @page {
                size: letter portrait;
                margin: 0.4in 0.35in 0.35in;
            }

            body {
                background: white;
                margin: 0;
            }

            .doc-no-print {
                display: none !important;
            }

            main {
                margin: 0 !important;
                padding: 0 !important;
            }

            .doc-shell {
                width: 100%;
            }

            .doc-paper {
                margin: 0 !important;
                width: 8.5in !important;
                max-width: 8.5in !important;
                min-height: 11in;
                border: 0;
                box-shadow: none;
                padding: 0.42in 0.45in 0.78in;
            }

            .doc-shell {
                width: 100%;
                min-width: auto;
            }

            .doc-field input,
            .doc-signature-input {
                color: #111827;
            }

            .doc-paper.doc-form-large .doc-field-row {
                row-gap: 0.26in;
                margin-bottom: 0.22in;
            }

            .doc-paper.doc-form-large .doc-field,
            .doc-paper.doc-form-large .doc-signature-row {
                min-height: 2.35rem;
            }

            .doc-paper .doc-section-title {
                background: #ffffff;
                border-left: 0.18rem solid #334155;
                border-radius: 0;
                padding-top: 0.08rem;
                padding-bottom: 0.08rem;
            }

            body,
            .doc-paper {
                font-size: 13px;
            }

            .doc-paper.doc-form-large .doc-section-title,
            .doc-section-title {
                font-size: 13px;
                font-weight: 700;
                margin-top: 0.45rem;
                margin-bottom: 0.3rem;
            }

            .doc-paper.doc-form-large .doc-subtitle-block,
            .doc-subtitle-block {
                font-size: 12px;
                line-height: 1.45;
                margin-bottom: 0.25rem;
            }

            .doc-paper.doc-form-large .doc-text-block,
            .doc-text-block {
                font-size: 12px;
                line-height: 1.45;
                margin-top: 0.18rem;
                margin-bottom: 0.28rem;
            }

            .doc-paper.doc-form-large .doc-field-row {
                column-gap: 0.26in;
                row-gap: 0.34in;
                margin-bottom: 0.3in;
            }

            .doc-paper.doc-form-large .doc-field,
            .doc-field {
                min-height: 2.8rem;
                border-bottom-width: 2px;
                line-height: 1.4;
            }

            .doc-paper.doc-form-large .doc-field label {
                align-items: center;
            }

            .doc-paper.doc-form-large .doc-field label span {
                font-size: 12px;
                letter-spacing: 0.015em;
            }

            .doc-paper.doc-form-large .doc-field input,
            .doc-field input,
            .doc-paper.doc-form-large .doc-signature-input,
            .doc-signature-input {
                font-size: 16px;
                padding-top: 0.12rem;
                padding-bottom: 0.12rem;
                line-height: 1.45;
            }

            .doc-paper.doc-form-large .doc-signature-row,
            .doc-signature-row {
                min-height: 2.65rem;
                border-bottom-width: 2px;
                gap: 0.58rem;
            }

            .doc-paper.doc-form-large .doc-signature-wrap {
                margin-bottom: 0.2rem;
            }

            .doc-paper.doc-form-large .doc-signature-label,
            .doc-signature-label {
                font-size: 12px;
                letter-spacing: 0.01em;
            }

            .doc-paper.doc-form-large .doc-field-options,
            .doc-field-options {
                row-gap: 0.22rem;
            }

            .doc-paper.doc-form-large .doc-field-item,
            .doc-field-item {
                font-size: 12px;
                line-height: 1.45;
            }

            .doc-paper.doc-form-large .doc-field-item input[type="checkbox"],
            .doc-field-item input[type="checkbox"] {
                width: 1rem;
                height: 1rem;
                border-width: 2px;
                margin-top: -1px;
            }

            .doc-paper.doc-form-large .doc-footer,
            .doc-footer {
                font-size: 9.5px;
                line-height: 1.35;
            }

            .doc-paper.doc-form-large,
            .doc-paper {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <?php require __DIR__ . '/app/partials/crm_sidebar_live.php'; ?>

    <main class="px-4 py-6 sm:px-6 lg:pl-80 lg:pr-8 lg:py-8">
        <section class="mb-6">
            <div class="flex flex-col gap-4 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between lg:p-7">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Patient Forms</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">Doc Library</h1>
                    <p class="mt-2 text-sm text-slate-600">Printable, paper-ready fillable forms with clinic header and footer branding.</p>
                </div>
                <button type="button" id="doc-print" class="inline-flex min-h-11 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Print form</button>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[320px_1fr]">
            <aside class="doc-no-print rounded-[1.7rem] border border-slate-200 bg-white p-3 shadow-sm">
                <p class="px-3 py-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Templates</p>
                <div class="space-y-2">
                    <?php foreach ($templates as $option): ?>
                        <?php
                        $tplKey = (string)($option['key'] ?? '');
                        $isActive = $tplKey === $selectedTemplateKey;
                        ?>
                        <a
                            href="<?= e(base_url('doc-library.php?template=' . rawurlencode($tplKey))) ?>"
                            class="block rounded-xl px-3 py-2 text-sm font-semibold transition <?= $isActive ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' ?>"
                        >
                            <?= e((string)($option['label'] ?? '')) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>

            <div class="doc-shell">
                <div class="doc-paper <?= $selectedTemplateKey === 'welcome_patient_information' ? 'doc-form-large' : '' ?>">
                    <header class="doc-header">
                        <img src="<?= e($baseLogo) ?>" alt="Elite Smiles" class="doc-logo" loading="eager">
                        <div class="doc-page-title"><?= e($documentTitle) ?></div>
                        <?php if ($documentSubtitle !== ''): ?>
                            <div class="doc-page-subtitle"><?= e($documentSubtitle) ?></div>
                        <?php endif; ?>
                    </header>

                    <form id="doc-library-form" class="space-y-2" autocomplete="off">
                        <?php foreach ($sections as $section): ?>
                            <?php
                            $sectionType = (string)($section['type'] ?? 'section');
                            $sectionTitle = (string)($section['title'] ?? '');
                            $sectionSubtitle = (string)($section['subtitle'] ?? '');
                            $fields = (array)($section['fields'] ?? []);
                            $rows = (array)($section['rows'] ?? []);
                            $pageBreakBefore = (bool)($section['page_break_before'] ?? false);
                            ?>

                            <?php if ($pageBreakBefore): ?>
                                <div class="doc-page-break"></div>
                            <?php endif; ?>

                            <?php if ($sectionTitle !== ''): ?>
                                <div class="doc-section-title"><?= e($sectionTitle) ?></div>
                            <?php endif; ?>

                            <?php if ($sectionSubtitle !== ''): ?>
                                <div class="doc-subtitle-block"><?= e($sectionSubtitle) ?></div>
                            <?php endif; ?>

                            <?php if (!empty($fields)): ?>
                                <div class="doc-text-block">
                                    <?php foreach ($fields as $field): ?>
                                        <?php
                                        $fieldType = (string)($field['type'] ?? 'text');
                                        $fieldText = (string)($field['text'] ?? '');
                                        $fieldName = (string)($field['name'] ?? '');
                                        $fieldLabel = (string)($field['label'] ?? '');
                                        $fieldOptions = (array)($field['options'] ?? []);
                                        ?>

                                        <?php if ($fieldType === 'paragraph'): ?>
                                            <?php
                                            $isList = str_contains($fieldText, "\n-");
                                            if ($isList) {
                                                $items = preg_split('/\R- /', preg_replace('/^\- /', '', $fieldText));
                                                ?>
                                                <ul class="doc-plain-list">
                                                    <?php foreach ((array)$items as $item): ?>
                                                        <?php $line = trim((string)$item); ?>
                                                        <?php if ($line !== ''): ?>
                                                            <li><?= e($line) ?></li>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php } else { ?>
                                                <p><?= $renderTextBlock($fieldText) ?></p>
                                            <?php } ?>
                                        <?php elseif ($fieldType === 'checkbox'): ?>
                                            <label class="doc-field-item">
                                                <input type="checkbox" name="<?= e($fieldName) ?>" value="1">
                                                <span><?= e($fieldLabel) ?></span>
                                            </label>
                                        <?php elseif ($fieldType === 'checkbox_list'): ?>
                                            <div class="doc-field-group">
                                                <p class="mb-1 text-xs font-semibold text-slate-700"><?= e($fieldLabel ?: 'Select all that apply:') ?></p>
                                                <div class="doc-field-options">
                                                    <?php foreach ($fieldOptions as $option): ?>
                                                        <label class="doc-field-item">
                                                            <input type="checkbox" name="<?= e($fieldName) ?>" value="<?= e((string)$option) ?>">
                                                            <span><?= e((string)$option) ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($rows)): ?>
                                <?php foreach ($rows as $row): ?>
                                    <?php if (!is_array($row) || empty($row)) {
                                        continue;
                                    } ?>

                                    <?php
                                    $row = array_values(array_filter((array)$row, static fn($entry): bool => is_array($entry)));
                                    if (empty($row)) {
                                        continue;
                                    }

                                    $firstType = (string)($row[0]['type'] ?? '');
                                    if ($firstType === 'row_title') {
                                        ?>
                                        <div class="doc-text-block"><strong><?= e((string)($row[0]['text'] ?? '')) ?></strong></div>
                                        <?php
                                        continue;
                                    }

                                    if ($firstType === 'signature_single' || $firstType === 'signature_double') {
                                        ?>
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <?php foreach ($row as $signature): ?>
                                                <?php
                                                $sigLabel = (string)($signature['label'] ?? '');
                                                $sigName = (string)($signature['name'] ?? $signature['left_name'] ?? '');
                                                if ($firstType === 'signature_double') {
                                                    $leftLabel = (string)($signature['left_label'] ?? $sigLabel);
                                                    $rightLabel = (string)($signature['right_label'] ?? '');
                                                    $leftName = (string)($signature['left_name'] ?? '');
                                                    $rightName = (string)($signature['right_name'] ?? '');
                                                    if ($leftLabel !== '') {
                                                        ?>
                                                        <div class="doc-signature-wrap">
                                                            <div class="doc-signature-row">
                                                                <span class="doc-signature-label"><?= e($leftLabel) ?></span>
                                                                <input class="doc-signature-input" type="text" name="<?= e($leftName) ?>" aria-label="<?= e($leftLabel) ?>">
                                                            </div>
                                                        </div>
                                                        <?php
                                                    }
                                                    if ($rightLabel !== '') {
                                                        ?>
                                                        <div class="doc-signature-wrap">
                                                            <div class="doc-signature-row">
                                                                <span class="doc-signature-label"><?= e($rightLabel) ?></span>
                                                                <input class="doc-signature-input" type="text" name="<?= e($rightName) ?>" aria-label="<?= e($rightLabel) ?>">
                                                            </div>
                                                        </div>
                                                        <?php
                                                    }
                                                    continue;
                                                }
                                                ?>
                                                <div class="doc-signature-wrap">
                                                    <div class="doc-signature-row">
                                                        <span class="doc-signature-label"><?= e($sigLabel) ?></span>
                                                        <input class="doc-signature-input" type="text" name="<?= e($sigName) ?>" aria-label="<?= e($sigLabel) ?>">
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php
                                        continue;
                                    }
                                    ?>

                                    <div class="doc-field-row">
                                        <?php foreach ($row as $field): ?>
                                            <?php
                                            $fieldType = (string)($field['type'] ?? 'text');
                                            $fieldLabel = (string)($field['label'] ?? '');
                                            $fieldName = (string)($field['name'] ?? '');
                                            $fieldOptions = (array)($field['options'] ?? []);
                                            $isInline = !empty($field['inline']);
                                            $span = $resolveSpan($field, count($row));
                                            $style = 'grid-column: span ' . $span . ' / span ' . $span . ';';
                                            $inputType = match ((string)($field['type'] ?? 'text')) {
                                                'date' => 'date',
                                                'email' => 'email',
                                                default => 'text',
                                            };

                                            if ($fieldType === 'checkbox_row') {
                                                ?>
                                                <div class="doc-field-group" style="<?= e($style) ?>">
                                                    <p class="mb-1 text-xs font-semibold text-slate-700"><?= e($fieldLabel) ?></p>
                                                    <div class="doc-field-options <?= $isInline ? 'doc-inline-items' : '' ?>">
                                                        <?php foreach ($fieldOptions as $option): ?>
                                                            <label class="doc-field-item">
                                                                <input type="checkbox" name="<?= e($fieldName) ?>" value="<?= e((string)$option) ?>">
                                                                <span><?= e((string)$option) ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <?php
                                                continue;
                                            }

                                            if ($fieldType === 'checkbox') {
                                                ?>
                                                <div class="doc-field-group" style="<?= e($style) ?>">
                                                    <label class="doc-field-item">
                                                        <input type="checkbox" name="<?= e($fieldName) ?>" value="1">
                                                        <span><?= e($fieldLabel) ?></span>
                                                    </label>
                                                </div>
                                                <?php
                                                continue;
                                            }
                                            ?>
                                            <div style="<?= e($style) ?>">
                                                <div class="doc-field">
                                                    <label>
                                                        <span><?= e($fieldLabel) ?></span>
                                                        <input type="<?= e($inputType) ?>" name="<?= e($fieldName) ?>" aria-label="<?= e($fieldLabel) ?>">
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </form>

                    <footer class="doc-footer">
                        Elite Smiles by Dr. Walter Meden · 11762 South State, Suite 300, Draper, UT 84020 · (801) 572-6262
                    </footer>
                </div>
            </div>
        </section>
    </main>

    <script>
        const printButton = document.getElementById('doc-print');
        if (printButton) {
            printButton.addEventListener('click', function () {
                window.print();
            });
        }
    </script>
</body>
</html>
