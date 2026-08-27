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
    str_contains($source, 'id="modal-communication-scheduling-content" class="mt-4 space-y-4"'),
    'Communication scheduling must use one compact vertical layout in the narrow lead summary column.'
);
lead_communication_scheduling_expect(
    str_contains($source, 'class="mt-3 grid grid-cols-1 gap-3"'),
    'Appointment date and time must remain full width instead of being squeezed into narrow side-by-side controls.'
);
lead_communication_scheduling_expect(
    str_contains($source, 'id="modal-communication-dob-section" class="border-t border-slate-200 pt-4"'),
    'DOB and age must be integrated into the Scheduling card with a compact divider.'
);
lead_communication_scheduling_expect(
    str_contains($source, '9:00 AM–6:00 PM · 30-minute slots'),
    'The scheduling availability note must be concise for the narrow Communication column.'
);
lead_communication_scheduling_expect(
    str_contains($source, 'function calculateAgeFromDob(value, today = new Date())'),
    'The lead workspace must calculate age from DOB using the current date.'
);
lead_communication_scheduling_expect(
    str_contains($source, 'modalLeadDobInput.value = dateValue;'),
    'Communication DOB must synchronize to the real lead DOB field before saving.'
);
lead_communication_scheduling_expect(
    str_contains($source, "leadSelectedSummaryPanel.style.gridRow = '1 / 3';")
        && str_contains($source, "leadSelectedSummaryPanel.style.overflowY = 'auto';"),
    'Scheduling details must remain usable across both desktop communication grid rows.'
);
lead_communication_scheduling_expect(
    str_contains($source, "leadEmailHistoryPanel.style.gridColumn = '3 / 4';")
        && str_contains($source, "leadEmailHistoryPanel.style.gridRow = '2 / 3';"),
    'Email history must remain in the right column instead of covering scheduling details.'
);

echo "Lead communication scheduling tests passed.\n";
