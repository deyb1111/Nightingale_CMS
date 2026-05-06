<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

use Nightingale\CSRF;
use Nightingale\DB;
use Nightingale\JsonResponse;
use Nightingale\Mailer;
use Nightingale\Session;

api_method('POST');
Session::requireRole('nurse');
CSRF::require();

$input = JsonResponse::readJsonInput();
$cid   = (int) ($input['consultation_id'] ?? 0);
$mid   = (int) ($input['medicine_id'] ?? 0);
$qty   = (int) ($input['quantity'] ?? 0);
$instr = (string) ($input['dosage_instructions'] ?? '');

if ($cid <= 0 || $mid <= 0 || $qty <= 0) {
    JsonResponse::badRequest('missing_required_fields');
}

$nurseRow = DB::run("SELECT nurse_id FROM nurse_profile WHERE user_id = ?",
                   [(int) $_SESSION['user_id']])->fetch();
if (!$nurseRow) JsonResponse::send(403, ['error' => 'no_nurse_profile']);

try {
    $stmt = DB::pdo()->prepare(
        "CALL sp_dispense_medicine(?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $cid,
        $mid,
        (int) $nurseRow['nurse_id'],
        (int) $_SESSION['user_id'],
        $qty,
        $instr,
    ]);
    $stmt->closeCursor();
} catch (\PDOException $e) {
    if (str_contains($e->getMessage(), 'Insufficient stock')) {
        JsonResponse::send(409, ['error' => 'insufficient_stock', 'detail' => $e->getMessage()]);
    }
    throw $e;
}

// Low-stock alert (post-decrement check)
$stockRow = DB::run(
    "SELECT m.medicine_id, m.medicine_name, m.current_stock, m.min_stock
       FROM medicine m WHERE m.medicine_id = ?",
    [$mid]
)->fetch();
if ($stockRow && (int) $stockRow['current_stock'] <= (int) $stockRow['min_stock']) {
    $admin = $_ENV['ADMIN_NOTIFICATION_EMAIL'] ?? null;
    if ($admin) {
        $body = "<p><strong>Low-stock alert</strong></p>";
        $body .= "<p>{$stockRow['medicine_name']} stock has reached {$stockRow['current_stock']} ";
        $body .= "(min {$stockRow['min_stock']}).  Please initiate a restock.</p>";
        Mailer::send($admin, 'Nightingale: Low-stock alert', $body);
    }
}

JsonResponse::ok(['stage' => 'dispensed']);
