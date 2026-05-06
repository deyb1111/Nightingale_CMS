<?php
declare(strict_types=1);

namespace Nightingale;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Thin PDO singleton with project defaults applied:
 *  - utf8mb4 connection
 *  - exception error mode
 *  - associative fetch
 *  - real prepared statements (no client-side emulation)
 */
final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = $_ENV['DB_HOST']     ?? '127.0.0.1';
        $port = (int) ($_ENV['DB_PORT'] ?? 3306);
        $name = $_ENV['DB_NAME']     ?? 'nightingale_cms';
        $user = $_ENV['DB_USER']     ?? 'cms_app_user';
        $pass = $_ENV['DB_PASSWORD'] ?? '';

        $dsn  = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND =>
                    "SET sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO," .
                    "NO_ENGINE_SUBSTITUTION'",
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        return self::$pdo;
    }

    /** Convenience prepared-statement runner. */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Set the audit-trigger session variables for the duration of
     * this DB connection.  Pass either a user_id (nurse/patient)
     * or admin_id; never both.
     */
    public static function setAuditUser(?int $userId, ?int $adminId): void
    {
        $pdo = self::pdo();
        $pdo->exec("SET @current_user_id  = " . ($userId  ?: 'NULL'));
        $pdo->exec("SET @current_admin_id = " . ($adminId ?: 'NULL'));
    }
}
