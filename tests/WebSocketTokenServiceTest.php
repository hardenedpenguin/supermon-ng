<?php

declare(strict_types=1);

namespace SupermonNg\Tests;

use PHPUnit\Framework\TestCase;
use SupermonNg\Services\WebSocketTokenService;

final class WebSocketTokenServiceTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/smng_wstok_' . uniqid('', true);
        mkdir($this->cacheDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->cacheDir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }
        parent::tearDown();
    }

    public function testIssueAndValidateToken(): void
    {
        $service = new WebSocketTokenService($this->cacheDir);
        $token = $service->issue('546051', 'alice');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $token);

        $data = $service->validate($token, '546051');
        $this->assertIsArray($data);
        $this->assertSame('546051', $data['node_id']);
        $this->assertSame('alice', $data['user']);

        $this->assertNull($service->validate($token, '99999'));
    }
}
