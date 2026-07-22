<?php
// modules/teacher/leave_status.php
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireTeacher();

require_once __DIR__ . '/../../models/Attendance.php';
$attendanceModel = new Attendance();
$teacherId = $attendanceModel->getTeacherIdByUserId($_SESSION['user_id']);
$leaveRequests = $attendanceModel->getTeacherLeaveRequests($teacherId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Status - Teacher Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex">
        <div class="w-64 bg-gradient-to-b from-blue-900 to-purple-900 text-white min-h-screen fixed">
            <div class="p-6"><h2 class="text-xl font-bold">Attendance System</h2></div>
            <div class="p-4 border-t border-b"><p><?php echo htmlspecialchars($_SESSION['user_name']); ?></p></div>
            <nav class="py-4">
                <a href="dashboard.php" class="block px-6 py-2 hover:bg-white/10"><i class="fas fa-tachometer-alt w-5 mr-2"></i> Dashboard</a>
                <a href="checkin.php" class="block px-6 py-2 hover:bg-white/10"><i class="fas fa-camera w-5 mr-2"></i> Face Check-in</a>
                <a href="attendance_history.php" class="block px-6 py-2 hover:bg-white/10"><i class="fas fa-history w-5 mr-2"></i> My History</a>
                <a href="leave.php" class="block px-6 py-2 hover:bg-white/10"><i class="fas fa-calendar-alt w-5 mr-2"></i> Apply Leave</a>
                <a href="leave_status.php" class="block px-6 py-2 bg-white/10"><i class="fas fa-envelope w-5 mr-2"></i> Leave Status</a>
                <a href="../../api/logout.php" class="block px-6 py-2 mt-8 hover:bg-red-600"><i class="fas fa-sign-out-alt w-5 mr-2"></i> Logout</a>
            </nav>
        </div>
        
        <div class="ml-64 flex-1 p-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">My Leave Requests</h1>
            <div class="bg-white rounded-xl shadow-2xl overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50"><tr><th class="px-6 py-3">Leave Type</th><th class="px-6 py-3">Start Date</th><th class="px-6 py-3">End Date</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Admin Remarks</th></tr></thead>
                    <tbody>
                        <?php if(empty($leaveRequests)): ?><tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No leave requests found</td></tr><?php endif; ?>
                        <?php foreach($leaveRequests as $leave): ?>
                        <tr class="border-t"><td class="px-6 py-3"><?php echo ucfirst($leave['leave_type']); ?></td><td class="px-6 py-3"><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></td><td class="px-6 py-3"><?php echo date('M d, Y', strtotime($leave['end_date'])); ?></td>
                            <td class="px-6 py-3"><?php $statusColors = ['pending'=>'bg-yellow-100 text-yellow-800','approved'=>'bg-green-100 text-green-800','rejected'=>'bg-red-100 text-red-800']; ?><span class="px-2 py-1 rounded text-xs <?php echo $statusColors[$leave['status']]; ?>"><?php echo ucfirst($leave['status']); ?></span></td>
                            <td class="px-6 py-3"><?php echo $leave['admin_remarks'] ?: '-'; ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>