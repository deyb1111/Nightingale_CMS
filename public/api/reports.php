<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

use Nightingale\DB;
use Nightingale\JsonResponse;
use Nightingale\Session;

api_method('GET');
Session::requireRole('admin','nurse');

$report = $_GET['report'] ?? 'kpis';
$month  = $_GET['month'] ?? date('Y-m');     // 'YYYY-MM'
$year   = (int) ($_GET['year']  ?? (int) date('Y'));
$today  = date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$monthStart = "$month-01";
$monthEnd   = date('Y-m-t', strtotime($monthStart));

switch ($report) {
    case 'kpis': {
        $totalEmployees = (int) DB::run("SELECT COUNT(*) FROM employee WHERE dept_id IS NOT NULL")->fetchColumn();

        $todayConsults = (int) DB::run(
            "SELECT COUNT(*) FROM consultation WHERE consult_date = ?", [$today]
        )->fetchColumn();

        $monthConsults = (int) DB::run(
            "SELECT COUNT(*) FROM consultation
               WHERE consult_date BETWEEN ? AND ?",
            [$monthStart, $monthEnd]
        )->fetchColumn();

        $monthReferrals = (int) DB::run(
            "SELECT COUNT(*) FROM referral WHERE referral_date BETWEEN ? AND ?",
            [$monthStart, $monthEnd]
        )->fetchColumn();

        $monthDispenses = (int) DB::run(
            "SELECT COUNT(*) FROM medicine_dispensed
               WHERE DATE(dispensed_at) BETWEEN ? AND ?",
            [$monthStart, $monthEnd]
        )->fetchColumn();

        $apeYear = $year;
        $apeDone = (int) DB::run(
            "SELECT COUNT(DISTINCT employee_id) FROM ape_record
               WHERE exam_year = ? AND status IN ('completed','cleared')",
            [$apeYear]
        )->fetchColumn();
        $apePct = $totalEmployees > 0
            ? round($apeDone * 100 / $totalEmployees) : 0;

        $today_emergencies = (int) DB::run(
            "SELECT COUNT(*) FROM consultation
               WHERE consult_date = ? AND case_type = 'emergency'",
            [$today]
        )->fetchColumn();

        JsonResponse::ok([
            'today'      => [
                'date'             => $today,
                'consultations'    => $todayConsults,
                'emergencies'      => $today_emergencies,
            ],
            'month'      => [
                'period'        => $month,
                'consultations' => $monthConsults,
                'referrals'     => $monthReferrals,
                'dispenses'     => $monthDispenses,
            ],
            'ape'        => [
                'year'      => $apeYear,
                'completed' => $apeDone,
                'total'     => $totalEmployees,
                'percent'   => $apePct,
            ],
            'employees'  => $totalEmployees,
        ]);
    }

    case 'illness': {
        $rows = DB::run(
            "SELECT diagnosis, COUNT(*) AS visits
               FROM consultation
               WHERE consult_date BETWEEN ? AND ?
                 AND case_type = 'illness'
                 AND diagnosis IS NOT NULL
                 AND diagnosis <> ''
               GROUP BY diagnosis
               ORDER BY visits DESC
               LIMIT 6",
            [$monthStart, $monthEnd]
        )->fetchAll();
        JsonResponse::ok(['period' => $month, 'top_illnesses' => $rows]);
    }

    case 'by-department': {
        $rows = DB::run(
            "SELECT d.dept_id, d.dept_name,
                    COUNT(c.consultation_id) AS visits,
                    SUM(CASE WHEN r.referral_id IS NOT NULL THEN 1 ELSE 0 END) AS referrals
               FROM department d
               LEFT JOIN employee e ON e.dept_id = d.dept_id
               LEFT JOIN consultation c ON c.employee_id = e.employee_id
                                       AND c.consult_date BETWEEN ? AND ?
               LEFT JOIN referral r ON r.consultation_id = c.consultation_id
               GROUP BY d.dept_id, d.dept_name
               ORDER BY visits DESC",
            [$monthStart, $monthEnd]
        )->fetchAll();
        JsonResponse::ok(['period' => $month, 'departments' => $rows]);
    }

    case 'inventory': {
        $rows = DB::run(
            "SELECT medicine_id, medicine_name, dosage_strength, unit, category,
                    min_stock, current_stock, expiry_date,
                    DATEDIFF(expiry_date, CURDATE()) AS days_to_expiry,
                    CASE
                      WHEN current_stock = 0                                         THEN 'out'
                      WHEN current_stock <= min_stock * 0.25                         THEN 'critical'
                      WHEN current_stock <  min_stock                                THEN 'low'
                      WHEN expiry_date    <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)    THEN 'near_expiry'
                      ELSE 'good'
                    END AS stock_status
               FROM medicine
               ORDER BY FIELD(stock_status,'critical','low','near_expiry','good'), medicine_name"
        )->fetchAll();
        JsonResponse::ok(['inventory' => $rows]);
    }

    case 'referrals': {
        $rows = DB::run(
            "SELECT r.referral_id, r.referral_type, r.referred_to, r.reason,
                    r.referral_date, r.status,
                    c.consultation_id, c.diagnosis,
                    e.first_name, e.last_name, d.dept_name
               FROM referral r
               JOIN consultation c ON c.consultation_id = r.consultation_id
               JOIN employee e     ON e.employee_id     = c.employee_id
               JOIN department d   ON d.dept_id         = e.dept_id
               WHERE r.referral_date BETWEEN ? AND ?
               ORDER BY r.referral_date DESC, r.referral_id DESC
               LIMIT 30",
            [$monthStart, $monthEnd]
        )->fetchAll();
        JsonResponse::ok(['period' => $month, 'referrals' => $rows]);
    }

    case 'monthly-trend': {
        // last 12 months
        $start = date('Y-m-01', strtotime('-11 months', strtotime($today)));
        $rows = DB::run(
            "SELECT DATE_FORMAT(consult_date, '%Y-%m') AS period,
                    COUNT(*)                              AS visits,
                    SUM(CASE WHEN case_type = 'emergency' THEN 1 ELSE 0 END) AS emergencies
               FROM consultation
               WHERE consult_date >= ?
               GROUP BY period
               ORDER BY period",
            [$start]
        )->fetchAll();
        JsonResponse::ok(['series' => $rows]);
    }

    default:
        JsonResponse::badRequest('unknown_report');
}
