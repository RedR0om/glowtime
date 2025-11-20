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

$paymentStatusClass = match($paymentStatus) {
    'verified' => 'success',
    'rejected' => 'danger',
    'pending' => 'warning',
    default => 'secondary'
};

$appointmentStatusClass = match($appointmentStatus) {
    'confirmed' => 'success',
    'cancelled' => 'danger',
    'completed' => 'info',
    'pending' => 'warning',
    default => 'secondary'
};

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
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 12px;
            overflow: hidden;
        }
        .invoice-header {
            background: linear-gradient(135deg, #e91e63 0%, #f06292 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .invoice-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }
        .invoice-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        .invoice-body {
            padding: 2rem;
        }
        .invoice-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        .invoice-section:last-child {
            border-bottom: none;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #e91e63;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
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
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .pricing-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .pricing-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #e9ecef;
        }
        .pricing-table tr:last-child td {
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
            background: #f8f9fa;
            font-size: 1.1rem;
        }
        .pricing-table .total-row .amount {
            color: #e91e63;
            font-size: 1.2rem;
        }
        .invoice-footer {
            background: #f8f9fa;
            padding: 1.5rem 2rem;
            text-align: center;
            color: #666;
            font-size: 0.9rem;
        }
        @media print {
            body {
                background: white;
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
            .invoice-body {
                padding: 1rem;
            }
            .info-row {
                flex-direction: column;
                gap: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <h1><i class="bi bi-flower1"></i> Glowtime Salon</h1>
            <p>Booking Invoice</p>
        </div>
        
        <!-- Body -->
        <div class="invoice-body">
            <!-- Booking Reference & Status -->
            <div class="invoice-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h3 class="mb-0">Booking Reference</h3>
                        <p class="text-muted mb-0"><?= h($bookingRef) ?></p>
                    </div>
                    <div class="text-end">
                        <div class="mb-2">
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
            <div class="mt-2 no-print">
                <button onclick="window.print()" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-printer"></i> Print Invoice
                </button>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

