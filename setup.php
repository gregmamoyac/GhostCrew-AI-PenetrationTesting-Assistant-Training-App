<?php
/* install.php - Enhanced GhostCrew Installation Script with Real-time Validation */

session_start();

// Check if already installed
if (file_exists('config.php') && file_exists('auth_config.php')) {
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

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success = [];

// Handle AJAX requests for real-time validation
if (isset($_GET['action']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'validate_db_config':
            echo json_encode(validateDatabaseConfigAjax($_POST));
            exit;
            
        case 'create_databases':
            echo json_encode(createDatabasesAjax($_SESSION['db_config'] ?? []));
            exit;
            
        case 'validate_tables':
            echo json_encode(validateTablesAjax($_SESSION['db_config'] ?? []));
            exit;
            
        case 'repair_table':
            echo json_encode(repairTableAjax($_POST, $_SESSION['db_config'] ?? []));
            exit;
            
        case 'create_admin_user':
            echo json_encode(createAdminUserAjax($_SESSION['db_config'] ?? [], $_POST));
            exit;
            
        case 'delete_installer':
            if (unlink(__FILE__)) {
                echo json_encode(['success' => true, 'message' => 'Installer deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete installer file']);
            }
            exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1: // Database Configuration
            $errors = validateDatabaseConfig($_POST);
            if (empty($errors)) {
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
                header('Location: install.php?step=2');
                exit;
            }
            break;
    }
}

// Table structure definitions
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
                'working_directory' => ['type' => 'text', 'null' => 'YES'],
                'command' => ['type' => 'text', 'null' => 'NO'],
                'output' => ['type' => 'longtext', 'null' => 'YES'],
                'timestamp' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()'],
                'response_timestamp' => ['type' => 'timestamp', 'null' => 'YES'],
                'execution_time' => ['type' => 'decimal(10,6)', 'null' => 'YES'],
                'exit_code' => ['type' => 'int(11)', 'null' => 'YES'],
                'status' => ['type' => "enum('pending','completed','failed','timeout')", 'null' => 'YES', 'default' => 'pending'],
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
            'system_config' => [
                'id' => ['type' => 'int(11)', 'null' => 'NO', 'key' => 'PRI', 'extra' => 'auto_increment'],
                'config_key' => ['type' => 'varchar(100)', 'null' => 'NO', 'key' => 'UNI'],
                'config_value' => ['type' => 'text', 'null' => 'YES'],
                'description' => ['type' => 'text', 'null' => 'YES'],
                'updated_by' => ['type' => 'int(11)', 'null' => 'YES'],
                'updated_at' => ['type' => 'timestamp', 'null' => 'NO', 'default' => 'current_timestamp()']
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

function createDatabasesAjax($config) {
    $result = ['success' => true, 'checks' => []];
    
    try {
        // Create config.php
        $result['checks']['config_file'] = ['status' => 'checking', 'message' => 'Creating config.php...'];
        $configContent = generateConfigFile($config);
        if (!file_put_contents('config.php', $configContent)) {
            $result['checks']['config_file'] = ['status' => 'error', 'message' => 'Failed to write config.php. Check directory permissions.'];
            $result['success'] = false;
        } else {
            $result['checks']['config_file'] = ['status' => 'success', 'message' => 'config.php created successfully'];
        }
        
        // Create auth_config.php
        $result['checks']['auth_config_file'] = ['status' => 'checking', 'message' => 'Creating auth_config.php...'];
        $authConfigContent = generateAuthConfigFile($config);
        if (!file_put_contents('auth_config.php', $authConfigContent)) {
            $result['checks']['auth_config_file'] = ['status' => 'error', 'message' => 'Failed to write auth_config.php. Check directory permissions.'];
            $result['success'] = false;
        } else {
            $result['checks']['auth_config_file'] = ['status' => 'success', 'message' => 'auth_config.php created successfully'];
        }
        
        // Create terminal_app database
        $result['checks']['terminal_database'] = ['status' => 'checking', 'message' => 'Creating terminal_app database...'];
        $terminalSql = getTerminalAppSQL();
        
        $conn = new mysqli($config['terminal_host'], $config['terminal_user'], $config['terminal_pass']);
        if ($conn->connect_error) {
            $result['checks']['terminal_database'] = ['status' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error];
            $result['success'] = false;
        } else {
            if (!$conn->multi_query($terminalSql)) {
                $result['checks']['terminal_database'] = ['status' => 'error', 'message' => 'Failed to create database: ' . $conn->error];
                $result['success'] = false;
            } else {
                // Clear results
                do {
                    if ($resultSet = $conn->store_result()) {
                        $resultSet->free();
                    }
                } while ($conn->next_result());
                $result['checks']['terminal_database'] = ['status' => 'success', 'message' => 'Terminal database created successfully'];
            }
        }
        $conn->close();
        
        // Create ghostcrew_admin database
        $result['checks']['admin_database'] = ['status' => 'checking', 'message' => 'Creating ghostcrew_admin database...'];
        $adminSql = getGhostcrewAdminSQL();
        
        $conn = new mysqli($config['admin_host'], $config['admin_user'], $config['admin_pass']);
        if ($conn->connect_error) {
            $result['checks']['admin_database'] = ['status' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error];
            $result['success'] = false;
        } else {
            if (!$conn->multi_query($adminSql)) {
                $result['checks']['admin_database'] = ['status' => 'error', 'message' => 'Failed to create database: ' . $conn->error];
                $result['success'] = false;
            } else {
                // Clear results
                do {
                    if ($resultSet = $conn->store_result()) {
                        $resultSet->free();
                    }
                } while ($conn->next_result());
                $result['checks']['admin_database'] = ['status' => 'success', 'message' => 'Admin database created successfully'];
            }
        }
        $conn->close();
        
    } catch (Exception $e) {
        $result['checks']['database_creation'] = ['status' => 'error', 'message' => 'Installation error: ' . $e->getMessage()];
        $result['success'] = false;
    }
    
    return $result;
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
                    // Check column properties
                    $actual = $actualColumns[$columnName];
                    if (strtolower($actual['type']) !== strtolower($expectedProps['type'])) {
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
                    // Check column properties
                    $actual = $actualColumns[$columnName];
                    if (strtolower($actual['type']) !== strtolower($expectedProps['type'])) {
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
    
    if (!empty($errors)) {
        $result['checks']['user_validation'] = ['status' => 'error', 'message' => implode('; ', $errors)];
        $result['success'] = false;
        return $result;
    }
    
    $result['checks']['user_validation'] = ['status' => 'success', 'message' => 'User data validated'];
    
    try {
        // Include the auth config we just created
        include 'auth_config.php';
        
        $result['checks']['database_connection'] = ['status' => 'checking', 'message' => 'Connecting to admin database...'];
        $adminDb = getAdminDB();
        $result['checks']['database_connection'] = ['status' => 'success', 'message' => 'Connected to admin database'];
        
        // Check if username already exists
        $result['checks']['username_check'] = ['status' => 'checking', 'message' => 'Checking if username exists...'];
        $stmt = $adminDb->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $userData['username']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $result['checks']['username_check'] = ['status' => 'error', 'message' => 'Username already exists'];
            $result['success'] = false;
            return $result;
        }
        $result['checks']['username_check'] = ['status' => 'success', 'message' => 'Username available'];
        
        // Check if email already exists
        $result['checks']['email_check'] = ['status' => 'checking', 'message' => 'Checking if email exists...'];
        $stmt = $adminDb->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $userData['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $result['checks']['email_check'] = ['status' => 'error', 'message' => 'Email already exists'];
            $result['success'] = false;
            return $result;
        }
        $result['checks']['email_check'] = ['status' => 'success', 'message' => 'Email available'];
        
        // Create admin user
        $result['checks']['user_creation'] = ['status' => 'checking', 'message' => 'Creating admin user...'];
        $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
        $stmt = $adminDb->prepare("INSERT INTO users (username, password_hash, full_name, email, role, is_active) VALUES (?, ?, ?, ?, 'admin', 1)");
        $stmt->bind_param("ssss", $userData['username'], $passwordHash, $userData['full_name'], $userData['email']);
        
        if (!$stmt->execute()) {
            $result['checks']['user_creation'] = ['status' => 'error', 'message' => 'Failed to create admin user: ' . $stmt->error];
            $result['success'] = false;
            return $result;
        }
        
        $result['checks']['user_creation'] = ['status' => 'success', 'message' => 'Admin user created successfully'];
        
    } catch (Exception $e) {
        $result['checks']['user_creation'] = ['status' => 'error', 'message' => 'User creation error: ' . $e->getMessage()];
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
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Select the database
$conn->select_db(DB_NAME);

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

// Add error reporting for development (remove in production)
if (defined(\'DEBUG\') && DEBUG) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}
?>';
}

function generateAuthConfigFile($config) {
    return file_get_contents('auth_config.php') ?: '<?php

/* auth_config.php - Generated by GhostCrew Installer */

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
define(\'ADMIN_DB_HOST\', \'' . addslashes($config['admin_host']) . '\');
define(\'ADMIN_DB_USER\', \'' . addslashes($config['admin_user']) . '\');
define(\'ADMIN_DB_PASS\', \'' . addslashes($config['admin_pass']) . '\');
define(\'ADMIN_DB_NAME\', \'' . addslashes($config['admin_name']) . '\');

// Security settings
define(\'SESSION_TIMEOUT\', 3600);
define(\'MAX_LOGIN_ATTEMPTS\', 5);
define(\'LOGIN_LOCKOUT_TIME\', 900);
define(\'PASSWORD_MIN_LENGTH\', 8);
define(\'INSTANCE_TOKEN_LIFETIME\', 28800);

// Create admin database connection
function getAdminDB() {
    static $adminConn = null;
    
    if ($adminConn === null) {
        $adminConn = new mysqli(ADMIN_DB_HOST, ADMIN_DB_USER, ADMIN_DB_PASS, ADMIN_DB_NAME);
        
        if ($adminConn->connect_error) {
            die("Admin database connection failed: " . $adminConn->connect_error);
        }
        
        $adminConn->query("SET time_zone = \'+00:00\'");
    }
    
    return $adminConn;
}

?>';
}

function getTerminalAppSQL() {
    return 'SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS terminal_app DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE terminal_app;

CREATE TABLE command_history (
  id int(11) NOT NULL,
  host_id varchar(50) NOT NULL,
  session_id varchar(64) DEFAULT NULL,
  working_directory text DEFAULT NULL,
  command text NOT NULL,
  output longtext DEFAULT NULL,
  timestamp timestamp NOT NULL DEFAULT current_timestamp(),
  response_timestamp timestamp NULL DEFAULT NULL,
  execution_time decimal(10,6) DEFAULT NULL,
  exit_code int(11) DEFAULT NULL,
  status enum(\'pending\',\'completed\',\'failed\',\'timeout\') DEFAULT \'pending\',
  context_data longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(context_data))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE command_statistics (
  id int(11) NOT NULL,
  host_id varchar(50) NOT NULL,
  command_base varchar(100) NOT NULL,
  execution_count int(11) DEFAULT 1,
  avg_execution_time decimal(10,6) DEFAULT NULL,
  last_executed timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  success_rate decimal(5,2) DEFAULT 100.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `hosts` (
  id int(11) NOT NULL,
  host_id varchar(50) NOT NULL,
  hostname varchar(255) NOT NULL,
  ip_address varchar(45) NOT NULL,
  os_info text DEFAULT NULL,
  last_seen timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  connected tinyint(1) DEFAULT 1,
  first_seen timestamp NOT NULL DEFAULT current_timestamp(),
  total_sessions int(11) DEFAULT 0,
  total_commands int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE host_instance_mappings (
  id int(11) NOT NULL,
  host_id varchar(50) NOT NULL,
  instance_token varchar(128) NOT NULL,
  user_id int(11) DEFAULT NULL,
  mapped_at timestamp NOT NULL DEFAULT current_timestamp(),
  expires_at timestamp NOT NULL DEFAULT current_timestamp(),
  is_active tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE shell_sessions (
  id int(11) NOT NULL,
  session_id varchar(64) NOT NULL,
  host_id varchar(50) NOT NULL,
  current_directory text DEFAULT NULL,
  initial_directory text DEFAULT NULL,
  environment_vars longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(environment_vars)),
  start_time timestamp NOT NULL DEFAULT current_timestamp(),
  last_activity timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  is_active tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE command_history ADD PRIMARY KEY (id), ADD KEY idx_host_session (host_id,session_id), ADD KEY idx_session_time (session_id,timestamp), ADD KEY idx_status_time (status,timestamp), ADD KEY idx_working_dir (host_id,working_directory(100));
ALTER TABLE command_statistics ADD PRIMARY KEY (id), ADD UNIQUE KEY unique_host_command (host_id,command_base), ADD KEY idx_host_stats (host_id,execution_count);
ALTER TABLE hosts ADD PRIMARY KEY (id), ADD UNIQUE KEY host_id (host_id), ADD KEY idx_host_status (connected,last_seen), ADD KEY idx_host_id (host_id);
ALTER TABLE host_instance_mappings ADD PRIMARY KEY (id), ADD KEY idx_host_instance (host_id,instance_token), ADD KEY idx_instance_active (instance_token,is_active), ADD KEY idx_token_expires (instance_token,expires_at,is_active);
ALTER TABLE shell_sessions ADD PRIMARY KEY (id), ADD UNIQUE KEY session_id (session_id), ADD KEY idx_session_active (session_id,is_active), ADD KEY idx_session_host (host_id,is_active,last_activity);

ALTER TABLE command_history MODIFY id int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE command_statistics MODIFY id int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE hosts MODIFY id int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE host_instance_mappings MODIFY id int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE shell_sessions MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE command_history ADD CONSTRAINT command_history_ibfk_1 FOREIGN KEY (host_id) REFERENCES `hosts` (host_id) ON DELETE CASCADE;
ALTER TABLE command_statistics ADD CONSTRAINT command_statistics_ibfk_1 FOREIGN KEY (host_id) REFERENCES `hosts` (host_id) ON DELETE CASCADE;
ALTER TABLE host_instance_mappings ADD CONSTRAINT host_instance_mappings_ibfk_1 FOREIGN KEY (host_id) REFERENCES `hosts` (host_id) ON DELETE CASCADE;
ALTER TABLE shell_sessions ADD CONSTRAINT shell_sessions_ibfk_1 FOREIGN KEY (host_id) REFERENCES `hosts` (host_id) ON DELETE CASCADE;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;';
}

function getGhostcrewAdminSQL() {
    return 'SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS ghostcrew_admin DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ghostcrew_admin;

CREATE TABLE audit_log (
  id int(11) NOT NULL,
  user_id int(11) DEFAULT NULL,
  action_type enum(\'login\',\'logout\',\'command_execute\',\'session_start\',\'session_end\',\'chat_message\',\'system_access\') NOT NULL,
  action_details longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(action_details)),
  ip_address varchar(45) DEFAULT NULL,
  user_agent text DEFAULT NULL,
  timestamp timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chatbot_conversations (
  id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  session_id varchar(64) DEFAULT NULL,
  conversation_id varchar(64) NOT NULL,
  parent_message_id int(11) DEFAULT NULL,
  message_type enum(\'user\',\'bot\') NOT NULL,
  message text NOT NULL,
  timestamp timestamp NOT NULL DEFAULT current_timestamp(),
  context_data longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(context_data)),
  response_time decimal(8,3) DEFAULT NULL,
  message_tokens int(11) DEFAULT NULL,
  model_used varchar(50) DEFAULT \'local\',
  suggested_command text DEFAULT NULL,
  command_executed tinyint(1) DEFAULT 0,
  rating tinyint(1) DEFAULT NULL,
  flagged tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE remote_sessions (
  id int(11) NOT NULL,
  session_id varchar(64) NOT NULL,
  user_id int(11) NOT NULL,
  host_id varchar(50) NOT NULL,
  hostname varchar(255) NOT NULL,
  ip_address varchar(45) NOT NULL,
  os_info text DEFAULT NULL,
  start_time timestamp NOT NULL DEFAULT current_timestamp(),
  end_time timestamp NULL DEFAULT NULL,
  status enum(\'active\',\'disconnected\',\'terminated\') DEFAULT \'active\',
  last_activity timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  total_commands int(11) DEFAULT 0,
  session_notes text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_config (
  id int(11) NOT NULL,
  config_key varchar(100) NOT NULL,
  config_value text DEFAULT NULL,
  description text DEFAULT NULL,
  updated_by int(11) DEFAULT NULL,
  updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_config (id, config_key, config_value, description, updated_by, updated_at) VALUES
(1, \'session_timeout\', \'3600\', \'User session timeout in seconds\', NULL, \'2025-06-01 18:25:18\'),
(2, \'max_command_history\', \'100000\', \'Maximum commands to keep in history per session\', NULL, \'2025-06-01 18:25:18\'),
(3, \'chatbot_enabled\', \'1\', \'Enable/disable chatbot functionality\', NULL, \'2025-05-26 22:03:43\'),
(4, \'audit_retention_days\', \'999999\', \'Days to retain audit logs\', NULL, \'2025-06-01 18:25:18\'),
(5, \'max_concurrent_sessions\', \'10\', \'Maximum concurrent sessions per user\', NULL, \'2025-05-26 22:03:43\');

CREATE TABLE users (
  id int(11) NOT NULL,
  username varchar(50) NOT NULL,
  password_hash varchar(255) NOT NULL,
  full_name varchar(100) NOT NULL,
  email varchar(100) NOT NULL,
  role enum(\'admin\',\'manager\',\'operator\') DEFAULT \'operator\',
  is_active tinyint(1) DEFAULT 1,
  last_login timestamp NULL DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  created_by int(11) DEFAULT NULL,
  manager_id int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_instance_tokens (
  id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  instance_token varchar(128) NOT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  expires_at timestamp NOT NULL DEFAULT current_timestamp(),
  is_active tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
  id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  session_token varchar(128) NOT NULL,
  ip_address varchar(45) NOT NULL,
  user_agent text DEFAULT NULL,
  login_time timestamp NOT NULL DEFAULT current_timestamp(),
  last_activity timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  logout_time timestamp NULL DEFAULT NULL,
  is_active tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE audit_log ADD PRIMARY KEY (id), ADD KEY idx_user_audit (user_id,timestamp), ADD KEY idx_action_time (action_type,timestamp);
ALTER TABLE chatbot_conversations ADD PRIMARY KEY (id), ADD KEY idx_user_conversation (user_id,conversation_id,timestamp), ADD KEY idx_session_chat (session_id,timestamp), ADD KEY idx_flagged_review (flagged,timestamp), ADD KEY idx_parent_message (parent_message_id), ADD KEY idx_conversation_thread (conversation_id,timestamp), ADD KEY idx_conversation_messages (conversation_id,timestamp), ADD KEY idx_user_conversations (user_id,timestamp), ADD KEY idx_message_search (message_type,timestamp);
ALTER TABLE remote_sessions ADD PRIMARY KEY (id), ADD UNIQUE KEY session_id (session_id), ADD KEY idx_user_host (user_id,host_id), ADD KEY idx_session_status (status,start_time);
ALTER TABLE system_config ADD PRIMARY KEY (id), ADD UNIQUE KEY config_key (config_key), ADD KEY updated_by (updated_by);
ALTER TABLE users ADD PRIMARY KEY (id), ADD UNIQUE KEY username (username), ADD KEY created_by (created_by), ADD KEY idx_manager_id (manager_id);
ALTER TABLE user_instance_tokens ADD PRIMARY KEY (id), ADD UNIQUE KEY instance_token (instance_token), ADD KEY user_id (user_id);
ALTER TABLE user_sessions ADD PRIMARY KEY (id), ADD UNIQUE KEY session_token (session_token), ADD KEY user_id (user_id);

ALTER TABLE audit_log MODIFY id int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE chatbot_conversations MODIFY id int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE remote_sessions MODIFY id int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE system_config MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
ALTER TABLE users MODIFY id int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE user_instance_tokens MODIFY id int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE user_sessions MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE audit_log ADD CONSTRAINT audit_log_ibfk_1 FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE SET NULL;
ALTER TABLE chatbot_conversations ADD CONSTRAINT chatbot_conversations_ibfk_1 FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE CASCADE, ADD CONSTRAINT chatbot_conversations_ibfk_2 FOREIGN KEY (session_id) REFERENCES remote_sessions (session_id) ON DELETE SET NULL;
ALTER TABLE remote_sessions ADD CONSTRAINT remote_sessions_ibfk_1 FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE CASCADE;
ALTER TABLE system_config ADD CONSTRAINT system_config_ibfk_1 FOREIGN KEY (updated_by) REFERENCES `users` (id) ON DELETE SET NULL;
ALTER TABLE users ADD CONSTRAINT users_ibfk_1 FOREIGN KEY (created_by) REFERENCES `users` (id) ON DELETE SET NULL, ADD CONSTRAINT users_manager_fk FOREIGN KEY (manager_id) REFERENCES `users` (id) ON DELETE SET NULL;
ALTER TABLE user_instance_tokens ADD CONSTRAINT user_instance_tokens_ibfk_1 FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE CASCADE;
ALTER TABLE user_sessions ADD CONSTRAINT user_sessions_ibfk_1 FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE CASCADE;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;';
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
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1><i class="fas fa-ghost"></i> GhostCrew Installation</h1>
            <p class="mb-0">Secure Remote Access Terminal Setup</p>
        </div>
        
        <div class="install-body">
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                    <i class="fas fa-database me-2"></i> Database Config
                </div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                    <i class="fas fa-cogs me-2"></i> Create & Validate
                </div>
                <div class="step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                    <i class="fas fa-user-shield me-2"></i> Admin User
                </div>
                <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">
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
                    <p class="mb-0">GhostCrew is already installed and configured. You can proceed to login.</p>
                </div>
                <div class="text-center">
                    <a href="index.php" class="btn btn-success">
                        <i class="fas fa-sign-in-alt"></i> Go to Login
                    </a>
                </div>
                
            <?php elseif ($step === 1): ?>
                <!-- Step 1: Database Configuration -->
                <h3><i class="fas fa-database"></i> Database Configuration</h3>
                <p>Configure the database connections for GhostCrew. Two separate databases are required:</p>
                
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
                        <button type="submit" id="continueBtn" class="btn btn-success" disabled>
                            <i class="fas fa-arrow-right"></i> Continue to Step 2
                        </button>
                    </div>
                </form>
                
                <!-- Real-time Status Section -->
                <div class="realtime-section" id="dbTestResults" style="display: none;">
                    <div class="realtime-header">
                        <h5><i class="fas fa-chart-line"></i> Database Connection Tests</h5>
                    </div>
                    <div id="dbStatusChecks" class="status-checks"></div>
                </div>
                
            <?php elseif ($step === 2): ?>
                <!-- Step 2: Create Databases and Tables with Real-time Validation -->
                <h3><i class="fas fa-cogs"></i> Create Databases and Validate Structure</h3>
                <p>This step will create the database structure and validate all tables and columns.</p>
                
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Database Configuration</h5>
                    <p><strong>Terminal Database:</strong> <?php echo htmlspecialchars($_SESSION['db_config']['terminal_user']); ?>@<?php echo htmlspecialchars($_SESSION['db_config']['terminal_host']); ?>/<?php echo htmlspecialchars($_SESSION['db_config']['terminal_name']); ?></p>
                    <p><strong>Admin Database:</strong> <?php echo htmlspecialchars($_SESSION['db_config']['admin_user']); ?>@<?php echo htmlspecialchars($_SESSION['db_config']['admin_host']); ?>/<?php echo htmlspecialchars($_SESSION['db_config']['admin_name']); ?></p>
                </div>
                
                <div class="text-center mb-4">
                    <button type="button" id="createDbBtn" class="btn btn-primary">
                        <i class="fas fa-database"></i> Create Databases & Tables
                    </button>
                    <button type="button" id="validateTablesBtn" class="btn btn-warning" disabled>
                        <i class="fas fa-check-double"></i> Validate Table Structure
                    </button>
                    <button type="button" id="continueStep3Btn" class="btn btn-success" disabled>
                        <i class="fas fa-arrow-right"></i> Continue to Step 3
                    </button>
                    <a href="install.php?step=1" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
                
                <!-- Real-time Status Sections -->
                <div class="realtime-section" id="dbCreationResults" style="display: none;">
                    <div class="realtime-header">
                        <h5><i class="fas fa-database"></i> Database Creation</h5>
                    </div>
                    <div id="dbCreationChecks" class="status-checks"></div>
                </div>
                
                <div class="realtime-section" id="tableValidationResults" style="display: none;">
                    <div class="realtime-header">
                        <h5><i class="fas fa-table"></i> Table Structure Validation</h5>
                        <button type="button" id="recheckTablesBtn" class="btn btn-sm btn-primary" style="display: none;">
                            <i class="fas fa-redo"></i> Recheck All Tables
                        </button>
                    </div>
                    <div id="tableValidationChecks" class="status-checks"></div>
                </div>
                
            <?php elseif ($step === 3): ?>
                <!-- Step 3: Create Admin User -->
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
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? 'admin@localhost'); ?>" 
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
                        <a href="install.php?step=2" class="btn btn-secondary">
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
                
            <?php elseif ($step === 4): ?>
                <!-- Step 4: Installation Complete -->
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
        // Real-time status update functions
        function createStatusItem(id, message, status = 'checking') {
            const statusIcons = {
                checking: '<div class="spinner"></div>',
                success: '<i class="fas fa-check-circle"></i>',
                error: '<i class="fas fa-times-circle"></i>'
            };
            
            return `
                <div class="status-item" id="${id}">
                    <div class="status-icon status-${status}">
                        ${statusIcons[status]}
                    </div>
                    <div class="status-message">${message}</div>
                    <div class="status-actions" id="${id}_actions"></div>
                </div>
            `;
        }
        
        function updateStatusItem(id, message, status, actions = '') {
            const element = document.getElementById(id);
            if (element) {
                const statusIcons = {
                    checking: '<div class="spinner"></div>',
                    success: '<i class="fas fa-check-circle"></i>',
                    error: '<i class="fas fa-times-circle"></i>'
                };
                
                element.className = 'status-item';
                element.innerHTML = `
                    <div class="status-icon status-${status}">
                        ${statusIcons[status]}
                    </div>
                    <div class="status-message">${message}</div>
                    <div class="status-actions">${actions}</div>
                `;
            }
        }
        
        // Step 1: Database Configuration Testing
        document.getElementById('testConnectionsBtn')?.addEventListener('click', function() {
            const form = document.getElementById('dbConfigForm');
            const formData = new FormData(form);
            const resultsDiv = document.getElementById('dbTestResults');
            const checksDiv = document.getElementById('dbStatusChecks');
            const continueBtn = document.getElementById('continueBtn');
            
            resultsDiv.style.display = 'block';
            checksDiv.innerHTML = '';
            continueBtn.disabled = true;
            
            // Add initial status items
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
                Object.keys(data.checks).forEach(checkId => {
                    const check = data.checks[checkId];
                    updateStatusItem(checkId, check.message, check.status);
                });
                
                if (data.success) {
                    continueBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                updateStatusItem('terminal_connection', 'Connection test failed', 'error');
            });
        });
        
        // Step 2: Database Creation
        document.getElementById('createDbBtn')?.addEventListener('click', function() {
            const resultsDiv = document.getElementById('dbCreationResults');
            const checksDiv = document.getElementById('dbCreationChecks');
            const validateBtn = document.getElementById('validateTablesBtn');
            
            resultsDiv.style.display = 'block';
            checksDiv.innerHTML = '';
            
            // Add initial status items
            checksDiv.innerHTML = 
                createStatusItem('config_file', 'Creating config.php...') +
                createStatusItem('auth_config_file', 'Creating auth_config.php...') +
                createStatusItem('terminal_database', 'Creating terminal database...') +
                createStatusItem('admin_database', 'Creating admin database...');
            
            fetch('install.php?action=create_databases', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                Object.keys(data.checks).forEach(checkId => {
                    const check = data.checks[checkId];
                    updateStatusItem(checkId, check.message, check.status);
                });
                
                if (data.success) {
                    validateBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
        
        // Step 2: Table Validation
        document.getElementById('validateTablesBtn')?.addEventListener('click', validateTables);
        document.getElementById('recheckTablesBtn')?.addEventListener('click', validateTables);
        
        function validateTables() {
            const resultsDiv = document.getElementById('tableValidationResults');
            const checksDiv = document.getElementById('tableValidationChecks');
            const continueBtn = document.getElementById('continueStep3Btn');
            const recheckBtn = document.getElementById('recheckTablesBtn');
            
            resultsDiv.style.display = 'block';
            checksDiv.innerHTML = '<div class="status-item"><div class="status-icon status-checking"><div class="spinner"></div></div><div class="status-message">Validating all tables and columns...</div></div>';
            continueBtn.disabled = true;
            
            fetch('install.php?action=validate_tables', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                checksDiv.innerHTML = '';
                let hasErrors = false;
                
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
                        document.getElementById(checkId + '_actions').innerHTML = actions;
                    }
                });
                
                if (data.success) {
                    continueBtn.disabled = false;
                }
                
                recheckBtn.style.display = hasErrors ? 'inline-block' : 'none';
            })
            .catch(error => {
                console.error('Error:', error);
                checksDiv.innerHTML = '<div class="status-item"><div class="status-icon status-error"><i class="fas fa-times-circle"></i></div><div class="status-message">Validation failed</div></div>';
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
                    // Add recheck button
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
        
        // Step 3: Admin User Creation
        document.getElementById('createAdminBtn')?.addEventListener('click', function() {
            const form = document.getElementById('adminUserForm');
            const formData = new FormData(form);
            const resultsDiv = document.getElementById('adminCreationResults');
            const checksDiv = document.getElementById('adminCreationChecks');
            
            // Validate passwords match
            const password = form.querySelector('[name="password"]').value;
            const confirmPassword = form.querySelector('[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return;
            }
            
            resultsDiv.style.display = 'block';
            checksDiv.innerHTML = '';
            
            // Add initial status items
            checksDiv.innerHTML = 
                createStatusItem('user_validation', 'Validating user data...') +
                createStatusItem('database_connection', 'Connecting to admin database...') +
                createStatusItem('username_check', 'Checking username availability...') +
                createStatusItem('email_check', 'Checking email availability...') +
                createStatusItem('user_creation', 'Creating admin user...');
            
            fetch('install.php?action=create_admin_user', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Object.keys(data.checks).forEach(checkId => {
                    const check = data.checks[checkId];
                    updateStatusItem(checkId, check.message, check.status);
                });
                
                if (data.success) {
                    setTimeout(() => {
                        window.location.href = 'install.php?step=4';
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                updateStatusItem('user_creation', 'User creation failed', 'error');
            });
        });
        
        // Password confirmation validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Delete installer function
        function deleteInstaller() {
            if (confirm('Are you sure you want to delete the installer? This action cannot be undone.')) {
                fetch('install.php?action=delete_installer', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
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
                    alert('Error deleting installer: ' + error.message);
                });
            }
        }
        
        // Auto-advance on successful database configuration
        document.getElementById('dbConfigForm')?.addEventListener('submit', function(e) {
            const continueBtn = document.getElementById('continueBtn');
            if (continueBtn.disabled) {
                e.preventDefault();
                alert('Please test the database connections first.');
            }
        });
        
        // Show real-time feedback on form changes
        document.querySelectorAll('input[type="password"]').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.length > 0 && this.value.length < 8) {
                    this.style.borderColor = 'var(--accent-red)';
                } else {
                    this.style.borderColor = 'var(--border-color)';
                }
            });
        });
        
        document.querySelectorAll('input[type="email"]').forEach(input => {
            input.addEventListener('input', function() {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (this.value.length > 0 && !emailRegex.test(this.value)) {
                    this.style.borderColor = 'var(--accent-red)';
                } else {
                    this.style.borderColor = 'var(--border-color)';
                }
            });
        });
        
        // Auto-scroll to status sections when they appear
        function scrollToElement(elementId) {
            const element = document.getElementById(elementId);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        
        // Enhanced error handling with user-friendly messages
        window.addEventListener('unhandledrejection', function(event) {
            console.error('Unhandled promise rejection:', event.reason);
            // Don't show alert for network errors during testing
        });
        
        // Initialize tooltips and help text
        document.addEventListener('DOMContentLoaded', function() {
            // Add helpful tooltips
            const tooltips = [
                { selector: 'input[name="terminal_host"]', title: 'Usually "localhost" for local MySQL installation' },
                { selector: 'input[name="admin_host"]', title: 'Usually "localhost" for local MySQL installation' },
                { selector: 'input[name="app_url"]', title: 'The complete URL where users will access GhostCrew' }
            ];
            
            tooltips.forEach(tooltip => {
                const element = document.querySelector(tooltip.selector);
                if (element) {
                    element.setAttribute('title', tooltip.title);
                }
            });
        });
        
        // Progress persistence
        function saveProgress(step, data = {}) {
            localStorage.setItem('ghostcrew_install_progress', JSON.stringify({
                step: step,
                data: data,
                timestamp: Date.now()
            }));
        }
        
        function loadProgress() {
            const saved = localStorage.getItem('ghostcrew_install_progress');
            if (saved) {
                try {
                    const progress = JSON.parse(saved);
                    // Clear progress if older than 1 hour
                    if (Date.now() - progress.timestamp > 3600000) {
                        localStorage.removeItem('ghostcrew_install_progress');
                        return null;
                    }
                    return progress;
                } catch (e) {
                    localStorage.removeItem('ghostcrew_install_progress');
                    return null;
                }
            }
            return null;
        }
        
        // Clear progress on successful completion
        <?php if ($step === 4): ?>
        localStorage.removeItem('ghostcrew_install_progress');
        <?php endif; ?>
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+Enter to trigger main action button
            if (e.ctrlKey && e.key === 'Enter') {
                const primaryBtn = document.querySelector('.btn-primary:not([disabled])');
                if (primaryBtn) {
                    primaryBtn.click();
                }
            }
        });
        
        // Enhanced visual feedback
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function() {
                if (!this.disabled) {
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 100);
                }
            });
        });
        
        // Real-time connection status indicator
        let connectionTestInterval;
        
        function startConnectionMonitoring() {
            // Only start if we're on step 1 and have tested connections
            if (<?php echo $step; ?> === 1 && !document.getElementById('continueBtn').disabled) {
                connectionTestInterval = setInterval(() => {
                    // Periodically retest connections in background
                    fetch('install.php?action=validate_db_config', {
                        method: 'POST',
                        body: new FormData(document.getElementById('dbConfigForm'))
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            document.getElementById('continueBtn').disabled = true;
                            // Show warning
                            const warning = document.createElement('div');
                            warning.className = 'alert alert-warning mt-3';
                            warning.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Database connection lost. Please test connections again.';
                            document.querySelector('.text-center').appendChild(warning);
                            clearInterval(connectionTestInterval);
                        }
                    })
                    .catch(() => {
                        // Connection monitoring failed, stop monitoring
                        clearInterval(connectionTestInterval);
                    });
                }, 30000); // Check every 30 seconds
            }
        }
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (connectionTestInterval) {
                clearInterval(connectionTestInterval);
            }
        });
    </script>
</body>
</html>