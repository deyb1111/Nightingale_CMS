<?php
declare(strict_types=1);
require __DIR__ . '/../_init.php';

use Nightingale\Auth;
use Nightingale\DB;
use Nightingale\JsonResponse;
use Nightingale\Mailer;

api_method('POST');
$input = JsonResponse::readJsonInput();
$email = strtolower(trim((string) ($input['email'] ?? '')));
$portal = ($input['portal'] ?? 'user') === 'admin' ? 'admin' : 'user';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    JsonResponse::badRequest('invalid_email');
}

// Look up — but always respond identically to avoid email enumeration.
$row = $portal === 'admin'
    ? DB::run("SELECT admin_id, full_name, email FROM admin_account WHERE email = ? AND is_active = 1", [$email])->fetch()
    : DB::run(
        "SELECT u.user_id, u.email, e.first_name, e.last_name
           FROM user_account u
           JOIN employee e ON e.employee_id = u.employee_id
           WHERE u.email = ? AND u.is_active = 1",
        [$email]
    )->fetch();

if ($row) {
    $token  = bin2hex(random_bytes(32));
    $hash   = hash('sha256', $token);
    $expiry = date('Y-m-d H:i:s', time() + 1800);

    if ($portal === 'admin') {
        DB::run("INSERT INTO password_reset_tokens (admin_id, token_hash, expires_at, ip_address) VALUES (?, ?, ?, ?)",
                [(int) $row['admin_id'], $hash, $expiry, Auth::clientIp()]);
        $name = $row['full_name'];
    } else {
        DB::run("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, ip_address) VALUES (?, ?, ?, ?)",
                [(int) $row['user_id'], $hash, $expiry, Auth::clientIp()]);
        $name = trim($row['first_name'] . ' ' . $row['last_name']);
    }

    $base = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
    $url  = "$base/password-reset.php?portal=$portal&token=$token";

    $body  = "<h2>Hi $name,</h2>";
    $body .= "<p>We received a password-reset request for your Nightingale account. ";
    $body .= "If this was you, click the link below within 30 minutes:</p>";
    $body .= "<p><a href='$url'>$url</a></p>";
    $body .= "<p>If you did not request this, you can safely ignore this email.</p>";
    Mailer::send($row['email'], 'Nightingale: Password Reset', $body);
}

JsonResponse::ok(['stage' => 'sent_if_account_exists']);
