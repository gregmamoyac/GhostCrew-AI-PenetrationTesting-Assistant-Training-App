<?php
// process_audit.php - Background audit log processor
require_once __DIR__ . '/auth_config.php';

if (php_sapi_name() !== 'cli' && !isset($argv[1])) {
    // Not running from command line, exit
    exit;
}

$tempFile = $argv[1] ?? '';

if (empty($tempFile) || !file_exists($tempFile)) {
    exit;
}

try {
    $auditData = json_decode(file_get_contents($tempFile), true);
    if (!$auditData) {
        unlink($tempFile);
        exit;
    }
    
    $adminDb = getAdminDB();
    $stmt = $adminDb->prepare("INSERT INTO audit_log (user_id, action_type, action_details, ip_address, user_agent, timestamp) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", 
        $auditData['user_id'], 
        $auditData['action_type'], 
        $auditData['action_details'], 
        $auditData['ip_address'], 
        $auditData['user_agent'],
        $auditData['timestamp']
    );
    $stmt->execute();
    
    // Clean up temp file
    unlink($tempFile);
    
} catch (Exception $e) {
    error_log("Background audit processing failed: " . $e->getMessage());
}
?>