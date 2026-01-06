<?php

/**
 * UISP Expense Manager Plugin
 * Main plugin file - This is executed when the plugin runs
 */

// For UISP plugin installation, this file must exist and be executable
// The actual web interface is in public.php

// Log that the plugin is working
$logFile = __DIR__ . '/data/plugin.log';
$dataDir = __DIR__ . '/data';

// Ensure data directory exists
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Write to log
file_put_contents($logFile, "Expense Manager Plugin main.php executed on " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Return success for UISP
echo "Expense Manager Plugin loaded successfully.";

?>
