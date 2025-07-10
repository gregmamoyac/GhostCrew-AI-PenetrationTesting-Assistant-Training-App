<?php
/* install.php - Enhanced GhostCrew Installation Script with Updated Database Schema */

// DEBUG MODE - Set to true for detailed error reporting
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Set reasonable limits for installation
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);

// Start session with error handling
if (session_status() === PHP_SESSION_NONE) {
    if (!session_start()) {
        if (DEBUG_MODE) {
            die('Failed to start session');
        }
    }
}

// Check if already installed
if (file_exists('config.php') || file_exists('auth_config.php')) {
    $installComplete = true;
    // Check if we can connect to verify installation
    try {
        include 'auth_config.php';
        $adminDb = getAdminDB();
        $stmt = $adminDb->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
        $stmt->execute();
        $result = $stmt->get_result();
        $hasAdmin = $result->fetch_assoc()['count'] > 0;
    } catch (Exception $e) {
        $installComplete = false;
        $hasAdmin = false;
    }
} else {
    $installComplete = false;
    $hasAdmin = false;
}

// Check for existing installation components
$existingInstallation = checkExistingInstallation();

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success = [];

// Only show cleanup warning if conditions are met
$showCleanupWarning = $existingInstallation['has_existing'] && 
                     !$installComplete && 
                     ($step <= 1) && 
                     empty($_SESSION['db_config']) &&
                     !isset($_GET['action']);

// Function to safely output JSON (prevents any stray output)
function safeJsonOutput($data) {
    // Clean any output that might have been generated
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Set proper headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Output JSON and exit
    echo json_encode($data);
    exit;
}

// Enhanced error handler for AJAX requests
function handleAjaxError($message, $exception = null) {
    $errorData = [
        'success' => false,
        'message' => $message
    ];
    
    if (DEBUG_MODE && $exception) {
        $errorData['debug'] = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
    }
    
    if (DEBUG_MODE) {
        error_log("Install.php Error: " . $message);
        if ($exception) {
            error_log("Exception: " . $exception->getMessage());
        }
    }
    
    safeJsonOutput($errorData);
}

// Handle AJAX requests for real-time validation
if (isset($_GET['action']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start output buffering immediately
    ob_start();
    
    try {
        $response = null;
        
        switch ($_GET['action']) {
            case 'validate_db_config':
                $response = validateDatabaseConfigAjax($_POST);
                break;
                
            case 'save_db_config':
                $response = saveDatabaseConfigAjax($_POST);
                break;
                
            case 'create_config_files':
                if (empty($_SESSION['db_config'])) {
                    handleAjaxError('No database configuration found in session');
                }
                $response = createConfigFilesAjax($_SESSION['db_config']);
                break;
                
            case 'create_databases':
                if (empty($_SESSION['db_config'])) {
                    handleAjaxError('No database configuration found in session');
                }
                $response = createDatabasesAjax($_SESSION['db_config']);
                break;
                
            case 'validate_tables':
                if (empty($_SESSION['db_config'])) {
                    handleAjaxError('No database configuration found in session');
                }
                $response = validateTablesAjax($_SESSION['db_config']);
                break;
                
            case 'repair_table':
                if (empty($_SESSION['db_config'])) {
                    handleAjaxError('No database configuration found in session');
                }
                $response = repairTableAjax($_POST, $_SESSION['db_config']);
                break;
                
            case 'create_admin_user':
                if (empty($_SESSION['db_config'])) {
                    handleAjaxError('No database configuration found in session');
                }
                $response = createAdminUserAjax($_SESSION['db_config'], $_POST);
                break;
                
            case 'cleanup_installation':
                $response = cleanupInstallationAjax();
                break;
                
            case 'delete_installer':
                if (unlink(__FILE__)) {
                    $response = ['success' => true, 'message' => 'Installer deleted successfully'];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to delete installer file'];
                }
                break;
                
            default:
                handleAjaxError('Invalid action: ' . $_GET['action']);
        }
        
        if ($response === null) {
            handleAjaxError('No response generated for action: ' . $_GET['action']);
        }
        
        // Use safe JSON output
        safeJsonOutput($response);
        
    } catch (Exception $e) {
        handleAjaxError('Exception occurred: ' . $e->getMessage(), $e);
    } catch (Error $e) {
        handleAjaxError('Fatal error occurred: ' . $e->getMessage(), $e);
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['action'])) {
    switch ($step) {
        case 1: // Database Configuration
            $errors = validateDatabaseConfig($_POST);
            if (empty($errors)) {
                // Only save to session if not already saved
                if (empty($_SESSION['db_config'])) {
                    $_SESSION['db_config'] = [
                        'terminal_host' => $_POST['terminal_host'],
                        'terminal_user' => $_POST['terminal_user'],
                        'terminal_pass' => $_POST['terminal_pass'],
                        'terminal_name' => $_POST['terminal_name'],
                        'admin_host' => $_POST['admin_host'],
                        'admin_user' => $_POST['admin_user'],
                        'admin_pass' => $_POST['admin_pass'],
                        'admin_name' => $_POST['admin_name'],
                        'app_url' => $_POST['app_url']
                    ];
                }
                header('Location: install.php?step=2');
                exit;
            }
            break;
    }
}

// Updated table structure definitions based on the provided SQL files
function getExpectedTableStructures() {
    return [
        'terminal_app' => [
            'hosts' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'host_id' => ['type' => 'varchar(50)', 'null' => 'NO', 'key' => 'UNI'],
                'hostname' => ['type' => 'varchar(255)', 'null' => 'NO'],
                'ip_address' => ['type' => 'varchar(45)', 'null' => 'NO'],
                'os_info' => ['type' => 'text', 'null' => 'YES'],
                'last_seen' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'connected' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '1'],
                'first_seen' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'total_sessions' => ['type' => 'int(11)', 'null' => 'YES', 'default' => '0'],
                'total_commands' => ['type' => 'int(11)', 'null' => 'YES', 'default' => '0']
            ],
            'command_history' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'host_id' => ['type' => 'varchar(50)', 'null' => 'NO'],
                'session_id' => ['type' => 'varchar(64)', 'null' => 'YES'],
                'is_interactive' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '0'],
                'working_directory' => ['type' => 'text', 'null' => 'YES'],
                'command' => ['type' => 'text', 'null' => 'NO'],
                'output' => ['type' => 'longtext', 'null' => 'YES'],
                'timestamp' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'execution_start' => ['type' => 'timestamp', 'null' => 'YES'],
                'response_timestamp' => ['type' => 'timestamp', 'null' => 'YES'],
                'execution_time' => ['type' => 'decimal(10,6)', 'null' => 'YES'],
                'exit_code' => ['type' => 'int(11)', 'null' => 'YES'],
                'status' => ['type' => "enum('pending','executing','completed','failed','timeout')", 'null' => 'YES', 'default' => 'pending'],
                'context_data' => ['type' => 'longtext', 'null' => 'YES']
            ],
            'shell_sessions' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'session_id' => ['type' => 'varchar(64)', 'null' => 'NO', 'key' => 'UNI'],
                'host_id' => ['type' => 'varchar(50)', 'null' => 'NO'],
                'current_directory' => ['type' => 'text', 'null' => 'YES'],
                'initial_directory' => ['type' => 'text', 'null' => 'YES'],
                'environment_vars' => ['type' => 'longtext', 'null' => 'YES'],
                'start_time' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'last_activity' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'is_active' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '1']
            ],
            'command_statistics' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'host_id' => ['type' => 'varchar(50)', 'null' => 'NO'],
                'command_base' => ['type' => 'varchar(100)', 'null' => 'NO'],
                'execution_count' => ['type' => 'int(11)', 'null' => 'YES', 'default' => '1'],
                'avg_execution_time' => ['type' => 'decimal(10,6)', 'null' => 'YES'],
                'last_executed' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'success_rate' => ['type' => 'decimal(5,2)', 'null' => 'YES', 'default' => '100.00']
            ],
            'host_instance_mappings' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'host_id' => ['type' => 'varchar(50)', 'null' => 'NO'],
                'instance_token' => ['type' => 'varchar(128)', 'null' => 'NO'],
                'user_id' => ['type' => 'int(11)', 'null' => 'YES'],
                'mapped_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'expires_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'is_active' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '1']
            ],
            'interactive_command_patterns' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'command_pattern' => ['type' => 'varchar(255)', 'null' => 'NO'],
                'pattern_type' => ['type' => "enum('exact','prefix','regex','contains')", 'null' => 'YES', 'default' => 'exact'],
                'is_interactive' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '1'],
                'is_continuous' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '0'],
                'timeout_seconds' => ['type' => 'int(11)', 'null' => 'YES', 'default' => '1800'],
                'description' => ['type' => 'text', 'null' => 'YES'],
                'created_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'is_active' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '1']
            ],
            'streaming_output' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'command_id' => ['type' => 'int(11)', 'null' => 'NO'],
                'session_id' => ['type' => 'varchar(64)', 'null' => 'NO'],
                'output_chunk' => ['type' => 'longtext', 'null' => 'NO'],
                'chunk_sequence' => ['type' => 'int(11)', 'null' => 'YES', 'default' => '1'],
                'is_partial' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '1'],
                'timestamp' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'last_update' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()']
            ],
            'streaming_sessions' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'command_id' => ['type' => 'int(11)', 'null' => 'NO'],
                'session_id' => ['type' => 'varchar(64)', 'null' => 'NO'],
                'host_id' => ['type' => 'varchar(50)', 'null' => 'NO'],
                'status' => ['type' => "enum('active','paused','completed','terminated')", 'null' => 'YES', 'default' => 'active'],
                'start_time' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'end_time' => ['type' => 'timestamp', 'null' => 'YES'],
                'last_activity' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'total_input_lines' => ['type' => 'int(11)', 'null' => 'YES', 'default' => '0'],
                'total_output_size' => ['type' => 'bigint(20)', 'null' => 'YES', 'default' => '0']
            ],
            'user_input_queue' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'session_id' => ['type' => 'varchar(64)', 'null' => 'NO'],
                'host_id' => ['type' => 'varchar(50)', 'null' => 'NO'],
                'user_id' => ['type' => 'int(11)', 'null' => 'YES'],
                'command_id' => ['type' => 'int(11)', 'null' => 'YES'],
                'input_data' => ['type' => 'text', 'null' => 'NO'],
                'input_type' => ['type' => "enum('command','response','ctrl_signal')", 'null' => 'YES', 'default' => 'response'],
                'timestamp' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'processed' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '0'],
                'processed_at' => ['type' => 'timestamp', 'null' => 'YES'],
                'priority' => ['type' => 'tinyint(4)', 'null' => 'YES', 'default' => '5']
            ]
        ],
        'ghostcrew_admin' => [
            'users' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'username' => ['type' => 'varchar(50)', 'null' => 'NO', 'key' => 'UNI'],
                'password_hash' => ['type' => 'varchar(255)', 'null' => 'NO'],
                'full_name' => ['type' => 'varchar(100)', 'null' => 'NO'],
                'email' => ['type' => 'varchar(100)', 'null' => 'NO'],
                'role' => ['type' => "enum('admin','manager','operator')", 'null' => 'YES', 'default' => 'operator'],
                'is_active' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '1'],
                'last_login' => ['type' => 'timestamp', 'null' => 'YES'],
                'created_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'updated_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'created_by' => ['type' => 'int(11)', 'null' => 'YES'],
                'manager_id' => ['type' => 'int(11)', 'null' => 'YES']
            ],
            'user_sessions' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'user_id' => ['type' => 'int(11)', 'null' => 'NO'],
                'session_token' => ['type' => 'varchar(128)', 'null' => 'NO', 'key' => 'UNI'],
                'ip_address' => ['type' => 'varchar(45)', 'null' => 'NO'],
                'user_agent' => ['type' => 'text', 'null' => 'YES'],
                'login_time' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'last_activity' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'logout_time' => ['type' => 'timestamp', 'null' => 'YES'],
                'is_active' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '1']
            ],
            'user_instance_tokens' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'user_id' => ['type' => 'int(11)', 'null' => 'NO'],
                'instance_token' => ['type' => 'varchar(128)', 'null' => 'NO', 'key' => 'UNI'],
                'created_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'expires_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'is_active' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '1']
            ],
            'audit_log' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'user_id' => ['type' => 'int(11)', 'null' => 'YES'],
                'action_type' => ['type' => "enum('login','logout','command_execute','session_start','session_end','chat_message','system_access')", 'null' => 'NO'],
                'action_details' => ['type' => 'longtext', 'null' => 'YES'],
                'ip_address' => ['type' => 'varchar(45)', 'null' => 'YES'],
                'user_agent' => ['type' => 'text', 'null' => 'YES'],
                'timestamp' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()']
            ],
            'remote_sessions' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'session_id' => ['type' => 'varchar(64)', 'null' => 'NO', 'key' => 'UNI'],
                'user_id' => ['type' => 'int(11)', 'null' => 'NO'],
                'host_id' => ['type' => 'varchar(50)', 'null' => 'NO'],
                'hostname' => ['type' => 'varchar(255)', 'null' => 'NO'],
                'ip_address' => ['type' => 'varchar(45)', 'null' => 'NO'],
                'os_info' => ['type' => 'text', 'null' => 'YES'],
                'start_time' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'end_time' => ['type' => 'timestamp', 'null' => 'YES'],
                'status' => ['type' => "enum('active','disconnected','terminated')", 'null' => 'YES', 'default' => 'active'],
                'last_activity' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'total_commands' => ['type' => 'int(11)', 'null' => 'YES', 'default' => '0'],
                'session_notes' => ['type' => 'text', 'null' => 'YES']
            ],
            'chatbot_conversations' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'user_id' => ['type' => 'int(11)', 'null' => 'NO'],
                'session_id' => ['type' => 'varchar(64)', 'null' => 'YES'],
                'conversation_id' => ['type' => 'varchar(64)', 'null' => 'NO'],
                'parent_message_id' => ['type' => 'int(11)', 'null' => 'YES'],
                'message_type' => ['type' => "enum('user','bot')", 'null' => 'NO'],
                'message' => ['type' => 'text', 'null' => 'NO'],
                'timestamp' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'context_data' => ['type' => 'longtext', 'null' => 'YES'],
                'response_time' => ['type' => 'decimal(8,3)', 'null' => 'YES'],
                'message_tokens' => ['type' => 'int(11)', 'null' => 'YES'],
                'model_used' => ['type' => 'varchar(50)', 'null' => 'YES', 'default' => 'local'],
                'suggested_command' => ['type' => 'text', 'null' => 'YES'],
                'command_executed' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '0'],
                'rating' => ['type' => 'tinyint(1)', 'null' => 'YES'],
                'flagged' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '0']
            ],
            'chatbot_feedback' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'conversation_id' => ['type' => 'varchar(64)', 'null' => 'NO'],
                'message_id' => ['type' => 'int(11)', 'null' => 'NO'],
                'user_id' => ['type' => 'int(11)', 'null' => 'NO'],
                'feedback_type' => ['type' => "enum('helpful','not_helpful','incorrect','suggestion')", 'null' => 'NO'],
                'feedback_text' => ['type' => 'text', 'null' => 'YES'],
                'created_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()']
            ],
            'chatbot_knowledge_base' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'category' => ['type' => 'varchar(100)', 'null' => 'NO'],
                'question' => ['type' => 'text', 'null' => 'NO'],
                'answer' => ['type' => 'text', 'null' => 'NO'],
                'keywords' => ['type' => 'text', 'null' => 'YES'],
                'command_example' => ['type' => 'text', 'null' => 'YES'],
                'created_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'updated_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'is_active' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '1']
            ],
            'command_log' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'session_id' => ['type' => 'varchar(64)', 'null' => 'NO'],
                'is_interactive' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '0'],
                'user_id' => ['type' => 'int(11)', 'null' => 'NO'],
                'command' => ['type' => 'text', 'null' => 'NO'],
                'output' => ['type' => 'longtext', 'null' => 'YES'],
                'execution_time' => ['type' => 'decimal(10,6)', 'null' => 'YES'],
                'status' => ['type' => "enum('pending','completed','failed','timeout')", 'null' => 'YES', 'default' => 'pending'],
                'timestamp' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'response_timestamp' => ['type' => 'timestamp', 'null' => 'YES'],
                'error_message' => ['type' => 'text', 'null' => 'YES']
            ],
            'command_patterns' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'pattern' => ['type' => 'varchar(255)', 'null' => 'NO'],
                'category' => ['type' => 'varchar(50)', 'null' => 'NO'],
                'description' => ['type' => 'text', 'null' => 'NO'],
                'suggested_commands' => ['type' => 'text', 'null' => 'NO'],
                'response_template' => ['type' => 'text', 'null' => 'NO'],
                'match_type' => ['type' => "enum('exact','contains','regex')", 'null' => 'YES', 'default' => 'contains'],
                'priority' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '5'],
                'is_active' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '1'],
                'created_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'updated_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()']
            ],
            'command_suggestions' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'conversation_id' => ['type' => 'varchar(64)', 'null' => 'NO'],
                'user_id' => ['type' => 'int(11)', 'null' => 'NO'],
                'suggested_command' => ['type' => 'text', 'null' => 'NO'],
                'command_description' => ['type' => 'text', 'null' => 'YES'],
                'priority' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '5'],
                'category' => ['type' => 'varchar(50)', 'null' => 'YES', 'default' => 'general'],
                'suggestion_context' => ['type' => 'text', 'null' => 'YES'],
                'executed' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '0'],
                'executed_at' => ['type' => 'timestamp', 'null' => 'YES'],
                'created_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()']
            ],
            'hosts_info' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'host_id' => ['type' => 'varchar(50)', 'null' => 'NO', 'key' => 'UNI'],
                'hostname' => ['type' => 'varchar(255)', 'null' => 'NO'],
                'ip_address' => ['type' => 'varchar(45)', 'null' => 'NO'],
                'os_info' => ['type' => 'text', 'null' => 'YES'],
                'first_seen' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'last_seen' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'total_sessions' => ['type' => 'int(11)', 'null' => 'YES', 'default' => '0'],
                'total_commands' => ['type' => 'int(11)', 'null' => 'YES', 'default' => '0'],
                'is_active' => ['type' => 'tinyint(1)', 'null' => 'YES', 'default' => '1'],
                'notes' => ['type' => 'text', 'null' => 'YES']
            ],
            'session_contexts' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'session_id' => ['type' => 'varchar(64)', 'null' => 'NO'],
                'conversation_id' => ['type' => 'varchar(64)', 'null' => 'NO'],
                'context_type' => ['type' => "enum('command_history','system_info','working_directory')", 'null' => 'NO'],
                'context_data' => ['type' => 'longtext', 'null' => 'YES'],
                'updated_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()']
            ],
            'session_feedback' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'session_id' => ['type' => 'varchar(64)', 'null' => 'NO'],
                'user_id' => ['type' => 'int(11)', 'null' => 'NO'],
                'overall_score' => ['type' => 'int(11)', 'null' => 'YES'],
                'instructor_feedback' => ['type' => 'text', 'null' => 'YES'],
                'command_feedback' => ['type' => 'longtext', 'null' => 'YES'],
                'rating' => ['type' => 'tinyint(4)', 'null' => 'YES'],
                'graded_by' => ['type' => 'int(11)', 'null' => 'YES'],
                'graded_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'created_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()']
            ],
            'system_config' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'config_key' => ['type' => 'varchar(100)', 'null' => 'NO', 'key' => 'UNI'],
                'config_value' => ['type' => 'text', 'null' => 'YES'],
                'description' => ['type' => 'text', 'null' => 'YES'],
                'updated_by' => ['type' => 'int(11)', 'null' => 'YES'],
                'updated_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()']
            ],
            'user_interactions' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'session_id' => ['type' => 'varchar(64)', 'null' => 'NO'],
                'user_id' => ['type' => 'int(11)', 'null' => 'NO'],
                'interaction_type' => ['type' => "enum('input','output','termination','command')", 'null' => 'NO'],
                'interaction_data' => ['type' => 'longtext', 'null' => 'YES'],
                'timestamp' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()']
            ]
        ]
    ];
}

