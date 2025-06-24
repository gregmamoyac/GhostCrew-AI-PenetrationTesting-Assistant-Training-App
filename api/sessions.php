<?php
// Session-related API actions
switch ($action) {
    case 'ping_session':
        pingSession();
        break;
    case 'get_setup_command':
        getSetupCommand();
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
    case 'get_system_info':
        getSystemInfo();
        break;
    case 'get_command_history':
        getCommandHistory();
        break;
    case 'log_terminal_clear':
        logTerminalClear();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid session action']);
}

function pingSession() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Session refreshed']);
}

// Function to get setup command with instance token
function getSetupCommand() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $instanceToken = getCurrentInstanceToken();
    if (!$instanceToken) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to generate instance token']);
        return;
    }
    
    $setupUrl = APP_URL . "/local/autoconnect.hta?token=" . urlencode($instanceToken);
    
    echo json_encode([
        'status' => 'success',
        'command' => "mshta \"$setupUrl\"",
        'instance_token' => $instanceToken
    ]);
}

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
    
    // Verify user has access to this host
    $instanceToken = getCurrentInstanceToken();
    $stmt = $GLOBALS['conn']->prepare("SELECT h.* FROM hosts h 
                                      JOIN host_instance_mappings him ON h.host_id = him.host_id 
                                      WHERE h.host_id = ? AND him.instance_token = ? AND him.is_active = 1");
    $stmt->bind_param("ss", $hostId, $instanceToken);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Host not found or access denied']);
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

// Function to update command statistics
function updateCommandStatistics($hostId, $command, $executionTime, $success) {
    global $conn;
    
    $commandBase = strtolower(explode(' ', trim($command))[0]);
    
    $stmt = $conn->prepare("
        INSERT INTO command_statistics (host_id, command_base, execution_count, avg_execution_time, success_rate) 
        VALUES (?, ?, 1, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        execution_count = execution_count + 1,
        avg_execution_time = (avg_execution_time * (execution_count - 1) + ?) / execution_count,
        success_rate = (success_rate * (execution_count - 1) + ?) / execution_count
    ");
    
    $successRate = $success ? 100.0 : 0.0;
    $stmt->bind_param("ssdddd", $hostId, $commandBase, $executionTime, $successRate, $executionTime, $successRate);
    $stmt->execute();
}


function getSystemInfo() {
    global $conn;
    
    $hostCount = 0;
    $commandCount = 0;
    $activeSessionCount = 0;
    
    $user = getCurrentUser();
    $instanceToken = getCurrentInstanceToken();
    
    // Get the count of connected hosts for this user
    if ($instanceToken) {
        $sql = "SELECT COUNT(DISTINCT h.id) as host_count 
                FROM hosts h 
                JOIN host_instance_mappings him ON h.host_id = him.host_id 
                WHERE h.connected = 1 AND him.instance_token = ? AND him.is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $instanceToken);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $hostCount = $result->fetch_assoc()['host_count'];
        }
    }
    
    // Get the count of commands executed by this user
    if ($user) {
        $adminDb = getAdminDB();
        $stmt = $adminDb->prepare("SELECT COUNT(*) as command_count FROM command_log WHERE user_id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $commandCount = $result->fetch_assoc()['command_count'];
        }
        
        // Get active session count
        $stmt = $adminDb->prepare("SELECT COUNT(*) as session_count FROM remote_sessions WHERE status = 'active' AND user_id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $activeSessionCount = $result->fetch_assoc()['session_count'];
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
    $stmt = $conn->prepare("SELECT id, command, output, timestamp, execution_time, working_directory, exit_code FROM command_history 
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


function logTerminalClear() {
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
    
    // Log the clear action in admin database
    $adminDb = getAdminDB();
    $stmt = $adminDb->prepare("INSERT INTO command_log (session_id, user_id, command, output, status) VALUES (?, ?, '--- user cleared terminal ---', 'Terminal display cleared by user', 'completed')");
    $stmt->bind_param("sis", $sessionId, $user['id']);
    
    if ($stmt->execute()) {
        // Log audit event
        logAuditEvent($user['id'], 'system_access', [
            'action' => 'terminal_clear',
            'session_id' => $sessionId
        ]);
        
        echo json_encode(['status' => 'success', 'message' => 'Terminal clear logged']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to log terminal clear']);
    }
}

