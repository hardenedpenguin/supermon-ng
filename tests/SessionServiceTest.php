<?php

declare(strict_types=1);

namespace SupermonNg\Tests;

use PHPUnit\Framework\TestCase;
use SupermonNg\Services\SessionService;

final class SessionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        parent::tearDown();
    }

    public function testUnauthenticatedWhenSessionEmpty(): void
    {
        $service = new SessionService();
        $this->assertNull($service->getCurrentUser());
        $this->assertFalse($service->isAuthenticated());
    }

    public function testAuthenticatedWhenSessionValid(): void
    {
        session_name('supermon61');
        session_start();
        $_SESSION['user'] = 'testuser';
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();

        $service = new SessionService();
        $this->assertSame('testuser', $service->getCurrentUser());
        $this->assertTrue($service->isAuthenticated());
    }
}
