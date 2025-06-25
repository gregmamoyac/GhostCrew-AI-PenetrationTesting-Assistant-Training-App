// Global variables
let currentUser = null;
let currentData = {
    users: [],
    sessions: [],
    logs: [],
    commands: [],
    settings: {}
};
let currentEditingUser = null;
let sortDirection = {};
let charts = {}; // Store chart instances
// Global variable to store current report data
let currentReportData = {};

// Initialize charts object on window for global access
window.charts = {};

// API Base URL - adjust this to your server path
const API_BASE = 'api.php';

// Session management
function isLoggedIn() {
    return localStorage.getItem('session_token') !== null;
}

function saveSession(user, sessionToken) {
    localStorage.setItem('session_token', sessionToken);
    localStorage.setItem('user_data', JSON.stringify(user));
    localStorage.setItem('session_expires', Date.now() + (8 * 60 * 60 * 1000)); // 8 hours
}

function clearSession() {
    localStorage.removeItem('session_token');
    localStorage.removeItem('user_data');
    localStorage.removeItem('session_expires');
}

function getStoredUser() {
    const userData = localStorage.getItem('user_data');
    const expires = localStorage.getItem('session_expires');
    
    if (userData && expires && Date.now() < parseInt(expires)) {
        return JSON.parse(userData);
    }
    
    clearSession();
    return null;
}

// Authentication
async function login() {
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    
    try {
        const response = await fetch(API_BASE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'login',
                username: username,
                password: password
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            saveSession(data.user, data.session_token || 'temp_token');
            
            document.getElementById('loginScreen').setAttribute('style', 'display:none !important');
            document.getElementById('mainApp').style.display = 'block';
            document.getElementById('currentUser').textContent = `Welcome, ${data.user.full_name}`;

            // Set up role-based access
            setupRoleBasedAccess(data.user.role);

            // Restore active tab or default to dashboard
            const activeTab = localStorage.getItem('activeTab') || 'dashboard';
            if (document.getElementById(activeTab)) {
                showSection(activeTab);
            } else {
                showSection('dashboard');
            }

            showAlert('Login successful!', 'success');
        } else {
            document.getElementById('loginError').textContent = data.message || 'Invalid credentials';
            document.getElementById('loginError').style.display = 'block';
        }
    } catch (error) {
        console.error('Login error:', error);
        document.getElementById('loginError').textContent = 'Connection error. Please try again.';
        document.getElementById('loginError').style.display = 'block';
    }
}

function logout() {
    clearSession();
    currentUser = null;
    document.getElementById('loginScreen').style.display = 'block';
    document.getElementById('mainApp').setAttribute('style', 'display:none !important');
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';
    document.getElementById('loginError').setAttribute('style', 'display:none !important');
    
    // Destroy all charts
    Object.values(charts).forEach(chart => {
        if (chart) chart.destroy();
    });
    charts = {};
}

function setupRoleBasedAccess(role) {
    // Get all navigation tabs
    const userManagementTab = document.getElementById('usersTab');
    const sessionsTab = document.querySelector('.nav-tab[onclick*="sessions"]');
    const feedbackTab = document.querySelector('.nav-tab[onclick*="feedback"]');
    const reportsTab = document.querySelector('.nav-tab[onclick*="reports"]');
    const logsTab = document.getElementById('logsTab');
    const settingsTab = document.getElementById('settingsTab');
    const profileTab = document.getElementById('profileTab');
    const dashboardTab = document.querySelector('.nav-tab[onclick*="dashboard"]');
    
    // All tabs array for easy management
    const allTabs = [
        { element: dashboardTab, name: 'dashboard' },
        { element: userManagementTab, name: 'users' },
        { element: sessionsTab, name: 'sessions' },
        { element: feedbackTab, name: 'feedback' },
        { element: reportsTab, name: 'reports' },
        { element: logsTab, name: 'logs' },
        { element: settingsTab, name: 'settings' },
        { element: profileTab, name: 'profile' }
    ];
    
    // Define access permissions for each role
    const rolePermissions = {
        'operator': [
            'dashboard',  // Can see their own stats
            'sessions',   // Can see their own sessions only
            'sessionView',   // Can see their own sessions only
            'feedback',   // Can see their own grades only
            'gradeView',   // Can see their own grades only
            'profile'     // Can manage their own profile
        ],
        'manager': [
            'dashboard',  // Can see team stats
            'users',      // Can manage operators
            'sessions',   // Can see all sessions
            'sessionView',// Can see sessions
            'feedback',   // Can grade sessions
            'gradeView',   // Can see grades
            'reports',    // Can view reports
            'profile'     // Can manage their own profile
        ],
        'admin': [
            'dashboard',  // Can see all stats
            'users',      // Can manage all users
            'sessions',   // Can see all sessions
            'sessionView',// Can see sessions
            'feedback',   // Can grade all sessions
            'gradeView',   // Can see grades
            'reports',    // Can view all reports
            'logs',       // Can view system logs
            'settings',   // Can modify system settings
            'profile'     // Can manage their own profile
        ]
    };
    
    // Get permissions for current role
    const allowedTabs = rolePermissions[role] || [];
    
    // Hide/show tabs based on permissions
    allTabs.forEach(tab => {
        if (tab.element) {
            if (allowedTabs.includes(tab.name)) {
                tab.element.style.display = 'block';
                tab.element.style.removeProperty('display');
            } else {
                tab.element.style.display = 'none';
            }
        }
    });
    
    // Store role permissions globally for use in other functions
    window.currentUserPermissions = allowedTabs;
    
    // If current active tab is not allowed, redirect to dashboard
    const activeTab = localStorage.getItem('activeTab');
    if (activeTab && !allowedTabs.includes(activeTab)) {
        localStorage.setItem('activeTab', 'dashboard');
        showSection('dashboard');
    }
    
    console.log(`Role-based access configured for ${role}:`, allowedTabs);
}

//Navigation
function showSection(sectionId, clickedElement = null) {

    console.log(`sectionId:`, sectionId);
    console.log(`clickedElement:`, clickedElement);
    console.log(`window.currentUserPermissions:`, window.currentUserPermissions);
    console.log(`window.currentUserPermissions:`, window.currentUserPermissions);
    console.log(`window.currentUserPermissions.includes(sectionId):`, window.currentUserPermissions.includes(sectionId));

    // Check if user has permission to access this section
    if (!window.currentUserPermissions || !window.currentUserPermissions.includes(sectionId)) {
        showAlert('Access denied: Insufficient permissions', 'danger');
        return;
    }
    
    // Check if we're leaving the feedback section and reset it
    const currentActiveSection = document.querySelector('.content-section.active');
    if (currentActiveSection && currentActiveSection.id === 'feedback' && sectionId !== 'feedback') {
        resetFeedbackSection();
    }

    
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected section and activate tab
    const targetSection = document.getElementById(sectionId);
    if (targetSection) {
        targetSection.classList.add('active');
    }
    
    // If clickedElement is provided, use it; otherwise find the tab button
    if (clickedElement) {
        clickedElement.classList.add('active');
    } else {
        const tabButton = document.querySelector(`[onclick*="${sectionId}"]`);
        if (tabButton) {
            tabButton.classList.add('active');
        }
    }
    
    // Save active tab
    localStorage.setItem('activeTab', sectionId);
    
    // Load section data
    switch(sectionId) {
        case 'dashboard':
            loadDashboard();
            break;
        case 'users':
            loadUsers();
            break;
        case 'sessions':
            loadSessions();
            break;
        case 'feedback':
            loadFeedback();
            break;
        case 'reports':
            loadReports();
            break;
        case 'logs':
            loadLogs();
            break;
        case 'settings':
            loadSettings();
            break;
        case 'profile':
            loadProfile();
            break;
    }
}

// Add this new function to reset the feedback section
function resetFeedbackSection() {
    document.getElementById('gradingUserFilter').value = '';
    document.getElementById('gradingSessionFilter').innerHTML = '<option value="">Select Session</option>';
    document.getElementById('gradingContent').innerHTML = '<p>Select a user and session to begin grading.</p>';
}

// Dashboard functions
async function loadDashboard() {
    try {
        const userRole = currentUser ? currentUser.role : 'admin';
        const userId = currentUser ? currentUser.id : null;
        
        const response = await fetch(`${API_BASE}?action=dashboard&user_role=${userRole}&user_id=${userId}`);
        const data = await response.json();
        
        if (data.success) {
            // Update statistics
            document.getElementById('totalUsers').textContent = data.stats.total_users || 0;
            document.getElementById('activeSessions').textContent = data.stats.active_sessions || 0;
            document.getElementById('totalCommands').textContent = data.stats.total_commands || 0;
            document.getElementById('avgExecutionTime').textContent = data.stats.avg_execution_time || 0;
            
            // Create charts with error handling
            if (data.charts && data.charts.activity) {
                createActivityChart(data.charts.activity);
            } else {
                console.warn('No activity chart data received');
                createActivityChart({ labels: [], datasets: { active_users: [], sessions: [], commands: [] } });
            }
            
            if (data.charts && data.charts.status) {
                createStatusChart(data.charts.status);
            } else {
                console.warn('No status chart data received');
                createStatusChart({ labels: ['No Data'], values: [0] });
            }
        } else {
            console.error('Dashboard API error:', data.message);
            showAlert('Error loading dashboard data: ' + (data.message || 'Unknown error'), 'danger');
        }
    } catch (error) {
        console.error('Dashboard load error:', error);
        showAlert('Error loading dashboard data', 'danger');
    }
}

function getDefaultStartDate() {
    const date = new Date();
    date.setDate(date.getDate() - 30);
    return date.toISOString().split('T')[0];
}

function getDefaultEndDate() {
    return new Date().toISOString().split('T')[0];
}

function updateOverviewStats(stats) {
    // Update main stat cards
    document.getElementById('totalUsers').textContent = stats.overview.users.reduce((sum, u) => sum + u.active_count, 0);
    document.getElementById('totalSessions').textContent = stats.overview.sessions.total_sessions || 0;
    document.getElementById('avgScore').textContent = stats.overview.grading.avg_score ? Math.round(stats.overview.grading.avg_score) : 0;
    document.getElementById('chatbotInteractions').textContent = stats.overview.chatbot.total_messages || 0;
    
    // Update grading stats
    if (document.getElementById('totalGraded')) {
        document.getElementById('totalGraded').textContent = stats.overview.grading.total_graded || 0;
        document.getElementById('avgGrade').textContent = stats.overview.grading.avg_score ? Math.round(stats.overview.grading.avg_score) : 0;
        document.getElementById('highPerformers').textContent = stats.overview.grading.high_performers || 0;
        document.getElementById('lowPerformers').textContent = stats.overview.grading.low_performers || 0;
    }
    
    // Update chatbot stats
    if (document.getElementById('totalMessages')) {
        document.getElementById('totalMessages').textContent = stats.overview.chatbot.total_messages || 0;
        document.getElementById('suggestionsGiven').textContent = stats.overview.chatbot.suggestions_given || 0;
        document.getElementById('suggestionsExecuted').textContent = stats.overview.chatbot.suggestions_executed || 0;
        
        const executionRate = stats.overview.chatbot.suggestions_given > 0 ? 
            Math.round((stats.overview.chatbot.suggestions_executed / stats.overview.chatbot.suggestions_given) * 100) : 0;
        document.getElementById('suggestionRate').textContent = executionRate + '%';
    }
    
    // Update login stats
    if (document.getElementById('uniqueUsers')) {
        document.getElementById('uniqueUsers').textContent = stats.overview.logins.unique_users || 0;
        document.getElementById('totalLogins').textContent = stats.overview.logins.total_logins || 0;
        document.getElementById('todayLogins').textContent = stats.overview.logins.today_logins || 0;
        document.getElementById('avgSessionLength').textContent = stats.overview.sessions.avg_duration ? 
            Math.round(stats.overview.sessions.avg_duration) + 'm' : '0m';
    }
}

function createAllCharts(charts) {
    // Destroy existing charts
    Object.values(window.charts || {}).forEach(chart => {
        if (chart && typeof chart.destroy === 'function') {
            chart.destroy();
        }
    });
    window.charts = {};
    
    // Create new charts
    createActivityChart(charts.activity);
    createCommandChart(charts.command_usage);
    createGradeDistributionChart(charts.grade_distribution);
    createDurationTrendsChart(charts.duration_trends);
    createPerformanceByRoleChart(charts.performance_by_role);
    createChatbotEffectivenessChart(charts.chatbot_effectiveness);
    createComprehensiveActivityChart(charts.activity);
    createCommandSuccessChart(charts.command_usage);
}

