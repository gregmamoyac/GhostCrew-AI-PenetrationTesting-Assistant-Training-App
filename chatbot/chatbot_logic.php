<?php

class GhostChatbot {
    
    private $session_data_dir = 'sessions/';
    private $max_message_length = 500;
    
    public function __construct() {
        // Create sessions directory if it doesn't exist
        if (!is_dir($this->session_data_dir)) {
            mkdir($this->session_data_dir, 0755, true);
        }
    }
    
    /**
     * Process incoming message and return response
     */
    public function processMessage($message, $session_id) {
        // Validate and sanitize input
        $message = $this->sanitizeInput($message);
        $session_id = $this->sanitizeSessionId($session_id);
        
        if (strlen($message) > $this->max_message_length) {
            return "Message too long. Please keep it under {$this->max_message_length} characters.";
        }
        
        // Load session data
        $session_data = $this->loadSessionData($session_id);
        
        // Add user message to history
        $session_data['messages'][] = [
            'type' => 'user',
            'message' => $message,
            'timestamp' => time()
        ];
        
        // Generate response based on message content
        $response = $this->generateResponse($message, $session_data);
        
        // Add bot response to history
        $session_data['messages'][] = [
            'type' => 'bot',
            'message' => $response,
            'timestamp' => time()
        ];
        
        // Save session data
        $this->saveSessionData($session_id, $session_data);
        
        return $response;
    }
    
    /**
     * Generate response based on user input
     */
    private function generateResponse($message, $session_data) {
        $message_lower = strtolower($message);
        
        // For now, just echo back the user's message with some context
        $responses = [
            "You said: \"{$message}\"",
            "I received your message: \"{$message}\"",
            "Message received: \"{$message}\"",
            "You wrote: \"{$message}\"",
            "Your input was: \"{$message}\""
        ];
        
        // Add some basic command recognition
        if (strpos($message_lower, 'hello') !== false || strpos($message_lower, 'hi') !== false) {
            return "Hello! You said: \"{$message}\"";
        }
        
        if (strpos($message_lower, 'help') !== false) {
            return "You asked for help. Your message was: \"{$message}\"";
        }
        
        if (strpos($message_lower, 'ghost') !== false || strpos($message_lower, 'crew') !== false) {
            return "You mentioned GhostCrew! Your full message: \"{$message}\"";
        }
        
        if (strpos($message_lower, 'command') !== false || strpos($message_lower, 'terminal') !== false) {
            return "You're asking about commands/terminal. Your message: \"{$message}\"";
        }
        
        if (strpos($message_lower, 'bye') !== false || strpos($message_lower, 'goodbye') !== false) {
            return "Goodbye! Your message was: \"{$message}\"";
        }
        
        // Default response - echo back the message
        return $responses[array_rand($responses)];
    }
    
    /**
     * Load session data from file
     */
    private function loadSessionData($session_id) {
        $file_path = $this->session_data_dir . $session_id . '.json';
        
        if (file_exists($file_path)) {
            $data = json_decode(file_get_contents($file_path), true);
            if ($data !== null) {
                return $data;
            }
        }
        
        // Return default session data
        return [
            'session_id' => $session_id,
            'created' => time(),
            'last_activity' => time(),
            'messages' => []
        ];
    }
    
    /**
     * Save session data to file
     */
    private function saveSessionData($session_id, $data) {
        $data['last_activity'] = time();
        $file_path = $this->session_data_dir . $session_id . '.json';
        
        // Keep only last 50 messages to prevent files from getting too large
        if (count($data['messages']) > 50) {
            $data['messages'] = array_slice($data['messages'], -50);
        }
        
        file_put_contents($file_path, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    /**
     * Sanitize user input
     */
    private function sanitizeInput($input) {
        // Remove any HTML tags and trim whitespace
        return trim(strip_tags($input));
    }
    
    /**
     * Sanitize session ID
     */
    private function sanitizeSessionId($session_id) {
        // Only allow alphanumeric characters, underscores, and hyphens
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $session_id);
    }
    
    /**
     * Clean up old sessions (optional maintenance function)
     */
    public function cleanupOldSessions($max_age_days = 7) {
        $max_age_seconds = $max_age_days * 24 * 60 * 60;
        $current_time = time();
        
        $files = glob($this->session_data_dir . '*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['last_activity'])) {
                if (($current_time - $data['last_activity']) > $max_age_seconds) {
                    unlink($file);
                }
            }
        }
    }
}

?>