<?php
/**
 * User Management Utility for GhostCrew
 * This script helps create users and manage passwords
 * Run from command line: php user_management.php
 */

require_once '../auth_config.php';

// Command line interface
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

echo "=== GhostCrew User Management Utility ===\n\n";

// Menu
while (true) {
    echo "Select an option:\n";
    echo "1. Create new user\n";
    echo "2. Reset user password\n";
    echo "3. List all users\n";
    echo "4. Deactivate user\n";
    echo "5. Activate user\n";
    echo "6. Generate password hash\n";
    echo "7. Test password verification\n";
    echo "0. Exit\n";
    echo "\nEnter choice: ";
    
    $choice = trim(fgets(STDIN));
    
    switch ($choice) {
        case '1':
            createUser();
            break;
        case '2':
            resetPassword();
            break;
        case '3':
            listUsers();
            break;
        case '4':
            toggleUserStatus(false);
            break;
        case '5':
            toggleUserStatus(true);
            break;
        case '6':
            generatePasswordHash();
            break;
        case '7':
            testPasswordVerification();
            break;
        case '0':
            echo "Goodbye!\n";
            exit(0);
        default:
            echo "Invalid choice. Please try again.\n\n";
    }
}

function createUser() {
    echo "\n=== Create New User ===\n";
    
    echo "Username: ";
    $username = trim(fgets(STDIN));
    
    echo "Full Name: ";
    $fullName = trim(fgets(STDIN));
    
    echo "Email: ";
    $email = trim(fgets(STDIN));
    
    echo "Role (admin/manager/operator): ";
    $role = trim(fgets(STDIN));
    
    if (!in_array($role, ['admin', 'manager', 'operator'])) {
        echo "Invalid role. Must be admin, manager, or operator.\n\n";
        return;
    }
    
    echo "Password: ";
    $password = trim(fgets(STDIN));
    
    if (strlen($password) < 8) {
        echo "Password must be at least 8 characters long.\n\n";
        return;
    }
    
    // Hash the password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $adminDb = getAdminDB();
        $stmt = $adminDb->prepare("INSERT INTO users (username, password_hash, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $passwordHash, $fullName, $email, $role);
        
        if ($stmt->execute()) {
            echo "User '$username' created successfully!\n";
            echo "Password hash: $passwordHash\n\n";
        } else {
            echo "Error creating user: " . $stmt->error . "\n\n";
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo "Database error: " . $e->getMessage() . "\n\n";
    }
}

function resetPassword() {
    echo "\n=== Reset User Password ===\n";
    
    echo "Username: ";
    $username = trim(fgets(STDIN));
    
    echo "New Password: ";
    $password = trim(fgets(STDIN));
    
    if (strlen($password) < 8) {
        echo "Password must be at least 8 characters long.\n\n";
        return;
    }
    
    // Hash the password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $adminDb = getAdminDB();
        $stmt = $adminDb->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE username = ?");
        $stmt->bind_param("ss", $passwordHash, $username);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo "Password reset successfully for user '$username'!\n";
                echo "New password hash: $passwordHash\n\n";
            } else {
                echo "User '$username' not found.\n\n";
            }
        } else {
            echo "Error resetting password: " . $stmt->error . "\n\n";
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo "Database error: " . $e->getMessage() . "\n\n";
    }
}

function listUsers() {
    echo "\n=== User List ===\n";
    
    try {
        $adminDb = getAdminDB();
        $result = $adminDb->query("SELECT id, username, full_name, email, role, is_active, last_login, created_at FROM users ORDER BY created_at DESC");
        
        if ($result->num_rows > 0) {
            printf("%-5s %-15s %-25s %-30s %-10s %-8s %-20s\n", 
                   "ID", "Username", "Full Name", "Email", "Role", "Active", "Last Login");
            echo str_repeat("-", 120) . "\n";
            
            while ($row = $result->fetch_assoc()) {
                printf("%-5d %-15s %-25s %-30s %-10s %-8s %-20s\n",
                       $row['id'],
                       $row['username'],
                       substr($row['full_name'], 0, 24),
                       substr($row['email'], 0, 29),
                       $row['role'],
                       $row['is_active'] ? 'Yes' : 'No',
                       $row['last_login'] ? date('Y-m-d H:i', strtotime($row['last_login'])) : 'Never'
                );
            }
        } else {
            echo "No users found.\n";
        }
        
        echo "\n";
    } catch (Exception $e) {
        echo "Database error: " . $e->getMessage() . "\n\n";
    }
}

function toggleUserStatus($activate) {
    $action = $activate ? 'activate' : 'deactivate';
    echo "\n=== " . ucfirst($action) . " User ===\n";
    
    echo "Username: ";
    $username = trim(fgets(STDIN));
    
    try {
        $adminDb = getAdminDB();
        $stmt = $adminDb->prepare("UPDATE users SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE username = ?");
        $status = $activate ? 1 : 0;
        $stmt->bind_param("is", $status, $username);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo "User '$username' " . ($activate ? 'activated' : 'deactivated') . " successfully!\n\n";
            } else {
                echo "User '$username' not found.\n\n";
            }
        } else {
            echo "Error updating user: " . $stmt->error . "\n\n";
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo "Database error: " . $e->getMessage() . "\n\n";
    }
}

function generatePasswordHash() {
    echo "\n=== Generate Password Hash ===\n";
    
    echo "Password to hash: ";
    $password = trim(fgets(STDIN));
    
    if (empty($password)) {
        echo "Password cannot be empty.\n\n";
        return;
    }
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "Password hash: $hash\n";
    
    // Verify the hash works
    if (password_verify($password, $hash)) {
        echo "✓ Verification test passed!\n\n";
    } else {
        echo "✗ Verification test failed!\n\n";
    }
}

function testPasswordVerification() {
    echo "\n=== Test Password Verification ===\n";
    
    echo "Plain text password: ";
    $password = trim(fgets(STDIN));
    
    echo "Password hash to test against: ";
    $hash = trim(fgets(STDIN));
    
    if (password_verify($password, $hash)) {
        echo "✓ Password verification PASSED!\n\n";
    } else {
        echo "✗ Password verification FAILED!\n\n";
    }
}

function checkPasswordFunctions() {
    echo "\n=== Password Function Check ===\n";
    
    if (function_exists('password_hash')) {
        echo "✓ password_hash() function is available\n";
    } else {
        echo "✗ password_hash() function is NOT available\n";
        echo "  You need PHP 5.5.0 or later, or install the password_compat library\n";
    }
    
    if (function_exists('password_verify')) {
        echo "✓ password_verify() function is available\n";
    } else {
        echo "✗ password_verify() function is NOT available\n";
        echo "  You need PHP 5.5.0 or later, or install the password_compat library\n";
    }
    
    echo "PHP Version: " . PHP_VERSION . "\n\n";
}

// Check password functions on startup
checkPasswordFunctions();
?>