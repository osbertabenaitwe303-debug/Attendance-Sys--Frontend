<?php
// modules/admin/leave_requests.php - Complete Leave Management
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireAdmin();

require_once __DIR__ . '/../../models/Attendance.php';
$attendanceModel = new Attendance();

// Handle approve/reject actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_id = $_POST['leave_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    if ($action === 'approve') {
        if ($attendanceModel->updateLeaveStatus($leave_id, 'approved', $remarks)) {
            $message = "Leave request approved successfully!";
        } else {
            $error = "Failed to approve leave request";
        }
    } elseif ($action === 'reject') {
        if ($attendanceModel->updateLeaveStatus($leave_id, 'rejected', $remarks)) {
            $message = "Leave request rejected!";
        } else {
            $error = "Failed to reject leave request";
        }
    }
}

// Get all leave requests
$pendingLeaves = $attendanceModel->getAllLeaveRequests('pending');
$approvedLeaves = $attendanceModel->getAllLeaveRequests('approved');
$rejectedLeaves = $attendanceModel->getAllLeaveRequests('rejected');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            transition: all 0.3s;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 100;
            width: 280px;
        }
        .main-content {
            margin-left: 280px;
            transition: all 0.3s;
        }
        .sidebar-item {
            transition: all 0.2s;
        }
        .sidebar-item:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        .sidebar-item.active {
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
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
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Mobile Menu Toggle -->
    <button id="menuToggle" class="fixed top-4 left-4 z-50 bg-blue-600 text-white p-2 rounded-lg md:hidden">
        <i class="fas fa-bars text-xl"></i>
    </button>
    
    <!-- Vertical Sidebar -->
    <div class="sidebar bg-gradient-to-b from-blue-900 to-purple-900 text-white shadow-2xl" id="sidebar">
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
            <a href="dashboard.php" class="sidebar-item flex items-center space-x-3 px-6 py-3 hover:bg-white/10 transition">
                <i class="fas fa-tachometer-alt w-5"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage_teachers.php" class="sidebar-item flex items-center space-x-3 px-6 py-3 hover:bg-white/10 transition">
                <i class="fas fa-chalkboard-teacher w-5"></i>
                <span>Manage Teachers</span>
            </a>
            <a href="register_teacher.php" class="sidebar-item flex items-center space-x-3 px-6 py-3 hover:bg-white/10 transition">
                <i class="fas fa-user-plus w-5"></i>
                <span>Register Teacher</span>
            </a>
            <a href="attendance.php" class="sidebar-item flex items-center space-x-3 px-6 py-3 hover:bg-white/10 transition">
                <i class="fas fa-calendar-check w-5"></i>
                <span>Attendance Records</span>
            </a>
            <a href="reports.php" class="sidebar-item flex items-center space-x-3 px-6 py-3 hover:bg-white/10 transition">
                <i class="fas fa-chart-bar w-5"></i>
                <span>Reports</span>
            </a>
            <a href="leave_requests.php" class="sidebar-item active flex items-center space-x-3 px-6 py-3 bg-white/10 transition">
                <i class="fas fa-envelope w-5"></i>
                <span>Leave Requests</span>
                <?php if(count($pendingLeaves) > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?php echo count($pendingLeaves); ?></span>
                <?php endif; ?>
            </a>
            <a href="gps_settings.php" class="sidebar-item flex items-center space-x-3 px-6 py-3 hover:bg-white/10 transition">
                <i class="fas fa-map-marker-alt w-5"></i>
                <span>GPS Settings</span>
            </a>
            <a href="../../api/logout.php" class="sidebar-item flex items-center space-x-3 px-6 py-3 hover:bg-red-600 transition mt-8">
                <i class="fas fa-sign-out-alt w-5"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="p-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-800">Leave Requests</h1>
                    <p class="text-gray-600">Approve or reject teacher leave applications</p>
                </div>
                
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                        <i class="fas fa-check-circle mr-2"></i> <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                        <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Tabs -->
                <div class="mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="flex space-x-8">
                            <button onclick="showTab('pending')" id="tabPending" class="py-2 px-1 border-b-2 font-medium text-sm <?php echo empty($_GET['tab']) || $_GET['tab'] == 'pending' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                Pending <span class="ml-1 bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full text-xs"><?php echo count($pendingLeaves); ?></span>
                            </button>
                            <button onclick="showTab('approved')" id="tabApproved" class="py-2 px-1 border-b-2 font-medium text-sm <?php echo isset($_GET['tab']) && $_GET['tab'] == 'approved' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                Approved <span class="ml-1 bg-green-100 text-green-800 px-2 py-0.5 rounded-full text-xs"><?php echo count($approvedLeaves); ?></span>
                            </button>
                            <button onclick="showTab('rejected')" id="tabRejected" class="py-2 px-1 border-b-2 font-medium text-sm <?php echo isset($_GET['tab']) && $_GET['tab'] == 'rejected' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                                Rejected <span class="ml-1 bg-red-100 text-red-800 px-2 py-0.5 rounded-full text-xs"><?php echo count($rejectedLeaves); ?></span>
                            </button>
                        </nav>
                    </div>
                </div>
                
                <!-- Pending Leaves Table -->
                <div id="pendingTab" class="tab-content <?php echo empty($_GET['tab']) || $_GET['tab'] == 'pending' ? '' : 'hidden'; ?>">
                    <div class="bg-white rounded-xl shadow-2xl overflow-hidden">
                        <div class="bg-gradient-to-r from-yellow-500 to-orange-500 px-6 py-4">
                            <h2 class="text-xl font-bold text-white"><i class="fas fa-clock mr-2"></i> Pending Requests</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teacher</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Leave Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Start Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">End Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php if (empty($pendingLeaves)): ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                                <i class="fas fa-check-circle text-4xl mb-2 block text-green-500"></i>
                                                No pending leave requests
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pendingLeaves as $leave): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4">
                                                    <div class="font-medium"><?php echo htmlspecialchars($leave['name']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($leave['teacher_code']); ?></div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="capitalize"><?php echo $leave['leave_type']; ?></span>
                                                </td>
                                                <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></td>
                                                <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($leave['end_date'])); ?></td>
                                                <td class="px-6 py-4 max-w-xs">
                                                    <p class="text-sm truncate"><?php echo htmlspecialchars(substr($leave['reason'], 0, 50)); ?></p>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex space-x-2">
                                                        <button onclick="openModal(<?php echo $leave['id']; ?>, 'approve', '<?php echo htmlspecialchars($leave['name']); ?>')" 
                                                                class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button onclick="openModal(<?php echo $leave['id']; ?>, 'reject', '<?php echo htmlspecialchars($leave['name']); ?>')" 
                                                                class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Approved Leaves Table -->
                <div id="approvedTab" class="tab-content <?php echo isset($_GET['tab']) && $_GET['tab'] == 'approved' ? '' : 'hidden'; ?>">
                    <div class="bg-white rounded-xl shadow-2xl overflow-hidden">
                        <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                            <h2 class="text-xl font-bold text-white"><i class="fas fa-check-circle mr-2"></i> Approved Requests</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left">Teacher</th>
                                        <th class="px-6 py-3 text-left">Leave Type</th>
                                        <th class="px-6 py-3 text-left">Dates</th>
                                        <th class="px-6 py-3 text-left">Admin Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($approvedLeaves)): ?>
                                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No approved leave requests</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($approvedLeaves as $leave): ?>
                                            <tr class="border-t">
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($leave['name']); ?></td>
                                                <td class="px-6 py-4 capitalize"><?php echo $leave['leave_type']; ?></td>
                                                <td class="px-6 py-4"><?php echo date('M d', strtotime($leave['start_date'])); ?> - <?php echo date('M d', strtotime($leave['end_date'])); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($leave['admin_remarks'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Rejected Leaves Table -->
                <div id="rejectedTab" class="tab-content <?php echo isset($_GET['tab']) && $_GET['tab'] == 'rejected' ? '' : 'hidden'; ?>">
                    <div class="bg-white rounded-xl shadow-2xl overflow-hidden">
                        <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4">
                            <h2 class="text-xl font-bold text-white"><i class="fas fa-times-circle mr-2"></i> Rejected Requests</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left">Teacher</th>
                                        <th class="px-6 py-3 text-left">Leave Type</th>
                                        <th class="px-6 py-3 text-left">Dates</th>
                                        <th class="px-6 py-3 text-left">Rejection Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rejectedLeaves)): ?>
                                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">No rejected leave requests</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($rejectedLeaves as $leave): ?>
                                            <tr class="border-t">
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($leave['name']); ?></td>
                                                <td class="px-6 py-4 capitalize"><?php echo $leave['leave_type']; ?></td>
                                                <td class="px-6 py-4"><?php echo date('M d', strtotime($leave['start_date'])); ?> - <?php echo date('M d', strtotime($leave['end_date'])); ?></td>
                                                <td class="px-6 py-4"><?php echo htmlspecialchars($leave['admin_remarks'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal for Remarks -->
    <div id="remarksModal" class="modal">
        <div class="modal-content">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 p-4 rounded-t-xl">
                <h3 class="text-xl font-bold text-white" id="modalTitle">Approve Leave</h3>
            </div>
            <div class="p-6">
                <form id="actionForm" method="POST" action="">
                    <input type="hidden" name="leave_id" id="leaveId">
                    <input type="hidden" name="action" id="actionType">
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-bold mb-2">Teacher: <span id="teacherName"></span></label>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-bold mb-2">Remarks (Optional)</label>
                        <textarea name="remarks" id="remarks" rows="3" class="w-full px-3 py-2 border rounded-lg" placeholder="Add any comments..."></textarea>
                    </div>
                    
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600">Confirm</button>
                        <button type="button" onclick="closeModal()" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tab) {
            // Hide all tabs
            document.getElementById('pendingTab').classList.add('hidden');
            document.getElementById('approvedTab').classList.add('hidden');
            document.getElementById('rejectedTab').classList.add('hidden');
            
            // Show selected tab
            document.getElementById(tab + 'Tab').classList.remove('hidden');
            
            // Update URL without reload
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
        }
        
        let currentModal = null;
        
        function openModal(leaveId, action, teacherName) {
            const modal = document.getElementById('remarksModal');
            document.getElementById('leaveId').value = leaveId;
            document.getElementById('actionType').value = action;
            document.getElementById('teacherName').innerHTML = teacherName;
            document.getElementById('modalTitle').innerHTML = action === 'approve' ? '✅ Approve Leave Request' : '❌ Reject Leave Request';
            document.getElementById('actionForm').action = window.location.href;
            modal.classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('remarksModal').classList.remove('active');
            document.getElementById('remarks').value = '';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('remarksModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Mobile menu toggle
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
    </script>
</body>
</html>