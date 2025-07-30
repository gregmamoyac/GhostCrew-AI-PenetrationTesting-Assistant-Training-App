<?php
/* chatbot_engine.php - Enhanced AI Chatbot Engine - FIXED */

require_once 'auth_config.php';
require_once 'config.php';

class ChatbotEngine {
    private $adminDb;
    private $userId;
    private $sessionId;
    private $conversationId;
    
    public function __construct($userId, $sessionId = null) {
        $this->adminDb = getAdminDB();
        $this->userId = $userId;
        $this->sessionId = $sessionId;
        $this->conversationId = $this->getOrCreateConversationId($sessionId);
    }
    
    /**
     * Process a user message and generate a bot response
     */
    public function processMessage($message, $context = []) {
        $startTime = microtime(true);
        
        // Store user message
        $messageId = $this->logMessage('user', $message);
        
        // Generate response
        $response = $this->generateResponse($message, $context);
        $responseTime = microtime(true) - $startTime;
        
        // Ensure response has all required keys with defaults
        $response = array_merge([
            'message' => 'I apologize, but I encountered an error processing your request.',
            'context' => [],
            'suggested_command' => null,
            'command_description' => null,
            'category' => 'general',
            'priority' => 5,
            'suggestion_context' => null
        ], $response);
        
        // Store bot response
        $botMessageId = $this->logMessage(
            'bot', 
            $response['message'], 
            $response['context'], 
            $responseTime, 
            $response['suggested_command']
        );
        
        // Store command suggestion if present
        $suggestionId = null;
        if (!empty($response['suggested_command'])) {
            $suggestionId = $this->storeCommandSuggestion(
                $response['suggested_command'],
                $response['command_description'] ?? '',
                $response['suggestion_context'] ?? '',
                $response['category'] ?? 'general',
                $response['priority'] ?? 5
            );
        }
        
        $suggestedCommands = [];
        if (!empty($response['suggested_command'])) {
            $suggestedCommands[] = [
                'id' => 'chatbot_cmd_0',
                'command' => $response['suggested_command'],
                'description' => $response['command_description'] ?? 'Chatbot suggestion',
                'type' => 'chatbot'
            ];
        }

        return [
            'message_id' => $messageId,
            'bot_message_id' => $botMessageId,
            'bot_response' => $response['message'],
            'suggested_command' => $response['suggested_command'], // Keep for backward compatibility
            'suggested_commands' => $suggestedCommands, // New format
            'command_description' => $response['command_description'],
            'suggestion_id' => $suggestionId,
            'category' => $response['category'],
            'response_time' => $responseTime
        ];
    }
    
    /**
     * Generate intelligent response based on message and context
     */
    private function generateResponse($message, $context = []) {
        $message = strtolower(trim($message));
        
        // First, try pattern matching for quick responses
        $patternResponse = $this->matchCommandPattern($message);
        if ($patternResponse) {
            return $patternResponse;
        }
        
        // Try knowledge base lookup
        $knowledgeResponse = $this->searchKnowledgeBase($message);
        if ($knowledgeResponse) {
            return $knowledgeResponse;
        }
        
        // Context-aware responses based on recent commands
        if (!empty($context['command_history'])) {
            $contextResponse = $this->generateContextualResponse($message, $context);
            if ($contextResponse) {
                return $contextResponse;
            }
        }
        
        // Fallback to rule-based responses
        return $this->generateRuleBasedResponse($message, $context);
    }
    
