<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: login.php");
    exit;
}

// Store user name for display
$_SESSION['user_name'] = get_user_name($_SESSION['user_id']);

$success = $error = "";

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id   = $_POST['service_id'] ?? null;
    $style        = trim($_POST['style'] ?? "");
    $date         = $_POST['date'] ?? null;
    $time         = $_POST['time'] ?? null;
    $bookingType  = $_POST['booking_type'] ?? "salon";
    $location     = ($bookingType === "home") ? trim($_POST['location_address'] ?? "") : null;

    if ($service_id && $date && $time) {
        $start_at = date("Y-m-d H:i:s", strtotime("$date $time"));

        // Get service details
        $stmt = pdo()->prepare("SELECT * FROM services WHERE id=?");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch();

        if ($service) {
            $end_at = date("Y-m-d H:i:s", strtotime("+{$service['duration_minutes']} minutes", strtotime($start_at)));

            // ✅ Conflict check (any overlap)
            $check = pdo()->prepare("SELECT COUNT(*) FROM appointments 
                WHERE status IN ('pending','confirmed')
                AND (
                    (start_at < ? AND end_at > ?) 
                    OR (start_at < ? AND end_at > ?) 
                    OR (start_at >= ? AND end_at <= ?)
                )");
            $check->execute([$end_at, $start_at, $start_at, $end_at, $start_at, $end_at]);
            $conflict = $check->fetchColumn();

            if ($conflict > 0) {
                $error = "❌ Sorry, this time slot is already booked. Please choose another.";
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

                // Upload proof (optional)
                $proofFile = null;
                if (!empty($_FILES['payment_proof']['name'])) {
                    $ext = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','gif'];
                    if (in_array($ext, $allowed)) {
                        if (!is_dir("uploads")) mkdir("uploads");
                        $proofFile = "uploads/proof_" . time() . ".$ext";
                        move_uploaded_file($_FILES['payment_proof']['tmp_name'], $proofFile);
                    } else {
                        $error = "❌ Invalid proof file type. Allowed: JPG, PNG, GIF.";
                    }
                }

                if (!$error) {
                    // Generate booking ref
                    $bookingRef = "BOOK-" . date("Ymd") . "-" . rand(100, 999);

                    $stmt = pdo()->prepare("INSERT INTO appointments 
                        (booking_ref, client_id, service_id, booking_type, location_address, style, start_at, end_at, down_payment, transport_fee, payment_proof, payment_status, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')");
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
                        $proofFile
                    ]);

                    $success = "✅ Booking successful! Your reference is <strong>$bookingRef</strong>. Please wait for admin verification.";
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
}
.step-indicator .step-circle.active { 
    background: var(--salon-primary); 
    color:white; 
    border-color: var(--salon-primary);
}
.step-indicator .step-circle.completed { 
    background: #198754; 
    color:white; 
    border-color: #198754;
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
    function selectBookingType(type) {
      // Update hidden select
      document.getElementById("booking_type").value = type;
      
      // Update card selection visual
      document.querySelectorAll('.booking-type-card').forEach(card => {
        card.classList.remove('selected');
      });
      event.currentTarget.classList.add('selected');
      
      // Toggle location field
      toggleLocation();
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
    });

    function nextStep(step) {
      document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
      document.getElementById('step' + step).classList.add('active');
      updateIndicator(step);

      if (step === 4) {
        let serviceSelect = document.getElementById("service");
        let serviceName = serviceSelect.options[serviceSelect.selectedIndex].text;
        document.getElementById("reviewService").innerText = serviceName;
        document.getElementById("reviewDate").innerText = document.getElementById("date").value;
        document.getElementById("reviewTime").innerText = document.getElementById("time").value;
        document.getElementById("reviewStyle").innerText = document.getElementById("style").value || "None";
        document.getElementById("reviewType").innerText = document.getElementById("booking_type").value;
        document.getElementById("reviewAddress").innerText = document.querySelector("[name='location_address']").value || "N/A";
        document.getElementById("reviewDownPayment").innerText = document.getElementById("downPayment").innerText;
      }
    }

    function prevStep(step) {
      document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
      document.getElementById('step' + step).classList.add('active');
      updateIndicator(step);
    }

    function updateIndicator(activeStep) {
      for (let i = 1; i <= 4; i++) {
        document.getElementById('indicator-' + i).classList.remove('active');
      }
      document.getElementById('indicator-' + activeStep).classList.add('active');
    }

    function updateDownPayment() {
      let select = document.getElementById("service");
      let price = select.options[select.selectedIndex]?.getAttribute("data-price");
      if (price) {
        let dp = (price * 0.3).toFixed(2);
        document.getElementById("downPayment").innerText = "₱" + dp + " + transport (if any)";
      } else {
        document.getElementById("downPayment").innerText = "₱0.00";
      }
    }
  </script>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 text-salon mb-0">
            <i class="bi bi-calendar-plus"></i> Book Appointment
        </h1>
        <p class="text-muted mb-0">Schedule your salon visit in 4 easy steps</p>
    </div>
    <div>
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
                            <label for="service" class="form-label fw-bold">
                                <i class="bi bi-list-ul"></i> Select Service *
                            </label>
                            <select class="form-select form-select-lg" name="service_id" id="service" required onchange="updateDownPayment(); loadBookedTimes();">
                                <option value="">-- Choose a Service --</option>
                                <?php
                                $services = pdo()->query("SELECT * FROM services ORDER BY name")->fetchAll();
                                foreach ($services as $s):
                                ?>
                                    <option value="<?= $s['id'] ?>" data-price="<?= $s['price'] ?>" data-duration="<?= $s['duration_minutes'] ?>">
                                        <?= htmlspecialchars($s['name']) ?> - ₱<?= number_format($s['price'],2) ?> (<?= $s['duration_minutes'] ?> mins)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="booking_type" class="form-label fw-bold">
                                <i class="bi bi-geo-alt"></i> Booking Type *
                            </label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card booking-type-card" onclick="selectBookingType('salon')">
                                        <div class="card-body text-center">
                                            <i class="bi bi-building fs-1 text-salon mb-2"></i>
                                            <h6>Salon Visit</h6>
                                            <p class="text-muted small mb-0">Visit our beautiful salon</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card booking-type-card" onclick="selectBookingType('home')">
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

                    <!-- Step 3: Payment -->
                    <div class="step" id="step3">
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
                                                <strong>GCash:</strong> 0917-123-4567
                                            </div>
                                            <div class="payment-method">
                                                <i class="bi bi-credit-card text-warning"></i>
                                                <strong>PayMaya:</strong> Available
                                            </div>
                                            <div class="payment-method">
                                                <i class="bi bi-bank text-success"></i>
                                                <strong>BDO:</strong> Bank Transfer
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="payment_proof" class="form-label fw-bold">
                                <i class="bi bi-image"></i> Upload Proof of Payment <span class="text-muted">(Optional)</span>
                            </label>
                            <input type="file" class="form-control" name="payment_proof" id="payment_proof" accept="image/*">
                            <div class="form-text">
                                <i class="bi bi-info-circle"></i> 
                                Upload a screenshot or photo of your payment receipt. Accepted formats: JPG, PNG, GIF
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary btn-lg" onclick="prevStep(2)">
                                <i class="bi bi-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-salon btn-lg" onclick="nextStep(4)">
                                Review Booking <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 4: Review & Confirm -->
                    <div class="step" id="step4">
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
                            <button type="button" class="btn btn-outline-secondary btn-lg" onclick="prevStep(3)">
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
