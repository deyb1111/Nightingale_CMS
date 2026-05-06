<?php
declare(strict_types=1);

namespace Nightingale;

/**
 * Common helpers used by every API endpoint:
 *  - record login attempts
 *  - simple per-username/IP rate limiter (5 failed attempts per 15 min)
 *  - signed pending-TOTP token (5-minute lifetime)
 *  - initials helper for the topbar avatar
 */
final class Auth
{
    public const MAX_FAILED_PER_WINDOW   = 5;
    public const RATE_LIMIT_WINDOW_MINUTES = 15;
    public const PENDING_TOTP_TTL         = 300; // 5 minutes

    public static function recordAttempt(string $username, string $portal, bool $success): void
    {
        DB::run(
            "INSERT INTO login_attempts
               (username, portal, ip_address, user_agent, succeeded)
             VALUES (?, ?, ?, ?, ?)",
            [
                substr($username, 0, 60),
                $portal,
                self::clientIp(),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                $success ? 1 : 0,
            ]
        );
    }

    public static function isRateLimited(string $username): bool
    {
        $stmt = DB::run(
            "SELECT COUNT(*) AS c
               FROM login_attempts
               WHERE username    = ?
                 AND succeeded   = 0
                 AND attempted_at >= NOW() - INTERVAL ? MINUTE",
            [$username, self::RATE_LIMIT_WINDOW_MINUTES]
        );
        $row = $stmt->fetch();
        return ((int) ($row['c'] ?? 0)) >= self::MAX_FAILED_PER_WINDOW;
    }

    /**
     * Produce a short-lived HMAC-signed token that proves the password
     * step succeeded.  Carries the user_id (or admin_id) and portal
     * across the two-step nurse/admin login flow.
     */
    public static function issuePendingTotp(array $payload): string
    {
        $payload['exp'] = time() + self::PENDING_TOTP_TTL;
        $body  = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
        $sig   = hash_hmac('sha256', $body, self::secret());
        return $body . '.' . $sig;
    }

    public static function consumePendingTotp(string $token): array
    {
        if (!str_contains($token, '.')) {
            JsonResponse::badRequest('pending_token_invalid');
        }
        [$body, $sig] = explode('.', $token, 2);
        $expected = hash_hmac('sha256', $body, self::secret());
        if (!hash_equals($expected, $sig)) {
            JsonResponse::badRequest('pending_token_invalid');
        }
        $payload = json_decode((string) base64_decode($body, true), true);
        if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) {
            JsonResponse::badRequest('pending_token_expired');
        }
        return $payload;
    }

    public static function initials(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
        if (empty($parts)) return '--';
        if (count($parts) === 1) return strtoupper(substr($parts[0], 0, 2));
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
    }

    public static function clientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    private static function secret(): string
    {
        $key = $_ENV['APP_KEY'] ?? '';
        if ($key === '') $key = 'INSECURE-DEFAULT-CHANGE-ME-IN-ENV';
        return $key;
    }
}
