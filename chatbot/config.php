<?php
// Chatbot Configuration File

// Database configuration (if you want to use database instead of files)
define('DB_HOST', 'localhost');
define('DB_NAME', 'ghostcrew_chatbot');
define('DB_USER', 'root');
define('DB_PASS', '');

// Chatbot settings
define('MAX_MESSAGE_LENGTH', 500);
define('MAX_MESSAGES_PER_SESSION', 50);
define('SESSION_TIMEOUT_HOURS', 24);
define('CLEANUP_OLD_SESSIONS_DAYS', 7);

// Rate limiting (messages per minute per session)
define('RATE_LIMIT_MESSAGES', 30);
define('RATE_LIMIT_WINDOW', 60); // seconds

// Security settings
define('ALLOWED_ORIGINS', ['http://localhost', 'https://localhost']);
define('MAX_SESSION_ID_LENGTH', 50);

// Enable/disable features
define('ENABLE_LOGGING', true);
define('ENABLE_RATE_LIMITING', true);
define('ENABLE_SESSION_PERSISTENCE', true);

// Paths
define('SESSION_DATA_DIR', __DIR__ . '/sessions/');
define('LOG_FILE', __DIR__ . '/logs/chatbot.log');

// Create necessary directories
if (!is_dir(SESSION_DATA_DIR)) {
    mkdir(SESSION_DATA_DIR, 0755, true);
}

if (ENABLE_LOGGING && !is_dir(dirname(LOG_FILE))) {
    mkdir(dirname(LOG_FILE), 0755, true);
}

// Error reporting
if (ENABLE_LOGGING) {
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_FILE);
}

?>