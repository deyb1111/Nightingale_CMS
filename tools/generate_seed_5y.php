<?php
/**
 * NIGHTINGALE — 5-year seed data generator.
 *
 * Produces sql/06_seed_5_years.sql  with realistic clinic activity
 * spanning 2021-05-01 → 2026-05-01.  The output is deterministic
 * (mt_srand'd) so re-running the script gives byte-identical SQL,
 * which is convenient for diff-friendly version control.
 *
 * Usage:
 *   php tools/generate_seed_5y.php
 */

declare(strict_types=1);

mt_srand(2026); // deterministic output

// ─────────────────────────── CONFIG
const START_DATE     = '2021-05-01';
const END_DATE       = '2026-05-01';
const EMPLOYEE_COUNT = 250;
const NURSE_COUNT    = 3;        // matches 05_seed_reference.sql
const NURSE_FIRST_ID = 1;
const ADMIN_USER_ID  = 1;        // for inventory_log "actioned_by"

const DEFAULT_PASSWORD_HASH =
    '$2y$12$tL6xGk2/Iit2SSQZVuvDJejHH64K5D7dthslXi8b/UZuTeP8oz75O';
// ^^ bcrypt of "password" — change after first login

const DEPARTMENTS = [
    1 => 'Information Technology',
    2 => 'Accounting',
    3 => 'Operations',
    4 => 'Human Resources',
    5 => 'Administration & Executive',
];

// Department weights — Operations has the most employees.
const DEPT_WEIGHTS = [
    1 => 28, 2 => 18, 3 => 32, 4 => 12, 5 => 10,
];

const FIRST_NAMES_M = [
    'Carlo','Jun','Ramon','Marco','Paolo','Anton','Eric','Mark','Joel','Niel',
    'Roberto','Felix','Angelo','Gerald','Bernard','Edmund','Lance','Wilson',
    'Ariel','Joseph','Michael','Daniel','Andres','Vincent','Leo','Ronald',
    'Roel','Allan','Carlos','Joshua','Jaime','Patrick','Edgar','Marvin',
];
const FIRST_NAMES_F = [
    'Maria','Lena','Ana','Ella','Joy','Karla','Cristina','Liza','Tanya',
    'Bea','Camille','Daisy','Elaine','Faye','Grace','Honey','Irene','Jen',
    'Kimberly','Lara','Mara','Nina','Olivia','Patricia','Queenie','Rachel',
    'Sandra','Tina','Vivian','Wendy','Xenia','Yvonne','Zarah','Diane',
];
const LAST_NAMES = [
    'Santos','Reyes','dela Cruz','Garcia','Mendoza','Pascual','Torres','Castro',
    'Ramos','Bautista','Aquino','Domingo','Villanueva','Salvador','Tan','Lim',
    'Chua','Sy','Co','Ong','Yap','Que','Tiu','Go','Cruz','Flores','Manalo',
    'Aguilar','Aglipay','Antonio','Bernardo','Buenaventura','Cabrera','Cabal',
    'Crisostomo','Concepcion','David','Diaz','Enriquez','Esguerra','Fernandez',
    'Gonzales','Hernandez','Ignacio','Javier','Lopez','Macaraeg','Magsino',
];
const BLOOD_TYPES = [
    ['O+', 38], ['A+', 27], ['B+', 25], ['AB+', 5],
    ['O-', 2.5], ['A-', 1.5], ['B-', 0.7], ['AB-', 0.3],
];
const ALLERGIES = [
    null, null, null, null, null,             // ~70% have none
    'Penicillin', 'Penicillin',
    'Sulfa drugs',
    'Aspirin',
    'Latex',
    'Peanuts',
    'Asthma — dust mites',
    'Shellfish',
    'Pollen',
    'Insect stings',
];
const HOLIDAYS = [
    // PH regular holidays — recurring (M-D)
    '01-01','04-09','05-01','06-12','08-21','08-26','11-30','12-25','12-30','12-31',
];

