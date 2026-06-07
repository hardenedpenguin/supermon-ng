<?php

declare(strict_types=1);

namespace SupermonNg\Services;

/**
 * Resolves application and user_files paths from environment.
 */
final class AppPathService
{
    public function appRoot(): string
    {
        return rtrim($_ENV['APP_ROOT'] ?? dirname(__DIR__, 2), '/');
    }

    public function userFiles(): string
    {
        $path = $_ENV['USER_FILES_PATH'] ?? $this->appRoot() . '/user_files';

        return rtrim($path, '/') . '/';
    }

    public function envFile(): string
    {
        return $this->appRoot() . '/.env';
    }
}
