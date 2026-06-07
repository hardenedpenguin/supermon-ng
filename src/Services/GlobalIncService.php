<?php

declare(strict_types=1);

namespace SupermonNg\Services;

final class GlobalIncService
{
    private const PLACEHOLDER_CALLS = [
        'YOURCALL',
        'YOUR_CALLSIGN',
        'YOUR-CALL',
        'NOCALL',
    ];

    /** @var array<string, string> API field => PHP variable name */
    private const FIELD_VARS = [
        'call' => 'CALL',
        'name' => 'NAME',
        'location' => 'LOCATION',
        'title2' => 'TITLE2',
        'title3' => 'TITLE3',
        'sm_server_name' => 'SMSERVERNAME',
        'welcome_msg' => 'WELCOME_MSG',
        'welcome_msg_logged' => 'WELCOME_MSG_LOGGED',
        'background_color' => 'BACKGROUND_COLOR',
        'background_height' => 'BACKGROUND_HEIGHT',
        'display_background' => 'DISPLAY_BACKGROUND',
        'callsign_color' => 'CALLSIGN_COLOR',
        'dvm_url' => 'DVM_URL',
        'my_url' => 'MY_URL',
    ];

    /** @var list<string> */
    private const OPTIONAL_VARS = [
        'TITLE2',
        'TITLE3',
        'CALLSIGN_COLOR',
        'MY_URL',
        'DVM_URL',
    ];

    public function __construct(
        private readonly AppPathService $paths
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $parsed = $this->parseFile($this->loadSourceContent());
        $data = $this->defaults();

        foreach (self::FIELD_VARS as $field => $var) {
            if (!isset($parsed[$var])) {
                continue;
            }
            $entry = $parsed[$var];
            $value = $entry['value'];
            if ($field === 'location') {
                $value = $this->stripLocationHtml($value);
            } else {
                $value = trim($value);
            }
            $data[$field] = $value;
        }

        foreach (self::OPTIONAL_VARS as $var) {
            $field = array_search($var, self::FIELD_VARS, true);
            if ($field === false) {
                continue;
            }
            $data[$field . '_enabled'] = isset($parsed[$var]) && !$parsed[$var]['commented'];
        }

        return $data;
    }

