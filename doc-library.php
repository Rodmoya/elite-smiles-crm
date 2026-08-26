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

// Split sections into printed pages. A section may set 'page_break_before' => true
// to start a fresh sheet of paper, matching how the original paper forms are laid
// out (e.g. the Welcome packet is two physical pages).
$pages = [[]];
foreach ($sections as $section) {
    if (!empty($section['page_break_before']) && $pages[count($pages) - 1] !== []) {
        $pages[] = [];
    }
    $pages[count($pages) - 1][] = $section;
}

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

// A labeled intake box: label sits above a bordered writing area sized for pen
// entry. Date fields are plain text boxes (with an "MM / DD / YYYY" guide) -
// this is a print-only form, so a browser date-picker widget is meaningless
// and would look broken on paper.
$renderIntakeBox = static function (array $field, string $style = '') use (&$renderIntakeBox): void {
    $fieldType = (string)($field['type'] ?? 'text');
    $fieldLabel = (string)($field['label'] ?? '');
    $fieldName = (string)($field['name'] ?? '');
    $isDate = $fieldType === 'date';
    $placeholder = $isDate ? 'MM / DD / YYYY' : '';
    ?>
    <div class="doc-field-box" style="<?= e($style) ?>">
        <label class="doc-field-box-label" for="f_<?= e($fieldName) ?>"><?= e($fieldLabel) ?></label>
        <input class="doc-field-box-input" type="text" id="f_<?= e($fieldName) ?>" name="<?= e($fieldName) ?>" placeholder="<?= e($placeholder) ?>" aria-label="<?= e($fieldLabel) ?>">
    </div>
    <?php
};

