#!/usr/bin/env php
<?php
declare(strict_types=1);

$path = dirname(__DIR__) . '/src/Application/Controllers/NodeController.php';
$lines = file($path, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    exit(1);
}

function inferStatus(string $buffer): int
{
    $lower = strtolower($buffer);
    if (str_contains($lower, 'not authorized') || str_contains($lower, 'permission')) {
        return 403;
    }
    if (str_contains($lower, 'authentication')) {
        return 401;
    }
    if (str_contains($lower, 'not found')) {
        return 404;
    }
    if (str_contains($lower, 'could not connect') || str_contains($lower, 'asterisk manager')) {
        return 502;
    }
    if (str_contains($lower, 'valid') || str_contains($lower, 'required') || str_contains($lower, 'invalid')) {
        return 400;
    }
    return 500;
}

$count = 0;
$i = 0;
$n = count($lines);
while ($i < $n) {
    if (!str_contains($lines[$i], "'success' => false")) {
        $i++;
        continue;
    }
    $buffer = $lines[$i];
    $j = $i + 1;
    while ($j < $n && $j < $i + 20) {
        if (str_contains($lines[$j], '->withStatus(')) {
            break;
        }
        if (preg_match("/^\s*return \\\$response->withHeader\('Content-Type', 'application\/json'\);/", $lines[$j])) {
            $status = inferStatus($buffer);
            $lines[$j] = str_replace(
                "return \$response->withHeader('Content-Type', 'application/json');",
                "return \$response->withStatus($status)->withHeader('Content-Type', 'application/json');",
                $lines[$j]
            );
            $count++;
            break;
        }
        $buffer .= "\n" . $lines[$j];
        $j++;
    }
    $i++;
}

file_put_contents($path, implode("\n", $lines) . "\n");
echo "Patched $count error responses in NodeController.php\n";
