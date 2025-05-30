<?php
// API Backend (api.php) - Updated with fixes

ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

define('ADMIN_DB_HOST', 'localhost');
define('ADMIN_DB_USER', 'svc_ghostcrew_admin');
define('ADMIN_DB_PASS', 'SecureP@ssw0rd2024!');
define('ADMIN_DB_NAME', 'ghostcrew_admin');

// Database configuration
$host = ADMIN_DB_HOST;
$dbname = ADMIN_DB_NAME;
$username = ADMIN_DB_USER;
$password = ADMIN_DB_PASS;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
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
            handleReports($pdo);
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
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
    
    // Activity chart - sessions and commands per day for last 7 days
    if ($userRole === 'operator') {
        $stmt = $pdo->prepare("
            SELECT DATE(start_time) as date, COUNT(*) as sessions 
            FROM remote_sessions 
            WHERE user_id = ? AND start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(start_time)
            ORDER BY date
        ");
        $stmt->execute([$userId]);
        $sessionData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            SELECT DATE(timestamp) as date, COUNT(*) as commands 
            FROM command_log 
            WHERE user_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(timestamp)
            ORDER BY date
        ");
        $stmt->execute([$userId]);
        $commandData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else if ($userRole === 'manager') {
        $stmt = $pdo->query("
            SELECT DATE(rs.start_time) as date, COUNT(*) as sessions 
            FROM remote_sessions rs 
            JOIN users u ON rs.user_id = u.id 
            WHERE u.role = 'operator' AND rs.start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(rs.start_time)
            ORDER BY date
        ");
        $sessionData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("
            SELECT DATE(cl.timestamp) as date, COUNT(*) as commands 
            FROM command_log cl 
            JOIN users u ON cl.user_id = u.id 
            WHERE u.role = 'operator' AND cl.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(cl.timestamp)
            ORDER BY date
        ");
        $commandData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("
            SELECT DATE(start_time) as date, COUNT(*) as sessions 
            FROM remote_sessions 
            WHERE start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(start_time)
            ORDER BY date
        ");
        $sessionData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("
            SELECT DATE(timestamp) as date, COUNT(*) as commands 
            FROM command_log 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(timestamp)
            ORDER BY date
        ");
        $commandData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Prepare chart data
    $last7Days = [];
    for ($i = 6; $i >= 0; $i--) {
        $last7Days[] = date('M j', strtotime("-$i days"));
    }
    
    $sessionCounts = array_fill(0, 7, 0);
    $commandCounts = array_fill(0, 7, 0);
    
    foreach ($sessionData as $row) {
        $dayIndex = 6 - (strtotime('today') - strtotime($row['date'])) / 86400;
        if ($dayIndex >= 0 && $dayIndex < 7) {
            $sessionCounts[$dayIndex] = $row['sessions'];
        }
    }
    
    foreach ($commandData as $row) {
        $dayIndex = 6 - (strtotime('today') - strtotime($row['date'])) / 86400;
        if ($dayIndex >= 0 && $dayIndex < 7) {
            $commandCounts[$dayIndex] = $row['commands'];
        }
    }
    
    $charts['activity'] = [
        'labels' => $last7Days,
        'sessions' => $sessionCounts,
        'commands' => $commandCounts
    ];
    
    // Status chart
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
    
    $charts['status'] = [
        'labels' => array_column($statusData, 'status'),
        'values' => array_column($statusData, 'count')
    ];
    
    echo json_encode(['success' => true, 'stats' => $stats, 'charts' => $charts]);
}

function handleUsers($pdo) {
    $stmt = $pdo->query("SELECT id, username, full_name, email, role, is_active, last_login, created_at FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure is_active is returned as integer
    foreach ($users as &$user) {
        $user['is_active'] = (int)$user['is_active'];
        $user['id'] = (int)$user['id'];
    }
    
    echo json_encode(['success' => true, 'users' => $users]);
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

function handleReports($pdo) {
    $stats = [];
    
    // Total sessions
    $stmt = $pdo->query("SELECT COUNT(*) FROM remote_sessions");
    $stats['total_sessions'] = $stmt->fetchColumn();
    
    // Average duration
    $stmt = $pdo->query("
        SELECT AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_duration 
        FROM remote_sessions 
        WHERE end_time IS NOT NULL
    ");
    $avgDuration = $stmt->fetchColumn();
    $stats['avg_duration'] = $avgDuration ? round($avgDuration) . 'm' : '0m';
    
    // Top command
    $stmt = $pdo->query("
        SELECT SUBSTRING_INDEX(command, ' ', 1) as base_command, COUNT(*) as count 
        FROM command_log 
        GROUP BY base_command 
        ORDER BY count DESC 
        LIMIT 1
    ");
    $topCommand = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['top_command'] = $topCommand ? $topCommand['base_command'] : '-';
    
    // Completion rate
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM command_log WHERE status = 'completed') * 100.0 / 
            (SELECT COUNT(*) FROM command_log) as completion_rate
    ");
    $stats['completion_rate'] = round($stmt->fetchColumn());
    
    // Chart data
    $charts = [];
    
    // Command usage
    $stmt = $pdo->query("
        SELECT SUBSTRING_INDEX(command, ' ', 1) as base_command, COUNT(*) as count 
        FROM command_log 
        GROUP BY base_command 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $commandUsage = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $charts['command_usage'] = [
        'labels' => array_column($commandUsage, 'base_command'),
        'values' => array_column($commandUsage, 'count')
    ];
    
    // Duration distribution
    $stmt = $pdo->query("
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(MINUTE, start_time, end_time) < 5 THEN '0-5 min'
                WHEN TIMESTAMPDIFF(MINUTE, start_time, end_time) < 15 THEN '5-15 min'
                WHEN TIMESTAMPDIFF(MINUTE, start_time, end_time) < 30 THEN '15-30 min'
                WHEN TIMESTAMPDIFF(MINUTE, start_time, end_time) < 60 THEN '30-60 min'
                ELSE '60+ min'
            END as duration_range,
            COUNT(*) as count
        FROM remote_sessions 
        WHERE end_time IS NOT NULL
        GROUP BY duration_range
        ORDER BY 
            CASE duration_range
                WHEN '0-5 min' THEN 1
                WHEN '5-15 min' THEN 2
                WHEN '15-30 min' THEN 3
                WHEN '30-60 min' THEN 4
                WHEN '60+ min' THEN 5
            END
    ");
    $durationData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $charts['duration'] = [
        'labels' => array_column($durationData, 'duration_range'),
        'values' => array_column($durationData, 'count')
    ];
    
    echo json_encode(['success' => true, 'stats' => $stats, 'charts' => $charts]);
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
    
    echo json_encode(['success' => true, 'logs' => $logs]);
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

function handleSaveUser($pdo, $input) {
    $isUpdate = $input['action'] === 'update_user';
    $userId = $input['user_id'] ?? null;
    $username = $input['username'];
    $fullName = $input['full_name'];
    $email = $input['email'];
    $role = $input['role'];
    $isActive = $input['is_active'];
    $password = $input['password'] ?? '';
    
    if ($isUpdate && $userId) {
        // Update user
        if (!empty($password)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, full_name = ?, email = ?, role = ?, is_active = ?, password_hash = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$username, $fullName, $email, $role, $isActive, $passwordHash, $userId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, full_name = ?, email = ?, role = ?, is_active = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$username, $fullName, $email, $role, $isActive, $userId]);
        }
    } else {
        // Create user
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Password is required for new users']);
            return;
        }
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, full_name, email, role, is_active, password_hash, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$username, $fullName, $email, $role, $isActive, $passwordHash]);
    }
    
    echo json_encode(['success' => true]);
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
    $newPassword = 'temp' . substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 8);
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$passwordHash, $userId]);
    
    echo json_encode(['success' => true, 'new_password' => $newPassword]);
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
?>