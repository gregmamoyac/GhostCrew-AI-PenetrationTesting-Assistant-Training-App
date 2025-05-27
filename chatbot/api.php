<?php
// Chatbot API for GhostCrew
require_once '../auth_config.php';

// Require authentication
requireAuth();

// Set header to return JSON
header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get request data
$message = $_POST['message'] ?? '';
$sessionId = $_POST['session_id'] ?? null;
$conversationId = $_POST['conversation_id'] ?? 'conv_' . uniqid();

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

// Log user message
$contextData = [
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
    'current_session' => $sessionId,
    'timestamp' => date('Y-m-d H:i:s')
];

logChatbotInteraction($user['id'], $sessionId, $conversationId, 'user', $message, $contextData);

// Generate bot response (simple rule-based for now)
$startTime = microtime(true);
$response = generateBotResponse($message, $user, $sessionId);
$responseTime = microtime(true) - $startTime;

// Log bot response
logChatbotInteraction($user['id'], $sessionId, $conversationId, 'bot', $response, [], $responseTime);

// Log audit event
logAuditEvent($user['id'], 'chat_message', [
    'conversation_id' => $conversationId,
    'session_id' => $sessionId,
    'message_length' => strlen($message),
    'response_time' => $responseTime
]);

echo json_encode([
    'success' => true,
    'response' => $response,
    'conversation_id' => $conversationId,
    'response_time' => round($responseTime, 3)
]);

// Simple bot response generator
function generateBotResponse($message, $user, $sessionId) {
    $message = strtolower(trim($message));
    
    // Command help responses
    if (strpos($message, 'help') !== false || strpos($message, 'command') !== false) {
        return generateCommandHelp($message);
    }
    
    // Security and system info
    if (strpos($message, 'security') !== false || strpos($message, 'safe') !== false) {
        return "Security is our top priority! All commands and sessions are logged for audit purposes. Remember:\n\n" .
               "• Never run commands you don't understand\n" .
               "• Avoid running destructive commands without backups\n" .
               "• All activity is monitored and recorded\n" .
               "• Follow your organization's security policies";
    }
    
    // Session information
    if (strpos($message, 'session') !== false && $sessionId) {
        return "You're currently in session: " . substr($sessionId, 0, 16) . "...\n\n" .
               "Session features:\n" .
               "• Persistent shell environment\n" .
               "• Command history tracking\n" .
               "• Directory state maintained\n" .
               "• Environment variables preserved";
    }
    
    // Troubleshooting help
    if (strpos($message, 'not working') !== false || strpos($message, 'error') !== false || strpos($message, 'problem') !== false) {
        return "I can help troubleshoot! Common issues:\n\n" .
               "🔧 **Connection Problems:**\n" .
               "• Check if the HTA file is running on the target machine\n" .
               "• Verify network connectivity between host and server\n" .
               "• Ensure Windows allows HTA execution\n\n" .
               "🔧 **Command Issues:**\n" .
               "• Commands execute from the current directory context\n" .
               "• Use 'cd' to change directories, then run commands\n" .
               "• Long-running commands may appear frozen but are executing\n\n" .
               "🔧 **Session Issues:**\n" .
               "• Each new connection creates a fresh session\n" .
               "• Closed sessions become read-only historical records\n" .
               "• Session timeout occurs after inactivity";
    }
    
    // Windows command help
    if (strpos($message, 'windows') !== false || strpos($message, 'cmd') !== false) {
        return generateWindowsCommandHelp();
    }
    
    // PowerShell help
    if (strpos($message, 'powershell') !== false || strpos($message, 'ps1') !== false) {
        return "**PowerShell Commands:**\n\n" .
               "• `powershell Get-Process` - List running processes\n" .
               "• `powershell Get-Service` - List services\n" .
               "• `powershell Get-EventLog System -Newest 10` - Recent system events\n" .
               "• `powershell Get-WmiObject Win32_ComputerSystem` - System info\n" .
               "• `powershell Get-NetIPAddress` - Network configuration\n\n" .
               "💡 Tip: Prefix PowerShell commands with 'powershell' to execute them directly!";
    }
    
    // Network commands
    if (strpos($message, 'network') !== false || strpos($message, 'ip') !== false) {
        return "**Network Commands:**\n\n" .
               "• `ipconfig` - Show IP configuration\n" .
               "• `ipconfig /all` - Detailed network info\n" .
               "• `ping google.com` - Test connectivity\n" .
               "• `netstat -an` - Show network connections\n" .
               "• `arp -a` - Show ARP table\n" .
               "• `nslookup domain.com` - DNS lookup\n" .
               "• `route print` - Show routing table";
    }
    
    // File system commands
    if (strpos($message, 'file') !== false || strpos($message, 'directory') !== false || strpos($message, 'folder') !== false) {
        return "**File System Commands:**\n\n" .
               "• `dir` - List directory contents\n" .
               "• `cd path` - Change directory\n" .
               "• `mkdir folder` - Create directory\n" .
               "• `copy source dest` - Copy files\n" .
               "• `move source dest` - Move/rename files\n" .
               "• `del filename` - Delete file\n" .
               "• `type filename` - Display file contents\n" .
               "• `attrib filename` - Show/modify file attributes";
    }
    
    // System information
    if (strpos($message, 'system') !== false || strpos($message, 'info') !== false) {
        return "**System Information Commands:**\n\n" .
               "• `systeminfo` - Detailed system information\n" .
               "• `tasklist` - List running processes\n" .
               "• `taskkill /PID 1234` - Kill process by PID\n" .
               "• `services.msc` - Open services manager\n" .
               "• `msconfig` - System configuration\n" .
               "• `dxdiag` - DirectX diagnostics\n" .
               "• `wmic computersystem get model,manufacturer` - Hardware info";
    }
    
    // Greeting responses
    if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening)/', $message)) {
        $greetings = [
            "Hello " . $user['full_name'] . "! I'm here to help you with commands and troubleshooting. What can I assist you with?",
            "Hi there! Ready to help you navigate the terminal. What would you like to know?",
            "Greetings! I'm your GhostCrew assistant. Ask me about commands, troubleshooting, or system administration.",
            "Hello! I can help with Windows commands, PowerShell, networking, and troubleshooting. What do you need?"
        ];
        return $greetings[array_rand($greetings)];
    }
    
    // Thank you responses
    if (strpos($message, 'thank') !== false || strpos($message, 'thanks') !== false) {
        $responses = [
            "You're welcome! Feel free to ask if you need more help.",
            "Happy to help! Let me know if you have other questions.",
            "Glad I could assist! I'm here whenever you need support.",
            "No problem at all! Keep me posted if you run into any issues."
        ];
        return $responses[array_rand($responses)];
    }
    
    // Default response with suggestions
    return "I'm here to help! I can assist with:\n\n" .
           "🔧 **Troubleshooting** - Connection issues, command problems\n" .
           "💻 **Windows Commands** - CMD, PowerShell, system administration\n" .
           "🌐 **Network Commands** - IP configuration, connectivity testing\n" .
           "📁 **File Operations** - Directory navigation, file management\n" .
           "🔒 **Security Guidance** - Best practices and safety tips\n\n" .
           "Try asking me about any of these topics, or type specific commands you need help with!";
}

