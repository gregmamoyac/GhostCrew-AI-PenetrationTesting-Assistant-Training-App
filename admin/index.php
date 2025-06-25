<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GhostCrew Administrator - Secure Admin Portal</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Login Screen -->
    <div  id="loginScreen" class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="login-container">
            <div class="login-header">
                <h1>GhostCrew Administrator</h1>
                <p>Secure Admin Portal</p>
            </div>

            <div id="loginError" class="alert alert-danger" style="display: none;">
                <i class="fas fa-exclamation-circle me-2"></i>
                <span class="error-text"></span>
            </div>

            <form id="loginForm">
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-user me-2"></i>Username
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" 
                            class="form-control" 
                            id="username" 
                            name="username" 
                            placeholder="Enter your username"
                            value="admin"
                            required 
                            autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                    <div class="input-group position-relative">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            placeholder="Enter your password"
                            required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="passwordToggleIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-login" id="loginButton">
                    <span class="button-text">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Sign In
                    </span>
                    <div class="loading-spinner"></div>
                </button>
            </form>

            <div class="footer-text">
                <small>
                    <i class="fas fa-shield-alt me-1"></i>
                    Authorized personnel only
                </small>
            </div>
        </div>
    </div>

    <!-- Main Application -->
    <div id="mainApp" style="display: none;">
        <div class="container">
            <div class="header">
                <div class="logo">
                <img src="../assets/img/clippy.png" height="50px" width="50px"></span> GhostCrew Admin
                </div>
                <div class="user-info">
                    <span id="currentUser">Welcome, User</span>
                    <button class="btn btn-secondary btn-small" onclick="logout()">Logout</button>
                </div>
            </div>

            <div class="nav-tabs">
                <button class="nav-tab active" onclick="showSection('dashboard', this)">Dashboard</button>
                <button class="nav-tab" id="usersTab" onclick="showSection('users', this)">User Management</button>
                <button class="nav-tab" onclick="showSection('sessions', this)">Session Review</button>
                <button class="nav-tab" onclick="showSection('feedback', this)">Feedback & Grading</button>
                <button class="nav-tab" onclick="showSection('reports', this)">Reports</button>
                <button class="nav-tab" id="logsTab" onclick="showSection('logs', this)">System Logs</button>
                <button class="nav-tab" id="settingsTab" onclick="showSection('settings', this)">Settings</button>
                <button class="nav-tab" id="profileTab" onclick="showSection('profile', this)">My Profile</button>
            </div>

            <!-- Dashboard Section -->
            <div id="dashboard" class="content-section active">
                <div class="section-header">
                    <h2 class="section-title">Dashboard Overview</h2>
                    <button class="btn btn-primary" onclick="refreshDashboard()">Refresh</button>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number" id="totalUsers">0</div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="activeSessions">0</div>
                        <div class="stat-label">Active Sessions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="totalCommands">0</div>
                        <div class="stat-label">Commands Executed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="avgExecutionTime">0</div>
                        <div class="stat-label">Avg Execution Time (s)</div>
                    </div>
                </div>

                <div class="chart-container">
                    <h3>Session Activity Over Time</h3>
                    <div style="position: relative; height: 280px; width: 100%;">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>

                <div class="chart-container">
                    <h3>Command Status Distribution</h3>
                    <div style="position: relative; height: 280px; width: 100%;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- User Management Section -->
            <div id="users" class="content-section">
                <div class="section-header">
                    <h2 class="section-title">User Management</h2>
                    <button class="btn btn-primary" onclick="showUserModal()">Add New User</button>
                </div>

                <div class="filters">
                    <div class="filter-group">
                        <input type="text" id="userSearch" class="search-box" placeholder="Search users...">
                    </div>
                    <div class="filter-group">
                        <select id="roleFilter" class="form-select">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="manager">Manager</option>
                            <option value="operator">Operator</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <select id="statusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table" id="usersTable">
                        <thead>
                            <tr>
                                <th onclick="sortTable('users', 0)">ID</th>
                                <th onclick="sortTable('users', 1)">Username</th>
                                <th onclick="sortTable('users', 2)">Full Name</th>
                                <th onclick="sortTable('users', 3)">Email</th>
                                <th onclick="sortTable('users', 4)">Role</th>
                                <th onclick="sortTable('users', 5)">Manager</th>
                                <th onclick="sortTable('users', 6)">Status</th>
                                <th onclick="sortTable('users', 7)">Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Session Review Section -->
            <div id="sessions" class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Session Review</h2>
                    <div class="export-options">
                        <button class="btn btn-secondary btn-small" onclick="exportSessions('pdf')">Export PDF</button>
                        <button class="btn btn-secondary btn-small" onclick="exportSessions('excel')">Export Excel</button>
                    </div>
                </div>

                <div class="filters">
                    <div class="filter-group">
                        <input type="text" id="sessionSearch" class="search-box" placeholder="Search sessions...">
                    </div>
                    <div class="filter-group">
                        <select id="sessionUserFilter" class="form-select">
                            <option value="">All Users</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <select id="sessionStatusFilter" class="form-select">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="disconnected">Disconnected</option>
                            <option value="terminated">Terminated</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <input type="date" id="sessionDateFilter" class="form-input">
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table" id="sessionsTable">
                        <thead>
                            <tr>
                                <th onclick="sortTable('sessions', 0)">Session ID</th>
                                <th onclick="sortTable('sessions', 1)">User</th>
                                <th onclick="sortTable('sessions', 2)">Hostname</th>
                                <th onclick="sortTable('sessions', 3)">Start Time</th>
                                <th onclick="sortTable('sessions', 4)">Duration</th>
                                <th onclick="sortTable('sessions', 5)">Commands</th>
                                <th onclick="sortTable('sessions', 6)">Status</th>
                                <th onclick="sortTable('sessions', 7)">Grade Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sessionsTableBody">
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Feedback & Grading Section -->
            <div id="feedback" class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Feedback & Grading</h2>
                </div>

                <div class="filters">
                    <div class="filter-group">
                        <select id="gradingUserFilter" class="form-select">
                            <option value="">Select User</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <select id="gradingSessionFilter" class="form-select">
                            <option value="">Select Session</option>
                        </select>
                    </div>
                </div>

                <div id="gradingContent">
                    <p>Select a user and session to begin grading.</p>
                </div>
            </div>

            <!-- Reports Section -->
            <div id="reports" class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Reports & Analytics Dashboard</h2>
                    <div class="report-filters">
                        <input type="date" id="reportStartDate" class="form-input" style="width: 150px;">
                        <input type="date" id="reportEndDate" class="form-input" style="width: 150px;">
                        <select id="userRoleFilter" class="form-select" style="width: 150px;">
                            <option value="">All Roles</option>
                            <option value="operator">Operators</option>
                            <option value="manager">Managers</option>
                            <option value="admin">Admins</option>
                        </select>
                        <button class="btn btn-primary" onclick="refreshReports()">Update Reports</button>
                        <button class="btn btn-secondary" onclick="resetReportFilters()">Reset Filters</button>
                    </div>
                </div>
                
                <!-- Overview Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number" id="totalUsers">0</div>
                        <div class="stat-label">Active Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="totalSessions">0</div>
                        <div class="stat-label">Total Sessions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="avgScore">0</div>
                        <div class="stat-label">Average Grade</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="chatbotInteractions">0</div>
                        <div class="stat-label">Chatbot Interactions</div>
                    </div>
                </div>
                
                <!-- Detailed Statistics Tabs -->
                <div class="nav-tabs" style="margin-top: 30px;">
                    <button class="nav-tab active" onclick="showReportTab('overview', this)">📊 Overview</button>
                    <button class="nav-tab" onclick="showReportTab('performance', this)">🏆 Performance</button>
                    <button class="nav-tab" onclick="showReportTab('grading', this)">📝 Grading</button>
                    <button class="nav-tab" onclick="showReportTab('chatbot', this)">🤖 Chatbot</button>
                    <button class="nav-tab" onclick="showReportTab('activity', this)">📈 Activity</button>
                </div>
                
                <!-- Overview Tab -->
                <div id="overviewReport" class="report-tab-content active">
                    <div class="reports-grid">
                        <!-- User Activity Chart -->
                        <div class="report-card">
                            <div class="report-header">
                                <h3>📈 Daily Activity (Last 14 Days)</h3>
                                <button class="btn btn-small btn-secondary" onclick="exportChart('activity')">Export</button>
                            </div>
                            <div class="report-content">
                                <canvas id="activityChart" height="200"></canvas>
                            </div>
                        </div>
                        
                        <!-- Command Usage Chart -->
                        <div class="report-card">
                            <div class="report-header">
                                <h3>⚡ Top Commands</h3>
                                <button class="btn btn-small btn-secondary" onclick="exportChart('commands')">Export</button>
                            </div>
                            <div class="report-content">
                                <canvas id="commandChart" height="200"></canvas>
                            </div>
                        </div>
                        
                        <!-- Grade Distribution -->
                        <div class="report-card">
                            <div class="report-header">
                                <h3>📊 Grade Distribution</h3>
                                <button class="btn btn-small btn-secondary" onclick="exportChart('grades')">Export</button>
                            </div>
                            <div class="report-content">
                                <canvas id="gradeChart" height="200"></canvas>
                            </div>
                        </div>
                        
                        <!-- Session Duration Trends -->
                        <div class="report-card">
                            <div class="report-header">
                                <h3>⏱️ Session Duration Trends</h3>
                                <button class="btn btn-small btn-secondary" onclick="exportChart('duration')">Export</button>
                            </div>
                            <div class="report-content">
                                <canvas id="durationChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Performance Tab -->
                <div id="performanceReport" class="report-tab-content">
                    <div class="reports-grid">
                        <div class="report-card full-width">
                            <div class="report-header">
                                <h3>👥 User Performance Analysis</h3>
                                <div class="export-options">
                                    <button class="btn btn-small btn-secondary" onclick="exportDetailedReport('user_performance', 'excel')">Export Excel</button>
                                    <button class="btn btn-small btn-secondary" onclick="exportDetailedReport('user_performance', 'pdf')">Export PDF</button>
                                </div>
                            </div>
                            <div class="report-content">
                                <div id="userPerformanceTable"></div>
                            </div>
                        </div>
                        
                        <div class="report-card">
                            <div class="report-header">
                                <h3>📊 Performance by Role</h3>
                            </div>
                            <div class="report-content">
                                <canvas id="performanceByRoleChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Grading Tab -->
                <div id="gradingReport" class="report-tab-content">
                    <div class="reports-grid">
                        <div class="report-card full-width">
                            <div class="report-header">
                                <h3>📝 Grading Analytics</h3>
                                <div class="export-options">
                                    <button class="btn btn-small btn-secondary" onclick="exportDetailedReport('grading_analytics', 'excel')">Export Excel</button>
                                    <button class="btn btn-small btn-secondary" onclick="exportDetailedReport('grading_analytics', 'pdf')">Export PDF</button>
                                </div>
                            </div>
                            <div class="report-content">
                                <div id="gradingAnalyticsTable"></div>
                            </div>
                        </div>
                        
                        <div class="report-card">
                            <div class="report-header">
                                <h3>📊 Grading Statistics</h3>
                            </div>
                            <div class="report-content">
                                <div id="gradingStats" class="report-stats">
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="totalGraded">0</div>
                                        <div class="report-stat-label">Sessions Graded</div>
                                    </div>
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="avgGrade">0</div>
                                        <div class="report-stat-label">Average Score</div>
                                    </div>
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="highPerformers">0</div>
                                        <div class="report-stat-label">High Performers (80+)</div>
                                    </div>
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="lowPerformers">0</div>
                                        <div class="report-stat-label">Need Improvement (<60)</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Chatbot Tab -->
                <div id="chatbotReport" class="report-tab-content">
                    <div class="reports-grid">
                        <div class="report-card full-width">
                            <div class="report-header">
                                <h3>🤖 Chatbot Analytics</h3>
                                <div class="export-options">
                                    <button class="btn btn-small btn-secondary" onclick="exportDetailedReport('chatbot_analytics', 'excel')">Export Excel</button>
                                    <button class="btn btn-small btn-secondary" onclick="exportDetailedReport('chatbot_analytics', 'pdf')">Export PDF</button>
                                </div>
                            </div>
                            <div class="report-content">
                                <div id="chatbotAnalyticsTable"></div>
                            </div>
                        </div>
                        
                        <div class="report-card">
                            <div class="report-header">
                                <h3>📈 Chatbot Effectiveness</h3>
                            </div>
                            <div class="report-content">
                                <canvas id="chatbotEffectivenessChart" height="200"></canvas>
                            </div>
                        </div>
                        
                        <div class="report-card">
                            <div class="report-header">
                                <h3>📊 Chatbot Statistics</h3>
                            </div>
                            <div class="report-content">
                                <div id="chatbotStats" class="report-stats">
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="totalMessages">0</div>
                                        <div class="report-stat-label">Total Messages</div>
                                    </div>
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="suggestionsGiven">0</div>
                                        <div class="report-stat-label">Suggestions Given</div>
                                    </div>
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="suggestionsExecuted">0</div>
                                        <div class="report-stat-label">Suggestions Executed</div>
                                    </div>
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="suggestionRate">0%</div>
                                        <div class="report-stat-label">Execution Rate</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Tab -->
                <div id="activityReport" class="report-tab-content">
                    <div class="reports-grid">
                        <div class="report-card full-width">
                            <div class="report-header">
                                <h3>📈 Comprehensive Activity Timeline</h3>
                                <button class="btn btn-small btn-secondary" onclick="exportChart('comprehensive_activity')">Export</button>
                            </div>
                            <div class="report-content">
                                <canvas id="comprehensiveActivityChart" height="300"></canvas>
                            </div>
                        </div>
                        
                        <div class="report-card">
                            <div class="report-header">
                                <h3>👤 User Login Activity</h3>
                            </div>
                            <div class="report-content">
                                <div id="loginStats" class="report-stats">
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="uniqueUsers">0</div>
                                        <div class="report-stat-label">Unique Users</div>
                                    </div>
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="totalLogins">0</div>
                                        <div class="report-stat-label">Total Logins</div>
                                    </div>
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="todayLogins">0</div>
                                        <div class="report-stat-label">Today's Logins</div>
                                    </div>
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="avgSessionLength">0m</div>
                                        <div class="report-stat-label">Avg Session Length</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="report-card">
                            <div class="report-header">
                                <h3>⚡ Command Success Rates</h3>
                            </div>
                            <div class="report-content">
                                <canvas id="commandSuccessChart" height="200"></canvas>
                            </div>
                        </div>
                        
                        <div class="report-card">
                            <div class="report-header">
                                <h3>📊 System Usage Metrics</h3>
                            </div>
                            <div class="report-content">
                                <div id="systemUsageStats" class="report-stats">
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="totalCommandsExecuted">0</div>
                                        <div class="report-stat-label">Commands Executed</div>
                                    </div>
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="avgExecutionTime">0s</div>
                                        <div class="report-stat-label">Avg Execution Time</div>
                                    </div>
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="errorRate">0%</div>
                                        <div class="report-stat-label">Error Rate</div>
                                    </div>
                                    <div class="report-stat-item">
                                        <div class="report-stat-value" id="activeSessions">0</div>
                                        <div class="report-stat-label">Active Sessions</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Logs Section -->
            <div id="logs" class="content-section">
                <div class="section-header">
                    <h2 class="section-title">System Logs</h2>
                    <button class="btn btn-secondary" onclick="exportLogs()">Export Logs</button>
                </div>

                <div class="filters">
                    <div class="filter-group">
                        <select id="logTypeFilter" class="form-select">
                            <option value="">All Actions</option>
                            <option value="login">Login</option>
                            <option value="logout">Logout</option>
                            <option value="command_execute">Command Execute</option>
                            <option value="session_start">Session Start</option>
                            <option value="session_end">Session End</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <input type="date" id="logDateFilter" class="form-input">
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table" id="logsTable">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>IP Address</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody">
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Settings Section -->
            <div id="settings" class="content-section">
                <div class="section-header">
                    <h2 class="section-title">System Settings</h2>
                    <button class="btn btn-primary" onclick="saveSettings()">Save Settings</button>
                </div>

                <div class="form-group">
                    <label class="form-label">Session Timeout (seconds)</label>
                    <input type="number" id="sessionTimeout" class="form-input" value="3600">
                </div>

                <div class="form-group">
                    <label class="form-label">Max Command History</label>
                    <input type="number" id="maxCommandHistory" class="form-input" value="1000">
                </div>

                <div class="form-group">
                    <label class="form-label">Audit Retention (days)</label>
                    <input type="number" id="auditRetention" class="form-input" value="90">
                </div>

                <div class="form-group">
                    <label class="form-label">Max Concurrent Sessions</label>
                    <input type="number" id="maxConcurrentSessions" class="form-input" value="10">
                </div>
            </div>
        </div>

        <!-- Profile Section -->
        <div id="profile" class="content-section">
            <div class="section-header">
                <h2 class="section-title">My Profile</h2>
                <button class="btn btn-primary" onclick="showEditProfileModal()">Edit Profile</button>
            </div>
            
            <div class="profile-grid">
                <div class="profile-info">
                    <h3>User Information</h3>
                    <div id="profileUserInfo"></div>
                </div>
                
                <div class="profile-sessions">
                    <h3>My Recent Sessions</h3>
                    <div id="profileSessions"></div>
                </div>
                
                <div class="profile-grades">
                    <h3>My Grades</h3>
                    <div id="profileGrades"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Session View Section -->
    <div id="sessionView" class="content-section">
        <div class="section-header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button class="btn btn-secondary" onclick="backToSessions()">← Back to Sessions</button>
                <h2 class="section-title" id="sessionViewTitle">Session Details</h2>
            </div>
            <div class="export-options">
                <button class="btn btn-secondary btn-small" id="sessionExportPDF">Export PDF</button>
                <button class="btn btn-secondary btn-small" id="sessionExportExcel">Export Excel</button>
            </div>
        </div>
        <div id="sessionViewContent">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>

    <!-- Grade View Section -->
    <div id="gradeView" class="content-section">
        <div class="section-header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button class="btn btn-secondary" onclick="backToSessions()">← Back to Sessions</button>
                <h2 class="section-title" id="gradeViewTitle">Grade Review</h2>
            </div>
            <div id="gradeEditControls" style="display: none;">
                <button class="btn btn-primary" onclick="enableGradeEditing()">Edit Grade</button>
                <button class="btn btn-success" onclick="saveGradeEdits()" style="display: none;" id="saveGradeBtn">Save Changes</button>
                <button class="btn btn-secondary" onclick="cancelGradeEditing()" style="display: none;" id="cancelGradeBtn">Cancel</button>
            </div>
        </div>
        <div id="gradeViewContent">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>

    <!-- User Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="userModalTitle">Add New User</h3>
                <button class="close-btn" onclick="closeUserModal()">&times;</button>
            </div>
            <form id="userForm">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" id="userUsername" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" id="userFullName" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" id="userEmail" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select id="userRole" class="form-select" required>
                        <option value="operator">Operator</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Manager</label>
                    <select id="userManager" class="form-select">
                        <option value="">No Manager</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" id="userPassword" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="userStatus" class="form-select">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeUserModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Session Detail Modal -->
    <div id="sessionModal" class="modal">
        <div class="modal-content" style="max-width: 900px; max-height: 80vh; overflow-y: auto;">
            <div class="modal-header">
                <h3>Session Details</h3>
                <button class="close-btn" onclick="closeSessionModal()">&times;</button>
            </div>
            <div id="sessionDetailContent">
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Action</h3>
                <button class="close-btn" onclick="closeConfirmModal()">&times;</button>
            </div>
            <div id="confirmMessage"></div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button class="btn btn-secondary" onclick="closeConfirmModal()">Cancel</button>
                <button class="btn btn-danger" id="confirmButton">Confirm</button>
            </div>
        </div>
    </div>
    <div class="clippy"></div>
    <script src="js/main.js"></script>
</body>
</html>