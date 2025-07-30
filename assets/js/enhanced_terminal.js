// js/enhanced_terminal.js - Complete Enhanced Terminal with Streaming Support

// AI Endpoint Configuration
const AI_CONFIG = {
    // Replace with your actual AI endpoint
    endpoint: 'https://zl47lm7yy1.execute-api.us-east-2.amazonaws.com/invoke',
    
    // Request timeout in milliseconds
    timeout: 30000,
    
    // Maximum prompt length to prevent oversized requests
    maxPromptLength: 2000,
    
    // Whether to include recent commands in context
    includeCommandHistory: true,
    
    // Maximum number of recent commands to include
    maxRecentCommands: 3,
    
    // Whether to include system information in prompts
    includeSystemContext: true,
    
    // Custom headers if needed (e.g., for authentication)
    headers: {
        'Content-Type': 'application/json',
        // 'X-API-Key': 'YOUR-API-KEY' // Uncomment if needed
    }
};

// Command validation patterns
const COMMAND_VALIDATION = {
    // Maximum length for suggested commands
    maxCommandLength: 200,
    
    // Allowed command patterns (add more as needed)
    allowedPatterns: [
        /^[a-zA-Z][a-zA-Z0-9\-_\s\.\/\\]*$/, // Basic alphanumeric with common chars
        /^cd\s+.+/, // Change directory
        /^dir(\s+.*)?$/, // Directory listing
        /^ping\s+.+/, // Network ping
        /^ipconfig(\s+.*)?$/, // IP configuration
        /^netstat(\s+.*)?$/, // Network statistics
        /^tasklist(\s+.*)?$/, // Task list
        /^systeminfo(\s+.*)?$/, // System information
        /^help(\s+.*)?$/, // Help commands
        /^echo\s+.+/, // Echo commands
        /^type\s+.+/, // Type commands
        /^copy\s+.+/, // Copy commands
        /^move\s+.+/, // Move commands
        /^del\s+.+/, // Delete commands
        /^mkdir\s+.+/, // Make directory
        /^rmdir\s+.+/ // Remove directory
    ],
    
    // Forbidden patterns for security
    forbiddenPatterns: []
};

// Response processing configuration
const RESPONSE_CONFIG = {
    // Keywords to identify different command categories
    categoryKeywords: {
        'file_operations': ['file', 'folder', 'directory', 'copy', 'move', 'delete', 'dir', 'ls', 'mkdir', 'rmdir'],
        'network': ['ping', 'telnet', 'ssh', 'ftp', 'network', 'connection', 'ipconfig', 'netstat'],
        'system_info': ['system', 'info', 'version', 'hardware', 'computer', 'systeminfo', 'whoami'],
        'processes': ['process', 'task', 'running', 'kill', 'service', 'tasklist', 'taskkill'],
        'interactive': ['telnet', 'ssh', 'msfconsole', 'mysql', 'python', 'cmd', 'powershell'],
        'help': ['help', 'documentation', 'manual', 'guide', 'tutorial', 'how to']
    },
    
    // Patterns to extract commands from AI responses
    commandExtractionPatterns: [
        /```(?:cmd|bash|shell|powershell)?\s*\n?([^`]+)```/gi, // Code blocks
        /`([^`]+)`/g, // Inline code
        /(?:run|execute|try|use|type)\s*:\s*([^\n\r]+)/gi, // "run: command" patterns
        /(?:command|cmd)\s*:\s*([^\n\r]+)/gi, // "command: xyz" patterns
        /^\s*>\s*([^\n\r]+)/gm // Lines starting with >
    ]
};

// Usage analytics (optional)
const ANALYTICS_CONFIG = {
    // Whether to log interactions for improvement
    logInteractions: true,
    
    // Whether to track command suggestions and execution
    trackSuggestions: true,
    
    // Whether to collect feedback ratings
    collectFeedback: true
};

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
let AI_ENDPOINT = 'https://zl47lm7yy1.execute-api.us-east-2.amazonaws.com/invoke';

// Streaming support variables
let streamingCommands = new Map(); // Track active streaming commands
let streamingInterval = null;
let userInputQueue = [];
let isStreamingMode = false;
let currentStreamingCommandId = null;
let lastStreamingUpdate = '1970-01-01 00:00:00';
let streamingOutputBuffer = '';
let streamingCheckInterval = null;
let chatContext = [];

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

function scrollToBottomOfChat() {
    const chatMessages = document.getElementById('chat-messages');
    if (chatMessages) {
        chatMessages.scrollTo({
            top: chatMessages.scrollHeight,
            behavior: 'smooth'
        });
    }
}

function scrollToTopOfChat() {
    const chatMessages = document.getElementById('chat-messages');
    if (chatMessages) {
        chatMessages.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }
}

