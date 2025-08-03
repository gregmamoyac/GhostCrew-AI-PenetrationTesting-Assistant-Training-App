-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 03, 2025 at 07:24 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ghostcrew_admin`
--
CREATE DATABASE IF NOT EXISTS `ghostcrew_admin` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ghostcrew_admin`;

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`gcadmin34yVbZL`@`%` PROCEDURE `CleanupOldConversations` (IN `days_to_keep` INT)   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Delete feedback first (foreign key constraint)
    DELETE f FROM chatbot_feedback f
    JOIN chatbot_conversations c ON f.conversation_id = c.conversation_id
    WHERE c.timestamp < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    -- Delete old conversation messages
    DELETE FROM chatbot_conversations 
    WHERE timestamp < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    COMMIT;
    
    -- Return cleanup summary
    SELECT 
        CONCAT('Cleaned up conversations older than ', days_to_keep, ' days') as message,
        ROW_COUNT() as conversations_deleted;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `ai_command_suggestions`
--

CREATE TABLE `ai_command_suggestions` (
  `id` int(11) NOT NULL,
  `command` text DEFAULT NULL,
  `suggested_count` int(11) DEFAULT 1,
  `executed_count` int(11) DEFAULT 0,
  `executed` tinyint(1) DEFAULT 0,
  `success_rate` decimal(5,2) DEFAULT 0.00,
  `last_executed` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_config`
--

CREATE TABLE `ai_config` (
  `id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_config`
--

INSERT INTO `ai_config` (`id`, `config_key`, `config_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'aws_ai_endpoint', 'https://runtime.sagemaker.us-east-2.amazonaws.com/endpoints/jumpstart-dft-llama-3-2-3b-instruct-20250722-004448/invocations', 'AWS AI API endpoint URL', '2025-07-18 01:55:03', '2025-07-22 00:59:15'),
(2, 'aws_api_key', '', 'AWS AI API authentication key', '2025-07-18 01:55:03', '2025-07-22 00:59:15'),
(3, 'max_tokens', '1000', 'Maximum tokens per AI response', '2025-07-18 01:55:03', '2025-07-18 01:55:03'),
(4, 'temperature', '0.7', 'AI response creativity level (0.0-1.0)', '2025-07-18 01:55:03', '2025-07-18 01:55:03'),
(5, 'context_messages', '10', 'Number of previous messages to include as context', '2025-07-18 01:55:03', '2025-07-18 01:55:03'),
(6, 'command_detection', '1', 'Enable automatic command detection in AI responses', '2025-07-18 01:55:03', '2025-07-18 01:55:03'),
(7, 'timeout_seconds', '30', 'Timeout for AI API requests in seconds', '2025-07-18 01:55:03', '2025-07-18 01:55:03'),
(8, 'model_name', 'aws-ai', 'AI model identifier', '2025-07-18 01:55:03', '2025-07-18 01:55:03'),
(9, 'system_prompt', 'You are an AI assistant helping with terminal commands and system administration. When suggesting commands, format them clearly and explain what they do. If you suggest a command, include it in a \"suggested_command\" field in your response.', 'System prompt for AI behavior', '2025-07-18 01:55:03', '2025-07-18 01:55:03'),
(10, 'max_context_length', '4000', 'Maximum context length in characters', '2025-07-18 01:55:03', '2025-07-18 01:55:03'),
(11, 'retry_attempts', '3', 'Number of retry attempts for failed requests', '2025-07-18 01:55:03', '2025-07-18 01:55:03'),
(12, 'retry_delay', '1000', 'Initial retry delay in milliseconds', '2025-07-18 01:55:03', '2025-07-18 01:55:03');

-- --------------------------------------------------------

--
-- Table structure for table `ai_performance_log`
--

