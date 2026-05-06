<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

use Nightingale\DB;
use Nightingale\JsonResponse;
use Nightingale\Session;

api_method('GET');
Session::requireRole('admin');

$day  = $_GET['day']  ?? date('Y-m-d');
$tableFilter = $_GET['table'] ?? null;
$limit = max(1, min(500, (int) ($_GET['limit'] ?? 100)));

$params = [$day];
$where  = "WHERE DATE(a.action_timestamp) = ?";
if ($tableFilter) {
    $where .= " AND a.table_affected = ?";
    $params[] = $tableFilter;
}

$rows = DB::run(
    "SELECT a.audit_id, a.action_timestamp, a.table_affected, a.action_type,
            a.record_id, a.old_value, a.new_value, a.user_id, a.admin_id,
            COALESCE(au.username, ad.username) AS actor_username,
            CONCAT(IFNULL(e.first_name,''),' ',IFNULL(e.last_name,'')) AS actor_user_name,
            ad.full_name AS actor_admin_name
       FROM audit_log a
       LEFT JOIN user_account  au ON au.user_id  = a.user_id
       LEFT JOIN admin_account ad ON ad.admin_id = a.admin_id
       LEFT JOIN employee      e  ON e.employee_id = au.employee_id
       $where
       ORDER BY a.action_timestamp DESC
       LIMIT $limit",
    $params
)->fetchAll();

JsonResponse::ok(['day' => $day, 'entries' => $rows]);
