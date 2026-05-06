<?php
declare(strict_types=1);
require __DIR__ . '/../_init.php';

use Nightingale\Auth;
use Nightingale\DB;
use Nightingale\JsonResponse;

api_method('POST');
$input = JsonResponse::readJsonInput();

// Optional IP allowlist for the admin portal.
$allow = trim((string) ($_ENV['ADMIN_IP_ALLOWLIST'] ?? ''));
if ($allow !== '') {
    $allowed = array_map('trim', explode(',', $allow));
    if (!in_array(Auth::clientIp(), $allowed, true)) {
        JsonResponse::send(403, ['error' => 'ip_not_allowed']);
    }
}

$username = trim((string) ($input['username'] ?? ''));
$password = (string) ($input['password'] ?? '');
if ($username === '' || $password === '') {
    JsonResponse::badRequest('missing_credentials');
}
if (Auth::isRateLimited($username)) {
    JsonResponse::send(429, ['error' => 'too_many_attempts']);
}

$stmt = DB::run(
    "SELECT admin_id, username, full_name, password_hash, is_active,
            totp_enabled, totp_secret
       FROM admin_account
       WHERE username = ? AND is_active = 1
       LIMIT 1",
    [$username]
);
$admin = $stmt->fetch();
if (!$admin || !password_verify($password, $admin['password_hash'])) {
    Auth::recordAttempt($username, 'admin', false);
    JsonResponse::send(401, ['error' => 'invalid_credentials']);
}

Auth::recordAttempt($username, 'admin', true);

// TOTP is ALWAYS required for admins (Reference §03 #06, §04).
if (empty($admin['totp_secret'])) {
    $token = Auth::issuePendingTotp([
        'kind'     => 'admin_totp_setup',
        'admin_id' => (int) $admin['admin_id'],
    ]);
    JsonResponse::ok([
        'stage'         => 'totp_setup_required',
        'pending_token' => $token,
    ]);
}

$token = Auth::issuePendingTotp([
    'kind'     => 'admin_totp_verify',
    'admin_id' => (int) $admin['admin_id'],
]);
JsonResponse::ok([
    'stage'         => 'totp_required',
    'pending_token' => $token,
    'label'         => $admin['full_name'],
]);
