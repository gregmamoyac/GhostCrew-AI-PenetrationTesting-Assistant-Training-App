<?php
// Chat-related API actions (Final Version)
switch ($action) {
    case 'chat_message':
        handleEnhancedChatMessage();
        break;
    case 'get_chat_history':
        getEnhancedChatHistory();
        break;
    case 'submit_feedback':
        submitEnhancedChatFeedback();
        break;
    case 'mark_command_executed':
        markCommandExecuted();
        break;
    case 'rate_chat_message':
        // Legacy support - redirect to submit_feedback
        $_POST['feedback_type'] = ($_POST['rating'] >= 4) ? 'helpful' : 'not_helpful';
        submitEnhancedChatFeedback();
        break;
    case 'execute_suggested_command':
        executeSuggestedCommand();
        break;
    case 'get_command_suggestions':
        getCommandSuggestions();
        break;
    case 'mark_suggestion_used':
        // Legacy support - redirect to mark_command_executed
        markCommandExecuted();
        break;
    case 'submit_feedback':
        // Legacy support - redirect to submit_feedback
        submitChatFeedback();
        break;
    case 'reset_chat_context':
        resetChatContext();
        break;
    case 'analyze_command_result':   // ADDED
        analyzeCommandResult();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid chat action']);
}

// Updated function to store chatbot conversation with message ID tracking (UPDATED)
function storeChatbotConversation($userId, $sessionId, $conversationId, $messageType, $message, $contextData = [], $responseTime = null, $suggestedCommand = null) {
    $adminDb = getAdminDB();
    
    $contextJson = json_encode($contextData);
    
    // Handle session_id properly - use NULL for welcome sessions
    $dbSessionId = ($sessionId && $sessionId !== 'welcome') ? $sessionId : null;
    
    $stmt = $adminDb->prepare("
        INSERT INTO chatbot_conversations 
        (user_id, session_id, conversation_id, message_type, message, context_data, response_time, suggested_command, command_executed) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");
    
    $stmt->bind_param("isssssds", $userId, $dbSessionId, $conversationId, $messageType, $message, $contextJson, $responseTime, $suggestedCommand);
    $stmt->execute();
    
    $messageId = $adminDb->insert_id;
    
    // Log the conversation storage for debugging
    error_log("Stored chatbot conversation - ID: $messageId, Type: $messageType, Has Command: " . (!empty($suggestedCommand) ? 'Yes' : 'No'));
    
    return $messageId;
}

function storeChatbotFeedback($conversationId, $messageId, $userId, $feedbackType, $feedbackText = null) {
    $adminDb = getAdminDB();
    
    $stmt = $adminDb->prepare("
        INSERT INTO chatbot_feedback 
        (conversation_id, message_id, user_id, feedback_type, feedback_text) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("siiss", $conversationId, $messageId, $userId, $feedbackType, $feedbackText);
    $stmt->execute();
    
    return $adminDb->insert_id;
}

function executeSuggestedCommand() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : '';
    
    if ($messageId <= 0 || empty($sessionId) || empty($hostId)) {
        echo json_encode(['status' => 'error', 'message' => 'Message ID, session ID, and host ID are required']);
        return;
    }
    
    try {
        // Get the suggested command
        $adminDb = getAdminDB();
        $stmt = $adminDb->prepare("SELECT suggested_command FROM chatbot_conversations WHERE id = ? AND user_id = ? AND message_type = 'bot'");
        $stmt->bind_param("ii", $messageId, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Message not found or no command available']);
            return;
        }
        
        $message = $result->fetch_assoc();
        $command = $message['suggested_command'];
        
        if (empty($command)) {
            echo json_encode(['status' => 'error', 'message' => 'No command found in message']);
            return;
        }
        
        // Mark command as executed
        $stmt = $adminDb->prepare("UPDATE chatbot_conversations SET command_executed = 1 WHERE id = ?");
        $stmt->bind_param("i", $messageId);
        $stmt->execute();
        
        echo json_encode([
            'status' => 'success', 
            'command' => $command,
            'message' => 'Command ready to execute'
        ]);
    } catch (Exception $e) {
        error_log("Execute suggested command error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
}

function getCommandSuggestions() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
    
    try {
        $adminDb = getAdminDB();
        $stmt = $adminDb->prepare("
            SELECT id, suggested_command, message, timestamp, command_executed
            FROM chatbot_conversations 
            WHERE user_id = ? AND message_type = 'bot' AND suggested_command IS NOT NULL AND suggested_command != ''
            " . ($sessionId ? "AND session_id = ?" : "") . "
            ORDER BY timestamp DESC
            LIMIT ?
        ");
        
        if ($sessionId) {
            $stmt->bind_param("isi", $user['id'], $sessionId, $limit);
        } else {
            $stmt->bind_param("ii", $user['id'], $limit);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $suggestions = [];
        while ($row = $result->fetch_assoc()) {
            $suggestions[] = $row;
        }
        
        echo json_encode(['status' => 'success', 'suggestions' => $suggestions]);
    } catch (Exception $e) {
        error_log("Get command suggestions error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
}

// Updated function to handle enhanced chat messages with multiple commands (UPDATED)
function handleEnhancedChatMessage() {
    $sessionId = sanitize($_REQUEST['session_id'] ?? '');
    $message = sanitize($_REQUEST['message'] ?? '');
    $chatHistoryJson = $_REQUEST['chat_history'] ?? '';
    $conversationId = sanitize($_REQUEST['conversation_id'] ?? '');
    $userId = $_SESSION['user_id'] ?? null;

    if (empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Message is required']);
        exit;
    }

    // Handle session ID properly - use NULL for welcome session
    $dbSessionId = ($sessionId && $sessionId !== 'welcome') ? $sessionId : null;
    
    // Parse chat history as string (not JSON)
    $chatHistory = is_string($chatHistoryJson) ? $chatHistoryJson : '';
    
    try {
        // Build enhanced prompt with session context
        $sessionContext = '';
        if ($dbSessionId) {
            $commandContext = getDetailedSessionCommandContext($dbSessionId, 10);
            $interactiveContext = getInteractiveSessionContext($dbSessionId);
            
            if (!empty($commandContext)) {
                $sessionContext .= "\nRecent command history:\n" . $commandContext;
            }
            
            if (!empty($interactiveContext)) {
                $sessionContext .= "\nCurrent session context: " . $interactiveContext;
            }
        }
        
        // Try to get AI response with chat history as string
        $aiResponse = callAIEndpointWithHistory($message, $chatHistory, $sessionContext);
        
        if ($aiResponse && isset($aiResponse['generated_text'])) {
            // AI response successful
            $botResponse = $aiResponse['generated_text'];
            $isAIResponse = true;
            error_log("AI response received: " . strlen($botResponse) . " characters");
        } else {
            // Fallback to local chatbot
            error_log("AI response failed, using fallback");
            $botResponseData = generateBotResponse($message, [], $userId, $dbSessionId);
            $botResponse = $botResponseData['message'] ?? $botResponseData;
            $isAIResponse = false;
        }

        // Process the response (extract commands, etc.)
        $processedResponse = processAIResponse($botResponse);

        // Store conversations in database with proper session handling
        if (empty($conversationId)) {
            $conversationId = ($dbSessionId ? $dbSessionId : 'welcome') . '_' . time();
        }
        
        // Store user message - use NULL for welcome session
        $userMessageId = storeChatbotConversation($userId, $dbSessionId, $conversationId, 'user', $message);
        
        // Store bot response with proper session handling
        // For multiple commands, we'll store the first command as the main suggested_command
        $primaryCommand = !empty($processedResponse['suggested_commands']) ? $processedResponse['suggested_commands'][0]['command'] : null;
        $botMessageId = storeChatbotConversation(
            $userId, 
            $dbSessionId, 
            $conversationId, 
            'bot', 
            $processedResponse['message'], 
            [
                'ai_generated' => $isAIResponse, 
                'chat_history_length' => strlen($chatHistory),
                'command_count' => count($processedResponse['suggested_commands'])
            ], 
            null, 
            $primaryCommand
        );

        // UPDATE: Add message_id to each suggested command for proper tracking
        foreach ($processedResponse['suggested_commands'] as &$command) {
            $command['message_id'] = $botMessageId;
        }

        echo json_encode([
            'status' => 'success',
            'message' => $processedResponse['message'],
            'bot_response' => $processedResponse['message'],
            'suggested_commands' => $processedResponse['suggested_commands'], // Now includes message_id
            'category' => $processedResponse['category'],
            'bot_message_id' => $botMessageId,
            'conversation_id' => $conversationId,
            'is_ai_generated' => $isAIResponse,
            'chat_history_used' => strlen($chatHistory)
        ]);

    } catch (Exception $e) {
        error_log("Chat AI Error: " . $e->getMessage());
        
        // Fallback to local chatbot on AI failure
        try {
            $fallbackResponse = generateBotResponse($message, [], $userId, $dbSessionId);
            
            // Convert old format to new format with message_id
            $suggestedCommands = [];
            if (!empty($fallbackResponse['suggested_command'])) {
                // Store fallback response to get message ID
                $fallbackMessageId = storeChatbotConversation(
                    $userId, 
                    $dbSessionId, 
                    $conversationId, 
                    'bot', 
                    $fallbackResponse['message'], 
                    ['fallback_used' => true], 
                    null, 
                    $fallbackResponse['suggested_command']
                );
                
                $suggestedCommands[] = [
                    'id' => 'fallback_cmd_0',
                    'message_id' => $fallbackMessageId, // UPDATED: Include message_id
                    'command' => $fallbackResponse['suggested_command'],
                    'description' => $fallbackResponse['command_description'] ?? 'Fallback command suggestion',
                    'type' => 'fallback'
                ];
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => $fallbackResponse['message'],
                'bot_response' => $fallbackResponse['message'],
                'suggested_commands' => $suggestedCommands,
                'category' => $fallbackResponse['category'] ?? 'general',
                'is_ai_generated' => false,
                'fallback_used' => true,
                'chat_history_used' => strlen($chatHistory)
            ]);
        } catch (Exception $fallbackError) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'AI service unavailable and fallback failed'
            ]);
        }
    }
    exit;
}

function callAIEndpointWithHistory($userMessage, $chatHistory = '', $sessionContext = '') {
    try {
        // Build the system prompt
        $systemPrompt = "You are Ghosty the AI Guide, an AI-powered decision-support assistant for junior to mid-level Red Team operators. Your role is to provide real-time, scenario-based guidance during simulated offensive cybersecurity operations. For each user query, clarify the operator's question or action, then suggest 1-3 specific Courses of Action (COAs) based on the provided scenario context, including likely outcomes, risk levels, and stealth effectiveness. Responses should be concise, actionable, and aligned with red team tactics, techniques, and procedures (TTPs). Include a brief explanation of why each COA is recommended, referencing common tools (e.g., Metasploit, Nmap) or techniques where applicable. Maintain a professional, neutral tone and prioritize operational realism and educational value.";
        
        if (!empty($sessionContext)) {
            $systemPrompt .= "\n\nSession Context:" . $sessionContext;
        }
        
        if (!empty($chatHistory)) {
            $systemPrompt .= "\n\nRecent commands executed: " . $chatHistory;
        }
        
        $systemPrompt .= "\n\nLimit responses to 3-4 sentences and include practical command suggestions when relevant.";
        
        // Ensure chatHistory is a string, not an array
        $chatHistoryString = '';
        if (is_array($chatHistory)) {
            $chatHistoryString = implode(', ', $chatHistory);
        } else {
            $chatHistoryString = (string)$chatHistory;
        }
        
        // Prepare the request payload - chat_history must be a string
        $requestData = [
            'mode' => 'operator',
            'input' => $userMessage,
            'chat_history' => $chatHistoryString, // Send as string, not array
            //'system_prompt' => $systemPrompt,
            //'max_tokens' => 1000,
            //'temperature' => 0.7
        ];
        
        error_log("AI Request payload: " . json_encode($requestData));
        
        // AI endpoint configuration
        //$endpoint = 'https://zl47lm7yy1.execute-api.us-east-2.amazonaws.com/invoke';
        $endpoint = 'http://192.168.1.171:8090';
        $headers = [
            'Content-Type: application/json',
            'User-Agent: GhostCrew-Terminal/1.0'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        error_log("AI Response - HTTP: $httpCode, Error: $error, Response length: " . strlen($response));
        
        if ($error) {
            error_log("AI API cURL error: " . $error);
            return null;
        }
        
        if ($httpCode === 200 && $response) {
            $decoded = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                error_log("AI Response successfully decoded as JSON");
                return $decoded;
            } else {
                // AI returned a plain string, not JSON - wrap it in expected format
                error_log("AI Response is plain string, wrapping in expected format");
                return [
                    'generated_text' => trim($response, '"'), // Remove surrounding quotes if present
                    'status' => 'success'
                ];
            }
        } else {
            error_log("AI API HTTP error: $httpCode - Response: " . substr($response, 0, 500));
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("AI endpoint error: " . $e->getMessage());
        return null;
    }
}

function submitChatFeedback() {
    $messageId = sanitize($_REQUEST['message_id'] ?? '');
    $conversationId = sanitize($_REQUEST['conversation_id'] ?? '');
    $feedbackType = sanitize($_REQUEST['feedback_type'] ?? '');
    $feedbackText = sanitize($_REQUEST['feedback_text'] ?? '');
    $userId = $_SESSION['user_id'];

    try {
        $feedbackId = storeChatbotFeedback($conversationId, $messageId, $userId, $feedbackType, $feedbackText);
        return json_encode(['status' => 'success', 'feedback_id' => $feedbackId]);
    } catch (Exception $e) {
        return json_encode(['status' => 'error', 'message' => 'Failed to store feedback']);
    }
}

function getEnhancedChatHistory() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $conversationId = isset($_POST['conversation_id']) ? sanitize($_POST['conversation_id']) : '';
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
    
    try {
        $adminDb = getAdminDB();
        
        $sql = "SELECT id, conversation_id, message_type, message, suggested_command, timestamp, response_time, model_used 
                FROM chatbot_conversations 
                WHERE user_id = ?";
        $params = [$user['id']];
        $types = "i";
        
        if ($sessionId) {
            $sql .= " AND session_id = ?";
            $params[] = $sessionId;
            $types .= "s";
        }
        
        if ($conversationId) {
            $sql .= " AND conversation_id = ?";
            $params[] = $conversationId;
            $types .= "s";
        }
        
        $sql .= " ORDER BY timestamp DESC LIMIT ?";
        $params[] = $limit;
        $types .= "i";
        
        $stmt = $adminDb->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        
        echo json_encode(['status' => 'success', 'messages' => array_reverse($messages)]);
        
    } catch (Exception $e) {
        error_log("Get enhanced chat history error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to get chat history']);
    }
}

function submitEnhancedChatFeedback() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
    $conversationId = isset($_POST['conversation_id']) ? sanitize($_POST['conversation_id']) : '';
    $feedbackType = isset($_POST['feedback_type']) ? sanitize($_POST['feedback_type']) : '';
    $feedbackText = isset($_POST['feedback_text']) ? sanitize($_POST['feedback_text']) : '';
    
    if ($messageId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Message ID is required']);
        return;
    }
    
    try {
        $feedbackId = storeChatbotFeedback($conversationId, $messageId, $user['id'], $feedbackType, $feedbackText);
        echo json_encode(['status' => 'success', 'feedback_id' => $feedbackId]);
    } catch (Exception $e) {
        error_log("Submit enhanced chat feedback error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to store feedback']);
    }
}

// Fixed function to mark command as executed (REMOVED executed_at reference)
function markCommandExecuted() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
    // Keep backwards compatibility with suggestion_id
    if ($messageId <= 0) {
        $messageId = isset($_POST['suggestion_id']) ? (int)$_POST['suggestion_id'] : 0;
    }
    
    if ($messageId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Message ID is required']);
        return;
    }
    
    try {
        $adminDb = getAdminDB();
        
        // Check current status and get command info
        $stmt = $adminDb->prepare("
            SELECT command_executed, suggested_command, message 
            FROM chatbot_conversations 
            WHERE id = ? AND user_id = ? AND message_type = 'bot'
        ");
        $stmt->bind_param("ii", $messageId, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Message not found']);
            return;
        }
        
        $conversation = $result->fetch_assoc();
        
        // Update the command_executed flag (REMOVED executed_at reference)
        $stmt = $adminDb->prepare("
            UPDATE chatbot_conversations 
            SET command_executed = 1 
            WHERE id = ? AND user_id = ? AND message_type = 'bot'
        ");
        $stmt->bind_param("ii", $messageId, $user['id']);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Log the execution for audit purposes
            logDetailedAuditEvent($user['id'], 'command_suggestion_executed', [
                'message_id' => $messageId,
                'suggested_command' => $conversation['suggested_command'],
                'was_previously_executed' => $conversation['command_executed'],
                'execution_timestamp' => date('Y-m-d H:i:s') // Add timestamp to audit log instead
            ]);
            
            echo json_encode([
                'status' => 'success', 
                'message' => 'Command marked as executed',
                'message_id' => $messageId,
                'was_previously_executed' => (bool)$conversation['command_executed']
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update execution status']);
        }
        
    } catch (Exception $e) {
        error_log("Mark command executed error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
}

// Enhanced AI response processing
function processAIResponse($aiResponse) {
    $processedResponse = [
        'message' => $aiResponse,
        'suggested_commands' => [], // Changed from single to array
        'category' => 'general'
    ];
    
    // Clean up and format the message for better display
    $formattedMessage = $aiResponse;
    
    // Extract ALL command suggestions from the response
    $suggestedCommands = extractAllCommands($formattedMessage);
    
    // Determine category based on content
    $categoryKeywords = [
        'file_operations' => ['file', 'folder', 'directory', 'copy', 'move', 'delete', 'dir', 'ls', 'mkdir', 'rmdir'],
        'network' => ['ping', 'telnet', 'ssh', 'ftp', 'network', 'connection', 'netcat', 'nc', 'port', 'socket', 'nmap', 'scan'],
        'system_info' => ['system', 'info', 'version', 'hardware', 'computer', 'systeminfo', 'whoami'],
        'processes' => ['process', 'task', 'running', 'kill', 'service', 'tasklist', 'taskkill'],
        'interactive' => ['telnet', 'ssh', 'msfconsole', 'mysql', 'python', 'netcat', 'nc'],
        'help' => ['help', 'documentation', 'manual', 'guide', 'tutorial', 'how to']
    ];
    
    $detectedCategory = 'general';
    $searchText = strtolower($formattedMessage);
    
    foreach ($categoryKeywords as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($searchText, $keyword) !== false) {
                $detectedCategory = $category;
                break 2;
            }
        }
    }
    
    $processedResponse['suggested_commands'] = $suggestedCommands;
    $processedResponse['category'] = $detectedCategory;
    
    return $processedResponse;
}

// Updated function to extract all commands with proper indexing (UPDATED)
function extractAllCommands($text) {
    $commands = [];
    $commandIndex = 0;
    
    // Pattern 1: Code blocks with optional language
    $codeBlockPattern = '/```(?:bash|cmd|shell|powershell|console)?\s*\n?([^\n`]+?)(?:\n[^`]*?)?\n?```/i';
    if (preg_match_all($codeBlockPattern, $text, $matches)) {
        foreach ($matches[1] as $match) {
            $potentialCommand = trim($match);
            if (isValidCommandSuggestion($potentialCommand)) {
                $commands[] = [
                    'id' => 'cmd_' . $commandIndex++,
                    'command' => $potentialCommand,
                    'description' => 'Command from code block',
                    'type' => 'code_block',
                    'message_id' => null // Will be set by calling function
                ];
            }
        }
    }
    
    // Pattern 2: **Command:** pattern
    $commandPattern = '/\*\*Command:\*\*\s*`([^`]+)`/i';
    if (preg_match_all($commandPattern, $text, $matches)) {
        foreach ($matches[1] as $match) {
            $potentialCommand = trim($match);
            // Check if this command wasn't already found
            $alreadyFound = false;
            foreach ($commands as $existingCmd) {
                if ($existingCmd['command'] === $potentialCommand) {
                    $alreadyFound = true;
                    break;
                }
            }
            
            if (!$alreadyFound && isValidCommandSuggestion($potentialCommand)) {
                $commands[] = [
                    'id' => 'cmd_' . $commandIndex++,
                    'command' => $potentialCommand,
                    'description' => 'Step command',
                    'type' => 'step',
                    'message_id' => null // Will be set by calling function
                ];
            }
        }
    }
    
    // Pattern 3: Inline code with backticks (but not if already found in code blocks or commands)
    $inlineCodePattern = '/`([a-zA-Z][a-zA-Z0-9\-_\s\.\/\\\\]{2,80})`/';
    if (preg_match_all($inlineCodePattern, $text, $matches)) {
        foreach ($matches[1] as $match) {
            $potentialCommand = trim($match);
            // Check if this command wasn't already found
            $alreadyFound = false;
            foreach ($commands as $existingCmd) {
                if ($existingCmd['command'] === $potentialCommand) {
                    $alreadyFound = true;
                    break;
                }
            }
            
            if (!$alreadyFound && isValidCommandSuggestion($potentialCommand) && str_word_count($potentialCommand) <= 6) {
                $commands[] = [
                    'id' => 'cmd_' . $commandIndex++,
                    'command' => $potentialCommand,
                    'description' => 'Inline command suggestion',
                    'type' => 'inline',
                    'message_id' => null // Will be set by calling function
                ];
            }
        }
    }
    
    return $commands;
}

function isValidCommandSuggestion($command) {
    if (!$command || strlen($command) < 2 || strlen($command) > 200) return false;
    
    // Remove any backticks or quotes that might be part of formatting
    $command = preg_replace('/[`"\']/', '', trim($command));
    
    // Check for dangerous patterns first
    $dangerousPatterns = [
        '/[<>"|&;]/', // Dangerous shell characters
        '/format\s+c:/i', // Format commands
        '/del\s+\*\.*/i', // Dangerous delete patterns
        '/shutdown/i', // Shutdown commands
        '/reboot/i', // Reboot commands
    ];

    foreach ($dangerousPatterns as $pattern) {
        if (preg_match($pattern, $command)) {
            return false;
        }
    }
    
    // Check for valid command patterns
    $validPatterns = [
        '/^[a-zA-Z][a-zA-Z0-9\-_]*(\s+[^\s].*)?$/', // Starts with letter, can have arguments
        '/^nmap\s+/', // Nmap commands
        '/^ping\s+/', // Ping commands
        '/^dir(\s|$)/', // Directory listing
        '/^cd(\s|$)/', // Change directory
        '/^ipconfig(\s|$)/', // IP configuration
        '/^netstat(\s|$)/', // Network statistics
        '/^tasklist(\s|$)/', // Task list
        '/^systeminfo(\s|$)/', // System information
    ];

    // Must match at least one valid pattern
    $isValid = false;
    foreach ($validPatterns as $pattern) {
        if (preg_match($pattern, $command)) {
            $isValid = true;
            break;
        }
    }
    
    // Additional checks
    $hasValidStart = preg_match('/^[a-zA-Z]/', $command); // Must start with letter
    $notTooManyWords = str_word_count($command) <= 10; // Not too many arguments
    
    return $isValid && $hasValidStart && $notTooManyWords;
}

function callAIEndpoint($prompt, $customPreamble = null, $sessionId = null) {
    // Convert old format to new format with chat history
    $chatHistory = [];
    
    // Get command context for the session if provided
    $sessionContext = '';
    if ($sessionId) {
        $commandContext = getDetailedSessionCommandContext($sessionId, 5);
        if (!empty($commandContext)) {
            $sessionContext = $commandContext;
        }
    }
    
    // Use the new function with empty chat history
    return callAIEndpointWithHistory($prompt, $chatHistory, $sessionContext);
}

function getDefaultPreamble($commandContext = '', $sessionId = null) {
    $preamble = "You are Ghosty the AI Guide, an AI-powered decision-support assistant for junior to mid-level Red Team operators. Your role is to provide real-time, scenario-based guidance during simulated offensive cybersecurity operations. For each user query, clarify the operator's question or action, then suggest 1-3 specific Courses of Action (COAs) based on the provided scenario context, including likely outcomes, risk levels, and stealth effectiveness. Responses should be concise, actionable, and aligned with red team tactics, techniques, and procedures (TTPs). Include a brief explanation of why each COA is recommended, referencing common tools (e.g., Metasploit, Nmap) or techniques where applicable. Maintain a professional, neutral tone and prioritize operational realism and educational value.";
    
    // Get detailed command history if session is provided
    if ($sessionId) {
        $detailedContext = getDetailedSessionCommandContext($sessionId, 10);
        if (!empty($detailedContext)) {
            $preamble .= "\n\nRecent command history context:\n" . $detailedContext;
        }
        
        // Check if user is in an interactive session
        $interactiveContext = getInteractiveSessionContext($sessionId);
        if ($interactiveContext) {
            $preamble .= "\n\nCurrent session context: " . $interactiveContext;
        }
    } else if (!empty($commandContext)) {
        $preamble .= "\n\nRecent command history context:\n" . $commandContext;
    }
    
    $preamble .= "\n\nLimit responses to 3-4 sentences and include practical command suggestions when relevant.";
    
    return $preamble;
}

function getDetailedSessionCommandContext($sessionId, $limit = 10) {
    try {
        global $conn;
        
        $stmt = $conn->prepare("
            SELECT command, output, exit_code, timestamp, execution_time, status, is_interactive 
            FROM command_history 
            WHERE session_id = ? AND status IN ('completed', 'executing')
            ORDER BY timestamp DESC 
            LIMIT ?
        ");
        $stmt->bind_param("si", $sessionId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $context = [];
        while ($row = $result->fetch_assoc()) {
            $outputPreview = '';
            if ($row['output']) {
                $outputPreview = strlen($row['output']) > 300 ? substr($row['output'], 0, 300) . '...' : $row['output'];
            } else if ($row['status'] === 'executing' && $row['is_interactive']) {
                $outputPreview = '[Interactive session running]';
            } else {
                $outputPreview = '[No output]';
            }
            
            $statusInfo = $row['status'] === 'executing' ? ' (still running)' : '';
            $interactiveInfo = $row['is_interactive'] ? ' [INTERACTIVE]' : '';
            
            $context[] = sprintf(
                "Command: %s%s%s\nResult: %s (exit code: %s)\nExecuted: %s\n",
                $row['command'],
                $interactiveInfo,
                $statusInfo,
                $outputPreview,
                $row['exit_code'] ?? 'N/A',
                $row['timestamp']
            );
        }
        
        return implode("\n", array_reverse($context)); // Chronological order
        
    } catch (Exception $e) {
        error_log("Error getting detailed session context: " . $e->getMessage());
        return '';
    }
}

function getSessionCommandContext($sessionId, $limit = 10) {
    try {
        global $conn;
        
        $stmt = $conn->prepare("
            SELECT command, output, exit_code, timestamp, execution_time 
            FROM command_history 
            WHERE session_id = ? AND status = 'completed'
            ORDER BY timestamp DESC 
            LIMIT ?
        ");
        $stmt->bind_param("si", $sessionId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $context = [];
        while ($row = $result->fetch_assoc()) {
            $outputPreview = strlen($row['output']) > 200 ? substr($row['output'], 0, 200) . '...' : $row['output'];
            $context[] = sprintf(
                "Command: %s\nResult: %s (exit code: %d)\n",
                $row['command'],
                $outputPreview,
                $row['exit_code']
            );
        }
        
        return implode("\n", array_reverse($context)); // Chronological order
        
    } catch (Exception $e) {
        error_log("Error getting session context: " . $e->getMessage());
        return '';
    }
}

function getInteractiveSessionContext($sessionId) {
    try {
        global $conn;
        
        // Check for active interactive sessions
        $stmt = $conn->prepare("
            SELECT ch.command, ss.status 
            FROM streaming_sessions ss 
            JOIN command_history ch ON ss.command_id = ch.id 
            WHERE ss.session_id = ? AND ss.status = 'active'
            ORDER BY ss.start_time DESC 
            LIMIT 1
        ");
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return "User is currently in an interactive session running: " . $row['command'];
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Error getting interactive context: " . $e->getMessage());
        return null;
    }
}

function resetChatContext() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    
    try {
        // Clear any cached context for this session
        // You can add additional cleanup here if needed
        
        echo json_encode(['status' => 'success', 'message' => 'Chat context reset']);
        
    } catch (Exception $e) {
        error_log("Reset chat context error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to reset context']);
    }
}

function analyzeCommandResult() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $command = isset($_POST['command']) ? sanitize($_POST['command']) : '';
    $output = isset($_POST['output']) ? $_POST['output'] : '';
    $executionTime = isset($_POST['execution_time']) ? (float)$_POST['execution_time'] : 0;
    $exitCode = isset($_POST['exit_code']) ? (int)$_POST['exit_code'] : 0;
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $silent = isset($_POST['silent']) ? (bool)$_POST['silent'] : true; // Default to silent
    
    if (empty($command) || empty($output)) {
        echo json_encode(['status' => 'error', 'message' => 'Command and output required']);
        return;
    }
    
    try {
        // Build analysis prompt with context
        $analysisPrompt = buildCommandAnalysisPrompt($command, $output, $executionTime, $exitCode, $sessionId);
        
        // Get AI response with session context
        $aiResponse = callAIEndpoint($analysisPrompt, null, $sessionId);
        
        $analysisText = '';
        $suggestedCommand = null;
        $commandDescription = null;
        
        if ($aiResponse && isset($aiResponse['generated_text'])) {
            $analysisText = $aiResponse['generated_text'];
        } else {
            // Fallback to local analysis
            $analysisData = generateLocalCommandAnalysis($command, $output, $exitCode);
            $analysisText = $analysisData['analysis'];
            $suggestedCommand = $analysisData['suggested_command'];
            $commandDescription = $analysisData['command_description'];
        }
        
        if (!empty($analysisText)) {
            // Process the AI response for commands
            $processedResponse = processAIResponse($analysisText);
            if (!$suggestedCommand && $processedResponse['suggested_command']) {
                $suggestedCommand = $processedResponse['suggested_command'];
                $commandDescription = $processedResponse['command_description'];
            }
            
            // Only store and return if not silent
            if (!$silent) {
                // Format the analysis with command context
                $formattedAnalysis = "🔍 **Command Analysis: `{$command}`**\n\n" . $analysisText;
                
                // Store the analysis in chat history
                $conversationId = $sessionId . '_analysis_' . time();
                $botMessageId = storeChatbotConversation(
                    $user['id'], 
                    $sessionId, 
                    $conversationId, 
                    'bot', 
                    $formattedAnalysis,
                    [
                        'auto_analysis' => true,
                        'analyzed_command' => $command,
                        'exit_code' => $exitCode
                    ],
                    null,
                    $suggestedCommand
                );
                
                echo json_encode([
                    'status' => 'success',
                    'analysis' => $formattedAnalysis,
                    'suggested_command' => $suggestedCommand,
                    'command_description' => $commandDescription,
                    'bot_message_id' => $botMessageId
                ]);
            } else {
                // Silent mode - just return success without storing
                echo json_encode([
                    'status' => 'success',
                    'silent' => true
                ]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to generate analysis']);
        }
        
    } catch (Exception $e) {
        error_log("Command analysis error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Analysis failed']);
    }
}

function buildCommandAnalysisPrompt($command, $output, $executionTime, $exitCode, $sessionId) {
    $prompt = "I just executed this command: \"$command\"\n\n";
    
    if ($exitCode !== 0) {
        $prompt .= "The command failed with exit code $exitCode.\n\n";
    } else {
        $prompt .= "The command completed successfully";
        if ($executionTime > 0) {
            $prompt .= " in " . number_format($executionTime, 2) . " seconds";
        }
        $prompt .= ".\n\n";
    }
    
    // Truncate output for analysis
    $outputForAnalysis = strlen($output) > 1500 ? substr($output, 0, 1500) . "\n[...output truncated...]" : $output;
    $prompt .= "Command output:\n```\n$outputForAnalysis\n```\n\n";
    
    // Add interactive session context
    $interactiveContext = getInteractiveSessionContext($sessionId);
    if ($interactiveContext) {
        $prompt .= "Note: $interactiveContext\n\n";
    }
    
    $prompt .= "Provide a brief analysis of this result and suggest the next logical command to run.";
    
    return $prompt;
}

?>