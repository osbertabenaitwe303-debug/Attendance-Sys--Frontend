<?php
// modules/teacher/dashboard.php - COMPLETE (Real-time Clock + Fixed Statistics)
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireTeacher();

require_once __DIR__ . '/../../models/Attendance.php';
require_once __DIR__ . '/../../config/db.php';

// Time-based greeting function
function getTeacherGreeting() {
    $hour = date('H');
    if ($hour >= 5 && $hour < 12) {
        return 'Good Morning';
    } elseif ($hour >= 12 && $hour < 17) {
        return 'Good Afternoon';
    } else {
        return 'Good Evening';
    }
}
$teacherGreeting = getTeacherGreeting();

$attendanceModel = new Attendance();
$database = new Database();
$conn = $database->getConnection();
$teacherId = $attendanceModel->getTeacherIdByUserId($_SESSION['user_id']);

// Get school information
$schoolQuery = "SELECT school_name, location_address, school_lat, school_lng, geofence_radius FROM school_settings WHERE id = 1";
$schoolStmt = $conn->prepare($schoolQuery);
$schoolStmt->execute();
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);

if (!$school) {
    $school = [
        'school_name' => 'St. Luke C.O.U Primary School',
        'location_address' => 'Kaburangire, Koranorya, Mbarara, Uganda',
        'school_lat' => -0.6072,
        'school_lng' => 30.6545,
        'geofence_radius' => 200
    ];
}

// Get today's attendance status
$todayStatus = $attendanceModel->getTodayStatus($teacherId);

// =============================================
// FIXED STATISTICS QUERIES
// =============================================

// Get current month and year
$currentMonth = date('m');
$currentYear = date('Y');

// Get present days count
$presentQuery = "SELECT COUNT(*) as count FROM attendance_records 
                 WHERE teacher_id = :teacher_id 
                 AND MONTH(check_in) = :month 
                 AND YEAR(check_in) = :year 
                 AND status = 'present'";
$presentStmt = $conn->prepare($presentQuery);
$presentStmt->bindParam(':teacher_id', $teacherId);
$presentStmt->bindParam(':month', $currentMonth);
$presentStmt->bindParam(':year', $currentYear);
$presentStmt->execute();
$presentDays = $presentStmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get absent days count
$absentQuery = "SELECT COUNT(*) as count FROM attendance_records 
                WHERE teacher_id = :teacher_id 
                AND MONTH(check_in) = :month 
                AND YEAR(check_in) = :year 
                AND status = 'absent'";
$absentStmt = $conn->prepare($absentQuery);
$absentStmt->bindParam(':teacher_id', $teacherId);
$absentStmt->bindParam(':month', $currentMonth);
$absentStmt->bindParam(':year', $currentYear);
$absentStmt->execute();
$absentDays = $absentStmt->fetch(PDO::FETCH_ASSOC)['count'];

// Calculate attendance rate
$totalMarked = $presentDays + $absentDays;
$attendanceRate = $totalMarked > 0 ? round(($presentDays / $totalMarked) * 100) : 0;

// Get pending leaves count
$leaveQuery = "SELECT COUNT(*) as count FROM leave_requests 
               WHERE teacher_id = :teacher_id AND status = 'pending'";
$leaveStmt = $conn->prepare($leaveQuery);
$leaveStmt->bindParam(':teacher_id', $teacherId);
$leaveStmt->execute();
$pendingLeaves = $leaveStmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get recent attendance records
$recentQuery = "SELECT * FROM attendance_records 
                WHERE teacher_id = :teacher_id 
                ORDER BY check_in DESC LIMIT 5";
