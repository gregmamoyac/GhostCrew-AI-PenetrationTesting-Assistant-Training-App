// js/enhanced_terminal.js - Complete chatbot integration and improvements

// Enhanced Terminal App JavaScript with Authentication, Session Management, and AI Chatbot
$(document).ready(function() {
    // Variables
    let activeHostId = null;
    let activeSessionId = 'welcome';
    let currentRemoteSessionId = null;
    let connectedHosts = [];
    let historicalSessions = [];
    let refreshInterval = null;
    let commandHistoryIndex = -1;
    let commandHistory = [];
    let currentCommand = '';
    let sessionCommandHistories = {}; // Store command history per session
    let chatMessages = {};
    let currentConversationId = null;
    let chatInitialized = false;
    let lastCommandSent = null;
    
    // Initialize the application
    initApp();
    
    // Send command when Send button is clicked
    $('#send-command').on('click', function() {
        sendCommand();
    });
    
    // Send command when Enter key is pressed, navigate history with up/down arrows
    $('#terminal-input').on('keydown', function(e) {
        if (e.key === 'Enter') {
            sendCommand();
        } else if (e.key === 'ArrowUp') {
            navigateCommandHistory(-1);
            e.preventDefault();
        } else if (e.key === 'ArrowDown') {
            navigateCommandHistory(1);
            e.preventDefault();
        }
    });
    
    // Send chat message when button is clicked
    $('#send-chat').on('click', function() {
        sendChatMessage();
    });
    
    // Handle chat input enter key
    $('#chat-input').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendChatMessage();
        }
    });
    
    // Handle command suggestion execution
    $(document).on('click', '.load-suggestion-btn', function() {
        const suggestionId = $(this).data('suggestion-id');
        const command = $(this).data('command');
        
        loadSuggestedCommand(suggestionId, command);
    });
    
    // Handle message rating
    $(document).on('click', '.rate-message-btn', function() {
        const messageId = $(this).data('message-id');
        const rating = $(this).data('rating');
        rateMessage(messageId, rating);
    });
    
    // Switch between tabs
    $('#session-tabs').on('click', '.tab', function() {
        const sessionId = $(this).data('session');
        switchToSession(sessionId);
    });
    
    // Close tabs with middle click
    $('#session-tabs').on('mousedown', '.tab', function(e) {
        if (e.which === 2) { // Middle mouse button
            e.preventDefault();
            const sessionId = $(this).data('session');
            if (sessionId !== 'welcome') {
                closeSession(sessionId);
            }
        }
    });
    
    // Switch to a host's terminal when clicked
    $('#host-list').on('click', 'li', function() {
        if ($(this).hasClass('no-hosts')) {
            return;
        }
        
        const hostId = $(this).data('hostid');
        switchToHost(hostId);
    });
    
    // View historical session
    $('#historical-list').on('click', 'li', function() {
        if ($(this).hasClass('no-hosts')) {
            return;
        }
        
        const sessionId = $(this).data('sessionid');
        viewHistoricalSession(sessionId);
    });
    
    // Function to initialize the application
    function initApp() {
        console.log('Initializing GhostCrew Terminal v4.0 with Enhanced AI Assistant');
        
        // Initialize chat interface
        initializeChatInterface();
        
        // Load initial data
        loadSystemInfo();
        loadHosts();
        loadHistoricalSessions();
        
        // Set up refresh interval
        refreshInterval = setInterval(function() {
            loadHosts();
            
            // If a host is active, check for command results
            if (activeHostId && currentRemoteSessionId) {
                loadCommandHistory(currentRemoteSessionId);
            }
        }, 3000); // Refresh every 3 seconds
        
        // Session timeout warning
        if (typeof SESSION_TIMEOUT !== 'undefined' && SESSION_TIMEOUT > 300) {
            setTimeout(function() {
                showSessionWarning();
            }, (SESSION_TIMEOUT - 300) * 1000); // 5 minutes before timeout
        }
        
        // Auto-scroll chat on new messages
        observeChatChanges();
    }
    
    // Function to initialize chat interface
    function initializeChatInterface() {
        // Add welcome message to chat
        if (!chatInitialized) {
            setTimeout(() => {
                addChatMessage('bot', 'Hello! I\'m your AI command assistant. I can help you with Windows commands, explain how to perform tasks, and suggest useful commands.\n\n**Try asking me:**\n• "How do I list files?"\n• "Help with network commands"\n• "Show me system information"\n• "How do I create a folder?"\n\nWhat would you like to know?');
                chatInitialized = true;
            }, 1000);
        }
    }
    
    // Function to observe chat changes for auto-scroll
    function observeChatChanges() {
        const chatMessages = document.getElementById('chat-messages');
        if (chatMessages) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                        scrollChatToBottom();
                    }
                });
            });
            
            observer.observe(chatMessages, {
                childList: true,
                subtree: true
            });
        }
    }
    
    // Function to show session timeout warning
    function showSessionWarning() {
        if (confirm('Your session will expire in 5 minutes. Click OK to stay logged in.')) {
            // Make a request to refresh session
            $.ajax({
                url: 'api.php',
                type: 'POST',
                data: {
                    action: 'ping_session',
                    csrf_token: CSRF_TOKEN
                }
            });
        }
    }
    
    // Function to load system information
    function loadSystemInfo() {
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'get_system_info',
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    updateSystemInfo(response);
                } else if (response.redirect) {
                    window.location.href = response.redirect;
                }
            },
            error: function(xhr) {
                if (xhr.status === 401) {
                    window.location.href = 'login.php';
                }
            }
        });
    }
    
    // Function to update system info display
    function updateSystemInfo(data) {
        const serverInfo = data.server_info;
        document.title = `GhostCrew - ${serverInfo.user} | Hosts: ${data.host_count} | Sessions: ${data.active_sessions}`;
    }
    
    // Function to load connected hosts
    function loadHosts() {
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'get_hosts',
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    connectedHosts = response.hosts;
                    updateHostList();
                } else if (response.redirect) {
                    window.location.href = response.redirect;
                }
            },
            error: function(xhr) {
                if (xhr.status === 401) {
                    window.location.href = 'login.php';
                }
            }
        });
    }
    
    // Function to load historical sessions
    function loadHistoricalSessions() {
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'get_historical_sessions',
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    historicalSessions = response.sessions;
                    updateHistoricalSessionsList();
                }
            }
        });
    }
    
    // Function to update the host list
    function updateHostList() {
        const $hostList = $('#host-list');
        $hostList.empty();
        
        if (connectedHosts.length === 0) {
            $hostList.append('<li class="no-hosts">No hosts connected</li>');
            
            // Handle disconnected active host
            if (activeHostId && currentRemoteSessionId) {
                handleHostGracefulDisconnection(activeHostId);
            }
            
            return;
        }
        
        $.each(connectedHosts, function(index, host) {
            const isActive = (host.host_id === activeHostId);
            const lastSeen = new Date(host.last_seen);
            const lastSeenFormatted = lastSeen.toLocaleString();
            const secondsSinceLastSeen = host.seconds_since_last_seen || 0;
            
            let statusIndicator = '<span class="host-status online">● Online</span>';
            if (secondsSinceLastSeen > 60) {
                statusIndicator = `<span class="host-status delayed">● Delayed (${Math.floor(secondsSinceLastSeen / 60)}m)</span>`;
            }
            
            let hostHtml = `<li data-hostid="${host.host_id}" class="${isActive ? 'active' : ''}">`;
            hostHtml += `<div class="host-name">${escapeHtml(host.hostname)} ${statusIndicator}</div>`;
            hostHtml += `<div class="host-ip"><small>IP: ${escapeHtml(host.ip_address)}</small></div>`;
            hostHtml += `<div class="host-seen"><small>Last seen: ${lastSeenFormatted}</small></div>`;
            hostHtml += '</li>';
            
            $hostList.append(hostHtml);
        });
        
        // Remove disconnection message if host is back
        if (activeHostId && connectedHosts.find(h => h.host_id === activeHostId)) {
            $(`#${activeHostId}-terminal .connection-lost-message`).remove();
            $(`#session-tabs .tab[data-session="${activeHostId}"]`).removeClass('reconnecting');
            
            if (currentRemoteSessionId) {
                $('#terminal-input').prop('disabled', false);
                $('#send-command').prop('disabled', false);
            }
        }
    }
    
    // Function to update historical sessions list
    function updateHistoricalSessionsList() {
        const $historicalList = $('#historical-list');
        $historicalList.empty();
        
        if (historicalSessions.length === 0) {
            $historicalList.append('<li class="no-hosts">No historical sessions</li>');
            return;
        }
        
        $.each(historicalSessions.slice(0, 10), function(index, session) {
            const startTime = new Date(session.start_time);
            const endTime = session.end_time ? new Date(session.end_time) : null;
            const duration = endTime ? Math.round((endTime - startTime) / 1000 / 60) : 'N/A';
            
            let statusClass = 'ended';
            let statusText = 'Ended';
            if (session.status === 'disconnected') {
                statusClass = 'disconnected';
                statusText = 'Disconnected';
            }
            
            let sessionHtml = `<li data-sessionid="${session.session_id}">`;
            sessionHtml += `<div><span class="session-status ${statusClass}"></span><strong>${escapeHtml(session.hostname)}</strong></div>`;
            sessionHtml += `<div><small>Commands: ${session.total_commands} | Duration: ${duration}m</small></div>`;
            sessionHtml += `<div><small>${startTime.toLocaleString()}</small></div>`;
            sessionHtml += '</li>';
            
            $historicalList.append(sessionHtml);
        });
    }
    
    // Function to handle host disconnection
    function handleHostDisconnection() {
        if (!$(`#${activeHostId}-terminal .connection-lost-message`).length) {
            $(`#${activeHostId}-terminal .terminal-output`).append(
                `<div class="connection-lost-message">
                    <p><i class="fas fa-exclamation-triangle"></i> Connection to host has been lost.</p>
                    <p>The host is no longer responding. Session has been terminated.</p>
                </div>`
            );
        }
        
        // Disable terminal input
        $('#terminal-input').prop('disabled', true);
        $('#send-command').prop('disabled', true);
        
        // Mark tab as reconnecting
        $(`#session-tabs .tab[data-session="${activeHostId}"]`).addClass('reconnecting');
        
        // End the remote session
        if (currentRemoteSessionId) {
            endSession(currentRemoteSessionId);
            currentRemoteSessionId = null;
        }
        
        // Notify chat about disconnection
        addChatMessage('bot', '⚠️ **Connection Lost**\n\nThe connection to the host has been lost. The session has been terminated automatically.');
    }
    
    // Function to switch to a host
    function switchToHost(hostId) {
        // Check if we already have an active session for this host
        const existingTab = $(`#session-tabs .tab[data-session="${hostId}"]`);
        
        if (existingTab.length > 0) {
            // Switch to existing session
            const sessionId = existingTab.data('remote-session');
            if (sessionId) {
                switchToSession(hostId);
                return;
            }
        }
        
        if (hostId === activeHostId && currentRemoteSessionId) {
            return; // Already active
        }
        
        activeHostId = hostId;
        
        // Update active host in the list
        $('#host-list li').removeClass('active');
        $(`#host-list li[data-hostid="${hostId}"]`).addClass('active');
        
        // Find the host information
        const host = connectedHosts.find(h => h.host_id === hostId);
        if (!host) {
            return;
        }
        
        // Start a new session for this host
        startNewSession(hostId, host);
    }
    
    // Function to start a new session
    function startNewSession(hostId, host) {
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'start_session',
                host_id: hostId,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    currentRemoteSessionId = response.session_id;
                    createNewSessionTab(hostId, host, response.session_id);
                    
                    // Initialize chat for this session
                    initializeChatForSession(response.session_id);
                    
                    // Notify about new session
                    addChatMessage('bot', `🖥️ **New Session Started**\n\nConnected to **${host.hostname}** (${host.ip_address})\nSession ID: ${response.session_id}\n\nI'm here to help with commands! Try asking me for suggestions.`);
                    
                    console.log(`New session started: ${response.session_id}`);
                } else {
                    console.error('Failed to start session:', response.message);
                    showNotification('Failed to start new session: ' + response.message, 'error');
                }
            },
            error: function(xhr) {
                if (xhr.status === 401) {
                    window.location.href = 'login.php';
                } else {
                    showNotification('Network error while starting session', 'error');
                }
            }
        });
    }
    
    // Function to create a new session tab
    function createNewSessionTab(hostId, host, sessionId) {
        // Remove any existing tab for this host
        $(`#session-tabs .tab[data-session="${hostId}"]`).remove();
        $(`#${hostId}-terminal`).remove();
        
        // Create new tab
        $('#session-tabs .tab').removeClass('active');
        const tabHtml = `<div class="tab active" data-session="${hostId}" data-remote-session="${sessionId}">
            <span class="tab-name">${escapeHtml(host.hostname)}</span>
            <span class="tab-close" data-session="${hostId}">&times;</span>
        </div>`;
        $('#session-tabs').append(tabHtml);
        
        // Create new terminal session
        $('.terminal-session').removeClass('active');
        const terminalHtml = `
            <div class="terminal-session active" id="${hostId}-terminal">
                <div class="terminal-output">
                    <div class="welcome-message">
                        <p><i class="fas fa-desktop"></i> Connected to ${escapeHtml(host.hostname)}</p>
                        <p><strong>IP:</strong> ${escapeHtml(host.ip_address)}</p>
                        <p><strong>OS:</strong> ${escapeHtml(host.os_info)}</p>
                        <p><strong>Session ID:</strong> ${sessionId}</p>
                        <p>Ready to receive commands. Type commands below or ask the AI assistant for help.</p>
                    </div>
                </div>
            </div>`;
        $('.terminal-container').append(terminalHtml);
        
        // Add event listener for the close button
        $(`.tab-close[data-session="${hostId}"]`).on('click', function(e) {
            e.stopPropagation();
            closeSession(hostId);
        });
        
        // Enable terminal input
        $('#terminal-input').prop('disabled', false).focus();
        $('#send-command').prop('disabled', false);
        
        // Reset command history for this session
        commandHistoryIndex = -1;
        commandHistory = sessionCommandHistories[sessionId] || [];
        
        // Update terminal prompt
        updateTerminalPrompt('C:\\>');
        
        // Load any existing command history for this session
        loadCommandHistory(sessionId);
    }
    
    // Function to initialize chat for a session
    function initializeChatForSession(sessionId) {
        currentConversationId = `conv_${sessionId}_${Date.now()}`;
        
        // Load existing chat history if any
        loadChatHistory(sessionId);
    }
    
    // Function to view historical session
    function viewHistoricalSession(sessionId) {
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'get_session_history',
                session_id: sessionId,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    createHistoricalSessionTab(response.session, response.commands);
                } else {
                    showNotification('Failed to load session history: ' + response.message, 'error');
                }
            }
        });
    }
    
    // Function to create historical session tab
    function createHistoricalSessionTab(session, commands) {
        const tabId = `hist-${session.session_id}`;
        
        // Remove existing tab if present
        $(`#session-tabs .tab[data-session="${tabId}"]`).remove();
        $(`#${tabId}-terminal`).remove();
        
        // Create tab
        $('#session-tabs .tab').removeClass('active');
        const tabHtml = `<div class="tab active readonly" data-session="${tabId}">
            <span class="tab-name">[HIST] ${escapeHtml(session.hostname)}</span>
            <span class="tab-close" data-session="${tabId}">&times;</span>
        </div>`;
        $('#session-tabs').append(tabHtml);
        
        // Create terminal session
        $('.terminal-session').removeClass('active');
        let terminalHtml = `
            <div class="terminal-session active" id="${tabId}-terminal">
                <div class="terminal-output">`;
        
        // Add readonly message
        terminalHtml += `<div class="readonly-message">
            <p><i class="fas fa-history"></i> Historical Session - Read Only</p>
            <p><strong>Session:</strong> ${session.session_id}</p>
            <p><strong>Host:</strong> ${escapeHtml(session.hostname)} (${escapeHtml(session.ip_address)})</p>
            <p><strong>Duration:</strong> ${session.start_time} - ${session.end_time || 'N/A'}</p>
        </div>`;
        
        // Add command history
        commands.forEach(function(cmd) {
            terminalHtml += `<div class="command-entry">
                <div class="prompt-line">C:\\> ${escapeHtml(cmd.command)}</div>`;
            
            if (cmd.output) {
                terminalHtml += `<div class="result">${escapeHtml(cmd.output)}</div>`;
            } else {
                terminalHtml += `<div class="result"><em>No output</em></div>`;
            }
            
            terminalHtml += `</div>`;
        });
        
        terminalHtml += `</div></div>`;
        $('.terminal-container').append(terminalHtml);
        
        // Add close event
        $(`.tab-close[data-session="${tabId}"]`).on('click', function(e) {
            e.stopPropagation();
            $(`#session-tabs .tab[data-session="${tabId}"]`).remove();
            $(`#${tabId}-terminal`).remove();
            
            if ($('.tab').length === 1) {
                $('.tab[data-session="welcome"]').addClass('active');
                $('#welcome-terminal').addClass('active');
                updateTerminalPrompt();
            }
        });
        
        // Disable input for historical sessions
        $('#terminal-input').prop('disabled', true);
        $('#send-command').prop('disabled', true);
        
        // Switch to this session
        activeSessionId = tabId;
        activeHostId = null;
        currentRemoteSessionId = null;
        
        // Update terminal prompt
        updateTerminalPrompt();
        
        // Scroll to bottom
        const $terminal = $(`#${tabId}-terminal .terminal-output`);
        scrollToBottom($terminal[0]);
    }
    
    // Function to close a session
    function closeSession(sessionId) {
        const $tab = $(`#session-tabs .tab[data-session="${sessionId}"]`);
        const remoteSessionId = $tab.data('remote-session');
        
        // End remote session if it's active
        if (remoteSessionId && !$tab.hasClass('readonly')) {
            endSession(remoteSessionId);
        }
        
        // Remove tab and terminal
        $tab.remove();
        $(`#${sessionId}-terminal`).remove();
        
        // Reset active session if this was active
        if (sessionId === activeSessionId) {
            const $remainingTabs = $('#session-tabs .tab:not([data-session="welcome"])');
            if ($remainingTabs.length > 0) {
                const newSessionId = $($remainingTabs[0]).data('session');
                switchToSession(newSessionId);
            } else {
                // Always switch to welcome if no other sessions
                switchToSession('welcome');
                $('.tab[data-session="welcome"]').addClass('active');
                $('#welcome-terminal').addClass('active');
                activeSessionId = 'welcome';
                activeHostId = null;
                currentRemoteSessionId = null;
            }
        }
    }
    
    // Function to switch between sessions
    function switchToSession(sessionId) {
        const $tab = $(`#session-tabs .tab[data-session="${sessionId}"]`);
        const remoteSessionId = $tab.data('remote-session');
        const isReadonly = $tab.hasClass('readonly');
        
        // Update active tab
        $('#session-tabs .tab').removeClass('active');
        $tab.addClass('active');
        
        // Update active terminal session
        $('.terminal-session').removeClass('active');
        $(`#${sessionId}-terminal`).addClass('active');
        
        // Update variables
        activeSessionId = sessionId;
        
        if (sessionId === 'welcome') {
            activeHostId = null;
            currentRemoteSessionId = null;
            $('#terminal-input').prop('disabled', true);
            $('#send-command').prop('disabled', true);
            updateTerminalPrompt();
            commandHistory = [];
            commandHistoryIndex = -1;
        } else if (isReadonly) {
            activeHostId = null;
            currentRemoteSessionId = null;
            $('#terminal-input').prop('disabled', true);
            $('#send-command').prop('disabled', true);
            updateTerminalPrompt();
            commandHistory = [];
            commandHistoryIndex = -1;
        } else {
            activeHostId = sessionId;
            currentRemoteSessionId = remoteSessionId;
            
            // Load command history for this session
            commandHistory = sessionCommandHistories[remoteSessionId] || [];
            commandHistoryIndex = -1;
            
            // Update active host in list
            $('#host-list li').removeClass('active');
            $(`#host-list li[data-hostid="${sessionId}"]`).addClass('active');
            
            // Enable input if host is still connected
            const hostExists = connectedHosts.find(h => h.host_id === sessionId);
            if (hostExists && remoteSessionId) {
                $('#terminal-input').prop('disabled', false).focus();
                $('#send-command').prop('disabled', false);
                
                // Load chat history for this session
                loadChatHistory(remoteSessionId);
                currentConversationId = `conv_${remoteSessionId}_${Date.now()}`;
            } else {
                $('#terminal-input').prop('disabled', true);
                $('#send-command').prop('disabled', true);
            }
        }
    }
    
    // Function to load command history for a session
    function loadCommandHistory(sessionId) {
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'get_command_history',
                session_id: sessionId,
                limit: 50,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    updateTerminalWithHistory(sessionId, response.commands);
                }
            }
        });
    }
    
    // Function to update terminal with command history
    function updateTerminalWithHistory(sessionId, commands) {
        const terminalId = sessionId === currentRemoteSessionId ? activeHostId : sessionId;
        const $terminal = $(`#${terminalId}-terminal .terminal-output`);
        
        if (!$terminal.length) return;
        
        // Clear existing command entries, but keep welcome message
        $terminal.find('.command-entry').remove();
        
        // Sort commands by timestamp (oldest first)
        commands.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
        
        // Store commands for history navigation and update session storage
        const sessionCommands = commands.map(cmd => cmd.command);
        sessionCommandHistories[sessionId] = sessionCommands;
        
        if (sessionId === currentRemoteSessionId) {
            commandHistory = sessionCommands;
        }
        
        let lastWorkingDir = 'C:\\>';
        
        // Add each command and its output to the terminal
        $.each(commands, function(index, cmd) {
            // Update working directory if available
            if (cmd.working_directory) {
                lastWorkingDir = cmd.working_directory.endsWith('>') ? cmd.working_directory : cmd.working_directory + '>';
            }
            
            // Fix escaped slashes in display
            let displayDir = lastWorkingDir.replace(/\\\\/g, '\\');
            
            let entryHtml = `<div class="command-entry">`;
            entryHtml += `<div class="prompt-line">${escapeHtml(displayDir)} ${escapeHtml(cmd.command)}</div>`;
            if (cmd.output !== null) {
                entryHtml += `<div class="result">${escapeHtml(cmd.output)}</div>`;
            } else {
                entryHtml += `<div class="result"><em>Waiting for response...</em></div>`;
            }
            
            entryHtml += `</div>`;
            
            $terminal.append(entryHtml);
        });
        
        // Update terminal prompt with last working directory
        if (sessionId === currentRemoteSessionId) {
            updateTerminalPrompt(lastWorkingDir);
        }
        
        // Only scroll to bottom if user hasn't manually scrolled up
        if (sessionId === currentRemoteSessionId && !isUserScrolledUp($terminal[0])) {
            scrollToBottom($terminal[0]);
        }
    }
    
    // Function to send a command
    function sendCommand() {
        const command = $('#terminal-input').val().trim();
        
        if (command === '' || !activeHostId || !currentRemoteSessionId) {
            return;
        }
        
        // Store the command being sent
        lastCommandSent = {
            command: command,
            timestamp: Date.now(),
            sessionId: currentRemoteSessionId
        };
        
        // Clear the input field
        $('#terminal-input').val('').focus();
        
        // Add to command history
        if (!commandHistory.includes(command)) {
            commandHistory.unshift(command);
            sessionCommandHistories[currentRemoteSessionId] = commandHistory;
        }
        commandHistoryIndex = -1;
        
        // Send the command to the server
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'send_command',
                host_id: activeHostId,
                session_id: currentRemoteSessionId,
                command: command,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Add the command to the terminal immediately
                    const $terminal = $(`#${activeHostId}-terminal .terminal-output`);
                    const currentPrompt = $('#terminal-prompt').text().replace(/\\\\/g, '\\');

                    let entryHtml = `<div class="command-entry">`;
                    entryHtml += `<div class="prompt-line">${escapeHtml(currentPrompt)} ${escapeHtml(command)}</div>`;
                    entryHtml += `<div class="result"><em>Executing...</em></div>`;
                    entryHtml += `</div>`;
                    
                    $terminal.append(entryHtml);
                    scrollToBottom($terminal[0]); // Always scroll after sending command
                    
                    // Send command context to chatbot after a short delay
                    setTimeout(() => {
                        sendCommandContextToChat(command);
                    }, 500);
                    
                } else if (response.redirect) {
                    window.location.href = response.redirect;
                } else {
                    showNotification('Failed to send command: ' + response.message, 'error');
                }
            },
            error: function(xhr) {
                if (xhr.status === 401) {
                    window.location.href = 'login.php';
                } else {
                    showNotification('Network error while sending command', 'error');
                }
            }
        });
    }
    
    // Function to end a session
    function endSession(sessionId) {
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'end_session',
                session_id: sessionId,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                console.log(`Session ${sessionId} ended`);
                
                // Refresh historical sessions
                loadHistoricalSessions();
            }
        });
    }

    // Function to navigate command history
    function navigateCommandHistory(direction) {
        if (commandHistory.length === 0) {
            return;
        }
        
        // Store current command if we're at the beginning of history
        if (commandHistoryIndex === -1 && direction === -1) {
            currentCommand = $('#terminal-input').val();
        }
        
        // Calculate new index
        commandHistoryIndex += direction;
        
        // Ensure index is within bounds
        if (commandHistoryIndex < -1) {
            commandHistoryIndex = -1;
        } else if (commandHistoryIndex >= commandHistory.length) {
            commandHistoryIndex = commandHistory.length - 1;
        }
        
        // Set command from history or restore current command
        if (commandHistoryIndex === -1) {
            $('#terminal-input').val(currentCommand || '');
        } else {
            $('#terminal-input').val(commandHistory[commandHistoryIndex]);
        }
    }

    // Enhanced Chat functionality
    function sendChatMessage() {
        const message = $('#chat-input').val().trim();
        
        if (message === '') {
            return;
        }
        
        // Clear input and reset height
        $('#chat-input').val('').height('auto');
        
        // Add user message to chat
        addChatMessage('user', message);
        
        // Show typing indicator
        showTypingIndicator();
        
        // Send to server
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'chat_message',
                session_id: currentRemoteSessionId || 'welcome',
                message: message,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                hideTypingIndicator();
                
                if (response.status === 'success') {
                    // Add bot response with enhanced formatting
                    addChatMessage('bot', response.bot_response, {
                        suggested_command: response.suggested_command,
                        command_description: response.command_description,
                        suggestion_id: response.suggestion_id,
                        category: response.category,
                        bot_message_id: response.bot_message_id
                    });
                } else {
                    addChatMessage('bot', 'Sorry, I encountered an error processing your request. Please try again.');
                }
            },
            error: function() {
                hideTypingIndicator();
                addChatMessage('bot', 'Sorry, I\'m having trouble connecting right now. Please try again.');
            }
        });
    }

    // Enhanced function to add chat messages
    function addChatMessage(type, message, options = {}) {
        const $chatMessages = $('#chat-messages');
        
        // Remove welcome message if it exists
        $chatMessages.find('.chat-welcome').remove();
        
        const timestamp = new Date().toLocaleTimeString();
        let messageHtml = `<div class="chat-message ${type}" data-timestamp="${timestamp}">`;
        
        if (type === 'bot') {
            // Enhanced formatting for bot messages
            message = formatBotMessage(message);
            messageHtml += `<div class="message-content">${message}</div>`;
            
            // Add command suggestion if present
            if (options.suggested_command && options.suggestion_id) {
                messageHtml += `<div class="command-suggestion">
                    <div class="suggestion-header">
                        <i class="fas fa-lightbulb"></i>
                        <span>Suggested Command</span>
                    </div>
                    <div class="suggestion-content">
                        <code>${escapeHtml(options.suggested_command)}</code>
                        <button class="load-suggestion-btn" 
                                data-suggestion-id="${options.suggestion_id}" 
                                data-command="${escapeHtml(options.suggested_command)}" 
                                title="${escapeHtml(options.command_description || 'Load this command into terminal')}">
                            <i class="fas fa-download"></i>
                            Load
                        </button>
                    </div>
                    ${options.command_description ? `<div class="suggestion-description">${escapeHtml(options.command_description)}</div>` : ''}
                </div>`;
            }
            
            // Add rating buttons for bot messages
            if (options.bot_message_id) {
                messageHtml += `<div class="message-actions">
                    <div class="rating-buttons">
                        <button class="rate-message-btn helpful" data-message-id="${options.bot_message_id}" data-rating="5" title="This was helpful">
                            <i class="fas fa-thumbs-up"></i>
                        </button>
                        <button class="rate-message-btn not-helpful" data-message-id="${options.bot_message_id}" data-rating="1" title="This was not helpful">
                            <i class="fas fa-thumbs-down"></i>
                        </button>
                    </div>
                    <span class="message-time">${timestamp}</span>
                </div>`;
            }
        } else {
            // User message
            messageHtml += `<div class="message-content">${escapeHtml(message)}</div>`;
            messageHtml += `<span class="message-time">${timestamp}</span>`;
        }
        
        messageHtml += '</div>';
        
        $chatMessages.append(messageHtml);
        scrollChatToBottom();
        
        // Animate message appearance
        const $newMessage = $chatMessages.children().last();
        $newMessage.hide().fadeIn(300);
    }

    // Function to format bot messages with enhanced markdown-like syntax
    function formatBotMessage(message) {
        // Convert markdown-like formatting
        message = message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        message = message.replace(/\*(.*?)\*/g, '<em>$1</em>');
        message = message.replace(/`(.*?)`/g, '<code>$1</code>');
        message = message.replace(/\n/g, '<br>');
        
        // Convert bullet points
        message = message.replace(/^• (.+)$/gm, '<div class="bullet-point">• $1</div>');
        
        // Convert numbered lists
        message = message.replace(/^(\d+)\. (.+)$/gm, '<div class="numbered-point">$1. $2</div>');
        
        return message;
    }

    // Function to show typing indicator
    function showTypingIndicator() {
        const $chatMessages = $('#chat-messages');
        const typingHtml = `<div class="typing-indicator" id="typing-indicator">
            <div class="typing-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <span class="typing-text">AI is thinking...</span>
        </div>`;
        $chatMessages.append(typingHtml);
        scrollChatToBottom();
    }

    // Function to hide typing indicator
    function hideTypingIndicator() {
        $('#typing-indicator').fadeOut(200, function() {
            $(this).remove();
        });
    }

    // Function to load chat history
    function loadChatHistory(sessionId) {
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'get_chat_history',
                session_id: sessionId,
                limit: 20,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.messages.length > 0) {
                    const $chatMessages = $('#chat-messages');
                    $chatMessages.find('.chat-welcome').remove();
                    $chatMessages.empty();
                    
                    response.messages.forEach(function(msg) {
                        addChatMessage(msg.message_type, msg.message, {
                            suggested_command: msg.suggested_command,
                            bot_message_id: msg.id
                        });
                    });
                }
            }
        });
    }

    // Function to send command context to chat
    function sendCommandContextToChat(command) {
        // Auto-suggest help if command might have failed or user asks for help
        const helpKeywords = ['help', '?', 'how', 'what', 'error', 'failed', 'cannot'];
        const shouldSuggestHelp = helpKeywords.some(keyword => 
            command.toLowerCase().includes(keyword)
        );
        
        if (shouldSuggestHelp || Math.random() < 0.1) { // 10% chance for proactive help
            setTimeout(() => {
                addChatMessage('bot', `I see you ran: **${command}**\n\nNeed help with this command or have questions about the output? Just ask me!`);
            }, 2000);
        }
    }

    // Function to load suggested command into terminal input
    function loadSuggestedCommand(suggestionId, command) {
        if (!activeHostId || !currentRemoteSessionId) {
            showNotification('No active session available', 'warning');
            return;
        }
        
        // Load the command into terminal input
        $('#terminal-input').val(command).focus();
        
        // Mark suggestion as used (but not executed)
        markSuggestionAsUsed(suggestionId);
        
        // Update button state
        $(`.load-suggestion-btn[data-suggestion-id="${suggestionId}"]`)
            .removeClass('load-suggestion-btn')
            .addClass('loaded-btn')
            .html('<i class="fas fa-check"></i> Loaded')
            .prop('disabled', true);
        
        // Add chat message indicating the command was loaded
        addChatMessage('bot', `📝 **Command Loaded**\n\nLoaded command into terminal: **${command}**\n\nPress Enter or click Send to execute it.`);
    }

    // Helper function to mark suggestion as used
    function markSuggestionAsUsed(suggestionId) {
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'mark_suggestion_used',
                suggestion_id: suggestionId,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json'
        });
    }

    // Function to rate a message
    function rateMessage(messageId, rating) {
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'rate_chat_message',
                message_id: messageId,
                rating: rating,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Update button state
                    const $button = $(`.rate-message-btn[data-message-id="${messageId}"][data-rating="${rating}"]`);
                    $button.addClass('rated').prop('disabled', true);
                    
                    // Disable other rating buttons for this message
                    $(`.rate-message-btn[data-message-id="${messageId}"]`).prop('disabled', true);
                    
                    showNotification('Thank you for your feedback!', 'success');
                } else {
                    showNotification('Failed to save rating', 'error');
                }
            }
        });
    }

    // Enhanced notification system
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="notification ${type}">
                <div class="notification-content">
                    <i class="fas ${getNotificationIcon(type)}"></i>
                    <span>${message}</span>
                </div>
                <button class="notification-close">&times;</button>
            </div>
        `);
        
        $('body').append(notification);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            notification.fadeOut(300, () => notification.remove());
        }, 5000);
        
        // Manual close
        notification.find('.notification-close').on('click', () => {
            notification.fadeOut(300, () => notification.remove());
        });
    }

    function getNotificationIcon(type) {
        switch(type) {
            case 'success': return 'fa-check-circle';
            case 'error': return 'fa-exclamation-circle';
            case 'warning': return 'fa-exclamation-triangle';
            default: return 'fa-info-circle';
        }
    }

    // Utility functions
    function escapeHtml(text) {
        if (typeof text !== 'string') return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function scrollToBottom(element) {
        if (element) {
            element.scrollTop = element.scrollHeight;
        }
    }

    // Function to check if user has scrolled up from bottom
    function isUserScrolledUp(element) {
        if (!element) return false;
        const threshold = 50; // pixels from bottom
        return (element.scrollHeight - element.scrollTop - element.clientHeight) > threshold;
    }

    function scrollChatToBottom() {
        const chatMessages = document.getElementById('chat-messages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    // Auto-complete functionality for terminal input
    const commonCommands = [
        'dir', 'cd', 'copy', 'move', 'del', 'mkdir', 'rmdir', 'type', 'echo',
        'ping', 'ipconfig', 'netstat', 'tracert', 'nslookup',
        'tasklist', 'taskkill', 'systeminfo', 'ver', 'hostname', 'whoami',
        'help', 'cls', 'exit', 'sc', 'net', 'wmic'
    ];

    $('#terminal-input').on('input', function() {
        const input = $(this).val();
        const words = input.split(' ');
        const currentWord = words[words.length - 1];
        
        if (currentWord.length > 1) {
            const matches = commonCommands.filter(cmd => 
                cmd.toLowerCase().startsWith(currentWord.toLowerCase())
            );
            
            if (matches.length === 1 && matches[0] !== currentWord) {
                // Auto-complete suggestion
                const suggestion = input.substring(0, input.lastIndexOf(currentWord)) + matches[0];
                // Could implement auto-complete UI here
            }
        }
    });

    // Performance optimization: Limit terminal output lines
    function limitTerminalOutput() {
        $('.terminal-output').each(function() {
            const $output = $(this);
            const $entries = $output.find('.command-entry');
            
            if ($entries.length > 100) {
                // Remove oldest entries, keep last 100
                $entries.slice(0, $entries.length - 100).remove();
                
                // Add indicator that output was trimmed
                if (!$output.find('.output-trimmed').length) {
                    $output.prepend('<div class="output-trimmed"><em>... (older output trimmed) ...</em></div>');
                }
            }
        });
    }

    // Run performance optimization every 30 seconds
    setInterval(limitTerminalOutput, 30000);

    // Enhanced session management
    function handleSessionReconnection() {
        // Check if we need to reconnect to any sessions
        if (currentRemoteSessionId) {
            // Verify session is still active
            $.ajax({
                url: 'api.php',
                type: 'POST',
                data: {
                    action: 'ping_session',
                    csrf_token: CSRF_TOKEN
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status !== 'success') {
                        // Session expired, show warning
                        showNotification('Session may have expired. Please refresh the page.', 'warning');
                    }
                }
            });
        }
    }

    // Check session every 5 minutes
    setInterval(handleSessionReconnection, 300000);

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (currentRemoteSessionId) {
            // Try to end the current session
            navigator.sendBeacon('api.php', new URLSearchParams({
                action: 'end_session',
                session_id: currentRemoteSessionId,
                csrf_token: CSRF_TOKEN
            }));
        }
    });

    // Initialize welcome chat message after a delay
    setTimeout(function() {
        if ($('#chat-messages .chat-message').length === 0 && !chatInitialized) {
            addChatMessage('bot', 'Hello! I\'m your AI command assistant. I can help you with Windows commands, explain how to perform tasks, and suggest useful commands.\n\n**Quick Tips:**\n• Ask me "How do I...?" questions\n• Request command suggestions\n• Get help with command syntax\n• Understand command output\n\n**Keyboard Shortcuts:**\n• `Ctrl+L` - Clear terminal\n• `Ctrl+K` - Clear chat  \n• `Escape` - Focus terminal\n• `Ctrl+/` - Focus chat\n\nWhat would you like to know?');
            chatInitialized = true;
        }
    }, 2000);

    // Smart command suggestions based on context
    function suggestRelevantCommands() {
        if (!currentRemoteSessionId || !lastCommandSent) return;
        
        const timeSinceLastCommand = Date.now() - lastCommandSent.timestamp;
        
        // If it's been more than 30 seconds since last command, offer help
        if (timeSinceLastCommand > 30000 && Math.random() < 0.3) {
            const suggestions = [
                'Need help with your next command? Just ask!',
                'Looking for command suggestions? I can help!',
                'Want to explore what else you can do? Ask me for ideas!',
                'Stuck? Try asking me "What commands can I use here?"'
            ];
            
            const randomSuggestion = suggestions[Math.floor(Math.random() * suggestions.length)];
            addChatMessage('bot', `💡 **Tip**: ${randomSuggestion}`);
        }
    }

    // Run smart suggestions occasionally
    setInterval(suggestRelevantCommands, 45000);

    // Enhanced error handling for AJAX requests
    $(document).ajaxError(function(event, xhr, settings, thrownError) {
        if (xhr.status === 401) {
            showNotification('Session expired. Redirecting to login...', 'warning');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 2000);
        } else if (xhr.status === 403) {
            showNotification('Access denied. Please check your permissions.', 'error');
        } else if (xhr.status >= 500) {
            showNotification('Server error. Please try again later.', 'error');
        }
    });

    // Initialize drag and drop for file upload (future enhancement)
    $('.terminal-output').on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('drag-over');
    });

    $('.terminal-output').on('dragleave', function(e) {
        $(this).removeClass('drag-over');
    });

    $('.terminal-output').on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('drag-over');
        
        // Future: Handle file uploads
        addChatMessage('bot', '📁 **File Upload**: File upload functionality coming soon! For now, you can use commands like `copy` or `xcopy` to work with files.');
    });

    // Add loading states for better UX
    function setLoadingState(element, loading) {
        if (loading) {
            $(element).addClass('loading').prop('disabled', true);
        } else {
            $(element).removeClass('loading').prop('disabled', false);
        }
    }

    // Console debug information
    console.log('🚀 GhostCrew Terminal Enhanced v4.0 initialized');
    console.log('📊 Features loaded:');
    console.log('   ✅ AI Chatbot with contextual responses');
    console.log('   ✅ Command suggestions and execution');
    console.log('   ✅ Session management and history');
    console.log('   ✅ Enhanced terminal with autocomplete');
    console.log('   ✅ Keyboard shortcuts and context menus');
    console.log('   ✅ Smart notifications and error handling');
    console.log('   ✅ Performance optimizations');
    });
    
    // Function to update terminal prompt (defined globally for access from index.php)
    window.updateTerminalPrompt = function(workingDir) {
        const promptElement = $('#terminal-prompt');
        if (workingDir && workingDir !== '') {
            // Remove escaped characters and format properly
            let formattedPrompt = workingDir.replace(/\\\\/g, '\\'); // Convert \\ to \
            if (!formattedPrompt.endsWith('>')) {
                formattedPrompt += '>';
            }
            promptElement.text(formattedPrompt);
        } else {
            promptElement.text('$');
        }
    };

// Enhanced keyboard shortcuts
$(document).on('keydown', function(e) {
    // Ctrl+L to clear terminal
    if (e.ctrlKey && e.key === 'l' && activeHostId && currentRemoteSessionId) {
        e.preventDefault();
        clearTerminalDisplay();
    }
    
    // Ctrl+K to clear chat
    if (e.ctrlKey && e.key === 'k') {
        e.preventDefault();
        $('#chat-messages').empty();
        addChatMessage('bot', 'Chat cleared! How can I help you?');
    }
    
    // Escape to focus terminal input
    if (e.key === 'Escape') {
        $('#terminal-input').focus();
    }
    
    // Ctrl+/ to focus chat input
    if (e.ctrlKey && e.key === '/') {
        e.preventDefault();
        $('#chat-input').focus();
    }
});

// Context menu for terminal (right-click)
$(document).on('contextmenu', '.terminal-output', function(e) {
    e.preventDefault();
    
    // Create context menu
    const contextMenu = $(`
        <div class="context-menu" style="position: absolute; left: ${e.pageX}px; top: ${e.pageY}px;">
            <div class="context-menu-item" data-action="clear">
                <i class="fas fa-eraser"></i> Clear Terminal
            </div>
            <div class="context-menu-item" data-action="copy">
                <i class="fas fa-copy"></i> Copy All Output
            </div>
            <div class="context-menu-item" data-action="help">
                <i class="fas fa-question-circle"></i> Ask AI for Help
            </div>
        </div>
    `);
    
    $('body').append(contextMenu);
    
    // Handle context menu clicks
    contextMenu.on('click', '.context-menu-item', function() {
        const action = $(this).data('action');
        const $terminalOutput = $(e.target).closest('.terminal-output');
        
        switch(action) {
            case 'clear':
                // Get the current active session info from the DOM
                const $activeTerminal = $('.terminal-session.active');
                const activeTerminalId = $activeTerminal.attr('id');
                
                if (activeTerminalId && activeTerminalId !== 'welcome-terminal') {
                    // Extract host ID from terminal ID (format: hostId-terminal)
                    const currentHostId = activeTerminalId.replace('-terminal', '');
                    const $activeTab = $('.tab.active');
                    const currentSessionId = $activeTab.data('remote-session');
                    
                    if (currentHostId && currentSessionId && !$activeTab.hasClass('readonly')) {
                        clearTerminalDisplayWithIds(currentHostId, currentSessionId, $terminalOutput);
                    } else {
                        showNotification('Cannot clear this terminal session', 'warning');
                    }
                } else {
                    showNotification('No active terminal session to clear', 'warning');
                }
                break;
                
            case 'copy':
                const terminalText = $terminalOutput.text();
                navigator.clipboard.writeText(terminalText).then(() => {
                    showNotification('Terminal output copied to clipboard!', 'success');
                }).catch(() => {
                    showNotification('Failed to copy terminal output', 'error');
                });
                break;
                
            case 'help':
                $('#chat-input').val('Can you help me understand the recent terminal output?').focus();
                break;
        }
        
        contextMenu.remove();
    });
    
    // Remove context menu when clicking elsewhere
    $(document).one('click', () => contextMenu.remove());
});

// Function to clear terminal display and log the action
function clearTerminalDisplay() {
    if (!activeHostId || !currentRemoteSessionId) {
        showNotification('No active session to clear', 'warning');
        return;
    }
    
    clearTerminalDisplayWithIds(activeHostId, currentRemoteSessionId);
}

// Function to clear terminal display with specific IDs
function clearTerminalDisplayWithIds(hostId, sessionId, $terminalElement = null) {
    if (!hostId || !sessionId) {
        showNotification('Invalid session for clearing', 'error');
        return;
    }
    
    // Get terminal element if not provided
    const $terminal = $terminalElement || $(`#${hostId}-terminal .terminal-output`);
    
    if (!$terminal.length) {
        showNotification('Terminal not found', 'error');
        return;
    }
    
    // Clear the visual terminal
    $terminal.find('.command-entry').remove();
    
    // Add a visual indicator
    const clearIndicator = `<div class="terminal-clear-indicator">
        <p><i class="fas fa-broom"></i> Terminal display cleared by user</p>
        <p><small>Command history is preserved in session records</small></p>
    </div>`;
    $terminal.append(clearIndicator);
    
    // Log the clear action in the database
    logTerminalClearWithSession(sessionId);
    
    // Notify chat (only if this is the active session)
    if (sessionId === currentRemoteSessionId) {
        addChatMessage('bot', '🧹 **Terminal Cleared**\n\nDisplay cleared! Your command history is still preserved in the session records.');
    }
    
    // Show notification
    showNotification('Terminal display cleared', 'success');
}

