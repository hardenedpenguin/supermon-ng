<?php

declare(strict_types=1);

namespace SupermonNg\Tests;

use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use SupermonNg\Services\LocalAllmonGeneratorService;

final class LocalAllmonGeneratorServiceTest extends TestCase
{
    private string $tempDir;
    private LocalAllmonGeneratorService $service;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/smng_allmon_' . uniqid('', true);
        mkdir($this->tempDir . '/sbin', 0755, true);

        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());

        $this->service = new LocalAllmonGeneratorService(
            $logger,
            $this->tempDir . '/',
            '/nonexistent/rpt.conf',
            '/nonexistent/manager.conf'
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
        parent::tearDown();
    }

    public function testCreatesNodeInfoIniWhenMissing(): void
    {
        $result = $this->service->syncNodeInfoIniNodes(['546050', '546051']);

        $this->assertTrue($result['success']);
        $path = $this->tempDir . '/sbin/node_info.ini';
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('NODE = 546050 546051', $content);
        $this->assertStringContainsString('[skywarnplus]', $content);
    }

    public function testUpdatesNodeLineAndPreservesOtherSettings(): void
    {
        $path = $this->tempDir . '/sbin/node_info.ini';
        file_put_contents($path, <<<'INI'
[general]
NODE = 123456
WX_USE_GPS = yes
WX_CODE = 77511
WX_LOCATION = Alvin, Texas
TEMP_UNIT = C

[skywarnplus]
MASTER_ENABLE = no
API_URL = http://127.0.0.1:8100
INI);

        $result = $this->service->syncNodeInfoIniNodes(['546050']);

        $this->assertTrue($result['success']);
        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('NODE = 546050', $content);
        $this->assertStringNotContainsString('123456', $content);
        $this->assertStringContainsString('WX_USE_GPS = yes', $content);
        $this->assertStringContainsString('WX_LOCATION = Alvin, Texas', $content);
        $this->assertStringContainsString('MASTER_ENABLE = no', $content);
    }

    public function testSkipsWriteWhenNodeAlreadyMatches(): void
    {
        $path = $this->tempDir . '/sbin/node_info.ini';
        file_put_contents($path, "[general]\nNODE = 546050\nWX_USE_GPS = no\n");

        $result = $this->service->syncNodeInfoIniNodes(['546050']);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['skipped']);
    }

    public function testWriteAllmonIniSkippedDoesNotTouchNodeInfo(): void
    {
        $allmon = $this->tempDir . '/allmon.ini';
        file_put_contents($allmon, "[546050]\nhost=127.0.0.1:5038\n");

        $nodeInfo = $this->tempDir . '/sbin/node_info.ini';
        file_put_contents($nodeInfo, "[general]\nNODE = 999999\n");

        $result = $this->service->writeAllmonIni('allmon.ini', true, false);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['skipped']);
        $this->assertStringContainsString('NODE = 999999', (string) file_get_contents($nodeInfo));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
