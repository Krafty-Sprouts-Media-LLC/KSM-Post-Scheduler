<?php
/**
 * Simple trigger script for testing
 */

// WordPress environment setup
define('WP_USE_THEMES', false);
require_once('../../../../../wp-load.php');

// Set content type
header('Content-Type: text/plain');

echo "=== KSM Post Scheduler Test ===\n";
echo "Current time: " . wp_date('Y-m-d H:i:s') . "\n\n";

// Clear previous logs
file_put_contents(__DIR__ . '/result.txt', '');

// Get the scheduler instance
if (class_exists('KSM_Post_Scheduler')) {
    $scheduler = new KSM_Post_Scheduler();
    
    echo "Running scheduler...\n";
    $result = $scheduler->schedule_posts();
    
    echo "Scheduler result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n\n";
    
    // Show the logs
    $logs = file_get_contents(__DIR__ . '/result.txt');
    echo "=== LOGS ===\n";
    echo $logs;
} else {
    echo "KSM_Post_Scheduler class not found!\n";
}
?>