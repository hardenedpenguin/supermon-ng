<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;

final class SetupService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AppPathService $paths,
        private readonly LocalAllmonGeneratorService $allmonGenerator
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        $userFiles = $this->paths->userFiles();
        $htpasswd = $userFiles . '.htpasswd';
        $setupFlag = $userFiles . '.setup_complete';
        $userCount = $this->countHtpasswdUsers($htpasswd);
        $nodeCount = $this->countAllmonNodes($userFiles . 'allmon.ini');

        $needsSetup = false;
        $reasons = [];

        if ($userCount === 0) {
            $needsSetup = true;
            $reasons[] = 'no_users';
        }
        if ($nodeCount === 0) {
            $needsSetup = true;
            $reasons[] = 'no_nodes';
        }
        if (!is_file($setupFlag) && ($userCount === 0 || $nodeCount === 0)) {
            $needsSetup = true;
        }

        return [
            'needs_setup' => $needsSetup,
            'reasons' => $reasons,
            'user_count' => $userCount,
            'node_count' => $nodeCount,
            'setup_complete' => is_file($setupFlag),
            'can_generate_allmon' => is_readable('/etc/asterisk/rpt.conf'),
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function createAdminUser(string $username, string $password): array
    {
        $status = $this->getStatus();
        if (!$status['needs_setup'] && $status['user_count'] > 0) {
            return ['success' => false, 'message' => 'Setup is already complete'];
        }

        if (!preg_match('/^[a-zA-Z0-9._-]{2,32}$/', $username)) {
            return ['success' => false, 'message' => 'Invalid username'];
        }
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters'];
        }

        $userFiles = $this->paths->userFiles();
        $htpasswd = $userFiles . '.htpasswd';
        $hash = $this->hashPassword($username, $password);
        $line = $username . ':' . $hash . PHP_EOL;

        if (file_put_contents($htpasswd, $line, LOCK_EX) === false) {
            return ['success' => false, 'message' => 'Could not write .htpasswd'];
        }
        chmod($htpasswd, 0644);

        $this->provisionAuthusers($userFiles . 'authusers.inc', $username);
        $this->logger->info('Setup wizard created admin user', ['username' => $username]);

        return ['success' => true, 'message' => 'Admin user created'];
    }

    /**
     * @return array{success: bool, message: string, nodes?: list<string>}
     */
    public function generateAllmon(bool $force = false): array
    {
        $result = $this->allmonGenerator->writeAllmonIni('allmon.ini', false, $force);
        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message']];
        }

        return [
            'success' => true,
            'message' => $result['message'],
            'nodes' => $result['nodes'] ?? [],
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function markComplete(): array
    {
        $flag = $this->paths->userFiles() . '.setup_complete';
        file_put_contents($flag, json_encode(['completed_at' => date('c')], JSON_PRETTY_PRINT));

        return ['success' => true, 'message' => 'Setup marked complete'];
    }

    private function countHtpasswdUsers(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return $lines === false ? 0 : count($lines);
    }

    private function countAllmonNodes(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }
        $content = file_get_contents($path);
        if ($content === false) {
            return 0;
        }
        preg_match_all('/^\[(\d+)\]/m', $content, $matches);

        return count($matches[1] ?? []);
    }

    private function hashPassword(string $username, string $password): string
    {
        $cmd = 'htpasswd -nbB ' . escapeshellarg($username) . ' ' . escapeshellarg($password) . ' 2>/dev/null';
        $output = shell_exec($cmd);
        if (is_string($output) && str_contains($output, ':')) {
            $parts = explode(':', trim($output), 2);

            return $parts[1] ?? password_hash($password, PASSWORD_BCRYPT);
        }

        return password_hash($password, PASSWORD_BCRYPT);
    }

    private function provisionAuthusers(string $path, string $username): void
    {
        if (!is_file($path)) {
            return;
        }
        $content = file_get_contents($path);
        if ($content === false) {
            return;
        }

        $replacements = ['anarchy', 'testuser'];
        foreach ($replacements as $old) {
            $content = str_replace('"' . $old . '"', '"' . $username . '"', $content);
        }

        file_put_contents($path, $content);
    }
}
