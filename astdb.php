#! /usr/bin/php -q
<?php

define('APP_DIR', "/var/www/html/supermon-ng/");
define('ASTDB_FILE', APP_DIR . "astdb.txt");
define('PRIVATE_NODES_FILE', APP_DIR . "user_files/privatenodes.txt");
define('ALLSTAR_DB_URL', "http://allmondb.allstarlink.org/");

define('MIN_DB_SIZE_BYTES', 300000);
define('MAX_RETRIES', 5);
define('RETRY_SLEEP_SECONDS', 5);
define('HTTP_TIMEOUT_SECONDS', 20);

define('CRON_MAX_DELAY_SECONDS', 1800);

$privateNodesContent = '';
$allstarNodesContent = '';

function script_log(string $message): void {
    echo "[" . date("Y-m-d H:i:s") . "] " . $message . "\n";
}

if (file_exists(PRIVATE_NODES_FILE)) {
    $lines = @file(PRIVATE_NODES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        script_log("Warning: Could not read private nodes file: " . PRIVATE_NODES_FILE);
    } else {
        $validLines = [];
        foreach ($lines as $idx => $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 4 && trim($parts[0]) !== '') {
                $validLines[] = $line;
            } else {
                script_log("Warning: Skipping malformed private node entry on line " . ($idx+1) . ": $line");
            }
        }
        $privateNodesContent = implode("\n", $validLines) . (count($validLines) ? "\n" : "");
        script_log("Loaded " . strlen($privateNodesContent) . " bytes from valid entries in " . PRIVATE_NODES_FILE);
    }
}

$isStrictlyPrivate = (bool) getenv('PRIVATE_NODE');

if (!$isStrictlyPrivate) {
    script_log("Not a strictly private node setup. Will attempt to fetch public Allstar DB.");

    if (isset($argv[1]) && $argv[1] === 'cron') {
        $delaySeconds = mt_rand(0, CRON_MAX_DELAY_SECONDS);
        if ($delaySeconds > 0) {
            script_log("Cron mode: Waiting for $delaySeconds seconds before fetching...");
            sleep($delaySeconds);
        }
    }

    $streamContext = stream_context_create([
        'http' => [
            'timeout' => HTTP_TIMEOUT_SECONDS,
            'ignore_errors' => true
        ]
    ]);

    for ($attempt = 1; $attempt <= MAX_RETRIES; $attempt++) {
        script_log("Attempt $attempt of " . MAX_RETRIES . " to fetch Allstar DB from " . ALLSTAR_DB_URL);
        $currentContent = file_get_contents(ALLSTAR_DB_URL, false, $streamContext);
        $http_response_header = $http_response_header ?? [];

        if ($currentContent !== false) {
            $contentLength = strlen($currentContent);
            $statusLine = $http_response_header[0] ?? '';
            preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
            $statusCode = isset($match[1]) ? (int)$match[1] : 0;

            if ($statusCode >= 200 && $statusCode < 300 && $contentLength >= MIN_DB_SIZE_BYTES) {
                $allstarNodesContent = $currentContent;
                script_log("Successfully fetched Allstar DB: $contentLength bytes.");
                break;
            } else {
                $reason = "";
                if (!($statusCode >= 200 && $statusCode < 300)) {
                    $reason .= "HTTP status $statusCode. ";
                }
                if ($contentLength < MIN_DB_SIZE_BYTES) {
                    $reason .= "File too small ($contentLength bytes, minimum " . MIN_DB_SIZE_BYTES . "). ";
                }
                script_log("Fetch failed: $reason");
            }
        } else {
            $lastError = error_get_last();
            $errorMsg = $lastError ? $lastError['message'] : "Unknown error";
            script_log("Fetch failed: Could not connect or read from URL. Error: $errorMsg");
        }

        if ($attempt < MAX_RETRIES) {
            script_log("Will retry in " . RETRY_SLEEP_SECONDS . " seconds...");
            sleep(RETRY_SLEEP_SECONDS);
        } else {
            script_log("Max retries exceeded for fetching Allstar DB. Proceeding without it.");
        }
    }
} else {
    script_log("Strictly private node setup. Skipping public Allstar DB fetch.");
}

$finalContent = $privateNodesContent . $allstarNodesContent;

$finalContent = preg_replace('/[\x00-\x09\x0B-\x0C\x0E-\x1F\x7F-\xFF]/', '', $finalContent);

if (empty($finalContent) && $isStrictlyPrivate && empty($privateNodesContent)) {
    script_log("No data to write (strictly private mode and no private nodes found/loaded). " . ASTDB_FILE . " will not be updated.");
} elseif (empty($finalContent) && !$isStrictlyPrivate) {
    script_log("No data to write (public fetch failed and no private nodes). " . ASTDB_FILE . " will not be updated to prevent data loss.");
} else {
    $fh = fopen(ASTDB_FILE, 'w');
    if ($fh === false) {
        die("Fatal: Cannot open output file for writing: " . ASTDB_FILE . ". Check permissions.\n");
    }

    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        die("Fatal: Unable to obtain exclusive lock on " . ASTDB_FILE . ". Another process might be active.\n");
    }

    if (fwrite($fh, $finalContent) === false) {
        flock($fh, LOCK_UN);
        fclose($fh);
        die("Fatal: Cannot write to " . ASTDB_FILE . ". Disk full or permissions issue?\n");
    }

    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    $bytesWritten = strlen($finalContent);
    script_log("Success: " . ASTDB_FILE . " updated ($bytesWritten bytes).");
}

exit(0);

?>