<?php

declare(strict_types=1);

namespace SupermonNg\Tests;

use PHPUnit\Framework\TestCase;
use SupermonNg\Services\UserPermissionService;

final class UserPermissionServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/smng_test_' . uniqid('', true);
        mkdir($this->tempDir, 0755, true);
        $_ENV['APP_ENV'] = 'production';
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function testDeniesPermissionWhenAuthFileMissingInProduction(): void
    {
        $service = new UserPermissionService($this->tempDir . '/');
        $this->assertFalse($service->hasPermission('anyuser', 'CONNECTUSER'));
    }

    public function testGrantsPermissionWhenUserListed(): void
    {
        file_put_contents(
            $this->tempDir . '/authusers.inc',
            "<?php\n\$CONNECTUSER = array('alice');\n"
        );
        $service = new UserPermissionService($this->tempDir . '/');
        $this->assertTrue($service->hasPermission('alice', 'CONNECTUSER'));
        $this->assertFalse($service->hasPermission('bob', 'CONNECTUSER'));
    }
}
