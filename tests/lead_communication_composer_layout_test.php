<?php

declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../app/partials/dashboard_pipeline.php');

if (!is_string($source)) {
    fwrite(STDERR, "Could not read the lead communication composer source.\n");
    exit(1);
}

function lead_composer_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

lead_composer_expect(
    str_contains($source, 'id="modal-composer-body" class="min-h-0 w-full flex-1 overflow-y-auto'),
    'The communication composer body must scroll when its controls exceed the available height.'
);
lead_composer_expect(
    str_contains($source, "composerMode === 'email' ? 340"),
    'Email mode must reserve enough vertical space to keep its action row accessible.'
);
lead_composer_expect(
    str_contains($source, "composerMode === 'email' ? 0.43"),
    'Email mode must use the larger responsive composer ratio.'
);
lead_composer_expect(
    str_contains($source, 'data-composer-panel="email" class="hidden flex h-full'),
    'The email panel must be a flex container so its body can shrink without pushing actions below the fold.'
);
lead_composer_expect(
    substr_count($source, 'applyCommunicationViewportFit();') >= 4,
    'Changing composer mode must recalculate the communication workspace layout.'
);
lead_composer_expect(
    str_contains($source, 'id="modal-lead-send-email-button"'),
    'The email composer must retain its send action.'
);

echo "Lead communication composer layout tests passed.\n";
