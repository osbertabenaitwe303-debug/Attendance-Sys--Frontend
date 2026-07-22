<?php
// modules/admin/reports.php - Enhanced Attendance Reports with Search, Filter & Monthly View
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireAdmin();

require_once __DIR__ . '/../../models/Attendance.php';
$attendanceModel = new Attendance();

// Get filter parameters
$view = $_GET['view'] ?? 'daily'; // daily, monthly, teacher
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$teacher_id = $_GET['teacher_id'] ?? '';

// Quick date filters
$quick_filter = $_GET['quick'] ?? '';
if ($quick_filter) {
    switch($quick_filter) {
        case 'today':
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');
            break;
        case 'week':
            $start_date = date('Y-m-d', strtotime('monday this week'));
            $end_date = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'month':
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-t');
            break;
    }
} else {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');
}

// Monthly view
$month_view = $_GET['month'] ?? date('m');
$year_view = $_GET['year'] ?? date('Y');

// Get data based on view
$teachers = $attendanceModel->getAllTeachers();

if ($view == 'monthly') {
    $records = $attendanceModel->getMonthlyAttendance($year_view, $month_view, $teacher_id ?: null);
    $stats = $attendanceModel->getMonthlyStats($year_view, $month_view);
} elseif ($view == 'teacher') {
    $records = $attendanceModel->getTeacherMonthlySummary($year_view, $month_view);
} elseif ($search || $status_filter) {
    $records = $attendanceModel->searchAttendance($search, $start_date, $end_date, $status_filter ?: null);
} else {
    $records = $attendanceModel->getAttendanceByDateRange($start_date, $end_date, $teacher_id ?: null);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            position: fixed; left: 0; top: 0; height: 100vh; width: 280px;
            background: linear-gradient(135deg, #1e3a8a, #5b21b6); color: white; z-index: 100;
        }
        .main-content { margin-left: 280px; }
        .sidebar-item { transition: all 0.2s; display: flex; align-items: center; padding: 12px 24px; }
        .sidebar-item:hover { background: rgba(255,255,255,0.1); }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } }
    </style>
</head>
<body class="bg-gray-100">
    <button id="menuToggle" class="fixed top-4 left-4 z-50 bg-blue-600 text-white p-2 rounded-lg md:hidden">
        <i class="fas fa-bars text-xl"></i>
    </button>
    
    <div class="sidebar" id="sidebar">
        <div class="p-6 border-b border-white/20"><h2 class="text-xl font-bold">Attendance System</h2></div>
        <div class="p-4 border-b border-white/20"><p class="font-semibold"><?php echo $_SESSION['user_name']; ?></p></div>
        <nav class="py-4">
            <a href="dashboard.php" class="sidebar-item"><i class="fas fa-tachometer-alt w-5 mr-3"></i> Dashboard</a>
            <a href="manage_teachers.php" class="sidebar-item"><i class="fas fa-chalkboard-teacher w-5 mr-3"></i> Manage Teachers</a>
            <a href="attendance.php" class="sidebar-item"><i class="fas fa-calendar-check w-5 mr-3"></i> Attendance</a>
            <a href="reports.php" class="sidebar-item bg-white/10"><i class="fas fa-chart-bar w-5 mr-3"></i> Reports</a>
            <a href="leave_requests.php" class="sidebar-item"><i class="fas fa-envelope w-5 mr-3"></i> Leave Requests</a>
            <a href="gps_settings.php" class="sidebar-item"><i class="fas fa-map-marker-alt w-5 mr-3"></i> GPS Settings</a>
            <a href="../../api/logout.php" class="sidebar-item mt-8 hover:bg-red-600"><i class="fas fa-sign-out-alt w-5 mr-3"></i> Logout</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="p-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Attendance Reports</h1>
                <div class="flex gap-2">
                    <button onclick="exportToPDF()" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">
                        <i class="fas fa-file-pdf mr-2"></i> Export PDF
                    </button>
                    <button onclick="exportToCSV()" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">
                        <i class="fas fa-file-csv mr-2"></i> Export CSV
                    </button>
                    <button onclick="window.print()" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                </div>
            </div>
            
