<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Psr\Http\Message\ResponseInterface as Response;

/**
 * Consistent JSON responses and safe error messages for API handlers.
 */
final class ApiResponseHelper
{
    public static function isProduction(): bool
    {
        return ($_ENV['APP_ENV'] ?? 'production') === 'production';
    }

    public static function safeExceptionMessage(\Throwable $e): string
    {
        if (!self::isProduction()) {
            return $e->getMessage();
        }

        return 'An error occurred';
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($payload));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param array<string, mixed> $extra
     */
    public static function error(
        Response $response,
        string $message,
        int $status = 500,
        array $extra = []
    ): Response {
        return self::json($response, array_merge([
            'success' => false,
            'message' => $message,
        ], $extra), $status);
    }
}
