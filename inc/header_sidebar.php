<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Glowtime Salon Management</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom Salon CSS -->
    <link href="css/salon-style.css" rel="stylesheet">
</head>
<body class="sidebar-layout">
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <!-- Brand -->
        <div class="sidebar-brand">
            <h4><i class="bi bi-flower1"></i> Glowtime</h4>
            <p>Salon Management</p>
        </div>
        
        <!-- Navigation -->
        <ul class="sidebar-nav">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <!-- Admin Navigation -->
                    <li class="nav-item">
                        <a href="admin_dashboard.php" class="nav-link <?= $current_page === 'admin_dashboard.php' ? 'active' : '' ?>">
                            <i class="bi bi-speedometer2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="appointments.php" class="nav-link <?= $current_page === 'appointments.php' ? 'active' : '' ?>">
                            <i class="bi bi-calendar-check"></i>
                            Appointments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="services.php" class="nav-link <?= $current_page === 'services.php' ? 'active' : '' ?>">
                            <i class="bi bi-scissors"></i>
                            Services
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link <?= $current_page === 'reports.php' ? 'active' : '' ?>">
                            <i class="bi bi-bar-chart"></i>
                            Reports
                        </a>
                    </li>
                <?php else: ?>
                    <!-- Client Navigation -->
                    <li class="nav-item">
                        <a href="client_dashboard.php" class="nav-link <?= $current_page === 'client_dashboard.php' ? 'active' : '' ?>">
                            <i class="bi bi-house"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="client_book.php" class="nav-link <?= $current_page === 'client_book.php' ? 'active' : '' ?>">
                            <i class="bi bi-calendar-plus"></i>
                            Book Appointment
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="client_history.php" class="nav-link <?= $current_page === 'client_history.php' ? 'active' : '' ?>">
                            <i class="bi bi-clock-history"></i>
                            My History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="client_ai.php" class="nav-link <?= $current_page === 'client_ai.php' ? 'active' : '' ?>">
                            <i class="bi bi-robot"></i>
                            AI Assistant
                        </a>
                    </li>
                <?php endif; ?>
                
                <!-- Divider -->
                <li class="nav-item">
                    <hr style="border-color: rgba(255,255,255,0.1); margin: 1rem 1.5rem;">
                </li>
                
                <!-- User Info -->
                <li class="nav-item">
                    <div class="nav-link" style="cursor: default;">
                        <i class="bi bi-person-circle"></i>
                        <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
                        <small class="d-block text-white-50" style="margin-left: 32px;">
                            <?= ucfirst($_SESSION['role'] ?? 'user') ?>
                        </small>
                    </div>
                </li>
                
                <!-- Logout -->
                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="bi bi-box-arrow-right"></i>
                        Logout
                    </a>
                </li>
            <?php else: ?>
                <!-- Guest Navigation -->
                <li class="nav-item">
                    <a href="login.php" class="nav-link <?= $current_page === 'login.php' ? 'active' : '' ?>">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Login
                    </a>
                </li>
                <li class="nav-item">
                    <a href="register.php" class="nav-link <?= $current_page === 'register.php' ? 'active' : '' ?>">
                        <i class="bi bi-person-plus"></i>
                        Register
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Mobile Header -->
        <div class="mobile-header">
            <button class="btn btn-outline-salon" onclick="toggleSidebar()" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="fw-bold text-salon">
                <i class="bi bi-flower1"></i> Glowtime
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="dropdown">
                    <button class="btn btn-outline-salon btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-person"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><span class="dropdown-item-text">
                            <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
                        </span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Page Content Container -->
        <div class="container-fluid">