CREATE TABLE `ai_performance_log` (
  `id` int(11) NOT NULL,
  `message_id` int(11) DEFAULT NULL,
  `request_timestamp` timestamp NULL DEFAULT current_timestamp(),
  `response_timestamp` timestamp NULL DEFAULT NULL,
  `response_time_ms` int(11) DEFAULT NULL,
  `tokens_used` int(11) DEFAULT NULL,
  `model_used` varchar(50) DEFAULT NULL,
  `status` enum('success','error','timeout') DEFAULT 'success',
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` enum('login','logout','command_execute','session_start','session_end','chat_message','system_access') NOT NULL,
  `action_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--
-- Table structure for table `chatbot_conversations`
--

CREATE TABLE `chatbot_conversations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `conversation_id` varchar(64) NOT NULL,
  `parent_message_id` int(11) DEFAULT NULL,
  `message_type` enum('user','bot') NOT NULL,
  `message` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `context_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `response_time` decimal(8,3) DEFAULT NULL,
  `message_tokens` int(11) DEFAULT NULL,
  `model_used` varchar(50) DEFAULT 'local',
  `suggested_command` text DEFAULT NULL,
  `command_executed` tinyint(1) DEFAULT 0,
  `rating` tinyint(1) DEFAULT NULL,
  `flagged` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--
-- Triggers `chatbot_conversations`
--
DELIMITER $$
CREATE TRIGGER `set_conversation_id_before_insert` BEFORE INSERT ON `chatbot_conversations` FOR EACH ROW BEGIN
    IF NEW.conversation_id IS NULL OR NEW.conversation_id = '' THEN
        SET NEW.conversation_id = CONCAT('conv_', IFNULL(NEW.session_id, 'default'), '_', UNIX_TIMESTAMP(), '_', SUBSTRING(MD5(RAND()), 1, 8));
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_command_stats_after_execution` AFTER UPDATE ON `chatbot_conversations` FOR EACH ROW BEGIN
    IF NEW.command_executed = 1 AND OLD.command_executed = 0 AND NEW.suggested_command IS NOT NULL THEN
        UPDATE command_usage_stats 
        SET 
            executed_count = executed_count + 1,
            last_executed = NOW(),
            success_rate = CASE 
                WHEN suggested_count > 0 THEN (executed_count + 1) / suggested_count * 100
                ELSE 0
            END
        WHERE command = NEW.suggested_command;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_command_stats_after_suggestion` AFTER INSERT ON `chatbot_conversations` FOR EACH ROW BEGIN
    IF NEW.suggested_command IS NOT NULL AND NEW.suggested_command != '' THEN
        INSERT INTO command_usage_stats (command, suggested_count, last_suggested)
        VALUES (NEW.suggested_command, 1, NEW.timestamp)
        ON DUPLICATE KEY UPDATE
            suggested_count = suggested_count + 1,
            last_suggested = NEW.timestamp;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_feedback`
--

CREATE TABLE `chatbot_feedback` (
  `id` int(11) NOT NULL,
  `conversation_id` varchar(64) NOT NULL,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `feedback_type` enum('helpful','not_helpful','incorrect','suggestion') NOT NULL,
  `feedback_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `chatbot_knowledge_base`
--

CREATE TABLE `chatbot_knowledge_base` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `keywords` text DEFAULT NULL,
  `command_example` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `command_log`
--

CREATE TABLE `command_log` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `is_interactive` tinyint(1) DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `command` text NOT NULL,
  `output` longtext DEFAULT NULL,
  `execution_time` decimal(10,6) DEFAULT NULL,
  `status` enum('pending','completed','failed','timeout') DEFAULT 'pending',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `response_timestamp` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `command_patterns`
--

CREATE TABLE `command_patterns` (
  `id` int(11) NOT NULL,
  `pattern` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `suggested_commands` text NOT NULL,
  `response_template` text NOT NULL,
  `match_type` enum('exact','contains','regex') DEFAULT 'contains',
  `priority` tinyint(1) DEFAULT 5,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `command_suggestions`
--

CREATE TABLE `command_suggestions` (
  `id` int(11) NOT NULL,
  `conversation_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `suggested_command` text NOT NULL,
  `command_description` text DEFAULT NULL,
  `priority` tinyint(1) DEFAULT 5,
  `category` varchar(50) DEFAULT 'general',
  `suggestion_context` text DEFAULT NULL,
  `executed` tinyint(1) DEFAULT 0,
  `executed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `command_usage_stats`
--

CREATE TABLE `command_usage_stats` (
  `id` int(11) NOT NULL,
  `command` varchar(255) NOT NULL,
  `suggested_count` int(11) DEFAULT 0,
  `executed_count` int(11) DEFAULT 0,
  `success_rate` decimal(5,2) DEFAULT 0.00,
  `avg_response_time` decimal(8,3) DEFAULT NULL,
  `last_suggested` timestamp NULL DEFAULT NULL,
  `last_executed` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `hosts_info`
--

CREATE TABLE `hosts_info` (
  `id` int(11) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `os_info` text DEFAULT NULL,
  `first_seen` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_seen` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_sessions` int(11) DEFAULT 0,
  `total_commands` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `remote_sessions`
--

CREATE TABLE `remote_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `os_info` text DEFAULT NULL,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `status` enum('active','disconnected','terminated') DEFAULT 'active',
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_commands` int(11) DEFAULT 0,
  `session_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `remote_sessions`
--
DELIMITER $$
CREATE TRIGGER `after_session_terminate` AFTER UPDATE ON `remote_sessions` FOR EACH ROW BEGIN
    DECLARE v_command_count INT DEFAULT 0;
    DECLARE v_session_duration INT DEFAULT NULL;
    DECLARE v_summary_exists INT DEFAULT 0;
    DECLARE v_temp_message TEXT;
    
    -- Only trigger when status changes to terminated
    IF (OLD.status != 'terminated' AND NEW.status = 'terminated') 
       OR (OLD.end_time IS NULL AND NEW.end_time IS NOT NULL AND NEW.status = 'terminated') THEN
        
        -- Check if summary already exists
        SELECT COUNT(*) INTO v_summary_exists 
        FROM session_summaries 
        WHERE session_id = NEW.session_id;
        
        -- Only create placeholder if summary doesn't exist
        IF v_summary_exists = 0 THEN
            -- Get command count for this session
            SELECT COUNT(*) INTO v_command_count 
            FROM command_log 
            WHERE session_id = NEW.session_id;
            
            -- Calculate session duration in minutes
            IF NEW.start_time IS NOT NULL AND NEW.end_time IS NOT NULL THEN
                SET v_session_duration = TIMESTAMPDIFF(MINUTE, NEW.start_time, NEW.end_time);
            END IF;
            
            -- Create a more detailed temporary message
            SET v_temp_message = CONCAT(
                '? Session being analyzed...\n\n',
                '? Preliminary Data:\n',
                '• Commands executed: ', v_command_count, '\n',
                '• Session duration: ', COALESCE(v_session_duration, 0), ' minutes\n',
                '• Status: Analysis in progress\n\n',
                '⏱️ AI analysis typically completes within 2-3 minutes.\n',
                'Please refresh to see the full summary.'
            );
            
            -- Insert temporary summary
            INSERT INTO session_summaries (
                session_id,
                user_id,
                hostname,
                command_count,
                session_duration,
                ai_summary,
                session_start_time,
                session_end_time
            ) VALUES (
                NEW.session_id,
                NEW.user_id,
                NEW.hostname,
                v_command_count,
                v_session_duration,
                v_temp_message,
                NEW.start_time,
                NEW.end_time
            );
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `session_contexts`
--

CREATE TABLE `session_contexts` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `conversation_id` varchar(64) NOT NULL,
  `context_type` enum('command_history','system_info','working_directory') NOT NULL,
  `context_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `session_feedback`
--

CREATE TABLE `session_feedback` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `overall_score` int(11) DEFAULT NULL,
  `instructor_feedback` text DEFAULT NULL,
  `command_feedback` longtext DEFAULT NULL,
  `rating` tinyint(4) DEFAULT NULL,
  `graded_by` int(11) DEFAULT NULL,
  `graded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `session_summaries`
--

CREATE TABLE `session_summaries` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `hostname` varchar(255) DEFAULT NULL,
  `command_count` int(11) DEFAULT 0,
  `session_duration` int(11) DEFAULT NULL,
  `ai_summary` longtext DEFAULT NULL,
  `summary_generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `session_start_time` timestamp NULL DEFAULT NULL,
  `session_end_time` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `system_config`
--

CREATE TABLE `system_config` (
  `id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_config`
--

INSERT INTO `system_config` (`id`, `config_key`, `config_value`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'session_timeout', '3600', 'User session timeout in seconds', NULL, '2025-06-01 18:25:18'),
(2, 'max_command_history', '100000', 'Maximum commands to keep in history per session', NULL, '2025-06-01 18:25:18'),
(3, 'chatbot_enabled', '1', 'Enable/disable chatbot functionality', NULL, '2025-05-26 22:03:43'),
(4, 'audit_retention_days', '999999', 'Days to retain audit logs', NULL, '2025-06-01 18:25:18'),
(5, 'max_concurrent_sessions', '10', 'Maximum concurrent sessions per user', NULL, '2025-05-26 22:03:43');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','manager','operator') DEFAULT 'operator',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`, `created_by`, `manager_id`) VALUES
(1, 'admin', '$2y$10$RBlnxjKPGfYxscehuPnFLOxBf42YhFsqrBIi9AepKQvUrXW9.t9sS', 'System Administrator', 'admin@ghostcrew.local', 'admin', 1, '2025-08-03 16:56:21', '2025-07-15 03:36:38', '2025-08-03 16:56:21', NULL, NULL)

-- --------------------------------------------------------

--
-- Table structure for table `user_instance_tokens`
--

CREATE TABLE `user_instance_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `instance_token` varchar(128) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `user_interactions`
--

CREATE TABLE `user_interactions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `interaction_type` enum('input','output','termination','command') NOT NULL,
  `interaction_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(128) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_command_suggestions`
--
ALTER TABLE `ai_command_suggestions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ai_config`
--
ALTER TABLE `ai_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_config_key` (`config_key`);

--
-- Indexes for table `ai_performance_log`
--
ALTER TABLE `ai_performance_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_performance_timestamp` (`request_timestamp`),
  ADD KEY `idx_performance_model` (`model_used`),
  ADD KEY `idx_message_performance` (`message_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_audit` (`user_id`,`timestamp`),
  ADD KEY `idx_action_time` (`action_type`,`timestamp`);

--
-- Indexes for table `chatbot_conversations`
--
ALTER TABLE `chatbot_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_conversation` (`user_id`,`conversation_id`,`timestamp`),
  ADD KEY `idx_session_chat` (`session_id`,`timestamp`),
  ADD KEY `idx_flagged_review` (`flagged`,`timestamp`),
  ADD KEY `idx_parent_message` (`parent_message_id`),
  ADD KEY `idx_conversation_thread` (`conversation_id`,`timestamp`),
  ADD KEY `idx_conversation_messages` (`conversation_id`,`timestamp`),
  ADD KEY `idx_user_conversations` (`user_id`,`timestamp`),
  ADD KEY `idx_message_search` (`message_type`,`timestamp`),
  ADD KEY `idx_user_sessions` (`user_id`,`session_id`,`timestamp`),
  ADD KEY `idx_model_performance` (`model_used`,`response_time`);

--
-- Indexes for table `chatbot_feedback`
--
ALTER TABLE `chatbot_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation_feedback` (`conversation_id`,`created_at`),
  ADD KEY `idx_message_feedback` (`message_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `chatbot_knowledge_base`
--
ALTER TABLE `chatbot_knowledge_base`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`,`is_active`),
  ADD KEY `idx_keywords` (`keywords`(255));
ALTER TABLE `chatbot_knowledge_base` ADD FULLTEXT KEY `idx_question_answer` (`question`,`answer`,`keywords`);

--
-- Indexes for table `command_log`
--
ALTER TABLE `command_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session_time` (`session_id`,`timestamp`),
  ADD KEY `idx_user_commands` (`user_id`,`timestamp`);

--
-- Indexes for table `command_patterns`
--
ALTER TABLE `command_patterns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pattern_category` (`category`,`is_active`,`priority`),
  ADD KEY `idx_pattern_active` (`is_active`,`priority`);

--
-- Indexes for table `command_suggestions`
--
ALTER TABLE `command_suggestions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation_suggestions` (`conversation_id`,`created_at`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_executed_suggestions` (`executed`,`created_at`),
  ADD KEY `idx_user_suggestions` (`user_id`,`executed`,`created_at`),
  ADD KEY `idx_priority_category` (`category`,`priority`,`created_at`);

--
-- Indexes for table `command_usage_stats`
--
ALTER TABLE `command_usage_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_command` (`command`),
  ADD KEY `idx_command_stats` (`suggested_count`,`executed_count`);

--
-- Indexes for table `hosts_info`
--
ALTER TABLE `hosts_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `host_id` (`host_id`),
  ADD KEY `idx_host_activity` (`is_active`,`last_seen`);

--
-- Indexes for table `remote_sessions`
--
ALTER TABLE `remote_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_user_host` (`user_id`,`host_id`),
  ADD KEY `idx_session_status` (`status`,`start_time`);

--
-- Indexes for table `session_contexts`
--
ALTER TABLE `session_contexts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_session_context` (`session_id`,`conversation_id`,`context_type`),
  ADD KEY `idx_session_context` (`session_id`,`context_type`),
  ADD KEY `idx_context_lookup` (`session_id`,`context_type`,`updated_at`);

--
-- Indexes for table `session_feedback`
--
ALTER TABLE `session_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `graded_by` (`graded_by`),
  ADD KEY `session_id` (`session_id`);

--
-- Indexes for table `session_summaries`
--
ALTER TABLE `session_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `system_config`
--
ALTER TABLE `system_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_manager_id` (`manager_id`);

--
-- Indexes for table `user_instance_tokens`
--
ALTER TABLE `user_instance_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `instance_token` (`instance_token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_interactions`
--
ALTER TABLE `user_interactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session_interactions` (`session_id`,`timestamp`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_command_suggestions`
--
ALTER TABLE `ai_command_suggestions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ai_config`
--
ALTER TABLE `ai_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `ai_performance_log`
--
ALTER TABLE `ai_performance_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=619;

--
-- AUTO_INCREMENT for table `chatbot_conversations`
--
ALTER TABLE `chatbot_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=640;

--
-- AUTO_INCREMENT for table `chatbot_feedback`
--
ALTER TABLE `chatbot_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `chatbot_knowledge_base`
--
ALTER TABLE `chatbot_knowledge_base`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `command_log`
--
ALTER TABLE `command_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;

--
-- AUTO_INCREMENT for table `command_patterns`
--
ALTER TABLE `command_patterns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `command_suggestions`
--
ALTER TABLE `command_suggestions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `command_usage_stats`
--
ALTER TABLE `command_usage_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=154;

--
-- AUTO_INCREMENT for table `hosts_info`
--
ALTER TABLE `hosts_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `remote_sessions`
--
ALTER TABLE `remote_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `session_contexts`
--
ALTER TABLE `session_contexts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `session_feedback`
--
ALTER TABLE `session_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `session_summaries`
--
ALTER TABLE `session_summaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `system_config`
--
ALTER TABLE `system_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `user_instance_tokens`
--
ALTER TABLE `user_instance_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `user_interactions`
--
ALTER TABLE `user_interactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=174;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_performance_log`
--
ALTER TABLE `ai_performance_log`
  ADD CONSTRAINT `fk_performance_message` FOREIGN KEY (`message_id`) REFERENCES `chatbot_conversations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `chatbot_conversations`
--
ALTER TABLE `chatbot_conversations`
  ADD CONSTRAINT `chatbot_conversations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chatbot_conversations_ibfk_2` FOREIGN KEY (`session_id`) REFERENCES `remote_sessions` (`session_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_parent_message` FOREIGN KEY (`parent_message_id`) REFERENCES `chatbot_conversations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `chatbot_feedback`
--
ALTER TABLE `chatbot_feedback`
  ADD CONSTRAINT `chatbot_feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chatbot_feedback_ibfk_2` FOREIGN KEY (`message_id`) REFERENCES `chatbot_conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_feedback_message` FOREIGN KEY (`message_id`) REFERENCES `chatbot_conversations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `command_log`
--
ALTER TABLE `command_log`
  ADD CONSTRAINT `command_log_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `remote_sessions` (`session_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `command_log_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `command_suggestions`
--
ALTER TABLE `command_suggestions`
  ADD CONSTRAINT `command_suggestions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `remote_sessions`
--
ALTER TABLE `remote_sessions`
  ADD CONSTRAINT `remote_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `session_contexts`
--
ALTER TABLE `session_contexts`
  ADD CONSTRAINT `session_contexts_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `remote_sessions` (`session_id`) ON DELETE CASCADE;

--
-- Constraints for table `session_feedback`
--
ALTER TABLE `session_feedback`
  ADD CONSTRAINT `session_feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `session_feedback_ibfk_2` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `session_feedback_ibfk_3` FOREIGN KEY (`session_id`) REFERENCES `remote_sessions` (`session_id`) ON DELETE CASCADE;

--
-- Constraints for table `session_summaries`
--
ALTER TABLE `session_summaries`
  ADD CONSTRAINT `session_summaries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `system_config`
--
ALTER TABLE `system_config`
  ADD CONSTRAINT `system_config_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_manager_fk` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_instance_tokens`
--
ALTER TABLE `user_instance_tokens`
  ADD CONSTRAINT `user_instance_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_interactions`
--
ALTER TABLE `user_interactions`
  ADD CONSTRAINT `user_interactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