function createActivityChart(data) {
    console.log('createActivityChart called with:', data); // DEBUG
    const canvas = document.getElementById('activityChart');
    if (!canvas) {
        console.warn('Activity chart canvas not found');
        return;
    }
    
    // Destroy existing chart if it exists
    if (window.charts && window.charts.activity) {
        window.charts.activity.destroy();
    }
    
    // Ensure window.charts exists
    if (!window.charts) {
        window.charts = {};
    }
    
    const ctx = canvas.getContext('2d');
    window.charts.activity = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'Active Users',
                data: data.datasets?.active_users || data.active_users || [],
                borderColor: '#58a6ff',
                backgroundColor: 'rgba(88, 166, 255, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Sessions',
                data: data.datasets?.sessions || data.sessions || [],
                borderColor: '#a9a7ff',
                backgroundColor: 'rgba(169, 167, 255, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Commands',
                data: data.datasets?.commands || data.commands || [],
                borderColor: '#3fb950',
                backgroundColor: 'rgba(63, 185, 80, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { 
                        color: '#f0f6fc',
                        usePointStyle: true,
                        padding: 20
                    }
                }
            },
            scales: {
                x: {
                    type: 'category',
                    ticks: { 
                        color: '#8b949e',
                        maxRotation: 45
                    },
                    grid: { 
                        color: '#30363d',
                        display: true
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: { 
                        color: '#8b949e',
                        stepSize: 1
                    },
                    grid: { 
                        color: '#30363d',
                        display: true
                    }
                }
            },
            elements: {
                point: {
                    radius: 4,
                    hoverRadius: 6
                },
                line: {
                    borderWidth: 2
                }
            }
        }
    });
}

function createCommandChart(data) {
    const canvas = document.getElementById('commandChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    window.charts.commands = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || ['No Data'],
            datasets: [{
                label: 'Usage Count',
                data: data.usage || [0],
                backgroundColor: [
                    '#58a6ff', '#a9a7ff', '#3fb950', '#d29922', '#f85149',
                    '#17a2b8', '#8b949e', '#fd7e14', '#6f42c1', '#e83e8c'
                ],
                borderColor: '#21262d',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    ticks: { color: '#8b949e' },
                    grid: { color: '#30363d' }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: '#8b949e' },
                    grid: { color: '#30363d' }
                }
            }
        }
    });
}

function createGradeDistributionChart(data) {
    const canvas = document.getElementById('gradeChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    window.charts.grades = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels || ['No Data'],
            datasets: [{
                data: data.values || [0],
                backgroundColor: ['#3fb950', '#58a6ff', '#d29922', '#f85149', '#8b949e'],
                borderColor: '#21262d',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#f0f6fc', padding: 20 }
                }
            }
        }
    });
}

function createDurationTrendsChart(data) {
    const canvas = document.getElementById('durationChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    window.charts.duration = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'Average Duration (minutes)',
                data: data.values || [],
                borderColor: '#d29922',
                backgroundColor: 'rgba(210, 153, 34, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    labels: { color: '#f0f6fc' }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#8b949e' },
                    grid: { color: '#30363d' }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: '#8b949e' },
                    grid: { color: '#30363d' }
                }
            }
        }
    });
}

function createPerformanceByRoleChart(data) {
    const canvas = document.getElementById('performanceByRoleChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    window.charts.performance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || ['No Data'],
            datasets: [{
                label: 'Average Score',
                data: data.scores || [0],
                backgroundColor: '#58a6ff',
                borderColor: '#21262d',
                borderWidth: 1,
                yAxisID: 'y'
            }, {
                label: 'Average Rating',
                data: data.ratings || [0],
                backgroundColor: '#a9a7ff',
                borderColor: '#21262d',
                borderWidth: 1,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#f0f6fc' }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#8b949e' },
                    grid: { color: '#30363d' }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    max: 100,
                    ticks: { color: '#8b949e' },
                    grid: { color: '#30363d' }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    max: 5,
                    ticks: { color: '#8b949e' },
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
}

function createChatbotEffectivenessChart(data) {
    const canvas = document.getElementById('chatbotEffectivenessChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    window.charts.chatbot = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'Suggestions Given',
                data: data.suggestions || [],
                borderColor: '#58a6ff',
                backgroundColor: 'rgba(88, 166, 255, 0.1)',
                tension: 0.4
            }, {
                label: 'Suggestions Executed',
                data: data.executed || [],
                borderColor: '#3fb950',
                backgroundColor: 'rgba(63, 185, 80, 0.1)',
                tension: 0.4
            }, {
                label: 'Effectiveness %',
                data: data.effectiveness || [],
                borderColor: '#d29922',
                backgroundColor: 'rgba(210, 153, 34, 0.1)',
                tension: 0.4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#f0f6fc' }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#8b949e' },
                    grid: { color: '#30363d' }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: '#8b949e' },
                    grid: { color: '#30363d' }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    max: 100,
                    ticks: { color: '#8b949e' },
                    grid: { drawOnChartArea: false }
                }
            }
        }
    });
}

function createComprehensiveActivityChart(data) {
    const canvas = document.getElementById('comprehensiveActivityChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    window.charts.comprehensive = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || [],
            datasets: [{
                label: 'Active Users',
                data: data.datasets.active_users || [],
                backgroundColor: 'rgba(88, 166, 255, 0.8)',
                borderColor: '#58a6ff',
                borderWidth: 1
            }, {
                label: 'Sessions',
                data: data.datasets.sessions || [],
                backgroundColor: 'rgba(169, 167, 255, 0.8)',
                borderColor: '#a9a7ff',
                borderWidth: 1
            }, {
                label: 'Commands',
                data: data.datasets.commands || [],
                backgroundColor: 'rgba(63, 185, 80, 0.8)',
                borderColor: '#3fb950',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#f0f6fc' }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#8b949e' },
                    grid: { color: '#30363d' }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: '#8b949e' },
                    grid: { color: '#30363d' }
                }
            }
        }
    });
}

function createCommandSuccessChart(data) {
    const canvas = document.getElementById('commandSuccessChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    window.charts.commandSuccess = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || ['No Data'],
            datasets: [{
                label: 'Success Rate %',
                data: data.success_rates || [0],
                backgroundColor: data.success_rates ? data.success_rates.map(rate => 
                    rate >= 80 ? '#3fb950' : rate >= 60 ? '#d29922' : '#f85149'
                ) : ['#8b949e'],
                borderColor: '#21262d',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    ticks: { color: '#8b949e' },
                    grid: { color: '#30363d' }
                },
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { 
                        color: '#8b949e',
                        callback: function(value) {
                            return value + '%';
                        }
                    },
                    grid: { color: '#30363d' }
                }
            }
        }
    });
}

// Report tab management
function showReportTab(tabName, element) {
    // Remove active class from all tabs and content
    document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.report-tab-content').forEach(content => content.classList.remove('active'));
    
    // Add active class to clicked tab and corresponding content
    element.classList.add('active');
    document.getElementById(tabName + 'Report').classList.add('active');
    
    // Load tab-specific content
    loadTabContent(tabName);
}

async function loadTabContent(tabName) {
    switch (tabName) {
        case 'performance':
            await loadDetailedReport('user_performance');
            break;
        case 'grading':
            await loadDetailedReport('grading_analytics');
            break;
        case 'chatbot':
            await loadDetailedReport('chatbot_analytics');
            break;
    }
}

async function loadDetailedReports() {
    // Load all detailed reports in background
    await loadDetailedReport('user_performance');
    await loadDetailedReport('grading_analytics');
    await loadDetailedReport('chatbot_analytics');
}

async function loadDetailedReport(type) {
    try {
        const startDate = document.getElementById('reportStartDate').value || getDefaultStartDate();
        const endDate = document.getElementById('reportEndDate').value || getDefaultEndDate();
        
        const params = new URLSearchParams({
            type: type,
            start_date: startDate,
            end_date: endDate
        });
        
        const response = await fetch(`${API_BASE}?action=detailed_report&${params}`);
        const data = await response.json();
        
        if (data.success) {
            renderDetailedReportTable(type, data.data);
        }
    } catch (error) {
        console.error(`Error loading ${type} report:`, error);
    }
}

