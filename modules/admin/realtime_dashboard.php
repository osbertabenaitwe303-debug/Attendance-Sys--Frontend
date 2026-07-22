<?php
// modules/admin/realtime_dashboard.php - Real-time Live Dashboard
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireAdmin();

require_once __DIR__ . '/../../config/db.php';

$database = new Database();
$conn = $database->getConnection();

// Get initial data
$sql = "SELECT 
    COUNT(*) as total_teachers
FROM teachers";
$stmt = $conn->prepare($sql);
$stmt->execute();
$totalTeachers = $stmt->fetch(PDO::FETCH_ASSOC)['total_teachers'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-time Dashboard - ST.LUKE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 280px; background: linear-gradient(135deg, #1e3a8a, #5b21b6); color: white; z-index: 100; }
        .main-content { margin-left: 280px; }
        .sidebar-item { transition: all 0.2s; display: flex; align-items: center; padding: 12px 24px; }
        .sidebar-item:hover { background: rgba(255,255,255,0.1); }
        .live-indicator { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } }
    </style>
</head>
<body class="bg-gray-100">
    <button id="menuToggle" class="fixed top-4 left-4 z-50 bg-blue-600 text-white p-2 rounded-lg md:hidden">
        <i class="fas fa-bars text-xl"></i>
    </button>
    
    <div class="sidebar" id="sidebar">
        <div class="p-6 border-b border-white/20">
            <div class="flex items-center space-x-3">
                <i class="fas fa-chalkboard-teacher text-3xl"></i>
                <div>
                    <h2 class="text-xl font-bold">Attendance System</h2>
                    <p class="text-xs text-blue-200">Admin Panel</p>
                </div>
            </div>
        </div>
        <div class="p-4 border-b border-white/20">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-shield text-xl"></i>
                </div>
                <div>
                    <p class="font-semibold"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></p>
                    <p class="text-xs text-blue-200">Administrator</p>
                </div>
            </div>
        </div>
        <nav class="flex-1 py-6">
            <a href="dashboard.php" class="sidebar-item"><i class="fas fa-tachometer-alt w-5 mr-3"></i> Dashboard</a>
            <a href="realtime_dashboard.php" class="sidebar-item active bg-white/10"><i class="fas fa-bolt w-5 mr-3"></i> Live Dashboard</a>
            <a href="manage_teachers.php" class="sidebar-item"><i class="fas fa-chalkboard-teacher w-5 mr-3"></i> Manage Teachers</a>
            <a href="attendance.php" class="sidebar-item"><i class="fas fa-calendar-check w-5 mr-3"></i> Attendance</a>
            <a href="reports.php" class="sidebar-item"><i class="fas fa-chart-bar w-5 mr-3"></i> Reports</a>
            <a href="leave_requests.php" class="sidebar-item"><i class="fas fa-envelope w-5 mr-3"></i> Leave Requests</a>
            <a href="gps_settings.php" class="sidebar-item"><i class="fas fa-map-marker-alt w-5 mr-3"></i> GPS Settings</a>
            <a href="audit_logs.php" class="sidebar-item"><i class="fas fa-history w-5 mr-3"></i> Audit Logs</a>
            <a href="../../api/logout.php" class="sidebar-item mt-8 hover:bg-red-600"><i class="fas fa-sign-out-alt w-5 mr-3"></i> Logout</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="p-8">
            <!-- Header with Live Indicator -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-bolt text-yellow-500 mr-2"></i>Real-time Dashboard
                    </h1>
                    <p class="text-gray-600">Live attendance status - Updates every 30 seconds</p>
                </div>
                <div class="flex items-center space-x-2 bg-green-100 px-4 py-2 rounded-full">
                    <span class="w-3 h-3 bg-green-500 rounded-full live-indicator"></span>
                    <span class="text-green-700 font-semibold">LIVE</span>
                    <span id="lastUpdate" class="text-sm text-gray-500 ml-2">--:--:--</span>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Teachers</p>
                            <p class="text-3xl font-bold" id="totalTeachers"><?php echo $totalTeachers; ?></p>
                        </div>
                        <i class="fas fa-users text-3xl text-blue-500"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Present</p>
                            <p class="text-3xl font-bold text-green-600" id="presentCount">-</p>
                        </div>
                        <i class="fas fa-check-circle text-3xl text-green-500"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Late</p>
                            <p class="text-3xl font-bold text-yellow-600" id="lateCount">-</p>
                        </div>
                        <i class="fas fa-clock text-3xl text-yellow-500"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Absent</p>
                            <p class="text-3xl font-bold text-red-600" id="absentCount">-</p>
                        </div>
                        <i class="fas fa-times-circle text-3xl text-red-500"></i>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Live Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6 lg:col-span-2">
                    <h3 class="font-bold text-gray-800 mb-4">Live Attendance Distribution</h3>
                    <canvas id="liveChart" height="150"></canvas>
                </div>
                
                <!-- Recent Activity -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="font-bold text-gray-800 mb-4">Recent Activity</h3>
                    <div id="recentActivity" class="space-y-3 max-h-64 overflow-y-auto">
                        <p class="text-gray-500 text-center">Loading...</p>
                    </div>
                </div>
            </div>
            
            <!-- Live Attendance Table -->
            <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
                <h3 class="font-bold text-gray-800 mb-4">Live Attendance Records</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teacher</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check In</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check Out</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTable" class="divide-y divide-gray-200">
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let liveChart;
        
        // Initialize chart
        function initChart() {
            const ctx = document.getElementById('liveChart').getContext('2d');
            liveChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Present', 'Late', 'Absent', 'Not Checked In'],
                    datasets: [{
                        data: [0, 0, 0, 0],
                        backgroundColor: ['#22c55e', '#eab308', '#ef4444', '#9ca3af']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
        
        // Fetch live data
        async function fetchLiveData() {
            try {
                const response = await fetch('../../api/realtime_dashboard.php');
                const data = await response.json();
                
                if (data.success) {
                    // Update statistics
                    document.getElementById('presentCount').textContent = data.statistics.present;
                    document.getElementById('lateCount').textContent = data.statistics.late;
                    document.getElementById('absentCount').textContent = data.statistics.absent;
                    document.getElementById('totalTeachers').textContent = data.statistics.total_teachers;
                    document.getElementById('lastUpdate').textContent = new Date(data.timestamp).toLocaleTimeString();
                    
                    // Update chart
                    liveChart.data.datasets[0].data = [
                        data.statistics.present,
                        data.statistics.late,
                        data.statistics.absent,
                        data.statistics.not_checked_in
                    ];
                    liveChart.update();
                    
                    // Update table
                    const tableBody = document.getElementById('attendanceTable');
                    tableBody.innerHTML = data.records.map(record => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium">${record.teacher_name}</td>
                            <td class="px-4 py-3 text-gray-500">${record.teacher_code}</td>
                            <td class="px-4 py-3">${record.check_in ? new Date(record.check_in).toLocaleTimeString() : '-'}</td>
                            <td class="px-4 py-3">${record.check_out ? new Date(record.check_out).toLocaleTimeString() : '<span class="text-yellow-600">Active</span>'}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded text-xs font-medium ${record.status === 'present' ? 'bg-green-100 text-green-800' : record.status === 'late' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'}">
                                    ${record.status}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">${record.time_ago}</td>
                        </tr>
                    `).join('');
                    
                    // Update recent activity
                    const activityDiv = document.getElementById('recentActivity');
                    activityDiv.innerHTML = data.recent_activity.map(activity => `
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-user-check text-green-500"></i>
                                <span class="text-sm">${activity.teacher_name}</span>
                            </div>
                            <span class="text-xs text-gray-500">${activity.time_ago}</span>
                        </div>
                    `).join('');
                }
            } catch (error) {
                console.error('Error fetching live data:', error);
            }
        }
        
        // Initialize
        initChart();
        fetchLiveData();
        
        // Auto-refresh every 30 seconds
        setInterval(fetchLiveData, 30000);
        
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
    </script>
</body>
</html>