<?php
/**
 * Login Flow Test - Tests the complete login process
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Login Flow Test</h1>";

// Step 1: Clear any existing session
session_start();
session_unset();
session_destroy();
echo "<p>✅ Step 1: Cleared existing session</p>";

// Step 2: Start fresh session
session_start();
echo "<p>✅ Step 2: Started new session (ID: " . session_id() . ")</p>";

// Step 3: Include auth config
require_once 'auth_config.php';
echo "<p>✅ Step 3: Loaded auth configuration</p>";

// Step 4: Test authentication before login
$authBefore = isAuthenticated();
echo "<p>" . ($authBefore ? "❌" : "✅") . " Step 4: Authentication before login: " . ($authBefore ? "TRUE (unexpected)" : "FALSE (expected)") . "</p>";

// Step 5: Perform login
echo "<p>🔄 Step 5: Attempting login...</p>";
$loginResult = authenticateUser('admin', 'admin123');

if ($loginResult['success']) {
    echo "<p>✅ Step 5: Login successful</p>";
    
    // Step 6: Test authentication immediately after login
    $authAfter = isAuthenticated();
    echo "<p>" . ($authAfter ? "✅" : "❌") . " Step 6: Authentication after login: " . ($authAfter ? "TRUE (expected)" : "FALSE (problem!)") . "</p>";
    
    if ($authAfter) {
        // Step 7: Test getCurrentUser()
        $user = getCurrentUser();
        if ($user) {
            echo "<p>✅ Step 7: getCurrentUser() returned: " . $user['username'] . " (" . $user['full_name'] . ")</p>";
        } else {
            echo "<p>❌ Step 7: getCurrentUser() returned null</p>";
        }
        
        // Step 8: Test requireAuth() function
        echo "<p>🔄 Step 8: Testing requireAuth()...</p>";
        try {
            requireAuth();
            echo "<p>✅ Step 8: requireAuth() passed</p>";
        } catch (Exception $e) {
            echo "<p>❌ Step 8: requireAuth() failed: " . $e->getMessage() . "</p>";
        }
        
        // Step 9: Show session data
        echo "<p>✅ Step 9: Session data:</p>";
        echo "<ul>";
        foreach ($_SESSION as $key => $value) {
            if ($key === 'session_token') {
                echo "<li>$key: " . substr($value, 0, 16) . "...</li>";
            } else {
                echo "<li>$key: $value</li>";
            }
        }
        echo "</ul>";
        
        // Step 10: Check database session
        echo "<p>🔄 Step 10: Checking database session...</p>";
        try {
            $adminDb = getAdminDB();
            $stmt = $adminDb->prepare("SELECT us.*, u.username FROM user_sessions us JOIN users u ON us.user_id = u.id WHERE us.session_token = ?");
            $stmt->bind_param("s", $_SESSION['session_token']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $sessionData = $result->fetch_assoc();
                echo "<p>✅ Step 10: Database session found</p>";
                echo "<ul>";
                echo "<li>User: " . $sessionData['username'] . "</li>";
                echo "<li>Login Time: " . $sessionData['login_time'] . "</li>";
                echo "<li>Last Activity: " . $sessionData['last_activity'] . "</li>";
                echo "<li>Is Active: " . ($sessionData['is_active'] ? 'YES' : 'NO') . "</li>";
                echo "<li>Logout Time: " . ($sessionData['logout_time'] ?? 'NULL') . "</li>";
                echo "</ul>";
                
                if (!$sessionData['is_active']) {
                    echo "<p>❌ WARNING: Session is marked as inactive in database!</p>";
                }
                
                if ($sessionData['logout_time']) {
                    echo "<p>❌ WARNING: Session has logout time set: " . $sessionData['logout_time'] . "</p>";
                }
            } else {
                echo "<p>❌ Step 10: No session found in database</p>";
            }
        } catch (Exception $e) {
            echo "<p>❌ Step 10: Database error: " . $e->getMessage() . "</p>";
        }
        
    } else {
        echo "<p>❌ PROBLEM IDENTIFIED: Authentication fails immediately after login</p>";
        echo "<p>This means isAuthenticated() is returning false right after authenticateUser() returns true</p>";
    }
    
} else {
    echo "<p>❌ Step 5: Login failed: " . $loginResult['message'] . "</p>";
}

// Step 11: Test what happens when we redirect (simulate)
echo "<hr><h2>Redirect Simulation Test</h2>";
if (isset($_SESSION['session_token'])) {
    echo "<p>Current session token: " . substr($_SESSION['session_token'], 0, 16) . "...</p>";
    
    // Simulate what index.php does
    echo "<p>🔄 Simulating index.php authentication check...</p>";
    
    // This is what requireAuth() does
    if (!isAuthenticated()) {
        echo "<p>❌ PROBLEM: isAuthenticated() returns false on index.php check</p>";
        echo "<p>This is why you're being redirected back to login</p>";
    } else {
        echo "<p>✅ SUCCESS: isAuthenticated() returns true on index.php check</p>";
        echo "<p>Login should work properly now</p>";
    }
}

echo "<hr>";
echo "<h2>Next Steps</h2>";
echo "<p><a href='login.php'>Try Real Login</a> | <a href='index.php'>Go to Main App</a> | <a href='auth_debug.php'>Detailed Debug</a></p>";
?>