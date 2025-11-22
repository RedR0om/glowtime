<?php
require_once 'inc/bootstrap.php';

// No login required - public invoice page
$bookingRef = $_GET['ref'] ?? '';

if (empty($bookingRef)) {
    die('Invalid booking reference.');
}

// Fetch appointment details with all related information
try {
    $stmt = pdo()->prepare("
        SELECT 
            a.*,
            s.name AS service_name,
            s.price AS service_price,
            s.duration_minutes,
            u.name AS client_name,
            u.email AS client_email,
            u.phone AS client_phone,
            st.staff_name AS stylist_name
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN users u ON a.client_id = u.id
        LEFT JOIN staff st ON a.assigned_staff_id COLLATE utf8mb4_unicode_ci = st.staff_id COLLATE utf8mb4_unicode_ci
        WHERE a.booking_ref = ?
        LIMIT 1
    ");
    $stmt->execute([$bookingRef]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        die('Booking not found.');
    }
} catch (PDOException $e) {
    die('Error loading booking details.');
}

// Helper function for HTML escaping
function h($v) { 
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); 
}

// Format dates
$appointmentDate = date("F d, Y", strtotime($appointment['start_at']));
$appointmentTime = date("h:i A", strtotime($appointment['start_at']));
$appointmentEndTime = date("h:i A", strtotime($appointment['end_at']));
$bookingDate = date("F d, Y", strtotime($appointment['created_at'] ?? 'now'));

// Status badges
$paymentStatus = $appointment['payment_status'] ?? 'pending';
$appointmentStatus = $appointment['status'] ?? 'pending';

$paymentStatusClass = 'secondary';
switch($paymentStatus) {
    case 'verified':
        $paymentStatusClass = 'success';
        break;
    case 'rejected':
        $paymentStatusClass = 'danger';
        break;
    case 'pending':
        $paymentStatusClass = 'warning';
        break;
}

$appointmentStatusClass = 'secondary';
switch($appointmentStatus) {
    case 'confirmed':
        $appointmentStatusClass = 'success';
        break;
    case 'cancelled':
        $appointmentStatusClass = 'danger';
        break;
    case 'completed':
        $appointmentStatusClass = 'info';
        break;
    case 'pending':
        $appointmentStatusClass = 'warning';
        break;
}

