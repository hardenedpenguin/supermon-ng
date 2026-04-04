#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate user_files/allmon.ini from Asterisk rpt.conf + manager.conf.
 *
 * Uses the first non-[general] manager stanza that defines secret (file order).
 * AMI host is [general] bindaddr + port; 0.0.0.0 / empty bind uses 127.0.0.1 for the client.
 *
 * Usage:
 *   php scripts/generate_local_allmon.php --dry-run
 *   php scripts/generate_local_allmon.php --if-missing
 *   php scripts/generate_local_allmon.php --force
 *
 * One of --dry-run, --if-missing, or --force is required.
 * With both --if-missing and --force, an existing file is left unchanged (if-missing wins).
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);

require_once $root . '/vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use SupermonNg\Services\LocalAllmonGeneratorService;

$hasDry = in_array('--dry-run', $argv, true);
$hasIfMissing = in_array('--if-missing', $argv, true);
$hasForce = in_array('--force', $argv, true);

if (!$hasDry && !$hasIfMissing && !$hasForce) {
    fwrite(STDERR, "Usage: php scripts/generate_local_allmon.php (--dry-run | --if-missing | --force)\n");
    fwrite(STDERR, "  --dry-run     Print INI to stdout; do not write\n");
    fwrite(STDERR, "  --if-missing  Write user_files/allmon.ini only if it does not exist\n");
    fwrite(STDERR, "  --force       Write allmon.ini; backup existing to .bak.<timestamp>\n");
    fwrite(STDERR, "If both --if-missing and --force are set, an existing file is not overwritten.\n");
    exit(1);
}

$_ENV['USER_FILES_PATH'] = $_ENV['USER_FILES_PATH'] ?? $root . '/user_files/';
$rpt = $_ENV['ASTERISK_RPT_CONF'] ?? '/etc/asterisk/rpt.conf';
$mgr = $_ENV['ASTERISK_MANAGER_CONF'] ?? '/etc/asterisk/manager.conf';

$logger = new Logger('generate-local-allmon');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));

$service = new LocalAllmonGeneratorService(
    $logger,
    $_ENV['USER_FILES_PATH'],
    $rpt,
    $mgr
);

if ($hasDry) {
    $gen = $service->generate();
    if (!$gen['ok']) {
        fwrite(STDERR, $gen['error'] . "\n");
        exit(1);
    }
    echo $gen['content'];
    exit(0);
}

$ifMissing = $hasIfMissing;
$force = $hasForce && !$hasIfMissing;
$result = $service->writeAllmonIni('allmon.ini', $ifMissing, $force);

if (!empty($result['skipped'])) {
    fwrite(STDERR, $result['message'] . "\n");
    exit(0);
}

if (!$result['success']) {
    fwrite(STDERR, $result['message'] . "\n");
    exit(1);
}

fwrite(STDERR, $result['message'] . "\n");
if (!empty($result['nodes'])) {
    fwrite(STDERR, 'Nodes: ' . implode(', ', $result['nodes']) . "\n");
}
exit(0);
