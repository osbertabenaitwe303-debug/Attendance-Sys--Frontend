<?php
// views/reset_password.php - COMPLETELY FIXED VERSION
// Remove any existing session start - let it start only if needed

require_once __DIR__ . '/../config/db.php';

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$error = '';

// Get school information
$database = new Database();
$conn = $database->getConnection();

$schoolQuery = "SELECT school_name, location_address FROM school_settings WHERE id = 1";
$schoolStmt = $conn->prepare($schoolQuery);
$schoolStmt->execute();
$school = $schoolStmt->fetch(PDO::FETCH_ASSOC);

if (!$school) {
    $school = [
        'school_name' => 'St. Luke C.O.U Primary School',
        'location_address' => 'Kaburangire, Koranorya, Mbarara, Uganda'
    ];
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($email) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill all fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($new_password) < 4) {
        $error = "Password must be at least 4 characters!";
    } else {
        // Check if email exists
        $checkQuery = "SELECT id, role FROM users WHERE email = :email";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            $user = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            
            // Update password
            $updateQuery = "UPDATE users SET password = :password WHERE email = :email";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bindParam(':password', $hashed_password);
            $updateStmt->bindParam(':email', $email);
            
            if ($updateStmt->execute()) {
                $message = "✅ Password reset successfully! You can now login.";
                // Clear form fields by redirecting to avoid resubmission
                header("Location: reset_password.php?success=1");
                exit();
            } else {
                $error = "Failed to reset password. Please try again.";
            }
        } else {
            $error = "Email not found in our system!";
        }
    }
}

// Show success message after redirect
if (isset($_GET['success'])) {
    $message = "✅ Password reset successfully! You can now login.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo htmlspecialchars($school['school_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .reset-card {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .school-header {
            background: linear-gradient(135deg, #f59e0b, #ea580c);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="reset-card max-w-md w-full bg-white rounded-2xl shadow-2xl overflow-hidden">
        <!-- School Header -->
        <div class="school-header p-4 text-center">
            <div class="flex items-center justify-center space-x-2 mb-1">
                <i class="fas fa-school text-white text-xl"></i>
                <h2 class="text-white font-bold text-md"><?php echo htmlspecialchars($school['school_name']); ?></h2>
            </div>
            <p class="text-orange-100 text-xs"><?php echo htmlspecialchars($school['location_address']); ?></p>
        </div>
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 p-6 text-center">
            <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-key text-3xl text-white"></i>
            </div>
            <h1 class="text-2xl font-bold text-white">Reset Password</h1>
            <p class="text-blue-100 text-sm mt-1">Reset your attendance system password</p>
        </div>
        
        <!-- Form Area -->
        <div class="p-6">
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded shadow">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded shadow">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Reset Form - NO DEFAULT PASSWORD -->
            <form method="POST" action="" class="mb-6">
                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-2">
                        <i class="fas fa-envelope mr-2 text-gray-500"></i>
                        Email Address
                    </label>
                    <input type="email" name="email" required 
                           placeholder="Enter your registered email"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 font-bold mb-2">
                        <i class="fas fa-lock mr-2 text-gray-500"></i>
                        New Password
                    </label>
                    <input type="password" name="new_password" required 
                           placeholder="Enter new password (min 4 characters)"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                
                <div class="mb-6">
                    <label class="block text-gray-700 font-bold mb-2">
                        <i class="fas fa-lock mr-2 text-gray-500"></i>
                        Confirm Password
                    </label>
                    <input type="password" name="confirm_password" required 
                           placeholder="Confirm new password"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                
                <button type="submit" name="reset_password" 
                        class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold py-3 px-4 rounded-lg hover:from-blue-700 hover:to-purple-700 transition transform hover:scale-105 shadow-lg">
                    <i class="fas fa-sync-alt mr-2"></i>
                    Reset Password
                </button>
            </form>
            
            <!-- Back to Login -->
            <div class="text-center">
                <a href="login.php" 
                   class="text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Login
                </a>
            </div>
            
            <!-- School Info Box -->
            <div class="mt-6 p-3 bg-blue-50 rounded-lg text-xs text-blue-800">
                <i class="fas fa-info-circle mr-1"></i>
                <strong><?php echo htmlspecialchars($school['school_name']); ?></strong><br>
                Kaburangire, Koranorya, Mbarara, Uganda<br>
                <span class="text-blue-600">📞 Contact administrator if you need assistance</span>
            </div>
        </div>
    </div>
</body>
</html>