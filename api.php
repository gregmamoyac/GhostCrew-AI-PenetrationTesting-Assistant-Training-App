<?php
// Enhanced API with authentication and session management
require_once 'config.php';
require_once 'auth_config.php';

// Set header to return JSON
header('Content-Type: application/json');

// Get the action from the request
$action = isset($_REQUEST['action']) ? sanitize($_REQUEST['action']) : '';

// Define which actions don't require authentication (for HTA clients)
$unauthenticatedActions = ['register_host', 'ping_host', 'get_command', 'submit_result'];

// Only require authentication for web-based actions
if (!in_array($action, $unauthenticatedActions) && !isset($_REQUEST['internal_call'])) {
    requireAuth();
}

// Handle different API actions
switch ($action) {
    case 'register_host':
        registerHost();
        break;
        
    case 'ping_host':
        pingHost();
        break;
        
    case 'get_hosts':
        getHosts();
        break;
        
    case 'send_command':
        sendCommand();
        break;
        
    case 'get_command':
        getCommand();
        break;
        
    case 'submit_result':
        submitResult();
        break;
        
    case 'get_system_info':
        getSystemInfo();
        break;
        
    case 'get_command_history':
        getCommandHistory();
        break;
        
    case 'start_session':
        startSession();
        break;
        
    case 'end_session':
        endSession();
        break;
        
    case 'get_historical_sessions':
        getHistoricalSessions();
        break;
        
    case 'get_session_history':
        getSessionHistory();
        break;
        
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

// Function to register a new host
function registerHost() {
    global $conn;
    
    // Get host information from the request
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : uniqid('host_');
    $hostname = isset($_POST['hostname']) ? sanitize($_POST['hostname']) : 'Unknown';
    $ipAddress = isset($_POST['ip_address']) ? sanitize($_POST['ip_address']) : $_SERVER['REMOTE_ADDR'];
    $osInfo = isset($_POST['os_info']) ? sanitize($_POST['os_info']) : 'Unknown';
    
    // Check if the host already exists in the main database
    $stmt = $conn->prepare("SELECT id FROM hosts WHERE host_id = ?");
    $stmt->bind_param("s", $hostId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing host
        $stmt = $conn->prepare("UPDATE hosts SET hostname = ?, ip_address = ?, os_info = ?, connected = 1, last_seen = CURRENT_TIMESTAMP WHERE host_id = ?");
        $stmt->bind_param("ssss", $hostname, $ipAddress, $osInfo, $hostId);
    } else {
        // Insert new host
        $stmt = $conn->prepare("INSERT INTO hosts (host_id, hostname, ip_address, os_info) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $hostId, $hostname, $ipAddress, $osInfo);
    }
    
    if ($stmt->execute()) {
        // Also update/insert in admin database for tracking
        $adminDb = getAdminDB();
        $adminStmt = $adminDb->prepare("INSERT INTO hosts_info (host_id, hostname, ip_address, os_info) 
                                       VALUES (?, ?, ?, ?) 
                                       ON DUPLICATE KEY UPDATE 
                                       hostname = VALUES(hostname), 
                                       ip_address = VALUES(ip_address), 
                                       os_info = VALUES(os_info), 
                                       last_seen = CURRENT_TIMESTAMP,
                                       is_active = 1");
        $adminStmt->bind_param("ssss", $hostId, $hostname, $ipAddress, $osInfo);
        $adminStmt->execute();
        
        echo json_encode(['status' => 'success', 'host_id' => $hostId]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to register host']);
    }
    
    $stmt->close();
}

// Function to ping a host
function pingHost() {
    global $conn;
    
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : '';
    
    if (empty($hostId)) {
        echo json_encode(['status' => 'error', 'message' => 'Host ID is required']);
        return;
    }
    
    // Update host's last seen timestamp
    $stmt = $conn->prepare("UPDATE hosts SET last_seen = CURRENT_TIMESTAMP, connected = 1 WHERE host_id = ?");
    $stmt->bind_param("s", $hostId);
    
    if ($stmt->execute()) {
        // Also update admin database
        $adminDb = getAdminDB();
        $adminStmt = $adminDb->prepare("UPDATE hosts_info SET last_seen = CURRENT_TIMESTAMP, is_active = 1 WHERE host_id = ?");
        $adminStmt->bind_param("s", $hostId);
        $adminStmt->execute();
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update host status']);
    }
    
    $stmt->close();
}

// Function to get all connected hosts
function getHosts() {
    global $conn;
    
    // Only return hosts that have been seen recently (last 5 minutes)
    $sql = "SELECT * FROM hosts WHERE connected = 1 AND last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY last_seen DESC";
    $result = $conn->query($sql);
    
    $hosts = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Calculate seconds since last seen
            $lastSeen = strtotime($row['last_seen']);
            $now = time();
            $secondsSinceLastSeen = $now - $lastSeen;
            
            // Add this information to the host data
            $row['seconds_since_last_seen'] = $secondsSinceLastSeen;
            
            $hosts[] = $row;
        }
    }
    
    echo json_encode(['status' => 'success', 'hosts' => $hosts]);
}

// Function to start a new session
function startSession() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : '';
    
    if (empty($hostId)) {
        echo json_encode(['status' => 'error', 'message' => 'Host ID is required']);
        return;
    }
    
    // Get host information
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM hosts WHERE host_id = ?");
    $stmt->bind_param("s", $hostId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Host not found']);
        return;
    }
    
    $host = $result->fetch_assoc();
    
    // Generate unique session ID
    $sessionId = generateSessionId();
    
    // Create session in admin database
    $adminDb = getAdminDB();
    $stmt = $adminDb->prepare("INSERT INTO remote_sessions (session_id, user_id, host_id, hostname, ip_address, os_info) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sissss", $sessionId, $user['id'], $hostId, $host['hostname'], $host['ip_address'], $host['os_info']);
    
    if ($stmt->execute()) {
        // Log audit event
        logAuditEvent($user['id'], 'session_start', [
            'session_id' => $sessionId,
            'host_id' => $hostId,
            'hostname' => $host['hostname'],
            'ip_address' => $host['ip_address']
        ]);
        
        echo json_encode([
            'status' => 'success', 
            'session_id' => $sessionId,
            'host' => $host
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create session']);
    }
}

// Function to end a session
function endSession() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    
    if (empty($sessionId)) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID is required']);
        return;
    }
    
    // End session in admin database
    $adminDb = getAdminDB();
    $stmt = $adminDb->prepare("UPDATE remote_sessions SET end_time = CURRENT_TIMESTAMP, status = 'terminated' WHERE session_id = ? AND user_id = ?");
    $stmt->bind_param("si", $sessionId, $user['id']);
    
    if ($stmt->execute()) {
        // Log audit event
        logAuditEvent($user['id'], 'session_end', [
            'session_id' => $sessionId,
            'end_type' => 'manual'
        ]);
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to end session']);
    }
}

// Function to send a command to a host with persistent shell
function sendCommand() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : '';
    $command = isset($_POST['command']) ? $_POST['command'] : '';
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    
    if (empty($hostId) || empty($command) || empty($sessionId)) {
        echo json_encode(['status' => 'error', 'message' => 'Host ID, command, and session ID are required']);
        return;
    }
    
    // Store the command in the main database with session context
    global $conn;
    $stmt = $conn->prepare("INSERT INTO command_history (host_id, command, session_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $hostId, $command, $sessionId);
    
    if ($stmt->execute()) {
        $commandId = $stmt->insert_id;
        
        // Log command in admin database
        $adminDb = getAdminDB();
        $adminStmt = $adminDb->prepare("INSERT INTO command_log (session_id, user_id, command, status) VALUES (?, ?, ?, 'pending')");
        $adminStmt->bind_param("sis", $sessionId, $user['id'], $command);
        $adminStmt->execute();
        
        // Update session activity
        $adminStmt = $adminDb->prepare("UPDATE remote_sessions SET last_activity = CURRENT_TIMESTAMP, total_commands = total_commands + 1 WHERE session_id = ?");
        $adminStmt->bind_param("s", $sessionId);
        $adminStmt->execute();
        
        // Log audit event
        logAuditEvent($user['id'], 'command_execute', [
            'session_id' => $sessionId,
            'host_id' => $hostId,
            'command' => $command,
            'command_id' => $commandId
        ]);
        
        echo json_encode(['status' => 'success', 'command_id' => $commandId]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to store command']);
    }
    
    $stmt->close();
}

// Function for a host to get pending commands (modified for persistent shell)
function getCommand() {
    global $conn;
    
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : '';
    
    if (empty($hostId)) {
        echo json_encode(['status' => 'error', 'message' => 'Host ID is required']);
        return;
    }
    
    // Update host's last seen timestamp
    $stmt = $conn->prepare("UPDATE hosts SET last_seen = CURRENT_TIMESTAMP WHERE host_id = ?");
    $stmt->bind_param("s", $hostId);
    $stmt->execute();
    $stmt->close();
    
    // Get the oldest command without output, including session context
    $stmt = $conn->prepare("SELECT id, command, session_id FROM command_history 
                           WHERE host_id = ? AND output IS NULL 
                           ORDER BY timestamp ASC LIMIT 1");
    $stmt->bind_param("s", $hostId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'status' => 'success', 
            'command_id' => $row['id'], 
            'command' => $row['command'],
            'session_id' => $row['session_id']
        ]);
    } else {
        echo json_encode(['status' => 'success', 'command_id' => 0, 'command' => '', 'session_id' => '']);
    }
    
    $stmt->close();
}

// Function for a host to submit command results
function submitResult() {
    global $conn;
    
    $commandId = isset($_POST['command_id']) ? (int)$_POST['command_id'] : 0;
    $output = isset($_POST['output']) ? $_POST['output'] : '';
    $executionTime = isset($_POST['execution_time']) ? (float)$_POST['execution_time'] : null;
    
    if ($commandId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Command ID is required']);
        return;
    }
    
    // Get command details for logging
    $stmt = $conn->prepare("SELECT command, session_id FROM command_history WHERE id = ?");
    $stmt->bind_param("i", $commandId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Command not found']);
        return;
    }
    
    $commandData = $result->fetch_assoc();
    
    // Store the command output in the main database
    $stmt = $conn->prepare("UPDATE command_history SET output = ?, execution_time = ? WHERE id = ?");
    $stmt->bind_param("sdi", $output, $executionTime, $commandId);
    
    if ($stmt->execute()) {
        // Update admin database
        $adminDb = getAdminDB();
        $adminStmt = $adminDb->prepare("UPDATE command_log SET output = ?, execution_time = ?, status = 'completed', response_timestamp = CURRENT_TIMESTAMP WHERE session_id = ? AND command = ?");
        $adminStmt->bind_param("sdss", $output, $executionTime, $commandData['session_id'], $commandData['command']);
        $adminStmt->execute();
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to store command output']);
    }
    
    $stmt->close();
}

// Function to get system information
function getSystemInfo() {
    global $conn;
    
    $hostCount = 0;
    $commandCount = 0;
    $activeSessionCount = 0;
    
    // Get the count of connected hosts
    $sql = "SELECT COUNT(*) as host_count FROM hosts WHERE connected = 1";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $hostCount = $row['host_count'];
    }
    
    // Get the count of commands executed
    $sql = "SELECT COUNT(*) as command_count FROM command_history";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $commandCount = $row['command_count'];
    }
    
    // Get active session count from admin database
    $user = getCurrentUser();
    if ($user) {
        $adminDb = getAdminDB();
        $stmt = $adminDb->prepare("SELECT COUNT(*) as session_count FROM remote_sessions WHERE status = 'active' AND user_id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $activeSessionCount = $row['session_count'];
        }
    }
    
    // Get server information
    $serverInfo = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'],
        'server_name' => $_SERVER['SERVER_NAME'],
        'document_root' => $_SERVER['DOCUMENT_ROOT'],
        'user' => $user ? $user['username'] : 'Unknown'
    ];
    
    echo json_encode([
        'status' => 'success',
        'host_count' => $hostCount,
        'command_count' => $commandCount,
        'active_sessions' => $activeSessionCount,
        'server_info' => $serverInfo
    ]);
}

