<?php

declare(strict_types=1);

namespace SupermonNg\Services;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Compares installed version (from includes/common.inc) to GitHub's latest release.
 * GitHub API responses are cached for 24 hours; semver comparison runs on every request
 * so the banner clears immediately after an upgrade without waiting for cache expiry.
 */
final class VersionCheckService
{
    private const CACHE_KEY = 'github_release_latest_json_v1';

    private const DEFAULT_REPO = 'hardenedpenguin/supermon-ng';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly IncludeManagerService $includeManager
    ) {
    }

    /**
     * Payload for bootstrap / API (safe to expose to the browser).
     *
     * @return array{
     *   enabled: bool,
     *   installedVersion: string|null,
     *   updateAvailable: bool,
     *   latestVersion: string|null,
     *   latestTag: string|null,
     *   releaseUrl: string|null,
     *   publishedAt: string|null,
     *   checkedAt: string|null,
     *   checkFailed: bool
     * }
     */
    public function getUpdateCheck(): array
    {
        $envFlag = strtolower((string) ($_ENV['UPDATE_CHECK_ENABLED'] ?? 'true'));
        $enabled = !in_array($envFlag, ['false', '0', 'no', 'off'], true);

        $installed = $this->getInstalledSemver();

        $base = [
            'enabled' => $enabled,
            'installedVersion' => $installed,
            'updateAvailable' => false,
            'latestVersion' => null,
            'latestTag' => null,
            'releaseUrl' => null,
            'publishedAt' => null,
            'checkedAt' => date('c'),
            'checkFailed' => false,
        ];

        if (!$enabled) {
            return $base;
        }

        if ($installed === null) {
            $this->logger->notice('Update check skipped: could not parse installed version from common.inc');
            $base['checkFailed'] = true;

            return $base;
        }

        try {
            /** @var array<string, mixed> $remote */
            $remote = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
                $item->expiresAfter(86400);

                return $this->fetchGitHubReleaseData();
            });
        } catch (\Throwable $e) {
            $this->logger->warning('Update check cache failed', ['error' => $e->getMessage()]);
            $base['checkFailed'] = true;

            return $base;
        }

        if (!empty($remote['fetchFailed'])) {
            $base['checkFailed'] = true;

            return $base;
        }

        $latestSemver = isset($remote['latestVersion']) ? (string) $remote['latestVersion'] : null;
        $base['latestVersion'] = $latestSemver;
        $base['latestTag'] = isset($remote['latestTag']) ? (string) $remote['latestTag'] : null;
        $base['releaseUrl'] = isset($remote['releaseUrl']) ? (string) $remote['releaseUrl'] : null;
        $base['publishedAt'] = isset($remote['publishedAt']) ? (string) $remote['publishedAt'] : null;
        $base['checkedAt'] = isset($remote['fetchedAt']) ? (string) $remote['fetchedAt'] : date('c');

        if ($latestSemver !== null && version_compare($latestSemver, $installed, '>')) {
            $base['updateAvailable'] = true;
        }

        return $base;
    }

    public static function parseSemverFromTitle(?string $title): ?string
    {
        if ($title === null || $title === '') {
            return null;
        }
        if (preg_match('/\bV(\d+\.\d+\.\d+)\b/i', $title, $m)) {
            return $m[1];
        }

        return null;
    }

    private function getInstalledSemver(): ?string
    {
        $this->includeManager->includeCommonInc();
        global $TITLE_LOGGED;

        return self::parseSemverFromTitle($TITLE_LOGGED ?? null);
    }

    /**
     * Cached payload: latest release metadata from GitHub (no comparison to local install).
     *
     * @return array{fetchFailed: bool, latestVersion: string|null, latestTag: string|null, releaseUrl: string|null, publishedAt: string|null, fetchedAt: string}
     */
    private function fetchGitHubReleaseData(): array
    {
        $now = date('c');
        $empty = [
            'fetchFailed' => true,
            'latestVersion' => null,
            'latestTag' => null,
            'releaseUrl' => null,
            'publishedAt' => null,
            'fetchedAt' => $now,
        ];

        $repo = trim((string) ($_ENV['GITHUB_REPO'] ?? self::DEFAULT_REPO));
        if ($repo === '' || !preg_match('#^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+$#', $repo)) {
            $repo = self::DEFAULT_REPO;
        }

        $url = 'https://api.github.com/repos/' . $repo . '/releases/latest';
        $raw = $this->httpGetJson($url);

        if ($raw === null) {
            return $empty;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return $empty;
        }

        $tagName = isset($data['tag_name']) ? (string) $data['tag_name'] : '';
        $latestSemver = self::normalizeTagToSemver($tagName);
        $htmlUrl = isset($data['html_url']) ? (string) $data['html_url'] : null;
        $publishedAt = isset($data['published_at']) ? (string) $data['published_at'] : null;

        return [
            'fetchFailed' => false,
            'latestVersion' => $latestSemver,
            'latestTag' => $tagName !== '' ? $tagName : null,
            'releaseUrl' => $htmlUrl,
            'publishedAt' => $publishedAt,
            'fetchedAt' => $now,
        ];
    }

    private static function normalizeTagToSemver(string $tag): ?string
    {
        $t = trim($tag);
        if ($t === '') {
            return null;
        }
        $t = preg_replace('/^v/i', '', $t);
        if (preg_match('/^(\d+\.\d+\.\d+)/', (string) $t, $m)) {
            return $m[1];
        }

        return null;
    }

    private function httpGetJson(string $url): ?string
    {
        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: supermon-ng/' . ($_ENV['API_VERSION'] ?? '1.0.0'),
            'X-GitHub-Api-Version: 2022-11-28',
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code !== 200 || !is_string($body) || $body === '') {
                $this->logger->warning('GitHub release API non-200 or empty', ['code' => $code, 'url' => $url]);

                return null;
            }

            return $body;
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 12,
                'header' => implode("\r\n", $headers) . "\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if (!is_string($body) || $body === '') {
            $this->logger->warning('GitHub release API fetch failed (file_get_contents)', ['url' => $url]);

            return null;
        }

        return $body;
    }
}
