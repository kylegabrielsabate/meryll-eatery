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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
    <a href="admin.php" class="logo">
        <i class="bi bi-building-fill logo-icon"></i>
        <div>
            <div>Meryll Eatery</div>
            <small style="font-size:0.7rem;opacity:0.8">Admin Panel</small>
        </div>
    </a>
    
    <nav class="nav-menu">
        <a href="admin.php" class="nav-link active">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        <a href="admin_inventory.php" class="nav-link">
            <i class="bi bi-box-seam"></i>
            <span>Inventory</span>
        </a>
        <a href="admin_orders.php" class="nav-link">
            <i class="bi bi-receipt"></i>
            <span>Orders</span>
        </a>
        <a href="admin_report.php" class="nav-link">
            <i class="bi bi-bar-chart-line"></i>
            <span>Reports</span>
        </a>
        <a href="get_order_details.php" class="nav-link">
            <i class="bi bi-file-earmark-text"></i>
            <span>Order Details</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="../user/logout.php" class="nav-link" style="color:#ef4444">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Main Content -->
<main class="main-content">
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>Dashboard Overview</h1>
                <p><?= date('l, F j, Y') ?></p>
            </div>
            <div class="header-right" style="display:flex;align-items:center;gap:20px">
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
                            <a href="#" class="mark-read-btn">Mark all read</a>
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
                
                <div class="user-profile" style="display:flex;align-items:center;gap:10px">
                    <div class="user-avatar" style="width:40px;height:40px;background:#fef2f2;color:#ef4444;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div>
                        <div class="user-name" style="font-weight:600;font-size:0.9rem"><?= htmlspecialchars($admin_name) ?></div>
                        <div class="user-role" style="font-size:0.75rem;color:#64748b">Administrator</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="welcome-card" style="background:#fff;padding:2rem;border-radius:16px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);margin-bottom:2rem;display:flex;justify-content:space-between;align-items:center">
            <div class="welcome-text">
                <h2 style="font-size:1.5rem;margin-bottom:0.5rem">Welcome back, <?= htmlspecialchars($admin_name) ?>! 👋</h2>
                <p style="color:#64748b">Here is an overview of your store today. New online orders will pop up here in real-time.</p>
            </div>
            <div class="welcome-icon" style="font-size:4rem;color:#fef2f2">
                <i class="bi bi-shop"></i>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="bi bi-receipt"></i>
                </div>
                <div class="stat-info">
                    <h3>Pending Orders</h3>
                    <p class="stat-number">0</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3>Completed Today</h3>
                    <p class="stat-number">0</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="bi bi-cash-coin"></i>
                </div>
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