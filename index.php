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
            // Create the controller HTML as a string
            const controllerHTML = `
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>GhostCrew Demo Controller</title>
                    <script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; margin: 0; }
                        .controller-container { max-width: 500px; margin: 0 auto; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .header h1 { margin: 0; font-size: 24px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
                        .header p { margin: 10px 0 0 0; opacity: 0.8; font-size: 14px; }
                        .button-grid { display: grid; gap: 15px; }
                        .demo-button { background: linear-gradient(45deg, #28a745, #20c997); color: white; border: none; padding: 15px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; text-align: left; transition: all 0.3s ease; box-shadow: 0 4px 8px rgba(0,0,0,0.2); line-height: 1.4; }
                        .demo-button:hover { background: linear-gradient(45deg, #218838, #1abc9c); transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0,0,0,0.3); }
                        .demo-button:active { transform: translateY(0); box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
                        .demo-button.clicked { background: linear-gradient(45deg, #6c757d, #868e96); animation: flash 0.3s ease; }
                        .terminal-button { background: linear-gradient(45deg, #dc3545, #e91e63); }
                        .terminal-button:hover { background: linear-gradient(45deg, #c82333, #d81b60); }
                        @keyframes flash { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }
                        .controls { margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.2); text-align: center; }
                        .speed-control { margin: 10px 0; }
                        .speed-control label { display: block; margin-bottom: 5px; font-size: 14px; }
                        .speed-control input { width: 200px; margin: 0 10px; }
                        .utility-buttons { display: flex; gap: 10px; justify-content: center; margin-top: 15px; }
                        .utility-button { background: linear-gradient(45deg, #6f42c1, #8e5bca); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 12px; transition: all 0.2s ease; }
                        .utility-button:hover { background: linear-gradient(45deg, #5a2e91, #7448a3); }
                        .status { text-align: center; margin-top: 15px; font-size: 12px; opacity: 0.8; }
                        .section-divider { margin: 20px 0; text-align: center; font-size: 12px; opacity: 0.6; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px; }
                    </style>
                </head>
                <body>
                    <div class="controller-container">
                        <div class="header">
                            <h1>GhostCrew Demo Controller</h1>
                        </div>
                        
                        <div class="button-grid">
                            <button class="demo-button" onclick="executeCommand(1, this)">1. What tools can I use to find remote devices and run exploits?</button>
                            
                            <div class="section-divider">Network Discovery Commands</div>
                            <button class="demo-button terminal-button" onclick="executeTerminalCommand(1, this)">2. nmap -sn 192.168.1.1/24</button>
                            <button class="demo-button terminal-button" onclick="executeTerminalCommand(2, this)">3. nmap -sS -sV -O 192.168.1.220</button>
                            
                            <div class="section-divider">Metasploit Commands</div>
                            <button class="demo-button terminal-button" onclick="executeTerminalCommand(3, this)">4. msfconsole</button>
                            <button class="demo-button" onclick="executeCommand(2, this)">5. How do I determine which exploit to choose in metasploit?</button>
                            <button class="demo-button terminal-button" onclick="executeTerminalCommand(4, this)">6. search vsftp 2.3.4</button>
                            <button class="demo-button terminal-button" onclick="executeTerminalCommand(12, this)">7. use 0</button>
                            <button class="demo-button terminal-button" onclick="executeTerminalCommand(5, this)">8. show options</button>
                            <button class="demo-button terminal-button" onclick="executeTerminalCommand(6, this)">9. set RHOSTS 192.168.1.220</button>
                            <button class="demo-button terminal-button" onclick="executeTerminalCommand(7, this)">10. run</button>
                            
                            <div class="section-divider">Post-Exploitation Commands</div>
                            <button class="demo-button terminal-button" onclick="executeTerminalCommand(8, this)">11. hostname</button>
                            <button class="demo-button terminal-button" onclick="executeTerminalCommand(9, this)">12. whoami</button>
                            <button class="demo-button terminal-button" onclick="executeTerminalCommand(10, this)">13. exit</button>
                            <button class="demo-button terminal-button" onclick="executeTerminalCommand(11, this)">14. exit</button>
                        </div>
                        
                        <div class="controls">
                            <div class="speed-control">
                                <label for="typing-speed">Typing Speed:</label>
                                <input type="range" id="typing-speed" min="50" max="300" value="100">
                                <span id="speed-value">100ms</span>
                            </div>
                            
                            <div class="utility-buttons">
                                <button class="utility-button" onclick="runAllCommands()">Run All Chat</button>
                                <button class="utility-button" onclick="runAllTerminal()">Run All Terminal</button>
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
                        
                        // Chat commands
                        const chatCommands = [
                            "What tools can I use to find remote devices and run exploits?",
                            "How do I determine which exploit to choose in metasploit?"
                        ];
                        
                        // Terminal commands
                        const terminalCommands = [
                            "nmap -sn 192.168.1.1/24",
                            "nmap -sS -sV -O 192.168.1.220", 
                            "msfconsole",
                            "search vsftp 2.3.4",
                            "show options",
                            "set RHOSTS 192.168.1.220",
                            "run",
                            "hostname",
                            "whoami",
                            "exit",
                            "exit",
                            "use 0"
                        ];
                        
                        // New realistic typing function
                        function typeRealistic(text, selector, baseSpeed = 100) {
                            return new Promise((resolve) => {
                                try {
                                    if (!mainWindow || !mainWindow.$ || mainWindow.$('').length === 0) {
                                        // Fallback if jQuery not available
                                        const field = mainWindow.document.querySelector(selector);
                                        if (field) {
                                            field.value = '';
                                            field.focus();
                                            
                                            let i = 0;
                                            function typeChar() {
                                                if (i < text.length) {
                                                    field.value += text.charAt(i);
                                                    field.dispatchEvent(new Event('input', { bubbles: true }));
                                                    
                                                    const variation = Math.random() * 100 - 50;
                                                    const nextDelay = Math.max(baseSpeed + variation, 20);
                                                    
                                                    if (['.', ',', '!', '?', ';'].includes(text.charAt(i))) {
                                                        setTimeout(typeChar, nextDelay + 200);
                                                    } else {
                                                        setTimeout(typeChar, nextDelay);
                                                    }
                                                    i++;
                                                } else {
                                                    field.dispatchEvent(new Event('change', { bubbles: true }));
                                                    field.focus();
                                                    resolve();
                                                }
                                            }
                                            typeChar();
                                        } else {
                                            console.log('Element not found:', selector);
                                            resolve();
                                        }
                                    } else {
                                        // Use jQuery if available
                                        const $field = mainWindow.$(selector);
                                        if ($field.length === 0) {
                                            console.log('Element ' + selector + ' not found');
                                            resolve();
                                            return;
                                        }
                                        
                                        $field.val('').focus();
                                        
                                        let i = 0;
                                        function typeChar() {
                                            if (i < text.length) {
                                                $field.val($field.val() + text.charAt(i));
                                                $field.trigger('input');
                                                
                                                const variation = Math.random() * 100 - 50;
                                                const nextDelay = Math.max(baseSpeed + variation, 20);
                                                
                                                if (['.', ',', '!', '?', ';'].includes(text.charAt(i))) {
                                                    setTimeout(typeChar, nextDelay + 200);
                                                } else {
                                                    setTimeout(typeChar, nextDelay);
                                                }
                                                i++;
                                            } else {
                                                $field.trigger('change');
                                                $field.focus();
                                                console.log('Finished realistic typing into ' + selector + ':', text);
                                                resolve();
                                            }
                                        }
                                        typeChar();
                                    }
                                } catch (error) {
                                    console.error('Error in typeRealistic:', error);
                                    resolve();
                                }
                            });
                        }
                        
                        function executeCommand(commandIndex, buttonElement) {
                            if (!mainWindow) {
                                updateStatus('Error: Cannot access main window');
                                return;
                            }
                            
                            const command = chatCommands[commandIndex - 1];
                            const speed = parseInt(document.getElementById('typing-speed').value);
                            
                            buttonElement.classList.add('clicked');
                            setTimeout(() => buttonElement.classList.remove('clicked'), 300);
                            
                            updateStatus('Typing chat command ' + commandIndex + '...');
                            
                            typeRealistic(command, "#chat-input", speed).then(() => {
                                updateStatus('Chat command ' + commandIndex + ' sent');
                            }).catch(error => {
                                updateStatus('Error: ' + error.message);
                                console.error('Error executing command:', error);
                            });
                        }
                        
                        function executeTerminalCommand(commandIndex, buttonElement) {
                            if (!mainWindow) {
                                updateStatus('Error: Cannot access main window');
                                return;
                            }
                            
                            const command = terminalCommands[commandIndex - 1];
                            const speed = parseInt(document.getElementById('typing-speed').value);
                            
                            buttonElement.classList.add('clicked');
                            setTimeout(() => buttonElement.classList.remove('clicked'), 300);
                            
                            updateStatus('Typing terminal command ' + commandIndex + '...');
                            
                            typeRealistic(command, "#terminal-input", speed).then(() => {
                                updateStatus('Terminal command ' + commandIndex + ' sent');
                            }).catch(error => {
                                updateStatus('Error: ' + error.message);
                                console.error('Error executing terminal command:', error);
                            });
                        }
                        
                        function runAllCommands() {
                            if (!mainWindow) {
                                updateStatus('Error: Cannot access main window');
                                return;
                            }
                            
                            const speed = parseInt(document.getElementById('typing-speed').value);
                            const delayBetween = 3000;
                            
                            updateStatus('Running all chat commands...');
                            
                            let currentIndex = 0;
                            function runNextCommand() {
                                if (currentIndex < chatCommands.length) {
                                    typeRealistic(chatCommands[currentIndex], "#chat-input", speed).then(() => {
                                        currentIndex++;
                                        if (currentIndex < chatCommands.length) {
                                            setTimeout(runNextCommand, delayBetween);
                                        } else {
                                            updateStatus('All chat commands completed');
                                        }
                                    });
                                }
                            }
                            
                            runNextCommand();
                        }
                        
                        function runAllTerminal() {
                            if (!mainWindow) {
                                updateStatus('Error: Cannot access main window');
                                return;
                            }
                            
                            const speed = parseInt(document.getElementById('typing-speed').value);
                            const delayBetween = 2000;
                            
                            updateStatus('Running all terminal commands...');
                            
                            let currentIndex = 0;
                            function runNextTerminalCommand() {
                                if (currentIndex < terminalCommands.length) {
                                    typeRealistic(terminalCommands[currentIndex], "#terminal-input", speed).then(() => {
                                        currentIndex++;
                                        if (currentIndex < terminalCommands.length) {
                                            setTimeout(runNextTerminalCommand, delayBetween);
                                        } else {
                                            updateStatus('All terminal commands completed');
                                        }
                                    });
                                }
                            }
                            
                            runNextTerminalCommand();
                        }
                        
                        function clearMainInput() {
                            if (!mainWindow) {
                                updateStatus('Error: Cannot access main window');
                                return;
                            }
                            
                            try {
                                const chatInput = mainWindow.document.getElementById('chat-input');
                                const terminalInput = mainWindow.document.getElementById('terminal-input');
                                
                                if (chatInput) {
                                    chatInput.value = '';
                                    chatInput.focus();
                                }
                                if (terminalInput) {
                                    terminalInput.value = '';
                                }
                                
                                updateStatus('Inputs cleared');
                            } catch (error) {
                                updateStatus('Error: ' + error.message);
                                console.error('Error clearing input:', error);
                            }
                        }
                        
                        function focusMainWindow() {
                            if (!mainWindow) {
                                updateStatus('Error: Cannot access main window');
                                return;
                            }
                            
                            try {
                                mainWindow.focus();
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
                </html>
            `;
            
            // Create a blob with the HTML content
            const blob = new Blob([controllerHTML], { type: 'text/html' });
            const blobUrl = URL.createObjectURL(blob);
            
            // Open the blob URL in a new window
            const controllerWindow = window.open(
                blobUrl,
                'demoController',
                'width=550,height=900,scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no,status=no'
            );
            
            // Clean up the blob URL after a delay to allow the window to load
            setTimeout(() => {
                URL.revokeObjectURL(blobUrl);
            }, 5000);
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
                                            
                                            <div class="ai-status-indicator configured">
                                                <i class="fas fa-robot"></i>
                                                <span>Ghosty the AI Guide: 
                                                        <span class="status-connected">Connected</span>
                                                </span>
                                            </div>
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
                                        <span class="ai-status-badge connected" title="AI Connected">
                                            <i class="fas fa-circle"></i>
                                        </span>
                                </span>
                            </div>
                            <div class="chat-messages" id="chat-messages">
                                <div class="chat-welcome">
                                        Hello! I'm Ghosty the AI Guide.<br>
                                        Ask me about commands or request help with specific tasks!
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