// Replace with this enhanced version:
function scrollChatToShowMessage(messageElement = null, behavior = 'smooth') {
    const chatMessages = document.getElementById('chat-messages');
    if (!chatMessages) return;
    
    if (messageElement) {
        // Scroll to show a specific message
        messageElement.scrollIntoView({ 
            behavior: behavior,
            block: 'nearest',
            inline: 'nearest'
        });
    } else {
        // Default behavior - scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
}



function scrollChatToShowUserMessage() {
    const chatMessages = document.getElementById('chat-messages');
    if (!chatMessages) return;
    
    // Find the last user message
    const userMessages = chatMessages.querySelectorAll('.chat-message.user');
    if (userMessages.length > 0) {
        const lastUserMessage = userMessages[userMessages.length - 1];
        
        // Scroll to show the user message with some context
        lastUserMessage.scrollIntoView({ 
            behavior: 'smooth',
            block: 'start',
            inline: 'nearest'
        });
    }
}

function initializeChatScrollMonitor() {
    const chatMessages = document.getElementById('chat-messages');
    if (!chatMessages) return;
    
    // Create scroll indicator
    const scrollIndicator = $(`
        <button class="chat-scroll-indicator" title="Scroll to bottom">
            <i class="fas fa-chevron-down"></i> New messages
        </button>
    `);
    
    $('.chat-section').append(scrollIndicator);
    
    // Monitor scroll position
    $(chatMessages).on('scroll', function() {
        const isNearBottom = isUserNearBottom(100);
        
        if (isNearBottom) {
            scrollIndicator.removeClass('visible');
        } else {
            // Check if there are messages below
            const totalHeight = this.scrollHeight;
            const currentScroll = this.scrollTop + this.clientHeight;
            
            if (totalHeight - currentScroll > 100) {
                scrollIndicator.addClass('visible');
            }
        }
    });
    
    // Click handler for scroll indicator
    scrollIndicator.on('click', function() {
        scrollToBottomOfChat();
        $(this).removeClass('visible');
    });
}

function isUserNearBottom(threshold = 100) {
    const chatMessages = document.getElementById('chat-messages');
    if (!chatMessages) return true;
    
    return (chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight) < threshold;
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

// Updated function to format bot messages with proper command ID handling (UPDATED)
function formatBotMessage(message, suggestedCommands = []) {
    if (!message || typeof message !== 'string') {
        return 'Invalid message received.';
    }
    
    // Ensure suggestedCommands is always an array
    if (!Array.isArray(suggestedCommands)) {
        suggestedCommands = [];
    }
    
    // First, escape any HTML to prevent XSS
    message = message.replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
    
    // Handle code blocks FIRST - preserve them exactly as they are
    const codeBlocks = [];
    let codeBlockIndex = 0;
    
    // Extract and preserve code blocks
    message = message.replace(/```([^`]*?)```/g, function(match, content) {
        const placeholder = `__CODEBLOCK_${codeBlockIndex}__`;
        codeBlocks[codeBlockIndex] = content.trim();
        codeBlockIndex++;
        return placeholder;
    });
    
    // Convert line breaks to HTML
    message = message.replace(/\n/g, '<br>');
    
    // Convert markdown formatting
    message = message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>'); // Bold
    message = message.replace(/(?<!\*)\*([^*\n]+)\*(?!\*)/g, '<em>$1</em>'); // Italic
    
    // Handle inline code with load buttons
    message = processInlineCommands(message, suggestedCommands);
    
    // Convert bullet points
    message = message.replace(/^• (.+)$/gm, '<div class="bullet-point">• $1</div>');
    
    // Convert numbered lists
    message = message.replace(/^(\d+)\. (.+)$/gm, '<div class="numbered-point">$1. $2</div>');
    
    // Restore code blocks with load buttons - UPDATED to use proper message IDs
    for (let i = 0; i < codeBlocks.length; i++) {
        const placeholder = `__CODEBLOCK_${i}__`;
        const codeContent = codeBlocks[i].replace(/<br>/g, '\n');
        
        // Check if this code block contains a command that should have a load button
        const matchingCommand = suggestedCommands.find(cmd => 
            cmd.command === codeContent.trim() && cmd.type === 'code_block'
        );
        
        if (matchingCommand) {
            message = message.replace(placeholder, 
                `<div class="code-block-with-button">
                    <div class="code-block">
                        <pre><code>${codeContent}</code></pre>
                    </div>
                    <button class="inline-load-btn" 
                            data-command="${escapeHtml(matchingCommand.command)}" 
                            data-cmd-id="${matchingCommand.message_id || matchingCommand.id}" 
                            data-suggestion-id="${matchingCommand.message_id || matchingCommand.id}"
                            title="${escapeHtml(matchingCommand.description)}">
                        <i class="fas fa-download"></i> Load
                    </button>
                </div>`);
        } else {
            message = message.replace(placeholder, 
                `<div class="code-block"><pre><code>${codeContent}</code></pre></div>`);
        }
    }
    
    // Convert multiple <br> tags to proper paragraph breaks
    message = message.replace(/(<br>\s*){2,}/g, '</p><p>');
    
    // Wrap in paragraphs if there are paragraph breaks
    if (message.includes('</p><p>')) {
        message = '<p>' + message + '</p>';
    }
    
    // Clean up any empty paragraphs
    message = message.replace(/<p>\s*<\/p>/g, '');
    
    return message;
}

// Updated function to process inline commands with proper message IDs (UPDATED)
function processInlineCommands(message, suggestedCommands) {
    // Find inline code patterns and replace with command + load button if applicable
    return message.replace(/`([^`\n]+)`/g, function(match, content) {
        const trimmedContent = content.trim();
        
        // Check if this is a suggested command
        const matchingCommand = suggestedCommands.find(cmd => 
            cmd.command === trimmedContent && (cmd.type === 'inline' || cmd.type === 'step')
        );
        
        if (matchingCommand) {
            return `<span class="inline-command-container">
                <code class="inline-code">${content}</code>
                <button class="inline-load-btn small" 
                        data-command="${escapeHtml(matchingCommand.command)}" 
                        data-cmd-id="${matchingCommand.message_id || matchingCommand.id}"
                        data-suggestion-id="${matchingCommand.message_id || matchingCommand.id}"
                        title="${escapeHtml(matchingCommand.description)}">
                    <i class="fas fa-download"></i>
                </button>
            </span>`;
        } else {
            return `<code class="inline-code">${content}</code>`;
        }
    });
}

function initApp() {
    console.log('Initializing GhostCrew Terminal v4.0 with Enhanced AI Assistant and Streaming Support');

    
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

// Function to observe chat changes for auto-scroll
function observeChatChanges() {
    const chatMessages = document.getElementById('chat-messages');
    if (chatMessages) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    // Don't auto-scroll here - let individual message handlers decide
                    // Only auto-scroll for typing indicator or if explicitly needed
                    const addedNode = mutation.addedNodes[0];
                    if (addedNode.classList && addedNode.classList.contains('typing-indicator')) {
                        // Only scroll for typing indicator if user was near bottom
                        if (isUserNearBottom(200)) {
                            setTimeout(() => scrollChatToShowUserMessage(), 50);
                        }
                    }
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
    console.group('[STREAMING] Processing Updates');
    console.log('Number of updates:', updates.length);
    
    updates.forEach(function(update, index) {
        const commandId = update.command_id;
        const outputChunk = update.output_chunk;
        const isPartial = update.is_partial;
        const lastUpdate = update.last_update;
        const commandStatus = update.status;
        const streamingStatus = update.streaming_status;
        
        console.log(`Update ${index + 1}:`, {
            commandId,
            outputLength: outputChunk ? outputChunk.length : 0,
            isPartial,
            lastUpdate,
            commandStatus,
            streamingStatus,
            command: update.command
        });
        
        // Update last update timestamp
        if (lastUpdate > lastStreamingUpdate) {
            lastStreamingUpdate = lastUpdate;
            console.log('Updated lastStreamingUpdate to:', lastStreamingUpdate);
        }
        
        // Find the terminal for this command
        const $terminal = $(`#${activeHostId}-terminal .terminal-output`);
        
        // Find the command entry for this command ID
        let $commandEntry = $terminal.find(`[data-command-id="${commandId}"]`);
        
        if ($commandEntry.length === 0) {
            console.log('Creating new command entry for streaming command:', commandId);
            // Create new command entry for streaming
            const commandText = update.command || 'Interactive Command';
            const currentPrompt = $('#terminal-prompt').text().replace(/\\\\/g, '\\');
            
            let entryHtml = `<div class="command-entry streaming-command" data-command-id="${commandId}">`;
            entryHtml += `<div class="prompt-line">${escapeHtml(currentPrompt)} ${escapeHtml(commandText)}</div>`;
            entryHtml += `<div class="result streaming-output" data-command-id="${commandId}">`;
            entryHtml += `<pre class="streaming-content"></pre>`;
            entryHtml += `</div>`;
            entryHtml += `</div>`;
            
            $terminal.append(entryHtml);
            $commandEntry = $terminal.find(`[data-command-id="${commandId}"]`);
        } else {
            console.log('Found existing command entry for:', commandId);
        }
        
        // Get the streaming content container
        let $pre = $commandEntry.find('.streaming-content');
        if ($pre.length === 0) {
            console.log('Creating streaming content container');
            $commandEntry.find('.result').html('<pre class="streaming-content"></pre>');
            $pre = $commandEntry.find('.streaming-content');
        }
        
        // Update content
        if (outputChunk && outputChunk.length > 0) {
            const currentText = $pre.text();
            const newLength = outputChunk.length;
            const currentLength = currentText.length;
            
            console.log(`Content update - Current: ${currentLength} chars, New: ${newLength} chars`);
            
            // Only append if this chunk contains new content not already displayed
            if (!currentText.endsWith(outputChunk.slice(-Math.min(100, outputChunk.length)))) {
                console.log('Updating streaming content');
                $pre.text(outputChunk);
            } else {
                console.log('Content already up to date, skipping update');
            }
        }
        
        // Handle interactive mode state changes
        if (streamingStatus === 'active' && !isStreamingMode) {
            console.log('🔴 Enabling streaming mode for command:', commandId);
            enableStreamingMode(commandId);
        } else if (streamingStatus === 'completed' && isStreamingMode && currentStreamingCommandId === commandId) {
            console.log('✅ Streaming session completed for command:', commandId);
            // Mark as completed but preserve all content
            $commandEntry.removeClass('streaming-command').addClass('completed-command');
            
            // Add completion indicator without overwriting content
            const $result = $commandEntry.find('.result');
            if (!$result.find('.session-completed').length) {
                $result.append('<div class="session-completed"><small><i class="fas fa-check-circle"></i> Interactive session completed</small></div>');
            }
            
            disableStreamingMode();
        }
        
        // Auto-scroll to bottom only if user hasn't scrolled up
        if (!isUserScrolledUp($terminal[0])) {
            scrollToBottom($terminal[0]);
        }
    });
    
    console.groupEnd();
}

function showCommandExecuting() {
    const $container = $('.terminal-input-container');
    const $input = $('#terminal-input');
    const $button = $('#send-command');
    const $status = $('#execution-status');
    
    // Don't disable for interactive commands
    if (!isStreamingMode) {
        $input.prop('disabled', true);
        $button.prop('disabled', true);
    }
    
    $container.addClass('executing');
    $button.html('<i class="fas fa-spinner fa-spin"></i> Executing...');
    $status.addClass('visible');
    
    // Add visual feedback
    $input.addClass('executing-state');
}

function hideCommandExecuting() {
    const $container = $('.terminal-input-container');
    const $input = $('#terminal-input');
    const $button = $('#send-command');
    const $status = $('#execution-status');
    
    $container.removeClass('executing');
    $input.prop('disabled', false).removeClass('executing-state').focus();
    $button.prop('disabled', false).html('Send');
    $status.removeClass('visible');
}

// Enhanced streaming mode functions
function enableStreamingMode(commandId) {
    if (!isStreamingMode) {
        isStreamingMode = true;
        currentStreamingCommandId = commandId;
        
        console.log(`🔴 Enabling streaming mode for command ${commandId}`);
        
        // Don't disable input for streaming - keep it active for interactive commands
        $('#terminal-input').prop('disabled', false).focus();
        $('#send-command').prop('disabled', false);
        
        // Update UI indicators
        const $tab = $(`#session-tabs .tab[data-session="${activeHostId}"]`);
        $tab.addClass('streaming');
        
        // Add streaming indicator to tab
        if (!$tab.find('.streaming-indicator').length) {
            $tab.find('.tab-name').after('<span class="streaming-indicator"></span>');
        }
        
        // Update terminal prompt for interactive mode
        updateTerminalPromptForStreaming(commandId);
        
        // Start connection monitoring
        startStreamingConnectionMonitor();
        
        // Hide any execution loading state
        hideCommandExecuting();
        
        // Notify chat
        addChatMessage('bot', '🔴 **Interactive Mode Active**\n\nUse the main terminal input to send commands to the interactive session.\n\n**Tips:**\n• Type commands and press Enter\n• Use Ctrl+C to interrupt\n• Commands now go directly to the interactive session');
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
        const $tab = $(`#session-tabs .tab[data-session="${activeHostId}"]`);
        $tab.removeClass('streaming');
        $tab.find('.streaming-indicator').remove();
        
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

// Throttling mechanism to prevent command spam
let lastCommandTime = 0;
const COMMAND_THROTTLE_MS = 500; // 500ms between commands

function canSendCommand() {
    const now = Date.now();
    if (now - lastCommandTime < COMMAND_THROTTLE_MS) {
        showNotification('Please wait before sending another command', 'warning');
        return false;
    }
    lastCommandTime = now;
    return true;
}

// Update the main sendCommand function to include throttling
const originalSendCommand = sendCommand;
sendCommand = function() {
    // Allow immediate sending for interactive commands
    if (isStreamingMode || canSendCommand()) {
        originalSendCommand();
    }
};

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

// Enhanced function to handle input echo properly
function showInputEcho(input, isControl = false) {
    if (!isStreamingMode || !currentStreamingCommandId) return;
    
    const $terminal = $(`#${activeHostId}-terminal .terminal-output`);
    const $commandEntry = $terminal.find(`[data-command-id="${currentStreamingCommandId}"]`);
    
    if ($commandEntry.length > 0) {
        let $pre = $commandEntry.find('.streaming-content');
        if ($pre.length === 0) {
            $pre = $commandEntry.find('.result pre');
            if ($pre.length === 0) {
                $commandEntry.find('.result').html('<pre class="streaming-content"></pre>');
                $pre = $commandEntry.find('.streaming-content');
            } else {
                $pre.addClass('streaming-content');
            }
        }
        
        // Get current content as text
        const currentContent = $pre.text();
        
        // Format the input for display
        const displayInput = isControl ? `[${input}]` : input;
        
        // Append input with newline if content exists
        const newContent = currentContent + (currentContent ? '\n' : '') + displayInput;
        $pre.text(newContent);
        
        scrollToBottom($terminal[0]);
    }
}

// Additional helper function to ensure proper content handling
function appendStreamingContent(commandId, newContent) {
    const $commandEntry = $(`.command-entry[data-command-id="${commandId}"]`);
    if ($commandEntry.length === 0) return;
    
    let $pre = $commandEntry.find('.streaming-content');
    if ($pre.length === 0) {
        $pre = $commandEntry.find('.result pre');
        if ($pre.length === 0) {
            $commandEntry.find('.result').html('<pre class="streaming-content"></pre>');
            $pre = $commandEntry.find('.streaming-content');
        } else {
            $pre.addClass('streaming-content');
        }
    }
    
    // Always append to existing content for streaming
    const currentContent = $pre.text();
    $pre.text(currentContent + newContent);
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

// Enhanced sendCommand function with better interactive session handling
function sendCommand() {
    const command = $('#terminal-input').val().trim();
    
    if (command === '') {
        console.warn('[TERMINAL] Empty command, not sending');
        return;
    }
    
    if (!activeHostId || !currentRemoteSessionId) {
        console.error('[TERMINAL] No active session - Host ID:', activeHostId, 'Session ID:', currentRemoteSessionId);
        showNotification('No active session available', 'error');
        return;
    }
    
    console.group('[TERMINAL] Sending Command');
    console.log('Command:', command);
    console.log('Host ID:', activeHostId);
    console.log('Session ID:', currentRemoteSessionId);
    console.log('Streaming mode:', isStreamingMode);
    console.log('Current streaming command ID:', currentStreamingCommandId);
    console.time('command-execution-time');
    
    // Show loading state
    showCommandExecuting();
    
    // Store the command being sent
    lastCommandSent = {
        command: command,
        timestamp: Date.now(),
        sessionId: currentRemoteSessionId
    };
    
    console.log('Last command sent stored:', lastCommandSent);
    
    // Clear the input field
    $('#terminal-input').val('').focus();
    
    // Add to command history
    if (!commandHistory.includes(command)) {
        commandHistory.unshift(command);
        sessionCommandHistories[currentRemoteSessionId] = commandHistory;
        console.log('Added to command history. Total commands:', commandHistory.length);
    }
    commandHistoryIndex = -1;
    
    // Check if we're in streaming mode - send as interactive input
    if (isStreamingMode && currentStreamingCommandId) {
        console.log('🔄 In streaming mode, checking session status first...');
        
        // First verify the streaming session is still active
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
                console.log('Streaming status response:', response);
                
                if (response.status === 'success' && response.active_sessions && response.active_sessions.length > 0) {
                    const activeSession = response.active_sessions.find(s => s.command_id == currentStreamingCommandId);
                    if (activeSession && activeSession.status === 'active') {
                        console.log('✅ Streaming session still active, sending as interactive input');
                        sendInteractiveInput(command);
                        hideCommandExecuting();
                    } else {
                        console.warn('⚠️ Streaming session ended, switching to normal mode');
                        disableStreamingMode();
                        sendRegularCommand(command);
                    }
                } else {
                    console.warn('⚠️ No active streaming sessions, switching to normal mode');
                    disableStreamingMode();
                    sendRegularCommand(command);
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Error checking streaming status:', status, error);
                console.log('Assuming session ended, switching to normal mode');
                disableStreamingMode();
                sendRegularCommand(command);
            }
        });
        return;
    }
    
    console.log('📤 Sending as regular command');
    // If we're not in streaming mode, send as regular command
    sendRegularCommand(command);
}

function sendInteractiveInput(command) {
    console.group('[TERMINAL] Sending Interactive Input');
    console.log('Interactive command:', command);
    console.log('Current streaming command ID:', currentStreamingCommandId);
    
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
            console.timeEnd('command-execution-time');
            console.log('✅ Interactive input sent successfully:', response);
            console.groupEnd();
            
            if (response.status === 'success') {
                console.log('Interactive input queued successfully');
            } else {
                console.error('Failed to send interactive input:', response.message);
                showNotification('Failed to send input: ' + (response.message || 'Unknown error'), 'error');
                hideCommandExecuting();
            }
        },
        error: function(xhr, status, error) {
            console.timeEnd('command-execution-time');
            console.group('[TERMINAL] Interactive Input Error');
            console.error('❌ Interactive input failed');
            console.error('Status:', status, 'Error:', error);
            console.error('Response:', xhr.responseText);
            console.groupEnd();
            
            showNotification('Network error while sending input', 'error');
            hideCommandExecuting();
        }
    });
}

function sendRegularCommand(command) {
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
    
    console.log('Command analysis:');
    console.log('- Likely interactive:', isLikelyInteractive);
    console.log('- Matched patterns:', interactivePatterns.filter(pattern => pattern.test(command)));
    
    if (isLikelyInteractive) {
        console.log('🔄 Detected interactive command, notifying chat');
        addChatMessage('bot', `🔄 **Executing Interactive Command**\n\nDetected potentially interactive command: **${command}**\n\nIf this command requires input, you'll see interactive controls appear below the terminal.`);
    }
    
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
            console.timeEnd('command-execution-time');
            console.log('📥 Command response received:', response);
            
            if (response.status === 'success') {
                const commandId = response.command_id;
                const isInteractive = response.is_interactive;
                
                console.log('✅ Command sent successfully');
                console.log('- Command ID:', commandId);
                console.log('- Interactive:', isInteractive);
                console.log('- Response time:', (Date.now() - lastCommandSent.timestamp) + 'ms');
                console.groupEnd();
                
                // Send command context to chatbot after a short delay
                setTimeout(() => {
                    console.log('[TERMINAL] Sending command context to chat');
                    sendCommandContextToChat(command);
                }, 500);
                
                // For non-interactive commands, show executing state until completion
                if (!isInteractive) {
                    console.log('📋 Regular command, monitoring completion...');
                    monitorCommandCompletion(commandId);
                } else {
                    console.log('🔄 Interactive command, hiding loading state immediately');
                    hideCommandExecuting();
                }
                
            } else if (response.redirect) {
                console.warn('🔄 Redirecting to:', response.redirect);
                window.location.href = response.redirect;
            } else {
                console.error('❌ Command failed:', response.message);
                console.groupEnd();
                showNotification('Failed to send command: ' + response.message, 'error');
                hideCommandExecuting();
            }
        },
        error: function(xhr, status, error) {
            console.timeEnd('command-execution-time');
            console.group('[TERMINAL] Command Error');
            console.error('❌ Command request failed');
            console.error('Status:', status);
            console.error('Error:', error);
            console.error('HTTP Status:', xhr.status);
            console.error('Response Text:', xhr.responseText);
            console.groupEnd();
            
            if (xhr.status === 401) {
                console.error('🔐 Authentication failed, redirecting to login');
                window.location.href = 'login.php';
            } else {
                showNotification('Network error while sending command', 'error');
                hideCommandExecuting();
            }
        }
    });
}

