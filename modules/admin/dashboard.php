<?php
// modules/admin/dashboard.php - WITH REAL-TIME CLOCK
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireAdmin();

require_once __DIR__ . '/../../models/Attendance.php';
require_once __DIR__ . '/../../config/db.php';

// Time-based greeting function
function getGreeting() {
    $hour = date('H');
    if ($hour >= 5 && $hour < 12) {
        return 'Good Morning';
    } elseif ($hour >= 12 && $hour < 17) {
        return 'Good Afternoon';
    } else {
        return 'Good Evening';
    }
}
$greeting = getGreeting();

$attendanceModel = new Attendance();
$database = new Database();
$conn = $database->getConnection();

// Get school info
$schoolQuery = "SELECT school_name, location_address FROM school_settings WHERE id = 1";
$schoolStmt = $conn->prepare($schoolQuery);
$schoolStmt->execute();
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);

if (!$school) {
    $school = [
        'school_name' => 'St. Luke C.O.U Primary School',
        'location_address' => 'Kaburangire, Koranorya, Mbarara, Uganda'
    ];
}

$totalTeachers = $attendanceModel->countTeachers();
$todayAttendance = $attendanceModel->getTodayAttendance();
$pendingLeaves = $attendanceModel->getPendingLeaves();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars($school['school_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            transition: all 0.3s;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 100;
            width: 280px;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
        }
        
        .main-content {
            margin-left: 280px;
            transition: all 0.3s;
        }
        
        .sidebar-item {
            transition: all 0.2s;
            display: flex;
            align-items: center;
            padding: 14px 24px;
            color: #cbd5e1;
            text-decoration: none;
            cursor: pointer;
            border-left: 4px solid transparent;
        }
        
        .sidebar-item:hover {
            background: rgba(148, 163, 184, 0.1);
            color: white;
            border-left-color: #6366f1;
            transform: translateX(5px);
        }
        
        .sidebar-item.active {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.2) 0%, transparent 100%);
            color: white;
            border-left-color: #6366f1;
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
        
        .stat-card {
            transition: all 0.3s ease;
            background: white;
            border-left: 4px solid #6366f1;
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(99, 102, 241, 0.15);
        }
        
        .school-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .school-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .real-time-clock {
            background: rgba(99, 102, 241, 0.2);
            padding: 10px 18px;
            border-radius: 25px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            font-weight: bold;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
        
        .action-card {
            transition: all 0.3s ease;
            background: white;
            border-top: 3px solid #6366f1;
        }
        
        .action-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(99, 102, 241, 0.2);
        }
        
        .action-card i {
            transition: transform 0.3s ease;
        }
        
        .action-card:hover i {
            transform: scale(1.1);
        }
        
        .chart-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        
        .badge-pending {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
        }
        
        .badge-present {
            background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
            color: white;
        }
        
        .badge-absent {
            background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
            color: white;
        }
    </style>
