<?php
// modules/teacher/leave.php
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireTeacher();

require_once __DIR__ . '/../../models/Attendance.php';
$attendanceModel = new Attendance();
$teacherId = $attendanceModel->getTeacherIdByUserId($_SESSION['user_id']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leaveType = $_POST['leave_type'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $reason = $_POST['reason'];
    
    if ($startDate > $endDate) {
        $error = "End date must be after start date";
    } else {
        if ($attendanceModel->submitLeaveRequest($teacherId, $leaveType, $startDate, $endDate, $reason)) {
            $message = "Leave request submitted successfully!";
        } else {
            $error = "Failed to submit leave request";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Leave - Teacher Portal</title>
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
                <a href="attendance_history.php" class="block px-6 py-2 hover:bg-white/10"><i class="fas fa-history w-5 mr-2"></i> My History</a>
                <a href="leave.php" class="block px-6 py-2 bg-white/10"><i class="fas fa-calendar-alt w-5 mr-2"></i> Apply Leave</a>
                <a href="leave_status.php" class="block px-6 py-2 hover:bg-white/10"><i class="fas fa-envelope w-5 mr-2"></i> Leave Status</a>
                <a href="../../api/logout.php" class="block px-6 py-2 mt-8 hover:bg-red-600"><i class="fas fa-sign-out-alt w-5 mr-2"></i> Logout</a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="ml-64 flex-1 p-8">
            <div class="max-w-2xl mx-auto">
                <h1 class="text-3xl font-bold text-gray-800 mb-6">Apply for Leave</h1>
                
                <?php if($message): ?><div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4"><?php echo $message; ?></div><?php endif; ?>
                <?php if($error): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4"><?php echo $error; ?></div><?php endif; ?>
                
                <div class="bg-white rounded-xl shadow-2xl p-6">
                    <form method="POST">
                        <div class="mb-4"><label class="block text-gray-700 font-bold mb-2">Leave Type</label>
                            <select name="leave_type" required class="w-full px-3 py-2 border rounded-lg">
                                <option value="sick">Sick Leave</option><option value="casual">Casual Leave</option>
                                <option value="emergency">Emergency Leave</option><option value="paid">Paid Leave</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div><label class="block text-gray-700 font-bold mb-2">Start Date</label><input type="date" name="start_date" required class="w-full px-3 py-2 border rounded-lg"></div>
                            <div><label class="block text-gray-700 font-bold mb-2">End Date</label><input type="date" name="end_date" required class="w-full px-3 py-2 border rounded-lg"></div>
                        </div>
                        <div class="mb-6"><label class="block text-gray-700 font-bold mb-2">Reason</label><textarea name="reason" rows="4" required class="w-full px-3 py-2 border rounded-lg" placeholder="Please provide reason for leave..."></textarea></div>
                        <button type="submit" class="w-full bg-blue-500 text-white py-3 rounded-lg font-bold hover:bg-blue-600">Submit Request</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>