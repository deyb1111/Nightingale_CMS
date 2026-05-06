<?php
declare(strict_types=1);
require __DIR__ . '/../_init.php';

use Nightingale\JsonResponse;
use Nightingale\Session;

api_method('POST');
Session::destroy();
JsonResponse::ok(['stage' => 'logged_out']);
