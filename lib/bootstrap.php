<?php
/**
 * Bootstrap file — auto-loaded via composer.json "files" section.
 * Loads .env, sets timezone, and configures the session cookie.
 */
declare(strict_types=1);

if (defined('NIGHTINGALE_BOOTSTRAPPED')) {
    return;
}
define('NIGHTINGALE_BOOTSTRAPPED', true);

define('NIGHTINGALE_ROOT', dirname(__DIR__));

// Load .env (vlucas/phpdotenv)
if (is_file(NIGHTINGALE_ROOT . '/vendor/autoload.php')
    && class_exists(\Dotenv\Dotenv::class)
    && is_file(NIGHTINGALE_ROOT . '/.env')) {
    \Dotenv\Dotenv::createImmutable(NIGHTINGALE_ROOT)->safeLoad();
}

date_default_timezone_set('Asia/Manila');

// Default session cookie params (overridden in lib/session.php on session_start).
ini_set('session.use_strict_mode',  '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly',  '1');
ini_set('session.cookie_samesite',  $_ENV['SESSION_COOKIE_SAMESITE'] ?? 'Lax');
ini_set('session.cookie_secure',    ($_ENV['SESSION_COOKIE_SECURE'] ?? '0') === '1' ? '1' : '0');
ini_set('session.gc_maxlifetime',   (string) ((int) ($_ENV['SESSION_LIFETIME_SECONDS'] ?? 28800)));