$recentStmt = $conn->prepare($recentQuery);
$recentStmt->bindParam(':teacher_id', $teacherId);
$recentStmt->execute();
$recentRecords = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - <?php echo htmlspecialchars($school['school_name']); ?></title>
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
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
        
        .stat-card {
            transition: all 0.3s ease;
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
            border-top: 3px solid #6366f1;
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(99, 102, 241, 0.15);
        }
        
        .stat-card h3 {
            font-size: 36px;
            font-weight: 700;
            margin: 12px 0;
            color: #1e293b;
        }
        
        .stat-card p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
        }
        
        .stat-icon {
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .clock-container {
            background: rgba(99, 102, 241, 0.2);
            padding: 10px 18px;
            border-radius: 25px;
            font-family: 'Courier New', monospace;
            border: 1px solid rgba(99, 102, 241, 0.3);
            font-size: 12px;
        }
        
        .header-bg {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            position: relative;
            overflow: hidden;
        }
        
        .header-bg::before {
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
        
        .action-btn {
            transition: all 0.3s ease;
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .action-btn:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(99, 102, 241, 0.15);
            border-color: #6366f1;
        }
        
        .action-btn i {
            font-size: 32px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Mobile Menu Toggle -->
    <button id="menuToggle" class="fixed top-4 left-4 z-50 bg-indigo-600 text-white p-2 rounded-lg md:hidden hover:bg-indigo-700 transition">
        <i class="fas fa-bars text-xl"></i>
    </button>
    
    <!-- Vertical Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="p-6 border-b border-slate-700">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-school text-white text-lg"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold">Attendance</h2>
                    <p class="text-xs text-slate-400">Teacher Portal</p>
                </div>
            </div>
        </div>
        
        <div class="p-4 border-b border-slate-700">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-tie text-white text-sm"></i>
                </div>
                <div>
                    <p class="font-semibold text-sm"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Teacher'); ?></p>
                    <p class="text-xs text-slate-400">Teacher</p>
                </div>
            </div>
        </div>
        
        <nav class="flex-1 py-6 space-y-2">
            <a href="dashboard.php" class="sidebar-item active">
                <i class="fas fa-tachometer-alt w-5 mr-3"></i>
                <span>Dashboard</span>
            </a>
            <a href="checkin.php" class="sidebar-item">
                <i class="fas fa-camera w-5 mr-3"></i>
                <span>Check-in/Out</span>
            </a>
            <a href="attendance_history.php" class="sidebar-item">
                <i class="fas fa-history w-5 mr-3"></i>
                <span>My History</span>
            </a>
            <a href="leave.php" class="sidebar-item">
                <i class="fas fa-calendar-alt w-5 mr-3"></i>
                <span>Apply Leave</span>
            </a>
            <a href="leave_status.php" class="sidebar-item">
                <i class="fas fa-inbox w-5 mr-3"></i>
                <span>Leave Status</span>
                <?php if($pendingLeaves > 0): ?>
                    <span class="ml-auto bg-indigo-500 text-white text-xs px-2 py-1 rounded-full font-semibold"><?php echo $pendingLeaves; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php" class="sidebar-item">
                <i class="fas fa-user w-5 mr-3"></i>
                <span>My Profile</span>
            </a>
            <a href="../../api/logout.php" class="sidebar-item hover:border-l-red-500 mt-8">
                <i class="fas fa-sign-out-alt w-5 mr-3"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- School Header with REAL-TIME CLOCK integrated -->
        <div class="bg-gradient-to-r from-green-600 to-blue-600 text-white shadow-lg">
            <div class="px-8 py-4 flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-school text-2xl"></i>
                    <div>
                        <h1 class="text-xl font-bold"><?php echo htmlspecialchars($school['school_name']); ?></h1>
                        <p class="text-green-100 text-sm">
                            <i class="fas fa-map-marker-alt mr-1"></i>
                            <?php echo htmlspecialchars($school['location_address']); ?>
                        </p>
                    </div>
                </div>

            </div>
            <!-- GPS Info Bar -->
            <div class="px-8 pb-3 flex items-center space-x-4 text-xs text-green-100">
                <span><i class="fas fa-map-pin mr-1"></i> Lat: <?php echo $school['school_lat']; ?></span>
                <span><i class="fas fa-map-pin mr-1"></i> Lng: <?php echo $school['school_lng']; ?></span>
                <span><i class="fas fa-circle mr-1"></i> Geofence: <?php echo $school['geofence_radius']; ?>m</span>
                <span><i class="fas fa-flag-checkered mr-1"></i> Mbarara, Uganda</span>
            </div>
        </div>
        
<div class="p-8">
            <!-- Greeting Section with Big Clock -->
            <div class="mb-8">
                <div class="bg-gradient-to-r from-green-600 to-blue-600 rounded-2xl p-6 text-white">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <div>
                            <p class="text-lg opacity-90"><?php echo $teacherGreeting; ?>,</p>
                            <h1 class="text-3xl font-bold"><span class="text-white"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>!</h1>
                            <p class="text-sm opacity-80 mt-1">Here's your attendance summary</p>
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
            
            <!-- Today's Status Card -->
            <div class="mb-8">
                <?php if ($todayStatus && $todayStatus['check_in']): ?>
                    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm opacity-90">Today's Attendance</p>
                                <?php if ($todayStatus['check_out']): ?>
                                    <p class="text-2xl font-bold">✅ Completed</p>
                                    <p class="text-sm">Checked in: <?php echo date('h:i A', strtotime($todayStatus['check_in'])); ?></p>
                                    <p class="text-sm">Checked out: <?php echo date('h:i A', strtotime($todayStatus['check_out'])); ?></p>
                                <?php else: ?>
                                    <p class="text-2xl font-bold">⏳ Active</p>
                                    <p class="text-sm">Checked in at <?php echo date('h:i A', strtotime($todayStatus['check_in'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <i class="fas fa-calendar-check text-5xl opacity-50"></i>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-gradient-to-r from-yellow-500 to-orange-500 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm opacity-90">Today's Attendance</p>
                                <p class="text-2xl font-bold">❌ Not Checked In</p>
                                <p class="text-sm">Please check in using face recognition</p>
                            </div>
                            <i class="fas fa-clock text-5xl opacity-50"></i>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Statistics Cards - FIXED -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="stat-card">
                    <i class="fas fa-check-circle text-3xl text-green-500"></i>
                    <h3 class="text-green-600"><?php echo $presentDays; ?></h3>
                    <p>Present Days (This Month)</p>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-times-circle text-3xl text-red-500"></i>
                    <h3 class="text-red-600"><?php echo $absentDays; ?></h3>
                    <p>Absent Days</p>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-chart-line text-3xl text-blue-500"></i>
                    <h3 class="text-blue-600"><?php echo $attendanceRate; ?>%</h3>
                    <p>Attendance Rate</p>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-envelope text-3xl text-yellow-500"></i>
                    <h3 class="text-yellow-600"><?php echo $pendingLeaves; ?></h3>
                    <p>Pending Leaves</p>
                </div>
            </div>
            
            <!-- Recent Attendance -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Recent Attendance</h2>
                <?php if (empty($recentRecords)): ?>
                    <p class="text-gray-500 text-center py-4">No attendance records yet.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($recentRecords as $record): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium"><?php echo date('F j, Y', strtotime($record['check_in'])); ?></p>
                                    <p class="text-sm text-gray-500">Check-in: <?php echo date('h:i A', strtotime($record['check_in'])); ?></p>
                                    <?php if ($record['check_out']): ?>
                                        <p class="text-sm text-gray-500">Check-out: <?php echo date('h:i A', strtotime($record['check_out'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="px-3 py-1 rounded-full text-sm <?php echo $record['status'] == 'present' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                    <?php echo ucfirst($record['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <a href="checkin.php" class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white text-center hover:shadow-xl transition">
                    <i class="fas fa-camera text-4xl mb-3"></i>
                    <h3 class="text-xl font-bold">Check In/Out</h3>
                    <p class="text-sm opacity-90">Mark attendance with face recognition</p>
                </a>
                <a href="leave.php" class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white text-center hover:shadow-xl transition">
                    <i class="fas fa-calendar-alt text-4xl mb-3"></i>
                    <h3 class="text-xl font-bold">Apply for Leave</h3>
                    <p class="text-sm opacity-90">Submit leave request</p>
                </a>
            </div>
            
            <!-- Leave Balance Display -->
            <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-calendar-check mr-2 text-blue-500"></i>Leave Balance
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="leaveBalance">
                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                        <p class="text-sm text-gray-600">Annual Leave</p>
                        <p class="text-2xl font-bold text-blue-600" id="annualBalance">-</p>
                        <p class="text-xs text-gray-500">days remaining</p>
                    </div>
                    <div class="text-center p-4 bg-red-50 rounded-lg">
                        <p class="text-sm text-gray-600">Sick Leave</p>
                        <p class="text-2xl font-bold text-red-600" id="sickBalance">-</p>
                        <p class="text-xs text-gray-500">days remaining</p>
                    </div>
                    <div class="text-center p-4 bg-purple-50 rounded-lg">
                        <p class="text-sm text-gray-600">Compassionate Leave</p>
                        <p class="text-2xl font-bold text-purple-600" id="compassionateBalance">-</p>
                        <p class="text-xs text-gray-500">days remaining</p>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Chart -->
            <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-chart-bar mr-2 text-green-500"></i>Attendance Overview
                </h2>
                <canvas id="teacherChart" height="100"></canvas>
            </div>
        </div>
    </div>
    
<script>
        // =============================================
        // REAL-TIME CLOCK FUNCTION - updates big clock
        // =============================================
        function updateRealTimeClock() {
            const now = new Date();
            
            // Format time: HH:MM:SS AM/PM
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            const timeStr = `${String(hours).padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
            
            // Format date: Month Day, Year
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const dateStr = `${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
            
            // Update big clock in greeting section
            if (document.getElementById('bigClock')) {
                document.getElementById('bigClock').innerHTML = timeStr;
            }
            if (document.getElementById('bigDate')) {
                document.getElementById('bigDate').innerHTML = dateStr;
            }
        }
        
        // Initialize clock and update every second
        updateRealTimeClock();
        setInterval(updateRealTimeClock, 1000);
        
        // Mobile menu toggle
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
        
        // Load Leave Balance
        async function loadLeaveBalance() {
            try {
                const teacherId = <?php echo json_encode($teacherId); ?>;
                const response = await fetch(`../../api/leave_balance.php?teacher_id=${teacherId}`);
                const data = await response.json();
                
                if (data.success && data.data) {
                    document.getElementById('annualBalance').textContent = data.data.annual_leave_remaining ?? '-';
                    document.getElementById('sickBalance').textContent = data.data.sick_leave_remaining ?? '-';
                    document.getElementById('compassionateBalance').textContent = data.data.compassionate_leave_remaining ?? '-';
                }
            } catch (error) {
                console.error('Error loading leave balance:', error);
            }
        }
        
        // Load Teacher Chart
        async function loadTeacherChart() {
            try {
                const teacherId = <?php echo json_encode($teacherId); ?>;
                const response = await fetch(`../../api/get_chart_data.php?type=teacher_summary&teacher_id=${teacherId}`);
                const data = await response.json();
                
                if (data.success && data.data && data.data.summary) {
                    const ctx = document.getElementById('teacherChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: ['Present', 'Late', 'Absent'],
                            datasets: [{
                                label: 'This Month',
                                data: [
                                    data.data.summary.present,
                                    data.data.summary.late,
                                    data.data.summary.absent
                                ],
                                backgroundColor: ['#4CAF50', '#FF9800', '#F44336']
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading teacher chart:', error);
            }
        }
        
        // Initialize
        loadLeaveBalance();
        loadTeacherChart();
    </script>
</body>
</html>