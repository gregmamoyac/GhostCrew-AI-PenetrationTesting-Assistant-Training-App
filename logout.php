<?php
require_once 'auth_config.php';

// Log the logout
if (isset($_SESSION['user_id'])) {
    logAuditEvent($_SESSION['user_id'], 'logout', [
        'manual_logout' => true,
        'session_duration' => time() - ($_SESSION['login_time'] ?? time())
    ]);
}

// Perform logout
logoutUser();

// Redirect to login page
header('Location: login.php?message=logged_out');
exit;
?>