function renderDetailedReportTable(type, data) {
    let containerId, headers, rowRenderer;
    
    switch (type) {
        case 'user_performance':
            containerId = 'userPerformanceTable';
            headers = ['User', 'Role', 'Sessions', 'Graded', 'Avg Score', 'Avg Rating', 'Commands', 'Success Rate', 'Avg Duration'];
            rowRenderer = (row) => [
                row.full_name || 'Unknown',
                row.role || 'Unknown',
                row.total_sessions || 0,
                row.graded_sessions || 0,
                row.avg_score && !isNaN(parseFloat(row.avg_score)) ? Math.round(parseFloat(row.avg_score)) : '-',
                row.avg_rating && !isNaN(parseFloat(row.avg_rating)) ? parseFloat(row.avg_rating).toFixed(1) : '-',
                row.total_commands || 0,
                row.total_commands > 0 ? Math.round((row.successful_commands / row.total_commands) * 100) + '%' : '-',
                row.avg_session_duration && !isNaN(parseFloat(row.avg_session_duration)) ? Math.round(parseFloat(row.avg_session_duration)) + 'm' : '-'
            ];
            break;
            
        case 'grading_analytics':
            containerId = 'gradingAnalyticsTable';
            headers = ['Session', 'Student', 'Grader', 'Score', 'Rating', 'Commands', 'Session Date', 'Graded Date'];
            rowRenderer = (row) => [
                row.session_id || 'Unknown',
                row.student_name || 'Unknown',
                row.grader_name || 'Unknown',
                (row.overall_score || 0) + '/100',
                row.rating && !isNaN(parseInt(row.rating)) ? '★'.repeat(parseInt(row.rating)) + '☆'.repeat(5 - parseInt(row.rating)) : 'Not rated',
                row.commands_in_session || 0,
                formatDate(row.session_date),
                formatDate(row.graded_at)
            ];
            break;
            
        case 'chatbot_analytics':
            containerId = 'chatbotAnalyticsTable';
            headers = ['User', 'Questions', 'Suggestions Received', 'Suggestions Executed', 'Execution Rate', 'Avg Response Time', 'Helpful Ratings'];
            rowRenderer = (row) => [
                row.user_name,
                row.questions_asked || 0,
                row.suggestions_received || 0,
                row.suggestions_executed || 0,
                row.suggestions_received > 0 ? Math.round((row.suggestions_executed / row.suggestions_received) * 100) + '%' : '-',
                row.avg_response_time && !isNaN(parseFloat(row.avg_response_time)) ? parseFloat(row.avg_response_time).toFixed(2) + 's' : '-',
                (row.helpful_ratings || 0) + '/' + ((row.helpful_ratings || 0) + (row.unhelpful_ratings || 0))
            ];
            break;
    }
    
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const table = `
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr>
                </thead>
                <tbody>
                    ${data.map(row => `<tr>${rowRenderer(row).map(cell => `<td>${cell}</td>`).join('')}</tr>`).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = table;
}

// Utility functions
function refreshReports() {
    loadReports();
    showAlert('Reports updated!', 'success');
}

// Export functions for detailed reports
async function exportDetailedReport(type, format) {
    try {
        const startDate = document.getElementById('reportStartDate').value || getDefaultStartDate();
        const endDate = document.getElementById('reportEndDate').value || getDefaultEndDate();
        
        const params = new URLSearchParams({
            type: type,
            start_date: startDate,
            end_date: endDate
        });
        
        const response = await fetch(`${API_BASE}?action=detailed_report&${params}`);
        const data = await response.json();
        
        if (data.success) {
            if (format === 'excel') {
                exportToExcel(`${type}_report.xlsx`, data.data);
            } else if (format === 'pdf') {
                exportToPDF(`${type.replace('_', ' ').toUpperCase()} Report`, data.data);
            }
        }
    } catch (error) {
        console.error('Export error:', error);
        showAlert('Error exporting report', 'danger');
    }
}

function exportChart(chartType) {
    const chart = window.charts[chartType];
    if (chart) {
        const url = chart.toBase64Image();
        const link = document.createElement('a');
        link.download = `${chartType}_chart.png`;
        link.href = url;
        link.click();
        showAlert('Chart exported successfully!', 'success');
    }
}

function resetReportFilters() {
    document.getElementById('reportStartDate').value = getDefaultStartDate();
    document.getElementById('reportEndDate').value = getDefaultEndDate();
    document.getElementById('userRoleFilter').value = '';
    loadReports();
}

async function refreshDashboard() {
    await loadDashboard();
    showAlert('Dashboard refreshed!', 'success');
}

// System Logs
async function loadLogs() {
    try {
        const response = await fetch(`${API_BASE}?action=logs`);
        const data = await response.json();
        
        if (data.success) {
            currentData.logs = data.logs;
            renderLogsTable();
        }
    } catch (error) {
        console.error('Logs load error:', error);
        showAlert('Error loading logs', 'danger');
    }
}

function renderLogsTable() {
    const tbody = document.getElementById('logsTableBody');
    tbody.innerHTML = '';
    
    let filteredLogs = filterLogs();
    
    filteredLogs.forEach((log, index) => {
        const user = currentData.users.find(u => u.id === log.user_id);
        const userName = user ? user.full_name : (log.user_name || 'System');
        
        // Truncate details for display
        let displayDetails = log.action_details || '-';
        const isLong = displayDetails.length > 100;
        const truncatedDetails = isLong ? displayDetails.substring(0, 100) + '...' : displayDetails;
        
        const row = tbody.insertRow();
        row.innerHTML = `
            <td>${formatDate(log.timestamp)}</td>
            <td>${userName}</td>
            <td><span class="status-badge">${log.action_type}</span></td>
            <td>${log.ip_address || '-'}</td>
            <td>
                <span class="log-details ${isLong ? 'expandable' : ''}" onclick="${isLong ? `expandLogDetails(${index})` : ''}" data-full="${encodeURIComponent(displayDetails)}">
                    ${truncatedDetails}
                </span>
            </td>
        `;
    });

    if (filteredLogs.length === 0) {
        const row = tbody.insertRow();
        row.innerHTML = `<td colspan="5" style="text-align: center; color: var(--text-secondary); font-style: italic; padding: 40px;">No results found</td>`;
    }
}

function expandLogDetails(index) {
    const detailSpan = document.querySelector(`.log-details[onclick="expandLogDetails(${index})"]`);
    if (detailSpan) {
        const fullText = decodeURIComponent(detailSpan.dataset.full);
        
        // Create modal for full details
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'block';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 800px;">
                <div class="modal-header">
                    <h3>Log Details</h3>
                    <button class="close-btn" onclick="this.closest('.modal').remove()">&times;</button>
                </div>
                <div style="max-height: 400px; overflow-y: auto;">
                    <pre style="white-space: pre-wrap; word-break: break-word; background: var(--primary-dark); padding: 16px; border-radius: 8px; color: var(--text-primary);">${fullText}</pre>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
}

function filterLogs() {
    let filtered = currentData.logs;
    
    const typeFilter = document.getElementById('logTypeFilter')?.value || '';
    const dateFilter = document.getElementById('logDateFilter')?.value || '';
    
    if (typeFilter) {
        filtered = filtered.filter(log => log.action_type === typeFilter);
    }
    
    if (dateFilter) {
        filtered = filtered.filter(log => log.timestamp.startsWith(dateFilter));
    }
    
    return filtered.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
}

// Settings
async function loadSettings() {
    try {
        const response = await fetch(`${API_BASE}?action=settings`);
        const data = await response.json();
        
        if (data.success) {
            currentData.settings = data.settings;
            document.getElementById('sessionTimeout').value = data.settings.session_timeout || 3600;
            document.getElementById('maxCommandHistory').value = data.settings.max_command_history || 1000;
            document.getElementById('auditRetention').value = data.settings.audit_retention_days || 90;
            document.getElementById('maxConcurrentSessions').value = data.settings.max_concurrent_sessions || 10;
        }
    } catch (error) {
        console.error('Settings load error:', error);
        showAlert('Error loading settings', 'danger');
    }
}

async function saveSettings() {
    try {
        const response = await fetch(API_BASE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'save_settings',
                settings: {
                    session_timeout: parseInt(document.getElementById('sessionTimeout').value),
                    max_command_history: parseInt(document.getElementById('maxCommandHistory').value),
                    audit_retention_days: parseInt(document.getElementById('auditRetention').value),
                    max_concurrent_sessions: parseInt(document.getElementById('maxConcurrentSessions').value)
                }
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('Settings saved successfully!', 'success');
        } else {
            showAlert('Error saving settings: ' + data.message, 'danger');
        }
    } catch (error) {
        console.error('Save settings error:', error);
        showAlert('Error saving settings', 'danger');
    }
}

// Export Functions
function exportSessions(format) {
    const sessions = filterSessions();
    const data = sessions.map(session => {
        const user = currentData.users.find(u => u.id === session.user_id);
        return {
            'Session ID': session.session_id,
            'User': user ? user.full_name : 'Unknown',
            'Hostname': session.hostname,
            'IP Address': session.ip_address,
            'Start Time': session.start_time,
            'End Time': session.end_time || 'Active',
            'Duration': calculateDuration(session.start_time, session.end_time),
            'Commands': session.total_commands,
            'Status': session.status,
            'OS Info': session.os_info || ''
        };
    });

    if (format === 'pdf') {
        exportToPDF('Sessions Report', data);
    } else if (format === 'excel') {
        exportToExcel('sessions_report.xlsx', data);
    }
}

async function exportSessionDetail(sessionId, format) {
    try {
        const response = await fetch(`${API_BASE}?action=session_detail&session_id=${sessionId}`);
        const data = await response.json();
        
        if (data.success) {
            const session = data.session;
            // Ensure users data is loaded first
            if (!currentData.users || currentData.users.length === 0) {
                await loadUsers();
            }
            const user = currentData.users.find(u => parseInt(u.id) === parseInt(session.user_id));
            const commands = data.commands;
            const conversations = data.conversations || [];
            
            const exportData = {
                session: {
                    'Session ID': session.session_id,
                    'User': user ? user.full_name : 'Unknown',
                    'Hostname': session.hostname,
                    'IP Address': session.ip_address,
                    'Start Time': formatDate(session.start_time),
                    'End Time': session.end_time ? formatDate(session.end_time) : 'Still active',
                    'Status': session.status,
                    'OS Info': session.os_info || 'Not available'
                },
                commands: commands.map(cmd => ({
                    'Time': formatDate(cmd.timestamp),
                    'Command': cmd.command,
                    'Status': cmd.status,
                    'Execution Time': cmd.execution_time ? cmd.execution_time + 's' : 'N/A',
                    'Output': cmd.output || 'No output'
                })),
                conversations: conversations.map(conv => ({
                    'Time': formatDate(conv.timestamp),
                    'Type': conv.message_type === 'user' ? 'Student' : 'Bot',
                    'Message': conv.message
                }))
            };
            
            if (format === 'pdf') {
                exportSessionToPDF(sessionId, exportData);
            } else if (format === 'excel') {
                exportSessionToExcel(sessionId, exportData);
            }
        }
    } catch (error) {
        console.error('Export session detail error:', error);
        showAlert('Error exporting session details', 'danger');
    }
}

function exportLogs() {
    const logs = filterLogs();
    const data = logs.map(log => {
        const user = currentData.users.find(u => u.id === log.user_id);
        return {
            'Timestamp': log.timestamp,
            'User': user ? user.full_name : 'System',
            'Action': log.action_type,
            'IP Address': log.ip_address || '',
            'Details': log.action_details || ''
        };
    });

    exportToExcel('system_logs.xlsx', data);
}

function exportToPDF(title, data) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    doc.setFontSize(16);
    doc.text(title, 20, 20);
    doc.setFontSize(10);
    doc.text(`Generated on: ${new Date().toLocaleString()}`, 20, 30);
    
    let y = 50;
    const pageHeight = doc.internal.pageSize.height;
    
    if (data.length > 0) {
        const headers = Object.keys(data[0]);
        const colWidth = 180 / headers.length;
        
        // Headers
        headers.forEach((header, index) => {
            doc.text(header, 20 + (index * colWidth), y);
        });
        y += 10;
        
        // Data rows
        data.forEach(row => {
            if (y > pageHeight - 20) {
                doc.addPage();
                y = 20;
            }
            
            headers.forEach((header, index) => {
                const value = String(row[header] || '').substring(0, 20);
                doc.text(value, 20 + (index * colWidth), y);
            });
            y += 10;
        });
    }
    
    doc.save(`${title.toLowerCase().replace(/\s+/g, '_')}.pdf`);
    showAlert('PDF exported successfully!', 'success');
}

function exportSessionToPDF(sessionId, data) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    doc.setFontSize(16);
    doc.text(`Session Report: ${sessionId}`, 20, 20);
    doc.setFontSize(10);
    doc.text(`Generated on: ${new Date().toLocaleString()}`, 20, 30);
    
    let y = 50;
    
    // Session Info
    doc.setFontSize(14);
    doc.text('Session Information', 20, y);
    y += 10;
    doc.setFontSize(10);
    
    Object.entries(data.session).forEach(([key, value]) => {
        doc.text(`${key}: ${value}`, 20, y);
        y += 8;
    });
    
    y += 10;
    
    // Commands
    doc.setFontSize(14);
    doc.text('Commands Executed', 20, y);
    y += 10;
    doc.setFontSize(8);
    
    data.commands.forEach(cmd => {
        if (y > 270) {
            doc.addPage();
            y = 20;
        }
        
        doc.text(`${cmd.Time}: ${cmd.Command}`, 20, y);
        y += 6;
        doc.text(`Status: ${cmd.Status}, Time: ${cmd['Execution Time']}`, 25, y);
        y += 6;
        if (cmd.Output && cmd.Output !== 'No output') {
            const output = cmd.Output.substring(0, 100) + (cmd.Output.length > 100 ? '...' : '');
            doc.text(`Output: ${output}`, 25, y);
            y += 6;
        }
        y += 4;
    });
    
    doc.save(`session_${sessionId}_report.pdf`);
    showAlert('Session PDF exported successfully!', 'success');
}

function exportToExcel(filename, data) {
    const ws = XLSX.utils.json_to_sheet(data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Data');
    XLSX.writeFile(wb, filename);
    showAlert('Excel file exported successfully!', 'success');
}

function exportSessionToExcel(sessionId, data) {
    const wb = XLSX.utils.book_new();
    
    // Session info sheet
    const sessionWs = XLSX.utils.json_to_sheet([data.session]);
    XLSX.utils.book_append_sheet(wb, sessionWs, 'Session Info');
    
    // Commands sheet
    if (data.commands.length > 0) {
        const commandsWs = XLSX.utils.json_to_sheet(data.commands);
        XLSX.utils.book_append_sheet(wb, commandsWs, 'Commands');
    }
    
    // Conversations sheet
    if (data.conversations.length > 0) {
        const conversationsWs = XLSX.utils.json_to_sheet(data.conversations);
        XLSX.utils.book_append_sheet(wb, conversationsWs, 'Conversations');
    }
    
    XLSX.writeFile(wb, `session_${sessionId}_report.xlsx`);
    showAlert('Session Excel file exported successfully!', 'success');
}

// Utility Functions
function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString();
}

function calculateDuration(startTime, endTime) {
    if (!endTime) return 'Active';
    const duration = new Date(endTime) - new Date(startTime);
    const minutes = Math.floor(duration / 60000);
    const hours = Math.floor(minutes / 60);
    
    if (hours > 0) {
        return `${hours}h ${minutes % 60}m`;
    }
    return `${minutes}m`;
}

function sortTable(tableType, columnIndex) {
    const tableId = tableType + 'Table';
    const table = document.getElementById(tableId);
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    const sortKey = `${tableType}_${columnIndex}`;
    const ascending = !sortDirection[sortKey];
    sortDirection[sortKey] = ascending;
    
    rows.sort((a, b) => {
        const aVal = a.cells[columnIndex].textContent.trim();
        const bVal = b.cells[columnIndex].textContent.trim();
        
        // Try to parse as numbers first
        const aNum = parseFloat(aVal);
        const bNum = parseFloat(bVal);
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return ascending ? aNum - bNum : bNum - aNum;
        }
        
        // Sort as strings
        return ascending ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    });
    
    tbody.innerHTML = '';
    rows.forEach(row => tbody.appendChild(row));
    
    // Update sort indicators
    table.querySelectorAll('th').forEach((th, index) => {
        th.classList.remove('sort-asc', 'sort-desc');
        if (index === columnIndex) {
            th.classList.add(ascending ? 'sort-asc' : 'sort-desc');
        }
    });
}

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; font-size: 18px; padding: 0; margin-left: 10px;">&times;</button>
    `;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.minWidth = '300px';
    alertDiv.style.maxWidth = '500px';
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}

function showConfirmModal(message, onConfirm) {
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmButton').onclick = () => {
        onConfirm();
        closeConfirmModal();
    };
    document.getElementById('confirmModal').style.display = 'block';
}

function closeConfirmModal() {
    document.getElementById('confirmModal').setAttribute('style', 'display:none !important');
}

// Event Listeners
document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    login();
});

// Add event listeners for star hover effect
document.addEventListener('mouseover', function(e) {
    if (e.target.classList.contains('star')) {
        const stars = e.target.parentElement.querySelectorAll('.star');
        const hoverIndex = Array.from(stars).indexOf(e.target);
        stars.forEach((star, index) => {
            star.style.color = index <= hoverIndex ? 'var(--accent-yellow)' : 'var(--text-secondary)';
        });
    }
});

document.addEventListener('mouseout', function(e) {
    if (e.target.classList.contains('star')) {
        const stars = e.target.parentElement.querySelectorAll('.star');
        stars.forEach((star, index) => {
            star.style.color = star.classList.contains('active') ? 'var(--accent-yellow)' : 'var(--text-secondary)';
        });
    }
});

