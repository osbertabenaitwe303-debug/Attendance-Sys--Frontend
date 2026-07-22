<?php
// modules/teacher/pin_checkin.php - PIN Fallback Check-in
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireTeacher();

require_once __DIR__ . '/../../models/Attendance.php';
require_once __DIR__ . '/../../config/db.php';

$attendanceModel = new Attendance();
$database = new Database();
$conn = $database->getConnection();
$teacherId = $attendanceModel->getTeacherIdByUserId($_SESSION['user_id']);

// SECURITY: Check if face is enrolled (REQUIRED)
$isFaceEnrolled = $attendanceModel->isFaceEnrolled($teacherId);
if (!$isFaceEnrolled) {
    header('Location: enroll_face.php?error=Face enrollment required before PIN check-in');
    exit;
}

// Check if PIN is enabled for this teacher
$stmt = $conn->prepare("SELECT pin_enabled, checkin_pin FROM teachers WHERE id = ?");
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher || !$teacher['pin_enabled']) {
    header('Location: checkin.php');
    exit;
}

// Process PIN verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = $_POST['pin'] ?? '';
    
    if ($pin === $teacher['checkin_pin']) {
        // PIN verified - create attendance record
        $check = $conn->prepare("SELECT id FROM attendance_records WHERE teacher_id = ? AND DATE(check_in) = CURDATE()");
        $check->execute([$teacherId]);
        
        if ($check->rowCount() > 0) {
            // Already checked in - do checkout
            $update = $conn->prepare("UPDATE attendance_records SET check_out = NOW() WHERE teacher_id = ? AND DATE(check_in) = CURDATE() AND check_out IS NULL");
            $update->execute([$teacherId]);
            $message = 'Check-out successful!';
            $action = 'checkout';
        } else {
            // Check in
            $insert = $conn->prepare("INSERT INTO attendance_records (teacher_id, check_in, status, check_in_method) VALUES (?, NOW(), 'present', 'pin')");
            $insert->execute([$teacherId]);
            $message = 'Check-in successful!';
            $action = 'checkin';
        }
    } else {
        $error = 'Invalid PIN. Please try again.';
    }
}

// Get today's status
$todayStmt = $conn->prepare("SELECT * FROM attendance_records WHERE teacher_id = ? AND DATE(check_in) = CURDATE()");
$todayStmt->execute([$teacherId]);
$todayRecord = $todayStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIN Check-in - ST.LUKE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #1e3a8a, #5b21b6); min-height: 100vh; }
        .pin-button { width: 70px; height: 70px; border-radius: 50%; font-size: 24px; }
    </style>
</head>
<body class="flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md">
        <div class="text-center mb-6">
            <i class="fas fa-key text-4xl text-blue-600 mb-3"></i>
            <h1 class="text-2xl font-bold text-gray-800">PIN Check-in</h1>
            <p class="text-gray-500">Enter your 4-6 digit PIN</p>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 text-center">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-center">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($todayRecord && $todayRecord['check_out']): ?>
            <div class="bg-green-500 text-white px-4 py-3 rounded mb-4 text-center">
                <i class="fas fa-check-circle mr-2"></i> Already completed for today
            </div>
            <a href="dashboard.php" class="block text-center bg-blue-500 text-white py-3 rounded-lg hover:bg-blue-600">Back to Dashboard</a>
        <?php else: ?>
            <form method="POST">
                <div class="mb-6">
                    <input type="password" id="pinDisplay" name="pin" class="w-full text-center text-3xl tracking-widest border-b-2 border-blue-500 focus:outline-none py-2" placeholder="••••" maxlength="6" required>
                </div>
                
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <button type="button" class="pin-button bg-gray-100 text-gray-800 font-bold hover:bg-gray-200" onclick="addDigit('<?php echo $i; ?>')"><?php echo $i; ?></button>
                    <?php endfor; ?>
                    <button type="button" class="pin-button bg-gray-100" onclick="clearPin()"><i class="fas fa-times"></i></button>
                    <button type="button" class="pin-button bg-gray-100 text-gray-800 font-bold" onclick="addDigit('0')">0</button>
                    <button type="submit" class="pin-button bg-green-500 text-white"><i class="fas fa-check"></i></button>
                </div>
                
                <a href="checkin.php" class="block text-center text-blue-500 hover:underline">Use Face Recognition Instead</a>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        function addDigit(digit) {
            const input = document.getElementById('pinDisplay');
            if (input.value.length < 6) {
                input.value += digit;
            }
        }
        
        function clearPin() {
            document.getElementById('pinDisplay').value = '';
        }
    </script>
</body>
</html>