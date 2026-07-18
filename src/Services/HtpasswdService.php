<?php

declare(strict_types=1);

namespace SupermonNg\Services;

/**
 * Verifies credentials against the user_files/.htpasswd file.
 *
 * Centralized so both interactive login (AuthController) and per-request
 * identity resolution (SessionService) share one implementation and cannot
 * drift apart. Supports the hash formats htpasswd/manage_users.php can emit:
 * bcrypt ($2y$), Apache MD5 ($apr1$), {SHA}, plain hex MD5, and legacy
 * plaintext.
 */
final class HtpasswdService
{
    private string $htpasswdFile;

    public function __construct(?string $userFilesPath = null)
    {
        $path = $userFilesPath
            ?? $_ENV['USER_FILES_PATH']
            ?? (dirname(__DIR__, 2) . '/user_files/');
        $this->htpasswdFile = rtrim($path, '/') . '/.htpasswd';
    }

    /**
     * True only when the username exists in .htpasswd and the password matches.
     */
    public function verify(string $username, string $password): bool
    {
        if ($username === '' || $password === '') {
            return false;
        }
        if (!is_file($this->htpasswdFile) || !is_readable($this->htpasswdFile)) {
            return false;
        }

        $lines = file($this->htpasswdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            if (trim($parts[0]) === $username) {
                return $this->verifyPassword($password, trim($parts[1]));
            }
        }

        return false;
    }

    public function verifyPassword(string $password, string $storedHash): bool
    {
        if (strpos($storedHash, '$2y$') === 0 || strpos($storedHash, '$2a$') === 0) {
            return password_verify($password, $storedHash);
        }

        if (strpos($storedHash, '$apr1$') === 0) {
            return $this->verifyApacheMd5($password, $storedHash);
        }

        if (strpos($storedHash, '{SHA}') === 0) {
            $hash = base64_encode(sha1($password, true));
            return hash_equals($storedHash, '{SHA}' . $hash);
        }

        if (strlen($storedHash) === 32 && ctype_xdigit($storedHash)) {
            return hash_equals($storedHash, md5($password));
        }

        // Legacy plaintext entries (discouraged; kept for backward compatibility).
        return hash_equals($storedHash, $password);
    }

    private function verifyApacheMd5(string $password, string $storedHash): bool
    {
        if (!preg_match('/^\$apr1\$([a-zA-Z0-9\/\.]{1,8})\$/', $storedHash, $matches)) {
            return false;
        }

        return hash_equals($storedHash, $this->apacheMd5($password, $matches[1]));
    }

    private function apacheMd5(string $password, string $salt): string
    {
        $len = strlen($password);
        $text = $password . '$apr1$' . $salt;
        $bin = pack('H32', md5($password . $salt . $password));

        for ($i = $len; $i > 0; $i -= 16) {
            $text .= substr($bin, 0, min(16, $i));
        }

        for ($i = $len; $i > 0; $i >>= 1) {
            $text .= ($i & 1) ? chr(0) : $password[0];
        }

        $bin = pack('H32', md5($text));

        for ($i = 0; $i < 1000; $i++) {
            $new = ($i & 1) ? $password : $bin;
            if ($i % 3) {
                $new .= $salt;
            }
            if ($i % 7) {
                $new .= $password;
            }
            $new .= ($i & 1) ? $bin : $password;
            $bin = pack('H32', md5($new));
        }

        $tmp = '';
        for ($i = 0; $i < 5; $i++) {
            $k = $i + 6;
            $j = $i + 12;
            if ($j == 16) {
                $j = 5;
            }
            $tmp = $bin[$i] . $bin[$k] . $bin[$j] . $tmp;
        }

        $tmp = chr(0) . chr(0) . $bin[11] . $tmp;
        $tmp = strtr(
            strrev(substr(base64_encode($tmp), 2)),
            'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/',
            './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'
        );

        return '$apr1$' . $salt . '$' . $tmp;
    }
}
