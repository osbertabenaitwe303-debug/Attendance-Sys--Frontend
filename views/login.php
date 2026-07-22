<?php
// views/login.php - COMPLETE LOGIN PAGE LINKED TO YOUR SYSTEM
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: ../modules/admin/dashboard.php");
        exit();
    } else {
        header("Location: ../modules/teacher/dashboard.php");
        exit();
    }
}

// Get school information from database
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

$auth = new Auth();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            if ($result['role'] === 'admin') {
                header("Location: ../modules/admin/dashboard.php");
                exit();
            } else {
                header("Location: ../modules/teacher/dashboard.php");
                exit();
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Login - <?php echo htmlspecialchars($school['school_name']); ?> | Smart Attendance</title>
    <!-- Tailwind CSS + Font Awesome + Google Fonts (Inter) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            background-image: url('https://images.pexels.com/photos/280229/pexels-photo-280229.jpeg?auto=compress&cs=tinysrgb&w=1600');
            background-size: cover;
            background-position: center 30%;
            background-repeat: no-repeat;
            background-attachment: fixed;
            position: relative;
        }

        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(145deg, 
                rgba(0, 0, 0, 0.6) 0%, 
                rgba(0, 0, 0, 0.45) 35%,
                rgba(44, 28, 16, 0.55) 100%);
            backdrop-filter: blur(1.2px);
            z-index: 0;
            pointer-events: none;
        }

        .login-wrapper {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }

        .house-card {
            background: rgba(255, 255, 245, 0.98);
            border-radius: 2rem;
            box-shadow: 0 30px 45px -18px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(253, 230, 138, 0.3);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            transition: transform 0.25s ease;
            animation: cardRise 0.6s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }

        @keyframes cardRise {
            from {
                opacity: 0;
                transform: translateY(38px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .school-header-house {
            background: linear-gradient(115deg, #2c3e2f 0%, #3e5a3a 100%);
            padding: 1.2rem 1rem;
            text-align: center;
            border-bottom: 3px solid #f4c542;
            position: relative;
        }
        .house-icon-big {
            font-size: 2.8rem;
            color: #f4c542;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.25));
            animation: gentleFloat 3.5s infinite ease-in-out;
            display: inline-block;
        }
        @keyframes gentleFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }
        .school-title {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.2px;
            color: white;
            margin-top: 8px;
            word-break: break-word;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        .address-house {
            background: rgba(244, 197, 66, 0.2);
            backdrop-filter: blur(3px);
            border-radius: 60px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 16px;
            font-size: 0.7rem;
            font-weight: 500;
            color: #fff2d7;
            margin-top: 10px;
        }

        .attendance-area {
            background: linear-gradient(115deg, #7a5c2e 0%, #a77c3c 100%);
            padding: 1.5rem 1rem;
            text-align: center;
            color: white;
        }
        .face-circle {
            width: 78px;
            height: 78px;
            background: radial-gradient(circle at 30% 25%, #f5b042, #c97e2a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            box-shadow: 0 12px 18px -6px rgba(0,0,0,0.4);
        }
        .face-circle i {
            font-size: 2.4rem;
            color: #2c2b26;
        }
        .attendance-area h3 {
            font-size: 1.7rem;
            font-weight: 800;
            letter-spacing: -0.3px;
        }
        .recognition-tag {
            background: rgba(0,0,0,0.25);
            backdrop-filter: blur(4px);
            border-radius: 40px;
            padding: 5px 16px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }

        .input-group {
            margin-bottom: 1.3rem;
        }
        .form-label {
            display: block;
            font-weight: 600;
            color: #2d3e2b;
            margin-bottom: 8px;
            font-size: 0.85rem;
        }
        .input-icon-col {
            color: #b87c2e;
            width: 20px;
        }
        .house-input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e6d5b8;
            border-radius: 28px;
            font-size: 0.9rem;
            transition: all 0.2s;
            outline: none;
            background: #fffef7;
        }
        .house-input:focus {
            border-color: #d69e3a;
            box-shadow: 0 0 0 3px rgba(214, 158, 58, 0.2);
        }
        .btn-house-login {
            width: 100%;
            background: linear-gradient(98deg, #5f6a3e, #7e5a2b);
            border: none;
            padding: 13px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.25s;
            cursor: pointer;
            box-shadow: 0 12px 18px -8px #4b3a1e;
        }
        .btn-house-login:hover {
            transform: translateY(-2px);
            background: linear-gradient(98deg, #4f5a32, #684c1f);
            box-shadow: 0 20px 22px -12px #3a2e16;
        }
        .error-notice {
            background: #fef1e0;
            border-left: 5px solid #e67e22;
            padding: 0.8rem 1rem;
            border-radius: 24px;
            margin-bottom: 1.5rem;
            font-size: 0.8rem;
            font-weight: 500;
            color: #a55312;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .footer-links-house {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #ede3ce;
            text-align: center;
        }
        .reset-link {
            font-size: 0.75rem;
            color: #876b38;
            font-weight: 600;
            transition: 0.2s;
            text-decoration: none;
        }
        .reset-link:hover {
            color: #c1852b;
            text-decoration: underline;
        }
        .location-chip {
            background: #f7f2e4;
            border-radius: 60px;
            padding: 6px 15px;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4a3b22;
        }
        .security-note {
            font-size: 0.65rem;
            color: #7a6a4b;
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="house-card">
        <!-- School Header -->
        <div class="school-header-house">
            <div class="house-icon-big">
                <i class="fas fa-house-chimney"></i>
            </div>
            <div class="school-title">
                <?php echo htmlspecialchars($school['school_name']); ?>
            </div>
            <div class="address-house">
                <i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($school['location_address']); ?>
            </div>
        </div>

        <!-- Smart Attendance Section -->
        <div class="attendance-area">
            <div class="face-circle">
                <i class="fas fa-face-grin-tongue-squint"></i>
            </div>
            <h3>Digital Attendance management system</h3>
            
        </div>

        <!-- Login Form -->
        <div class="p-6 md:p-7">
            <?php if ($error): ?>
                <div class="error-notice">
                    <i class="fas fa-triangle-exclamation"></i> 
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Email field -->
                <div class="input-group">
                    <label class="form-label"><i class="fas fa-envelope input-icon-col mr-1"></i> Email Address</label>
                    <input type="email" name="email" id="email" required 
                           placeholder="admin@system.com / teacher@example.com"
                           class="house-input" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <!-- Password field -->
                <div class="input-group">
                    <label class="form-label"><i class="fas fa-lock input-icon-col mr-1"></i> Password</label>
                    <input type="password" name="password" id="password" required 
                           placeholder="••••••••"
                           class="house-input">
                </div>
                <button type="submit" class="btn-house-login mt-1">
                    <i class="fas fa-arrow-right-to-bracket"></i> Access Dashboard
                </button>
            </form>

            <!-- Footer Links -->
            <div class="footer-links-house">
                <div class="mb-3">
                    <a href="reset_password.php" class="reset-link inline-flex items-center gap-1">
                        <i class="fas fa-key"></i> Forgot password?
                    </a>
                </div>
                <div class="flex justify-center mb-3">
                    <div class="location-chip">
                        <i class="fas fa-home text-amber-700"></i> 
                        <span><?php echo htmlspecialchars($school['location_address']); ?></span>
                    </div>
                </div>
                <div class="security-note flex justify-center gap-2 items-center">
                    <i class="fas fa-shield-heart text-amber-700"></i> 
                </div>
                <div class="security-note flex justify-center gap-2 items-center mt-2 text-xs">
                    <i class="fas fa-user-shield"></i> 
                
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Add smooth interactions
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-focus on email field
        const emailField = document.getElementById('email');
        if (emailField && !emailField.value) {
            emailField.focus();
        }
    });
</script>
</body>
</html>