    /**
     * Match user input against command patterns
     */
    private function matchCommandPattern($message) {
        try {
            $stmt = $this->adminDb->prepare("
                SELECT * FROM command_patterns 
                WHERE is_active = 1 
                AND (
                    (match_type = 'contains' AND ? LIKE CONCAT('%', pattern, '%')) OR
                    (match_type = 'exact' AND LOWER(pattern) = ?) OR
                    (match_type = 'regex' AND ? REGEXP pattern)
                )
                ORDER BY priority DESC, LENGTH(pattern) DESC
                LIMIT 1
            ");
            $stmt->bind_param("sss", $message, $message, $message);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $pattern = $result->fetch_assoc();
                $commands = explode(',', $pattern['suggested_commands']);
                
                return [
                    'message' => $pattern['response_template'],
                    'suggested_command' => trim($commands[0]),
                    'command_description' => $pattern['description'],
                    'category' => $pattern['category'],
                    'priority' => $pattern['priority'],
                    'context' => ['pattern_matched' => $pattern['pattern']]
                ];
            }
        } catch (Exception $e) {
            error_log("Pattern matching error: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Search knowledge base for relevant information
     */
    private function searchKnowledgeBase($message) {
        try {
            // Full-text search in knowledge base
            $stmt = $this->adminDb->prepare("
                SELECT *, MATCH(question, answer, keywords) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM chatbot_knowledge_base 
                WHERE is_active = 1 
                AND MATCH(question, answer, keywords) AGAINST(? IN NATURAL LANGUAGE MODE)
                ORDER BY relevance DESC
                LIMIT 1
            ");
            $stmt->bind_param("ss", $message, $message);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $knowledge = $result->fetch_assoc();
                
                return [
                    'message' => $knowledge['answer'],
                    'suggested_command' => $knowledge['command_example'],
                    'command_description' => "Example for: " . $knowledge['question'],
                    'category' => $knowledge['category'],
                    'priority' => 8,
                    'context' => ['knowledge_id' => $knowledge['id']]
                ];
            }
        } catch (Exception $e) {
            error_log("Knowledge base search error: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Generate contextual response based on recent command history
     */
    private function generateContextualResponse($message, $context) {
        $lastCommand = null;
        $workingDirectory = 'C:\\';
        
        // Extract last command and working directory from context
        if (isset($context['command_history'])) {
            if (is_array($context['command_history'])) {
                $lastCommand = end($context['command_history']);
            } else {
                $lastCommand = $context['command_history'];
            }
        }
        
        if (isset($context['working_directory']['current_directory'])) {
            $workingDirectory = $context['working_directory']['current_directory'];
        }
        
        // Context-aware responses
        if (strpos($message, 'what') !== false && strpos($message, 'directory') !== false) {
            return [
                'message' => "You are currently in: **$workingDirectory**\n\nUse 'dir' to see the contents of this directory.",
                'suggested_command' => 'dir',
                'command_description' => 'List contents of current directory',
                'category' => 'file_operations',
                'priority' => 9,
                'context' => ['contextual_response' => 'working_directory']
            ];
        }
        
        if ($lastCommand && (strpos($message, 'error') !== false || strpos($message, 'failed') !== false || strpos($message, 'help') !== false)) {
            $commandBase = $this->extractCommandBase($lastCommand['command'] ?? $lastCommand);
            return $this->generateCommandHelp($commandBase, $lastCommand);
        }
        
        return null;
    }
    
    /**
     * Generate help for a specific command
     */
    private function generateCommandHelp($command, $lastCommand = null) {
        $command = strtolower(trim($command));
        
        $helpResponses = [
            'dir' => [
                'message' => "The **dir** command lists files and directories. Here are useful variations:\n\n• **dir** - Basic listing\n• **dir /a** - Show all files (including hidden)\n• **dir /s** - Include subdirectories\n• **dir *.txt** - Show only .txt files\n• **dir /o:d** - Order by date",
                'suggested_command' => 'dir /a',
                'command_description' => 'Show all files including hidden ones',
                'category' => 'help',
                'priority' => 7,
                'context' => ['help_for' => 'dir']
            ],
            'cd' => [
                'message' => "The **cd** command changes directories:\n\n• **cd [path]** - Change to specific directory\n• **cd ..** - Go up one level\n• **cd \\** - Go to root directory\n• **cd** - Show current directory",
                'suggested_command' => 'cd ..',
                'command_description' => 'Go up one directory level',
                'category' => 'help',
                'priority' => 7,
                'context' => ['help_for' => 'cd']
            ],
            'copy' => [
                'message' => "The **copy** command copies files:\n\n• **copy source dest** - Copy file\n• **copy *.txt backup\\** - Copy all .txt files\n• **copy /y** - Overwrite without prompting",
                'suggested_command' => 'copy *.* backup\\',
                'command_description' => 'Copy all files to backup folder',
                'category' => 'help',
                'priority' => 7,
                'context' => ['help_for' => 'copy']
            ],
            'ping' => [
                'message' => "The **ping** command tests network connectivity:\n\n• **ping [host]** - Test connectivity\n• **ping -t [host]** - Continuous ping\n• **ping -n 5 [host]** - Ping 5 times only",
                'suggested_command' => 'ping -n 5 google.com',
                'command_description' => 'Ping Google 5 times to test connectivity',
                'category' => 'help',
                'priority' => 7,
                'context' => ['help_for' => 'ping']
            ]
        ];
        
        if (isset($helpResponses[$command])) {
            return $helpResponses[$command];
        }
        
        return [
            'message' => "I see you ran `$command`. Here are some general tips:\n\n• Add `/help` or `/?` to most commands for built-in help\n• Use **help** to see all available commands\n• Try **help $command** for specific command help",
            'suggested_command' => "help $command",
            'command_description' => "Get help for the $command command",
            'category' => 'help',
            'priority' => 6,
            'context' => ['generic_help' => $command]
        ];
    }
    
    /**
     * Generate rule-based response for common patterns
     */
    private function generateRuleBasedResponse($message, $context = []) {
        // Greeting responses
        if (preg_match('/^(hi|hello|hey|greetings)/i', $message)) {
            return [
                'message' => "Hello! I'm Ghosty the AI Guide. I can help you with Windows commands, explain how to perform tasks, and suggest useful commands.\n\nWhat would you like to do?",
                'category' => 'greeting',
                'context' => ['response_type' => 'greeting']
            ];
        }
        
        // Help requests
        if (strpos($message, 'help') !== false || strpos($message, 'command') !== false) {
            return $this->generateHelpResponse($message);
        }
        
        // File operations
        if (preg_match('/(file|folder|directory|create|delete|copy|move|list)/i', $message)) {
            return $this->generateFileOperationResponse($message);
        }
        
        // Network operations
        if (preg_match('/(network|ping|connect|internet|ip)/i', $message)) {
            return $this->generateNetworkResponse($message);
        }
        
        // System information
        if (preg_match('/(system|info|hardware|version|computer)/i', $message)) {
            return $this->generateSystemInfoResponse($message);
        }
        
        // Process management
        if (preg_match('/(process|task|running|kill|stop)/i', $message)) {
            return $this->generateProcessResponse($message);
        }
        
        // Default response
        return [
            'message' => "I can help you with Windows commands! Try asking me about:\n\n• **File operations** (copy, move, delete files)\n• **Directory navigation** (changing folders, listing contents)\n• **Network commands** (ping, connectivity tests)\n• **System information** (hardware details, version info)\n• **Process management** (viewing and managing running programs)\n\nWhat would you like to know?",
            'category' => 'general',
            'context' => ['response_type' => 'default_help']
        ];
    }
    
    /**
     * Generate help response
     */
    private function generateHelpResponse($message) {
        if (strpos($message, 'file') !== false) {
            return [
                'message' => "Here are common file and directory commands:\n\n• **dir** - List files and folders\n• **cd [path]** - Change directory\n• **mkdir [name]** - Create folder\n• **copy [source] [dest]** - Copy files\n• **move [source] [dest]** - Move files\n• **del [file]** - Delete file\n• **type [file]** - Display file contents",
                'suggested_command' => 'dir',
                'command_description' => 'List files in current directory',
                'category' => 'file_operations',
                'priority' => 8,
                'context' => ['help_category' => 'file_operations']
            ];
        }
        
        return [
            'message' => "I can help you with Windows commands! Here are the main categories:\n\n• **File & Directory Operations**\n• **Network Commands**\n• **System Information**\n• **Process Management**\n• **User & Security Commands**\n\nAsk me about any specific category or describe what you want to do!",
            'category' => 'help',
            'context' => ['help_type' => 'categories']
        ];
    }
    
    /**
     * Generate file operation response
     */
    private function generateFileOperationResponse($message) {
        if (strpos($message, 'list') !== false || strpos($message, 'show') !== false) {
            return [
                'message' => "To list files and directories:\n\n• **dir** - Basic listing\n• **dir /a** - Show all files including hidden\n• **dir /s** - Include subdirectories\n• **tree** - Show directory structure",
                'suggested_command' => 'dir',
                'command_description' => 'List files and directories',
                'category' => 'file_operations',
                'priority' => 9,
                'context' => ['operation' => 'list_files']
            ];
        }
        
        if (strpos($message, 'create') !== false || strpos($message, 'make') !== false) {
            if (strpos($message, 'folder') !== false || strpos($message, 'directory') !== false) {
                return [
                    'message' => "To create a new folder:\n\n**mkdir [folder_name]**\n\nExample: `mkdir \"My New Folder\"`\n\nYou can also create nested folders:\n`mkdir \"Parent\\Child\\Subfolder\"`",
                    'suggested_command' => 'mkdir NewFolder',
                    'command_description' => 'Create a new folder named "NewFolder"',
                    'category' => 'file_operations',
                    'priority' => 8,
                    'context' => ['operation' => 'create_folder']
                ];
            } else {
                return [
                    'message' => "To create a new file:\n\n• **echo [content] > [filename]** - Create file with content\n• **type nul > [filename]** - Create empty file\n• **copy nul [filename]** - Another way to create empty file",
                    'suggested_command' => 'echo Hello World > test.txt',
                    'command_description' => 'Create a text file with sample content',
                    'category' => 'file_operations',
                    'priority' => 7,
                    'context' => ['operation' => 'create_file']
                ];
            }
        }
        
        if (strpos($message, 'copy') !== false) {
            return [
                'message' => "To copy files:\n\n• **copy [source] [destination]** - Copy single file\n• **xcopy [source] [dest] /s** - Copy folders with subdirectories\n• **robocopy [source] [dest]** - Advanced copy with more options",
                'suggested_command' => 'copy document.txt backup_document.txt',
                'command_description' => 'Copy document.txt to backup_document.txt',
                'category' => 'file_operations',
                'priority' => 8,
                'context' => ['operation' => 'copy_files']
            ];
        }
        
        return [
            'message' => "Common file operations:\n\n• **dir** - List files\n• **copy** - Copy files\n• **move** - Move files\n• **del** - Delete files\n• **mkdir** - Create folders\n\nWhat specific file operation do you need help with?",
            'category' => 'file_operations',
            'context' => ['operation' => 'general_file_help']
        ];
    }
    
    /**
     * Generate network response
     */
    private function generateNetworkResponse($message) {
        return [
            'message' => "Network diagnostic commands:\n\n• **ping [host]** - Test connectivity\n• **ipconfig** - Show network configuration\n• **ipconfig /all** - Detailed network info\n• **netstat** - Show network connections\n• **tracert [host]** - Trace route to host\n• **nslookup [domain]** - DNS lookup",
            'suggested_command' => 'ping google.com',
            'command_description' => 'Test internet connectivity by pinging Google',
            'category' => 'network',
            'priority' => 8,
            'context' => ['operation' => 'network_diagnostics']
        ];
    }
    
    /**
     * Generate system info response
     */
    private function generateSystemInfoResponse($message) {
        return [
            'message' => "System information commands:\n\n• **systeminfo** - Complete system details\n• **ver** - Windows version\n• **hostname** - Computer name\n• **whoami** - Current user\n• **wmic computersystem get model,name,manufacturer** - Hardware info",
            'suggested_command' => 'systeminfo',
            'command_description' => 'Display comprehensive system information',
            'category' => 'system_info',
            'priority' => 8,
            'context' => ['operation' => 'system_information']
        ];
    }
    
    /**
     * Generate process management response
     */
    private function generateProcessResponse($message) {
        return [
            'message' => "Process management commands:\n\n• **tasklist** - Show all running processes\n• **tasklist /svc** - Show processes with services\n• **taskkill /pid [ID]** - Kill process by ID\n• **taskkill /im [name]** - Kill process by name\n\n⚠️ **Warning**: Be careful when killing processes!",
            'suggested_command' => 'tasklist',
            'command_description' => 'Display all currently running processes',
            'category' => 'processes',
            'priority' => 8,
            'context' => ['operation' => 'process_management']
        ];
    }
    
    /**
     * Extract base command from full command string
     */
    private function extractCommandBase($command) {
        if (is_string($command)) {
            $parts = explode(' ', trim($command));
            return strtolower($parts[0]);
        }
        return 'unknown';
    }
    
    /**
     * Log a chat message
     */
    private function logMessage($messageType, $message, $contextData = [], $responseTime = null, $suggestedCommand = null) {
        try {
            $contextJson = json_encode($contextData);
            
            $stmt = $this->adminDb->prepare("
                INSERT INTO chatbot_conversations 
                (user_id, session_id, conversation_id, message_type, message, context_data, response_time, suggested_command) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssssds", 
                $this->userId, 
                $this->sessionId, 
                $this->conversationId, 
                $messageType, 
                $message, 
                $contextJson, 
                $responseTime, 
                $suggestedCommand
            );
            $stmt->execute();
            
            return $this->adminDb->insert_id;
        } catch (Exception $e) {
            error_log("Log message error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Store a command suggestion
     */
    private function storeCommandSuggestion($command, $description, $context, $category = 'general', $priority = 5) {
        try {
            $stmt = $this->adminDb->prepare("
                INSERT INTO command_suggestions 
                (conversation_id, user_id, suggested_command, command_description, suggestion_context, category, priority) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sissssi", 
                $this->conversationId, 
                $this->userId, 
                $command, 
                $description, 
                $context, 
                $category, 
                $priority
            );
            $stmt->execute();
            
            return $this->adminDb->insert_id;
        } catch (Exception $e) {
            error_log("Store command suggestion error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get or create conversation ID for session
     */
    private function getOrCreateConversationId($sessionId) {
        if (!$sessionId) {
            return 'conv_welcome_' . $this->userId . '_' . time();
        }
        
        try {
            // Check if conversation exists for this session
            $stmt = $this->adminDb->prepare("
                SELECT DISTINCT conversation_id 
                FROM chatbot_conversations 
                WHERE session_id = ? AND user_id = ?
                ORDER BY timestamp DESC 
                LIMIT 1
            ");
            $stmt->bind_param("si", $sessionId, $this->userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return $result->fetch_assoc()['conversation_id'];
            }
        } catch (Exception $e) {
            error_log("Get conversation ID error: " . $e->getMessage());
        }
        
        // Create new conversation ID
        return 'conv_' . $sessionId . '_' . time();
    }
    
    /**
     * Get chat history for a session
     */
    public function getChatHistory($limit = 50) {
        try {
            $stmt = $this->adminDb->prepare("
                SELECT id, message_type, message, timestamp, response_time, suggested_command, context_data
                FROM chatbot_conversations 
                WHERE conversation_id = ? AND user_id = ?
                ORDER BY timestamp ASC 
                LIMIT ?
            ");
            $stmt->bind_param("sii", $this->conversationId, $this->userId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $messages = [];
            while ($row = $result->fetch_assoc()) {
                if ($row['context_data']) {
                    $row['context_data'] = json_decode($row['context_data'], true);
                }
                $messages[] = $row;
            }
            
            return $messages;
        } catch (Exception $e) {
            error_log("Get chat history error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update session context
     */
    public function updateSessionContext($contextType, $contextData) {
        if (!$this->sessionId) return;
        
        try {
            $contextJson = json_encode($contextData);
            $stmt = $this->adminDb->prepare("
                INSERT INTO session_contexts (session_id, conversation_id, context_type, context_data) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                context_data = VALUES(context_data), 
                updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->bind_param("ssss", $this->sessionId, $this->conversationId, $contextType, $contextJson);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Update session context error: " . $e->getMessage());
        }
    }
    
    /**
     * Get session context
     */
    public function getSessionContext() {
        if (!$this->sessionId) return [];
        
        try {
            $stmt = $this->adminDb->prepare("
                SELECT context_type, context_data 
                FROM session_contexts 
                WHERE session_id = ? AND conversation_id = ?
            ");
            $stmt->bind_param("ss", $this->sessionId, $this->conversationId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $context = [];
            while ($row = $result->fetch_assoc()) {
                $context[$row['context_type']] = json_decode($row['context_data'], true);
            }
            
            return $context;
        } catch (Exception $e) {
            error_log("Get session context error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark command suggestion as executed
     */
    public function markSuggestionExecuted($suggestionId) {
        try {
            $stmt = $this->adminDb->prepare("
                UPDATE command_suggestions 
                SET executed = 1, executed_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->bind_param("ii", $suggestionId, $this->userId);
            $stmt->execute();
            
            return $stmt->affected_rows > 0;
        } catch (Exception $e) {
            error_log("Mark suggestion executed error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record feedback for a message
     */
    public function recordFeedback($messageId, $feedbackType, $feedbackText = null) {
        try {
            $stmt = $this->adminDb->prepare("
                INSERT INTO chatbot_feedback 
                (conversation_id, message_id, user_id, feedback_type, feedback_text) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("siiss", 
                $this->conversationId, 
                $messageId, 
                $this->userId, 
                $feedbackType, 
                $feedbackText
            );
            $stmt->execute();
            
            return $this->adminDb->insert_id;
        } catch (Exception $e) {
            error_log("Record feedback error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get command statistics for better suggestions
     */
    private function getCommandStatistics($hostId = null) {
        if (!$hostId) return [];
        
        try {
            $conn = $GLOBALS['conn']; // Terminal app database connection
            $stmt = $conn->prepare("
                SELECT command_base, execution_count, avg_execution_time, success_rate
                FROM command_statistics 
                WHERE host_id = ?
                ORDER BY execution_count DESC
                LIMIT 10
            ");
            $stmt->bind_param("s", $hostId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $stats = [];
            while ($row = $result->fetch_assoc()) {
                $stats[] = $row;
            }
            
            return $stats;
        } catch (Exception $e) {
            error_log("Get command statistics error: " . $e->getMessage());
            return [];
        }
    }
}

// Enhanced chatbot functions for API
function generateBotResponse($message, $context = [], $userId = null, $sessionId = null) {
    if (!$userId) {
        $user = getCurrentUser();
        $userId = $user ? $user['id'] : null;
    }
    
    if (!$userId) {
        return [
            'message' => 'Authentication required for chatbot functionality.',
            'category' => 'error'
        ];
    }
    
    try {
        $chatbot = new ChatbotEngine($userId, $sessionId);
        $response = $chatbot->processMessage($message, $context);
        
        // Convert to new format
        $suggestedCommands = [];
        if (!empty($response['suggested_command'])) {
            $suggestedCommands[] = [
                'id' => 'engine_cmd_0',
                'command' => $response['suggested_command'],
                'description' => $response['command_description'] ?? 'Generated command suggestion',
                'type' => 'engine'
            ];
        }
        
        return [
            'message' => $response['bot_response'],
            'suggested_command' => $response['suggested_command'], // Keep for backward compatibility
            'suggested_commands' => $suggestedCommands, // New format
            'command_description' => $response['command_description'],
            'suggestion_context' => $context,
            'context' => []
        ];
    } catch (Exception $e) {
        error_log("Generate bot response error: " . $e->getMessage());
        return [
            'message' => 'I apologize, but I encountered an error. Please try again.',
            'category' => 'error'
        ];
    }
}

function getChatbotHistory($sessionId, $userId = null, $limit = 50) {
    if (!$userId) {
        $user = getCurrentUser();
        $userId = $user ? $user['id'] : null;
    }
    
    if (!$userId) return [];
    
    try {
        $chatbot = new ChatbotEngine($userId, $sessionId);
        return $chatbot->getChatHistory($limit);
    } catch (Exception $e) {
        error_log("Get chatbot history error: " . $e->getMessage());
        return [];
    }
}

function executeChatbotSuggestion($suggestionId, $sessionId, $hostId, $userId = null) {
    if (!$userId) {
        $user = getCurrentUser();
        $userId = $user ? $user['id'] : null;
    }
    
    if (!$userId) return false;
    
    try {
        $chatbot = new ChatbotEngine($userId, $sessionId);
        return $chatbot->markSuggestionExecuted($suggestionId);
    } catch (Exception $e) {
        error_log("Execute chatbot suggestion error: " . $e->getMessage());
        return false;
    }
}

function updateChatbotContext($sessionId, $contextType, $contextData, $userId = null) {
    if (!$userId) {
        $user = getCurrentUser();
        $userId = $user ? $user['id'] : null;
    }
    
    if (!$userId) return;
    
    try {
        $chatbot = new ChatbotEngine($userId, $sessionId);
        $chatbot->updateSessionContext($contextType, $contextData);
    } catch (Exception $e) {
        error_log("Update chatbot context error: " . $e->getMessage());
    }
}
?>