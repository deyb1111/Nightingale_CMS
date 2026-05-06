<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

use Nightingale\CSRF;
use Nightingale\DB;
use Nightingale\JsonResponse;
use Nightingale\Session;

$method = api_method('GET', 'POST');

if ($method === 'GET') {
    Session::requireRole('nurse', 'admin', 'patient');
    $consultId = (int) ($_GET['id'] ?? 0);
    if ($consultId <= 0) JsonResponse::badRequest('missing_id');

    $row = DB::run(
        "SELECT c.*, e.employee_no, e.first_name, e.last_name, d.dept_name,
                vs.bp_systolic, vs.bp_diastolic, vs.temperature, vs.pulse_rate,
                vs.resp_rate, vs.o2_saturation, vs.weight_kg
           FROM consultation c
           JOIN employee   e ON e.employee_id = c.employee_id
           JOIN department d ON d.dept_id     = e.dept_id
           LEFT JOIN vital_signs vs ON vs.consultation_id = c.consultation_id
           WHERE c.consultation_id = ?",
        [$consultId]
    )->fetch();
    if (!$row) JsonResponse::send(404, ['error' => 'not_found']);

    if (Session::role() === 'patient'
        && (int) $row['employee_id'] !== (int) ($_SESSION['employee_id'] ?? 0)) {
        JsonResponse::forbidden();
    }

    $row['dispenses'] = DB::run(
        "SELECT md.dispense_id, md.quantity, md.dosage_instructions, md.dispensed_at,
                m.medicine_name, m.dosage_strength, m.unit
           FROM medicine_dispensed md
           JOIN medicine m ON m.medicine_id = md.medicine_id
           WHERE md.consultation_id = ?
           ORDER BY md.dispensed_at",
        [$consultId]
    )->fetchAll();

    $row['referrals'] = DB::run(
        "SELECT * FROM referral WHERE consultation_id = ? ORDER BY referral_date",
        [$consultId]
    )->fetchAll();

    JsonResponse::ok(['consultation' => $row]);
}

// POST — open OR close
Session::requireRole('nurse');
CSRF::require();
$input  = JsonResponse::readJsonInput();
$action = $input['action'] ?? 'open';

if ($action === 'open') {
    $employeeId    = (int) ($input['employee_id'] ?? 0);
    $queueNumber   = (int) ($input['queue_number'] ?? 0);
    $chief         = trim((string) ($input['chief_complaint'] ?? ''));
    $caseType      = (string) ($input['case_type'] ?? 'illness');
    if ($employeeId === 0 || $chief === '') {
        JsonResponse::badRequest('missing_required_fields');
    }
    $allowed = ['illness','injury','follow_up','emergency'];
    if (!in_array($caseType, $allowed, true)) JsonResponse::badRequest('invalid_case_type');

    $nurseRow = DB::run(
        "SELECT nurse_id FROM nurse_profile WHERE user_id = ?",
        [(int) $_SESSION['user_id']]
    )->fetch();
    if (!$nurseRow) JsonResponse::send(403, ['error' => 'no_nurse_profile']);

    $stmt = DB::pdo()->prepare("CALL sp_open_consultation(?, ?, ?, ?, ?, @cid)");
    $stmt->execute([$employeeId, (int) $nurseRow['nurse_id'], $queueNumber, $chief, $caseType]);
    $stmt->closeCursor();
    $cid = (int) DB::pdo()->query("SELECT @cid AS cid")->fetchColumn();
    JsonResponse::ok(['stage' => 'opened', 'consultation_id' => $cid]);
}

if ($action === 'close') {
    $cid        = (int) ($input['consultation_id'] ?? 0);
    $workStatus = (string) ($input['work_status'] ?? 'fit');
    $diagnosis  = (string) ($input['diagnosis'] ?? '');
    $notes      = (string) ($input['nurse_notes'] ?? '');
    if ($cid === 0) JsonResponse::badRequest('missing_consultation_id');
    if (!in_array($workStatus, ['fit','light_duty','sick_leave','for_hospitalization'], true)) {
        JsonResponse::badRequest('invalid_work_status');
    }
    $stmt = DB::pdo()->prepare("CALL sp_close_consultation(?, ?, ?, ?)");
    $stmt->execute([$cid, $workStatus, $diagnosis, $notes]);
    $stmt->closeCursor();
    JsonResponse::ok(['stage' => 'closed']);
}

JsonResponse::badRequest('unknown_action');
