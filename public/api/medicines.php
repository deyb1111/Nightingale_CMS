<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

use Nightingale\DB;
use Nightingale\JsonResponse;
use Nightingale\Session;

api_method('GET');
Session::requireRole('nurse', 'admin', 'patient');

$rows = DB::run(
    "SELECT medicine_id, medicine_name, generic_name, dosage_strength, unit,
            category, min_stock, current_stock, expiry_date,
            CASE
              WHEN current_stock = 0                                         THEN 'out'
              WHEN current_stock <= min_stock * 0.25                         THEN 'critical'
              WHEN current_stock <  min_stock                                THEN 'low'
              WHEN expiry_date    <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)    THEN 'near_expiry'
              ELSE 'good'
            END AS stock_status
       FROM medicine
       ORDER BY stock_status DESC, medicine_name"
)->fetchAll();

JsonResponse::ok(['medicines' => $rows]);