function validateDatabaseConfig($data) {
    $errors = [];
    
    // Required fields
    $required = ['terminal_host', 'terminal_user', 'terminal_pass', 'terminal_name', 
                'admin_host', 'admin_user', 'admin_pass', 'admin_name', 'app_url'];
    
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = "Field '" . ucfirst(str_replace('_', ' ', $field)) . "' is required.";
        }
    }
    
    if (!empty($errors)) {
        return $errors;
    }
    
    // Test terminal database connection
    try {
        $conn = new mysqli($data['terminal_host'], $data['terminal_user'], $data['terminal_pass']);
        if ($conn->connect_error) {
            $errors[] = "Terminal database connection failed: " . $conn->connect_error;
        } else {
            // Test create database permission
            $sql = "CREATE DATABASE IF NOT EXISTS test_permissions_check";
            if (!$conn->query($sql)) {
                $errors[] = "Terminal database user lacks CREATE DATABASE permission: " . $conn->error;
            } else {
                $conn->query("DROP DATABASE IF EXISTS test_permissions_check");
            }
        }
        $conn->close();
    } catch (Exception $e) {
        $errors[] = "Terminal database error: " . $e->getMessage();
    }
    
    // Test admin database connection
    try {
        $conn = new mysqli($data['admin_host'], $data['admin_user'], $data['admin_pass']);
        if ($conn->connect_error) {
            $errors[] = "Admin database connection failed: " . $conn->connect_error;
        } else {
            // Test create database permission
            $sql = "CREATE DATABASE IF NOT EXISTS test_permissions_check";
            if (!$conn->query($sql)) {
                $errors[] = "Admin database user lacks CREATE DATABASE permission: " . $conn->error;
            } else {
                $conn->query("DROP DATABASE IF EXISTS test_permissions_check");
            }
        }
        $conn->close();
    } catch (Exception $e) {
        $errors[] = "Admin database error: " . $e->getMessage();
    }
    
    // Validate URL format
    if (!filter_var($data['app_url'], FILTER_VALIDATE_URL)) {
        $errors[] = "Application URL must be a valid URL.";
    }
    
    return $errors;
}

function validateDatabaseConfigAjax($data) {
    $result = ['success' => true, 'checks' => []];
    
    // Test terminal database connection
    $result['checks']['terminal_connection'] = ['status' => 'checking', 'message' => 'Testing terminal database connection...'];
    
    try {
        $conn = new mysqli($data['terminal_host'], $data['terminal_user'], $data['terminal_pass']);
        if ($conn->connect_error) {
            $result['checks']['terminal_connection'] = ['status' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error];
            $result['success'] = false;
        } else {
            $result['checks']['terminal_connection'] = ['status' => 'success', 'message' => 'Connection successful'];
            
            // Test permissions
            $result['checks']['terminal_permissions'] = ['status' => 'checking', 'message' => 'Testing CREATE DATABASE permission...'];
            $sql = "CREATE DATABASE IF NOT EXISTS test_permissions_check";
            if (!$conn->query($sql)) {
                $result['checks']['terminal_permissions'] = ['status' => 'error', 'message' => 'CREATE DATABASE permission denied: ' . $conn->error];
                $result['success'] = false;
            } else {
                $conn->query("DROP DATABASE IF EXISTS test_permissions_check");
                $result['checks']['terminal_permissions'] = ['status' => 'success', 'message' => 'Permissions verified'];
            }
        }
        $conn->close();
    } catch (Exception $e) {
        $result['checks']['terminal_connection'] = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        $result['success'] = false;
    }
    
    // Test admin database connection
    $result['checks']['admin_connection'] = ['status' => 'checking', 'message' => 'Testing admin database connection...'];
    
    try {
        $conn = new mysqli($data['admin_host'], $data['admin_user'], $data['admin_pass']);
        if ($conn->connect_error) {
            $result['checks']['admin_connection'] = ['status' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error];
            $result['success'] = false;
        } else {
            $result['checks']['admin_connection'] = ['status' => 'success', 'message' => 'Connection successful'];
            
            // Test permissions
            $result['checks']['admin_permissions'] = ['status' => 'checking', 'message' => 'Testing CREATE DATABASE permission...'];
            $sql = "CREATE DATABASE IF NOT EXISTS test_permissions_check";
            if (!$conn->query($sql)) {
                $result['checks']['admin_permissions'] = ['status' => 'error', 'message' => 'CREATE DATABASE permission denied: ' . $conn->error];
                $result['success'] = false;
            } else {
                $conn->query("DROP DATABASE IF EXISTS test_permissions_check");
                $result['checks']['admin_permissions'] = ['status' => 'success', 'message' => 'Permissions verified'];
            }
        }
        $conn->close();
    } catch (Exception $e) {
        $result['checks']['admin_connection'] = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        $result['success'] = false;
    }
    
    return $result;
}

function saveDatabaseConfigAjax($data) {
    $result = ['success' => true, 'message' => ''];
    
    try {
        // Validate required fields
        $required = ['terminal_host', 'terminal_user', 'terminal_pass', 'terminal_name', 
                    'admin_host', 'admin_user', 'admin_pass', 'admin_name', 'app_url'];
        
        $missing = [];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            $result['success'] = false;
            $result['message'] = 'Missing required fields: ' . implode(', ', $missing);
            return $result;
        }
        
        // Save to session
        $_SESSION['db_config'] = [
            'terminal_host' => $data['terminal_host'],
            'terminal_user' => $data['terminal_user'],
            'terminal_pass' => $data['terminal_pass'],
            'terminal_name' => $data['terminal_name'],
            'admin_host' => $data['admin_host'],
            'admin_user' => $data['admin_user'],
            'admin_pass' => $data['admin_pass'],
            'admin_name' => $data['admin_name'],
            'app_url' => $data['app_url']
        ];
        
        $result['message'] = 'Database configuration saved to session successfully';
        
    } catch (Exception $e) {
        $result['success'] = false;
        $result['message'] = 'Failed to save configuration: ' . $e->getMessage();
    }
    
    return $result;
}

// Create config files function
function createConfigFilesAjax($config) {
    $result = ['success' => true, 'checks' => []];
    
    try {
        // Delete existing config files first to prevent conflicts
        $result['checks']['cleanup_existing'] = ['status' => 'checking', 'message' => 'Removing any existing config files...'];
        
        $filesToRemove = ['config.php', 'auth_config.php'];
        $removedFiles = [];
        
        foreach ($filesToRemove as $file) {
            if (file_exists($file)) {
                if (unlink($file)) {
                    $removedFiles[] = $file;
                }
            }
        }
        
        if (!empty($removedFiles)) {
            $result['checks']['cleanup_existing'] = ['status' => 'success', 'message' => 'Removed existing files: ' . implode(', ', $removedFiles)];
        } else {
            $result['checks']['cleanup_existing'] = ['status' => 'success', 'message' => 'No existing config files found'];
        }
        
        // Create config.php
        $result['checks']['config_file'] = ['status' => 'checking', 'message' => 'Creating config.php...'];
        
        try {
            $configContent = generateConfigFile($config);
            if (strlen($configContent) < 100) {
                throw new Exception("Generated config content is too short");
            }
            
            if (!file_put_contents('config.php', $configContent)) {
                throw new Exception("file_put_contents returned false");
            }
            
            // Verify file was created and has content
            if (!file_exists('config.php') || filesize('config.php') < 100) {
                throw new Exception("File creation verification failed");
            }
            
            $result['checks']['config_file'] = ['status' => 'success', 'message' => 'config.php created successfully'];
            
        } catch (Exception $e) {
            $result['checks']['config_file'] = ['status' => 'error', 'message' => 'Failed to create config.php: ' . $e->getMessage()];
            $result['success'] = false;
        }
        
        // Create auth_config.php
        $result['checks']['auth_config_file'] = ['status' => 'checking', 'message' => 'Creating auth_config.php...'];
        
        try {
            $authConfigContent = generateAuthConfigFile($config);
            if (strlen($authConfigContent) < 100) {
                throw new Exception("Generated auth config content is too short");
            }
            
            if (!file_put_contents('auth_config.php', $authConfigContent)) {
                throw new Exception("file_put_contents returned false");
            }
            
            // Verify file was created and has content
            if (!file_exists('auth_config.php') || filesize('auth_config.php') < 100) {
                throw new Exception("File creation verification failed");
            }
            
            $result['checks']['auth_config_file'] = ['status' => 'success', 'message' => 'auth_config.php created successfully'];
            
        } catch (Exception $e) {
            $result['checks']['auth_config_file'] = ['status' => 'error', 'message' => 'Failed to create auth_config.php: ' . $e->getMessage()];
            $result['success'] = false;
        }

        // Wait for files to be visible and validate content
        if ($result['success']) {
            $result['checks']['file_verification'] = ['status' => 'checking', 'message' => 'Verifying configuration files...'];
            
            $verification = waitForConfigFiles($config);
            if (!$verification['success']) {
                $result['checks']['file_verification'] = ['status' => 'error', 'message' => $verification['message']];
                $result['success'] = false;
            } else {
                $result['checks']['file_verification'] = ['status' => 'success', 'message' => 'All configuration files verified and ready'];
            }
        }
        
    } catch (Exception $e) {
        $result['checks']['general_error'] = ['status' => 'error', 'message' => 'Configuration file creation error: ' . $e->getMessage()];
        $result['success'] = false;
    }
    
    return $result;
}

// Database creation function that only creates databases
function createDatabasesAjax($config) {
    $result = ['success' => true, 'checks' => []];
    
    try {
        // Verify config files exist first
        if (!file_exists('config.php') || !file_exists('auth_config.php')) {
            $result['checks']['config_check'] = ['status' => 'error', 'message' => 'Configuration files must be created first (missing: ' . 
                (!file_exists('config.php') ? 'config.php ' : '') . 
                (!file_exists('auth_config.php') ? 'auth_config.php ' : '') . ')'];
            $result['success'] = false;
            return $result;
        }
        
        $result['checks']['config_check'] = ['status' => 'success', 'message' => 'All configuration files found'];
        
        // Create terminal_app database
        $result['checks']['terminal_database'] = ['status' => 'checking', 'message' => 'Creating terminal_app database...'];
        
        try {
            $terminalSql = getTerminalAppSQL();
            if (strlen($terminalSql) < 100) {
                throw new Exception("Terminal SQL script is too short");
            }
            
            $conn = new mysqli($config['terminal_host'], $config['terminal_user'], $config['terminal_pass']);
            if ($conn->connect_error) {
                throw new Exception('Connection failed: ' . $conn->connect_error);
            }
            
            // Enable error reporting for this connection
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            
            if (!$conn->multi_query($terminalSql)) {
                throw new Exception('Multi-query failed: ' . $conn->error);
            }
            
            // Clear all results
            do {
                if ($resultSet = $conn->store_result()) {
                    $resultSet->free();
                }
            } while ($conn->next_result());
            
            // Verify database was created
            $conn->select_db($config['terminal_name']);
            $tableCheck = $conn->query("SHOW TABLES");
            if ($tableCheck->num_rows < 5) {
                throw new Exception("Database created but tables are missing");
            }
            
            $conn->close();
            $result['checks']['terminal_database'] = ['status' => 'success', 'message' => 'Terminal database created successfully'];
            
        } catch (Exception $e) {
            if (isset($conn)) {
                $conn->close();
            }
            $result['checks']['terminal_database'] = ['status' => 'error', 'message' => 'Terminal database error: ' . $e->getMessage()];
            $result['success'] = false;
        }
        
        // Create ghostcrew_admin database
        $result['checks']['admin_database'] = ['status' => 'checking', 'message' => 'Creating ghostcrew_admin database...'];
        
        try {
            $adminSql = getGhostcrewAdminSQL();
            if (strlen($adminSql) < 100) {
                throw new Exception("Admin SQL script is too short");
            }
            
            $conn = new mysqli($config['admin_host'], $config['admin_user'], $config['admin_pass']);
            if ($conn->connect_error) {
                throw new Exception('Connection failed: ' . $conn->connect_error);
            }
            
            // Enable error reporting for this connection
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            
            if (!$conn->multi_query($adminSql)) {
                throw new Exception('Multi-query failed: ' . $conn->error);
            }
            
            // Clear all results
            do {
                if ($resultSet = $conn->store_result()) {
                    $resultSet->free();
                }
            } while ($conn->next_result());
            
            // Verify database was created
            $conn->select_db($config['admin_name']);
            $tableCheck = $conn->query("SHOW TABLES");
            if ($tableCheck->num_rows < 11) {
                throw new Exception("Database created but tables are missing");
            }
            
            $conn->close();
            $result['checks']['admin_database'] = ['status' => 'success', 'message' => 'Admin database created successfully'];
            
        } catch (Exception $e) {
            if (isset($conn)) {
                $conn->close();
            }
            $result['checks']['admin_database'] = ['status' => 'error', 'message' => 'Admin database error: ' . $e->getMessage()];
            $result['success'] = false;
        }
        
    } catch (Exception $e) {
        $result['checks']['general_error'] = ['status' => 'error', 'message' => 'Database creation error: ' . $e->getMessage()];
        $result['success'] = false;
    }
    
    return $result;
}

