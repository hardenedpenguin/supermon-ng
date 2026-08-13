<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use ZipArchive;

final class ConfigBackupService
{
    private const ROOT_FILES = [
        'allmon.ini',
        'authusers.inc',
        'authini.inc',
        'favorites.ini',
        'favini.inc',
        'privatenodes.txt',
        'controlpanel.ini',
        'global.inc',
        '.htpasswd',
        '.setup_complete',
        '.setup_global_saved',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AppPathService $paths
    ) {
    }

    /**
     * @return array{success: bool, path?: string, filename?: string, message?: string}
     */
    public function createExportArchive(): array
    {
        if (!class_exists(ZipArchive::class)) {
            return ['success' => false, 'message' => 'PHP zip extension is not available'];
        }

        $userFiles = $this->paths->userFiles();
        $tmp = tempnam(sys_get_temp_dir(), 'smng-backup-');
        if ($tmp === false) {
            return ['success' => false, 'message' => 'Could not create temporary file'];
        }

        $zipPath = $tmp . '.zip';
        rename($tmp, $zipPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['success' => false, 'message' => 'Could not open zip archive'];
        }

        foreach (self::ROOT_FILES as $file) {
            $full = $userFiles . $file;
            if (is_file($full) && is_readable($full)) {
                $this->addFileToZip($zip, $full, 'user_files/' . $file);
            }
        }

        $this->addDirectoryToZip($zip, $userFiles . 'preferences', 'user_files/preferences');
        $this->addDirectoryToZip($zip, $userFiles . 'sbin', 'user_files/sbin');

        foreach (glob($userFiles . 'dvswitch_config*.yml') ?: [] as $yml) {
            $this->addFileToZip($zip, $yml, 'user_files/' . basename($yml));
        }

        foreach (glob($userFiles . '*-allmon.ini') ?: [] as $ini) {
            $this->addFileToZip($zip, $ini, 'user_files/' . basename($ini));
        }

        $envFile = $this->paths->envFile();
        if (is_file($envFile) && is_readable($envFile)) {
            $this->addFileToZip($zip, $envFile, '.env');
        }

        $manifest = [
            'created_at' => date('c'),
            'version' => '1',
            'files' => self::ROOT_FILES,
        ];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        if (!$zip->close()) {
            @unlink($zipPath);

            return ['success' => false, 'message' => 'Could not finalize zip archive'];
        }

        $filename = 'supermon-ng-config-' . date('Ymd-His') . '.zip';

        return [
            'success' => true,
            'path' => $zipPath,
            'filename' => $filename,
        ];
    }

