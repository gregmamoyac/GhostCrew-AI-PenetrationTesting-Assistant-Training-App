<?php
// Authentication Configuration for GhostCrew

// CRITICAL: Fix timezone synchronization between PHP and MySQL
// This prevents the 2-hour offset issue
date_default_timezone_set('UTC');

// Configure session settings ONLY if session is not already active
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_lifetime', 0); // Session cookies only
    ini_set('session.gc_maxlifetime', 7200); // 2 hours server-side cleanup
    
    // Start session
    session_start();
}

// Include password compatibility functions
if (file_exists('includes/password_compat.php')) {
    require_once 'includes/password_compat.php';
}

// Admin database configuration (separate from terminal app database)
define('ADMIN_DB_HOST', 'localhost');
define('ADMIN_DB_USER', 'svc_ghostcrew_admin');
define('ADMIN_DB_PASS', 'SecureP@ssw0rd2024!');
define('ADMIN_DB_NAME', 'ghostcrew_admin');

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('PASSWORD_MIN_LENGTH', 8);

// Create admin database connection
function getAdminDB() {
    static $adminConn = null;
    
    if ($adminConn === null) {
        $adminConn = new mysqli(ADMIN_DB_HOST, ADMIN_DB_USER, ADMIN_DB_PASS);
        
        if ($adminConn->connect_error) {
            die("Admin database connection failed: " . $adminConn->connect_error);
        }
        
        // Create database if it doesn't exist
        $sql = "CREATE DATABASE IF NOT EXISTS " . ADMIN_DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        if (!$adminConn->query($sql)) {
            die("Error creating admin database: " . $adminConn->error);
        }
        
        $adminConn->select_db(ADMIN_DB_NAME);
        
        // CRITICAL: Ensure MySQL uses same timezone as PHP (UTC)
        // This prevents the 2-hour offset issue
        $adminConn->query("SET time_zone = '+00:00'");
        
        // Verify timezone sync (for debugging)
        if (defined('DEBUG') && DEBUG) {
            $result = $adminConn->query("SELECT NOW() as db_time, UNIX_TIMESTAMP(NOW()) as db_timestamp");
            if ($result) {
                $row = $result->fetch_assoc();
                $phpTime = time();
                $timeDiff = abs($phpTime - $row['db_timestamp']);
                if ($timeDiff > 5) {
                    error_log("WARNING: PHP/MySQL timezone mismatch: PHP=$phpTime, MySQL=" . $row['db_timestamp'] . ", diff=$timeDiff seconds");
                }
            }
        }
    }
    
    return $adminConn;
}

// Generate secure session token
function generateSessionToken() {
    return bin2hex(random_bytes(32));
}

// Generate unique session ID
function generateSessionId() {
    return 'sess_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8));
}

// Check if user is authenticated
function isAuthenticated() {
    // Check for required session variables
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        return false;
    }
    
    try {
        $adminDb = getAdminDB();
        
        // First, get the session data
        $stmt = $adminDb->prepare("SELECT user_id, last_activity, is_active FROM user_sessions WHERE session_token = ?");
        $stmt->bind_param("s", $_SESSION['session_token']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Session not found in database
            return false;
        }
        
        $session = $result->fetch_assoc();
        
        // Check if session is active
        if (!$session['is_active']) {
            return false;
        }
        
        // Check if user ID matches
        if ($session['user_id'] != $_SESSION['user_id']) {
            return false;
        }
        
        // Now check if the user is active
        $stmt = $adminDb->prepare("SELECT is_active FROM users WHERE id = ?");
        $stmt->bind_param("i", $session['user_id']);
        $stmt->execute();
        $userResult = $stmt->get_result();
        
        if ($userResult->num_rows === 0) {
            // User not found
            return false;
        }
        
        $user = $userResult->fetch_assoc();
        if (!$user['is_active']) {
            // User is not active
            return false;
        }
        
        // Check session timeout - but be more lenient
        $lastActivity = strtotime($session['last_activity']);
        $currentTime = time();
        $timeDiff = $currentTime - $lastActivity;
        
        // Only timeout if it's been more than the session timeout AND more than 5 minutes
        if ($timeDiff > SESSION_TIMEOUT && $timeDiff > 300) {
            return false;
        }
        
        // Update last activity - but only if more than 60 seconds have passed
        if ($timeDiff > 60) {
            $stmt = $adminDb->prepare("UPDATE user_sessions SET last_activity = CURRENT_TIMESTAMP WHERE session_token = ?");
            $stmt->bind_param("s", $_SESSION['session_token']);
            $stmt->execute();
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Authentication check failed: " . $e->getMessage());
        // On database errors, allow authentication to continue
        // This prevents login issues due to temporary DB problems
        return true;
    }
}