// Function to wait for and verify config files
function waitForConfigFiles($config, $maxWaitTime = 30) {
    $startTime = time();
    $configFile = 'config.php';
    $authConfigFile = 'auth_config.php';
    
    // Wait for files to exist
    while ((time() - $startTime) < $maxWaitTime) {
        if (file_exists($configFile) && file_exists($authConfigFile)) {
            // Files exist, now verify content
            usleep(500000); // Wait 0.5 seconds for file system to sync
            
            // Verify config.php content
            $configVerification = verifyConfigFileContent($configFile, $config);
            if (!$configVerification['success']) {
                return [
                    'success' => false, 
                    'message' => 'config.php verification failed: ' . $configVerification['message']
                ];
            }
            
            // Verify auth_config.php content
            $authConfigVerification = verifyAuthConfigFileContent($authConfigFile, $config);
            if (!$authConfigVerification['success']) {
                return [
                    'success' => false, 
                    'message' => 'auth_config.php verification failed: ' . $authConfigVerification['message']
                ];
            }
            
            return ['success' => true, 'message' => 'All configuration files verified successfully'];
        }
        
        usleep(250000); // Wait 0.25 seconds before checking again
    }
    
    return [
        'success' => false, 
        'message' => 'Timeout waiting for configuration files to be created'
    ];
}

// Verify config.php file content
function verifyConfigFileContent($filePath, $expectedConfig) {
    if (!is_readable($filePath)) {
        return ['success' => false, 'message' => 'File is not readable'];
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) {
        return ['success' => false, 'message' => 'Could not read file content'];
    }
    
    // Check for required constants
    $requiredConstants = [
        'DB_HOST' => $expectedConfig['terminal_host'],
        'DB_USER' => $expectedConfig['terminal_user'],
        'DB_NAME' => $expectedConfig['terminal_name'],
        'APP_URL' => $expectedConfig['app_url']
    ];
    
    foreach ($requiredConstants as $constant => $expectedValue) {
        $pattern = '/define\s*\(\s*[\'"]' . preg_quote($constant, '/') . '[\'"].*[\'"]' . preg_quote(addslashes($expectedValue), '/') . '[\'"]\s*\)/';
        if (!preg_match($pattern, $content)) {
            return ['success' => false, 'message' => "Missing or incorrect constant: $constant"];
        }
    }
    
    return ['success' => true, 'message' => 'config.php verified'];
}

// Verify auth_config.php file content
function verifyAuthConfigFileContent($filePath, $expectedConfig) {
    if (!is_readable($filePath)) {
        return ['success' => false, 'message' => 'File is not readable'];
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) {
        return ['success' => false, 'message' => 'Could not read file content'];
    }
    
    // Check for required constants
    $requiredConstants = [
        'ADMIN_DB_HOST' => $expectedConfig['admin_host'],
        'ADMIN_DB_USER' => $expectedConfig['admin_user'],
        'ADMIN_DB_NAME' => $expectedConfig['admin_name']
    ];
    
    foreach ($requiredConstants as $constant => $expectedValue) {
        $pattern = '/define\s*\(\s*[\'"]' . preg_quote($constant, '/') . '[\'"].*[\'"]' . preg_quote(addslashes($expectedValue), '/') . '[\'"]\s*\)/';
        if (!preg_match($pattern, $content)) {
            return ['success' => false, 'message' => "Missing or incorrect constant: $constant"];
        }
    }
    
    // Check for required functions
    if (!strpos($content, 'function getAdminDB()')) {
        return ['success' => false, 'message' => 'Missing getAdminDB() function'];
    }
    
    return ['success' => true, 'message' => 'auth_config.php verified'];
}

function validateTablesAjax($config) {
    $result = ['success' => true, 'checks' => []];
    $expectedStructures = getExpectedTableStructures();
    
    // Validate terminal database tables
    try {
        $conn = new mysqli($config['terminal_host'], $config['terminal_user'], $config['terminal_pass'], $config['terminal_name']);
        
        foreach ($expectedStructures['terminal_app'] as $tableName => $expectedColumns) {
            $tableKey = "terminal_table_$tableName";
            $result['checks'][$tableKey] = ['status' => 'checking', 'message' => "Validating table $tableName..."];
            
            // Check if table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE '$tableName'");
            if ($tableCheck->num_rows === 0) {
                $result['checks'][$tableKey] = ['status' => 'error', 'message' => "Table $tableName does not exist", 'repair' => true];
                $result['success'] = false;
                continue;
            }
            
            // Check columns
            $columnsResult = $conn->query("DESCRIBE $tableName");
            $actualColumns = [];
            while ($row = $columnsResult->fetch_assoc()) {
                $actualColumns[$row['Field']] = [
                    'type' => $row['Type'],
                    'null' => $row['Null'],
                    'key' => $row['Key'],
                    'default' => $row['Default'],
                    'extra' => $row['Extra']
                ];
            }
            
            $columnIssues = [];
            foreach ($expectedColumns as $columnName => $expectedProps) {
                if (!isset($actualColumns[$columnName])) {
                    $columnIssues[] = "Missing column: $columnName";
                } else {
                    // Check column properties (more flexible type checking)
                    $actual = $actualColumns[$columnName];
                    $expectedType = strtolower($expectedProps['type']);
                    $actualType = strtolower($actual['type']);
                    
                    // Handle enum type variations
                    if (strpos($expectedType, 'enum') === 0) {
                        if (strpos($actualType, 'enum') !== 0) {
                            $columnIssues[] = "Column $columnName type mismatch: expected enum, got {$actual['type']}";
                        }
                    } elseif (strpos($expectedType, 'int') === 0) {
                        if (strpos($actualType, 'int') !== 0) {
                            $columnIssues[] = "Column $columnName type mismatch: expected int, got {$actual['type']}";
                        }
                    } elseif (strpos($expectedType, 'varchar') === 0) {
                        if (strpos($actualType, 'varchar') !== 0) {
                            $columnIssues[] = "Column $columnName type mismatch: expected varchar, got {$actual['type']}";
                        }
                    } elseif ($expectedType !== $actualType) {
                        // For other types, require exact match
                        $columnIssues[] = "Column $columnName type mismatch: expected {$expectedProps['type']}, got {$actual['type']}";
                    }
                }
            }
            
            if (!empty($columnIssues)) {
                $result['checks'][$tableKey] = ['status' => 'error', 'message' => implode('; ', $columnIssues), 'repair' => true];
                $result['success'] = false;
            } else {
                $result['checks'][$tableKey] = ['status' => 'success', 'message' => "Table $tableName structure validated"];
            }
        }
        
        $conn->close();
    } catch (Exception $e) {
        $result['checks']['terminal_validation'] = ['status' => 'error', 'message' => 'Terminal database validation error: ' . $e->getMessage()];
        $result['success'] = false;
    }
    
    // Validate admin database tables
    try {
        $conn = new mysqli($config['admin_host'], $config['admin_user'], $config['admin_pass'], $config['admin_name']);
        
        foreach ($expectedStructures['ghostcrew_admin'] as $tableName => $expectedColumns) {
            $tableKey = "admin_table_$tableName";
            $result['checks'][$tableKey] = ['status' => 'checking', 'message' => "Validating table $tableName..."];
            
            // Check if table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE '$tableName'");
            if ($tableCheck->num_rows === 0) {
                $result['checks'][$tableKey] = ['status' => 'error', 'message' => "Table $tableName does not exist", 'repair' => true];
                $result['success'] = false;
                continue;
            }
            
            // Check columns
            $columnsResult = $conn->query("DESCRIBE $tableName");
            $actualColumns = [];
            while ($row = $columnsResult->fetch_assoc()) {
                $actualColumns[$row['Field']] = [
                    'type' => $row['Type'],
                    'null' => $row['Null'],
                    'key' => $row['Key'],
                    'default' => $row['Default'],
                    'extra' => $row['Extra']
                ];
            }
            
            $columnIssues = [];
            foreach ($expectedColumns as $columnName => $expectedProps) {
                if (!isset($actualColumns[$columnName])) {
                    $columnIssues[] = "Missing column: $columnName";
                } else {
                    // Check column properties (more flexible type checking)
                    $actual = $actualColumns[$columnName];
                    $expectedType = strtolower($expectedProps['type']);
                    $actualType = strtolower($actual['type']);
                    
                    // Handle enum type variations
                    if (strpos($expectedType, 'enum') === 0) {
                        if (strpos($actualType, 'enum') !== 0) {
                            $columnIssues[] = "Column $columnName type mismatch: expected enum, got {$actual['type']}";
                        }
                    } elseif (strpos($expectedType, 'int') === 0) {
                        if (strpos($actualType, 'int') !== 0) {
                            $columnIssues[] = "Column $columnName type mismatch: expected int, got {$actual['type']}";
                        }
                    } elseif (strpos($expectedType, 'varchar') === 0) {
                        if (strpos($actualType, 'varchar') !== 0) {
                            $columnIssues[] = "Column $columnName type mismatch: expected varchar, got {$actual['type']}";
                        }
                    } elseif ($expectedType !== $actualType) {
                        // For other types, require exact match
                        $columnIssues[] = "Column $columnName type mismatch: expected {$expectedProps['type']}, got {$actual['type']}";
                    }
                }
            }
            
            if (!empty($columnIssues)) {
                $result['checks'][$tableKey] = ['status' => 'error', 'message' => implode('; ', $columnIssues), 'repair' => true];
                $result['success'] = false;
            } else {
                $result['checks'][$tableKey] = ['status' => 'success', 'message' => "Table $tableName structure validated"];
            }
        }
        
        $conn->close();
    } catch (Exception $e) {
        $result['checks']['admin_validation'] = ['status' => 'error', 'message' => 'Admin database validation error: ' . $e->getMessage()];
        $result['success'] = false;
    }
    
    return $result;
}

function repairTableAjax($data, $config) {
    $result = ['success' => true, 'message' => ''];
    
    $database = $data['database'];
    $table = $data['table'];
    
    try {
        if ($database === 'terminal_app') {
            $sql = getTerminalAppSQL();
            $conn = new mysqli($config['terminal_host'], $config['terminal_user'], $config['terminal_pass'], $config['terminal_name']);
        } else {
            $sql = getGhostcrewAdminSQL();
            $conn = new mysqli($config['admin_host'], $config['admin_user'], $config['admin_pass'], $config['admin_name']);
        }
        
        if ($conn->connect_error) {
            $result['success'] = false;
            $result['message'] = 'Database connection failed: ' . $conn->connect_error;
            return $result;
        }
        
        // Drop and recreate the specific table
        $conn->query("DROP TABLE IF EXISTS $table");
        
        // Re-run the creation script
        if (!$conn->multi_query($sql)) {
            $result['success'] = false;
            $result['message'] = 'Failed to repair table: ' . $conn->error;
        } else {
            // Clear results
            do {
                if ($resultSet = $conn->store_result()) {
                    $resultSet->free();
                }
            } while ($conn->next_result());
            $result['message'] = "Table $table repaired successfully";
        }
        
        $conn->close();
        
    } catch (Exception $e) {
        $result['success'] = false;
        $result['message'] = 'Repair error: ' . $e->getMessage();
    }
    
    return $result;
}

function createAdminUserAjax($config, $userData) {
    $result = ['success' => true, 'checks' => []];
    
    try {
        // Validate config exists
        if (empty($config)) {
            return [
                'success' => false,
                'checks' => [
                    'config_validation' => ['status' => 'error', 'message' => 'No database configuration found']
                ]
            ];
        }
        
        // Validate user data
        $result['checks']['user_validation'] = ['status' => 'checking', 'message' => 'Validating user data...'];
        
        $required = ['username', 'password', 'confirm_password', 'full_name', 'email'];
        $errors = [];
        
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                $errors[] = "Field '" . ucfirst(str_replace('_', ' ', $field)) . "' is required.";
            }
        }
        
        if ($userData['password'] !== $userData['confirm_password']) {
            $errors[] = "Passwords do not match.";
        }
        
        if (strlen($userData['password']) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }
        
        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address.";
        }
        
        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $userData['username'])) {
            $errors[] = "Username must be 3-50 characters and contain only letters, numbers, and underscores.";
        }
        
        if (!empty($errors)) {
            $result['checks']['user_validation'] = ['status' => 'error', 'message' => implode('; ', $errors)];
            $result['success'] = false;
            return $result;
        }
        
        $result['checks']['user_validation'] = ['status' => 'success', 'message' => 'User data validated'];
        
        // Test database connection directly
        $result['checks']['database_connection'] = ['status' => 'checking', 'message' => 'Connecting to admin database...'];
        
        try {
            $adminDb = new mysqli($config['admin_host'], $config['admin_user'], $config['admin_pass'], $config['admin_name']);
            
            if ($adminDb->connect_error) {
                throw new Exception("Database connection error: " . $adminDb->connect_error);
            }
            
            // Test the connection with a simple query
            $testResult = $adminDb->query("SELECT 1");
            if (!$testResult) {
                throw new Exception("Database connection test failed: " . $adminDb->error);
            }
            
        } catch (Exception $e) {
            $result['checks']['database_connection'] = ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
            $result['success'] = false;
            return $result;
        }
        
        $result['checks']['database_connection'] = ['status' => 'success', 'message' => 'Connected to admin database'];
        
        // Check if username already exists
        $result['checks']['username_check'] = ['status' => 'checking', 'message' => 'Checking if username exists...'];
        
        try {
            $stmt = $adminDb->prepare("SELECT id FROM users WHERE username = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $adminDb->error);
            }
            
            $stmt->bind_param("s", $userData['username']);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $checkResult = $stmt->get_result();
            if ($checkResult->num_rows > 0) {
                $result['checks']['username_check'] = ['status' => 'error', 'message' => 'Username already exists'];
                $result['success'] = false;
                $stmt->close();
                return $result;
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $result['checks']['username_check'] = ['status' => 'error', 'message' => 'Username check failed: ' . $e->getMessage()];
            $result['success'] = false;
            return $result;
        }
        
        $result['checks']['username_check'] = ['status' => 'success', 'message' => 'Username available'];
        
        // Check if email already exists
        $result['checks']['email_check'] = ['status' => 'checking', 'message' => 'Checking if email exists...'];
        
        try {
            $stmt = $adminDb->prepare("SELECT id FROM users WHERE email = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $adminDb->error);
            }
            
            $stmt->bind_param("s", $userData['email']);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $checkResult = $stmt->get_result();
            if ($checkResult->num_rows > 0) {
                $result['checks']['email_check'] = ['status' => 'error', 'message' => 'Email already exists'];
                $result['success'] = false;
                $stmt->close();
                return $result;
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $result['checks']['email_check'] = ['status' => 'error', 'message' => 'Email check failed: ' . $e->getMessage()];
            $result['success'] = false;
            return $result;
        }
        
        $result['checks']['email_check'] = ['status' => 'success', 'message' => 'Email available'];
        
        // Create admin user
        $result['checks']['user_creation'] = ['status' => 'checking', 'message' => 'Creating admin user...'];
        
        try {
            $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            $stmt = $adminDb->prepare("INSERT INTO users (username, password_hash, full_name, email, role, is_active, created_at) VALUES (?, ?, ?, ?, 'admin', 1, NOW())");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $adminDb->error);
            }
            
            $stmt->bind_param("ssss", $userData['username'], $passwordHash, $userData['full_name'], $userData['email']);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $userId = $adminDb->insert_id;
            $stmt->close();
            
            if ($userId <= 0) {
                throw new Exception("User creation failed - no insert ID returned");
            }
            
            $result['checks']['user_creation'] = ['status' => 'success', 'message' => "Admin user created successfully (ID: $userId)"];
            
        } catch (Exception $e) {
            $result['checks']['user_creation'] = ['status' => 'error', 'message' => 'User creation failed: ' . $e->getMessage()];
            $result['success'] = false;
            return $result;
        }
        
        // Verify user was created
        $result['checks']['user_verification'] = ['status' => 'checking', 'message' => 'Verifying user creation...'];
        
        try {
            $stmt = $adminDb->prepare("SELECT id, username, role FROM users WHERE username = ? AND role = 'admin'");
            $stmt->bind_param("s", $userData['username']);
            $stmt->execute();
            $verifyResult = $stmt->get_result();
            
            if ($verifyResult->num_rows === 1) {
                $user = $verifyResult->fetch_assoc();
                $result['checks']['user_verification'] = ['status' => 'success', 'message' => "Admin user verified (ID: {$user['id']})"];
            } else {
                $result['checks']['user_verification'] = ['status' => 'error', 'message' => 'User verification failed - user not found after creation'];
                $result['success'] = false;
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $result['checks']['user_verification'] = ['status' => 'error', 'message' => 'User verification failed: ' . $e->getMessage()];
            $result['success'] = false;
        }
        
        // Close database connection
        $adminDb->close();
        
    } catch (Exception $e) {
        error_log("Admin user creation exception: " . $e->getMessage());
        $result['checks']['general_error'] = ['status' => 'error', 'message' => 'Unexpected error: ' . $e->getMessage()];
        $result['success'] = false;
    } catch (Error $e) {
        error_log("Admin user creation fatal error: " . $e->getMessage());
        $result['checks']['fatal_error'] = ['status' => 'error', 'message' => 'Fatal error: ' . $e->getMessage()];
        $result['success'] = false;
    }
    
    return $result;
}

