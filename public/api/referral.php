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

$input        = JsonResponse::readJsonInput();
$cid          = (int) ($input['consultation_id'] ?? 0);
$type         = (string) ($input['referral_type'] ?? 'company_doctor');
$referredTo   = trim((string) ($input['referred_to'] ?? ''));
$reason       = trim((string) ($input['reason'] ?? ''));

if ($cid === 0 || $referredTo === '' || $reason === '') {
    JsonResponse::badRequest('missing_required_fields');
}
if (!in_array($type, ['company_doctor','hospital','specialist','emergency'], true)) {
    JsonResponse::badRequest('invalid_referral_type');
}

DB::run(
    "INSERT INTO referral
       (consultation_id, referral_type, referred_to, reason, referral_date, status)
     VALUES (?, ?, ?, ?, CURDATE(), 'issued')",
    [$cid, $type, $referredTo, $reason]
);
$refId = (int) DB::pdo()->lastInsertId();

// Send notification email for any non-company-doctor referral
if ($type !== 'company_doctor') {
    $admin = $_ENV['ADMIN_NOTIFICATION_EMAIL'] ?? null;
    if ($admin) {
        $body  = "<p><strong>New referral issued</strong></p>";
        $body .= "<p>Type: $type<br>Referred to: $referredTo<br>Reason: $reason</p>";
        $body .= "<p>Consultation #$cid — Referral #$refId</p>";
        Mailer::send($admin, 'Nightingale: Referral notification', $body);
    }
}

JsonResponse::ok(['stage' => 'created', 'referral_id' => $refId]);
