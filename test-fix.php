<?php
/**
 * Test script to verify the scheduling fix
 * 
 * @package KSM_Post_Scheduler
 * @version 1.4.4
 * @author KraftySpouts Media
 */

// WordPress environment setup
define('WP_USE_THEMES', false);
require_once('../../../../../wp-load.php');

echo "Testing scheduling fix...\n";
echo "Current time: " . wp_date('Y-m-d H:i:s') . "\n";

// Get the scheduler instance
if (class_exists('KSM_Post_Scheduler')) {
    $scheduler = new KSM_Post_Scheduler();
    
    echo "Running scheduler...\n";
    $result = $scheduler->schedule_posts();
    
    echo "Scheduler result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
    echo "Check result.txt for detailed logs.\n";
} else {
    echo "KSM_Post_Scheduler class not found!\n";
}
?>