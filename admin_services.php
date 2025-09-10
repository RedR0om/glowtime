<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$success = $error = "";

// Handle Add Service
if (isset($_POST['add_service'])) {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $duration = $_POST['duration'];

    $stmt = pdo()->prepare("INSERT INTO services (name, price, duration_minutes) VALUES (?, ?, ?)");
    $stmt->execute([$name, $price, $duration]);

    // Handle image upload
    $service_id = pdo()->lastInsertId();
    if (!empty($_FILES['image']['name'])) {
        move_uploaded_file($_FILES['image']['tmp_name'], "images/service{$service_id}.jpg");
    }

    $success = "✅ Service added successfully!";
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    pdo()->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
    if (file_exists("images/service{$id}.jpg")) unlink("images/service{$id}.jpg");
    $success = "🗑️ Service deleted successfully!";
}

// Handle Edit
if (isset($_POST['edit_service'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    $duration = $_POST['duration'];

    $stmt = pdo()->prepare("UPDATE services SET name=?, price=?, duration_minutes=? WHERE id=?");
    $stmt->execute([$name, $price, $duration, $id]);

    if (!empty($_FILES['image']['name'])) {
        move_uploaded_file($_FILES['image']['tmp_name'], "images/service{$id}.jpg");
    }

    $success = "✏️ Service updated successfully!";
}

// Fetch all services
$services = pdo()->query("SELECT * FROM services")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Services</title>
  <style>
    body { font-family:"Segoe UI",sans-serif; background:#faf5ff; margin:0; padding:20px; }
    h1 { color:#a21caf; text-align:center; }
    .msg { text-align:center; font-weight:bold; margin:10px auto; padding:10px; border-radius:8px; max-width:600px; }
    .success { background:#d1fae5; color:#065f46; }
    .error { background:#fee2e2; color:#991b1b; }

    .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; margin-top:20px; }
    .card { background:white; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); overflow:hidden; transition:transform 0.2s; }
    .card:hover { transform:translateY(-5px); }
    .card img { width:100%; height:150px; object-fit:cover; }
    .card-body { padding:15px; }
    .card-body h3 { margin:0; color:#a21caf; }
    .card-body p { margin:5px 0; color:#555; }
    .actions { display:flex; gap:10px; margin-top:10px; }
    .btn { padding:8px 12px; border:none; border-radius:8px; cursor:pointer; font-weight:bold; }
    .btn-edit { background:#ec4899; color:white; }
    .btn-edit:hover { background:#db2777; }
    .btn-delete { background:#ef4444; color:white; }
    .btn-delete:hover { background:#b91c1c; }
    .btn-add { background:#a21caf; color:white; display:block; margin:20px auto; padding:12px 20px; border-radius:10px; }
    .btn-add:hover { background:#7e22ce; }

    /* Modal */
    .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; }
    .modal-content { background:white; padding:20px; border-radius:12px; width:400px; box-shadow:0 6px 15px rgba(0,0,0,0.2); }
    .modal-content h2 { margin-top:0; color:#a21caf; }
    .modal-content label { display:block; margin-top:10px; font-weight:bold; }
    .modal-content input { width:100%; padding:8px; margin-top:5px; border:1px solid #ccc; border-radius:8px; }
    .modal-content button { margin-top:15px; width:100%; padding:10px; background:#ec4899; color:white; border:none; border-radius:8px; font-weight:bold; cursor:pointer; }
    .modal-content button:hover { background:#db2777; }
  </style>
  <script>
    function openModal(id) {
      document.getElementById(id).style.display = "flex";
    }
    function closeModal(id) {
      document.getElementById(id).style.display = "none";
    }
  </script>
</head>
<body>
  <h1>💇 Manage Services</h1>

  <?php if ($success): ?>
    <p class="msg success"><?= $success ?></p>
  <?php elseif ($error): ?>
    <p class="msg error"><?= $error ?></p>
  <?php endif; ?>

  <!-- Add Service Button -->
  <button class="btn-add" onclick="openModal('addModal')">➕ Add New Service</button>

  <!-- Services Grid -->
  <div class="grid">
    <?php foreach ($services as $s): ?>
      <div class="card">
        <?php if (file_exists("images/service{$s['id']}.jpg")): ?>
          <img src="images/service<?= $s['id'] ?>.jpg" alt="<?= htmlspecialchars($s['name']) ?>">
        <?php else: ?>
          <img src="images/default.jpg" alt="Service">
        <?php endif; ?>
        <div class="card-body">
          <h3><?= htmlspecialchars($s['name']) ?></h3>
          <p>₱<?= number_format($s['price'],2) ?> • <?= $s['duration_minutes'] ?> mins</p>
          <div class="actions">
            <button class="btn btn-edit" onclick="openModal('editModal<?= $s['id'] ?>')">Edit</button>
            <a href="?delete=<?= $s['id'] ?>" onclick="return confirm('Delete this service?')">
              <button class="btn btn-delete">Delete</button>
            </a>
          </div>
        </div>
      </div>

      <!-- Edit Modal -->
      <div class="modal" id="editModal<?= $s['id'] ?>">
        <div class="modal-content">
          <h2>Edit Service</h2>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <label>Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($s['name']) ?>" required>
            <label>Price</label>
            <input type="number" name="price" step="0.01" value="<?= $s['price'] ?>" required>
            <label>Duration (minutes)</label>
            <input type="number" name="duration" value="<?= $s['duration_minutes'] ?>" required>
            <label>Image (optional)</label>
            <input type="file" name="image" accept="image/*">
            <button type="submit" name="edit_service">Update</button>
          </form>
          <button onclick="closeModal('editModal<?= $s['id'] ?>')">Cancel</button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Add Modal -->
  <div class="modal" id="addModal">
    <div class="modal-content">
      <h2>Add Service</h2>
      <form method="post" enctype="multipart/form-data">
        <label>Name</label>
        <input type="text" name="name" required>
        <label>Price</label>
        <input type="number" name="price" step="0.01" required>
        <label>Duration (minutes)</label>
        <input type="number" name="duration" required>
        <label>Image</label>
        <input type="file" name="image" accept="image/*" required>
        <button type="submit" name="add_service">Add Service</button>
      </form>
      <button onclick="closeModal('addModal')">Cancel</button>
    </div>
  </div>
</body>
</html>
