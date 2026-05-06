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
if (($payload['kind'] ?? '') !== 'totp_verify') {
    JsonResponse::badRequest('wrong_pending_kind');
}

$userId = (int) ($payload['user_id'] ?? 0);
$stmt = DB::run(
    "SELECT u.user_id, u.username, u.role, u.totp_secret,
            e.employee_id, e.first_name, e.last_name, e.dept_id,
            d.dept_name
       FROM user_account u
       JOIN employee   e ON e.employee_id = u.employee_id
       JOIN department d ON d.dept_id     = e.dept_id
       WHERE u.user_id = ? AND u.is_active = 1
       LIMIT 1",
    [$userId]
);
$user = $stmt->fetch();
if (!$user) JsonResponse::send(401, ['error' => 'user_not_found']);

$ok = !empty($user['totp_secret']) && TOTP::verify($user['totp_secret'], $code);

// Backup-code fallback
if (!$ok && preg_match('/^[A-Z0-9\-]{8,}$/i', $code)) {
    $bcStmt = DB::run(
        "SELECT code_id, code_hash FROM totp_backup_codes
           WHERE user_id = ? AND is_used = 0",
        [$userId]
    );
    foreach ($bcStmt->fetchAll() as $row) {
        if (TOTP::verifyBackupCode($code, $row['code_hash'])) {
            DB::run(
                "UPDATE totp_backup_codes
                   SET is_used = 1, used_at = NOW()
                   WHERE code_id = ?",
                [(int) $row['code_id']]
            );
            $ok = true;
            break;
        }
    }
}

if (!$ok) {
    Auth::recordAttempt($user['username'], 'user', false);
    JsonResponse::send(401, ['error' => 'invalid_totp']);
}

Auth::recordAttempt($user['username'], 'user', true);
DB::run("UPDATE user_account SET last_login = NOW() WHERE user_id = ?", [$userId]);

$fullName = trim($user['first_name'] . ' ' . $user['last_name']);
Session::login([
    'role'        => $user['role'],
    'user_id'     => (int) $user['user_id'],
    'admin_id'    => null,
    'employee_id' => (int) $user['employee_id'],
    'username'    => $user['username'],
    'label'       => ($user['role'] === 'nurse' ? 'Nurse ' : '') . $fullName,
    'initials'    => Auth::initials($fullName),
    'dept_name'   => $user['dept_name'],
]);

JsonResponse::ok([
    'stage' => 'authenticated',
    'role'  => $user['role'],
]);
