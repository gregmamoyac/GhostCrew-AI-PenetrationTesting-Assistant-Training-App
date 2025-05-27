// Terminal App JavaScript
$(document).ready(function() {
    // Variables
    let activeHostId = null;
    let activeSessionId = 'welcome';
    let connectedHosts = [];
    let refreshInterval = null;
    let commandHistoryIndex = -1;
    let commandHistory = [];

    // Initialize the application
    initApp();

    // Copy setup command to clipboard
    $('#copy-command').on('click', function() {
        const command = $('code').text();
        navigator.clipboard.writeText(command).then(function() {
            alert('Command copied to clipboard!');
        });
    });

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
            // Don't allow closing the welcome tab
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

    // Function to initialize the application
    function initApp() {
        // Load system information
        loadSystemInfo();
        
        // Load hosts initially
        loadHosts();
        
        // Set up refresh interval to update hosts and check for command outputs
        refreshInterval = setInterval(function() {
            loadHosts();
            
            // If a host is active, check for command results
            if (activeHostId) {
                loadCommandHistory(activeHostId);
            }
        }, 3000); // Refresh every 3 seconds for better responsiveness
    }

    // Function to load system information
    function loadSystemInfo() {
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'get_system_info'
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const serverInfo = response.server_info;
                    const hostCount = response.host_count;
                    const commandCount = response.command_count;
                    
                    let infoHtml = `Server: ${serverInfo.server_name} | `;
                    infoHtml += `PHP: ${serverInfo.php_version} | `;
                    infoHtml += `Hosts: ${hostCount} | `;
                    infoHtml += `Commands: ${commandCount}`;
                    
                    $('#host-info').html(infoHtml);
                }
            }
        });
    }

    // Function to load connected hosts
    function loadHosts() {
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'get_hosts'
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    connectedHosts = response.hosts;
                    updateHostList();
                    
                    // Update connection status indicator
                    if (connectedHosts.length > 0) {
                        $('.status-indicator').removeClass('disconnected').addClass('connected');
                        $('.status-indicator').parent().text('Status: Connected');
                    } else {
                        $('.status-indicator').removeClass('connected').addClass('disconnected');
                        $('.status-indicator').parent().text('Status: Disconnected');
                    }
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
            
            // If there's an active host but it's no longer in the list, close its session
            if (activeHostId) {
                // Check if we already have a "lost connection" message for this host
                if (!$(`#${activeHostId}-terminal .connection-lost-message`).length) {
                    $(`#${activeHostId}-terminal .terminal-output`).append(
                        `<div class="connection-lost-message">
                            <p>Connection to host has been lost. The host is no longer responding.</p>
                            <p>You can wait for it to reconnect or close this tab.</p>
                        </div>`
                    );
                }
                
                // Disable the terminal input for this host
                $('#terminal-input').prop('disabled', true);
                $('#send-command').prop('disabled', true);
                
                // Add a "reconnecting" class to the tab
                $(`#session-tabs .tab[data-session="${activeHostId}"]`).addClass('reconnecting');
            }
            
            return;
        }
        
        $.each(connectedHosts, function(index, host) {
            const isActive = (host.host_id === activeHostId);
            const lastSeen = new Date(host.last_seen);
            const lastSeenFormatted = lastSeen.toLocaleString();
            const secondsSinceLastSeen = host.seconds_since_last_seen || 0;
            
            // Add a status indicator based on how recently the host has been seen
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
        
        // If active host is in the list, make sure we remove any "lost connection" message
        if (activeHostId && connectedHosts.find(h => h.host_id === activeHostId)) {
            $(`#${activeHostId}-terminal .connection-lost-message`).remove();
            $(`#session-tabs .tab[data-session="${activeHostId}"]`).removeClass('reconnecting');
            
            // Re-enable the terminal input
            $('#terminal-input').prop('disabled', false);
            $('#send-command').prop('disabled', false);
        }
    }

    // Function to switch to a host
    function switchToHost(hostId) {
        // If the host is already active, do nothing
        if (hostId === activeHostId) {
            return;
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
        
        // Create a new tab for this host if it doesn't exist
        if ($(`#session-tabs .tab[data-session="${hostId}"]`).length === 0) {
            $('#session-tabs .tab').removeClass('active');
            $('#session-tabs').append(`<div class="tab active" data-session="${hostId}">
                <span class="tab-name">${host.hostname}</span>
                <span class="tab-close" data-session="${hostId}">&times;</span>
            </div>`);
            
            // Create a new terminal session for this host
            $('.terminal-session').removeClass('active');
            $('.terminal-container').append(`
                <div class="terminal-session active" id="${hostId}-terminal">
                    <div class="terminal-output">
                        <div class="welcome-message">
                            <p>Connected to ${host.hostname} (${host.ip_address})</p>
                            <p>OS: ${host.os_info}</p>
                            <p>Type commands below to interact with this host.</p>
                        </div>
                    </div>
                </div>
            `);
            
            // Add event listener for the close button
            $(`.tab-close[data-session="${hostId}"]`).on('click', function(e) {
                e.stopPropagation(); // Prevent the tab from being activated
                closeSession(hostId);
            });
        } else {
            // Switch to the existing tab
            $('#session-tabs .tab').removeClass('active');
            $(`#session-tabs .tab[data-session="${hostId}"]`).addClass('active');
            
            // Switch to the existing terminal session
            $('.terminal-session').removeClass('active');
            $(`#${hostId}-terminal`).addClass('active');
        }
        
        // Enable the terminal input
        $('#terminal-input').prop('disabled', false).focus();
        $('#send-command').prop('disabled', false);
        
        // Load command history for this host
        loadCommandHistory(hostId);
        
        // Reset command history navigation
        commandHistoryIndex = -1;
        commandHistory = [];
    }
    
    // Function to close a session tab
    function closeSession(sessionId) {
        // Remove the tab
        $(`#session-tabs .tab[data-session="${sessionId}"]`).remove();
        
        // Remove the terminal session
        $(`#${sessionId}-terminal`).remove();
        
        // If this was the active session, switch to another one or the welcome tab
        if (sessionId === activeSessionId) {
            // Find another tab, or default to welcome
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
        // If this is a host session, update activeHostId
        if (sessionId !== 'welcome') {
            activeHostId = sessionId;
            
            // Update active host in the list
            $('#host-list li').removeClass('active');
            $(`#host-list li[data-hostid="${sessionId}"]`).addClass('active');
            
            // Enable the terminal input
            $('#terminal-input').prop('disabled', false).focus();
            $('#send-command').prop('disabled', false);
        } else {
            activeHostId = null;
            
            // Disable the terminal input for welcome screen
            $('#terminal-input').prop('disabled', true);
            $('#send-command').prop('disabled', true);
        }
        
        // Update active tab
        $('#session-tabs .tab').removeClass('active');
        $(`#session-tabs .tab[data-session="${sessionId}"]`).addClass('active');
        
        // Update active terminal session
        $('.terminal-session').removeClass('active');
        $(`#${sessionId}-terminal`).addClass('active');
        
        activeSessionId = sessionId;
    }

    // Function to load command history for a host
    function loadCommandHistory(hostId) {
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'get_command_history',
                host_id: hostId,
                limit: 50
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    updateTerminalWithHistory(hostId, response.commands);
                }
            }
        });
    }

    // Function to update terminal with command history
    function updateTerminalWithHistory(hostId, commands) {
        const $terminal = $(`#${hostId}-terminal .terminal-output`);
        
        // Clear existing command entries, but keep the welcome message
        $terminal.find('.command-entry').remove();
        
        // Sort commands by timestamp (oldest first)
        commands.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
        
        // Store commands for history navigation
        commandHistory = commands.map(cmd => cmd.command);
        
        // Add each command and its output to the terminal
        $.each(commands, function(index, cmd) {
            let entryHtml = `<div class="command-entry">`;
            entryHtml += `<div class="command">$ ${cmd.command}</div>`;
            
            if (cmd.output !== null) {
                entryHtml += `<div class="result">${cmd.output}</div>`;
            } else {
                entryHtml += `<div class="result"><em>Waiting for response...</em></div>`;
            }
            
            entryHtml += `</div>`;
            
            $terminal.append(entryHtml);
        });
        
        // Scroll to the bottom of the terminal
        $terminal.scrollTop($terminal[0].scrollHeight);
    }

    // Function to send a command
    function sendCommand() {
        const command = $('#terminal-input').val().trim();
        
        if (command === '' || !activeHostId) {
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
                command: command
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Add the command to the terminal immediately
                    const $terminal = $(`#${activeHostId}-terminal .terminal-output`);
                    
                    let entryHtml = `<div class="command-entry">`;
                    entryHtml += `<div class="command">$ ${command}</div>`;
                    entryHtml += `<div class="result"><em>Waiting for response...</em></div>`;
                    entryHtml += `</div>`;
                    
                    $terminal.append(entryHtml);
                    
                    // Scroll to the bottom of the terminal
                    $terminal.scrollTop($terminal[0].scrollHeight);
                    
                    // Add command to local history if not already there
                    if (!commandHistory.includes(command)) {
                        commandHistory.unshift(command);
                    }
                }
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
});