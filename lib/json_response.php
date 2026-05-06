<?php
declare(strict_types=1);

namespace Nightingale;

final class JsonResponse
{
    public static function send(int $status, mixed $body): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok(mixed $body = ['ok' => true]): never
    {
        self::send(200, $body);
    }

    public static function badRequest(string $message, array $extra = []): never
    {
        self::send(400, array_merge(['error' => $message], $extra));
    }

    public static function unauthorized(string $message = 'authentication_required'): never
    {
        self::send(401, ['error' => $message]);
    }

    public static function forbidden(string $message = 'forbidden'): never
    {
        self::send(403, ['error' => $message]);
    }

    public static function serverError(\Throwable $e): never
    {
        $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
        $body  = ['error' => 'server_error'];
        if ($debug) {
            $body['message'] = $e->getMessage();
            $body['trace']   = explode("\n", $e->getTraceAsString());
        }
        self::send(500, $body);
    }

    /** Decode incoming JSON request body into an array. */
    public static function readJsonInput(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') return [];
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            self::badRequest('invalid_json', ['detail' => $e->getMessage()]);
        }
        return is_array($decoded) ? $decoded : [];
    }
}
