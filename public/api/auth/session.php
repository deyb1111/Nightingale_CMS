<?php
declare(strict_types=1);
require __DIR__ . '/../_init.php';

use Nightingale\CSRF;
use Nightingale\JsonResponse;
use Nightingale\Session;

api_method('GET');

if (!Session::isAuth()) {
    JsonResponse::ok(['authenticated' => false]);
}

JsonResponse::ok([
    'authenticated' => true,
    'user'          => Session::safeUser(),
    'csrf_token'    => CSRF::token(),
]);
