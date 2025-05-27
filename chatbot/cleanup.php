<?php
/**
 * Session Cleanup Script
 * Run this script periodically to clean up old chat sessions
 * Can be executed via cron job or manually
 */

require_once 'config.php';
require_once 'chatbot_logic.php';

// Check if script is being run from command line or web
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    // If accessed via web, add basic authentication or IP restriction
    header('Content-Type: text/plain');
    
    // Simple IP-based access control (adjust as needed)
    $allowed_ips = ['127.0.0.1', '::1'];
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (!in_array($client_ip, $allowed_ips)) {
        http_response_code(403);
        echo "Access denied\n";
        exit;
    }
}

echo "Starting chatbot session cleanup...\n";

try {
    // Initialize chatbot
    $chatbot = new GhostChatbot();
    
    // Get cleanup parameters
    $max_age_days = isset($_GET['days']) ? (int)$_GET['days'] : CLEANUP_OLD_SESSIONS_DAYS;
    $max_age_days = max(1, min(30, $max_age_days)); // Between 1 and 30 days
    
    echo "Cleaning up sessions older than {$max_age_days} days...\n";
    
    // Count sessions before cleanup
    $session_files = glob(SESSION_DATA_DIR . '*.json');
    $total_sessions = count($session_files);
    echo "Found {$total_sessions} session files\n";
    
    // Perform cleanup
    $chatbot->cleanupOldSessions($max_age_days);
    
    // Count sessions after cleanup
    $remaining_files = glob(SESSION_DATA_DIR . '*.json');
    $remaining_sessions = count($remaining_files);
    $cleaned_sessions = $total_sessions - $remaining_sessions;
    
    echo "Cleanup completed!\n";
    echo "Sessions removed: {$cleaned_sessions}\n";
    echo "Sessions remaining: {$remaining_sessions}\n";
    
    // Log the cleanup activity
    if (ENABLE_LOGGING) {
        $log_message = date('Y-m-d H:i:s') . " - Cleanup: Removed {$cleaned_sessions} sessions, {$remaining_sessions} remaining\n";
        file_put_contents(LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
    }
    
} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
    
    if (ENABLE_LOGGING) {
        $log_message = date('Y-m-d H:i:s') . " - Cleanup Error: " . $e->getMessage() . "\n";
        file_put_contents(LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
    }
    
    exit(1);
}

echo "Cleanup script finished successfully\n";

?>