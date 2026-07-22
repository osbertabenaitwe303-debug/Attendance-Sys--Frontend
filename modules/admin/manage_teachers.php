<?php
// modules/admin/manage_teachers.php - COMPLETE WITH CLEAR TEACHER CODES
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireAdmin();

require_once __DIR__ . '/../../models/User.php';
$userModel = new User();
$teachers = $userModel->getAllTeachers();

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers - Admin Panel</title>
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
        .sidebar-item.active {
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
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
            max-width: 400px;
            width: 90%;
        }
        .code-badge {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            background: #f3f4f6;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 13px;
            display: inline-block;
            letter-spacing: 0.5px;
        }
        .table-container {
            overflow-x: auto;
        }
        .status-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Mobile Menu Toggle -->
    <button id="menuToggle" class="fixed top-4 left-4 z-50 bg-blue-600 text-white p-2 rounded-lg md:hidden">
        <i class="fas fa-bars text-xl"></i>
    </button>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="bg-gradient-to-r from-red-600 to-red-700 p-4 rounded-t-xl">
                <h3 class="text-xl font-bold text-white"><i class="fas fa-trash mr-2"></i> Delete Teacher</h3>
            </div>
            <div class="p-6">
                <p class="mb-4">Are you sure you want to delete <strong id="teacherName"></strong>?</p>
                <p class="text-sm text-red-600 mb-4">⚠️ This action cannot be undone! All attendance records and leave requests will be deleted.</p>
                <div class="flex gap-3">
                    <button onclick="confirmDelete()" class="flex-1 bg-red-500 text-white py-2 rounded-lg hover:bg-red-600">
                        Yes, Delete
                    </button>
                    <button onclick="closeModal()" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
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
            <a href="dashboard.php" class="sidebar-item">
                <i class="fas fa-tachometer-alt w-5 mr-3"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage_teachers.php" class="sidebar-item active">
                <i class="fas fa-chalkboard-teacher w-5 mr-3"></i>
                <span>Manage Teachers</span>
            </a>
            <a href="register_teacher.php" class="sidebar-item">
                <i class="fas fa-user-plus w-5 mr-3"></i>
                <span>Register Teacher</span>
            </a>
            <a href="attendance.php" class="sidebar-item">
                <i class="fas fa-calendar-check w-5 mr-3"></i>
                <span>Attendance Records</span>
            </a>
            <a href="reports.php" class="sidebar-item">
                <i class="fas fa-chart-bar w-5 mr-3"></i>
                <span>Reports</span>
            </a>
            <a href="leave_requests.php" class="sidebar-item">
                <i class="fas fa-envelope w-5 mr-3"></i>
                <span>Leave Requests</span>
            </a>
            <a href="gps_settings.php" class="sidebar-item">
                <i class="fas fa-map-marker-alt w-5 mr-3"></i>
                <span>GPS Settings</span>
            </a>
            <a href="../../api/logout.php" class="sidebar-item mt-8 hover:bg-red-600">
                <i class="fas fa-sign-out-alt w-5 mr-3"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="p-8">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Manage Teachers</h1>
                    <p class="text-gray-600">View, manage, and enroll faces for all teachers</p>
                </div>
                <a href="register_teacher.php" class="bg-gradient-to-r from-green-500 to-green-600 text-white px-5 py-2 rounded-lg hover:from-green-600 hover:to-green-700 transition shadow-lg flex items-center">
                    <i class="fas fa-plus mr-2"></i> Add New Teacher
                </a>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if ($success == '1'): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow">
                    <i class="fas fa-check-circle mr-2"></i> Face enrolled successfully!
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Teachers Table -->
            <div class="bg-white rounded-xl shadow-2xl overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-xl font-bold text-white">
                                <i class="fas fa-users mr-2"></i> Teacher List
                            </h2>
                            <p class="text-blue-100 text-sm mt-1">Total: <?php echo count($teachers); ?> teachers</p>
                        </div>
                        <div class="bg-white/20 rounded-lg px-3 py-1 text-white text-sm">
                            <i class="fas fa-id-card mr-1"></i> Teacher Code Format: T001, T002, etc.
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-user mr-1"></i> Name
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-id-card mr-1"></i> Teacher Code
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-envelope mr-1"></i> Email
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-face-smile mr-1"></i> Face Status
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <i class="fas fa-cogs mr-1"></i> Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($teachers)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                        <i class="fas fa-user-graduate text-4xl mb-2 block"></i>
                                        No teachers found. Click "Add New Teacher" to get started.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($teachers as $teacher): ?>
                                    <tr class="hover:bg-gray-50 transition" id="teacher-row-<?php echo $teacher['teacher_id']; ?>">
                                        <td class="px-6 py-4 text-sm text-gray-500"><?php echo $counter++; ?></td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="w-9 h-9 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center mr-3 shadow">
                                                    <i class="fas fa-user-graduate text-white text-sm"></i>
                                                </div>
                                                <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($teacher['name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <i class="fas fa-qrcode text-purple-500 mr-2"></i>
                                                <code class="code-badge bg-purple-100 text-purple-800 px-3 py-1 rounded-full font-bold">
                                                    <?php echo htmlspecialchars($teacher['teacher_code'] ?? 'N/A'); ?>
                                                </code>
                                            </div>
                                         </td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($teacher['email']); ?> </td>
                                        <td class="px-6 py-4">
                                            <?php if($teacher['face_enrolled']): ?>
                                                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-semibold">
                                                    <i class="fas fa-check-circle"></i> Enrolled
                                                </span>
                                            <?php else: ?>
                                                <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-xs font-semibold status-badge">
                                                    <i class="fas fa-exclamation-circle"></i> Not Enrolled
                                                </span>
                                            <?php endif; ?>
                                         </td>
                                        <td class="px-6 py-4">
                                            <div class="flex space-x-2">
                                                <a href="enroll_face.php?id=<?php echo $teacher['teacher_id']; ?>" 
                                                   class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg text-sm transition flex items-center shadow">
                                                    <i class="fas fa-camera mr-1"></i> Enroll
                                                </a>
                                                <button onclick="showDeleteModal(<?php echo $teacher['teacher_id']; ?>, '<?php echo addslashes($teacher['name']); ?>')" 
                                                        class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg text-sm transition flex items-center shadow">
                                                    <i class="fas fa-trash mr-1"></i> Delete
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
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-8">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-5 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Total Teachers</p>
                            <p class="text-3xl font-bold"><?php echo count($teachers); ?></p>
                        </div>
                        <i class="fas fa-users text-4xl opacity-50"></i>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-5 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Face Enrolled</p>
                            <p class="text-3xl font-bold">
                                <?php 
                                    $enrolled = array_filter($teachers, function($t) { 
                                        return $t['face_enrolled']; 
                                    });
                                    echo count($enrolled);
                                ?>
                            </p>
                        </div>
                        <i class="fas fa-face-smile text-4xl opacity-50"></i>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-yellow-500 to-orange-500 rounded-xl shadow-lg p-5 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Pending Enrollment</p>
                            <p class="text-3xl font-bold">
                                <?php 
                                    $pending = array_filter($teachers, function($t) { 
                                        return !$t['face_enrolled']; 
                                    });
                                    echo count($pending);
                                ?>
                            </p>
                        </div>
                        <i class="fas fa-clock text-4xl opacity-50"></i>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-lg p-5 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Completion Rate</p>
                            <p class="text-3xl font-bold">
                                <?php 
                                    $rate = count($teachers) > 0 ? round((count($enrolled) / count($teachers)) * 100) : 0;
                                    echo $rate . '%';
                                ?>
                            </p>
                        </div>
                        <i class="fas fa-chart-line text-4xl opacity-50"></i>
                    </div>
                </div>
            </div>
            
            <!-- Info Box -->
            <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                <h3 class="font-bold text-blue-800 mb-2">
                    <i class="fas fa-info-circle mr-2"></i> Teacher Code Information:
                </h3>
                <ul class="text-sm text-blue-700 space-y-1 ml-6 list-disc">
                    <li>Teacher codes are automatically generated as <strong>T001, T002, T003</strong>, etc.</li>
                    <li>Each teacher has a unique code that never changes</li>
                    <li>Use the teacher code for quick identification and reports</li>
                    <li>Codes can be customized when registering a teacher</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        let currentTeacherId = null;
        let currentTeacherName = '';
        
        function showDeleteModal(teacherId, teacherName) {
            currentTeacherId = teacherId;
            currentTeacherName = teacherName;
            document.getElementById('teacherName').innerHTML = teacherName;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('deleteModal').classList.remove('active');
            currentTeacherId = null;
            currentTeacherName = '';
        }
        
        async function confirmDelete() {
            if (!currentTeacherId) return;
            
            const modal = document.getElementById('deleteModal');
            const confirmBtn = event.target;
            const originalText = confirmBtn.innerHTML;
            
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Deleting...';
            
            try {
                const response = await fetch('../../api/delete_teacher.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: currentTeacherId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const row = document.getElementById('teacher-row-' + currentTeacherId);
                    if (row) {
                        row.remove();
                    }
                    showToast('✅ Teacher deleted successfully!', 'success');
                    closeModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('❌ Error: ' + result.message, 'error');
                    closeModal();
                }
            } catch(error) {
                console.error('Delete error:', error);
                showToast('❌ Error: ' + error.message, 'error');
                closeModal();
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalText;
            }
        }
        
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `fixed bottom-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} transition-all duration-300`;
            toast.innerHTML = message;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, 300);
            }, 3000);
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
    </script>
</body>
</html>