// Illness chief-complaint pool (weighted)
const ILLNESS_COMPLAINTS = [
    ['Upper respiratory infection — cough, colds, sore throat', 28, 'Acute Pharyngitis',     ['Biogesic','Cetirizine']],
    ['Headache / migraine',                                      14, 'Tension Headache',     ['Biogesic']],
    ['Hypertension follow-up',                                   12, 'Hypertension Stage 1', ['Losartan']],
    ['Acute gastroenteritis — diarrhea',                         10, 'Acute Gastroenteritis',['Loperamide','Hyoscine']],
    ['Allergic rhinitis',                                         8, 'Allergic Rhinitis',    ['Cetirizine']],
    ['Hyperacidity / heartburn',                                  7, 'GERD',                 ['Omeprazole']],
    ['Asthma exacerbation',                                       6, 'Bronchial Asthma',     ['Ventolin','Cetirizine']],
    ['Fever — unspecified',                                       5, 'Viral Fever',          ['Biogesic']],
    ['Dysmenorrhea',                                              4, 'Primary Dysmenorrhea', ['Mefenamic Acid']],
    ['Skin rash / dermatitis',                                    3, 'Contact Dermatitis',   ['Cetirizine']],
    ['Diabetes follow-up',                                        3, 'Type 2 Diabetes',      ['Metformin']],
];
const INJURY_COMPLAINTS = [
    ['Minor wound — laceration left hand',     'Minor Laceration', ['Betadine','Alcohol 70%']],
    ['Sprain — right ankle',                   'Ankle Sprain',     ['Mefenamic Acid']],
    ['Burn — superficial right forearm',       '1st-Degree Burn',  ['Betadine']],
    ['Contusion — left knee',                  'Contusion',        ['Mefenamic Acid']],
    ['Abrasion — right elbow',                 'Abrasion',         ['Betadine','Alcohol 70%']],
];
const FOLLOW_UP_COMPLAINTS = [
    ['BP follow-up — Losartan compliance',     'HTN Follow-up',    ['Losartan']],
    ['DM follow-up — A1C check',               'DM Follow-up',     ['Metformin']],
    ['Wound check — laceration day 3',         'Wound Healing',    ['Betadine']],
    ['Asthma control review',                  'Asthma F/U',       ['Ventolin']],
];
const EMERGENCY_COMPLAINTS = [
    ['High fever 39°C, suspected dengue',          'Suspected Dengue',     'hospital',     'Pasig City General Hospital — ER',          'Refer for CBC and dengue NS1 test'],
    ['Severe asthma attack, SPO2 88%',             'Severe Asthma',         'hospital',     'The Medical City — Pasig — Emergency Room', 'Bronchodilator administered, oxygen needed'],
    ['Chest pain, suspected ACS',                  'Possible ACS',          'hospital',     "St. Luke's Medical Center — ER",            'ECG abnormal, urgent cardiology consult'],
    ['Severe head trauma after fall',              'Head Trauma',           'emergency',    'Manila Doctors Hospital — ER',              'GCS 13, refer for CT scan'],
    ['Anaphylactic reaction to seafood',           'Anaphylaxis',           'emergency',    'Pasig City General Hospital — ER',          'Epinephrine given, hospital monitoring'],
];
const REFERRAL_COMPANY_DOCTOR = [
    ['Hypertension — Stage 2',         'company_doctor', 'Dr. Santos (Company Doctor)',     'BP consistently >150/95 over 2 weeks'],
    ['Persistent cough > 3 weeks',     'company_doctor', 'Dr. Santos (Company Doctor)',     'Possible asthma vs TB — chest X-ray ordered'],
    ['Migraine — recurrent',           'company_doctor', 'Dr. Santos (Company Doctor)',     'For neurology evaluation'],
    ['Diabetes — uncontrolled',        'specialist',     'Dr. Patel (Endocrinologist)',     'A1C 9.4 — needs medication adjustment'],
    ['Lower back pain — chronic',      'specialist',     'Dr. Lim (Orthopedic Specialist)','For MRI / orthopedic clearance'],
];

// ─────────────────────────── HELPERS

function pickWeighted(array $items): array
{
    $total = 0;
    foreach ($items as $row) { $total += $row[1]; }
    $r = mt_rand(1, (int) ($total * 100)) / 100;
    $cum = 0;
    foreach ($items as $row) { $cum += $row[1]; if ($cum >= $r) return $row; }
    return $items[count($items) - 1];
}

function pickWeightedSimple(array $items): mixed
{
    return pickWeighted($items)[0];
}

function rndDate(string $start, string $end): string
{
    $s = strtotime($start);
    $e = strtotime($end);
    return date('Y-m-d', mt_rand($s, $e));
}

function isHoliday(int $ts): bool
{
    $md = date('m-d', $ts);
    foreach (HOLIDAYS as $h) { if ($h === $md) return true; }
    // Holy Week — approximation: Thu/Fri before Easter (just exclude Apr 14 if year contains it consistently).
    if ($md === '04-14' || $md === '04-13') return mt_rand(0, 3) === 0;
    return false;
}

