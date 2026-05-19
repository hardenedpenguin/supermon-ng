<?php

declare(strict_types=1);

namespace SupermonNg\Services;

/**
 * Central session and current-user resolution for HTTP API handlers.
 */
final class SessionService
{
    private const SESSION_LIFETIME_SECONDS = 86400;

    public function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('supermon61');
            session_start();
        }
    }

    /**
     * Authenticated username from session, HTTP Basic Auth, or REMOTE_USER.
     */
    public function getCurrentUser(): ?string
    {
        $this->ensureSessionStarted();

        if (isset($_SESSION['user']) && $_SESSION['user'] !== ''
            && isset($_SESSION['authenticated']) && $_SESSION['authenticated']) {
            if (isset($_SESSION['login_time'])
                && (time() - (int) $_SESSION['login_time']) < self::SESSION_LIFETIME_SECONDS) {
                return (string) $_SESSION['user'];
            }
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            return null;
        }

        if (!empty($_SERVER['PHP_AUTH_USER'])) {
            return (string) $_SERVER['PHP_AUTH_USER'];
        }

        if (!empty($_SERVER['REMOTE_USER'])) {
            return (string) $_SERVER['REMOTE_USER'];
        }

        return null;
    }

    public function isAuthenticated(): bool
    {
        return $this->getCurrentUser() !== null;
    }
}
