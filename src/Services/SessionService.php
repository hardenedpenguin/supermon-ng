<?php

declare(strict_types=1);

namespace SupermonNg\Services;

/**
 * Central session and current-user resolution for HTTP API handlers.
 */
final class SessionService
{
    private const SESSION_LIFETIME_SECONDS = 86400;

    private HtpasswdService $htpasswd;

    public function __construct(?HtpasswdService $htpasswd = null)
    {
        $this->htpasswd = $htpasswd ?? new HtpasswdService();
    }

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

        // HTTP Basic Auth: PHP populates PHP_AUTH_USER from the client's
        // Authorization header even when the web server never verified it, so
        // treat it as authenticated only after re-checking the password against
        // .htpasswd. This closes the header-spoofing bypass while preserving
        // legitimate Basic-auth deployments (where the password is present).
        if (!empty($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            if ($this->htpasswd->verify((string) $_SERVER['PHP_AUTH_USER'], (string) $_SERVER['PHP_AUTH_PW'])) {
                return (string) $_SERVER['PHP_AUTH_USER'];
            }
        }

        // REMOTE_USER is set by the web server only after it authenticated the
        // request (it is not client-settable via a request header), so it is
        // safe to trust when present.
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
