<?php
// API Backend (api.php) - Updated with AI Configuration support

ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');


// Database configuration
$host = getenv('DB_HOST');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');
$dbname = getenv('DB_NAME');


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get request data
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? null;

try {
    switch ($action) {
        case 'login':
            handleLogin($pdo, $input);
            break;
        case 'dashboard':
            handleDashboard($pdo);
            break;
        case 'users':
            handleUsers($pdo);
            break;
        case 'sessions':
            handleSessions($pdo);
            break;
        case 'session_detail':
            handleSessionDetail($pdo, $_GET['session_id']);
            break;
        case 'user_sessions':
            handleUserSessions($pdo, $_GET['user_id']);
            break;
        case 'grading_data':
            handleGradingData($pdo, $_GET['session_id']);
            break;
        case 'save_feedback':
            handleSaveFeedback($pdo, $input);
            break;
        case 'reports':
            handleEnhancedReports($pdo, $_GET);
            break;
        case 'detailed_report':
            handleDetailedReport($pdo, $_GET['type'], $_GET);
            break;
        case 'export_report':
            handleExportReport($pdo, $_GET['type'], $_GET);
            break;
        case 'logs':
            handleLogs($pdo);
            break;
        case 'settings':
            handleSettings($pdo);
            break;
        case 'save_settings':
            handleSaveSettings($pdo, $input);
            break;
        // AI Configuration endpoints
        case 'ai_config':
            handleAiConfig($pdo);
            break;
        case 'save_ai_config':
            handleSaveAiConfig($pdo, $input);
            break;
        case 'test_ai_connection':
            handleTestAiConnection($pdo);
            break;
        case 'ai_performance_stats':
            handleAiPerformanceStats($pdo, $_GET);
            break;
        case 'ai_command_stats':
            handleAiCommandStats($pdo, $_GET);
            break;
        case 'cleanup_ai_logs':
            handleCleanupAiLogs($pdo, $input);
            break;
        case 'export_ai_config':
            handleExportAiConfig($pdo);
            break;
        case 'import_ai_config':
            handleImportAiConfig($pdo, $input);
            break;
        case 'check_ai_status':
            handleCheckAiStatus($pdo);
            break;
        // Existing endpoints
        case 'create_user':
        case 'update_user':
            handleSaveUser($pdo, $input);
            break;
        case 'delete_user':
            handleDeleteUser($pdo, $input);
            break;
        case 'reset_password':
            handleResetPassword($pdo, $input);
            break;
        case 'delete_session':
            handleDeleteSession($pdo, $input);
            break;
        case 'toggle_user_status':
            handleToggleUserStatus($pdo, $input);
            break;
        case 'user_grades':
            handleUserGrades($pdo, $_GET['user_id']);
            break;
        case 'managers_list':
            handleManagersList($pdo);
            break;
        case 'update_profile':
            handleUpdateProfile($pdo, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// AI Configuration Functions
function handleAiConfig($pdo) {
    try {
        $stmt = $pdo->query("SELECT config_key, config_value FROM ai_config ORDER BY config_key");
        $configData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $config = [];
        foreach ($configData as $row) {
            $config[$row['config_key']] = $row['config_value'];
        }
        
        // Set default values if not found
        $defaults = [
            'aws_ai_endpoint' => '',
            'aws_api_key' => '',
            'timeout_seconds' => '30',
            'max_tokens' => '1000',
            'temperature' => '0.7',
            'context_messages' => '10',
            'system_prompt' => 'You are a helpful AI assistant for command-line operations.',
            'retry_attempts' => '3',
            'retry_delay' => '1000',
            'max_context_length' => '4000',
            'command_detection' => '1'
        ];
        
        foreach ($defaults as $key => $value) {
            if (!isset($config[$key])) {
                $config[$key] = $value;
            }
        }
        
        echo json_encode(['success' => true, 'config' => $config]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleSaveAiConfig($pdo, $input) {
    try {
        $config = $input['config'] ?? [];
        $updated = 0;
        
        foreach ($config as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO ai_config (config_key, config_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
            ");
            $stmt->execute([$key, $value]);
            $updated++;
        }
        
        echo json_encode(['success' => true, 'message' => "Updated $updated configuration settings"]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleTestAiConnection($pdo) {
    try {
        // Get AI configuration
        $stmt = $pdo->query("SELECT config_key, config_value FROM ai_config");
        $configData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $config = [];
        foreach ($configData as $row) {
            $config[$row['config_key']] = $row['config_value'];
        }
        
        $endpoint = $config['aws_ai_endpoint'] ?? '';
        $apiKey = $config['aws_api_key'] ?? '';
        $timeout = (int)($config['timeout_seconds'] ?? 30);
        
        if (empty($endpoint) || empty($apiKey)) {
            echo json_encode(['success' => false, 'message' => 'Missing endpoint URL or API key']);
            return;
        }
        
        // Test connection with a simple request
        $testData = [
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => 50,
            'messages' => [
                ['role' => 'user', 'content' => 'Hello, this is a connection test. Please respond with "Connection successful".']
            ]
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($testData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo json_encode(['success' => false, 'message' => 'Connection error: ' . $error]);
            return;
        }
        
        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            if (isset($responseData['content'])) {
                echo json_encode(['success' => true, 'message' => 'Connection successful! AI responded correctly.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Connected but unexpected response format']);
            }
        } else {
            $errorMsg = "HTTP $httpCode";
            if ($response) {
                $errorData = json_decode($response, true);
                if (isset($errorData['error']['message'])) {
                    $errorMsg .= ': ' . $errorData['error']['message'];
                }
            }
            echo json_encode(['success' => false, 'message' => $errorMsg]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleAiPerformanceStats($pdo, $params) {
    try {
        $days = (int)($params['days'] ?? 7);
        
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_requests,
                AVG(response_time) as avg_response_time,
                SUM(tokens_used) as total_tokens,
                COUNT(CASE WHEN success = 1 THEN 1 END) as successful_requests
            FROM ai_performance_log 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$days]);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleAiCommandStats($pdo, $params) {
    try {
        $limit = (int)($params['limit'] ?? 10);
        
        $stmt = $pdo->prepare("
            SELECT 
                command,
                COUNT(*) as suggested_count,
                COUNT(CASE WHEN executed = 1 THEN 1 END) as executed_count,
                AVG(CASE WHEN executed = 1 THEN success_rate ELSE 0 END) as success_rate,
                MAX(last_executed) as last_executed
            FROM ai_command_suggestions 
            GROUP BY command
            ORDER BY suggested_count DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'stats' => $stats]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleCleanupAiLogs($pdo, $input) {
    try {
        $days = (int)($input['days'] ?? 30);
        
        $stmt = $pdo->prepare("DELETE FROM ai_performance_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        $deleted = $stmt->rowCount();
        
        echo json_encode(['success' => true, 'message' => "Cleaned up $deleted old performance log entries"]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleExportAiConfig($pdo) {
    try {
        $stmt = $pdo->query("SELECT config_key, config_value FROM ai_config");
        $configData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $config = [];
        foreach ($configData as $row) {
            $config[$row['config_key']] = $row['config_value'];
        }
        
        $exportData = [
            'exported_at' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'config' => $config
        ];
        
        echo json_encode(['success' => true, 'config' => json_encode($exportData, JSON_PRETTY_PRINT)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleImportAiConfig($pdo, $input) {
    try {
        $configData = $input['config_data'] ?? '';
        $data = json_decode($configData, true);
        
        if (!$data || !isset($data['config'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid configuration format']);
            return;
        }
        
        $imported = 0;
        $total = count($data['config']);
        
        foreach ($data['config'] as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO ai_config (config_key, config_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
            ");
            $stmt->execute([$key, $value]);
            $imported++;
        }
        
        echo json_encode(['success' => true, 'imported' => $imported, 'total' => $total]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleCheckAiStatus($pdo) {
    try {
        // Simple status check - verify configuration exists
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM ai_config WHERE config_key IN ('aws_ai_endpoint', 'aws_api_key')");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $connected = $result['count'] >= 2;
        echo json_encode(['success' => true, 'connected' => $connected]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Create AI configuration tables if they don't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ai_config (
            config_key VARCHAR(64) PRIMARY KEY,
            config_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ai_performance_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_type VARCHAR(32),
            response_time INT,
            tokens_used INT,
            success BOOLEAN DEFAULT FALSE,
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ai_command_suggestions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            command TEXT,
            suggested_count INT DEFAULT 1,
            executed_count INT DEFAULT 0,
            executed BOOLEAN DEFAULT FALSE,
            success_rate DECIMAL(5,2) DEFAULT 0.00,
            last_executed TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {
    // Tables might already exist, continue
}

// [Rest of the existing functions remain unchanged]
// ... (Include all the existing functions from the original api.php)

function validateUserAccess($requiredRole, $currentUserRole, $targetUserId = null, $currentUserId = null) {
    $roleHierarchy = ['operator' => 1, 'manager' => 2, 'admin' => 3];
    
    // Admin can access everything
    if ($currentUserRole === 'admin') {
        return true;
    }
    
    // Manager can access operator data and their own
    if ($currentUserRole === 'manager') {
        if ($requiredRole === 'operator' || $requiredRole === 'manager') {
            return true;
        }
        if ($targetUserId && $targetUserId == $currentUserId) {
            return true;
        }
    }
    
    // Operator can only access their own data
    if ($currentUserRole === 'operator') {
        return $targetUserId && $targetUserId == $currentUserId;
    }
    
    return false;
}

function handleToggleUserStatus($pdo, $input) {
    $userId = $input['user_id'];
    $isActive = $input['is_active'];
    
    $stmt = $pdo->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$isActive, $userId]);
    
    echo json_encode(['success' => true]);
}

function handleLogin($pdo, $input) {
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        // Update last login
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        // Generate session token
        $sessionToken = bin2hex(random_bytes(32));
        
        // Store session
        $sessionStmt = $pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent) 
            VALUES (?, ?, ?, ?)
        ");
        $sessionStmt->execute([
            $user['id'], 
            $sessionToken, 
            $_SERVER['REMOTE_ADDR'] ?? '', 
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        unset($user['password_hash']); // Don't send password hash to client
        echo json_encode([
            'success' => true, 
            'user' => $user, 
            'session_token' => $sessionToken
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
}

function handleDashboard($pdo) {
    $userRole = $_GET['user_role'] ?? 'admin';
    $userId = $_GET['user_id'] ?? null;
    
    // Get statistics based on user role
    $stats = [];
    
    if ($userRole === 'operator') {
        // Operators only see their own data
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $stats['total_users'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as active_sessions FROM remote_sessions WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        $stats['active_sessions'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_commands FROM command_log WHERE user_id = ?");
        $stmt->execute([$userId]);
        $stats['total_commands'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT AVG(execution_time) as avg_time FROM command_log WHERE user_id = ? AND execution_time IS NOT NULL");
        $stmt->execute([$userId]);
        $stats['avg_execution_time'] = round($stmt->fetchColumn() ?: 0, 3);
    } else if ($userRole === 'manager') {
        // Managers see their subordinates' data (operators)
        $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE role = 'operator' AND is_active = 1");
        $stats['total_users'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("
            SELECT COUNT(*) as active_sessions 
            FROM remote_sessions rs 
            JOIN users u ON rs.user_id = u.id 
            WHERE u.role = 'operator' AND rs.status = 'active'
        ");
        $stats['active_sessions'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("
            SELECT COUNT(*) as total_commands 
            FROM command_log cl 
            JOIN users u ON cl.user_id = u.id 
            WHERE u.role = 'operator'
        ");
        $stats['total_commands'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("
            SELECT AVG(cl.execution_time) as avg_time 
            FROM command_log cl 
            JOIN users u ON cl.user_id = u.id 
            WHERE u.role = 'operator' AND cl.execution_time IS NOT NULL
        ");
        $stats['avg_execution_time'] = round($stmt->fetchColumn() ?: 0, 3);
    } else {
        // Admins see all data
        $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE is_active = 1");
        $stats['total_users'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) as active_sessions FROM remote_sessions WHERE status = 'active'");
        $stats['active_sessions'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) as total_commands FROM command_log");
        $stats['total_commands'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT AVG(execution_time) as avg_time FROM command_log WHERE execution_time IS NOT NULL");
        $stats['avg_execution_time'] = round($stmt->fetchColumn() ?: 0, 3);
    }
    
    // Get chart data (filtered by role)
    $charts = [];
    
    // Activity chart - sessions, commands, and active users per day for last 7 days
    if ($userRole === 'operator') {
        // Sessions data
        $stmt = $pdo->prepare("
            SELECT DATE(start_time) as date, COUNT(*) as sessions 
            FROM remote_sessions 
            WHERE user_id = ? AND start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(start_time)
            ORDER BY date
        ");
        $stmt->execute([$userId]);
        $sessionData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Commands data
        $stmt = $pdo->prepare("
            SELECT DATE(timestamp) as date, COUNT(*) as commands 
            FROM command_log 
            WHERE user_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(timestamp)
            ORDER BY date
        ");
        $stmt->execute([$userId]);
        $commandData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Active users for operator is just themselves when they log in
        $activeUsersData = [];
        if ($userId) {
            $stmt = $pdo->prepare("
                SELECT DATE(login_time) as date, 1 as active_users 
                FROM user_sessions 
                WHERE user_id = ? AND login_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(login_time)
                ORDER BY date
            ");
            $stmt->execute([$userId]);
            $activeUsersData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else if ($userRole === 'manager') {
        // Sessions data for operators
        $stmt = $pdo->query("
            SELECT DATE(rs.start_time) as date, COUNT(*) as sessions 
            FROM remote_sessions rs 
            JOIN users u ON rs.user_id = u.id 
            WHERE u.role = 'operator' AND rs.start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(rs.start_time)
            ORDER BY date
        ");
        $sessionData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Commands data for operators
        $stmt = $pdo->query("
            SELECT DATE(cl.timestamp) as date, COUNT(*) as commands 
            FROM command_log cl 
            JOIN users u ON cl.user_id = u.id 
            WHERE u.role = 'operator' AND cl.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(cl.timestamp)
            ORDER BY date
        ");
        $commandData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Active users (operators only)
        $stmt = $pdo->query("
            SELECT DATE(us.login_time) as date, COUNT(DISTINCT us.user_id) as active_users 
            FROM user_sessions us 
            JOIN users u ON us.user_id = u.id 
            WHERE u.role = 'operator' AND us.login_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(us.login_time)
            ORDER BY date
        ");
        $activeUsersData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // All sessions for admins
        $stmt = $pdo->query("
            SELECT DATE(start_time) as date, COUNT(*) as sessions 
            FROM remote_sessions 
            WHERE start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(start_time)
            ORDER BY date
        ");
        $sessionData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // All commands for admins
        $stmt = $pdo->query("
            SELECT DATE(timestamp) as date, COUNT(*) as commands 
            FROM command_log 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(timestamp)
            ORDER BY date
        ");
        $commandData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // All active users for admins
        $stmt = $pdo->query("
            SELECT DATE(login_time) as date, COUNT(DISTINCT user_id) as active_users 
            FROM user_sessions 
            WHERE login_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(login_time)
            ORDER BY date
        ");
        $activeUsersData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Prepare chart data for last 7 days
    $last7Days = [];
    for ($i = 6; $i >= 0; $i--) {
        $last7Days[] = date('M j', strtotime("-$i days"));
    }
    
    $sessionCounts = array_fill(0, 7, 0);
    $commandCounts = array_fill(0, 7, 0);
    $activeUsersCounts = array_fill(0, 7, 0);
    
    // Process session data
    foreach ($sessionData as $row) {
        $dayIndex = 6 - (strtotime('today') - strtotime($row['date'])) / 86400;
        if ($dayIndex >= 0 && $dayIndex < 7) {
            $sessionCounts[$dayIndex] = (int)$row['sessions'];
        }
    }
    
    // Process command data
    foreach ($commandData as $row) {
        $dayIndex = 6 - (strtotime('today') - strtotime($row['date'])) / 86400;
        if ($dayIndex >= 0 && $dayIndex < 7) {
            $commandCounts[$dayIndex] = (int)$row['commands'];
        }
    }
    
    // Process active users data
    foreach ($activeUsersData as $row) {
        $dayIndex = 6 - (strtotime('today') - strtotime($row['date'])) / 86400;
        if ($dayIndex >= 0 && $dayIndex < 7) {
            $activeUsersCounts[$dayIndex] = (int)$row['active_users'];
        }
    }
    
    $charts['activity'] = [
        'labels' => $last7Days,
        'datasets' => [
            'active_users' => $activeUsersCounts,
            'sessions' => $sessionCounts,
            'commands' => $commandCounts
        ]
    ];
    
    // Status chart - command execution status distribution
    if ($userRole === 'operator') {
        $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM command_log WHERE user_id = ? GROUP BY status");
        $stmt->execute([$userId]);
    } else if ($userRole === 'manager') {
        $stmt = $pdo->query("
            SELECT cl.status, COUNT(*) as count 
            FROM command_log cl 
            JOIN users u ON cl.user_id = u.id 
            WHERE u.role = 'operator' 
            GROUP BY cl.status
        ");
    } else {
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM command_log GROUP BY status");
    }
    $statusData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure we have some data for the status chart
    if (empty($statusData)) {
        $statusData = [
            ['status' => 'No Data', 'count' => 0]
        ];
    }
    
    $charts['status'] = [
        'labels' => array_column($statusData, 'status'),
        'values' => array_map('intval', array_column($statusData, 'count'))
    ];
    
    echo json_encode(['success' => true, 'stats' => $stats, 'charts' => $charts]);
}

function handleUsers($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT u.id, u.username, u.full_name, u.email, u.role, u.is_active, 
                   u.last_login, u.created_at, u.manager_id,
                   m.full_name as manager_name, m.role as manager_role
            FROM users u 
            LEFT JOIN users m ON u.manager_id = m.id 
            ORDER BY u.id
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ensure is_active is returned as integer
        foreach ($users as &$user) {
            $user['is_active'] = (int)$user['is_active'];
            $user['id'] = (int)$user['id'];
            $user['manager_id'] = $user['manager_id'] ? (int)$user['manager_id'] : null;
        }
        
        echo json_encode(['success' => true, 'users' => $users]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleSessions($pdo) {
    $stmt = $pdo->query("
        SELECT rs.*, u.full_name as user_name,
               CASE WHEN sf.session_id IS NOT NULL THEN 1 ELSE 0 END as has_feedback
        FROM remote_sessions rs 
        LEFT JOIN users u ON rs.user_id = u.id 
        LEFT JOIN session_feedback sf ON rs.session_id = sf.session_id
        ORDER BY rs.start_time DESC
    ");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure has_feedback is properly converted to integer
    foreach ($sessions as &$session) {
        $session['has_feedback'] = (int)$session['has_feedback'];
        $session['user_id'] = (int)$session['user_id'];
    }
    
    echo json_encode(['success' => true, 'sessions' => $sessions]);
}

function handleSessionDetail($pdo, $sessionId) {
    // Get session info
    $stmt = $pdo->prepare("SELECT * FROM remote_sessions WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode(['success' => false, 'message' => 'Session not found']);
        return;
    }
    
    // Get session commands
    $stmt = $pdo->prepare("SELECT * FROM command_log WHERE session_id = ? ORDER BY timestamp");
    $stmt->execute([$sessionId]);
    $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get chatbot conversations
    $stmt = $pdo->prepare("
        SELECT * FROM chatbot_conversations 
        WHERE session_id = ? 
        ORDER BY timestamp
    ");
    $stmt->execute([$sessionId]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get feedback if exists
    $stmt = $pdo->prepare("
        SELECT sf.*, u.full_name as grader_name 
        FROM session_feedback sf 
        LEFT JOIN users u ON sf.graded_by = u.id 
        WHERE sf.session_id = ?
    ");
    $stmt->execute([$sessionId]);
    $feedback = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'session' => $session, 
        'commands' => $commands, 
        'conversations' => $conversations,
        'feedback' => $feedback
    ]);
}

function handleUserSessions($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT session_id, start_time FROM remote_sessions WHERE user_id = ? ORDER BY start_time DESC");
    $stmt->execute([$userId]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'sessions' => $sessions]);
}

function handleGradingData($pdo, $sessionId) {
    // Get session info
    $stmt = $pdo->prepare("SELECT * FROM remote_sessions WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode(['success' => false, 'message' => 'Session not found']);
        return;
    }
    
    // Get commands
    $stmt = $pdo->prepare("SELECT * FROM command_log WHERE session_id = ? ORDER BY timestamp");
    $stmt->execute([$sessionId]);
    $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get chatbot conversations
    $stmt = $pdo->prepare("
        SELECT * FROM chatbot_conversations 
        WHERE session_id = ? 
        ORDER BY timestamp
    ");
    $stmt->execute([$sessionId]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get existing feedback
    $stmt = $pdo->prepare("SELECT * FROM session_feedback WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $feedback = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($feedback && $feedback['command_feedback']) {
        $feedback['command_feedback'] = json_decode($feedback['command_feedback'], true);
    }
    
    echo json_encode([
        'success' => true, 
        'session' => $session, 
        'commands' => $commands, 
        'conversations' => $conversations,
        'feedback' => $feedback
    ]);
}

function handleSaveFeedback($pdo, $input) {
    $sessionId = $input['session_id'];
    $userId = $input['user_id'];
    $overallScore = $input['overall_score'];
    $instructorFeedback = $input['instructor_feedback'];
    $commandFeedback = json_encode($input['command_feedback']);
    $rating = $input['rating'];
    $gradedBy = $input['graded_by'];
    
    // Check if feedback already exists
    $stmt = $pdo->prepare("SELECT id FROM session_feedback WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $existingFeedback = $stmt->fetch();
    
    if ($existingFeedback) {
        // Update existing feedback
        $stmt = $pdo->prepare("
            UPDATE session_feedback 
            SET overall_score = ?, instructor_feedback = ?, command_feedback = ?, 
                rating = ?, graded_by = ?, graded_at = NOW() 
            WHERE session_id = ?
        ");
        $stmt->execute([$overallScore, $instructorFeedback, $commandFeedback, $rating, $gradedBy, $sessionId]);
    } else {
        // Create new feedback
        $stmt = $pdo->prepare("
            INSERT INTO session_feedback 
            (session_id, user_id, overall_score, instructor_feedback, command_feedback, rating, graded_by, graded_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$sessionId, $userId, $overallScore, $instructorFeedback, $commandFeedback, $rating, $gradedBy]);
    }
    
    echo json_encode(['success' => true]);
}

function handleEnhancedReports($pdo, $params = []) {
    error_log("handleEnhancedReports called with params: " . json_encode($params));
    try {
        $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $params['end_date'] ?? date('Y-m-d');

        // Add this right after $endDate is set in handleEnhancedReports
        $actualStartDate = date('Y-m-d', strtotime($endDate . ' -14 days'));
        error_log("Query date range: " . $actualStartDate . " to " . $endDate);

        $userRole = $params['user_role'] ?? '';
        
        $stats = [];
        $charts = [];
        
        // ============ OVERVIEW STATISTICS ============
        
        // Total users by role
        $stmt = $pdo->query("
            SELECT role, COUNT(*) as count, 
                   SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
            FROM users 
            GROUP BY role
        ");
        $userStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Session statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_sessions,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_sessions,
                COUNT(CASE WHEN status = 'completed' OR status = 'terminated' THEN 1 END) as completed_sessions,
                AVG(TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, NOW()))) as avg_duration,
                COUNT(CASE WHEN DATE(start_time) >= ? THEN 1 END) as recent_sessions
            FROM remote_sessions 
            WHERE DATE(start_time) BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $startDate, $endDate]);
        $sessionStats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Add this after any session query
        $testStmt = $pdo->query("SELECT COUNT(*) as total, MAX(start_time) as latest FROM remote_sessions");
        $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
        error_log("Total sessions in DB: " . $testResult['total'] . ", Latest: " . $testResult['latest']);
        
        // Command statistics
        $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_commands,
            COUNT(CASE WHEN cl.status = 'completed' THEN 1 END) as successful_commands,
            COUNT(CASE WHEN cl.status = 'failed' THEN 1 END) as failed_commands,
            AVG(cl.execution_time) as avg_execution_time
        FROM command_log cl
        JOIN remote_sessions rs ON cl.session_id = rs.session_id
        WHERE DATE(cl.timestamp) BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $commandStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Grading statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_graded,
                AVG(overall_score) as avg_score,
                AVG(rating) as avg_rating,
                COUNT(CASE WHEN overall_score >= 80 THEN 1 END) as high_performers,
                COUNT(CASE WHEN overall_score < 60 THEN 1 END) as low_performers
            FROM session_feedback sf
            JOIN remote_sessions rs ON sf.session_id = rs.session_id
            WHERE DATE(sf.graded_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $gradingStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Chatbot interaction statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_messages,
                COUNT(CASE WHEN message_type = 'user' THEN 1 END) as user_messages,
                COUNT(CASE WHEN message_type = 'bot' THEN 1 END) as bot_responses,
                COUNT(CASE WHEN suggested_command IS NOT NULL THEN 1 END) as suggestions_given,
                COUNT(CASE WHEN command_executed = 1 THEN 1 END) as suggestions_executed,
                AVG(response_time) as avg_response_time
            FROM chatbot_conversations cc
            JOIN remote_sessions rs ON cc.session_id = rs.session_id
            WHERE DATE(cc.timestamp) BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $chatbotStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Login activity
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(*) as total_logins,
                COUNT(CASE WHEN DATE(login_time) = CURDATE() THEN 1 END) as today_logins
            FROM user_sessions 
            WHERE DATE(login_time) BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $loginStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stats['overview'] = [
            'users' => $userStats,
            'sessions' => $sessionStats,
            'commands' => $commandStats,
            'grading' => $gradingStats,
            'chatbot' => $chatbotStats,
            'logins' => $loginStats
        ];
        
        // ============ CHART DATA ============
        
        // User activity over time (last 14 days) - Fixed
        $actualStartDate = date('Y-m-d', strtotime($endDate . ' -14 days'));
        error_log("Activity query date range: " . $actualStartDate . " to " . $endDate);

        $stmt = $pdo->prepare("
            SELECT 
                DATE(start_time) as date,
                COUNT(DISTINCT user_id) as active_users,
                COUNT(*) as sessions
            FROM remote_sessions 
            WHERE DATE(start_time) BETWEEN ? AND ?
            GROUP BY DATE(start_time)
            ORDER BY date
        ");
        $stmt->execute([$actualStartDate, $endDate]);
        $sessionActivityData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get command counts for the same period
        $stmt = $pdo->prepare("
            SELECT 
                DATE(cl.timestamp) as date,
                COUNT(*) as commands
            FROM command_log cl
            WHERE DATE(cl.timestamp) BETWEEN ? AND ?
            GROUP BY DATE(cl.timestamp)
            ORDER BY date
        ");
        $stmt->execute([$actualStartDate, $endDate]);
        $commandActivityData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Create complete dataset for all 14 days
        $activityData = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime($endDate . " -$i days"));
            $activityData[$date] = [
                'date' => $date,
                'active_users' => 0,
                'sessions' => 0,
                'commands' => 0
            ];
        }

        // Fill in session data
        foreach ($sessionActivityData as $row) {
            $date = $row['date'];
            if (isset($activityData[$date])) {
                $activityData[$date]['active_users'] = (int)$row['active_users'];
                $activityData[$date]['sessions'] = (int)$row['sessions'];
            }
        }

        // Fill in command data
        foreach ($commandActivityData as $row) {
            $date = $row['date'];
            if (isset($activityData[$date])) {
                $activityData[$date]['commands'] = (int)$row['commands'];
            }
        }

        // Convert to indexed array and sort
        $activityData = array_values($activityData);
        error_log("Final activity data: " . json_encode($activityData));
        
        // Grade distribution
        $stmt = $pdo->prepare("
            SELECT 
                CASE 
                    WHEN overall_score >= 90 THEN '90-100'
                    WHEN overall_score >= 80 THEN '80-89'
                    WHEN overall_score >= 70 THEN '70-79'
                    WHEN overall_score >= 60 THEN '60-69'
                    ELSE 'Below 60'
                END as grade_range,
                COUNT(*) as count
            FROM session_feedback sf
            JOIN remote_sessions rs ON sf.session_id = rs.session_id
            WHERE DATE(sf.graded_at) BETWEEN ? AND ?
            GROUP BY grade_range
            ORDER BY 
                CASE grade_range
                    WHEN '90-100' THEN 1
                    WHEN '80-89' THEN 2
                    WHEN '70-79' THEN 3
                    WHEN '60-69' THEN 4
                    ELSE 5
                END
        ");
        $stmt->execute([$startDate, $endDate]);
        $gradeDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Most used commands
        $stmt = $pdo->prepare("
        SELECT 
            SUBSTRING_INDEX(cl.command, ' ', 1) as base_command,
            COUNT(*) as usage_count,
            COUNT(CASE WHEN cl.status = 'completed' THEN 1 END) as success_count,
            ROUND(COUNT(CASE WHEN cl.status = 'completed' THEN 1 END) * 100.0 / COUNT(*), 1) as success_rate
        FROM command_log cl
        JOIN remote_sessions rs ON cl.session_id = rs.session_id
        WHERE DATE(cl.timestamp) BETWEEN ? AND ?
        GROUP BY base_command
        ORDER BY usage_count DESC
        LIMIT 10
        ");
        $stmt->execute([$startDate, $endDate]);
        $commandUsage = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Session duration trends
        $stmt = $pdo->prepare("
        SELECT 
            DATE(rs.start_time) as date,
            AVG(TIMESTAMPDIFF(MINUTE, rs.start_time, COALESCE(rs.end_time, NOW()))) as avg_duration,
            COUNT(*) as session_count
        FROM remote_sessions rs
        WHERE DATE(rs.start_time) BETWEEN ? AND ?
        GROUP BY DATE(rs.start_time)
        ORDER BY date
        ");
        $stmt->execute([$startDate, $endDate]);
        $durationTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // User performance by role
        $stmt = $pdo->prepare("
            SELECT 
                u.role,
                COUNT(DISTINCT sf.session_id) as graded_sessions,
                AVG(sf.overall_score) as avg_score,
                AVG(sf.rating) as avg_rating,
                COUNT(DISTINCT rs.session_id) as total_sessions
            FROM users u
            LEFT JOIN remote_sessions rs ON u.id = rs.user_id
            LEFT JOIN session_feedback sf ON rs.session_id = sf.session_id
            WHERE DATE(rs.start_time) BETWEEN ? AND ?
            GROUP BY u.role
        ");
        $stmt->execute([$startDate, $endDate]);
        $performanceByRole = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Chatbot effectiveness
        $stmt = $pdo->prepare("
            SELECT 
                DATE(cc.timestamp) as date,
                COUNT(CASE WHEN cc.suggested_command IS NOT NULL THEN 1 END) as suggestions_given,
                COUNT(CASE WHEN cc.command_executed = 1 THEN 1 END) as suggestions_executed,
                AVG(cc.response_time) as avg_response_time
            FROM chatbot_conversations cc
            JOIN remote_sessions rs ON cc.session_id = rs.session_id
            WHERE DATE(cc.timestamp) BETWEEN ? AND ?
            GROUP BY DATE(cc.timestamp)
            ORDER BY date
        ");
        $stmt->execute([$startDate, $endDate]);
        $chatbotEffectiveness = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Prepare chart data
        $charts = [
            'activity' => prepareActivityChart($activityData),
            'grade_distribution' => prepareGradeDistributionChart($gradeDistribution),
            'command_usage' => prepareCommandUsageChart($commandUsage),
            'duration_trends' => prepareDurationTrendsChart($durationTrends),
            'performance_by_role' => preparePerformanceChart($performanceByRole),
            'chatbot_effectiveness' => prepareChatbotChart($chatbotEffectiveness)
        ];
        
        echo json_encode(['success' => true, 'stats' => $stats, 'charts' => $charts]);
        
    } catch (Exception $e) {
        error_log("Enhanced reports error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error loading reports: ' . $e->getMessage()]);
    }
}

function prepareActivityChart($data) {
    $dates = [];
    $users = [];
    $sessions = [];
    $commands = [];
    
    // Fill in missing dates for the last 14 days
    for ($i = 13; $i >= 0; $i--) {
        $date = date('M j', strtotime("-$i days"));
        $dbDate = date('Y-m-d', strtotime("-$i days"));
        
        $dates[] = $date;
        
        $found = false;
        foreach ($data as $row) {
            if ($row['date'] === $dbDate) {
                $users[] = (int)$row['active_users'];
                $sessions[] = (int)$row['sessions'];
                $commands[] = (int)$row['commands'];
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $users[] = 0;
            $sessions[] = 0;
            $commands[] = 0;
        }
    }
    
    return [
        'labels' => $dates,
        'datasets' => [
            'active_users' => $users,
            'sessions' => $sessions,
            'commands' => $commands
        ]
    ];
}

function prepareGradeDistributionChart($data) {
    if (empty($data)) {
        return ['labels' => ['No Data'], 'values' => [0]];
    }
    
    return [
        'labels' => array_column($data, 'grade_range'),
        'values' => array_map('intval', array_column($data, 'count'))
    ];
}

function prepareCommandUsageChart($data) {
    if (empty($data)) {
        return ['labels' => ['No Data'], 'usage' => [0], 'success_rates' => [0]];
    }
    
    return [
        'labels' => array_column($data, 'base_command'),
        'usage' => array_map('intval', array_column($data, 'usage_count')),
        'success_rates' => array_map('floatval', array_column($data, 'success_rate'))
    ];
}

function prepareDurationTrendsChart($data) {
    $dates = [];
    $durations = [];
    
    foreach ($data as $row) {
        $dates[] = date('M j', strtotime($row['date']));
        $durations[] = round((float)$row['avg_duration'], 1);
    }
    
    return [
        'labels' => $dates,
        'values' => $durations
    ];
}

function preparePerformanceChart($data) {
    if (empty($data)) {
        return ['labels' => ['No Data'], 'scores' => [0], 'ratings' => [0]];
    }
    
    return [
        'labels' => array_column($data, 'role'),
        'scores' => array_map(function($score) { return round((float)$score, 1); }, array_column($data, 'avg_score')),
        'ratings' => array_map(function($rating) { return round((float)$rating, 1); }, array_column($data, 'avg_rating'))
    ];
}

function prepareChatbotChart($data) {
    $dates = [];
    $suggestions = [];
    $executed = [];
    $effectiveness = [];
    
    foreach ($data as $row) {
        $dates[] = date('M j', strtotime($row['date']));
        $suggestions[] = (int)$row['suggestions_given'];
        $executed[] = (int)$row['suggestions_executed'];
        $effectiveness[] = $row['suggestions_given'] > 0 ? 
            round(($row['suggestions_executed'] / $row['suggestions_given']) * 100, 1) : 0;
    }
    
    return [
        'labels' => $dates,
        'suggestions' => $suggestions,
        'executed' => $executed,
        'effectiveness' => $effectiveness
    ];
}

function handleDetailedReport($pdo, $type, $params = []) {
    $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $params['end_date'] ?? date('Y-m-d');
    
    try {
        switch ($type) {
            case 'user_performance':
                $stmt = $pdo->prepare("
                    SELECT 
                        u.full_name,
                        u.role,
                        COUNT(DISTINCT rs.session_id) as total_sessions,
                        COUNT(DISTINCT sf.session_id) as graded_sessions,
                        AVG(sf.overall_score) as avg_score,
                        AVG(sf.rating) as avg_rating,
                        COUNT(cl.id) as total_commands,
                        COUNT(CASE WHEN cl.status = 'completed' THEN 1 END) as successful_commands,
                        AVG(TIMESTAMPDIFF(MINUTE, rs.start_time, rs.end_time)) as avg_session_duration
                    FROM users u
                    LEFT JOIN remote_sessions rs ON u.id = rs.user_id AND DATE(rs.start_time) BETWEEN ? AND ?
                    LEFT JOIN session_feedback sf ON rs.session_id = sf.session_id
                    LEFT JOIN command_log cl ON rs.session_id = cl.session_id
                    WHERE u.is_active = 1
                    GROUP BY u.id, u.full_name, u.role
                    ORDER BY avg_score DESC
                ");
                $stmt->execute([$startDate, $endDate]);
                break;
                
            case 'grading_analytics':
                $stmt = $pdo->prepare("
                    SELECT 
                        sf.session_id,
                        u.full_name as student_name,
                        g.full_name as grader_name,
                        sf.overall_score,
                        sf.rating,
                        sf.graded_at,
                        rs.start_time as session_date,
                        COUNT(cl.id) as commands_in_session
                    FROM session_feedback sf
                    JOIN remote_sessions rs ON sf.session_id = rs.session_id
                    JOIN users u ON sf.user_id = u.id
                    LEFT JOIN users g ON sf.graded_by = g.id
                    LEFT JOIN command_log cl ON rs.session_id = cl.session_id
                    WHERE DATE(sf.graded_at) BETWEEN ? AND ?
                    GROUP BY sf.id
                    ORDER BY sf.graded_at DESC
                ");
                $stmt->execute([$startDate, $endDate]);
                break;
                
            case 'chatbot_analytics':
                $stmt = $pdo->prepare("
                    SELECT 
                        u.full_name as user_name,
                        COUNT(CASE WHEN cc.message_type = 'user' THEN 1 END) as questions_asked,
                        COUNT(CASE WHEN cc.suggested_command IS NOT NULL THEN 1 END) as suggestions_received,
                        COUNT(CASE WHEN cc.command_executed = 1 THEN 1 END) as suggestions_executed,
                        AVG(cc.response_time) as avg_response_time,
                        COUNT(CASE WHEN cc.rating = 1 THEN 1 END) as helpful_ratings,
                        COUNT(CASE WHEN cc.rating = 0 THEN 1 END) as unhelpful_ratings
                    FROM chatbot_conversations cc
                    JOIN remote_sessions rs ON cc.session_id = rs.session_id
                    JOIN users u ON cc.user_id = u.id
                    WHERE DATE(cc.timestamp) BETWEEN ? AND ?
                    GROUP BY cc.user_id, u.full_name
                    ORDER BY questions_asked DESC
                ");
                $stmt->execute([$startDate, $endDate]);
                break;
                
            default:
                throw new Exception("Unknown report type: $type");
        }
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $data, 'type' => $type]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleLogs($pdo) {
    $stmt = $pdo->query("
        SELECT al.*, u.username as user_name 
        FROM audit_log al 
        LEFT JOIN users u ON al.user_id = u.id 
        ORDER BY al.timestamp DESC 
        LIMIT 1000
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get chatbot conversations as additional log entries
    $chatStmt = $pdo->query("
        SELECT 
            cc.timestamp,
            cc.user_id,
            u.username as user_name,
            'chat_message' as action_type,
            cc.session_id as ip_address,
            CONCAT('Type: ', cc.message_type, ', Message: ', cc.message) as action_details
        FROM chatbot_conversations cc
        LEFT JOIN users u ON cc.user_id = u.id
        ORDER BY cc.timestamp DESC
        LIMIT 500
    ");
    $chatLogs = $chatStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge and sort logs
    $allLogs = array_merge($logs, $chatLogs);
    usort($allLogs, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    echo json_encode(['success' => true, 'logs' => array_slice($allLogs, 0, 1000)]);
}

function handleSettings($pdo) {
    $stmt = $pdo->query("SELECT config_key, config_value FROM system_config");
    $settingsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $settings = [];
    foreach ($settingsData as $row) {
        $settings[$row['config_key']] = $row['config_value'];
    }
    
    echo json_encode(['success' => true, 'settings' => $settings]);
}

function handleSaveSettings($pdo, $input) {
    $settings = $input['settings'];
    
    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("
            INSERT INTO system_config (config_key, config_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
        ");
        $stmt->execute([$key, $value]);
    }
    
    echo json_encode(['success' => true]);
}

// UPDATED: Save user function to include manager assignment
function handleSaveUser($pdo, $input) {
    try {
        $isUpdate = $input['action'] === 'update_user';
        $userId = $input['user_id'] ?? null;
        $username = $input['username'];
        $fullName = $input['full_name'];
        $email = $input['email'];
        $role = $input['role'];
        $isActive = $input['is_active'];
        $password = $input['password'] ?? '';
        $managerId = $input['manager_id'] ?? null; // New field
        
        if ($isUpdate && $userId) {
            // Update user
            if (!empty($password)) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, full_name = ?, email = ?, role = ?, is_active = ?, 
                        password_hash = ?, manager_id = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$username, $fullName, $email, $role, $isActive, $passwordHash, $managerId, $userId]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, full_name = ?, email = ?, role = ?, is_active = ?, 
                        manager_id = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$username, $fullName, $email, $role, $isActive, $managerId, $userId]);
            }
        } else {
            // Create user
            if (empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Password is required for new users']);
                return;
            }
            
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, full_name, email, role, is_active, password_hash, manager_id, created_at, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([$username, $fullName, $email, $role, $isActive, $passwordHash, $managerId, $input['created_by'] ?? null]);
        }
        
        echo json_encode(['success' => true]);
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Integrity constraint violation
            echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleDeleteUser($pdo, $input) {
    $userId = $input['user_id'];
    
    // Don't allow deleting the admin user
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['role'] === 'admin') {
        echo json_encode(['success' => false, 'message' => 'Cannot delete admin user']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    
    echo json_encode(['success' => true]);
}

function handleResetPassword($pdo, $input) {
    $userId = $input['user_id'];
    $newPassword = $input['new_password'] ?? null;
    
    if (!$newPassword) {
        // Generate random password if none provided (backwards compatibility)
        $newPassword = 'temp' . substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 8);
    }
    
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$passwordHash, $userId]);
    
    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
}

function handleDeleteSession($pdo, $input) {
    $sessionId = $input['session_id'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Delete related records first
        $stmt = $pdo->prepare("DELETE FROM command_log WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        
        $stmt = $pdo->prepare("DELETE FROM session_contexts WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        
        $stmt = $pdo->prepare("DELETE FROM chatbot_conversations WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        
        $stmt = $pdo->prepare("DELETE FROM session_feedback WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        
        // Delete the session
        $stmt = $pdo->prepare("DELETE FROM remote_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Create session_feedback table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS session_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL,
            user_id INT NOT NULL,
            overall_score INT DEFAULT NULL,
            instructor_feedback TEXT DEFAULT NULL,
            command_feedback LONGTEXT DEFAULT NULL,
            rating TINYINT DEFAULT NULL,
            graded_by INT DEFAULT NULL,
            graded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (session_id) REFERENCES remote_sessions(session_id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {
    // Table might already exist, continue
}

// Handle user grades
function handleUserGrades($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT sf.*, rs.session_id, rs.start_time
            FROM session_feedback sf 
            JOIN remote_sessions rs ON sf.session_id = rs.session_id
            WHERE sf.user_id = ? 
            ORDER BY sf.graded_at DESC
        ");
        $stmt->execute([$userId]);
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'grades' => $grades]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// NEW: Handle managers list for user creation/editing
function handleManagersList($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT id, full_name, role 
            FROM users 
            WHERE role IN ('manager', 'admin') AND is_active = 1 
            ORDER BY role DESC, full_name ASC
        ");
        $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'managers' => $managers]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleUpdateProfile($pdo, $input) {
    $userId = $input['user_id'];
    $fullName = $input['full_name'];
    $email = $input['email'];
    $currentPassword = $input['current_password'];
    $newPassword = $input['new_password'] ?? null;
    
    try {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            return;
        }
        
        // Update profile
        if ($newPassword) {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, password_hash = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$fullName, $email, $passwordHash, $userId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$fullName, $email, $userId]);
        }
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

?>