    public function isConfigured(): bool
    {
        $call = strtoupper((string) ($this->read()['call'] ?? ''));

        return $call !== '' && !in_array($call, self::PLACEHOLDER_CALLS, true);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: bool, message: string}
     */
    public function write(array $input): array
    {
        $call = strtoupper(trim((string) ($input['call'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $location = trim((string) ($input['location'] ?? ''));

        if ($call === '' || !preg_match('/^[A-Z0-9\/-]{3,12}$/', $call)) {
            return ['success' => false, 'message' => 'Enter a valid callsign (3–12 characters)'];
        }
        if ($name === '') {
            return ['success' => false, 'message' => 'Operator name is required'];
        }
        if ($location === '') {
            return ['success' => false, 'message' => 'Location is required'];
        }

        $content = $this->loadSourceContent();
        $parsed = $this->parseFile($content);
        $lines = preg_split('/\R/', $content);
        if ($lines === false) {
            return ['success' => false, 'message' => 'Could not read global.inc'];
        }

        $smServerName = trim((string) ($input['sm_server_name'] ?? '')) ?: 'Supermon-ng';

        $welcomeMsg = trim((string) ($input['welcome_msg'] ?? ''));
        if ($welcomeMsg === '') {
            $welcomeMsg = '<p style=\'margin-top:1em;\'><center><b>Welcome to '
                . $this->escapeHtml($call) . ' Supermon-ng</b></center></p>';
        }

        $welcomeMsgLogged = trim((string) ($input['welcome_msg_logged'] ?? ''));
        if ($welcomeMsgLogged === '') {
            $welcomeMsgLogged = '<p style=\'margin-top:1em;\'><center><b>Welcome back, '
                . $this->escapeHtml($name) . '!</b></center></p>';
        }

        $locationRaw = $parsed['LOCATION']['value_raw'] ?? null;
        $locationValue = $this->buildLocationValue($location, is_string($locationRaw) ? $locationRaw : null);

        $required = [
            'CALL' => $call,
            'NAME' => $name,
            'LOCATION' => $locationValue,
            'SMSERVERNAME' => $smServerName,
            'WELCOME_MSG' => $welcomeMsg,
            'WELCOME_MSG_LOGGED' => $welcomeMsgLogged,
            'BACKGROUND_COLOR' => trim((string) ($input['background_color'] ?? 'black')) ?: 'black',
            'BACKGROUND_HEIGHT' => trim((string) ($input['background_height'] ?? '164px')) ?: '164px',
            'DISPLAY_BACKGROUND' => trim((string) ($input['display_background'] ?? 'black')) ?: 'black',
        ];

        foreach ($required as $var => $value) {
            $lines = $this->patchVariable($lines, $var, $value, true);
        }

        $optional = [
            'TITLE2' => [
                'field' => 'title2',
                'default' => 'ASL3+ Management Dashboard',
            ],
            'TITLE3' => [
                'field' => 'title3',
                'default' => 'AllStarLink/IRLP/EchoLink/Digital - Bridging Control Center',
            ],
            'CALLSIGN_COLOR' => [
                'field' => 'callsign_color',
                'default' => '#00ff00',
            ],
            'MY_URL' => [
                'field' => 'my_url',
                'default' => 'http://yourwebsite.org/',
            ],
            'DVM_URL' => [
                'field' => 'dvm_url',
                'default' => '../dvswitch',
            ],
        ];

        foreach ($optional as $var => $meta) {
            $field = $meta['field'];
            $enabled = !empty($input[$field . '_enabled']);
            $value = trim((string) ($input[$field] ?? ''));
            if ($value === '' && isset($parsed[$var])) {
                $value = $parsed[$var]['value'];
            }
            if ($value === '') {
                $value = $meta['default'];
            }
            $active = $enabled && trim($value) !== '';
            $lines = $this->patchVariable($lines, $var, $value, $active);
        }

        $path = $this->globalIncPath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['success' => false, 'message' => 'Could not create user_files directory'];
        }

        if (is_file($path)) {
            $bak = $path . '.bak.' . date('YmdHis');
            @copy($path, $bak);
        }

        $output = implode(PHP_EOL, $lines);
        if (!str_ends_with($output, PHP_EOL)) {
            $output .= PHP_EOL;
        }

        if (file_put_contents($path, $output, LOCK_EX) === false) {
            return ['success' => false, 'message' => 'Could not write global.inc'];
        }

        chmod($path, 0644);

        return ['success' => true, 'message' => 'Site configuration saved to global.inc'];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'call' => '',
            'name' => '',
            'location' => '',
            'title2' => 'ASL3+ Management Dashboard',
            'title3' => 'AllStarLink/IRLP/EchoLink/Digital - Bridging Control Center',
            'title2_enabled' => true,
            'title3_enabled' => true,
            'sm_server_name' => 'Supermon-ng',
            'welcome_msg' => '',
            'welcome_msg_logged' => '',
            'background_color' => 'black',
            'background_height' => '164px',
            'display_background' => 'black',
            'callsign_color' => '',
            'callsign_color_enabled' => false,
            'dvm_url' => '',
            'dvm_url_enabled' => false,
            'my_url' => '',
            'my_url_enabled' => false,
        ];
    }

    private function loadSourceContent(): string
    {
        $path = $this->globalIncPath();
        if (is_file($path)) {
            $content = file_get_contents($path);
            if ($content !== false) {
                return $content;
            }
        }

        $example = $this->examplePath();
        if (is_file($example)) {
            $content = file_get_contents($example);
            if ($content !== false) {
                return $content;
            }
        }

        return "<?php\n?>\n";
    }

    /**
     * @return array<string, array{commented: bool, value: string, value_raw: string, indent: string}>
     */
    private function parseFile(string $content): array
    {
        $parsed = [];
        $lines = preg_split('/\R/', $content);
        if ($lines === false) {
            return $parsed;
        }

        foreach ($lines as $line) {
            $assignment = $this->parsePhpAssignmentLine($line);
            if ($assignment === null) {
                continue;
            }
            $parsed[$assignment['var']] = [
                'commented' => $assignment['commented'],
                'value' => $assignment['value'],
                'value_raw' => $assignment['value_raw'],
                'indent' => $assignment['indent'],
            ];
        }

        return $parsed;
    }

    /**
     * @return array{indent: string, commented: bool, var: string, value_raw: string, value: string}|null
     */
    private function parsePhpAssignmentLine(string $line): ?array
    {
        if (!preg_match('/^(\s*)(?:(\/\/\s*))?\$([A-Z_][A-Z0-9_]*)\s*=\s*(.+);\s*$/', $line, $matches)) {
            return null;
        }

        return [
            'indent' => $matches[1],
            'commented' => $matches[2] !== '',
            'var' => $matches[3],
            'value_raw' => $matches[4],
            'value' => $this->decodePhpString($matches[4]),
        ];
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function patchVariable(array $lines, string $var, string $value, bool $active): array
    {
        $found = false;
        foreach ($lines as $index => $line) {
            $assignment = $this->parsePhpAssignmentLine($line);
            if ($assignment === null || $assignment['var'] !== $var) {
                continue;
            }

            $lines[$index] = $this->formatAssignmentLine(
                $assignment['indent'],
                $var,
                $value,
                !$active
            );
            $found = true;
            break;
        }

        if (!$found && $active) {
            $lines = $this->insertAssignment($lines, $var, $value);
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function insertAssignment(array $lines, string $var, string $value): array
    {
        $insertAt = count($lines);
        foreach ($lines as $index => $line) {
            if (trim($line) === '?>') {
                $insertAt = $index;
                break;
            }
        }

        array_splice(
            $lines,
            $insertAt,
            0,
            [$this->formatAssignmentLine('', $var, $value, false)]
        );

        return $lines;
    }

    private function formatAssignmentLine(string $indent, string $var, string $value, bool $commented): string
    {
        $assignment = '$' . $var . ' = ' . $this->encodePhpString($value) . ';';
        if ($commented) {
            $prefix = $indent !== '' ? $indent : '';
            return $prefix . '// ' . ltrim($assignment);
        }

        return $indent . $assignment;
    }

    private function decodePhpString(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/^"(.*)"$/s', $raw, $matches)) {
            return stripcslashes($matches[1]);
        }
        if (preg_match("/^'(.*)'$/s", $raw, $matches)) {
            return str_replace(["\\'", '\\\\'], ["'", '\\'], $matches[1]);
        }

        return $raw;
    }

    private function encodePhpString(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private function buildLocationValue(string $plainLocation, ?string $existingRaw): string
    {
        $existing = $existingRaw !== null ? $this->decodePhpString($existingRaw) : '';
        if (preg_match('/<span[^>]*style\s*=\s*["\']([^"\']*)["\'][^>]*>/i', $existing, $matches)) {
            return '<span style="' . $matches[1] . '">' . $this->escapeHtml($plainLocation) . '</span>';
        }

        return '<span style="color: #00ff00">' . $this->escapeHtml($plainLocation) . '</span>';
    }

    private function globalIncPath(): string
    {
        return $this->paths->userFiles() . 'global.inc';
    }

    private function examplePath(): string
    {
        return $this->paths->userFiles() . 'global.inc.example';
    }

    private function stripLocationHtml(string $location): string
    {
        $text = strip_tags($location);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($text);
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
