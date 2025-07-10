// js/enhanced_terminal.js - Complete Enhanced Terminal with Streaming Support

$(document).ready(function() {
    // Global Variables
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
    let chat_server = 'http://192.168.1.171:8090/chat';
    
    // Streaming support variables
    let streamingCommands = new Map(); // Track active streaming commands
    let streamingInterval = null;
    let userInputQueue = [];
    let isStreamingMode = false;
    let currentStreamingCommandId = null;
    let lastStreamingUpdate = '1970-01-01 00:00:00';
    let streamingOutputBuffer = '';
    let streamingCheckInterval = null;
    
    // ===========================================
    // EVENT HANDLERS
    // ===========================================
    
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
    
    // ===========================================
    // INITIALIZATION
    // ===========================================
    
    // Initialize the application
    initApp();
    
    function initApp() {
        console.log('Initializing GhostCrew Terminal v4.0 with Enhanced AI Assistant and Streaming Support');
        
        // Initialize chat interface
        initializeChatInterface();
        
        // Initialize streaming support
        initializeStreamingSupport();
        
        // Load initial data
        loadSystemInfo();
        loadHosts();
        loadHistoricalSessions();
        
        // Set up refresh interval
        refreshInterval = setInterval(function() {
            loadHosts();
            
            // If a host is active, check for command results and streaming
            if (activeHostId && currentRemoteSessionId) {
                loadCommandHistory(currentRemoteSessionId);
                checkForStreamingUpdates();
            }
        }, 2000); // Refresh every 2 seconds for better responsiveness
        
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
                addChatMessage('bot', 'Hello! I\'m your AI command assistant. I can help you with commands, explain how to perform tasks, and provide interactive session support.\n\n**Try asking me:**\n• "How do I use telnet?"\n• "Help with interactive commands"\n• "What can I do in this session?"\n\nWhat would you like to know?');
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
    
    // ===========================================
    // STREAMING SUPPORT
    // ===========================================
    
    // Initialize streaming support
    function initializeStreamingSupport() {
        console.log('🔄 Initializing streaming support...');
        
        // Set up keyboard shortcuts for interactive mode
        setupStreamingKeyboardShortcuts();
    }

    function startStreamingConnectionMonitor() {
        if (window.streamingConnectionInterval) {
            clearInterval(window.streamingConnectionInterval);
        }
        
        window.streamingConnectionInterval = setInterval(function() {
            if (isStreamingMode && currentRemoteSessionId && activeHostId) {
                // Check if host is still connected
                const currentHost = connectedHosts.find(h => h.host_id === activeHostId);
                if (!currentHost) {
                    console.warn('Host disconnected during streaming session');
                    showNotification('Host disconnected during interactive session', 'warning');
                    disableStreamingMode();
                    return;
                }
                
                // Check if host is delayed
                const secondsSinceLastSeen = currentHost.seconds_since_last_seen || 0;
                if (secondsSinceLastSeen > 120) { // 2 minutes
                    showNotification(`Host connection delayed (${Math.floor(secondsSinceLastSeen / 60)}m since last ping)`, 'warning');
                }
            }
        }, 10000); // Check every 10 seconds
    }

    function stopStreamingConnectionMonitor() {
        if (window.streamingConnectionInterval) {
            clearInterval(window.streamingConnectionInterval);
            window.streamingConnectionInterval = null;
        }
    }
    
    function setupStreamingKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            if (isStreamingMode) {
                // Ctrl+C to interrupt
                if (e.ctrlKey && e.key === 'c') {
                    e.preventDefault();
                    sendPredefinedInput('^C');
                }
                // Focus streaming input when typing
                else if (!e.ctrlKey && !e.altKey && e.key.length === 1) {
                    $('#streaming-input').focus();
                }
            }
        });
    }
    
    // Function to check for streaming updates
    function checkForStreamingUpdates() {
        if (!currentRemoteSessionId) return;
        
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'get_streaming_output',
                session_id: currentRemoteSessionId,
                last_update: lastStreamingUpdate,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.updates && response.updates.length > 0) {
                    processStreamingUpdates(response.updates);
                }
            },
            error: function() {
                // Silently fail - this runs frequently
            }
        });
    }
    
    function processStreamingUpdates(updates) {
        console.log('Processing streaming updates:', updates.length);
        
        updates.forEach(function(update) {
            const commandId = update.command_id;
            const output = update.output_chunk;
            const isPartial = update.is_partial;
            const lastUpdate = update.last_update;
            const commandStatus = update.status;
            const streamingStatus = update.streaming_status;
            
            // Update last update timestamp
            if (lastUpdate > lastStreamingUpdate) {
                lastStreamingUpdate = lastUpdate;
            }
            
            // Find the terminal for this command
            const $terminal = $(`#${activeHostId}-terminal .terminal-output`);
            
            // Find the command entry for this command ID
            let $commandEntry = $terminal.find(`[data-command-id="${commandId}"]`);
            
            if ($commandEntry.length === 0) {
                // Create new command entry for streaming
                const commandText = update.command || 'Interactive Command';
                const currentPrompt = $('#terminal-prompt').text().replace(/\\\\/g, '\\');
                
                let entryHtml = `<div class="command-entry streaming-command" data-command-id="${commandId}">`;
                entryHtml += `<div class="prompt-line">${escapeHtml(currentPrompt)} ${escapeHtml(commandText)}</div>`;
                entryHtml += `<div class="result streaming-output" data-command-id="${commandId}"></div>`;
                entryHtml += `</div>`;
                
                $terminal.append(entryHtml);
                $commandEntry = $terminal.find(`[data-command-id="${commandId}"]`);
            }
            
            // Update the output
            const $output = $commandEntry.find('.result');
            let $pre = $output.find('pre');

            // Create pre element if it doesn't exist
            if ($pre.length === 0) {
                $output.html('<pre></pre>');
                $pre = $output.find('pre');
            }

            if (isPartial) {
                // For partial updates, append new content instead of replacing all
                const currentContent = $pre.html();
                const newContent = escapeHtml(output);
                
                // Only update if the new content is different and longer
                if (newContent.length > currentContent.replace(/<[^>]*>/g, '').length) {
                    $pre.html(newContent);
                }
            } else {
                // For final updates, replace all content and mark as completed
                $pre.html(escapeHtml(output));
                $commandEntry.removeClass('streaming-command').addClass('completed-command');
                
                // Add completion indicator without overwriting content
                if (output && output.trim()) {
                    $output.append('<div class="session-completed"><small><i class="fas fa-check-circle"></i> Interactive session completed</small></div>');
                }
            }
            
            // Handle interactive mode
            if (streamingStatus === 'active' && !isStreamingMode) {
                enableStreamingMode(commandId);
            } else if (streamingStatus === 'completed' && isStreamingMode && currentStreamingCommandId === commandId) {
                disableStreamingMode();
            }
            
            // Auto-scroll to bottom
            scrollToBottom($terminal[0]);
        });
    }
    
    function enableStreamingMode(commandId) {
        if (!isStreamingMode) {
            isStreamingMode = true;
            currentStreamingCommandId = commandId;
            
            console.log(`🔴 Enabling streaming mode for command ${commandId}`);
            
            // Keep the regular terminal input enabled - don't disable it
            $('#terminal-input').prop('disabled', false).focus();
            $('#send-command').prop('disabled', false);
            
            // Update UI indicators
            $(`#session-tabs .tab[data-session="${activeHostId}"]`).addClass('streaming');
            
            // Update terminal prompt to show command name
            updateTerminalPromptForStreaming(commandId);
            
            // Start connection monitoring
            startStreamingConnectionMonitor();
            
            // Notify chat
            addChatMessage('bot', '🔴 **Interactive Mode Active**\n\nUse the main terminal input to send commands to the interactive session.\n\n**Tips:**\n• Type commands and press Enter\n• Use Ctrl+C to interrupt\n• Click Terminate to end session');
        }
    }

    function disableStreamingMode() {
        if (isStreamingMode) {
            isStreamingMode = false;
            currentStreamingCommandId = null;
            
            console.log('⚪ Disabling streaming mode');
            
            // Keep terminal input enabled
            $('#terminal-input').prop('disabled', false).focus();
            $('#send-command').prop('disabled', false);
            
            // Update UI indicators
            $(`#session-tabs .tab[data-session="${activeHostId}"]`).removeClass('streaming');
            
            // Restore normal prompt with a slight delay to avoid conflict
            setTimeout(function() {
                restoreNormalPrompt();
            }, 500);
            
            // Stop connection monitoring
            stopStreamingConnectionMonitor();
            
            // Notify chat
            addChatMessage('bot', '⚪ **Interactive Mode Ended**\n\nReturned to normal command mode.');
            
            // Force a command history refresh to get the latest prompt
            setTimeout(function() {
                if (currentRemoteSessionId) {
                    loadCommandHistory(currentRemoteSessionId);
                }
            }, 1000);
        }
    }

    function restoreNormalPrompt() {
        // Try to get the last working directory from completed commands
        if (currentRemoteSessionId) {
            $.ajax({
                url: 'api.php',
                type: 'POST',
                data: {
                    action: 'get_command_history',
                    session_id: currentRemoteSessionId,
                    limit: 10,
                    csrf_token: CSRF_TOKEN
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        // Look for the most recent working directory
                        let lastWorkingDir = '>';
                        response.commands.forEach(function(cmd) {
                            if (cmd.working_directory && cmd.status === 'completed') {
                                lastWorkingDir = cmd.working_directory.endsWith('>') ? 
                                    cmd.working_directory : cmd.working_directory + '>';
                            }
                        });
                        updateTerminalPrompt(lastWorkingDir);
                    } else {
                        // Fallback to simple prompt
                        updateTerminalPrompt('>');
                    }
                },
                error: function() {
                    // Fallback to simple prompt
                    updateTerminalPrompt('>');
                }
            });
        } else {
            updateTerminalPrompt('>');
        }
    }

    function updateTerminalPromptForStreaming(commandId) {
        // Get the command name from the command entry
        const $commandEntry = $(`.command-entry[data-command-id="${commandId}"]`);
        if ($commandEntry.length > 0) {
            const commandText = $commandEntry.find('.prompt-line').text();
            // Extract just the command name (first word after the >)
            const commandMatch = commandText.match(/>\s*(\w+)/);
            if (commandMatch) {
                const commandName = commandMatch[1];
                // Update the actual terminal prompt element
                $('#terminal-prompt').text(`${commandName}>`);
                return;
            }
        }
        
        // Fallback to generic interactive prompt
        $('#terminal-prompt').text('interactive>');
    }
    
    function sendStreamingInput() {
        const input = $('#streaming-input').val();
        if (!input || !isStreamingMode || !currentRemoteSessionId) {
            return;
        }
        
        // Clear input
        $('#streaming-input').val('').focus();
        
        // Send input to server
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'send_user_input',
                session_id: currentRemoteSessionId,
                host_id: activeHostId,
                input: input,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    console.log('Sent user input:', input);
                    // Show input echo in terminal
                    showInputEcho(input);
                } else {
                    showNotification('Failed to send input: ' + (response.message || 'Unknown error'), 'error');
                }
            },
            error: function() {
                showNotification('Network error while sending input', 'error');
            }
        });
    }
    
    function sendPredefinedInput(input) {
        if (!isStreamingMode || !currentRemoteSessionId) {
            return;
        }
        
        // Handle special control characters
        let actualInput = input;
        if (input === '^C') {
            actualInput = String.fromCharCode(3); // Ctrl+C
        }
        
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'send_user_input',
                session_id: currentRemoteSessionId,
                host_id: activeHostId,
                input: actualInput,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // REMOVE THIS LINE - don't show immediate echo
                    // showInputEcho(input, true);
                } else {
                    showNotification('Failed to send control input', 'error');
                }
            }
        });
    }
    
    function showInputEcho(input, isControl = false) {
        const $terminal = $(`#${activeHostId}-terminal .terminal-output`);
        const $commandEntry = $terminal.find(`[data-command-id="${currentStreamingCommandId}"]`);
        
        if ($commandEntry.length > 0) {
            let $output = $commandEntry.find('.result');
            let $pre = $output.find('pre');
            
            // Create pre element if it doesn't exist
            if ($pre.length === 0) {
                $output.html('<pre></pre>');
                $pre = $output.find('pre');
            }
            
            // Get current content
            let currentContent = $pre.html();
            
            // Use a simple interactive prompt instead of the working directory prompt
            const interactivePrompt = getInteractivePrompt(currentStreamingCommandId);
            const displayInput = isControl ? `<span class="control-input">[${input}]</span>` : escapeHtml(input);
            
            // Append the new input to existing content
            const newLine = currentContent ? '\n' : '';
            $pre.html(currentContent + newLine + interactivePrompt + ' ' + displayInput);
            
            scrollToBottom($terminal[0]);
        }
    }

    // Add this helper function if it doesn't exist
    function getInteractivePrompt(commandId) {
        const $commandEntry = $(`.command-entry[data-command-id="${commandId}"]`);
        if ($commandEntry.length > 0) {
            const commandText = $commandEntry.find('.prompt-line').text();
            // Extract just the command name (first word after the >)
            const commandMatch = commandText.match(/>\s*(\w+)/);
            if (commandMatch) {
                const commandName = commandMatch[1];
                return commandName + '>';
            }
        }
        return 'interactive>';
    }
    
    // ===========================================
    // DATA LOADING FUNCTIONS
    // ===========================================
    
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

            // Update page title with zero hosts
            updatePageTitle(0);
            
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
        // Update page title with current host count
        updatePageTitle(connectedHosts.length);
    }

    function updatePageTitle(hostCount) {
        const user = USERNAME || 'User';
        const activeSessions = $('.tab:not([data-session="welcome"])').length;
        document.title = `GhostCrew - ${user} | Hosts: ${hostCount} | Sessions: ${activeSessions}`;
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
    
    // ===========================================
    // COMMAND FUNCTIONS
    // ===========================================
    
    // Enhanced sendCommand function with streaming detection
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
        
        // Check if this might be an interactive command
        const interactivePatterns = [
            /^(telnet|ssh|ftp|sftp|msfconsole)\s*/,
            /^(mysql|psql)\s+.*-[uU]/,
            /^python3?(\s|$)/,
            /^(node|irb|bc|gdb)(\s|$)/,
            /^(vim|nano|less|more)\s+/,
            /^(top|htop|watch)\s+/,
            /^ping\s+/,
            /^tail\s+.*-f/
        ];
        
        const isLikelyInteractive = interactivePatterns.some(pattern => pattern.test(command)) || 
                                ['msfconsole', 'telnet', 'ssh', 'python', 'python3', 'mysql', 'psql'].includes(command.split(' ')[0]);
        
        if (isLikelyInteractive) {
            // Warn user about interactive command
            addChatMessage('bot', `🔄 **Executing Interactive Command**\n\nDetected potentially interactive command: **${command}**\n\nIf this command requires input, you'll see interactive controls appear below the terminal.`);
        }
        
        // Check if we're in streaming mode - send as user input instead
        if (isStreamingMode && currentStreamingCommandId) {
            // First check if the streaming session is actually still active
            $.ajax({
                url: 'api.php',
                type: 'POST',
                data: {
                    action: 'get_streaming_status',
                    session_id: currentRemoteSessionId,
                    csrf_token: CSRF_TOKEN
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success' && response.active_sessions && response.active_sessions.length > 0) {
                        // Check if our specific command is still active
                        const activeSession = response.active_sessions.find(s => s.command_id == currentStreamingCommandId);
                        if (activeSession && activeSession.status === 'active') {
                            // Session is still active, send as interactive input
                            sendInteractiveInput(command);
                        } else {
                            // Session is not active, disable streaming mode and send as regular command
                            console.log('Streaming session ended, switching to normal mode');
                            disableStreamingMode();
                            sendRegularCommand(command);
                        }
                    } else {
                        // No active sessions, disable streaming mode and send as regular command
                        console.log('No active streaming sessions, switching to normal mode');
                        disableStreamingMode();
                        sendRegularCommand(command);
                    }
                },
                error: function() {
                    // On error, assume session ended and send as regular command
                    console.log('Error checking streaming status, switching to normal mode');
                    disableStreamingMode();
                    sendRegularCommand(command);
                }
            });
            return; // Don't execute the regular command sending below
        }
        
        // If we're not in streaming mode, send as regular command
        sendRegularCommand(command);
    }

    // Add these helper functions at the end of the enhanced_terminal.js file:
    function sendInteractiveInput(command) {
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'send_user_input',
                session_id: currentRemoteSessionId,
                host_id: activeHostId,
                input: command,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    console.log('Sent interactive input:', command);
                    // REMOVE THIS LINE - don't show immediate echo
                    // showInputEcho(command);
                } else {
                    showNotification('Failed to send input: ' + (response.message || 'Unknown error'), 'error');
                }
            },
            error: function() {
                showNotification('Network error while sending input', 'error');
            }
        });
    }

    function sendRegularCommand(command) {
        // Send the command to the server (normal mode)
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
                    const commandId = response.command_id;
                    const isInteractive = response.is_interactive;
                    
                    console.log('Command sent successfully. ID:', commandId, 'Interactive:', isInteractive);
                    
                    // REMOVE THIS ENTIRE SECTION - don't add command to terminal immediately
                    /*
                    const $terminal = $(`#${activeHostId}-terminal .terminal-output`);
                    const currentPrompt = $('#terminal-prompt').text().replace(/\\\\/g, '\\');

                    let entryHtml = `<div class="command-entry" data-command-id="${commandId}">`;
                    entryHtml += `<div class="prompt-line">${escapeHtml(currentPrompt)} ${escapeHtml(command)}</div>`;
                    
                    if (isInteractive) {
                        entryHtml += `<div class="result streaming-output" data-command-id="${commandId}"><em>Starting interactive session...</em></div>`;
                    } else {
                        entryHtml += `<div class="result"><em>Executing...</em></div>`;
                    }
                    
                    entryHtml += `</div>`;
                    
                    $terminal.append(entryHtml);
                    scrollToBottom($terminal[0]);
                    */
                    
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
        
        // Get existing command IDs to avoid duplicates
        const existingCommandIds = new Set();
        $terminal.find('.command-entry[data-command-id]').each(function() {
            existingCommandIds.add(parseInt($(this).data('command-id')));
        });
        
        // Sort commands by timestamp (oldest first)
        commands.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
        
        // Store commands for history navigation and update session storage
        const sessionCommands = commands.map(cmd => cmd.command);
        sessionCommandHistories[sessionId] = sessionCommands;
        
        if (sessionId === currentRemoteSessionId) {
            commandHistory = sessionCommands;
        }
        
        let lastWorkingDir = '>';
        
        // Update working directory tracking for existing commands
        $terminal.find('.command-entry[data-command-id]').each(function() {
            const $entry = $(this);
            const commandId = parseInt($entry.data('command-id'));
            const matchingCommand = commands.find(cmd => parseInt(cmd.id) === commandId);
            
            if (matchingCommand && matchingCommand.working_directory) {
                lastWorkingDir = matchingCommand.working_directory.endsWith('>') ? 
                    matchingCommand.working_directory : matchingCommand.working_directory + '>';
            }
        });
        
        // Add only new commands to the terminal
        $.each(commands, function(index, cmd) {
            // Skip if command already exists
            if (existingCommandIds.has(parseInt(cmd.id))) {
                return; // Skip this command
            }
            
            // Update working directory if available
            if (cmd.working_directory) {
                lastWorkingDir = cmd.working_directory.endsWith('>') ? cmd.working_directory : cmd.working_directory + '>';
            }
            
            // Fix escaped slashes in display
            let displayDir = lastWorkingDir.replace(/\\\\/g, '\\');
            
            let entryHtml = `<div class="command-entry" data-command-id="${cmd.id}">`;
            entryHtml += `<div class="prompt-line">${escapeHtml(displayDir)} ${escapeHtml(cmd.command)}</div>`;
            
            if (cmd.output !== null) {
                if (cmd.is_interactive && cmd.status === 'executing') {
                    // For interactive commands that are still executing
                    entryHtml += `<div class="result streaming-output" data-command-id="${cmd.id}">`;
                    entryHtml += `<pre>${escapeHtml(cmd.output || 'Interactive session running...')}</pre>`;
                    entryHtml += `</div>`;
                    
                    // Mark as streaming command
                    entryHtml = entryHtml.replace('command-entry', 'command-entry streaming-command');
                } else {
                    entryHtml += `<div class="result"><pre>${escapeHtml(cmd.output)}</pre></div>`;
                }
            } else {
                entryHtml += `<div class="result"><em>Waiting for response...</em></div>`;
            }
            
            entryHtml += `</div>`;
            
            $terminal.append(entryHtml);
        });
        
        // Update existing commands that might have new output
        $.each(commands, function(index, cmd) {
            if (existingCommandIds.has(parseInt(cmd.id))) {
                const $existingEntry = $terminal.find(`[data-command-id="${cmd.id}"]`);
                const $existingResult = $existingEntry.find('.result');
                
                // Only update if there's new output or status change
                if (cmd.output !== null) {
                    if (cmd.is_interactive && cmd.status === 'executing') {
                        $existingResult.html(`<pre>${escapeHtml(cmd.output || 'Interactive session running...')}</pre>`);
                        $existingEntry.addClass('streaming-command');
                    } else if (cmd.status === 'completed') {
                        $existingResult.html(`<pre>${escapeHtml(cmd.output)}</pre>`);
                        $existingEntry.removeClass('streaming-command').addClass('completed-command');
                    }
                }
            }
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
    
    // ===========================================
    // SESSION MANAGEMENT
    // ===========================================
    
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
                    
                    // Reset streaming state for new session
                    lastStreamingUpdate = '1970-01-01 00:00:00';
                    
                    // Notify about new session
                    addChatMessage('bot', `🖥️ **New Session Started**\n\nConnected to **${host.hostname}** (${host.ip_address})\nSession ID: ${response.session_id}\n\nI'm here to help with commands and interactive sessions!`);
                    
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
                        <p>Ready to receive commands. Interactive commands will show special controls when needed.</p>
                    </div>
                </div>
            </div>`;
        $('.terminal-container').append(terminalHtml);

        // Add close event
        $(`.tab-close[data-session="${hostId}"]`).on('click', function(e) {
            e.stopPropagation();
            closeSession(hostId);
            
            if ($('.tab').length === 1) {
                $('.tab[data-session="welcome"]').addClass('active');
                $('#welcome-terminal').addClass('active');
                updateTerminalPrompt();
            }
        });
        
        // Enable terminal input
        $('#terminal-input').prop('disabled', false).focus();
        $('#send-command').prop('disabled', false);
        
        // Reset command history for this session
        commandHistoryIndex = -1;
        commandHistory = sessionCommandHistories[sessionId] || [];
        
        // Update terminal prompt
        updateTerminalPrompt('>');
        
        // Load any existing command history for this session
        loadCommandHistory(sessionId);
        updatePageTitle(connectedHosts.length);
    }
    
    // Function to initialize chat for a session
    function initializeChatForSession(sessionId) {
        currentConversationId = `conv_${sessionId}_${Date.now()}`;
        
        // Load existing chat history if any
        loadChatHistory(sessionId);
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
            
            // Disable streaming mode
            if (isStreamingMode) {
                disableStreamingMode();
            }
        } else if (isReadonly) {
            activeHostId = null;
            currentRemoteSessionId = null;
            $('#terminal-input').prop('disabled', true);
            $('#send-command').prop('disabled', true);
            updateTerminalPrompt();
            commandHistory = [];
            commandHistoryIndex = -1;
            
            // Disable streaming mode
            if (isStreamingMode) {
                disableStreamingMode();
            }
        } else {
            activeHostId = sessionId;
            currentRemoteSessionId = remoteSessionId;
            
            // Reset streaming state for session switch
            lastStreamingUpdate = '1970-01-01 00:00:00';
            
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
                
                // Check for active streaming sessions
                setTimeout(checkForStreamingUpdates, 1000);
            } else {
                $('#terminal-input').prop('disabled', true);
                $('#send-command').prop('disabled', true);
            }
        }
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
            // Always switch to welcome tab when closing active session
            switchToSession('welcome');
            $('.tab[data-session="welcome"]').addClass('active');
            $('#welcome-terminal').addClass('active');
            activeSessionId = 'welcome';
            activeHostId = null;
            currentRemoteSessionId = null;
            
            // Reset terminal state
            $('#terminal-input').prop('disabled', true);
            $('#send-command').prop('disabled', true);
            updateTerminalPrompt();
            
            // Disable streaming mode
            if (isStreamingMode) {
                disableStreamingMode();
            }
        }
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
                <div class="prompt-line">> ${escapeHtml(cmd.command)}</div>`;
            
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
    
    // ===========================================
    // CHAT FUNCTIONS
    // ===========================================
    
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
            url: chat_server,
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
    
    // ===========================================
    // UTILITY FUNCTIONS
    // ===========================================
    
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
            
            // Disable streaming mode
            if (isStreamingMode) {
                disableStreamingMode();
            }
            
            // End the session
            if (currentRemoteSessionId) {
                endSession(currentRemoteSessionId);
                currentRemoteSessionId = null;
            }
        }
    }
    
    // Expose functions globally for access from other scripts
    window.activeHostId = activeHostId;
    window.currentRemoteSessionId = currentRemoteSessionId;
    window.isStreamingMode = isStreamingMode;
    
});

