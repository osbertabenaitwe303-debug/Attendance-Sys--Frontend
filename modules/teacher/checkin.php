<?php
// modules/teacher/checkin.php - TEACHER CHECKIN WITH FACE VERIFICATION
require_once __DIR__ . '/../../includes/auth.php';
$auth = new Auth();
$auth->requireTeacher();
require_once __DIR__ . '/../../models/Attendance.php';

$attendanceModel = new Attendance();
$database = new Database();
$conn = $database->getConnection();
$teacherId = $attendanceModel->getTeacherIdByUserId($_SESSION['user_id']);
$isFaceEnrolled = $attendanceModel->isFaceEnrolled($teacherId);
$todayStatus = $attendanceModel->getTodayStatus($teacherId);
$hasCheckedIn = ($todayStatus && $todayStatus['check_in']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Check-in</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.15.0/dist/tf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.5/dist/face-api.min.js"></script>
    <style>
        #video { transform: scaleX(-1); background: #000; border-radius: 12px; }
        .face-guide { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 200px; height: 200px; border: 3px solid #fbbf24; border-radius: 50%; transition: all 0.3s; }
        .face-guide.verified { border-color: #22c55e; box-shadow: 0 0 30px rgba(34,197,94,0.5); background: rgba(34,197,94,0.1); }
        .face-guide.failed { border-color: #ef4444; box-shadow: 0 0 30px rgba(239,68,68,0.3); background: rgba(239,68,68,0.1); }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-xl shadow-2xl p-6">
            <h1 class="text-2xl font-bold mb-4">Face Check-in/Out</h1>
            
            <div class="relative bg-black rounded-xl overflow-hidden mb-4" style="min-height: 400px;">
                <video id="video" width="100%" height="400" autoplay muted playsinline></video>
                <canvas id="photoCanvas" width="320" height="240" style="display:none;"></canvas>
                <div class="face-guide" id="faceGuide"></div>
            </div>
            
            <div id="statusMsg" class="p-3 rounded-lg mb-4 text-center bg-yellow-100">
                <i class="fas fa-spinner fa-spin mr-2"></i> Loading...
            </div>
            
            <?php if (!$isFaceEnrolled): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Face Not Enrolled!</strong>
                    <p class="mt-2">You need to enroll your face before you can check in.</p>
                    <a href="enroll_face.php" class="inline-block mt-3 bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                        <i class="fas fa-camera mr-2"></i> Enroll Face Now
                    </a>
                </div>
            <?php elseif (!$hasCheckedIn): ?>
                <button id="checkinBtn" onclick="checkIn()" disabled class="w-full bg-green-500 text-white py-3 rounded-lg font-bold disabled:opacity-50">
                    <i class="fas fa-sign-in-alt mr-2"></i> Check In
                </button>
            <?php elseif ($hasCheckedIn): ?>
                <button id="checkoutBtn" onclick="checkOut()" disabled class="w-full bg-red-500 text-white py-3 rounded-lg font-bold disabled:opacity-50">
                    <i class="fas fa-sign-out-alt mr-2"></i> Check Out
                </button>
            <?php endif; ?>
        </div>
    </div>
    
<script>
        let video = document.getElementById('video');
        let faceGuide = document.getElementById('faceGuide');
        let statusMsg = document.getElementById('statusMsg');
        let checkinBtn = document.getElementById('checkinBtn');
        let checkoutBtn = document.getElementById('checkoutBtn');
        let stream = null;
        let modelsLoaded = false;
        let faceVerified = false;
        let currentDescriptor = null;
        let currentLocation = null;
        let photoCanvas = document.getElementById('photoCanvas');
        let capturedPhoto = null;
        
        // ==================== LIVENESS DETECTION ====================
        let livenessState = 'idle'; // idle, waiting_blink, waiting_smile, passed, failed
        let blinkCount = 0;
        let smileDetected = false;
        let eyesClosedStart = null;
        let lastFaceDetected = null;
        let livenessChecks = [];
        
        // Texture Analysis for Anti-Spoofing
        function analyzeTexture(imageData) {
            const data = imageData.data;
            const width = imageData.width;
            const height = imageData.height;
            
            // Convert to grayscale and check for spoofs
            let sum = 0, sumSq = 0;
            let r, g, b, gray;
            const pixelCount = width * height;
            
            for (let i = 0; i < pixelCount; i++) {
                r = data[i * 4];
                g = data[i * 4 + 1];
                b = data[i * 4 + 2];
                gray = 0.299 * r + 0.587 * g + 0.114 * b;
                sum += gray;
                sumSq += gray * gray;
            }
            
            const mean = sum / pixelCount;
            const variance = (sumSq / pixelCount) - (mean * mean);
            
            // High variance suggests real face, low suggests printed photo
            // Real faces typically have variance > 1000
            const textureScore = variance / 100;
            
            return {
                variance: variance,
                isReal: variance > 500, // Threshold for real face
                score: textureScore
            };
        }
        
// Check if eyes are closed (blink detection)
        function checkBlink(landmarks) {
            if (!landmarks || !landmarks.positions) return false;
            
            // Get eye positions from landmarks
            const leftEye = [
                landmarks.positions[36], landmarks.positions[37], 
                landmarks.positions[38], landmarks.positions[39], 
                landmarks.positions[40], landmarks.positions[41]
            ];
            const rightEye = [
                landmarks.positions[42], landmarks.positions[43], 
                landmarks.positions[44], landmarks.positions[45], 
                landmarks.positions[46], landmarks.positions[47]
            ];
            
            if (!leftEye || !rightEye) return false;
            
            // Calculate eye aspect ratio
            const getEAR = (eye) => {
                if (eye.length < 6) return 1;
                const vertical1 = Math.hypot(eye[1][0] - eye[5][0], eye[1][1] - eye[5][1]);
                const vertical2 = Math.hypot(eye[2][0] - eye[4][0], eye[2][1] - eye[4][1]);
                const horizontal = Math.hypot(eye[0][0] - eye[3][0], eye[0][1] - eye[3][1]);
                return (vertical1 + vertical2) / (2 * horizontal);
            };
            
            const leftEAR = getEAR(leftEye);
            const rightEAR = getEAR(rightEye);
            const avgEAR = (leftEAR + rightEAR) / 2;
            
            // Eyes closed if EAR < 0.2
            if (avgEAR < 0.2) {
                if (eyesClosedStart === null) {
                    eyesClosedStart = Date.now();
                }
                return false;
            } else {
                // Eyes opened - check for blink
                if (eyesClosedStart !== null) {
                    const blinkDuration = Date.now() - eyesClosedStart;
                    if (blinkDuration > 100 && blinkDuration < 400) {
                        blinkCount++;
                        livenessChecks.push({type: 'blink', time: Date.now()});
                    }
                    eyesClosedStart = null;
                }
            }
            return false;
        }
        
// Check if smiling
        function checkSmile(landmarks) {
            if (!landmarks || !landmarks.positions) return false;
            
            // Get mouth positions from landmarks (indices 48-67 for mouth)
            const mouth = [];
            for (let i = 48; i <= 67; i++) {
                if (landmarks.positions[i]) {
                    mouth.push(landmarks.positions[i]);
                }
            }
            
            if (mouth.length < 12) return false;
            
            // Calculate mouth aspect ratio for smile
            const mouthWidth = Math.hypot(mouth[0][0] - mouth[6][0], mouth[0][1] - mouth[6][1]);
            const mouthHeight = Math.hypot(mouth[3][0] - mouth[9][0], mouth[3][1] - mouth[9][1]);
            const mouthRatio = mouthHeight / (mouthWidth + 0.01);
            
            // Smile detected if mouth is open/aspect ratio is significant
            if (mouthRatio > 0.15) {
                if (!smileDetected) {
                    smileDetected = true;
                    livenessChecks.push({type: 'smile', time: Date.now()});
                }
                return true;
            }
            return false;
        }
        
        // Start liveness challenge
        function startLivenessChallenge() {
            livenessState = 'waiting_blink';
            blinkCount = 0;
            smileDetected = false;
            livenessChecks = [];
            
            statusMsg.innerHTML = '<div class="bg-yellow-100 p-4 rounded-lg text-center">' +
                '<i class="fas fa-person-fade-turn-right text-2xl mb-2"></i>' +
                '<p class="font-bold">Step 1: BLINK your eyes 2 times</p>' +
                '<p class="text-sm text-gray-600">Blink slowly and naturally</p>' +
                '</div>';
        }
        
        // Update liveness challenge status
        function updateLivenessChallenge(detection) {
            if (!detection || !detection.landmarks) return;
            
            const landmarks = detection.landmarks;
            
            // Check for blinks
            checkBlink(landmarks);
            
            // Update status based on state
            if (livenessState === 'waiting_blink') {
                if (blinkCount >= 2) {
                    livenessState = 'waiting_smile';
                    statusMsg.innerHTML = '<div class="bg-yellow-100 p-4 rounded-lg text-center">' +
                        '<i class="fas fa-smile text-2xl mb-2"></i>' +
                        '<p class="font-bold">Step 2: SMILE now!</p>' +
                        '<p class="text-sm text-gray-600">Show your teeth or smile naturally</p>' +
                        '</div>';
                }
            } else if (livenessState === 'waiting_smile') {
                checkSmile(landmarks);
                
                if (smileDetected) {
                    // Liveness check passed!
                    livenessState = 'passed';
                    statusMsg.innerHTML = '<div class="bg-green-100 p-4 rounded-lg text-center">' +
                        '<i class="fas fa-check-circle text-2xl text-green-600 mb-2"></i>' +
                        '<p class="font-bold text-green-700">Liveness Verified!</p>' +
                        '<p class="text-sm text-gray-600">Now looking at camera...</p>' +
                        '</div>';
                    
                    // Enable face verification after liveness
                    setTimeout(() => {
                        startVerification();
                    }, 500);
                }
            }
        }
        
// Get real GPS location
        function getLocation() {
            return new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    reject(new Error('Geolocation not supported'));
                    return;
                }
                
                // Show getting location status
                const statusEl = document.getElementById('statusMsg');
                if (statusEl) statusEl.innerHTML = '<i class="fas fa-satellite-dish mr-2"></i> Getting GPS location...';
                
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        currentLocation = {
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                            accuracy: position.coords.accuracy
                        };
                        // Update status with actual location
                        if (statusEl) statusEl.innerHTML = `<i class="fas fa-check-circle text-green-500 mr-2"></i> GPS: ${position.coords.latitude.toFixed(6)}, ${position.coords.longitude.toFixed(6)} (${position.coords.accuracy}m)`;
                        console.log('GPS Captured:', currentLocation);
                        resolve(currentLocation);
                    },
                    (error) => {
                        console.log('GPS Error:', error.message);
                        if (statusEl) statusEl.innerHTML = `<i class="fas fa-exclamation-triangle text-red-500 mr-2"></i> GPS Error: ${error.message}`;
                        resolve(null);
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                );
            });
        }
        
        async function loadModels() {
            statusMsg.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Loading models...';
            
            // Get GPS location on page load
            getLocation().then(loc => {
                if (loc) {
                    console.log('Initial GPS:', loc);
                }
            });
            
            try {
                const modelUrl = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.5/model/';
                await faceapi.nets.tinyFaceDetector.loadFromUri(modelUrl);
                await faceapi.nets.faceLandmark68Net.loadFromUri(modelUrl);
                await faceapi.nets.faceRecognitionNet.loadFromUri(modelUrl);
                modelsLoaded = true;
                statusMsg.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-2"></i> Ready! Look at camera.';
                startCamera();
            } catch(e) {
                statusMsg.innerHTML = '<i class="fas fa-exclamation-circle text-red-500 mr-2"></i> Model load failed';
            }
        }
        
