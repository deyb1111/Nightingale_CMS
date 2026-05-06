<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

use Nightingale\CSRF;
use Nightingale\DB;
use Nightingale\JsonResponse;
use Nightingale\Session;

api_method('POST');
Session::requireRole('nurse');
CSRF::require();

$input = JsonResponse::readJsonInput();
$cid = (int) ($input['consultation_id'] ?? 0);
if ($cid === 0) JsonResponse::badRequest('missing_consultation_id');

$bpS    = isset($input['bp_systolic'])  ? (int) $input['bp_systolic']  : null;
$bpD    = isset($input['bp_diastolic']) ? (int) $input['bp_diastolic'] : null;
$temp   = isset($input['temperature'])  ? (float) $input['temperature']: null;
$pulse  = isset($input['pulse_rate'])   ? (int) $input['pulse_rate']   : null;
$resp   = isset($input['resp_rate'])    ? (int) $input['resp_rate']    : null;
$o2     = isset($input['o2_saturation'])? (int) $input['o2_saturation']: null;
$weight = isset($input['weight_kg'])    ? (float) $input['weight_kg']  : null;

DB::run(
    "INSERT INTO vital_signs
       (consultation_id, bp_systolic, bp_diastolic, temperature,
        pulse_rate, resp_rate, o2_saturation, weight_kg)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        bp_systolic   = VALUES(bp_systolic),
        bp_diastolic  = VALUES(bp_diastolic),
        temperature   = VALUES(temperature),
        pulse_rate    = VALUES(pulse_rate),
        resp_rate     = VALUES(resp_rate),
        o2_saturation = VALUES(o2_saturation),
        weight_kg     = VALUES(weight_kg)",
    [$cid, $bpS, $bpD, $temp, $pulse, $resp, $o2, $weight]
);

JsonResponse::ok(['stage' => 'saved']);