function generateCommandHelp($message) {
    if (strpos($message, 'basic') !== false || strpos($message, 'beginner') !== false) {
        return "**Basic Commands for Beginners:**\n\n" .
               "• `dir` - List files and folders\n" .
               "• `cd foldername` - Enter a folder\n" .
               "• `cd ..` - Go back one folder\n" .
               "• `cd \\` - Go to root directory\n" .
               "• `pwd` or `cd` - Show current location\n" .
               "• `cls` - Clear the screen\n" .
               "• `exit` - Close command prompt\n" .
               "• `help` - Show available commands\n\n" .
               "💡 **Tip:** Commands are not case-sensitive in Windows!";
    }
    
    return "**Essential Commands:**\n\n" .
           "**Navigation & Files:**\n" .
           "• `dir` - List directory contents\n" .
           "• `cd path` - Change directory\n" .
           "• `mkdir name` - Create directory\n" .
           "• `copy file dest` - Copy files\n\n" .
           "**System Info:**\n" .
           "• `systeminfo` - System details\n" .
           "• `tasklist` - Running processes\n" .
           "• `ipconfig` - Network info\n\n" .
           "**Need more specific help?** Ask about:\n" .
           "• Windows commands\n• PowerShell\n• Network commands\n• File operations";
}

function generateWindowsCommandHelp() {
    return "**Common Windows Commands:**\n\n" .
           "**File & Directory:**\n" .
           "• `dir [/a] [/s]` - List files (all/subdirs)\n" .
           "• `tree` - Display directory structure\n" .
           "• `xcopy source dest /s` - Copy with subdirectories\n" .
           "• `robocopy source dest /mir` - Mirror directories\n\n" .
           "**System Management:**\n" .
           "• `sfc /scannow` - System file checker\n" .
           "• `chkdsk C: /f` - Check disk for errors\n" .
           "• `gpupdate /force` - Update group policy\n" .
           "• `shutdown /r /t 0` - Restart immediately\n\n" .
           "**Process Management:**\n" .
           "• `tasklist /svc` - List services with processes\n" .
           "• `taskkill /f /im notepad.exe` - Force kill by name\n" .
           "• `wmic process list full` - Detailed process info\n\n" .
           "**Registry & Services:**\n" .
           "• `sc query` - List services\n" .
           "• `sc start/stop servicename` - Control services\n" .
           "• `reg query HKLM\\Software` - Query registry";
}
?>