async function startCamera() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
                await video.play();
                
                // Start liveness challenge after camera loads
                statusMsg.innerHTML = '<div class="bg-blue-100 p-4 rounded-lg text-center">' +
                    '<i class="fas fa-shield-alt text-2xl text-blue-600 mb-2"></i>' +
                    '<p class="font-bold">Security Check</p>' +
                    '<p class="text-sm text-gray-600">Please wait...</p>' +
                    '</div>';
                
                // Start liveness verification
                startLivenessChallenge();
                startVerification();
            } catch(e) {
                statusMsg.innerHTML = '<i class="fas fa-exclamation-circle text-red-500 mr-2"></i> Camera error';
            }
        }
        
async function startVerification() {
            setInterval(async () => {
                if (!modelsLoaded || video.readyState !== 4) return;
                
                const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks().withFaceDescriptor();
                
                if (detection && detection.descriptor) {
                    // Update liveness detection first (if not passed yet)
                    if (livenessState !== 'passed') {
                        updateLivenessChallenge(detection);
                    }
                    
                    // Always try to verify face (don't skip!)
                    currentDescriptor = Array.from(detection.descriptor);
                    
                    const response = await fetch('../../api/verify_face.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ teacher_id: <?php echo $teacherId; ?>, face_descriptor: currentDescriptor })
                    });
                    const result = await response.json();
                    
                    if (result.success && result.match) {
                        faceVerified = true;
                        faceGuide.classList.add('verified');
                        faceGuide.classList.remove('failed');
                        statusMsg.innerHTML = `<i class="fas fa-check-circle text-green-500 mr-2"></i> Verified! (${result.confidence}%)`;
                        if (checkinBtn) checkinBtn.disabled = false;
                        if (checkoutBtn) checkoutBtn.disabled = false;
                    } else if (result.enrolled === false) {
                        // Face not enrolled - show special message
                        faceVerified = false;
                        faceGuide.classList.add('failed');
                        faceGuide.classList.remove('verified');
                        statusMsg.innerHTML = `<i class="fas fa-exclamation-triangle text-red-500 mr-2"></i> Face not enrolled! <a href="enroll_face.php" class="underline">Click here to enroll</a>`;
                        if (checkinBtn) checkinBtn.disabled = true;
                        if (checkoutBtn) checkoutBtn.disabled = true;
                    } else {
                        faceVerified = false;
                        faceGuide.classList.add('failed');
                        faceGuide.classList.remove('verified');
                        statusMsg.innerHTML = `<i class="fas fa-times-circle text-red-500 mr-2"></i> Not recognized (${result.confidence || 0}%)`;
                        if (checkinBtn) checkinBtn.disabled = true;
                        if (checkoutBtn) checkoutBtn.disabled = true;
                    }
                } else {
                    faceVerified = false;
                    faceGuide.classList.remove('verified', 'failed');
                    statusMsg.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> No face detected';
                    if (checkinBtn) checkinBtn.disabled = true;
                    if (checkoutBtn) checkoutBtn.disabled = true;
                }
            }, 500);
        }
        
        async function checkIn() {
            if (!faceVerified) { alert('Face verification failed!'); return; }
            
            checkinBtn.disabled = true;
            checkinBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            
            // Capture photo for audit trail
            capturedPhoto = capturePhoto();
            
            // Get GPS location before check-in
            const location = await getLocation();
            console.log('GPS Location:', location);
            
            const response = await fetch('../../api/do_checkin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    teacher_id: <?php echo $teacherId; ?>, 
                    action: 'checkin', 
                    face_descriptor: currentDescriptor,
                    photo: capturedPhoto,
                    latitude: location ? location.latitude : null,
                    longitude: location ? location.longitude : null
                })
            });
            const result = await response.json();
            alert(result.message);
            if (result.success) location.reload();
            else {
                checkinBtn.disabled = false;
                checkinBtn.innerHTML = '<i class="fas fa-sign-in-alt mr-2"></i> Check In';
            }
        }
        
        function capturePhoto() {
            const ctx = photoCanvas.getContext('2d');
            ctx.drawImage(video, 0, 0, photoCanvas.width, photoCanvas.height);
            return photoCanvas.toDataURL('image/jpeg', 0.7);
        }
        
        async function checkOut() {
            if (!faceVerified) { alert('Face verification failed!'); return; }
            
            checkoutBtn.disabled = true;
            checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            
            // Capture photo for audit trail
            capturedPhoto = capturePhoto();
            
            // Get GPS location before check-out
            const location = await getLocation();
            console.log('GPS Location:', location);
            
            const response = await fetch('../../api/do_checkin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    teacher_id: <?php echo $teacherId; ?>, 
                    action: 'checkout', 
                    face_descriptor: currentDescriptor,
                    photo: capturedPhoto,
                    latitude: location ? location.latitude : null,
                    longitude: location ? location.longitude : null
                })
            });
            const result = await response.json();
            alert(result.message);
            if (result.success) location.reload();
            else {
                checkoutBtn.disabled = false;
                checkoutBtn.innerHTML = '<i class="fas fa-sign-out-alt mr-2"></i> Check Out';
            }
        }
        
        loadModels();
        window.addEventListener('beforeunload', () => { if(stream) stream.getTracks().forEach(t => t.stop()); });
    </script>
</body>
</html>