</head>
<body class="bg-gray-100">
    <button id="menuToggle" class="fixed top-4 left-4 z-50 bg-indigo-600 text-white p-2 rounded-lg md:hidden hover:bg-indigo-700 transition">
        <i class="fas fa-bars text-xl"></i>
    </button>
    
    <div class="sidebar" id="sidebar">
        <div class="p-6 border-b border-slate-700">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-school text-white text-lg"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold">Attendance</h2>
                    <p class="text-xs text-slate-400">Admin Panel</p>
                </div>
            </div>
        </div>
        <div class="p-4 border-b border-slate-700">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-shield text-white text-sm"></i>
                </div>
                <div>
                    <p class="font-semibold text-sm"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></p>
                    <p class="text-xs text-slate-400">Administrator</p>
                </div>
            </div>
        </div>
        <nav class="flex-1 py-6 space-y-2">
            <a href="dashboard.php" class="sidebar-item active"><i class="fas fa-tachometer-alt w-5 mr-3"></i> Dashboard</a>
            <a href="manage_teachers.php" class="sidebar-item"><i class="fas fa-users w-5 mr-3"></i> Manage Teachers</a>
            <a href="register_teacher.php" class="sidebar-item"><i class="fas fa-user-plus w-5 mr-3"></i> Register Teacher</a>
            <a href="attendance.php" class="sidebar-item"><i class="fas fa-calendar-check w-5 mr-3"></i> Attendance</a>
            <a href="reports.php" class="sidebar-item"><i class="fas fa-chart-bar w-5 mr-3"></i> Reports</a>
            <a href="leave_requests.php" class="sidebar-item"><i class="fas fa-inbox w-5 mr-3"></i> Leave Requests</a>
            <a href="gps_settings.php" class="sidebar-item"><i class="fas fa-cog w-5 mr-3"></i> Settings</a>
            <a href="../../api/logout.php" class="sidebar-item mt-8 hover:bg-red-500/20 hover:border-l-red-500"><i class="fas fa-sign-out-alt w-5 mr-3"></i> Logout</a>
        </nav>
    </div>
    
    <div class="main-content">
        <!-- School Header with Real-time Clock -->
        <div class="school-header text-white shadow-lg relative z-10">
            <div class="px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center space-x-3 mb-2">
                            <i class="fas fa-school text-3xl text-indigo-400"></i>
                            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($school['school_name']); ?></h1>
                        </div>
                        <p class="text-slate-300 flex items-center text-sm">
                            <i class="fas fa-map-marker-alt mr-2 text-indigo-400"></i>
                            <?php echo htmlspecialchars($school['location_address']); ?>
                        </p>
                    </div>

                </div>
            </div>
        </div>
        
<div class="p-8">
            <div class="mb-8">
                <!-- Greeting with Big Counting Clock -->
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl p-6 text-white mb-4">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <div>
                            <p class="text-lg opacity-90"><?php echo $greeting; ?>,</p>
                            <h2 class="text-3xl font-bold"><span class="text-white"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>!</h2>
                            <p class="text-sm opacity-80 mt-1">Monitor your school's attendance system in real-time</p>
                        </div>
                        <div class="text-center">
                            <div class="bg-white/20 rounded-xl px-6 py-4 backdrop-blur-sm">
                                <p class="text-xs uppercase tracking-wider opacity-80 mb-1">Current Time</p>
<div class="text-6xl font-bold font-mono tracking-wider" id="bigClock">--:--:--</div>
                                <p class="text-sm opacity-80 mt-1" id="bigDate">--/--/----</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="stat-card rounded-xl p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-slate-500 text-sm font-medium">Total Teachers</p>
                            <p class="text-4xl font-bold text-slate-800 mt-2"><?php echo $totalTeachers; ?></p>
                        </div>
                        <div class="bg-gradient-to-br from-blue-100 to-blue-200 rounded-full p-4">
                            <i class="fas fa-users text-3xl text-blue-600"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card rounded-xl p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-slate-500 text-sm font-medium">Today's Attendance</p>
                            <p class="text-4xl font-bold text-slate-800 mt-2"><?php echo $todayAttendance; ?></p>
                        </div>
                        <div class="bg-gradient-to-br from-green-100 to-green-200 rounded-full p-4">
                            <i class="fas fa-calendar-check text-3xl text-green-600"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card rounded-xl p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-slate-500 text-sm font-medium">Pending Leaves</p>
                            <p class="text-4xl font-bold text-slate-800 mt-2"><?php echo $pendingLeaves; ?></p>
                        </div>
                        <div class="bg-gradient-to-br from-amber-100 to-amber-200 rounded-full p-4">
                            <i class="fas fa-envelope text-3xl text-amber-600"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <a href="manage_teachers.php" class="action-card rounded-xl p-6 hover:shadow-lg">
                    <div class="text-center">
                        <i class="fas fa-users text-4xl text-indigo-600 mb-3"></i>
                        <h3 class="font-bold text-lg text-slate-800 mb-2">Manage Teachers</h3>
                        <p class="text-slate-600 text-sm">Add, edit or remove teachers</p>
                    </div>
                </a>
                <a href="attendance.php" class="action-card rounded-xl p-6 hover:shadow-lg">
                    <div class="text-center">
                        <i class="fas fa-clock text-4xl text-green-600 mb-3"></i>
                        <h3 class="font-bold text-lg text-slate-800 mb-2">View Attendance</h3>
                        <p class="text-slate-600 text-sm">Monitor daily records</p>
                    </div>
                </a>
                <a href="leave_requests.php" class="action-card rounded-xl p-6 hover:shadow-lg">
                    <div class="text-center">
                        <i class="fas fa-inbox text-4xl text-amber-600 mb-3"></i>
                        <h3 class="font-bold text-lg text-slate-800 mb-2">Leave Requests</h3>
                        <p class="text-slate-600 text-sm">Approve or reject leaves</p>
                    </div>
                </a>
                <a href="reports.php" class="action-card rounded-xl p-6 hover:shadow-lg">
                    <div class="text-center">
                        <i class="fas fa-chart-bar text-4xl text-purple-600 mb-3"></i>
                        <h3 class="font-bold text-lg text-slate-800 mb-2">Reports</h3>
                        <p class="text-slate-600 text-sm">Generate analytics</p>
                    </div>
                </a>
            </div>
            
            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Weekly Attendance Trend Chart -->
                <div class="chart-container p-6">
                    <h3 class="font-bold text-lg text-slate-800 mb-4">
                        <i class="fas fa-chart-line mr-2 text-indigo-600"></i>Weekly Attendance Trend
                    </h3>
                    <canvas id="weeklyChart" height="250"></canvas>
                </div>
                
                <!-- Attendance Distribution Pie Chart -->
                <div class="chart-container p-6">
                    <h3 class="font-bold text-lg text-slate-800 mb-4">
                        <i class="fas fa-chart-pie mr-2 text-green-600"></i>Attendance Distribution
                    </h3>
                    <canvas id="distributionChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
