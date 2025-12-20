<?php
require_once 'inc/bootstrap.php';
require_once 'staff.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: login.php");
    exit;
}

// Store user name for display
$_SESSION['user_name'] = get_user_name($_SESSION['user_id']);

$success = $error = "";

// Simple HTML-safe getter
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Email function is now in inc/bootstrap.php

/**
 * Get available stylists for a specific date
 * Excludes stylists who are marked as absent on the given date
 */
function get_available_stylists($date) {
    $allStaff = get_all_staff();
    
    // Get absences for the specified date
    $stmt = pdo()->prepare("
        SELECT assigned_staff_id 
        FROM staff_attendance 
        WHERE date_absent = ? 
        AND (is_deleted = '0' OR is_deleted = '' OR is_deleted IS NULL)
    ");
    $stmt->execute([$date]);
    $absentStaffIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'assigned_staff_id');
    
    // Filter out absent and inactive staff
    $availableStaff = array_filter($allStaff, function($staff) use ($absentStaffIds) {
        return $staff['is_active'] === 'Yes' && !in_array($staff['staff_id'], $absentStaffIds);
    });
    
    return array_values($availableStaff); // Re-index array
}

/**
 * Upload binary QR content to Cloudinary.
 *
 * @param string $binaryContent
 * @param string $bookingRef
 * @return array ['success' => bool, 'url' => string, 'error' => string]
 */
function uploadQrToCloudinary($binaryContent, $bookingRef) {
    $tempFile = tempnam(sys_get_temp_dir(), 'qr_');
    if ($tempFile === false) {
        return ['success' => false, 'url' => '', 'error' => 'Unable to create temporary file for QR upload.'];
    }

    $bytesWritten = file_put_contents($tempFile, $binaryContent);
    if ($bytesWritten === false) {
        @unlink($tempFile);
        return ['success' => false, 'url' => '', 'error' => 'Unable to write QR image to temporary file.'];
    }

    $qrFolder = 'glowtime/qr_codes';
    $publicId = 'qr_code_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower($bookingRef)) . '_' . time();

    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, CLOUDINARY_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        $postFields = [
            'file' => new CURLFile($tempFile, 'image/png', $bookingRef . '.png'),
            'upload_preset' => CLOUDINARY_UPLOAD_PRESET,
            'folder' => $qrFolder,
            'public_id' => $publicId,
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } catch (Exception $e) {
        @unlink($tempFile);
        return ['success' => false, 'url' => '', 'error' => 'QR upload error: ' . $e->getMessage()];
    }

    @unlink($tempFile);

    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['secure_url'])) {
            return ['success' => true, 'url' => $result['secure_url'], 'error' => ''];
        }
    }

    return ['success' => false, 'url' => '', 'error' => 'QR upload failed: ' . $response];
}

/**
 * Generate a QR code image for the booking reference.
 *
 * @param string $bookingRef
 * @return array Returns array with metadata including Cloudinary URL or error message.
 */
