<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Glowtime System</title>
  <style>
    body {
      margin:0; padding:0;
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(135deg, #ffe6f0, #e6f7ff);
      color:#333;
      animation: fadeIn 1s ease-in-out;
    }
    header {
      background:#e75480;
      padding:15px 25px;
      color:#fff;
      display:flex;
      justify-content:space-between;
      align-items:center;
      box-shadow:0 3px 8px rgba(0,0,0,0.1);
    }
    header h1 { margin:0; font-size:20px; }
    nav a {
      color:#fff;
      text-decoration:none;
      margin-left:15px;
      font-weight:bold;
      transition:opacity 0.3s;
    }
    nav a:hover { opacity:0.8; }
    .container {
      margin:20px auto;
      max-width:800px;
      padding:20px;
    }
    .card {
      background:#fff;
      padding:20px;
      margin-bottom:20px;
      border-radius:16px;
      box-shadow:0 6px 20px rgba(0,0,0,0.1);
      animation: slideUp 0.6s ease-out;
      transition:transform 0.2s, box-shadow 0.3s;
    }
    .card:hover {
      transform:translateY(-3px);
      box-shadow:0 10px 25px rgba(0,0,0,0.15);
    }
    button {
      padding:10px 14px;
      cursor:pointer;
      border:none;
      border-radius:8px;
      background:#e75480;
      color:#fff;
      font-weight:bold;
      transition:background 0.3s, transform 0.2s;
    }
    button:hover { background:#d14672; transform:scale(1.05); }
    input, select {
      width:100%;
      padding:10px;
      margin:6px 0;
      border:1px solid #ddd;
      border-radius:8px;
      outline:none;
      transition:0.3s;
    }
    input:focus, select:focus {
      border-color:#e75480;
      box-shadow:0 0 6px rgba(231,84,128,0.4);
    }
    .success {
      padding:12px;
      background:#e6ffed;
      border:1px solid #c3e6cb;
      color:#155724;
      border-radius:8px;
      margin-bottom:15px;
    }
    /* Chatbot styles */
    #chatLog {
      height:250px; overflow-y:auto;
      border:1px solid #ddd;
      padding:10px; background:#fafafa;
      border-radius:10px; font-size:14px;
    }
    .chat-bubble {
      margin:8px 0; padding:10px 14px;
      border-radius:18px; max-width:78%;
      clear:both; animation: fadeIn 0.4s ease-in;
    }
    .you { background:#f8d7da; color:#721c24; float:right; text-align:right; }
    .bot { background:#d4edda; color:#155724; float:left; text-align:left; }
    @keyframes fadeIn { from{opacity:0;} to{opacity:1;} }
    @keyframes slideUp { from{transform:translateY(40px);opacity:0;} to{transform:translateY(0);opacity:1;} }
  </style>
</head>
<body>
<header>
  <h1>🌸 Glowtime</h1>
  <nav>
    <?php if(isset($_SESSION['user_id'])): ?>
      <?php if($_SESSION['role']==='admin'): ?>
        <a href="admin_dashboard.php">Admin Dashboard</a>
      <?php else: ?>
        <a href="client_dashboard.php">Dashboard</a>
      <?php endif; ?>
      <a href="logout.php">Logout</a>
    <?php else: ?>
      <a href="login.php">Login</a>
      <a href="register.php">Register</a>
    <?php endif; ?>
  </nav>
</header>
<div class="container">
