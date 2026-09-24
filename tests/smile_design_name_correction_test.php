<?php
declare(strict_types=1);

// In-memory database substitute: verifies both records change together.
$leadRecord = ['id' => 7, 'full_name' => 'Jhon Smith', 'first_name' => 'Jhon', 'last_name' => 'Smith', 'email' => '', 'phone' => '', 'procedure_interest' => 'Veneers'];
$caseRecord = ['id' => 12, 'lead_id' => 7, 'patient_name' => 'Jhon Smith', 'first_name' => 'Jhon', 'last_name' => 'Smith', 'email' => '', 'phone' => '', 'procedure_interest' => 'Veneers'];
$auditRecords = [];
$snapshot = null;
$failCaseWrite = false;

function db_one(string $sql, array $params = []): ?array {
    return str_contains($sql, 'FROM smile_cases') ? $GLOBALS['caseRecord'] : (str_contains($sql, 'FROM leads') ? $GLOBALS['leadRecord'] : null);
}
function db_execute(string $sql, array $params = []): int {
    if (str_contains($sql, 'UPDATE smile_cases') && $GLOBALS['failCaseWrite']) throw new RuntimeException('Simulated case write failure');
    $record = str_contains($sql, 'UPDATE leads') ? 'leadRecord' : 'caseRecord';
    foreach (['full_name', 'patient_name', 'first_name', 'last_name', 'email', 'phone'] as $field) {
        if (array_key_exists($field, $params)) $GLOBALS[$record][$field] = $params[$field];
    }
    return 1;
}
function db_insert(string $sql, array $params = []): int { $GLOBALS['auditRecords'][] = $params; return 1; }
function db_begin(): bool { $GLOBALS['snapshot'] = [$GLOBALS['leadRecord'], $GLOBALS['caseRecord'], $GLOBALS['auditRecords']]; return true; }
function db_commit(): bool { $GLOBALS['snapshot'] = null; return true; }
function db_rollBack(): bool {
    [$GLOBALS['leadRecord'], $GLOBALS['caseRecord'], $GLOBALS['auditRecords']] = $GLOBALS['snapshot'];
    $GLOBALS['snapshot'] = null;
    return true;
}

require_once dirname(__DIR__) . '/app/smile_design/smile_design_service.php';
function name_expect(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }

$saved = smile_design_update_case_contact(12, ['first_name' => 'John', 'last_name' => 'Smith', 'patient_name' => 'John Smith'], 3);
name_expect($saved && $GLOBALS['leadRecord']['full_name'] === 'John Smith' && $GLOBALS['caseRecord']['patient_name'] === 'John Smith', 'Correct both linked names');
name_expect(smile_design_case(12)['patient_name'] === 'John Smith', 'Linked lead cannot restore typo on reload');
name_expect(count($GLOBALS['auditRecords']) === 1 && $GLOBALS['auditRecords'][0]['event_key'] === 'case_contact_updated', 'Audit correction');

// Existing Case details form also works when only a structured name field is edited.
smile_design_update_case_contact(12, ['first_name' => 'Jane', 'last_name' => 'Smith', 'patient_name' => 'John Smith'], 3);
name_expect($GLOBALS['leadRecord']['full_name'] === 'Jane Smith' && $GLOBALS['caseRecord']['patient_name'] === 'Jane Smith', 'Structured field change updates display and lead');

$GLOBALS['failCaseWrite'] = true;
try {
    smile_design_update_case_contact(12, ['first_name' => 'Janet', 'last_name' => 'Smith', 'patient_name' => 'Janet Smith'], 3);
    throw new RuntimeException('Expected simulated failure');
} catch (RuntimeException $e) {
    name_expect($e->getMessage() === 'Simulated case write failure', 'Original failure propagated');
}
name_expect($GLOBALS['leadRecord']['full_name'] === 'Jane Smith' && $GLOBALS['caseRecord']['patient_name'] === 'Jane Smith', 'Partial rename rolled back');
echo "Smile Design name correction, linked lead sync and rollback tests passed.\n";
