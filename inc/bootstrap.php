<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// OpenAI API Configuration
define('OPENAI_API_KEY', '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');

// Cloudinary Configuration
define('CLOUDINARY_URL', 'https://api.cloudinary.com/v1_1/dkcjftn5c/image/upload');
define('CLOUDINARY_UPLOAD_PRESET', 'tmtcrs');

// Database connection
$host = "localhost";
$db   = "glowtime_system";   // ✅ your DB name
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// function to reuse PDO
function pdo() {
    global $pdo;
    return $pdo;
}

/* -------- User Helpers -------- */

function get_user_name($id) {
    $stmt = pdo()->prepare('SELECT name FROM users WHERE id=?');
    $stmt->execute([$id]);
    return $stmt->fetchColumn();
}

function get_user_email($id) {
    $stmt = pdo()->prepare('SELECT email FROM users WHERE id=?');
    $stmt->execute([$id]);
    return $stmt->fetchColumn();
}

function get_user_phone($id) {
    $stmt = pdo()->prepare('SELECT phone FROM users WHERE id=?');
    $stmt->execute([$id]);
    return $stmt->fetchColumn();
}

/* -------- Appointment Helpers -------- */

/**
 * Recommend a service for a user based on booking history
 */
function recommend_service_for_user($user_id) {
    $pdo = pdo();
    $stmt = $pdo->prepare("SELECT service_id, COUNT(*) as cnt
        FROM appointments WHERE client_id=? 
        GROUP BY service_id ORDER BY cnt DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    if ($row) return (int)$row['service_id'];

    // fallback: most popular service overall
    $row = $pdo->query("SELECT service_id, COUNT(*) as cnt 
        FROM appointments GROUP BY service_id ORDER BY cnt DESC LIMIT 1")->fetch();
    if ($row) return (int)$row['service_id'];

    // fallback: first available service
    $s = $pdo->query("SELECT id FROM services LIMIT 1")->fetch();
    return $s ? (int)$s['id'] : null;
}

/**
 * Check if appointment conflicts with existing ones
 * (any overlap = conflict)
 */
function has_conflict($staff_id, DateTime $start, DateTime $end) {
    $pdo = pdo();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments 
        WHERE NOT (end_at <= ? OR start_at >= ?)");
    $stmt->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Suggest available slots for a service (within working hours)
 */
function suggest_available_slots($service_id, $staff_id = null, $days_ahead = 7, $slots_needed = 5) {
    $pdo = pdo();
    $stmt = $pdo->prepare("SELECT duration_minutes FROM services WHERE id=? LIMIT 1");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();
    if (!$service) return [];

    $duration = (int)$service['duration_minutes'];
    $now = new DateTime();
    $results = [];
    $interval = new DateInterval('PT30M'); // check every 30 min

    for ($d=0; $d<$days_ahead && count($results)<$slots_needed; $d++) {
        $day = (clone $now)->modify("+{$d} days");
        $startOfDay = new DateTime($day->format('Y-m-d').' 09:00');
        $endOfDay   = new DateTime($day->format('Y-m-d').' 18:00');

        for ($slot=clone $startOfDay; $slot<$endOfDay; $slot->add($interval)) {
            if ($slot < $now) continue;
            $slotEnd = (clone $slot)->modify("+{$duration} minutes");
            if (has_conflict($staff_id, $slot, $slotEnd)) continue;
            $results[] = ['start'=>$slot->format('Y-m-d H:i:s'), 'end'=>$slotEnd->format('Y-m-d H:i:s')];
            if (count($results) >= $slots_needed) break 2;
        }
    }
    return $results;
}

/* -------- Style Recommendation -------- */

/**
 * Smarter style recommendations (rule-based + optional AI)
 */
function style_recommendation_for_user($user_id, $use_openai=false) {
    $pdo = pdo();
    $stmt = $pdo->prepare("SELECT s.name FROM appointments a 
        JOIN services s ON s.id=a.service_id 
        WHERE a.client_id=? ORDER BY a.start_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $recent = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Try AI if enabled
    if ($use_openai && defined('OPENAI_API_KEY') && OPENAI_API_KEY) {
        $prompt = "User recent services: ".implode(', ', $recent).". Suggest 3 hairstyle/grooming tips.";
        $resp = openai_call($prompt);
        if ($resp) return ['source'=>'openai','text'=>$resp];
    }

    // Rule-based suggestions
    $recs = [];

    if (in_array('Hair Color', $recent)) {
        $recs[] = "Try balayage highlights for a natural sun-kissed look.";
        $recs[] = "Maintain your color with sulfate-free shampoo.";
    }
    if (in_array('Haircut', $recent)) {
        $recs[] = "A layered bob could add volume to your look.";
        $recs[] = "Undercut styles are trending this season.";
    }
    if (in_array('Hair Spa', $recent)) {
        $recs[] = "Regular spa treatments will keep your scalp healthy.";
        $recs[] = "Pair it with a nourishing mask for deep hydration.";
    }
    if (empty($recs)) {
        $recs[] = "Try a haircut tailored to your face shape.";
        $recs[] = "Consult your stylist for seasonal hairstyle trends.";
    }

    return ['source'=>'rules','items'=>$recs];
}

/* -------- Optional AI Integration -------- */

function openai_call($prompt) {
    if (!defined('OPENAI_API_KEY') || !OPENAI_API_KEY) return null;
    $api_key = OPENAI_API_KEY;
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer '.$api_key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a salon assistant.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 200
    ]));
    $res = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($res, true);
    return $json['choices'][0]['message']['content'] ?? null;
}

/* -------- Cloudinary Integration -------- */

/**
 * Upload image to Cloudinary
 * @param string $fileInputName - The name of the file input field
 * @param string $folder - Optional folder name in Cloudinary
 * @return array - ['success' => bool, 'url' => string, 'error' => string]
 */
function uploadToCloudinary($fileInputName, $folder = 'glowtime/payment_proofs') {
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'url' => '', 'error' => 'No file uploaded or upload error'];
    }

    $file = $_FILES[$fileInputName];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $mimeType = mime_content_type($file['tmp_name']);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'url' => '', 'error' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.'];
    }

    // Validate file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'url' => '', 'error' => 'File too large. Maximum size is 10MB.'];
    }

    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, CLOUDINARY_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        
        $postFields = [
            'file' => new CURLFile($file['tmp_name'], $mimeType, $file['name']),
            'upload_preset' => CLOUDINARY_UPLOAD_PRESET,
            'folder' => $folder,
            'public_id' => 'payment_proof_' . time() . '_' . rand(1000, 9999)
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['secure_url'])) {
                return ['success' => true, 'url' => $result['secure_url'], 'error' => ''];
            }
        }

        return ['success' => false, 'url' => '', 'error' => 'Upload failed: ' . $response];
        
    } catch (Exception $e) {
        return ['success' => false, 'url' => '', 'error' => 'Upload error: ' . $e->getMessage()];
    }
}