function generateAuthConfigFile($config) {
    return '<?php

/* auth_config.php - Generated by GhostCrew Installer */

// Prevent multiple inclusions
if (defined(\'GHOSTCREW_AUTH_CONFIG_LOADED\')) {
    return;
}
define(\'GHOSTCREW_AUTH_CONFIG_LOADED\', true);

// Authentication Configuration for GhostCrew

// CRITICAL: Fix timezone synchronization between PHP and MySQL
date_default_timezone_set(\'UTC\');

// Configure session settings ONLY if session is not already active
if (session_status() === PHP_SESSION_NONE) {
    ini_set(\'session.cookie_httponly\', 1);
    ini_set(\'session.use_only_cookies\', 1);
    ini_set(\'session.cookie_lifetime\', 0);
    ini_set(\'session.gc_maxlifetime\', 7200);
    session_start();
}

// Admin database configuration
if (!defined(\'ADMIN_DB_HOST\')) {
    define(\'ADMIN_DB_HOST\', \'' . addslashes($config['admin_host']) . '\');
}
if (!defined(\'ADMIN_DB_USER\')) {
    define(\'ADMIN_DB_USER\', \'' . addslashes($config['admin_user']) . '\');
}
if (!defined(\'ADMIN_DB_PASS\')) {
    define(\'ADMIN_DB_PASS\', \'' . addslashes($config['admin_pass']) . '\');
}
if (!defined(\'ADMIN_DB_NAME\')) {
    define(\'ADMIN_DB_NAME\', \'' . addslashes($config['admin_name']) . '\');
}

// Security settings
if (!defined(\'SESSION_TIMEOUT\')) {
    define(\'SESSION_TIMEOUT\', 3600);
}
if (!defined(\'MAX_LOGIN_ATTEMPTS\')) {
    define(\'MAX_LOGIN_ATTEMPTS\', 5);
}
if (!defined(\'LOGIN_LOCKOUT_TIME\')) {
    define(\'LOGIN_LOCKOUT_TIME\', 900);
}
if (!defined(\'PASSWORD_MIN_LENGTH\')) {
    define(\'PASSWORD_MIN_LENGTH\', 8);
}
if (!defined(\'INSTANCE_TOKEN_LIFETIME\')) {
    define(\'INSTANCE_TOKEN_LIFETIME\', 28800);
}

// Create admin database connection - FIXED to only connect when databases exist
if (!function_exists(\'getAdminDB\')) {
    function getAdminDB() {
        static $adminConn = null;
        
        if ($adminConn === null) {
            try {
                // First check if database exists
                $testConn = new mysqli(ADMIN_DB_HOST, ADMIN_DB_USER, ADMIN_DB_PASS);
                if ($testConn->connect_error) {
                    throw new Exception("Cannot connect to MySQL server: " . $testConn->connect_error);
                }
                
                // Check if database exists
                $dbCheck = $testConn->query("SHOW DATABASES LIKE \'" . ADMIN_DB_NAME . "\'");
                if ($dbCheck->num_rows === 0) {
                    $testConn->close();
                    throw new Exception("Database \'" . ADMIN_DB_NAME . "\' does not exist yet");
                }
                $testConn->close();
                
                // Database exists, now connect to it
                $adminConn = new mysqli(ADMIN_DB_HOST, ADMIN_DB_USER, ADMIN_DB_PASS, ADMIN_DB_NAME);
                
                if ($adminConn->connect_error) {
                    throw new Exception("Admin database connection failed: " . $adminConn->connect_error);
                }
                
                $adminConn->query("SET time_zone = \'+00:00\'");
                
                // Test the connection with a simple query
                $testResult = $adminConn->query("SELECT 1");
                if (!$testResult) {
                    throw new Exception("Database connection test failed: " . $adminConn->error);
                }
                
            } catch (Exception $e) {
                error_log("getAdminDB error: " . $e->getMessage());
                throw $e;
            }
        }
        
        return $adminConn;
    }
}

// Generate secure session token
if (!function_exists(\'generateSessionToken\')) {
    function generateSessionToken() {
        return bin2hex(random_bytes(32));
    }
}

// Generate unique session ID
if (!function_exists(\'generateSessionId\')) {
    function generateSessionId() {
        return "sess_" . date("Ymd_His") . "_" . bin2hex(random_bytes(8));
    }
}

// Generate unique instance token for user session
if (!function_exists(\'generateInstanceToken\')) {
    function generateInstanceToken($userId) {
        $adminDb = getAdminDB();
        
        // Clean up expired tokens
        $adminDb->query("DELETE FROM user_instance_tokens WHERE expires_at < NOW()");
        
        // Deactivate old tokens for this user
        $stmt = $adminDb->prepare("UPDATE user_instance_tokens SET is_active = 0 WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        // Generate new token
        $instanceToken = "inst_" . $userId . "_" . time() . "_" . bin2hex(random_bytes(16));
        $expiresAt = date("Y-m-d H:i:s", time() + INSTANCE_TOKEN_LIFETIME);
        
        $stmt = $adminDb->prepare("INSERT INTO user_instance_tokens (user_id, instance_token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userId, $instanceToken, $expiresAt);
        
        if ($stmt->execute()) {
            return $instanceToken;
        }
        
        return false;
    }
}

// Get current user"s instance token
if (!function_exists(\'getCurrentInstanceToken\')) {
    function getCurrentInstanceToken() {
        if (!isset($_SESSION["user_id"])) {
            return null;
        }
        
        if (!isset($_SESSION["instance_token"])) {
            $_SESSION["instance_token"] = generateInstanceToken($_SESSION["user_id"]);
        }
        
        return $_SESSION["instance_token"];
    }
}

// Validate instance token
if (!function_exists(\'validateInstanceToken\')) {
    function validateInstanceToken($token) {
        if (empty($token)) {
            return false;
        }
        
        $adminDb = getAdminDB();
        $stmt = $adminDb->prepare("SELECT user_id FROM user_instance_tokens WHERE instance_token = ? AND is_active = 1 AND expires_at > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
}

// Check if user is authenticated
if (!function_exists(\'isAuthenticated\')) {
    function isAuthenticated() {
        // Check for required session variables
        if (!isset($_SESSION["user_id"]) || !isset($_SESSION["session_token"])) {
            return false;
        }
        
        try {
            $adminDb = getAdminDB();
            
            // First, get the session data
            $stmt = $adminDb->prepare("SELECT user_id, last_activity, is_active FROM user_sessions WHERE session_token = ?");
            $stmt->bind_param("s", $_SESSION["session_token"]);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Session not found in database
                return false;
            }
            
            $session = $result->fetch_assoc();
            
            // Check if session is active
            if (!$session["is_active"]) {
                return false;
            }
            
            // Check if user ID matches
            if ($session["user_id"] != $_SESSION["user_id"]) {
                return false;
            }
            
            // Now check if the user is active
            $stmt = $adminDb->prepare("SELECT is_active FROM users WHERE id = ?");
            $stmt->bind_param("i", $session["user_id"]);
            $stmt->execute();
            $userResult = $stmt->get_result();
            
            if ($userResult->num_rows === 0) {
                // User not found
                return false;
            }
            
            $user = $userResult->fetch_assoc();
            if (!$user["is_active"]) {
                // User is not active
                return false;
            }
            
            // Check session timeout - but be more lenient
            $lastActivity = strtotime($session["last_activity"]);
            $currentTime = time();
            $timeDiff = $currentTime - $lastActivity;
            
            // Only timeout if it"s been more than the session timeout AND more than 5 minutes
            if ($timeDiff > SESSION_TIMEOUT && $timeDiff > 300) {
                return false;
            }
            
            // Update last activity - but only if more than 60 seconds have passed
            if ($timeDiff > 60) {
                $stmt = $adminDb->prepare("UPDATE user_sessions SET last_activity = CURRENT_TIMESTAMP WHERE session_token = ?");
                $stmt->bind_param("s", $_SESSION["session_token"]);
                $stmt->execute();
            }
            
            // Ensure user has valid instance token
            if (!isset($_SESSION["instance_token"]) || !validateInstanceToken($_SESSION["instance_token"])) {
                $_SESSION["instance_token"] = generateInstanceToken($_SESSION["user_id"]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Authentication check failed: " . $e->getMessage());
            // On database errors, allow authentication to continue
            // This prevents login issues due to temporary DB problems
            return true;
        }
    }
}

// Get current user information
if (!function_exists(\'getCurrentUser\')) {
    function getCurrentUser() {
        if (!isAuthenticated()) {
            return null;
        }
        
        $adminDb = getAdminDB();
        $stmt = $adminDb->prepare("SELECT u.* FROM users u 
                                  JOIN user_sessions us ON u.id = us.user_id 
                                  WHERE us.session_token = ? AND us.is_active = 1");
        $stmt->bind_param("s", $_SESSION["session_token"]);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0 ? $result->fetch_assoc() : null;
    }
}

// Authenticate user
if (!function_exists(\'authenticateUser\')) {
    function authenticateUser($username, $password) {
        try {
            $adminDb = getAdminDB();
            
            // Check for too many failed attempts
            $ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
            $stmt = $adminDb->prepare("SELECT COUNT(*) as attempts FROM audit_log 
                                      WHERE action_type = \'login\' 
                                      AND JSON_EXTRACT(action_details, \'$.success\') = false 
                                      AND ip_address = ? 
                                      AND timestamp > DATE_SUB(NOW(), INTERVAL ? SECOND)");
            $lockoutTime = LOGIN_LOCKOUT_TIME;
            $stmt->bind_param("si", $ip, $lockoutTime);
            $stmt->execute();
            $attempts = $stmt->get_result()->fetch_assoc()["attempts"];
            
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                logAuditEvent(null, "login", [
                    "success" => false,
                    "username" => $username,
                    "reason" => "too_many_attempts",
                    "ip_lockout" => true
                ]);
                return ["success" => false, "message" => "Too many failed attempts. Please try again later."];
            }
            
            // Get user
            $stmt = $adminDb->prepare("SELECT id, username, password_hash, is_active, full_name FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                logAuditEvent(null, "login", [
                    "success" => false,
                    "username" => $username,
                    "reason" => "user_not_found"
                ]);
                return ["success" => false, "message" => "Invalid username or password."];
            }
            
            $user = $result->fetch_assoc();
            
            if (!$user["is_active"]) {
                logAuditEvent($user["id"], "login", [
                    "success" => false,
                    "username" => $username,
                    "reason" => "account_disabled"
                ]);
                return ["success" => false, "message" => "Account is disabled."];
            }
            
            if (!password_verify($password, $user["password_hash"])) {
                logAuditEvent($user["id"], "login", [
                    "success" => false,
                    "username" => $username,
                    "reason" => "invalid_password"
                ]);
                return ["success" => false, "message" => "Invalid username or password."];
            }
            
            // Create session
            $sessionToken = generateSessionToken();
            $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? "";
            
            $stmt = $adminDb->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $user["id"], $sessionToken, $ip, $userAgent);
            
            if (!$stmt->execute()) {
                return ["success" => false, "message" => "Failed to create session."];
            }
            
            // Generate instance token
            $instanceToken = generateInstanceToken($user["id"]);
            if (!$instanceToken) {
                return ["success" => false, "message" => "Failed to create instance token."];
            }
            
            // Update last login
            $stmt = $adminDb->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("i", $user["id"]);
            $stmt->execute();
            
            // Set session variables
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["session_token"] = $sessionToken;
            $_SESSION["instance_token"] = $instanceToken;
            $_SESSION["login_time"] = time();
            $_SESSION["last_activity"] = time();
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            logAuditEvent($user["id"], "login", [
                "success" => true,
                "username" => $username,
                "session_token" => substr($sessionToken, 0, 8) . "...",
                "instance_token" => substr($instanceToken, 0, 8) . "..."
            ]);
            
            return ["success" => true, "message" => "Login successful"];
            
        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            return ["success" => false, "message" => "Authentication system error."];
        }
    }
}

// Logout user
if (!function_exists(\'logoutUser\')) {
    function logoutUser() {
        if (isset($_SESSION["session_token"])) {
            $adminDb = getAdminDB();
            $stmt = $adminDb->prepare("UPDATE user_sessions SET logout_time = CURRENT_TIMESTAMP, is_active = 0 WHERE session_token = ?");
            $stmt->bind_param("s", $_SESSION["session_token"]);
            $stmt->execute();
            
            // Deactivate instance token
            if (isset($_SESSION["instance_token"])) {
                $stmt = $adminDb->prepare("UPDATE user_instance_tokens SET is_active = 0 WHERE instance_token = ?");
                $stmt->bind_param("s", $_SESSION["instance_token"]);
                $stmt->execute();
            }
            
            logAuditEvent($_SESSION["user_id"] ?? null, "logout", [
                "session_token" => substr($_SESSION["session_token"], 0, 8) . "...",
                "instance_token" => substr($_SESSION["instance_token"] ?? "", 0, 8) . "..."
            ]);
        }
        
        // Clear session
        session_unset();
        session_destroy();
        session_start(); // Start new session
    }
}

// Log audit events
if (!function_exists(\'logAuditEvent\')) {
    function logAuditEvent($userId, $actionType, $actionDetails = []) {
        $adminDb = getAdminDB();
        $ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
        $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? "";
        $actionDetailsJson = json_encode($actionDetails);
        
        $stmt = $adminDb->prepare("INSERT INTO audit_log (user_id, action_type, action_details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $userId, $actionType, $actionDetailsJson, $ip, $userAgent);
        $stmt->execute();
    }
}

// Log chatbot interaction
if (!function_exists(\'logChatbotInteraction\')) {
    function logChatbotInteraction($userId, $sessionId, $conversationId, $messageType, $message, $contextData = [], $responseTime = null) {
        $adminDb = getAdminDB();
        $contextJson = json_encode($contextData);
        
        $stmt = $adminDb->prepare("INSERT INTO chatbot_conversations (user_id, session_id, conversation_id, message_type, message, context_data, response_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssd", $userId, $sessionId, $conversationId, $messageType, $message, $contextJson, $responseTime);
        $stmt->execute();
        
        return $adminDb->insert_id;
    }
}

// Get or create conversation ID for session
if (!function_exists(\'getOrCreateConversationId\')) {
    function getOrCreateConversationId($sessionId) {
        $adminDb = getAdminDB();
        
        // Check if conversation exists for this session
        $stmt = $adminDb->prepare("SELECT DISTINCT conversation_id FROM chatbot_conversations WHERE session_id = ? ORDER BY timestamp DESC LIMIT 1");
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc()["conversation_id"];
        }
        
        // Create new conversation ID
        return "conv_" . $sessionId . "_" . time();
    }
}

// Store command suggestion
if (!function_exists(\'storeCommandSuggestion\')) {
    function storeCommandSuggestion($conversationId, $userId, $command, $description, $context = null) {
        $adminDb = getAdminDB();
        
        $stmt = $adminDb->prepare("INSERT INTO command_suggestions (conversation_id, user_id, suggested_command, command_description, suggestion_context) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sisss", $conversationId, $userId, $command, $description, $context);
        $stmt->execute();
        
        return $adminDb->insert_id;
    }
}

// Mark command suggestion as executed
if (!function_exists(\'markSuggestionExecuted\')) {
    function markSuggestionExecuted($suggestionId) {
        $adminDb = getAdminDB();
        
        $stmt = $adminDb->prepare("UPDATE command_suggestions SET executed = 1, executed_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("i", $suggestionId);
        $stmt->execute();
    }
}

// Require authentication for protected pages
if (!function_exists(\'requireAuth\')) {
    function requireAuth() {
        if (!isAuthenticated()) {
            // Don"t call logoutUser() here - that"s what"s ending the session immediately!
            // Just clear the invalid session variables
            if (isset($_SESSION["user_id"]) || isset($_SESSION["session_token"])) {
                unset($_SESSION["user_id"]);
                unset($_SESSION["username"]);
                unset($_SESSION["session_token"]);
                unset($_SESSION["instance_token"]);
            }
            
            if (isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest") {
                // AJAX request
                http_response_code(401);
                echo json_encode(["success" => false, "message" => "Authentication required", "redirect" => "login.php"]);
                exit;
            } else {
                // Regular request
                header("Location: login.php?redirect=" . urlencode($_SERVER["REQUEST_URI"]));
                exit;
            }
        }
    }
}

// Sanitize input (enhanced version)
if (!function_exists(\'sanitizeInput\')) {
    function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map("sanitizeInput", $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, "UTF-8");
    }
}

// Check user permission
if (!function_exists(\'hasPermission\')) {
    function hasPermission($userId, $permission) {
        $user = getCurrentUser();
        if (!$user) return false;
        
        // Admin has all permissions
        if ($user["role"] === "admin") return true;
        
        // Define role permissions
        $permissions = [
            "manager" => ["view_all_sessions", "view_reports", "manage_users"],
            "operator" => ["execute_commands", "view_own_sessions"]
        ];
        
        return isset($permissions[$user["role"]]) && in_array($permission, $permissions[$user["role"]]);
    }
}

// CSRF Protection
if (!function_exists(\'generateCSRFToken\')) {
    function generateCSRFToken() {
        if (!isset($_SESSION["csrf_token"])) {
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        }
        return $_SESSION["csrf_token"];
    }
}

if (!function_exists(\'validateCSRFToken\')) {
    function validateCSRFToken($token) {
        return isset($_SESSION["csrf_token"]) && hash_equals($_SESSION["csrf_token"], $token);
    }
}
?>';
}

// Updated SQL functions with the exact content from the provided files
function getTerminalAppSQL() {
    return 'SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS `terminal_app` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `terminal_app`;

CREATE TABLE `command_history` (
  `id` int(11) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `is_interactive` tinyint(1) DEFAULT 0,
  `working_directory` text DEFAULT NULL,
  `command` text NOT NULL,
  `output` longtext DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `execution_start` timestamp NULL DEFAULT NULL,
  `response_timestamp` timestamp NULL DEFAULT NULL,
  `execution_time` decimal(10,6) DEFAULT NULL,
  `exit_code` int(11) DEFAULT NULL,
  `status` enum(\'pending\',\'executing\',\'completed\',\'failed\',\'timeout\') DEFAULT \'pending\',
  `context_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context_data`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `command_statistics` (
  `id` int(11) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `command_base` varchar(100) NOT NULL,
  `execution_count` int(11) DEFAULT 1,
  `avg_execution_time` decimal(10,6) DEFAULT NULL,
  `last_executed` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `success_rate` decimal(5,2) DEFAULT 100.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `hosts` (
  `id` int(11) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `os_info` text DEFAULT NULL,
  `last_seen` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `connected` tinyint(1) DEFAULT 1,
  `first_seen` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_sessions` int(11) DEFAULT 0,
  `total_commands` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `host_instance_mappings` (
  `id` int(11) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `instance_token` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `mapped_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `interactive_command_patterns` (
  `id` int(11) NOT NULL,
  `command_pattern` varchar(255) NOT NULL,
  `pattern_type` enum(\'exact\',\'prefix\',\'regex\',\'contains\') DEFAULT \'exact\',
  `is_interactive` tinyint(1) DEFAULT 1,
  `is_continuous` tinyint(1) DEFAULT 0,
  `timeout_seconds` int(11) DEFAULT 1800,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `interactive_command_patterns` (`id`, `command_pattern`, `pattern_type`, `is_interactive`, `is_continuous`, `timeout_seconds`, `description`, `created_at`, `is_active`) VALUES
(1, \'msfconsole\', \'exact\', 1, 0, 1800, \'Metasploit\', \'2025-07-07 23:54:06\', 1),
(2, \'ssh\', \'prefix\', 1, 0, 1800, \'SSH remote access\', \'2025-07-07 23:54:06\', 1),
(3, \'python\', \'exact\', 1, 0, 1800, \'Python interpreter\', \'2025-07-07 23:54:06\', 1),
(4, \'python3\', \'exact\', 1, 0, 1800, \'Python 3 interpreter\', \'2025-07-07 23:54:06\', 1),
(5, \'mysql\', \'prefix\', 1, 0, 1800, \'MySQL client\', \'2025-07-07 23:54:06\', 1),
(6, \'vim\', \'prefix\', 1, 0, 1800, \'Vim editor\', \'2025-07-07 23:54:06\', 1),
(7, \'nano\', \'prefix\', 1, 0, 1800, \'Nano editor\', \'2025-07-07 23:54:06\', 1),
(8, \'top\', \'exact\', 1, 0, 1800, \'System monitor\', \'2025-07-07 23:54:06\', 1),
(9, \'ping\', \'prefix\', 1, 0, 1800, \'Network ping\', \'2025-07-07 23:54:06\', 1),
(10, \'telnet\', \'exact\', 1, 0, 1800, \'Telnet client - exact match\', \'2025-07-08 00:24:32\', 1),
(11, \'telnet \', \'prefix\', 1, 0, 1800, \'Telnet client with arguments\', \'2025-07-08 00:24:32\', 1);

CREATE TABLE `shell_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `current_directory` text DEFAULT NULL,
  `initial_directory` text DEFAULT NULL,
  `environment_vars` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`environment_vars`)),
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `streaming_output` (
  `id` int(11) NOT NULL,
  `command_id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `output_chunk` longtext NOT NULL,
  `chunk_sequence` int(11) DEFAULT 1,
  `is_partial` tinyint(1) DEFAULT 1,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `streaming_sessions` (
  `id` int(11) NOT NULL,
  `command_id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `status` enum(\'active\',\'paused\',\'completed\',\'terminated\') DEFAULT \'active\',
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_input_lines` int(11) DEFAULT 0,
  `total_output_size` bigint(20) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_input_queue` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `command_id` int(11) DEFAULT NULL,
  `input_data` text NOT NULL,
  `input_type` enum(\'command\',\'response\',\'ctrl_signal\') DEFAULT \'response\',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed` tinyint(1) DEFAULT 0,
  `processed_at` timestamp NULL DEFAULT NULL,
  `priority` tinyint(4) DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `command_history` ADD PRIMARY KEY (`id`), ADD KEY `idx_host_session` (`host_id`,`session_id`), ADD KEY `idx_session_time` (`session_id`,`timestamp`), ADD KEY `idx_status_time` (`status`,`timestamp`), ADD KEY `idx_working_dir` (`host_id`,`working_directory`(100)), ADD KEY `idx_is_interactive` (`is_interactive`), ADD KEY `idx_status` (`status`);
ALTER TABLE `command_statistics` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `unique_host_command` (`host_id`,`command_base`), ADD KEY `idx_host_stats` (`host_id`,`execution_count`);
ALTER TABLE `hosts` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `host_id` (`host_id`), ADD KEY `idx_host_status` (`connected`,`last_seen`), ADD KEY `idx_host_id` (`host_id`);
ALTER TABLE `host_instance_mappings` ADD PRIMARY KEY (`id`), ADD KEY `idx_host_instance` (`host_id`,`instance_token`), ADD KEY `idx_instance_active` (`instance_token`,`is_active`), ADD KEY `idx_token_expires` (`instance_token`,`expires_at`,`is_active`);
ALTER TABLE `interactive_command_patterns` ADD PRIMARY KEY (`id`);
ALTER TABLE `shell_sessions` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `session_id` (`session_id`), ADD KEY `idx_session_active` (`session_id`,`is_active`), ADD KEY `idx_session_host` (`host_id`,`is_active`,`last_activity`);
ALTER TABLE `streaming_output` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `unique_command_sequence` (`command_id`,`chunk_sequence`), ADD KEY `idx_session_time` (`session_id`,`last_update`), ADD KEY `idx_command_id` (`command_id`);
ALTER TABLE `streaming_sessions` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `command_id` (`command_id`), ADD KEY `idx_session_status` (`session_id`,`status`);
ALTER TABLE `user_input_queue` ADD PRIMARY KEY (`id`), ADD KEY `idx_session_processed` (`session_id`,`processed`,`timestamp`), ADD KEY `idx_processed` (`processed`);

ALTER TABLE `command_history` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `command_statistics` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `hosts` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `host_instance_mappings` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `interactive_command_patterns` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
ALTER TABLE `shell_sessions` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `streaming_output` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `streaming_sessions` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `user_input_queue` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `command_history` ADD CONSTRAINT `command_history_ibfk_1` FOREIGN KEY (`host_id`) REFERENCES `hosts` (`host_id`) ON DELETE CASCADE;
ALTER TABLE `command_statistics` ADD CONSTRAINT `command_statistics_ibfk_1` FOREIGN KEY (`host_id`) REFERENCES `hosts` (`host_id`) ON DELETE CASCADE;
ALTER TABLE `host_instance_mappings` ADD CONSTRAINT `host_instance_mappings_ibfk_1` FOREIGN KEY (`host_id`) REFERENCES `hosts` (`host_id`) ON DELETE CASCADE;
ALTER TABLE `shell_sessions` ADD CONSTRAINT `shell_sessions_ibfk_1` FOREIGN KEY (`host_id`) REFERENCES `hosts` (`host_id`) ON DELETE CASCADE;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;';
}

function getGhostcrewAdminSQL() {
    return 'SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS `ghostcrew_admin` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ghostcrew_admin`;

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` enum(\'login\',\'logout\',\'command_execute\',\'session_start\',\'session_end\',\'chat_message\',\'system_access\') NOT NULL,
  `action_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`action_details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chatbot_conversations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `conversation_id` varchar(64) NOT NULL,
  `parent_message_id` int(11) DEFAULT NULL,
  `message_type` enum(\'user\',\'bot\') NOT NULL,
  `message` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `context_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context_data`)),
  `response_time` decimal(8,3) DEFAULT NULL,
  `message_tokens` int(11) DEFAULT NULL,
  `model_used` varchar(50) DEFAULT \'local\',
  `suggested_command` text DEFAULT NULL,
  `command_executed` tinyint(1) DEFAULT 0,
  `rating` tinyint(1) DEFAULT NULL,
  `flagged` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chatbot_feedback` (
  `id` int(11) NOT NULL,
  `conversation_id` varchar(64) NOT NULL,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `feedback_type` enum(\'helpful\',\'not_helpful\',\'incorrect\',\'suggestion\') NOT NULL,
  `feedback_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chatbot_knowledge_base` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `keywords` text DEFAULT NULL,
  `command_example` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `command_log` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `is_interactive` tinyint(1) DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `command` text NOT NULL,
  `output` longtext DEFAULT NULL,
  `execution_time` decimal(10,6) DEFAULT NULL,
  `status` enum(\'pending\',\'completed\',\'failed\',\'timeout\') DEFAULT \'pending\',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `response_timestamp` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `command_patterns` (
  `id` int(11) NOT NULL,
  `pattern` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `suggested_commands` text NOT NULL,
  `response_template` text NOT NULL,
  `match_type` enum(\'exact\',\'contains\',\'regex\') DEFAULT \'contains\',
  `priority` tinyint(1) DEFAULT 5,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `command_suggestions` (
  `id` int(11) NOT NULL,
  `conversation_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `suggested_command` text NOT NULL,
  `command_description` text DEFAULT NULL,
  `priority` tinyint(1) DEFAULT 5,
  `category` varchar(50) DEFAULT \'general\',
  `suggestion_context` text DEFAULT NULL,
  `executed` tinyint(1) DEFAULT 0,
  `executed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hosts_info` (
  `id` int(11) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `os_info` text DEFAULT NULL,
  `first_seen` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_seen` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_sessions` int(11) DEFAULT 0,
  `total_commands` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `remote_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `os_info` text DEFAULT NULL,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `status` enum(\'active\',\'disconnected\',\'terminated\') DEFAULT \'active\',
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_commands` int(11) DEFAULT 0,
  `session_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `session_contexts` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `conversation_id` varchar(64) NOT NULL,
  `context_type` enum(\'command_history\',\'system_info\',\'working_directory\') NOT NULL,
  `context_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context_data`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `session_feedback` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `overall_score` int(11) DEFAULT NULL,
  `instructor_feedback` text DEFAULT NULL,
  `command_feedback` longtext DEFAULT NULL,
  `rating` tinyint(4) DEFAULT NULL,
  `graded_by` int(11) DEFAULT NULL,
  `graded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_config` (
  `id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_interactions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `interaction_type` enum(\'input\',\'output\',\'termination\',\'command\') NOT NULL,
  `interaction_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`interaction_data`)),
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_config` (`id`, `config_key`, `config_value`, `description`, `updated_by`, `updated_at`) VALUES
(1, \'session_timeout\', \'3600\', \'User session timeout in seconds\', NULL, \'2025-06-01 18:25:18\'),
(2, \'max_command_history\', \'100000\', \'Maximum commands to keep in history per session\', NULL, \'2025-06-01 18:25:18\'),
(3, \'chatbot_enabled\', \'1\', \'Enable/disable chatbot functionality\', NULL, \'2025-05-26 22:03:43\'),
(4, \'audit_retention_days\', \'999999\', \'Days to retain audit logs\', NULL, \'2025-06-01 18:25:18\'),
(5, \'max_concurrent_sessions\', \'10\', \'Maximum concurrent sessions per user\', NULL, \'2025-05-26 22:03:43\');

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum(\'admin\',\'manager\',\'operator\') DEFAULT \'operator\',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_instance_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `instance_token` varchar(128) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(128) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `audit_log` ADD PRIMARY KEY (`id`), ADD KEY `idx_user_audit` (`user_id`,`timestamp`), ADD KEY `idx_action_time` (`action_type`,`timestamp`);
ALTER TABLE `chatbot_conversations` ADD PRIMARY KEY (`id`), ADD KEY `idx_user_conversation` (`user_id`,`conversation_id`,`timestamp`), ADD KEY `idx_session_chat` (`session_id`,`timestamp`), ADD KEY `idx_flagged_review` (`flagged`,`timestamp`), ADD KEY `idx_parent_message` (`parent_message_id`), ADD KEY `idx_conversation_thread` (`conversation_id`,`timestamp`), ADD KEY `idx_conversation_messages` (`conversation_id`,`timestamp`), ADD KEY `idx_user_conversations` (`user_id`,`timestamp`), ADD KEY `idx_message_search` (`message_type`,`timestamp`);
ALTER TABLE `chatbot_feedback` ADD PRIMARY KEY (`id`), ADD KEY `idx_conversation_feedback` (`conversation_id`,`created_at`), ADD KEY `idx_message_feedback` (`message_id`), ADD KEY `user_id` (`user_id`);
ALTER TABLE `chatbot_knowledge_base` ADD PRIMARY KEY (`id`), ADD KEY `idx_category` (`category`,`is_active`), ADD KEY `idx_keywords` (`keywords`(255));
ALTER TABLE `chatbot_knowledge_base` ADD FULLTEXT KEY `idx_question_answer` (`question`,`answer`,`keywords`);
ALTER TABLE `command_log` ADD PRIMARY KEY (`id`), ADD KEY `idx_session_time` (`session_id`,`timestamp`), ADD KEY `idx_user_commands` (`user_id`,`timestamp`);
ALTER TABLE `command_patterns` ADD PRIMARY KEY (`id`), ADD KEY `idx_pattern_category` (`category`,`is_active`,`priority`), ADD KEY `idx_pattern_active` (`is_active`,`priority`);
ALTER TABLE `command_suggestions` ADD PRIMARY KEY (`id`), ADD KEY `idx_conversation_suggestions` (`conversation_id`,`created_at`), ADD KEY `user_id` (`user_id`), ADD KEY `idx_executed_suggestions` (`executed`,`created_at`), ADD KEY `idx_user_suggestions` (`user_id`,`executed`,`created_at`), ADD KEY `idx_priority_category` (`category`,`priority`,`created_at`);
ALTER TABLE `hosts_info` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `host_id` (`host_id`), ADD KEY `idx_host_activity` (`is_active`,`last_seen`);
ALTER TABLE `remote_sessions` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `session_id` (`session_id`), ADD KEY `idx_user_host` (`user_id`,`host_id`), ADD KEY `idx_session_status` (`status`,`start_time`);
ALTER TABLE `session_contexts` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `unique_session_context` (`session_id`,`conversation_id`,`context_type`), ADD KEY `idx_session_context` (`session_id`,`context_type`), ADD KEY `idx_context_lookup` (`session_id`,`context_type`,`updated_at`);
ALTER TABLE `session_feedback` ADD PRIMARY KEY (`id`), ADD KEY `user_id` (`user_id`), ADD KEY `graded_by` (`graded_by`), ADD KEY `session_id` (`session_id`);
ALTER TABLE `system_config` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `config_key` (`config_key`), ADD KEY `updated_by` (`updated_by`);
ALTER TABLE `users` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `username` (`username`), ADD KEY `created_by` (`created_by`), ADD KEY `idx_manager_id` (`manager_id`);
ALTER TABLE `user_instance_tokens` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `instance_token` (`instance_token`), ADD KEY `user_id` (`user_id`);
ALTER TABLE `user_sessions` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `session_token` (`session_token`), ADD KEY `user_id` (`user_id`);
ALTER TABLE `user_interactions` ADD PRIMARY KEY (`id`), ADD KEY `idx_session_interactions` (`session_id`,`timestamp`), ADD KEY `user_id` (`user_id`);

ALTER TABLE `audit_log` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `chatbot_conversations` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `chatbot_feedback` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `chatbot_knowledge_base` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `command_log` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `command_patterns` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `command_suggestions` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `hosts_info` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `remote_sessions` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `session_contexts` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `session_feedback` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `system_config` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `user_instance_tokens` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `user_sessions` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `user_interactions` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `audit_log` ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `chatbot_conversations` ADD CONSTRAINT `chatbot_conversations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE, ADD CONSTRAINT `chatbot_conversations_ibfk_2` FOREIGN KEY (`session_id`) REFERENCES `remote_sessions` (`session_id`) ON DELETE SET NULL;
ALTER TABLE `chatbot_feedback` ADD CONSTRAINT `chatbot_feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE, ADD CONSTRAINT `chatbot_feedback_ibfk_2` FOREIGN KEY (`message_id`) REFERENCES `chatbot_conversations` (`id`) ON DELETE CASCADE;
ALTER TABLE `command_log` ADD CONSTRAINT `command_log_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `remote_sessions` (`session_id`) ON DELETE CASCADE, ADD CONSTRAINT `command_log_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `command_suggestions` ADD CONSTRAINT `command_suggestions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `remote_sessions` ADD CONSTRAINT `remote_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `session_contexts` ADD CONSTRAINT `session_contexts_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `remote_sessions` (`session_id`) ON DELETE CASCADE;
ALTER TABLE `session_feedback` ADD CONSTRAINT `session_feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE, ADD CONSTRAINT `session_feedback_ibfk_2` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL, ADD CONSTRAINT `session_feedback_ibfk_3` FOREIGN KEY (`session_id`) REFERENCES `remote_sessions` (`session_id`) ON DELETE CASCADE;
ALTER TABLE `system_config` ADD CONSTRAINT `system_config_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `users` ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL, ADD CONSTRAINT `users_manager_fk` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `user_instance_tokens` ADD CONSTRAINT `user_instance_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `user_sessions` ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `user_interactions` ADD CONSTRAINT `user_interactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;';
}


// Function to check for existing installation files and databases
function checkExistingInstallation() {
    $existing = [
        'config_files' => [],
        'databases' => [],
        'has_existing' => false
    ];
    
    // Check for config files
    if (file_exists('config.php')) {
        $existing['config_files'][] = 'config.php';
        $existing['has_existing'] = true;
    }
    
    if (file_exists('auth_config.php')) {
        $existing['config_files'][] = 'auth_config.php';
        $existing['has_existing'] = true;
    }
    
    // Check for databases if we have session config
    if (!empty($_SESSION['db_config'])) {
        $config = $_SESSION['db_config'];
        
        // Check terminal database
        try {
            $conn = new mysqli($config['terminal_host'], $config['terminal_user'], $config['terminal_pass']);
            if (!$conn->connect_error) {
                $dbCheck = $conn->query("SHOW DATABASES LIKE '" . $config['terminal_name'] . "'");
                if ($dbCheck && $dbCheck->num_rows > 0) {
                    $existing['databases'][] = $config['terminal_name'];
                    $existing['has_existing'] = true;
                }
            }
            $conn->close();
        } catch (Exception $e) {
            // Ignore connection errors for this check
        }
        
        // Check admin database
        try {
            $conn = new mysqli($config['admin_host'], $config['admin_user'], $config['admin_pass']);
            if (!$conn->connect_error) {
                $dbCheck = $conn->query("SHOW DATABASES LIKE '" . $config['admin_name'] . "'");
                if ($dbCheck && $dbCheck->num_rows > 0) {
                    $existing['databases'][] = $config['admin_name'];
                    $existing['has_existing'] = true;
                }
            }
            $conn->close();
        } catch (Exception $e) {
            // Ignore connection errors for this check
        }
    }
    
    return $existing;
}

// Function to clean up existing installation
function cleanupExistingInstallation($config = null) {
    $result = ['success' => true, 'cleaned' => [], 'errors' => []];
    
    // Remove config files
    $configFiles = ['config.php', 'auth_config.php'];
    foreach ($configFiles as $file) {
        if (file_exists($file)) {
            if (unlink($file)) {
                $result['cleaned'][] = "Deleted config file: $file";
            } else {
                $result['errors'][] = "Failed to delete config file: $file";
                $result['success'] = false;
            }
        }
    }
    
    // Remove admin directory if empty
    if (is_dir('admin') && count(scandir('admin')) <= 2) {
        if (rmdir('admin')) {
            $result['cleaned'][] = "Removed empty admin directory";
        }
    }
    
    // Drop databases if config is provided
    if ($config) {
        // Drop terminal database
        try {
            $conn = new mysqli($config['terminal_host'], $config['terminal_user'], $config['terminal_pass']);
            if (!$conn->connect_error) {
                $sql = "DROP DATABASE IF EXISTS `" . $config['terminal_name'] . "`";
                if ($conn->query($sql)) {
                    $result['cleaned'][] = "Dropped database: " . $config['terminal_name'];
                } else {
                    $result['errors'][] = "Failed to drop database: " . $config['terminal_name'] . " - " . $conn->error;
                }
            }
            $conn->close();
        } catch (Exception $e) {
            $result['errors'][] = "Error dropping terminal database: " . $e->getMessage();
        }
        
        // Drop admin database
        try {
            $conn = new mysqli($config['admin_host'], $config['admin_user'], $config['admin_pass']);
            if (!$conn->connect_error) {
                $sql = "DROP DATABASE IF EXISTS `" . $config['admin_name'] . "`";
                if ($conn->query($sql)) {
                    $result['cleaned'][] = "Dropped database: " . $config['admin_name'];
                } else {
                    $result['errors'][] = "Failed to drop database: " . $config['admin_name'] . " - " . $conn->error;
                }
            }
            $conn->close();
        } catch (Exception $e) {
            $result['errors'][] = "Error dropping admin database: " . $e->getMessage();
        }
    }
    
    return $result;
}

// AJAX handler for cleanup
function cleanupInstallationAjax() {
    $result = ['success' => true, 'checks' => []];
    
    try {
        $result['checks']['cleanup_start'] = ['status' => 'checking', 'message' => 'Starting cleanup process...'];
        
        // Get config from session if available
        $config = $_SESSION['db_config'] ?? null;
        
        // Perform cleanup
        $cleanupResult = cleanupExistingInstallation($config);
        
        if ($cleanupResult['success']) {
            $result['checks']['cleanup_complete'] = ['status' => 'success', 'message' => 'Cleanup completed successfully'];
            
            // Add details of what was cleaned
            if (!empty($cleanupResult['cleaned'])) {
                $result['checks']['cleanup_details'] = ['status' => 'success', 'message' => 'Cleaned items: ' . implode(', ', $cleanupResult['cleaned'])];
            }
            
            // Clear session
            unset($_SESSION['db_config']);
            
        } else {
            $result['checks']['cleanup_complete'] = ['status' => 'error', 'message' => 'Cleanup completed with errors'];
            $result['success'] = false;
            
            // Add error details
            if (!empty($cleanupResult['errors'])) {
                $result['checks']['cleanup_errors'] = ['status' => 'error', 'message' => 'Errors: ' . implode(', ', $cleanupResult['errors'])];
            }
        }
        
    } catch (Exception $e) {
        $result['checks']['cleanup_error'] = ['status' => 'error', 'message' => 'Cleanup failed: ' . $e->getMessage()];
        $result['success'] = false;
    }
    
    return $result;
}

function generateConfigFile($config) {
    return '<?php

/* config.php - Generated by GhostCrew Installer */

// Enhanced configuration with session support
define(\'DB_HOST\', \'' . addslashes($config['terminal_host']) . '\');
define(\'DB_USER\', \'' . addslashes($config['terminal_user']) . '\');
define(\'DB_PASS\', \'' . addslashes($config['terminal_pass']) . '\');
define(\'DB_NAME\', \'' . addslashes($config['terminal_name']) . '\');

// Application configuration
define(\'APP_URL\', \'' . addslashes($config['app_url']) . '\');
define(\'LOCAL_LISTENER_URL\', \'' . addslashes($config['app_url']) . '/local\');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set timezone
$conn->query("SET time_zone = \'+00:00\'");

// Enhanced sanitize function
function sanitize($input) {
    global $conn;
    if (is_array($input)) {
        return array_map(\'sanitize\', $input);
    }
    return $conn->real_escape_string(htmlspecialchars(trim($input), ENT_QUOTES, \'UTF-8\'));
}

// Function to log command execution
function logCommandExecution($hostId, $sessionId, $command, $output = null, $executionTime = null, $status = "completed") {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE command_history SET output = ?, execution_time = ?, status = ?, response_timestamp = CURRENT_TIMESTAMP WHERE host_id = ? AND session_id = ? AND command = ? AND status = \'pending\' ORDER BY timestamp DESC LIMIT 1");
    $stmt->bind_param("sdsssss", $output, $executionTime, $status, $hostId, $sessionId, $command);
    $stmt->execute();
    $stmt->close();
}

// Function to update shell session state
function updateShellSession($sessionId, $hostId, $currentDirectory = null, $environmentVars = null) {
    global $conn;
    
    // Check if session exists
    $stmt = $conn->prepare("SELECT id FROM shell_sessions WHERE session_id = ?");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing session
        $updateFields = ["last_activity = CURRENT_TIMESTAMP"];
        $params = "";
        $values = [];
        
        if ($currentDirectory !== null) {
            $updateFields[] = "current_directory = ?";
            $params .= "s";
            $values[] = $currentDirectory;
        }
        
        if ($environmentVars !== null) {
            $updateFields[] = "environment_vars = ?";
            $params .= "s";
            $values[] = json_encode($environmentVars);
        }
        
        $values[] = $sessionId;
        $params .= "s";
        
        $sql = "UPDATE shell_sessions SET " . implode(", ", $updateFields) . " WHERE session_id = ?";
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($params, ...$values);
        }
        $stmt->execute();
    } else {
        // Create new session
        $envJson = $environmentVars ? json_encode($environmentVars) : null;
        $stmt = $conn->prepare("INSERT INTO shell_sessions (session_id, host_id, current_directory, environment_vars) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $sessionId, $hostId, $currentDirectory, $envJson);
        $stmt->execute();
    }
    
    $stmt->close();
}

// Function to get shell session state
function getShellSession($sessionId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM shell_sessions WHERE session_id = ? AND is_active = 1");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $session = $result->fetch_assoc();
        if ($session["environment_vars"]) {
            $session["environment_vars"] = json_decode($session["environment_vars"], true);
        }
        return $session;
    }
    
    return null;
}

// Function to end shell session
function endShellSession($sessionId) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE shell_sessions SET is_active = 0 WHERE session_id = ?");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $stmt->close();
}

// Function to get host statistics
function getHostStatistics($hostId) {
    global $conn;
    
    $stats = [
        "total_sessions" => 0,
        "total_commands" => 0,
        "active_sessions" => 0,
        "last_activity" => null
    ];
    
    // Get total sessions from admin database if available
    if (function_exists("getAdminDB")) {
        try {
            $adminDb = getAdminDB();
            $stmt = $adminDb->prepare("SELECT COUNT(*) as count FROM remote_sessions WHERE host_id = ?");
            $stmt->bind_param("s", $hostId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $stats["total_sessions"] = $result->fetch_assoc()["count"];
            }
            
            $stmt = $adminDb->prepare("SELECT COUNT(*) as count FROM remote_sessions WHERE host_id = ? AND status = \'active\'");
            $stmt->bind_param("s", $hostId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $stats["active_sessions"] = $result->fetch_assoc()["count"];
            }
        } catch (Exception $e) {
            // Admin DB not available, use local data
        }
    }
    
    // Get command count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM command_history WHERE host_id = ?");
    $stmt->bind_param("s", $hostId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stats["total_commands"] = $result->fetch_assoc()["count"];
    }
    
    // Get last activity
    $stmt = $conn->prepare("SELECT MAX(timestamp) as last_activity FROM command_history WHERE host_id = ?");
    $stmt->bind_param("s", $hostId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stats["last_activity"] = $result->fetch_assoc()["last_activity"];
    }
    
    return $stats;
}

// Function to clean up old data (should be called periodically)
function cleanupOldData($daysToKeep = 30) {
    global $conn;
    
    // Clean up old command history
    $stmt = $conn->prepare("DELETE FROM command_history WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->bind_param("i", $daysToKeep);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    
    // Clean up inactive shell sessions older than 1 day
    $stmt = $conn->prepare("DELETE FROM shell_sessions WHERE is_active = 0 AND last_activity < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $stmt->execute();
    $deletedSessions = $stmt->affected_rows;
    $stmt->close();
    
    return [
        "commands_deleted" => $deleted,
        "sessions_deleted" => $deletedSessions
    ];
}

// Add error reporting for development (remove in production)
if (defined(\'DEBUG\') && DEBUG) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}


?>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GhostCrew Installation</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-bg: #0d1117;
            --secondary-bg: #161b22;
            --tertiary-bg: #21262d;
            --primary-text: #c9d1d9;
            --secondary-text: #8b949e;
            --accent-blue: #58a6ff;
            --accent-green: #3fb950;
            --accent-red: #f85149;
            --accent-yellow: #d29922;
            --border-color: #30363d;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--primary-bg);
            color: var(--primary-text);
            min-height: 100vh;
        }

        .install-container {
            max-width: 1000px;
            margin: 2rem auto;
            background: var(--secondary-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .install-header {
            background: var(--tertiary-bg);
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }

        .install-header h1 {
            color: var(--accent-blue);
            margin: 0;
            font-weight: 600;
        }

        .install-body {
            padding: 2rem;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .step {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background: var(--tertiary-bg);
            border-radius: 6px;
            margin: 0 0.5rem;
            border: 1px solid var(--border-color);
            font-size: 0.9rem;
        }

        .step.active {
            background: var(--accent-blue);
            color: white;
        }

        .step.completed {
            background: var(--accent-green);
            color: white;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            color: var(--primary-text);
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            background: var(--tertiary-bg);
            border: 1px solid var(--border-color);
            color: var(--primary-text);
            border-radius: 6px;
            padding: 0.75rem;
        }

        .form-control:focus {
            background: var(--tertiary-bg);
            border-color: var(--accent-blue);
            color: var(--primary-text);
            box-shadow: 0 0 0 0.2rem rgba(88, 166, 255, 0.25);
        }

        .btn-primary {
            background: var(--accent-blue);
            border: none;
            border-radius: 6px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }

        .btn-primary:hover {
            background: #4493e0;
        }

        .btn-success {
            background: var(--accent-green);
            border: none;
            border-radius: 6px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }

        .btn-secondary {
            background: var(--tertiary-bg);
            border: 1px solid var(--border-color);
            color: var(--primary-text);
            border-radius: 6px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }

        .btn-danger {
            background: var(--accent-red);
            border: none;
            border-radius: 6px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }

        .btn-warning {
            background: var(--accent-yellow);
            border: none;
            border-radius: 6px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            color: var(--primary-bg);
        }

        .alert {
            border-radius: 6px;
            border: none;
        }

        .alert-danger {
            background: rgba(248, 81, 73, 0.1);
            color: var(--accent-red);
            border: 1px solid rgba(248, 81, 73, 0.3);
        }

        .alert-success {
            background: rgba(63, 185, 80, 0.1);
            color: var(--accent-green);
            border: 1px solid rgba(63, 185, 80, 0.3);
        }

        .alert-info {
            background: rgba(88, 166, 255, 0.1);
            color: var(--accent-blue);
            border: 1px solid rgba(88, 166, 255, 0.3);
        }

        .alert-warning {
            background: rgba(210, 153, 34, 0.1);
            color: var(--accent-yellow);
            border: 1px solid rgba(210, 153, 34, 0.3);
        }

        .code-block {
            background: var(--tertiary-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 1rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.875rem;
            margin: 1rem 0;
        }

        .database-section {
            background: var(--tertiary-bg);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .database-section h4 {
            color: var(--accent-blue);
            margin-bottom: 1rem;
        }

        .status-checks {
            margin-top: 2rem;
        }

        .status-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: var(--tertiary-bg);
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .status-icon {
            width: 20px;
            height: 20px;
            margin-right: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .status-checking {
            color: var(--accent-blue);
        }

        .status-success {
            color: var(--accent-green);
        }

        .status-error {
            color: var(--accent-red);
        }

        .status-message {
            flex: 1;
        }

        .status-actions {
            display: flex;
            gap: 0.5rem;
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid var(--border-color);
            border-top: 2px solid var(--accent-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .row {
            margin: 0 -0.75rem;
        }

        .col-md-6 {
            padding: 0 0.75rem;
        }

        .realtime-section {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .realtime-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .installation-content-hidden {
            display: none !important;
        }
        
        .cleanup-overlay {
            position: relative;
        }
        
        .cleanup-overlay::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(13, 17, 23, 0.8);
            backdrop-filter: blur(2px);
            z-index: 10;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1><i class="fas fa-ghost"></i> GhostCrew Installation</h1>
            <p class="mb-0">Secure Remote Access Terminal Setup</p>
        </div>
        
        <div class="install-body">
            <!-- Existing Installation Warning -->
            <?php if ($showCleanupWarning): ?>
                <div class="alert alert-warning" id="existingInstallationWarning">
                    <h5><i class="fas fa-exclamation-triangle"></i> Existing Installation Detected</h5>
                    <p>The following components from a previous installation were found:</p>
                    
                    <?php if (!empty($existingInstallation['config_files'])): ?>
                        <p><strong>Configuration Files:</strong></p>
                        <ul>
                            <?php foreach ($existingInstallation['config_files'] as $file): ?>
                                <li><code><?php echo htmlspecialchars($file); ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if (!empty($existingInstallation['databases'])): ?>
                        <p><strong>Databases:</strong></p>
                        <ul>
                            <?php foreach ($existingInstallation['databases'] as $db): ?>
                                <li><code><?php echo htmlspecialchars($db); ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <div class="alert alert-danger mt-3">
                        <strong><i class="fas fa-exclamation-circle"></i> Warning:</strong> 
                        Proceeding with the installation will overwrite existing data. 
                        This action cannot be undone.
                    </div>
                    
                    <div class="text-center mt-3">
                        <button type="button" id="cleanupBtn" class="btn btn-danger">
                            <i class="fas fa-trash-alt"></i> Remove Existing Installation
                        </button>
                        <button type="button" id="proceedAnywayBtn" class="btn btn-warning">
                            <i class="fas fa-exclamation-triangle"></i> Proceed Anyway
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Cancel Installation
                        </a>
                    </div>
                </div>
                
                <!-- Cleanup Status Section -->
                <div class="realtime-section" id="cleanupResults" style="display: none;">
                    <div class="realtime-header">
                        <h5><i class="fas fa-trash-alt"></i> Cleanup Process</h5>
                    </div>
                    <div id="cleanupChecks" class="status-checks"></div>
                </div>
            <?php endif; ?>

            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                    <i class="fas fa-database me-2"></i> Database Config
                </div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                    <i class="fas fa-cogs me-2"></i> Create Databases
                </div>
                <div class="step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                    <i class="fas fa-check-double me-2"></i> Validate Tables
                </div>
                <div class="step <?php echo $step >= 4 ? 'active' : ''; ?> <?php echo $step > 4 ? 'completed' : ''; ?>">
                    <i class="fas fa-user-shield me-2"></i> Admin User
                </div>
                <div class="step <?php echo $step >= 5 ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle me-2"></i> Complete
                </div>
            </div>

            <!-- Display Errors -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle"></i> Installation Errors</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Display Success -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle"></i> Success!</h5>
                    <ul class="mb-0">
                        <?php foreach ($success as $msg): ?>
                            <li><?php echo htmlspecialchars($msg); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($installComplete && $hasAdmin): ?>
                <!-- Installation Already Complete -->
                <div class="alert alert-success">
                    <h5><i class="fas fa-check-circle"></i> Installation Complete!</h5>
                    <p class="mb-0">GhostCrew is already installed and configured with an active admin account.</p>
                </div>
                
                <div class="database-section">
                    <h4><i class="fas fa-info-circle"></i> Current Installation</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Configuration files present</li>
                                <li><i class="fas fa-check text-success me-2"></i> Database connections active</li>
                                <li><i class="fas fa-check text-success me-2"></i> Admin account configured</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> System ready for use</li>
                                <li><i class="fas fa-info-circle text-info me-2"></i> Installation: <?php echo date('Y-m-d H:i:s', filemtime('config.php')); ?></li>
                                <li><i class="fas fa-database text-primary me-2"></i> <?php 
                                    try {
                                        include_once 'auth_config.php';
                                        $adminDb = getAdminDB();
                                        $stmt = $adminDb->prepare("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        echo $result->fetch_assoc()['count'] . ' active users';
                                    } catch (Exception $e) {
                                        echo 'Database accessible';
                                    }
                                ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <div class="btn-group mb-3" role="group">
                        <a href="index.php" class="btn btn-success btn-lg">
                            <i class="fas fa-sign-in-alt"></i> Go to Login
                        </a>
                        <a href="admin/" class="btn btn-primary btn-lg">
                            <i class="fas fa-cog"></i> Admin Panel
                        </a>
                    </div>
                    
                    <div class="mt-3">
                        <button type="button" id="reinstallBtn" class="btn btn-warning">
                            <i class="fas fa-redo"></i> Reinstall GhostCrew
                        </button>
                        <small class="text-muted d-block mt-2">
                            Click this if you need to reconfigure or reinstall the system
                        </small>
                    </div>
                </div>

            <?php elseif ($step === 1): ?>
                <!-- Step 1: Database Configuration & Config File Creation -->
                <h3><i class="fas fa-database"></i> Database Configuration & Config Files</h3>
                <p>Configure the database connections and create configuration files for GhostCrew. Two separate databases are required:</p>
                
                <form method="POST" id="dbConfigForm">
                    <div class="database-section">
                        <h4><i class="fas fa-terminal"></i> Terminal Application Database</h4>
                        <p class="text-muted">Stores command history, host information, and session data.</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Database Host</label>
                                    <input type="text" name="terminal_host" class="form-control" value="<?php echo htmlspecialchars($_POST['terminal_host'] ?? 'localhost'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Database Name</label>
                                    <input type="text" name="terminal_name" class="form-control" value="<?php echo htmlspecialchars($_POST['terminal_name'] ?? 'terminal_app'); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="terminal_user" class="form-control" value="<?php echo htmlspecialchars($_POST['terminal_user'] ?? 'svc_terminal-app'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="terminal_pass" class="form-control" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="database-section">
                        <h4><i class="fas fa-shield-alt"></i> Admin & Authentication Database</h4>
                        <p class="text-muted">Stores user accounts, sessions, audit logs, and chat data.</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Database Host</label>
                                    <input type="text" name="admin_host" class="form-control" value="<?php echo htmlspecialchars($_POST['admin_host'] ?? 'localhost'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Database Name</label>
                                    <input type="text" name="admin_name" class="form-control" value="<?php echo htmlspecialchars($_POST['admin_name'] ?? 'ghostcrew_admin'); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="admin_user" class="form-control" value="<?php echo htmlspecialchars($_POST['admin_user'] ?? 'svc_ghostcrew_admin'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="admin_pass" class="form-control" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="database-section">
                        <h4><i class="fas fa-globe"></i> Application Configuration</h4>
                        
                        <div class="form-group">
                            <label class="form-label">Application URL</label>
                            <input type="url" name="app_url" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['app_url'] ?? 'http://localhost/GhostCrew'); ?>" 
                                   placeholder="http://localhost/GhostCrew" required>
                            <small class="text-muted">The full URL where GhostCrew will be accessible.</small>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="button" id="testConnectionsBtn" class="btn btn-primary">
                            <i class="fas fa-database"></i> Test Connections
                        </button>
                        <button type="button" id="createConfigBtn" class="btn btn-warning" disabled>
                            <i class="fas fa-file-code"></i> Create Config Files
                        </button>
                        <button type="submit" id="continueBtn" class="btn btn-success" disabled>
                            <i class="fas fa-arrow-right"></i> Continue to Step 2
                        </button>
                    </div>
                </form>
                
                <!-- Real-time Status Sections -->
                <div class="realtime-section" id="dbTestResults" style="display: none;">
                    <div class="realtime-header">
                        <h5><i class="fas fa-chart-line"></i> Database Connection Tests</h5>
                    </div>
                    <div id="dbStatusChecks" class="status-checks"></div>
                </div>
                
                <div class="realtime-section" id="configCreationResults" style="display: none;">
                    <div class="realtime-header">
                        <h5><i class="fas fa-file-code"></i> Configuration File Creation</h5>
                    </div>
                    <div id="configCreationChecks" class="status-checks"></div>
                </div>
                
            <?php elseif ($step === 2): ?>
                <!-- Step 2: Create Databases -->
                <h3><i class="fas fa-cogs"></i> Create Databases</h3>
                <p>This step will create the database structures for GhostCrew.</p>
                
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Database Configuration</h5>
                    <p><strong>Terminal Database:</strong> <?php echo htmlspecialchars($_SESSION['db_config']['terminal_user']); ?>@<?php echo htmlspecialchars($_SESSION['db_config']['terminal_host']); ?>/<?php echo htmlspecialchars($_SESSION['db_config']['terminal_name']); ?></p>
                    <p><strong>Admin Database:</strong> <?php echo htmlspecialchars($_SESSION['db_config']['admin_user']); ?>@<?php echo htmlspecialchars($_SESSION['db_config']['admin_host']); ?>/<?php echo htmlspecialchars($_SESSION['db_config']['admin_name']); ?></p>
                    <p><strong>Config Files:</strong> config.php and auth_config.php created</p>
                </div>
                
                <div class="text-center mb-4">
                    <button type="button" id="createDbBtn" class="btn btn-primary">
                        <i class="fas fa-database"></i> Create Databases
                    </button>
                    
                    <a href="install.php?step=3" id="continueStep3Link" class="btn btn-success" 
                    style="pointer-events: none; opacity: 0.6;" 
                    onclick="return !this.classList.contains('disabled');">
                        <i class="fas fa-arrow-right"></i> Continue to Step 3
                    </a>
                    
                    <a href="install.php?step=1" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                
                <!-- Real-time Status Section -->
                <div class="realtime-section" id="dbCreationResults" style="display: none;">
                    <div class="realtime-header">
                        <h5><i class="fas fa-database"></i> Database Creation</h5>
                    </div>
                    <div id="dbCreationChecks" class="status-checks"></div>
                </div>
                
            <?php elseif ($step === 3): ?>
                <!-- Step 3: Validate Tables -->
                <h3><i class="fas fa-check-double"></i> Validate Table Structure</h3>
                <p>This step validates that all database tables were created correctly with the proper structure.</p>
                
                <div class="text-center mb-4">
                    <button type="button" id="validateTablesBtn" class="btn btn-primary">
                        <i class="fas fa-check-double"></i> Validate All Tables
                    </button>
                    
                    <a href="install.php?step=4" id="continueStep4Link" class="btn btn-success" 
                    style="pointer-events: none; opacity: 0.6;" 
                    onclick="return !this.classList.contains('disabled');">
                        <i class="fas fa-arrow-right"></i> Continue to Step 4
                    </a>
                    
                    <a href="install.php?step=2" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                
                <!-- Real-time Status Section -->
                <div class="realtime-section" id="tableValidationResults" style="display: none;">
                    <div class="realtime-header">
                        <h5><i class="fas fa-table"></i> Table Structure Validation</h5>
                        <button type="button" id="recheckTablesBtn" class="btn btn-sm btn-primary" style="display: none;">
                            <i class="fas fa-redo"></i> Recheck All Tables
                        </button>
                    </div>
                    <div id="tableValidationChecks" class="status-checks"></div>
                </div>
                
            <?php elseif ($step === 4): ?>
                <!-- Step 4: Create Admin User -->
                <h3><i class="fas fa-user-shield"></i> Create Admin User</h3>
                <p>Create the first administrator account for GhostCrew:</p>
                
                <form id="adminUserForm">
                    <div class="database-section">
                        <h4><i class="fas fa-user"></i> Administrator Account</h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? 'admin'); ?>" 
                                           required minlength="3" maxlength="50">
                                    <small class="text-muted">3-50 characters, alphanumeric and underscores only</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="full_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? 'System Administrator'); ?>" 
                                           required maxlength="100">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? 'admin@ghostcrew.local'); ?>" 
                                   required maxlength="100">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" 
                                           required minlength="8" id="password">
                                    <small class="text-muted">Minimum 8 characters</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" name="confirm_password" class="form-control" 
                                           required minlength="8" id="confirm_password">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="button" id="createAdminBtn" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Create Admin User
                        </button>
                        <a href="install.php?step=3" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </form>
                
                <!-- Real-time Status Section -->
                <div class="realtime-section" id="adminCreationResults" style="display: none;">
                    <div class="realtime-header">
                        <h5><i class="fas fa-user-plus"></i> Admin User Creation</h5>
                    </div>
                    <div id="adminCreationChecks" class="status-checks"></div>
                </div>
                
            <?php elseif ($step === 5): ?>
                <!-- Step 5: Installation Complete -->
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    </div>
                    
                    <h3 class="text-success">Installation Complete!</h3>
                    <p class="lead">GhostCrew has been successfully installed and configured.</p>
                </div>
                
                <div class="database-section">
                    <h4><i class="fas fa-info-circle"></i> What's Next?</h4>
                    <ol>
                        <li><strong>Security:</strong> Delete this install.php file for security</li>
                        <li><strong>Login:</strong> Use your admin credentials to access the system</li>
                        <li><strong>Host Setup:</strong> Copy the setup command from the dashboard to connect hosts</li>
                        <li><strong>Users:</strong> Create additional user accounts as needed</li>
                    </ol>
                </div>
                
                <div class="code-block">
                    <strong>Setup Command Preview:</strong><br>
                    mshta "<?php echo htmlspecialchars($_SESSION['db_config']['app_url'] ?? 'http://localhost/GhostCrew'); ?>/local/autoconnect.hta?token=USER_TOKEN_HERE"
                </div>
                
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle"></i> Important Security Note</h5>
                    <p class="mb-0">Please delete the <code>install.php</code> file after completing the installation to prevent unauthorized access to the installer.</p>
                </div>
                
                <div class="text-center">
                    <a href="index.php" class="btn btn-success btn-lg">
                        <i class="fas fa-sign-in-alt"></i> Login to GhostCrew
                    </a>
                    <button onclick="deleteInstaller()" class="btn btn-danger ms-2">
                        <i class="fas fa-trash"></i> Delete Installer
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Hide installation content if cleanup warning is shown
        document.addEventListener('DOMContentLoaded', function() {
            const cleanupWarning = document.getElementById('existingInstallationWarning');
            const stepIndicator = document.querySelector('.step-indicator');
            const installationContent = document.querySelectorAll('.step-indicator ~ *:not(#existingInstallationWarning):not(#cleanupResults)');
            
            if (cleanupWarning) {
                installationContent.forEach(element => {
                    if (!element.id || (element.id !== 'existingInstallationWarning' && element.id !== 'cleanupResults')) {
                        element.classList.add('installation-content-hidden');
                    }
                });
                
                if (stepIndicator) {
                    stepIndicator.classList.add('installation-content-hidden');
                }
            }
        });
        
        // Enhanced function to safely create status items
        function createStatusItem(id, message, status = 'checking') {
            const safeId = id || 'unknown_' + Date.now();
            const safeMessage = message || 'Processing...';
            const safeStatus = status || 'checking';
            
            const statusIcons = {
                checking: '<div class="spinner"></div>',
                success: '<i class="fas fa-check-circle"></i>',
                error: '<i class="fas fa-times-circle"></i>'
            };
            
            const statusIcon = statusIcons[safeStatus] || statusIcons.checking;
            
            return `
                <div class="status-item" id="${safeId}">
                    <div class="status-icon status-${safeStatus}">
                        ${statusIcon}
                    </div>
                    <div class="status-message">${safeMessage}</div>
                    <div class="status-actions" id="${safeId}_actions"></div>
                </div>
            `;
        }
        
        // Enhanced function to safely update status items
        function updateStatusItem(id, message, status, actions = '') {
            const element = document.getElementById(id);
            if (!element) {
                console.warn(`Status element with id '${id}' not found`);
                return;
            }
            
            const safeMessage = message || 'No message provided';
            const safeStatus = status || 'error';
            
            const statusIcons = {
                checking: '<div class="spinner"></div>',
                success: '<i class="fas fa-check-circle"></i>',
                error: '<i class="fas fa-times-circle"></i>'
            };
            
            const statusIcon = statusIcons[safeStatus] || statusIcons.error;
            
            element.className = 'status-item';
            element.innerHTML = `
                <div class="status-icon status-${safeStatus}">
                    ${statusIcon}
                </div>
                <div class="status-message">${safeMessage}</div>
                <div class="status-actions" id="${id}_actions">${actions}</div>
            `;
        }
        
        // Function to show installation content
        function showInstallationContent() {
            const warningDiv = document.getElementById('existingInstallationWarning');
            const cleanupDiv = document.getElementById('cleanupResults');
            const stepIndicator = document.querySelector('.step-indicator');
            const installationContent = document.querySelectorAll('.installation-content-hidden');
            
            if (warningDiv) warningDiv.style.display = 'none';
            if (cleanupDiv) cleanupDiv.style.display = 'none';
            
            installationContent.forEach(element => {
                element.classList.remove('installation-content-hidden');
            });
            
            if (stepIndicator) {
                stepIndicator.classList.remove('installation-content-hidden');
            }
        }
        
        // Handle cleanup button click
        document.getElementById('cleanupBtn')?.addEventListener('click', function() {
            if (confirm('Are you sure you want to remove the existing installation? This will delete all configuration files and databases. This action cannot be undone.')) {
                const resultsDiv = document.getElementById('cleanupResults');
                const checksDiv = document.getElementById('cleanupChecks');
                
                resultsDiv.style.display = 'block';
                checksDiv.innerHTML = '';
                
                document.getElementById('cleanupBtn').disabled = true;
                document.getElementById('proceedAnywayBtn').disabled = true;
                
                checksDiv.innerHTML = 
                    createStatusItem('cleanup_start', 'Starting cleanup process...') +
                    createStatusItem('cleanup_complete', 'Removing existing installation components...');
                
                fetch('install.php?action=cleanup_installation', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data && data.checks && typeof data.checks === 'object') {
                        Object.keys(data.checks).forEach(checkId => {
                            const check = data.checks[checkId];
                            if (check && typeof check === 'object') {
                                updateStatusItem(checkId, check.message || 'No message', check.status || 'error');
                            }
                        });
                    }
                    
                    if (data && data.success === true) {
                        const successDiv = document.createElement('div');
                        successDiv.className = 'alert alert-success mt-3';
                        successDiv.innerHTML = `
                            <h6><i class="fas fa-check-circle"></i> Cleanup Completed Successfully!</h6>
                            <p>All existing installation components have been removed.</p>
                            <button class="btn btn-success mt-2" onclick="location.reload()">
                                <i class="fas fa-refresh"></i> Restart Installation
                            </button>
                        `;
                        checksDiv.appendChild(successDiv);
                        
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        document.getElementById('cleanupBtn').disabled = false;
                        document.getElementById('proceedAnywayBtn').disabled = false;
                        
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'alert alert-danger mt-3';
                        errorDiv.innerHTML = `
                            <h6><i class="fas fa-times-circle"></i> Cleanup Failed</h6>
                            <p>Some components could not be removed. You may need to remove them manually.</p>
                            <button class="btn btn-warning mt-2" onclick="showInstallationContent()">
                                <i class="fas fa-forward"></i> Proceed with Installation
                            </button>
                        `;
                        checksDiv.appendChild(errorDiv);
                    }
                })
                .catch(error => {
                    console.error('Cleanup error:', error);
                    
                    document.getElementById('cleanupBtn').disabled = false;
                    document.getElementById('proceedAnywayBtn').disabled = false;
                    
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger mt-3';
                    errorDiv.innerHTML = `
                        <h6><i class="fas fa-times-circle"></i> Cleanup Error</h6>
                        <p>An error occurred during cleanup: ${error.message}</p>
                        <button class="btn btn-warning mt-2" onclick="showInstallationContent()">
                            <i class="fas fa-forward"></i> Proceed with Installation
                        </button>
                    `;
                    checksDiv.appendChild(errorDiv);
                });
            }
        });
        
        // Handle proceed anyway button click
        document.getElementById('proceedAnywayBtn')?.addEventListener('click', function() {
            if (confirm('Are you sure you want to proceed? This may cause conflicts with existing installation components.')) {
                showInstallationContent();
            }
        });
        
        // Step 1: Test database connections
        document.getElementById('testConnectionsBtn')?.addEventListener('click', function() {
            const form = document.getElementById('dbConfigForm');
            const formData = new FormData(form);
            const resultsDiv = document.getElementById('dbTestResults');
            const checksDiv = document.getElementById('dbStatusChecks');
            const createConfigBtn = document.getElementById('createConfigBtn');
            
            resultsDiv.style.display = 'block';
            checksDiv.innerHTML = '';
            createConfigBtn.disabled = true;
            
            checksDiv.innerHTML = 
                createStatusItem('terminal_connection', 'Testing terminal database connection...') +
                createStatusItem('terminal_permissions', 'Checking terminal database permissions...') +
                createStatusItem('admin_connection', 'Testing admin database connection...') +
                createStatusItem('admin_permissions', 'Checking admin database permissions...');
            
            fetch('install.php?action=validate_db_config', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data && data.checks && typeof data.checks === 'object') {
                    Object.keys(data.checks).forEach(checkId => {
                        const check = data.checks[checkId];
                        if (check && typeof check === 'object') {
                            updateStatusItem(checkId, check.message || 'No message', check.status || 'error');
                        }
                    });
                }
                
                if (data && data.success === true) {
                    fetch('install.php?action=save_db_config', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(saveData => {
                        if (saveData && saveData.success === true) {
                            createConfigBtn.disabled = false;
                            createConfigBtn.classList.add('btn-warning');
                            createConfigBtn.classList.remove('btn-secondary');
                            
                            const successDiv = document.createElement('div');
                            successDiv.className = 'alert alert-success mt-3';
                            successDiv.innerHTML = `
                                <i class="fas fa-check-circle"></i> Database connections validated successfully! 
                                Configuration saved to session. You can now create the config files.
                            `;
                            checksDiv.appendChild(successDiv);
                        } else {
                            updateStatusItem('terminal_connection', 'Failed to save configuration to session', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Config save error:', error);
                        updateStatusItem('terminal_connection', 'Failed to save configuration: ' + error.message, 'error');
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                updateStatusItem('terminal_connection', 'Connection test failed: ' + error.message, 'error');
            });
        });
        
        // Step 1: Create config files
        document.getElementById('createConfigBtn')?.addEventListener('click', function() {
            const form = document.getElementById('dbConfigForm');
            const formData = new FormData(form);
            const resultsDiv = document.getElementById('configCreationResults');
            const checksDiv = document.getElementById('configCreationChecks');
            const continueBtn = document.getElementById('continueBtn');
            
            resultsDiv.style.display = 'block';
            checksDiv.innerHTML = '';
            continueBtn.disabled = true;
            
            checksDiv.innerHTML = 
                createStatusItem('config_file', 'Creating config.php...') +
                createStatusItem('auth_config_file', 'Creating auth_config.php...') +
                createStatusItem('file_verification', 'Verifying configuration files...');
            
            fetch('install.php?action=create_config_files', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data && data.checks && typeof data.checks === 'object') {
                    Object.keys(data.checks).forEach(checkId => {
                        const check = data.checks[checkId];
                        if (check && typeof check === 'object') {
                            updateStatusItem(checkId, check.message || 'No message', check.status || 'error');
                        }
                    });
                }
                
                if (data && data.success === true) {
                    continueBtn.disabled = false;
                    continueBtn.classList.add('btn-success');
                    continueBtn.classList.remove('btn-secondary');
                    
                    const successDiv = document.createElement('div');
                    successDiv.className = 'alert alert-success mt-3';
                    successDiv.innerHTML = `
                        <i class="fas fa-check-circle"></i> All configuration files created successfully! 
                        <br>
                        <ul class="mt-2 mb-0">
                            <li>config.php - Main application configuration</li>
                            <li>auth_config.php - Authentication system configuration</li>
                        </ul>
                        <p class="mt-2 mb-0">You can now continue to Step 2 to create the databases.</p>
                    `;
                    checksDiv.appendChild(successDiv);
                }
            })
            .catch(error => {
                console.error('Config creation error:', error);
                updateStatusItem('config_file', 'Config creation failed: ' + error.message, 'error');
            });
        });
        
        // Step 2: Create databases
        document.getElementById('createDbBtn')?.addEventListener('click', function() {
            const resultsDiv = document.getElementById('dbCreationResults');
            const checksDiv = document.getElementById('dbCreationChecks');
            
            resultsDiv.style.display = 'block';
            checksDiv.innerHTML = '';
            
            disableContinueToStep3();

            checksDiv.innerHTML = 
                createStatusItem('config_check', 'Verifying configuration files...') +
                createStatusItem('terminal_database', 'Creating terminal database...') +
                createStatusItem('admin_database', 'Creating admin database...');
            
            fetch('install.php?action=create_databases', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data && data.checks && typeof data.checks === 'object') {
                    Object.keys(data.checks).forEach(checkId => {
                        const check = data.checks[checkId];
                        if (check && typeof check === 'object') {
                            updateStatusItem(checkId, check.message || 'No message', check.status || 'error');
                        }
                    });
                }
                
                if (data && data.success === true) {
                    enableContinueToStep3();
                    
                    const successDiv = document.createElement('div');
                    successDiv.className = 'alert alert-success mt-3';
                    successDiv.innerHTML = `
                        <i class="fas fa-check-circle"></i> Databases created successfully! 
                        <br>
                        <a href="install.php?step=3" class="btn btn-sm btn-success mt-2">
                            <i class="fas fa-arrow-right"></i> Continue to Step 3
                        </a>
                    `;
                    checksDiv.appendChild(successDiv);
                }
            })
            .catch(error => {
                console.error('Database creation error:', error);
                updateStatusItem('config_check', 'Database creation failed: ' + error.message, 'error');
                disableContinueToStep3();
            });
        });

        // Enable/disable continue functions
        function enableContinueToStep3() {
            const continueLink = document.getElementById('continueStep3Link');
            if (continueLink) {
                continueLink.style.pointerEvents = 'auto';
                continueLink.style.opacity = '1';
                continueLink.classList.remove('disabled');
                continueLink.classList.add('btn-success');
                continueLink.classList.remove('btn-secondary');
                return true;
            }
            return false;
        }

        function disableContinueToStep3() {
            const continueLink = document.getElementById('continueStep3Link');
            if (continueLink) {
                continueLink.style.pointerEvents = 'none';
                continueLink.style.opacity = '0.6';
                continueLink.classList.add('disabled');
                return true;
            }
            return false;
        }

        function enableContinueToStep4() {
            const continueLink = document.getElementById('continueStep4Link');
            if (continueLink) {
                continueLink.style.pointerEvents = 'auto';
                continueLink.style.opacity = '1';
                continueLink.classList.remove('disabled');
                continueLink.classList.add('btn-success');
                continueLink.classList.remove('btn-secondary');
                return true;
            }
            return false;
        }

        function disableContinueToStep4() {
            const continueLink = document.getElementById('continueStep4Link');
            if (continueLink) {
                continueLink.style.pointerEvents = 'none';
                continueLink.style.opacity = '0.6';
                continueLink.classList.add('disabled');
                return true;
            }
            return false;
        }
        
        // Step 3: Table Validation
        document.getElementById('validateTablesBtn')?.addEventListener('click', validateTables);
        document.getElementById('recheckTablesBtn')?.addEventListener('click', validateTables);
        
        function validateTables() {
            const resultsDiv = document.getElementById('tableValidationResults');
            const checksDiv = document.getElementById('tableValidationChecks');
            const recheckBtn = document.getElementById('recheckTablesBtn');
            
            resultsDiv.style.display = 'block';
            checksDiv.innerHTML = '<div class="status-item"><div class="status-icon status-checking"><div class="spinner"></div></div><div class="status-message">Validating all tables and columns...</div></div>';
            
            disableContinueToStep4();
            
            fetch('install.php?action=validate_tables', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            })
            .then(response => response.json())
            .then(data => {
                checksDiv.innerHTML = '';
                let hasErrors = false;
                
                if (data.checks && typeof data.checks === 'object') {
                    Object.keys(data.checks).forEach(checkId => {
                        const check = data.checks[checkId];
                        let actions = '';
                        
                        if (check.status === 'error' && check.repair) {
                            const tableName = checkId.replace('terminal_table_', '').replace('admin_table_', '');
                            const database = checkId.startsWith('terminal_') ? 'terminal_app' : 'ghostcrew_admin';
                            actions = `<button class="btn btn-sm btn-warning" onclick="repairTable('${database}', '${tableName}', '${checkId}')">
                                <i class="fas fa-wrench"></i> Repair
                            </button>`;
                            hasErrors = true;
                        }
                        
                        checksDiv.innerHTML += createStatusItem(checkId, check.message, check.status);
                        if (actions) {
                            const actionsEl = document.getElementById(checkId + '_actions');
                            if (actionsEl) {
                                actionsEl.innerHTML = actions;
                            }
                        }
                    });
                }
                
                if (data.success) {
                    enableContinueToStep4();
                    
                    const successDiv = document.createElement('div');
                    successDiv.className = 'alert alert-success mt-3';
                    successDiv.innerHTML = `
                        <i class="fas fa-check-circle"></i> All tables validated successfully! 
                        <br>
                        <a href="install.php?step=4" class="btn btn-sm btn-success mt-2">
                            <i class="fas fa-arrow-right"></i> Continue to Step 4
                        </a>
                    `;
                    checksDiv.appendChild(successDiv);
                }
                
                if (recheckBtn) {
                    recheckBtn.style.display = hasErrors ? 'inline-block' : 'none';
                }
            })
            .catch(error => {
                console.error('Table validation error:', error);
                checksDiv.innerHTML = createStatusItem('validation_error', 'Validation failed: ' + error.message, 'error');
                disableContinueToStep4();
            });
        }
        
        // Repair table function
        function repairTable(database, table, statusId) {
            updateStatusItem(statusId, 'Repairing table...', 'checking');
            
            const formData = new FormData();
            formData.append('database', database);
            formData.append('table', table);
            
            fetch('install.php?action=repair_table', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStatusItem(statusId, data.message, 'success');
                    document.getElementById(statusId + '_actions').innerHTML = 
                        `<button class="btn btn-sm btn-primary" onclick="validateTables()">
                            <i class="fas fa-redo"></i> Recheck
                        </button>`;
                } else {
                    updateStatusItem(statusId, data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                updateStatusItem(statusId, 'Repair failed', 'error');
            });
        }
        
        // Step 4: Admin User Creation
        document.getElementById('createAdminBtn')?.addEventListener('click', function() {
            const form = document.getElementById('adminUserForm');
            const formData = new FormData(form);
            const resultsDiv = document.getElementById('adminCreationResults');
            const checksDiv = document.getElementById('adminCreationChecks');
            
            // Validate passwords match on client side first
            const password = form.querySelector('[name="password"]').value;
            const confirmPassword = form.querySelector('[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return;
            }
            
            if (password.length < 8) {
                alert('Password must be at least 8 characters long!');
                return;
            }
            
            // Validate email format
            const email = form.querySelector('[name="email"]').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address!');
                return;
            }
            
            // Validate username format
            const username = form.querySelector('[name="username"]').value;
            const usernameRegex = /^[a-zA-Z0-9_]{3,50}$/;
            if (!usernameRegex.test(username)) {
                alert('Username must be 3-50 characters and contain only letters, numbers, and underscores!');
                return;
            }
            
            resultsDiv.style.display = 'block';
            checksDiv.innerHTML = '';
            
            checksDiv.innerHTML = 
                createStatusItem('user_validation', 'Validating user data...') +
                createStatusItem('database_connection', 'Connecting to admin database...') +
                createStatusItem('username_check', 'Checking username availability...') +
                createStatusItem('email_check', 'Checking email availability...') +
                createStatusItem('user_creation', 'Creating admin user...') +
                createStatusItem('user_verification', 'Verifying user creation...');
            
            fetch('install.php?action=create_admin_user', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data && data.checks && typeof data.checks === 'object') {
                    Object.keys(data.checks).forEach(checkId => {
                        const check = data.checks[checkId];
                        if (check && typeof check === 'object') {
                            updateStatusItem(checkId, check.message || 'No message provided', check.status || 'error');
                        }
                    });
                }
                
                if (data && data.success === true) {
                    const successDiv = document.createElement('div');
                    successDiv.className = 'alert alert-success mt-3';
                    successDiv.innerHTML = `
                        <h6><i class="fas fa-check-circle"></i> Admin User Created Successfully!</h6>
                        <p>You can now proceed to the final step.</p>
                        <a href="install.php?step=5" class="btn btn-success mt-2">
                            <i class="fas fa-arrow-right"></i> Continue to Final Step
                        </a>
                    `;
                    checksDiv.appendChild(successDiv);
                    
                    setTimeout(() => {
                        window.location.href = 'install.php?step=5';
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Admin user creation error:', error);
                
                checksDiv.innerHTML = '';
                
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger';
                errorDiv.innerHTML = `
                    <h6><i class="fas fa-times-circle"></i> Admin User Creation Failed</h6>
                    <p><strong>Error:</strong> ${error.message}</p>
                    <div class="mt-3">
                        <button class="btn btn-sm btn-primary" onclick="location.reload()">
                            <i class="fas fa-refresh"></i> Refresh Page
                        </button>
                        <a href="install.php?step=5" class="btn btn-sm btn-warning">
                            <i class="fas fa-skip-forward"></i> Skip to Final Step
                        </a>
                    </div>
                `;
                checksDiv.appendChild(errorDiv);
            });
        });

        // Enhanced password confirmation validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
                this.style.borderColor = '#f85149';
            } else {
                this.setCustomValidity('');
                this.style.borderColor = '#30363d';
            }
        });
        
        // Delete installer function
        function deleteInstaller() {
            if (confirm('Are you sure you want to delete the installer? This action cannot be undone.')) {
                fetch('install.php?action=delete_installer', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Installer deleted successfully!');
                        window.location.href = 'index.php';
                    } else {
                        alert('Failed to delete installer: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Delete installer error:', error);
                    alert('Error deleting installer: ' + error.message);
                });
            }
        }
    </script>
</body>
</html>