function dayMultiplier(int $ts): float
{
    $month = (int) date('n', $ts);
    if (in_array($month, [7, 8, 9], true))   return 1.4; // flu season
    if (in_array($month, [12, 1], true))     return 1.2; // colds
    if (in_array($month, [4], true))         return 1.3; // dengue start
    return 1.0;
}

function clinSql(string $s): string
{
    if ($s === null) return 'NULL';
    return "'" . str_replace("'", "''", $s) . "'";
}

function mysqlValues(array $rows): string
{
    $parts = [];
    foreach ($rows as $row) {
        $cells = [];
        foreach ($row as $cell) {
            if ($cell === null)            { $cells[] = 'NULL'; }
            elseif (is_int($cell) || is_float($cell)) { $cells[] = (string) $cell; }
            else                           { $cells[] = clinSql((string) $cell); }
        }
        $parts[] = '  (' . implode(',', $cells) . ')';
    }
    return implode(",\n", $parts);
}

function chunkInsert(string $table, array $columns, array $rows, int $chunk = 500): string
{
    if (empty($rows)) return "-- no rows for $table\n";
    $sql = '';
    foreach (array_chunk($rows, $chunk) as $batch) {
        $sql .= "INSERT INTO $table (" . implode(',', $columns) . ") VALUES\n";
        $sql .= mysqlValues($batch) . ";\n\n";
    }
    return $sql;
}

// ─────────────────────────── EMPLOYEE GENERATION

$employees = []; // employee_id => row
$employeeIds = [];
$nextEmpId = 4;       // 1-3 reserved for nurses
$usedUsernames = ['admin','hr.admin','nurse','blim','ccruz'];

for ($i = 0; $i < EMPLOYEE_COUNT; $i++) {
    $isMale = mt_rand(0, 1) === 1;
    $first  = $isMale
        ? FIRST_NAMES_M[array_rand(FIRST_NAMES_M)]
        : FIRST_NAMES_F[array_rand(FIRST_NAMES_F)];
    $last   = LAST_NAMES[array_rand(LAST_NAMES)];

    // Employee number — sequential, EMP-#####
    $empNo  = sprintf('EMP-%05d', $nextEmpId);

    // Birthdate: 22–62 years old as of 2026
    $age    = mt_rand(22, 62);
    $birthY = 2026 - $age;
    $birthM = mt_rand(1, 12);
    $birthD = mt_rand(1, 28);
    $bdate  = sprintf('%04d-%02d-%02d', $birthY, $birthM, $birthD);

    $blood = pickWeighted(array_map(fn($r) => [$r[0], $r[1]], BLOOD_TYPES))[0];
    $allergies = ALLERGIES[array_rand(ALLERGIES)];
    $emerName  = LAST_NAMES[array_rand(LAST_NAMES)] . ' ' . FIRST_NAMES_M[array_rand(FIRST_NAMES_M)];
    $emerPhone = '09' . sprintf('%09d', mt_rand(100000000, 999999999));

    // Department weighted
    $dept = pickWeighted(array_map(fn($id, $w) => [$id, $w], array_keys(DEPT_WEIGHTS), array_values(DEPT_WEIGHTS)))[0];

    // Hire date
    $hireY = mt_rand(2015, 2024);
    $hireM = mt_rand(1, 12);
    $hireD = mt_rand(1, 28);
    $hdate = sprintf('%04d-%02d-%02d', $hireY, $hireM, $hireD);

    $row = [
        'employee_id'       => $nextEmpId,
        'employee_no'       => $empNo,
        'first_name'        => $first,
        'last_name'         => $last,
        'birthdate'         => $bdate,
        'gender'            => $isMale ? 'M' : 'F',
        'blood_type'        => $blood,
        'allergies'         => $allergies,
        'emergency_contact' => $emerName,
        'emergency_phone'   => $emerPhone,
        'dept_id'           => (int) $dept,
        'hire_date'         => $hdate,
    ];
    $employees[$nextEmpId] = $row;
    $employeeIds[]         = $nextEmpId;
    $nextEmpId++;
}

// ─────────────────────────── PATIENT ACCOUNTS

