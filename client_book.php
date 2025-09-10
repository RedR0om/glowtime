<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: login.php");
    exit;
}

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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Book Appointment</title>
  <style>
    body { font-family:"Segoe UI",sans-serif; background:#faf5ff; margin:0; padding:20px; }
    h1 { text-align:center; color:#a21caf; margin-bottom:25px; }
    .container { max-width:800px; margin:0 auto; background:white; border-radius:12px; padding:25px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .msg { padding:12px; border-radius:8px; margin-bottom:15px; text-align:center; }
    .success { background:#d1fae5; color:#065f46; }
    .error { background:#fee2e2; color:#991b1b; }
    .step { display:none; }
    .step.active { display:block; }
    label { font-weight:bold; display:block; margin-top:10px; }
    input, select, textarea { width:100%; padding:10px; margin-top:5px; border:1px solid #ccc; border-radius:8px; }
    button { margin-top:20px; padding:12px; background:#ec4899; color:white; font-weight:bold; border:none; border-radius:10px; cursor:pointer; width:100%; transition:0.3s; }
    button:hover { background:#db2777; }
    .step-indicator { text-align:center; margin-bottom:20px; }
    .step-indicator span { display:inline-block; padding:10px 15px; border-radius:50%; background:#f3e8ff; margin:0 5px; font-weight:bold; }
    .step-indicator .active { background:#a21caf; color:white; }
    .review-box { background:#fce7f3; padding:15px; border-radius:10px; margin-top:15px; }
    .review-box p { margin:8px 0; font-weight:bold; }
    small { display:block; margin-top:5px; }
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
</head>
<body>
  <h1>📅 Secure Booking</h1>
  <div class="container">
    <?php if ($success): ?>
      <p class="msg success"><?= $success ?></p>
    <?php elseif ($error): ?>
      <p class="msg error"><?= $error ?></p>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="post" enctype="multipart/form-data" id="bookingForm">
      <div class="step-indicator">
        <span id="indicator-1" class="active">1</span>
        <span id="indicator-2">2</span>
        <span id="indicator-3">3</span>
        <span id="indicator-4">4</span>
      </div>

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
</body>
</html>