document.getElementById('userForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const userData = {
        action: currentEditingUser ? 'update_user' : 'create_user',
        user_id: currentEditingUser,
        username: document.getElementById('userUsername').value,
        full_name: document.getElementById('userFullName').value,
        email: document.getElementById('userEmail').value,
        role: document.getElementById('userRole').value,
        manager_id: document.getElementById('userManager').value || null,
        is_active: parseInt(document.getElementById('userStatus').value),
        password: document.getElementById('userPassword').value,
        created_by: currentUser ? currentUser.id : null
    };
    
    try {
        const response = await fetch(API_BASE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(userData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeUserModal();
            await loadUsers();
            showAlert(currentEditingUser ? 'User updated successfully!' : 'User created successfully!', 'success');
        } else {
            showAlert('Error saving user: ' + data.message, 'danger');
        }
    } catch (error) {
        console.error('Save user error:', error);
        showAlert('Error saving user', 'danger');
    }
});

// Search and filter event listeners
document.addEventListener('input', function(e) {
    if (e.target.id === 'userSearch') {
        renderUsersTable();
    } else if (e.target.id === 'sessionSearch') {
        renderSessionsTable();
    }
});

document.addEventListener('change', function(e) {
    if (e.target.id === 'roleFilter' || e.target.id === 'statusFilter') {
        renderUsersTable();
    } else if (e.target.id.includes('sessionUserFilter') || e.target.id.includes('sessionStatusFilter') || e.target.id.includes('sessionDateFilter')) {
        renderSessionsTable();
    } else if (e.target.id === 'gradingUserFilter') {
        loadUserSessions();
        document.getElementById('gradingContent').innerHTML = '<p>Select a session to begin grading.</p>';
    } else if (e.target.id === 'gradingSessionFilter') {
        loadGradingContent(e.target.value);
    } else if (e.target.id === 'logTypeFilter' || e.target.id === 'logDateFilter') {
        renderLogsTable();
    }
});

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    const modals = ['userModal', 'sessionModal', 'confirmModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (e.target === modal) {
            modal.setAttribute('style', 'display:none !important');
        }
    });
});

// Initialize application
document.addEventListener('DOMContentLoaded', function() {
    // Check if user is already logged in
    const storedUser = getStoredUser();
    if (storedUser) {
        currentUser = storedUser;
        document.getElementById('loginScreen').setAttribute('style', 'display:none !important');
        document.getElementById('mainApp').style.display = 'block';
        document.getElementById('currentUser').textContent = `Welcome, ${storedUser.full_name}`;
        
        // Set up role-based access FIRST
        setupRoleBasedAccess(storedUser.role);
        
        // Then restore active tab (will be filtered by permissions)
        const activeTab = localStorage.getItem('activeTab') || 'dashboard';
        if (hasPermission(activeTab)) {
            showSection(activeTab);
        } else {
            // Fallback to dashboard if stored tab is not accessible
            showSection('dashboard');
        }

        if (document.getElementById('reportStartDate')) {
            document.getElementById('reportStartDate').value = getDefaultStartDate();
            document.getElementById('reportEndDate').value = getDefaultEndDate();
        }

    } else {
        // Show login screen initially
        document.getElementById('loginScreen').style.display = 'block';
        document.getElementById('mainApp').setAttribute('style', 'display:none !important');
    }
});

// Session expiry check
setInterval(() => {
    const expires = localStorage.getItem('session_expires');
    if (expires && Date.now() >= parseInt(expires)) {
        showAlert('Your session has expired. Please log in again.', 'warning');
        logout();
    }
}, 60000); // Check every minute

function createStatusChart(data) {
    const canvas = document.getElementById('statusChart');
    if (!canvas) {
        console.warn('Status chart canvas not found');
        return;
    }
    
    // Ensure window.charts exists
    if (!window.charts) {
        window.charts = {};
    }
    
    // Destroy existing chart if it exists
    if (window.charts.status) {
        window.charts.status.destroy();
    }
    
    const ctx = canvas.getContext('2d');
    window.charts.status = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels || ['No Data'],
            datasets: [{
                data: data.values || [0],
                backgroundColor: ['#3fb950', '#d29922', '#f85149', '#8b949e'],
                borderColor: '#21262d',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#f0f6fc',
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        }
    });
}

// Add resize handler for charts
function handleChartResize() {
    Object.values(window.charts || {}).forEach(chart => {
        if (chart && typeof chart.resize === 'function') {
            chart.resize();
        }
    });
}

// Add resize event listener
window.addEventListener('resize', handleChartResize);

// User Management
async function loadUsers() {
    // Don't check permissions here - let role-based filtering handle access
    // This function is called by other sections that operators need access to
    
    try {
        const response = await fetch(`${API_BASE}?action=users`);
        const data = await response.json();
        
        if (data.success) {
            // Filter users based on permissions
            currentData.users = filterDataByPermissions(data.users, 'users');
            
            // Only render the users table if we're actually on the users section
            if (document.getElementById('users').classList.contains('active')) {
                renderUsersTable();
            }
        }
    } catch (error) {
        console.error('Users load error:', error);
        showAlert('Error loading users', 'danger');
    }
}

function renderUsersTable() {
    const tbody = document.getElementById('usersTableBody');
    tbody.innerHTML = '';
    
    let filteredUsers = filterUsers();
    
    filteredUsers.forEach(user => {
        // Convert is_active to boolean for consistent checking
        const isActive = user.is_active == 1 || user.is_active === true || user.is_active === 'true';
        const managerInfo = user.manager_name ? `${user.manager_name} (${user.manager_role})` : 'No Manager';
        
        const row = tbody.insertRow();
        row.innerHTML = `
            <td>${user.id}</td>
            <td>${user.username}</td>
            <td>${user.full_name}</td>
            <td>${user.email}</td>
            <td><span class="status-badge">${user.role}</span></td>
            <td>${managerInfo}</td>
            <td><span class="status-badge ${isActive ? 'status-active' : 'status-inactive'}">${isActive ? 'Active' : 'Inactive'}</span></td>
            <td>${formatDate(user.last_login)}</td>
            <td>
                <button class="btn btn-secondary btn-small" onclick="editUser('${user.id}')">Edit</button>
                ${isActive ? 
                    `<button class="btn btn-danger btn-small" onclick="toggleUserStatus(${user.id}, 0)">Disable</button>` :
                    `<button class="btn btn-success btn-small" onclick="toggleUserStatus(${user.id}, 1)">Enable</button>`
                }
                <button class="btn btn-danger btn-small" onclick="deleteUser(${user.id})">Delete</button>
                <button class="btn btn-primary btn-small" onclick="resetPassword(${user.id})">Reset Password</button>
            </td>
        `;
    });
    
    // Add no results message if needed
    if (filteredUsers.length === 0) {
        const row = tbody.insertRow();
        row.innerHTML = `<td colspan="9" style="text-align: center; color: var(--text-secondary); font-style: italic; padding: 40px;">No results found</td>`;
    }
}

async function toggleUserStatus(userId, newStatus) {
    try {
        const response = await fetch(API_BASE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'toggle_user_status',
                user_id: userId,
                is_active: newStatus
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update the user status in the local data with proper type conversion
            const userIndex = currentData.users.findIndex(u => u.id == userId);
            if (userIndex !== -1) {
                currentData.users[userIndex].is_active = parseInt(newStatus); // Ensure it's stored as integer
            }
            
            // Re-render the table to show updated status
            renderUsersTable();
            
            showAlert(`User ${newStatus ? 'enabled' : 'disabled'} successfully!`, 'success');
        } else {
            showAlert('Error updating user status: ' + data.message, 'danger');
        }
    } catch (error) {
        console.error('Toggle user status error:', error);
        showAlert('Error updating user status', 'danger');
    }
}

function filterUsers() {
    let filtered = currentData.users;
    
    const search = document.getElementById('userSearch')?.value.toLowerCase() || '';
    const roleFilter = document.getElementById('roleFilter')?.value || '';
    const statusFilter = document.getElementById('statusFilter')?.value || '';
    
    if (search) {
        filtered = filtered.filter(user => 
            user.username.toLowerCase().includes(search) ||
            user.full_name.toLowerCase().includes(search) ||
            user.email.toLowerCase().includes(search)
        );
    }
    
    if (roleFilter) {
        filtered = filtered.filter(user => user.role === roleFilter);
    }
    
    if (statusFilter !== '') {
        filtered = filtered.filter(user => user.is_active == statusFilter);
    }
    
    return filtered;
}

async function loadManagersList() {
    try {
        const response = await fetch(`${API_BASE}?action=managers_list`);
        const data = await response.json();
        
        if (data.success) {
            const managerSelect = document.getElementById('userManager');
            managerSelect.innerHTML = '<option value="">No Manager</option>';
            
            data.managers.forEach(manager => {
                const option = document.createElement('option');
                option.value = manager.id;
                option.textContent = `${manager.full_name} (${manager.role})`;
                managerSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Load managers error:', error);
    }
}

async function showUserModal(userId = null) {
    currentEditingUser = userId;
    const modal = document.getElementById('userModal');
    const title = document.getElementById('userModalTitle');
    
    // Load managers list
    await loadManagersList();
    
    if (userId) {
        const user = currentData.users.find(u => u.id === userId);
        if (!user) {
            showAlert('User not found', 'danger');
            return;
        }
        
        title.textContent = 'Edit User';
        document.getElementById('userUsername').value = user.username;
        document.getElementById('userFullName').value = user.full_name;
        document.getElementById('userEmail').value = user.email;
        document.getElementById('userRole').value = user.role;
        document.getElementById('userManager').value = user.manager_id || '';
        document.getElementById('userStatus').value = user.is_active;
        document.getElementById('userPassword').value = '';
        document.getElementById('userPassword').placeholder = 'Leave blank to keep current password';
    } else {
        title.textContent = 'Add New User';
        document.getElementById('userForm').reset();
        document.getElementById('userPassword').placeholder = 'Enter password';
        document.getElementById('userManager').value = '';
    }
    
    modal.style.display = 'block';
}

function closeUserModal() {
    document.getElementById('userModal').setAttribute('style', 'display:none !important');
    currentEditingUser = null;
}

function editUser(userId) {
    // Convert userId to number for comparison
    const userIdNum = parseInt(userId);
    const user = currentData.users.find(u => parseInt(u.id) === userIdNum);
    
    if (!user) {
        showAlert('User not found', 'danger');
        console.error('User not found. Available users:', currentData.users.map(u => ({id: u.id, username: u.username})));
        return;
    }
    
    showUserModal(userIdNum);
}

async function deleteUser(userId) {
    const user = currentData.users.find(u => u.id === userId);
    showConfirmModal(
        `Are you sure you want to delete user "${user.full_name}"? This action cannot be undone.`,
        async () => {
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete_user',
                        user_id: userId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    await loadUsers();
                    showAlert('User deleted successfully!', 'success');
                } else {
                    showAlert('Error deleting user: ' + data.message, 'danger');
                }
            } catch (error) {
                console.error('Delete user error:', error);
                showAlert('Error deleting user', 'danger');
            }
        }
    );
}

async function resetPassword(userId) {
    const user = currentData.users.find(u => u.id === userId);
    
    // Create custom password input modal
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'block';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reset Password for ${user.full_name}</h3>
                <button class="close-btn" onclick="this.remove()">&times;</button>
            </div>
            <form id="resetPasswordForm">
                <div class="form-group">
                    <label class="form-label">New Password:</label>
                    <input type="password" id="newPassword" class="form-input" required minlength="8">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password:</label>
                    <input type="password" id="confirmPassword" class="form-input" required>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    `;
    
    modal.querySelector('#resetPasswordForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        if (newPassword !== confirmPassword) {
            showAlert('Passwords do not match', 'danger');
            return;
        }
        
        try {
            const response = await fetch(API_BASE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'reset_password',
                    user_id: userId,
                    new_password: newPassword
                })
            });
            
            const data = await response.json();
            if (data.success) {
                showAlert('Password reset successfully!', 'success');
                modal.remove();
            } else {
                showAlert('Error resetting password: ' + data.message, 'danger');
            }
        } catch (error) {
            showAlert('Error resetting password', 'danger');
        }
    });
    
    document.body.appendChild(modal);
}

// Session Management
async function loadSessions() {
    if (!hasPermission('sessions')) {
        showAlert('Access denied', 'danger');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}?action=sessions`);
        const data = await response.json();
        
        if (data.success) {
            // Filter sessions based on permissions
            currentData.sessions = filterDataByPermissions(data.sessions, 'sessions');
            populateSessionFilters();
            renderSessionsTable();
        }
    } catch (error) {
        console.error('Sessions load error:', error);
        showAlert('Error loading sessions', 'danger');
    }
}

function populateSessionFilters() {
    const userFilter = document.getElementById('sessionUserFilter');
    userFilter.innerHTML = '<option value="">All Users</option>';
    
    currentData.users.forEach(user => {
        const option = document.createElement('option');
        option.value = user.id;
        option.textContent = user.full_name;
        userFilter.appendChild(option);
    });
}