<script>
        // Real-time clock function - updates big clock
        function updateDateTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            });
            const dateStr = now.toLocaleDateString('en-US', { 
                weekday: 'short', 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
            // Update big clock in greeting section
            if (document.getElementById('bigClock')) {
                document.getElementById('bigClock').innerHTML = timeStr;
            }
            if (document.getElementById('bigDate')) {
                document.getElementById('bigDate').innerHTML = dateStr;
            }
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        // Mobile menu toggle
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
        
        // Chart.js Visualizations
        async function loadChartData() {
            try {
                // Weekly Trend Chart
                const weeklyResponse = await fetch('../../api/get_chart_data.php?type=weekly');
                const weeklyData = await weeklyResponse.json();
                
                if (weeklyData.success && weeklyData.data) {
                    const ctx1 = document.getElementById('weeklyChart').getContext('2d');
                    new Chart(ctx1, {
                        type: 'line',
                        data: {
                            labels: weeklyData.data.map(d => d.label),
                            datasets: [{
                                label: 'Present',
                                data: weeklyData.data.map(d => d.present),
                                borderColor: '#4CAF50',
                                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                                fill: true,
                                tension: 0.4
                            }, {
                                label: 'Late',
                                data: weeklyData.data.map(d => d.late),
                                borderColor: '#FF9800',
                                backgroundColor: 'rgba(255, 152, 0, 0.1)',
                                fill: true,
                                tension: 0.4
                            }, {
                                label: 'Absent',
                                data: weeklyData.data.map(d => d.absent),
                                borderColor: '#F44336',
                                backgroundColor: 'rgba(244, 67, 54, 0.1)',
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { position: 'bottom' }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });
                }
                
                // Distribution Pie Chart
                const distResponse = await fetch('../../api/get_chart_data.php?type=distribution');
                const distData = await distResponse.json();
                
                if (distData.success && distData.data) {
                    const ctx2 = document.getElementById('distributionChart').getContext('2d');
                    new Chart(ctx2, {
                        type: 'doughnut',
                        data: {
                            labels: distData.data.labels,
                            datasets: distData.data.datasets
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { position: 'bottom' }
                            }
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading chart data:', error);
            }
        }
        
        // Load charts when page loads
        loadChartData();
    </script>
</body>
</html>