<?php
// modules/admin/enroll_face.php - FACE ENROLLMENT PAGE
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireAdmin();

require_once __DIR__ . '/../../config/db.php';

$teacher_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$teacher_name = '';

if ($teacher_id) {
    $database = new Database();
    $conn = $database->getConnection();
    $query = "SELECT u.name FROM users u JOIN teachers t ON u.id = t.user_id WHERE t.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($teacher) $teacher_name = $teacher['name'];
}

if ($teacher_id <= 0) {
    header('Location: manage_teachers.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Enrollment - <?php echo htmlspecialchars($teacher_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.15.0/dist/tf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.5/dist/face-api.min.js"></script>
    <style>
        #video { transform: scaleX(-1); background: #000; border-radius: 12px; }
        .face-guide { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 250px; height: 250px; border: 3px solid #fbbf24; border-radius: 50%; transition: all 0.3s; }
        .face-guide.detected { border-color: #22c55e; box-shadow: 0 0 30px rgba(34,197,94,0.5); background: rgba(34,197,94,0.1); }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <a href="manage_teachers.php" class="text-blue-600">&larr; Back</a>
        <h1 class="text-3xl font-bold mt-2">Face Enrollment</h1>
        <p class="text-gray-600 mb-4">Teacher: <strong><?php echo htmlspecialchars($teacher_name); ?></strong></p>
        
        <div class="bg-white rounded-xl shadow-2xl p-6">
            <div class="relative bg-black rounded-xl overflow-hidden mb-4" style="min-height: 400px;">
                <video id="video" width="100%" height="400" autoplay muted playsinline></video>
                <div class="face-guide" id="faceGuide"></div>
            </div>
            
            <div id="status" class="p-3 rounded-lg mb-4 text-center bg-yellow-100">
                <i class="fas fa-spinner fa-spin mr-2"></i> Loading face models...
            </div>
            
            <button id="enrollBtn" onclick="enrollFace()" disabled class="w-full bg-green-500 text-white py-3 rounded-lg font-bold disabled:opacity-50">
                <i class="fas fa-camera mr-2"></i> Capture & Enroll Face
            </button>
            
            <div id="result" class="mt-4 hidden"></div>
        </div>
    </div>
    
    <script>
        let video = document.getElementById('video');
        let faceGuide = document.getElementById('faceGuide');
        let statusDiv = document.getElementById('status');
        let enrollBtn = document.getElementById('enrollBtn');
        let resultDiv = document.getElementById('result');
        let stream = null;
        let modelsLoaded = false;
        let currentDescriptor = null;
        
        function showResult(message, isError) {
            resultDiv.classList.remove('hidden');
            if (isError) {
                resultDiv.className = 'mt-4 p-3 bg-red-100 text-red-800 rounded-lg';
                resultDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i> ' + message;
            } else {
                resultDiv.className = 'mt-4 p-3 bg-green-100 text-green-800 rounded-lg';
                resultDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i> ' + message;
            }
        }
        
        async function loadModels() {
            statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Loading face models...';
            try {
                const modelUrl = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.5/model/';
                await faceapi.nets.tinyFaceDetector.loadFromUri(modelUrl);
                await faceapi.nets.faceLandmark68Net.loadFromUri(modelUrl);
                await faceapi.nets.faceRecognitionNet.loadFromUri(modelUrl);
                modelsLoaded = true;
                statusDiv.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-2"></i> Ready! Position face in circle.';
                startCamera();
            } catch(e) {
                statusDiv.innerHTML = '<i class="fas fa-exclamation-circle text-red-500 mr-2"></i> Error loading models';
            }
        }
        
        async function startCamera() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
                await video.play();
                startDetection();
            } catch(e) {
                statusDiv.innerHTML = '<i class="fas fa-exclamation-circle text-red-500 mr-2"></i> Camera error';
            }
        }
        
        function startDetection() {
            setInterval(async () => {
                if (!modelsLoaded || video.readyState !== 4) return;
                const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks().withFaceDescriptor();
                
                if (detection && detection.descriptor) {
                    if (!currentDescriptor) {
                        faceGuide.classList.add('detected');
                        statusDiv.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-2"></i> Face detected! Ready to enroll.';
                        enrollBtn.disabled = false;
                    }
                    currentDescriptor = Array.from(detection.descriptor);
                } else {
                    faceGuide.classList.remove('detected');
                    statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> No face detected. Look at camera.';
                    enrollBtn.disabled = true;
                    currentDescriptor = null;
                }
            }, 200);
        }
        
        async function enrollFace() {
            if (!currentDescriptor) {
                showResult('No face detected!', true);
                return;
            }
            
            enrollBtn.disabled = true;
            enrollBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
            
            try {
                const response = await fetch('../../api/save_face_descriptor.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        teacher_id: <?php echo $teacher_id; ?>,
                        face_descriptor: currentDescriptor
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showResult('Face enrolled successfully! Redirecting...', false);
                    setTimeout(() => {
                        window.location.href = 'manage_teachers.php?success=1';
                    }, 2000);
                } else {
                    showResult('Error: ' + result.message, true);
                    enrollBtn.disabled = false;
                    enrollBtn.innerHTML = '<i class="fas fa-camera mr-2"></i> Try Again';
                }
            } catch(e) {
                showResult('Error: ' + e.message, true);
                enrollBtn.disabled = false;
                enrollBtn.innerHTML = '<i class="fas fa-camera mr-2"></i> Try Again';
            }
        }
        
        window.addEventListener('beforeunload', () => { if(stream) stream.getTracks().forEach(t => t.stop()); });
        loadModels();
    </script>
</body>
</html>