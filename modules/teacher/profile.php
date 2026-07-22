<?php
// modules/teacher/profile.php
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireTeacher();

require_once __DIR__ . '/../../models/Attendance.php';
require_once __DIR__ . '/../../config/db.php';

$attendanceModel = new Attendance();
$database = new Database();
$conn = $database->getConnection();
$teacherId = $attendanceModel->getTeacherIdByUserId($_SESSION['user_id']);

$message = '';
$error = '';

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image']) && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
    $uploadDir = __DIR__ . '/../../uploads/profiles/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = time() . '_' . basename($_FILES['profile_image']['name']);
    $targetPath = $uploadDir . $fileName;
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = mime_content_type($_FILES['profile_image']['tmp_name']);
    
    if (in_array($fileType, $allowedTypes)) {
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
            $imagePath = 'uploads/profiles/' . $fileName;
            $updateImg = "UPDATE teachers SET profile_image = :img WHERE id = :teacher_id";
            $stmt = $conn->prepare($updateImg);
            $stmt->bindParam(':img', $imagePath);
            $stmt->bindParam(':teacher_id', $teacherId);
            $stmt->execute();
            $message = "Profile picture uploaded successfully!";
        } else {
            $error = "Failed to upload image";
        }
    } else {
        $error = "Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.";
    }
}

// Get teacher details
$query = "SELECT u.name, u.email, t.teacher_code, t.phone, t.face_enrolled, t.profile_image FROM users u JOIN teachers t ON u.id = t.user_id WHERE t.id = :teacher_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':teacher_id', $teacherId);
$stmt->execute();
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    
    $result = $auth->updateProfile($_SESSION['user_id'], $name, $email);
    if ($result['success']) {
        $updatePhone = "UPDATE teachers SET phone = :phone WHERE id = :teacher_id";
        $stmt = $conn->prepare($updatePhone);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':teacher_id', $teacherId);
        $stmt->execute();
        $message = "Profile updated successfully!";
        $teacher['name'] = $name;
        $teacher['email'] = $email;
        $teacher['phone'] = $phone;
    } else {
        $error = $result['message'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    if ($new !== $confirm) {
        $error = "New passwords do not match";
    } else {
        $result = $auth->changePassword($_SESSION['user_id'], $current, $new);
        if ($result['success']) $message = $result['message'];
        else $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Teacher Portal</title>
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
                <a href="leave_status.php" class="block px-6 py-2 hover:bg-white/10"><i class="fas fa-envelope w-5 mr-2"></i> Leave Status</a>
                <a href="profile.php" class="block px-6 py-2 bg-white/10"><i class="fas fa-user w-5 mr-2"></i> My Profile</a>
                <a href="../../api/logout.php" class="block px-6 py-2 mt-8 hover:bg-red-600"><i class="fas fa-sign-out-alt w-5 mr-2"></i> Logout</a>
            </nav>
        </div>
        
        <div class="ml-64 flex-1 p-8">
            <div class="max-w-4xl mx-auto">
                <h1 class="text-3xl font-bold text-gray-800 mb-6">My Profile</h1>
                
                <?php if($message): ?><div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4"><?php echo $message; ?></div><?php endif; ?>
                <?php if($error): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4"><?php echo $error; ?></div><?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Profile Picture Upload -->
                    <div class="bg-white rounded-xl shadow-2xl p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4"><i class="fas fa-camera mr-2"></i> Profile Picture</h2>
                        <div class="text-center">
                            <div class="w-32 h-32 mx-auto mb-4 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden">
                                <?php if (!empty($teacher['profile_image'])): ?>
                                    <?php
                                        // Always show image relative to web root with cache busting
                                        $imgSrc = '/' . ltrim($teacher['profile_image'], '/');
                                        // Add cache busting query string
                                        $imgSrc .= '?v=' . time();
                                    ?>
                                    <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-user text-4xl text-gray-400"></i>
                                <?php endif; ?>
                            </div>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="file" name="profile_image" accept="image/*" class="mb-3 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                <button type="submit" name="upload_image" class="w-full bg-purple-500 text-white py-2 rounded-lg font-bold hover:bg-purple-600">Upload Picture</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Profile Info -->
                    <div class="bg-white rounded-xl shadow-2xl p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4"><i class="fas fa-user mr-2"></i> Profile Information</h2>
                        <form method="POST">
                            <div class="mb-4"><label class="block text-gray-700 font-bold mb-2">Full Name</label><input type="text" name="name" value="<?php echo htmlspecialchars($teacher['name']); ?>" required class="w-full px-3 py-2 border rounded-lg"></div>
                            <div class="mb-4"><label class="block text-gray-700 font-bold mb-2">Email Address</label><input type="email" name="email" value="<?php echo htmlspecialchars($teacher['email']); ?>" required class="w-full px-3 py-2 border rounded-lg"></div>
                            <div class="mb-4"><label class="block text-gray-700 font-bold mb-2">Teacher Code</label><input type="text" value="<?php echo htmlspecialchars($teacher['teacher_code']); ?>" readonly disabled class="w-full px-3 py-2 border rounded-lg bg-gray-100"></div>
                            <div class="mb-4"><label class="block text-gray-700 font-bold mb-2">Phone Number</label><input type="tel" name="phone" value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-lg"></div>
                            <div class="mb-4"><label class="block text-gray-700 font-bold mb-2">Face Enrollment</label><span class="inline-block px-3 py-2 rounded <?php echo $teacher['face_enrolled'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>"><?php echo $teacher['face_enrolled'] ? 'Enrolled' : 'Not Enrolled'; ?></span></div>
                            <button type="submit" name="update_profile" class="w-full bg-blue-500 text-white py-2 rounded-lg font-bold hover:bg-blue-600">Update Profile</button>
                        </form>
                    </div>
                    
                    <!-- Change Password -->
                    <div class="bg-white rounded-xl shadow-2xl p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4"><i class="fas fa-lock mr-2"></i> Change Password</h2>
                        <form method="POST">
                            <div class="mb-4"><label class="block text-gray-700 font-bold mb-2">Current Password</label><input type="password" name="current_password" required class="w-full px-3 py-2 border rounded-lg"></div>
                            <div class="mb-4"><label class="block text-gray-700 font-bold mb-2">New Password</label><input type="password" name="new_password" required class="w-full px-3 py-2 border rounded-lg"></div>
                            <div class="mb-4"><label class="block text-gray-700 font-bold mb-2">Confirm New Password</label><input type="password" name="confirm_password" required class="w-full px-3 py-2 border rounded-lg"></div>
                            <button type="submit" name="change_password" class="w-full bg-green-500 text-white py-2 rounded-lg font-bold hover:bg-green-600">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>