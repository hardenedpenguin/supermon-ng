<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;

final class ConfigImportService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AppPathService $paths
    ) {
    }

    /**
     * Import AllScan-style favorites (label/cmd pairs) into favorites.ini [general].
     *
     * @return array{success: bool, message: string, imported?: int}
     */
    public function importAllScanFavorites(string $content): array
    {
        $entries = $this->parseLabelCmdPairs($content);
        if ($entries === []) {
            return ['success' => false, 'message' => 'No label/cmd pairs found in import file'];
        }

        $path = $this->paths->userFiles() . 'favorites.ini';
        $existing = is_file($path) ? (string) file_get_contents($path) : '';
        $block = PHP_EOL . '; Imported from AllScan' . PHP_EOL;
        foreach ($entries as $entry) {
            $block .= 'label[] = "' . $this->escapeIni($entry['label']) . '"' . PHP_EOL;
            $block .= 'cmd[] = "' . $this->escapeIni($entry['cmd']) . '"' . PHP_EOL;
        }

        if ($existing === '') {
            $existing = "; Supermon-ng Favorites Configuration\n[general]\n";
        } elseif (!str_contains($existing, '[general]')) {
            $existing .= "\n[general]\n";
        }

        file_put_contents($path, $existing . $block);
        $this->logger->info('Imported AllScan favorites', ['count' => count($entries)]);

        return [
            'success' => true,
            'message' => 'Imported ' . count($entries) . ' favorite(s)',
            'imported' => count($entries),
        ];
    }

    /**
     * Merge Allmon3-style node stanzas into allmon.ini.
     *
     * @return array{success: bool, message: string, imported?: int, nodes?: list<string>}
     */
    public function importAllmon3Nodes(string $content): array
    {
        $stanzas = $this->parseAllmon3Stanzas($content);
        if ($stanzas === []) {
            return ['success' => false, 'message' => 'No node stanzas found in import file'];
        }

        $path = $this->paths->userFiles() . 'allmon.ini';
        $existing = is_file($path) ? (string) file_get_contents($path) : '';
        $append = '';
        $nodes = [];

        foreach ($stanzas as $nodeId => $fields) {
            if (preg_match('/\[' . preg_quote($nodeId, '/') . '\]/', $existing)) {
                continue;
            }

            $append .= PHP_EOL . '[' . $nodeId . ']' . PHP_EOL;
            foreach ($fields as $key => $value) {
                $append .= $key . '=' . $value . PHP_EOL;
            }
            $nodes[] = $nodeId;
        }

        if ($nodes === []) {
            return ['success' => false, 'message' => 'All nodes from import already exist in allmon.ini'];
        }

        file_put_contents($path, $existing . $append);
        $this->logger->info('Imported Allmon3 nodes', ['nodes' => $nodes]);

        return [
            'success' => true,
            'message' => 'Imported ' . count($nodes) . ' node(s) into allmon.ini',
            'imported' => count($nodes),
            'nodes' => $nodes,
        ];
    }

    /**
     * @return list<array{label: string, cmd: string}>
     */
    private function parseLabelCmdPairs(string $content): array
    {
        $entries = [];
        $labels = [];
        $cmds = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ';') || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^label\[\]\s*=\s*["\']?(.+?)["\']?\s*$/i', $line, $m)) {
                $labels[] = trim($m[1], '"\'');
            } elseif (preg_match('/^cmd\[\]\s*=\s*["\']?(.+?)["\']?\s*$/i', $line, $m)) {
                $cmds[] = trim($m[1], '"\'');
            } elseif (preg_match('/^label\s*=\s*["\']?(.+?)["\']?\s*$/i', $line, $m)) {
                $labels[] = trim($m[1], '"\'');
            } elseif (preg_match('/^cmd\s*=\s*["\']?(.+?)["\']?\s*$/i', $line, $m)) {
                $cmds[] = trim($m[1], '"\'');
            }
        }

        $count = min(count($labels), count($cmds));
        for ($i = 0; $i < $count; $i++) {
            $entries[] = ['label' => $labels[$i], 'cmd' => $cmds[$i]];
        }

        return $entries;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function parseAllmon3Stanzas(string $content): array
    {
        $stanzas = [];
        $current = null;
        $fields = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, ';') || str_starts_with($trim, '#')) {
                continue;
            }

            if (preg_match('/^\[(\d+)\]/', $trim, $m)) {
                if ($current !== null && $fields !== []) {
                    $stanzas[$current] = $fields;
                }
                $current = $m[1];
                $fields = [];
                continue;
            }

            if ($current !== null && str_contains($trim, '=')) {
                [$key, $value] = array_map('trim', explode('=', $trim, 2));
                $key = strtolower($key);
                if (in_array($key, ['host', 'user', 'passwd', 'menu', 'system', 'hidenodeurl'], true)) {
                    $fields[$key] = $value;
                }
            }
        }

        if ($current !== null && $fields !== []) {
            $stanzas[$current] = $fields;
        }

        return $stanzas;
    }

    private function escapeIni(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
