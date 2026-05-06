<?php
declare(strict_types=1);

namespace Nightingale;

/**
 * Session helpers — wraps the PHP session with role-aware guards.
 * Session shape:
 *   [
 *     'auth'       => true,
 *     'role'       => 'nurse'|'patient'|'admin',
 *     'user_id'    => int|null,    // for nurse/patient
 *     'admin_id'   => int|null,    // for admin
 *     'employee_id'=> int|null,
 *     'username'   => string,
 *     'label'      => string,
 *     'initials'   => string,
 *     'login_at'   => int (unix ts),
 *     'last_seen'  => int,
 *   ]
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        $name = $_ENV['SESSION_NAME'] ?? 'NIGHTINGALE_SID';
        session_name($name);

        session_set_cookie_params([
            'lifetime' => (int) ($_ENV['SESSION_LIFETIME_SECONDS'] ?? 28800),
            'path'     => '/',
            'secure'   => ($_ENV['SESSION_COOKIE_SECURE'] ?? '0') === '1',
            'httponly' => true,
            'samesite' => $_ENV['SESSION_COOKIE_SAMESITE'] ?? 'Lax',
        ]);

        session_start();

        // Absolute lifetime check
        $lifetime = (int) ($_ENV['SESSION_LIFETIME_SECONDS'] ?? 28800);
        if (!empty($_SESSION['login_at']) && time() - (int) $_SESSION['login_at'] > $lifetime) {
            self::destroy();
            session_start();
        }

        $_SESSION['last_seen'] = time();

        // Set audit-trigger session variables for this PHP request.
        if (!empty($_SESSION['auth'])) {
            DB::setAuditUser(
                $_SESSION['user_id']  ?? null,
                $_SESSION['admin_id'] ?? null
            );
        }
    }

    public static function login(array $session): void
    {
        self::start();
        // Regenerate ID on privilege change.
        session_regenerate_id(true);
        $_SESSION = array_merge($_SESSION, $session, [
            'auth'      => true,
            'login_at'  => time(),
            'last_seen' => time(),
        ]);
        DB::setAuditUser(
            $_SESSION['user_id']  ?? null,
            $_SESSION['admin_id'] ?? null
        );
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires'  => time() - 4242,
                    'path'     => $params['path']     ?? '/',
                    'domain'   => $params['domain']   ?? '',
                    'secure'   => $params['secure']   ?? false,
                    'httponly' => $params['httponly'] ?? true,
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]);
            }
            session_destroy();
        }
    }

    public static function isAuth(): bool
    {
        self::start();
        return !empty($_SESSION['auth']);
    }

    public static function role(): ?string
    {
        self::start();
        return $_SESSION['role'] ?? null;
    }

    /** Throw 401 / 403 unless user is logged in with one of the given roles. */
    public static function requireRole(string ...$roles): void
    {
        self::start();
        if (empty($_SESSION['auth'])) {
            JsonResponse::send(401, ['error' => 'authentication_required']);
        }
        if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
            JsonResponse::send(403, ['error' => 'forbidden', 'required_role' => $roles]);
        }
    }

    /** Same as requireRole() but for HTML pages — redirects to login.php. */
    public static function requireRoleOrRedirect(string ...$roles): void
    {
        self::start();
        if (empty($_SESSION['auth']) || !in_array($_SESSION['role'] ?? '', $roles, true)) {
            $loginPage = in_array('admin', $roles, true) ? 'admin-login.php' : 'login.php';
            $base = rtrim($_ENV['APP_URL'] ?? '', '/');
            $url  = $base !== '' ? "$base/$loginPage" : $loginPage;
            header("Location: $url", true, 302);
            exit;
        }
    }

    public static function safeUser(): array
    {
        self::start();
        if (empty($_SESSION['auth'])) return [];
        return [
            'role'        => $_SESSION['role']        ?? null,
            'username'    => $_SESSION['username']    ?? null,
            'label'       => $_SESSION['label']       ?? null,
            'initials'    => $_SESSION['initials']    ?? null,
            'employee_id' => $_SESSION['employee_id'] ?? null,
            'user_id'     => $_SESSION['user_id']     ?? null,
            'admin_id'    => $_SESSION['admin_id']    ?? null,
            'login_at'    => $_SESSION['login_at']    ?? null,
        ];
    }
}
