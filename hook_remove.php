<?php

$logFile = __DIR__ . '/data/plugin.log';
if (file_exists($logFile)) {
    $logMessage = "Expense Manager Plugin removed on " . date('Y-m-d H:i:s') . "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

?>