$accounts = [];
$nextUserId = 4; // 1-3 reserved for nurses
foreach ($employees as $emp) {
    // Username = first letter of first name + last name (lowercase, no spaces)
    $base = strtolower(substr($emp['first_name'], 0, 1) . preg_replace('/[^a-z]/i', '', $emp['last_name']));
    $username = $base;
    $i = 1;
    while (in_array($username, $usedUsernames, true)) {
        $username = $base . $i++;
    }
    $usedUsernames[] = $username;

    // Email mirrors the (already-unique) username — guarantees uniqueness.
    $email = $username . '@company.ph';

    $accounts[] = [
        'user_id'       => $nextUserId,
        'employee_id'   => $emp['employee_id'],
        'username'      => $username,
        'email'         => $email,
        'password_hash' => DEFAULT_PASSWORD_HASH,
        'role'          => 'patient',
        'totp_enabled'  => 0,
        'is_active'     => 1,
        'created_at'    => $emp['hire_date'] . ' 09:00:00',
    ];
    $nextUserId++;
}

// ─────────────────────────── DAILY CONSULTATION TIMELINE

$queueRows         = [];
$consultRows       = [];
$vitalRows         = [];
$dispenseRows      = [];
$invLogRows        = [];
$referralRows      = [];
$apeRows           = [];

$nextQueueId       = 1;
$nextConsultId     = 1;
$nextVitalId       = 1;
$nextDispenseId    = 1;
$nextInvLogId      = 1;
$nextReferralId    = 1;
$nextApeId         = 1;

// Live stock tracker for medicine inventory simulation.
// Restocks are scheduled monthly to keep stock between min and 4×min.
$stock = [
    1 => 220, 2 => 80,  3 => 130, 4 => 130, 5 => 110,
    6 => 130, 7 => 110, 8 => 100, 9 => 30,  10 => 25,
    11 => 70, 12 => 60,
];
$medMaxByName = [
    'Biogesic' => 1, 'Ventolin' => 2, 'Amoxicillin' => 3, 'Cetirizine' => 4,
    'Omeprazole' => 5, 'Mefenamic Acid' => 6, 'Losartan' => 7, 'Metformin' => 8,
    'Alcohol 70%' => 9, 'Betadine' => 10, 'Loperamide' => 11, 'Hyoscine' => 12,
];
$medCapacity = [
    1 => 400, 2 => 160, 3 => 240, 4 => 220, 5 => 200,
    6 => 220, 7 => 200, 8 => 180, 9 => 60,  10 => 50,
    11 => 140, 12 => 120,
];
$medMin = [
    1 => 80, 2 => 40, 3 => 40, 4 => 40, 5 => 30,
    6 => 40, 7 => 30, 8 => 30, 9 => 10, 10 => 10,
    11 => 25, 12 => 25,
];

function pickNurse(int $ts): int
{
    // Rotate nurse weekly (mod 3) plus weekday bias
    $w = (int) date('W', $ts);
    $d = (int) date('N', $ts);
    return ($w + $d) % NURSE_COUNT + NURSE_FIRST_ID;
}

function genVitals(string $caseType, array $emp, int $ts): array
{
    $age = (int) (($ts - strtotime($emp['birthdate'])) / 86400 / 365.25);
    $bpS = mt_rand(105, 130);
    $bpD = mt_rand(65, 85);
    $temp = mt_rand(360, 372) / 10.0;
    $pulse = mt_rand(62, 92);
    $resp  = mt_rand(14, 20);
    $o2    = mt_rand(96, 100);
    $weight = mt_rand(45 * 10, 95 * 10) / 10.0;

    if ($caseType === 'illness' || $caseType === 'emergency') {
        $temp += mt_rand(0, 30) / 10.0;        // can run a fever
        $pulse += mt_rand(0, 30);
    }
    if (mt_rand(0, 5) === 0) {
        // ~17% have hypertension-ish readings
        $bpS = mt_rand(135, 165);
        $bpD = mt_rand(85, 105);
    }
    if ($caseType === 'emergency' && mt_rand(0, 1) === 0) {
        $o2 = mt_rand(85, 94);
    }
    return [$bpS, $bpD, round($temp, 1), $pulse, $resp, $o2, round($weight, 2)];
}

// ─────────────────────────── MAIN SIMULATION LOOP

$tsStart = strtotime(START_DATE);
$tsEnd   = strtotime(END_DATE);
$lastRestockMonth = '';
$lastConsultStartTime = [];

