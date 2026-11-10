<?php
session_start();
include '../connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../user/login.php');
    exit();
}

$admin_name = $_SESSION['username'];
$admin_id = $_SESSION['user_id'];

// Fetch unread notifications count
$stmt = $con->prepare("SELECT COUNT(*) as count FROM notifications WHERE admin_id = ? AND is_read = 0");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Fetch recent notifications
$stmt = $con->prepare("SELECT * FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$recent_notifs = $stmt->get_result();

// ✅ FETCH REAL STATS FROM DATABASE
$stmt = $con->prepare("SELECT COUNT(*) as count FROM orders WHERE status = 'Pending'");
$stmt->execute();
$pending_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

$stmt = $con->prepare("SELECT COUNT(*) as count FROM orders WHERE status = 'Completed' AND DATE(created_at) = CURDATE()");
$stmt->execute();
$completed_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

$stmt = $con->prepare("SELECT SUM(total_amount) as total FROM orders WHERE status = 'Completed'");
$stmt->execute();
$total_revenue = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Meryll Eatery</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="admin_style.css">
</head>
<body>

<!-- ✅ IDENTICAL SIDEBAR START -->
<aside class="sidebar" id="sidebar">
    <button class="sidebar-close" onclick="closeSidebar()"><i class="fas fa-arrow-left"></i></button>
    <a href="admin.php" class="logo">
        <span class="logo-icon"><i class="fas fa-utensils"></i></span> Meryll Eatery
    </a>
    <ul class="nav-menu">
        <li class="nav-item"><a href="admin.php" class="nav-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li class="nav-item"><a href="admin_orders.php" class="nav-link"><i class="fas fa-clipboard-list"></i> Manage Orders</a></li>
        <li class="nav-item"><a href="get_order_details.php" class="nav-link"><i class="fas fa-file-invoice"></i> Order Details</a></li>
        <li class="nav-item"><a href="admin_inventory.php" class="nav-link"><i class="fas fa-boxes-stacked"></i> Inventory</a></li>
        <li class="nav-item"><a href="admin_report.php" class="nav-link"><i class="fas fa-chart-line"></i> Monthly Report</a></li>
    </ul>
    <div class="sidebar-footer">
        <a href="admin.php" class="nav-link"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($admin_name); ?></a>
        <a href="../user/logout.php" class="nav-link" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>
<!-- ✅ IDENTICAL SIDEBAR END -->

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<main class="main-content">
    <div class="admin-container" style="max-width: 1200px; margin: 0 auto;">
        <div class="admin-header">
            <div>
                <h1>Dashboard Overview</h1>
                <p style="color: var(--admin-text-muted, #64748b);"><?= date('l, F j, Y') ?></p>
            </div>
        </div>

        <!-- Stats Grid with REAL DATABASE DATA -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-receipt"></i></div>
                <div class="stat-info">
                    <h3>Pending Orders</h3>
                    <p class="stat-number"><?= $pending_count ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3>Completed Today</h3>
                    <p class="stat-number"><?= $completed_count ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-cash-register"></i></div>
                <div class="stat-info">
                    <h3>Total Revenue</h3>
                    <p class="stat-number">₱<?= number_format($total_revenue, 2) ?></p>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function closeSidebar() { 
    document.getElementById('sidebar').classList.remove('active'); 
    document.getElementById('sidebarOverlay').classList.remove('active'); 
    document.body.style.overflow = ''; 
}
</script>
</body>
</html>