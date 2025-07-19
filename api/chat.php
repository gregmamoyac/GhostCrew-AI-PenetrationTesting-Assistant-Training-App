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
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid chat action']);
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
?>