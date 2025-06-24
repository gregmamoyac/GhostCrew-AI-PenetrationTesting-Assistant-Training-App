<?php
// Command-related API actions
switch ($action) {
    case 'send_command':
        sendCommand();
        break;
    case 'get_command':
        getCommand();
        break;
    case 'submit_result':
        submitResult();
        break;
    case 'get_command_history':
        getCommandHistory();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid command action']);
}

function updateCommandStatistics($hostId, $command, $executionTime, $success) {
    global $conn;
    
    $commandBase = strtolower(explode(' ', trim($command))[0]);
    
    $stmt = $conn->prepare("
        INSERT INTO command_statistics (host_id, command_base, execution_count, avg_execution_time, success_rate) 
        VALUES (?, ?, 1, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        execution_count = execution_count + 1,
        avg_execution_time = (avg_execution_time * (execution_count - 1) + ?) / execution_count,
        success_rate = (success_rate * (execution_count - 1) + ?) / execution_count
    ");
    
    $successRate = $success ? 100.0 : 0.0;
    $stmt->bind_param("ssdddd", $hostId, $commandBase, $executionTime, $successRate, $executionTime, $successRate);
    $stmt->execute();
}


function sendCommand() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : '';
    $command = isset($_POST['command']) ? $_POST['command'] : '';
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    
    if (empty($hostId) || empty($command) || empty($sessionId)) {
        echo json_encode(['status' => 'error', 'message' => 'Host ID, command, and session ID are required']);
        return;
    }
    
    // Store the command in the main database with session context
    global $conn;
    $stmt = $conn->prepare("INSERT INTO command_history (host_id, command, session_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $hostId, $command, $sessionId);
    
    if ($stmt->execute()) {
        $commandId = $stmt->insert_id;
        
        // Log command in admin database
        $adminDb = getAdminDB();
        $adminStmt = $adminDb->prepare("INSERT INTO command_log (session_id, user_id, command, status) VALUES (?, ?, ?, 'pending')");
        $adminStmt->bind_param("sis", $sessionId, $user['id'], $command);
        $adminStmt->execute();
        
        // Update session activity
        $adminStmt = $adminDb->prepare("UPDATE remote_sessions SET last_activity = CURRENT_TIMESTAMP, total_commands = total_commands + 1 WHERE session_id = ?");
        $adminStmt->bind_param("s", $sessionId);
        $adminStmt->execute();
        
        // Log audit event
        logAuditEvent($user['id'], 'command_execute', [
            'session_id' => $sessionId,
            'host_id' => $hostId,
            'command' => $command,
            'command_id' => $commandId
        ]);
        
        // Update session context for chatbot
        updateChatbotContext($sessionId, 'command_history', [
            'command' => $command,
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'pending'
        ], $user['id']);
        
        echo json_encode(['status' => 'success', 'command_id' => $commandId]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to store command']);
    }
    
    $stmt->close();
}


function getCommand() {
    global $conn;
    
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : '';
    $instanceToken = isset($_POST['instance_token']) ? sanitize($_POST['instance_token']) : '';
    
    if (empty($hostId)) {
        echo json_encode(['status' => 'error', 'message' => 'Host ID is required']);
        return;
    }
    
    // Validate instance token if provided
    if (!empty($instanceToken) && !validateInstanceToken($instanceToken)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired instance token']);
        return;
    }
    
    // Update host's last seen timestamp
    $stmt = $conn->prepare("UPDATE hosts SET last_seen = CURRENT_TIMESTAMP WHERE host_id = ?");
    if ($stmt) {
        $stmt->bind_param("s", $hostId);
        $stmt->execute();
        $stmt->close();
    }
    
    // Get the oldest command without output
    $sql = "SELECT id, command, session_id FROM command_history 
            WHERE host_id = ? AND output IS NULL 
            ORDER BY timestamp ASC LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'Database prepare error: ' . $conn->error]);
        return;
    }
    
    $stmt->bind_param("s", $hostId);
    
    if (!$stmt->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Query execution failed: ' . $stmt->error]);
        $stmt->close();
        return;
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $response = [
            'status' => 'success', 
            'command_id' => $row['id'], 
            'command' => $row['command'],
            'session_id' => $row['session_id'] ?? ''
        ];
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'status' => 'success', 
            'command_id' => 0, 
            'command' => '',
            'session_id' => ''
        ]);
    }
    
    $stmt->close();
}
# test

function getCommandHistory() {
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
    
    global $conn;
    $stmt = $conn->prepare("SELECT id, command, output, timestamp, execution_time, working_directory, exit_code FROM command_history WHERE session_id = ? ORDER BY timestamp DESC LIMIT ?");
    $stmt->bind_param("si", $sessionId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $commands = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $commands[] = $row;
        }
    }
    
    echo json_encode(['status' => 'success', 'commands' => $commands]);
    $stmt->close();
}

function submitResult() {
    global $conn;
    
    $commandId = isset($_POST['command_id']) ? (int)$_POST['command_id'] : 0;
    $output = isset($_POST['output']) ? $_POST['output'] : '';
    $executionTime = isset($_POST['execution_time']) ? (float)$_POST['execution_time'] : null;
    $workingDirectory = isset($_POST['working_directory']) ? sanitize($_POST['working_directory']) : null;
    $exitCode = isset($_POST['exit_code']) ? (int)$_POST['exit_code'] : null;
    
    if ($commandId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Command ID is required']);
        return;
    }
    
    // Get command details for logging
    $stmt = $conn->prepare("SELECT command, session_id, host_id FROM command_history WHERE id = ?");
    $stmt->bind_param("i", $commandId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Command not found']);
        return;
    }
    
    $commandData = $result->fetch_assoc();
    
    // Store the command output in the main database
    $stmt = $conn->prepare("UPDATE command_history SET output = ?, execution_time = ?, working_directory = ?, exit_code = ?, response_timestamp = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("sdsii", $output, $executionTime, $workingDirectory, $exitCode, $commandId);
    
    if ($stmt->execute()) {
        // Update admin database
        $adminDb = getAdminDB();
        $adminStmt = $adminDb->prepare("UPDATE command_log SET output = ?, execution_time = ?, status = 'completed', response_timestamp = CURRENT_TIMESTAMP WHERE session_id = ? AND command = ?");
        $adminStmt->bind_param("sdss", $output, $executionTime, $commandData['session_id'], $commandData['command']);
        $adminStmt->execute();
        
        // Update command statistics
        updateCommandStatistics($commandData['host_id'], $commandData['command'], $executionTime, $exitCode === 0);
        
        // Update session context for chatbot
        if (!empty($commandData['session_id'])) {
            updateChatbotContext($commandData['session_id'], 'command_history', [
                'command' => $commandData['command'],
                'output' => $output,
                'working_directory' => $workingDirectory,
                'exit_code' => $exitCode,
                'execution_time' => $executionTime,
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => 'completed'
            ]);
            
            // Update working directory context
            if (!empty($workingDirectory)) {
                updateChatbotContext($commandData['session_id'], 'working_directory', [
                    'current_directory' => $workingDirectory,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to store command output']);
    }
    
    $stmt->close();
}

