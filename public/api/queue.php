<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

use Nightingale\CSRF;
use Nightingale\DB;
use Nightingale\JsonResponse;
use Nightingale\Session;

$method = api_method('GET', 'POST', 'PATCH');

if ($method === 'GET') {
    Session::requireRole('nurse', 'admin', 'patient');
    $date = $_GET['date'] ?? date('Y-m-d');

    if (Session::role() === 'patient') {
        $empId = (int) ($_SESSION['employee_id'] ?? 0);
        $rows = DB::run(
            "SELECT q.queue_id, q.queue_number, q.queue_date, q.time_in, q.status,
                    c.consultation_id, c.case_type, c.diagnosis, c.work_status
               FROM queue q
               LEFT JOIN consultation c ON c.queue_id = q.queue_id
               WHERE q.employee_id = ?
               ORDER BY q.queue_date DESC, q.time_in DESC
               LIMIT 20",
            [$empId]
        )->fetchAll();
        JsonResponse::ok(['queue' => $rows]);
    }

    $rows = DB::run(
        "SELECT q.queue_id, q.queue_number, q.queue_date, q.time_in, q.status,
                e.employee_id, e.employee_no, e.first_name, e.last_name,
                d.dept_name,
                c.consultation_id
           FROM queue q
           JOIN employee   e ON e.employee_id = q.employee_id
           JOIN department d ON d.dept_id     = e.dept_id
           LEFT JOIN consultation c ON c.queue_id = q.queue_id
           WHERE q.queue_date = ?
           ORDER BY q.queue_number ASC",
        [$date]
    )->fetchAll();
    JsonResponse::ok(['date' => $date, 'queue' => $rows]);
}

if ($method === 'POST') {
    Session::requireRole('nurse');
    CSRF::require();
    $input = JsonResponse::readJsonInput();
    $empId   = (int) ($input['employee_id'] ?? 0);
    $qNumber = (int) ($input['queue_number'] ?? 0);
    if ($empId === 0)  JsonResponse::badRequest('missing_employee_id');
    if ($qNumber <= 0) JsonResponse::badRequest('missing_queue_number');

    DB::run(
        "INSERT INTO queue (employee_id, queue_number, queue_date, time_in, status)
         VALUES (?, ?, CURDATE(), CURTIME(), 'waiting')",
        [$empId, $qNumber]
    );
    $queueId = (int) DB::pdo()->lastInsertId();
    JsonResponse::ok(['stage' => 'created', 'queue_id' => $queueId]);
}

if ($method === 'PATCH') {
    Session::requireRole('nurse');
    CSRF::require();
    $input = JsonResponse::readJsonInput();
    $queueId = (int) ($input['queue_id'] ?? 0);
    $status  = (string) ($input['status'] ?? '');
    if (!in_array($status, ['waiting','in_progress','done','cancelled'], true)) {
        JsonResponse::badRequest('invalid_status');
    }
    DB::run("UPDATE queue SET status = ? WHERE queue_id = ?", [$status, $queueId]);
    JsonResponse::ok(['stage' => 'updated']);
}