    /**
     * @return array{success: bool, message: string, restored?: list<string>}
     */
    public function importArchive(string $zipPath): array
    {
        if (!class_exists(ZipArchive::class)) {
            return ['success' => false, 'message' => 'PHP zip extension is not available'];
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['success' => false, 'message' => 'Invalid or unreadable zip archive'];
        }

        $userFiles = $this->paths->userFiles();
        /** @var list<array{target: string, contents: string, restored: string}> $pending */
        $pending = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name)) {
                continue;
            }

            $normalized = $this->normalizeArchivePath($name);
            if ($normalized === null) {
                continue;
            }

            if (str_starts_with($normalized, 'user_files/')) {
                $relative = substr($normalized, strlen('user_files/'));
                if ($relative === '' || str_ends_with($normalized, '/')) {
                    continue;
                }
                $first = strtolower(explode('/', $relative, 2)[0]);
                if ($first === 'sbin') {
                    $this->logger->warning('Skipped sbin path during config restore', ['path' => $relative]);
                    continue;
                }
                $contents = $zip->getFromIndex($i);
                if ($contents === false) {
                    continue;
                }
                if (str_ends_with(strtolower($relative), '.inc')) {
                    try {
                        $this->assertSafePhpConfig($contents);
                    } catch (\Exception $e) {
                        $zip->close();

                        return ['success' => false, 'message' => "Unsafe PHP in {$relative}: " . $e->getMessage()];
                    }
                }
                $pending[] = [
                    'target' => $userFiles . $relative,
                    'contents' => $contents,
                    'restored' => 'user_files/' . $relative,
                ];
            } elseif ($normalized === '.env') {
                $contents = $zip->getFromIndex($i);
                if ($contents !== false) {
                    $pending[] = [
                        'target' => $this->paths->envFile(),
                        'contents' => $contents,
                        'restored' => '.env',
                    ];
                }
            }
        }

        $backupDir = sys_get_temp_dir() . '/supermon-ng-restore-backup-' . date('Ymd-His');
        if (!mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
            $zip->close();

            return ['success' => false, 'message' => 'Could not create pre-restore backup directory'];
        }

        $this->backupCurrentConfig($backupDir);
        $restored = [];

        foreach ($pending as $entry) {
            $dir = dirname($entry['target']);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                $zip->close();

                return ['success' => false, 'message' => "Could not create directory for {$entry['restored']}"];
            }
            file_put_contents($entry['target'], $entry['contents']);
            $restored[] = $entry['restored'];
        }

        $zip->close();
        $this->logger->info('Configuration restored from archive', [
            'restored_count' => count($restored),
            'backup_dir' => $backupDir,
        ]);

        return [
            'success' => true,
            'message' => 'Configuration restored successfully',
            'restored' => $restored,
            'pre_restore_backup' => $backupDir,
        ];
    }

    /**
     * Normalize zip entry paths: forward slashes, drop ".", reject "..".
     */
    private function normalizeArchivePath(string $name): ?string
    {
        $name = str_replace('\\', '/', $name);
        $parts = [];
        foreach (explode('/', $name) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return null;
            }
            $parts[] = $part;
        }

        return $parts === [] ? null : implode('/', $parts);
    }

    private function backupCurrentConfig(string $backupDir): void
    {
        $userFiles = $this->paths->userFiles();
        $target = $backupDir . '/user_files';
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        foreach (self::ROOT_FILES as $file) {
            $src = $userFiles . $file;
            if (is_file($src)) {
                copy($src, $target . '/' . $file);
            }
        }
    }

    private function addDirectoryToZip(ZipArchive $zip, string $dir, string $zipPrefix): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            $relative = substr($path, strlen($dir) + 1);
            $this->addFileToZip($zip, $path, $zipPrefix . '/' . $relative);
        }
    }

    private function addFileToZip(ZipArchive $zip, string $path, string $zipName): void
    {
        if (!is_readable($path)) {
            return;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return;
        }

        $zip->addFromString($zipName, $contents);
    }

    /**
     * Same rules as ConfigController::assertSafePhpConfig — .inc files are included as PHP.
     */
    private function assertSafePhpConfig(string $content): void
    {
        $reject = function (string $what): void {
            throw new \Exception(
                "This file is loaded as PHP code by Supermon-ng, so only simple " .
                "configuration is allowed (variable assignments of strings, numbers, " .
                "and arrays, plus comments). Refusing to restore: found $what."
            );
        };

        $allowedChars = ['=', ';', ',', '(', ')', '[', ']', '.', '-', '+', '"'];
        $allowedWords = ['array', 'true', 'false', 'null'];
        $tokens = token_get_all($content);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_string($token)) {
                if (!in_array($token, $allowedChars, true)) {
                    $reject("'" . $token . "'");
                }
                continue;
            }

            [$id, $text] = $token;
            if ($id === T_VARIABLE) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $next = $tokens[$j];
                    if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }
                    if ($next === '(') {
                        $reject('variable function call');
                    }
                    break;
                }
            }
            switch ($id) {
                case T_OPEN_TAG:
                case T_CLOSE_TAG:
                case T_WHITESPACE:
                case T_COMMENT:
                case T_DOC_COMMENT:
                case T_VARIABLE:
                case T_CONSTANT_ENCAPSED_STRING:
                case T_ENCAPSED_AND_WHITESPACE:
                case T_LNUMBER:
                case T_DNUMBER:
                case T_ARRAY:
                case T_DOUBLE_ARROW:
                    break;
                case T_STRING:
                    if (!in_array(strtolower($text), $allowedWords, true)) {
                        $reject("'" . $text . "'");
                    }
                    break;
                case T_INLINE_HTML:
                    if (trim($text) !== '') {
                        $reject('content outside the PHP open/close tags');
                    }
                    break;
                default:
                    $reject("'" . trim($text) . "' (" . token_name($id) . ")");
            }
        }
    }
}
