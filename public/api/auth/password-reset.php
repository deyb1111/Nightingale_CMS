<?php
declare(strict_types=1);
require __DIR__ . '/../_init.php';

use Nightingale\DB;
use Nightingale\JsonResponse;

api_method('POST');
$input = JsonResponse::readJsonInput();

$portal      = ($input['portal'] ?? 'user') === 'admin' ? 'admin' : 'user';
$token       = (string) ($input['token'] ?? '');
$newPassword = (string) ($input['new_password'] ?? '');

if (strlen($newPassword) < 10) {
    JsonResponse::badRequest('password_too_short', ['min_length' => 10]);
}
$hash = hash('sha256', $token);
$row = DB::run(
    "SELECT token_id, user_id, admin_id, expires_at, used_at
       FROM password_reset_tokens
       WHERE token_hash = ?
       LIMIT 1",
    [$hash]
)->fetch();

if (!$row || $row['used_at'] || strtotime($row['expires_at']) < time()) {
    JsonResponse::send(400, ['error' => 'token_invalid_or_expired']);
}

$pwHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

if ($portal === 'admin' && $row['admin_id']) {
    DB::run("UPDATE admin_account SET password_hash = ? WHERE admin_id = ?",
            [$pwHash, (int) $row['admin_id']]);
} elseif ($portal === 'user' && $row['user_id']) {
    DB::run("UPDATE user_account  SET password_hash = ? WHERE user_id  = ?",
            [$pwHash, (int) $row['user_id']]);
} else {
    JsonResponse::badRequest('portal_mismatch');
}

DB::run("UPDATE password_reset_tokens SET used_at = NOW() WHERE token_id = ?",
        [(int) $row['token_id']]);

JsonResponse::ok(['stage' => 'password_changed']);
