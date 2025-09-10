<?php
require_once 'inc/bootstrap.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Client Dashboard - Glowtime</title>
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
    <a href="client_home.php" target="contentFrame" class="active">🏠 Dashboard</a>
    <a href="client_book.php" target="contentFrame">📅 Book Appointment</a>
    <a href="client_history.php" target="contentFrame">🗂️ My Appointments</a>
    <a href="client_ai.php" target="contentFrame">🤖 AI Assistant</a>
    <a href="logout.php">🚪 Logout</a>
  </div>

  <div class="content">
    <iframe name="contentFrame" src="client_home.php"></iframe>
  </div>
</body>
</html>
