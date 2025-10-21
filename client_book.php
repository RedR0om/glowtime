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

                // Upload proof to Cloudinary (optional)
                $proofFile = null;
                if (!empty($_FILES['payment_proof']['name'])) {
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

                    $stmt = pdo()->prepare("INSERT INTO appointments 
                        (booking_ref, client_id, service_id, booking_type, location_address, style, start_at, end_at, down_payment, transport_fee, payment_proof, payment_status, status, assigned_staff_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?)");
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
                        $assigned_staff_id
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
      document.getElementById("selectedServiceInfo").style.display = "block";
      document.getElementById("selectedServiceName").innerText = element.querySelector('.card-title').innerText;
      
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
      document.getElementById("selectedServiceInfo").style.display = "none";
      
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
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 text-salon mb-0">
            <i class="bi bi-calendar-plus"></i> Book Appointment
        </h1>
        <p class="text-muted mb-0">Schedule your salon visit in 5 easy steps</p>
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
                                <i class="bi bi-image"></i> Upload Proof of Payment <span class="text-muted">(Optional)</span>
                            </label>
                            <input type="file" class="form-control" name="payment_proof" id="payment_proof" accept="image/*">
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
