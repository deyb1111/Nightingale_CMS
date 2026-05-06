<?php
declare(strict_types=1);
require __DIR__ . '/../_init.php';

use Nightingale\Auth;
use Nightingale\DB;
use Nightingale\JsonResponse;
use Nightingale\Session;
use Nightingale\TOTP;

api_method('POST');
$input = JsonResponse::readJsonInput();
$token = (string) ($input['pending_token'] ?? '');
$code  = (string) ($input['code'] ?? '');

$payload = Auth::consumePendingTotp($token);
if (($payload['kind'] ?? '') !== 'admin_totp_verify') {
    JsonResponse::badRequest('wrong_pending_kind');
}
$adminId = (int) ($payload['admin_id'] ?? 0);

$stmt = DB::run(
    "SELECT admin_id, username, full_name, totp_secret
       FROM admin_account WHERE admin_id = ? AND is_active = 1",
    [$adminId]
);
$admin = $stmt->fetch();
if (!$admin) JsonResponse::send(401, ['error' => 'admin_not_found']);

$ok = !empty($admin['totp_secret']) && TOTP::verify($admin['totp_secret'], $code);

if (!$ok && preg_match('/^[A-Z0-9\-]{8,}$/i', $code)) {
    $bcStmt = DB::run(
        "SELECT code_id, code_hash FROM totp_backup_codes
           WHERE admin_id = ? AND is_used = 0",
        [$adminId]
    );
    foreach ($bcStmt->fetchAll() as $row) {
        if (TOTP::verifyBackupCode($code, $row['code_hash'])) {
            DB::run("UPDATE totp_backup_codes SET is_used=1, used_at=NOW() WHERE code_id = ?",
                    [(int) $row['code_id']]);
            $ok = true;
            break;
        }
    }
}
if (!$ok) {
    Auth::recordAttempt($admin['username'], 'admin', false);
    JsonResponse::send(401, ['error' => 'invalid_totp']);
}

Auth::recordAttempt($admin['username'], 'admin', true);
DB::run("UPDATE admin_account SET last_login = NOW() WHERE admin_id = ?", [$adminId]);

Session::login([
    'role'      => 'admin',
    'user_id'   => null,
    'admin_id'  => $adminId,
    'username'  => $admin['username'],
    'label'     => $admin['full_name'],
    'initials'  => Auth::initials($admin['full_name']),
]);

JsonResponse::ok(['stage' => 'authenticated', 'role' => 'admin']);
