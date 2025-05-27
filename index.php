<?php
require_once 'auth_config.php';

// Require authentication
requireAuth();

// Debug: Log authentication check
error_log("Index.php: Authentication check passed");
error_log("Index.php: Session ID: " . session_id());
error_log("Index.php: User ID: " . ($_SESSION['user_id'] ?? 'not set'));

// Get current user
$user = getCurrentUser();
if (!$user) {
    error_log("Index.php: getCurrentUser() returned null, redirecting to login");
    header('Location: login.php');
    exit;
}

// Generate CSRF token
$csrfToken = generateCSRFToken();
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
    <style>
        /* Enhanced CSS with fixed terminal height and scrolling */
        :root {
            --primary-dark: #0d1117;
            --secondary-dark: #161b22;
            --tertiary-dark: #21262d;
            --accent-blue: #58a6ff;
            --accent-green: #3fb950;
            --accent-red: #f85149;
            --accent-yellow: #d29922;
            --text-primary: #f0f6fc;
            --text-secondary: #8b949e;
            --border-color: #30363d;
            --hover-bg: #262c36;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--secondary-dark) 100%);
            color: var(--text-primary);
            line-height: 1.6;
            height: 100vh;
            overflow: hidden;
        }

        .container-fluid {
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 0;
        }

        /* Header styles */
        header {
            background: linear-gradient(135deg, var(--tertiary-dark) 0%, var(--secondary-dark) 100%);
            color: var(--text-primary);
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            flex-shrink: 0;
        }

        header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(45deg, var(--accent-blue), var(--accent-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        header h1::before {
            content: "👻";
            font-size: 1.5rem;
            -webkit-text-fill-color: initial;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 0.9rem;
        }

        .user-info .user-details {
            text-align: right;
        }

        .user-info .user-name {
            font-weight: 600;
            color: var(--accent-blue);
        }

        .user-info .user-role {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .logout-btn {
            background: linear-gradient(135deg, var(--accent-red) 0%, rgba(248, 81, 73, 0.8) 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(248, 81, 73, 0.4);
        }

        /* Main content styles */
        .main-content {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* Sidebar styles */
        .sidebar {
            width: 320px;
            background: linear-gradient(180deg, var(--tertiary-dark) 0%, var(--secondary-dark) 100%);
            color: var(--text-primary);
            padding: 20px;
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
            flex-shrink: 0;
        }

        .sidebar h3 {
            margin-bottom: 16px;
            font-size: 1.2rem;
            font-weight: 600;
            border-bottom: 2px solid var(--accent-blue);
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar h3::before {
            content: "🖥️";
            font-size: 1.1rem;
        }

        #host-list {
            list-style: none;
            margin-bottom: 25px;
            padding-left: 0;
            max-height: 250px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--accent-blue) var(--tertiary-dark);
        }

        #host-list::-webkit-scrollbar {
            width: 6px;
        }

        #host-list::-webkit-scrollbar-track {
            background: var(--tertiary-dark);
            border-radius: 3px;
        }

        #host-list::-webkit-scrollbar-thumb {
            background: var(--accent-blue);
            border-radius: 3px;
        }

        #host-list li {
            padding: 16px;
            margin-bottom: 12px;
            background: linear-gradient(135deg, var(--tertiary-dark) 0%, rgba(33, 38, 45, 0.8) 100%);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        #host-list li::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent-blue);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        #host-list li:hover {
            background: linear-gradient(135deg, var(--hover-bg) 0%, rgba(38, 44, 54, 0.9) 100%);
            box-shadow: 0 4px 16px rgba(88, 166, 255, 0.2);
        }

        #host-list li:hover::before {
            transform: scaleY(1);
        }

        #host-list li.active {
            background: linear-gradient(135deg, rgba(88, 166, 255, 0.2) 0%, rgba(88, 166, 255, 0.1) 100%);
            border-color: var(--accent-blue);
            box-shadow: 0 0 20px rgba(88, 166, 255, 0.3);
        }

        #host-list li.active::before {
            transform: scaleY(1);
            background: var(--accent-green);
        }

        .host-name {
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-family: 'JetBrains Mono', monospace;
        }

        .host-status {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .host-status.online {
            background: rgba(63, 185, 80, 0.2);
            color: var(--accent-green);
            border: 1px solid rgba(63, 185, 80, 0.3);
        }

        .host-status.delayed {
            background: rgba(210, 153, 34, 0.2);
            color: var(--accent-yellow);
            border: 1px solid rgba(210, 153, 34, 0.3);
        }

        .host-ip, .host-seen {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-family: 'JetBrains Mono', monospace;
        }

        .no-hosts {
            font-style: italic;
            color: var(--text-secondary);
            cursor: default !important;
            text-align: center;
            padding: 30px 20px;
            background: rgba(139, 148, 158, 0.1);
            border: 2px dashed var(--border-color);
            border-radius: 8px;
        }

        .connection-setup {
            margin-top: 20px;
            padding: 20px;
            background: rgba(88, 166, 255, 0.05);
            border-radius: 8px;
            border: 1px solid rgba(88, 166, 255, 0.2);
        }

        .connection-setup h3::before {
            content: "⚙️";
        }

        .setup-command {
            background: var(--primary-dark);
            padding: 16px;
            border-radius: 8px;
            margin-top: 12px;
            position: relative;
            border: 1px solid var(--border-color);
            font-family: 'JetBrains Mono', monospace;
        }

        .setup-command code {
            display: block;
            word-break: break-all;
            color: var(--accent-green);
            font-size: 0.9rem;
        }

        #copy-command {
            position: absolute;
            top: 12px;
            right: 12px;
            background: var(--accent-blue);
            border: none;
            color: white;
            cursor: pointer;
            font-size: 1rem;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        #copy-command:hover {
            background: rgba(88, 166, 255, 0.8);
            transform: scale(1.1);
        }

        /* Historical sessions section */
        .historical-sessions {
            margin-top: 20px;
            padding: 20px;
            background: rgba(63, 185, 80, 0.05);
            border-radius: 8px;
            border: 1px solid rgba(63, 185, 80, 0.2);
        }

        .historical-sessions h3::before {
            content: "📁";
        }

        #historical-list {
            list-style: none;
            padding-left: 0;
            max-height: 200px;
            overflow-y: auto;
        }

        #historical-list li {
            padding: 10px;
            margin-bottom: 8px;
            background: rgba(63, 185, 80, 0.1);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        #historical-list li:hover {
            background: rgba(63, 185, 80, 0.2);
        }

        /* Terminal area styles */
        .terminal-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
            background: var(--secondary-dark);
            overflow: hidden;
        }

        /* Tab styles */
        .tabs {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
            overflow-x: auto;
            scrollbar-width: none;
            flex-shrink: 0;
        }

        .tabs::-webkit-scrollbar {
            display: none;
        }

        .tab {
            padding: 12px 20px;
            background: var(--tertiary-dark);
            color: var(--text-secondary);
            margin-right: 8px;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            position: relative;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            font-family: 'JetBrains Mono', monospace;
            font-weight: 500;
            white-space: nowrap;
        }

        .tab::before {
            content: "📟";
            margin-right: 8px;
            font-size: 0.9rem;
        }

        .tab-name {
            margin-right: 12px;
        }

        .tab-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: rgba(139, 148, 158, 0.3);
            color: var(--text-secondary);
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
        }

        .tab-close:hover {
            background-color: var(--accent-red);
            color: white;
            transform: scale(1.1);
        }

        .tab:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
            transform: translateY(-2px);
        }

        .tab.active {
            background: linear-gradient(135deg, var(--accent-blue) 0%, rgba(88, 166, 255, 0.8) 100%);
            color: white;
            border-color: var(--accent-blue);
            box-shadow: 0 4px 12px rgba(88, 166, 255, 0.3);
        }

        .tab.active .tab-close {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .tab.reconnecting {
            background: linear-gradient(135deg, var(--accent-red) 0%, rgba(248, 81, 73, 0.8) 100%);
            animation: pulse-tab 1.5s infinite;
        }

        @keyframes pulse-tab {
            0% { opacity: 0.7; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.02); }
            100% { opacity: 0.7; transform: scale(1); }
        }

        /* Terminal container styles - FIXED HEIGHT WITH SCROLLING */
        .terminal-container {
            flex: 1;
            background: var(--primary-dark);
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            border: 1px solid var(--border-color);
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            min-height: 0; /* Important for flex child */
        }

        .terminal-session {
            display: none;
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            scrollbar-width: thin;
            scrollbar-color: var(--accent-green) var(--primary-dark);
            min-height: 0; /* Important for flex child */
        }

        .terminal-session::-webkit-scrollbar {
            width: 8px;
        }

        .terminal-session::-webkit-scrollbar-track {
            background: var(--primary-dark);
        }

        .terminal-session::-webkit-scrollbar-thumb {
            background: var(--accent-green);
            border-radius: 4px;
        }

        .terminal-session.active {
            display: flex;
            flex-direction: column;
        }

        .terminal-output {
            color: var(--accent-green);
            font-family: 'JetBrains Mono', monospace;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.4;
            flex: 1;
            overflow-y: auto;
            margin-bottom: 15px;
        }

        .welcome-message {
            color: var(--accent-blue);
            text-align: center;
            padding: 40px 20px;
            background: rgba(88, 166, 255, 0.05);
            border-radius: 8px;
            border: 1px solid rgba(88, 166, 255, 0.2);
        }

        .welcome-message p {
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .welcome-message p:first-child {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .command-entry {
            margin-bottom: 12px;
            padding: 8px 0;
        }

        .command-entry .command {
            color: var(--accent-red);
            font-weight: 500;
        }

        .command-entry .result {
            color: var(--accent-green);
            margin-top: 4px;
        }

        .connection-lost-message {
            color: var(--accent-red);
            background: rgba(248, 81, 73, 0.1);
            border: 1px solid rgba(248, 81, 73, 0.3);
            border-radius: 8px;
            padding: 16px;
            margin: 15px 0;
            text-align: center;
            font-weight: 500;
        }

        .readonly-message {
            color: var(--accent-yellow);
            background: rgba(210, 153, 34, 0.1);
            border: 1px solid rgba(210, 153, 34, 0.3);
            border-radius: 8px;
            padding: 12px;
            margin: 15px 0;
            text-align: center;
            font-weight: 500;
        }

        /* Terminal input styles */
        .terminal-input-container {
            display: flex;
            background: var(--primary-dark);
            padding: 16px 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            align-items: center;
            flex-shrink: 0;
        }

        .prompt {
            color: var(--accent-red);
            margin-right: 12px;
            font-weight: bold;
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.1rem;
        }

        #terminal-input {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--accent-green);
            font-family: 'JetBrains Mono', monospace;
            font-size: 1rem;
            outline: none;
            padding: 8px 12px;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }

        #terminal-input:focus {
            background: rgba(88, 166, 255, 0.05);
        }

        #send-command {
            background: linear-gradient(135deg, var(--accent-blue) 0%, rgba(88, 166, 255, 0.8) 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            margin-left: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #send-command::after {
            content: "→";
            font-size: 1.2rem;
        }

        #send-command:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(88, 166, 255, 0.4);
        }

        #send-command:disabled {
            background: var(--text-secondary);
            cursor: not-allowed;
            opacity: 0.5;
        }

        /* Responsive styles */
        @media (max-width: 1200px) {
            .sidebar {
                width: 280px;
            }
        }

        @media (max-width: 992px) {
            .main-content {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
                max-height: 400px;
            }
        }

        @media (max-width: 768px) {
            header h1 {
                font-size: 1.5rem;
            }

            .tab {
                padding: 10px 16px;
                font-size: 0.9rem;
            }
        }

        /* Session status indicators */
        .session-status {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .session-status.active {
            background: var(--accent-green);
            animation: pulse-dot 2s infinite;
        }

        .session-status.ended {
            background: var(--accent-red);
        }

        .session-status.disconnected {
            background: var(--accent-yellow);
        }

        @keyframes pulse-dot {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        /* Loading states */
        .loading {
            background: linear-gradient(90deg, var(--tertiary-dark) 0px, var(--hover-bg) 40px, var(--tertiary-dark) 80px);
            background-size: 200px;
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200px 0; }
            100% { background-position: calc(200px + 100%) 0; }
        }
    </style>
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
                        <code>mshta <?php echo APP_URL; ?>/local/autoconnect.hta</code>
                        <button id="copy-command" title="Copy to clipboard"><i class="fas fa-copy"></i></button>
                    </div>
                </div>
                
                <div class="historical-sessions">
                    <h3>Historical Sessions</h3>
                    <ul id="historical-list">
                        <li class="no-hosts">No historical sessions</li>
                    </ul>
                </div>
            </div>
            
            <div class="terminal-area">
                <div class="tabs" id="session-tabs">
                    <div class="tab active" data-session="welcome">
                        <span class="tab-name">Welcome</span>
                    </div>
                </div>
                
                <div class="terminal-container">
                    <div class="terminal-session active" id="welcome-terminal">
                        <div class="terminal-output">
                            <div class="welcome-message">
                                <p>Welcome to GhostCrew!</p>
                                <p>Logged in as: <strong><?php echo htmlspecialchars($user['full_name']); ?></strong></p>
                                <p>Connect a host using the setup command to begin.</p>
                                <p>Your sessions are tracked and logged for security purposes.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="terminal-input-container">
                    <div class="prompt">$</div>
                    <input type="text" id="terminal-input" placeholder="Enter command..." disabled>
                    <button id="send-command" disabled>Send</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Global variables
        const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
        const USER_ID = <?php echo $user['id']; ?>;
        const USERNAME = '<?php echo htmlspecialchars($user['username']); ?>';
        
        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }
        
        // Enhanced copy command functionality
        $('#copy-command').click(function() {
            const command = $('.setup-command code').text();
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

        // Auto-scroll terminal output
        function scrollToBottom(element) {
            element.scrollTop = element.scrollHeight;
        }
    </script>
    
    <!-- Enhanced terminal.js will be loaded -->
    <script src="js/enhanced_terminal.js"></script>
</body>
</html>