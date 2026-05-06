<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use Nightingale\Session;

Session::start();
if (Session::isAuth()) {
    $route = ['nurse' => 'nurse.php', 'patient' => 'patient.php', 'admin' => 'admin.php'];
    header('Location: ' . ($route[Session::role()] ?? 'login.php'));
    exit;
}
header('Location: login.php');
exit;