<!-- View Tabs -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="flex flex-wrap gap-2 mb-4">
                    <a href="?view=daily<?php echo $quick_filter ? '&quick='.$quick_filter : ''; ?>" class="px-4 py-2 rounded-lg <?php echo $view=='daily'?'bg-blue-600 text-white':'bg-gray-200 text-gray-700'; ?>">
                        <i class="fas fa-list mr-2"></i>Daily
                    </a>
                    <a href="?view=monthly&month=<?php echo $month_view; ?>&year=<?php echo $year_view; ?>" class="px-4 py-2 rounded-lg <?php echo $view=='monthly'?'bg-blue-600 text-white':'bg-gray-200 text-gray-700'; ?>">
                        <i class="fas fa-calendar-alt mr-2"></i>Monthly
                    </a>
                    <a href="?view=teacher&month=<?php echo $month_view; ?>&year=<?php echo $year_view; ?>" class="px-4 py-2 rounded-lg <?php echo $view=='teacher'?'bg-blue-600 text-white':'bg-gray-200 text-gray-700'; ?>">
                        <i class="fas fa-users mr-2"></i>Teacher Wise
                    </a>
                </div>
                
                <!-- Quick Filters -->
                <div class="flex flex-wrap gap-2 mb-4">
                    <a href="?<?php echo $view=='monthly' || $view=='teacher' ? 'view='.$view.'&month='.$month_view.'&year='.$year_view : 'view=daily'; ?>&quick=today" class="px-3 py-1 rounded text-sm <?php echo $quick_filter=='today'?'bg-blue-100 text-blue-700':'bg-gray-100 text-gray-600'; ?>">Today</a>
                    <a href="?<?php echo $view=='monthly' || $view=='teacher' ? 'view='.$view.'&month='.$month_view.'&year='.$year_view : 'view=daily'; ?>&quick=week" class="px-3 py-1 rounded text-sm <?php echo $quick_filter=='week'?'bg-blue-100 text-blue-700':'bg-gray-100 text-gray-600'; ?>">This Week</a>
                    <a href="?<?php echo $view=='monthly' || $view=='teacher' ? 'view='.$view.'&month='.$month_view.'&year='.$year_view : 'view=daily'; ?>&quick=month" class="px-3 py-1 rounded text-sm <?php echo $quick_filter=='month'?'bg-blue-100 text-blue-700':'bg-gray-100 text-gray-600'; ?>">This Month</a>
                </div>
                
                <!-- Search & Filter Form -->
                <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <?php if($view != 'monthly' && $view != 'teacher'): ?>
                    <div><label class="block text-gray-700 font-bold mb-1 text-sm">Search Name</label><input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search teacher..." class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                    <div><label class="block text-gray-700 font-bold mb-1 text-sm">Start Date</label><input type="date" name="start_date" value="<?php echo $start_date; ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                    <div><label class="block text-gray-700 font-bold mb-1 text-sm">End Date</label><input type="date" name="end_date" value="<?php echo $end_date; ?>" class="w-full px-3 py-2 border rounded-lg text-sm"></div>
                    <div><label class="block text-gray-700 font-bold mb-1 text-sm">Status</label><select name="status" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">All Status</option><option value="present" <?php echo $status_filter=='present'?'selected':''; ?>>Present</option><option value="absent" <?php echo $status_filter=='absent'?'selected':''; ?>>Absent</option><option value="late" <?php echo $status_filter=='late'?'selected':''; ?>>Late</option></select></div>
                    <div><label class="block text-gray-700 font-bold mb-1 text-sm">Teacher</label><select name="teacher_id" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">All Teachers</option><?php foreach($teachers as $t): ?><option value="<?php echo $t['id']; ?>" <?php echo $teacher_id == $t['id'] ? 'selected' : ''; ?>><?php echo $t['name']; ?></option><?php endforeach; ?></select></div>
                    <?php else: ?>
                    <div><label class="block text-gray-700 font-bold mb-1 text-sm">Month</label><select name="month" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="1" <?php echo $month_view==1?'selected':''; ?>>January</option><option value="2" <?php echo $month_view==2?'selected':''; ?>>February</option><option value="3" <?php echo $month_view==3?'selected':''; ?>>March</option><option value="4" <?php echo $month_view==4?'selected':''; ?>>April</option><option value="5" <?php echo $month_view==5?'selected':''; ?>>May</option><option value="6" <?php echo $month_view==6?'selected':''; ?>>June</option><option value="7" <?php echo $month_view==7?'selected':''; ?>>July</option><option value="8" <?php echo $month_view==8?'selected':''; ?>>August</option><option value="9" <?php echo $month_view==9?'selected':''; ?>>September</option><option value="10" <?php echo $month_view==10?'selected':''; ?>>October</option><option value="11" <?php echo $month_view==11?'selected':''; ?>>November</option><option value="12" <?php echo $month_view==12?'selected':''; ?>>December</option></select></div>
                    <div><label class="block text-gray-700 font-bold mb-1 text-sm">Year</label><select name="year" class="w-full px-3 py-2 border rounded-lg text-sm"><?php for($y=date('Y');$y>=date('Y')-5;$y--): ?><option value="<?php echo $y; ?>" <?php echo $year_view==$y?'selected':''; ?>><?php echo $y; ?></option><?php endfor; ?></select></div>
                    <div><label class="block text-gray-700 font-bold mb-1 text-sm">Teacher</label><select name="teacher_id" class="w-full px-3 py-2 border rounded-lg text-sm"><option value="">All Teachers</option><?php foreach($teachers as $t): ?><option value="<?php echo $t['id']; ?>" <?php echo $teacher_id == $t['id'] ? 'selected' : ''; ?>><?php echo $t['name']; ?></option><?php endforeach; ?></select></div>
                    <?php endif; ?>
                    <input type="hidden" name="view" value="<?php echo $view; ?>">
                    <div class="flex items-end"><button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg text-sm">Filter</button></div>
                </form>
            </div>
            
