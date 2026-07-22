<?php
// modules/admin/audit_logs.php - Audit Log Viewer
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireAdmin();

require_once __DIR__ . '/../../config/db.php';

$database = new Database();
$conn = $database->getConnection();

// Get filter parameters
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user_id'] ?? '';
$limit = 100;

// Build query
$sql = "SELECT al.*, u.name as user_name, u.email as user_email
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE 1=1";

$params = [];

if ($action_filter) {
    $sql .= " AND al.action LIKE :action";
    $params[':action'] = '%' . $action_filter . '%';
}

if ($user_filter) {
    $sql .= " AND al.user_id = :user_id";
    $params[':user_id'] = $user_filter;
}

$sql .= " ORDER BY al.created_at DESC LIMIT :limit";
$params[':limit'] = $limit;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users for filter
$usersStmt = $conn->prepare("SELECT id, name FROM users ORDER BY name");
$usersStmt->execute();
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 280px; background: linear-gradient(135deg, #1e3a8a, #5b21b6); color: white; z-index: 100; }
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
            <a href="manage_teachers.php" class="sidebar-item"><i class="fas fa-chalkboard-teacher w-5 mr-3"></i> Manage Teachers</a>
            <a href="register_teacher.php" class="sidebar-item"><i class="fas fa-user-plus w-5 mr-3"></i> Register Teacher</a>
            <a href="attendance.php" class="sidebar-item"><i class="fas fa-calendar-check w-5 mr-3"></i> Attendance Records</a>
            <a href="reports.php" class="sidebar-item"><i class="fas fa-chart-bar w-5 mr-3"></i> Reports</a>
            <a href="leave_requests.php" class="sidebar-item"><i class="fas fa-envelope w-5 mr-3"></i> Leave Requests</a>
            <a href="gps_settings.php" class="sidebar-item"><i class="fas fa-map-marker-alt w-5 mr-3"></i> GPS Settings</a>
            <a href="audit_logs.php" class="sidebar-item active bg-white/10"><i class="fas fa-history w-5 mr-3"></i> Audit Logs</a>
            <a href="../../api/logout.php" class="sidebar-item mt-8 hover:bg-red-600"><i class="fas fa-sign-out-alt w-5 mr-3"></i> Logout</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="p-8">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">
                    <i class="fas fa-history mr-2 text-blue-500"></i>Audit Logs
                </h1>
                <p class="text-gray-600">View system activity and security logs</p>
            </div>
            
            <!-- Filter Form -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">Action Type</label>
                        <select name="action" class="w-full px-3 py-2 border rounded-lg">
                            <option value="">All Actions</option>
                            <option value="checkin" <?php echo $action_filter === 'checkin' ? 'selected' : ''; ?>>Check-in</option>
                            <option value="checkout" <?php echo $action_filter === 'checkout' ? 'selected' : ''; ?>>Check-out</option>
                            <option value="leave" <?php echo $action_filter === 'leave' ? 'selected' : ''; ?>>Leave</option>
                            <option value="login" <?php echo $action_filter === 'login' ? 'selected' : ''; ?>>Login</option>
                            <option value="gps" <?php echo $action_filter === 'gps' ? 'selected' : ''; ?>>GPS</option>
                            <option value="face" <?php echo $action_filter === 'face' ? 'selected' : ''; ?>>Face Verification</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">User</label>
                        <select name="user_id" class="w-full px-3 py-2 border rounded-lg">
                            <option value="">All Users</option>
                            <?php foreach($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo $user_filter == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <?php
                $totalLogs = count($logs);
                $checkinLogs = count(array_filter($logs, fn($l) => stripos($l['action'], 'checkin') !== false));
                $leaveLogs = count(array_filter($logs, fn($l) => stripos($l['action'], 'leave') !== false));
                $failedLogs = count(array_filter($logs, fn($l) => stripos($l['details'], 'failed') !== false || stripos($l['details'], 'error') !== false));
                ?>
                <div class="bg-white rounded-xl shadow p-4">
                    <p class="text-gray-500 text-sm">Total Logs</p>
                    <p class="text-2xl font-bold"><?php echo $totalLogs; ?></p>
                </div>
                <div class="bg-white rounded-xl shadow p-4">
                    <p class="text-gray-500 text-sm">Check-in/Out</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo $checkinLogs; ?></p>
                </div>
                <div class="bg-white rounded-xl shadow p-4">
                    <p class="text-gray-500 text-sm">Leave Actions</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo $leaveLogs; ?></p>
                </div>
                <div class="bg-white rounded-xl shadow p-4">
                    <p class="text-gray-500 text-sm">Failed Attempts</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo $failedLogs; ?></p>
                </div>
            </div>
            
            <!-- Logs Table -->
            <div class="bg-white rounded-xl shadow-2xl overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-3">
                    <h2 class="text-white font-bold">Activity Log (Last <?php echo $limit; ?> entries)</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timestamp</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if(empty($logs)): ?>
                                <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No audit logs found</td></tr>
                            <?php else: ?>
                                <?php foreach($logs as $log): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 rounded text-xs font-medium <?php 
                                            echo stripos($log['action'], 'checkin') !== false ? 'bg-green-100 text-green-800' :
                                            (stripos($log['action'], 'leave') !== false ? 'bg-blue-100 text-blue-800' :
                                            (stripos($log['action'], 'login') !== false ? 'bg-purple-100 text-purple-800' :
                                            'bg-gray-100 text-gray-800'));
                                        ?>">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate">
                                        <?php echo htmlspecialchars($log['details'] ?? '-'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?>
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
    
    <script>
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
    </script>
</body>
</html>