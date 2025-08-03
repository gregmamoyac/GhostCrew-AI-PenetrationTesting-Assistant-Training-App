# GhostCrew

An AI-powered simulation and decision-support platform for Red Team and penetration testing professionals.

**MICS Capstone Project - Summer 2025**  
UC Berkeley School of Information

## Team Members

- **Caleb Infinger**
- **Katelynn Hernandez** 
- **Joshua Penny**
- **Gregory Mamoyac**
- **Kevin Korp**
- **Sahil Shankar**

[View Project Details on UC Berkeley](https://www.ischool.berkeley.edu/projects/2025/ghostcrew)

## Description

Red Team and penetration testing professionals often face limited access to realistic, high-fidelity training environments due to live exercises' high cost, time commitment, and logistical complexity. This lack of frequent, practical experience can lead to slower skill development and higher risk during real-world operations.

GhostCrew addresses these challenges by providing an AI-powered platform that simulates decision-making scenarios, models outcomes, and provides intelligent feedback in real time. This empowers operators to rehearse complex operations, receive contextual guidance, and learn from simulations, ultimately accelerating proficiency and reducing errors during actual engagements.

### Key Features

- **AI-Powered Guidance**: Real-time tactical recommendations and decision support during penetration testing operations
- **Adaptive Feedback Loop**: Intelligent system that learns and adapts to enhance Red Team performance
- **Centralized Repository**: Comprehensive storage of past operations and lessons learned
- **Modular Integration**: Compatible with existing tooling through command-line interface and dashboard
- **Full Lifecycle Support**: Covers training, live operations, and post-mission analysis
- **Session Management**: Complete tracking and grading system for training sessions

### Use Cases

**Pre-Engagement Planning**
- Simulate victim machines and environments
- Provide decision-making opportunities for operator training
- Strategy planning and scenario modeling

**During Engagement**
- Just-in-time guidance and recommendations
- Real-time adaptive feedback from AI engine
- Course-of-action suggestions

**Post-Mission Analysis**
- Comprehensive debriefs with detailed analytics
- Tool and strategy effectiveness analysis
- Training feedback and performance grading

## How It Works

1. **Login**: Users authenticate to the GhostCrew platform
2. **Device Connection**: Execute the connection command to link a remote device to GhostCrew
3. **Remote Operations**: Execute commands on the remote device as if using it locally
4. **Session Management**: Track, analyze, and grade sessions through the admin portal
5. **Reporting**: Generate comprehensive reports and analytics

### Supported Platforms

- **Windows**: PowerShell and Python required
- **Linux**: Kali Linux recommended with PowerShell installed
  - [PowerShell on Linux Installation Guide](https://learn.microsoft.com/en-us/powershell/scripting/install/installing-powershell-on-linux?view=powershell-7.5)

## Installation

### Prerequisites

- **XAMPP** (Apache, MySQL, PHP)
- **PowerShell** (Windows native, Linux installation required)
- **Python** (3.x recommended)
- **Web server** with PHP support
- **MySQL/MariaDB** database server

### Web Application Setup

1. **Install XAMPP**
   ```bash
   # Download XAMPP from: https://www.apachefriends.org/download.html
   # Follow platform-specific installation instructions
   ```

2. **Deploy Application Files**
   ```bash
   # Copy all project files to XAMPP htdocs folder
   cp -r ghostcrew/ /path/to/xampp/htdocs/
   ```

3. **Database Configuration**
   
   Import the required SQL files to create databases:
   ```sql
   # In phpMyAdmin or MySQL command line:
   # Import ghostcrew_admin.sql
   # Import terminal_app.sql
   ```
   
   This creates a default administrator account:
   - **Username**: `admin`
   - **Password**: `!Password123!`

4. **Create Database Users**
   ```sql
   # Create dedicated users for each database
   CREATE USER 'ghostcrew_admin'@'localhost' IDENTIFIED BY 'your_secure_password';
   CREATE USER 'terminal_app'@'localhost' IDENTIFIED BY 'your_secure_password';
   
   # Grant appropriate permissions
   GRANT ALL PRIVILEGES ON ghostcrew_admin.* TO 'ghostcrew_admin'@'localhost';
   GRANT ALL PRIVILEGES ON terminal_app.* TO 'terminal_app'@'localhost';
   
   FLUSH PRIVILEGES;
   ```

5. **Update Configuration Files**
   
   Edit the following files with your database credentials:
   
   **config.php**
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'terminal_app');
   define('DB_PASS', 'your_secure_password');
   define('DB_NAME', 'terminal_app');
   define('APP_URL', 'http://your-domain.com/ghostcrew/');
   define('LOCAL_LISTENER_URL', 'http://your-domain.com/ghostcrew/scripts/');
   ```
   
   **auth_config.php**
   ```php
   define('ADMIN_DB_HOST', 'localhost');
   define('ADMIN_DB_USER', 'ghostcrew_admin');
   define('ADMIN_DB_PASS', 'your_secure_password');
   define('ADMIN_DB_NAME', 'ghostcrew_admin');
   ```
   
   **admin/api.php**
   ```php
   define('ADMIN_DB_HOST', 'localhost');
   define('ADMIN_DB_USER', 'ghostcrew_admin');
   define('ADMIN_DB_PASS', 'your_secure_password');
   define('ADMIN_DB_NAME', 'ghostcrew_admin');
   ```

### AI Integration Setup

1. **Configure AI Endpoint**

   >  **Note**: It is recommended to use the default AI models developed by the GhostCrew team, but if you chose to create your own, you will need to modify the files below to reflect your endpoint URL. Additional configuration may be required to ensure correct payload and response.
   
   Once your AI service is configured and tested:
   
   **api/chat.php**
   ```php
   $endpoint = 'https://your-ai-service.com/api/chat';
   // Default: https://vtwi9xccxj.execute-api.us-east-2.amazonaws.com/default/invokeRAG
   ```
   
   **admin/generate_session_summary.php**
   ```php
   $ai_endpoint = 'https://your-ai-service.com/api/summary';
   // Default: https://zl47lm7yy1.execute-api.us-east-2.amazonaws.com/invoke
   ```

2. **Setup Automated Session Summaries**
   
   Configure a scheduled task or cron job to automatically generate session summaries:
   
   **Windows (Task Scheduler)**
   ```
   Program: C:\path\to\php.exe
   Arguments: C:\path\to\ghostcrew\admin\generate_session_summary.php
   Frequency: Every minute
   ```
   
   **Linux (Cron)**
   ```bash
   # Edit crontab
   crontab -e
   
   # Add this line to run every minute
   * * * * * /usr/bin/php /path/to/ghostcrew/admin/generate_session_summary.php
   ```

### Remote Device Setup

1. **Windows Devices**
   - PowerShell is pre-installed
   - Install Python 3.x from [python.org](https://python.org)
   - Run the PowerShell installer to check and install dependencies

2. **Linux Devices (Kali Linux Recommended)**
   ```bash
   # Install PowerShell
   # Follow Microsoft's official guide:
   # https://learn.microsoft.com/en-us/powershell/scripting/install/installing-powershell-on-linux
   
   # Install Python (usually pre-installed on Kali)
   sudo apt update
   sudo apt install python3 python3-pip
   ```

## Usage

1. **Access the Web Interface**
   ```
   http://your-domain.com/ghostcrew/
   ```

2. **Login with Default Credentials**
   - Username: `admin`
   - Password: `!Password123!`

3. **Connect Remote Device**
   - Execute the provided connection command on your target device
   - Ensure PowerShell and Python are properly installed

4. **Start Training Session**
   - Begin penetration testing activities
   - Receive real-time AI guidance and feedback

5. **Review Sessions**
   - Access the Admin portal with the same credentials
   - View, grade, and analyze completed sessions
   - Generate comprehensive reports

## Security Considerations

- **Change Default Credentials**: Immediately update the default admin password
- **Database Security**: Use strong passwords for database users
- **Network Security**: Deploy over HTTPS in production environments
- **Access Control**: Implement proper user authentication and authorization
- **Regular Updates**: Keep all components updated with latest security patches

## Contributing

This project was developed as part of the UC Berkeley MICS Capstone Program. For questions or contributions, please contact the development team through the official UC Berkeley project page.

## Support

For technical support or questions about this project, please refer to the [UC Berkeley project page](https://www.ischool.berkeley.edu/projects/2025/ghostcrew) or contact the development team.

---

**Developed for the UC Berkeley School of Information MICS Capstone Summer 2025 Project**