// Function to log terminal clear action with specific session
function logTerminalClearWithSession(sessionId) {
    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: {
            action: 'log_terminal_clear',
            session_id: sessionId,
            csrf_token: CSRF_TOKEN
        },
        dataType: 'json',
        success: function(response) {
            console.log('Terminal clear logged for session:', sessionId);
        },
        error: function() {
            console.log('Failed to log terminal clear for session:', sessionId);
        }
    });
}

// Function to log terminal clear action
function logTerminalClear() {
    if (!currentRemoteSessionId) return;
    
    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: {
            action: 'log_terminal_clear',
            session_id: currentRemoteSessionId,
            csrf_token: CSRF_TOKEN
        },
        dataType: 'json',
        success: function(response) {
            console.log('Terminal clear logged');
        },
        error: function() {
            console.log('Failed to log terminal clear');
        }
    });
}

// Global helper functions
window.GhostCrewTerminal = {
    sendCommand: function(command) {
        $('#terminal-input').val(command);
        $('#send-command').click();
    },

    askAI: function(question) {
        $('#chat-input').val(question);
        $('#send-chat').click();
    },

    clearTerminal: function() {
        if (window.activeHostId && window.currentRemoteSessionId) {
            $(`#${window.activeHostId}-terminal .terminal-output .command-entry`).remove();
        }
    },

    clearChat: function() {
        $('#chat-messages').empty();
    }
};