function renderSessionsTable() {
    const tbody = document.getElementById('sessionsTableBody');
    tbody.innerHTML = '';
    
    let filteredSessions = filterSessions();
    
    filteredSessions.forEach(session => {
        // Ensure we have users data loaded and find the correct user
        let user = currentData.users.find(u => parseInt(u.id) === parseInt(session.user_id));
        
        // If user not found in current data, try to get it from the session data itself
        if (!user && session.user_name) {
            user = { full_name: session.user_name };
        }
        
        const userName = user ? user.full_name : `Unknown User (ID: ${session.user_id})`;
        const duration = calculateDuration(session.start_time, session.end_time);
        const hasGrade = session.has_feedback === 1 || session.has_feedback === true;
        
        // Determine what buttons to show based on permissions
        const canView = canViewSession(session.session_id);
        const canGrade = canGradeSession(session.session_id);
        const canDelete = canDeleteSession(session.session_id);
        
        let actionButtons = '';
        
        if (canView) {
            actionButtons += `<button class="btn btn-secondary btn-small" onclick="viewSessionDetailPage('${session.session_id}')">View</button>`;
            
            if (hasGrade) {
                actionButtons += `<button class="btn btn-primary btn-small" onclick="viewGradePage('${session.session_id}')">View Grade</button>`;
            }
        }
        
        if (canGrade && !hasGrade) {
            actionButtons += `<button class="btn btn-primary btn-small" onclick="gradeSession('${session.session_id}')">Grade</button>`;
        } else if (canGrade && hasGrade) {
            actionButtons += `<button class="btn btn-warning btn-small" onclick="gradeSession('${session.session_id}')">Edit Grade</button>`;
        }
        
        if (canDelete) {
            actionButtons += `<button class="btn btn-danger btn-small" onclick="deleteSession('${session.session_id}')">Delete</button>`;
        }
        
        if (!actionButtons) {
            actionButtons = '<span style="color: var(--text-secondary); font-style: italic;">No actions available</span>';
        }
        
        const row = tbody.insertRow();
        row.innerHTML = `
            <td>${session.session_id}</td>
            <td>${userName}</td>
            <td>${session.hostname}</td>
            <td>${formatDate(session.start_time)}</td>
            <td>${duration}</td>
            <td>${session.total_commands}</td>
            <td><span class="status-badge status-${session.status}">${session.status}</span></td>
            <td>${hasGrade ? '<span class="status-badge status-graded">Graded</span>' : '<span class="status-badge status-ungraded">Ungraded</span>'}</td>
            <td>${actionButtons}</td>
        `;
    });

    if (filteredSessions.length === 0) {
        const row = tbody.insertRow();
        row.innerHTML = `<td colspan="9" style="text-align: center; color: var(--text-secondary); font-style: italic; padding: 40px;">No results found</td>`;
    }
}

function filterSessions() {
    let filtered = currentData.sessions;
    
    const search = document.getElementById('sessionSearch')?.value.toLowerCase() || '';
    const userFilter = document.getElementById('sessionUserFilter')?.value || '';
    const statusFilter = document.getElementById('sessionStatusFilter')?.value || '';
    const dateFilter = document.getElementById('sessionDateFilter')?.value || '';
    
    if (search) {
        filtered = filtered.filter(session => 
            session.session_id.toLowerCase().includes(search) ||
            session.hostname.toLowerCase().includes(search)
        );
    }
    
    if (userFilter) {
        filtered = filtered.filter(session => session.user_id == userFilter);
    }
    
    if (statusFilter) {
        filtered = filtered.filter(session => session.status === statusFilter);
    }
    
    if (dateFilter) {
        filtered = filtered.filter(session => session.start_time.startsWith(dateFilter));
    }
    
    return filtered;
}

