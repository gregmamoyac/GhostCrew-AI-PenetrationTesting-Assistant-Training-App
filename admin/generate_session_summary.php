<?php
// generate_session_summary.php

ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);
date_default_timezone_set('UTC');


class SessionSummaryGenerator {
    private $pdo;
    //private $ai_endpoint = 'https://zl47lm7yy1.execute-api.us-east-2.amazonaws.com/invoke'; // Replace with actual endpoint
    private $ai_endpoint = 'http://192.168.1.171:8090';
    
    public function __construct() {
        if (!defined('ADMIN_DB_HOST')) {
            define('ADMIN_DB_HOST', '192.168.1.171');
        }
        if (!defined('ADMIN_DB_USER')) {
            define('ADMIN_DB_USER', 'svc_ghostcrew_admin');
        }
        if (!defined('ADMIN_DB_PASS')) {
            define('ADMIN_DB_PASS', '!Password123!');
        }
        if (!defined('ADMIN_DB_NAME')) {
            define('ADMIN_DB_NAME', 'ghostcrew_admin');
        }

        // Database configuration
        $host = ADMIN_DB_HOST;
        $username = ADMIN_DB_USER;
        $password = ADMIN_DB_PASS;
        $dbname = ADMIN_DB_NAME;
        
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function processTerminatedSessions() {
        try {
            // Find terminated sessions without summaries
            $stmt = $this->pdo->prepare("
                SELECT rs.session_id, rs.user_id, rs.hostname, rs.start_time, rs.end_time
                FROM remote_sessions rs
                INNER JOIN session_summaries ss ON rs.session_id = ss.session_id
                WHERE rs.status = 'terminated' 
                AND ss.ai_summary LIKE '🔄 Session being analyzed...%'
            ");
            $stmt->execute();
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->logWithTimestamp("Found " . count($sessions) . " terminated sessions without summaries");
            
            foreach ($sessions as $session) {
                $this->generateSummaryForSession($session);
                $this->logWithTimestamp("Generated summary for session: " . $session['session_id']);
            }
            
            $this->logWithTimestamp("Completed processing " . count($sessions) . " terminated sessions");
            
        } catch (Exception $e) {
            $this->logWithTimestamp("Error processing {$session['session_id']}");
        }
    }
    
    private function generateSummaryForSession($session) {
        try {
            error_log("Processing session for AI summary: " . $session['session_id']);
            
            // Get all commands for this session in chronological order
            $stmt = $this->pdo->prepare("
                SELECT id, command, output, status, timestamp, response_timestamp
                FROM command_log
                WHERE session_id = ?
                ORDER BY timestamp ASC
            ");
            $stmt->execute([$session['session_id']]);
            $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Found " . count($commands) . " commands for session: " . $session['session_id']);
            
            if (empty($commands)) {
                $this->updateSummaryToEmpty($session);
                return;
            }
            
            // Prepare data for AI
            $input_data = [];
            foreach ($commands as $cmd) {
                $input_data[] = [
                    'id' => $cmd['id'],
                    'timestamp' => $cmd['timestamp'],
                    'command' => $cmd['command'],
                    'status' => $cmd['status'],
                    'response' => $cmd['output'] ?? ''
                ];
            }
            
            // Use the actual session_id from remote_sessions table
            $payload = [
                'mode' => 'admin',
                'session_id' => $session['session_id'],
                'input' => json_encode($input_data)
            ];
            
            // Send to AI and get summary
            $ai_summary = $this->sendToAI($payload);
            
            if ($ai_summary) {
                $this->updateSummary($session, $ai_summary, count($commands));
                error_log("Successfully generated and stored AI summary for session: " . $session['session_id']);
                echo "Generated summary for session: " . $session['session_id'] . "\n";
            } else {
                error_log("Failed to get AI summary for session: " . $session['session_id']);
                // Update with failure message
                $this->updateSummaryWithError($session, count($commands));
            }
            
        } catch (Exception $e) {
            error_log("Error generating summary for session {$session['session_id']}: " . $e->getMessage());
        }
    }

    private function updateSummary($session, $ai_summary, $command_count) {
        $duration = null;
        if ($session['start_time'] && $session['end_time']) {
            $start = new DateTime($session['start_time']);
            $end = new DateTime($session['end_time']);
            $duration = $end->getTimestamp() - $start->getTimestamp();
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE session_summaries 
            SET ai_summary = ?, 
                summary_generated_at = NOW(),
                command_count = ?,
                session_duration = ?
            WHERE session_id = ?
        ");
        
        $stmt->execute([
            $ai_summary,
            $command_count,
            $duration,
            $session['session_id']
        ]);
    }

    private function updateSummaryToEmpty($session) {
        $stmt = $this->pdo->prepare("
            UPDATE session_summaries 
            SET ai_summary = 'No commands executed in this session',
                summary_generated_at = NOW()
            WHERE session_id = ?
        ");
        
        $stmt->execute([$session['session_id']]);
    }

    private function updateSummaryWithError($session, $command_count) {
        $stmt = $this->pdo->prepare("
            UPDATE session_summaries 
            SET ai_summary = 'Error: Failed to generate AI summary.',
                summary_generated_at = NOW(),
                command_count = ?
            WHERE session_id = ?
        ");
        
        $stmt->execute([
            $command_count,
            $session['session_id']
        ]);
    }
    
    private function sendToAI($payload) {
        // Log the JSON payload being sent to AI
        $json_payload = json_encode($payload, JSON_PRETTY_PRINT);
        $this->logWithTimestamp("=== AI API Request ===");
        $this->logWithTimestamp("Endpoint: " . $this->ai_endpoint);
        $this->logWithTimestamp("Payload: " . $json_payload);
        $this->logWithTimestamp("========================");
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->ai_endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json_payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Log the response as well
        $this->logWithTimestamp("=== AI API Response ===");
        $this->logWithTimestamp("HTTP Code: " . $http_code);
        $this->logWithTimestamp("Response: " . ($response ?: 'No response'));
        $this->logWithTimestamp("=========================");
        
        if (curl_errno($ch)) {
            $this->logWithTimestamp("cURL error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);
        
        if ($http_code !== 200) {
            $this->logWithTimestamp("AI API returned HTTP code: $http_code, Response: $response");
            return false;
        }
        
        return trim($response);
    }
    
    private function insertSummary($session, $ai_summary, $command_count) {
        $duration = null;
        if ($session['start_time'] && $session['end_time']) {
            $start = new DateTime($session['start_time']);
            $end = new DateTime($session['end_time']);
            $duration = $end->getTimestamp() - $start->getTimestamp();
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO session_summaries 
            (session_id, user_id, hostname, command_count, session_duration, ai_summary, session_start_time, session_end_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $session['session_id'],
            $session['user_id'],
            $session['hostname'],
            $command_count,
            $duration,
            $ai_summary,
            $session['start_time'],
            $session['end_time']
        ]);
    }
    
    private function insertEmptySummary($session) {
        $stmt = $this->pdo->prepare("
            INSERT INTO session_summaries 
            (session_id, user_id, hostname, command_count, ai_summary, session_start_time, session_end_time)
            VALUES (?, ?, ?, 0, 'No commands executed in this session', ?, ?)
        ");
        
        $stmt->execute([
            $session['session_id'],
            $session['user_id'],
            $session['hostname'],
            $session['start_time'],
            $session['end_time']
        ]);
    }

    // Add this method to your SessionSummaryGenerator class
    private function logWithTimestamp($message) {
        $timestamp = date('Y-m-d H:i:s');
        echo "[$timestamp] $message\n";
        error_log("[$timestamp] $message");
    }
}

// Run the processor
if (php_sapi_name() === 'cli') {
    try {
        $processor = new SessionSummaryGenerator();
        $processor->processTerminatedSessions();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>