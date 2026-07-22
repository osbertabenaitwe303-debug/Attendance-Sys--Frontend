<?php
// modules/admin/attendance.php - View all attendance records
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireAdmin();

require_once __DIR__ . '/../../models/Attendance.php';
$attendanceModel = new Attendance();

// Get filter parameters
$date = $_GET['date'] ?? date('Y-m-d');
$teacher_id = $_GET['teacher_id'] ?? '';

// Get all teachers for filter
$teachers = $attendanceModel->getAllTeachers();

// Get attendance records
$records = $attendanceModel->getAttendanceByDateRange($date, $date, $teacher_id ?: null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, #1e3a8a, #5b21b6);
            color: white;
            z-index: 100;
        }
        .main-content {
            margin-left: 280px;
        }
        .sidebar-item {
            transition: all 0.2s;
            display: flex;
            align-items: center;
            padding: 12px 24px;
        }
        .sidebar-item:hover {
            background: rgba(255,255,255,0.1);
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="p-6 border-b border-white/20">
            <h2 class="text-xl font-bold">Attendance System</h2>
            <p class="text-xs text-blue-200">Admin Panel</p>
        </div>
        <div class="p-4 border-b border-white/20">
            <p class="font-semibold"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
            <p class="text-xs text-blue-200">Administrator</p>
        </div>
        <nav class="py-4">
            <a href="dashboard.php" class="sidebar-item"><i class="fas fa-tachometer-alt w-5 mr-3"></i> Dashboard</a>
            <a href="manage_teachers.php" class="sidebar-item"><i class="fas fa-chalkboard-teacher w-5 mr-3"></i> Manage Teachers</a>
            <a href="attendance.php" class="sidebar-item bg-white/10"><i class="fas fa-calendar-check w-5 mr-3"></i> Attendance Records</a>
            <a href="reports.php" class="sidebar-item"><i class="fas fa-chart-bar w-5 mr-3"></i> Reports</a>
            <a href="leave_requests.php" class="sidebar-item"><i class="fas fa-envelope w-5 mr-3"></i> Leave Requests</a>
            <a href="gps_settings.php" class="sidebar-item"><i class="fas fa-map-marker-alt w-5 mr-3"></i> GPS Settings</a>
            <a href="../../api/logout.php" class="sidebar-item mt-8 hover:bg-red-600"><i class="fas fa-sign-out-alt w-5 mr-3"></i> Logout</a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="p-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Attendance Records</h1>
            
            <!-- Filter Form -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">Date</label>
                        <input type="date" name="date" value="<?php echo $date; ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">Teacher</label>
                        <select name="teacher_id" class="w-full px-3 py-2 border rounded-lg">
                            <option value="">All Teachers</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo $teacher_id == $teacher['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">Filter</button>
                    </div>
                </form>
            </div>
            
            <!-- Records Table -->
            <div class="bg-white rounded-xl shadow-2xl overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4">
                    <h2 class="text-xl font-bold text-white">Attendance for <?php echo date('F j, Y', strtotime($date)); ?></h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">Teacher</th>
                                <th class="px-6 py-3 text-left">Check In</th>
                                <th class="px-6 py-3 text-left">Check Out</th>
                                <th class="px-6 py-3 text-left">Location</th>
                                <th class="px-6 py-3 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php if (empty($records)): ?>
                                <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No attendance records for this date</td></tr>
                            <?php else: ?>
                                <?php foreach ($records as $record): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <div class="font-medium"><?php echo htmlspecialchars($record['name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($record['teacher_code']); ?></div>
                                        </td>
                                        <td class="px-6 py-4"><?php echo date('h:i A', strtotime($record['check_in'])); ?></td>
                                        <td class="px-6 py-4"><?php echo $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '-'; ?></td>
                                        <td class="px-6 py-4">
                                            <?php if ($record['latitude'] && $record['longitude']): ?>
                                                <span class="text-sm"><?php echo $record['latitude']; ?>, <?php echo $record['longitude']; ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 rounded text-xs <?php echo $record['status'] == 'present' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>