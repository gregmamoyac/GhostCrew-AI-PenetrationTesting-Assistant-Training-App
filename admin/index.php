<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GhostCrew Manage</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Login Screen -->
    <div id="loginScreen" class="login-container">
        <div class="logo" style="justify-content: center; margin-bottom: 30px;">
            <span><img src="../img/clippy.png" height="32px" width="32px"></span> GhostCrew Manage
        </div>
        <form id="loginForm">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" id="username" class="form-input" required value="admin">
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" id="password" class="form-input" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
        </form>
        <div id="loginError" class="alert alert-danger" style="display: none; margin-top: 20px;"></div>
    </div>

    <!-- Main Application -->
    <div id="mainApp" style="display: none;">
        <div class="container">
            <div class="header">
                <div class="logo">
                <img src="../img/clippy.png" height="50px" width="50px"></span> GhostCrew Manage
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
                    <canvas id="activityChart" width="400" height="200"></canvas>
                </div>

                <div class="chart-container">
                    <h3>Command Status Distribution</h3>
                    <canvas id="statusChart" width="400" height="200"></canvas>
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
                                <th onclick="sortTable('users', 5)">Status</th>
                                <th onclick="sortTable('users', 6)">Last Login</th>
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
                    <h2 class="section-title">Reports & Analytics</h2>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number" id="reportTotalSessions">0</div>
                        <div class="stat-label">Total Sessions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="reportAvgDuration">0</div>
                        <div class="stat-label">Avg Session Duration</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="reportTopCommand">-</div>
                        <div class="stat-label">Most Used Command</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="reportCompletionRate">0%</div>
                        <div class="stat-label">Success Rate</div>
                    </div>
                </div>

                <div class="chart-container">
                    <h3>Command Usage Over Time</h3>
                    <canvas id="commandUsageChart" width="400" height="200"></canvas>
                </div>

                <div class="chart-container">
                    <h3>Session Duration Distribution</h3>
                    <canvas id="durationChart" width="400" height="200"></canvas>
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