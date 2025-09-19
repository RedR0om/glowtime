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
</style>
  <script>
    function toggleLocation() {
      const type = document.getElementById("booking_type").value;
      document.getElementById("locationField").style.display = (type === "home") ? "block" : "none";
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

      <!-- Step 1 -->
      <div class="step active" id="step1">
        <label>Choose Service:</label>
        <select name="service_id" id="service" required onchange="updateDownPayment(); loadBookedTimes();">
          <option value="">-- Select Service --</option>
          <?php
          $services = pdo()->query("SELECT * FROM services")->fetchAll();
          foreach ($services as $s):
          ?>
            <option value="<?= $s['id'] ?>" data-price="<?= $s['price'] ?>"><?= htmlspecialchars($s['name']) ?> (₱<?= number_format($s['price'],2) ?>)</option>
          <?php endforeach; ?>
        </select>

        <label>Booking Type:</label>
        <select name="booking_type" id="booking_type" onchange="toggleLocation()" required>
          <option value="salon">Salon Appointment</option>
          <option value="home">Home Service</option>
        </select>

        <div id="locationField" style="display:none;">
          <label>Home Address:</label>
          <textarea name="location_address" rows="3"></textarea>
        </div>

        <button type="button" onclick="nextStep(2)">Next</button>
      </div>

      <!-- Step 2 -->
      <div class="step" id="step2">
        <label>Date:</label>
        <input type="date" name="date" id="date" required min="<?= date('Y-m-d') ?>" onchange="loadBookedTimes()">
        
        <label>Time:</label>
        <input type="time" name="time" id="time" required>
        <small id="timeNotice" style="color:red;display:none;">⚠️ This time is already booked.</small>
        
        <label>Preferred Style (optional):</label>
        <input type="text" name="style" id="style" placeholder="e.g. Bob Cut">
        
        <button type="button" onclick="prevStep(1)">Back</button>
        <button type="button" onclick="nextStep(3)">Next</button>
      </div>

      <!-- Step 3 -->
      <div class="step" id="step3">
        <p><strong>💰 Down Payment:</strong> <span id="downPayment">₱0.00</span></p>
        <p><strong>🏦 Pay via:</strong> GCash (0917-123-4567), PayMaya, or BDO</p>
        <label>Upload Proof of Payment (optional):</label>
        <input type="file" name="payment_proof" id="payment_proof" accept="image/*">
        <button type="button" onclick="prevStep(2)">Back</button>
        <button type="button" onclick="nextStep(4)">Next</button>
      </div>

      <!-- Step 4 -->
      <div class="step" id="step4">
        <h3>Review Your Booking</h3>
        <div class="review-box">
          <p>📌 Service: <span id="reviewService"></span></p>
          <p>📅 Date: <span id="reviewDate"></span></p>
          <p>⏰ Time: <span id="reviewTime"></span></p>
          <p>✂️ Style: <span id="reviewStyle"></span></p>
          <p>🏠 Booking Type: <span id="reviewType"></span></p>
          <p>📍 Address: <span id="reviewAddress"></span></p>
          <p>💰 Down Payment: <span id="reviewDownPayment"></span></p>
        </div>
        <button type="button" onclick="prevStep(3)">Back</button>
        <button type="submit">Confirm Booking</button>
      </div>
    </form>
    <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'inc/footer_sidebar.php'; ?>
