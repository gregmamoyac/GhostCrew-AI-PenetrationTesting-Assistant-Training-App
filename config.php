<?php

/* config.php */


// Enhanced configuration with session support
define('DB_HOST', 'localhost');
define('DB_USER', 'svc_terminal-app');
define('DB_PASS', 'HxjV[pHnF)5riLPh');
define('DB_NAME', 'terminal_app');

// Application configuration
define('APP_URL', 'http://localhost/GhostCrew');
define('LOCAL_LISTENER_URL', 'http://localhost/GhostCrew/local');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if database exists, if not create it
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) !== TRUE) {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db(DB_NAME);

// Set timezone
$conn->query("SET time_zone = '+00:00'");

// Create enhanced tables if they don't exist
$sql = "CREATE TABLE IF NOT EXISTS hosts (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    host_id VARCHAR(50) NOT NULL UNIQUE,
    hostname VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    os_info TEXT,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    connected TINYINT(1) DEFAULT 1,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_sessions INT(11) DEFAULT 0,
    total_commands INT(11) DEFAULT 0,
    INDEX idx_host_status (connected, last_seen),
    INDEX idx_host_id (host_id)
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating hosts table: " . $conn->error);
}

// Enhanced command history table with session support
$sql = "CREATE TABLE IF NOT EXISTS command_history (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    host_id VARCHAR(50) NOT NULL,
    session_id VARCHAR(64) DEFAULT NULL,
    command TEXT NOT NULL,
    output LONGTEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    response_timestamp TIMESTAMP NULL,
    execution_time DECIMAL(10,6),
    status ENUM('pending', 'completed', 'failed', 'timeout') DEFAULT 'pending',
    context_data JSON,
    INDEX idx_host_session (host_id, session_id),
    INDEX idx_session_time (session_id, timestamp),
    INDEX idx_status_time (status, timestamp),
    FOREIGN KEY (host_id) REFERENCES hosts(host_id) ON DELETE CASCADE
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating command_history table: " . $conn->error);
}

// Check if session_id column exists and add it if missing (for backwards compatibility)
$result = $conn->query("SHOW COLUMNS FROM command_history LIKE 'session_id'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE command_history ADD COLUMN session_id VARCHAR(64) DEFAULT NULL AFTER host_id");
    $conn->query("ALTER TABLE command_history ADD INDEX idx_session_id (session_id)");
}

// Shell sessions table for tracking persistent shell state
$sql = "CREATE TABLE IF NOT EXISTS shell_sessions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL UNIQUE,
    host_id VARCHAR(50) NOT NULL,
    current_directory TEXT,
    environment_vars JSON,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_session_active (session_id, is_active),
    FOREIGN KEY (host_id) REFERENCES hosts(host_id) ON DELETE CASCADE
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating shell_sessions table: " . $conn->error);
}

// Clean up old connections - mark hosts as disconnected if not seen in the last 5 minutes
$sql = "UPDATE hosts SET connected = 0 WHERE last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
$conn->query($sql);

// Clean up old shell sessions - mark as inactive if no activity in the last 10 minutes
$sql = "UPDATE shell_sessions SET is_active = 0 WHERE last_activity < DATE_SUB(NOW(), INTERVAL 10 MINUTE)";
$conn->query($sql);

// Clean up old pending commands - mark as timeout if older than 5 minutes
$sql = "UPDATE command_history SET status = 'timeout' WHERE status = 'pending' AND timestamp < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
$conn->query($sql);

// Enhanced sanitize function
function sanitize($input) {
    global $conn;
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return $conn->real_escape_string(htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

// Function to log command execution
function logCommandExecution($hostId, $sessionId, $command, $output = null, $executionTime = null, $status = 'completed') {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE command_history SET output = ?, execution_time = ?, status = ?, response_timestamp = CURRENT_TIMESTAMP WHERE host_id = ? AND session_id = ? AND command = ? AND status = 'pending' ORDER BY timestamp DESC LIMIT 1");
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
        if ($session['environment_vars']) {
            $session['environment_vars'] = json_decode($session['environment_vars'], true);
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
        'total_sessions' => 0,
        'total_commands' => 0,
        'active_sessions' => 0,
        'last_activity' => null
    ];
    
    // Get total sessions from admin database if available
    if (function_exists('getAdminDB')) {
        try {
            $adminDb = getAdminDB();
            $stmt = $adminDb->prepare("SELECT COUNT(*) as count FROM remote_sessions WHERE host_id = ?");
            $stmt->bind_param("s", $hostId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $stats['total_sessions'] = $result->fetch_assoc()['count'];
            }
            
            $stmt = $adminDb->prepare("SELECT COUNT(*) as count FROM remote_sessions WHERE host_id = ? AND status = 'active'");
            $stmt->bind_param("s", $hostId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $stats['active_sessions'] = $result->fetch_assoc()['count'];
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
        $stats['total_commands'] = $result->fetch_assoc()['count'];
    }
    
    // Get last activity
    $stmt = $conn->prepare("SELECT MAX(timestamp) as last_activity FROM command_history WHERE host_id = ?");
    $stmt->bind_param("s", $hostId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stats['last_activity'] = $result->fetch_assoc()['last_activity'];
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
        'commands_deleted' => $deleted,
        'sessions_deleted' => $deletedSessions
    ];
}

// Add error reporting for development (remove in production)
if (defined('DEBUG') && DEBUG) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}
?>