async function viewSessionDetail(sessionId) {
    try {
        const response = await fetch(`${API_BASE}?action=session_detail&session_id=${sessionId}`);
        const data = await response.json();
        
        if (data.success) {
            const session = data.session;
            // Ensure users data is loaded first
            if (!currentData.users || currentData.users.length === 0) {
                await loadUsers();
            }
            const user = currentData.users.find(u => parseInt(u.id) === parseInt(session.user_id));
            const commands = data.commands;
            const feedback = data.feedback;
            const conversations = data.conversations || [];
            
            const content = `
                <div class="session-info">
                    <h4>Session Information</h4>
                    <p><strong>Session ID:</strong> ${session.session_id}</p>
                    <p><strong>User:</strong> ${user ? user.full_name : 'Unknown'}</p>
                    <p><strong>Hostname:</strong> ${session.hostname}</p>
                    <p><strong>IP Address:</strong> ${session.ip_address}</p>
                    <p><strong>Start Time:</strong> ${formatDate(session.start_time)}</p>
                    <p><strong>End Time:</strong> ${session.end_time ? formatDate(session.end_time) : 'Still active'}</p>
                    <p><strong>Status:</strong> ${session.status}</p>
                    <p><strong>OS Info:</strong> ${session.os_info || 'Not available'}</p>
                </div>
                
                <div class="commands-section">
                    <h4>Commands Executed (${commands.length})</h4>
                    <div class="export-options">
                        <button class="btn btn-secondary btn-small" onclick="exportSessionDetail('${sessionId}', 'pdf')">Export PDF</button>
                        <button class="btn btn-secondary btn-small" onclick="exportSessionDetail('${sessionId}', 'excel')">Export Excel</button>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Command</th>
                                    <th>Status</th>
                                    <th>Execution Time</th>
                                    <th>Output</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${commands.map((cmd, index) => `
                                    <tr>
                                        <td>${formatDate(cmd.timestamp)}</td>
                                        <td><code>${cmd.command}</code></td>
                                        <td><span class="status-badge status-${cmd.status}">${cmd.status}</span></td>
                                        <td>${cmd.execution_time ? cmd.execution_time + 's' : 'N/A'}</td>
                                        <td>
                                            ${cmd.output ? 
                                                `<div class="command-output collapsed" id="output-${index}" onclick="toggleOutput(${index})">
                                                    ${cmd.output}
                                                </div>` : 
                                                'No output'
                                            }
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
                
                ${conversations.length > 0 ? `
                <div class="commands-section">
                    <h4>Chatbot Conversations</h4>
                    <div class="timeline">
                        ${conversations.map(conv => `
                            <div class="chat-message ${conv.message_type}">
                                <strong>${conv.message_type === 'user' ? 'User' : 'Bot'}:</strong>
                                <p>${conv.message}</p>
                                <small>${formatDate(conv.timestamp)}</small>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
                
                ${feedback ? `
                <div class="feedback-display">
                    <h4>Grading & Feedback</h4>
                    <p><strong>Overall Score:</strong> ${feedback.overall_score}/100</p>
                    <p><strong>Rating:</strong> ${'★'.repeat(feedback.rating)}${'☆'.repeat(5-feedback.rating)}</p>
                    <p><strong>Instructor Feedback:</strong> ${feedback.instructor_feedback}</p>
                    <p><strong>Graded by:</strong> ${currentData.users.find(u => u.id === feedback.graded_by)?.full_name || 'Unknown'}</p>
                    <p><strong>Graded on:</strong> ${formatDate(feedback.graded_at)}</p>
                </div>
                ` : '<p><em>No grading available for this session.</em></p>'}
            `;
            
            document.getElementById('sessionDetailContent').innerHTML = content;
            document.getElementById('sessionModal').style.display = 'block';
        }
    } catch (error) {
        console.error('Session detail error:', error);
        showAlert('Error loading session details', 'danger');
    }
}

function toggleOutput(index) {
    const outputDiv = document.getElementById(`output-${index}`);
    if (outputDiv) {
        outputDiv.classList.toggle('collapsed');
    }
}

function closeSessionModal() {
    document.getElementById('sessionModal').setAttribute('style', 'display:none !important');
}

async function deleteSession(sessionId) {
    showConfirmModal(
        `Are you sure you want to delete session "${sessionId}"? This will also delete all associated commands and feedback. This action cannot be undone.`,
        async () => {
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete_session',
                        session_id: sessionId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    await loadSessions();
                    showAlert('Session deleted successfully!', 'success');
                } else {
                    showAlert('Error deleting session: ' + data.message, 'danger');
                }
            } catch (error) {
                console.error('Delete session error:', error);
                showAlert('Error deleting session', 'danger');
            }
        }
    );
}

// Feedback and Grading
async function loadFeedback() {
    if (!hasPermission('feedback')) {
        showAlert('Access denied', 'danger');
        return;
    }
    
    await loadUsers(); // Ensure users are loaded for the dropdowns
    populateGradingFilters();
    
    // For operators, auto-select their own user and load their sessions
    if (currentUser && currentUser.role === 'operator') {
        document.getElementById('gradingUserFilter').value = currentUser.id;
        document.getElementById('gradingUserFilter').disabled = true; // Disable since they can only see their own
        await loadUserSessions();
        
        // Show message about viewing own grades
        document.getElementById('gradingContent').innerHTML = `
            <div class="operator-feedback-info">
                <div class="info-card">
                    <h4>📚 Your Learning Progress</h4>
                    <p>Here you can view your session grades and feedback from instructors.</p>
                    <p>Select a session from the dropdown above to view detailed feedback.</p>
                </div>
            </div>
        `;
    }
}

function populateGradingFilters() {
    const userFilter = document.getElementById('gradingUserFilter');
    userFilter.innerHTML = '<option value="">Select User</option>';
    
    let usersToShow = [];
    
    if (currentUser.role === 'operator') {
        // Operators can only see themselves
        usersToShow = [currentUser];
    } else if (currentUser.role === 'manager') {
        // Managers see operators and themselves
        usersToShow = currentData.users.filter(u => u.role === 'operator' || u.id == currentUser.id);
    } else {
        // Admins see everyone (except other admins for grading purposes)
        usersToShow = currentData.users.filter(u => u.role === 'operator' || u.role === 'manager');
    }
    
    usersToShow.forEach(user => {
        const option = document.createElement('option');
        option.value = user.id;
        option.textContent = user.full_name + (user.id == currentUser.id ? ' (You)' : '');
        userFilter.appendChild(option);
    });
}

function gradeSession(sessionId) {
    // Check if user can grade this session
    if (!canGradeSession(sessionId)) {
        showAlert('Access denied: You cannot grade sessions', 'danger');
        return;
    }
    
    // Store the session ID for potential use after saving
    window.currentGradingSessionId = sessionId;
    
    showSection('feedback');
    
    const session = currentData.sessions.find(s => s.session_id === sessionId);
    if (!session) return;
    
    document.getElementById('gradingUserFilter').value = session.user_id;
    loadUserSessions();
    document.getElementById('gradingSessionFilter').value = sessionId;
    loadGradingContent(sessionId);
}

async function loadUserSessions() {
    const userId = document.getElementById('gradingUserFilter').value;
    const sessionFilter = document.getElementById('gradingSessionFilter');
    
    sessionFilter.innerHTML = '<option value="">Select Session</option>';
    
    if (userId) {
        try {
            const response = await fetch(`${API_BASE}?action=user_sessions&user_id=${userId}`);
            const data = await response.json();
            
            if (data.success) {
                data.sessions.forEach(session => {
                    const option = document.createElement('option');
                    option.value = session.session_id;
                    option.textContent = `${session.session_id} - ${formatDate(session.start_time)}`;
                    sessionFilter.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Load user sessions error:', error);
        }
    }
}

async function loadGradingContent(sessionId) {
    if (!sessionId) {
        if (currentUser.role === 'operator') {
            document.getElementById('gradingContent').innerHTML = `
                <div class="operator-feedback-info">
                    <div class="info-card">
                        <h4>📚 Your Learning Progress</h4>
                        <p>Select a session from the dropdown above to view your grades and feedback.</p>
                    </div>
                </div>
            `;
        } else {
            document.getElementById('gradingContent').innerHTML = '<p>Select a user and session to begin grading.</p>';
        }
        return;
    }
    
    // Check if operator is trying to access their own session
    if (currentUser.role === 'operator') {
        const response = await fetch(`${API_BASE}?action=session_detail&session_id=${sessionId}`);
        const data = await response.json();
        if (data.success && data.session.user_id != currentUser.id) {
            showAlert('Access denied: You can only view your own sessions', 'danger');
            return;
        }
    }
    
    // Rest of the existing loadGradingContent function...
    try {
        const response = await fetch(`${API_BASE}?action=grading_data&session_id=${sessionId}`);
        const data = await response.json();
        
        if (data.success) {
            const session = data.session;
            // Ensure users data is loaded first
            if (!currentData.users || currentData.users.length === 0) {
                await loadUsers();
            }
            const user = currentData.users.find(u => parseInt(u.id) === parseInt(session.user_id));
            const commands = data.commands;
            const conversations = data.conversations || [];
            const existingFeedback = data.feedback;
            
            // Create timeline as before...
            const timeline = [];
            
            commands.forEach(cmd => {
                timeline.push({
                    type: 'command',
                    timestamp: cmd.timestamp,
                    data: cmd
                });
            });
            
            conversations.forEach(conv => {
                timeline.push({
                    type: 'conversation',
                    timestamp: conv.timestamp,
                    data: conv
                });
            });
            
            timeline.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
            
            const isOperatorViewing = currentUser.role === 'operator';
            const canGrade = !isOperatorViewing; // Only non-operators can grade
            
            const content = `
                <div class="grading-form">
                    <h3>${isOperatorViewing ? 'Viewing' : 'Grading'} Session: ${sessionId}</h3>
                    <p><strong>${isOperatorViewing ? 'Your Session' : 'Student'}:</strong> ${user ? user.full_name : 'Unknown'}</p>
                    <p><strong>Session Date:</strong> ${formatDate(session.start_time)}</p>
                    <p><strong>Commands Executed:</strong> ${commands.length}</p>
                    <p><strong>Chat Interactions:</strong> ${conversations.length}</p>
                    
                    ${existingFeedback && isOperatorViewing ? `
                        <div class="feedback-display">
                            <h4>📊 Your Grade</h4>
                            <div class="grade-summary">
                                <div class="grade-item">
                                    <span class="grade-label">Overall Score:</span>
                                    <span class="grade-value">${existingFeedback.overall_score}/100</span>
                                </div>
                                <div class="grade-item">
                                    <span class="grade-label">Rating:</span>
                                    <span class="grade-value">${'★'.repeat(existingFeedback.rating)}${'☆'.repeat(5-existingFeedback.rating)}</span>
                                </div>
                                <div class="grade-item">
                                    <span class="grade-label">Instructor Feedback:</span>
                                    <span class="grade-value">${existingFeedback.instructor_feedback}</span>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                    
                    <div class="grading-timeline">
                        <h4>${isOperatorViewing ? 'Your Session Timeline' : 'Session Timeline (Commands & Conversations)'}</h4>
                        ${timeline.map(item => {
                            if (item.type === 'command') {
                                return `
                                    <div class="grading-timeline-item command-item">
                                        <div class="timeline-time">${formatDate(item.timestamp)}</div>
                                        <div class="timeline-content">
                                            <h5>Command: <code>${item.data.command}</code></h5>
                                            <p><strong>Status:</strong> <span class="status-badge status-${item.data.status}">${item.data.status}</span></p>
                                            <p><strong>Execution Time:</strong> ${item.data.execution_time ? item.data.execution_time + 's' : 'N/A'}</p>
                                            ${item.data.output ? `
                                                <div class="command-output-full">
                                                    <strong>Output:</strong>
                                                    <pre>${item.data.output}</pre>
                                                </div>
                                            ` : ''}
                                            ${existingFeedback?.command_feedback?.[item.data.command] ? `
                                                <div class="instructor-feedback">
                                                    <strong>💬 Instructor Feedback:</strong>
                                                    <p>${existingFeedback.command_feedback[item.data.command]}</p>
                                                </div>
                                            ` : ''}
                                            ${canGrade ? `
                                                <div class="form-group">
                                                    <label class="form-label">Feedback for this command:</label>
                                                    <input type="text" class="form-input" id="cmd_${item.data.id}" 
                                                           value="${existingFeedback?.command_feedback?.[item.data.command] || ''}" 
                                                           placeholder="Enter feedback for this command">
                                                </div>
                                            ` : ''}
                                        </div>
                                    </div>
                                `;
                            } else {
                                return `
                                    <div class="grading-timeline-item conversation-item">
                                        <div class="timeline-time">${formatDate(item.timestamp)}</div>
                                        <div class="timeline-content">
                                            <h5>${item.data.message_type === 'user' ? (isOperatorViewing ? 'Your Question' : 'Student Question') : 'Bot Response'}</h5>
                                            <div class="chat-message ${item.data.message_type}">
                                                ${item.data.message}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            }
                        }).join('')}
                    </div>
                    
                    ${canGrade ? `
                        <div class="feedback-section">
                            <h4>Overall Session Grading</h4>
                            <div class="grade-input">
                                <label class="form-label">Overall Score (0-100):</label>
                                <input type="number" id="overallScore" class="form-input" min="0" max="100" 
                                    value="${existingFeedback?.overall_score || ''}" style="width: 100px;"
                                    oninput="this.value = Math.max(0, Math.min(100, this.value))">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Overall Rating:</label>
                                <div class="rating-stars" id="ratingStars">
                                    ${[1,2,3,4,5].map(i => 
                                        `<span class="star ${(existingFeedback?.rating >= i) ? 'active' : ''}" 
                                               onclick="setRating(${i})">★</span>`
                                    ).join('')}
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Instructor Feedback:</label>
                                <textarea class="form-input" id="instructorFeedback" rows="4" 
                                          placeholder="Provide overall feedback for the student">${existingFeedback?.instructor_feedback || ''}</textarea>
                            </div>
                            
                            <button class="btn btn-primary" onclick="saveFeedback('${sessionId}')">
                                ${existingFeedback ? 'Update' : 'Save'} Feedback
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('gradingContent').innerHTML = content;
        }
    } catch (error) {
        console.error('Load grading content error:', error);
        showAlert('Error loading grading data', 'danger');
    }
}

function setRating(rating) {
    const stars = document.querySelectorAll('.star');
    stars.forEach((star, index) => {
        star.classList.toggle('active', index < rating);
    });
}

async function saveFeedback(sessionId) {
    try {
        const response = await fetch(`${API_BASE}?action=grading_data&session_id=${sessionId}`);
        const data = await response.json();
        
        if (!data.success) {
            showAlert('Error loading session data', 'danger');
            return;
        }
        
        const commands = data.commands;
        const overallScore = parseInt(document.getElementById('overallScore').value);
        const instructorFeedback = document.getElementById('instructorFeedback').value;
        const rating = document.querySelectorAll('.star.active').length;
        
        const commandFeedback = {};
        commands.forEach(cmd => {
            const feedbackInput = document.getElementById(`cmd_${cmd.id}`);
            if (feedbackInput && feedbackInput.value) {
                commandFeedback[cmd.command] = feedbackInput.value;
            }
        });
        
        const saveResponse = await fetch(API_BASE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'save_feedback',
                session_id: sessionId,
                user_id: data.session.user_id,
                overall_score: overallScore,
                instructor_feedback: instructorFeedback,
                command_feedback: commandFeedback,
                rating: rating,
                graded_by: currentUser.id
            })
        });
        
        const saveData = await saveResponse.json();
        
        if (saveData.success) {
            showAlert('Feedback saved successfully!', 'success');
            
            // Update the sessions data to reflect the new grading status
            const sessionIndex = currentData.sessions.findIndex(s => s.session_id === sessionId);
            if (sessionIndex !== -1) {
                currentData.sessions[sessionIndex].has_feedback = 1;
            }
            
            // Immediately switch to the Grade View page
            viewGradePage(sessionId);
        } else {
            showAlert('Error saving feedback: ' + saveData.message, 'danger');
        }
    } catch (error) {
        console.error('Save feedback error:', error);
        showAlert('Error saving feedback', 'danger');
    }
}

// Reports and Analytics
async function loadReports() {
    if (!hasPermission('reports')) {
        showAlert('Access denied', 'danger');
        return;
    }
    
    try {
        const startDate = document.getElementById('reportStartDate').value || getDefaultStartDate();
        const endDate = document.getElementById('reportEndDate').value || getDefaultEndDate();
        const userRole = document.getElementById('userRoleFilter').value || '';
        
        const params = new URLSearchParams({
            start_date: startDate,
            end_date: endDate,
            user_role: userRole
        });
        
        const response = await fetch(`${API_BASE}?action=reports&${params}`);
        const data = await response.json();
        
        if (data.success) {
            currentReportData = data;
            updateOverviewStats(data.stats);
            createAllCharts(data.charts);
            loadDetailedReports();
        } else {
            showAlert('Error loading reports: ' + (data.message || 'Unknown error'), 'danger');
        }
    } catch (error) {
        console.error('Reports load error:', error);
        showAlert('Error loading reports: ' + error.message, 'danger');
    }
}

function updateReportStats(stats) {
    // Safely update stats elements if they exist
    const elements = {
        'totalSessions': stats.total_sessions,
        'avgDuration': stats.avg_duration,
        'topCommand': stats.top_command,
        'completionRate': stats.completion_rate + '%'
    };
    
    Object.entries(elements).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    });
}


function createCommandUsageChart(data) {
    // Destroy existing chart if it exists
    if (charts.commandUsage) {
        charts.commandUsage.destroy();
    }
    
    const canvas = document.getElementById('commandUsageChart');
    if (!canvas) {
        console.warn('Command usage chart canvas not found');
        return;
    }
    
    const ctx = canvas.getContext('2d');
    charts.commandUsage = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || ['No Data'],
            datasets: [{
                label: 'Usage Count',
                data: data.values || [0],
                backgroundColor: [
                    '#58a6ff', '#a9a7ff', '#3fb950', '#d29922', 
                    '#f85149', '#17a2b8', '#8b949e'
                ],
                borderColor: '#21262d',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: '#8b949e'
                    },
                    grid: {
                        color: '#30363d'
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#8b949e'
                    },
                    grid: {
                        color: '#30363d'
                    }
                }
            }
        }
    });
}

function createDurationChart(data) {
    // Destroy existing chart if it exists
    if (charts.duration) {
        charts.duration.destroy();
    }
    
    const canvas = document.getElementById('durationChart');
    if (!canvas) {
        console.warn('Duration chart canvas not found');
        return;
    }
    
    const ctx = canvas.getContext('2d');
    charts.duration = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels || ['No Data'],
            datasets: [{
                label: 'Session Count',
                data: data.values || [0],
                backgroundColor: '#58a6ff',
                borderColor: '#a9a7ff',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: '#8b949e'
                    },
                    grid: {
                        color: '#30363d'
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#8b949e'
                    },
                    grid: {
                        color: '#30363d'
                    }
                }
            }
        }
    });
}


// Session Detail Page View
async function viewSessionDetailPage(sessionId) {
    try {
        const response = await fetch(`${API_BASE}?action=session_detail&session_id=${sessionId}`);
        const data = await response.json();
        
        if (data.success) {
            const session = data.session;
            
            // Check if user can access this session based on role
            if (!canAccessSession(session)) {
                showAlert('Access denied: You can only view your own sessions', 'danger');
                return;
            }
            
            // Ensure users data is loaded first
            if (!currentData.users || currentData.users.length === 0) {
                await loadUsers();
            }
            const user = currentData.users.find(u => parseInt(u.id) === parseInt(session.user_id));
            const commands = data.commands;
            const conversations = data.conversations || [];
            
            // Create chronological timeline
            const timeline = [];
            
            // Add commands to timeline
            commands.forEach(cmd => {
                timeline.push({
                    type: 'command',
                    timestamp: cmd.timestamp,
                    data: cmd
                });
            });
            
            // Add conversations to timeline
            conversations.forEach(conv => {
                timeline.push({
                    type: 'conversation',
                    timestamp: conv.timestamp,
                    data: conv
                });
            });
            
            // Sort by timestamp
            timeline.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
            
            const isOwnSession = currentUser && session.user_id == currentUser.id;
            const viewerLabel = isOwnSession ? 'Your' : (user ? user.full_name + "'s" : 'Unknown User');
            
            const content = `
                <div class="session-info">
                    <h4>${viewerLabel} Session Information</h4>
                    <p><strong>Session ID:</strong> ${session.session_id}</p>
                    <p><strong>${isOwnSession ? 'Your Session' : 'User'}:</strong> ${user ? user.full_name : 'Unknown'}</p>
                    <p><strong>Hostname:</strong> ${session.hostname}</p>
                    <p><strong>IP Address:</strong> ${session.ip_address}</p>
                    <p><strong>Start Time:</strong> ${formatDate(session.start_time)}</p>
                    <p><strong>End Time:</strong> ${session.end_time ? formatDate(session.end_time) : 'Still active'}</p>
                    <p><strong>Status:</strong> ${session.status}</p>
                    <p><strong>OS Info:</strong> ${session.os_info || 'Not available'}</p>
                </div>
                
                <div class="grading-timeline">
                    <h4>${viewerLabel} Session Timeline (${timeline.length} Events)</h4>
                    ${timeline.map(item => {
                        if (item.type === 'command') {
                            return `
                                <div class="grading-timeline-item command-item">
                                    <div class="timeline-time">${formatDate(item.timestamp)}</div>
                                    <div class="timeline-content">
                                        <h5>Command: <code>${item.data.command}</code></h5>
                                        <p><strong>Status:</strong> <span class="status-badge status-${item.data.status}">${item.data.status}</span></p>
                                        <p><strong>Execution Time:</strong> ${item.data.execution_time ? item.data.execution_time + 's' : 'N/A'}</p>
                                        ${item.data.output ? `
                                            <div class="command-output-full">
                                                <strong>Output:</strong>
                                                <pre>${item.data.output}</pre>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            `;
                        } else {
                            return `
                                <div class="grading-timeline-item conversation-item">
                                    <div class="timeline-time">${formatDate(item.timestamp)}</div>
                                    <div class="timeline-content">
                                        <h5>${item.data.message_type === 'user' ? (isOwnSession ? 'Your Question' : 'Student Question') : 'Bot Response'}</h5>
                                        <div class="chat-message ${item.data.message_type}">
                                            ${item.data.message}
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                    }).join('')}
                </div>
            `;
            
            document.getElementById('sessionViewTitle').textContent = `${viewerLabel} Session: ${sessionId}`;
            document.getElementById('sessionViewContent').innerHTML = content;
            
            // Set up export buttons (only show for admins and managers)
            if (currentUser.role !== 'operator') {
                document.getElementById('sessionExportPDF').onclick = () => exportSessionDetail(sessionId, 'pdf');
                document.getElementById('sessionExportExcel').onclick = () => exportSessionDetail(sessionId, 'excel');
                document.getElementById('sessionExportPDF').style.display = 'inline-block';
                document.getElementById('sessionExportExcel').style.display = 'inline-block';
            } else {
                document.getElementById('sessionExportPDF').style.display = 'none';
                document.getElementById('sessionExportExcel').style.display = 'none';
            }
            
            // Show session view
            showSection('sessionView');
        }
    } catch (error) {
        console.error('Session detail error:', error);
        showAlert('Error loading session details', 'danger');
    }
}

// Grade View Page
async function viewGradePage(sessionId) {
    try {
        const response = await fetch(`${API_BASE}?action=grading_data&session_id=${sessionId}`);
        const data = await response.json();
        
        if (data.success) {
            const session = data.session;
            
            // Check if user can access this session based on role
            if (!canAccessSession(session)) {
                showAlert('Access denied: You can only view your own grades', 'danger');
                return;
            }
            
            // Ensure users data is loaded first
            if (!currentData.users || currentData.users.length === 0) {
                await loadUsers();
            }
            const user = currentData.users.find(u => parseInt(u.id) === parseInt(session.user_id));
            const commands = data.commands;
            const conversations = data.conversations || [];
            const feedback = data.feedback;
            
            const isOwnSession = currentUser && session.user_id == currentUser.id;
            const viewerLabel = isOwnSession ? 'Your' : (user ? user.full_name + "'s" : 'Unknown User');
            
            // Create chronological timeline
            const timeline = [];
            
            // Add commands to timeline
            commands.forEach(cmd => {
                timeline.push({
                    type: 'command',
                    timestamp: cmd.timestamp,
                    data: cmd
                });
            });
            
            // Add conversations to timeline
            conversations.forEach(conv => {
                timeline.push({
                    type: 'conversation',
                    timestamp: conv.timestamp,
                    data: conv
                });
            });
            
            // Sort by timestamp
            timeline.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
            
            const content = `
                <div class="session-info">
                    <h4>${viewerLabel} Session Information</h4>
                    <p><strong>Session ID:</strong> ${session.session_id}</p>
                    <p><strong>${isOwnSession ? 'Your Session' : 'Student'}:</strong> ${user ? user.full_name : 'Unknown'}</p>
                    <p><strong>Session Date:</strong> ${formatDate(session.start_time)}</p>
                    <p><strong>Commands Executed:</strong> ${commands.length}</p>
                    <p><strong>Chat Interactions:</strong> ${conversations.length}</p>
                </div>
                
                ${feedback ? `
                    <div class="feedback-display">
                        <h4>${isOwnSession ? '🎓 Your Grade Information' : '📊 Grade Information'}</h4>
                        <div class="grade-summary">
                            <div class="grade-item">
                                <span class="grade-label">Overall Score:</span>
                                <span class="grade-value">${feedback.overall_score}/100</span>
                            </div>
                            <div class="grade-item">
                                <span class="grade-label">Rating:</span>
                                <span class="grade-value">${feedback.rating ? '★'.repeat(feedback.rating) + '☆'.repeat(5-feedback.rating) : 'Not rated'}</span>
                            </div>
                            <div class="grade-item">
                                <span class="grade-label">Instructor Feedback:</span>
                                <span class="grade-value">${feedback.instructor_feedback || 'No feedback provided'}</span>
                            </div>
                            <div class="grade-item">
                                <span class="grade-label">Graded by:</span>
                                <span class="grade-value">${currentData.users.find(u => u.id === feedback.graded_by)?.full_name || 'Unknown'}</span>
                            </div>
                            <div class="grade-item">
                                <span class="grade-label">Graded on:</span>
                                <span class="grade-value">${feedback.graded_at ? formatDate(feedback.graded_at) : 'Not graded'}</span>
                            </div>
                        </div>
                    </div>
                ` : `
                    <div class="feedback-display">
                        <h4>${isOwnSession ? '📝 Your Session Status' : '📝 Session Status'}</h4>
                        <p style="text-align: center; color: var(--accent-yellow); font-style: italic;">
                            ${isOwnSession ? 'Your session has not been graded yet.' : 'This session has not been graded yet.'}
                        </p>
                    </div>
                `}
                
                <div class="grading-timeline">
                    <h4>${viewerLabel} Session Timeline with ${feedback ? 'Feedback' : 'Activity'}</h4>
                    ${timeline.map(item => {
                        if (item.type === 'command') {
                            const commandFeedback = feedback?.command_feedback?.[item.data.command] || '';
                            return `
                                <div class="grading-timeline-item command-item">
                                    <div class="timeline-time">${formatDate(item.timestamp)}</div>
                                    <div class="timeline-content">
                                        <h5>Command: <code>${item.data.command}</code></h5>
                                        <p><strong>Status:</strong> <span class="status-badge status-${item.data.status}">${item.data.status}</span></p>
                                        <p><strong>Execution Time:</strong> ${item.data.execution_time ? item.data.execution_time + 's' : 'N/A'}</p>
                                        ${item.data.output ? `
                                            <div class="command-output-full">
                                                <strong>Output:</strong>
                                                <pre>${item.data.output}</pre>
                                            </div>
                                        ` : ''}
                                        ${commandFeedback ? `
                                            <div class="instructor-feedback">
                                                <strong>💬 Instructor Feedback:</strong>
                                                <p>${commandFeedback}</p>
                                            </div>
                                        ` : ''}
                                        ${!commandFeedback && feedback ? `
                                            <div class="instructor-feedback" style="opacity: 0.6;">
                                                <strong>💬 Instructor Feedback:</strong>
                                                <p><em>No specific feedback for this command</em></p>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            `;
                        } else {
                            return `
                                <div class="grading-timeline-item conversation-item">
                                    <div class="timeline-time">${formatDate(item.timestamp)}</div>
                                    <div class="timeline-content">
                                        <h5>${item.data.message_type === 'user' ? (isOwnSession ? 'Your Question' : 'Student Question') : 'Bot Response'}</h5>
                                        <div class="chat-message ${item.data.message_type}">
                                            ${item.data.message}
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                    }).join('')}
                </div>
            `;
            
            document.getElementById('gradeViewTitle').textContent = `${viewerLabel} Grade: ${sessionId}`;
            document.getElementById('gradeViewContent').innerHTML = content;
            
            // Only show edit controls for users who can grade (not operators)
            const editControls = document.getElementById('gradeEditControls');
            if (canGradeSession(sessionId)) {
                editControls.style.display = 'block';
            } else {
                editControls.style.display = 'none';
            }
            
            // Store session ID for editing (only relevant for graders)
            window.currentEditingSessionId = sessionId;
            
            // Show grade view
            showSection('gradeView');
        }
    } catch (error) {
        console.error('Grade view error:', error);
        showAlert('Error loading grade details', 'danger');
    }
}

// Grade editing functions
function enableGradeEditing() {
    // Show edit controls
    document.getElementById('saveGradeBtn').style.display = 'inline-block';
    document.getElementById('cancelGradeBtn').style.display = 'inline-block';
    document.querySelector('[onclick="enableGradeEditing()"]').setAttribute('style', 'display:none !important');
    
    // Show edit section
    document.getElementById('editGradeSection').style.display = 'block';
    
    // Show command feedback edit inputs
    document.querySelectorAll('[id^="edit-cmd-"]').forEach(el => {
        el.style.display = 'block';
    });
}

function cancelGradeEditing() {
    // Hide edit controls
    document.getElementById('saveGradeBtn').setAttribute('style', 'display:none !important');
    document.getElementById('cancelGradeBtn').setAttribute('style', 'display:none !important');
    document.querySelector('[onclick="enableGradeEditing()"]').style.display = 'inline-block';
    
    // Hide edit section
    document.getElementById('editGradeSection').setAttribute('style', 'display:none !important');
    
    // Hide command feedback edit inputs
    document.querySelectorAll('[id^="edit-cmd-"]').forEach(el => {
        el.setAttribute('style', 'display:none !important');
    });
}

function setEditRating(rating) {
    const stars = document.querySelectorAll('#editRatingStars .star');
    stars.forEach((star, index) => {
        star.classList.toggle('active', index < rating);
    });
}

async function saveGradeEdits() {
    try {
        const sessionId = window.currentEditingSessionId;
        const response = await fetch(`${API_BASE}?action=grading_data&session_id=${sessionId}`);
        const data = await response.json();
        
        if (!data.success) {
            showAlert('Error loading session data', 'danger');
            return;
        }
        
        const commands = data.commands;
        const overallScore = parseInt(document.getElementById('editOverallScore').value);
        const instructorFeedback = document.getElementById('editInstructorFeedback').value;
        const rating = document.querySelectorAll('#editRatingStars .star.active').length;
        
        const commandFeedback = {};
        commands.forEach(cmd => {
            const feedbackInput = document.getElementById(`edit-input-${cmd.id}`);
            if (feedbackInput && feedbackInput.value) {
                commandFeedback[cmd.command] = feedbackInput.value;
            }
        });
        
        const saveResponse = await fetch(API_BASE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'save_feedback',
                session_id: sessionId,
                user_id: data.session.user_id,
                overall_score: overallScore,
                instructor_feedback: instructorFeedback,
                command_feedback: commandFeedback,
                rating: rating,
                graded_by: currentUser.id
            })
        });
        
        const saveData = await saveResponse.json();
        
        if (saveData.success) {
            showAlert('Grade updated successfully!', 'success');
            cancelGradeEditing();
            // Reload the grade view to show updated data
            viewGradePage(sessionId);
        } else {
            showAlert('Error saving grade: ' + saveData.message, 'danger');
        }
    } catch (error) {
        console.error('Save grade error:', error);
        showAlert('Error saving grade', 'danger');
    }
}

function backToSessions() {
    showSection('sessions');
}

function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('passwordToggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

async function loadProfile() {
    if (!currentUser) return;
    
    // Load user info
    document.getElementById('profileUserInfo').innerHTML = `
        <div class="profile-stat">
            <span class="profile-stat-label">Username:</span>
            <span class="profile-stat-value">${currentUser.username}</span>
        </div>
        <div class="profile-stat">
            <span class="profile-stat-label">Full Name:</span>
            <span class="profile-stat-value">${currentUser.full_name}</span>
        </div>
        <div class="profile-stat">
            <span class="profile-stat-label">Email:</span>
            <span class="profile-stat-value">${currentUser.email}</span>
        </div>
        <div class="profile-stat">
            <span class="profile-stat-label">Role:</span>
            <span class="profile-stat-value">${currentUser.role}</span>
        </div>
        <div class="profile-stat">
            <span class="profile-stat-label">Last Login:</span>
            <span class="profile-stat-value">${formatDate(currentUser.last_login)}</span>
        </div>
    `;
    
    // Load user's sessions
    await loadUserProfileSessions();
    await loadUserGrades();
}

function showEditProfileModal() {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'block';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Profile</h3>
                <button class="close-btn" onclick="this.closest('.modal').remove()">&times;</button>
            </div>
            <form id="editProfileForm">
                <div class="form-group">
                    <label class="form-label">Full Name:</label>
                    <input type="text" id="editFullName" class="form-input" value="${currentUser.full_name}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email:</label>
                    <input type="email" id="editEmail" class="form-input" value="${currentUser.email}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Current Password:</label>
                    <input type="password" id="editCurrentPassword" class="form-input" placeholder="Enter current password to save changes" required>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password (optional):</label>
                    <input type="password" id="editNewPassword" class="form-input" placeholder="Leave blank to keep current password" minlength="8">
                    <div id="passwordStrength" class="password-strength" style="display: none;">
                        <div class="strength-meter">
                            <div class="strength-fill"></div>
                        </div>
                        <div class="strength-text">Password strength: <span id="strengthText">Weak</span></div>
                        <div class="password-requirements">
                            <div class="requirement" id="req-length">• At least 8 characters</div>
                            <div class="requirement" id="req-upper">• One uppercase letter</div>
                            <div class="requirement" id="req-lower">• One lowercase letter</div>
                            <div class="requirement" id="req-number">• One number</div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password:</label>
                    <input type="password" id="editConfirmPassword" class="form-input" placeholder="Confirm new password">
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveProfileBtn">Save Changes</button>
                </div>
            </form>
        </div>
    `;
    
    // Add password strength checking
    const newPasswordInput = modal.querySelector('#editNewPassword');
    const strengthMeter = modal.querySelector('#passwordStrength');
    
    newPasswordInput.addEventListener('input', function() {
        const password = this.value;
        if (password.length > 0) {
            strengthMeter.style.display = 'block';
            updatePasswordStrength(password);
        } else {
            strengthMeter.style.display = 'none';
        }
    });
    
    modal.querySelector('#editProfileForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await saveProfileChanges(modal);
    });
    
    document.body.appendChild(modal);
}

function updatePasswordStrength(password) {
    const requirements = {
        length: password.length >= 8,
        upper: /[A-Z]/.test(password),
        lower: /[a-z]/.test(password),
        number: /\d/.test(password)
    };
    
    const metCount = Object.values(requirements).filter(Boolean).length;
    const strengthFill = document.querySelector('.strength-fill');
    const strengthText = document.getElementById('strengthText');
    
    // Update requirement indicators
    Object.keys(requirements).forEach(req => {
        const element = document.getElementById(`req-${req}`);
        if (element) {
            element.classList.toggle('met', requirements[req]);
        }
    });
    
    // Update strength meter
    const strength = metCount <= 1 ? 'weak' : metCount <= 2 ? 'fair' : metCount <= 3 ? 'good' : 'strong';
    const widths = { weak: '25%', fair: '50%', good: '75%', strong: '100%' };
    const colors = { weak: '#f85149', fair: '#d29922', good: '#58a6ff', strong: '#3fb950' };
    
    strengthFill.style.width = widths[strength];
    strengthFill.style.backgroundColor = colors[strength];
    strengthText.textContent = strength.charAt(0).toUpperCase() + strength.slice(1);
    strengthText.style.color = colors[strength];
}

async function saveProfileChanges(modal) {
    const fullName = modal.querySelector('#editFullName').value;
    const email = modal.querySelector('#editEmail').value;
    const currentPassword = modal.querySelector('#editCurrentPassword').value;
    const newPassword = modal.querySelector('#editNewPassword').value;
    const confirmPassword = modal.querySelector('#editConfirmPassword').value;
    
    // Validation
    if (newPassword && newPassword !== confirmPassword) {
        showAlert('New passwords do not match', 'danger');
        return;
    }
    
    if (newPassword && newPassword.length < 8) {
        showAlert('New password must be at least 8 characters long', 'danger');
        return;
    }
    
    const saveBtn = modal.querySelector('#saveProfileBtn');
    saveBtn.textContent = 'Saving...';
    saveBtn.disabled = true;
    
    try {
        const response = await fetch(API_BASE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_profile',
                user_id: currentUser.id,
                full_name: fullName,
                email: email,
                current_password: currentPassword,
                new_password: newPassword || null
            })
        });
        
        const data = await response.json();
        if (data.success) {
            // Update current user data
            currentUser.full_name = fullName;
            currentUser.email = email;
            localStorage.setItem('user_data', JSON.stringify(currentUser));
            
            showAlert('Profile updated successfully!', 'success');
            modal.remove();
            loadProfile(); // Refresh profile display
        } else {
            showAlert('Error updating profile: ' + data.message, 'danger');
        }
    } catch (error) {
        showAlert('Error updating profile', 'danger');
    } finally {
        saveBtn.textContent = 'Save Changes';
        saveBtn.disabled = false;
    }
}