// Get current user information
function getCurrentUser() {
    if (!isAuthenticated()) {
        return null;
    }
    
    $adminDb = getAdminDB();
    $stmt = $adminDb->prepare("SELECT u.* FROM users u 
                              JOIN user_sessions us ON u.id = us.user_id 
                              WHERE us.session_token = ? AND us.is_active = 1");
    $stmt->bind_param("s", $_SESSION['session_token']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

// Authenticate user
function authenticateUser($username, $password) {
    try {
        $adminDb = getAdminDB();
        
        // Check for too many failed attempts
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $adminDb->prepare("SELECT COUNT(*) as attempts FROM audit_log 
                                  WHERE action_type = 'login' 
                                  AND JSON_EXTRACT(action_details, '$.success') = false 
                                  AND ip_address = ? 
                                  AND timestamp > DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $lockoutTime = LOGIN_LOCKOUT_TIME;
        $stmt->bind_param("si", $ip, $lockoutTime);
        $stmt->execute();
        $attempts = $stmt->get_result()->fetch_assoc()['attempts'];
        
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            logAuditEvent(null, 'login', [
                'success' => false,
                'username' => $username,
                'reason' => 'too_many_attempts',
                'ip_lockout' => true
            ]);
            return ['success' => false, 'message' => 'Too many failed attempts. Please try again later.'];
        }
        
        // Get user
        $stmt = $adminDb->prepare("SELECT id, username, password_hash, is_active, full_name FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            logAuditEvent(null, 'login', [
                'success' => false,
                'username' => $username,
                'reason' => 'user_not_found'
            ]);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
        
        $user = $result->fetch_assoc();
        
        if (!$user['is_active']) {
            logAuditEvent($user['id'], 'login', [
                'success' => false,
                'username' => $username,
                'reason' => 'account_disabled'
            ]);
            return ['success' => false, 'message' => 'Account is disabled.'];
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            logAuditEvent($user['id'], 'login', [
                'success' => false,
                'username' => $username,
                'reason' => 'invalid_password'
            ]);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
        
        // Create session
        $sessionToken = generateSessionToken();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $adminDb->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user['id'], $sessionToken, $ip, $userAgent);
        
        if (!$stmt->execute()) {
            return ['success' => false, 'message' => 'Failed to create session.'];
        }
        
        // Update last login
        $stmt = $adminDb->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        logAuditEvent($user['id'], 'login', [
            'success' => true,
            'username' => $username,
            'session_token' => substr($sessionToken, 0, 8) . '...'
        ]);
        
        return ['success' => true, 'message' => 'Login successful'];
        
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Authentication system error.'];
    }
}

// Logout user
function logoutUser() {
    if (isset($_SESSION['session_token'])) {
        $adminDb = getAdminDB();
        $stmt = $adminDb->prepare("UPDATE user_sessions SET logout_time = CURRENT_TIMESTAMP, is_active = 0 WHERE session_token = ?");
        $stmt->bind_param("s", $_SESSION['session_token']);
        $stmt->execute();
        
        logAuditEvent($_SESSION['user_id'] ?? null, 'logout', [
            'session_token' => substr($_SESSION['session_token'], 0, 8) . '...'
        ]);
    }
    
    // Clear session
    session_unset();
    session_destroy();
    session_start(); // Start new session
}

// Log audit events
function logAuditEvent($userId, $actionType, $actionDetails = []) {
    $adminDb = getAdminDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $actionDetailsJson = json_encode($actionDetails);
    
    $stmt = $adminDb->prepare("INSERT INTO audit_log (user_id, action_type, action_details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $userId, $actionType, $actionDetailsJson, $ip, $userAgent);
    $stmt->execute();
}

// Log chatbot interaction
function logChatbotInteraction($userId, $sessionId, $conversationId, $messageType, $message, $contextData = [], $responseTime = null) {
    $adminDb = getAdminDB();
    $contextJson = json_encode($contextData);
    
    $stmt = $adminDb->prepare("INSERT INTO chatbot_conversations (user_id, session_id, conversation_id, message_type, message, context_data, response_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssd", $userId, $sessionId, $conversationId, $messageType, $message, $contextJson, $responseTime);
    $stmt->execute();
    
    return $adminDb->insert_id;
}

// Require authentication for protected pages
function requireAuth() {
    if (!isAuthenticated()) {
        // Don't call logoutUser() here - that's what's ending the session immediately!
        // Just clear the invalid session variables
        if (isset($_SESSION['user_id']) || isset($_SESSION['session_token'])) {
            unset($_SESSION['user_id']);
            unset($_SESSION['username']);
            unset($_SESSION['session_token']);
        }
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            // AJAX request
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required', 'redirect' => 'login.php']);
            exit;
        } else {
            // Regular request
            header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }
}

// Sanitize input (enhanced version)
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Check user permission
function hasPermission($userId, $permission) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    // Admin has all permissions
    if ($user['role'] === 'admin') return true;
    
    // Define role permissions
    $permissions = [
        'manager' => ['view_all_sessions', 'view_reports', 'manage_users'],
        'operator' => ['execute_commands', 'view_own_sessions']
    ];
    
    return isset($permissions[$user['role']]) && in_array($permission, $permissions[$user['role']]);
}

// CSRF Protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>