for ($ts = $tsStart; $ts <= $tsEnd; $ts += 86400) {
    $weekday = (int) date('N', $ts);   // 1 = Mon, 7 = Sun
    if ($weekday >= 6 || isHoliday($ts)) continue;

    // Mid-month restock cycle
    $monthKey = date('Y-m', $ts);
    if ($monthKey !== $lastRestockMonth && (int) date('j', $ts) >= 14) {
        $lastRestockMonth = $monthKey;
        foreach ($stock as $medId => $qty) {
            $cap = $medCapacity[$medId];
            if ($qty < $cap * 0.5) {
                $add = $cap - $qty;
                $stock[$medId] += $add;
                $invLogRows[] = [
                    $nextInvLogId++,
                    $medId,
                    ADMIN_USER_ID,
                    'restock',
                    $add,
                    $stock[$medId],
                    "Monthly restock — $monthKey",
                    date('Y-m-d', $ts) . ' 07:30:00',
                ];
            }
        }
    }

    $base   = mt_rand(6, 14);
    $count  = (int) round($base * dayMultiplier($ts));
    $count  = max(2, min($count, 22));

    // Pick the patients seen today (no duplicates)
    $todayPatients = (array) array_rand(array_flip($employeeIds), min($count, count($employeeIds)));

    $qNumber = 1;
    foreach ($todayPatients as $empId) {
        $empId = (int) $empId;
        $emp = $employees[$empId];

        // Choose case type: illness 65 / injury 18 / follow_up 12 / emergency 5
        $r = mt_rand(1, 100);
        if      ($r <= 65)  $caseType = 'illness';
        elseif  ($r <= 83)  $caseType = 'injury';
        elseif  ($r <= 95)  $caseType = 'follow_up';
        else                $caseType = 'emergency';

        // Working hours 08:00–17:00, ~30 min per consult
        $hour = 8 + (int) (($qNumber - 1) / 2);
        if ($hour > 16) $hour = 16;
        $minute = ($qNumber % 2) * 30 + mt_rand(0, 25);
        $startSeconds = $hour * 3600 + $minute * 60 + mt_rand(0, 59);
        $endSeconds   = $startSeconds + mt_rand(15, 35) * 60;
        $timeIn       = sprintf('%02d:%02d:%02d', $hour, $minute, mt_rand(0, 59));
        $timeStart    = $timeIn;
        $timeEnd      = sprintf('%02d:%02d:%02d',
            (int) ($endSeconds / 3600) % 24,
            (int) ($endSeconds / 60) % 60,
            $endSeconds % 60);

        $nurseId = pickNurse($ts);

        // Pick chief complaint + meds
        if ($caseType === 'illness') {
            $row = pickWeighted(array_map(fn($r) => [$r, $r[1]], ILLNESS_COMPLAINTS))[0];
            $complaint = $row[0];
            $diagnosis = $row[2];
            $candidateMeds = $row[3];
        } elseif ($caseType === 'injury') {
            $row = INJURY_COMPLAINTS[array_rand(INJURY_COMPLAINTS)];
            $complaint = $row[0];
            $diagnosis = $row[1];
            $candidateMeds = $row[2];
        } elseif ($caseType === 'follow_up') {
            $row = FOLLOW_UP_COMPLAINTS[array_rand(FOLLOW_UP_COMPLAINTS)];
            $complaint = $row[0];
            $diagnosis = $row[1];
            $candidateMeds = $row[2];
        } else { // emergency
            $row = EMERGENCY_COMPLAINTS[array_rand(EMERGENCY_COMPLAINTS)];
            $complaint = $row[0];
            $diagnosis = $row[1];
            $emergencyRow = $row;
            $candidateMeds = ['Biogesic'];
        }

        $workStatusRoll = mt_rand(1, 100);
        if ($caseType === 'emergency')        $workStatus = 'for_hospitalization';
        elseif ($caseType === 'illness')      $workStatus = $workStatusRoll <= 60 ? 'fit' : ($workStatusRoll <= 90 ? 'light_duty' : 'sick_leave');
        elseif ($caseType === 'injury')       $workStatus = $workStatusRoll <= 50 ? 'light_duty' : ($workStatusRoll <= 90 ? 'fit' : 'sick_leave');
        else                                  $workStatus = $workStatusRoll <= 80 ? 'fit' : 'light_duty';

        $nurseNote = match ($caseType) {
            'illness'   => "Vital signs taken. {$diagnosis}. Symptoms managed with available meds. Patient advised hydration and rest.",
            'injury'    => "Wound cleansed and dressed. Tetanus toxoid status verified. Re-evaluation scheduled if symptoms persist.",
            'follow_up' => "Follow-up reviewed. Compliance stable. Continue current regimen and monitor.",
            'emergency' => "Stabilized in clinic. Vital signs trending; emergency referral coordinated.",
        };

        // queue row
        $queueRows[] = [
            $nextQueueId,
            $empId,
            $qNumber,
            date('Y-m-d', $ts),
            $timeIn,
            'done',
        ];

        // consultation row
        $consultRows[] = [
            $nextConsultId,
            $nextQueueId,
            $empId,
            $nurseId,
            $complaint,
            $diagnosis,
            $caseType,
            $nurseNote,
            $workStatus,
            date('Y-m-d', $ts),
            $timeStart,
            $timeEnd,
        ];

        // vitals
        [$bpS, $bpD, $temp, $pulse, $resp, $o2, $weight] = genVitals($caseType, $emp, $ts);
        $vitalRows[] = [
            $nextVitalId++,
            $nextConsultId,
            $bpS, $bpD, $temp, $pulse, $resp, $o2, $weight,
            date('Y-m-d', $ts) . ' ' . $timeStart,
        ];

        // medicines (0–3 dispenses)
        $dispenseCount = 0;
        if ($caseType === 'emergency') {
            $dispenseCount = 1;
        } elseif ($caseType === 'follow_up') {
            $dispenseCount = mt_rand(0, 1);
        } elseif ($caseType === 'injury') {
            $dispenseCount = mt_rand(1, 2);
        } else {
            $dispenseCount = mt_rand(1, 3);
        }
        $dispenseCount = min($dispenseCount, count($candidateMeds));
        $candidateNames = $candidateMeds;
        shuffle($candidateNames);
        for ($k = 0; $k < $dispenseCount; $k++) {
            $name = $candidateNames[$k];
            $medId = $medMaxByName[$name] ?? null;
            if ($medId === null) continue;

            $qty = match ($name) {
                'Biogesic','Mefenamic Acid'      => mt_rand(2, 6),
                'Cetirizine','Losartan','Metformin','Omeprazole' => mt_rand(2, 5),
                'Amoxicillin'                    => mt_rand(7, 21),
                'Ventolin'                       => mt_rand(1, 3),
                'Loperamide','Hyoscine'          => mt_rand(2, 4),
                'Alcohol 70%','Betadine'         => 1,
                default                          => 1,
            };

            // Don't go negative — restock just-in-time if needed
            if ($stock[$medId] < $qty) {
                $add = $medCapacity[$medId] - $stock[$medId];
                $stock[$medId] += $add;
                $invLogRows[] = [
                    $nextInvLogId++,
                    $medId, ADMIN_USER_ID, 'restock',
                    $add, $stock[$medId],
                    'Emergency restock (low-stock alert)',
                    date('Y-m-d', $ts) . ' 07:00:00',
                ];
            }
            $stock[$medId] -= $qty;

            $dosage = match ($name) {
                'Biogesic','Mefenamic Acid'    => '500 mg, 1 tab every 6 hours as needed for pain/fever',
                'Cetirizine'                   => '10 mg, 1 tab once daily',
                'Losartan'                     => '50 mg, 1 tab once daily',
                'Metformin'                    => '500 mg, 1 tab twice daily after meals',
                'Omeprazole'                   => '20 mg, 1 cap once daily before breakfast',
                'Amoxicillin'                  => '500 mg, 1 cap every 8 hours for 7 days',
                'Ventolin'                     => '2 mg, 1 tab every 6 hours as needed',
                'Loperamide'                   => '2 mg, 1 cap after each loose stool (max 8/day)',
                'Hyoscine'                     => '10 mg, 1 tab every 8 hours as needed',
                'Alcohol 70%','Betadine'       => 'Apply topically to affected area',
                default                        => 'As directed',
            };

            $dispenseRows[] = [
                $nextDispenseId++,
                $nextConsultId,
                $medId,
                $nurseId,
                $qty,
                $dosage,
                date('Y-m-d', $ts) . ' ' . $timeEnd,
            ];
            $invLogRows[] = [
                $nextInvLogId++,
                $medId, ADMIN_USER_ID, 'dispensed',
                -$qty, $stock[$medId],
                "Dispensed for consultation #$nextConsultId",
                date('Y-m-d', $ts) . ' ' . $timeEnd,
            ];
        }

        // referral
        if ($caseType === 'emergency') {
            $referralRows[] = [
                $nextReferralId++,
                $nextConsultId,
                $emergencyRow[2],          // type
                $emergencyRow[3],          // referred_to
                $emergencyRow[4],          // reason
                date('Y-m-d', $ts),
                'completed',
            ];
        } elseif (mt_rand(1, 100) <= 8) {
            $row = REFERRAL_COMPANY_DOCTOR[array_rand(REFERRAL_COMPANY_DOCTOR)];
            $statusRoll = mt_rand(1, 100);
            $status = $statusRoll <= 70 ? 'completed' : ($statusRoll <= 90 ? 'acknowledged' : 'issued');
            $referralRows[] = [
                $nextReferralId++,
                $nextConsultId,
                $row[1],
                $row[2],
                $row[3],
                date('Y-m-d', $ts),
                $status,
            ];
        }

        $nextQueueId++;
        $nextConsultId++;
        $qNumber++;
    }
}

