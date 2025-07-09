<?php
// Enhanced Command-related API actions with FIXED streaming support

// Ensure we always return JSON
header('Content-Type: application/json');

// Capture any output that might interfere with JSON
ob_start();

// Error handler to return JSON errors
function jsonErrorHandler($errno, $errstr, $errfile, $errline) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => "PHP Error: $errstr"]);
    exit;
}
set_error_handler('jsonErrorHandler');

// Exception handler
function jsonExceptionHandler($exception) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Exception: ' . $exception->getMessage()]);
    exit;
}
set_exception_handler('jsonExceptionHandler');

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
    case 'stream_output':
        streamOutput();
        break;
    case 'get_user_input':
        getUserInput();
        break;
    case 'send_user_input':
        sendUserInput();
        break;
    case 'get_streaming_output':
        getStreamingOutput();
        break;
    case 'update_command_output':
        updateCommandOutput();
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid command action']);
}

function updateCommandOutput() {
    global $conn;
    
    $commandId = isset($_POST['command_id']) ? (int)$_POST['command_id'] : 0;
    $output = isset($_POST['output']) ? $_POST['output'] : '';
    $isPartial = isset($_POST['is_partial']) ? (bool)$_POST['is_partial'] : true;
    
    if ($commandId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Command ID required']);
        return;
    }
    
    error_log("Updating command $commandId with output: " . substr($output, 0, 50) . "...");
    
    try {
        if ($isPartial) {
            // For partial updates, append to existing output and mark as executing
            $stmt = $conn->prepare("UPDATE command_history SET output = CONCAT(IFNULL(output, ''), ?), status = 'executing', response_timestamp = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("si", $output, $commandId);
        } else {
            // For final updates, replace output and mark as completed
            $stmt = $conn->prepare("UPDATE command_history SET output = ?, status = 'completed', response_timestamp = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("si", $output, $commandId);
        }
        
        $stmt->execute();
        echo json_encode(['status' => 'success']);
        
    } catch (Exception $e) {
        error_log("Update command output error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to update command output']);
    }
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

// Enhanced sendCommand function with better interactive detection
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
    
    // Determine if command is likely interactive
    $isInteractive = isInteractiveCommand($command);
    
    error_log("Processing command: $command | Interactive: " . ($isInteractive ? 'YES' : 'NO'));
    
    // Store the command in the main database with session context
    global $conn;
    
    // Check if is_interactive column exists, if not, create a simpler insert
    $columnCheck = $conn->query("SHOW COLUMNS FROM command_history LIKE 'is_interactive'");
    $hasInteractiveColumn = $columnCheck && $columnCheck->num_rows > 0;
    
    if ($hasInteractiveColumn) {
        $stmt = $conn->prepare("INSERT INTO command_history (host_id, command, session_id, is_interactive, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->bind_param("sssi", $hostId, $command, $sessionId, $isInteractive);
    } else {
        error_log("is_interactive column not found, using basic insert");
        $stmt = $conn->prepare("INSERT INTO command_history (host_id, command, session_id) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $hostId, $command, $sessionId);
    }
    
    if ($stmt->execute()) {
        $commandId = $stmt->insert_id;
        
        // If interactive, try to create streaming session (only if table exists)
        if ($isInteractive) {
            try {
                $tableCheck = $conn->query("SHOW TABLES LIKE 'streaming_sessions'");
                if ($tableCheck && $tableCheck->num_rows > 0) {
                    $streamStmt = $conn->prepare("INSERT INTO streaming_sessions (command_id, session_id, host_id, status) VALUES (?, ?, ?, 'active')");
                    $streamStmt->bind_param("iss", $commandId, $sessionId, $hostId);
                    $streamStmt->execute();
                    error_log("Created streaming session for command ID: $commandId");
                } else {
                    error_log("streaming_sessions table not found, skipping streaming session creation");
                }
            } catch (Exception $e) {
                error_log("Error creating streaming session: " . $e->getMessage());
            }
        }
        
        // Log command in admin database
        try {
            $adminDb = getAdminDB();
            $adminStmt = $adminDb->prepare("INSERT INTO command_log (session_id, user_id, command, status) VALUES (?, ?, ?, 'pending')");
            $adminStmt->bind_param("sis", $sessionId, $user['id'], $command);
            $adminStmt->execute();
            
            // Update session activity
            $adminStmt = $adminDb->prepare("UPDATE remote_sessions SET last_activity = CURRENT_TIMESTAMP, total_commands = total_commands + 1 WHERE session_id = ?");
            $adminStmt->bind_param("s", $sessionId);
            $adminStmt->execute();
        } catch (Exception $e) {
            error_log("Error updating admin database: " . $e->getMessage());
        }
        
        // Log audit event
        logAuditEvent($user['id'], 'command_execute', [
            'session_id' => $sessionId,
            'host_id' => $hostId,
            'command' => $command,
            'command_id' => $commandId,
            'is_interactive' => $isInteractive
        ]);
        
        echo json_encode([
            'status' => 'success', 
            'command_id' => $commandId,
            'is_interactive' => $isInteractive
        ]);
    } else {
        error_log("Failed to store command: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Failed to store command']);
    }
    
    $stmt->close();
}

function isInteractiveCommand($command) {
    $command = trim($command);
    $commandParts = explode(' ', $command);
    $baseCommand = basename($commandParts[0]);
    
    // Known interactive commands - more comprehensive list
    $interactiveCommands = [
        'telnet', 'ssh', 'ftp', 'sftp', 'mysql', 'psql', 'redis-cli',
        'python', 'python3', 'node', 'irb', 'bc', 'gdb', 'vim', 'nano',
        'less', 'more', 'top', 'htop', 'watch', 'tail', 'ping', 'nc', 'netcat'
    ];
    
    // Check exact command match (including standalone commands like 'telnet' with no args)
    if (in_array($baseCommand, $interactiveCommands)) {
        error_log("Interactive command detected (exact match): $baseCommand from command: $command");
        return true;
    }
    
    // Check for specific interactive patterns
    $interactivePatterns = [
        '/^telnet(\s+.*)?$/',         // telnet with or without arguments
        '/^ssh\s+/',                  // ssh with arguments  
        '/^mysql\s+/',                // mysql with arguments
        '/^psql\s+/',                 // psql with arguments
        '/^python.*-i/',              // python with -i flag
        '/^python3.*-i/',             // python3 with -i flag
        '/^tail\s+.*-f/',             // tail -f
        '/^watch\s+/',                // watch command
        '/^ping\s+/',                 // ping command
        '/^nc\s+/',                   // netcat
        '/^netcat\s+/'                // netcat
    ];
    
    foreach ($interactivePatterns as $pattern) {
        if (preg_match($pattern, $command)) {
            error_log("Interactive command detected (pattern match): $pattern for command: $command");
            return true;
        }
    }
    
    // Check database patterns if available
    global $conn;
    try {
        // Check if the interactive_command_patterns table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'interactive_command_patterns'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("SELECT command_pattern, pattern_type, is_interactive FROM interactive_command_patterns WHERE is_active = 1");
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $pattern = $row['command_pattern'];
                $patternType = $row['pattern_type'];
                $isInteractive = $row['is_interactive'];
                
                if (!$isInteractive) continue;
                
                switch ($patternType) {
                    case 'exact':
                        if ($baseCommand === $pattern) {
                            error_log("Interactive command detected (DB exact): $pattern");
                            return true;
                        }
                        break;
                    case 'prefix':
                        if (strpos($command, $pattern) === 0) {
                            error_log("Interactive command detected (DB prefix): $pattern");
                            return true;
                        }
                        break;
                    case 'contains':
                        if (strpos($command, $pattern) !== false) {
                            error_log("Interactive command detected (DB contains): $pattern");
                            return true;
                        }
                        break;
                    case 'regex':
                        if (preg_match("/$pattern/", $command)) {
                            error_log("Interactive command detected (DB regex): $pattern");
                            return true;
                        }
                        break;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error checking interactive patterns: " . $e->getMessage());
        // Continue with built-in patterns even if DB check fails
    }
    
    error_log("Command NOT detected as interactive: $command");
    return false;
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
    $sql = "SELECT id, command, session_id, is_interactive FROM command_history 
            WHERE host_id = ? AND output IS NULL AND status = 'pending'
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
        
        // Mark command as executing
        $updateStmt = $conn->prepare("UPDATE command_history SET status = 'executing', execution_start = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->bind_param("i", $row['id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        $response = [
            'status' => 'success', 
            'command_id' => $row['id'], 
            'command' => $row['command'],
            'session_id' => $row['session_id'] ?? '',
            'is_interactive' => (bool)$row['is_interactive']
        ];
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'status' => 'success', 
            'command_id' => 0, 
            'command' => '',
            'session_id' => '',
            'is_interactive' => false
        ]);
    }
    
    $stmt->close();
}

// FIXED: Handle streaming output from client
function streamOutput() {
    global $conn;
    
    $commandId = isset($_POST['command_id']) ? (int)$_POST['command_id'] : 0;
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $output = isset($_POST['output']) ? $_POST['output'] : '';
    $isPartial = isset($_POST['is_partial']) ? (bool)$_POST['is_partial'] : true;
    $chunkSequence = isset($_POST['chunk_sequence']) ? (int)$_POST['chunk_sequence'] : 1;
    
    if ($commandId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Command ID required']);
        return;
    }
    
    if (empty($sessionId)) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID required']);
        return;
    }
    
    error_log("Received streaming output for command $commandId (partial: " . ($isPartial ? 'YES' : 'NO') . "): " . substr($output, 0, 100) . "...");
    
    try {
        // Check if streaming tables exist, if not create simple ones
        $tableCheck = $conn->query("SHOW TABLES LIKE 'streaming_output'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            // Create simple streaming table
            $createTableSQL = "CREATE TABLE IF NOT EXISTS streaming_output (
                id int(11) AUTO_INCREMENT PRIMARY KEY,
                command_id int(11) NOT NULL,
                session_id varchar(64) NOT NULL,
                output_chunk longtext NOT NULL,
                chunk_sequence int(11) DEFAULT 1,
                is_partial tinyint(1) DEFAULT 1,
                timestamp timestamp DEFAULT CURRENT_TIMESTAMP,
                last_update timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_command_sequence (command_id, chunk_sequence)
            )";
            $conn->query($createTableSQL);
            error_log("Created streaming_output table");
        }
        
        if ($isPartial) {
            // For partial updates, always append new content, don't replace
            $stmt = $conn->prepare(
                "INSERT INTO streaming_output (command_id, session_id, output_chunk, chunk_sequence, is_partial) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                output_chunk = CONCAT(IFNULL(output_chunk, ''), VALUES(output_chunk)),
                is_partial = VALUES(is_partial),
                last_update = CURRENT_TIMESTAMP"
            );
            $stmt->bind_param("issii", $commandId, $sessionId, $output, $chunkSequence, $isPartial);
            $stmt->execute();
            
            // Update command history status to indicate it's executing/streaming
            $stmt = $conn->prepare("UPDATE command_history SET status = 'executing' WHERE id = ? AND status = 'pending'");
            $stmt->bind_param("i", $commandId);
            $stmt->execute();
            
        } else {
            // Final output - get complete output by concatenating all chunks
            $stmt = $conn->prepare("SELECT GROUP_CONCAT(output_chunk ORDER BY chunk_sequence SEPARATOR '') as complete_output FROM streaming_output WHERE command_id = ?");
            $stmt->bind_param("i", $commandId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $completeOutput = $output; // Default to current output
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                // Only append if this is truly new content
                $existingOutput = $row['complete_output'] ?: '';
                if (!empty($output) && strpos($existingOutput, $output) === false) {
                    $completeOutput = $existingOutput . $output;
                } else {
                    $completeOutput = $existingOutput;
                }
            }
            
            // Insert final chunk
            $stmt = $conn->prepare(
                "INSERT INTO streaming_output (command_id, session_id, output_chunk, chunk_sequence, is_partial) 
                 VALUES (?, ?, ?, ?, 0)
                 ON DUPLICATE KEY UPDATE 
                 output_chunk = VALUES(output_chunk),
                 is_partial = 0,
                 last_update = CURRENT_TIMESTAMP"
            );
            $stmt->bind_param("issi", $commandId, $sessionId, $output, $chunkSequence);
            $stmt->execute();
            
            // Update main command history with complete output
            $stmt = $conn->prepare("UPDATE command_history SET output = ?, status = 'completed', response_timestamp = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("si", $completeOutput, $commandId);
            $stmt->execute();
            
            // Close streaming session if it exists
            $streamStmt = $conn->prepare("UPDATE streaming_sessions SET status = 'completed', end_time = CURRENT_TIMESTAMP WHERE command_id = ?");
            $streamStmt->bind_param("i", $commandId);
            $streamStmt->execute();
            
            error_log("Command $commandId completed with final output length: " . strlen($completeOutput));
        }
        
        echo json_encode(['status' => 'success']);
        
    } catch (Exception $e) {
        error_log("Stream output error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to store streaming output']);
    }
}

function getUserInput() {
    global $conn;
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : '';
    
    if (empty($sessionId)) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID is required']);
        return;
    }
    
    if (empty($hostId)) {
        echo json_encode(['status' => 'error', 'message' => 'Host ID is required']);
        return;
    }
    
    try {
        // Check if user_input_queue table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'user_input_queue'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            echo json_encode([
                'status' => 'success',
                'input' => null
            ]);
            return;
        }
        
        // Get pending user input for this session
        $stmt = $conn->prepare("SELECT id, input_data FROM user_input_queue 
                               WHERE session_id = ? AND host_id = ? AND processed = 0 
                               ORDER BY timestamp ASC LIMIT 1");
        $stmt->bind_param("ss", $sessionId, $hostId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Mark as processed
            $updateStmt = $conn->prepare("UPDATE user_input_queue SET processed = 1, processed_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->bind_param("i", $row['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            error_log("Retrieved user input for session $sessionId: " . $row['input_data']);
            
            echo json_encode([
                'status' => 'success',
                'input' => $row['input_data']
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'input' => null
            ]);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Get user input error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to get user input']);
    }
}

// FIXED: Simple streaming output retrieval with better data handling
function getStreamingOutput() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $commandId = isset($_POST['command_id']) ? (int)$_POST['command_id'] : 0;
    $lastUpdate = isset($_POST['last_update']) ? sanitize($_POST['last_update']) : '1970-01-01 00:00:00';
    
    if (empty($sessionId)) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID required']);
        return;
    }
    
    global $conn;
    
    try {
        // Check if streaming tables exist
        $tableCheck = $conn->query("SHOW TABLES LIKE 'streaming_output'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            // No streaming table, check regular command history for interactive commands
            $sql = "SELECT ch.id as command_id, ch.command, ch.output, ch.timestamp as last_update, ch.status, ch.is_interactive
                    FROM command_history ch 
                    WHERE ch.session_id = ? AND ch.timestamp > ?";
            
            $params = [$sessionId, $lastUpdate];
            $types = "ss";
            
            if ($commandId > 0) {
                $sql .= " AND ch.id = ?";
                $params[] = $commandId;
                $types .= "i";
            }
            
            $sql .= " ORDER BY ch.timestamp DESC LIMIT 10";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $updates = [];
            while ($row = $result->fetch_assoc()) {
                $updates[] = [
                    'command_id' => $row['command_id'],
                    'command' => $row['command'],
                    'output_chunk' => $row['output'] ?: ($row['is_interactive'] ? 'Interactive session running...' : 'Executing...'),
                    'is_partial' => $row['status'] === 'executing',
                    'status' => $row['status'],
                    'streaming_status' => $row['status'] === 'executing' ? 'active' : 'completed',
                    'last_update' => $row['last_update']
                ];
            }
            
            echo json_encode([
                'status' => 'success',
                'updates' => $updates,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            return;
        }
        
        // Use streaming tables - get accumulated output for each command
        $sql = "SELECT so.command_id, 
                       GROUP_CONCAT(so.output_chunk ORDER BY so.chunk_sequence SEPARATOR '') as output_chunk,
                       MAX(so.last_update) as last_update,
                       MIN(so.is_partial) as is_partial,
                       ch.command, ch.status, ch.is_interactive
                FROM streaming_output so
                JOIN command_history ch ON so.command_id = ch.id
                WHERE so.session_id = ? AND so.last_update > ?";
        
        $params = [$sessionId, $lastUpdate];
        $types = "ss";
        
        if ($commandId > 0) {
            $sql .= " AND so.command_id = ?";
            $params[] = $commandId;
            $types .= "i";
        }
        
        $sql .= " GROUP BY so.command_id ORDER BY MAX(so.last_update) DESC LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $updates = [];
        while ($row = $result->fetch_assoc()) {
            $updates[] = [
                'command_id' => $row['command_id'],
                'command' => $row['command'],
                'output_chunk' => $row['output_chunk'] ?: 'Interactive session running...',
                'is_partial' => (bool)$row['is_partial'],
                'status' => $row['status'],
                'streaming_status' => $row['is_partial'] ? 'active' : 'completed',
                'last_update' => $row['last_update']
            ];
        }
        
        if (!empty($updates)) {
            error_log("Returning " . count($updates) . " streaming updates for session $sessionId");
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

// Simple user input sending
function sendUserInput() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $sessionId = isset($_POST['session_id']) ? sanitize($_POST['session_id']) : '';
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : '';
    $input = isset($_POST['input']) ? $_POST['input'] : '';
    
    if (empty($sessionId) || empty($hostId)) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID and Host ID required']);
        return;
    }
    
    global $conn;
    
    try {
        // Check if user_input_queue table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'user_input_queue'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            // Create a simple table if it doesn't exist
            $createTableSQL = "CREATE TABLE IF NOT EXISTS user_input_queue (
                id int(11) AUTO_INCREMENT PRIMARY KEY,
                session_id varchar(64) NOT NULL,
                host_id varchar(50) NOT NULL,
                user_id int(11) DEFAULT NULL,
                input_data text NOT NULL,
                processed tinyint(1) DEFAULT 0,
                timestamp timestamp DEFAULT CURRENT_TIMESTAMP,
                processed_at timestamp NULL DEFAULT NULL
            )";
            $conn->query($createTableSQL);
            error_log("Created user_input_queue table");
        }
        
        // Insert user input into queue
        $stmt = $conn->prepare("INSERT INTO user_input_queue (session_id, host_id, user_id, input_data) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $sessionId, $hostId, $user['id'], $input);
        $stmt->execute();
        
        error_log("Queued user input for session $sessionId, host $hostId: $input");
        echo json_encode(['status' => 'success']);
        
    } catch (Exception $e) {
        error_log("Send user input error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to queue user input']);
    }
}

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
    $stmt = $conn->prepare("SELECT id, command, output, timestamp, execution_time, working_directory, exit_code, is_interactive, status FROM command_history 
                           WHERE session_id = ? 
                           ORDER BY timestamp DESC LIMIT ?");
    $stmt->bind_param("si", $sessionId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $commands = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Get streaming output if available and command is interactive
            if ($row['is_interactive'] && $row['status'] === 'executing') {
                $streamStmt = $conn->prepare("SELECT GROUP_CONCAT(output_chunk ORDER BY chunk_sequence SEPARATOR '') as streaming_output FROM streaming_output WHERE command_id = ?");
                $streamStmt->bind_param("i", $row['id']);
                $streamStmt->execute();
                $streamResult = $streamStmt->get_result();
                if ($streamResult->num_rows > 0) {
                    $streamRow = $streamResult->fetch_assoc();
                    $row['streaming_output'] = $streamRow['streaming_output'];
                    // Use streaming output as main output if available
                    if (!empty($row['streaming_output'])) {
                        $row['output'] = $row['streaming_output'];
                    }
                }
                $streamStmt->close();
            }
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
    $stmt = $conn->prepare("SELECT command, session_id, host_id, is_interactive FROM command_history WHERE id = ?");
    $stmt->bind_param("i", $commandId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Command not found']);
        return;
    }
    
    $commandData = $result->fetch_assoc();
    
    // For interactive commands, don't overwrite the streaming output with generic completion message
    if ($commandData['is_interactive'] && empty($output)) {
        // Just update the metadata, keep the existing streaming output
        $stmt = $conn->prepare("UPDATE command_history SET execution_time = ?, working_directory = ?, exit_code = ?, status = 'completed', response_timestamp = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("dsii", $executionTime, $workingDirectory, $exitCode, $commandId);
    } else {
        // For non-interactive commands or when we have actual output, update normally
        $stmt = $conn->prepare("UPDATE command_history SET output = ?, execution_time = ?, working_directory = ?, exit_code = ?, status = 'completed', response_timestamp = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("sdsii", $output, $executionTime, $workingDirectory, $exitCode, $commandId);
    }

    if ($stmt->execute()) {
        // If this was an interactive command, close the streaming session
        if ($commandData['is_interactive']) {
            $streamStmt = $conn->prepare("UPDATE streaming_sessions SET status = 'completed', end_time = CURRENT_TIMESTAMP WHERE command_id = ?");
            $streamStmt->bind_param("i", $commandId);
            $streamStmt->execute();
            $streamStmt->close();
        }
        
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
                'status' => 'completed',
                'is_interactive' => (bool)$commandData['is_interactive']
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

// Helper function to update chatbot context (implement if not exists)
if (!function_exists('updateChatbotContext')) {
    function updateChatbotContext($sessionId, $contextType, $contextData, $userId = null) {
        try {
            $adminDb = getAdminDB();
            $contextJson = json_encode($contextData);
            
            // Store context in chatbot_context table
            $stmt = $adminDb->prepare("INSERT INTO chatbot_context (session_id, user_id, context_type, context_data) VALUES (?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE context_data = ?, updated_at = CURRENT_TIMESTAMP");
            $stmt->bind_param("sisss", $sessionId, $userId, $contextType, $contextJson, $contextJson);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to update chatbot context: " . $e->getMessage());
        }
    }
}
?>