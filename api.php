<?php
// api.php - central router for API actions
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);

require_once 'config.php';
require_once 'auth_config.php';
require_once 'chatbot_engine.php';

header('Content-Type: application/json');

$action = isset($_REQUEST['action']) ? sanitize($_REQUEST['action']) : '';

$unauthenticated = ['register_host', 'ping_host', 'get_command', 'submit_result'];
if (!in_array($action, $unauthenticated) && !isset($_REQUEST['internal_call'])) {
    requireAuth();
}

$routes = [
    'register_host'          => 'hosts.php',
    'ping_host'              => 'hosts.php',
    'get_hosts'              => 'hosts.php',
    'mark_host_disconnected' => 'hosts.php',

    'ping_session'           => 'sessions.php',
    'get_setup_command'      => 'sessions.php',
    'start_session'          => 'sessions.php',
    'end_session'            => 'sessions.php',
    'get_historical_sessions'=> 'sessions.php',
    'get_session_history'    => 'sessions.php',
    'get_system_info'        => 'sessions.php',
    'log_terminal_clear'     => 'sessions.php',

    'send_command'           => 'commands.php',
    'get_command'            => 'commands.php',
    'submit_result'          => 'commands.php',
    'get_command_history'    => 'commands.php',

    'chat_message'           => 'chat.php',
    'get_chat_history'       => 'chat.php',
    'execute_suggested_command' => 'chat.php',
    'rate_chat_message'      => 'chat.php',
    'get_command_suggestions'=> 'chat.php',
    'mark_suggestion_used'   => 'chat.php',
];

if (isset($routes[$action])) {
    require __DIR__ . '/api/' . $routes[$action];
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
