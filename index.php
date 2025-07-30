<?php
/* index.php - Updated with chatbot integration (Fixed) */

require_once 'config.php';
require_once 'auth_config.php';

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
$WindowssetupCommand = 'curl -o "GhostCrew.ps1" "' . APP_URL . '/local/GhostCrew.ps1" && powershell -ExecutionPolicy Bypass -File GhostCrew.ps1 -ApiURL "' . APP_URL . '/api.php" -Token "' . $instanceToken . '"';
$LinuxsetupCommand = 'curl -o "GhostCrew.ps1" "' . APP_URL . '/local/GhostCrew.ps1" && pwsh GhostCrew.ps1 -ApiURL "' . APP_URL . '/api.php" -Token "' . $instanceToken . '"';

// Get AI status for frontend
$aiStatus = getAiStatus();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GhostCrew Operator - Secure Access Terminal</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
// Open the demo controller popup when the page loads
window.addEventListener('load', function() {
    setTimeout(() => {
        const controllerHTML = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Controller</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; margin: 0; }
        .controller-container { max-width: 500px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 24px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
        .header p { margin: 10px 0 0 0; opacity: 0.8; font-size: 14px; }
        .button-grid { display: grid; gap: 15px; }
        .demo-button { background: linear-gradient(45deg, #28a745, #20c997); color: white; border: none; padding: 15px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; text-align: left; transition: all 0.3s ease; box-shadow: 0 4px 8px rgba(0,0,0,0.2); line-height: 1.4; width: 100%; }
        .demo-button:hover { background: linear-gradient(45deg, #218838, #1abc9c); transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0,0,0,0.3); }
        .demo-button:active { transform: translateY(0); box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .demo-button.clicked { background: linear-gradient(45deg, #6c757d, #868e96); animation: flash 0.3s ease; }
        @keyframes flash { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }
        .controls { margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.2); text-align: center; }
        .speed-control { margin: 10px 0; }
        .speed-control label { display: block; margin-bottom: 5px; font-size: 14px; }
        .speed-control input { width: 200px; margin: 0 10px; }
        .utility-buttons { display: flex; gap: 10px; justify-content: center; margin-top: 15px; }
        .utility-button { background: linear-gradient(45deg, #6f42c1, #8e5bca); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 12px; transition: all 0.2s ease; }
        .utility-button:hover { background: linear-gradient(45deg, #5a2e91, #7448a3); }
        .status { text-align: center; margin-top: 15px; font-size: 12px; opacity: 0.8; }
    </style>
</head>
<body>
    <div class="controller-container">
        <div class="header">
            <h1>🛡️ Demo Controller</h1>
            <p>Cybersecurity Education Demo</p>
        </div>
        
        <div class="button-grid">
            <button class="demo-button" onclick="executeCommand(1, this)">1. How do I find my IP address?</button>
            <button class="demo-button" onclick="executeCommand(2, this)">2. How do I find IP addresses on online hosts on my subnet of 192.168.1.1/24?</button>
            <button class="demo-button" onclick="executeCommand(3, this)">3. How do I scan for open ports and their running services on IP 192.168.1.220?</button>
            <button class="demo-button" onclick="executeCommand(4, this)">4. How do I use Metasploit?</button>
            <button class="demo-button" onclick="executeCommand(5, this)">5. How do I find exploits for vsftpd?</button>
            <button class="demo-button" onclick="executeCommand(6, this)">6. How do I choose a target and run an exploit in Metasploit?</button>
            <button class="demo-button" onclick="executeCommand(7, this)">7. Using Metasploit, once I have run an exploit and gained remote access, how do I collect passwords from a Linux host?</button>
            <button class="demo-button" onclick="executeCommand(8, this)">8. I have Linux passwd and shadow files, how do I crack the passwords from these files using John the Ripper?</button>
        </div>
        
        <div class="controls">
            <div class="speed-control">
                <label for="typing-speed">Typing Speed:</label>
                <input type="range" id="typing-speed" min="20" max="200" value="50">
                <span id="speed-value">50ms</span>
            </div>
            
            <div class="utility-buttons">
                <button class="utility-button" onclick="runAllCommands()">Run All</button>
                <button class="utility-button" onclick="clearMainInput()">Clear Input</button>
                <button class="utility-button" onclick="focusMainWindow()">Focus Main</button>
            </div>
            
            <div class="status" id="status">Ready</div>
        </div>
    </div>

    <script>
        let mainWindow = window.opener;
        
        if (!mainWindow) {
            document.getElementById('status').textContent = 'Error: No main window reference';
        }
        
        document.getElementById('typing-speed').addEventListener('input', function() {
            document.getElementById('speed-value').textContent = this.value + 'ms';
        });
        
        const commands = [
            "How do I find my IP address?",
            "How do I find the IP addresses on online hosts on my subnet of 192.168.1.1/24?",
            "How do I scan for open ports and their running services on the IP 192.168.1.220?",
            "How do I use Metasploit?",
            "How do I find exploits for vsftpd?",
            "How do I choose a target and run an exploit in Metasploit?",
            "Using Metasploit, once I have run an exploit and gained remote access, how do I collect passwords from a Linux host?",
            "I have Linux passwd and shadow files, how do I crack the passwords from these files using John the Ripper?"
        ];
        
        // Fallback function to simulate typing when main functions aren't available
        function simulateTyping(text, speed, callback) {
            const $input = mainWindow.$('#chat-input');
            
            if (!$input || $input.length === 0) {
                updateStatus('Error: Chat input not found');
                return;
            }
            
            // Clear existing content and focus
            $input.val('').focus();
            
            let i = 0;
            const typeInterval = setInterval(() => {
                if (i < text.length) {
                    $input.val($input.val() + text.charAt(i));
                    
                    // Trigger input event to notify any listeners
                    $input.trigger('input');
                    
                    i++;
                } else {
                    clearInterval(typeInterval);
                    
                    // Trigger change event when done
                    $input.trigger('change');
                    
                    // Keep focus on the input
                    $input.focus();
                    
                    console.log('Finished typing:', text);
                    
                    if (callback) {
                        callback();
                    }
                }
            }, speed);
        }
        
        function executeCommand(commandIndex, buttonElement) {
            if (!mainWindow || mainWindow.closed) {
                updateStatus('Error: Cannot access main window');
                return;
            }
            
            const command = commands[commandIndex - 1];
            const speed = parseInt(document.getElementById('typing-speed').value);
            
            buttonElement.classList.add('clicked');
            setTimeout(() => buttonElement.classList.remove('clicked'), 300);
            
            updateStatus('Typing command ' + commandIndex + '...');
            
            try {
                // Try different ways to call the function
                if (typeof mainWindow.typeIntoChat === 'function') {
                    mainWindow.typeIntoChat(command, speed);
                    setTimeout(() => {
                        updateStatus('Command ' + commandIndex + ' sent');
                        // Auto-focus the input after typing
                        try {
                            const $input = mainWindow.$('#chat-input');
                            if ($input && $input.length > 0) {
                                $input.focus();
                            }
                        } catch (e) {
                            console.log('Could not focus input:', e);
                        }
                    }, (command.length * speed) + 200);
                } else if (mainWindow.window && typeof mainWindow.window.typeIntoChat === 'function') {
                    mainWindow.window.typeIntoChat(command, speed);
                    setTimeout(() => {
                        updateStatus('Command ' + commandIndex + ' sent');
                        // Auto-focus the input after typing
                        try {
                            const $input = mainWindow.$('#chat-input');
                            if ($input && $input.length > 0) {
                                $input.focus();
                            }
                        } catch (e) {
                            console.log('Could not focus input:', e);
                        }
                    }, (command.length * speed) + 200);
                } else {
                    // Fallback: simulate typing character by character
                    simulateTyping(command, speed, () => {
                        updateStatus('Command ' + commandIndex + ' sent (simulated)');
                    });
                }
                
            } catch (error) {
                updateStatus('Error: ' + error.message);
                console.error('Error executing command:', error);
            }
        }
        
        function runAllCommands() {
            if (!mainWindow || mainWindow.closed) {
                updateStatus('Error: Cannot access main window');
                return;
            }
            
            const speed = parseInt(document.getElementById('typing-speed').value);
            const delayBetween = 2000;
            
            updateStatus('Running all commands...');
            
            try {
                if (typeof mainWindow.typeMultipleStrings === 'function') {
                    mainWindow.typeMultipleStrings(commands, speed, delayBetween);
                    // Calculate total time and focus at the end
                    const totalTime = commands.reduce((total, cmd) => total + (cmd.length * speed), 0) + (commands.length * delayBetween);
                    setTimeout(() => {
                        updateStatus('All commands completed');
                        // Auto-focus the input after all commands
                        try {
                            const $input = mainWindow.$('#chat-input');
                            if ($input && $input.length > 0) {
                                $input.focus();
                            }
                        } catch (e) {
                            console.log('Could not focus input:', e);
                        }
                    }, totalTime);
                } else {
                    // Fallback: run commands one by one with simulated typing
                    let currentIndex = 0;
                    function runNext() {
                        if (currentIndex < commands.length) {
                            const command = commands[currentIndex];
                            simulateTyping(command, speed, () => {
                                currentIndex++;
                                if (currentIndex < commands.length) {
                                    setTimeout(runNext, delayBetween);
                                } else {
                                    updateStatus('All commands completed');
                                }
                            });
                        }
                    }
                    runNext();
                }
                
            } catch (error) {
                updateStatus('Error: ' + error.message);
                console.error('Error running all commands:', error);
            }
        }
        
        function clearMainInput() {
            if (!mainWindow || mainWindow.closed) {
                updateStatus('Error: Cannot access main window');
                return;
            }
            
            try {
                const $input = mainWindow.$('#chat-input');
                if ($input && $input.length > 0) {
                    $input.val('').focus();
                    updateStatus('Input cleared');
                } else {
                    updateStatus('Input element not found');
                }
            } catch (error) {
                updateStatus('Error: ' + error.message);
                console.error('Error clearing input:', error);
            }
        }
        
        function focusMainWindow() {
            if (!mainWindow || mainWindow.closed) {
                updateStatus('Error: Cannot access main window');
                return;
            }
            
            try {
                mainWindow.focus();
                const $input = mainWindow.$('#chat-input');
                if ($input && $input.length > 0) {
                    $input.focus();
                }
                updateStatus('Main window focused');
            } catch (error) {
                updateStatus('Error: ' + error.message);
                console.error('Error focusing main window:', error);
            }
        }
        
        function updateStatus(message) {
            document.getElementById('status').textContent = message;
            setTimeout(() => {
                document.getElementById('status').textContent = 'Ready';
            }, 3000);
        }
        
        setInterval(() => {
            if (!mainWindow || mainWindow.closed) {
                document.getElementById('status').textContent = 'Connection lost - Main window closed';
                document.getElementById('status').style.color = '#ff6b6b';
            }
        }, 2000);
        
        updateStatus('Controller ready');
    <\/script>
</body>
</html>`;

        const blob = new Blob([controllerHTML], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        
        const controllerWindow = window.open(
            url,
            'demoController',
            'width=600,height=700,scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no,status=no'
        );
        
        setTimeout(() => {
            URL.revokeObjectURL(url);
        }, 1000);
        
    }, 500);
});
</script>
</head>
<body>
    <div class="container-fluid">
        <header>
            <h1>GhostCrew Operator</h1>
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
                    <p>To connect a new host, select your operating system and run the provided command in an administrator or root terminal:</p>
                    <div class="connection-os-tabs">
                        <button class="connection-tab-button active" onclick="showTab('windows')">Windows</button>
                        <button class="connection-tab-button" onclick="showTab('linux')">Linux</button>
                    </div>
                    <div id="windows-tab" class="connection-tab-content active">
                        <div class="connection-setup-command">
                            <code id="windows-command-text"><?php echo htmlspecialchars($WindowssetupCommand); ?></code>
                            <button onclick="copyToClipboard('windows-command-text', this)" title="Copy to clipboard">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div id="linux-tab" class="connection-tab-content">
                        <div class="connection-setup-command">
                            <code id="linux-command-text"><?php echo htmlspecialchars($LinuxsetupCommand); ?></code>
                            <button onclick="copyToClipboard('linux-command-text', this)" title="Copy to clipboard">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
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
                                            <p>Use Ghosty the AI Guide on the right for command help and suggestions.</p>
                                            
                                            <?php if ($aiStatus['configured']): ?>
                                                <div class="ai-status-indicator configured">
                                                    <i class="fas fa-robot"></i>
                                                    <span>Ghosty the AI Guide: 
                                                        <?php if ($aiStatus['connected']): ?>
                                                            <span class="status-connected">Connected</span>
                                                        <?php else: ?>
                                                            <span class="status-error">Connection Error</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="terminal-input-container">
                                <div class="prompt" id="terminal-prompt">$</div>
                                <input type="text" id="terminal-input" placeholder="Enter command..." disabled>
                                <span class="execution-status" id="execution-status">Executing...</span>
                                <button id="send-command" disabled>Send</button>
                            </div>
                        </div>
                        
                        <div class="chat-section">
                            <div class="chat-header">
                                <span>
                                    Ghosty the AI Guide
                                    <?php if ($aiStatus['connected']): ?>
                                        <span class="ai-status-badge connected" title="AI Connected">
                                            <i class="fas fa-circle"></i>
                                        </span>
                                    <?php elseif ($aiStatus['configured']): ?>
                                        <span class="ai-status-badge error" title="AI Error: <?php echo htmlspecialchars($aiStatus['message']); ?>">
                                            <i class="fas fa-circle"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="ai-status-badge not-configured" title="AI Not Configured">
                                            <i class="fas fa-circle"></i>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="chat-messages" id="chat-messages">
                                <div class="chat-welcome">
                                    <?php if ($aiStatus['connected']): ?>
                                        Hello! I'm Ghosty the AI Guide.<br>
                                        Ask me about commands or request help with specific tasks!
                                    <?php elseif ($aiStatus['configured']): ?>
                                        Hello! I'm Ghosty the AI Guide.<br>
                                        <small class="text-warning">Note: AI is experiencing connection issues. I'll provide basic assistance.</small>
                                    <?php else: ?>
                                        Hello! I'mGhosty the AI Guide.<br>
                                        <small class="text-muted">Note: Enhanced features require AI configuration.</small>
                                    <?php endif; ?>
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
        const AI_STATUS = <?php echo json_encode($aiStatus); ?>;
        
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
                promptElement.text('$');
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

        // Display AI status in console for debugging
        console.log('AI Status:', AI_STATUS);
    </script>
    <div class="clippy"></div>
    <!-- Enhanced terminal.js will be loaded -->
    <script src="assets/js/enhanced_terminal.js"></script>
</body>
</html>