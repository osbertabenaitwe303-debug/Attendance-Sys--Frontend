<?php
// modules/admin/gps_settings.php - CLEAN VERSION (No grace_period)
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireAdmin();

require_once __DIR__ . '/../../config/db.php';

$database = new Database();
$conn = $database->getConnection();

$message = '';
$error = '';

// Create table with all required columns for schedule management
$createTable = "CREATE TABLE IF NOT EXISTS school_settings (
    id INT PRIMARY KEY DEFAULT 1,
    school_name VARCHAR(200) NOT NULL DEFAULT 'St. Luke C.O.U Primary School',
    school_lat DECIMAL(10, 8) NOT NULL DEFAULT -0.6072,
    school_lng DECIMAL(11, 8) NOT NULL DEFAULT 30.6545,
    geofence_radius INT NOT NULL DEFAULT 500,
    start_time TIME DEFAULT '08:00:00',
    end_time TIME DEFAULT '16:00:00',
    location_address VARCHAR(300) DEFAULT 'Kaburangire, Koranorya, Mbarara, Uganda',
    late_threshold_minutes INT DEFAULT 15,
    grace_period_minutes INT DEFAULT 15,
    absent_cutoff_time TIME DEFAULT '17:00:00',
    auto_absent_enabled TINYINT(1) DEFAULT 1
)";
$conn->exec($createTable);

// Add missing columns if they don't exist (for existing databases)
try {
    $conn->exec("ALTER TABLE school_settings ADD COLUMN late_threshold_minutes INT DEFAULT 15 AFTER location_address");
} catch (Exception $e) {}
try {
    $conn->exec("ALTER TABLE school_settings ADD COLUMN grace_period_minutes INT DEFAULT 15 AFTER late_threshold_minutes");
} catch (Exception $e) {}
try {
    $conn->exec("ALTER TABLE school_settings ADD COLUMN absent_cutoff_time TIME DEFAULT '17:00:00' AFTER grace_period_minutes");
} catch (Exception $e) {}
try {
    $conn->exec("ALTER TABLE school_settings ADD COLUMN auto_absent_enabled TINYINT(1) DEFAULT 1 AFTER absent_cutoff_time");
} catch (Exception $e) {}

