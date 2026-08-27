<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../app/partials/dashboard_pipeline.php');

if (!is_string($source)) {
    fwrite(STDERR, "Could not read the lead communication scheduling source.\n");
    exit(1);
}

function lead_communication_scheduling_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

lead_communication_scheduling_expect(
    str_contains($source, 'id="modal-communication-consultation-date-picker"'),
    'Communication scheduling must expose a dedicated appointment date control.'
);
lead_communication_scheduling_expect(
    str_contains($source, 'id="modal-communication-consultation-time-input"'),
    'Communication scheduling must expose a constrained appointment time list.'
);
lead_communication_scheduling_expect(
    str_contains($source, '$slotMinutes = 9 * 60; $slotMinutes <= 18 * 60; $slotMinutes += 30'),
    'Communication appointment times must run from 9:00 AM through 6:00 PM in 30-minute intervals.'
);
lead_communication_scheduling_expect(
    str_contains($source, 'function communicationConsultationSlotIsAllowed(timeValue)'),
    'Communication scheduling must validate the approved appointment slots before synchronizing the real field.'
);
lead_communication_scheduling_expect(
    str_contains($source, 'modalLeadConsultationDateInput.value = combinedValue;'),
    'Communication scheduling must synchronize to the existing appointment field used by the save flow.'
);
lead_communication_scheduling_expect(
    str_contains($source, 'id="modal-communication-dob-input"'),
    'Communication scheduling must include date of birth without requiring a tab change.'
);
lead_communication_scheduling_expect(
    str_contains($source, 'id="modal-communication-age-summary"'),
    'Communication scheduling must show the calculated age beside DOB.'
);
lead_communication_scheduling_expect(
    str_contains($source, 'function calculateAgeFromDob(value, today = new Date())'),
    'The lead workspace must calculate age from DOB using the current date.'
);
lead_communication_scheduling_expect(
    str_contains($source, 'modalLeadDobInput.value = dateValue;'),
    'Communication DOB must synchronize to the real lead DOB field before saving.'
);

echo "Lead communication scheduling tests passed.\n";