// Calculate totals
$servicePrice = (float)($appointment['service_price'] ?? 0);
$transportFee = (float)($appointment['transport_fee'] ?? 0);
$downPayment = (float)($appointment['down_payment'] ?? 0);
$totalAmount = $servicePrice + $transportFee;
$remainingBalance = $totalAmount - $downPayment;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Invoice - <?= h($bookingRef) ?> | Glowtime Salon</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .invoice-container {
            max-width: 900px;
            margin: 2rem auto;
            background: #fef7f7;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15), 0 0 0 1px rgba(233, 30, 99, 0.1);
            border-radius: 16px;
            overflow: hidden;
            position: relative;
        }
        
        /* Invoice texture background using image */
        .invoice-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('images/invoice.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            z-index: 0;
            pointer-events: none;
            opacity: 0.50;
        }
        
        .invoice-container::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            /* Subtle overlay to blend with pink background */
            background: radial-gradient(circle at 20% 30%, rgba(233, 30, 99, 0.03) 0%, transparent 40%),
                        radial-gradient(circle at 80% 70%, rgba(240, 98, 146, 0.03) 0%, transparent 40%);
            z-index: 0;
            pointer-events: none;
        }
        
        /* Decorative flower elements inside card */
        .decorative-flower {
            position: absolute;
            opacity: 0.04;
            z-index: 0;
            pointer-events: none;
            font-size: 180px;
            color: #e91e63;
            filter: blur(0.5px);
        }
        
        .flower-1 {
            top: -40px;
            right: -40px;
            transform: rotate(15deg);
        }
        
        .flower-2 {
            bottom: -40px;
            left: -40px;
            transform: rotate(-15deg);
        }
        
        .flower-3 {
            top: 25%;
            right: 8%;
            font-size: 120px;
            transform: rotate(45deg);
            color: #f06292;
        }
        
        .flower-4 {
            bottom: 15%;
            left: 12%;
            font-size: 100px;
            transform: rotate(-30deg);
            color: #f8bbd9;
        }
        .invoice-header {
            background: linear-gradient(135deg, #e91e63 0%, #f06292 100%);
            color: white;
            padding: 1rem 1.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .invoice-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            animation: float 20s infinite linear;
            opacity: 0.3;
        }
        
        .invoice-header::after {
            content: '🌸';
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 80px;
            opacity: 0.15;
            transform: rotate(25deg);
        }
        
        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(30px, 30px) rotate(360deg); }
        }
        
        .invoice-header h1 {
            position: relative;
            z-index: 1;
        }
        
        .invoice-header p {
            position: relative;
            z-index: 1;
        }
        .invoice-header h1 {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 700;
        }
        .invoice-header p {
            margin: 0.25rem 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .invoice-body {
            padding: 1rem 1.25rem;
            position: relative;
            z-index: 1;
            background: transparent;
        }
        
        .invoice-body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, 
                transparent 0%, 
                #e91e63 20%, 
                #f06292 50%, 
                #4dd0e1 80%, 
                transparent 100%);
            z-index: 1;
        }
        .invoice-section {
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: none;
            position: relative;
            z-index: 1;
        }
        .invoice-section h3 {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #e91e63;
            margin-bottom: 0.375rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
            position: relative;
            padding-left: 1.25rem;
        }
        
        .section-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 24px;
            background: linear-gradient(180deg, #e91e63 0%, #f06292 100%);
            border-radius: 2px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.25rem 0;
            border-bottom: none;
            position: relative;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 500;
            color: #666;
        }
        .info-value {
            font-weight: 600;
            color: #212529;
        }
        .status-badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            opacity: 0.7;
        }
        .pricing-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.375rem;
        }
        .pricing-table td {
            padding: 0.375rem 0.5rem;
            border-bottom: none;
        }
        .pricing-table .label {
            color: #666;
        }
        .pricing-table .amount {
            text-align: right;
            font-weight: 600;
            color: #212529;
        }
        .pricing-table .total-row {
            font-size: 1.1rem;
        }
        .pricing-table .total-row .amount {
            color: #e91e63;
            font-size: 1.2rem;
        }
        
        .invoice-footer {
            background: linear-gradient(180deg, rgba(254, 247, 247, 0.8) 0%, rgba(248, 187, 217, 0.3) 100%);
            padding: 0.75rem 1.25rem;
            text-align: center;
            color: #666;
            font-size: 0.85rem;
            position: relative;
            z-index: 1;
            border-top: 3px solid transparent;
            border-image: linear-gradient(90deg, #e91e63, #f06292, #f8bbd9) 1;
        }
        
        .invoice-footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: repeating-linear-gradient(
                90deg,
                transparent,
                transparent 10px,
                rgba(233, 30, 99, 0.2) 10px,
                rgba(233, 30, 99, 0.2) 20px
            );
        }
        @media print {
            body {
                background: white;
            }
            .invoice-container::before,
            .invoice-container::after {
                display: none;
            }
            .decorative-flower {
                display: none;
            }
            .invoice-container {
                box-shadow: none;
                margin: 0;
            }
            .no-print {
                display: none;
            }
        }
        @media (max-width: 768px) {
            .invoice-container {
                margin: 0;
                border-radius: 0;
            }
            .invoice-container::before {
                background-size: 100% auto;
                background-repeat: repeat-y;
                background-position: top center;
                opacity: 0.50;
            }
            .invoice-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Decorative flowers inside the card -->
        <div class="decorative-flower flower-1">🌸</div>
        <div class="decorative-flower flower-2">🌺</div>
        <div class="decorative-flower flower-3">💐</div>
        <div class="decorative-flower flower-4">🌷</div>
        <!-- Header -->
        <div class="invoice-header">
            <h1><i class="bi bi-flower1"></i> Glowtime Salon</h1>
            <p>Booking Invoice</p>
        </div>
        
        <!-- Body -->
        <div class="invoice-body">
            <!-- Booking Reference & Status -->
            <div class="invoice-section">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div>
                        <h3 class="mb-0">Booking Reference</h3>
                        <p class="text-muted mb-0"><?= h($bookingRef) ?></p>
                    </div>
                    <div class="text-end">
                        <div class="mb-1">
                            <span class="status-badge bg-<?= $paymentStatusClass ?> text-white">
                                <i class="bi bi-credit-card"></i> Payment: <?= ucfirst($paymentStatus) ?>
                            </span>
                        </div>
                        <div>
                            <span class="status-badge bg-<?= $appointmentStatusClass ?> text-white">
                                <i class="bi bi-calendar-check"></i> <?= ucfirst($appointmentStatus) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Client Information -->
            <div class="invoice-section">
                <div class="section-title">
                    <i class="bi bi-person-circle"></i> Client Information
                </div>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?= h($appointment['client_name']) ?></span>
                </div>
                <?php if (!empty($appointment['client_email'])): ?>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?= h($appointment['client_email']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($appointment['client_phone'])): ?>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?= h($appointment['client_phone']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Service Details -->
            <div class="invoice-section">
                <div class="section-title">
                    <i class="bi bi-scissors"></i> Service Details
                </div>
                <div class="info-row">
                    <span class="info-label">Service:</span>
                    <span class="info-value"><?= h($appointment['service_name']) ?></span>
                </div>
                <?php if (!empty($appointment['style'])): ?>
                <div class="info-row">
                    <span class="info-label">Preferred Style:</span>
                    <span class="info-value"><?= h($appointment['style']) ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Duration:</span>
                    <span class="info-value"><?= h($appointment['duration_minutes']) ?> minutes</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Stylist:</span>
                    <span class="info-value">
                        <?= !empty($appointment['stylist_name']) ? h($appointment['stylist_name']) : 'Not Assigned' ?>
                    </span>
                </div>
            </div>
            
            <!-- Appointment Schedule -->
            <div class="invoice-section">
                <div class="section-title">
                    <i class="bi bi-calendar-event"></i> Appointment Schedule
                </div>
                <div class="info-row">
                    <span class="info-label">Date:</span>
                    <span class="info-value"><?= h($appointmentDate) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Time:</span>
                    <span class="info-value"><?= h($appointmentTime) ?> - <?= h($appointmentEndTime) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Booking Type:</span>
                    <span class="info-value">
                        <?php if (($appointment['booking_type'] ?? 'salon') === 'home'): ?>
                            <i class="bi bi-house"></i> Home Service
                        <?php else: ?>
                            <i class="bi bi-building"></i> Salon Visit
                        <?php endif; ?>
                    </span>
                </div>
                <?php if (($appointment['booking_type'] ?? '') === 'home' && !empty($appointment['location_address'])): ?>
                <div class="info-row">
                    <span class="info-label">Address:</span>
                    <span class="info-value"><?= h($appointment['location_address']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Pricing -->
            <div class="invoice-section">
                <div class="section-title">
                    <i class="bi bi-cash-stack"></i> Payment Summary
                </div>
                <table class="pricing-table">
                    <tr>
                        <td class="label">Service Fee:</td>
                        <td class="amount">₱<?= number_format($servicePrice, 2) ?></td>
                    </tr>
                    <?php if ($transportFee > 0): ?>
                    <tr>
                        <td class="label">Transport Fee:</td>
                        <td class="amount">₱<?= number_format($transportFee, 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td class="label"><strong>Total Amount:</strong></td>
                        <td class="amount"><strong>₱<?= number_format($totalAmount, 2) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="label">Down Payment (30%):</td>
                        <td class="amount">₱<?= number_format($downPayment, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Remaining Balance:</td>
                        <td class="amount">₱<?= number_format($remainingBalance, 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="invoice-footer">
            <p class="mb-1"><strong>Booking Date:</strong> <?= h($bookingDate) ?></p>
            <p class="mb-0">Thank you for choosing Glowtime Salon! ✨</p>
            <div class="mt-1 no-print d-flex gap-2 justify-content-center">
                <button onclick="window.print()" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-printer"></i> Print Invoice
                </button>
                <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="appointments.php?search=<?= urlencode($bookingRef) ?>" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-calendar-check"></i> Manage Appointment
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

