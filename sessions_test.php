<?php
/**
 * Minimal session test to isolate the problem
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configure session (same as auth_config.php)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 7200);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Session Test</h1>";

// Test 1: Basic session functionality
if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 0;
}
$_SESSION['test_counter']++;

echo "<h2>Test 1: Basic Session</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "Counter: " . $_SESSION['test_counter'] . "<br>";
echo "Status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "INACTIVE") . "<br>";

// Test 2: Database connection
echo "<h2>Test 2: Database Connection</h2>";
try {
    // Database configuration (same as auth_config.php)
    define('ADMIN_DB_HOST', 'localhost');
    define('ADMIN_DB_USER', 'svc_ghostcrew_admin');
    define('ADMIN_DB_PASS', 'SecureP@ssw0rd2024!');
    define('ADMIN_DB_NAME', 'ghostcrew_admin');
    
    $adminConn = new mysqli(ADMIN_DB_HOST, ADMIN_DB_USER, ADMIN_DB_PASS, ADMIN_DB_NAME);
    
    if ($adminConn->connect_error) {
        echo "❌ Database connection failed: " . $adminConn->connect_error . "<br>";
    } else {
        echo "✅ Database connection successful<br>";
        
        // Test if users table exists
        $result = $adminConn->query("SHOW TABLES LIKE 'users'");
        if ($result->num_rows > 0) {
            echo "✅ Users table exists<br>";
            
            // Check if admin user exists
            $result = $adminConn->query("SELECT username, password_hash FROM users WHERE username = 'admin'");
            if ($result->num_rows > 0) {
                $admin = $result->fetch_assoc();
                echo "✅ Admin user exists<br>";
                echo "Password hash: " . substr($admin['password_hash'], 0, 30) . "...<br>";
                
                // Test password verification
                if (password_verify('admin123', $admin['password_hash'])) {
                    echo "✅ Password verification works<br>";
                } else {
                    echo "❌ Password verification failed<br>";
                    
                    // Try to create a new hash
                    $newHash = password_hash('admin123', PASSWORD_DEFAULT);
                    echo "New hash would be: " . substr($newHash, 0, 30) . "...<br>";
                    
                    if (password_verify('admin123', $newHash)) {
                        echo "✅ New hash verification works<br>";
                        echo "<strong>SOLUTION: Update admin password hash in database</strong><br>";
                        echo "SQL: UPDATE users SET password_hash = '$newHash' WHERE username = 'admin';<br>";
                    }
                }
            } else {
                echo "❌ Admin user does not exist<br>";
                echo "<strong>SOLUTION: Create admin user</strong><br>";
            }
        } else {
            echo "❌ Users table does not exist<br>";
            echo "<strong>SOLUTION: Run database schema creation</strong><br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Database test failed: " . $e->getMessage() . "<br>";
}

// Test 3: Session file permissions
echo "<h2>Test 3: Session Storage</h2>";
$sessionPath = session_save_path();
if (empty($sessionPath)) {
    $sessionPath = sys_get_temp_dir();
}

echo "Session save path: $sessionPath<br>";
echo "Path exists: " . (is_dir($sessionPath) ? "✅ YES" : "❌ NO") . "<br>";
echo "Path writable: " . (is_writable($sessionPath) ? "✅ YES" : "❌ NO") . "<br>";

// Test 4: Cookie settings
echo "<h2>Test 4: Cookie Settings</h2>";
echo "cookie_httponly: " . (ini_get('session.cookie_httponly') ? "✅ ON" : "❌ OFF") . "<br>";
echo "cookie_secure: " . (ini_get('session.cookie_secure') ? "⚠️ ON (requires HTTPS)" : "✅ OFF") . "<br>";
echo "use_only_cookies: " . (ini_get('session.use_only_cookies') ? "✅ ON" : "❌ OFF") . "<br>";
echo "Using HTTPS: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? "✅ YES" : "❌ NO") . "<br>";

if (ini_get('session.cookie_secure') && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
    echo "<strong>⚠️ WARNING: Secure cookies enabled but not using HTTPS!</strong><br>";
    echo "<strong>SOLUTION: Either enable HTTPS or disable secure cookies</strong><br>";
}

// Test 5: PHP version and functions
echo "<h2>Test 5: PHP Environment</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "password_hash available: " . (function_exists('password_hash') ? "✅ YES" : "❌ NO") . "<br>";
echo "password_verify available: " . (function_exists('password_verify') ? "✅ YES" : "❌ NO") . "<br>";

// Test 6: Simple authentication simulation
echo "<h2>Test 6: Authentication Simulation</h2>";
if (isset($_POST['test_auth'])) {
    $_SESSION['simulated_user'] = 'test_user';
    $_SESSION['simulated_token'] = 'test_token_' . time();
    echo "✅ Simulated login successful<br>";
    echo "Simulated user: " . $_SESSION['simulated_user'] . "<br>";
    echo "Simulated token: " . $_SESSION['simulated_token'] . "<br>";
} else {
    echo '<form method="post"><button name="test_auth" type="submit">Test Authentication</button></form>';
    if (isset($_SESSION['simulated_user'])) {
        echo "Previous simulation still active: " . $_SESSION['simulated_user'] . "<br>";
    }
}

echo "<hr>";
echo "<h2>Quick Actions</h2>";
echo '<a href="session_test.php">Refresh</a> | ';
echo '<a href="debug_session.php">Full Debug</a> | ';
echo '<a href="test_login.php">Login Test</a>';

// Show session contents
if (!empty($_SESSION)) {
    echo "<h2>Current Session Contents</h2>";
    echo "<pre>";
    foreach ($_SESSION as $key => $value) {
        echo htmlspecialchars($key) . " => " . htmlspecialchars(print_r($value, true)) . "\n";
    }
    echo "</pre>";
}
?>