// ===========================================
// GLOBAL FUNCTIONS AND EVENT HANDLERS
// ===========================================

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
/*
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
*/

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

function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.connection-tab-content');
    tabContents.forEach(tab => tab.classList.remove('active'));
    
    // Remove active class from all buttons
    const tabButtons = document.querySelectorAll('.connection-tab-button');
    tabButtons.forEach(button => button.classList.remove('active'));
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}

function copyToClipboard(elementId, buttonElement) {
    const text = document.getElementById(elementId).textContent;
    const button = buttonElement || document.querySelector(`button[onclick*="${elementId}"]`);
    
    // Function to show visual feedback
    function showFeedback(success = true) {
        if (button) {
            const originalHTML = button.innerHTML;
            const originalBackground = button.style.background || getComputedStyle(button).background;
            
            button.innerHTML = success ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>';
            button.style.background = success ? 'var(--accent-green)' : '#dc3545';
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.style.background = originalBackground;
            }, 2000);
        }
    }
    
    // Try modern clipboard API first
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showFeedback(true);
        }).catch(err => {
            console.warn('Clipboard API failed, trying fallback:', err);
            fallbackCopy();
        });
    } else {
        // Use fallback method
        fallbackCopy();
    }
    
    function fallbackCopy() {
        try {
            // Create a temporary textarea element
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            
            // Select and copy the text
            textArea.focus();
            textArea.select();
            
            // For mobile compatibility
            textArea.setSelectionRange(0, 99999);
            
            const successful = document.execCommand('copy');
            document.body.removeChild(textArea);
            
            if (successful) {
                showFeedback(true);
            } else {
                throw new Error('execCommand failed');
            }
        } catch (err) {
            console.error('All copy methods failed:', err);
            showFeedback(false);
            
            // Last resort: show a prompt with the text
            if (confirm('Copy failed. Click OK to show the command in a dialog box so you can copy it manually.')) {
                prompt('Copy this command:', text);
            }
        }
    }
}