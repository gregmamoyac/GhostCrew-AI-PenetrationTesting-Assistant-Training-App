<?php
/**
 * Database Setup Script for GhostCrew
 * This script creates the database and initial admin user
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$host = 'localhost';
$user = 'svc_ghostcrew_admin';  // Change this
$pass = 'SecureP@ssw0rd2024!';  // Change this
$dbname = 'ghostcrew_admin';

echo "<h1>GhostCrew Database Setup</h1>";

// Step 1: Connect to MySQL server
echo "<h2>Step 1: Connecting to MySQL Server</h2>";
try {
    $conn = new mysqli($host, $user, $pass);
    if ($conn->connect_error) {
        die("❌ Connection failed: " . $conn->connect_error);
    }
    echo "✅ Connected to MySQL server successfully<br>";
} catch (Exception $e) {
    die("❌ MySQL connection error: " . $e->getMessage());
}

// Step 2: Create database
echo "<h2>Step 2: Creating Database</h2>";
$sql = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "✅ Database '$dbname' created or already exists<br>";
} else {
    die("❌ Error creating database: " . $conn->error);
}

// Step 3: Select database
$conn->select_db($dbname);
echo "✅ Selected database '$dbname'<br>";

// Step 4: Create tables
echo "<h2>Step 3: Creating Tables</h2>";

$tables = [
    'users' => "CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        role ENUM('admin', 'manager', 'operator') DEFAULT 'operator',
        is_active TINYINT(1) DEFAULT 1,
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by INT(11),
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )",
    
    'user_sessions' => "CREATE TABLE IF NOT EXISTS user_sessions (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        session_token VARCHAR(128) NOT NULL UNIQUE,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT,
        login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        logout_time TIMESTAMP NULL,
        is_active TINYINT(1) DEFAULT 1,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    'audit_log' => "CREATE TABLE IF NOT EXISTS audit_log (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11),
        action_type ENUM('login', 'logout', 'command_execute', 'session_start', 'session_end', 'chat_message', 'system_access') NOT NULL,
        action_details JSON,
        ip_address VARCHAR(45),
        user_agent TEXT,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user_audit (user_id, timestamp),
        INDEX idx_action_time (action_type, timestamp)
    )"
];

foreach ($tables as $tableName => $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "✅ Table '$tableName' created successfully<br>";
    } else {
        echo "❌ Error creating table '$tableName': " . $conn->error . "<br>";
    }
}

// Step 5: Create admin user
echo "<h2>Step 4: Creating Admin User</h2>";

// Check if admin user already exists
$result = $conn->query("SELECT id FROM users WHERE username = 'admin'");
if ($result->num_rows > 0) {
    echo "⚠️ Admin user already exists<br>";
    
    // Update password to ensure it works
    $password = 'admin123';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
    $stmt->bind_param("s", $passwordHash);
    
    if ($stmt->execute()) {
        echo "✅ Admin password updated<br>";
        echo "Username: admin<br>";
        echo "Password: $password<br>";
        echo "Hash: " . substr($passwordHash, 0, 30) . "...<br>";
    } else {
        echo "❌ Failed to update admin password<br>";
    }
} else {
    // Create new admin user
    $username = 'admin';
    $password = 'admin123';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $fullName = 'System Administrator';
    $email = 'admin@ghostcrew.local';
    $role = 'admin';
    
    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->bind_param("sssss", $username, $passwordHash, $fullName, $email, $role);
    
    if ($stmt->execute()) {
        echo "✅ Admin user created successfully<br>";
        echo "Username: $username<br>";
        echo "Password: $password<br>";
        echo "Email: $email<br>";
        echo "Role: $role<br>";
        echo "Hash: " . substr($passwordHash, 0, 30) . "...<br>";
    } else {
        echo "❌ Failed to create admin user: " . $stmt->error . "<br>";
    }
}

// Step 6: Test password verification
echo "<h2>Step 5: Testing Password Verification</h2>";
$result = $conn->query("SELECT password_hash FROM users WHERE username = 'admin'");
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $testPassword = 'admin123';
    
    if (password_verify($testPassword, $user['password_hash'])) {
        echo "✅ Password verification test PASSED<br>";
    } else {
        echo "❌ Password verification test FAILED<br>";
        echo "This indicates a problem with your PHP password functions<br>";
    }
} else {
    echo "❌ Admin user not found for testing<br>";
}

// Step 7: Display connection string for config files
echo "<h2>Step 6: Configuration</h2>";
echo "Use these settings in your auth_config.php:<br>";
echo "<pre>";
echo "define('ADMIN_DB_HOST', '$host');\n";
echo "define('ADMIN_DB_USER', '$user');\n";
echo "define('ADMIN_DB_PASS', '$pass');\n";
echo "define('ADMIN_DB_NAME', '$dbname');\n";
echo "</pre>";

echo "<h2>Setup Complete!</h2>";
echo "You can now try logging in with:<br>";
echo "Username: <strong>admin</strong><br>";
echo "Password: <strong>admin123</strong><br>";
echo "<br>";
echo '<a href="test_login.php">Test Login</a> | <a href="login.php">Go to Login Page</a>';

$conn->close();
?>