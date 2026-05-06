<?php
declare(strict_types=1);

namespace Nightingale;

/**
 * Per-session CSRF token.  Sent to JS via /api/auth/session and
 * echoed back via the X-CSRF-Token header on every state-changing
 * request.
 */
final class CSRF
{
    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function require(): void
    {
        Session::start();
        $expected = $_SESSION['csrf_token'] ?? null;
        $actual   = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$expected || !hash_equals($expected, $actual)) {
            JsonResponse::send(419, ['error' => 'csrf_token_invalid']);
        }
    }
}
