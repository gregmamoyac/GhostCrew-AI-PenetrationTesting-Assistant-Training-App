SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS ghostcrew_admin DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE ghostcrew_admin;

CREATE TABLE audit_log (
  id int(11) NOT NULL,
  user_id int(11) DEFAULT NULL,
  action_type enum('login','logout','command_execute','session_start','session_end','chat_message','system_access') NOT NULL,
  action_details longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(action_details)),
  ip_address varchar(45) DEFAULT NULL,
  user_agent text DEFAULT NULL,
  timestamp timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chatbot_conversations (
  id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  session_id varchar(64) DEFAULT NULL,
  conversation_id varchar(64) NOT NULL,
  message_type enum('user','bot') NOT NULL,
  message text NOT NULL,
  timestamp timestamp NOT NULL DEFAULT current_timestamp(),
  context_data longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(context_data)),
  response_time decimal(8,3) DEFAULT NULL,
  rating tinyint(1) DEFAULT NULL,
  flagged tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE command_log (
  id int(11) NOT NULL,
  session_id varchar(64) NOT NULL,
  user_id int(11) NOT NULL,
  command text NOT NULL,
  output longtext DEFAULT NULL,
  execution_time decimal(10,6) DEFAULT NULL,
  status enum('pending','completed','failed','timeout') DEFAULT 'pending',
  timestamp timestamp NOT NULL DEFAULT current_timestamp(),
  response_timestamp timestamp NULL DEFAULT NULL,
  error_message text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hosts_info (
  id int(11) NOT NULL,
  host_id varchar(50) NOT NULL,
  hostname varchar(255) NOT NULL,
  ip_address varchar(45) NOT NULL,
  os_info text DEFAULT NULL,
  first_seen timestamp NOT NULL DEFAULT current_timestamp(),
  last_seen timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  total_sessions int(11) DEFAULT 0,
  total_commands int(11) DEFAULT 0,
  is_active tinyint(1) DEFAULT 1,
  notes text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE remote_sessions (
  id int(11) NOT NULL,
  session_id varchar(64) NOT NULL,
  user_id int(11) NOT NULL,
  host_id varchar(50) NOT NULL,
  hostname varchar(255) NOT NULL,
  ip_address varchar(45) NOT NULL,
  os_info text DEFAULT NULL,
  start_time timestamp NOT NULL DEFAULT current_timestamp(),
  end_time timestamp NULL DEFAULT NULL,
  status enum('active','disconnected','terminated') DEFAULT 'active',
  last_activity timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  total_commands int(11) DEFAULT 0,
  session_notes text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_config (
  id int(11) NOT NULL,
  config_key varchar(100) NOT NULL,
  config_value text DEFAULT NULL,
  description text DEFAULT NULL,
  updated_by int(11) DEFAULT NULL,
  updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id int(11) NOT NULL,
  username varchar(50) NOT NULL,
  password_hash varchar(255) NOT NULL,
  full_name varchar(100) NOT NULL,
  email varchar(100) NOT NULL,
  role enum('admin','manager','operator') DEFAULT 'operator',
  is_active tinyint(1) DEFAULT 1,
  last_login timestamp NULL DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  created_by int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
  id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  session_token varchar(128) NOT NULL,
  ip_address varchar(45) NOT NULL,
  user_agent text DEFAULT NULL,
  login_time timestamp NOT NULL DEFAULT current_timestamp(),
  last_activity timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  logout_time timestamp NULL DEFAULT NULL,
  is_active tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE audit_log
  ADD PRIMARY KEY (id),
  ADD KEY idx_user_audit (user_id,timestamp),
  ADD KEY idx_action_time (action_type,timestamp);

ALTER TABLE chatbot_conversations
  ADD PRIMARY KEY (id),
  ADD KEY idx_user_conversation (user_id,conversation_id,timestamp),
  ADD KEY idx_session_chat (session_id,timestamp),
  ADD KEY idx_flagged_review (flagged,timestamp);

ALTER TABLE command_log
  ADD PRIMARY KEY (id),
  ADD KEY idx_session_time (session_id,timestamp),
  ADD KEY idx_user_commands (user_id,timestamp);

ALTER TABLE hosts_info
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY host_id (host_id),
  ADD KEY idx_host_activity (is_active,last_seen);

ALTER TABLE remote_sessions
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY session_id (session_id),
  ADD KEY idx_user_host (user_id,host_id),
  ADD KEY idx_session_status (status,start_time);

ALTER TABLE system_config
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY config_key (config_key),
  ADD KEY updated_by (updated_by);

ALTER TABLE users
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY username (username),
  ADD KEY created_by (created_by);

ALTER TABLE user_sessions
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY session_token (session_token),
  ADD KEY user_id (user_id);


ALTER TABLE audit_log
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE chatbot_conversations
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE command_log
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE hosts_info
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE remote_sessions
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE system_config
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE users
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE user_sessions
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE audit_log
  ADD CONSTRAINT audit_log_ibfk_1 FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE SET NULL;

ALTER TABLE chatbot_conversations
  ADD CONSTRAINT chatbot_conversations_ibfk_1 FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE CASCADE,
  ADD CONSTRAINT chatbot_conversations_ibfk_2 FOREIGN KEY (session_id) REFERENCES remote_sessions (session_id) ON DELETE SET NULL;

ALTER TABLE command_log
  ADD CONSTRAINT command_log_ibfk_1 FOREIGN KEY (session_id) REFERENCES remote_sessions (session_id) ON DELETE CASCADE,
  ADD CONSTRAINT command_log_ibfk_2 FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE CASCADE;

ALTER TABLE remote_sessions
  ADD CONSTRAINT remote_sessions_ibfk_1 FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE CASCADE;

ALTER TABLE system_config
  ADD CONSTRAINT system_config_ibfk_1 FOREIGN KEY (updated_by) REFERENCES `users` (id) ON DELETE SET NULL;

ALTER TABLE users
  ADD CONSTRAINT users_ibfk_1 FOREIGN KEY (created_by) REFERENCES `users` (id) ON DELETE SET NULL;

ALTER TABLE user_sessions
  ADD CONSTRAINT user_sessions_ibfk_1 FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE CASCADE;


SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS terminal_app DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE terminal_app;

CREATE TABLE command_history (
  id int(11) NOT NULL,
  host_id varchar(50) NOT NULL,
  session_id varchar(64) DEFAULT NULL,
  command text NOT NULL,
  output longtext DEFAULT NULL,
  timestamp timestamp NOT NULL DEFAULT current_timestamp(),
  response_timestamp timestamp NULL DEFAULT NULL,
  execution_time decimal(10,6) DEFAULT NULL,
  status enum('pending','completed','failed','timeout') DEFAULT 'pending',
  context_data longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(context_data))
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

CREATE TABLE shell_sessions (
  id int(11) NOT NULL,
  session_id varchar(64) NOT NULL,
  host_id varchar(50) NOT NULL,
  current_directory text DEFAULT NULL,
  environment_vars longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(environment_vars)),
  start_time timestamp NOT NULL DEFAULT current_timestamp(),
  last_activity timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  is_active tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


ALTER TABLE command_history
  ADD PRIMARY KEY (id),
  ADD KEY idx_host_session (host_id,session_id),
  ADD KEY idx_session_time (session_id,timestamp),
  ADD KEY idx_status_time (status,timestamp);

ALTER TABLE hosts
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY host_id (host_id),
  ADD KEY idx_host_status (connected,last_seen),
  ADD KEY idx_host_id (host_id);

ALTER TABLE shell_sessions
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY session_id (session_id),
  ADD KEY idx_session_active (session_id,is_active),
  ADD KEY host_id (host_id);


ALTER TABLE command_history
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE hosts
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE shell_sessions
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE command_history
  ADD CONSTRAINT command_history_ibfk_1 FOREIGN KEY (host_id) REFERENCES `hosts` (host_id) ON DELETE CASCADE;

ALTER TABLE shell_sessions
  ADD CONSTRAINT shell_sessions_ibfk_1 FOREIGN KEY (host_id) REFERENCES `hosts` (host_id) ON DELETE CASCADE;