// Add command execution loading states
function showCommandExecuting() {
    $('#terminal-input').prop('disabled', true);
    $('#send-command').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Executing...');
}

function hideCommandExecuting() {
    $('#terminal-input').prop('disabled', false).focus();
    $('#send-command').prop('disabled', false).html('Send');
}

// Monitor regular command completion
function monitorCommandCompletion(commandId) {
    const checkCompletion = () => {
        $.ajax({
            url: 'api.php',
            type: 'POST',
            data: {
                action: 'get_command_history',
                session_id: currentRemoteSessionId,
                limit: 1,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.commands.length > 0) {
                    const latestCommand = response.commands[0];
                    if (latestCommand.id == commandId && latestCommand.status === 'completed') {
                        hideCommandExecuting();
                        return;
                    }
                }
                // Command not completed yet, check again
                setTimeout(checkCompletion, 1000);
            },
            error: function() {
                hideCommandExecuting();
            }
        });
    };
    
    // Start monitoring after a brief delay
    setTimeout(checkCompletion, 1000);
}

function sendCommandResultToAI(command, output, sessionId) {
    if (!output || output.trim() === '') return;
    
    const contextMessage = `I just ran the command "${command}" and got this output:\n\n${output}\n\nCan you help me understand what this means and suggest what I should do next?`;
    
    // Auto-add to chat without user typing
    setTimeout(() => {
        addChatMessage('user', `Command result analysis for: ${command}`);
        $('#chat-input').val(contextMessage);
        sendChatMessage();
    }, 2000);
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
                if (cmd.output && cmd.status === 'completed' && sessionId === currentRemoteSessionId) {
                    sendCommandResultToAI(cmd.command, cmd.output, sessionId);
                }
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
    // Always allow switching, even if we're on welcome tab
    
    // Check if we already have an active session for this host
    const existingTab = $(`#session-tabs .tab[data-session="${hostId}"]`);
    
    if (existingTab.length > 0 && !existingTab.hasClass('readonly')) {
        // Switch to existing session
        const sessionId = existingTab.data('remote-session');
        if (sessionId) {
            switchToSession(hostId);
            return;
        }
    }
    
    // Ensure we're not already connecting to this host
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
        showNotification('Host not found or disconnected', 'error');
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
    console.group('[SESSION] Closing Session');
    console.log('Closing session:', sessionId);
    console.log('Current active session:', activeSessionId);
    console.log('Current remote session:', currentRemoteSessionId);
    console.log('Active host:', activeHostId);
    
    const $tab = $(`#session-tabs .tab[data-session="${sessionId}"]`);
    const remoteSessionId = $tab.data('remote-session');
    
    console.log('Tab remote session ID:', remoteSessionId);
    console.log('Tab is readonly:', $tab.hasClass('readonly'));
    
    // End remote session if it's active
    if (remoteSessionId && !$tab.hasClass('readonly')) {
        console.log('Ending remote session...');
        endSession(remoteSessionId);
    }
    
    // Remove tab and terminal
    $tab.remove();
    $(`#${sessionId}-terminal`).remove();
    console.log('Removed tab and terminal elements');
    
    // Reset active session if this was active
    if (sessionId === activeSessionId) {
        console.log('This was the active session, resetting to welcome...');
        
        // Force switch to welcome tab and reset all session variables
        activeSessionId = 'welcome';
        activeHostId = null;
        currentRemoteSessionId = null;
        
        console.log('Reset session variables:');
        console.log('- activeSessionId:', activeSessionId);
        console.log('- activeHostId:', activeHostId);
        console.log('- currentRemoteSessionId:', currentRemoteSessionId);
        
        // Activate welcome tab and terminal
        $('#session-tabs .tab').removeClass('active');
        $('.tab[data-session="welcome"]').addClass('active');
        $('.terminal-session').removeClass('active');
        $('#welcome-terminal').addClass('active');
        
        console.log('Switched to welcome tab');
        
        // Reset terminal state
        $('#terminal-input').prop('disabled', true);
        $('#send-command').prop('disabled', true);
        updateTerminalPrompt('$');
        
        // Disable streaming mode
        if (isStreamingMode) {
            console.log('Disabling streaming mode...');
            disableStreamingMode();
        }
        
        // Clear command history
        commandHistory = [];
        commandHistoryIndex = -1;
        
        // Update host list to remove active state
        $('#host-list li').removeClass('active');
        
        // Update page title
        updatePageTitle(connectedHosts.length);
        
        console.log('✅ Session closed, switched to welcome tab');
    }
    console.groupEnd();
}

