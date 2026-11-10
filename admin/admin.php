<?php
session_start();
include '../connect.php';

// ✅ ROLE-BASED SECURITY
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../user/login.php');
    exit();
}

$admin_name = $_SESSION['username'];
$admin_id = $_SESSION['user_id']; 

// Fetch unread notifications count
$stmt = $con->prepare("SELECT COUNT(*) as count FROM notifications WHERE admin_id = ? AND is_read = 0");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['count'];

// Fetch recent notifications for dropdown
$stmt = $con->prepare("SELECT * FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$recent_notifs = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Meryll Eatery</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<!-- ✅ LINK TO EXTERNAL CSS -->
<link rel="stylesheet" href="admin_style.css"> 
</head>
<body>

<!-- ✅ REAL-TIME POPUP NOTIFICATION -->
<div class="notif-popup" id="notifPopup">
    <div class="notif-popup-header">
        <i class="bi bi-bell-fill notif-popup-icon"></i>
        <span class="notif-popup-title">New Order Received!</span>
    </div>
    <div class="notif-popup-msg" id="notifPopupMsg">Checking for new orders...</div>
</div>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-header">
        <i class="bi bi-building-fill"></i> 
        <div>
            <h2>Meryll Eatery</h2>
            <span class="sidebar-subtitle">Admin Panel</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="admin.php" class="nav-item active"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <!-- ✅ UPDATED SIDEBAR LINKS -->
        <a href="admin_inventory.php" class="nav-item"><i class="bi bi-box-seam"></i> Inventory</a>
        <a href="admin_orders.php" class="nav-item"><i class="bi bi-receipt"></i> Orders</a>
        <a href="admin_report.php" class="nav-item"><i class="bi bi-bar-chart-line"></i> Reports</a>
        <a href="get_order_details.php" class="nav-item"><i class="bi bi-file-earmark-text"></i> Order Details</a>
        
        <div class="nav-divider"></div>
        <a href="../user/logout.php" class="nav-item logout-link"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</aside>

<!-- Main Content -->
<main class="main-content">
    <header class="top-header">
        <div class="header-left">
            <h2>Dashboard Overview</h2>
            <p class="header-date"><?= date('l, F j, Y') ?></p>
        </div>
        <div class="header-right">
            <!-- Notification Bell -->
            <div class="notif-bell-container">
                <div class="notif-bell" onclick="toggleNotifDropdown(event)">
                    <i class="bi bi-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="notif-badge"><?= $unread_count ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Dropdown -->
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <span>Notifications</span>
                        <a href="mark_all_read.php" class="mark-read-btn">Mark all read</a>
                    </div>
                    <div class="notif-list">
                        <?php if ($recent_notifs->num_rows > 0): ?>
                            <?php while($notif = $recent_notifs->fetch_assoc()): ?>
                                <div class="notif-item <?= $notif['is_read'] ? '' : 'unread' ?>" onclick="viewNotif(<?= $notif['notification_id'] ?>)">
                                    <div class="notif-item-content">
                                        <div class="notif-item-title"><?= htmlspecialchars($notif['title']) ?></div>
                                        <div class="notif-item-msg"><?= htmlspecialchars($notif['message']) ?></div>
                                    </div>
                                    <div class="notif-item-time"><?= date('M j, g:i A', strtotime($notif['created_at'])) ?></div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="notif-empty">No notifications yet</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="user-profile">
                <div class="user-avatar"><i class="bi bi-person-fill"></i></div>
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($admin_name) ?></span>
                    <span class="user-role">Administrator</span>
                </div>
            </div>
        </div>
    </header>

    <div class="dashboard-content">
        <div class="welcome-card">
            <div class="welcome-text">
                <h2>Welcome back, <?= htmlspecialchars($admin_name) ?>! 👋</h2>
                <p>Here is an overview of your store today. New online orders will pop up here in real-time.</p>
            </div>
            <div class="welcome-icon">
                <i class="bi bi-shop"></i>
            </div>
        </div>

        <!-- Placeholder for Stats (You can add your stats here later) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-red"><i class="bi bi-receipt"></i></div>
                <div class="stat-info">
                    <h3>Pending Orders</h3>
                    <p class="stat-number">0</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-blue"><i class="bi bi-check-circle"></i></div>
                <div class="stat-info">
                    <h3>Completed Today</h3>
                    <p class="stat-number">0</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-green"><i class="bi bi-cash-coin"></i></div>
                <div class="stat-info">
                    <h3>Total Revenue</h3>
                    <p class="stat-number">₱0.00</p>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Toggle dropdown
function toggleNotifDropdown(e) {
    e.stopPropagation();
    document.getElementById('notifDropdown').classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('notifDropdown');
    const bell = document.querySelector('.notif-bell-container');
    if (!bell.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

function viewNotif(id) {
    // You can link this to your get_order_details.php later
    window.location.href = 'get_order_details.php?notif_id=' + id;
}

// ✅ REAL-TIME POPUP CHECK (Polls every 30 seconds)
function checkNewOrders() {
    fetch('check_new_orders.php')
        .then(r => r.json())
        .then(data => {
            if (data.has_new_order) {
                showPopup(data.message);
            }
        })
        .catch(err => console.log('Polling error:', err));
}

function showPopup(msg) {
    const popup = document.getElementById('notifPopup');
    document.getElementById('notifPopupMsg').textContent = msg;
    popup.classList.add('show');
    
    // Hide after 6 seconds
    setTimeout(() => {
        popup.classList.remove('show');
    }, 6000);
}

// Start polling
setInterval(checkNewOrders, 30000);
checkNewOrders();
</script>

</body>
</html>