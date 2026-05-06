<?php
declare(strict_types=1);

namespace Nightingale;

use OTPHP\TOTP as OTPHPTOTP;

/**
 * RFC 6238 TOTP wrapper with ±1-step (±30 s) clock-drift tolerance,
 * per Nightingale System Reference §04.
 */
final class TOTP
{
    public static function generateSecret(): string
    {
        // 20 random bytes encoded as Base32 (no padding) — Reference §04.
        $bytes = random_bytes(20);
        return self::base32Encode($bytes);
    }

    public static function provisioningUri(string $secret, string $accountName): string
    {
        $issuer = $_ENV['TOTP_ISSUER'] ?? 'Nightingale CMS';
        $totp = OTPHPTOTP::createFromSecret($secret);
        $totp->setLabel($accountName);
        $totp->setIssuer($issuer);
        return $totp->getProvisioningUri();
    }

    public static function verify(string $secret, string $code): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) return false;
        $totp = OTPHPTOTP::createFromSecret($secret);
        // OTPHP "leeway" is in steps; window=1 allows previous and next codes.
        return $totp->verify($code, null, 1);
    }

    public static function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            // 10 hex chars grouped XXXXX-XXXXX
            $a = bin2hex(random_bytes(3));
            $b = bin2hex(random_bytes(3));
            $codes[] = strtoupper(substr($a, 0, 5) . '-' . substr($b, 0, 5));
        }
        return $codes;
    }

    public static function hashBackupCode(string $code): string
    {
        return password_hash(strtoupper(trim($code)), PASSWORD_BCRYPT, ['cost' => 10]);
    }

    public static function verifyBackupCode(string $code, string $hash): bool
    {
        return password_verify(strtoupper(trim($code)), $hash);
    }

    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $bits   = '';
        foreach (str_split($data) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $chunks = str_split($bits, 5);
        foreach ($chunks as $chunk) {
            if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $output .= $alphabet[bindec($chunk)];
        }
        return $output;
    }
}
