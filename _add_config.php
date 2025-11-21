<?php
/**
 * Helper script to update all files to use config.php
 * Run this once to add require_once config.php to all PHP files
 */

$files = [
    'index.php',
    'dashboard.php',
    'process.php',
    'reimbursement.php',
    'users.php',
    'report.php'
];

$configRequire = "require_once __DIR__ . '/config.php';\n";

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    
    $content = file_get_contents($file);
    
    // Check if config already required
    if (strpos($content, "require_once __DIR__ . '/config.php'") !== false) {
        echo "$file already has config.php\n";
        continue;
    }
    
    // Add after the first require or session_start
    if (preg_match('/(session_start\(\);|require [\'"]db\.php[\'"];)/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
        $pos = $matches[0][1] + strlen($matches[0][0]);
        $content = substr_replace($content, "\n" . $configRequire, $pos, 0);
        file_put_contents($file, $content);
        echo "Updated $file\n";
    }
}

echo "\nDone! Now all files can use url() function.\n";
echo "Set CLEAN_URL in config.php to true/false to toggle URL mode.\n";
?>