// ─────────────────────────── ANNUAL PHYSICAL EXAMS

for ($year = 2021; $year <= 2025; $year++) {
    foreach ($employeeIds as $empId) {
        // 88% completion rate (Reference §03 #15 example)
        if (mt_rand(1, 100) > 88) continue;

        $monthsRange = match ($year) {
            2021 => [10, 11, 12],
            default => [3, 4, 5, 6, 7, 8, 9, 10, 11],
        };
        $month = $monthsRange[array_rand($monthsRange)];
        $day   = mt_rand(1, 28);
        $examDate = sprintf('%04d-%02d-%02d', $year, $month, $day);

        $bpS    = mt_rand(105, 138);
        $bpD    = mt_rand(65, 88);
        $weight = mt_rand(45 * 10, 95 * 10) / 10.0;
        $height = mt_rand(150 * 10, 185 * 10) / 10.0;
        $bmi    = round($weight / (($height / 100) ** 2), 2);

        $statusRoll = mt_rand(1, 100);
        $status     = $statusRoll <= 80 ? 'cleared' : ($statusRoll <= 95 ? 'completed' : 'flagged');
        $remarks = match ($status) {
            'cleared'   => 'Fit for work. Annual exam completed.',
            'completed' => 'Awaiting physician sign-off.',
            'flagged'   => 'BP elevated — referred to company doctor.',
        };

        $apeRows[] = [
            $nextApeId++,
            $empId,
            (($empId - 4) % NURSE_COUNT) + NURSE_FIRST_ID,
            $year,
            $examDate,
            $bpS, $bpD, $weight, $height, $bmi,
            $status, $remarks,
        ];
    }
}

