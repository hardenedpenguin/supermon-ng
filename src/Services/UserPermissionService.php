<?php

declare(strict_types=1);

namespace SupermonNg\Services;

/**
 * Central permission checks against user_files/authusers.inc (same rules as NodeController).
 */
final class UserPermissionService
{
    public function __construct(
        private readonly string $userFilesPath
    ) {
    }

    private function authUsersFile(): string
    {
        return rtrim($this->userFilesPath, '/') . '/authusers.inc';
    }

    /**
     * @param non-empty-string|null $user
     */
    public function hasPermission(?string $user, string $permission): bool
    {
        if ($user === null || $user === '') {
            return false;
        }

        $authFile = $this->authUsersFile();
        if (!is_readable($authFile)) {
            // Fail closed in production; allow open permissions only in non-production dev installs.
            return ($_ENV['APP_ENV'] ?? 'production') !== 'production';
        }

        /** @noinspection PhpUnusedLocalVariableInspection */
        include $authFile;

        if (isset($$permission) && is_array($$permission)) {
            return in_array($user, $$permission, true);
        }

        return false;
    }
}