// Function to handle host disconnection gracefully
function handleHostGracefulDisconnection(hostId) {
    // Mark host as disconnected in database
    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: {
            action: 'mark_host_disconnected',
            host_id: hostId,
            csrf_token: CSRF_TOKEN
        },
        dataType: 'json',
        success: function(response) {
            console.log('Host marked as disconnected:', hostId);
        }
    });
    
    // Update UI to show disconnected state
    const $hostItem = $(`#host-list li[data-hostid="${hostId}"]`);
    if ($hostItem.length) {
        $hostItem.find('.host-status').removeClass('online').addClass('disconnected').text('● Disconnected');
        $hostItem.addClass('disconnected-host');
    }
    
    // Add disconnection message to terminal if active
    if (activeHostId === hostId) {
        const $terminal = $(`#${hostId}-terminal .terminal-output`);
        if ($terminal.length && !$terminal.find('.connection-lost-message').length) {
            $terminal.append(`
                <div class="connection-lost-message">
                    <p><i class="fas fa-plug"></i> Host disconnected gracefully</p>
                    <p>The remote terminal was closed. This host will be removed from the active list.</p>
                </div>
            `);
        }
        
        // Disable terminal input
        $('#terminal-input').prop('disabled', true);
        $('#send-command').prop('disabled', true);
        
        // End the session
        if (currentRemoteSessionId) {
            endSession(currentRemoteSessionId);
            currentRemoteSessionId = null;
        }
    }
}