<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

use Nightingale\CSRF;
use Nightingale\DB;
use Nightingale\JsonResponse;
use Nightingale\Session;

api_method('POST');
Session::requireRole('admin');
CSRF::require();

$input = JsonResponse::readJsonInput();
$mid     = (int) ($input['medicine_id'] ?? 0);
$qty     = (int) ($input['quantity'] ?? 0);
$remarks = (string) ($input['remarks'] ?? '');

if ($mid <= 0 || $qty <= 0) JsonResponse::badRequest('missing_required_fields');

// admin restock — actioned_by uses admin user_id mapping; fall back to user 1.
$actionedBy = (int) ($_SESSION['user_id'] ?? 1);

$stmt = DB::pdo()->prepare("CALL sp_restock_medicine(?, ?, ?, ?)");
$stmt->execute([$mid, $actionedBy, $qty, $remarks]);
$stmt->closeCursor();

JsonResponse::ok(['stage' => 'restocked']);
