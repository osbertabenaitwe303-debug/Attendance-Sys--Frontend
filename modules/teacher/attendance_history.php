<?php
// modules/teacher/attendance_history.php
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireTeacher();

require_once __DIR__ . '/../../models/Attendance.php';
$attendanceModel = new Attendance();
$teacherId = $attendanceModel->getTeacherIdByUserId($_SESSION['user_id']);

// Get filter parameters
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// Get attendance records
$records = $attendanceModel->getAttendanceByDateRange("$year-$month-01", "$year-$month-31", $teacherId);
$stats = $attendanceModel->getTeacherStats($teacherId, $month, $year);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History - Teacher Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-b from-blue-900 to-purple-900 text-white min-h-screen fixed">
            <div class="p-6"><h2 class="text-xl font-bold">Attendance System</h2></div>
            <div class="p-4 border-t border-b"><p><?php echo htmlspecialchars($_SESSION['user_name']); ?></p></div>
            <nav class="py-4">
                <a href="dashboard.php" class="block px-6 py-2 hover:bg-white/10"><i class="fas fa-tachometer-alt w-5 mr-2"></i> Dashboard</a>
                <a href="checkin.php" class="block px-6 py-2 hover:bg-white/10"><i class="fas fa-camera w-5 mr-2"></i> Face Check-in</a>
                <a href="attendance_history.php" class="block px-6 py-2 bg-white/10"><i class="fas fa-history w-5 mr-2"></i> My History</a>
                <a href="leave.php" class="block px-6 py-2 hover:bg-white/10"><i class="fas fa-calendar-alt w-5 mr-2"></i> Apply Leave</a>
                <a href="../../api/logout.php" class="block px-6 py-2 mt-8 hover:bg-red-600"><i class="fas fa-sign-out-alt w-5 mr-2"></i> Logout</a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="ml-64 flex-1 p-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">My Attendance History</h1>
            
            <!-- Stats Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-lg shadow p-4"><p class="text-gray-500 text-sm">Total Days</p><p class="text-2xl font-bold"><?php echo $stats['total_days'] ?? 0; ?></p></div>
                <div class="bg-white rounded-lg shadow p-4"><p class="text-gray-500 text-sm">Present</p><p class="text-2xl font-bold text-green-600"><?php echo $stats['present_days'] ?? 0; ?></p></div>
                <div class="bg-white rounded-lg shadow p-4"><p class="text-gray-500 text-sm">Absent</p><p class="text-2xl font-bold text-red-600"><?php echo $stats['absent_days'] ?? 0; ?></p></div>
                <div class="bg-white rounded-lg shadow p-4"><p class="text-gray-500 text-sm">Percentage</p><p class="text-2xl font-bold text-blue-600"><?php echo $stats['percentage'] ?? 0; ?>%</p></div>
            </div>
            
            <!-- Filter -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <form method="GET" class="flex gap-4 items-end">
                    <div><label class="block text-sm text-gray-600 mb-1">Month</label><select name="month" class="border rounded px-3 py-2"><option value="01" <?php echo $month=='01'?'selected':''; ?>>January</option><option value="02" <?php echo $month=='02'?'selected':''; ?>>February</option><option value="03" <?php echo $month=='03'?'selected':''; ?>>March</option><option value="04" <?php echo $month=='04'?'selected':''; ?>>April</option><option value="05" <?php echo $month=='05'?'selected':''; ?>>May</option><option value="06" <?php echo $month=='06'?'selected':''; ?>>June</option><option value="07" <?php echo $month=='07'?'selected':''; ?>>July</option><option value="08" <?php echo $month=='08'?'selected':''; ?>>August</option><option value="09" <?php echo $month=='09'?'selected':''; ?>>September</option><option value="10" <?php echo $month=='10'?'selected':''; ?>>October</option><option value="11" <?php echo $month=='11'?'selected':''; ?>>November</option><option value="12" <?php echo $month=='12'?'selected':''; ?>>December</option></select></div>
                    <div><label class="block text-sm text-gray-600 mb-1">Year</label><select name="year" class="border rounded px-3 py-2"><option value="2023">2023</option><option value="2024" selected>2024</option><option value="2025">2025</option></select></div>
                    <div><button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">Filter</button></div>
                </form>
            </div>
            
            <!-- Records Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left">Date</th><th class="px-6 py-3 text-left">Check In</th><th class="px-6 py-3 text-left">Check Out</th><th class="px-6 py-3 text-left">Status</th></tr></thead>
                    <tbody>
                        <?php if(empty($records)): ?><tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No attendance records found</td></tr><?php endif; ?>
                        <?php foreach($records as $record): ?>
                        <tr class="border-t"><td class="px-6 py-3"><?php echo date('M d, Y', strtotime($record['check_in'])); ?></td><td class="px-6 py-3"><?php echo date('h:i A', strtotime($record['check_in'])); ?></td><td class="px-6 py-3"><?php echo $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '-'; ?></td><td class="px-6 py-3"><span class="px-2 py-1 rounded text-xs <?php echo $record['status']=='present'?'bg-green-100 text-green-800':'bg-red-100 text-red-800'; ?>"><?php echo ucfirst($record['status']); ?></span></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>