<?php
// Chat-related API actions
switch ($action) {
    case 'chat_message':
        handleChatMessage();
        break;
    case 'get_chat_history':
        getChatHistory();
        break;
    case 'execute_suggested_command':
        executeSuggestedCommand();
        break;
    case 'rate_chat_message':
        rateChatMessage();
        break;
    case 'get_command_suggestions':
        getCommandSuggestions();
        break;
    case 'mark_suggestion_used':
        markSuggestionUsed();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid chat action']);
}

function handleChatMessage() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    if (empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Message is required']);
        return;
    }
    
    try {
        // Initialize chatbot engine
        $chatbot = new ChatbotEngine($user['id'], $sessionId);
        
        // Get session context
        $context = $chatbot->getSessionContext();
        
        // Process the message
        $response = $chatbot->processMessage($message, $context);
        
        echo json_encode([
            'status' => 'success',
            'message_id' => $response['message_id'],
            'bot_message_id' => $response['bot_message_id'],
            'bot_response' => $response['bot_response'],
            'suggested_command' => $response['suggested_command'],
            'command_description' => $response['command_description'],
            'suggestion_id' => $response['suggestion_id'],
            'category' => $response['category'] ?? 'general',
            'response_time' => $response['response_time']
        ]);
        
    } catch (Exception $e) {
        error_log("Chatbot error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Sorry, I encountered an error processing your request. Please try again.',
            'bot_response' => 'I apologize, but I\'m having technical difficulties right now. Please try asking your question again.'
        ]);
    }
}


function getChatHistory() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
    
    if (empty($sessionId)) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID is required']);
        return;
    }
    
    try {
        $messages = getChatbotHistory($sessionId, $user['id'], $limit);
        echo json_encode(['status' => 'success', 'messages' => $messages]);
    } catch (Exception $e) {
        error_log("Get chat history error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to load chat history']);
    }
}


function executeSuggestedCommand() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $suggestionId = isset($_POST['suggestion_id']) ? (int)$_POST['suggestion_id'] : 0;
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : '';
    
    if ($suggestionId <= 0 || empty($sessionId) || empty($hostId)) {
        echo json_encode(['status' => 'error', 'message' => 'Suggestion ID, session ID, and host ID are required']);
        return;
    }
    
    // Get the suggested command
    $adminDb = getAdminDB();
    $stmt = $adminDb->prepare("SELECT suggested_command FROM command_suggestions WHERE id = ? AND user_id = ? AND executed = 0");
    $stmt->bind_param("ii", $suggestionId, $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Suggestion not found or already executed']);
        return;
    }
    
    $suggestion = $result->fetch_assoc();
    $command = $suggestion['suggested_command'];
    
    // Mark suggestion as executed
    executeChatbotSuggestion($suggestionId, $sessionId, $hostId, $user['id']);
    
    // Execute the command using existing sendCommand logic
    $_POST['host_id'] = $hostId;
    $_POST['command'] = $command;
    $_POST['session_id'] = $sessionId;
    
    sendCommand();
}


function rateChatMessage() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    
    if ($messageId <= 0 || ($rating < 1 || $rating > 5)) {
        echo json_encode(['status' => 'error', 'message' => 'Valid message ID and rating (1-5) are required']);
        return;
    }
    
    $adminDb = getAdminDB();
    $stmt = $adminDb->prepare("UPDATE chatbot_conversations SET rating = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("iii", $rating, $messageId, $user['id']);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Rating saved']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save rating']);
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
    
    $adminDb = getAdminDB();
    $stmt = $adminDb->prepare("
        SELECT cs.*, cc.conversation_id 
        FROM command_suggestions cs
        LEFT JOIN chatbot_conversations cc ON cs.conversation_id = cc.conversation_id
        WHERE cs.user_id = ? AND cs.executed = 0
        " . ($sessionId ? "AND cc.session_id = ?" : "") . "
        ORDER BY cs.priority DESC, cs.created_at DESC
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
}

function markSuggestionUsed() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $suggestionId = isset($_POST['suggestion_id']) ? (int)$_POST['suggestion_id'] : 0;
    
    if ($suggestionId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Valid suggestion ID required']);
        return;
    }
    
    $adminDb = getAdminDB();
    $stmt = $adminDb->prepare("UPDATE command_suggestions SET executed = 1, executed_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $suggestionId, $user['id']);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Suggestion marked as used']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to mark suggestion']);
    }
}


