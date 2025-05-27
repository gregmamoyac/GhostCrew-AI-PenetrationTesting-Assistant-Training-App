// Enhanced Terminal App JavaScript with Authentication and Session Management
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

    // Initialize the application
    initApp();

    // Send command when Send button is clicked
    $('#send-command').on('click', function() {
        sendCommand();
    });

    // Send command when Enter key is pressed
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
        console.log('Initializing GhostCrew Terminal v2.0');
        
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
        setTimeout(function() {
            showSessionWarning();
        }, (SESSION_TIMEOUT - 300) * 1000); // 5 minutes before timeout
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
                handleHostDisconnection();
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
            hostHtml += `<div class="host-name">${host.hostname} ${statusIndicator}</div>`;
            hostHtml += `<div class="host-ip"><small>IP: ${host.ip_address}</small></div>`;
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
            sessionHtml += `<div><span class="session-status ${statusClass}"></span><strong>${session.hostname}</strong></div>`;
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
    }

    // Function to switch to a host
    function switchToHost(hostId) {
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
                    
                    console.log(`New session started: ${response.session_id}`);
                } else {
                    console.error('Failed to start session:', response.message);
                    showError('Failed to start new session: ' + response.message);
                }
            },
            error: function(xhr) {
                if (xhr.status === 401) {
                    window.location.href = 'login.php';
                } else {
                    showError('Network error while starting session');
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
            <span class="tab-name">${host.hostname}</span>
            <span class="tab-close" data-session="${hostId}">&times;</span>
        </div>`;
        $('#session-tabs').append(tabHtml);
        
        // Create new terminal session
        $('.terminal-session').removeClass('active');
        const terminalHtml = `
            <div class="terminal-session active" id="${hostId}-terminal">
                <div class="terminal-output">
                    <div class="welcome-message">
                        <p><i class="fas fa-desktop"></i> Connected to ${host.hostname}</p>
                        <p><strong>IP:</strong> ${host.ip_address}</p>
                        <p><strong>OS:</strong> ${host.os_info}</p>
                        <p><strong>Session ID:</strong> ${sessionId}</p>
                        <p>Ready to receive commands. Type commands below.</p>
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
        
        // Reset command history
        commandHistoryIndex = -1;
        commandHistory = [];
        
        // Load any existing command history for this session
        loadCommandHistory(sessionId);
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
                    showError('Failed to load session history: ' + response.message);
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
            <span class="tab-name">[HIST] ${session.hostname}</span>
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
            <p><strong>Host:</strong> ${session.hostname} (${session.ip_address})</p>
            <p><strong>Duration:</strong> ${session.start_time} - ${session.end_time || 'N/A'}</p>
        </div>`;
        
        // Add command history
        commands.forEach(function(cmd) {
            terminalHtml += `<div class="command-entry">
                <div class="command">$ ${escapeHtml(cmd.command)}</div>`;
            
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
            }
        });
        
        // Disable input for historical sessions
        $('#terminal-input').prop('disabled', true);
        $('#send-command').prop('disabled', true);
        
        // Switch to this session
        activeSessionId = tabId;
        activeHostId = null;
        currentRemoteSessionId = null;
        
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
            const $remainingTabs = $('#session-tabs .tab');
            if ($remainingTabs.length > 0) {
                const newSessionId = $($remainingTabs[0]).data('session');
                switchToSession(newSessionId);
            } else {
                switchToSession('welcome');
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
        } else if (isReadonly) {
            activeHostId = null;
            currentRemoteSessionId = null;
            $('#terminal-input').prop('disabled', true);
            $('#send-command').prop('disabled', true);
        } else {
            activeHostId = sessionId;
            currentRemoteSessionId = remoteSessionId;
            
            // Update active host in list
            $('#host-list li').removeClass('active');
            $(`#host-list li[data-hostid="${sessionId}"]`).addClass('active');
            
            // Enable input if host is still connected
            const hostExists = connectedHosts.find(h => h.host_id === sessionId);
            if (hostExists && remoteSessionId) {
                $('#terminal-input').prop('disabled', false).focus();
                $('#send-command').prop('disabled', false);
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
        
        // Store commands for history navigation
        commandHistory = commands.map(cmd => cmd.command);
        
        // Add each command and its output to the terminal
        $.each(commands, function(index, cmd) {
            let entryHtml = `<div class="command-entry">`;
            entryHtml += `<div class="command">$ ${escapeHtml(cmd.command)}</div>`;
            
            if (cmd.output !== null) {
                entryHtml += `<div class="result">${escapeHtml(cmd.output)}</div>`;
            } else {
                entryHtml += `<div class="result"><em>Waiting for response...</em></div>`;
            }
            
            entryHtml += `</div>`;
            
            $terminal.append(entryHtml);
        });
        
        // Scroll to the bottom of the terminal
        scrollToBottom($terminal[0]);
    }

    // Function to send a command
    function sendCommand() {
        const command = $('#terminal-input').val().trim();
        
        if (command === '' || !activeHostId || !currentRemoteSessionId) {
            return;
        }
        
        // Clear the input field
        $('#terminal-input').val('').focus();
        
        // Reset command history navigation
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
                    
                    let entryHtml = `<div class="command-entry">`;
                    entryHtml += `<div class="command">$ ${escapeHtml(command)}</div>`;
                    entryHtml += `<div class="result"><em>Executing...</em></div>`;
                    entryHtml += `</div>`;
                    
                    $terminal.append(entryHtml);
                    scrollToBottom($terminal[0]);
                    
                    // Add command to local history
                    if (!commandHistory.includes(command)) {
                        commandHistory.unshift(command);
                    }
                } else if (response.redirect) {
                    window.location.href = response.redirect;
                } else {
                    showError('Failed to send command: ' + response.message);
                }
            },
            error: function(xhr) {
                if (xhr.status === 401) {
                    window.location.href = 'login.php';
                } else {
                    showError('Network error while sending command');
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

    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showError(message) {
        console.error(message);
        // You could implement a toast notification system here
        alert(message); // Simple fallback
    }

    function scrollToBottom(element) {
        if (element) {
            element.scrollTop = element.scrollHeight;
        }
    }

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
});