// Function to get command history for a session
function getCommandHistory() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
    
    if (empty($sessionId)) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID is required']);
        return;
    }
    
    // Get the command history for the session
    global $conn;
    $stmt = $conn->prepare("SELECT id, command, output, timestamp, execution_time FROM command_history 
                           WHERE session_id = ? 
                           ORDER BY timestamp DESC LIMIT ?");
    $stmt->bind_param("si", $sessionId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $commands = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $commands[] = $row;
        }
    }
    
    echo json_encode(['status' => 'success', 'commands' => $commands]);
    
    $stmt->close();
}

// Function to get historical sessions for current user
function getHistoricalSessions() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $adminDb = getAdminDB();
    $stmt = $adminDb->prepare("SELECT session_id, host_id, hostname, ip_address, start_time, end_time, status, total_commands 
                              FROM remote_sessions 
                              WHERE user_id = ? AND status != 'active'
                              ORDER BY start_time DESC 
                              LIMIT 100");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sessions = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $sessions[] = $row;
        }
    }
    
    echo json_encode(['status' => 'success', 'sessions' => $sessions]);
}

// Function to get session history (read-only)
function getSessionHistory() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    
    if (empty($sessionId)) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID is required']);
        return;
    }
    
    // Verify user owns this session or has appropriate permissions
    $adminDb = getAdminDB();
    $stmt = $adminDb->prepare("SELECT * FROM remote_sessions WHERE session_id = ? AND (user_id = ? OR ? = 'admin')");
    $stmt->bind_param("sis", $sessionId, $user['id'], $user['role']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Session not found or access denied']);
        return;
    }
    
    $sessionInfo = $result->fetch_assoc();
    
    // Get command history
    $stmt = $adminDb->prepare("SELECT command, output, timestamp, execution_time, status 
                              FROM command_log 
                              WHERE session_id = ? 
                              ORDER BY timestamp ASC");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $commands = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $commands[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 'success', 
        'session' => $sessionInfo,
        'commands' => $commands,
        'readonly' => true
    ]);
}
?>