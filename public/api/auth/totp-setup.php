<?php
declare(strict_types=1);
require __DIR__ . '/../_init.php';

use Nightingale\Auth;
use Nightingale\DB;
use Nightingale\JsonResponse;
use Nightingale\Mailer;
use Nightingale\QR;
use Nightingale\TOTP;

api_method('POST');
$input  = JsonResponse::readJsonInput();
$action = $input['action'] ?? 'init';
$token  = (string) ($input['pending_token'] ?? '');

$payload = Auth::consumePendingTotp($token);
$kind    = $payload['kind'] ?? '';
if (!in_array($kind, ['totp_setup', 'admin_totp_setup'], true)) {
    JsonResponse::badRequest('wrong_pending_kind');
}
$isAdmin = $kind === 'admin_totp_setup';

$id   = (int) ($isAdmin ? ($payload['admin_id'] ?? 0) : ($payload['user_id'] ?? 0));
$name = '';
$mail = '';

if ($isAdmin) {
    $row = DB::run("SELECT username, email, full_name FROM admin_account WHERE admin_id=?", [$id])->fetch();
    if (!$row) JsonResponse::send(404, ['error' => 'admin_not_found']);
    $name = $row['full_name'];
    $mail = $row['email'];
    $accountLabel = "Nightingale Admin ({$row['username']})";
} else {
    $row = DB::run(
        "SELECT u.username, u.email, e.first_name, e.last_name
           FROM user_account u
           JOIN employee   e ON e.employee_id = u.employee_id
           WHERE u.user_id = ?",
        [$id]
    )->fetch();
    if (!$row) JsonResponse::send(404, ['error' => 'user_not_found']);
    $name = trim($row['first_name'] . ' ' . $row['last_name']);
    $mail = $row['email'];
    $accountLabel = "Nightingale ({$row['username']})";
}

if ($action === 'init') {
    $secret = TOTP::generateSecret();
    $uri    = TOTP::provisioningUri($secret, $accountLabel);
    $svg    = QR::svg($uri);
    $newToken = Auth::issuePendingTotp([
        'kind'         => $kind,
        'admin_id'     => $isAdmin ? $id : null,
        'user_id'      => $isAdmin ? null : $id,
        'pending_secret' => $secret,
    ]);
    JsonResponse::ok([
        'stage'           => 'enrolment_initialised',
        'pending_token'   => $newToken,
        'secret'          => $secret,
        'qr_svg'          => $svg,
        'provisioning_uri'=> $uri,
        'account'         => $accountLabel,
    ]);
}

if ($action === 'confirm') {
    $code = (string) ($input['code'] ?? '');
    $secret = (string) ($payload['pending_secret'] ?? '');
    if ($secret === '' || !TOTP::verify($secret, $code)) {
        JsonResponse::badRequest('invalid_totp');
    }

    // Generate + persist hashed backup codes
    $codes = TOTP::generateBackupCodes(8);
    if ($isAdmin) {
        DB::run("UPDATE admin_account SET totp_secret = ?, totp_enabled = 1 WHERE admin_id = ?",
                [$secret, $id]);
        DB::run("DELETE FROM totp_backup_codes WHERE admin_id = ?", [$id]);
        foreach ($codes as $c) {
            DB::run("INSERT INTO totp_backup_codes (admin_id, code_hash) VALUES (?, ?)",
                    [$id, TOTP::hashBackupCode($c)]);
        }
    } else {
        DB::run("UPDATE user_account SET totp_secret = ?, totp_enabled = 1 WHERE user_id = ?",
                [$secret, $id]);
        DB::run("DELETE FROM totp_backup_codes WHERE user_id = ?", [$id]);
        foreach ($codes as $c) {
            DB::run("INSERT INTO totp_backup_codes (user_id, code_hash) VALUES (?, ?)",
                    [$id, TOTP::hashBackupCode($c)]);
        }
    }

    // Email backup codes (or log them in dev)
    $bodyHtml  = "<h2>Hi {$name},</h2>";
    $bodyHtml .= "<p>Two-factor authentication is now enabled for your Nightingale account. ";
    $bodyHtml .= "Save these <strong>one-time backup codes</strong> in a safe place — each works once if you lose your authenticator device:</p>";
    $bodyHtml .= '<pre style="font-family:monospace;font-size:14px;">' . implode("\n", $codes) . '</pre>';
    Mailer::send($mail, 'Nightingale: Backup Recovery Codes', $bodyHtml);

    JsonResponse::ok([
        'stage'        => 'enrolment_complete',
        'backup_codes' => $codes, // shown ONCE — never returned again
    ]);
}

JsonResponse::badRequest('unknown_action');
