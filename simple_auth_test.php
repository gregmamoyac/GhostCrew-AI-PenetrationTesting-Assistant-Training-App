<?php
/**
 * Simple test to isolate the authentication issue
 */

// Start session BEFORE including auth_config.php
if (session_status() === PHP_SESSION_NONE) {
    // Set session settings before starting
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.gc_maxlifetime', 7200);
    
    session_start();
}

echo "<h1>Simple Authentication Test</h1>";
echo "<p>Session ID: " . session_id() . "</p>";

// Now include config
require_once 'auth_config.php';

// Check if we have an existing session
if (isset($_SESSION['session_token'])) {
    echo "<p>Found existing session token: " . substr($_SESSION['session_token'], 0, 16) . "...</p>";
    
    // Test isAuthenticated directly
    echo "<p>Testing isAuthenticated()...</p>";
    $isAuth = isAuthenticated();
    echo "<p>Result: " . ($isAuth ? "✅ TRUE" : "❌ FALSE") . "</p>";
    
    if (!$isAuth) {
        echo "<p><a href='debug_isauth.php'>Debug why authentication fails</a></p>";
    }
    
} else {
    echo "<p>No existing session. Let's login...</p>";
    
    // Perform login
    $result = authenticateUser('admin', 'admin123');
    
    if ($result['success']) {
        echo "<p>✅ Login successful</p>";
        echo "<p>Session variables set:</p>";
        echo "<ul>";
        foreach ($_SESSION as $key => $value) {
            if ($key === 'session_token') {
                echo "<li>$key: " . substr($value, 0, 16) . "...</li>";
            } else {
                echo "<li>$key: $value</li>";
            }
        }
        echo "</ul>";
        
        // Immediately test authentication
        echo "<p>Testing authentication immediately after login...</p>";
        $isAuth = isAuthenticated();
        echo "<p>Result: " . ($isAuth ? "✅ TRUE" : "❌ FALSE") . "</p>";
        
        if (!$isAuth) {
            echo "<p>❌ PROBLEM: Authentication fails immediately after login</p>";
        } else {
            echo "<p>✅ SUCCESS: Authentication works after login</p>";
        }
        
    } else {
        echo "<p>❌ Login failed: " . $result['message'] . "</p>";
    }
}

echo "<hr>";
echo "<p><a href='simple_auth_test.php'>Refresh</a> | <a href='?clear=1'>Clear Session</a></p>";

if (isset($_GET['clear'])) {
    session_unset();
    session_destroy();
    echo "<script>window.location = 'simple_auth_test.php';</script>";
}
?>