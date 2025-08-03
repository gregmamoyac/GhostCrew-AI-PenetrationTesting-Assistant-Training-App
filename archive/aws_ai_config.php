<?php
/**
 * AWS AI Configuration Helper (Safe Loading)
 * Fixed to handle dependency loading issues
 */

require_once 'chatbot_engine.php'; // Ensure chatbot engine is loaded first

class AwsAiConfig {
    private static $config = null;
    private static $adminDb = null;
    private static $initialized = false;
    
    /**
     * Safe initialization that checks for dependencies
     */
    public static function init() {
        if (self::$initialized) {
            return true;
        }
        
        // Check if required functions exist
        if (!function_exists('getAdminDB')) {
            error_log("AWS AI Config: getAdminDB function not available yet");
            return false;
        }
        
        try {
            self::$adminDb = getAdminDB();
            self::loadConfig();
            self::$initialized = true;
            return true;
        } catch (Exception $e) {
            error_log("AWS AI Config init error: " . $e->getMessage());
            self::setDefaults();
            return false;
        }
    }
    
    /**
     * Load configuration from database
     */
    private static function loadConfig() {
        if (!self::$adminDb) {
            self::setDefaults();
            return;
        }
        
        try {
            $stmt = self::$adminDb->prepare("SELECT config_key, config_value FROM ai_config");
            $stmt->execute();
            $result = $stmt->get_result();
            
            self::$config = [];
            while ($row = $result->fetch_assoc()) {
                self::$config[$row['config_key']] = $row['config_value'];
            }
            
            // Set defaults for missing keys
            self::setDefaults();
            
        } catch (Exception $e) {
            error_log("AWS AI Config load error: " . $e->getMessage());
            self::setDefaults();
        }
    }
    
    /**
     * Set default configuration values
     */
    private static function setDefaults() {
        $defaults = [
            'aws_ai_endpoint' => getenv('AWS_AI_ENDPOINT') ?: 'https://your-aws-ai-endpoint.amazonaws.com/chat',
            'aws_api_key' => getenv('AWS_AI_API_KEY') ?: 'your-api-key-here',
            'max_tokens' => '1000',
            'temperature' => '0.7',
            'context_messages' => '10',
            'command_detection' => '1',
            'timeout_seconds' => '30',
            'model_name' => 'aws-ai',
            'system_prompt' => 'You are an AI assistant helping with terminal commands and system administration. When suggesting commands, format them clearly and explain what they do. If you suggest a command, include it in a "suggested_command" field in your response.',
            'max_context_length' => '4000',
            'retry_attempts' => '3',
            'retry_delay' => '1000'
        ];
        
        if (self::$config === null) {
            self::$config = [];
        }
        
        foreach ($defaults as $key => $value) {
            if (!isset(self::$config[$key])) {
                self::$config[$key] = $value;
            }
        }
    }
    
    /**
     * Get a configuration value with safe initialization
     */
    public static function get($key, $default = null) {
        // Try to initialize if not already done
        if (!self::$initialized) {
            self::init();
        }
        
        // If still not initialized, use defaults only
        if (!self::$initialized) {
            self::setDefaults();
        }
        
        return self::$config[$key] ?? $default;
    }
    