// Function to end a session
function endSession(sessionId) {
    console.group('[SESSION] Ending Session');
    console.log('Session ID:', sessionId);
    console.log('Current remote session:', currentRemoteSessionId);
    console.log('Active host:', activeHostId);
    
    // Reset chat preamble for the ending session
    resetChatPreambleForNewSession(sessionId);
    
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
            console.log('✅ Session ended successfully:', response);
            console.log(`Session ${sessionId} ended`);
            console.groupEnd();
            
            // Refresh historical sessions
            loadHistoricalSessions();
        },
        error: function(xhr, status, error) {
            console.error('❌ Failed to end session:', status, error);
            console.groupEnd();
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

// REPLACE the sendChatMessage function in enhanced_terminal.js

function sendChatMessage() {
    // Enhanced debugging for the input field
    const $chatInput = $('#chat-input');
    const rawValue = $chatInput.val();
    const message = rawValue ? rawValue.trim() : '';
    
    console.log('[CHAT DEBUG] Input field analysis:');
    console.log('- Element found:', $chatInput.length > 0);
    console.log('- Raw value:', JSON.stringify(rawValue));
    console.log('- Raw value type:', typeof rawValue);
    console.log('- Raw value length:', rawValue ? rawValue.length : 'null/undefined');
    console.log('- Trimmed message:', JSON.stringify(message));
    console.log('- Trimmed message length:', message.length);
    console.log('- Input element:', $chatInput[0]);
    
    if (message === '' || message.length === 0) {
        console.warn('[CHAT] Empty message detected, not sending');
        console.log('- Original value was:', JSON.stringify(rawValue));
        console.log('- After trim was:', JSON.stringify(message));
        
        // Check if there's actually content but it's whitespace
        if (rawValue && rawValue.length > 0) {
            console.warn('[CHAT] Message contained only whitespace:', JSON.stringify(rawValue));
        }
        
        // Re-focus the input field
        $chatInput.focus();
        return;
    }
    
    // Collect chat history from the current session - FIXED: Don't JSON.stringify it
    const chatHistory = collectChatHistory();
    
    console.group('[CHAT] Sending Message');
    console.log('User message:', message);
    console.log('Message length:', message.length);
    console.log('Chat history (string):', chatHistory);
    console.log('Chat history type:', typeof chatHistory);
    console.log('Current session:', currentRemoteSessionId || 'welcome');
    console.log('Conversation ID:', currentConversationId);
    console.log('Active host:', activeHostId);
    console.log('Streaming mode:', isStreamingMode);
    console.time('chat-response-time');
    
    // Clear input and reset height AFTER we've captured the value
    $chatInput.val('').height('auto');
    console.log('Input cleared, new value:', JSON.stringify($chatInput.val()));
    
    // Add user message to chat (this will handle scrolling to show user message)
    addChatMessage('user', message);
    
    // Show typing indicator without auto-scrolling
    showTypingIndicatorWithoutScroll();
    
    // Send to PHP chat handler with chat history - FIXED: Send as string, not JSON
    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: {
            action: 'chat_message',
            message: message,
            chat_history: chatHistory, // This is already a string from collectChatHistory()
            session_id: currentRemoteSessionId || 'welcome',
            conversation_id: currentConversationId,
            csrf_token: CSRF_TOKEN
        },
        dataType: 'json',
        timeout: 30000,
        success: function(response) {
            console.timeEnd('chat-response-time');
            hideTypingIndicator();

            console.log('[CHAT] Response received:', response);
            
            if (response.status === 'success') {
                console.log('✅ Chat response successful');
                console.log('Bot response length:', (response.bot_response || response.message).length);
                console.log('Suggested commands:', response.suggested_commands || []);
                console.log('Category:', response.category || 'general');
                
                // Ensure suggested_commands is always an array
                const suggestedCommands = Array.isArray(response.suggested_commands) ? response.suggested_commands : [];
                
                // Add bot response with enhanced formatting and multiple commands
                addChatMessage('bot', response.bot_response || response.message, {
                    suggested_commands: suggestedCommands,
                    category: response.category || 'general',
                    bot_message_id: response.bot_message_id,
                    conversation_id: response.conversation_id,
                    is_ai_generated: response.is_ai_generated
                });
                
                // Store conversation ID for future messages
                if (response.conversation_id) {
                    currentConversationId = response.conversation_id;
                    console.log('Updated conversation ID:', currentConversationId);
                }
                
                console.log('✅ Chat message processed successfully');
            } else {
                console.error('❌ Chat response failed:', response.message);
                addChatMessage('bot', response.message || 'Sorry, I encountered an error processing your message.');
            }
            console.groupEnd();
        },
        error: function(xhr, status, error) {
            console.timeEnd('chat-response-time');
            hideTypingIndicator();
            
            console.group('[CHAT] Error Response');
            console.error('❌ Chat request failed');
            console.error('Status:', status);
            console.error('Error:', error);
            console.error('HTTP Status:', xhr.status);
            console.error('Response Text:', xhr.responseText);
            console.groupEnd();
            
            let errorMessage = 'Sorry, I\'m having trouble processing your message right now.';
            
            if (status === 'timeout') {
                errorMessage = 'The request is taking too long. Please try a shorter message.';
                console.warn('[CHAT] Request timed out after 30 seconds');
            } else if (xhr.status === 401) {
                errorMessage = 'Your session has expired. Please refresh the page.';
                console.error('[CHAT] Authentication failed, redirecting to login');
                window.location.href = 'login.php';
                return;
            } else if (xhr.status >= 500) {
                errorMessage = 'The service is temporarily unavailable. Please try again later.';
                console.error('[CHAT] Server error:', xhr.status);
            }
            
            addChatMessage('bot', errorMessage);
        }
    });
}

// Function to debug chat history collection
function debugChatHistory() {
    const history = collectChatHistory(20);
    console.group('[CHAT DEBUG] Current Chat History');
    console.log('Total messages collected:', history.length);
    
    history.forEach((msg, index) => {
        console.log(`Message ${index + 1}:`, {
            role: msg.role,
            content: msg.content.substring(0, 100) + (msg.content.length > 100 ? '...' : ''),
            timestamp: msg.timestamp,
            fullLength: msg.content.length
        });
    });
    
    console.log('JSON payload size:', JSON.stringify(history).length, 'bytes');
    console.groupEnd();
    
    return history;
}

// Make it available globally for debugging
window.debugChatHistory = debugChatHistory;

// Debug function to test chat input field
function debugChatInput() {
    const $input = $('#chat-input');
    console.group('[CHAT DEBUG] Input Field Analysis');
    console.log('Element found:', $input.length > 0);
    console.log('Element:', $input[0]);
    console.log('Current value:', JSON.stringify($input.val()));
    console.log('Value length:', $input.val() ? $input.val().length : 0);
    console.log('Is focused:', $input.is(':focus'));
    console.log('Is visible:', $input.is(':visible'));
    console.log('Is disabled:', $input.prop('disabled'));
    console.log('Element properties:', {
        id: $input.attr('id'),
        class: $input.attr('class'),
        tagName: $input[0] ? $input[0].tagName : 'Not found',
        type: $input.attr('type')
    });
    console.groupEnd();
    return $input;
}

// Make it available globally for debugging
window.debugChatInput = debugChatInput;

// Function to collect chat history from the current chat display
function collectChatHistory(maxMessages = 10) {
    const commands = [];
    
    // Get recent commands from the current session's command history
    if (currentRemoteSessionId && sessionCommandHistories[currentRemoteSessionId]) {
        const recentCommands = sessionCommandHistories[currentRemoteSessionId].slice(-maxMessages);
        commands.push(...recentCommands);
    }
    
    // If no session commands, fall back to global command history
    if (commands.length === 0 && commandHistory.length > 0) {
        commands.push(...commandHistory.slice(-maxMessages));
    }
    
    // Return as comma-separated string (not array)
    const chatHistory = commands.join(', ');
    console.log(`[CHAT] Collected ${commands.length} commands for history: ${chatHistory}`);
    return chatHistory;
}

