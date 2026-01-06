<?php

$dataDir = __DIR__ . '/data';
$filesDir = __DIR__ . '/data/files';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

if (!is_dir($filesDir)) {
    mkdir($filesDir, 0755, true);
}

$logFile = __DIR__ . '/data/plugin.log';
file_put_contents($logFile, "Expense Manager Plugin installed successfully on " . date('Y-m-d H:i:s') . "\n");

?>
