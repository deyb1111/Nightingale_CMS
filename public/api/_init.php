<?php
declare(strict_types=1);

/**
 * Common header for every /api/*.php endpoint.
 * Loads composer autoload, starts the session, sets headers, and
 * applies a method whitelist if the caller passes one.
 */
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Nightingale\JsonResponse;
use Nightingale\Session;

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// Optional helper: api_method('POST') / api_method('GET','POST')
function api_method(string ...$allowed): string
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        JsonResponse::send(405, ['error' => 'method_not_allowed']);
    }
    return $method;
}

Session::start();

set_exception_handler(function (\Throwable $e) {
    error_log('[Nightingale API] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    JsonResponse::serverError($e);
});
