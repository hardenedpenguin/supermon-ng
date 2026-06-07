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
        $backupDir = sys_get_temp_dir() . '/supermon-ng-restore-backup-' . date('Ymd-His');
        if (!mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
            $zip->close();

            return ['success' => false, 'message' => 'Could not create pre-restore backup directory'];
        }

        $this->backupCurrentConfig($backupDir);
        $restored = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name) || str_contains($name, '..')) {
                continue;
            }

            if (str_starts_with($name, 'user_files/')) {
                $relative = substr($name, strlen('user_files/'));
                if ($relative === '' || str_ends_with($name, '/')) {
                    continue;
                }
                $target = $userFiles . $relative;
                $dir = dirname($target);
                if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                    $zip->close();

                    return ['success' => false, 'message' => "Could not create directory for {$relative}"];
                }
                $contents = $zip->getFromIndex($i);
                if ($contents === false) {
                    continue;
                }
                file_put_contents($target, $contents);
                $restored[] = 'user_files/' . $relative;
            } elseif ($name === '.env') {
                $contents = $zip->getFromIndex($i);
                if ($contents !== false) {
                    file_put_contents($this->paths->envFile(), $contents);
                    $restored[] = '.env';
                }
            }
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
}
