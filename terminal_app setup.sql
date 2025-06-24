SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS terminal_app DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE terminal_app;

CREATE TABLE command_history (
  id int(11) NOT NULL,
  host_id varchar(50) NOT NULL,
  session_id varchar(64) DEFAULT NULL,
  working_directory text DEFAULT NULL,
  command text NOT NULL,
  output longtext DEFAULT NULL,
  timestamp timestamp NOT NULL DEFAULT current_timestamp(),
  response_timestamp timestamp NULL DEFAULT NULL,
  execution_time decimal(10,6) DEFAULT NULL,
  exit_code int(11) DEFAULT NULL,
  status enum('pending','completed','failed','timeout') DEFAULT 'pending',
  context_data longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(context_data))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE command_statistics (
  id int(11) NOT NULL,
  host_id varchar(50) NOT NULL,
  command_base varchar(100) NOT NULL,
  execution_count int(11) DEFAULT 1,
  avg_execution_time decimal(10,6) DEFAULT NULL,
  last_executed timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  success_rate decimal(5,2) DEFAULT 100.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `hosts` (
  id int(11) NOT NULL,
  host_id varchar(50) NOT NULL,
  hostname varchar(255) NOT NULL,
  ip_address varchar(45) NOT NULL,
  os_info text DEFAULT NULL,
  last_seen timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  connected tinyint(1) DEFAULT 1,
  first_seen timestamp NOT NULL DEFAULT current_timestamp(),
  total_sessions int(11) DEFAULT 0,
  total_commands int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE host_instance_mappings (
  id int(11) NOT NULL,
  host_id varchar(50) NOT NULL,
  instance_token varchar(128) NOT NULL,
  user_id int(11) DEFAULT NULL,
  mapped_at timestamp NOT NULL DEFAULT current_timestamp(),
  expires_at timestamp NOT NULL DEFAULT current_timestamp(),
  is_active tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE shell_sessions (
  id int(11) NOT NULL,
  session_id varchar(64) NOT NULL,
  host_id varchar(50) NOT NULL,
  current_directory text DEFAULT NULL,
  initial_directory text DEFAULT NULL,
  environment_vars longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(environment_vars)),
  start_time timestamp NOT NULL DEFAULT current_timestamp(),
  last_activity timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  is_active tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


ALTER TABLE command_history
  ADD PRIMARY KEY (id),
  ADD KEY idx_host_session (host_id,session_id),
  ADD KEY idx_session_time (session_id,timestamp),
  ADD KEY idx_status_time (status,timestamp),
  ADD KEY idx_working_dir (host_id,working_directory(100));

ALTER TABLE command_statistics
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY unique_host_command (host_id,command_base),
  ADD KEY idx_host_stats (host_id,execution_count);

ALTER TABLE hosts
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY host_id (host_id),
  ADD KEY idx_host_status (connected,last_seen),
  ADD KEY idx_host_id (host_id);

ALTER TABLE host_instance_mappings
  ADD PRIMARY KEY (id),
  ADD KEY idx_host_instance (host_id,instance_token),
  ADD KEY idx_instance_active (instance_token,is_active),
  ADD KEY idx_token_expires (instance_token,expires_at,is_active);

ALTER TABLE shell_sessions
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY session_id (session_id),
  ADD KEY idx_session_active (session_id,is_active),
  ADD KEY idx_session_host (host_id,is_active,last_activity);


ALTER TABLE command_history
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE command_statistics
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE hosts
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE host_instance_mappings
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE shell_sessions
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE command_history
  ADD CONSTRAINT command_history_ibfk_1 FOREIGN KEY (host_id) REFERENCES `hosts` (host_id) ON DELETE CASCADE;

ALTER TABLE command_statistics
  ADD CONSTRAINT command_statistics_ibfk_1 FOREIGN KEY (host_id) REFERENCES `hosts` (host_id) ON DELETE CASCADE;

ALTER TABLE host_instance_mappings
  ADD CONSTRAINT host_instance_mappings_ibfk_1 FOREIGN KEY (host_id) REFERENCES `hosts` (host_id) ON DELETE CASCADE;

ALTER TABLE shell_sessions
  ADD CONSTRAINT shell_sessions_ibfk_1 FOREIGN KEY (host_id) REFERENCES `hosts` (host_id) ON DELETE CASCADE;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;
