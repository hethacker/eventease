<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth.php';
$currentUser = getLoggedInUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventEase - College Event Registration & Check-In</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../assets/css/style.css' : 'assets/css/style.css'; ?>">
</head>
<body>

    <!-- Header / Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../index.php' : 'index.php'; ?>">
                <i class="bi bi-ticket-perforated-fill text-primary fs-3"></i>
                <span>EventEase</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage == 'index.php') ? 'active' : ''; ?>" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../index.php' : 'index.php'; ?>">
                            <i class="bi bi-house-door me-1"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage == 'events.php') ? 'active' : ''; ?>" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../events.php' : 'events.php'; ?>">
                            <i class="bi bi-calendar-event me-1"></i> Explore Events
                        </a>
                    </li>
                    <?php if (isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($currentPage == 'my-events.php') ? 'active' : ''; ?>" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../my-events.php' : 'my-events.php'; ?>">
                                <i class="bi bi-qr-code-scan me-1"></i> My Passes &amp; Certificates
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-warning font-semibold" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-shield-lock me-1"></i> Admin Panel
                            </a>
                            <ul class="dropdown-menu dropdown-menu-dark shadow" aria-labelledby="adminDropdown">
                                <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? 'dashboard.php' : 'admin/dashboard.php'; ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? 'students.php' : 'admin/students.php'; ?>"><i class="bi bi-people-fill me-2"></i>Registered Students</a></li>
                                <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? 'events.php' : 'admin/events.php'; ?>"><i class="bi bi-calendar-plus me-2"></i>Manage Events</a></li>
                                <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? 'registrations.php' : 'admin/registrations.php'; ?>"><i class="bi bi-ticket-perforated me-2"></i>Event Passes & Registrations</a></li>
                                <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? 'checkin.php' : 'admin/checkin.php'; ?>"><i class="bi bi-qr-code me-2"></i>QR Code Check-In</a></li>
                                <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? 'attendance.php' : 'admin/attendance.php'; ?>"><i class="bi bi-bar-chart-line me-2"></i>Attendance Reports</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? 'winners.php' : 'admin/winners.php'; ?>"><i class="bi bi-trophy-fill me-2 text-warning"></i>Winners & Certificates</a></li>
                                <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? 'cert-records.php' : 'admin/cert-records.php'; ?>"><i class="bi bi-award me-2 text-success"></i>Certificate Records</a></li>
                                <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? 'wa-logs.php' : 'admin/wa-logs.php'; ?>"><i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp Logs</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex align-items-center gap-2">
                    <?php if (isLoggedIn()): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle rounded-pill px-3 py-1 text-truncate" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="max-width: 220px;">
                                <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark shadow">
                                <li>
                                    <div class="px-3 py-2 small text-muted border-bottom border-secondary">
                                        Role: <strong class="text-uppercase text-light"><?php echo htmlspecialchars($_SESSION['user_role']); ?></strong>
                                    </div>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../profile.php' : 'profile.php'; ?>">
                                        <i class="bi bi-person me-2"></i> My Profile
                                    </a>
                                </li>
                                <?php if ($_SESSION['user_role'] === 'student'): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../my-events.php' : 'my-events.php'; ?>">
                                        <i class="bi bi-ticket-detailed me-2"></i> My Passes
                                    </a>
                                </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../logout.php' : 'logout.php'; ?>">
                                        <i class="bi bi-box-arrow-right me-2"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../login.php' : 'login.php'; ?>" class="btn btn-outline-custom btn-sm">Login</a>
                        <a href="<?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../register.php' : 'register.php'; ?>" class="btn btn-gradient-primary btn-sm">Register Student</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Global Flash Notification -->
    <div class="container">
        <?php displayFlash(); ?>
    </div>

    <!-- Main Content Container -->
    <div class="main-wrapper">