function generate_booking_qr($bookingRef) {
    // Use smart fallback redirect page
    // QR codes point to redirect.php which tries to open the app first,
    // then falls back to web invoice if app is not installed
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        $baseUrl = "https://glowtime.ct.ws/redirect.php";
    } else {
        $baseUrl = "http://glowtime.test/redirect.php";
    }

    $qrTargetUrl = $baseUrl . '?ref=' . urlencode($bookingRef);
    
    // Keep web URL as fallback (for reference)
    $webUrl = (defined('ENVIRONMENT') && ENVIRONMENT === 'production') 
        ? "https://glowtime.ct.ws/invoice.php?ref=" . urlencode($bookingRef)
        : "http://glowtime.test/invoice.php?ref=" . urlencode($bookingRef);

    $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrTargetUrl);

    $qrImageContent = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($qrApiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $qrImageContent = curl_exec($ch);
        curl_close($ch);
    }

    if ($qrImageContent === false || $qrImageContent === null) {
        $qrImageContent = @file_get_contents($qrApiUrl);
    }

    if ($qrImageContent === false || empty($qrImageContent)) {
        return [
            'success' => false,
            'url' => '',
            'download_name' => $bookingRef . '.png',
            'target_url' => $qrTargetUrl,
            'error' => 'Unable to generate QR code from API.',
        ];
    }

    $uploadResult = uploadQrToCloudinary($qrImageContent, $bookingRef);

    if (!$uploadResult['success']) {
        return [
            'success' => false,
            'url' => '',
            'download_name' => $bookingRef . '.png',
            'target_url' => $qrTargetUrl,
            'error' => $uploadResult['error'],
        ];
    }

    return [
        'success' => true,
        'url'    => $uploadResult['url'],
        'download_name' => $bookingRef . '.png',
        'target_url'    => $qrTargetUrl, // Smart fallback: redirect.php?ref=... (tries app first, then web)
        'web_url' => $webUrl, // Direct web invoice URL for reference
        'error' => '',
    ];
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id   = $_POST['service_id'] ?? null;
    $style        = trim($_POST['style'] ?? "");
    $date         = $_POST['date'] ?? null;
    $time         = $_POST['time'] ?? null;
    $bookingType  = $_POST['booking_type'] ?? "salon";
    $location     = ($bookingType === "home") ? trim($_POST['location_address'] ?? "") : null;
    $assigned_staff_id = $_POST['assigned_staff_id'] ?? null;

    if ($service_id && $date && $time && $assigned_staff_id) {
        $start_at = date("Y-m-d H:i:s", strtotime("$date $time"));

        // Get service details
        $stmt = pdo()->prepare("SELECT * FROM services WHERE id=?");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch();

        if ($service) {
            $end_at = date("Y-m-d H:i:s", strtotime("+{$service['duration_minutes']} minutes", strtotime($start_at)));

            // ✅ Conflict check - same stylist at overlapping time
            $check = pdo()->prepare("SELECT COUNT(*) FROM appointments 
                WHERE assigned_staff_id = ?
                AND status IN ('pending','confirmed')
                AND (
                    (start_at < ? AND end_at > ?) 
                    OR (start_at < ? AND end_at > ?) 
                    OR (start_at >= ? AND end_at <= ?)
                )");
            $check->execute([$assigned_staff_id, $end_at, $start_at, $start_at, $end_at, $start_at, $end_at]);
            $conflict = $check->fetchColumn();

            if ($conflict > 0) {
                $error = "❌ Sorry, this stylist is already booked for this time slot. Please choose another time or stylist.";
            } else {
                // Transport fee
                $transportFee = 0;
                if ($bookingType === "home") {
                    if (stripos($location, 'Pateros') !== false) {
                        $transportFee = 100.00;
                    } else {
                        $transportFee = 200.00;
                    }
                }

                // Down payment = 30% + transport
                $down_payment = round(($service['price'] * 0.3) + $transportFee, 2);

                // Upload proof to Cloudinary (required)
                $proofFile = null;
                if (empty($_FILES['payment_proof']['name'])) {
                    $error = "❌ Payment proof is required. Please upload a screenshot or photo of your payment receipt.";
                } else {
                    $uploadResult = uploadToCloudinary('payment_proof');
                    if ($uploadResult['success']) {
                        $proofFile = $uploadResult['url'];
                    } else {
                        $error = "❌ " . $uploadResult['error'];
                    }
                }

                if (!$error) {
                    // Generate booking ref
                    $bookingRef = "BOOK-" . date("Ymd") . "-" . rand(100, 999);

                    // Generate QR code uploaded to Cloudinary
                    $qrData = generate_booking_qr($bookingRef);
                    $qrCodeUrl = null;
                    if ($qrData['success']) {
                        $qrCodeUrl = $qrData['url'];
                        $_SESSION['booking_qr'] = [
                            'image_url' => $qrData['url'],
                            'download_name' => $qrData['download_name'],
                            'target_url' => $qrData['target_url'],
                        ];
                    } else {
                        $_SESSION['booking_qr_error'] = "We couldn't generate your QR code automatically. You can still view your booking details in the history page.";
                    }

                    $stmt = pdo()->prepare("INSERT INTO appointments 
                        (booking_ref, client_id, service_id, booking_type, location_address, style, start_at, end_at, down_payment, transport_fee, payment_proof, qr_code_url, payment_status, status, assigned_staff_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?)");
                    $stmt->execute([
                        $bookingRef,
                        $_SESSION['user_id'],
                        $service_id,
                        $bookingType,
                        $location,
                        $style,
                        $start_at,
                        $end_at,
                        $down_payment,
                        $transportFee,
                        $proofFile,
                        $qrCodeUrl,
                        $assigned_staff_id
                    ]);

                    // Get client email and name for notification
                    $clientEmail = get_user_email($_SESSION['user_id']);
                    $clientName = get_user_name($_SESSION['user_id']);
                    
                    // Get stylist name
                    $stylistName = 'Not Assigned';
                    if ($assigned_staff_id) {
                        $stmtStylist = pdo()->prepare("SELECT staff_name FROM staff WHERE staff_id = ?");
                        $stmtStylist->execute([$assigned_staff_id]);
                        $stylist = $stmtStylist->fetch(PDO::FETCH_ASSOC);
                        if ($stylist) {
                            $stylistName = $stylist['staff_name'];
                        }
                    }

                    // Send booking confirmation email
                    if ($clientEmail) {
                        $invoiceUrl = (defined('ENVIRONMENT') && ENVIRONMENT === 'production') 
                            ? "https://glowtime.ct.ws/invoice.php?ref=" . urlencode($bookingRef)
                            : "http://glowtime.test/invoice.php?ref=" . urlencode($bookingRef);
                        
                        $emailMessage = "
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { background: linear-gradient(135deg, #e91e63 0%, #f06292 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                                .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
                                .booking-details { background: white; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #e91e63; }
                                .detail-row { margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
                                .detail-row:last-child { border-bottom: none; }
                                .label { font-weight: bold; color: #666; display: inline-block; width: 150px; }
                                .value { color: #333; }
                                .status-badge { display: inline-block; padding: 5px 15px; border-radius: 20px; font-weight: bold; }
                                .status-pending { background: #ffc107; color: #000; }
                                .footer { text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
                                .btn { display: inline-block; padding: 12px 24px; background: #e91e63; color: white; text-decoration: none; border-radius: 5px; margin-top: 15px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h2>🌸 Glowtime Salon</h2>
                                    <p>Booking Confirmation</p>
                                </div>
                                <div class='content'>
                                    <p>Hello <strong>" . h($clientName) . "</strong>,</p>
                                    <p>Thank you for booking with Glowtime Salon! Your appointment has been successfully created.</p>
                                    
                                    <div class='booking-details'>
                                        <h3 style='margin-top: 0; color: #e91e63;'>📋 Booking Details</h3>
                                        
                                        <div class='detail-row'>
                                            <span class='label'>Booking Reference:</span>
                                            <span class='value'><strong>" . h($bookingRef) . "</strong></span>
                                        </div>
                                        
                                        <div class='detail-row'>
                                            <span class='label'>Service:</span>
                                            <span class='value'>" . h($service['name']) . "</span>
                                        </div>
                                        
                                        " . (!empty($style) ? "
                                        <div class='detail-row'>
                                            <span class='label'>Preferred Style:</span>
                                            <span class='value'>" . h($style) . "</span>
                                        </div>
                                        " : "") . "
                                        
                                        <div class='detail-row'>
                                            <span class='label'>Stylist:</span>
                                            <span class='value'>" . h($stylistName) . "</span>
                                        </div>
                                        
                                        <div class='detail-row'>
                                            <span class='label'>Date:</span>
                                            <span class='value'>" . date("F d, Y", strtotime($start_at)) . "</span>
                                        </div>
                                        
                                        <div class='detail-row'>
                                            <span class='label'>Time:</span>
                                            <span class='value'>" . date("h:i A", strtotime($start_at)) . " - " . date("h:i A", strtotime($end_at)) . "</span>
                                        </div>
                                        
                                        <div class='detail-row'>
                                            <span class='label'>Booking Type:</span>
                                            <span class='value'>" . ($bookingType === 'home' ? '🏠 Home Service' : '🏢 Salon Visit') . "</span>
                                        </div>
                                        
                                        " . ($bookingType === 'home' && !empty($location) ? "
                                        <div class='detail-row'>
                                            <span class='label'>Address:</span>
                                            <span class='value'>" . h($location) . "</span>
                                        </div>
                                        " : "") . "
                                        
                                        <div class='detail-row'>
                                            <span class='label'>Down Payment:</span>
                                            <span class='value'><strong>₱" . number_format($down_payment, 2) . "</strong></span>
                                        </div>
                                        
                                        " . ($transportFee > 0 ? "
                                        <div class='detail-row'>
                                            <span class='label'>Transport Fee:</span>
                                            <span class='value'>₱" . number_format($transportFee, 2) . "</span>
                                        </div>
                                        " : "") . "
                                        
                                        <div class='detail-row'></div>
                                            <span class='label'>Payment Status:</span>
                                            <span class='value'><span class='status-badge status-pending'>Pending Verification</span></span>
                                        </div>
                                        
                                        <div class='detail-row'>
                                            <span class='label'>Appointment Status:</span>
                                            <span class='value'><span class='status-badge status-pending'>Pending</span></span>
                                        </div>
                                    </div>
                                    
                                    <p><strong>📌 What happens next?</strong></p>
                                    <p>Your booking is now pending admin verification. Our team will review your payment proof (if uploaded) and confirm your appointment within 24 hours. You will receive another email once your booking is confirmed.</p>
                                    
                                    <div style='text-align: center;'>
                                        <a href='" . $invoiceUrl . "' class='btn'>View Booking Invoice</a>
                                    </div>
                                    
                                    <p style='margin-top: 20px;'>If you have any questions, please don't hesitate to contact us.</p>
                                    
                                    <p>✨ We look forward to serving you!</p>
                                    <p><strong>Glowtime Salon Team</strong></p>
                                </div>
                                <div class='footer'>
                                    <p>This is an automated email. Please do not reply to this message.</p>
                                    <p>&copy; " . date('Y') . " Glowtime Salon. All rights reserved.</p>
                                </div>
                            </div>
                        </body>
                        </html>";
                        
                        $emailResult = sendEmail(
                            $clientEmail,
                            "Booking Confirmation - " . h($bookingRef) . " | Glowtime Salon",
                            $emailMessage
                        );
                        
                        // Log email result (optional - for debugging)
                        if (!$emailResult['success']) {
                            error_log("Failed to send booking confirmation email to {$clientEmail}: " . $emailResult['error']);
                        }
                    }

                    // Redirect to history page with success message
                    $_SESSION['booking_success'] = "✅ Booking successful! Your reference is ".$bookingRef.". Please wait for admin verification.";
                    header("Location: client_history.php");
                    exit;
                }
            }
        } else {
            $error = "❌ Invalid service selection.";
        }
    } else {
        $error = "❌ Please complete all required fields.";
    }
}
?>
<?php include 'inc/header_sidebar.php'; ?>

  <style>
/* Mobile-optimized Booking Page Styles */
@media (max-width: 768px) {
    /* Main content - Full width on mobile with no side padding - Account for fixed header */
    .main-content {
        width: 100% !important;
        max-width: 100% !important;
        margin-left: 0 !important;
        padding: calc(60px + 1rem) 0 1rem 0 !important; /* Account for fixed header (60px header + 1rem spacing) */
        box-sizing: border-box;
    }
    
    /* Mobile header - Full width with minimal side padding - FIXED POSITION */
    .mobile-header {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        z-index: 1050 !important;
        margin: 0 !important;
        padding: 0.75rem 0.5rem !important; /* Consistent with other pages - reduced vertical padding */
        gap: 0.5rem;
        background: var(--user-card-bg, #fff) !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1) !important;
        min-height: 60px !important; /* Consistent height */
        max-height: 60px !important; /* Consistent height */
        display: flex !important;
        align-items: center !important;
    }
    
    /* Mobile header buttons - Consistent sizing */
    .mobile-header .btn {
        margin: 0 !important;
        padding: 0.5rem 0.75rem !important;
        min-width: 44px !important;
        min-height: 44px !important;
        max-height: 44px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 0.875rem !important;
    }
    
    .mobile-header .dropdown-toggle {
        padding: 0.5rem 0.75rem !important;
        min-width: 44px !important;
        min-height: 44px !important;
        max-height: 44px !important;
        font-size: 0.875rem !important;
    }
    
    .mobile-header > div {
        margin: 0 !important;
        display: flex !important;
        align-items: center !important;
    }
    
    .container-fluid {
        width: 100% !important;
        max-width: 100% !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin: 0 !important;
    }
    
    /* Page Header - Smaller on mobile */
    .page-header {
        padding: 0 1rem !important;
        margin-bottom: 1.5rem !important;
    }
    
    .page-header h1 {
        font-size: 1.5rem !important;
        margin-bottom: 0.25rem;
    }
    
    .page-header p {
        font-size: 0.9rem !important;
    }
    
    /* Hide Back to Dashboard button on mobile */
    .page-header .btn {
        display: none !important;
    }
    
    /* Cards - Full width with internal padding only */
    .card {
        border-radius: 0 !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    
    /* Step Indicator - Smaller on mobile */
    .step-indicator {
        margin-bottom: 1.5rem !important;
        padding: 0 1rem;
    }
    
    .step-indicator .step-circle {
        width: 32px !important;
        height: 32px !important;
        line-height: 32px !important;
        font-size: 0.875rem !important;
        margin: 0 5px !important;
        color: #666 !important;
    }
    
    .step-indicator .step-circle.active {
        color: white !important;
        background: var(--salon-primary, #e91e63) !important;
    }
    
    .step-indicator .step-circle.completed {
        color: white !important;
        background: #198754 !important;
    }
    
    /* Step Header - Smaller on mobile */
    .step-header h4 {
        font-size: 1.25rem !important;
        margin-bottom: 0.5rem;
    }
    
    .step-header p {
        font-size: 0.875rem !important;
    }
    
    /* Form labels - Smaller on mobile */
    .form-label {
        font-size: 0.95rem !important;
    }
    
    .form-label.fw-bold {
        font-size: 1rem !important;
    }
    
    /* Service cards - Stack on mobile */
    .service-booking-card {
        margin-bottom: 1rem;
    }
    
    .service-booking-card .card-title {
        font-size: 1rem !important;
    }
    
    /* Booking type cards - Stack on mobile */
    .booking-type-card {
        margin-bottom: 1rem;
    }
    
    .booking-type-card h6 {
        font-size: 1rem !important;
    }
    
    .booking-type-card .fs-1 {
        font-size: 2.5rem !important;
    }
    
    /* Form controls - Better touch targets */
    .form-control,
    .form-select {
        font-size: 16px !important; /* Prevents iOS zoom */
        padding: 0.875rem 1rem;
        min-height: 48px;
    }
    
    .form-control-lg {
        font-size: 16px !important;
        padding: 1rem 1.25rem;
        min-height: 52px;
    }
    
    /* Buttons - Smaller on mobile */
    .btn-lg {
        padding: 0.875rem 1.25rem !important;
        font-size: 1rem !important;
        min-height: 48px;
    }
    
    .btn {
        padding: 0.75rem 1rem !important;
        font-size: 0.95rem !important;
    }
    
    /* Payment info cards */
    .payment-info-card,
    .payment-methods-card {
        margin-bottom: 1rem !important;
    }
    
    .payment-info-card .fs-4 {
        font-size: 1.5rem !important;
    }
    
    /* Review card */
    .review-card {
        margin-bottom: 1.5rem !important;
    }
    
    .review-item {
        padding: 0.5rem 0 !important;
        font-size: 0.9rem;
    }
    
    .review-label {
        font-size: 0.875rem !important;
    }
    
    .review-value {
        font-size: 0.875rem !important;
    }
    
    /* Alerts - Better spacing */
    .alert {
        margin: 0 1rem 1rem 1rem !important;
        font-size: 0.9rem;
    }
    
    /* Step content padding */
    .step {
        padding: 0 0.5rem;
    }
    
    /* Row padding for cards */
    .row.justify-content-center {
        margin: 0 !important;
        padding: 0 0.5rem !important;
    }
    
    .row:not(.justify-content-center) {
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    
    .row > [class*="col-"] {
        padding-left: 0.25rem !important;
        padding-right: 0.25rem !important;
    }
    
    /* Service cards container - Reduce padding */
    #serviceCards {
        margin-left: -0.25rem !important;
        margin-right: -0.25rem !important;
    }
    
    #serviceCards > [class*="col-"] {
        padding-left: 0.25rem !important;
        padding-right: 0.25rem !important;
    }
    
    /* Main card body - Reduce padding */
    .card-body {
        padding: 1rem 0.75rem !important;
    }
    
    /* Selected service info */
    #selectedServiceInfo {
        margin: 1rem 0 !important;
        font-size: 0.9rem;
    }
    
    /* Form text - Smaller */
    .form-text {
        font-size: 0.8rem !important;
    }
    
    /* Badges - Smaller */
    .badge {
        font-size: 0.75rem !important;
        padding: 0.35rem 0.65rem;
    }
}

/* Tablet adjustments */
@media (min-width: 769px) and (max-width: 991px) {
    .step-indicator .step-circle {
        width: 36px;
        height: 36px;
        line-height: 36px;
        margin: 0 8px;
    }
    
    .page-header h1 {
        font-size: 1.75rem;
    }
    
    /* Reduce padding on tablet */
    .step {
        padding: 0 0.75rem;
    }
    
    .row.justify-content-center {
        padding: 0 0.75rem !important;
    }
    
    .card-body {
        padding: 1.25rem 1rem !important;
    }
    
    #serviceCards {
        margin-left: -0.5rem !important;
        margin-right: -0.5rem !important;
    }
    
    #serviceCards > [class*="col-"] {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }
}
    .step { display:none; }
    .step.active { display:block; }
    .step-indicator { text-align:center; margin-bottom:20px; }
.step-indicator .step-circle { 
    display:inline-block; 
    width: 40px; 
    height: 40px; 
    line-height: 40px; 
    border-radius:50%; 
    background:#f8f9fa; 
    margin:0 10px; 
    font-weight:bold;
    border: 2px solid #dee2e6;
    color: #666;
    text-align: center;
    vertical-align: middle;
    position: relative;
    z-index: 1;
}
.step-indicator .step-circle.active { 
    background: var(--salon-primary, #e91e63) !important; 
    color: white !important; 
    border-color: var(--salon-primary, #e91e63) !important;
}
.step-indicator .step-circle.completed { 
    background: #198754 !important; 
    color: white !important; 
    border-color: #198754 !important;
}
.review-box { 
    background: var(--salon-light); 
    border: 2px solid var(--salon-primary); 
    border-radius:12px; 
    padding:20px; 
    margin-top:20px; 
}

.booking-type-card {
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid #dee2e6;
}

.booking-type-card:hover {
    border-color: var(--salon-primary);
    transform: translateY(-2px);
    box-shadow: var(--salon-shadow);
}

.booking-type-card.selected {
    border-color: var(--salon-primary);
    background: var(--salon-light);
}

.service-booking-card {
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid #dee2e6;
}

.service-booking-card:hover {
    border-color: var(--salon-primary);
    transform: translateY(-2px);
    box-shadow: var(--salon-shadow);
}

.service-booking-card.selected {
    border-color: var(--salon-primary);
    background: var(--salon-light);
    transform: translateY(-2px);
    box-shadow: var(--salon-shadow);
}

.payment-info-card, .payment-methods-card {
    border: none;
    box-shadow: var(--salon-shadow);
}

.payment-method {
    display: flex;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.payment-method:last-child {
    border-bottom: none;
}

.payment-method i {
    margin-right: 0.5rem;
    width: 20px;
}

.review-card {
    border: none;
    box-shadow: var(--salon-shadow);
}

.review-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.review-item:last-child {
    border-bottom: none;
}

.review-label {
    font-weight: 600;
    color: #666;
    display: flex;
    align-items: center;
}

.review-label i {
    margin-right: 0.5rem;
}

.review-value {
    font-weight: 500;
    text-align: right;
}

.step-header {
    text-align: center;
    border-bottom: 2px solid var(--salon-light);
    padding-bottom: 1rem;
}
  </style>
  <script>
    function selectBookingType(type, element) {
      // Update hidden select
      document.getElementById("booking_type").value = type;
      
      // Update card selection visual
      document.querySelectorAll('.booking-type-card').forEach(card => {
        card.classList.remove('selected');
      });
      element.classList.add('selected');
      
      // Toggle location field
      toggleLocation();
      
      // Recalculate down payment
      calculateDownPayment(null);
    }
    
    function toggleLocation() {
      const type = document.getElementById("booking_type").value;
      const locationField = document.getElementById("locationField");
      if (type === "home") {
        locationField.style.display = "block";
        document.getElementById("location_address").required = true;
      } else {
        locationField.style.display = "none";
        document.getElementById("location_address").required = false;
      }
    }

    let bookedSlots = [];

    function loadBookedTimes() {
      let service = document.getElementById("service").value;
      let date = document.getElementById("date").value;
      if (!service || !date) return;

      fetch(`get_booked_times.php?service_id=${service}&date=${date}`)
        .then(res => res.json())
        .then(data => { bookedSlots = data; });
    }

    document.addEventListener("DOMContentLoaded", () => {
      document.getElementById("time").addEventListener("change", function() {
        let chosen = this.value;
        let notice = document.getElementById("timeNotice");
        let conflict = bookedSlots.some(slot => chosen >= slot.start && chosen < slot.end);

        if (conflict) {
          notice.style.display = "block";
          this.value = "";
        } else {
          notice.style.display = "none";
        }
      });
      
      // Update down payment when location address changes (for home service)
      document.getElementById("location_address").addEventListener("input", function() {
        calculateDownPayment(null);
      });
      
      // Update down payment when booking type changes
      document.getElementById("booking_type").addEventListener("change", function() {
        calculateDownPayment(null);
      });
    });

    function nextStep(step) {
      // Validation for step 1 (service selection)
      if (step === 2) {
        let selectedService = document.getElementById("service").value;
        if (!selectedService) {
          alert("Please select a service before proceeding.");
          return;
        }
      }
      
      // Validation for step 2 (date & time) - load stylists when moving to step 3
      if (step === 3) {
        let date = document.getElementById("date").value;
        let time = document.getElementById("time").value;
        if (!date || !time) {
          alert("Please select both date and time before proceeding.");
          return;
        }
        // Load available stylists for selected date
        loadAvailableStylists(date);
      }
      
      // Validation for step 3 (stylist selection)
      if (step === 4) {
        let selectedStylist = document.getElementById("assigned_staff_id").value;
        if (!selectedStylist) {
          alert("Please select a stylist before proceeding.");
          return;
        }
      }
      
      // Validation for step 4 (payment proof upload) - before moving to review
      if (step === 5) {
        let paymentProof = document.getElementById("payment_proof");
        if (!paymentProof.files || paymentProof.files.length === 0) {
          alert("Please upload proof of payment before proceeding to review.");
          paymentProof.focus();
          return;
        }
      }
      
      document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
      document.getElementById('step' + step).classList.add('active');
      updateIndicator(step);

      // Update review step (now step 5)
      if (step === 5) {
        let serviceSelect = document.getElementById("service");
        let serviceName = serviceSelect.options[serviceSelect.selectedIndex].text;
        document.getElementById("reviewService").innerText = serviceName;
        document.getElementById("reviewDate").innerText = document.getElementById("date").value;
        document.getElementById("reviewTime").innerText = document.getElementById("time").value;
        document.getElementById("reviewStyle").innerText = document.getElementById("style").value || "None";
        document.getElementById("reviewType").innerText = document.getElementById("booking_type").value;
        document.getElementById("reviewAddress").innerText = document.querySelector("[name='location_address']").value || "N/A";
        document.getElementById("reviewDownPayment").innerText = document.getElementById("downPayment").innerText;
        
        // Add stylist name
        let stylistSelect = document.getElementById("assigned_staff_id");
        let stylistName = stylistSelect.options[stylistSelect.selectedIndex]?.text || "Not selected";
        document.getElementById("reviewStylist").innerText = stylistName;
      }
    }
    
    // Load available stylists via AJAX
    function loadAvailableStylists(date) {
      console.log('Loading stylists for date:', date);
      
      fetch(`get_available_stylists.php?date=${date}`)
        .then(res => {
          console.log('Response status:', res.status);
          return res.text();
        })
        .then(text => {
          console.log('Raw response:', text);
          return JSON.parse(text);
        })
        .then(data => {
          console.log('Parsed data:', data);
          
          const stylistSelect = document.getElementById("assigned_staff_id");
          stylistSelect.innerHTML = '<option value="">-- Choose a Stylist --</option>';
          
          // Check if data has error property
          if (data.error) {
            console.error('API Error:', data.error);
            stylistSelect.innerHTML += '<option value="" disabled>Error loading stylists</option>';
            return;
          }
          
          // Handle array response
          const stylists = Array.isArray(data) ? data : (data.data || []);
          console.log('Stylists count:', stylists.length);
          
          if (stylists.length === 0) {
            stylistSelect.innerHTML += '<option value="" disabled>No stylists available on this date</option>';
          } else {
            stylists.forEach(stylist => {
              console.log('Adding stylist:', stylist);
              const option = document.createElement('option');
              option.value = stylist.staff_id;
              option.textContent = stylist.staff_name;
              stylistSelect.appendChild(option);
            });
          }
        })
        .catch(err => {
          console.error('Error loading stylists:', err);
          alert('Error loading stylists. Please try again. Check console for details.');
        });
    }

    function prevStep(step) {
      document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
      document.getElementById('step' + step).classList.add('active');
      updateIndicator(step);
    }

    function updateIndicator(activeStep) {
      for (let i = 1; i <= 5; i++) {
        document.getElementById('indicator-' + i).classList.remove('active');
      }
      document.getElementById('indicator-' + activeStep).classList.add('active');
    }

    function selectService(serviceId, price, duration, element) {
      // Update hidden select
      const serviceSelect = document.getElementById("service");
      serviceSelect.value = serviceId;
      
      // Trigger change event to update down payment
      serviceSelect.dispatchEvent(new Event('change'));
      
      // Update card selection visual
      document.querySelectorAll('.service-booking-card').forEach(card => {
        card.classList.remove('selected');
      });
      element.classList.add('selected');
      
      // Update selected service info
      const selectedInfoBox = document.getElementById("selectedServiceInfo");
      const selectedInfoName = document.getElementById("selectedServiceName");
      if (selectedInfoBox && selectedInfoName) {
        selectedInfoBox.style.display = "block";
        selectedInfoName.innerText = element.querySelector('.card-title').innerText;
      }
      
      // Calculate and update down payment with transport
      calculateDownPayment(price);
      
      // Load booked times
      loadBookedTimes();
    }
    
    function clearServiceSelection() {
      // Clear hidden select
      document.getElementById("service").value = "";
      
      // Remove selection visual
      document.querySelectorAll('.service-booking-card').forEach(card => {
        card.classList.remove('selected');
      });
      
      // Hide selected service info
      const selectedInfoBox = document.getElementById("selectedServiceInfo");
      const selectedInfoName = document.getElementById("selectedServiceName");
      if (selectedInfoBox) {
        selectedInfoBox.style.display = "none";
      }
      if (selectedInfoName) {
        selectedInfoName.innerText = "";
      }
      
      // Reset down payment
      updateDownPayment();
    }

    function calculateDownPayment(price) {
      if (!price) {
        let select = document.getElementById("service");
        price = select.options[select.selectedIndex]?.getAttribute("data-price");
      }
      
      if (!price) {
        document.getElementById("downPayment").innerText = "₱0.00";
        return;
      }
      
      // Calculate 30% down payment
      let baseDownPayment = parseFloat(price) * 0.3;
      
      // Get booking type
      let bookingType = document.getElementById("booking_type").value;
      
      // Calculate transport fee if home service
      let transportFee = 0;
      if (bookingType === "home") {
        let location = document.getElementById("location_address").value.trim().toLowerCase();
        if (location.includes('pateros')) {
          transportFee = 100.00;
        } else if (location !== '') {
          transportFee = 200.00;
        } else {
          // If location not entered yet, show range
          document.getElementById("downPayment").innerText = "₱" + baseDownPayment.toFixed(2) + " + ₱100-200 (transport)";
          return;
        }
      }
      
      // Total down payment
      let totalDownPayment = baseDownPayment + transportFee;
      
      if (transportFee > 0) {
        document.getElementById("downPayment").innerText = "₱" + totalDownPayment.toFixed(2) + " (includes ₱" + transportFee.toFixed(2) + " transport)";
      } else {
        document.getElementById("downPayment").innerText = "₱" + totalDownPayment.toFixed(2);
      }
    }
    
    function updateDownPayment() {
      calculateDownPayment(null);
    }
  </script>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4 page-header">
    <div>
        <h1 class="h2 text-salon mb-0">
            <i class="bi bi-calendar-plus"></i> Book Appointment
        </h1>
        <p class="text-muted mb-0">Schedule your salon visit in 5 easy steps</p>
    </div>
    <div class="d-none d-md-block">
        <a href="client_dashboard.php" class="btn btn-outline-salon">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <!-- Alerts -->
    <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
    <?php elseif ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
    <?php endif; ?>

    <?php if (!$success): ?>
        <div class="card">
            <div class="card-body">
                <!-- Step Indicator -->
                <div class="step-indicator mb-4">
                    <span id="indicator-1" class="step-circle active">1</span>
                    <span id="indicator-2" class="step-circle">2</span>
                    <span id="indicator-3" class="step-circle">3</span>
                    <span id="indicator-4" class="step-circle">4</span>
                    <span id="indicator-5" class="step-circle">5</span>
      </div>

                <form method="post" enctype="multipart/form-data" id="bookingForm">
                    
                    <!-- Step 1: Service Selection -->
      <div class="step active" id="step1">
                        <div class="step-header mb-4">
                            <h4 class="text-salon">
                                <i class="bi bi-scissors"></i> Choose Your Service
                            </h4>
                            <p class="text-muted">Select the service you'd like to book</p>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold mb-3">
                                <i class="bi bi-list-ul"></i> Select Service *
                            </label>
                            
                            <div class="row" id="serviceCards">
          <?php
                                $services = pdo()->query("SELECT * FROM services ORDER BY name")->fetchAll();
          foreach ($services as $s):
              // Get service image URL (similar to services.php)
              $img = '';
              if (!empty($s['image_url'])) {
                  $img = $s['image_url'];
              } else {
                  // Fallback to local image if exists
                  $dir = __DIR__ . '/images/services';
                  $candidates = glob($dir . "/service{$s['id']}.*");
                  if ($candidates && file_exists($candidates[0])) {
                      $filename = str_replace($_SERVER['DOCUMENT_ROOT'], '', $candidates[0]);
                      $img = (strpos($filename, '/') === 0 ? $filename : 'images/services/' . basename($candidates[0]));
                  } else {
                      $img = 'images/default.jpg';
                  }
              }
          ?>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <div class="card service-booking-card h-100" onclick="selectService(<?= $s['id'] ?>, <?= $s['price'] ?>, <?= $s['duration_minutes'] ?>, this)">
                                        <img src="<?= htmlspecialchars($img) ?>" class="card-img-top" alt="<?= htmlspecialchars($s['name']) ?>" style="height: 200px; object-fit: cover;">
                                        <div class="card-body d-flex flex-column">
                                            <h6 class="card-title text-salon mb-2"><?= htmlspecialchars($s['name']) ?></h6>
                                            <div class="mb-2">
                                                <span class="badge bg-success">₱<?= number_format((float)$s['price'],2) ?></span>
                                                <span class="badge bg-info"><?= (int)$s['duration_minutes'] ?> mins</span>
                                            </div>
                                            <small class="text-muted mt-auto">Click to select this service</small>
                                        </div>
                                    </div>
                                </div>
          <?php endforeach; ?>
                            </div>
                            
                            <!-- Hidden select for form submission -->
                            <select name="service_id" id="service" class="d-none" required onchange="updateDownPayment(); loadBookedTimes();">
                                <option value="">-- Choose a Service --</option>
          <?php foreach ($services as $s): ?>
                                    <option value="<?= $s['id'] ?>" data-price="<?= $s['price'] ?>" data-duration="<?= $s['duration_minutes'] ?>">
                                        <?= htmlspecialchars($s['name']) ?> - ₱<?= number_format($s['price'],2) ?> (<?= $s['duration_minutes'] ?> mins)
                                    </option>
          <?php endforeach; ?>
                            </select>
                            
                            <div id="selectedServiceInfo" class="alert alert-success mt-3" style="display: none;">
                                <i class="bi bi-check-circle"></i>
                                <strong>Selected:</strong> <span id="selectedServiceName"></span>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="clearServiceSelection()">
                                    <i class="bi bi-x"></i> Change
                                </button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="booking_type" class="form-label fw-bold">
                                <i class="bi bi-geo-alt"></i> Booking Type *
                            </label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card booking-type-card" onclick="selectBookingType('salon', this)">
                                        <div class="card-body text-center">
                                            <i class="bi bi-building fs-1 text-salon mb-2"></i>
                                            <h6>Salon Visit</h6>
                                            <p class="text-muted small mb-0">Visit our beautiful salon</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card booking-type-card" onclick="selectBookingType('home', this)">
                                        <div class="card-body text-center">
                                            <i class="bi bi-house fs-1 text-salon mb-2"></i>
                                            <h6>Home Service</h6>
                                            <p class="text-muted small mb-0">We come to you</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <select name="booking_type" id="booking_type" class="d-none" onchange="toggleLocation()" required>
          <option value="salon">Salon Appointment</option>
          <option value="home">Home Service</option>
        </select>
                        </div>

                        <div id="locationField" class="mb-4" style="display:none;">
                            <label for="location_address" class="form-label fw-bold">
                                <i class="bi bi-map"></i> Home Address *
                            </label>
                            <textarea class="form-control" name="location_address" id="location_address" rows="3" placeholder="Enter your complete home address..."></textarea>
                            <div class="form-text">
                                <i class="bi bi-info-circle"></i> 
                                Transport fee: ₱100 (Pateros area) | ₱200 (Other areas)
                            </div>
        </div>

                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-salon btn-lg" onclick="nextStep(2)">
                                Next Step <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
      </div>

                    <!-- Step 2: Date & Time -->
      <div class="step" id="step2">
                        <div class="step-header mb-4">
                            <h4 class="text-salon">
                                <i class="bi bi-calendar"></i> Select Date & Time
                            </h4>
                            <p class="text-muted">Choose your preferred appointment schedule</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="date" class="form-label fw-bold">
                                        <i class="bi bi-calendar-date"></i> Appointment Date *
                                    </label>
                                    <input type="date" class="form-control form-control-lg" name="date" id="date" required min="<?= date('Y-m-d') ?>" onchange="loadBookedTimes()">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="time" class="form-label fw-bold">
                                        <i class="bi bi-clock"></i> Appointment Time *
                                    </label>
                                    <input type="time" class="form-control form-control-lg" name="time" id="time" required>
                                    <div id="timeNotice" class="alert alert-warning mt-2" style="display:none;">
                                        <i class="bi bi-exclamation-triangle"></i> This time slot is already booked. Please choose another time.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="style" class="form-label fw-bold">
                                <i class="bi bi-palette"></i> Preferred Style <span class="text-muted">(Optional)</span>
                            </label>
                            <input type="text" class="form-control" name="style" id="style" placeholder="e.g., Bob Cut, Balayage, Long Layers...">
                            <div class="form-text">
                                <i class="bi bi-lightbulb"></i> 
                                Describe your desired style or let our professionals recommend the best option for you.
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary btn-lg" onclick="prevStep(1)">
                                <i class="bi bi-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-salon btn-lg" onclick="nextStep(3)">
                                Next Step <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
      </div>

                    <!-- Step 3: Stylist Selection -->
      <div class="step" id="step3">
                        <div class="step-header mb-4">
                            <h4 class="text-salon">
                                <i class="bi bi-person-badge"></i> Choose Your Stylist
                            </h4>
                            <p class="text-muted">Select the stylist you'd like to serve you</p>
                        </div>
                        
                        <div class="mb-4">
                            <label for="assigned_staff_id" class="form-label fw-bold">
                                <i class="bi bi-scissors"></i> Select Stylist *
                            </label>
                            <select class="form-select form-select-lg" name="assigned_staff_id" id="assigned_staff_id" required>
                                <option value="">-- Loading stylists... --</option>
                            </select>
                            <div class="form-text">
                                <i class="bi bi-info-circle"></i> 
                                Only stylists available on your selected date are shown
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-lightbulb"></i>
                            <strong>Note:</strong> If no stylists are available on your selected date, please go back and choose a different date.
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary btn-lg" onclick="prevStep(2)">
                                <i class="bi bi-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-salon btn-lg" onclick="nextStep(4)">
                                Next Step <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
      </div>

                    <!-- Step 4: Payment -->
      <div class="step" id="step4">
                        <div class="step-header mb-4">
                            <h4 class="text-salon">
                                <i class="bi bi-credit-card"></i> Payment Information
                            </h4>
                            <p class="text-muted">Secure your booking with a down payment</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card payment-info-card mb-4">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-cash-stack text-success"></i> Down Payment Required
                                        </h6>
                                        <div class="payment-amount">
                                            <span id="downPayment" class="fs-4 fw-bold text-success">₱0.00</span>
                                        </div>
                                        <small class="text-muted">30% of service fee + transport (if applicable)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card payment-methods-card mb-4">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-wallet2 text-info"></i> Payment Methods
                                        </h6>
                                        <div class="payment-methods">
                                            <div class="payment-method">
                                                <i class="bi bi-phone text-primary"></i>
                                                <strong>GCash:&nbsp;</strong>0917-123-4567
                                            </div>
                                            <div class="payment-method">
                                                <i class="bi bi-credit-card text-warning"></i>
                                                <strong>PayMaya:&nbsp;</strong>0917-123-4567
                                            </div>
                                            <div class="payment-method">
                                                <i class="bi bi-bank text-success"></i>
                                                <strong>BDO:&nbsp;</strong>00202191842
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="payment_proof" class="form-label fw-bold">
                                <i class="bi bi-image"></i> Upload Proof of Payment *
                            </label>
                            <input type="file" class="form-control" name="payment_proof" id="payment_proof" accept="image/*" required>
                            <div class="form-text">
                                <i class="bi bi-info-circle"></i> 
                                Upload a screenshot or photo of your payment receipt. Accepted formats: JPG, PNG, GIF
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary btn-lg" onclick="prevStep(3)">
                                <i class="bi bi-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-salon btn-lg" onclick="nextStep(5)">
                                Review Booking <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
      </div>

                    <!-- Step 5: Review & Confirm -->
      <div class="step" id="step5">
                        <div class="step-header mb-4">
                            <h4 class="text-salon">
                                <i class="bi bi-check-circle"></i> Review Your Booking
                            </h4>
                            <p class="text-muted">Please review your appointment details before confirming</p>
                        </div>
                        
                        <div class="card review-card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-clipboard-check"></i> Booking Summary
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="review-item">
                                            <div class="review-label">
                                                <i class="bi bi-scissors text-salon"></i> Service
                                            </div>
                                            <div class="review-value" id="reviewService">-</div>
                                        </div>
                                        
                                        <div class="review-item">
                                            <div class="review-label">
                                                <i class="bi bi-calendar text-salon"></i> Date
                                            </div>
                                            <div class="review-value" id="reviewDate">-</div>
                                        </div>
                                        
                                        <div class="review-item">
                                            <div class="review-label">
                                                <i class="bi bi-clock text-salon"></i> Time
                                            </div>
                                            <div class="review-value" id="reviewTime">-</div>
                                        </div>
                                        
                                        <div class="review-item">
                                            <div class="review-label">
                                                <i class="bi bi-palette text-salon"></i> Style
                                            </div>
                                            <div class="review-value" id="reviewStyle">-</div>
                                        </div>
                                        
                                        <div class="review-item">
                                            <div class="review-label">
                                                <i class="bi bi-person-badge text-salon"></i> Stylist
                                            </div>
                                            <div class="review-value" id="reviewStylist">-</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="review-item">
                                            <div class="review-label">
                                                <i class="bi bi-geo-alt text-salon"></i> Type
                                            </div>
                                            <div class="review-value" id="reviewType">-</div>
                                        </div>
                                        
                                        <div class="review-item">
                                            <div class="review-label">
                                                <i class="bi bi-map text-salon"></i> Address
                                            </div>
                                            <div class="review-value" id="reviewAddress">-</div>
                                        </div>
                                        
                                        <div class="review-item">
                                            <div class="review-label">
                                                <i class="bi bi-cash text-salon"></i> Down Payment
                                            </div>
                                            <div class="review-value fw-bold text-success" id="reviewDownPayment">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>What happens next?</strong><br>
                            After confirming your booking, you'll receive a booking reference number. Our admin will verify your payment and confirm your appointment within 24 hours.
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary btn-lg" onclick="prevStep(4)">
                                <i class="bi bi-arrow-left"></i> Back
                            </button>
                            <button type="submit" class="btn btn-salon btn-lg">
                                <i class="bi bi-check-circle"></i> Confirm Booking
                            </button>
        </div>
      </div>
    </form>
    <?php endif; ?>
  </div>
    </div>
</div>

<?php include 'inc/footer_sidebar.php'; ?>
