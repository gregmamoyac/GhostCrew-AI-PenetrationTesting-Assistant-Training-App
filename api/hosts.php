<?php
// Host-related API actions

switch ($action) {
    case 'register_host':
        registerHost();
        break;
    case 'ping_host':
        pingHost();
        break;
    case 'get_hosts':
        getHosts();
        break;
    case 'mark_host_disconnected':
        markHostDisconnected();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid host action']);
}

function registerHost() {
    global $conn;
    
    // Get host information from the request
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : uniqid('host_');
    $hostname = isset($_POST['hostname']) ? sanitize($_POST['hostname']) : 'Unknown';
    $ipAddress = isset($_POST['ip_address']) ? sanitize($_POST['ip_address']) : $_SERVER['REMOTE_ADDR'];
    $osInfo = isset($_POST['os_info']) ? sanitize($_POST['os_info']) : 'Unknown';
    $instanceToken = isset($_POST['instance_token']) ? sanitize($_POST['instance_token']) : '';
    
    // Validate instance token if provided
    $userId = null;
    if (!empty($instanceToken)) {
        if (!validateInstanceToken($instanceToken)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired instance token']);
            return;
        }
        
        // Get user ID from instance token
        $adminDb = getAdminDB();
        $stmt = $adminDb->prepare("SELECT user_id FROM user_instance_tokens WHERE instance_token = ? AND is_active = 1");
        $stmt->bind_param("s", $instanceToken);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $userId = $result->fetch_assoc()['user_id'];
        }
    }
    
    // Check if the host already exists (by host_id, hostname, or IP)
    $stmt = $conn->prepare("SELECT id, host_id FROM hosts WHERE host_id = ? OR (hostname = ? AND ip_address = ?)");
    $stmt->bind_param("sss", $hostId, $hostname, $ipAddress);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Get existing host info
        $existingHost = $result->fetch_assoc();
        $actualHostId = $existingHost['host_id'];
        
        // Update existing host with latest info
        $stmt = $conn->prepare("UPDATE hosts SET hostname = ?, ip_address = ?, os_info = ?, connected = 1, last_seen = CURRENT_TIMESTAMP WHERE host_id = ?");
        $stmt->bind_param("ssss", $hostname, $ipAddress, $osInfo, $actualHostId);
        
        // Use the existing host_id for response
        $hostId = $actualHostId;
    } else {
        // Insert new host
        $stmt = $conn->prepare("INSERT INTO hosts (host_id, hostname, ip_address, os_info) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $hostId, $hostname, $ipAddress, $osInfo);
    }
    
    if ($stmt->execute()) {
        // Map host to instance token if provided
        if (!empty($instanceToken) && $userId) {
            $expiresAt = date('Y-m-d H:i:s', time() + INSTANCE_TOKEN_LIFETIME);
            $stmt = $conn->prepare("INSERT INTO host_instance_mappings (host_id, instance_token, user_id, expires_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), expires_at = VALUES(expires_at), is_active = 1");
            $stmt->bind_param("ssis", $hostId, $instanceToken, $userId, $expiresAt);
            $stmt->execute();
        }
        
        // Also update/insert in admin database for tracking
        $adminDb = getAdminDB();
        $adminStmt = $adminDb->prepare("INSERT INTO hosts_info (host_id, hostname, ip_address, os_info) 
                                       VALUES (?, ?, ?, ?) 
                                       ON DUPLICATE KEY UPDATE 
                                       hostname = VALUES(hostname), 
                                       ip_address = VALUES(ip_address), 
                                       os_info = VALUES(os_info), 
                                       last_seen = CURRENT_TIMESTAMP,
                                       is_active = 1");
        $adminStmt->bind_param("ssss", $hostId, $hostname, $ipAddress, $osInfo);
        $adminStmt->execute();
        
        echo json_encode(['status' => 'success', 'host_id' => $hostId]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to register host']);
    }
    
    $stmt->close();
}


function pingHost() {
    global $conn;
    
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : '';
    $instanceToken = isset($_POST['instance_token']) ? sanitize($_POST['instance_token']) : '';
    $isInteractive = isset($_POST['is_interactive']) ? (bool)$_POST['is_interactive'] : false;
    
    if (empty($hostId)) {
        echo json_encode(['status' => 'error', 'message' => 'Host ID is required']);
        return;
    }
    
    // More frequent updates for interactive sessions
    $stmt = $conn->prepare("UPDATE hosts SET last_seen = CURRENT_TIMESTAMP, connected = 1 WHERE host_id = ?");
    $stmt->bind_param("s", $hostId);
    
    if ($stmt->execute()) {
        // Update instance mapping if token provided
        if (!empty($instanceToken)) {
            $stmt = $conn->prepare("UPDATE host_instance_mappings SET mapped_at = CURRENT_TIMESTAMP WHERE host_id = ? AND instance_token = ? AND is_active = 1");
            $stmt->bind_param("ss", $hostId, $instanceToken);
            $stmt->execute();
        }
        
        // Also update admin database
        $adminDb = getAdminDB();
        $adminStmt = $adminDb->prepare("UPDATE hosts_info SET last_seen = CURRENT_TIMESTAMP, is_active = 1 WHERE host_id = ?");
        $adminStmt->bind_param("s", $hostId);
        $adminStmt->execute();
        
        // Log interactive session activity
        if ($isInteractive) {
            error_log("Interactive session heartbeat from host: $hostId");
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Heartbeat received']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update host status']);
    }
    
    $stmt->close();
}


function getHosts() {
    global $conn;
    
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $instanceToken = getCurrentInstanceToken();
    if (!$instanceToken) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid instance token']);
        return;
    }
    
    // Get hosts mapped to this user's instance token
    $sql = "SELECT h.*, him.mapped_at,
               CASE WHEN h.last_seen > DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 'online'
                    WHEN h.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'delayed'
                    ELSE 'disconnected' END as connection_status
            FROM hosts h 
            LEFT JOIN host_instance_mappings him ON h.host_id = him.host_id 
            WHERE h.connected = 1 
            AND h.last_seen > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            AND (him.instance_token = ? AND him.is_active = 1 AND him.expires_at > NOW())
            GROUP BY h.host_id
            ORDER BY h.connected DESC, h.last_seen DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $instanceToken);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $hosts = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Calculate seconds since last seen
            $lastSeen = strtotime($row['last_seen']);
            $now = time();
            $secondsSinceLastSeen = $now - $lastSeen;
            
            // Add this information to the host data
            $row['seconds_since_last_seen'] = $secondsSinceLastSeen;
            
            $hosts[] = $row;
        }
    }
    
    echo json_encode(['status' => 'success', 'hosts' => $hosts]);
}


function markHostDisconnected() {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
        return;
    }
    
    $hostId = isset($_POST['host_id']) ? sanitize($_POST['host_id']) : '';
    
    if (empty($hostId)) {
        echo json_encode(['status' => 'error', 'message' => 'Host ID is required']);
        return;
    }
    
    global $conn;
    
    // Mark host as disconnected
    $stmt = $conn->prepare("UPDATE hosts SET connected = 0, last_seen = CURRENT_TIMESTAMP WHERE host_id = ?");
    $stmt->bind_param("s", $hostId);
    
    if ($stmt->execute()) {
        // Also update admin database
        $adminDb = getAdminDB();
        $adminStmt = $adminDb->prepare("UPDATE hosts_info SET is_active = 0, last_seen = CURRENT_TIMESTAMP WHERE host_id = ?");
        $adminStmt->bind_param("s", $hostId);
        $adminStmt->execute();
        
        // Log audit event
        logAuditEvent($user['id'], 'system_access', [
            'action' => 'host_disconnected',
            'host_id' => $hostId
        ]);
        
        echo json_encode(['status' => 'success', 'message' => 'Host marked as disconnected']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to mark host as disconnected']);
    }
    
    $stmt->close();
}