function showChangePasswordModal() {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'block';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>Change Password</h3>
                <button class="close-btn" onclick="this.closest('.modal').remove()">&times;</button>
            </div>
            <form id="changePasswordForm">
                <div class="form-group">
                    <label class="form-label">Current Password:</label>
                    <input type="password" id="currentPassword" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password:</label>
                    <input type="password" id="newPassword" class="form-input" required minlength="8">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password:</label>
                    <input type="password" id="confirmNewPassword" class="form-input" required>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    `;
    
    modal.querySelector('#changePasswordForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        // Implementation for password change
        // Add API endpoint for this
    });
    
    document.body.appendChild(modal);
}

// Check if current user has permission for specific action
function hasPermission(action) {
    if (!window.currentUserPermissions) return false;
    return window.currentUserPermissions.includes(action);
}

// Check if user can access specific user data
function canAccessUserData(targetUserId) {
    if (!currentUser) return false;
    
    const currentUserId = currentUser.id;
    const currentUserRole = currentUser.role;
    
    // Admin can access everything
    if (currentUserRole === 'admin') return true;
    
    // Manager can access operator data and their own
    if (currentUserRole === 'manager') {
        if (targetUserId == currentUserId) return true;
        
        // Check if target user is an operator
        const targetUser = currentData.users.find(u => u.id == targetUserId);
        return targetUser && targetUser.role === 'operator';
    }
    
    // Operator can only access their own data
    if (currentUserRole === 'operator') {
        return targetUserId == currentUserId;
    }
    
    return false;
}

// Check if user can view a specific session
function canViewSession(sessionId) {
    if (!currentUser) return false;
    
    const session = currentData.sessions.find(s => s.session_id === sessionId);
    if (!session) return false;
    
    const currentUserRole = currentUser.role;
    const currentUserId = currentUser.id;
    
    // Admin can view all sessions
    if (currentUserRole === 'admin') return true;
    
    // Manager can view operator sessions and their own
    if (currentUserRole === 'manager') {
        if (session.user_id == currentUserId) return true;
        
        const sessionUser = currentData.users.find(u => u.id == session.user_id);
        return sessionUser && sessionUser.role === 'operator';
    }
    
    // Operator can only view their own sessions
    if (currentUserRole === 'operator') {
        return session.user_id == currentUserId;
    }
    
    return false;
}

// Check if user can grade a session (different from viewing)
function canGradeSession(sessionId) {
    if (!currentUser) return false;
    
    const session = currentData.sessions.find(s => s.session_id === sessionId);
    if (!session) return false;
    
    const currentUserRole = currentUser.role;
    const currentUserId = currentUser.id;
    
    // Operators cannot grade any sessions (including their own)
    if (currentUserRole === 'operator') return false;
    
    // Admin can grade all sessions
    if (currentUserRole === 'admin') return true;
    
    // Manager can grade operator sessions but not their own
    if (currentUserRole === 'manager') {
        if (session.user_id == currentUserId) return false; // Can't grade own session
        
        const sessionUser = currentData.users.find(u => u.id == session.user_id);
        return sessionUser && sessionUser.role === 'operator';
    }
    
    return false;
}

// Check if user can delete a session
function canDeleteSession(sessionId) {
    if (!currentUser) return false;
    
    // Only admins can delete sessions
    return currentUser.role === 'admin';
}

// Filter data based on user permissions
function filterDataByPermissions(data, dataType) {
    if (!currentUser) return [];
    
    const role = currentUser.role;
    const userId = currentUser.id;
    
    switch (dataType) {
        case 'users':
            if (role === 'admin') return data;
            if (role === 'manager') return data.filter(u => u.role === 'operator' || u.id == userId);
            if (role === 'operator') return data.filter(u => u.id == userId); // Operator only sees themselves
            break;
            
        case 'sessions':
            if (role === 'admin') return data;
            if (role === 'manager') {
                // Managers see sessions from operators and their own
                return data.filter(s => {
                    const sessionUser = currentData.users.find(u => u.id == s.user_id);
                    return sessionUser && (sessionUser.role === 'operator' || s.user_id == userId);
                });
            }
            if (role === 'operator') return data.filter(s => s.user_id == userId);
            break;
            
        case 'logs':
            if (role === 'admin') return data;
            // Only admins can see logs
            return [];
            
        default:
            return data;
    }
    
    return [];
}

// Check if user can view a specific session
function canViewSession(sessionId) {
    if (!currentUser) return false;
    
    const session = currentData.sessions.find(s => s.session_id === sessionId);
    if (!session) return false;
    
    const currentUserRole = currentUser.role;
    const currentUserId = currentUser.id;
    
    // Admin can view all sessions
    if (currentUserRole === 'admin') return true;
    
    // Manager can view operator sessions and their own
    if (currentUserRole === 'manager') {
        if (session.user_id == currentUserId) return true;
        
        const sessionUser = currentData.users.find(u => u.id == session.user_id);
        return sessionUser && sessionUser.role === 'operator';
    }
    
    // Operator can only view their own sessions
    if (currentUserRole === 'operator') {
        return session.user_id == currentUserId;
    }
    
    return false;
}

// Check if user can grade a session (different from viewing)
function canGradeSession(sessionId) {
    if (!currentUser) return false;
    
    const session = currentData.sessions.find(s => s.session_id === sessionId);
    if (!session) return false;
    
    const currentUserRole = currentUser.role;
    const currentUserId = currentUser.id;
    
    // Operators cannot grade any sessions (including their own)
    if (currentUserRole === 'operator') return false;
    
    // Admin can grade all sessions
    if (currentUserRole === 'admin') return true;
    
    // Manager can grade operator sessions but not their own
    if (currentUserRole === 'manager') {
        if (session.user_id == currentUserId) return false; // Can't grade own session
        
        const sessionUser = currentData.users.find(u => u.id == session.user_id);
        return sessionUser && sessionUser.role === 'operator';
    }
    
    return false;
}

// Check if user can delete a session
function canDeleteSession(sessionId) {
    if (!currentUser) return false;
    
    // Only admins can delete sessions
    return currentUser.role === 'admin';
}

function canAccessSession(session) {
    if (!currentUser || !session) return false;
    
    const currentUserRole = currentUser.role;
    const currentUserId = currentUser.id;
    
    // Admin can access all sessions
    if (currentUserRole === 'admin') return true;
    
    // Manager can access operator sessions and their own
    if (currentUserRole === 'manager') {
        if (session.user_id == currentUserId) return true;
        
        const sessionUser = currentData.users.find(u => u.id == session.user_id);
        return sessionUser && sessionUser.role === 'operator';
    }
    
    // Operator can only access their own sessions
    if (currentUserRole === 'operator') {
        return session.user_id == currentUserId;
    }
    
    return false;
}

async function loadUserProfileSessions() {
    if (!currentUser) return;
    
    try {
        const response = await fetch(`${API_BASE}?action=user_sessions&user_id=${currentUser.id}`);
        const data = await response.json();
        
        if (data.success && data.sessions.length > 0) {
            const recentSessions = data.sessions.slice(0, 5); // Show last 5 sessions
            
            document.getElementById('profileSessions').innerHTML = `
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Session ID</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${recentSessions.map(session => `
                                <tr>
                                    <td>${session.session_id}</td>
                                    <td>${formatDate(session.start_time)}</td>
                                    <td>
                                        <button class="btn btn-secondary btn-small" style="margin-right: 4px;" onclick="viewSessionDetailPage('${session.session_id}')">View</button>
                                        ${currentData.sessions.find(s => s.session_id === session.session_id)?.has_feedback ? 
                                            `<button class="btn btn-primary btn-small" onclick="viewGradePage('${session.session_id}')">View Grade</button>` : 
                                            '<span style="font-style: italic; color: var(--text-secondary);">Not graded</span>'
                                        }
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        } else {
            document.getElementById('profileSessions').innerHTML = `
                <div class="profile-stat">
                    <span class="profile-stat-value" style="text-align: center; width: 100%; font-style: italic; opacity: 0.7;">No sessions found</span>
                </div>
            `;
        }
    } catch (error) {
        console.error('Load profile sessions error:', error);
        document.getElementById('profileSessions').innerHTML = `
            <div class="profile-stat">
                <span class="profile-stat-value" style="text-align: center; width: 100%; color: var(--accent-red);">Error loading sessions</span>
            </div>
        `;
    }
}

async function loadUserGrades() {
    if (!currentUser) return;
    
    try {
        // Get user's sessions with feedback
        const response = await fetch(`${API_BASE}?action=user_grades&user_id=${currentUser.id}`);
        const data = await response.json();
        
        if (data.success && data.grades && data.grades.length > 0) {
            const totalGrades = data.grades.length;
            const avgScore = Math.round(data.grades.reduce((sum, g) => sum + (g.overall_score || 0), 0) / totalGrades);
            const avgRating = Math.round(data.grades.reduce((sum, g) => sum + (g.rating || 0), 0) / totalGrades * 10) / 10;
            
            document.getElementById('profileGrades').innerHTML = `
                <div class="profile-stat">
                    <span class="profile-stat-label">Total Graded Sessions:</span>
                    <span class="profile-stat-value">${totalGrades}</span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat-label">Average Score:</span>
                    <span class="profile-stat-value">${avgScore}/100</span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat-label">Average Rating:</span>
                    <span class="profile-stat-value">${avgRating}/5 ★</span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat-label">Latest Grade:</span>
                    <span class="profile-stat-value">${data.grades[0].overall_score}/100</span>
                </div>
            `;
        } else {
            document.getElementById('profileGrades').innerHTML = `
                <div class="profile-stat">
                    <span class="profile-stat-value" style="text-align: center; width: 100%; font-style: italic; opacity: 0.7;">No grades available</span>
                </div>
            `;
        }
    } catch (error) {
        console.error('Load user grades error:', error);
        document.getElementById('profileGrades').innerHTML = `
            <div class="profile-stat">
                <span class="profile-stat-value" style="text-align: center; width: 100%; color: var(--accent-red);">Error loading grades</span>
            </div>
        `;
    }
}

async function testReportsData() {
    try {
        const response = await fetch(`${API_BASE}?action=reports&start_date=2024-01-01&end_date=2025-12-31`);
        const data = await response.json();
        console.log('Reports API response:', data);
    } catch (error) {
        console.error('Test error:', error);
    }
}