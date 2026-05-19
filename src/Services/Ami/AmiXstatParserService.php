<?php

declare(strict_types=1);

namespace SupermonNg\Services\Ami;

use SupermonNg\Services\AstdbCacheService;

/**
 * Shared parser for RptStatus XStat / SawStat AMI responses.
 */
final class AmiXstatParserService
{
    public const ECHOLINK_NODE_THRESHOLD = 3000000;

    public function parse(string $rptStatus, string $sawStatus): AmiParseResult
    {
        $parsedVars = [];
        $conns = [];
        $keyups = [];
        $modes = [];
        $allLinkedNodes = [];

        if ($rptStatus !== '') {
            foreach (explode("\n", $rptStatus) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                if (str_starts_with($line, 'Var: ')) {
                    $varLine = substr($line, 5);
                    if (str_contains($varLine, '=')) {
                        [$key, $value] = explode('=', $varLine, 2);
                        $parsedVars[trim($key)] = trim($value);
                    }
                    continue;
                }

                if (str_starts_with($line, 'Conn: ')) {
                    $data = preg_split('/\s+/', substr($line, 6));
                    if (!empty($data[0])) {
                        $conns[] = $data;
                    }
                    continue;
                }

                if (preg_match('/LinkedNodes: (.*)/', $line, $matches)) {
                    foreach (preg_split('/, /', trim($matches[1])) as $link) {
                        if ($link === '') {
                            continue;
                        }
                        $nVal = substr($link, 1);
                        $modes[$nVal]['mode'] = substr($link, 0, 1);
                        if (is_numeric($nVal) && (int) $nVal >= 2000) {
                            $allLinkedNodes[] = $nVal;
                        }
                    }
                }
            }
        }

        if ($sawStatus !== '') {
            foreach (explode("\n", $sawStatus) as $line) {
                $line = trim($line);
                if ($line === '' || !str_starts_with($line, 'Conn: ')) {
                    continue;
                }
                $data = preg_split('/\s+/', substr($line, 6));
                if (isset($data[0], $data[1], $data[2], $data[3])) {
                    $keyups[$data[0]] = [
                        'node' => $data[0],
                        'isKeyed' => $data[1],
                        'keyed' => $data[2],
                        'unkeyed' => $data[3],
                    ];
                }
            }
        }

        return new AmiParseResult($parsedVars, $conns, $keyups, $modes, $allLinkedNodes);
    }

    /**
     * WebSocket broadcast payload (flat structure).
     *
     * @param callable(string): string|null $resolveInfo  node id => display info
     * @return array<string, mixed>
     */
    public function buildWebSocketPayload(
        AmiParseResult $parsed,
        string $queriedNode,
        callable $resolveInfo
    ): array {
        $remoteNodes = [];

        foreach ($parsed->conns as $connData) {
            $remoteNodes[] = $this->buildRemoteNodeRow($parsed, $connData, $resolveInfo);
        }

        foreach ($parsed->allLinkedNodes as $linkedNodeId) {
            $exists = false;
            foreach ($remoteNodes as $row) {
                if ((string) $row['node'] === (string) $linkedNodeId) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                continue;
            }
            $remoteNodes[] = $this->buildLinkedOnlyRow($parsed, (string) $linkedNodeId, $resolveInfo);
        }

        return [
            'cos_keyed' => (($parsed->parsedVars['RPT_RXKEYED'] ?? '0') === '1') ? 1 : 0,
            'tx_keyed' => (($parsed->parsedVars['RPT_TXKEYED'] ?? '0') === '1') ? 1 : 0,
            'cpu_temp' => $parsed->parsedVars['cpu_temp'] ?? null,
            'cpu_up' => $parsed->parsedVars['cpu_up'] ?? null,
            'cpu_load' => $parsed->parsedVars['cpu_load'] ?? null,
            'ALERT' => $parsed->parsedVars['ALERT'] ?? null,
            'WX' => $parsed->parsedVars['WX'] ?? null,
            'DISK' => $parsed->parsedVars['DISK'] ?? null,
            'remote_nodes' => $remoteNodes,
        ];
    }

