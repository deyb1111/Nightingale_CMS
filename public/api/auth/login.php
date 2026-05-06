<?php
declare(strict_types=1);
require __DIR__ . '/../_init.php';

use Nightingale\Auth;
use Nightingale\DB;
use Nightingale\JsonResponse;
use Nightingale\Session;

api_method('POST');
$input = JsonResponse::readJsonInput();

$username = trim((string) ($input['username'] ?? ''));
$password = (string) ($input['password'] ?? '');
if ($username === '' || $password === '') {
    JsonResponse::badRequest('missing_credentials');
}
if (Auth::isRateLimited($username)) {
    JsonResponse::send(429, ['error' => 'too_many_attempts']);
}

$stmt = DB::run(
    "SELECT u.user_id, u.username, u.password_hash, u.role, u.is_active,
            u.totp_enabled, u.totp_secret,
            e.employee_id, e.first_name, e.last_name, e.dept_id,
            d.dept_name
       FROM user_account u
       JOIN employee   e ON e.employee_id = u.employee_id
       JOIN department d ON d.dept_id     = e.dept_id
       WHERE u.username = ?
         AND u.is_active = 1
       LIMIT 1",
    [$username]
);
$user = $stmt->fetch();
if (!$user || !password_verify($password, $user['password_hash'])) {
    Auth::recordAttempt($username, 'user', false);
    JsonResponse::send(401, ['error' => 'invalid_credentials']);
}

$fullName = trim($user['first_name'] . ' ' . $user['last_name']);

// TOTP required for nurses, optional for patients (per Reference §03 #06).
$needsTotp = $user['role'] === 'nurse' || (int) $user['totp_enabled'] === 1;

if ($needsTotp) {
    if (empty($user['totp_secret'])) {
        // Force-enrollment path — handled by /totp-setup.php.
        Auth::recordAttempt($username, 'user', true);
        $token = Auth::issuePendingTotp([
            'kind'     => 'totp_setup',
            'user_id'  => (int) $user['user_id'],
            'role'     => $user['role'],
        ]);
        JsonResponse::ok([
            'stage'         => 'totp_setup_required',
            'pending_token' => $token,
            'username'      => $user['username'],
        ]);
    }
    Auth::recordAttempt($username, 'user', true);
    $token = Auth::issuePendingTotp([
        'kind'    => 'totp_verify',
        'user_id' => (int) $user['user_id'],
        'role'    => $user['role'],
    ]);
    JsonResponse::ok([
        'stage'         => 'totp_required',
        'pending_token' => $token,
        'role'          => $user['role'],
        'label'         => ($user['role'] === 'nurse' ? 'Nurse ' : '') . $fullName,
    ]);
}

// No TOTP — finalise session immediately.
Auth::recordAttempt($username, 'user', true);
DB::run("UPDATE user_account SET last_login = NOW() WHERE user_id = ?", [(int) $user['user_id']]);

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