// Fixed function to clean up AI response text
function cleanAIResponse(responseText) {
    if (!responseText || typeof responseText !== 'string') {
        return 'I apologize, but I received an invalid response.';
    }
    
    // Remove any leading/trailing whitespace
    responseText = responseText.trim();
    
    // Remove any JSON artifacts that might have leaked through
    responseText = responseText.replace(/^["']|["']$/g, ''); // Remove surrounding quotes
    responseText = responseText.replace(/\\n/g, '\n'); // Convert escaped newlines
    responseText = responseText.replace(/\\"/g, '"'); // Convert escaped quotes
    responseText = responseText.replace(/\\\\/g, '\\'); // Convert escaped backslashes
    
    // Ensure there's actual content
    if (responseText.length === 0) {
        return 'I apologize, but I didn\'t receive a proper response. Please try asking your question again.';
    }
    
    return responseText;
}

// Function to build contextual prompt for the AI
function buildContextualPrompt(userMessage) {
    let prompt = '';
    
    // Add system context
    prompt += 'You are an AI assistant helping with Windows command line operations and terminal management. ';
    prompt += 'Provide helpful, accurate responses about commands, troubleshooting, and system administration. ';
    
    // Add session context if available
    if (activeHostId && currentRemoteSessionId) {
        const hostInfo = connectedHosts.find(h => h.host_id === activeHostId);
        if (hostInfo) {
            prompt += `Current session context: Connected to ${hostInfo.hostname} (${hostInfo.ip_address}) running ${hostInfo.os_info}. `;
        }
        
        // Add recent commands context
        const recentCommands = getRecentCommandsContext();
        if (recentCommands.length > 0) {
            prompt += `Recent commands executed: ${recentCommands.join(', ')}. `;
        }
    }
    
    // Add streaming context
    if (isStreamingMode) {
        prompt += 'Currently in interactive command mode. ';
    }
    
    // Add the user's actual question
    prompt += `\n\nUser question: ${userMessage}`;
    
    // Add response guidelines
    prompt += '\n\nPlease provide a helpful response. If you suggest a command, format it clearly. ';
    prompt += 'Keep responses concise but informative.';
    
    return prompt;
}

// Fixed function to process AI response and extract command suggestions
function processAIResponse(aiResponse, originalMessage) {
    let processedResponse = {
        message: aiResponse,
        suggested_command: null,
        command_description: null,
        category: 'general'
    };
    
    // Clean up and format the message for better display
    let formattedMessage = aiResponse;
    
    // Preserve the original message for display
    processedResponse.message = formattedMessage;
    
    // Extract command suggestions ONLY from code blocks or very obvious command patterns
    let suggestedCommand = null;
    let commandDescription = null;
    
    // Look for code blocks first (most reliable)
    const codeBlockPattern = /```(?:bash|cmd|shell|powershell|console)?\s*\n?([^\n`]+?)(?:\n[^`]*?)?\n?```/gi;
    const codeBlockMatches = [...formattedMessage.matchAll(codeBlockPattern)];
    
    if (codeBlockMatches.length > 0) {
        const potentialCommand = codeBlockMatches[0][1].trim();
        if (isValidCommand(potentialCommand)) {
            suggestedCommand = potentialCommand;
            commandDescription = `Command from AI response`;
        }
    }
    
    // If no code block found, look for single-line commands in backticks
    if (!suggestedCommand) {
        const inlineCodePattern = /`([a-zA-Z][a-zA-Z0-9\-_\s\.\/\\]{2,80})`/g;
        const inlineMatches = [...formattedMessage.matchAll(inlineCodePattern)];
        
        for (const match of inlineMatches) {
            const potentialCommand = match[1].trim();
            if (isValidCommand(potentialCommand) && potentialCommand.split(' ').length <= 6) {
                // Only suggest if it's a short, simple command
                suggestedCommand = potentialCommand;
                commandDescription = `Command from AI response`;
                break;
            }
        }
    }
    
    // Context-specific command suggestions based on user question
    if (!suggestedCommand && originalMessage) {
        const lowerMessage = originalMessage.toLowerCase();
        
        if (lowerMessage.includes('nmap') && lowerMessage.includes('scan')) {
            // Look for nmap commands specifically
            const nmapPattern = /nmap\s+[^\n\r]{5,50}/gi;
            const nmapMatches = [...formattedMessage.matchAll(nmapPattern)];
            if (nmapMatches.length > 0) {
                suggestedCommand = nmapMatches[0][0].trim();
                commandDescription = 'Nmap scan command';
            }
        } else if (lowerMessage.includes('ping')) {
            const pingPattern = /ping\s+[^\n\r]{3,30}/gi;
            const pingMatches = [...formattedMessage.matchAll(pingPattern)];
            if (pingMatches.length > 0) {
                suggestedCommand = pingMatches[0][0].trim();
                commandDescription = 'Ping command';
            }
        }
    }
    
    // Determine category based on content
    const categoryKeywords = {
        'file_operations': ['file', 'folder', 'directory', 'copy', 'move', 'delete', 'dir', 'ls', 'mkdir', 'rmdir'],
        'network': ['ping', 'telnet', 'ssh', 'ftp', 'network', 'connection', 'netcat', 'nc', 'port', 'socket', 'nmap', 'scan'],
        'system_info': ['system', 'info', 'version', 'hardware', 'computer', 'systeminfo', 'whoami'],
        'processes': ['process', 'task', 'running', 'kill', 'service', 'tasklist', 'taskkill'],
        'interactive': ['telnet', 'ssh', 'msfconsole', 'mysql', 'python', 'netcat', 'nc'],
        'help': ['help', 'documentation', 'manual', 'guide', 'tutorial', 'how to']
    };
    
    let detectedCategory = 'general';
    const searchText = (originalMessage + ' ' + formattedMessage).toLowerCase();
    
    for (const [category, keywords] of Object.entries(categoryKeywords)) {
        if (keywords.some(keyword => searchText.includes(keyword))) {
            detectedCategory = category;
            break;
        }
    }
    
    processedResponse.suggested_command = suggestedCommand;
    processedResponse.command_description = commandDescription;
    processedResponse.category = detectedCategory;
    
    return processedResponse;
}

// Updated function to validate if a string looks like a valid command
function isValidCommand(command) {
    if (!command || command.length < 2 || command.length > 200) return false;
    
    // Remove any backticks or quotes that might be part of formatting
    command = command.replace(/[`"']/g, '').trim();
    
    // Check for dangerous patterns first
    const dangerousPatterns = [
        /[<>"|&;]/, // Dangerous shell characters
        /format\s+c:/i, // Format commands
        /del\s+\*\.*/i, // Dangerous delete patterns
        /shutdown/i, // Shutdown commands
        /reboot/i, // Reboot commands
    ];

    for (const pattern of dangerousPatterns) {
        if (pattern.test(command)) {
            //return false;
        }
    }
    
    // Check for valid command patterns
    const validPatterns = [
        /^[a-zA-Z][a-zA-Z0-9\-_]*(\s+[^\s].*)?$/, // Starts with letter, can have arguments
        /^nmap\s+/, // Nmap commands
        /^ping\s+/, // Ping commands
        /^dir(\s|$)/, // Directory listing
        /^cd(\s|$)/, // Change directory
        /^ipconfig(\s|$)/, // IP configuration
        /^netstat(\s|$)/, // Network statistics
        /^tasklist(\s|$)/, // Task list
        /^systeminfo(\s|$)/, // System information
        /^unshadow(\s|$)/, // System information
    ];

    // Must match at least one valid pattern
    const isValid = validPatterns.some(pattern => pattern.test(command));
    
    // Additional checks
    const hasValidStart = /^[a-zA-Z]/.test(command); // Must start with letter
    const notTooManyWords = command.split(/\s+/).length <= 10; // Not too many arguments
    
    return isValid && hasValidStart && notTooManyWords;
}

// Function to get recent commands for context
function getRecentCommandsContext() {
    if (!currentRemoteSessionId || !sessionCommandHistories[currentRemoteSessionId]) {
        return [];
    }
    
    return sessionCommandHistories[currentRemoteSessionId].slice(-3); // Last 3 commands
}

// Function to log chat interactions for learning
function logChatInteraction(userMessage, aiResponse, processedResponse) {
    // Log to browser console for debugging
    console.log('Chat Interaction:', {
        user: userMessage,
        ai: aiResponse,
        processed: processedResponse,
        session: currentRemoteSessionId,
        timestamp: new Date().toISOString()
    });
    
    // Optional: Send to server for analytics (if you have an endpoint)
    // This can help improve the AI integration over time
    /*
    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: {
            action: 'log_ai_interaction',
            user_message: userMessage,
            ai_response: aiResponse,
            suggested_command: processedResponse.suggested_command,
            session_id: currentRemoteSessionId,
            csrf_token: CSRF_TOKEN
        },
        dataType: 'json'
    });
    */
}

// Enhanced function to add chat messages (updated to handle AI responses better)
function addChatMessage(type, message, options = {}) {
    const $chatMessages = $('#chat-messages');
    const chatMessages = $chatMessages[0];
    
    // Check if user was near bottom before adding message
    const wasNearBottom = isUserNearBottom(150);
    
    // Remove welcome message if it exists
    $chatMessages.find('.chat-welcome').remove();
    
    const timestamp = new Date().toLocaleTimeString();
    let messageHtml = `<div class="chat-message ${type}" data-timestamp="${timestamp}">`;
    
    if (type === 'bot') {
        // Enhanced formatting for bot messages with command support
        const suggestedCommands = options.suggested_commands || [];
        message = formatBotMessage(message, suggestedCommands);
        messageHtml += `<div class="message-content">${message}</div>`;
        
        // Add rating buttons for AI responses
        const messageId = Date.now() + Math.random();
        messageHtml += `<div class="message-actions">
            <div class="rating-buttons">
                <button class="rate-message-btn helpful" data-message-id="${messageId}" data-rating="helpful" title="This was helpful">
                    <i class="fas fa-thumbs-up"></i>
                </button>
                <button class="rate-message-btn not-helpful" data-message-id="${messageId}" data-rating="not-helpful" title="This was not helpful">
                    <i class="fas fa-thumbs-down"></i>
                </button>
            </div>
            <span class="message-time">${timestamp}</span>
        </div>`;
    } else {
        // User message
        messageHtml += `<div class="message-content">${escapeHtml(message)}</div>`;
        messageHtml += `<span class="message-time">${timestamp}</span>`;
    }
    
    messageHtml += '</div>';
    
    $chatMessages.append(messageHtml);
    
    // Get the newly added message element
    const $newMessage = $chatMessages.children().last();
    const newMessageElement = $newMessage[0];
    
    // Animate message appearance
    $newMessage.hide().fadeIn(300, function() {
        // After animation completes, handle scrolling
        if (type === 'user') {
            // For user messages, always scroll to show them
            scrollChatToShowMessage(newMessageElement, 'smooth');
        } else if (type === 'bot') {
            // For bot messages, smart scrolling based on context
            if (wasNearBottom) {
                // If user was reading recent messages, scroll to show their last message
                setTimeout(() => {
                    scrollChatToShowUserMessage();
                }, 100);
            }
            // If user had scrolled up, don't auto-scroll at all
        }
    });
}

// Function to show typing indicator
function showTypingIndicatorWithoutScroll() {
    const $chatMessages = $('#chat-messages');
    const chatMessages = $chatMessages[0];
    
    // Check current scroll position
    const wasNearBottom = isUserNearBottom(150);
    
    const typingHtml = `<div class="typing-indicator" id="typing-indicator">
        <div class="typing-dots">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <span class="typing-text">AI is thinking...</span>
    </div>`;
    
    $chatMessages.append(typingHtml);
    
    // Only scroll if user was already near bottom
    if (wasNearBottom) {
        setTimeout(() => {
            scrollChatToShowUserMessage();
        }, 100);
    }
}

// Update the existing function
function showTypingIndicator() {
    showTypingIndicatorWithoutScroll();
}



// Function to hide typing indicator
function hideTypingIndicator() {
    $('#typing-indicator').fadeOut(200, function() {
        $(this).remove();
    });
}

// Function to load chat history
function loadChatHistory(sessionId) {
    if (!sessionId) return;
    
    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: {
            action: 'get_chat_history',
            session_id: sessionId,
            conversation_id: currentConversationId,
            limit: 50,
            csrf_token: CSRF_TOKEN
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success' && response.messages.length > 0) {
                const $chatMessages = $('#chat-messages');
                $chatMessages.find('.chat-welcome').remove();
                $chatMessages.empty();
                
                // Add messages without auto-scrolling
                response.messages.forEach(function(msg, index) {
                    if (msg.message_type === 'bot') {
                        // Convert old format to new format if needed
                        let suggestedCommands = [];
                        if (msg.suggested_command) {
                            suggestedCommands = [{
                                id: 'history_cmd_' + index,
                                command: msg.suggested_command,
                                description: 'Historical command suggestion',
                                type: 'history'
                            }];
                        }
                        
                        // Add message without triggering scroll animations
                        const messageHtml = createBotMessageHTML(msg.message, {
                            suggested_commands: suggestedCommands,
                            bot_message_id: msg.id,
                            conversation_id: msg.conversation_id
                        });
                        $chatMessages.append(messageHtml);
                    } else {
                        const messageHtml = createUserMessageHTML(msg.message);
                        $chatMessages.append(messageHtml);
                    }
                });
                
                // After loading history, scroll to bottom once
                setTimeout(() => {
                    const chatMessages = document.getElementById('chat-messages');
                    if (chatMessages) {
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }
                }, 100);
                
                // Set conversation ID from history
                if (response.messages.length > 0) {
                    currentConversationId = response.messages[0].conversation_id;
                }
            }
        }
    });
}

// Helper functions for creating message HTML without animations
function createBotMessageHTML(message, options = {}) {
    const timestamp = new Date().toLocaleTimeString();
    const suggestedCommands = options.suggested_commands || [];
    
    message = formatBotMessage(message, suggestedCommands);
    
    let messageHtml = `<div class="chat-message bot" data-timestamp="${timestamp}">`;
    messageHtml += `<div class="message-content">${message}</div>`;
    
    const messageId = Date.now() + Math.random();
    messageHtml += `<div class="message-actions">
        <div class="rating-buttons">
            <button class="rate-message-btn helpful" data-message-id="${messageId}" data-rating="helpful" title="This was helpful">
                <i class="fas fa-thumbs-up"></i>
            </button>
            <button class="rate-message-btn not-helpful" data-message-id="${messageId}" data-rating="not-helpful" title="This was not helpful">
                <i class="fas fa-thumbs-down"></i>
            </button>
        </div>
        <span class="message-time">${timestamp}</span>
    </div>`;
    
    messageHtml += '</div>';
    return messageHtml;
}

function createUserMessageHTML(message) {
    const timestamp = new Date().toLocaleTimeString();
    
    let messageHtml = `<div class="chat-message user" data-timestamp="${timestamp}">`;
    messageHtml += `<div class="message-content">${escapeHtml(message)}</div>`;
    messageHtml += `<span class="message-time">${timestamp}</span>`;
    messageHtml += '</div>';
    
    return messageHtml;
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

// Function to load suggested command into terminal input (UPDATED)
function loadSuggestedCommand(suggestionId, command) {
    if (!activeHostId || !currentRemoteSessionId) {
        showNotification('No active session available', 'warning');
        return;
    }
    
    // Load the command into terminal input
    $('#terminal-input').val(command).focus();
    
    // Mark suggestion as used in database via AJAX call
    markSuggestionAsUsed(suggestionId);
    
    // Update button state immediately for user feedback
    $(`.load-suggestion-btn[data-suggestion-id="${suggestionId}"]`)
        .removeClass('load-suggestion-btn')
        .addClass('loaded-btn')
        .html('<i class="fas fa-check"></i> Loaded')
        .prop('disabled', true);
    
    // Add chat message indicating the command was loaded
    addChatMessage('bot', `📝 **Command Loaded**\n\nLoaded command into terminal: **${command}**\n\nPress Enter or click Send to execute it.`);
}

// Enhanced function to mark suggestion as used (UPDATED)
function markSuggestionAsUsed(suggestionId) {
    // Make AJAX call to mark the command as executed in the database
    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: {
            action: 'mark_command_executed',
            message_id: suggestionId, // Use message_id since that's what the PHP function expects
            csrf_token: CSRF_TOKEN
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                console.log('Command marked as executed in database:', suggestionId);
            } else {
                console.warn('Failed to mark command as executed:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error marking command as executed:', error);
            // Still continue with the UI update even if database update fails
        }
    });
}

// Function to handle rating responses (updated for AI endpoint)
function rateMessage(messageId, rating) {
    // Visual feedback
    const $button = $(`.rate-message-btn[data-message-id="${messageId}"][data-rating="${rating}"]`);
    $button.addClass('rated').prop('disabled', true);
    
    // Disable other rating buttons for this message
    $(`.rate-message-btn[data-message-id="${messageId}"]`).prop('disabled', true);
    
    // Log the rating
    console.log('Message rated:', { messageId, rating, timestamp: new Date().toISOString() });
    
    // Show feedback
    const feedbackText = rating === 'helpful' ? 'Thank you for your feedback!' : 'Thanks for the feedback. I\'ll try to improve!';
    showNotification(feedbackText, 'success');
    
    // Optional: Send rating to server for AI improvement
    /*
    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: {
            action: 'rate_ai_message',
            message_id: messageId,
            rating: rating,
            csrf_token: CSRF_TOKEN
        },
        dataType: 'json'
    });
    */
}

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

// ===========================================
// ENHANCED CHAT FUNCTIONS
// ===========================================

// Enhanced function to add chat messages with AWS AI features
function addEnhancedChatMessage(type, message, options = {}) {
    const $chatMessages = $('#chat-messages');
    
    // Remove welcome message if it exists
    $chatMessages.find('.chat-welcome').remove();
    
    const timestamp = new Date().toLocaleTimeString();
    const messageId = options.messageId || Date.now();
    
    let messageHtml = `<div class="chat-message ${type}" data-message-id="${messageId}" data-timestamp="${timestamp}">`;
    
    if (type === 'bot') {
        // Enhanced formatting for bot messages
        message = formatBotMessage(message);
        messageHtml += `<div class="message-content">${message}</div>`;
        
        // Add command suggestion if present
        if (options.suggestedCommand) {
            messageHtml += createCommandSuggestionHTML(options.suggestedCommand, options.commandDescription, messageId);
        }
        
        // Add AI response metadata
        if (options.responseTime || options.modelUsed) {
            messageHtml += `<div class="ai-metadata">
                <small class="ai-info">
                    ${options.responseTime ? `<span class="response-time"><i class="fas fa-clock"></i> ${(options.responseTime * 1000).toFixed(0)}ms</span>` : ''}
                </small>
            </div>`;
        }
        
        // Enhanced rating and feedback system
        messageHtml += createFeedbackHTML(messageId, options.conversationId);
        
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
    
    // Auto-focus terminal if command was suggested
    if (type === 'bot' && options.suggestedCommand) {
        setTimeout(() => {
            if (!$('#terminal-input').is(':focus')) {
                showCommandHighlight();
            }
        }, 2000);
    }
}

function createCommandSuggestionHTML(command, description, messageId) {
    const escapedCommand = escapeHtml(command);
    const escapedDescription = escapeHtml(description || 'Suggested command from AI');
    
    return `<div class="command-suggestion-enhanced">
        <div class="suggestion-header">
            <i class="fas fa-terminal"></i>
            <span class="suggestion-title">Suggested Command</span>
            <span class="suggestion-badge">AI</span>
        </div>
        <div class="suggestion-command">
            <code class="command-code">${escapedCommand}</code>
            <div class="command-actions">
                <button class="send-to-terminal-btn" 
                        data-command="${escapedCommand}" 
                        data-message-id="${messageId}"
                        title="Send to terminal input">
                    <i class="fas fa-arrow-right"></i>
                    Send to Terminal
                </button>
                <button class="copy-command-btn" 
                        data-command="${escapedCommand}"
                        title="Copy to clipboard">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
        </div>
        <div class="suggestion-description">${escapedDescription}</div>
    </div>`;
}

function createFeedbackHTML(messageId, conversationId) {
    return `<div class="message-feedback">
        <span class="message-time">${new Date().toLocaleTimeString()}</span>
    </div>`;
}

// Enhanced typing indicator with AI branding
function showEnhancedTypingIndicator() {
    const $chatMessages = $('#chat-messages');
    const typingHtml = `<div class="typing-indicator enhanced" id="typing-indicator">
        <div class="ai-avatar">
            <i class="fas fa-robot"></i>
        </div>
        <div class="typing-content">
            <div class="typing-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <span class="typing-text">AI is thinking...</span>
        </div>
    </div>`;
    $chatMessages.append(typingHtml);
    scrollChatToBottom();
}

// Helper functions for enhanced chat

function generateConversationId() {
    const sessionPart = currentRemoteSessionId || 'welcome';
    const timestamp = Date.now();
    return `conv_${sessionPart}_${timestamp}`;
}

function markCommandAsUsed(messageId) {
    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: {
            action: 'mark_command_executed',
            message_id: messageId,
            csrf_token: CSRF_TOKEN
        },
        dataType: 'json',
        success: function(response) {
            console.log('Command marked as executed');
        }
    });
}

function submitFeedback(messageId, conversationId, feedbackType, feedbackText = null) {
    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: {
            action: 'submit_feedback',
            message_id: messageId,
            conversation_id: conversationId,
            feedback_type: feedbackType,
            feedback_text: feedbackText,
            csrf_token: CSRF_TOKEN
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                showNotification('Thank you for your feedback!', 'success');
            } else {
                showNotification('Failed to save feedback', 'error');
                // Re-enable buttons on error
                $(`.feedback-btn[data-message-id="${messageId}"]`).prop('disabled', false);
            }
        },
        error: function() {
            showNotification('Failed to save feedback', 'error');
            // Re-enable buttons on error
            $(`.feedback-btn[data-message-id="${messageId}"]`).prop('disabled', false);
        }
    });
}

function showFeedbackModal(messageId, conversationId, feedbackType) {
    const modal = $(`
        <div class="feedback-modal-overlay">
            <div class="feedback-modal">
                <div class="feedback-modal-header">
                    <h3><i class="fas fa-flag"></i> Report Issue</h3>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="feedback-modal-body">
                    <p>Help us improve by describing what was incorrect:</p>
                    <textarea id="feedback-text" placeholder="What was wrong with this response?" rows="4"></textarea>
                </div>
                <div class="feedback-modal-footer">
                    <button class="btn-cancel">Cancel</button>
                    <button class="btn-submit">Submit Report</button>
                </div>
            </div>
        </div>
    `);
    
    $('body').append(modal);
    
    // Event handlers
    modal.find('.close-modal, .btn-cancel').on('click', function() {
        modal.remove();
        // Re-enable feedback buttons
        $(`.feedback-btn[data-message-id="${messageId}"]`).prop('disabled', false).removeClass('selected');
    });
    
    modal.find('.btn-submit').on('click', function() {
        const feedbackText = modal.find('#feedback-text').val().trim();
        submitFeedback(messageId, conversationId, feedbackType, feedbackText);
        modal.remove();
    });
    
    // Focus textarea
    modal.find('#feedback-text').focus();
}

function showCommandNotification(command) {
    if (!command) return;
    
    const notification = $(`
        <div class="command-notification">
            <div class="notification-content">
                <i class="fas fa-terminal"></i>
                <span>AI suggested a command: <code>${escapeHtml(command)}</code></span>
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

function showCommandHighlight() {
    const $terminalInput = $('#terminal-input');
    $terminalInput.addClass('suggested-command-highlight');
    
    setTimeout(() => {
        $terminalInput.removeClass('suggested-command-highlight');
    }, 3000);
}

function scrollToTerminal() {
    const terminalElement = $('.terminal-input-container')[0];
    if (terminalElement) {
        terminalElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// Enhanced chat history loading with AWS AI context
function loadChatHistory(sessionId) {
    if (!sessionId) return;
    
    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: {
            action: 'get_chat_history',
            session_id: sessionId,
            conversation_id: currentConversationId,
            limit: 50,
            csrf_token: CSRF_TOKEN
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success' && response.messages.length > 0) {
                const $chatMessages = $('#chat-messages');
                $chatMessages.find('.chat-welcome').remove();
                $chatMessages.empty();
                
                response.messages.forEach(function(msg) {
                    if (msg.message_type === 'bot') {
                        addEnhancedChatMessage('bot', msg.message, {
                            messageId: msg.id,
                            suggestedCommand: msg.suggested_command,
                            commandDescription: 'Previously suggested command',
                            conversationId: msg.conversation_id,
                            responseTime: msg.response_time,
                            modelUsed: msg.model_used,
                            hasCommand: !!msg.suggested_command
                        });
                    } else {
                        addChatMessage('user', msg.message);
                    }
                });
                
                // Set conversation ID from history
                if (response.messages.length > 0) {
                    currentConversationId = response.messages[0].conversation_id;
                }
            }
        }
    });
}

// Initialize enhanced chat system
function initializeEnhancedChat() {
    console.log('Initializing Enhanced Chat with AWS AI Integration');
    
    // Load chat history if we have a session
    if (currentRemoteSessionId) {
        loadChatHistory(currentRemoteSessionId);
    }
    
    // Add welcome message with AWS AI branding
    //if (!chatInitialized) {
        setTimeout(() => {
            addEnhancedChatMessage('bot', 
                'Hello! I\'m your ** AI assistant**. I can help you with:\n\n' +
                '• **Command suggestions** with terminal integration\n' +
                '• **System administration** guidance\n' +
                '• **Interactive session** support\n' +
                '• **Real-time context** awareness\n\n' +
                'Try asking: *"How do I check disk usage?"* or *"Help me troubleshoot network issues"*', 
                {
                    modelUsed: 'AWS AI',
                    hasCommand: false
                }
            );
            chatInitialized = true;
        }, 1000);
    //}
}

// Function to handle session switching for chat context
function switchChatSession(newSessionId) {
    // Save current conversation state
    if (currentConversationId) {
        sessionStorage.setItem(`chat_conv_${currentRemoteSessionId}`, currentConversationId);
    }
    
    // Load conversation for new session
    currentConversationId = sessionStorage.getItem(`chat_conv_${newSessionId}`) || null;
    
    // Load chat history for new session
    if (newSessionId && newSessionId !== 'welcome') {
        loadChatHistory(newSessionId);
    } else {
        // Clear chat for welcome session
        $('#chat-messages').empty();
    }
}

// Enhanced error handling for AWS AI
function handleAIError(error, context = {}) {
    console.error('AWS AI Error:', error, context);
    
    let userMessage = 'I apologize, but I\'m experiencing technical difficulties.';
    
    if (error.includes('timeout')) {
        userMessage = 'The AI service is taking too long to respond. Please try a shorter message.';
    } else if (error.includes('rate limit')) {
        userMessage = 'I\'m receiving too many requests right now. Please wait a moment and try again.';
    } else if (error.includes('authentication')) {
        userMessage = 'There\'s an authentication issue with the AI service. Please contact support.';
    }
    
    addChatMessage('bot', userMessage);
}

// Helper function to send multiple commands sequentially
function sendCommandSequence(commands, index = 0) {
    if (index >= commands.length) {
        showNotification('All commands sent successfully', 'success');
        return;
    }
    
    const command = commands[index].trim();
    if (!command) {
        sendCommandSequence(commands, index + 1);
        return;
    }
    
    $('#terminal-input').val(command);
    
    // Send the command
    sendCommand();
    
    // Wait for completion before sending next command
    const checkCompletion = () => {
        if (!$('#send-command').prop('disabled')) {
            // Command completed, send next
            setTimeout(() => {
                sendCommandSequence(commands, index + 1);
            }, 1000); // 1 second delay between commands
        } else {
            // Still executing, check again
            setTimeout(checkCompletion, 500);
        }
    };
    
    setTimeout(checkCompletion, 1000);
}

function resetChatPreambleForNewSession(sessionId) {
    // Clear command analysis cache
    if (window.commandAnalysisCache) {
        window.commandAnalysisCache.clear();
    }
    
    // Send reset signal to server
    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: {
            action: 'reset_chat_context',
            session_id: sessionId,
            csrf_token: CSRF_TOKEN
        },
        dataType: 'json',
        success: function(response) {
            console.log('Chat context reset for session:', sessionId);
        },
        error: function() {
            console.log('Failed to reset chat context for session:', sessionId);
        }
    });
}

function analyzeCommandResult(commandId, command, output, executionTime, exitCode) {
    if (!output || output.trim().length < 10) {
        return; // Skip analysis for commands with minimal output
    }
    
    // Send silent analysis to AI (no chat message)
    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: {
            action: 'analyze_command_result',
            command: command,
            output: output,
            execution_time: executionTime,
            exit_code: exitCode,
            session_id: currentRemoteSessionId,
            silent: true, // Silent analysis
            csrf_token: CSRF_TOKEN
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                console.log('Command analysis completed silently');
                // Store analysis context for future AI interactions
                if (!window.commandAnalysisCache) {
                    window.commandAnalysisCache = new Map();
                }
                window.commandAnalysisCache.set(commandId, {
                    command: command,
                    output: output.substring(0, 500),
                    exitCode: exitCode,
                    timestamp: Date.now()
                });
                
                // Clean old cache entries (keep last 20)
                if (window.commandAnalysisCache.size > 20) {
                    const entries = Array.from(window.commandAnalysisCache.entries());
                    entries.sort((a, b) => a[1].timestamp - b[1].timestamp);
                    for (let i = 0; i < entries.length - 20; i++) {
                        window.commandAnalysisCache.delete(entries[i][0]);
                    }
                }
            }
        },
        error: function() {
            console.log('Silent command analysis failed');
        }
    });
}