    /**
     * Set a configuration value with safe initialization
     */
    public static function set($key, $value) {
        // Try to initialize if not already done
        if (!self::$initialized) {
            if (!self::init()) {
                return false; // Can't save to database yet
            }
        }
        
        if (!self::$adminDb) {
            return false;
        }
        
        try {
            $stmt = self::$adminDb->prepare("
                INSERT INTO ai_config (config_key, config_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
            ");
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
            
            self::$config[$key] = $value;
            return true;
        } catch (Exception $e) {
            error_log("AWS AI Config set error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all configuration values
     */
    public static function getAll() {
        if (!self::$initialized) {
            self::init();
        }
        
        if (!self::$initialized) {
            self::setDefaults();
        }
        
        return self::$config;
    }
    
    /**
     * Check if the system is properly initialized
     */
    public static function isInitialized() {
        return self::$initialized;
    }
    
    /**
     * Validate AWS AI connection
     */
    public static function validateConnection() {
        $endpoint = self::get('aws_ai_endpoint');
        $apiKey = self::get('aws_api_key');
        
        if (empty($endpoint) || empty($apiKey) || $apiKey === 'your-api-key-here') {
            return [
                'valid' => false,
                'error' => 'AWS AI endpoint or API key not configured'
            ];
        }
        
        // Test connection with a simple ping
        $testPayload = [
            'message' => 'ping',
            'max_tokens' => 10
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'User-Agent: GhostCrew-Terminal/1.0'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($testPayload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int)self::get('timeout_seconds', 30),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'valid' => false,
                'error' => "Connection error: " . $error
            ];
        }
        
        if ($httpCode === 200) {
            return [
                'valid' => true,
                'message' => 'AWS AI connection successful'
            ];
        } else {
            return [
                'valid' => false,
                'error' => "HTTP error: " . $httpCode . " - " . $response
            ];
        }
    }
    
    /**
     * Get performance statistics (safe version)
     */
    public static function getPerformanceStats($days = 7) {
        if (!self::$initialized || !self::$adminDb) {
            return [];
        }
        
        try {
            $stmt = self::$adminDb->prepare("
                SELECT 
                    DATE(request_timestamp) as date,
                    COUNT(*) as total_requests,
                    AVG(response_time_ms) as avg_response_time,
                    SUM(tokens_used) as total_tokens,
                    COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_requests,
                    COUNT(CASE WHEN status = 'error' THEN 1 END) as error_requests,
                    COUNT(CASE WHEN status = 'timeout' THEN 1 END) as timeout_requests
                FROM ai_performance_log 
                WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(request_timestamp)
                ORDER BY date DESC
            ");
            $stmt->bind_param("i", $days);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $stats = [];
            while ($row = $result->fetch_assoc()) {
                $stats[] = $row;
            }
            
            return $stats;
        } catch (Exception $e) {
            error_log("Performance stats error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Log performance metrics (safe version)
     */
    public static function logPerformance($messageId, $responseTime, $tokensUsed, $model, $status = 'success', $errorMessage = null) {
        if (!self::$initialized || !self::$adminDb) {
            return false;
        }
        
        try {
            $stmt = self::$adminDb->prepare("
                INSERT INTO ai_performance_log 
                (message_id, response_timestamp, response_time_ms, tokens_used, model_used, status, error_message)
                VALUES (?, NOW(), ?, ?, ?, ?, ?)
            ");
            $responseTimeMs = round($responseTime * 1000);
            $stmt->bind_param("iiisss", $messageId, $responseTimeMs, $tokensUsed, $model, $status, $errorMessage);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Performance log error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get command usage statistics (safe version)
     */
    public static function getCommandStats($limit = 20) {
        if (!self::$initialized || !self::$adminDb) {
            return [];
        }
        
        try {
            $stmt = self::$adminDb->prepare("
                SELECT 
                    command,
                    suggested_count,
                    executed_count,
                    success_rate,
                    last_suggested,
                    last_executed
                FROM command_usage_stats 
                ORDER BY suggested_count DESC 
                LIMIT ?
            ");
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $stats = [];
            while ($row = $result->fetch_assoc()) {
                $stats[] = $row;
            }
            
            return $stats;
        } catch (Exception $e) {
            error_log("Command stats error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Clean up old performance logs (safe version)
     */
    public static function cleanupPerformanceLogs($daysToKeep = 30) {
        if (!self::$initialized || !self::$adminDb) {
            return 0;
        }
        
        try {
            $stmt = self::$adminDb->prepare("
                DELETE FROM ai_performance_log 
                WHERE request_timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->bind_param("i", $daysToKeep);
            $stmt->execute();
            
            return $stmt->affected_rows;
        } catch (Exception $e) {
            error_log("Performance cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Export configuration for backup
     */
    public static function exportConfig() {
        $export = [
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'config' => self::getAll()
        ];
        
        return json_encode($export, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import configuration from backup
     */
    public static function importConfig($jsonData) {
        try {
            $data = json_decode($jsonData, true);
            if (!$data || !isset($data['config'])) {
                throw new Exception('Invalid configuration data');
            }
            
            $imported = 0;
            foreach ($data['config'] as $key => $value) {
                if (self::set($key, $value)) {
                    $imported++;
                }
            }
            
            return [
                'success' => true,
                'imported' => $imported,
                'total' => count($data['config'])
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

/**
 * Enhanced chatbot engine wrapper with AWS AI (safe version)
 */
class EnhancedChatbotEngine extends ChatbotEngine {
    
    public function __construct($userId, $sessionId = null) {
        parent::__construct($userId, $sessionId);
        // Don't initialize here - wait until actually needed
    }
    
    /**
     * Override to use AWS AI instead of local processing
     */
    public function processMessage($message, $context = []) {
        $startTime = microtime(true);
        
        // Store user message
        $userMessageId = $this->logMessage('user', $message);
        
        try {
            // Try to initialize AWS AI config
            if (!AwsAiConfig::isInitialized()) {
                AwsAiConfig::init();
            }
            
            // Check if we can use AWS AI
            $endpoint = AwsAiConfig::get('aws_ai_endpoint');
            $apiKey = AwsAiConfig::get('aws_api_key');
            
            if ($endpoint && $apiKey && $apiKey !== 'your-api-key-here') {
                // Send to AWS AI
                $aiResponse = $this->sendToAwsAI($message, $context);
                $responseTime = microtime(true) - $startTime;
                
                // Process response
                $botMessage = $aiResponse['message'] ?? 'I apologize, but I encountered an error.';
                $suggestedCommand = $aiResponse['suggested_command'] ?? null;
                $modelUsed = $aiResponse['model'] ?? AwsAiConfig::get('model_name');
                $tokensUsed = $aiResponse['tokens'] ?? null;
                
                // Store bot response
                $botMessageId = $this->logMessage(
                    'bot', 
                    $botMessage, 
                    $context, 
                    $responseTime, 
                    $suggestedCommand
                );
                
                // Log performance (if possible)
                AwsAiConfig::logPerformance($botMessageId, $responseTime, $tokensUsed, $modelUsed);
                
                return [
                    'user_message_id' => $userMessageId,
                    'bot_message_id' => $botMessageId,
                    'bot_response' => $botMessage,
                    'suggested_command' => $suggestedCommand,
                    'command_description' => $aiResponse['command_description'] ?? null,
                    'response_time' => $responseTime,
                    'model_used' => $modelUsed,
                    'tokens_used' => $tokensUsed
                ];
            } else {
                // Fall back to original chatbot engine
                return $this->generateFallbackResponse($message, $context, $userMessageId, $startTime);
            }
            
        } catch (Exception $e) {
            $responseTime = microtime(true) - $startTime;
            error_log("Enhanced chatbot error: " . $e->getMessage());
            
            // Fall back to original chatbot engine
            return $this->generateFallbackResponse($message, $context, $userMessageId, $startTime);
        }
    }
    
    /**
     * Generate fallback response using original chatbot logic
     */
    private function generateFallbackResponse($message, $context, $userMessageId, $startTime) {
        $responseTime = microtime(true) - $startTime;
        
        // Use parent class logic as fallback
        $parentResponse = parent::processMessage($message, $context);
        
        return [
            'user_message_id' => $userMessageId,
            'bot_message_id' => $parentResponse['bot_message_id'] ?? null,
            'bot_response' => $parentResponse['bot_response'] ?? 'I can help you with basic terminal commands. AWS AI integration is not configured.',
            'suggested_command' => $parentResponse['suggested_command'] ?? null,
            'command_description' => $parentResponse['command_description'] ?? null,
            'response_time' => $responseTime,
            'model_used' => 'local-fallback',
            'tokens_used' => null
        ];
    }
    
    /**
     * Send message to AWS AI (same as before)
     */
    private function sendToAwsAI($message, $context) {
        $endpoint = AwsAiConfig::get('aws_ai_endpoint');
        $apiKey = AwsAiConfig::get('aws_api_key');
        $maxTokens = (int)AwsAiConfig::get('max_tokens', 1000);
        $temperature = (float)AwsAiConfig::get('temperature', 0.7);
        $systemPrompt = AwsAiConfig::get('system_prompt');
        $timeout = (int)AwsAiConfig::get('timeout_seconds', 30);
        
        // Prepare context messages
        $contextMessages = $this->prepareContextMessages($context);
        
        $payload = [
            'message' => $message,
            'context' => $contextMessages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'system_prompt' => $systemPrompt,
            'session_id' => $this->sessionId
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'User-Agent: GhostCrew-Terminal/1.0'
        ];
        
        // Initialize cURL with retry logic
        $retryAttempts = (int)AwsAiConfig::get('retry_attempts', 3);
        $retryDelay = (int)AwsAiConfig::get('retry_delay', 1000);
        
        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FOLLOWLOCATION => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if (!$error && $httpCode === 200) {
                $decodedResponse = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $this->processAIResponse($decodedResponse);
                }
            }
            
            // If not the last attempt, wait before retrying
            if ($attempt < $retryAttempts) {
                usleep($retryDelay * 1000); // Convert to microseconds
                $retryDelay *= 2; // Exponential backoff
            }
        }
        
        // All attempts failed
        $errorMsg = $error ?: "HTTP $httpCode: $response";
        throw new Exception("AWS AI request failed after $retryAttempts attempts: " . $errorMsg);
    }
    
    /**
     * Prepare context messages for AI
     */
    private function prepareContextMessages($context) {
        $contextLimit = (int)AwsAiConfig::get('context_messages', 10);
        $maxLength = (int)AwsAiConfig::get('max_context_length', 4000);
        
        // Get recent conversation history
        $recentMessages = $this->getChatHistory($contextLimit * 2); // Get more than needed
        
        $contextMessages = [];
        $totalLength = 0;
        
        // Process messages in reverse order (newest first) to fit within length limit
        for ($i = count($recentMessages) - 1; $i >= 0 && count($contextMessages) < $contextLimit; $i--) {
            $msg = $recentMessages[$i];
            $messageLength = strlen($msg['message']);
            
            if ($totalLength + $messageLength > $maxLength) {
                break;
            }
            
            array_unshift($contextMessages, [
                'type' => $msg['message_type'],
                'message' => $msg['message'],
                'timestamp' => $msg['timestamp'],
                'suggested_command' => $msg['suggested_command'] ?? null
            ]);
            
            $totalLength += $messageLength;
        }
        
        return $contextMessages;
    }
    
    /**
     * Process AI response and extract commands
     */
    private function processAIResponse($response) {
        $message = $response['message'] ?? $response['response'] ?? '';
        $suggestedCommand = null;
        $commandDescription = null;
        
        // Check if response has explicit command field
        if (isset($response['suggested_command'])) {
            $suggestedCommand = trim($response['suggested_command']);
            $commandDescription = $response['command_description'] ?? 'Suggested command from AI';
        } else if (AwsAiConfig::get('command_detection', '1') === '1') {
            // Try to extract command from message using patterns
            $extracted = $this->extractCommandFromMessage($message);
            if ($extracted) {
                $suggestedCommand = $extracted['command'];
                $commandDescription = $extracted['description'];
            }
        }
        
        return [
            'message' => $message,
            'suggested_command' => $suggestedCommand,
            'command_description' => $commandDescription,
            'model' => $response['model'] ?? AwsAiConfig::get('model_name'),
            'tokens' => $response['usage']['total_tokens'] ?? null
        ];
    }
    
    /**
     * Extract commands from AI message text
     */
    private function extractCommandFromMessage($message) {
        // Enhanced patterns to detect commands in AI responses
        $patterns = [
            // Code blocks with shell/cmd/bash/powershell
            '/```(?:shell|bash|cmd|powershell|sh)\s*\n(.*?)\n```/s',
            // Generic code blocks that might contain commands
            '/```\s*\n([a-zA-Z0-9_-]+(?:\s+[^\n`]+)?)\n```/s',
            // Inline code with common command prefixes
            '/`([a-zA-Z0-9_-]+(?:\s+[^`]+)?)`/',
            // Commands with common system administration prefixes
            '/((?:sudo\s+)?(?:cd|ls|dir|ping|curl|wget|ssh|scp|git|npm|pip|docker|kubectl|systemctl|service|netstat|ps|top|grep|find|chmod|chown|mkdir|rmdir|rm|cp|mv|cat|less|more|head|tail|sort|uniq|wc|awk|sed|tar|gzip|gunzip|zip|unzip|which|whereis|whoami|id|uname|df|du|free|uptime|history|kill|killall|jobs|nohup|screen|tmux|vim|nano|emacs)\s+[^\n.!?;]+)/im',
            // Windows specific commands
            '/((?:dir|copy|move|del|md|rd|cd|type|echo|cls|ipconfig|netstat|tasklist|taskkill|sc|net|wmic|powershell|cmd)\s+[^\n.!?;]+)/im'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $command = trim($matches[1]);
                if (!empty($command) && strlen($command) > 2 && $this->isValidCommand($command)) {
                    return [
                        'command' => $command,
                        'description' => 'Command extracted from AI response'
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Validate if extracted text is likely a valid command
     */
    private function isValidCommand($command) {
        // Remove common false positives
        $invalidPatterns = [
            '/^(the|and|or|but|with|for|to|of|in|on|at|by|from)(\s|$)/i',
            '/^[0-9]+(\.|:|\s|$)/', // Numbered lists
            '/^\w+:\/\//', // URLs
            '/^[a-zA-Z]+@[a-zA-Z]/', // Email addresses
            '/^(http|https|ftp|ssh):/', // Protocols
        ];
        
        foreach ($invalidPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                return false;
            }
        }
        
        // Check if it contains common command elements
        $commandIndicators = [
            // Common command line flags
            '/\s-[a-zA-Z]/',
            '/\s--[a-zA-Z]/',
            // File paths
            '/\/[a-zA-Z0-9_-]+/',
            '/[C-Z]:\\\\/',
            // Common command patterns
            '/\.(exe|sh|py|pl|rb)(\s|$)/',
            // Pipe operations
            '/\s\|\s/',
            // Redirection
            '/\s[>|<]/'
        ];
        
        foreach ($commandIndicators as $pattern) {
            if (preg_match($pattern, $command)) {
                return true;
            }
        }
        
        // If command starts with known command prefixes, it's likely valid
        $knownCommands = [
            'sudo', 'cd', 'ls', 'dir', 'ping', 'curl', 'wget', 'ssh', 'scp', 'git',
            'npm', 'pip', 'docker', 'kubectl', 'systemctl', 'service', 'netstat',
            'ps', 'top', 'grep', 'find', 'chmod', 'chown', 'mkdir', 'rmdir', 'rm',
            'cp', 'mv', 'cat', 'less', 'more', 'head', 'tail', 'sort', 'uniq',
            'wc', 'awk', 'sed', 'tar', 'gzip', 'gunzip', 'zip', 'unzip', 'which',
            'whereis', 'whoami', 'id', 'uname', 'df', 'du', 'free', 'uptime',
            'history', 'kill', 'killall', 'jobs', 'nohup', 'screen', 'tmux',
            'vim', 'nano', 'emacs', 'copy', 'move', 'del', 'md', 'rd', 'type',
            'echo', 'cls', 'ipconfig', 'tasklist', 'taskkill', 'sc', 'net',
            'wmic', 'powershell', 'cmd'
        ];
        
        $firstWord = strtolower(explode(' ', trim($command))[0]);
        return in_array($firstWord, $knownCommands);
    }
}

/**
 * Helper functions for AWS AI integration (safe versions)
 */

/**
 * Get AWS AI configuration value (safe)
 */
function getAiConfig($key, $default = null) {
    return AwsAiConfig::get($key, $default);
}

/**
 * Set AWS AI configuration value (safe)
 */
function setAiConfig($key, $value) {
    return AwsAiConfig::set($key, $value);
}

/**
 * Test AWS AI connection (safe)
 */
function testAwsAiConnection() {
    return AwsAiConfig::validateConnection();
}

/**
 * Enhanced chatbot response function with AWS AI (safe)
 */
function generateEnhancedBotResponse($message, $context = [], $userId = null, $sessionId = null) {
    if (!$userId) {
        if (function_exists('getCurrentUser')) {
            $user = getCurrentUser();
            $userId = $user ? $user['id'] : null;
        }
    }
    
    if (!$userId) {
        return [
            'message' => 'Authentication required for chatbot functionality.',
            'error' => true
        ];
    }
    
    try {
        $chatbot = new EnhancedChatbotEngine($userId, $sessionId);
        return $chatbot->processMessage($message, $context);
    } catch (Exception $e) {
        error_log("Enhanced bot response error: " . $e->getMessage());
        return [
            'message' => 'I apologize, but I encountered an error. Please try again.',
            'error' => true
        ];
    }
}

/**
 * Safe initialization function that doesn't run automatically
 */
function initializeAwsAiConfig() {
    // Only initialize if all dependencies are available
    if (!function_exists('getAdminDB')) {
        return false;
    }
    
    try {
        return AwsAiConfig::init();
    } catch (Exception $e) {
        error_log("AWS AI initialization error: " . $e->getMessage());
        return false;
    }
}

// Don't auto-initialize - let the calling code decide when to initialize
?>