// Get current settings
$stmt = $conn->prepare("SELECT * FROM school_settings WHERE id = 1");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    $conn->exec("INSERT INTO school_settings (id, school_name, school_lat, school_lng, geofence_radius, location_address) 
                 VALUES (1, 'St. Luke C.O.U Primary School', -0.6072, 30.6545, 500, 'Kaburangire, Koranorya, Mbarara, Uganda')");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_name = $_POST['school_name'] ?? 'St. Luke C.O.U Primary School';
    $school_lat = floatval($_POST['school_lat'] ?? -0.6072);
    $school_lng = floatval($_POST['school_lng'] ?? 30.6545);
    $geofence_radius = intval($_POST['geofence_radius'] ?? 500);
    $location_address = $_POST['location_address'] ?? 'Kaburangire, Koranorya, Mbarara, Uganda';
    $start_time = $_POST['start_time'] ?? '08:00:00';
    $end_time = $_POST['end_time'] ?? '16:00:00';
    $late_threshold = intval($_POST['late_threshold_minutes'] ?? 15);
    $grace_period = intval($_POST['grace_period_minutes'] ?? 15);
    $absent_cutoff = $_POST['absent_cutoff_time'] ?? '17:00:00';
    $auto_absent = isset($_POST['auto_absent_enabled']) ? 1 : 0;
    
    $updateSQL = "UPDATE school_settings SET 
                  school_name = :name, 
                  school_lat = :lat, 
                  school_lng = :lng, 
                  geofence_radius = :radius,
                  location_address = :address,
                  start_time = :start_time,
                  end_time = :end_time,
                  late_threshold_minutes = :late_threshold,
                  grace_period_minutes = :grace_period,
                  absent_cutoff_time = :absent_cutoff,
                  auto_absent_enabled = :auto_absent
                  WHERE id = 1";
    $updateStmt = $conn->prepare($updateSQL);
    $updateStmt->bindParam(':name', $school_name);
    $updateStmt->bindParam(':lat', $school_lat);
    $updateStmt->bindParam(':lng', $school_lng);
    $updateStmt->bindParam(':radius', $geofence_radius);
    $updateStmt->bindParam(':address', $location_address);
    $updateStmt->bindParam(':start_time', $start_time);
    $updateStmt->bindParam(':end_time', $end_time);
    $updateStmt->bindParam(':late_threshold', $late_threshold);
    $updateStmt->bindParam(':grace_period', $grace_period);
    $updateStmt->bindParam(':absent_cutoff', $absent_cutoff);
    $updateStmt->bindParam(':auto_absent', $auto_absent);
    
    if ($updateStmt->execute()) {
        $message = "✅ Settings saved!";
        $settings = [
            'school_name' => $school_name, 
            'school_lat' => $school_lat, 
            'school_lng' => $school_lng,
            'geofence_radius' => $geofence_radius,
            'location_address' => $location_address,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'late_threshold_minutes' => $late_threshold,
            'grace_period_minutes' => $grace_period,
            'absent_cutoff_time' => $absent_cutoff,
            'auto_absent_enabled' => $auto_absent
        ];
    } else {
        $error = "Failed to save";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPS Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .sidebar { position: fixed; left: 0; top: 0; height: 100vh; width: 280px;
            background: linear-gradient(135deg, #1e3a8a, #5b21b6); color: white; z-index: 100; }
        .main-content { margin-left: 280px; }
        .sidebar-item { display: flex; align-items: center; padding: 12px 24px; }
        .sidebar-item:hover { background: rgba(255,255,255,0.1); }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } }
        #map { height: 400px; border-radius: 12px; }
    </style>
</head>
<body class="bg-gray-100">
    <button id="menuToggle" class="fixed top-4 left-4 z-50 bg-blue-600 text-white p-2 rounded-lg md:hidden">
        <i class="fas fa-bars text-xl"></i>
    </button>
    
    <div class="sidebar" id="sidebar">
        <div class="p-6 border-b border-white/20"><h2 class="text-xl font-bold">Attendance System</h2></div>
        <div class="p-4 border-b border-white/20"><p><?php echo $_SESSION['user_name']; ?></p></div>
        <nav class="py-4">
            <a href="dashboard.php" class="sidebar-item"><i class="fas fa-tachometer-alt w-5 mr-3"></i> Dashboard</a>
            <a href="manage_teachers.php" class="sidebar-item"><i class="fas fa-users w-5 mr-3"></i> Manage Teachers</a>
            <a href="gps_settings.php" class="sidebar-item bg-white/10"><i class="fas fa-map-marker-alt w-5 mr-3"></i> GPS Settings</a>
            <a href="../../api/logout.php" class="sidebar-item mt-8 hover:bg-red-600"><i class="fas fa-sign-out-alt w-5 mr-3"></i> Logout</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="p-8">
            <div class="max-w-5xl mx-auto">
                <h1 class="text-3xl font-bold text-gray-800 mb-6">GPS Geofence Settings</h1>
                
                <?php if ($message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-xl shadow-2xl overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-600 to-purple-600 p-4">
                            <h2 class="text-xl font-bold text-white"><i class="fas fa-map mr-2"></i> School Location</h2>
                        </div>
                        <div class="p-4">
                            <div id="map"></div>
<button onclick="getCurrentLocation()" class="bg-green-500 text-white px-4 py-2 rounded-lg w-full mt-2">
                                <i class="fas fa-location-dot mr-2"></i> Use My Location (Fast)
                            </button>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-2xl overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-600 to-purple-600 p-4">
                            <h2 class="text-xl font-bold text-white"><i class="fas fa-sliders-h mr-2"></i> Configuration</h2>
                        </div>
                        <div class="p-6">
                            <form method="POST">
                                <div class="mb-4">
                                    <label class="block text-gray-700 font-bold mb-2">School Name</label>
                                    <input type="text" name="school_name" value="<?php echo htmlspecialchars($settings['school_name']); ?>" class="w-full px-3 py-2 border rounded-lg">
                                </div>
                                
<div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 font-bold mb-2">Latitude</label>
                                        <input type="text" name="school_lat" id="school_lat" value="<?php echo $settings['school_lat']; ?>" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g., -0.6072">
                                        <p class="text-xs text-gray-500 mt-1">Click map or enter manually</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 font-bold mb-2">Longitude</label>
                                        <input type="text" name="school_lng" id="school_lng" value="<?php echo $settings['school_lng']; ?>" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g., 30.6545">
                                        <p class="text-xs text-gray-500 mt-1">Click map or enter manually</p>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-gray-700 font-bold mb-2">Radius (meters)</label>
                                    <input type="number" name="geofence_radius" value="<?php echo $settings['geofence_radius']; ?>" class="w-full px-3 py-2 border rounded-lg">
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 font-bold mb-2">Start Time</label>
                                        <input type="time" name="start_time" value="<?php echo $settings['start_time'] ?? '08:00'; ?>" class="w-full px-3 py-2 border rounded-lg">
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 font-bold mb-2">End Time</label>
                                        <input type="time" name="end_time" value="<?php echo $settings['end_time'] ?? '16:00'; ?>" class="w-full px-3 py-2 border rounded-lg">
                                    </div>
                                </div>
                                
<div class="mb-4">
                                    <label class="block text-gray-700 font-bold mb-2">Address</label>
                                    <input type="text" name="location_address" value="<?php echo htmlspecialchars($settings['location_address']); ?>" class="w-full px-3 py-2 border rounded-lg">
                                </div>
                                
                                <hr class="my-4 border-gray-300">
                                <h3 class="text-lg font-bold text-gray-800 mb-3"><i class="fas fa-clock mr-2"></i>Schedule Management</h3>
                                
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 font-bold mb-2">Late Threshold (minutes)</label>
                                        <input type="number" name="late_threshold_minutes" value="<?php echo $settings['late_threshold_minutes'] ?? 15; ?>" min="1" max="120" class="w-full px-3 py-2 border rounded-lg">
                                        <p class="text-xs text-gray-500 mt-1">After this = late</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 font-bold mb-2">Grace Period (minutes)</label>
                                        <input type="number" name="grace_period_minutes" value="<?php echo $settings['grace_period_minutes'] ?? 15; ?>" min="0" max="60" class="w-full px-3 py-2 border rounded-lg">
                                        <p class="text-xs text-gray-500 mt-1">Still counts as present</p>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-gray-700 font-bold mb-2">Absent Cutoff Time</label>
                                        <input type="time" name="absent_cutoff_time" value="<?php echo $settings['absent_cutoff_time'] ?? '17:00'; ?>" class="w-full px-3 py-2 border rounded-lg">
                                        <p class="text-xs text-gray-500 mt-1">Auto-mark absent after</p>
                                    </div>
                                    <div class="flex items-center">
                                        <label class="flex items-center cursor-pointer">
                                            <input type="checkbox" name="auto_absent_enabled" value="1" <?php echo (isset($settings['auto_absent_enabled']) && $settings['auto_absent_enabled']) ? 'checked' : ''; ?> class="w-5 h-5 text-green-600 rounded">
                                            <span class="ml-2 font-bold text-gray-700">Auto Absent</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="bg-blue-50 border-l-4 border-blue-500 p-3 rounded mb-4">
                                    <p class="text-sm text-blue-700"><strong>How it works:</strong></p>
                                    <ul class="text-xs text-blue-600 mt-1 list-disc list-inside">
                                        <li>Check in before Start Time = <span class="text-green-600 font-bold">Present</span></li>
                                        <li>Check in within Grace Period = <span class="text-green-600 font-bold">Present</span></li>
                                        <li>Check in after Late Threshold = <span class="text-yellow-600 font-bold">Late</span></li>
                                        <li>No check-in by Absent Cutoff = <span class="text-red-600 font-bold">Absent</span></li>
                                    </ul>
                                </div>
                                
<button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg font-bold hover:bg-green-700">
                                    <i class="fas fa-save mr-2"></i> Save Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let map, marker, circle;
        
        function initMap() {
            let lat = parseFloat(document.getElementById('school_lat').value) || -0.6072;
            let lng = parseFloat(document.getElementById('school_lng').value) || 30.6545;
            let rad = parseInt(document.querySelector('input[name="geofence_radius"]').value) || 500;
            
            map = L.map('map').setView([lat, lng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
            
            marker = L.marker([lat, lng]).addTo(map).bindPopup('School Location');
            circle = L.circle([lat, lng], { color: '#22c55e', fillColor: '#4ade80', fillOpacity: 0.2, radius: rad }).addTo(map);
            
            map.on('click', function(e) {
                document.getElementById('school_lat').value = e.latlng.lat.toFixed(6);
                document.getElementById('school_lng').value = e.latlng.lng.toFixed(6);
                if (marker) map.removeLayer(marker);
                if (circle) map.removeLayer(circle);
                marker = L.marker([e.latlng.lat, e.latlng.lng]).addTo(map).bindPopup('School Location');
                circle = L.circle([e.latlng.lat, e.latlng.lng], { color: '#22c55e', fillColor: '#4ade80', fillOpacity: 0.2, radius: rad }).addTo(map);
            });
        }
        
function getCurrentLocation() {
            const statusEl = document.createElement('div');
            statusEl.id = 'gpsStatus';
            statusEl.className = 'fixed bottom-4 right-4 bg-blue-600 text-white p-4 rounded-xl shadow-lg z-50';
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Getting GPS location...';
            document.body.appendChild(statusEl);
            
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    pos => {
                        document.getElementById('school_lat').value = pos.coords.latitude.toFixed(6);
                        document.getElementById('school_lng').value = pos.coords.longitude.toFixed(6);
                        initMap();
                        
                        statusEl.innerHTML = `<i class="fas fa-check-circle text-green-400 mr-2"></i> GPS Found!<br>
                            <span class="text-sm">Lat: ${pos.coords.latitude.toFixed(6)}</span><br>
                            <span class="text-sm">Lng: ${pos.coords.longitude.toFixed(6)}</span><br>
                            <span class="text-xs opacity-75">Click SAVE to store</span>`;
                        statusEl.className = 'fixed bottom-4 right-4 bg-green-600 text-white p-4 rounded-xl shadow-lg z-50';
                        
                        setTimeout(() => { if(statusEl) statusEl.remove(); }, 5000);
                    },
                    err => {
                        statusEl.innerHTML = `<i class="fas fa-times-circle text-red-400 mr-2"></i> GPS Error: ${err.message}<br>
                            <span class="text-xs">Try enabling location in browser</span>`;
                        statusEl.className = 'fixed bottom-4 right-4 bg-red-600 text-white p-4 rounded-xl shadow-lg z-50';
                        setTimeout(() => { if(statusEl) statusEl.remove(); }, 5000);
                    },
{ enableHighAccuracy: false, timeout: 5000, maximumAge: 5000 }
                );
            } else {
                statusEl.innerHTML = '<i class="fas fa-times-circle text-red-400 mr-2"></i> GPS not supported by browser';
                statusEl.className = 'fixed bottom-4 right-4 bg-red-600 text-white p-4 rounded-xl shadow-lg z-50';
            }
        }
        
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('open');
        });
        
        initMap();
    </script>
</body>
</html>