<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Exception;
use Psr\Log\LoggerInterface;

/**
 * Announcements library, playback, TTS, and cron scheduling.
 */
class AnnouncementsService
{
    private string $userFilesPath;
    private string $appRoot;

    /** @var array<string, array<string, string>>|null */
    private ?array $config = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AllStarConfigService $configService
    ) {
        $this->userFilesPath = $this->configService->getUserFilesPath();
        $this->appRoot = dirname($this->userFilesPath);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(?string $username): array
    {
        $config = $this->loadConfig();

        return [
            'files' => $this->listFiles(),
            'nodes' => $this->getNodes($username),
            'config' => [
                'defaults' => [
                    'mode' => $config['defaults']['mode'] ?? 'polite',
                    'scope' => $config['defaults']['scope'] ?? 'local',
                ],
                'scheduling' => [
                    'enabled' => $this->isSchedulingEnabled($config),
                ],
                'presets' => [
                    'minutes' => $this->splitList($config['presets']['minute_presets'] ?? '0,15,30,45'),
                    'hours' => $this->splitList($config['presets']['hour_presets'] ?? '6-11,12-17,17-21,7-20'),
                ],
                'tts' => [
                    'voice' => $config['tts']['voice'] ?? 'en_US-amy-low.onnx',
                ],
                'upload' => [
                    'max_bytes' => (int) ($config['upload']['max_bytes'] ?? 10485760),
                    'allowed_extensions' => $this->splitList(
                        $config['upload']['allowed_extensions'] ?? 'mp3,wav'
                    ),
                ],
            ],
        ];
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function getNodes(?string $username): array
    {
        $nodes = [];
        foreach ($this->configService->getAvailableNodes($username) as $node) {
            $id = (string) $node['id'];
            $system = (string) ($node['system'] ?? 'Nodes');
            $nodes[] = [
                'id' => $id,
                'label' => 'Node ' . $id . ($system !== '' && $system !== 'Nodes' ? ' (' . $system . ')' : ''),
            ];
        }

        return $nodes;
    }

    /**
     * @return list<array{name: string, size: int, modified: int}>
     */
    public function listFiles(): array
    {
        $mp3Dir = $this->getMp3Dir();
        $soundsDir = $this->getSoundsDir();
        $names = [];

        foreach (glob($mp3Dir . '/*.ul') ?: [] as $path) {
            $name = pathinfo($path, PATHINFO_FILENAME);
            if ($this->isValidName($name)) {
                $names[$name] = true;
            }
        }
        if (is_dir($soundsDir)) {
            foreach (glob($soundsDir . '/*.ul') ?: [] as $path) {
                $name = pathinfo($path, PATHINFO_FILENAME);
                if ($this->isValidName($name)) {
                    $names[$name] = true;
                }
            }
        }

        $files = [];
        foreach (array_keys($names) as $name) {
            $libraryPath = $mp3Dir . '/' . $name . '.ul';
            $soundsPath = $soundsDir . '/' . $name . '.ul';
            $path = is_file($libraryPath) ? $libraryPath : $soundsPath;
            if (!is_file($path)) {
                continue;
            }
            $files[] = [
                'name' => $name,
                'size' => (int) filesize($path),
                'modified' => (int) filemtime($path),
            ];
        }

        usort($files, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $files;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{success: bool, message: string}
     */
    public function play(array $params): array
    {
        $node = (string) ($params['node'] ?? '');
        $scope = strtolower((string) ($params['scope'] ?? 'local'));
        $mode = strtolower((string) ($params['mode'] ?? 'polite'));
        $file = (string) ($params['file'] ?? '');

        if (!$this->isValidNode($node)) {
            throw new Exception('Invalid node.');
        }
        if (!in_array($scope, ['local', 'global'], true)) {
            throw new Exception('Invalid scope.');
        }
        if (!in_array($mode, ['polite', 'priority'], true)) {
            throw new Exception('Invalid mode.');
        }
        if (!$this->isValidName($file)) {
            throw new Exception('Invalid announcement file.');
        }
        if (!$this->fileExists($file)) {
            throw new Exception('Announcement file not found.');
        }

        $this->ensureInstalledInSounds($file);

        $prefix = $this->getSoundPrefix();
        $output = $this->runSudoScript('announce-play.sh', [
            '--node', $node,
            '--scope', $scope,
            '--mode', $mode,
            '--file', $prefix . '/' . $file,
        ]);

        return [
            'success' => true,
            'message' => trim(implode("\n", $output)) ?: 'Playback started.',
        ];
    }

    /**
     * @return array{success: bool, message: string, name: string}
     */
    public function upload(string $tmpPath, string $originalName, ?string $requestedName = null): array
    {
        $config = $this->loadConfig();
        $maxBytes = (int) ($config['upload']['max_bytes'] ?? 10485760);
        $allowed = $this->splitList($config['upload']['allowed_extensions'] ?? 'mp3,wav');

        if (!is_file($tmpPath)) {
            throw new Exception('Upload failed.');
        }
        if (filesize($tmpPath) > $maxBytes) {
            throw new Exception('File exceeds maximum upload size.');
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            throw new Exception('File type not allowed.');
        }

        $name = $requestedName !== null && $requestedName !== ''
            ? $requestedName
            : pathinfo($originalName, PATHINFO_FILENAME);
        $name = $this->sanitizeName($name);
        if ($name === '') {
            throw new Exception('Invalid file name.');
        }

        $mp3Dir = $this->getMp3Dir();
        if (!is_dir($mp3Dir) && !mkdir($mp3Dir, 0755, true) && !is_dir($mp3Dir)) {
            throw new Exception('Could not create announcements library directory.');
        }

        $dest = $mp3Dir . '/' . $name . '.' . $ext;
        if (!rename($tmpPath, $dest)) {
            throw new Exception('Could not store uploaded file.');
        }

        $output = $this->runSudoScript('announce-install.sh', [
            '--input', $dest,
            '--name', $name,
            '--mp3-dir', $mp3Dir,
            '--sounds-dir', $this->getSoundsDir(),
        ]);

        return [
            'success' => true,
            'message' => trim(implode("\n", $output)) ?: 'Announcement installed.',
            'name' => $name,
        ];
    }

    /**
     * @return array{success: bool, message: string, name: string}
     */
    public function generateTts(string $text, string $name, string $node, ?string $voice = null): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new Exception('Text is required.');
        }
        if (strlen($text) > 2000) {
            throw new Exception('Text is too long.');
        }

        if (!$this->isValidNode($node)) {
            throw new Exception('Invalid node.');
        }

        $name = $this->sanitizeName($name);
        if ($name === '') {
            throw new Exception('Invalid file name.');
        }

        $mp3Dir = $this->getMp3Dir();
        if (!is_dir($mp3Dir) && !mkdir($mp3Dir, 0755, true) && !is_dir($mp3Dir)) {
            throw new Exception('Could not create announcements library directory.');
        }

        $textFile = tempnam($mp3Dir, 'tts-');
        if ($textFile === false) {
            throw new Exception('Could not create temporary file.');
        }
        file_put_contents($textFile, $text);

        $config = $this->loadConfig();
        $voice = $voice !== null && $voice !== ''
            ? $this->sanitizeVoice($voice)
            : ($config['tts']['voice'] ?? 'en_US-amy-low.onnx');
        $ttsCmd = $config['tts']['command'] ?? 'asl-tts';

        try {
            $output = $this->runSudoScript('announce-tts.sh', [
                '--text-file', $textFile,
                '--name', $name,
                '--node', $node,
                '--voice', $voice,
                '--mp3-dir', $mp3Dir,
                '--sounds-dir', $this->getSoundsDir(),
                '--tts-cmd', $ttsCmd,
            ]);
        } catch (Exception $e) {
            @unlink($textFile);
            throw $e;
        }

        return [
            'success' => true,
            'message' => trim(implode("\n", $output)) ?: 'TTS announcement created.',
            'name' => $name,
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function deleteFile(string $name): array
    {
        $name = $this->sanitizeName($name);
        if ($name === '' || !$this->fileExists($name)) {
            throw new Exception('Announcement file not found.');
        }

        $output = $this->runSudoScript('announce-delete.sh', [
            '--name', $name,
            '--mp3-dir', $this->getMp3Dir(),
            '--sounds-dir', $this->getSoundsDir(),
        ]);

        return [
            'success' => true,
            'message' => trim(implode("\n", $output)) ?: 'Announcement deleted.',
        ];
    }

    /**
     * @return array{
     *   default: string,
     *   regions: list<string>,
     *   voices: list<array{
     *     id: string,
     *     file: string,
     *     label: string,
     *     installed: bool,
     *     catalog: bool,
     *     region?: string,
     *     language?: string,
     *     locale?: string,
     *     quality?: string,
     *     curated?: bool
     *   }>
     * }
     */
    public function getVoices(): array
    {
        $config = $this->loadConfig();
        $default = (string) ($config['tts']['voice'] ?? 'en_US-amy-low.onnx');
        $voicesDir = $this->getVoicesDir();
        $catalogData = $this->loadVoiceCatalogData();
        $catalog = $catalogData['voices'];
        $regions = $catalogData['regions'];
        $installed = $this->listInstalledVoiceFiles($voicesDir);

        $voices = [];
        $seen = [];

        foreach ($catalog as $id => $entry) {
            $file = $id . '.onnx';
            $voices[] = [
                'id' => $id,
                'file' => $file,
                'label' => $entry['label'],
                'installed' => isset($installed[$file]),
                'catalog' => true,
                'region' => $entry['region'],
                'language' => $entry['language'],
                'locale' => $entry['locale'],
                'quality' => $entry['quality'],
                'curated' => $entry['curated'],
            ];
            $seen[$file] = true;
        }

        foreach (array_keys($installed) as $file) {
            if (isset($seen[$file])) {
                continue;
            }
            $id = preg_replace('/\.onnx$/', '', $file) ?? $file;
            $voices[] = [
                'id' => $id,
                'file' => $file,
                'label' => $id . ' (custom)',
                'installed' => true,
                'catalog' => false,
                'region' => 'Other',
                'language' => '',
                'locale' => '',
                'quality' => '',
                'curated' => false,
            ];
        }

        usort($voices, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return [
            'default' => $default,
            'regions' => $regions,
            'voices' => $voices,
        ];
    }

    /**
     * @return array{success: bool, message: string, file: string}
     */
    public function installVoice(string $voiceId): array
    {
        $voiceId = preg_replace('/\.onnx$/', '', trim($voiceId)) ?? '';
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $voiceId)) {
            throw new Exception('Invalid voice.');
        }

        $catalog = $this->loadVoiceCatalog();
        if (!isset($catalog[$voiceId])) {
            throw new Exception('Voice is not available in the catalog.');
        }

        $output = $this->runSudoScript('announce-voice-install.sh', [
            '--voice-id', $voiceId,
            '--voices-dir', $this->getVoicesDir(),
            '--huggingface-path', $catalog[$voiceId]['huggingface_path'],
        ]);

        $file = $voiceId . '.onnx';

        return [
            'success' => true,
            'message' => trim(implode("\n", $output)) ?: 'Voice installed.',
            'file' => $file,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSchedules(): array
    {
        $json = $this->runSudoScriptCapture('announce-schedule.sh', ['list']);
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{success: bool, message: string}
     */
    public function addSchedule(array $params): array
    {
        $this->assertSchedulingEnabled();

        $file = $this->sanitizeName((string) ($params['file'] ?? ''));
        if ($file === '' || !$this->fileExists($file)) {
            throw new Exception('Announcement file not found.');
        }

        $node = (string) ($params['node'] ?? '');
        if (!$this->isValidNode($node)) {
            throw new Exception('Invalid node.');
        }

        $scope = strtolower((string) ($params['scope'] ?? 'local'));
        $mode = strtolower((string) ($params['mode'] ?? 'polite'));
        if (!in_array($scope, ['local', 'global'], true)) {
            throw new Exception('Invalid scope.');
        }
        if (!in_array($mode, ['polite', 'priority'], true)) {
            throw new Exception('Invalid mode.');
        }

        $desc = trim((string) ($params['description'] ?? ''));
        if ($desc === '') {
            throw new Exception('Description is required.');
        }
        if (strlen($desc) > 120 || str_contains($desc, "\n")) {
            throw new Exception('Invalid description.');
        }

        $min = (string) ($params['minute'] ?? '');
        $hour = (string) ($params['hour'] ?? '');
        $dom = (string) ($params['dom'] ?? '*');
        $month = (string) ($params['month'] ?? '*');
        $dow = (string) ($params['dow'] ?? '*');
        $week = (string) ($params['week'] ?? '*');
        $useNth = !empty($params['use_nth']) ? '1' : '0';

        if ($min === '' || $hour === '') {
            throw new Exception('Minute and hour are required.');
        }

        $this->ensureInstalledInSounds($file);

        $output = $this->runSudoScript('announce-schedule.sh', [
            'add',
            '--min', $min,
            '--hour', $hour,
            '--dom', $dom,
            '--month', $month,
            '--dow', $dow,
            '--week', $week,
            '--use-nth', $useNth,
            '--node', $node,
            '--scope', $scope,
            '--mode', $mode,
            '--file', $file,
            '--desc', $desc,
        ]);

        return [
            'success' => true,
            'message' => trim(implode("\n", $output)) ?: 'Schedule added.',
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function toggleSchedule(string $id, bool $enabled): array
    {
        $this->assertSchedulingEnabled();
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            throw new Exception('Invalid schedule id.');
        }

        $output = $this->runSudoScript('announce-schedule.sh', [
            'toggle',
            '--id', $id,
            '--enable', $enabled ? '1' : '0',
        ]);

        return [
            'success' => true,
            'message' => trim(implode("\n", $output)) ?: 'Schedule updated.',
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function deleteSchedule(string $id): array
    {
        $this->assertSchedulingEnabled();
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            throw new Exception('Invalid schedule id.');
        }

        $output = $this->runSudoScript('announce-schedule.sh', [
            'delete',
            '--id', $id,
        ]);

        return [
            'success' => true,
            'message' => trim(implode("\n", $output)) ?: 'Schedule deleted.',
        ];
    }

    private function ensureInstalledInSounds(string $name): void
    {
        $library = $this->getMp3Dir() . '/' . $name . '.ul';
        $sounds = $this->getSoundsDir() . '/' . $name . '.ul';
        if (is_file($library) && !is_file($sounds)) {
            $this->runSudoScript('announce-install.sh', [
                '--input', $library,
                '--name', $name,
                '--mp3-dir', $this->getMp3Dir(),
                '--sounds-dir', $this->getSoundsDir(),
            ]);
        } elseif (is_file($sounds) && !is_file($library)) {
            $this->runSudoScript('announce-install.sh', [
                '--input', $sounds,
                '--name', $name,
                '--mp3-dir', $this->getMp3Dir(),
                '--sounds-dir', $this->getSoundsDir(),
            ]);
        }
    }

    private function fileExists(string $name): bool
    {
        return is_file($this->getMp3Dir() . '/' . $name . '.ul')
            || is_file($this->getSoundsDir() . '/' . $name . '.ul');
    }

    private function assertSchedulingEnabled(): void
    {
        if (!$this->isSchedulingEnabled($this->loadConfig())) {
            throw new Exception('Scheduling is disabled.');
        }
    }

    /**
     * @param array<string, array<string, mixed>> $config
     */
    private function isSchedulingEnabled(array $config): bool
    {
        $val = $config['scheduling']['enabled'] ?? 1;

        if (is_bool($val)) {
            return $val;
        }
        if (is_int($val)) {
            return $val === 1;
        }

        return in_array(strtolower((string) $val), ['1', 'true', 'yes', 'on'], true);
    }

    private function isValidNode(string $node): bool
    {
        return (bool) preg_match('/^[0-9]+$/', $node);
    }

    private function isValidName(string $name): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9._-]+$/', $name);
    }

    private function sanitizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $name) ?? '';
        $name = trim($name, '.-_');

        return $this->isValidName($name) ? $name : '';
    }

    private function sanitizeVoice(string $voice): string
    {
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $voice)) {
            throw new Exception('Invalid voice.');
        }

        return $voice;
    }

    /**
     * @return list<string>
     */
    private function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function loadConfig(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $path = $this->userFilesPath . 'announcements.ini';
        if (!is_file($path)) {
            $this->config = [];

            return $this->config;
        }

        $parsed = parse_ini_file($path, true, INI_SCANNER_TYPED);
        $this->config = is_array($parsed) ? $parsed : [];

        return $this->config;
    }

    private function getMp3Dir(): string
    {
        $config = $this->loadConfig();
        $relative = $config['paths']['mp3_dir'] ?? 'user_files/mp3';

        return $this->resolvePath((string) $relative);
    }

    private function getSoundsDir(): string
    {
        $config = $this->loadConfig();
        $path = $config['paths']['sounds_dir'] ?? '/usr/local/share/asterisk/sounds/announcements';

        return $this->resolvePath((string) $path);
    }

    private function getSoundPrefix(): string
    {
        $config = $this->loadConfig();

        return (string) ($config['paths']['sound_prefix'] ?? 'announcements');
    }

    private function getVoicesDir(): string
    {
        $config = $this->loadConfig();

        return (string) ($config['tts']['voices_dir'] ?? '/var/lib/piper-tts');
    }

    /**
     * @return array<string, array{label: string, huggingface_path: string, region: string, language: string, locale: string, quality: string, curated: bool}>
     */
    private function loadVoiceCatalog(): array
    {
        return $this->loadVoiceCatalogData()['voices'];
    }

    /**
     * @return array{regions: list<string>, voices: array<string, array{label: string, huggingface_path: string, region: string, language: string, locale: string, quality: string, curated: bool}>}
     */
    private function loadVoiceCatalogData(): array
    {
        $defaultRegions = [
            'Americas',
            'Europe',
            'Asia-Pacific',
            'Middle East & Africa',
            'Other',
        ];

        $path = $this->userFilesPath . 'announcement_voices.json';
        if (!is_file($path)) {
            return ['regions' => $defaultRegions, 'voices' => []];
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return ['regions' => $defaultRegions, 'voices' => []];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['voices']) || !is_array($decoded['voices'])) {
            return ['regions' => $defaultRegions, 'voices' => []];
        }

        $regions = $decoded['regions'] ?? $defaultRegions;
        if (!is_array($regions)) {
            $regions = $defaultRegions;
        }

        $catalog = [];
        foreach ($decoded['voices'] as $id => $entry) {
            if (!is_string($id) || !preg_match('/^[a-zA-Z0-9._-]+$/', $id)) {
                continue;
            }
            if (!is_array($entry)) {
                continue;
            }

            $label = trim((string) ($entry['label'] ?? $id));
            $hfPath = trim((string) ($entry['huggingface_path'] ?? ''));
            if ($hfPath === '' || !preg_match('/^[a-zA-Z0-9._\/-]+$/', $hfPath)) {
                continue;
            }

            $region = trim((string) ($entry['region'] ?? 'Other'));
            if ($region === '') {
                $region = 'Other';
            }

            $catalog[$id] = [
                'label' => $label,
                'huggingface_path' => $hfPath,
                'region' => $region,
                'language' => trim((string) ($entry['language'] ?? '')),
                'locale' => trim((string) ($entry['locale'] ?? '')),
                'quality' => trim((string) ($entry['quality'] ?? '')),
                'curated' => !empty($entry['curated']),
            ];
        }

        return [
            'regions' => array_values(array_filter(array_map('strval', $regions))),
            'voices' => $catalog,
        ];
    }

    /**
     * @return array<string, true>
     */
    private function listInstalledVoiceFiles(string $voicesDir): array
    {
        $installed = [];
        if (!is_dir($voicesDir) || !is_readable($voicesDir)) {
            return $installed;
        }

        foreach (glob($voicesDir . '/*.onnx') ?: [] as $path) {
            if (!is_file($path . '.json')) {
                continue;
            }
            $installed[basename($path)] = true;
        }

        return $installed;
    }

    private function resolvePath(string $path): string
    {
        if ($path !== '' && $path[0] === '/') {
            return $path;
        }

        return rtrim($this->appRoot, '/') . '/' . ltrim($path, '/');
    }

    /**
     * @param list<string> $args
     * @return list<string>
     */
    private function runSudoScript(string $scriptName, array $args): array
    {
        $script = $this->userFilesPath . 'sbin/' . $scriptName;
        if (!is_file($script) || !is_executable($script)) {
            throw new Exception('Privileged script is missing or not executable.');
        }

        $command = '/usr/bin/sudo -n ' . escapeshellarg($script);
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg((string) $arg);
        }
        $command .= ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $message = trim(implode("\n", $output));
            $this->logger->error('Announcements sudo script failed', [
                'script' => $scriptName,
                'exit_code' => $exitCode,
                'output' => $message,
            ]);
            throw new Exception($message !== '' ? $message : 'Privileged operation failed.');
        }

        return $output;
    }

    /**
     * @param list<string> $args
     */
    private function runSudoScriptCapture(string $scriptName, array $args): string
    {
        $lines = $this->runSudoScript($scriptName, $args);

        return trim(implode("\n", $lines));
    }
}
