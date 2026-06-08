<?php
declare(strict_types=1);

/**
 * Elite Smiles Landing Engine
 * File: crm/app/landing_pages/engine/renderer.php
 *
 * Renders the correct landing template for the current page.
 */

require_once __DIR__ . '/context.php';

if (!function_exists('lp_renderer_template_basename')) {
    function lp_renderer_template_basename(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return basename($value);
    }
}

if (!function_exists('lp_renderer_resolve_template_file')) {
    function lp_renderer_resolve_template_file(array $ctx, string $templateDir): string
    {
        $candidates = [];

        $explicitTemplate = trim((string) ($ctx['templateFile'] ?? ''));
        if ($explicitTemplate !== '') {
            if (is_file($explicitTemplate)) {
                return $explicitTemplate;
            }

            $basename = lp_renderer_template_basename($explicitTemplate);
            if ($basename !== '') {
                $candidates[] = $templateDir . '/' . $basename;
            }
        }

        $pageTemplate = lp_renderer_template_basename($ctx['pageRow']['template'] ?? '');
        if ($pageTemplate !== '') {
            $candidates[] = $templateDir . '/' . $pageTemplate;
        }

        $procedureTemplate = lp_renderer_template_basename($ctx['procedure']['template'] ?? '');
        if ($procedureTemplate !== '') {
            $candidates[] = $templateDir . '/' . $procedureTemplate;
        }

        $layoutVariant = trim((string) ($ctx['layoutVariant'] ?? 'standard'));
        if ($layoutVariant === 'voucher_compact') {
            $candidates[] = $templateDir . '/voucher.php';
        }

        $candidates[] = $templateDir . '/standard.php';

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $templateDir . '/standard.php';
    }
}

if (!function_exists('lp_render')) {
    function lp_render(array $pageRow = [], array $formResult = [], array $runtime = []): void
    {
        $templateDir = __DIR__ . '/../templates';
        $isPrebuiltContext = isset($pageRow['registry'], $pageRow['procedure'], $pageRow['pageRow']);
        $ctx = $isPrebuiltContext ? $pageRow : lp_build_context($pageRow, $runtime);

        if (!empty($formResult['standardForm']) && is_array($formResult['standardForm'])) {
            $ctx['standardForm'] = $formResult['standardForm'];
        }
        if (!empty($formResult['voucherForm']) && is_array($formResult['voucherForm'])) {
            $ctx['voucherForm'] = $formResult['voucherForm'];
        }
        if (isset($ctx['modal']) && is_array($ctx['modal'])) {
            $ctx['modal']['form_state'] = $ctx['standardForm'] ?? [];
        }
        $ctx['landingContext'] = $ctx;
        $ctx['ctx'] = $ctx;

        $templateFile = lp_renderer_resolve_template_file($ctx, $templateDir);

        if (!is_file($templateFile)) {
            http_response_code(500);
            exit('Landing template not found: ' . htmlspecialchars((string) basename($templateFile), ENT_QUOTES, 'UTF-8'));
        }

        extract($ctx, EXTR_SKIP);
        extract($formResult, EXTR_PREFIX_ALL, 'form');

        $successMessage   = (string) ($formResult['successMessage'] ?? '');
        $errorMessage     = (string) ($formResult['error'] ?? '');
        $voucherSubmitted = (bool)   ($formResult['voucherSubmitted'] ?? false);
        $voucherReference = (string) ($formResult['voucherReference'] ?? '');
        $selectedTemplate = basename($templateFile);

        require $templateFile;
    }
}
