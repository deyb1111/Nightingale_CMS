<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

use Nightingale\DB;
use Nightingale\JsonResponse;
use Nightingale\Session;

api_method('GET');
Session::requireRole('nurse','admin','patient');

$mode = $_GET['mode'] ?? 'list';

if ($mode === 'me') {
    Session::requireRole('patient');
    $empId = (int) ($_SESSION['employee_id'] ?? 0);

    $profile = DB::run(
        "SELECT e.employee_id, e.employee_no, e.first_name, e.last_name, e.birthdate,
                e.gender, e.blood_type, e.allergies, e.emergency_contact,
                e.emergency_phone, d.dept_name,
                u.email
           FROM employee e
           JOIN department d ON d.dept_id = e.dept_id
           LEFT JOIN user_account u ON u.employee_id = e.employee_id
           WHERE e.employee_id = ?",
        [$empId]
    )->fetch();

    $stats = DB::run(
        "SELECT
            COUNT(*) AS total_visits,
            SUM(CASE WHEN c.case_type = 'emergency' THEN 1 ELSE 0 END)         AS emergencies,
            (SELECT COUNT(*) FROM referral r
               JOIN consultation c2 ON c2.consultation_id = r.consultation_id
               WHERE c2.employee_id = ?)                                       AS referrals,
            MAX(c.consult_date)                                                AS last_visit
           FROM consultation c
           WHERE c.employee_id = ?",
        [$empId, $empId]
    )->fetch();

    $lastVitals = DB::run(
        "SELECT vs.bp_systolic, vs.bp_diastolic, vs.temperature, vs.weight_kg
           FROM vital_signs vs
           JOIN consultation c ON c.consultation_id = vs.consultation_id
           WHERE c.employee_id = ?
           ORDER BY c.consult_date DESC, c.time_start DESC
           LIMIT 1",
        [$empId]
    )->fetch();

    $history = DB::run(
        "SELECT c.consultation_id, c.consult_date, c.time_start, c.case_type,
                c.diagnosis, c.work_status,
                vs.bp_systolic, vs.bp_diastolic, vs.temperature
           FROM consultation c
           LEFT JOIN vital_signs vs ON vs.consultation_id = c.consultation_id
           WHERE c.employee_id = ?
           ORDER BY c.consult_date DESC, c.time_start DESC
           LIMIT 30",
        [$empId]
    )->fetchAll();

    $queue = DB::run(
        "SELECT q.queue_id, q.queue_number, q.queue_date, q.time_in, q.status
           FROM queue q
           WHERE q.employee_id = ?
           ORDER BY q.queue_date DESC, q.time_in DESC
           LIMIT 1",
        [$empId]
    )->fetch();

    JsonResponse::ok([
        'profile'     => $profile,
        'stats'       => $stats,
        'last_vitals' => $lastVitals,
        'history'     => $history,
        'today_queue' => $queue ?: null,
    ]);
}

// list / search — nurse + admin only
Session::requireRole('nurse','admin');
$query = trim((string) ($_GET['search'] ?? ''));
$params = [];
$where  = '';
if ($query !== '') {
    $where = " WHERE e.employee_no LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?";
    $like  = "%$query%";
    $params = [$like, $like, $like];
}

$rows = DB::run(
    "SELECT e.employee_id, e.employee_no, e.first_name, e.last_name,
            e.birthdate, e.gender, e.blood_type, d.dept_name,
            u.role
       FROM employee e
       JOIN department d ON d.dept_id = e.dept_id
       LEFT JOIN user_account u ON u.employee_id = e.employee_id
       $where
       ORDER BY e.last_name, e.first_name
       LIMIT 100",
    $params
)->fetchAll();

JsonResponse::ok(['patients' => $rows]);