// Event handlers for enhanced chat functionality
$(document).ready(function() {

    // Initialize the application
    initApp();

    // Initialize enhanced chat when DOM is ready
    initializeEnhancedChat();

    // Initialize scroll monitoring
    initializeChatScrollMonitor();

    // Updated send to terminal button handler (UPDATED)
    $(document).on('click', '.send-to-terminal-btn', function() {
        const command = $(this).data('command');
        const messageId = $(this).data('message-id');
        
        if (!activeHostId || !currentRemoteSessionId) {
            showNotification('No active terminal session available', 'warning');
            return;
        }
        
        // Load command into terminal input
        $('#terminal-input').val(command).focus();
        
        // Mark command as used in database
        if (messageId) {
            markSuggestionAsUsed(messageId);
        }
        
        // Update button state
        $(this).removeClass('send-to-terminal-btn')
            .addClass('command-sent-btn')
            .html('<i class="fas fa-check"></i> Sent')
            .prop('disabled', true);
        
        // Show notification
        showNotification('Command loaded into terminal. Press Enter to execute.', 'success');
        
        // Highlight terminal input briefly
        $('#terminal-input').addClass('command-highlight');
        setTimeout(() => {
            $('#terminal-input').removeClass('command-highlight');
        }, 2000);
        
        // Auto-scroll to terminal if needed
        scrollToTerminal();
    });
    
    // Copy command button handler
    $(document).on('click', '.copy-command-btn', function() {
        const command = $(this).data('command');
        const button = this;
        
        navigator.clipboard.writeText(command).then(() => {
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.classList.add('copied');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('copied');
            }, 2000);
            
            showNotification('Command copied to clipboard', 'success');
        }).catch(() => {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = command;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            showNotification('Command copied to clipboard', 'success');
        });
    });
    
    // Feedback button handlers
    $(document).on('click', '.feedback-btn', function() {
        const messageId = $(this).data('message-id');
        const conversationId = $(this).data('conversation-id');
        const feedbackType = $(this).data('feedback');
        const $button = $(this);
        
        // Disable all feedback buttons for this message
        $(`.feedback-btn[data-message-id="${messageId}"]`).prop('disabled', true);
        
        // Add visual feedback
        $button.addClass('selected');
        
        // Handle special feedback types
        if (feedbackType === 'incorrect') {
            showFeedbackModal(messageId, conversationId, feedbackType);
        } else {
            submitFeedback(messageId, conversationId, feedbackType);
        }
    });

    // Enhanced command sending with better feedback
    $('#send-command').on('click', function(e) {
        e.preventDefault();
        if (!$(this).prop('disabled')) {
            sendCommand();
        }
    });
    
    // Enhanced terminal input handling
    $('#terminal-input').on('keydown', function(e) {
        if (e.key === 'Enter' && !$(this).prop('disabled')) {
            e.preventDefault();
            sendCommand();
        } else if (e.key === 'ArrowUp') {
            navigateCommandHistory(-1);
            e.preventDefault();
        } else if (e.key === 'ArrowDown') {
            navigateCommandHistory(1);
            e.preventDefault();
        } else if (e.key === 'Escape') {
            // Clear input on escape
            $(this).val('');
        }
    });

    // Prevent multiple rapid submissions
    $('#terminal-input').on('paste', function() {
        // Small delay to allow paste to complete
        setTimeout(() => {
            if ($(this).val().includes('\n')) {
                // Handle multi-line paste by splitting and sending sequentially
                const lines = $(this).val().split('\n').filter(line => line.trim());
                $(this).val('');
                
                if (lines.length > 1) {
                    showNotification(`Sending ${lines.length} commands sequentially...`, 'info');
                    sendCommandSequence(lines);
                } else if (lines.length === 1) {
                    $(this).val(lines[0]);
                }
            }
        }, 10);
    });
    
    // Enhanced chat input handling with debugging
    $('#send-chat').on('click', function(e) {
        e.preventDefault();
        console.log('[CHAT] Send button clicked');
        console.log('Button element:', this);
        console.log('Input field exists:', $('#chat-input').length > 0);
        console.log('Input field value before send:', JSON.stringify($('#chat-input').val()));
        sendChatMessage();
    });

    // Add debugging for input field changes
    $('#chat-input').on('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        const value = $(this).val();
        console.log('[CHAT] Input changed:', JSON.stringify(value), 'Length:', value.length);
        
        // Auto-resize chat textarea
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    // Enhanced chat input keydown with debugging
    $('#chat-input').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            console.log('[CHAT] Enter key pressed');
            console.log('Input field value before send:', JSON.stringify($(this).val()));
            sendChatMessage();
        }
    });

    // Add debugging for focus events
    $('#chat-input').on('focus', function() {
        console.log('[CHAT] Input field focused, current value:', JSON.stringify($(this).val()));
    });

    $('#chat-input').on('blur', function() {
        console.log('[CHAT] Input field blurred, current value:', JSON.stringify($(this).val()));
    });

    // Handle command suggestion execution
    $(document).on('click', '.load-suggestion-btn', function() {
        const suggestionId = $(this).data('suggestion-id');
        const command = $(this).data('command');
        loadSuggestedCommand(suggestionId, command);
    });

    // Updated inline load button handler (UPDATED)
    $(document).on('click', '.inline-load-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const command = $(this).data('command');
        const cmdId = $(this).data('cmd-id');
        
        if (!activeHostId || !currentRemoteSessionId) {
            showNotification('No active terminal session available', 'warning');
            return;
        }
        
        // Load command into terminal input
        $('#terminal-input').val(command).focus();
        
        // Mark command as used in database if we have a command ID
        if (cmdId) {
            markSuggestionAsUsed(cmdId);
        }
        
        // Update button state
        $(this).removeClass('inline-load-btn')
            .addClass('inline-loaded-btn')
            .html('<i class="fas fa-check"></i>')
            .prop('disabled', true)
            .attr('title', 'Command loaded');
        
        // Show notification
        showNotification('Command loaded into terminal', 'success');
        
        // Highlight terminal input briefly
        $('#terminal-input').addClass('command-highlight');
        setTimeout(() => {
            $('#terminal-input').removeClass('command-highlight');
        }, 2000);
        
        console.log('Loaded command from inline button:', command);
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

    // Add keyboard shortcuts for chat navigation
    $(document).on('keydown', function(e) {
        // Existing shortcuts...
        
        // Ctrl+Home to scroll to top of chat
        if (e.ctrlKey && e.key === 'Home') {
            e.preventDefault();
            scrollToTopOfChat();
        }
        
        // Ctrl+End to scroll to bottom of chat
        if (e.ctrlKey && e.key === 'End') {
            e.preventDefault();
            scrollToBottomOfChat();
        }
        
        // Page Up/Down in chat when chat input is focused
        if ($('#chat-input').is(':focus')) {
            if (e.key === 'PageUp') {
                e.preventDefault();
                const chatMessages = document.getElementById('chat-messages');
                if (chatMessages) {
                    chatMessages.scrollBy({
                        top: -chatMessages.clientHeight * 0.8,
                        behavior: 'smooth'
                    });
                }
            } else if (e.key === 'PageDown') {
                e.preventDefault();
                const chatMessages = document.getElementById('chat-messages');
                if (chatMessages) {
                    chatMessages.scrollBy({
                        top: chatMessages.clientHeight * 0.8,
                        behavior: 'smooth'
                    });
                }
            }
        }
    });

    // Enhanced streaming status monitoring
    setInterval(() => {
        if (isStreamingMode && currentRemoteSessionId) {
            // Check if streaming session is still active
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
                    if (response.status === 'success') {
                        const hasActiveSession = response.active_sessions && 
                            response.active_sessions.some(s => s.command_id == currentStreamingCommandId);
                        
                        if (!hasActiveSession && isStreamingMode) {
                            console.log('Streaming session ended, disabling streaming mode');
                            disableStreamingMode();
                        }
                    }
                },
                error: function() {
                    // On error, assume session ended
                    if (isStreamingMode) {
                        console.log('Error checking streaming status, disabling streaming mode');
                        disableStreamingMode();
                    }
                }
            });
        }
    }, 5000); // Check every 5 seconds

    // Visual feedback for command execution
    $(document).on('ajaxStart', function() {
        if (!isStreamingMode) {
            $('#execution-status').text('Sending command...').addClass('visible');
        }
    });

    $(document).on('ajaxStop', function() {
        setTimeout(() => {
            $('#execution-status').removeClass('visible');
        }, 500);
    });
    
});