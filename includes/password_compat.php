<?php
/**
 * Password Compatibility Functions for GhostCrew
 * Ensures password_hash() and password_verify() are available
 * 
 * Include this file before using password functions if you're unsure
 * about PHP version compatibility
 */

// Check if password functions are available (PHP 5.5.0+)
if (!function_exists('password_hash') || !function_exists('password_verify')) {
    
    // Check PHP version
    if (version_compare(PHP_VERSION, '5.5.0', '<')) {
        
        // For PHP < 5.5.0, you would need to include password_compat library
        // Download from: https://github.com/ircmaxell/password_compat
        
        // Simple fallback using crypt() - NOT RECOMMENDED FOR PRODUCTION
        if (!function_exists('password_hash')) {
            function password_hash($password, $algo, $options = array()) {
                if ($algo !== PASSWORD_DEFAULT && $algo !== PASSWORD_BCRYPT) {
                    return false;
                }
                
                $cost = isset($options['cost']) ? $options['cost'] : 10;
                $salt = isset($options['salt']) ? $options['salt'] : null;
                
                if ($salt === null) {
                    // Generate random salt
                    $salt = '$2y$' . sprintf('%02d', $cost) . '$';
                    $salt .= substr(str_replace('+', '.', base64_encode(openssl_random_pseudo_bytes(16))), 0, 22);
                }
                
                return crypt($password, $salt);
            }
        }
        
        if (!function_exists('password_verify')) {
            function password_verify($password, $hash) {
                return crypt($password, $hash) === $hash;
            }
        }
        
        if (!function_exists('password_needs_rehash')) {
            function password_needs_rehash($hash, $algo, $options = array()) {
                return false; // Simple implementation
            }
        }
        
        // Define constants
        if (!defined('PASSWORD_DEFAULT')) {
            define('PASSWORD_DEFAULT', 1);
        }
        if (!defined('PASSWORD_BCRYPT')) {
            define('PASSWORD_BCRYPT', 1);
        }
        
        // Log warning
        error_log("WARNING: Using password compatibility fallback. Please upgrade to PHP 5.5.0+ or install password_compat library.");
        
    } else {
        // PHP version should support it, but functions are missing
        die("ERROR: PHP version " . PHP_VERSION . " should support password functions, but they are missing. Please check your PHP installation.");
    }
}

/**
 * Generate a secure password hash
 * 
 * @param string $password The password to hash
 * @param int $cost The algorithmic cost (4-31, default 10)
 * @return string The hashed password
 */
function generateSecureHash($password, $cost = 10) {
    // Validate cost parameter
    if ($cost < 4 || $cost > 31) {
        $cost = 10;
    }
    
    $options = array(
        'cost' => $cost,
    );
    
    return password_hash($password, PASSWORD_DEFAULT, $options);
}

/**
 * Verify a password against a hash
 * 
 * @param string $password The password to verify
 * @param string $hash The hash to verify against
 * @return bool True if password matches hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Check if a hash needs to be rehashed
 * 
 * @param string $hash The hash to check
 * @param int $cost The desired cost
 * @return bool True if rehash is needed
 */
function needsRehash($hash, $cost = 10) {
    $options = array(
        'cost' => $cost,
    );
    
    return password_needs_rehash($hash, PASSWORD_DEFAULT, $options);
}

/**
 * Generate a random password
 * 
 * @param int $length Password length
 * @param bool $includeSymbols Include symbols in password
 * @return string Generated password
 */
function generateRandomPassword($length = 12, $includeSymbols = true) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    
    if ($includeSymbols) {
        $chars .= '!@#$%^&*()_+-=[]{}|;:,.<>?';
    }
    
    $password = '';
    $charLength = strlen($chars);
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $charLength - 1)];
    }
    
    return $password;
}

/**
 * Validate password strength
 * 
 * @param string $password Password to validate
 * @return array Array with 'valid' boolean and 'messages' array
 */
function validatePasswordStrength($password) {
    $result = array(
        'valid' => true,
        'messages' => array()
    );
    
    if (strlen($password) < 8) {
        $result['valid'] = false;
        $result['messages'][] = 'Password must be at least 8 characters long';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $result['valid'] = false;
        $result['messages'][] = 'Password must contain at least one lowercase letter';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $result['valid'] = false;
        $result['messages'][] = 'Password must contain at least one uppercase letter';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $result['valid'] = false;
        $result['messages'][] = 'Password must contain at least one number';
    }
    
    if (strlen($password) > 0 && strlen($password) < 12) {
        $result['messages'][] = 'Consider using a longer password for better security';
    }
    
    return $result;
}

/**
 * Test password functions
 * 
 * @return array Test results
 */
function testPasswordFunctions() {
    $results = array();
    
    // Test basic functionality
    $testPassword = 'TestPassword123!';
    $hash = password_hash($testPassword, PASSWORD_DEFAULT);
    
    $results['hash_generated'] = !empty($hash);
    $results['verification_works'] = password_verify($testPassword, $hash);
    $results['wrong_password_fails'] = !password_verify('WrongPassword', $hash);
    $results['functions_available'] = function_exists('password_hash') && function_exists('password_verify');
    $results['php_version'] = PHP_VERSION;
    $results['hash_example'] = $hash;
    
    return $results;
}

// Auto-test on inclusion (only if not in production)
if (defined('DEBUG') && DEBUG === true) {
    $testResults = testPasswordFunctions();
    if (!$testResults['functions_available'] || !$testResults['verification_works']) {
        error_log("WARNING: Password function tests failed. Results: " . json_encode($testResults));
    }
}
?>