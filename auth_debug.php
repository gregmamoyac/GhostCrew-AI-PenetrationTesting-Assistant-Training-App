<?php
/**
 * Authentication Debug Script
 * This traces exactly what happens during authentication
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Don't include auth_config.php yet - we'll test step by step
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 7200);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('ADMIN_DB_HOST', 'localhost');
define('ADMIN_DB_USER', 'svc_ghostcrew_admin');
define('ADMIN_DB_PASS', 'SecureP@ssw0rd2024!');
define('ADMIN_DB_NAME', 'ghostcrew_admin');
define('SESSION_TIMEOUT', 3600);

function getAdminDB() {
    static $adminConn = null;
    
    if ($adminConn === null) {
        $adminConn = new mysqli(ADMIN_DB_HOST, ADMIN_DB_USER, ADMIN_DB_PASS, ADMIN_DB_NAME);
        
        if ($adminConn->connect_error) {
            die("Admin database connection failed: " . $adminConn->connect_error);
        }
        
        $adminConn->query("SET time_zone = '+00:00'");
    }
    
    return $adminConn;
}

function debugLog($message) {
    echo "<div style='background: #f0f0f0; padding: 5px; margin: 2px; font-family: monospace;'>";
    echo "[" . date('H:i:s') . "] " . $message;
    echo "</div>";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Authentication Debug</title>
    <style>body { font-family: Arial, sans-serif; margin: 20px; }</style>
</head>
<body>
    <h1>Authentication Debug Trace</h1>
    
    <?php
    debugLog("Starting authentication debug");
    debugLog("Session ID: " . session_id());
    debugLog("Current time: " . date('Y-m-d H:i:s'));
    
    // Test 1: Manual login simulation
    if (isset($_POST['debug_login'])) {
        debugLog("=== MANUAL LOGIN TEST ===");
        
        $username = 'admin';
        $password = 'admin123';
        
        debugLog("Attempting login for: $username");
        
        try {
            $adminDb = getAdminDB();
            debugLog("Database connection established");
            
            // Get user
            $stmt = $adminDb->prepare("SELECT id, username, password_hash, is_active, full_name FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                debugLog("❌ User not found");
                exit;
            }
            
            $user = $result->fetch_assoc();
            debugLog("✅ User found: ID=" . $user['id'] . ", Active=" . $user['is_active']);
            
            // Test password
            if (!password_verify($password, $user['password_hash'])) {
                debugLog("❌ Password verification failed");
                exit;
            }
            
            debugLog("✅ Password verified");
            
            // Create session token
            $sessionToken = bin2hex(random_bytes(32));
            debugLog("Generated session token: " . substr($sessionToken, 0, 16) . "...");
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // Insert session
            $stmt = $adminDb->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $user['id'], $sessionToken, $ip, $userAgent);
            
            if (!$stmt->execute()) {
                debugLog("❌ Failed to create session: " . $stmt->error);
                exit;
            }
            
            debugLog("✅ Session created in database");
            
            // Set PHP session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['session_token'] = $sessionToken;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            debugLog("✅ PHP session variables set");
            debugLog("Session contents: " . json_encode($_SESSION));
            
            // Immediately test authentication
            debugLog("=== IMMEDIATE AUTHENTICATION TEST ===");
            testAuthentication();
            
        } catch (Exception $e) {
            debugLog("❌ Exception: " . $e->getMessage());
        }
    }
    
    // Test 2: Check existing authentication
    if (isset($_SESSION['session_token'])) {
        debugLog("=== EXISTING SESSION CHECK ===");
        debugLog("Found session token: " . substr($_SESSION['session_token'], 0, 16) . "...");
        testAuthentication();
    }
    
    function testAuthentication() {
        debugLog("Testing authentication...");
        
        // Check session variables
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            debugLog("❌ Missing session variables");
            debugLog("user_id present: " . (isset($_SESSION['user_id']) ? 'YES' : 'NO'));
            debugLog("session_token present: " . (isset($_SESSION['session_token']) ? 'YES' : 'NO'));
            return false;
        }
        
        debugLog("✅ Session variables present");
        debugLog("User ID: " . $_SESSION['user_id']);
        debugLog("Session Token: " . substr($_SESSION['session_token'], 0, 16) . "...");
        
        try {
            $adminDb = getAdminDB();
            
            $stmt = $adminDb->prepare("SELECT us.id, us.last_activity, us.user_id, u.is_active, u.username 
                                      FROM user_sessions us 
                                      JOIN users u ON us.user_id = u.id 
                                      WHERE us.session_token = ? AND us.is_active = 1 AND u.is_active = 1");
            $stmt->bind_param("s", $_SESSION['session_token']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                debugLog("❌ Session not found in database or inactive");
                
                // Check if session exists but is inactive
                $stmt2 = $adminDb->prepare("SELECT is_active, logout_time FROM user_sessions WHERE session_token = ?");
                $stmt2->bind_param("s", $_SESSION['session_token']);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                
                if ($result2->num_rows > 0) {
                    $inactiveSession = $result2->fetch_assoc();
                    debugLog("Session found but inactive: is_active=" . $inactiveSession['is_active'] . ", logout_time=" . $inactiveSession['logout_time']);
                } else {
                    debugLog("Session completely missing from database");
                }
                
                return false;
            }
            
            $session = $result->fetch_assoc();
            debugLog("✅ Session found in database");
            debugLog("DB User ID: " . $session['user_id'] . " (PHP: " . $_SESSION['user_id'] . ")");
            debugLog("DB Username: " . $session['username']);
            debugLog("DB Last Activity: " . $session['last_activity']);
            debugLog("DB User Active: " . $session['is_active']);
            
            // Check session timeout
            $lastActivity = strtotime($session['last_activity']);
            $currentTime = time();
            $timeDiff = $currentTime - $lastActivity;
            
            debugLog("Last activity timestamp: " . $lastActivity);
            debugLog("Current timestamp: " . $currentTime);
            debugLog("Time difference: " . $timeDiff . " seconds");
            debugLog("Session timeout setting: " . SESSION_TIMEOUT . " seconds");
            
            if ($timeDiff > SESSION_TIMEOUT) {
                debugLog("❌ Session expired (diff: $timeDiff > timeout: " . SESSION_TIMEOUT . ")");
                
                // Mark as expired
                $stmt3 = $adminDb->prepare("UPDATE user_sessions SET is_active = 0, logout_time = CURRENT_TIMESTAMP WHERE session_token = ?");
                $stmt3->bind_param("s", $_SESSION['session_token']);
                $stmt3->execute();
                debugLog("Session marked as expired in database");
                
                return false;
            }
            
            debugLog("✅ Session not expired");
            
            // Verify user ID match
            if ($session['user_id'] != $_SESSION['user_id']) {
                debugLog("❌ User ID mismatch (DB: " . $session['user_id'] . " vs PHP: " . $_SESSION['user_id'] . ")");
                return false;
            }
            
            debugLog("✅ User ID matches");
            
            // Update last activity (only if more than 30 seconds)
            if ($timeDiff > 30) {
                $stmt4 = $adminDb->prepare("UPDATE user_sessions SET last_activity = CURRENT_TIMESTAMP WHERE session_token = ?");
                $stmt4->bind_param("s", $_SESSION['session_token']);
                $stmt4->execute();
                debugLog("Updated last activity in database");
            }
            
            debugLog("✅ Authentication successful!");
            return true;
            
        } catch (Exception $e) {
            debugLog("❌ Database error during authentication: " . $e->getMessage());
            return false;
        }
    }
    
    // Show current database state
    if (isset($_SESSION['session_token'])) {
        debugLog("=== DATABASE SESSION STATE ===");
        try {
            $adminDb = getAdminDB();
            $stmt = $adminDb->prepare("SELECT us.*, u.username FROM user_sessions us LEFT JOIN users u ON us.user_id = u.id WHERE us.session_token = ?");
            $stmt->bind_param("s", $_SESSION['session_token']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $sessionData = $result->fetch_assoc();
                foreach ($sessionData as $key => $value) {
                    debugLog("$key: $value");
                }
            } else {
                debugLog("No session found in database");
            }
        } catch (Exception $e) {
            debugLog("Error querying database: " . $e->getMessage());
        }
    }
    ?>
    
    <hr>
    <h2>Actions</h2>
    
    <?php if (!isset($_SESSION['session_token'])): ?>
        <form method="POST">
            <button type="submit" name="debug_login">Simulate Login</button>
        </form>
    <?php else: ?>
        <p>Session exists. <a href="auth_debug.php">Refresh to test again</a></p>
        <p><a href="?clear_session=1">Clear Session</a></p>
    <?php endif; ?>
    
    <?php
    if (isset($_GET['clear_session'])) {
        session_unset();
        session_destroy();
        debugLog("Session cleared");
        echo "<script>setTimeout(() => window.location = 'auth_debug.php', 1000);</script>";
    }
    ?>
    
    <p><a href="index.php">Try Main App</a> | <a href="test_login.php">Login Test</a></p>

</body>
</html>