// ─────────────────────────── EMIT SQL

$out = "-- ============================================================\n";
$out .= "-- NIGHTINGALE — 06 · 5-year seed data\n";
$out .= "-- Auto-generated by tools/generate_seed_5y.php\n";
$out .= "-- Window: " . START_DATE . " → " . END_DATE . "\n";
$out .= "-- Employees: " . count($employees) . "\n";
$out .= "-- Consultations: " . count($consultRows) . "\n";
$out .= "-- Vital sign records: " . count($vitalRows) . "\n";
$out .= "-- Medicines dispensed: " . count($dispenseRows) . "\n";
$out .= "-- Inventory log entries: " . count($invLogRows) . "\n";
$out .= "-- Referrals: " . count($referralRows) . "\n";
$out .= "-- APE records: " . count($apeRows) . "\n";
$out .= "-- ============================================================\n\n";
$out .= "USE nightingale_cms;\n\n";

$out .= "-- Disable triggers/audit while we bulk-load reference data.\n";
$out .= "SET @current_user_id  = NULL;\n";
$out .= "SET @current_admin_id = NULL;\n";
$out .= "SET FOREIGN_KEY_CHECKS = 0;\n";
$out .= "SET UNIQUE_CHECKS      = 0;\n";
$out .= "SET autocommit         = 0;\n";
$out .= "START TRANSACTION;\n\n";

// Employees
$empRows = array_map(fn($e) => [
    $e['employee_id'], $e['employee_no'], $e['first_name'], $e['last_name'],
    $e['birthdate'], $e['gender'], $e['blood_type'], $e['allergies'],
    $e['emergency_contact'], $e['emergency_phone'], $e['dept_id'], $e['hire_date'],
], array_values($employees));
$out .= chunkInsert(
    'employee',
    ['employee_id','employee_no','first_name','last_name','birthdate','gender',
     'blood_type','allergies','emergency_contact','emergency_phone','dept_id','hire_date'],
    $empRows
);

// User accounts
$accountRows = array_map(fn($a) => [
    $a['user_id'], $a['employee_id'], $a['username'], $a['email'],
    $a['password_hash'], $a['role'], $a['totp_enabled'], $a['is_active'], $a['created_at'],
], $accounts);
$out .= chunkInsert(
    'user_account',
    ['user_id','employee_id','username','email','password_hash','role','totp_enabled','is_active','created_at'],
    $accountRows
);

