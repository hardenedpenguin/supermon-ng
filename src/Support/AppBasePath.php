<?php

declare(strict_types=1);

namespace SupermonNg\Support;

/**
 * URL path prefix for browser and API URLs.
 *
 * Default install: /supermon-ng under a shared Apache document root.
 * Dedicated vhost (DocumentRoot = public): set APP_BASE_PATH=/ in .env and run
 * scripts/configure-app-base-path.sh (or install/update.sh).
 */
final class AppBasePath
{
    public const INSTALL_SUBDIR = '/supermon-ng';

    /**
     * Normalized path without trailing slash; empty string means site root (dedicated vhost).
     */
    public static function path(): string
    {
        $raw = $_ENV['APP_BASE_PATH'] ?? self::INSTALL_SUBDIR;
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '/') {
            return '';
        }

        return '/' . trim($raw, '/');
    }

    /**
     * Cookie path for PHP sessions (always starts with /).
     */
    public static function cookiePath(): string
    {
        $path = self::path();

        return $path === '' ? '/' : $path;
    }

    /**
     * Vite-style base URL with trailing slash (for reference/documentation).
     */
    public static function viteBase(): string
    {
        $path = self::path();

        return $path === '' ? '/' : $path . '/';
    }

    /**
     * Build a browser URL path (leading slash, includes base prefix).
     */
    public static function url(string $suffix = ''): string
    {
        $suffix = trim($suffix, '/');
        $base = self::path();

        if ($suffix === '') {
            return $base === '' ? '/' : $base;
        }

        return ($base === '' ? '' : $base) . '/' . $suffix;
    }

    /**
     * Strip application (and legacy subdir) prefix from a request URI path.
     */
    public static function stripPrefix(string $uri): string
    {
        $base = self::path();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        } elseif ($base === '' && str_starts_with($uri, self::INSTALL_SUBDIR)) {
            $uri = substr($uri, strlen(self::INSTALL_SUBDIR)) ?: '/';
        }

        return $uri === '' ? '/' : $uri;
    }

    /**
     * Base path for Slim when requests include a subdirectory prefix.
     */
    public static function slimBasePath(): ?string
    {
        $path = self::path();

        return $path === '' ? null : $path;
    }

    public static function isRoot(): bool
    {
        return self::path() === '';
    }

    /**
     * Slim base path for the current HTTP request (configured prefix or /supermon-ng in URL).
     */
    public static function slimBaseForRequest(string $requestPath): ?string
    {
        $configured = self::slimBasePath();
        if ($configured !== null && self::pathStartsWith($requestPath, $configured)) {
            return $configured;
        }

        if (self::pathStartsWith($requestPath, self::INSTALL_SUBDIR)) {
            return self::INSTALL_SUBDIR;
        }

        return null;
    }

    private static function pathStartsWith(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }
}