<!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <?php
                if ($view == 'monthly') {
                    $total = $stats['total_records'] ?? 0;
                    $present = $stats['present'] ?? 0;
                    $absent = $stats['absent'] ?? 0;
                    $late = $stats['late'] ?? 0;
                    ?>
                    <div class="bg-white rounded-xl shadow p-4"><p class="text-gray-500">Total Records</p><p class="text-2xl font-bold"><?php echo $total; ?></p></div>
                    <div class="bg-white rounded-xl shadow p-4"><p class="text-gray-500">Present</p><p class="text-2xl font-bold text-green-600"><?php echo $present; ?></p></div>
                    <div class="bg-white rounded-xl shadow p-4"><p class="text-gray-500">Absent</p><p class="text-2xl font-bold text-red-600"><?php echo $absent; ?></p></div>
                    <div class="bg-white rounded-xl shadow p-4"><p class="text-gray-500">Attendance Rate</p><p class="text-2xl font-bold text-blue-600"><?php echo $total > 0 ? round(($present/$total)*100) : 0; ?>%</p></div>
                    <?php
                } elseif ($view == 'teacher') {
                    $total = count($records);
                    $present = 0; $absent = 0; $late = 0;
                    foreach($records as $r) { $present += $r['present']; $absent += $r['absent']; $late += $r['late']; }
                    ?>
                    <div class="bg-white rounded-xl shadow p-4"><p class="text-gray-500">Total Teachers</p><p class="text-2xl font-bold"><?php echo $total; ?></p></div>
                    <div class="bg-white rounded-xl shadow p-4"><p class="text-gray-500">Total Present</p><p class="text-2xl font-bold text-green-600"><?php echo $present; ?></p></div>
                    <div class="bg-white rounded-xl shadow p-4"><p class="text-gray-500">Total Absent</p><p class="text-2xl font-bold text-red-600"><?php echo $absent; ?></p></div>
                    <div class="bg-white rounded-xl shadow p-4"><p class="text-gray-500">Avg Attendance</p><p class="text-2xl font-bold text-blue-600"><?php echo $total > 0 ? round(($present/($present+$absent))*100) : 0; ?>%</p></div>
                    <?php
                } else {
                    $total = count($records);
                    $present = count(array_filter($records, fn($r) => (isset($r['status']) && $r['status'] == 'present')));
                    $absent = count(array_filter($records, fn($r) => (isset($r['status']) && $r['status'] == 'absent')));
                    $late = count(array_filter($records, fn($r) => (isset($r['status']) && $r['status'] == 'late')));
                    ?>
                    <div class="bg-white rounded-xl shadow p-4"><p class="text-gray-500">Total Records</p><p class="text-2xl font-bold"><?php echo $total; ?></p></div>
                    <div class="bg-white rounded-xl shadow p-4"><p class="text-gray-500">Present</p><p class="text-2xl font-bold text-green-600"><?php echo $present; ?></p></div>
                    <div class="bg-white rounded-xl shadow p-4"><p class="text-gray-500">Absent</p><p class="text-2xl font-bold text-red-600"><?php echo $absent; ?></p></div>
                    <div class="bg-white rounded-xl shadow p-4"><p class="text-gray-500">Attendance Rate</p><p class="text-2xl font-bold text-blue-600"><?php echo $total > 0 ? round(($present/$total)*100) : 0; ?>%</p></div>
                    <?php
                }
                ?>
            </div>
            
            <!-- Chart -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <h3 class="font-bold text-gray-800 mb-4">Attendance Overview</h3>
                <canvas id="attendanceChart" height="100"></canvas>
            </div>
            
