<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;

/**
 * Builds user_files/allmon.ini from /etc/asterisk/rpt.conf (node stanzas) and
 * manager.conf ([general] bind/port + first non-general stanza with secret).
 * When allmon.ini is written, NODE in user_files/sbin/node_info.ini is updated
 * from the same rpt.conf list (not when --if-missing skips an existing allmon.ini).
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

        $result = [
            'success' => true,
            'message' => 'Wrote ' . $target,
            'path' => $target,
            'nodes' => $gen['nodes'] ?? [],
        ];

        $nodeSync = $this->syncNodeInfoIniNodes($gen['nodes'] ?? []);
        if ($nodeSync['success'] && empty($nodeSync['skipped'])) {
            $result['node_info'] = $nodeSync;
            $result['message'] .= '; ' . $nodeSync['message'];
        } elseif (!$nodeSync['success'] && empty($nodeSync['skipped'])) {
            $this->logger->warning('allmon.ini written but node_info.ini NODE sync failed', [
                'error' => $nodeSync['message'],
            ]);
            $result['node_info_warning'] = $nodeSync['message'];
        }

        return $result;
    }

    /**
     * Set NODE in user_files/sbin/node_info.ini to match rpt.conf node list.
     * Called only when allmon.ini was just written (install / --if-missing paths).
     *
     * @param list<string> $nodeIds
     * @return array{success: bool, skipped?: bool, message: string, path?: string, nodes?: list<string>}
     */
    public function syncNodeInfoIniNodes(array $nodeIds): array
    {
        if ($nodeIds === []) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'No nodes to sync to node_info.ini',
            ];
        }

        $target = $this->nodeInfoIniPath();
        $nodeLine = 'NODE = ' . implode(' ', $nodeIds);

        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['success' => false, 'message' => 'Cannot create directory: ' . $dir];
        }

        if (!is_file($target)) {
            $content = $this->defaultNodeInfoIniContent($nodeIds);
            if (@file_put_contents($target, $content, LOCK_EX) === false) {
                return ['success' => false, 'message' => 'Failed writing ' . $target];
            }
            $this->logger->info('Created node_info.ini with NODE from rpt.conf', ['nodes' => $nodeIds]);

            return [
                'success' => true,
                'message' => 'Wrote ' . $target,
                'path' => $target,
                'nodes' => $nodeIds,
            ];
        }

        $raw = @file_get_contents($target);
        if ($raw === false) {
            return ['success' => false, 'message' => 'Cannot read ' . $target];
        }

        $existingNodes = $this->parseNodeLineFromNodeInfo($raw);
        if ($existingNodes === $nodeIds) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'node_info.ini NODE already matches rpt.conf',
                'path' => $target,
                'nodes' => $nodeIds,
            ];
        }

        $updated = $this->applyNodeLineToNodeInfoIni($raw, $nodeLine);

        if (@file_put_contents($target, $updated, LOCK_EX) === false) {
            return ['success' => false, 'message' => 'Failed writing ' . $target];
        }

        $this->logger->info('Updated node_info.ini NODE from rpt.conf', ['nodes' => $nodeIds]);

        return [
            'success' => true,
            'message' => 'Updated NODE in ' . $target,
            'path' => $target,
            'nodes' => $nodeIds,
        ];
    }

    private function nodeInfoIniPath(): string
    {
        return $this->userFilesPath . 'sbin/node_info.ini';
    }

    /**
     * @param list<string> $nodeIds
     */
    private function defaultNodeInfoIniContent(array $nodeIds): string
    {
        return "[general]\n"
            . 'NODE = ' . implode(' ', $nodeIds) . "\n"
            . "WX_USE_GPS = no\n"
            . "WX_CODE = 00000\n"
            . "WX_LOCATION = City, State\n"
            . "TEMP_UNIT = F\n"
            . "ALERT_PROVIDER = skywarnplus\n\n"
            . "[skywarnplus]\n"
            . "MASTER_ENABLE = yes\n"
            . "API_URL = http://127.0.0.1:8100\n\n"
            . "[canwarn_ng]\n"
            . "MASTER_ENABLE = no\n"
            . "API_URL = http://127.0.0.1:8110\n";
    }

    /**
     * Replace or insert NODE = in [general]; preserve all other lines and sections.
     */
    private function applyNodeLineToNodeInfoIni(string $content, string $nodeLine): string
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $out = [];
        $inGeneral = false;
        $nodeHandled = false;
        $hadGeneral = false;

        foreach ($lines as $line) {
            $trim = trim($line);
            if (preg_match('/^\[([^\]]+)\]\s*$/', $trim, $m)) {
                if ($inGeneral && !$nodeHandled) {
                    $out[] = $nodeLine;
                    $nodeHandled = true;
                }
                $inGeneral = strcasecmp($m[1], 'general') === 0;
                if ($inGeneral) {
                    $hadGeneral = true;
                }
                $out[] = $line;
                continue;
            }

            if ($inGeneral && preg_match('/^NODE\s*=/i', $trim)) {
                if (!$nodeHandled) {
                    $out[] = $nodeLine;
                    $nodeHandled = true;
                }
                continue;
            }

            $out[] = $line;
        }

        if ($inGeneral && !$nodeHandled) {
            $out[] = $nodeLine;
            $nodeHandled = true;
        }

        if (!$hadGeneral) {
            $result = "[general]\n{$nodeLine}\n\n" . implode("\n", $out);
        } else {
            $result = implode("\n", $out);
        }

        if ($content !== '' && str_ends_with($content, "\n")) {
            $result .= "\n";
        }

        return $result;
    }

    /**
     * @return list<string>|null
     */
    private function parseNodeLineFromNodeInfo(string $content): ?array
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $inGeneral = false;

        foreach ($lines as $line) {
            $trim = trim($line);
            if (preg_match('/^\[([^\]]+)\]\s*$/', $trim, $m)) {
                $inGeneral = strcasecmp($m[1], 'general') === 0;
                continue;
            }
            if ($inGeneral && preg_match('/^NODE\s*=\s*(.*)$/i', $trim, $m)) {
                $value = trim($m[1]);
                if ($value === '') {
                    return [];
                }

                return array_values(array_filter(preg_split('/\s+/', $value) ?: [], static fn (string $id): bool => $id !== ''));
            }
        }

        return null;
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
        $blocks[] = '[ASL3+]';
        $blocks[] = 'default_node=' . $first;
        $blocks = array_merge($blocks, $this->defaultMenuIniLines($first));

        return implode("\n", $blocks) . "\n";
    }

    /**
     * Default dashboard menu links appended to every generated allmon.ini.
     * Trailing ">" on external URLs opens them in a new tab (see ConfigController menu loader).
     *
     * @return list<string>
     */
    private function defaultMenuIniLines(string $firstNodeId): array
    {
        return [
            '',
            '; --- Dashboard menu links (edit URLs/callsign/coordinates as needed) ---',
            '',
            '[LsNodes]',
            'url="/lsnod/' . $firstNodeId . '"',
            'menu=yes',
            '',
            '[DVSwitch]',
            'url="http://DVSwitch.org/>"',
            'menu=yes',
            '',
            '[D-Star]',
            'url="http://www.DStarinfo.com/reflectors.aspx>"',
            'menu=yes',
            '',
            '[Pi-Star]',
            'url="http://PiStar.uk/>"',
            'menu=yes',
            '',
            '[QRZ]',
            'url="http://QRZ.com/db/W5GLE>"',
            'menu=yes',
            '',
            '[ASL3]',
            'url="https://allstarlink.org>"',
            'menu=yes',
            '',
            '[NWS RADAR]',
            'system=US-Tools',
            'url="https://radar.weather.gov/>"',
            'menu=yes',
            '',
            '[WINDFINDER]',
            'system=US-Tools',
            'url="https://www.windfinder.com/#5/30.0651/-95.198558/spot>"',
            'menu=yes',
            '',
            '[WUNDERGROUND]',
            'system=US-Tools',
            'url="https://www.wunderground.com/wundermap>"',
            'menu=yes',
            '',
            '[USGS]',
            'system=US-Tools',
            'url="https://earthquake.usgs.gov/earthquakes/map>"',
            'menu=yes',
            '',
            '[FIRE INCIDENTS]',
            'system=US-Tools',
            'url="https://www.frontlinewildfire.com/texas-wildfire-map/>"',
            'menu=yes',
            '',
            '[OCEAN BUOY DATA]',
            'system=US-Tools',
            'url="https://www.ndbc.noaa.gov/obs.shtml?lat=30.065171&lon=-95.198558&zoom=7&type=oceans&status=r&pgm=&op=&ls=nA>"',
            'menu=yes',
            '',
            '[GOOGLE MAPS]',
            'system=US-Tools',
            'url="https://www.google.com/maps/place/Texas,+USA>"',
            'menu=yes',
        ];
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
