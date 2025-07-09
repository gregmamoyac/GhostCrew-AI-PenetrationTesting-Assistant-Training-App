<?php
// streaming_api.php - Additional API endpoints for streaming functionality
// Include this in your main api.php or create as a separate endpoint

require_once 'config.php';
require_once 'auth_config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$action = isset($_REQUEST['action']) ? sanitize($_REQUEST['action']) : '';

// Routes for streaming functionality
$streaming_routes = [
    'stream_output'         => 'handleStreamOutput',
    'get_user_input'        => 'handleGetUserInput', 
    'send_user_input'       => 'handleSendUserInput',
    'get_streaming_output'  => 'handleGetStreamingOutput',
    'terminate_streaming'   => 'handleTerminateStreaming',
    'get_streaming_status'  => 'handleGetStreamingStatus',
    'update_streaming_stats'=> 'handleUpdateStreamingStats'
];

// Check if this is a streaming-related action
if (isset($streaming_routes[$action])) {
    call_user_func($streaming_routes[$action]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid streaming action']);
}

function handleStreamOutput() {
    global $conn;
    
    $commandId = isset($_POST['command_id']) ? (int)$_POST['command_id'] : 0;
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $output = isset($_POST['output']) ? $_POST['output'] : '';
    $isPartial = isset($_POST['is_partial']) ? (bool)$_POST['is_partial'] : true;
    $chunkSequence = isset($_POST['chunk_sequence']) ? (int)$_POST['chunk_sequence'] : 1;
    
    if ($commandId <= 0 || empty($sessionId)) {
        echo json_encode(['status' => 'error', 'message' => 'Command ID and Session ID required']);
        return;
    }
    
    try {
        // Start transaction for consistency
        $conn->begin_transaction();
        
        // Insert or update streaming output
        $stmt = $conn->prepare(
            "INSERT INTO streaming_output (command_id, session_id, output_chunk, chunk_sequence, is_partial) 
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE 
             output_chunk = VALUES(output_chunk),
             is_partial = VALUES(is_partial),
             last_update = CURRENT_TIMESTAMP"
        );
        $stmt->bind_param("issii", $commandId, $sessionId, $output, $chunkSequence, $isPartial);
        $stmt->execute();
        
        // Update streaming session statistics
        $stmt = $conn->prepare(
            "UPDATE streaming_sessions 
             SET last_activity = CURRENT_TIMESTAMP, 
                 total_output_size = total_output_size + ?
             WHERE command_id = ?"
        );
        $outputSize = strlen($output);
        $stmt->bind_param("ii", $outputSize, $commandId);
        $stmt->execute();
        
        // If this is final output, update command_history
        if (!$isPartial) {
            // Get complete output by concatenating all chunks
            $stmt = $conn->prepare(
                "SELECT GROUP_CONCAT(output_chunk ORDER BY chunk_sequence SEPARATOR '') as complete_output
                 FROM streaming_output 
                 WHERE command_id = ?"
            );
            $stmt->bind_param("i", $commandId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $completeOutput = $row['complete_output'];
                
                // Update command history with complete output
                $stmt = $conn->prepare(
                    "UPDATE command_history 
                     SET output = ?, status = 'completed', response_timestamp = CURRENT_TIMESTAMP
                     WHERE id = ?"
                );
                $stmt->bind_param("si", $completeOutput, $commandId);
                $stmt->execute();
                
                // Mark streaming session as completed
                $stmt = $conn->prepare(
                    "UPDATE streaming_sessions 
                     SET status = 'completed', end_time = CURRENT_TIMESTAMP
                     WHERE command_id = ?"
                );
                $stmt->bind_param("i", $commandId);
                $stmt->execute();
            }
        }
        
        $conn->commit();
        echo json_encode(['status' => 'success']);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Stream output error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to store streaming output']);
    }
}

function handleGetUserInput() {
    global $conn;
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : '';
    
    if (empty($sessionId) || empty($hostId)) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID and Host ID required']);
        return;
    }
    
    try {
        // Get the next pending input for this session
        $stmt = $conn->prepare(
            "SELECT id, input_data, input_type, priority 
             FROM user_input_queue 
             WHERE session_id = ? AND host_id = ? AND processed = 0 
             ORDER BY priority DESC, timestamp ASC 
             LIMIT 1"
        );
        $stmt->bind_param("ss", $sessionId, $hostId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Mark as processed
            $updateStmt = $conn->prepare(
                "UPDATE user_input_queue 
                 SET processed = 1, processed_at = CURRENT_TIMESTAMP 
                 WHERE id = ?"
            );
            $updateStmt->bind_param("i", $row['id']);
            $updateStmt->execute();
            
            echo json_encode([
                'status' => 'success',
                'input' => $row['input_data'],
                'input_type' => $row['input_type'],
                'priority' => $row['priority']
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'input' => null
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Get user input error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to get user input']);
    }
}

function handleSendUserInput() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    global $conn;
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : '';
    $input = isset($_POST['input']) ? $_POST['input'] : '';
    $inputType = isset($_POST['input_type']) ? sanitize($_POST['input_type']) : 'response';
    $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 5;
    
    if (empty($sessionId) || empty($hostId)) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID and Host ID required']);
        return;
    }
    
    try {
        // Verify user has access to this session
        $adminDb = getAdminDB();
        $stmt = $adminDb->prepare(
            "SELECT id FROM remote_sessions 
             WHERE session_id = ? AND user_id = ? AND status = 'active'"
        );
        $stmt->bind_param("si", $sessionId, $user['id']);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Session not found or access denied']);
            return;
        }
        
        // Insert user input into queue
        $stmt = $conn->prepare(
            "INSERT INTO user_input_queue (session_id, host_id, user_id, input_data, input_type, priority) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssissi", $sessionId, $hostId, $user['id'], $input, $inputType, $priority);
        $stmt->execute();
        
        // Log the interaction in admin database
        $adminStmt = $adminDb->prepare(
            "INSERT INTO user_interactions (session_id, user_id, interaction_type, interaction_data) 
             VALUES (?, ?, 'input', ?)"
        );
        $interactionData = json_encode([
            'input' => $input,
            'input_type' => $inputType,
            'priority' => $priority,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        $adminStmt->bind_param("sis", $sessionId, $user['id'], $interactionData);
        $adminStmt->execute();
        
        // Update streaming session input count
        $stmt = $conn->prepare(
            "UPDATE streaming_sessions 
             SET total_input_lines = total_input_lines + 1, last_activity = CURRENT_TIMESTAMP
             WHERE session_id = ? AND status = 'active'"
        );
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        
        echo json_encode(['status' => 'success']);
        
    } catch (Exception $e) {
        error_log("Send user input error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to queue user input']);
    }
}

function handleGetStreamingOutput() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    global $conn;
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $lastUpdate = isset($_POST['last_update']) ? sanitize($_POST['last_update']) : '1970-01-01 00:00:00';
    $commandId = isset($_POST['command_id']) ? (int)$_POST['command_id'] : 0;
    $limit = isset($_POST['limit']) ? min((int)$_POST['limit'], 100) : 50;
    
    if (empty($sessionId)) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID required']);
        return;
    }
    
    try {
        // Verify user has access to this session
        $adminDb = getAdminDB();
        $stmt = $adminDb->prepare(
            "SELECT id FROM remote_sessions 
             WHERE session_id = ? AND user_id = ?"
        );
        $stmt->bind_param("si", $sessionId, $user['id']);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Session not found or access denied']);
            return;
        }
        
        // Build query for streaming updates
        $sql = "SELECT so.command_id, so.output_chunk, so.chunk_sequence, so.is_partial, 
                       so.last_update, ch.command, ch.status, ss.status as streaming_status
                FROM streaming_output so
                JOIN command_history ch ON so.command_id = ch.id
                LEFT JOIN streaming_sessions ss ON so.command_id = ss.command_id
                WHERE so.session_id = ? AND so.last_update > ?";
        
        $params = [$sessionId, $lastUpdate];
        $types = "ss";
        
        if ($commandId > 0) {
            $sql .= " AND so.command_id = ?";
            $params[] = $commandId;
            $types .= "i";
        }
        
        $sql .= " ORDER BY so.command_id, so.chunk_sequence LIMIT ?";
        $params[] = $limit;
        $types .= "i";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $updates = [];
        $commandOutputs = [];
        
        while ($row = $result->fetch_assoc()) {
            $cmdId = $row['command_id'];
            
            // Group chunks by command ID
            if (!isset($commandOutputs[$cmdId])) {
                $commandOutputs[$cmdId] = [
                    'command_id' => $cmdId,
                    'command' => $row['command'],
                    'status' => $row['status'],
                    'streaming_status' => $row['streaming_status'],
                    'chunks' => [],
                    'last_update' => $row['last_update']
                ];
            }
            
            $commandOutputs[$cmdId]['chunks'][] = [
                'sequence' => $row['chunk_sequence'],
                'output' => $row['output_chunk'],
                'is_partial' => (bool)$row['is_partial']
            ];
            
            // Keep track of latest update time
            if ($row['last_update'] > $commandOutputs[$cmdId]['last_update']) {
                $commandOutputs[$cmdId]['last_update'] = $row['last_update'];
            }
        }
        
        // Concatenate chunks for each command
        foreach ($commandOutputs as $cmdId => $cmdData) {
            usort($cmdData['chunks'], function($a, $b) {
                return $a['sequence'] - $b['sequence'];
            });
            
            $concatenatedOutput = '';
            $isPartial = false;
            
            foreach ($cmdData['chunks'] as $chunk) {
                $concatenatedOutput .= $chunk['output'];
                if ($chunk['is_partial']) {
                    $isPartial = true;
                }
            }
            
            $updates[] = [
                'command_id' => $cmdId,
                'command' => $cmdData['command'],
                'output_chunk' => $concatenatedOutput,
                'is_partial' => $isPartial,
                'status' => $cmdData['status'],
                'streaming_status' => $cmdData['streaming_status'],
                'last_update' => $cmdData['last_update']
            ];
        }
        
        echo json_encode([
            'status' => 'success',
            'updates' => $updates,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        error_log("Get streaming output error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to get streaming output']);
    }
}

function handleTerminateStreaming() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    global $conn;
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $commandId = isset($_POST['command_id']) ? (int)$_POST['command_id'] : 0;
    
    if (empty($sessionId) || $commandId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID and Command ID required']);
        return;
    }
    
    try {
        // Send termination signal (Ctrl+C) to the command
        $stmt = $conn->prepare(
            "INSERT INTO user_input_queue (session_id, host_id, user_id, input_data, input_type, priority, command_id) 
             SELECT ?, ch.host_id, ?, ?, 'ctrl_signal', 10, ?
             FROM command_history ch 
             WHERE ch.id = ? AND ch.session_id = ?"
        );
        $ctrlC = chr(3); // Ctrl+C character
        $stmt->bind_param("sisiii", $sessionId, $user['id'], $ctrlC, $commandId, $commandId, $sessionId);
        $stmt->execute();
        
        // Mark streaming session as terminated
        $stmt = $conn->prepare(
            "UPDATE streaming_sessions 
             SET status = 'terminated', end_time = CURRENT_TIMESTAMP
             WHERE command_id = ? AND session_id = ?"
        );
        $stmt->bind_param("is", $commandId, $sessionId);
        $stmt->execute();
        
        // Log termination
        $adminDb = getAdminDB();
        $adminStmt = $adminDb->prepare(
            "INSERT INTO user_interactions (session_id, user_id, interaction_type, interaction_data) 
             VALUES (?, ?, 'termination', ?)"
        );
        $terminationData = json_encode([
            'command_id' => $commandId,
            'action' => 'terminate_streaming',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        $adminStmt->bind_param("sis", $sessionId, $user['id'], $terminationData);
        $adminStmt->execute();
        
        echo json_encode(['status' => 'success', 'message' => 'Termination signal sent']);
        
    } catch (Exception $e) {
        error_log("Terminate streaming error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to terminate streaming command']);
    }
}

function handleGetStreamingStatus() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    global $conn;
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    
    if (empty($sessionId)) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID required']);
        return;
    }
    
    try {
        // Get active streaming sessions for this user session
        $stmt = $conn->prepare(
            "SELECT ss.id, ss.command_id, ss.status, ss.start_time, ss.last_activity,
                    ss.total_input_lines, ss.total_output_size,
                    ch.command, ch.status as command_status,
                    TIMESTAMPDIFF(SECOND, ss.start_time, NOW()) as duration_seconds
             FROM streaming_sessions ss
             JOIN command_history ch ON ss.command_id = ch.id
             WHERE ss.session_id = ? AND ss.status IN ('active', 'paused')
             ORDER BY ss.last_activity DESC"
        );
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $activeSessions = [];
        while ($row = $result->fetch_assoc()) {
            $activeSessions[] = $row;
        }
        
        // Get pending input count
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as pending_count
             FROM user_input_queue
             WHERE session_id = ? AND processed = 0"
        );
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        $pendingResult = $stmt->get_result();
        $pendingCount = $pendingResult->fetch_assoc()['pending_count'];
        
        // Get recent completed streaming sessions
        $stmt = $conn->prepare(
            "SELECT ss.command_id, ss.start_time, ss.end_time, ss.total_input_lines, ss.total_output_size,
                    ch.command, TIMESTAMPDIFF(SECOND, ss.start_time, ss.end_time) as duration_seconds
             FROM streaming_sessions ss
             JOIN command_history ch ON ss.command_id = ch.id
             WHERE ss.session_id = ? AND ss.status = 'completed'
             ORDER BY ss.end_time DESC
             LIMIT 5"
        );
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $recentSessions = [];
        while ($row = $result->fetch_assoc()) {
            $recentSessions[] = $row;
        }
        
        echo json_encode([
            'status' => 'success',
            'active_sessions' => $activeSessions,
            'pending_input_count' => $pendingCount,
            'recent_completed' => $recentSessions,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        error_log("Get streaming status error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to get streaming status']);
    }
}

function handleUpdateStreamingStats() {
    global $conn;
    
    $commandId = isset($_POST['command_id']) ? (int)$_POST['command_id'] : 0;
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $statsData = isset($_POST['stats_data']) ? $_POST['stats_data'] : '';
    
    if ($commandId <= 0 || empty($sessionId)) {
        echo json_encode(['status' => 'error', 'message' => 'Command ID and Session ID required']);
        return;
    }
    
    try {
        // Parse stats data
        $stats = json_decode($statsData, true);
        if (!$stats) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid stats data']);
            return;
        }
        
        // Update streaming session with performance metrics
        $updateFields = [];
        $params = [];
        $types = "";
        
        if (isset($stats['cpu_usage'])) {
            $updateFields[] = "cpu_usage = ?";
            $params[] = (float)$stats['cpu_usage'];
            $types .= "d";
        }
        
        if (isset($stats['memory_usage'])) {
            $updateFields[] = "memory_usage = ?";
            $params[] = (int)$stats['memory_usage'];
            $types .= "i";
        }
        
        if (isset($stats['bytes_read'])) {
            $updateFields[] = "total_output_size = total_output_size + ?";
            $params[] = (int)$stats['bytes_read'];
            $types .= "i";
        }
        
        if (isset($stats['lines_processed'])) {
            $updateFields[] = "total_input_lines = total_input_lines + ?";
            $params[] = (int)$stats['lines_processed'];
            $types .= "i";
        }
        
        if (!empty($updateFields)) {
            $sql = "UPDATE streaming_sessions SET " . implode(", ", $updateFields) . 
                   ", last_activity = CURRENT_TIMESTAMP WHERE command_id = ?";
            $params[] = $commandId;
            $types .= "i";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
        }
        
        // Store detailed analytics
        foreach ($stats as $metricName => $metricValue) {
            $stmt = $conn->prepare(
                "INSERT INTO session_analytics (session_id, host_id, metric_name, metric_value, metric_data)
                 SELECT ?, ch.host_id, ?, ?, ?
                 FROM command_history ch WHERE ch.id = ?"
            );
            $metricData = json_encode(['command_id' => $commandId, 'timestamp' => time()]);
            $stmt->bind_param("ssdsi", $sessionId, $metricName, $metricValue, $metricData, $commandId);
            $stmt->execute();
        }
        
        echo json_encode(['status' => 'success']);
        
    } catch (Exception $e) {
        error_log("Update streaming stats error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to update streaming stats']);
    }
}

// Helper function to cleanup old streaming data
function cleanupStreamingData($retentionDays = 7) {
    global $conn;
    
    try {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        
        // Clean up old streaming output chunks
        $stmt = $conn->prepare("DELETE FROM streaming_output WHERE timestamp < ?");
        $stmt->bind_param("s", $cutoffDate);
        $stmt->execute();
        $deletedOutputs = $stmt->affected_rows;
        
        // Clean up processed user input
        $stmt = $conn->prepare("DELETE FROM user_input_queue WHERE processed = 1 AND processed_at < ?");
        $stmt->bind_param("s", $cutoffDate);
        $stmt->execute();
        $deletedInputs = $stmt->affected_rows;
        
        // Clean up old session analytics
        $stmt = $conn->prepare("DELETE FROM session_analytics WHERE recorded_at < ?");
        $stmt->bind_param("s", $cutoffDate);
        $stmt->execute();
        $deletedAnalytics = $stmt->affected_rows;
        
        return [
            'deleted_outputs' => $deletedOutputs,
            'deleted_inputs' => $deletedInputs,
            'deleted_analytics' => $deletedAnalytics
        ];
        
    } catch (Exception $e) {
        error_log("Cleanup streaming data error: " . $e->getMessage());
        return false;
    }
}

// Helper function to get streaming statistics
function getStreamingStatistics($timeframe = '24 HOUR') {
    global $conn;
    
    try {
        $stats = [];
        
        // Total streaming sessions
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as total_sessions,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_sessions,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_sessions,
                    AVG(TIMESTAMPDIFF(SECOND, start_time, COALESCE(end_time, NOW()))) as avg_duration
             FROM streaming_sessions 
             WHERE start_time >= DATE_SUB(NOW(), INTERVAL ? {$timeframe})"
        );
        $stmt->bind_param("i", 1);
        $stmt->execute();
        $stats['sessions'] = $stmt->get_result()->fetch_assoc();
        
        // Output statistics
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as total_chunks,
                    SUM(LENGTH(output_chunk)) as total_bytes,
                    AVG(LENGTH(output_chunk)) as avg_chunk_size
             FROM streaming_output so
             JOIN streaming_sessions ss ON so.command_id = ss.command_id
             WHERE ss.start_time >= DATE_SUB(NOW(), INTERVAL ? {$timeframe})"
        );
        $stmt->bind_param("i", 1);
        $stmt->execute();
        $stats['output'] = $stmt->get_result()->fetch_assoc();
        
        // Input statistics
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as total_inputs,
                    COUNT(CASE WHEN processed = 1 THEN 1 END) as processed_inputs,
                    AVG(TIMESTAMPDIFF(SECOND, timestamp, processed_at)) as avg_processing_time
             FROM user_input_queue
             WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? {$timeframe})"
        );
        $stmt->bind_param("i", 1);
        $stmt->execute();
        $stats['input'] = $stmt->get_result()->fetch_assoc();
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Get streaming statistics error: " . $e->getMessage());
        return false;
    }
}

// Performance monitoring endpoint
if ($action === 'get_streaming_performance') {
    requireAuth();
    
    $timeframe = isset($_POST['timeframe']) ? sanitize($_POST['timeframe']) : '24 HOUR';
    $allowedTimeframes = ['1 HOUR', '24 HOUR', '7 DAY', '30 DAY'];
    
    if (!in_array($timeframe, $allowedTimeframes)) {
        $timeframe = '24 HOUR';
    }
    
    $stats = getStreamingStatistics($timeframe);
    
    if ($stats !== false) {
        echo json_encode([
            'status' => 'success',
            'timeframe' => $timeframe,
            'statistics' => $stats,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to get performance statistics']);
    }
}

// Cleanup endpoint (admin only)
if ($action === 'cleanup_streaming_data') {
    requireAuth();
    $user = getCurrentUser();
    
    if ($user['role'] !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Admin access required']);
        return;
    }
    
    $retentionDays = isset($_POST['retention_days']) ? (int)$_POST['retention_days'] : 7;
    $retentionDays = max(1, min(365, $retentionDays)); // Between 1 and 365 days
    
    $result = cleanupStreamingData($retentionDays);
    
    if ($result !== false) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Cleanup completed',
            'retention_days' => $retentionDays,
            'deleted_records' => $result
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Cleanup failed']);
    }
}

// WebSocket-like long polling for real-time updates
if ($action === 'poll_streaming_updates') {
    requireAuth();
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $lastPoll = isset($_POST['last_poll']) ? sanitize($_POST['last_poll']) : date('Y-m-d H:i:s');
    $timeout = isset($_POST['timeout']) ? min((int)$_POST['timeout'], 30) : 10; // Max 30 seconds
    
    if (empty($sessionId)) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID required']);
        return;
    }
    
    $startTime = time();
    $updates = [];
    
    // Poll for updates with timeout
    while ((time() - $startTime) < $timeout) {
        // Check for new streaming output
        $stmt = $conn->prepare(
            "SELECT 'output' as update_type, so.command_id, so.last_update as timestamp,
                    'New output available' as message
             FROM streaming_output so
             WHERE so.session_id = ? AND so.last_update > ?
             UNION ALL
             SELECT 'input' as update_type, 0 as command_id, uiq.timestamp,
                    'New input processed' as message
             FROM user_input_queue uiq
             WHERE uiq.session_id = ? AND uiq.processed_at > ?
             ORDER BY timestamp DESC
             LIMIT 10"
        );
        $stmt->bind_param("ssss", $sessionId, $lastPoll, $sessionId, $lastPoll);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $updates[] = $row;
            }
            break; // Exit polling loop if updates found
        }
        
        // Sleep for a short time before checking again
        usleep(500000); // 0.5 seconds
    }
    
    echo json_encode([
        'status' => 'success',
        'updates' => $updates,
        'poll_duration' => time() - $startTime,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>