<!-- Report Table - Different views -->
            <div class="bg-white rounded-xl shadow-2xl overflow-hidden" id="reportTable">
                <?php if($view == 'monthly'): ?>
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-3"><h2 class="text-white font-bold">Monthly Summary (<?php echo date('F Y', mktime(0,0,0,$month_view,1,$year_view)); ?>)</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3">Date</th><th class="px-6 py-3">Total</th><th class="px-6 py-3">Present</th><th class="px-6 py-3">Absent</th><th class="px-6 py-3">Late</th><th class="px-6 py-3">%</th></tr></thead>
                        <tbody>
                            <?php if(empty($records)): ?><tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No records found</td></tr><?php endif; ?>
                            <?php foreach($records as $r): $totalDay = $r['present']+$r['absent']+$r['late']; ?>
                            <tr class="border-t"><td class="px-6 py-3"><?php echo date('M d, Y', strtotime($r['date'])); ?></td>
                            <td class="px-6 py-3"><?php echo $r['total_checkins']; ?></td>
                            <td class="px-6 py-3 text-green-600"><?php echo $r['present']; ?></td>
                            <td class="px-6 py-3 text-red-600"><?php echo $r['absent']; ?></td>
                            <td class="px-6 py-3 text-yellow-600"><?php echo $r['late']; ?></td>
                            <td class="px-6 py-3"><?php echo $totalDay > 0 ? round(($r['present']/$totalDay)*100) : 0; ?>%</td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php elseif($view == 'teacher'): ?>
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-3"><h2 class="text-white font-bold">Teacher Monthly Summary (<?php echo date('F Y', mktime(0,0,0,$month_view,1,$year_view)); ?>)</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3">Teacher</th><th class="px-6 py-3">Total Days</th><th class="px-6 py-3">Present</th><th class="px-6 py-3">Absent</th><th class="px-6 py-3">Late</th><th class="px-6 py-3">%</th></tr></thead>
                        <tbody>
                            <?php if(empty($records)): ?><tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No records found</td></tr><?php endif; ?>
                            <?php foreach($records as $r): $totalT = $r['present']+$r['absent']+$r['late']; ?>
                            <tr class="border-t"><td class="px-6 py-3 font-medium"><?php echo $r['teacher_name']; ?></td>
                            <td class="px-6 py-3"><?php echo $r['total_days']; ?></td>
                            <td class="px-6 py-3 text-green-600"><?php echo $r['present']; ?></td>
                            <td class="px-6 py-3 text-red-600"><?php echo $r['absent']; ?></td>
                            <td class="px-6 py-3 text-yellow-600"><?php echo $r['late']; ?></td>
                            <td class="px-6 py-3"><?php echo $totalT > 0 ? round(($r['present']/$totalT)*100) : 0; ?>%</td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-3"><h2 class="text-white font-bold">Attendance Records (<?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>)</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3">Date</th><th class="px-6 py-3">Teacher</th><th class="px-6 py-3">Check In</th><th class="px-6 py-3">Check Out</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Location</th></tr></thead>
                        <tbody>
                            <?php if(empty($records)): ?><tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No records found</td></tr><?php endif; ?>
                            <?php foreach($records as $r): ?>
                            <tr class="border-t"><td class="px-6 py-3"><?php echo date('M d, Y', strtotime($r['check_in'])); ?></td>
                            <td class="px-6 py-3"><?php echo $r['name']; ?></td>
                            <td class="px-6 py-3"><?php echo date('h:i A', strtotime($r['check_in'])); ?></td>
                            <td class="px-6 py-3"><?php echo $r['check_out'] ? date('h:i A', strtotime($r['check_out'])) : '-'; ?></td>
                            <td class="px-6 py-3"><span class="px-2 py-1 rounded text-xs <?php echo $r['status']=='present'?'bg-green-100 text-green-800':'bg-red-100 text-red-800'; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                            <td class="px-6 py-3"><?php echo $r['latitude'] ? round($r['latitude'],4).', '.round($r['longitude'],4) : '-'; ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
