<?php

$logFile = __DIR__ . '/data/plugin.log';
$logMessage = "Expense Manager Plugin enabled on " . date('Y-m-d H:i:s') . "\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

?>
