-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 03, 2025 at 07:25 PM
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
-- Database: `terminal_app`
--
CREATE DATABASE IF NOT EXISTS `terminal_app` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `terminal_app`;

-- --------------------------------------------------------

--
-- Table structure for table `command_history`
--

CREATE TABLE `command_history` (
  `id` int(11) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `is_interactive` tinyint(1) DEFAULT 0,
  `working_directory` text DEFAULT NULL,
  `command` text NOT NULL,
  `output` longtext DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `execution_start` timestamp NULL DEFAULT NULL,
  `response_timestamp` timestamp NULL DEFAULT NULL,
  `execution_time` decimal(10,6) DEFAULT NULL,
  `exit_code` int(11) DEFAULT NULL,
  `status` enum('pending','executing','completed','failed','timeout') DEFAULT 'pending',
  `context_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `command_source` enum('terminal','interactive') DEFAULT 'terminal'

--
-- Table structure for table `command_statistics`
--

CREATE TABLE `command_statistics` (
  `id` int(11) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `command_base` varchar(100) NOT NULL,
  `execution_count` int(11) DEFAULT 1,
  `avg_execution_time` decimal(10,6) DEFAULT NULL,
  `last_executed` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `success_rate` decimal(5,2) DEFAULT 100.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `hosts`
--

CREATE TABLE `hosts` (
  `id` int(11) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `os_info` text DEFAULT NULL,
  `last_seen` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `connected` tinyint(1) DEFAULT 1,
  `first_seen` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_sessions` int(11) DEFAULT 0,
  `total_commands` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `host_instance_mappings`
--

CREATE TABLE `host_instance_mappings` (
  `id` int(11) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `instance_token` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `mapped_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `interactive_command_patterns`
--

CREATE TABLE `interactive_command_patterns` (
  `id` int(11) NOT NULL,
  `command_pattern` varchar(255) NOT NULL,
  `pattern_type` enum('exact','prefix','regex','contains') DEFAULT 'exact',
  `is_interactive` tinyint(1) DEFAULT 1,
  `is_continuous` tinyint(1) DEFAULT 0,
  `timeout_seconds` int(11) DEFAULT 1800,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `interactive_command_patterns`
--

INSERT INTO `interactive_command_patterns` (`id`, `command_pattern`, `pattern_type`, `is_interactive`, `is_continuous`, `timeout_seconds`, `description`, `created_at`, `is_active`) VALUES
(1, 'msfconsole', 'exact', 1, 0, 1800, 'Metasploit', '2025-07-07 23:54:06', 1),
(2, 'ssh', 'prefix', 1, 0, 1800, 'SSH remote access', '2025-07-07 23:54:06', 1),
(3, 'python', 'exact', 1, 0, 1800, 'Python interpreter', '2025-07-07 23:54:06', 1),
(4, 'python3', 'exact', 1, 0, 1800, 'Python 3 interpreter', '2025-07-07 23:54:06', 1),
(5, 'mysql', 'prefix', 1, 0, 1800, 'MySQL client', '2025-07-07 23:54:06', 1),
(6, 'vim', 'prefix', 1, 0, 1800, 'Vim editor', '2025-07-07 23:54:06', 1),
(7, 'nano', 'prefix', 1, 0, 1800, 'Nano editor', '2025-07-07 23:54:06', 1),
(8, 'top', 'exact', 1, 0, 1800, 'System monitor', '2025-07-07 23:54:06', 1),
(9, 'ping', 'prefix', 1, 0, 1800, 'Network ping', '2025-07-07 23:54:06', 1),
(10, 'telnet', 'exact', 1, 0, 1800, 'Telnet client - exact match', '2025-07-08 00:24:32', 1),
(11, 'telnet ', 'prefix', 1, 0, 1800, 'Telnet client with arguments', '2025-07-08 00:24:32', 1);

-- --------------------------------------------------------

--
-- Table structure for table `shell_sessions`
--

CREATE TABLE `shell_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `current_directory` text DEFAULT NULL,
  `initial_directory` text DEFAULT NULL,
  `environment_vars` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `streaming_output`
--

CREATE TABLE `streaming_output` (
  `id` int(11) NOT NULL,
  `command_id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `output_chunk` longtext NOT NULL,
  `chunk_sequence` int(11) DEFAULT 1,
  `is_partial` tinyint(1) DEFAULT 1,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `streaming_sessions`
--

CREATE TABLE `streaming_sessions` (
  `id` int(11) NOT NULL,
  `command_id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `status` enum('active','paused','completed','terminated') DEFAULT 'active',
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_input_lines` int(11) DEFAULT 0,
  `total_output_size` bigint(20) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_input_queue`
--

CREATE TABLE `user_input_queue` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `host_id` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `command_id` int(11) DEFAULT NULL,
  `input_data` text NOT NULL,
  `input_type` enum('command','response','ctrl_signal') DEFAULT 'response',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed` tinyint(1) DEFAULT 0,
  `processed_at` timestamp NULL DEFAULT NULL,
  `priority` tinyint(4) DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `command_history`
--
ALTER TABLE `command_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_host_session` (`host_id`,`session_id`),
  ADD KEY `idx_session_time` (`session_id`,`timestamp`),
  ADD KEY `idx_status_time` (`status`,`timestamp`),
  ADD KEY `idx_working_dir` (`host_id`,`working_directory`(100)),
  ADD KEY `idx_is_interactive` (`is_interactive`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_interactive_status` (`is_interactive`,`status`,`timestamp`),
  ADD KEY `idx_session_status` (`session_id`,`status`,`timestamp`);

--
-- Indexes for table `command_statistics`
--
ALTER TABLE `command_statistics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_host_command` (`host_id`,`command_base`),
  ADD KEY `idx_host_stats` (`host_id`,`execution_count`);

--
-- Indexes for table `hosts`
--
ALTER TABLE `hosts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `host_id` (`host_id`),
  ADD KEY `idx_host_status` (`connected`,`last_seen`),
  ADD KEY `idx_host_id` (`host_id`);

--
-- Indexes for table `host_instance_mappings`
--
ALTER TABLE `host_instance_mappings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_host_instance` (`host_id`,`instance_token`),
  ADD KEY `idx_instance_active` (`instance_token`,`is_active`),
  ADD KEY `idx_token_expires` (`instance_token`,`expires_at`,`is_active`);

--
-- Indexes for table `interactive_command_patterns`
--
ALTER TABLE `interactive_command_patterns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shell_sessions`
--
ALTER TABLE `shell_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_session_active` (`session_id`,`is_active`),
  ADD KEY `idx_session_host` (`host_id`,`is_active`,`last_activity`);

--
-- Indexes for table `streaming_output`
--
ALTER TABLE `streaming_output`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_command_sequence` (`command_id`,`chunk_sequence`),
  ADD KEY `idx_session_time` (`session_id`,`last_update`),
  ADD KEY `idx_command_id` (`command_id`);

--
-- Indexes for table `streaming_sessions`
--
ALTER TABLE `streaming_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `command_id` (`command_id`),
  ADD KEY `idx_session_status` (`session_id`,`status`);

--
-- Indexes for table `user_input_queue`
--
ALTER TABLE `user_input_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session_processed` (`session_id`,`processed`,`timestamp`),
  ADD KEY `idx_processed` (`processed`),
  ADD KEY `idx_priority_processing` (`session_id`,`host_id`,`processed`,`priority`,`timestamp`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `command_history`
--
ALTER TABLE `command_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `command_statistics`
--
ALTER TABLE `command_statistics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `hosts`
--
ALTER TABLE `hosts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `host_instance_mappings`
--
ALTER TABLE `host_instance_mappings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `interactive_command_patterns`
--
ALTER TABLE `interactive_command_patterns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `shell_sessions`
--
ALTER TABLE `shell_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `streaming_output`
--
ALTER TABLE `streaming_output`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `streaming_sessions`
--
ALTER TABLE `streaming_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_input_queue`
--
ALTER TABLE `user_input_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `command_history`
--
ALTER TABLE `command_history`
  ADD CONSTRAINT `command_history_ibfk_1` FOREIGN KEY (`host_id`) REFERENCES `hosts` (`host_id`) ON DELETE CASCADE;

--
-- Constraints for table `command_statistics`
--
ALTER TABLE `command_statistics`
  ADD CONSTRAINT `command_statistics_ibfk_1` FOREIGN KEY (`host_id`) REFERENCES `hosts` (`host_id`) ON DELETE CASCADE;

--
-- Constraints for table `host_instance_mappings`
--
ALTER TABLE `host_instance_mappings`
  ADD CONSTRAINT `host_instance_mappings_ibfk_1` FOREIGN KEY (`host_id`) REFERENCES `hosts` (`host_id`) ON DELETE CASCADE;

--
-- Constraints for table `shell_sessions`
--
ALTER TABLE `shell_sessions`
  ADD CONSTRAINT `shell_sessions_ibfk_1` FOREIGN KEY (`host_id`) REFERENCES `hosts` (`host_id`) ON DELETE CASCADE;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