<script>
        // Chart
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        // Get dynamic chart data based on view
        <?php if($view == 'monthly'): ?>
        // Monthly view - bar chart by day
        const monthlyLabels = [<?php echo implode(',', array_map(fn($r) => "'".date('d', strtotime($r['date']))."'", $records)); ?>];
        const monthlyData = [<?php echo implode(',', array_map(fn($r) => $r['present'], $records)); ?>];
        new Chart(ctx, {
            type: 'bar',
            data: { 
                labels: monthlyLabels,
                datasets: [{ label: 'Present', data: monthlyData, backgroundColor: '#22c55e' }] 
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
        });
        <?php elseif($view == 'teacher'): ?>
        // Teacher view - horizontal bar chart
        const teacherLabels = [<?php echo implode(',', array_map(fn($r) => "'".$r['teacher_name']."'", $records)); ?>];
        const teacherPresent = [<?php echo implode(',', array_map(fn($r) => $r['present'], $records)); ?>];
        const teacherAbsent = [<?php echo implode(',', array_map(fn($r) => $r['absent'], $records)); ?>];
        new Chart(ctx, {
            type: 'bar',
            data: { 
                labels: teacherLabels,
                datasets: [
                    { label: 'Present', data: teacherPresent, backgroundColor: '#22c55e' },
                    { label: 'Absent', data: teacherAbsent, backgroundColor: '#ef4444' }
                ] 
            },
            options: { responsive: true, indexAxis: 'y', plugins: { legend: { position: 'bottom' } }, scales: { x: { beginAtZero: true } } }
        });
        <?php else: ?>
        // Daily view - doughnut chart
        new Chart(ctx, {
            type: 'doughnut',
            data: { labels: ['Present', 'Absent', 'Late'], datasets: [{ data: [<?php echo $present; ?>, <?php echo $absent; ?>, <?php echo $late; ?>], backgroundColor: ['#22c55e', '#ef4444', '#eab308'] }] },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
        <?php endif; ?>
        
        function exportToCSV() {
            let csv = [];
            let rows = document.querySelectorAll('#reportTable table tr');
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll('td, th');
                for (let j = 0; j < cols.length; j++) row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
                csv.push(row.join(','));
            }
            let blob = new Blob([csv.join('\n')], { type: 'text/csv' });
            let url = URL.createObjectURL(blob);
            let a = document.createElement('a');
            a.href = url;
            a.download = 'attendance_report_<?php echo date('Y-m-d'); ?>.csv';
            a.click();
            URL.revokeObjectURL(url);
        }
        
        function exportToPDF() {
            // Use browser print with PDF option
            const printContent = document.getElementById('reportTable').innerHTML;
            const windowContent = window.open('', '', 'height=700,width=900');
            windowContent.document.write('<html><head><title>Attendance Report</title>');
            windowContent.document.write('<style>body{font-family:Arial,sans-serif}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#4CAF50;color:white}</style>');
            windowContent.document.write('</head><body>');
            windowContent.document.write('<h1>ST.LUKE Attendance Report</h1>');
            windowContent.document.write('<p>Period: <?php echo $start_date; ?> to <?php echo $end_date; ?></p>');
            windowContent.document.write(printContent);
            windowContent.document.write('</body></html>');
            windowContent.document.close();
            windowContent.focus();
            setTimeout(() => { windowContent.print(); }, 500);
        }
        
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
    </script>
</body>
</html>