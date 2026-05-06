<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

use Nightingale\CSRF;
use Nightingale\DB;
use Nightingale\JsonResponse;
use Nightingale\Session;

$method = api_method('GET','POST');

if ($method === 'GET') {
    Session::requireRole('admin','nurse','patient');
    $year = (int) ($_GET['year'] ?? (int) date('Y'));

    if (Session::role() === 'patient') {
        $rows = DB::run(
            "SELECT exam_year, exam_date, bp_systolic, bp_diastolic,
                    weight_kg, height_cm, bmi, status, remarks
               FROM ape_record
               WHERE employee_id = ?
               ORDER BY exam_year DESC",
            [(int) ($_SESSION['employee_id'] ?? 0)]
        )->fetchAll();
        JsonResponse::ok(['records' => $rows]);
    }

    $byDept = DB::run(
        "SELECT d.dept_id, d.dept_name,
                COUNT(DISTINCT e.employee_id) AS total_employees,
                COUNT(DISTINCT CASE WHEN ar.status IN ('completed','cleared')
                              THEN ar.employee_id END) AS done,
                COUNT(DISTINCT CASE WHEN ar.status IN ('pending','flagged') OR ar.ape_id IS NULL
                              THEN e.employee_id END) AS pending
           FROM department d
           LEFT JOIN employee e ON e.dept_id = d.dept_id
           LEFT JOIN ape_record ar ON ar.employee_id = e.employee_id AND ar.exam_year = ?
           GROUP BY d.dept_id, d.dept_name
           ORDER BY d.dept_name",
        [$year]
    )->fetchAll();
    foreach ($byDept as &$r) {
        $total = (int) $r['total_employees'];
        $done  = (int) $r['done'];
        $r['rate'] = $total > 0 ? round($done * 100.0 / $total) : 0;
    }
    unset($r);

    $totals = [
        'total_employees' => (int) DB::run("SELECT COUNT(*) FROM employee WHERE dept_id IS NOT NULL")->fetchColumn(),
        'completed'       => (int) DB::run(
            "SELECT COUNT(DISTINCT employee_id)
               FROM ape_record
               WHERE exam_year = ? AND status IN ('completed','cleared')",
            [$year]
        )->fetchColumn(),
    ];
    $totals['percent'] = $totals['total_employees'] > 0
        ? round($totals['completed'] * 100 / $totals['total_employees'])
        : 0;

    JsonResponse::ok(['year' => $year, 'totals' => $totals, 'by_department' => $byDept]);
}

// POST — create APE
Session::requireRole('nurse');
CSRF::require();
$input = JsonResponse::readJsonInput();
$empId  = (int) ($input['employee_id'] ?? 0);
$year   = (int) ($input['exam_year'] ?? (int) date('Y'));
$bpS    = isset($input['bp_systolic'])  ? (int) $input['bp_systolic']  : null;
$bpD    = isset($input['bp_diastolic']) ? (int) $input['bp_diastolic'] : null;
$weight = isset($input['weight_kg'])    ? (float) $input['weight_kg']  : null;
$height = isset($input['height_cm'])    ? (float) $input['height_cm']  : null;
$status = (string) ($input['status'] ?? 'completed');
$rem    = (string) ($input['remarks'] ?? '');

if ($empId === 0) JsonResponse::badRequest('missing_employee_id');

$bmi = ($weight && $height) ? round($weight / (($height / 100) ** 2), 2) : null;

$nurseRow = DB::run("SELECT nurse_id FROM nurse_profile WHERE user_id = ?",
                    [(int) $_SESSION['user_id']])->fetch();
$nurseId = $nurseRow ? (int) $nurseRow['nurse_id'] : 1;

DB::run(
    "INSERT INTO ape_record
       (employee_id, nurse_id, exam_year, exam_date, bp_systolic, bp_diastolic,
        weight_kg, height_cm, bmi, status, remarks)
     VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       exam_date    = VALUES(exam_date),
       bp_systolic  = VALUES(bp_systolic),
       bp_diastolic = VALUES(bp_diastolic),
       weight_kg    = VALUES(weight_kg),
       height_cm    = VALUES(height_cm),
       bmi          = VALUES(bmi),
       status       = VALUES(status),
       remarks      = VALUES(remarks)",
    [$empId, $nurseId, $year, $bpS, $bpD, $weight, $height, $bmi, $status, $rem]
);

JsonResponse::ok(['stage' => 'saved']);
