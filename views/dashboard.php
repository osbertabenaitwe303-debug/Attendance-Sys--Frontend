<?php
// views/dashboard.php
require_once __DIR__ . '/../includes/auth.php';
$auth = new Auth();
$auth->requireLogin();

if ($_SESSION['user_role'] === 'admin') {
    header("Location: ../modules/admin/dashboard.php");
} else {
    header("Location: ../modules/teacher/dashboard.php");
}
exit();
?>