    /**
     * NodeController AMI table structure keyed by node id.
     *
     * @param callable(string): string|null $resolveInfo
     * @return array<int|string, array<string, mixed>>
     */
    public function buildControllerCurNodes(
        AmiParseResult $parsed,
        string $queriedNode,
        callable $resolveInfo
    ): array {
        $curNodes = [];

        foreach ($parsed->conns as $connData) {
            $n = $connData[0];
            if ($n === '') {
                continue;
            }
            $row = $this->buildRemoteNodeRow($parsed, $connData, $resolveInfo);
            $curNodes[$n] = $row;
        }

        foreach ($parsed->allLinkedNodes as $linkedNodeId) {
            if (isset($curNodes[$linkedNodeId])) {
                continue;
            }
            $curNodes[$linkedNodeId] = $this->buildLinkedOnlyRow($parsed, (string) $linkedNodeId, $resolveInfo);
        }

        $localKey = 1;
        if (!isset($curNodes[$localKey])) {
            $curNodes[$localKey] = [];
        }
        $curNodes[$localKey]['node'] = $curNodes[$localKey]['node'] ?? $queriedNode;
        $curNodes[$localKey]['info'] = $curNodes[$localKey]['info'] ?? ($resolveInfo($queriedNode) ?? "Node $queriedNode");
        $curNodes[$localKey]['cos_keyed'] = (($parsed->parsedVars['RPT_RXKEYED'] ?? '0') === '1') ? 1 : 0;
        $curNodes[$localKey]['tx_keyed'] = (($parsed->parsedVars['RPT_TXKEYED'] ?? '0') === '1') ? 1 : 0;
        $curNodes[$localKey]['cpu_temp'] = $parsed->parsedVars['cpu_temp'] ?? null;
        $curNodes[$localKey]['cpu_up'] = $parsed->parsedVars['cpu_up'] ?? null;
        $curNodes[$localKey]['cpu_load'] = $parsed->parsedVars['cpu_load'] ?? null;
        $curNodes[$localKey]['ALERT'] = $parsed->parsedVars['ALERT'] ?? null;
        $curNodes[$localKey]['WX'] = $parsed->parsedVars['WX'] ?? null;
        $curNodes[$localKey]['DISK'] = $parsed->parsedVars['DISK'] ?? null;

        return $curNodes;
    }

    /**
     * @param list<string> $connData
     * @param callable(string): string|null $resolveInfo
     * @return array<string, mixed>
     */
    private function buildRemoteNodeRow(AmiParseResult $parsed, array $connData, callable $resolveInfo): array
    {
        $n = $connData[0];
        $ip = $connData[1] ?? '';
        $direction = $connData[3] ?? '';
        $elapsed = $connData[4] ?? '';
        $status = $connData[5] ?? '';
        $isEcholink = is_numeric($n) && (int) $n > self::ECHOLINK_NODE_THRESHOLD && $ip === '';

        $info = $resolveInfo((string) $n) ?? "Node $n";

        $row = [
            'node' => $n,
            'info' => $info,
            'ip' => $isEcholink ? '' : $ip,
            'direction' => $isEcholink ? ($connData[2] ?? '') : $direction,
            'elapsed' => $isEcholink ? ($connData[3] ?? '') : $elapsed,
            'link' => $isEcholink ? ($connData[4] ?? 'UNKNOWN') : $status,
            'keyed' => 'n/a',
            'last_keyed' => '-1',
            'mode' => $isEcholink ? 'Echolink' : 'Allstar',
        ];

        if ($isEcholink && isset($parsed->modes[$n]['mode'])) {
            $row['link'] = ($parsed->modes[$n]['mode'] === 'C') ? 'CONNECTING' : 'ESTABLISHED';
        }

        if (isset($parsed->keyups[$n])) {
            $row['keyed'] = ($parsed->keyups[$n]['isKeyed'] == 1) ? 'yes' : 'no';
            $row['last_keyed'] = $parsed->keyups[$n]['keyed'];
        }

        if (isset($parsed->modes[$n])) {
            $row['mode'] = $parsed->modes[$n]['mode'];
        }

        return $row;
    }

    /**
     * @param callable(string): string|null $resolveInfo
     * @return array<string, mixed>
     */
    private function buildLinkedOnlyRow(AmiParseResult $parsed, string $linkedNodeId, callable $resolveInfo): array
    {
        $row = [
            'node' => $linkedNodeId,
            'info' => $resolveInfo($linkedNodeId) ?? "Node $linkedNodeId",
            'ip' => 'Indirect',
            'direction' => isset($parsed->modes[$linkedNodeId])
                ? ($parsed->modes[$linkedNodeId]['mode'] === 'T' ? 'OUT' : 'IN')
                : 'unknown',
            'elapsed' => 'unknown',
            'link' => 'LINKED',
            'keyed' => 'n/a',
            'last_keyed' => '-1',
            'mode' => $parsed->modes[$linkedNodeId]['mode'] ?? 'Allstar',
        ];

        if (isset($parsed->keyups[$linkedNodeId])) {
            $row['keyed'] = ($parsed->keyups[$linkedNodeId]['isKeyed'] == 1) ? 'yes' : 'no';
            $row['last_keyed'] = $parsed->keyups[$linkedNodeId]['keyed'];
        }

        return $row;
    }

    /**
     * ASTDB-backed info resolver for WebSocket service.
     */
    public static function astdbInfoResolver(AstdbCacheService $astdb, array $prefetched = []): callable
    {
        return static function (string $nodeId) use ($astdb, $prefetched): string {
            if (isset($prefetched[$nodeId])) {
                $nodeInfo = $prefetched[$nodeId];
            } else {
                $nodeInfo = $astdb->getNodeInfo($nodeId);
            }
            if (!$nodeInfo) {
                return "Node $nodeId";
            }
            return trim(
                ($nodeInfo['callsign'] ?? '') . ' '
                . ($nodeInfo['description'] ?? '') . ' '
                . ($nodeInfo['location'] ?? '')
            );
        };
    }
}
