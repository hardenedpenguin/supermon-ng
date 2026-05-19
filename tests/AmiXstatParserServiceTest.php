<?php

declare(strict_types=1);

namespace SupermonNg\Tests;

use PHPUnit\Framework\TestCase;
use SupermonNg\Services\Ami\AmiXstatParserService;

final class AmiXstatParserServiceTest extends TestCase
{
    public function testParseExtractsVarsAndConnections(): void
    {
        $parser = new AmiXstatParserService();
        $rpt = "Var: RPT_RXKEYED=1\nVar: cpu_temp=42C\nConn: 12345 10.0.0.1 4569 OUT 0:01:00 ESTABLISHED\n";
        $saw = "Conn: 12345 1 100 200\n";

        $parsed = $parser->parse($rpt, $saw);

        $this->assertSame('1', $parsed->parsedVars['RPT_RXKEYED']);
        $this->assertSame('42C', $parsed->parsedVars['cpu_temp']);
        $this->assertCount(1, $parsed->conns);
        $this->assertSame('12345', $parsed->conns[0][0]);
        $this->assertArrayHasKey('12345', $parsed->keyups);
    }

    public function testBuildWebSocketPayloadIncludesRemoteNodes(): void
    {
        $parser = new AmiXstatParserService();
        $rpt = "Var: RPT_TXKEYED=0\nConn: 99999 1.2.3.4 4569 IN 0:00:05 ESTABLISHED\n";
        $parsed = $parser->parse($rpt, '');

        $payload = $parser->buildWebSocketPayload(
            $parsed,
            '546051',
            static fn (string $id): string => "Info for $id"
        );

        $this->assertSame(0, $payload['tx_keyed']);
        $this->assertCount(1, $payload['remote_nodes']);
        $this->assertSame('99999', $payload['remote_nodes'][0]['node']);
        $this->assertSame('Info for 99999', $payload['remote_nodes'][0]['info']);
    }
}
