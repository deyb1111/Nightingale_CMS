<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

use Nightingale\CSRF;
use Nightingale\DB;
use Nightingale\JsonResponse;
use Nightingale\Session;

$method = api_method('GET','POST');

if ($method === 'GET') {
    Session::requireRole('admin','nurse','patient');
    $rows = DB::run("SELECT setting_key, setting_value FROM clinic_settings")->fetchAll();
    $kv = [];
    foreach ($rows as $r) $kv[$r['setting_key']] = $r['setting_value'];
    JsonResponse::ok(['settings' => $kv]);
}

Session::requireRole('admin');
CSRF::require();
$input = JsonResponse::readJsonInput();
if (!is_array($input)) JsonResponse::badRequest('invalid_payload');

$allowed = [
    'clinic_name','clinic_address','opening_time','closing_time',
    'contact_email','require_2fa_all_users','session_timeout_minutes',
    'audit_logging_enabled',
];

foreach ($input as $key => $value) {
    if (!in_array($key, $allowed, true)) continue;
    DB::run(
        "INSERT INTO clinic_settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
        [$key, (string) $value]
    );
}

JsonResponse::ok(['stage' => 'saved']);
