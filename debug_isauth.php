<?php
/**
 * Debug version of isAuthenticated() to see exactly where it fails
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth_config.php';

echo "<h1>Debug isAuthenticated() Function</h1>";

// First, try to login
if (!isset($_SESSION['session_token'])) {
    echo "<p>No active session. <a href='login_flow_test.php'>Run login test first</a></p>";
    exit;
}

echo "<p>Session token found: " . substr($_SESSION['session_token'], 0, 16) . "...</p>";

function debugIsAuthenticated() {
    echo "<h2>Step-by-step authentication check:</h2>";
    
    // Step 1: Check session variables
    echo "<p><strong>Step 1: Check session variables</strong></p>";
    
    if (!isset($_SESSION['user_id'])) {
        echo "<p>❌ FAIL: \$_SESSION['user_id'] not set</p>";
        return false;
    }
    echo "<p>✅ \$_SESSION['user_id'] = " . $_SESSION['user_id'] . "</p>";
    
    if (!isset($_SESSION['session_token'])) {
        echo "<p>❌ FAIL: \$_SESSION['session_token'] not set</p>";
        return false;
    }
    echo "<p>✅ \$_SESSION['session_token'] = " . substr($_SESSION['session_token'], 0, 16) . "...</p>";
    
    // Step 2: Database connection
    echo "<p><strong>Step 2: Database connection</strong></p>";
    try {
        $adminDb = getAdminDB();
        echo "<p>✅ Database connection successful</p>";
    } catch (Exception $e) {
        echo "<p>❌ FAIL: Database connection failed: " . $e->getMessage() . "</p>";
        return false;
    }
    
    // Step 3: Query database
    echo "<p><strong>Step 3: Query database for session</strong></p>";
    try {
        $stmt = $adminDb->prepare("SELECT us.id, us.last_activity, us.user_id, us.is_active, u.is_active as user_active, u.username 
                                  FROM user_sessions us 
                                  JOIN users u ON us.user_id = u.id 
                                  WHERE us.session_token = ?");
        $stmt->bind_param("s", $_SESSION['session_token']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "<p>Query executed successfully</p>";
        echo "<p>Rows returned: " . $result->num_rows . "</p>";
        
        if ($result->num_rows === 0) {
            echo "<p>❌ FAIL: Session not found in database</p>";
            
            // Let's check if the session exists but without the JOIN conditions
            $stmt2 = $adminDb->prepare("SELECT * FROM user_sessions WHERE session_token = ?");
            $stmt2->bind_param("s", $_SESSION['session_token']);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            if ($result2->num_rows > 0) {
                $sessionData = $result2->fetch_assoc();
                echo "<p>Session exists in user_sessions table:</p>";
                echo "<ul>";
                foreach ($sessionData as $key => $value) {
                    echo "<li>$key: $value</li>";
                }
                echo "</ul>";
                
                // Check the user
                $stmt3 = $adminDb->prepare("SELECT * FROM users WHERE id = ?");
                $stmt3->bind_param("i", $sessionData['user_id']);
                $stmt3->execute();
                $result3 = $stmt3->get_result();
                
                if ($result3->num_rows > 0) {
                    $userData = $result3->fetch_assoc();
                    echo "<p>User data:</p>";
                    echo "<ul>";
                    foreach ($userData as $key => $value) {
                        if ($key !== 'password_hash') {
                            echo "<li>$key: $value</li>";
                        }
                    }
                    echo "</ul>";
                } else {
                    echo "<p>❌ User not found in users table</p>";
                }
            } else {
                echo "<p>❌ Session token not found in user_sessions table at all</p>";
            }
            
            return false;
        }
        
        $session = $result->fetch_assoc();
        echo "<p>✅ Session found in database</p>";
        echo "<ul>";
        foreach ($session as $key => $value) {
            echo "<li>$key: $value</li>";
        }
        echo "</ul>";
        
    } catch (Exception $e) {
        echo "<p>❌ FAIL: Database query failed: " . $e->getMessage() . "</p>";
        return false;
    }
    
    // Step 4: Check session active status
    echo "<p><strong>Step 4: Check session active status</strong></p>";
    if (!$session['is_active']) {
        echo "<p>❌ FAIL: Session is not active (is_active = 0)</p>";
        return false;
    }
    echo "<p>✅ Session is active</p>";
    
    // Step 5: Check user active status
    echo "<p><strong>Step 5: Check user active status</strong></p>";
    if (!$session['user_active']) {
        echo "<p>❌ FAIL: User is not active (user.is_active = 0)</p>";
        return false;
    }
    echo "<p>✅ User is active</p>";
    
    // Step 6: Check session timeout
    echo "<p><strong>Step 6: Check session timeout</strong></p>";
    $lastActivity = strtotime($session['last_activity']);
    $currentTime = time();
    $timeDiff = $currentTime - $lastActivity;
    
    echo "<p>Last activity: " . $session['last_activity'] . " (timestamp: $lastActivity)</p>";
    echo "<p>Current time: " . date('Y-m-d H:i:s') . " (timestamp: $currentTime)</p>";
    echo "<p>Time difference: $timeDiff seconds</p>";
    echo "<p>Session timeout: " . SESSION_TIMEOUT . " seconds</p>";
    
    if ($timeDiff > SESSION_TIMEOUT && $timeDiff > 300) {
        echo "<p>❌ FAIL: Session expired (diff: $timeDiff > timeout: " . SESSION_TIMEOUT . " and > 300)</p>";
        return false;
    }
    echo "<p>✅ Session not expired</p>";
    
    // Step 7: Check user ID match
    echo "<p><strong>Step 7: Check user ID match</strong></p>";
    echo "<p>Database user_id: " . $session['user_id'] . "</p>";
    echo "<p>Session user_id: " . $_SESSION['user_id'] . "</p>";
    
    if ($session['user_id'] != $_SESSION['user_id']) {
        echo "<p>❌ FAIL: User ID mismatch</p>";
        return false;
    }
    echo "<p>✅ User ID matches</p>";
    
    // Step 8: Success
    echo "<p><strong>✅ All checks passed - Authentication successful!</strong></p>";
    return true;
}

// Run the debug
$result = debugIsAuthenticated();

echo "<hr>";
echo "<h2>Result: " . ($result ? "SUCCESS" : "FAILURE") . "</h2>";

if (!$result) {
    echo "<p><strong>The authentication is failing at one of the steps above.</strong></p>";
    echo "<p>Check the specific step that failed and fix that issue.</p>";
}

echo "<p><a href='login_flow_test.php'>Run Login Flow Test Again</a></p>";
?>