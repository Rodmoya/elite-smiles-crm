<?php
declare(strict_types=1);

$board = (string)file_get_contents(dirname(__DIR__) . '/app/partials/dashboard_pipeline.php');
$card = (string)file_get_contents(dirname(__DIR__) . '/app/partials/lead_card.php');

function stage_move_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

stage_move_expect(str_contains($card, 'draggable="false"'), 'The whole lead card must not start an accidental drag.');
stage_move_expect(str_contains($card, 'data-lead-drag-handle'), 'Each lead needs an intentional drag handle.');
stage_move_expect(str_contains($card, 'data-stage-move-trigger'), 'Each lead needs a direct stage-move control.');
stage_move_expect(str_contains($board, 'id="pipeline-stage-dialog"'), 'Stage moves need a keyboard-accessible selection dialog.');
stage_move_expect(str_contains($board, 'stageMoveSubmit.disabled = stageMoveInFlight || !stageMoveSelect.value || stageMoveSelect.value === currentStage'), 'Moving to the current stage must not be submitted.');
stage_move_expect(str_contains($board, 'event.dataTransfer.setData('), 'Explicit dragging must provide transferable data.');
stage_move_expect(str_contains($board, 'const movingCard = draggedCard;'), 'A dropped card must be captured before dragend clears the global reference.');
stage_move_expect(str_contains($board, 'originDropzone.insertBefore(card, originNextSibling'), 'A failed move must restore the original card position.');
stage_move_expect(str_contains($board, 'stageMoveInFlight = true;'), 'Concurrent stage moves must be blocked.');

echo "Pipeline stage move tests passed.\n";
