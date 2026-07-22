<?php
/**
 * Entrypoint Router for Vercel Serverless PHP
 * ALL traffic is routed here. PHP files are executed (not downloaded).
 * Static assets are served with correct MIME types.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

// Root path: redirect to login
if ($uri === '/' || $uri === '/index.php') {
    require __DIR__ . '/../views/login.php';
    exit;
}

// Map clean URIs to PHP files (e.g. /login -> views/login.php)
$phpMappings = [
    '/login'                          => 'views/login.php',
    '/dashboard'                      => 'views/dashboard.php',
    '/logout'                         => 'views/logout.php',
    '/reset-password'                 => 'views/reset_password.php',

    // Admin module
    '/admin/dashboard'                => 'modules/admin/dashboard.php',
    '/admin/attendance'               => 'modules/admin/attendance.php',
    '/admin/audit-logs'               => 'modules/admin/audit_logs.php',
    '/admin/enroll-face'              => 'modules/admin/enroll_face.php',
    '/admin/gps-settings'             => 'modules/admin/gps_settings.php',
    '/admin/leave-requests'           => 'modules/admin/leave_requests.php',
    '/admin/manage-teachers'          => 'modules/admin/manage_teachers.php',
    '/admin/realtime-dashboard'       => 'modules/admin/realtime_dashboard.php',
    '/admin/register-teacher'         => 'modules/admin/register_teacher.php',
    '/admin/reports'                  => 'modules/admin/reports.php',

    // Teacher module
    '/teacher/dashboard'              => 'modules/teacher/dashboard.php',
    '/teacher/checkin'                => 'modules/teacher/checkin.php',
    '/teacher/pin-checkin'            => 'modules/teacher/pin_checkin.php',
    '/teacher/attendance-history'     => 'modules/teacher/attendance_history.php',
    '/teacher/leave'                  => 'modules/teacher/leave.php',
    '/teacher/leave-status'           => 'modules/teacher/leave_status.php',
    '/teacher/profile'                => 'modules/teacher/profile.php',
];

// Check clean URL mappings
if (isset($phpMappings[$uri])) {
    $file = __DIR__ . '/../' . $phpMappings[$uri];
    if (file_exists($file)) {
        require $file;
        exit;
    }
}

// Try exact path match (e.g. /views/login.php)
$targetFile = __DIR__ . '/..' . $uri;

if (file_exists($targetFile) && !is_dir($targetFile)) {
    $ext = pathinfo($targetFile, PATHINFO_EXTENSION);

    if ($ext === 'php') {
        require $targetFile;
        exit;
    }

    // Serve static assets with correct MIME type
    $mimeTypes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'json'  => 'application/json',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
    ];

    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    readfile($targetFile);
    exit;
}

// Ultimate fallback: show login
require __DIR__ . '/../views/login.php';
?>