// Renders a block of freeform paragraph/checkbox content that sits above the
// field rows in a section (used for the long consent/policy narrative text).
$renderFields = static function (array $fields) use ($renderTextBlock, $renderIntakeBox): void {
    foreach ($fields as $field) {
        $fieldType = (string)($field['type'] ?? 'text');
        $fieldText = (string)($field['text'] ?? '');
        $fieldName = (string)($field['name'] ?? '');
        $fieldLabel = (string)($field['label'] ?? '');
        $fieldOptions = (array)($field['options'] ?? []);

        if ($fieldType === 'paragraph') {
            $isList = str_contains($fieldText, "\n- ");
            if ($isList) {
                $items = preg_split('/\R- /', preg_replace('/^- /', '', $fieldText) ?? $fieldText) ?: [];
                echo '<ul class="doc-plain-list">';
                foreach ($items as $item) {
                    $line = trim((string)$item);
                    if ($line !== '') {
                        echo '<li>' . e($line) . '</li>';
                    }
                }
                echo '</ul>';
            } else {
                echo '<p>' . $renderTextBlock($fieldText) . '</p>';
            }
        } elseif ($fieldType === 'heading') {
            echo '<p class="doc-inline-heading">' . e($fieldText) . '</p>';
        } elseif ($fieldType === 'checkbox') {
            ?>
            <label class="doc-field-item doc-field-item-block">
                <input type="checkbox" name="<?= e($fieldName) ?>" value="1">
                <span><?= $renderTextBlock($fieldLabel) ?></span>
            </label>
            <?php
        } elseif ($fieldType === 'checkbox_list') {
            ?>
            <div class="doc-field-group">
                <?php if ($fieldLabel !== ''): ?><p class="doc-group-label"><?= e($fieldLabel) ?></p><?php endif; ?>
                <div class="doc-field-options">
                    <?php foreach ($fieldOptions as $option): ?>
                        <label class="doc-field-item">
                            <input type="checkbox" name="<?= e($fieldName) ?>" value="<?= e((string)$option) ?>">
                            <span><?= e((string)$option) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
        } elseif ($fieldType === 'initial_line') {
            ?>
            <div class="doc-initial-line">
                <span class="doc-initial-box" aria-hidden="true"></span>
                <span class="doc-initial-caption">(Initials) <?= e($fieldLabel) ?></span>
            </div>
            <?php
        } elseif ($fieldType === 'text' || $fieldType === 'date' || $fieldType === 'email') {
            $renderIntakeBox($field, 'max-width: 4.6in;');
        }
    }
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
            --doc-accent: #0f6f6a;
            --doc-accent-deep: #0b5450;
            --doc-accent-soft: #eaf5f4;
            --doc-ink: #111111;
            --doc-muted: #63696b;
            --doc-hairline: #d3dcdb;
            --doc-box-bg: #fdfefe;
            --doc-paper-bg: #ffffff;
        }

        .doc-shell {
            background: var(--doc-paper-bg);
            color: var(--doc-ink);
            font-family: 'Calibri', 'Segoe UI', Arial, Helvetica, sans-serif;
            width: min(8.7in, 100%);
        }

        .doc-paper {
            position: relative;
            padding: 0.28in 0.42in 0.34in;
            min-height: 9in;
            border: 1px solid var(--doc-hairline);
            border-radius: 0.4rem;
            box-shadow: 0 18px 44px rgba(28, 26, 27, 0.08);
            background: var(--doc-paper-bg);
            margin-bottom: 0.55in;
        }

        .doc-paper:last-child {
            margin-bottom: 0;
        }

        .doc-header {
            border-bottom: 2px solid var(--doc-accent);
            margin-bottom: 0.16rem;
            padding-bottom: 0.16rem;
            text-align: center;
        }

        .doc-header.doc-header-continued {
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding-bottom: 0.32rem;
        }

        .doc-logo {
            width: 1.3in;
            max-width: 100%;
            margin: 0 auto;
            display: block;
        }

        .doc-header-continued .doc-logo {
            width: 1.15in;
            margin: 0;
        }

        .doc-page-title {
            margin-top: 0.08rem;
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: var(--doc-ink);
        }

        .doc-header-continued .doc-page-title {
            margin-top: 0;
            font-size: 0.86rem;
            font-family: inherit;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .doc-page-subtitle {
            margin-top: 0.1rem;
            font-size: 0.72rem;
            color: var(--doc-muted);
            letter-spacing: 0.02em;
            font-style: italic;
        }

        .doc-page-index {
            font-size: 0.68rem;
            color: var(--doc-muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            white-space: nowrap;
        }

        .doc-section-title {
            margin-top: 0.18rem;
            margin-bottom: 0.1rem;
            padding: 0.08rem 0.4rem;
            font-size: 0.62rem;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            font-weight: 700;
            color: var(--doc-accent-deep);
            background: var(--doc-accent-soft);
            border-left: 3px solid var(--doc-accent);
            border-radius: 0.16rem;
            break-inside: avoid;
            break-after: avoid;
        }

        .doc-subtitle-block {
            font-size: 10px;
            color: var(--doc-muted);
            margin-top: 0.02rem;
            margin-bottom: 0.16rem;
        }

        .doc-text-block {
            font-size: 10.2px;
            color: var(--doc-ink);
            line-height: 1.28;
            margin-top: 0.06rem;
            margin-bottom: 0.14rem;
            text-align: left;
        }

        .doc-text-block p {
            margin: 0 0 0.22rem;
        }

        .doc-text-block p:last-child {
            margin-bottom: 0;
        }

        .doc-inline-heading {
            font-weight: 700;
            margin: 0.3rem 0 0.1rem !important;
            color: var(--doc-ink);
        }

        .doc-initial-line {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin: 0.16rem 0 0.3rem;
        }

        .doc-initial-box {
            display: inline-block;
            width: 1.05in;
            border: 1px solid var(--doc-ink);
            border-radius: 0.15rem;
            height: 1.3rem;
            background: var(--doc-box-bg);
        }

        .doc-initial-caption {
            font-size: 9.8px;
            color: var(--doc-muted);
        }

        /* --- Intake boxes: label on top, bordered writing box below. Sized to
           pack tightly - the office's original packet fits this much content
           on two physical pages, and these forms need to keep matching that. --- */
        .doc-field-row {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            column-gap: 0.22in;
            row-gap: 0.012in;
            margin-bottom: 0.016in;
            align-items: end;
        }

        .doc-field-box {
            display: flex;
            flex-direction: column;
            gap: 0.01rem;
            min-width: 0;
        }

        .doc-field-box-label {
            font-size: 6.2px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--doc-muted);
            line-height: 1;
        }

        .doc-field-box-input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid var(--doc-ink);
            border-radius: 0.12rem;
            background: var(--doc-box-bg);
            min-height: 1rem;
            padding: 0.04rem 0.22rem;
            font-family: inherit;
            font-size: 9.6px;
            color: var(--doc-ink);
            outline: 0;
        }

        .doc-field-box-input::placeholder {
            color: #b7adb0;
            font-size: 9px;
            letter-spacing: 0.03em;
        }

        .doc-field-inline-labeled {
            margin-bottom: 0.18rem;
        }

        /* Signature line: the blank writing line comes first, with the caption
           label sitting underneath it - matching the office's paper forms
           ("_____________  Signature of Patient or Parent" reads as a line to
           sign on, captioned below, not a label next to a line). */
        .doc-signature-wrap {
            margin-bottom: 0.2rem;
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
        }

        .doc-signature-line {
            border-bottom: 1px solid var(--doc-ink);
            min-height: 1.4rem;
            display: flex;
            align-items: flex-end;
        }

        .doc-signature-label {
            color: var(--doc-muted);
            font-weight: 600;
            font-size: 9.6px;
        }

        .doc-signature-input {
            width: 100%;
            border: 0;
            outline: 0;
            background: transparent;
            font-family: inherit;
            color: var(--doc-ink);
            min-width: 0;
            font-size: 12px;
            padding: 0.06rem 0.02rem;
        }

        .doc-plain-list {
            margin: 0.14rem 0 0.4rem;
            padding-left: 1.05rem;
            list-style: disc;
            font-size: 10.6px;
            line-height: 1.42;
            color: var(--doc-ink);
        }

        .doc-field-group {
            border: 1px solid var(--doc-hairline);
            border-radius: 0.2rem;
            padding: 0.07rem 0.2rem 0.09rem;
            margin-bottom: 0.05rem;
            background: #fff;
            break-inside: avoid;
        }

        .doc-group-label {
            margin: 0 0 0.05rem;
            font-size: 8.2px;
            font-weight: 600;
            color: var(--doc-ink);
        }

        /* "Inline" checkbox groups (Sex, Marital Status, etc.) sit on one row -
           the original form fits these on a single line, not a wrapped grid.
           (Compound selector so this reliably beats .doc-field-options'
           display:grid regardless of source order.) */
        .doc-field-options.doc-inline-items {
            display: flex;
            flex-wrap: nowrap;
            justify-content: space-between;
            gap: 0.25rem;
            margin-top: 0.01rem;
        }

        .doc-inline-items .doc-field-item {
            flex: 0 0 auto;
            white-space: nowrap;
        }

        .doc-field-options {
            display: grid;
            gap: 0.04rem;
        }

        .doc-field-item {
            display: inline-flex;
            align-items: center;
            gap: 0.26rem;
            font-size: 8.4px;
            color: var(--doc-ink);
            line-height: 1.1;
        }

        .doc-field-item-block {
            display: flex;
            margin: 0.12rem 0 0.24rem;
        }

        .doc-field-item input {
            width: 0.72rem;
            height: 0.72rem;
            border: 1px solid var(--doc-ink);
            accent-color: var(--doc-accent);
            flex: 0 0 auto;
        }

        /* Multi-column checklist grid, used for the dental/medical history lists. */
        .doc-check-grid {
            display: grid;
            gap: 0.05rem 0.3rem;
            margin: 0.07rem 0 0.1rem;
            break-inside: avoid;
        }

        .doc-check-grid[data-columns="2"] { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .doc-check-grid[data-columns="3"] { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .doc-check-grid[data-columns="4"] { grid-template-columns: repeat(4, minmax(0, 1fr)); }

        @media (max-width: 900px) {
            .doc-check-grid[data-columns="3"],
            .doc-check-grid[data-columns="4"] {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        /* Ruled blank lines for freeform pen writing (medications, allergies, etc.). */
        .doc-write-lines {
            margin: 0.08rem 0 0.16rem;
        }

        .doc-write-lines-label {
            font-size: 9.8px;
            font-weight: 600;
            color: var(--doc-ink);
            margin-bottom: 0.12rem;
        }

        .doc-write-line {
            border-bottom: 1px solid var(--doc-ink);
            height: 0.95rem;
        }

        .doc-write-columns {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 0.5in;
        }

        .doc-footer {
            position: absolute;
            left: 0.55in;
            right: 0.55in;
            bottom: 0.2in;
            border-top: 1px solid var(--doc-hairline);
            padding-top: 0.14rem;
            font-size: 8.6px;
            text-align: center;
            color: var(--doc-muted);
            line-height: 1.3;
            letter-spacing: 0.01em;
        }

        @media (max-width: 1024px) {
            .doc-paper {
                border: 0;
                border-radius: 0;
                box-shadow: none;
                padding: 0.42in 0.22in 0.6in;
            }

            .doc-footer {
                left: 0.22in;
                right: 0.22in;
            }
        }

        @media print {
            body {
                background: white;
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
                width: 100% !important;
                min-height: 100vh;
                border: 0;
                box-shadow: none;
                padding: 0.28in 0.42in 0.34in;
                break-after: page;
            }

            .doc-paper:last-child {
                break-after: auto;
            }

            .doc-field-box-input,
            .doc-signature-input {
                color: #111827;
                background: transparent;
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
                    <p class="mt-2 text-sm text-slate-600">Printable, paper-ready fillable forms with clinic header and footer branding. Legal and financial language matches the office's current signed forms exactly.</p>
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
                <form id="doc-library-form" autocomplete="off">
                    <?php $pageCount = count($pages); ?>
                    <?php foreach ($pages as $pageIndex => $pageSections): ?>
                        <div class="doc-paper">
                            <?php if ($pageIndex === 0): ?>
                                <header class="doc-header">
                                    <img src="<?= e($baseLogo) ?>" alt="Elite Smiles" class="doc-logo" loading="eager">
                                    <div class="doc-page-title"><?= e($documentTitle) ?></div>
                                    <?php if ($documentSubtitle !== ''): ?>
                                        <div class="doc-page-subtitle"><?= e($documentSubtitle) ?></div>
                                    <?php endif; ?>
                                </header>
                            <?php else: ?>
                                <header class="doc-header doc-header-continued">
                                    <img src="<?= e($baseLogo) ?>" alt="Elite Smiles" class="doc-logo" loading="eager">
                                    <div class="doc-page-title"><?= e($documentTitle) ?> (continued)</div>
                                    <span class="doc-page-index">Page <?= (int)($pageIndex + 1) ?> of <?= (int)$pageCount ?></span>
                                </header>
                            <?php endif; ?>

                            <?php foreach ($pageSections as $section): ?>
                                <?php
                                $sectionTitle = (string)($section['title'] ?? '');
                                $sectionSubtitle = (string)($section['subtitle'] ?? '');
                                $fields = (array)($section['fields'] ?? []);
                                $rows = (array)($section['rows'] ?? []);
                                ?>

                                <?php if ($sectionTitle !== ''): ?>
                                    <div class="doc-section-title"><?= e($sectionTitle) ?></div>
                                <?php endif; ?>

                                <?php if ($sectionSubtitle !== ''): ?>
                                    <div class="doc-subtitle-block"><?= e($sectionSubtitle) ?></div>
                                <?php endif; ?>

                                <?php if (!empty($fields)): ?>
                                    <div class="doc-text-block">
                                        <?php $renderFields($fields); ?>
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
                                            <div class="doc-text-block"><strong><?= $renderTextBlock((string)($row[0]['text'] ?? '')) ?></strong></div>
                                            <?php
                                            continue;
                                        }

                                        if ($firstType === 'checkbox_grid') {
                                            $grid = $row[0];
                                            $columns = max(1, (int)($grid['columns'] ?? 3));
                                            $gridName = (string)($grid['name'] ?? '');
                                            $gridOptions = (array)($grid['options'] ?? []);
                                            // Options are listed top-to-bottom within each column (matching the
                                            // original paper form's reading order), so the grid must fill by
                                            // column, not by row.
                                            $gridRows = (int)ceil(count($gridOptions) / $columns);
                                            $gridStyle = 'grid-auto-flow: column; grid-template-rows: repeat(' . max(1, $gridRows) . ', auto);';
                                            ?>
                                            <div class="doc-check-grid" data-columns="<?= (int)$columns ?>" style="<?= e($gridStyle) ?>">
                                                <?php foreach ($gridOptions as $option): ?>
                                                    <label class="doc-field-item">
                                                        <input type="checkbox" name="<?= e($gridName) ?>[]" value="<?= e((string)$option) ?>">
                                                        <span><?= e((string)$option) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php
                                            continue;
                                        }

                                        if ($firstType === 'checkbox_list') {
                                            $list = $row[0];
                                            $listName = (string)($list['name'] ?? '');
                                            $listLabel = (string)($list['label'] ?? '');
                                            ?>
                                            <div class="doc-field-group">
                                                <?php if ($listLabel !== ''): ?><p class="doc-group-label"><?= e($listLabel) ?></p><?php endif; ?>
                                                <div class="doc-field-options">
                                                    <?php foreach ((array)($list['options'] ?? []) as $option): ?>
                                                        <label class="doc-field-item">
                                                            <input type="checkbox" name="<?= e($listName) ?>" value="<?= e((string)$option) ?>">
                                                            <span><?= e((string)$option) ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php
                                            continue;
                                        }

                                        if ($firstType === 'write_lines') {
                                            ?>
                                            <div class="doc-write-columns">
                                                <?php foreach ($row as $writeField): ?>
                                                    <?php $lineCount = max(1, (int)($writeField['count'] ?? 2)); ?>
                                                    <div class="doc-write-lines">
                                                        <?php if (!empty($writeField['label'])): ?>
                                                            <p class="doc-write-lines-label"><?= e((string)$writeField['label']) ?></p>
                                                        <?php endif; ?>
                                                        <?php for ($i = 0; $i < $lineCount; $i++): ?>
                                                            <div class="doc-write-line"></div>
                                                        <?php endfor; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
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
                                                                <div class="doc-signature-line">
                                                                    <input class="doc-signature-input" type="text" name="<?= e($leftName) ?>" aria-label="<?= e($leftLabel) ?>">
                                                                </div>
                                                                <span class="doc-signature-label"><?= e($leftLabel) ?></span>
                                                            </div>
                                                            <?php
                                                        }
                                                        if ($rightLabel !== '') {
                                                            ?>
                                                            <div class="doc-signature-wrap">
                                                                <div class="doc-signature-line">
                                                                    <input class="doc-signature-input" type="text" name="<?= e($rightName) ?>" aria-label="<?= e($rightLabel) ?>">
                                                                </div>
                                                                <span class="doc-signature-label"><?= e($rightLabel) ?></span>
                                                            </div>
                                                            <?php
                                                        }
                                                        continue;
                                                    }
                                                    ?>
                                                    <div class="doc-signature-wrap">
                                                        <div class="doc-signature-line">
                                                            <input class="doc-signature-input" type="text" name="<?= e($sigName) ?>" aria-label="<?= e($sigLabel) ?>">
                                                        </div>
                                                        <span class="doc-signature-label"><?= e($sigLabel) ?></span>
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

                                                if ($fieldType === 'checkbox_row') {
                                                    ?>
                                                    <div class="doc-field-group" style="<?= e($style) ?>">
                                                        <p class="doc-group-label"><?= e($fieldLabel) ?></p>
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

                                                $renderIntakeBox($field, $style);
                                            ?>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <footer class="doc-footer">
                                Elite Smiles by Dr. Walter Meden &middot; 11762 South State, Suite 300, Draper, UT 84020 &middot; (801) 572-6262
                            </footer>
                        </div>
                    <?php endforeach; ?>
                </form>
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
