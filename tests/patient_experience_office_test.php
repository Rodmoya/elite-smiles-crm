<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/patient_experience/patient_experience_service.php';
function office_expect(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
patient_experience_ensure_schema();
db_begin();
try {
    $suffix = bin2hex(random_bytes(5));
    $staff = db_insert("INSERT INTO users (first_name, email, role) VALUES ('QA', :email, 'staff')", ['email' => 'staff-' . $suffix . '@example.invalid']);
    $admin = db_insert("INSERT INTO users (first_name, email, role) VALUES ('QA', :email, 'admin')", ['email' => 'admin-' . $suffix . '@example.invalid']);
    $session = patient_experience_start_placeholder_session(null, 'Office QA', $staff);
    office_expect(patient_experience_begin_session($session['token'])['ok'], 'Start actual patient information');
    office_expect(!patient_experience_archive_session($session['id'], 0)['ok'], 'No anonymous archival');
    office_expect(patient_experience_archive_session($session['id'], $staff)['ok'], 'Staff can trash unsigned packet');
    office_expect(patient_experience_session_by_kiosk_token($session['token']) === null, 'Archived token invalidated');
    office_expect(!patient_experience_resume_session($session['id'], $staff)['ok'], 'No opening an archived packet');
    office_expect(!in_array($session['id'], array_column(patient_experience_recent_sessions(100), 'id')), 'Trash excluded from active list');
    office_expect(in_array($session['id'], array_column(patient_experience_recent_sessions(100, true), 'id')), 'Trash visible in archived list');
    office_expect(patient_experience_archive_session($session['id'], $staff, true)['ok'], 'Restore unsigned packet');
    office_expect(patient_experience_resume_session($session['id'], $staff)['ok'], 'Restored packet can resume');
    db_insert("INSERT INTO patient_experience_signatures (checkin_session_id, signer_name) VALUES (:id, 'QA')", ['id' => $session['id']]);
    office_expect(!patient_experience_archive_session($session['id'], $staff)['ok'], 'Staff cannot archive signed packet');
    office_expect(patient_experience_archive_session($session['id'], $admin)['ok'], 'Admin can archive signed packet');
    office_expect((int)db_value('SELECT COUNT(*) FROM patient_experience_signatures WHERE checkin_session_id=:id', ['id'=>$session['id']]) === 1, 'Signature preserved');
    office_expect(patient_experience_archive_session($session['id'], $admin, true)['ok'], 'Admin can restore signed packet');
    $resumed = patient_experience_resume_session($session['id'], $admin);
    office_expect(!patient_experience_can_print_completed($session['id'], $resumed['token']), 'Unfinished packet cannot use completion print');
    db_execute("UPDATE patient_experience_checkin_sessions SET status='completed', completed_at=NOW() WHERE id=:id", ['id'=>$session['id']]);
    office_expect(patient_experience_can_print_completed($session['id'], $resumed['token']), 'Completed packet allows its own print token');
    office_expect(!patient_experience_can_print_completed($session['id'], 'bad-token'), 'Wrong print token denied');
    office_expect(!patient_experience_can_print_completed(0, $resumed['token']), 'Other packet denied');
    db_execute("UPDATE patient_experience_checkin_sessions SET completed_at=DATE_SUB(NOW(), INTERVAL 31 MINUTE) WHERE id=:id", ['id'=>$session['id']]);
    office_expect(!patient_experience_can_print_completed($session['id'], $resumed['token']), 'Expired print token denied');
} finally { db_rollBack(); }
echo "Office patient packet archive tests passed.\n";
