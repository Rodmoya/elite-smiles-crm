<?php
declare(strict_types=1);

/**
 * Length control for veneer generation.
 *
 * A live A/B on case 42 (set 10 pre-geometry-framework vs set 11 after it)
 * showed the framework fixed arch fullness but left crowns reading wide and
 * short with a nearly flat incisal line. The cause was an explicit ceiling
 * that capped tooth length at the patient's existing display, so a worn-short
 * smile could never be restored. Dr. Meden's clinical target is a 10.5-11 mm
 * central incisor, so length is now a target with an outer bound, not a cap.
 */

$providers = file_get_contents(dirname(__DIR__) . '/app/smile_design/providers.php');
$casePage = file_get_contents(dirname(__DIR__) . '/smile-design/cases/show.php');

if (!is_string($providers) || !is_string($casePage)) {
    fwrite(STDERR, "Could not read the smile design sources.\n");
    exit(1);
}

function smile_length_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

// --- The cap is gone and must not come back -------------------------------
smile_length_expect(
    !str_contains($providers, 'Incisal length ceiling'),
    'The old incisal length ceiling capped length at the existing display and must stay removed.'
);
smile_length_expect(
    !str_contains($providers, 'length ceiling'),
    'No rule may reference a length ceiling that no longer exists.'
);

// --- The clinical target reaches both providers ---------------------------
smile_length_expect(
    substr_count($providers, 'Clinical crown length target') === 2,
    'Both the Gemini and OpenAI providers must carry the crown length target.'
);
smile_length_expect(
    substr_count($providers, '10.5 mm to 11 mm') === 2,
    "Dr. Meden's 10.5-11 mm central incisor target must appear in both providers."
);
smile_length_expect(
    substr_count($providers, '4:5 width-to-length ratio') === 2,
    'The 4:5 width-to-length ratio must appear in both providers.'
);
smile_length_expect(
    str_contains($providers, 'lengthen them toward that target instead of preserving the short display'),
    'Worn-short teeth must be restored toward the target rather than preserved.'
);

// --- The outer bound survives, so "target" never means "unbounded" --------
smile_length_expect(
    substr_count($providers, 'Length failure rule') === 2,
    'Both providers must carry the length failure rule.'
);
smile_length_expect(
    substr_count($providers, 'chiclet') === 2,
    'The chiclet-block failure term must appear in both providers.'
);
foreach (['never hidden behind the lower lip', 'never crossing over it', 'denture-like smile line remains a failure'] as $bound) {
    smile_length_expect(
        substr_count($providers, $bound) === 2,
        'The length target must stay bounded in both providers by: ' . $bound
    );
}

// --- The arc must demand a visible step, not just a curve -----------------
smile_length_expect(
    str_contains($providers, 'the laterals are visibly shorter so their edges step up above the centrals'),
    'The smile arc rule must state the lateral step explicitly, since a flat edge line was the observed failure.'
);
smile_length_expect(
    str_contains($providers, 'A flat line of equal-length edges reads older and artificial and is a failure'),
    'A flat line of equal-length incisal edges must be named as a failure.'
);

// --- Geometry measurements must be visible to staff -----------------------
smile_length_expect(
    str_contains($casePage, 'Smile geometry measured'),
    'The case page must surface what the analysis measured, or results can only be judged by eye.'
);
foreach (['dental_midline', 'incisal_plane', 'smile_arc', 'central_incisor_proportion', 'gingival_zeniths'] as $field) {
    smile_length_expect(
        str_contains($casePage, "'" . $field . "'"),
        'The geometry panel must render the measured field: ' . $field
    );
}
smile_length_expect(
    str_contains($casePage, 'Corrections sent to the generator'),
    'The corrections handed to the image model must be visible alongside the measurements.'
);
smile_length_expect(
    str_contains($casePage, 'Smile geometry was not measured on this analysis'),
    'A cached analysis without geometry must say so rather than render an empty panel.'
);

fwrite(STDOUT, "Smile design length precision tests passed.\n");
