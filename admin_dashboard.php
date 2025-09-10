<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - Glowtime</title>
  <style>
    body {
      margin: 0;
      font-family: "Segoe UI", sans-serif;
      display: flex;
      background: #faf5ff;
    }
    .sidebar {
      width: 250px;
      background: linear-gradient(180deg, #ec4899, #a78bfa);
      color: white;
      height: 100vh;
      position: fixed;
      top: 0; left: 0;
      padding-top: 20px;
    }
    .sidebar h2 {
      text-align: center;
      margin-bottom: 20px;
    }
    .sidebar a {
      display: block;
      padding: 15px 20px;
      color: white;
      text-decoration: none;
      transition: background 0.3s ease;
    }
    .sidebar a:hover, .sidebar a.active {
      background: rgba(255,255,255,0.2);
    }
    .content {
      margin-left: 250px;
      padding: 20px;
      width: 100%;
    }
    iframe {
      width: 100%;
      height: calc(100vh - 40px);
      border: none;
      border-radius: 10px;
      background: white;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      animation: fadeIn 0.5s ease-in-out;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <h2>🌸 Glowtime</h2>
    <a href="dashboard.php" target="contentFrame" class="active">🏠 Dashboard</a>
    <a href="appointments.php" target="contentFrame">📅 Appointments</a>
    <a href="services.php" target="contentFrame">💇 Services</a>
    <a href="reports.php" target="contentFrame">📊 Reports</a>
    <a href="logout.php">🚪 Logout</a>
  </div>

  <div class="content">
    <iframe name="contentFrame" src="dashboard.php"></iframe>
  </div>
</body>
</html>