// Queue
$out .= chunkInsert('queue',
    ['queue_id','employee_id','queue_number','queue_date','time_in','status'],
    $queueRows, 1000);

// Consultation
$out .= chunkInsert('consultation',
    ['consultation_id','queue_id','employee_id','nurse_id','chief_complaint',
     'diagnosis','case_type','nurse_notes','work_status','consult_date','time_start','time_end'],
    $consultRows, 800);

// Vitals
$out .= chunkInsert('vital_signs',
    ['vital_id','consultation_id','bp_systolic','bp_diastolic','temperature',
     'pulse_rate','resp_rate','o2_saturation','weight_kg','recorded_at'],
    $vitalRows, 1000);

// Dispenses
$out .= chunkInsert('medicine_dispensed',
    ['dispense_id','consultation_id','medicine_id','nurse_id','quantity',
     'dosage_instructions','dispensed_at'],
    $dispenseRows, 1000);

// Referrals
$out .= chunkInsert('referral',
    ['referral_id','consultation_id','referral_type','referred_to','reason',
     'referral_date','status'],
    $referralRows, 1000);

// Inventory log
$out .= chunkInsert('inventory_log',
    ['log_id','medicine_id','actioned_by','action_type','qty_change',
     'new_stock_level','remarks','actioned_at'],
    $invLogRows, 1000);

// APE
$out .= chunkInsert('ape_record',
    ['ape_id','employee_id','nurse_id','exam_year','exam_date',
     'bp_systolic','bp_diastolic','weight_kg','height_cm','bmi','status','remarks'],
    $apeRows, 1000);

// Live medicine stock — set to current simulated values
$out .= "-- Sync medicine.current_stock with the simulation's final state.\n";
foreach ($stock as $medId => $qty) {
    $out .= "UPDATE medicine SET current_stock = $qty WHERE medicine_id = $medId;\n";
}
$out .= "\n";

// Reset auto-increments
$lastEmpId      = max(array_keys($employees));
$lastUserId     = !empty($accounts) ? max(array_column($accounts, 'user_id')) : 3;
$lastQueueId    = $nextQueueId;
$lastConsultId  = $nextConsultId;
$lastVitalId    = $nextVitalId;
$lastDispId     = $nextDispenseId;
$lastInvLogId   = $nextInvLogId;
$lastReferralId = $nextReferralId;
$lastApeId      = $nextApeId;

$out .= "ALTER TABLE employee          AUTO_INCREMENT = " . ($lastEmpId + 1) . ";\n";
$out .= "ALTER TABLE user_account      AUTO_INCREMENT = " . ($lastUserId + 1) . ";\n";
$out .= "ALTER TABLE queue             AUTO_INCREMENT = " . $lastQueueId    . ";\n";
$out .= "ALTER TABLE consultation      AUTO_INCREMENT = " . $lastConsultId  . ";\n";
$out .= "ALTER TABLE vital_signs       AUTO_INCREMENT = " . $lastVitalId    . ";\n";
$out .= "ALTER TABLE medicine_dispensed AUTO_INCREMENT = " . $lastDispId     . ";\n";
$out .= "ALTER TABLE inventory_log     AUTO_INCREMENT = " . $lastInvLogId   . ";\n";
$out .= "ALTER TABLE referral          AUTO_INCREMENT = " . $lastReferralId . ";\n";
$out .= "ALTER TABLE ape_record        AUTO_INCREMENT = " . $lastApeId      . ";\n";

$out .= "\nCOMMIT;\n";
$out .= "SET autocommit         = 1;\n";
$out .= "SET FOREIGN_KEY_CHECKS = 1;\n";
$out .= "SET UNIQUE_CHECKS      = 1;\n";

$outPath = __DIR__ . '/../sql/06_seed_5_years.sql';
file_put_contents($outPath, $out);

$bytes = filesize($outPath);
echo "Wrote " . number_format($bytes) . " bytes to $outPath\n";
echo "Stats:\n";
echo "  employees:           " . count($employees)    . "\n";
echo "  patient accounts:    " . count($accounts)     . "\n";
echo "  queue rows:          " . count($queueRows)    . "\n";
echo "  consultations:       " . count($consultRows)  . "\n";
echo "  vital sign rows:     " . count($vitalRows)    . "\n";
echo "  dispenses:           " . count($dispenseRows) . "\n";
echo "  referrals:           " . count($referralRows) . "\n";
echo "  inventory log rows:  " . count($invLogRows)   . "\n";
echo "  APE records:         " . count($apeRows)      . "\n";
