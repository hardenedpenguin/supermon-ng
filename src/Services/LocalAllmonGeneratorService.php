<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;

/**
 * Builds user_files/allmon.ini from /etc/asterisk/rpt.conf (node stanzas) and
 * manager.conf ([general] bind/port + first non-general stanza with secret).
 */
final class LocalAllmonGeneratorService
{
    public function __construct(
        private LoggerInterface $logger,
        private string $userFilesPath,
        private string $rptConfPath,
        private string $managerConfPath
    ) {
        $this->userFilesPath = rtrim($userFilesPath, '/') . '/';
    }

    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   content?: string,
     *   nodes?: list<string>,
     *   ami?: array{user: string, host: string, port: int}
     * }
     */
    public function generate(): array
    {
        if (!is_readable($this->rptConfPath)) {
            return ['ok' => false, 'error' => 'Cannot read rpt.conf: ' . $this->rptConfPath];
        }
        if (!is_readable($this->managerConfPath)) {
            return ['ok' => false, 'error' => 'Cannot read manager.conf: ' . $this->managerConfPath];
        }

        $nodes = $this->parseRptConfNodeIds($this->rptConfPath);
        if ($nodes === []) {
            return ['ok' => false, 'error' => 'No numeric node stanzas (e.g. [546053] or [546053](tags)) found in ' . $this->rptConfPath];
        }

        $ami = $this->parseManagerAmi($this->managerConfPath);
        if ($ami === null) {
            return ['ok' => false, 'error' => 'No AMI user stanza with secret found after [general] in ' . $this->managerConfPath];
        }

        $content = $this->buildIniContent($nodes, $ami);
        $this->logger->info('Generated local allmon.ini content', [
            'nodes' => $nodes,
            'ami_user' => $ami['user'],
            'host' => $ami['client_host'] . ':' . $ami['port'],
        ]);

        return [
            'ok' => true,
            'content' => $content,
            'nodes' => $nodes,
            'ami' => [
                'user' => $ami['user'],
                'host' => $ami['client_host'],
                'port' => $ami['port'],
            ],
        ];
    }

    /**
     * @return array{
     *   success: bool,
     *   skipped?: bool,
     *   message: string,
     *   path?: string,
     *   nodes?: list<string>
     * }
     */
    public function writeAllmonIni(string $filename, bool $ifMissing, bool $force): array
    {
        $target = $this->userFilesPath . ltrim($filename, '/');
        if ($ifMissing && is_file($target)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'Target exists; skipped (--if-missing)',
                'path' => $target,
            ];
        }
        if (!$ifMissing && !$force && is_file($target)) {
            return [
                'success' => false,
                'message' => 'Target exists; use --force or remove file first',
                'path' => $target,
            ];
        }

        $gen = $this->generate();
        if (!$gen['ok']) {
            return ['success' => false, 'message' => $gen['error'] ?? 'Generation failed'];
        }

        if (!is_dir($this->userFilesPath) && !mkdir($this->userFilesPath, 0755, true) && !is_dir($this->userFilesPath)) {
            return ['success' => false, 'message' => 'Cannot create user_files directory: ' . $this->userFilesPath];
        }

        if ($force && is_file($target)) {
            $bak = $target . '.bak.' . date('YmdHis');
            if (!@copy($target, $bak)) {
                return ['success' => false, 'message' => 'Could not backup existing file to ' . $bak];
            }
        }

        $written = @file_put_contents($target, $gen['content'], LOCK_EX);
        if ($written === false) {
            return ['success' => false, 'message' => 'Failed writing ' . $target];
        }

        return [
            'success' => true,
            'message' => 'Wrote ' . $target,
            'path' => $target,
            'nodes' => $gen['nodes'] ?? [],
        ];
    }

    /**
     * @return list<string>
     */
    public function parseRptConfNodeIds(string $path): array
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }
        $ordered = [];
        $seen = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ';') || str_starts_with($line, '#')) {
                continue;
            }
            // ASL3+ may use [nnnnnn](node-main,allscan-uci) — only require leading [digits]
            if (preg_match('/^\[\s*(\d{4,6})\s*\]/', $line, $m)) {
                $id = $m[1];
                if (!isset($seen[$id])) {
                    $seen[$id] = true;
                    $ordered[] = $id;
                }
            }
        }
        return $ordered;
    }

    /**
     * @return array{
     *   user: string,
     *   secret: string,
     *   client_host: string,
     *   port: int
     * }|null
     */
    public function parseManagerAmi(string $path): ?array
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return null;
        }

        $sections = [];
        $order = [];
        $current = null;

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, ';') || str_starts_with($trim, '#')) {
                continue;
            }
            if (preg_match('/^\[([^\]]+)\]\s*$/', $trim, $m)) {
                $current = trim($m[1]);
                if (!array_key_exists($current, $sections)) {
                    $sections[$current] = [];
                    $order[] = $current;
                }
                continue;
            }
            if ($current === null || !str_contains($trim, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $trim, 2);
            $k = trim($k);
            $v = trim($v, " \t\"'");
            $sections[$current][$k] = $v;
        }

        $general = $sections['general'] ?? [];
        $port = (int) ($general['port'] ?? 5038);
        if ($port < 1 || $port > 65535) {
            $port = 5038;
        }

        $bind = trim((string) ($general['bindaddr'] ?? '127.0.0.1'));
        $clientHost = $this->amiClientHost($bind);

        foreach ($order as $name) {
            if (strcasecmp($name, 'general') === 0) {
                continue;
            }
            $sec = $sections[$name] ?? [];
            $secret = trim((string) ($sec['secret'] ?? ''));
            if ($secret !== '') {
                return [
                    'user' => $name,
                    'secret' => $secret,
                    'client_host' => $clientHost,
                    'port' => $port,
                ];
            }
        }

        return null;
    }

    private function amiClientHost(string $bind): string
    {
        $b = strtolower($bind);
        if ($b === '' || $b === '0.0.0.0' || $b === '*' || $b === '::' || $b === '[::]') {
            return '127.0.0.1';
        }
        if (str_contains($bind, ':') && !str_starts_with($bind, '[')) {
            return '127.0.0.1';
        }
        return $bind;
    }

    /**
     * @param list<string> $nodeIds
     * @param array{user: string, secret: string, client_host: string, port: int} $ami
     */
    private function buildIniContent(array $nodeIds, array $ami): string
    {
        $hostLine = $ami['client_host'] . ':' . $ami['port'];
        $userEsc = $this->iniValue($ami['user']);
        $passEsc = $this->iniValue($ami['secret']);

        $blocks = [];
        $blocks[] = '; Auto-generated by Supermon-NG (LocalAllmonGeneratorService)';
        $blocks[] = '; Nodes from ' . $this->rptConfPath . '; AMI from ' . $this->managerConfPath;
        $blocks[] = '; First non-[general] stanza with secret = AMI user shown below';

        foreach ($nodeIds as $id) {
            $blocks[] = '';
            $blocks[] = '[' . $id . ']';
            $blocks[] = 'host=' . $hostLine;
            $blocks[] = 'user=' . $userEsc;
            $blocks[] = 'passwd=' . $passEsc;
            $blocks[] = 'menu=yes';
            $blocks[] = 'system=Nodes';
            $blocks[] = 'hideNodeURL=no';
        }

        $first = $nodeIds[0];
        $blocks[] = '';
        $blocks[] = '[LsNodes]';
        $blocks[] = 'url="/lsnod/' . $first . '"';
        $blocks[] = 'menu=yes';
        $blocks[] = '';
        $blocks[] = '[ASL3+]';
        $blocks[] = 'default_node=' . $first;

        return implode("\n", $blocks) . "\n";
    }

    private function iniValue(string $v): string
    {
        if ($v === '') {
            return '';
        }
        if (preg_match('/[\s=#;"]/', $v) || str_contains($v, '\\')) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"';
        }
        return $v;
    }
}
